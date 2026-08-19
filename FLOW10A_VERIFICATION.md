# AMMS FLOW-10A Verification — Strict Closure

**Verification date:** 20 August 2026 GMT+4:30
**Status:** **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**
**FLOW-10B:** **DEFERRED / WAITING FOR SVT3000 AND LAWFUL DERMALOG SDK ACCESS**
**FLOW-11:** **Not started**

## Executive result

FLOW-10A is implemented as an additive, device-independent software foundation over frozen FLOW-01 through FLOW-09. The strict-closure pass addressed the four findings raised by the reviewer: the terminal-only candidate contract now returns the active template bytes required for local 1:N matching; an independent `terminal-android/` foundation exists with Enrollment Mode, Meeting Mode, deterministic `FakeFingerprintAdapter`, attendance submission, session close, and memory clearing; real Laravel Action-level MariaDB/InnoDB races were executed; and the verification artifacts were regenerated around one authoritative regression/build run.

No claim is made for physical SVT3000 capture, DERMALOG SDK behavior, real template extraction, vendor quality thresholds, physical local matching, or hardware attendance. Those are FLOW-10B boundaries and remain explicitly deferred.

## Corrected candidate-set privacy contract

The previous privacy correction was too broad for the agreed architecture. The correct split is now enforced and tested:

| Boundary | `template_data` bytes | Scope |
|---|---:|---|
| React/human API resources | Never | Human permissions and safe metadata only. |
| Ordinary FLOW-09 manifest | Never | The frozen manifest remains unchanged and non-biometric. |
| Session-authenticated terminal biometric-candidates endpoint | Yes | Only ACTIVE templates for the exact bound Participant Version of the authenticated active terminal session. |
| Audit metadata and attendance rows | Never | Checksums, identifiers, scores, and sanitized metadata only. |

The terminal endpoint is the only delivery boundary for local 1:N matching. The endpoint performs terminal-session authentication, session freshness checks, exact Participant Version binding, ACTIVE-template filtering, and checksum construction. The terminal client must clear candidate bytes on close, expiry, stale-version response, revocation, assignment revoke, and sign-out.

## Implemented software foundation

| Area | Result |
|---|---|
| Additive persistence | `employee_biometric_templates`, `biometric_enrollment_sessions`, and `meeting_attendance_records` are additive migrations. |
| Template lifecycle | Immutable versions, ACTIVE/SUPERSEDED/REVOKED states, SHA-256 checksums, explicit re-enrollment, and reason-required revocation. |
| Enrollment governance | Scoped permissions, `BIOMETRIC_ENROLLMENT_OFFICER`, explicit Gate/Policy registration, TTL, minimum active fingers, terminal ownership, and `TERMINAL_BUSY` protection. |
| Terminal enrollment | `GET /api/terminal/v1/enrollment/current` retrieves the current safe task; `POST /api/terminal/v1/enrollment/{enrollment}/template` accepts template payloads only and rejects raw images/probes. |
| Candidate set | `GET /api/terminal/v1/sessions/{session}/biometric-candidates` returns exact Participant-Version candidates including template bytes only at the terminal boundary. |
| Attendance | Server validates session freshness, participant membership, template ownership, organization, candidate checksum, uniqueness, idempotency, revocation/supersession, and server time. |
| Manual fallback | Human-only `MANUAL_OVERRIDE`, mandatory reason, governance ownership, current participant membership, and immutable records. |
| Android foundation | `terminal-android/` includes Kotlin workflow/API seams, Persian RTL activity shell, Enrollment Mode, Meeting Mode, local fake identification, attendance, close, and memory clearing. |
| Hardware boundary | No DERMALOG class, SDK, physical device assumption, or silent fake-driver fallback is present. FLOW-10B remains deferred. |

## Independent Android foundation evidence

The sandbox has Java 21 after installing the JVM compiler, but it has no Android SDK, Gradle executable, ADB device, or legitimate DERMALOG SDK. Therefore the Android module’s vendor-independent core was compiled and executed as a JVM contract rather than falsely reported as an APK/device build.

```text
FLOW10A_ANDROID_CORE_PASS vectors=8
FLOW10A_ANDROID_FOUNDATION_PASS files=5
```

The tested vectors cover synthetic MATCH, NO_MATCH, LOW_SCORE, UNAVAILABLE, and CANCELLED outcomes, Meeting Mode state, Enrollment Mode state, and zero candidate bytes after `closeAndClear()`. The source-level workflow explicitly covers terminal credential login, current assignment retrieval, FLOW-09 activation, candidate-byte download, local identification, fingerprint attendance submission, session close, enrollment task retrieval, and template submission.

## Action-level MariaDB/InnoDB race evidence

The dedicated `amms_concurrency_test` database was rebuilt from the complete Laravel migration set. During this work, several pre-existing MySQL identifier-length defects in FLOW-02 through FLOW-09 migrations were corrected with unique short constraint/index names; schema semantics were not changed. The application-level probe invokes actual Laravel Actions and uses separate forked MariaDB connections.

The final probe output is stored in `flow10a_action_race_probe.json` and covers all requested races:

| Race | Result |
|---|---|
| Concurrent enrollment for same employee | One `ENROLLMENT_COMMITTED`, one `BIOMETRIC_ENROLLMENT_ALREADY_ACTIVE` (422), one active enrollment session. |
| Two terminal attendance submissions | One `ATTENDANCE_COMMITTED`, one `ATTENDANCE_ALREADY_RECORDED` (409), one final attendance row. |
| Re-enrollment versus revocation | Re-enrollment commits; competing revocation observes the locked superseded state and returns `BIOMETRIC_TEMPLATE_REVOKED` (422); one active replacement row remains. |
| FLOW-07 schedule mutation versus attendance | Schedule mutation commits; attendance returns `TERMINAL_SESSION_STALE` (422); the session is stale against the new schedule version. |
| FLOW-08 participant mutation versus attendance | Participant mutation commits; attendance returns `TERMINAL_SESSION_STALE` (422); the session is stale against the new Participant Version. |
| Template revocation versus attendance | Revocation commits; attendance returns `BIOMETRIC_TEMPLATE_REVOKED` (422); final template status is REVOKED. |

This is Action-level evidence, not only a uniqueness-table probe. The official cleanup script then closes terminal sessions and cancels enrollment sessions through their domain Actions before emitting the final invariant artifact.

## Definitive software regression/build evidence

The following results are from one authoritative run after the strict-closure fixes, with logs preserved as `definitive_android_core.log`, `definitive_feature_regression.log`, and `definitive_frontend_build.log`:

```text
FLOW10A_ANDROID_CORE_PASS vectors=8
FLOW10A_ANDROID_FOUNDATION_PASS files=5
Feature suite: 316 tests passed (1,154 assertions)
Frontend build: 1,862 modules transformed; build successful
```

The frontend emitted only the existing chunk-size advisory; the production build completed successfully. These are the authoritative release counts; earlier counts are historical and superseded.

## Negative Matrix A–P

The standalone matrix is the authoritative case-by-case log. Cases A–O have dedicated Feature or Action-level evidence. F and M now have both application-level MariaDB evidence and their existing database-level uniqueness evidence. P is closed by the final regression/build logs and the post-cleanup MariaDB invariant artifact below.

## Final invariant artifact

The latest MariaDB post-cleanup artifact is `flow10a_final_mariadb_invariants.json`:

```json
{
  "artifact": "FLOW10A_FINAL_MARIADB_INVARIANTS",
  "database": "amms_concurrency_test",
  "active_terminal_sessions": 0,
  "active_enrollment_sessions": 0,
  "active_terminals": 6,
  "active_template_rows": 0,
  "revoked_or_superseded_template_history": 6,
  "attendance_rows": 1,
  "duplicate_meeting_employee_identities": 0,
  "plaintext_probe_columns_present": [],
  "meeting_status_counts": {"SCHEDULED": 5}
}
```

The artifact proves zero active terminal/enrollment sessions after official cleanup, zero duplicate Meeting/Employee attendance identities, no probe/image columns, and all race-fixture meetings remaining `SCHEDULED`. The final SQLite regression and frontend build are recorded above from the same strict-closure run.

## Release boundary

All software evidence gates are complete and aligned. FLOW-10A is **not** marked `APPROVED / FROZEN`; it is `VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL`. FLOW-10B remains `DEFERRED / WAITING FOR SVT3000`, and FLOW-11 must not start.
