# FLOW-11 Negative Matrix and Evidence Log

**Status:** Verification complete — pending explicit user approval
**Evidence basis:** final Feature regression, final frontend build, real HTTP manual gate, Action-level MariaDB race probe, and canonical invariant artifact.

| ID | Negative or boundary scenario | Expected behavior | Evidence | Status |
|---|---|---|---|---|
| A | Unauthorized user starts execution | Policy and governance reject the request; Meeting remains `SCHEDULED` | `Flow11ExecutionTest`, full regression | **PASS** |
| B | System administrator starts or ends an ordinary Meeting without execution ownership | Permission alone is insufficient; organizational governance rejects the action | `Flow11ExecutionTest` | **PASS** |
| C | Two workers start one Meeting | One execution session is created; loser receives `MEETING_EXECUTION_ALREADY_STARTED` | `flow11_action_race_probe.json` | **PASS** |
| D | Two workers end one Meeting | One session becomes `ENDED`; loser receives `MEETING_EXECUTION_ALREADY_ENDED` | `flow11_action_race_probe.json` | **PASS** |
| E | Meeting starts without current schedule version | Start is rejected by `MEETING_SCHEDULE_VERSION_REQUIRED` | `Flow11ExecutionTest` and Action guards | **PASS** |
| F | Meeting starts without current participant version | Start is rejected by `MEETING_PARTICIPANT_VERSION_REQUIRED` | `Flow11ExecutionTest` and Action guards | **PASS** |
| G | FLOW-07 schedule mutation after execution start | Existing post-start mutation guard rejects the change | `Flow11ExecutionTest`, frozen FLOW-07 regression | **PASS** |
| H | FLOW-08 participant mutation after execution start | Existing participant validator rejects the change with started-meeting boundary | `Flow11ExecutionTest`, frozen FLOW-08 regression | **PASS** |
| I | Attendance after execution end | No new attendance row is created; canonical `MEETING_ATTENDANCE_CLOSED` is returned | `Flow11ExecutionTest`, reverse-order MariaDB probe, full regression | **PASS** |
| J | Attendance before execution end followed by end | Existing attendance remains and Meeting ends normally | `flow11_action_race_probe.json` attendance-first scenario | **PASS** |
| K | Concurrent Minutes-root creation | One root exists for the Meeting; the unique loser resolves to the existing root | `flow11_action_race_probe.json` | **PASS** |
| L | Minutes draft creation before execution | Draft creation is rejected as unavailable | `Flow11MinutesTest` | **PASS** |
| M | Minutes submission while Meeting is `IN_PROGRESS` | Submission is rejected with `MINUTES_MEETING_NOT_HELD` | `Flow11MinutesTest`, manual HTTP gate | **PASS** |
| N | Concurrent submission of one draft | One immutable version is submitted; the other worker receives a stale-version rejection | `flow11_action_race_probe.json` | **PASS** |
| O | Update of submitted version without correction state | Historical submitted version is not modified; update is rejected unless root is `RETURNED_FOR_CORRECTION` | `Flow11MinutesReviewTest` and Action guard | **PASS** |
| P | Correction request without reason | Validation/domain boundary rejects the request | `Flow11MinutesReviewTest`, manual reason field | **PASS** |
| Q | Correction and resubmission | Correction changes root to `RETURNED_FOR_CORRECTION`; resubmission creates version 2 and preserves version 1 | `Flow11MinutesReviewTest` | **PASS** |
| R | Wrong Directorate acknowledges | Scope and resource policy reject acknowledgement | `Flow11MinutesReviewTest`, governance resolver | **PASS** |
| S | Concurrent Directorate acknowledgement | Exactly one version-bound review row becomes `ACKNOWLEDGED` | `flow11_action_race_probe.json` | **PASS** |
| T | Authority Office attempts High Authority approval | Office actor is rejected; no approval is recorded | `Flow11HighAuthorityMinutesTest` | **PASS** |
| U | Non-holder attempts High Authority approval | Exact `authority_employee_id` binding rejects the request | `MinutesGovernanceResolver`, High Authority test | **PASS** |
| V | High Authority approval before all Directorate acknowledgements | Approval is blocked until all required unit reviews are acknowledged | `ApproveHighAuthorityMeetingMinutes` guard | **PASS** |
| W | Finalization before review completion | Finalization is rejected; Meeting remains `HELD` | `FinalizeMeetingMinutes` guard | **PASS** |
| X | Concurrent finalization | One finalization transition occurs; duplicate receives `MINUTES_ALREADY_FINALIZED` | `flow11_action_race_probe.json` | **PASS** |
| Y | Repeated official PDF generation | One document identity remains; checksum is identical | `flow11_action_race_probe.json`, PDF Feature test | **PASS** |
| Z | PDF before finalization | Document endpoint is unavailable until Minutes is finalized | `GenerateFinalMeetingMinutesDocument` guard | **PASS** |
| AA | Biometric or terminal fields in Minutes API/PDF | No template bytes, raw frames, candidate sets, matcher fields, or terminal secrets are exposed | `Flow11` resources, PDF test, canonical privacy artifact | **PASS** |
| AB | Post-finalization Minutes mutation | Finalized root and historical versions remain immutable | `Flow11MinutesReviewTest`, finalization state guards | **PASS** |
| AC | Post-finalization Meeting execution mutation | Meeting remains `COMPLETED`; execution cannot restart or end again | `FinalizeMeetingMinutes`, execution guards | **PASS** |
| AD | Stale client timestamps | Server timestamps are used for execution and reviews; client cannot control lifecycle time | Action implementations and Feature regression | **PASS** |
| AE | One official document per Minutes root | Unique root and version identities prevent duplicates | Canonical MariaDB invariants | **PASS** |
| AF | Active session leakage after verification | Official cleanup leaves zero active execution, terminal, and biometric-enrollment sessions | `flow11_final_mariadb_invariants.json` | **PASS** |
| AG | Full frozen-flow regression | FLOW-01 through FLOW-10A behavior remains green | `flow11_definitive_feature_regression.log` | **PASS** |
| AH | RTL production UI build | FLOW-11 execution and Minutes workspaces compile with the existing RTL shell | `flow11_definitive_frontend_build.log` | **PASS** |
| AI | Real software workflow | HTTP login, execution, Minutes, review, finalization, duplicate protection, and PDF all work outside Feature Tests | `flow11_manual_api_gate.json`: 14/14 | **PASS** |

## Summary

All FLOW-11 negative and boundary scenarios in this matrix are **PASS**. The authoritative regression contains **325 passing tests and 1,255 assertions**. The frontend production build transforms **1,863 modules** successfully. The MariaDB Action-level probe passes eight FLOW-11 race groups, and the real HTTP manual gate passes **14 of 14** checks.

The matrix does not mark FLOW-11 as approved or frozen. It records technical verification only; formal approval remains pending explicit user decision. FLOW-10B physical DERMALOG integration remains deferred, and no later action-item workflow has been started.
