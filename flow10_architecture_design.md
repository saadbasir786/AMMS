# AMMS FLOW-10 Architecture Design

## Frozen boundary

FLOW-10 is additive to the frozen FLOW-01 through FLOW-09 implementation. It does not alter MeetingRequest flow types, Meeting status, Meeting identity, Schedule Version semantics, Participant Version semantics, terminal credential authentication, or the ordinary FLOW-09 manifest. The only new meeting-side authoritative input is the immutable `participant_version_id` already bound to an active FLOW-09 terminal session.

## Restricted biometric persistence

| Table | Purpose | Sensitive-data rule |
|---|---|---|
| `employee_biometric_templates` | Immutable fingerprint template versions | Stores reusable template bytes only; no fingerprint image, raw sensor frame, or probe-template column. |
| `biometric_enrollment_sessions` | Human-authorized terminal enrollment mode | Stores employee/terminal/operator/state/TTL metadata; never stores capture bytes. |
| `meeting_attendance_records` | Immutable check-in records | Stores identity/audit metadata only; never stores template bytes, images, or probe data. |

`employee_biometric_templates` will use a binary BLOB column for `template_data`, SHA-256 `template_checksum`, explicit canonical `finger_position`, vendor/format/algorithm metadata, `version_number`, lifecycle status, source terminal, and enrollment/revocation actors. Template rows are immutable. Re-enrollment creates a new version and changes the former active row to `SUPERSEDED`; revocation changes an active row to `REVOKED` with a mandatory reason. A transaction-level employee/finger lock prevents two active versions for the same employee/finger.

`biometric_enrollment_sessions` will enforce one active enrollment per employee and, when a terminal is selected, one active enrollment per terminal. Its `terminal_id` is nullable to support the device-independent foundation while retaining the future terminal workflow contract. Its status lifecycle is `PENDING_CAPTURE`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, and `EXPIRED`; expiration is configuration-driven. An active FLOW-09 meeting session and an active enrollment session on the same terminal are mutually exclusive and produce `TERMINAL_BUSY`. Completed, cancelled, and expired sessions are immutable history. Each template may retain its originating `enrollment_session_id` for traceability without exposing bytes to human APIs.

`meeting_attendance_records` will have a unique `(meeting_id, employee_id)` constraint and an idempotency key `client_event_uuid`. It stores `FINGERPRINT` or `MANUAL_OVERRIDE`, server-recorded time, optional device time, bound participant version, terminal/session, candidate checksum, and sanitized matcher metadata. It is append-only through normal APIs.

## Human authorization

New permissions are additive: `biometric.status.view`, `biometric.enroll`, `biometric.reenroll`, `biometric.revoke`, `biometric.enrollment.cancel`, `attendance.view`, and `attendance.override`. Human operations use the existing Policy → Action pattern with active-user, permission, organizational scope, employee visibility, terminal availability, Meeting visibility, and originating-flow governance checks. SYSTEM_ADMIN infrastructure authority does not automatically grant enrollment or attendance override ownership.

## Terminal authentication and routes

Biometric device routes remain separate from human Sanctum routes and reuse FLOW-09 middleware. The ordinary manifest remains non-biometric. New session-scoped routes are:

- `GET /api/terminal/v1/sessions/{session}/biometric-candidates`
- `POST /api/terminal/v1/sessions/{session}/attendance/fingerprint`

Enrollment routes are terminal-credential scoped but require a human-created enrollment session and exact terminal ownership:

- `GET /api/terminal/v1/enrollment/current`
- `POST /api/terminal/v1/enrollment/{session}/template`
- `POST /api/terminal/v1/enrollment/{session}/complete`
- `POST /api/terminal/v1/enrollment/{session}/cancel`

Human routes create and manage enrollment sessions, list safe biometric metadata, revoke templates, and view attendance; no human route returns `template_data`.

## Candidate set contract

Candidate sets are built only from `terminal_meeting_sessions.participant_version_id`. For each exact participant-version member, only active templates are included. Unenrolled participants remain in official participant counts but are absent from candidate bytes and are reported separately as `biometric_ready=false`. Candidate checksums are deterministic over employee/template UUID/version identities and template checksums, while the actual package sent to a terminal includes only the exact candidate template bytes and minimal matching metadata. No email, phone, RBAC, password, salary, or unrelated HR data is included.

The candidate builder never mutates the ordinary FLOW-09 manifest. Session freshness, terminal ownership, terminal status, expiry, assignment validity, and current Schedule/Participant Version checks are enforced before candidates are returned. Candidate packages are not persisted centrally as a separate cache and are expected to be discarded by the terminal client on close, expiry, revocation, assignment revoke, or staleness.

## Attendance validation

`RecordFingerprintAttendance` validates the active session, terminal ownership, freshness, bound Meeting, exact Participant Version membership, active matched template ownership, candidate checksum, and `(meeting_id, employee_id)` uniqueness before insertion. The terminal's local matcher may decide the biometric match, but the backend does not trust arbitrary employee IDs or template UUIDs. Duplicate scans return `ATTENDANCE_ALREADY_RECORDED`; repeated `client_event_uuid` requests are idempotent.

`RecordManualAttendance` is human-authenticated, requires `attendance.override`, governance ownership, current participant membership, and a non-empty reason. It records `MANUAL_OVERRIDE` and never emits biometric matcher metadata. Nonparticipants cannot be manually marked present; FLOW-08 must add them first, followed by FLOW-09 refresh/re-activation.

## Device-independent adapter boundary

The software depends on `FingerprintDeviceAdapter`, not vendor classes. FLOW-10A provides a clearly labeled `FakeFingerprintAdapter`/simulator for deterministic contract verification. It supports synthetic match, no-match, low-score, device-unavailable, and capture-cancelled outcomes only in local/testing/verification environments. Production configuration must fail explicitly when no real driver is configured; it must never silently fall back to the fake adapter. `DermalogFingerprintAdapter`, real SVT3000 capture, SDK discovery, template-format discovery, quality thresholds, and local 1:N hardware matching are deferred to FLOW-10B. The final FLOW-10A report may become `VERIFICATION COMPLETE / PENDING EXPLICIT USER APPROVAL`, while recording `FLOW-10B DEFERRED / WAITING FOR PHYSICAL DEVICE`.
