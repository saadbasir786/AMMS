# FLOW-12 Negative Matrix

This matrix records the required rejection boundaries for Decision Follow-up and Action Item operations. All tests use additive migrations and preserve frozen FLOW-01 through FLOW-11 records.

| ID | Scenario | Expected result | Evidence type | Verification status |
|---|---|---|---|---|
| F12-N01 | Initialize before Meeting is COMPLETED | `422 FOLLOWUP_MEETING_NOT_COMPLETED` | FEATURE TEST PASS | TESTED PASS |
| F12-N02 | Initialize before Minutes FINALIZED | `422 FOLLOWUP_MINUTES_NOT_FINALIZED` | FEATURE TEST PASS | TESTED PASS |
| F12-N03 | Initialize without current finalized version | `422 FOLLOWUP_FINAL_VERSION_MISSING` | FEATURE TEST PASS | TESTED PASS |
| F12-N04 | Concurrent initialization for one finalized Decision | One root row; both requests idempotent | MARIADB PASS | MARIADB PASS |
| F12-N05 | Unauthenticated Follow-up access | `401` | FEATURE TEST PASS | TESTED PASS |
| F12-N06 | Inactive actor performs privileged Follow-up action | `403` | FEATURE TEST PASS | TESTED PASS |
| F12-N07 | Out-of-scope coordinator initializes another unit | `403` | FEATURE TEST PASS | TESTED PASS |
| F12-N08 | Authority Office is treated as owner for FLOW-05/06 classification or verification | `403` unless explicit owner role | MANUAL HTTP PASS | MANUAL PASS |
| F12-N09 | SYSTEM_ADMIN is treated as business owner | `403` unless separately scoped | MANUAL HTTP PASS | MANUAL PASS |
| F12-N10 | Classify NO_ACTION_REQUIRED without reason | `422 DECISION_NO_ACTION_REASON_REQUIRED` | FEATURE TEST PASS | TESTED PASS |
| F12-N11 | Reclassify a CLOSED Follow-up | `422 DECISION_FOLLOWUP_ALREADY_CLOSED` | FEATURE TEST PASS | TESTED PASS |
| F12-N12 | Create Action Item under NO_ACTION_REQUIRED | `422 DECISION_NO_ACTION_REQUIRED` | FEATURE TEST PASS | TESTED PASS |
| F12-N13 | Create Action Item outside meeting organization | `422 ACTION_ITEM_CROSS_ORGANIZATION_UNIT` | FEATURE TEST PASS | TESTED PASS |
| F12-N14 | Assign inactive employee | `422 ACTION_ITEM_ASSIGNEE_INACTIVE` | FEATURE TEST PASS | TESTED PASS |
| F12-N15 | Assign employee outside responsible unit | `422 ACTION_ITEM_ASSIGNEE_OUTSIDE_UNIT` | FEATURE TEST PASS | TESTED PASS |
| F12-N16 | Duplicate sequence under one Decision | Database unique/locking invariant; no duplicate sequence | MARIADB PASS | MARIADB PASS |
| F12-N17 | Start Action Item twice | `409 ACTION_ITEM_ALREADY_STARTED` | FEATURE TEST PASS | TESTED PASS |
| F12-N18 | Record progress outside 0–100 | `422 ACTION_ITEM_PROGRESS_INVALID` | FEATURE TEST PASS | TESTED PASS |
| F12-N19 | Progress a terminal Action Item | `409 ACTION_ITEM_INVALID_STATE` | FEATURE TEST PASS | TESTED PASS |
| F12-N20 | Block without reason | `422 ACTION_ITEM_BLOCK_REASON_REQUIRED` | FEATURE TEST PASS | TESTED PASS |
| F12-N21 | Unblock without note | `422` validation error | FEATURE TEST PASS | TESTED PASS |
| F12-N22 | Submit completion below 100% | `422 ACTION_ITEM_COMPLETION_PROGRESS_REQUIRED` | FEATURE TEST PASS | TESTED PASS |
| F12-N23 | Assigned employee verifies own completion | `403` | MANUAL HTTP PASS | MANUAL PASS |
| F12-N24 | Verify a non-pending completion | `409 ACTION_ITEM_NOT_PENDING_VERIFICATION` | MARIADB PASS | MARIADB PASS |
| F12-N25 | Return completion without reason | `422 ACTION_ITEM_COMPLETION_RETURN_REASON_REQUIRED` | FEATURE TEST PASS | TESTED PASS |
| F12-N26 | Cancel a COMPLETED Action Item | `409 ACTION_ITEM_CANNOT_CANCEL_COMPLETED` | FEATURE TEST PASS | TESTED PASS |
| F12-N27 | Request extension with earlier/equal date | `422 ACTION_ITEM_EXTENSION_INVALID_DATE` | FEATURE TEST PASS | TESTED PASS |
| F12-N28 | Submit two concurrent extension requests | One pending row; second rejected | MARIADB PASS | MARIADB PASS |
| F12-N29 | Approve stale extension after due date changed | `409 ACTION_ITEM_EXTENSION_STALE_DATE` | FEATURE TEST PASS | TESTED PASS |
| F12-N30 | Responsible-unit actor changes unit without Follow-up Owner scope | `403`; owner reassignment is allowed | MANUAL HTTP PASS | MANUAL PASS |
| F12-N31 | Upload evidence with forbidden MIME or oversized file | `422`; no file row is created for rejected upload | MANUAL HTTP PASS | MANUAL PASS |
| F12-N32 | Download evidence for another Action Item | `404 ACTION_ITEM_EVIDENCE_NOT_FOUND` | MANUAL HTTP PASS | MANUAL PASS |
| F12-N33 | Public evidence URL is generated | No public URL; authorized private route only | MANUAL HTTP PASS | MANUAL PASS |
| F12-N34 | Edit/delete update, assignment, extension, or audit history through normal APIs | No normal mutation routes; append-only rows preserved | STATIC INSPECTION ONLY | STATIC INSPECTION ONLY |
| F12-N35 | Concurrent completion verification | One COMPLETED row and one terminal-state rejection | MARIADB PASS | MARIADB PASS |
| F12-N36 | Follow-up closes before all items are terminal | `422 DECISION_FOLLOWUP_NOT_READY_TO_CLOSE` | FEATURE TEST PASS | TESTED PASS |
| F12-N37 | Follow-up closure mutates Meeting status | Meeting remains `COMPLETED` | FEATURE TEST PASS | TESTED PASS |
| F12-N38 | Follow-up closure mutates finalized Decision text/target date | Source fields unchanged | FEATURE TEST PASS | TESTED PASS |
| F12-N39 | Completion submission versus reassignment | If submission wins, item is `PENDING_VERIFICATION` with old assignee and reassignment is rejected; if reassignment wins, old-assignee submission is rejected and new assignment remains | MARIADB PASS | MARIADB PASS |
| F12-N40 | Extension approval versus completion verification | Completion remains authoritative; a completed item’s due date is not mutated by a losing extension approval | MARIADB PASS | MARIADB PASS |
| F12-N41 | Overdue check versus completion | `NOT IMPLEMENTED / NOT APPLICABLE`: overdue is a read-time derived property; no scheduled checker or notification command exists | STATIC INSPECTION ONLY | STATIC INSPECTION ONLY |
| F12-N42 | Audit metadata contains evidence bytes, base64, or request-body content | `0`; audit stores metadata only | CANONICAL DB INVARIANT PASS | CANONICAL DB INVARIANT PASS |

## Boundary

FLOW-12 is additive. It does not add biometric or terminal fields, does not reopen a finalized Minutes version, does not alter the official Minutes PDF, and does not claim implementation of FLOW-10B hardware integration.
