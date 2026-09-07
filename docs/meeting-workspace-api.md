# Meeting workspace API contracts

This reference covers the reschedule, participant-change and exception workspaces. Authorization remains governed by the [authorization charter](../.agents/rules/roles-permissions-and-scope.md). A workspace link grants navigation only; writes recheck their existing Policies and Action invariants.

## Pagination and current state

History requests accept `page` (integer, at least 1) and `per_page` (integer, default 15, clamped to 1–50). Histories use descending version/ID order. Current versions and active cases remain independent of the selected history page.

| Endpoint | Response contract |
| --- | --- |
| `GET /api/meetings/{meeting}/reschedule` | Existing `data` workspace; `schedule_versions` and `actions` are page arrays, with adjacent `schedule_versions_meta` and `actions_meta`. Active case includes the active proposal and open alternative requests. |
| `GET /api/meetings/{meeting}/schedule-history` | Existing named arrays for versions, cases and actions; each has an adjacent `<field>_meta`. Use the same page parameters to traverse these collections. |
| `GET /api/meetings/{meeting}/participant-change` | Existing `data` workspace; `participant_versions` and `participant_actions` are paginated with adjacent metadata. `employee_options` is an initial preview of at most 15 candidates; use the candidate endpoint for further results. |
| `GET /api/meetings/{meeting}/participant-history` | Named `participant_versions` and `actions` page arrays with adjacent metadata. |
| `GET /api/meetings/{meeting}/participant-change/employees` | Resource collection `data`, `meta`, `links`; optional `search` (up to 200 characters) matches first name, last name or employee number inside the actor's candidate scope. |
| `GET /api/meetings/{meeting}/exceptions` | Resource collection `data`, `meta`, `links`; top-level `meeting` contains identity, status, source-request status and `current_schedule_version`. |

Pagination metadata contains `current_page`, `last_page`, `per_page`, `total`, `from` and `to`. Empty pages preserve metadata. Scope applies before filtering/pagination; counts never include another meeting's records.

## Action capabilities

- `reschedule.propose` reflects either the existing peer proposal ability or the authority proposal ability. The appropriate write endpoint still enforces its distinct policy.
- Exception cases expose `action_capabilities`: `edit`, `submit`, `withdraw`, `reject`, `apply`, `resume`.
- Each exception review exposes its own `action_capabilities`: `acknowledge`, `approve`, `correction`, plus `unit_name`. Never reuse a meeting-wide review boolean for every row.
- Capability fields are advisory snapshots, derived from existing authorization and action preconditions. The server rechecks writes; a stale UI can still receive an authorization or state error.
- Raw Eloquent participant relationships use snake_case (`owner_unit`, `old_employee`, `new_employee`). Current version members carry the authoritative participant ownership fields, including formal-unit ownership for subunit employees.

## Exception editing and resumption

Create through `POST /api/meetings/{meeting}/exceptions`. For `RESUMPTION`, send `replacement_expected: true`; for `POSTPONEMENT`, send `false`. Dates use Gregorian `YYYY-MM-DD`, times use `HH:mm`.

A correction review uses `POST /api/meeting-exception-reviews/{review}/correction` with a reason. The preparer then calls `PUT /api/meeting-exceptions/{case}/draft` with the corrected reason and preserved optional version fields, creating a new draft version. Submit that draft using `POST /api/meeting-exceptions/{case}/submit`. Directly resubmitting a returned, already submitted version is rejected; old review rows remain intact.

Once a resumption case is ready, call `POST /api/meeting-exceptions/{case}/resume` with `meeting_date`, `start_time`, `end_time` and `location`. The ordinary `/apply` endpoint handles the other case types. The existing rejection, withdrawal and per-review approval endpoints retain their authority boundaries.

Validation failures must preserve form contents. Confirmation callbacks may return `false` to keep the dialog open while displaying the server error.

## Verification

- Backend: `php artisan test --filter='MeetingExceptionTest|PerFlowExceptionGovernanceTest|MeetingParticipantChangeTest|MeetingRescheduleTest|RescheduleAndParticipantChangeAuditTest'` from `backend/`, using the isolated test database.
- Frontend: `npm run test:workspaces` and `npm run build` from `frontend/`.
- Frontend tests exercise rendered components and request contracts; they are not interactive browser end-to-end tests.
