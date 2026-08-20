# FLOW-12 Additive Schema and Governance Design

## Boundary

FLOW-12 operates only on `meetings.status = COMPLETED`, `meeting_minutes.status = FINALIZED`, and `meeting_minute_versions.id = meeting_minutes.current_version_id`. All source foreign keys use restrictive deletion. The finalized Decision table is never altered.

## Tables

| Table | Purpose | Key constraints |
|---|---|---|
| `meeting_decision_followups` | One operational root for each finalized Decision | Unique `meeting_decision_id`; immutable source ids; status `UNCLASSIFIED`, `NO_ACTION_REQUIRED`, `ACTIVE`, `CLOSED`; no-action reason required by Action |
| `meeting_action_items` | Operational deliverables under one Follow-up root | Unique `(decision_followup_id, sequence_number)`; responsible unit required; status and progress invariants enforced by Actions; `lock_version` for optimistic conflict detection |
| `meeting_action_item_updates` | Append-only progress/block/completion history | No update/delete APIs; typed updates; optional update-level evidence link |
| `meeting_action_item_extension_requests` | Controlled due-date changes | One nullable unique pending marker per Action Item; only owner reviews; original Decision target date untouched |
| `meeting_action_item_assignment_history` | Append-only responsibility changes | Old/new unit and employee, mandatory reason, actor and server timestamp |
| `meeting_action_item_evidence` | Private evidence metadata | No public URLs; policy-checked download; metadata only in audit; evidence retained after completion return |

## Authorization

Permissions are additive and mapped through Spatie roles; every operation additionally checks active user, Meeting resource visibility, organization/unit scope, Follow-up Owner or responsible actor governance, and Action Item state. SYSTEM_ADMIN receives read/infrastructure access only and is not a business owner by default.

| Permission family | Main roles |
|---|---|
| `decision.followup.view`, `decision.followup.initialize`, `decision.followup.classify` | Meeting Coordinator, Executive Secretary; read access for scoped Directors/participants |
| `action-item.create`, `action-item.assign`, `action-item.change-unit`, `action-item.extension.review`, `action-item.verify-completion`, `action-item.cancel` | Follow-up Owner governance; permissions are necessary but never sufficient |
| `action-item.view`, `action-item.start`, `action-item.progress`, `action-item.block`, `action-item.submit-completion`, `action-item.extension.request` | Assigned employee and appropriately scoped responsible-unit actors |
| `action-item.evidence.view`, `action-item.evidence.upload` | Assigned employee, responsible unit actors, Follow-up Owner |

## State rules

Action Item lifecycle is `OPEN → IN_PROGRESS → PENDING_VERIFICATION → COMPLETED`, with `IN_PROGRESS ↔ BLOCKED`, completion return `PENDING_VERIFICATION → IN_PROGRESS`, and administrative cancellation from `OPEN`, `IN_PROGRESS`, or `BLOCKED` to `CANCELLED`. `COMPLETED` and `CANCELLED` are terminal. Progress is 0–100; `PENDING_VERIFICATION` and `COMPLETED` require 100. Overdue is derived from due date and non-terminal status; no `OVERDUE` database status exists.

## Owner mapping

FLOW-01 uses the local organizing-unit Meeting Coordinator. FLOW-02 and FLOW-03 use the lead/requesting Meeting Coordinator. FLOW-04, FLOW-05, and FLOW-06 use an Executive Secretary scoped to the authority-office unit. Authority Office and High Authority are not automatically owners.

## Migration and concurrency

Migrations are additive, use short explicit constraint/index names, and are safe on existing SQLite and MariaDB data. Initialization and all state-changing Actions use transactions and `lockForUpdate()` on the Meeting, Follow-up, or Action Item. Unique constraints protect roots and Action Item sequences; a nullable unique pending marker protects concurrent extension requests. No `migrate:fresh`, table drops, finalized Decision updates, or Meeting recreation are permitted.
