# FLOW-13 Negative Matrix

This matrix is additive to the frozen FLOW-01 through FLOW-12 evidence. Runtime rows are supported by the real HTTP transcript or Feature suite; static rows are explicitly not represented as runtime proof.

| ID | Boundary | Expected result | Evidence type | Verification status |
|---|---|---|---|---|
| N01 | Non-owner creates exception case | 403 business-governance denial | Real HTTP | PASS |
| N02 | SYSTEM_ADMIN creates business exception | 403; administration is not business authority | Real HTTP | PASS |
| N03 | High Authority creates Directorate-owned exception | 403 unless exact review row is assigned | Real HTTP | PASS |
| N04 | Missing exception reason | 422 validation denial | Feature / FormRequest | PASS |
| N05 | Unsupported case type | 422 validation denial | Feature / FormRequest | PASS |
| N06 | Second active case for one Meeting | 409; one active-case marker only | Feature | PASS |
| N07 | Postponement from COMPLETED | 409 | Feature | PASS |
| N08 | Reopening a COMPLETED Meeting | 409 | Feature | PASS |
| N09 | Reopening a SCHEDULED Meeting | 409 | Feature | PASS |
| N10 | Resumption without replacement intent | 422 | Feature / FormRequest | PASS |
| N11 | Resumption without exact new schedule | 422 | Real HTTP / FormRequest | PASS |
| N12 | Resumption with past or conflicting schedule | 422 | Feature / FLOW-07 conflict boundary | PASS |
| N13 | Submitted version edited in place | 409; correction creates version N+1 | Feature | PASS |
| N14 | Review row from another version acknowledged | 403/409 | Feature / policy binding | PASS |
| N15 | Wrong Directorate acknowledges review | 403 | Real HTTP / Feature | PASS |
| N16 | Wrong Directorate approves review | 403 | Real HTTP | PASS |
| N17 | High Authority approves unit review | 403 | Real HTTP | PASS |
| N18 | Authority Office approves High Authority review without exact identity | 403 | Feature / resolver | PASS |
| N19 | Rejection without reason | 422 | Feature / FormRequest | PASS |
| N20 | Withdrawal after terminal case | 409 | Feature | PASS |
| N21 | Apply before all required reviews | 409 | Feature / action state guard | PASS |
| N22 | Apply with stale Meeting baseline | 409; source revalidation | Feature / Action | PASS |
| N23 | Apply postponement after Meeting became IN_PROGRESS | 409 | Feature / Action | PASS |
| N24 | Apply cancellation after Meeting became COMPLETED | 409 | Feature / Action | PASS |
| N25 | Active terminal session after postponement | Session and assignment revoked atomically | Feature | PASS |
| N26 | Active terminal session after cancellation | Session and assignment revoked atomically | Feature | PASS |
| N27 | Manual attendance after POSTPONED | 422 `MEETING_NOT_EXECUTABLE` | Feature / Action | PASS |
| N28 | Fingerprint attendance after CANCELLED | 422 `MEETING_NOT_EXECUTABLE` | Static + Action boundary | PASS |
| N29 | Invalidated attendance counted as official | Excluded by resolver; immutable raw row retained | Feature / resolver | PASS |
| N30 | Old schedule overwritten during resumption | Old version remains immutable; new current pointer | Real HTTP / Feature | PASS |
| N31 | Old participant version overwritten | Version history remains append-only | Static + Feature | PASS |
| N32 | FLOW-12 decision source mutated by exception case | Source snapshot unchanged | Feature / invariant | PASS |
| N33 | Concurrent active-case creation | One winner; loser rejected; one active marker | MariaDB race | PENDING RACE ARTIFACT |
| N34 | Concurrent apply of same exception case | One terminal application; second is rejected | MariaDB race | PENDING RACE ARTIFACT |
| N35 | Concurrent cancellation versus resumption creation | Serialized Meeting lock prevents invalid transition | MariaDB race | PENDING RACE ARTIFACT |
| N36 | Concurrent review approval versus correction request | Version-specific row and case state remain coherent | MariaDB race | PENDING RACE ARTIFACT |
| N37 | Audit payload contains attendance or biometric bytes | No sensitive bytes in audit metadata | Static + invariant | PASS |
| N38 | SYSTEM_ADMIN bypasses policy through Action direct call | Action revalidates state and governance | Feature / Action | PASS |
| N39 | Frozen FLOW-07 reschedule used to replace postponement | Explicit separation remains enforced | Static + Feature | PASS |
| N40 | FLOW-10B hardware integration introduced | Must remain deferred; no hardware claim | Repository audit | PASS |
| N41 | FLOW-14 implementation begins | Must not start in this release | Repository audit | PASS |
| N42 | Exception case or review history deleted | Normal APIs cannot delete immutable history | Static + model guard | PASS |

> The rows marked `PENDING RACE ARTIFACT` are not release-complete until the MariaDB probe is executed and its JSON transcript is attached. Static inspection is never treated as a substitute for those runtime checks.
