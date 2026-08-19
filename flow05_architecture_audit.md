# FLOW-05 Architecture Audit

## Scope

Prompt 05 introduces `MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY` while Foundation and FLOW-01 through FLOW-04 remain frozen. The workflow requires exactly one `REQUESTER` Directorate, at least one `PARTICIPATING` Directorate, and one configured High Authority represented through the existing authority context architecture.

## Existing reusable infrastructure

The project already contains normalized `meeting_request_units`, per-unit review records, meeting-unit persistence, schedule proposal history, participant nominations and confirmations, authority configuration and holder resolution, authority participant handling, immutable audit actions, row locking, resources, and the FLOW-03/FLOW-04 frontend workspaces.

## Required FLOW-05 additions

The central status enum needs FLOW-05-specific states only where semantics differ from frozen workflows: `SUBMITTED_TO_PARTICIPATING_DIRECTORATES`, `UNDER_PARTICIPATING_REVIEW`, `DIRECTORATE_ACTION_REQUIRED`, `DIRECTORATES_APPROVED`, and `REJECTED_BY_PARTICIPATING_DIRECTORATE`. Existing FLOW-04 authority states and scheduling states are reused.

The existing `meeting_request_units` role vocabulary is `REQUESTER` plus `TARGET` for FLOW-03. FLOW-05 must use `REQUESTER` plus `PARTICIPATING`; therefore the implementation must either safely extend the role constraint and all relevant helpers or provide a FLOW-05-specific role-aware layer without changing frozen FLOW-03 semantics. High Authority and Authority Office must never be stored as meeting units.

The existing FLOW-04 authority schedule acknowledgement is requester-only. FLOW-05 needs a normalized per-formal-Directorate acknowledgement matrix for the current authority proposal. It also needs FLOW-03-style per-unit participant nomination and confirmation plus FLOW-04 authority-side participants and `meeting_authority_context` at finalization.

## Planned implementation boundaries

Actions remain separate. No `Flow05Service` or generic status PATCH will be introduced. The controller will expose explicit routes for creation, requester approval, per-participating-unit review/approval/return/rejection, resubmission, Authority Office hand-off/review/return/resubmission/forwarding, High Authority decision, authority scheduling, per-unit schedule acknowledgement, alternative request, per-unit participant management, authority participant management, and finalization.

Policies will require capability, organizational scope, resource membership, current state, and active account. The High Authority will continue to resolve by configured position and active employee holder. Finalization will run inside a transaction with a request row lock, create one meeting, create one meeting authority context, create one meeting unit for every formal Directorate, copy final participants, and force the High Authority as chairperson.

## Release gates

Prompt 05 requires a dedicated automated matrix covering creation/cardinality, scope, per-unit review, correction rules, Authority Office, High Authority decision, multi-unit schedule acknowledgement, participant ownership, finalization invariants, audit metadata, inactive users, regression, and concurrency. FLOW-05 must not be marked approved/frozen until its positive UI scenario, negative authorization scenarios, MariaDB concurrency probe, full regression, production build, and all Definition of Done items pass.
