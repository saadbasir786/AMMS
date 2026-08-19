# FLOW-11 Verification Report

**System:** AMMS — Administrative Meeting Management System
**Workflow:** FLOW-11 — Meeting Execution, Minutes, Decisions, Reviews, Finalization, and Completion
**Verification date:** 20 August 2026
**Release status:** **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

> FLOW-11 is implemented additively on the frozen FLOW-01 through FLOW-10A architecture. It has not been marked `APPROVED / FROZEN` automatically. FLOW-10B remains deferred, and no later action-item workflow has been started.

## 1. Implemented scope

FLOW-11 introduces a formal execution session for each Meeting. The server captures the immutable schedule and participant-version baseline when a meeting starts, records server-authoritative start and end timestamps, and enforces the lifecycle `SCHEDULED → IN_PROGRESS → HELD → COMPLETED`. Meeting status is never inferred from a client timestamp.

The Minutes subsystem introduces one root per Meeting and immutable, version-bound Minutes records. Each submitted version binds to the execution session, schedule version, and participant version. The version contains a general summary, structured discussion items, documentary decisions, privacy-safe attendance status, and version-specific review rows. Correction creates a new version and preserves the submitted historical version.

The review workflow supports Directorate acknowledgement, mandatory correction reasons, exact High Authority approval by the employee bound to the Meeting authority context, and finalization only after all required review gates are complete. Finalization atomically changes the Meeting from `HELD` to `COMPLETED`, writes immutable audit events, and prevents further attendance or Minutes mutation.

The official PDF is generated with Dompdf from the finalized immutable version. The document contains only official meeting data, attendance status, discussion content, decisions, review-finalization metadata, and a checksum reference. Biometric templates, raw sensor frames, candidate-set data, matcher implementation fields, and terminal secrets are not included.

## 2. Additive schema and domain components

| Area | Delivered components | Verification boundary |
|---|---|---|
| Execution | `meeting_execution_sessions`, `MeetingExecutionSession`, `StartMeetingExecution`, `EndMeetingExecution` | One execution session per Meeting; immutable schedule and participant baselines; exactly-once start/end behavior |
| Governance | `MeetingExecutionGovernanceResolver`, MeetingPolicy abilities, FLOW-11 permissions | Coordinator ownership for ordinary flows; Executive Secretary ownership for authority-controlled flows; active-user and scope checks |
| Attendance boundary | Attendance Actions updated for `HELD` and `COMPLETED` | Existing attendance remains immutable; new attendance is rejected after execution end |
| Minutes root/version | `meeting_minutes`, `meeting_minute_versions` | One root per Meeting; immutable submitted versions; version number increments after correction |
| Minutes content | Discussion items, structured decisions, attendance snapshots | No action-progress/status fields; attendance stores only identity/unit/role/status |
| Reviews | Unit review, correction request, High Authority approval | Version-specific review history; required reason for correction; exact authority employee binding |
| Final document | `meeting_minute_documents`, Dompdf builder, final PDF endpoint | One official document identity per finalized Minutes root; checksum-bound bytes; no pre-finalization access |
| RTL UI | `frontend/src/flow11.tsx`, Meeting Detail navigation and status labels | Persian/Dari RTL execution and Minutes workspaces with real API actions |

All FLOW-11 foreign keys use explicit short MariaDB-safe names. No existing FLOW-01 through FLOW-10A migration is reset or redesigned.

## 3. API and frontend surface

The authenticated API adds the following routes without changing frozen routes:

| Route | Function |
|---|---|
| `GET /api/meetings/{meeting}/execution` | Current execution state and immutable baseline references |
| `POST /api/meetings/{meeting}/execution/start` | Start execution with optional notes and server timestamp |
| `POST /api/meetings/{meeting}/execution/end` | End execution with optional notes and server timestamp |
| `GET /api/meetings/{meeting}/minutes` | Minutes workspace and current immutable version |
| `POST /api/meetings/{meeting}/minutes/draft` | Create exactly one draft root |
| `PUT /api/meetings/{meeting}/minutes/draft` | Save draft or create a new version after correction |
| `POST /api/meetings/{meeting}/minutes/submit` | Submit the current version and create review rows |
| `POST /api/meetings/{meeting}/minutes/reviews/correction` | Request correction with mandatory reason |
| `POST /api/meetings/{meeting}/minutes/reviews/{unit}/acknowledge` | Directorate acknowledgement for a specific unit |
| `POST /api/meetings/{meeting}/minutes/authority-approve` | Exact High Authority approval |
| `POST /api/meetings/{meeting}/minutes/finalize` | Finalize Minutes and complete the Meeting |
| `GET /api/meetings/{meeting}/minutes/final.pdf` | Official finalized PDF with checksum header |

The RTL frontend exposes `اجرای جلسه` and `صورت‌جلسه` from the existing Meeting Detail page. The execution workspace shows the formal schedule and participant baselines, real start/end timestamps, attendance boundary, and execution status. The Minutes workspace supports draft editing, discussion items, decisions, review matrix, correction, version history, finalization, and PDF access.

## 4. Automated verification

The authoritative final regression was executed after the final backend and frontend changes:

| Gate | Result |
|---|---:|
| Full Laravel Feature regression, FLOW-01 through FLOW-11 | **325 tests passed** |
| Assertions | **1,255 assertions** |
| Frontend TypeScript/Vite production build | **PASS** |
| Frontend modules transformed | **1,863** |
| Focused FLOW-11 Minutes tests | **5 tests passed, 71 assertions** |
| High Authority Minutes scenario | **1 test passed, 13 assertions** |
| Real HTTP manual gate | **14/14 checks passed** |
| Official PDF response | **HTTP 200, `%PDF` prefix, 23,781 bytes** |

The full Feature run includes the complete FLOW-01 through FLOW-10A regression suite. The final logs are preserved in `flow11_definitive_feature_regression.log` and `flow11_definitive_frontend_build.log`.

## 5. Action-level MariaDB race verification

The dedicated `amms_concurrency_test` MariaDB database was rebuilt from the complete migration set and seeded with the canonical AMMS roles, accounts, organizational scopes, and authority context. The probe uses separate forked Laravel processes and actual domain Actions rather than direct test-table simulations.

| Race group | Expected invariant | Result |
|---|---|---|
| Concurrent execution start | One `IN_PROGRESS` session; loser receives `MEETING_EXECUTION_ALREADY_STARTED` | **PASS** |
| Concurrent execution end | One `ENDED` session; loser receives `MEETING_EXECUTION_ALREADY_ENDED` | **PASS** |
| Attendance first versus execution end | Attendance commits and is preserved; end commits afterward | **PASS** |
| Execution end first versus attendance | No post-end attendance row; `MEETING_ATTENDANCE_CLOSED` | **PASS** |
| Concurrent Minutes-root creation | Exactly one Minutes root per Meeting | **PASS** |
| Concurrent Minutes submission | One submitted version; one stale-version rejection | **PASS** |
| Concurrent Directorate acknowledgement | One review row and one acknowledged state | **PASS** |
| Concurrent Minutes finalization | One finalized Minutes root and one completed Meeting | **PASS** |
| Repeated official document generation | One document identity and identical checksum | **PASS** |

The Action-level output is stored in `flow11_action_race_probe.json`. The canonical cleanup and invariant output is stored in `flow11_final_mariadb_invariants.json`.

## 6. Canonical MariaDB invariants

The canonical artifact was generated after official domain cleanup through `EndMeetingExecution` and `CloseTerminalMeetingSession`. It covers eight isolated FLOW-11 race fixtures.

| Invariant | Observed result |
|---|---:|
| Active execution sessions | `0` |
| Active terminal sessions | `0` |
| Active biometric enrollment sessions | `0` |
| Execution sessions remaining | `8`, all `ENDED` |
| Minutes roots | `4`, with no duplicate Meeting roots |
| Official final document rows | `1` |
| Duplicate official documents | `0` |
| Attendance rows | `1` |
| Attendance methods | `FINGERPRINT: 1` |
| Attendance employee IDs | `[1]` |
| Meeting status counts | `COMPLETED: 1`, `HELD: 7` |
| Plaintext probe columns in FLOW-11 tables | `[]` |

The `HELD` fixtures are intentionally retained as review-stage evidence: one is `DRAFT`, one `UNDER_REVIEW`, and one `READY_TO_FINALIZE`. The finalized fixture is the only `COMPLETED` Meeting and has exactly one official document.

## 7. Manual real-HTTP gate

The manual gate ran against a dedicated Laravel HTTP server backed by a copied SQLite database, not against a Feature Test harness. The transcript used real login sessions for the meeting coordinator and Directorate Director and executed the actual routes.

The verified path was: read execution state, start Meeting, create Minutes draft, save summary/discussion/decision data, reject submission while the Meeting was still `IN_PROGRESS`, end the Meeting, submit the Minutes version, acknowledge the required Directorate review, finalize the Minutes and Meeting, download the official PDF, and reject duplicate finalization. All **14/14 checks passed**. The sanitized transcript is stored in `flow11_manual_api_gate.json` and summarized in `flow11_manual_api_gate.log`.

## 8. Privacy and frozen-flow boundaries

No FLOW-11 table contains biometric template bytes, fingerprint images, raw sensor frames, probe templates, matcher candidate sets, or terminal session secrets. Minutes attendance snapshots contain only participant identity, organizational unit, participant role, display name, and `PRESENT` or `ABSENT` status. The final PDF follows the same boundary.

FLOW-07 schedule mutation and FLOW-08 participant mutation are blocked after execution starts. FLOW-10A attendance is accepted during execution and closed after `HELD` or `COMPLETED`; existing attendance history is never deleted or rewritten. FLOW-10B physical DERMALOG SVT3000 SE integration has not been attempted or claimed. FLOW-11 does not include action-item execution, follow-up automation, or any FLOW-12 scope.

## 9. Approval boundary

The implementation and verification gates are complete. The formal release decision remains external to this run:

> **FLOW-11: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.**

No automatic `APPROVED / FROZEN` status has been applied. FLOW-01 through FLOW-10A remain frozen, FLOW-10B remains deferred pending physical device access, and no subsequent workflow has started.
