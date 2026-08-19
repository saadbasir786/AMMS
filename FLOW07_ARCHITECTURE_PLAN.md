# AMMS FLOW-07 Architecture and Execution Plan

## Boundary

FLOW-07 is a post-creation Meeting capability. It begins only for an existing official Meeting whose originating MeetingRequest is already `CONVERTED_TO_MEETING` and whose Meeting status is `SCHEDULED`. It never changes `MeetingRequest.flow_type`, reopens the request, creates another Meeting, or introduces a `RESCHEDULING` Meeting status.

The current confirmed schedule remains authoritative while a case is open. Only date, start time, end time, location, and schedule metadata may change. Subject, purpose, formal units, participants, authority context, chairperson, agenda ownership, and approval decisions remain immutable through FLOW-07.

## Additive schema

The migration will add the following structures without resetting or rewriting existing Meeting data:

| Structure | Purpose | Key constraints |
|---|---|---|
| `meeting_schedule_versions` | Immutable schedule history | Unique `(meeting_id, version_number)`; version 1 is created by idempotent backfill; prior versions are never updated. |
| `meetings.current_schedule_version_id` | Pointer to the current confirmed schedule | Nullable only during migration/backfill; populated for every valid scheduled Meeting. |
| `meetings.active_reschedule_case_id` | One active case pointer | Used with row locking to enforce one active case per Meeting. |
| `meeting_reschedule_cases` | Formal reschedule lifecycle | Stores baseline version, initiator, unit, reason, governance mode, status, and confirmation metadata. |
| `meeting_reschedule_proposals` | Append-only peer or authority proposals | `ACTIVE`, `SUPERSEDED`, or `ACCEPTED`; proposal history is never overwritten. |
| `meeting_reschedule_acknowledgements` | Per-proposal, per-formal-unit acknowledgement | Unique `(proposal_id, organizational_unit_id)`; acknowledgements never transfer to a revised proposal. |
| `meeting_reschedule_alternative_requests` | Formal alternative requests | `OPEN` blocks confirmation; `RESOLVED` permits the revised official proposal to proceed. |
| `meeting_actions` | Meeting-level immutable audit | Records case creation, proposal activity, acknowledgement, alternative, readiness, and final reschedule events. |

Existing schedule columns on `meetings` remain the current confirmed snapshot. On confirmation, the new schedule version, current pointer, and existing Meeting fields are updated atomically in one transaction.

## Backfill

A dedicated idempotent backfill action/script will scan official Meetings. For every valid Meeting without a current version, it creates version 1 from the existing date, time, and location without changing those values, then sets `current_schedule_version_id`. Existing invalid schedule rows are reported with their Meeting IDs and are not assigned invented values. Running the backfill twice produces no duplicate versions.

## Governance resolver

`ScheduleGovernanceResolver` will map the originating `MeetingRequest.flow_type` to a centralized governance mode and capability set:

| Originating flow type | Governance mode | Required acknowledgement set | Official proposer/final confirmer |
|---|---|---|---|
| `INTERNAL_DIRECTORATE` | `INTERNAL_SINGLE_UNIT` | Existing formal unit | Authorized existing coordinator/scheduler according to current scope rules. |
| `INTER_DIRECTORATE` | `PEER_MULTI_UNIT` | All formal Meeting units | Authorized formal-unit coordinator; peer counter-proposals are allowed. |
| `MULTI_DIRECTORATE` | `PEER_MULTI_UNIT` | All formal Meeting units | Authorized lead/coordinator; all current proposal acknowledgements are required. |
| `DIRECTORATE_WITH_HIGH_AUTHORITY` | `AUTHORITY_CONTROLLED` | Formal Meeting units; no fake Authority Office peer | Executive Secretary/Authority Scheduler publishes official proposal and confirms. |
| `MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY` | `AUTHORITY_CONTROLLED` | Requesting plus participating formal Meeting units | Executive Secretary/Authority Scheduler publishes official proposal and confirms. |
| `HIGH_AUTHORITY_INITIATED` | `AUTHORITY_CONTROLLED` | Participating formal Meeting units only | Executive Secretary/Authority Scheduler publishes official proposal and confirms. |

The resolver is the only place that decides who may propose, acknowledge, request an alternative, and confirm. Controllers and React components consume resolver output rather than scattering flow-type conditionals.

## Actions and safeguards

The implementation will use explicit Actions: `CreateMeetingRescheduleCase`, `CreatePeerRescheduleProposal`, `CreateAuthorityRescheduleProposal`, `AcknowledgeMeetingRescheduleProposal`, `RequestMeetingScheduleAlternative`, `ResolveMeetingScheduleAlternative`, and `ConfirmMeetingReschedule`. Shared support will be split into `ScheduleGovernanceResolver`, `ScheduleConflictGuard`, and `ScheduleVersionService`.

Every action enforces active account and employee state, permission, formal-unit or authority scope, originating governance, resource state, mandatory reason, valid future schedule, end time after start time, no-change protection, and started-Meeting protection. Direct generic schedule mutation will be rejected or removed from normal application routes.

Final confirmation will lock the Meeting row, active case, and relevant employee records in sorted employee-ID order inside a MySQL/InnoDB transaction. It will verify the baseline pointer, active proposal, current acknowledgements, absence of open alternatives, and final conflict state before creating the new version and updating the Meeting snapshot. A stale baseline returns `SCHEDULE_BASELINE_CHANGED`; duplicate finalization returns the appropriate already-confirmed/state error.

Conflict checks run at proposal creation and again at final confirmation. They use half-open interval semantics (`new_start < existing_end && new_end > existing_start`) so adjacent meetings do not conflict. High Authority, chairperson, and final participants are checked against other official scheduled Meetings. Conflict responses disclose only information permitted by the actor's visibility.

## API and UI

Explicit Meeting routes will be added under `/api/meetings/{meeting}/reschedule...` for case creation, peer/authority proposal creation, acknowledgement, alternative request/resolution, confirmation, and schedule history. No generic `PATCH /meetings/{id}/schedule` endpoint will be introduced.

The Meeting resource will expose current schedule version, pending case badge, schedule history, proposals, acknowledgements, alternatives, and meeting actions without changing existing fields. The frontend will add `/meetings/:id/reschedule` and extend Meeting Detail with the current schedule card, version label, pending badge, immutable history, before/after diff, governance-specific workspace, acknowledgement matrix, alternatives, conflict results, and final confirmation controls in Persian/Dari RTL.

## Verification gates

Before FLOW-07 can be declared APPROVED/FROZEN, the project must pass additive migration and idempotent backfill tests, all six governance modes, proposal history and acknowledgement invalidation, alternative/revision flow, no-change and started-Meeting protections, conflict and privacy tests, stale baseline protection, duplicate confirmation concurrency, shared-participant conflict concurrency, acknowledgement uniqueness, Negative Matrix A–L, peer and authority browser scenarios, Meeting identity invariants, all FLOW-01 through FLOW-06 regressions, MariaDB/InnoDB verification, and the production frontend build.

The implementation must stop after FLOW-07 verification and must not begin another workflow until the user explicitly approves the result.
