# FLOW-12 Prompt 12 implementation checklist

Source: user attachment `/home/ubuntu/upload/pasted_content.txt`, AMMS Prompt 12, Decision Follow-up & Action Item Execution.

## Boundary and source of truth

FLOW-12 starts only from a Meeting with `status=COMPLETED`, the exact current Minutes root with `status=FINALIZED`, and its exact finalized immutable Minutes Version. It must never use drafts, returned versions, historical superseded versions, arbitrary Meeting text, or mutate Meeting status, Minutes status/current version, or documentary Decision fields (`decision_number`, `decision_text`, `responsible_unit_id`, `responsible_employee_id`, `target_date`, `notes`). FLOW-10B stays deferred; no FLOW-13.

## Root and Action Item domain

Add one idempotent `meeting_decision_followups` root per finalized Decision, linked immutably to Meeting, Minutes, finalized version, and Decision. Statuses: `UNCLASSIFIED`, `NO_ACTION_REQUIRED`, `ACTIVE`, `CLOSED`. No-action classification requires a reason; action-required becomes ACTIVE once an Action Item exists; closure requires no-action or all Action Items terminal, with at least one item for action-required decisions.

Add `meeting_action_items`, always owned by exactly one Decision Follow-up. Fields include deterministic `sequence_number`, title/description, responsible unit, optional assigned employee, statuses `OPEN`, `IN_PROGRESS`, `BLOCKED`, `PENDING_VERIFICATION`, `COMPLETED`, `CANCELLED`, progress 0–100, operational due date, server timestamps, submit/complete/cancel actors, cancellation reason, creator, and lock version. One Decision may have multiple items; display numbers are deterministic, e.g. `DEC-003-A01`.

Add append-only `meeting_action_item_updates` with types `PROGRESS`, `BLOCKED`, `UNBLOCKED`, `COMPLETION_SUBMITTED`, `COMPLETION_RETURNED`, `COMMENT`. Add controlled `meeting_action_item_extension_requests` with one pending request per item, mandatory reason, old/new dates, request/review data, and `PENDING/APPROVED/REJECTED/WITHDRAWN`. Add append-only assignment history with old/new unit/employee and reason. Add private evidence metadata storage if implemented; no public URLs, policy-checked downloads, configurable size/MIME, no binary bytes in audit, and evidence survives rejection.

## Governance and lifecycle

Flow owner derives from originating flow: FLOW-01 local Meeting Coordinator; FLOW-02/03 requester or lead Coordinator; FLOW-04/05/06 Executive Secretary. SYSTEM_ADMIN is not automatically business owner. Assigned employees may view/start/progress/block/submit; unit Directors may manage within scope and reassign inside unit; Follow-up Owner governs initialization/classification/creation/assignment changes/extensions/verification/cancellation/closure.

Lifecycle: `OPEN→IN_PROGRESS→PENDING_VERIFICATION→COMPLETED`; `IN_PROGRESS↔BLOCKED`; completion return `PENDING_VERIFICATION→IN_PROGRESS`; administrative cancel `OPEN/IN_PROGRESS/BLOCKED→CANCELLED`; no reopening completed items. Completion submission requires 100% progress and note; only Follow-up Owner/authorized verifier accepts or returns with reason. `due_date` is operational and may default from Decision target date but must never mutate the Decision. Overdue and due-soon are derived; never persist `OVERDUE`.

Use explicit Actions: `InitializeMeetingDecisionFollowUp`, `ClassifyMeetingDecision`, `CreateMeetingActionItem`, `StartMeetingActionItem`, `RecordActionItemProgress`, `MarkActionItemBlocked`, `UnblockActionItem`, `ChangeActionItemAssignment`, `RequestActionItemDueDateExtension`, `ReviewActionItemDueDateExtension`, `SubmitActionItemCompletion`, `VerifyActionItemCompletion`, `ReturnActionItemCompletion`, `CancelMeetingActionItem`, `CloseDecisionFollowUp`. Preserve Controller → FormRequest → Policy → Action → invariants → transaction/row lock → Model → audit/notification → Resource.

Permissions must combine permission + AMMS scope + resource + governance + state. Additive permissions include `decision.followup.*` and `action-item.*`; SYSTEM_ADMIN cannot complete/verify/change Directorate/approve extensions/cancel without explicit operational governance.

## API and UI

Add meeting-scoped `/api/meetings/{meeting}/follow-up` GET and initialize POST; decision classification and Action Item creation endpoints; global `/api/action-items` and detail; start/progress/block/unblock/assignment/extensions; extension approve/reject; completion submit/verify/return; cancel; evidence download. Use existing naming conventions and Resources.

Add Persian/Dari RTL `/meetings/:id/follow-up`, `/action-items`, `/action-items/:id`. Show Meeting/Minutes/final version, decision context, follow-up summary, Action Item cards/details, responsible unit/employee, due/overdue derived state, progress, blocked state, history, evidence, extension and verification state. Frontend filters are not security. Reuse existing axios/auth and RTL shell patterns.

## Notifications, audit, migration, tests

Reuse `amms_notifications` for in-app notifications only; no SMS/WhatsApp/new email provider. Audit events are immutable and include initialization/classification/creation/start/progress/block/unblock/assignment/extension/completion/cancel/closure; never log evidence bytes. All migrations are additive, MariaDB-compatible, explicit short constraint/index names, and never reset/drop/rewrite existing data.

Required tests cover initialization source gates/idempotency/concurrency, classifications, creation/assignment, lifecycle/progress/blocking, completion verification separation, extensions, derived overdue, assignment/cancellation, closure, FLOW-01..06 ownership, frozen FLOW-11 boundary, evidence security, and MariaDB races: initialization, duplicate sequence creation, progress-vs-cancel, completion-vs-reassignment, concurrent verification, extension-vs-completion, overdue-vs-completion. Required manual scenarios: FLOW-03 full completion return/resubmit/verify, extension, blocked/overdue, FLOW-05/06 governance, no-action, cross-unit reassignment. Final report remains `VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL`.
