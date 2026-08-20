# FLOW-11 Final Strict-Closure Negative Matrix

**Status:** Verification complete — pending explicit user approval
**Evidence basis:** independent per-flow Feature suite, strict real-HTTP manual transcript, visual PDF inspection, MariaDB Action-level race probe, rich canonical invariant artifact, final regression, and frontend build.

| ID | Boundary or negative scenario | Expected result | Evidence | Status |
|---|---|---|---|---|
| A | Unauthorized execution start | Policy/governance rejection; Meeting remains `SCHEDULED` | `Flow11ExecutionTest` | **PASS** |
| B | Concurrent execution start | One session; loser `MEETING_EXECUTION_ALREADY_STARTED` | `flow11_action_race_probe.json` | **PASS** |
| C | Concurrent execution end | One ended session; loser `MEETING_EXECUTION_ALREADY_ENDED` | MariaDB Action race | **PASS** |
| D | Schedule change after execution start | FLOW-07 mutation guard rejects it | FLOW-07 regression and execution guard | **PASS** |
| E | Participant change after execution start | FLOW-08 mutation guard rejects it | FLOW-08 regression and execution guard | **PASS** |
| F | Attendance before end followed by end | Attendance row is preserved; Meeting reaches `HELD` | MariaDB attendance-first race | **PASS** |
| G | Fingerprint attendance after `HELD` | No new row; `MEETING_ATTENDANCE_CLOSED` | Strict manual HTTP gate | **PASS** |
| H | Concurrent Minutes-root creation | Exactly one root for the Meeting | MariaDB Action race | **PASS** |
| I | Minutes draft before execution | `MINUTES_NOT_AVAILABLE` | `Flow11MinutesTest` | **PASS** |
| J | Submit while `IN_PROGRESS` | `MINUTES_MEETING_NOT_HELD` | Feature and manual HTTP gates | **PASS** |
| K | Concurrent Minutes submission | One immutable submitted version; stale loser | MariaDB Action race | **PASS** |
| L | Update submitted version without correction | Historical version cannot be changed | `Flow11MinutesReviewTest` | **PASS** |
| M | Correction request without reason | Validation/domain rejection | `Flow11MinutesReviewTest` | **PASS** |
| N | FLOW-03 cross-unit acknowledgement | Finance Director cannot acknowledge HR unit | Independent FLOW-03 Feature and manual gate | **PASS** |
| O | FLOW-03 correction/resubmission | V2 is created; V1 remains historical; V1 ACKs do not transfer | Independent FLOW-03 Feature and manual gate | **PASS** |
| P | FLOW-03 fresh review | A/B/C must acknowledge V2 again | Independent FLOW-03 Feature and manual gate | **PASS** |
| Q | FLOW-02 exact review count | Exactly two formal Directorate ACKs are recorded | Independent FLOW-02 Feature | **PASS** |
| R | FLOW-04 requester rule | Requester acknowledgement plus High Authority approval required | Independent FLOW-04 Feature | **PASS** |
| S | FLOW-05 all formal Directorates | TECH, FIN, and HR must acknowledge | Independent FLOW-05 Feature and manual gate | **PASS** |
| T | FLOW-05 Authority Office versus High Authority | Authority Office denied; exact High Authority accepted | Independent FLOW-05 Feature and manual gate | **PASS** |
| U | FLOW-06 no requester | `requesting_unit_id = null` and no requester ACK required | Independent FLOW-06 Feature and manual gate | **PASS** |
| V | FLOW-06 participating Directorates | All participating Directorates acknowledge before authority approval | Independent FLOW-06 Feature and manual gate | **PASS** |
| W | Concurrent Directorate acknowledgement | One version-bound acknowledgement state | MariaDB Action race | **PASS** |
| X | Wrong High Authority actor | Exact `authority_employee_id` binding rejects approval | High Authority Feature and manual gate | **PASS** |
| Y | Finalization before review completion | Finalization rejected; Meeting stays `HELD` | Finalization Action guard | **PASS** |
| Z | Concurrent finalization | One finalization; loser `MINUTES_ALREADY_FINALIZED` | MariaDB Action race | **PASS** |
| AA | Repeated PDF generation | One official document identity; checksum unchanged | MariaDB Action race and PDF Feature | **PASS** |
| AB | PDF before finalization | Official document unavailable before `FINALIZED` | Document Action guard | **PASS** |
| AC | Visual RTL PDF rendering | Persian/Dari text, RTL tables, required content readable and unclipped | `flow11_pdf_visual_inspection.md` plus rendered PDF image | **PASS** |
| AD | Biometric fields in Minutes/PDF | No template bytes, raw frames, candidate fields, matcher fields, or terminal secrets | Privacy Feature and visual/text inspection | **PASS** |
| AE | Post-finalization mutation | Minutes and Meeting lifecycle remain immutable | Finalization guards and regression | **PASS** |
| AF | Server-authoritative timestamps | Client cannot control start/end/review time | Action implementations and tests | **PASS** |
| AG | Duplicate decision sequence | Zero duplicate decision sequence numbers | Rich canonical invariant artifact | **PASS** |
| AH | Attendance after end in canonical fixture | `attendance_after_end_count = 0` | Rich canonical invariant artifact | **PASS** |
| AI | Duplicate execution sessions | `duplicate_execution_session_count = 0` | Rich canonical invariant artifact | **PASS** |
| AJ | Duplicate Minutes roots | `duplicate_minutes_root_count = 0` | Rich canonical invariant artifact | **PASS** |
| AK | Duplicate finalization | `duplicate_finalization_count = 0` | Rich canonical invariant artifact | **PASS** |
| AL | Request-to-Meeting duplicates | `request_meeting_duplicate_count = 0` | Rich canonical invariant artifact | **PASS** |
| AM | Sensitive fields in Minutes | `biometric_sensitive_fields_in_minutes = 0` and plaintext column list empty | Rich canonical invariant artifact | **PASS** |
| AN | Active session leakage | Active execution, terminal, and enrollment session counts are zero after official cleanup | Rich canonical invariant artifact | **PASS** |
| AO | Full frozen-flow regression | FLOW-01 through FLOW-10A remain green | Final regression: 325 tests, 1,255 assertions | **PASS** |
| AP | RTL production build | FLOW-11 frontend compiles in existing shell | Final build: 1,863 modules | **PASS** |
| AQ | Manual FLOW-03/FLOW-05/FLOW-06/attendance gate | All real HTTP checks pass outside Feature Tests | Strict transcript: 55/55 | **PASS** |

## Summary

All newly requested strict-review gates are **PASS**. The independent per-flow suite passes **6 tests and 64 assertions**. The final full regression passes **331 tests and 1,319 assertions**. The strict manual transcript passes **55 of 55** checks. The visual PDF gate passes for Persian/Dari RTL, required content, and biometric-field absence. The rich canonical artifact contains the completed-fixture request, flow, decision, review, acknowledgement, High Authority, attendance-after-end, duplicate, and privacy invariants.

This matrix records technical verification only. FLOW-11 remains **NOT APPROVED / NOT FROZEN** pending explicit user approval. FLOW-10B physical DERMALOG integration remains deferred, and FLOW-12 has not started.
