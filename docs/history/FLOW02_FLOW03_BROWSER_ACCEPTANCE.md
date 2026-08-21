# FLOW-02 + FLOW-03 Master Browser Acceptance

## Environment

The acceptance was executed against the real Persian/Dari RTL React UI at `http://127.0.0.1:5173`, the real Laravel HTTP API at `http://127.0.0.1:8000`, and the isolated MariaDB/InnoDB database `amms_browser_e2e`. No mocked API, SQLite database, direct Action invocation, or direct database mutation was used for the accepted business transitions. The frontend production build completed successfully with `tsc -b && vite build`, transforming 1,866 modules.

## Actors

| Responsibility | Browser actors used |
|---|---|
| Requester Executive / scheduler | `tech.exec@example.test` — Technology Executive Management |
| Requester Director | `tech.director@example.test` — Technology Director |
| Requester coordinator | `tech.coordinator@example.test` — Technology Meeting Coordinator |
| FLOW-02 target actors | `finance.director@example.test`, `finance.exec@example.test` |
| FLOW-03 target actors | `finance.director@example.test`, `finance.exec@example.test`, `hr.director@example.test`, `hr.exec@example.test` |
| Negative actor | `hr.director@example.test` against converted FLOW-02 |

All role switches were performed through the real login/logout UI. The blank login form after logout confirmed that credentials and the previous user identity were not retained in the shared-auth smoke.

## FLOW-02 — INTER_DIRECTORATE

FLOW-02 used `MR-2026-00002`, `flow_type=INTER_DIRECTORATE`, with exactly two formal units: Technology as `REQUESTING` and Finance as `TARGET/PARTICIPATING`. Technology Executive created and submitted the Persian/Dari request through the browser. Technology Director independently approved the requester side. Finance Executive discovered the request through the incoming queue, started Finance review, and Finance Director approved the target side. The request reached `BILATERAL_APPROVED` without either Directorate acting for the other.

The real scheduling workspace then recorded the Technology coordinator’s proposal, Finance’s acceptance, Technology and Finance participant nominations, and both participant-list confirmations. The official Meeting was created through the visible finalization form with Ahmad Ahmadi as chairperson, `تالار کنفرانس مرکزی` as location, and a Persian/Dari agenda.

The authoritative final state is `MR-2026-00002 → CONVERTED_TO_MEETING`, exactly one Meeting `MT-2026-00002`, and Meeting status `SCHEDULED`. The browser Meetings list and reopened detail showed the same Meeting number, schedule, location, chairperson, participants, and completed timeline. The duplicate Meeting count is zero.

## FLOW-03 — MULTI_DIRECTORATE

FLOW-03 used `MR-2026-00003`, `flow_type=MULTI_DIRECTORATE`, with Technology as the requester and Finance plus HR as independent participating target units. Technology Executive created the request through the `جلسه چندریاستی` form and selected both target Directorates. Technology Director approved requester-side governance.

Finance Executive discovered and started only Finance review through its incoming queue; Finance Director independently approved Finance. HR Executive discovered and started only HR review through its own incoming queue; HR Director independently approved HR. The UI showed Finance approved while HR remained pending until HR’s separate approval. Only after both target units approved did the request reach `MULTILATERAL_APPROVED`.

Technology Meeting Coordinator started multi-scheduling. Technology proposed `2026-08-25`, `10:00–11:00`, at `تالار کنفرانس مرکزی`. Finance Executive and HR Executive independently accepted the same active proposal through their owned-unit controls. Technology, Finance, and HR then each nominated and confirmed one unit-scoped participant: Ahmad Ahmadi (`employee_id=1`) for Technology, Zalmay Hakimi (`employee_id=5`) for Finance, and Hassan Ahmadi (`employee_id=7`) for HR. The workspace showed all three lists as `فهرست تأیید شده` before final schedule confirmation.

Technology Executive confirmed the final multilateral schedule and created the official Meeting through the browser. The form selected Ahmad Ahmadi as chairperson, retained `تالار کنفرانس مرکزی`, normalized the end time to `11:00`, and recorded the Persian/Dari agenda. The authoritative final state is `MR-2026-00003 → CONVERTED_TO_MEETING`, exactly one Meeting `MT-2026-00003`, and Meeting status `SCHEDULED`. The Meeting detail reopened from the normal Meetings list with Technology `REQUESTING`, Finance `PARTICIPATING`, HR `PARTICIPATING`, the same schedule, the same participants, and the full immutable timeline. The duplicate Meeting count is zero.

## Negative authorization and refresh checks

HR Director’s visible request navigation exposed no FLOW-02/FLOW-03 actionable governance controls. The direct `/meeting-requests/2` resource request returned HTTP 403 `FORBIDDEN` for HR Director, and the supplemental unauthorized `POST /api/meeting-requests/2/start-inter-directorate-scheduling` also returned HTTP 403 `FORBIDDEN` without changing the request. The HR-scoped Meetings page rendered an empty state rather than unrelated records.

Technology Executive’s normal `جلسات` navigation showed `MT-2026-00003` and `MT-2026-00002` as `زمان‌بندی‌شده`. Reopening both details confirmed stable Meeting identity, schedule, location, chairperson, participants, and timeline. No stale approval, scheduling, or participant-confirmation controls appeared after conversion and reopen. Finance Director’s Meetings list correctly remained scoped and empty because that Director was not a formal participant in these test Meetings.

## Role and UX findings

The accepted business semantics, state machines, approval semantics, participant ownership rules, schedule acceptance rules, Meeting identity invariant, organizational scope semantics, and authorization policy were not changed. Backend source code was not changed.

Two non-blocking presentation findings were retained in the audit. First, the FLOW-02 detail exposed a scheduling-start label to Finance Director even though the backend correctly rejected the action with HTTP 403; authorization remained authoritative, but the control visibility should be tightened in a future permitted UI-only pass. Second, an earlier participant selector presentation in the FLOW-02 scheduling workspace exposed a broader employee set than the unit-owned selector contract; the final server-side participant ownership and Meeting participants were correct, and the FLOW-03 workspace visibly filtered employees after selecting each unit owner. These are UX findings, not authorization bypasses or frozen-contract business defects.

During FLOW-03, Technology Coordinator’s first Technology nomination attempt returned the Persian/Dari 403 message `این عملیات برای شما مجاز نیست.` before the already-applied live Spatie permission cache was refreshed. The successful requester Executive path completed the same owned-unit nomination and confirmation without backend source changes. The existing live `MEETING_COORDINATOR` role repair was retained and the live permission cache was flushed; no business or authorization semantics were modified.

## Changed files and semantics

| Area | Result |
|---|---|
| Frontend source | `frontend/src/flow03.tsx` changed: shared Axios token lookup uses `sessionStorage`, and the requester scheduling visibility recognizes the scoped `MEETING_COORDINATOR` role. |
| Shared frontend source | `frontend/src/main.tsx` contains the previously committed `sessionStorage` auth fix; it was not changed in this acceptance commit. |
| Backend source | No files changed. |
| Database prerequisite | Existing live Spatie coordinator permissions were retained; permission cache was flushed only. |
| Business semantics changed | **NO** |
| Authorization semantics changed | **NO** |

## FLOW-01 shared smoke

FLOW-01 was not retested as a full journey, in accordance with the frozen instruction. The shared login/logout, sessionStorage-authenticated API navigation, role switching, and no-stale-user smoke remained functional while FLOW-02 and FLOW-03 were tested. FLOW-01 remains VERIFIED COMPLETE from its prior closure artifact.

## Unresolved blockers and final status

There is no unresolved HIGH or FROZEN-CONTRACT BLOCKER. The two UI findings above are non-blocking and did not permit an unauthorized business transition. The required frontend build passed, both browser journeys reached their authoritative final states, both Meeting identities are unique and stable, and the supplemental negative authorization check returned 403.

> **FLOW-02 + FLOW-03 MASTER BROWSER ACCEPTANCE**  
> **VERIFICATION COMPLETE**  
> **READY FOR FLOW-04 / FLOW-05 / FLOW-06 BROWSER ACCEPTANCE**

This is evidence delivery only; the overall Master Browser phase is not auto-approved, and FLOW-04 has not been started.
