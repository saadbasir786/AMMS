# FLOW-06 Architecture Notes

## Contract interpretation

FLOW-06 is `HIGH_AUTHORITY_INITIATED`. The High Authority is the originator and issuance is the authoritative decision. There is no requester Directorate, no requester approval, and no Directorate approve/reject semantics. Formal Directorates only acknowledge receipt, request clarification, coordinate participants, acknowledge schedule, request alternatives, and confirm participants.

## Existing extension points

The shared `MeetingRequest` aggregate already supports `flow_type`, `requesting_unit_id`, `units`, `unitReviews`, `scheduleProposals`, `authorityContext`, per-unit schedule acknowledgements, participant confirmations, authority participant confirmations, immutable `actions`, and one-to-one `meeting`. `meeting_requests.requesting_unit_id` is currently non-nullable and requires an additive nullable migration for FLOW-06.

The existing `organization_authorities`, `meeting_request_authority_contexts`, and `meeting_authority_contexts` models/tables already store position-based authority configuration and holder snapshots. FLOW-06 should extend the request authority context and add a versioned directive structure rather than create a second authority model.

`meeting_request_units` supports formal unit rows. FLOW-06 must create only `PARTICIPATING` rows and no `REQUESTER` row. `meeting_request_unit_reviews` is semantically neutral and can be extended or reused for per-unit directive response states, but the current FLOW-05 actions assume approval semantics, so FLOW-06 needs distinct Actions and status checks.

The central `MeetingRequestStatus` enum owns all workflow statuses and legal transitions. Existing FLOW-01 through FLOW-05 statuses must remain unchanged. FLOW-06 needs additive statuses: `PENDING_HIGH_AUTHORITY_ISSUANCE`, `RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION`, `ISSUED_BY_HIGH_AUTHORITY`, `DISTRIBUTED_TO_DIRECTORATES`, `UNDER_DIRECTORATE_COORDINATION`, `DIRECTORATE_CLARIFICATION_REQUIRED`, `PENDING_HIGH_AUTHORITY_REISSUANCE`, and `DIRECTORATES_ACKNOWLEDGED`. Existing `AUTHORITY_SCHEDULING`, `SCHEDULE_CONFIRMED`, and `CONVERTED_TO_MEETING` can be reused only where semantics match and must receive FLOW-06-specific guards.

`MeetingRequestPolicy` currently has explicit flow-specific methods for FLOW-04 and FLOW-05. FLOW-06 requires new methods for prepare, update, submit-for-issuance, return/resubmit, issue, distribute, view, per-unit coordination, acknowledge, clarification request/response, revision/reissue, schedule, schedule acknowledgement/alternative, unit participants, authority participants, and finalize. The generic `view()` and `index()` paths currently assume a requester unit and must be extended for authority-office, High Authority, creator, and distributed participating-unit visibility while denying Directorate visibility before distribution.

`routes/api.php` uses explicit REST action routes. FLOW-06 should add a dedicated controller and explicit routes under `/meeting-requests/authority-initiated` without generic status PATCH. The existing FLOW-04/05 controllers and Actions provide the preferred controller → policy → Action → transaction pattern.

The frontend `main.tsx` centralizes status labels, navigation, request creation buttons, detail dispatch, scheduling dispatch, and inbox routes. It currently assumes `requesting_unit` exists in list/detail displays. FLOW-06 requires additive status labels, a create route, an authority-initiated inbox, a dedicated detail component, a dedicated scheduling component or shared scheduling adapter, and requester-safe rendering when `requesting_unit` is null. Existing FLOW-04/05 components are visual analogues only; their requester approval semantics must not be copied.

## Required data design

Use a nullable `requesting_unit_id` migration that preserves existing rows. Add a versioned directive table or equivalent immutable version fields with request id, version, subject/purpose/directive/outcome snapshot, participating-unit snapshot, authority employee/position/issuer/issued-at, and current-version markers. Add explicit per-unit response/version linkage so old acknowledgements are invalid after reissuance. Add clarification records or a normalized extension that preserves question, response, actor, timestamps, and version.

The finalization path must row-lock the request, validate current directive issuance/version, all current unit acknowledgements, confirmed schedule and current proposal acknowledgements, participant confirmations, authority participant confirmation, active holder, mandatory High Authority participant, no requester unit, participating-only meeting units, one authority context, and one meeting.

## Required implementation order

1. Add safe migration(s), enum states, models/relations, and permission seeds.
2. Add dedicated FLOW-06 Actions and support trait with row locks and immutable audit events.
3. Add Policy methods, controller, routes, request validation, resource fields, and visibility/inbox queries.
4. Add frontend create/detail/inbox/scheduling/participant panels and RTL terminology.
5. Add behavioral tests, 70-case contract coverage or the exact contract count derived from the implementation, dedicated reissuance tests, MariaDB concurrency probes, and regression checks.
6. Perform browser verification only after backend and build gates are green; do not mark FLOW-06 approved before all gates including nine-account UI, negative A–L, reissuance, concurrency, and FLOW-01–05 regression pass.

## Explicit exclusions

Do not modify frozen flow semantics. Do not add cancellation, delegation, acting authority, attendance, biometrics, minutes, decisions, action items, SMS/email, or a generic status PATCH.
