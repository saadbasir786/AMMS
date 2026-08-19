# FLOW-10A Negative Matrix A–P

This matrix is an evidence log for the device-independent FLOW-10A foundation. It does not claim physical DERMALOG/SVT3000 integration.

| ID | Negative case | Expected result | Evidence state |
|---|---|---|---|
| A | Unauthorized human starts enrollment | HTTP 403 | **Implemented; dedicated endpoint assertion pending** — `BiometricPolicy` and `StartBiometricEnrollmentRequest`. |
| B | Terminal with active Meeting session starts enrollment | `TERMINAL_BUSY` | **Implemented; dedicated assertion pending** — `StartBiometricEnrollment` checks active FLOW-09 sessions. |
| C | Terminal A accesses Terminal B enrollment/session | Denied | **Implemented; dedicated isolation assertion pending** — terminal enrollment controller compares session terminal ownership. |
| D | Template submission contains fingerprint image/probe field | `RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED` | **Implemented; dedicated request assertion pending** — terminal enrollment and attendance requests prohibit raw fields. |
| E | Low-quality simulated capture | `FINGERPRINT_QUALITY_TOO_LOW` | **PASS at adapter contract level** — Fake adapter exposes rejected quality; endpoint assertion remains pending. |
| F | Concurrent enrollment for same employee/finger | One active path/template | **Database probe PASS** — one active template row under MariaDB/InnoDB uniqueness race; Action-level race assertion pending. |
| G | Re-enrollment | Old immutable version `SUPERSEDED`, new version `ACTIVE` | **PASS** — `Flow10ABiometricLifecycleTest`, 8 assertions. |
| H | Revoked template appears in candidate set | Must never occur | **Implemented; candidate-set assertion pending** — builder filters only `ACTIVE`. |
| I | FLOW-07 stale session | Candidate and attendance denied | **Implemented; dedicated assertion pending** — session schedule-version freshness guard. |
| J | FLOW-08 stale session | Candidate and attendance denied | **Implemented; dedicated assertion pending** — session participant-version freshness guard. |
| K | Matched employee is not in Participant Version | `BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT` | **Implemented; dedicated assertion pending** — attendance Action checks exact member projection. |
| L | Template belongs to a different employee | Rejected | **Implemented; dedicated assertion pending** — attendance Action checks employee/template identity. |
| M | Two terminals submit same attendance concurrently | One official row | **Database probe PASS** — one Meeting+Employee row under MariaDB/InnoDB uniqueness race; Action-level race assertion pending. |
| N | Manual attendance without reason | `MANUAL_ATTENDANCE_REASON_REQUIRED` | **Implemented; dedicated request assertion pending** — manual request requires a non-empty reason. |
| O | Unauthorized manual override | HTTP 403 / `UNAUTHORIZED_ATTENDANCE_OVERRIDE` | **Implemented; dedicated authorization assertion pending** — permission plus `AttendanceGovernanceResolver`. |
| P | Final invariants | Official Meeting identity, `SCHEDULED`, request `CONVERTED_TO_MEETING`, no image/probe storage, no browser template exposure | **FLOW-01–FLOW-09 regression PASS; final FLOW-10A invariant artifact and full manual closure pending**. |

## Current automated evidence

`Flow10AAdapterTest` passes with 2 tests and 10 assertions. `Flow10ABiometricLifecycleTest` passes with 1 test and 8 assertions. The full Feature suite passes with 303 tests and 1,103 assertions. The frontend build passes after 1,862 modules are transformed.

A real MariaDB/InnoDB database-level uniqueness probe passed for the same employee/finger active-template identity and the same Meeting/Employee attendance identity. It produced one committed row in each race and a duplicate/serialized result for the competing writer.

## Release rule

FLOW-10A remains **pending software gate closure** until the rows marked pending receive dedicated endpoint/manual/race evidence. After all software gates pass, the report may be changed to `VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL`. FLOW-10B remains deferred until a lawful physical SVT3000 device and vendor SDK boundary are available. FLOW-11 must not start.
