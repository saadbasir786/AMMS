# FLOW-10A Negative Matrix A–P

This matrix records the device-independent FLOW-10A foundation. It does not claim physical DERMALOG/SVT3000 integration. The terminal candidate endpoint is the only API allowed to return template bytes; human APIs, the ordinary FLOW-09 manifest, audit metadata, and attendance records remain byte-free.

| ID | Negative case | Expected result | Evidence state |
|---|---|---|---|
| A | Unauthorized human starts enrollment | HTTP 403 | **PASS** — `Flow10AAuthorizationTest` asserts the human API denial. |
| B | Terminal with active Meeting session starts enrollment | `TERMINAL_BUSY` | **PASS** — `Flow10ATerminalBoundaryTest` asserts the active-session block. |
| C | Terminal A accesses Terminal B enrollment/session | Denied | **PASS** — terminal ownership and session-bound access are asserted by `Flow10ATerminalBoundaryTest`. |
| D | Template submission contains fingerprint image/probe field | `RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED` | **PASS** — terminal request validation rejects raw-image/probe fields. |
| E | Low-quality simulated capture | `FINGERPRINT_QUALITY_TOO_LOW` | **PASS** — deterministic fake adapter and endpoint rejection are covered. |
| F | Concurrent enrollment for same employee/finger | One active path/template | **ACTION-LEVEL PASS** — `flow10a_action_race_probe.json` returns one `ENROLLMENT_COMMITTED`, one `BIOMETRIC_ENROLLMENT_ALREADY_ACTIVE` (422), and `concurrent_enrollment_final_active_sessions: 1`; the MariaDB uniqueness probe also passed. |
| G | Re-enrollment | Old immutable version `SUPERSEDED`, new version `ACTIVE` | **PASS** — `Flow10ABiometricLifecycleTest`, including audit/privacy assertions. |
| H | Revoked template appears in candidate set | Must never occur | **PASS** — `Flow10ACandidateSetTest` asserts revoked exclusion. |
| I | FLOW-07 stale session | Candidate and attendance denied | **ACTION-LEVEL PASS** — schedule mutation wins and attendance returns `TERMINAL_SESSION_STALE` (422). |
| J | FLOW-08 stale session | Candidate and attendance denied | **ACTION-LEVEL PASS** — Participant Version mutation wins and attendance returns `TERMINAL_SESSION_STALE` (422). |
| K | Matched employee is not in Participant Version | `BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT` | **PASS** — `Flow10AFingerprintAttendanceTest` asserts nonparticipant rejection. |
| L | Template belongs to a different employee | Rejected | **PASS** — `Flow10AFingerprintAttendanceTest` rejects wrong employee/template pairing. |
| M | Two terminals submit same attendance concurrently | One official row | **ACTION-LEVEL PASS** — `two_terminal_attendance_submissions` returns one `ATTENDANCE_COMMITTED`, one `ATTENDANCE_ALREADY_RECORDED` (409), and `two_terminal_attendance_final_rows: 1`; the MariaDB uniqueness probe also passed. |
| N | Manual attendance without reason | `MANUAL_ATTENDANCE_REASON_REQUIRED` | **PASS** — `Flow10AAttendanceTest` asserts the canonical code. |
| O | Unauthorized manual override | HTTP 403 / `UNAUTHORIZED_ATTENDANCE_OVERRIDE` | **PASS** — `Flow10AAttendanceTest` asserts unauthorized system-admin denial. |
| P | Final invariants and privacy boundary | No active sessions after official cleanup, no duplicate attendance identity, no image/probe storage, correct Meeting state, and no human/manifest template-byte exposure | **PASS** — `flow10a_final_mariadb_invariants.json` records active terminal/enrollment sessions `0`, duplicate identities `0`, `plaintext_probe_columns_present: []`, attendance rows `1`, and all five race-fixture meetings `SCHEDULED`; the definitive full regression/build counts are recorded below. |

## Positive boundary evidence

The candidate-set contract is deliberately asymmetric. The session-authenticated terminal endpoint returns `template_data` bytes for local 1:N matching, together with candidate UUID/employee/finger/checksum metadata. Human employee biometric resources, human enrollment responses, audit metadata, attendance rows, and the ordinary FLOW-09 manifest never return `template_data`, fingerprint images, raw sensor frames, or probe templates. The Android core’s `closeAndClear()` vector verifies candidate-byte clearing on session close.

## Independent Android evidence

```text
FLOW10A_ANDROID_CORE_PASS vectors=8
FLOW10A_ANDROID_FOUNDATION_PASS files=5
```

The independent `terminal-android/` core covers Enrollment Mode, Meeting Mode, deterministic fake outcomes, local matching seam, attendance submission, session close, and memory clearing. It is not a physical device or vendor SDK integration.

## Action-level MariaDB evidence

`flow10a_action_race_probe.json` was executed against a freshly migrated `amms_concurrency_test` MariaDB database using forked Laravel processes and actual Actions. The requested race groups all pass: concurrent enrollment, two-terminal attendance, re-enrollment versus revocation, FLOW-07 schedule versus attendance, FLOW-08 participant mutation versus attendance, and template revocation versus attendance. The official cleanup script uses `CloseTerminalMeetingSession` and `CancelBiometricEnrollment` before writing the invariant artifact.

## Current authoritative regression/build record

One authoritative run was completed after the strict-closure fixes, with logs preserved as `definitive_android_core.log`, `definitive_feature_regression.log`, and `definitive_frontend_build.log`:

```text
FLOW10A_ANDROID_CORE_PASS vectors=8
FLOW10A_ANDROID_FOUNDATION_PASS files=5
Feature suite: 316 tests passed (1,154 assertions)
Frontend build: 1,862 modules transformed; build successful
```

The frontend emitted only the existing chunk-size advisory; the production build completed successfully. Earlier counts are historical and superseded.

## Release rule

All software evidence gates are complete and aligned. FLOW-10A remains **pending explicit user approval** and must not be marked `APPROVED / FROZEN` automatically. FLOW-10B remains deferred until lawful physical SVT3000 and DERMALOG SDK access exists. FLOW-11 must not start.
