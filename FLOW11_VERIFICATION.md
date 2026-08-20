# FLOW-11 Verification Report

**System:** AMMS — Administrative Meeting Management System
**Workflow:** FLOW-11 — Meeting Execution, Minutes, Decisions, Reviews, Finalization, and Completion
**Verification date:** 20 August 2026
**Release status:** **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

> FLOW-11 is implemented additively on the frozen FLOW-01 through FLOW-10A architecture. It has not been marked `APPROVED / FROZEN` automatically. FLOW-10B remains deferred, and no later action-item workflow has been started.

## 1. Scope delivered

FLOW-11 adds a formal Meeting execution session with the lifecycle `SCHEDULED → IN_PROGRESS → HELD → COMPLETED`. The server captures immutable schedule and participant-version baselines at start, uses server-authoritative timestamps, and enforces one execution session per Meeting.

The Minutes subsystem adds one root per Meeting and immutable version-bound Minutes. A submitted version binds to the execution session, schedule version, and participant version. It stores official summary, discussion items, documentary decisions, privacy-safe attendance status, and version-specific review rows. Correction preserves the historical submitted version and creates a new version whose acknowledgements must be collected again.

The review workflow supports exact unit-scoped acknowledgement, mandatory correction reasons, exact High Authority approval by the employee in `MeetingAuthorityContext`, and finalization only after every required review gate is complete. Finalization atomically completes the Meeting and creates one official checksum-bound PDF identity.

The final PDF is generated with Dompdf as a Persian/Dari RTL document containing only official meeting content, attendance status, discussion, decisions, version, and checksum. It does not contain biometric templates, raw sensor frames, candidate sets, matcher fields, or terminal secrets.

## 2. Independent per-flow governance evidence

The strict reviewer required independent FLOW-01 through FLOW-06 evidence rather than relying only on the frozen-flow regression. `Flow11PerFlowGovernanceTest` now contains six independent scenarios and all pass.

| Flow | Independent evidence | Result |
|---|---|---|
| FLOW-01 | One formal Directorate acknowledgement is accepted | **PASS** |
| FLOW-02 | Exactly two formal Directorate acknowledgements are required and produce `READY_TO_FINALIZE` | **PASS** |
| FLOW-03 | Cross-unit acknowledgement is denied; correction creates V2; old acknowledgements do not transfer; all three units re-acknowledge V2 | **PASS** |
| FLOW-04 | Requester acknowledgement plus exact High Authority approval | **PASS** |
| FLOW-05 | All formal Directorates acknowledge; Authority Office is denied; exact High Authority approves | **PASS** |
| FLOW-06 | `requesting_unit_id = null`; all participating Directorates acknowledge; Authority Office is denied; High Authority approves | **PASS** |

The independent suite passes **6 tests and 64 assertions**. The dedicated High Authority scenario also passes, including Executive Secretary finalization. The complete final Feature suite therefore passes **331 tests and 1,319 assertions**.

## 3. API and RTL frontend

The authenticated API adds execution, Minutes workspace, draft/version, review, High Authority, finalization, and PDF routes without altering frozen FLOW-01 through FLOW-10A endpoints. The RTL frontend adds real execution and Minutes workspaces from the existing Meeting Detail page, including status, baseline, draft, review matrix, version history, finalization, and PDF access.

| API/UI area | Result |
|---|---|
| Execution state/start/end routes | **PASS** |
| Minutes workspace/draft/update/submit routes | **PASS** |
| Correction and version-specific review routes | **PASS** |
| Unit acknowledgement and High Authority approval | **PASS** |
| Finalization and official PDF route | **PASS** |
| Persian/Dari RTL frontend build | **PASS** |

## 4. Final automated verification

The authoritative final regression was run after the strict-closure changes:

| Gate | Result |
|---|---:|
| Full Laravel Feature regression, FLOW-01 through FLOW-11 | **331 tests passed** |
| Assertions | **1,319 assertions** |
| Frontend TypeScript/Vite production build | **PASS** |
| Frontend modules transformed | **1,863** |
| Independent FLOW-01 through FLOW-06 governance suite | **6 tests, 64 assertions** |
| High Authority Minutes scenario | **1 test, 13 assertions** |
| Frozen FLOW-10A manual foundation | **PASS** |

The final logs are stored in `flow11_final_definitive_feature_regression.log` and `flow11_final_definitive_frontend_build.log`.

## 5. Real manual strict gate

The strict manual gate ran against a dedicated Laravel HTTP server backed by a copied SQLite database. It used real login sessions and real API calls, not Feature Test helpers.

The manual FLOW-03 path executed V1 submission, Directorate A and B acknowledgement, Directorate C correction, V2 update and resubmission, verification that V1 acknowledgements did not transfer to V2, and fresh A/B/C acknowledgements. The manual FLOW-05 and FLOW-06 paths executed all formal Directorate acknowledgements, denied Authority Office approval, exact High Authority approval, and Executive Secretary finalization. FLOW-06 explicitly used `requesting_unit_id = null`.

The same real HTTP transcript performed two-finger enrollment, terminal activation, a fingerprint attendance submission while the Meeting was `IN_PROGRESS`, execution end to `HELD`, and a second real fingerprint submission. The second submission was rejected by the API with the exact code `MEETING_ATTENDANCE_CLOSED`.

| Manual gate | Result |
|---|---:|
| Strict real HTTP checks | **55/55 passed** |
| FLOW-03 V1/V2 correction and fresh acknowledgements | **PASS** |
| FLOW-05 Directorate/Authority Office/High Authority/Secretary path | **PASS** |
| FLOW-06 no-requester path | **PASS** |
| Fingerprint attendance before end | **PASS** |
| Fingerprint attendance after `HELD` | **PASS — `MEETING_ATTENDANCE_CLOSED`** |

Evidence is stored in `flow11_manual_strict_gate.json` and `flow11_manual_strict_gate.log`.

## 6. MariaDB Action-level concurrency

The dedicated `amms_concurrency_test` MariaDB database was rebuilt from the complete migration set. The probe uses separate forked Laravel processes and actual Actions.

| Race group | Expected invariant | Result |
|---|---|---|
| Concurrent execution start | One session; loser gets `MEETING_EXECUTION_ALREADY_STARTED` | **PASS** |
| Concurrent execution end | One ended session; loser gets `MEETING_EXECUTION_ALREADY_ENDED` | **PASS** |
| Attendance first versus end | Attendance is preserved and end commits | **PASS** |
| End first versus attendance | No post-end attendance row; `MEETING_ATTENDANCE_CLOSED` | **PASS** |
| Concurrent Minutes-root creation | Exactly one root per Meeting | **PASS** |
| Concurrent Minutes submission | One submitted version; stale loser | **PASS** |
| Concurrent Directorate acknowledgement | One version-bound review acknowledgement | **PASS** |
| Concurrent finalization | One finalized root and one completed Meeting | **PASS** |
| Repeated official PDF generation | One document identity and identical checksum | **PASS** |

The probe output is `flow11_action_race_probe.json`.

## 7. Visual PDF gate

The finalized FLOW-05 PDF was captured through the real HTTP endpoint and rendered as an image for visual inspection. It is a one-page A4 PDF produced by Dompdf 3.1.6 + CPDF.

| Visual/content requirement | Result |
|---|---|
| Persian/Dari title, labels, and RTL layout | **PASS** |
| Meeting number, subject, schedule, location | **PASS** |
| Actual server start/end timestamps | **PASS** |
| Chairperson and formal participant units | **PASS** |
| Present/Absent attendance status | **PASS** |
| Agenda and discussion summary | **PASS** |
| Decisions and `DEC-001` | **PASS** |
| Version number | **PASS** |
| SHA-256 checksum reference | **PASS** |
| Biometric or terminal implementation fields | **ABSENT / PASS** |

The captured PDF is `flow11_manual_final_minutes.pdf`, its extracted text is `flow11_manual_final_minutes.txt`, and the visual inspection record is `flow11_pdf_visual_inspection.md`. The response was HTTP 200, `application/pdf`, 24,038 bytes, and `%PDF` prefixed.

## 8. Rich canonical MariaDB invariants

The canonical artifact is generated after official domain cleanup through `EndMeetingExecution` and `CloseTerminalMeetingSession`. It reports eight race fixtures plus one explicit completed High Authority fixture.

The completed fixture records `meeting_request_id`, `request_status = CONVERTED_TO_MEETING`, `flow_type`, `decision_count`, `duplicate_decision_sequence_count`, `review_requirement_count`, `acknowledgement_count`, `high_authority_approval_count`, `attendance_after_end_count`, `duplicate_execution_session_count`, `duplicate_minutes_root_count`, `duplicate_finalization_count`, `request_meeting_duplicate_count`, and `biometric_sensitive_fields_in_minutes`.

| Canonical/global invariant | Result |
|---|---:|
| Canonical completed fixture | **1** |
| Canonical completed Meeting status | **COMPLETED** |
| Canonical request status | **CONVERTED_TO_MEETING** |
| Canonical requesting unit | `null` |
| Canonical decision count | **1** |
| Duplicate decision sequence count | **0** |
| Canonical review requirement count | **4** |
| Canonical Directorate acknowledgement count | **3** |
| Canonical High Authority approval count | **1** |
| Official final document rows | **2**, one for each finalized Minutes root |
| Attendance after end count | **0** |
| Duplicate execution sessions | **0** |
| Duplicate Minutes roots | **0** |
| Duplicate finalization count | **0** |
| Request-to-Meeting duplicate count | **0** |
| Biometric-sensitive fields in Minutes | **0** |
| Active execution sessions after cleanup | **0** |
| Active terminal sessions after cleanup | **0** |
| Active enrollment sessions after cleanup | **0** |
| Plaintext sensitive probe columns | `[]` |

The complete artifact is `flow11_final_mariadb_invariants.json`.

## 9. Release boundary

The implementation and all newly requested evidence gates are complete. The technical status is:

> **FLOW-11: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.**

No automatic `APPROVED / FROZEN` status has been applied. FLOW-01 through FLOW-10A remain frozen, FLOW-10B physical DERMALOG SVT3000 SE integration remains deferred, and FLOW-12 Decision & Action Item Follow-up has not started.
