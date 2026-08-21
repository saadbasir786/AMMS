# MASTER BROWSER E2E & ROLE-BASED UX ACCEPTANCE

## Environment

The acceptance environment used the real React frontend at `http://localhost:5173`, the real Laravel HTTP API at `http://127.0.0.1:8000`, and an isolated MariaDB/InnoDB database named `amms_browser_e2e`. The browser was Chromium in the sandbox acceptance environment. No SQLite database, mocked API, direct Action invocation, or direct database mutation was used as the business acceptance path.

## Roles tested

The representative browser actors exercised were the Technology Executive Management requester, the Technology Director, and the Technology meeting coordinator. The coordinator and Director login, dashboard, navigation, request, approval, and scope behavior were inspected. SYSTEM_ADMIN, Employee, Authority Office, Executive Secretary, and High Authority full browser journeys remain incomplete in this phase.

## Journeys tested

A real FLOW-01 `INTERNAL_DIRECTORATE` journey was executed through request creation, Persian/Dari form entry, Technology-scoped participant selection, draft submission, Director approval, and immutable timeline inspection. The request was persisted as `MR-2026-00001` and reached `APPROVED` through the real UI. The final coordinator-owned scheduling and Meeting-creation steps could not be completed through normal visible UI navigation because the coordinator’s scoped request and incoming-request lists contained no link to the approved request. The server confirmed that the coordinator can read the request, but normal UI discoverability is missing. The request remained `APPROVED` and no Meeting was created.

The broader FLOW-02 through FLOW-14 browser journeys, role matrix, validation, export, terminal-management, attendance, exception, and responsive module gates were not marked complete because the required FLOW-01 closure itself remained blocked and the browser session/deep-link harness repeatedly reset session state or failed screenshot capture.

## Role-UX cleanup summary

Three incorrect presentation behaviors were found and fixed in the frontend only. The Director-visible coordinator-only `شروع زمان‌بندی` action is now role-gated to `MEETING_COORDINATOR`; the backend remains authoritative. FLOW-01 chairperson and final-participant choices are now limited to the requesting Directorate and subordinate units. The login form no longer displays seeded development credentials by default and authentication tokens are session-scoped. Organization-wide Reports navigation is hidden from Employee accounts, and System Admin is no longer granted a blanket business-navigation bypass by the generic role gate.

The permitted frontend changes did not modify workflow states, policies, permissions, scope rules, lifecycle invariants, Meeting identity, or High Authority/Executive Secretary governance. The frontend production build passed with 1,866 modules transformed.

## Acceptance counts

| Measure | Count | Result |
|---|---:|---|
| Browser scenarios | 1 representative FLOW-01 journey | Incomplete |
| Passed browser scenarios | 0 complete end-to-end | — |
| Failed/incomplete browser scenarios | 1 | FLOW-01 closure blocked |
| UI issues found | 4 | 3 fixed, 1 unresolved |
| UI issues fixed | 3 | Frontend-only |
| Unresolved issues | 2 | One HIGH acceptance blocker and one browser-harness UX limitation |

## Backend authorization boundary

A supplementary direct HTTP check confirmed that the Director’s attempt to invoke the coordinator-owned `start-scheduling` operation remains `403 FORBIDDEN`. This check was used only to verify that the frontend visibility fix did not weaken backend authorization; it was not substituted for browser business acceptance.

## Frozen-contract blocker

There is no changed frozen backend contract. The unresolved HIGH finding is a role-based UX/discoverability blocker: a coordinator with valid backend read access has no normal visible navigation path to the approved request requiring scheduling. The required FLOW-01 journey cannot be completed end-to-end through the browser UI until that presentation/resource-discoverability gap is remediated and retested.

The browser harness also showed repeated session resets on external navigation and intermittent screenshot-capture failures. These were recorded as acceptance-environment limitations and did not mutate the request or create duplicates.

## Change boundaries

Production/business code changed: **NO**. Frontend code changed: **YES**, limited to permitted presentation and UX corrections. Backend code changed: **NO**. Business semantics changed: **NO**. Authorization semantics changed: **NO**. FLOW-10B was touched: **NO**. FLOW-15 was started: **NO**.

## Final status

> **MASTER BROWSER E2E & ROLE-BASED UX ACCEPTANCE**
> **VERIFICATION INCOMPLETE**
> **REMEDIATION REQUIRED**

The phase is not approved, frozen, or complete. The unresolved coordinator discoverability blocker must be fixed and the affected FLOW-01 browser journey must be rerun before any explicit release approval can be considered.
