# AMMS FLOW-04 Architecture Plan

## Scope

FLOW-04 introduces only the workflow **DIRECTORATE_WITH_HIGH_AUTHORITY**: one requesting Directorate routes a request through an Authority Office to one configured High Authority. Foundation, FLOW-01, FLOW-02, and FLOW-03 remain frozen and must not receive semantic changes.

## Current architecture findings

The existing backend already centralizes workflow states in `MeetingRequestStatus`, uses dedicated Action classes, applies Spatie permissions plus `amms_role_scopes`, records immutable `meeting_request_actions`, and stores schedule proposals and participant nominations in normalized tables. `meeting_request_units` is appropriate for the single requester row in FLOW-04, but the High Authority must not be represented as a TARGET unit. The current `meetings` table has the one-request/one-meeting uniqueness invariant and can be extended through a separate authority context.

The current seed organization is `اداره نمونه` with type `GOVERNMENT`, three Directorate structures, positions, employees, users, Spatie roles, and scoped role records. FLOW-04 requires additive authority-office and High-Authority organizational data. The exact office and authority labels will come from configuration, not hard-coded names.

## Additive data model

| Structure | Purpose | Key invariants |
|---|---|---|
| `organization_authorities` | Configures one active primary authority per organization, including type, position, office unit, and status. | One active primary configuration per organization; position and office belong to the same organization. |
| `meeting_request_authority_contexts` | Stores one authority snapshot per FLOW-04 request, including authority type, position, office, current holder, decision fields, and office-review fields. | Exactly one context per FLOW-04 request; holder is snapshotted when forwarded. |
| `meeting_authority_schedule_acknowledgements` | Records explicit requester acknowledgement of an official authority proposal. | At most one active acknowledgement per request/proposal/requester unit. |
| `meeting_authority_participant_confirmations` | Records requester-side and authority-side participant confirmation. | One confirmation per request/side; authority side cannot be controlled by requester. |
| `meeting_authority_contexts` | Persists High Authority context on the final Meeting. | Exactly one context per Meeting; High Authority is not a fake meeting unit. |

The existing `meeting_schedule_proposals` table will receive only additive metadata needed for authority semantics, such as proposal type/origin. Existing FLOW-02/FLOW-03 rows will remain valid through nullable defaults.

## State contract

The enum will add only FLOW-04 states: `SUBMITTED_TO_AUTHORITY_OFFICE`, `UNDER_AUTHORITY_OFFICE_REVIEW`, `RETURNED_BY_AUTHORITY_OFFICE`, `PENDING_HIGH_AUTHORITY_DECISION`, `RETURNED_BY_HIGH_AUTHORITY`, `HIGH_AUTHORITY_APPROVED`, `REJECTED_BY_HIGH_AUTHORITY`, and `AUTHORITY_SCHEDULING`. Existing transitions remain unchanged.

The intended lifecycle is:

`DRAFT → PENDING_REQUESTER_APPROVAL → REQUESTER_APPROVED → SUBMITTED_TO_AUTHORITY_OFFICE → UNDER_AUTHORITY_OFFICE_REVIEW → PENDING_HIGH_AUTHORITY_DECISION → HIGH_AUTHORITY_APPROVED → AUTHORITY_SCHEDULING → SCHEDULE_CONFIRMED → CONVERTED_TO_MEETING`.

Return, rejection, material-change, holder-availability, acknowledgement, participant-confirmation, and duplicate-finalization invariants will be enforced by Actions inside transactions with row locks.

## Authorization model

New permissions will be added only for capabilities that do not match existing semantics: authority-office review/forward/return, High Authority decision, authority scheduling, authority-side participant management, and finalization. Policies will require permission, active user, active employee, configured organization authority, valid office or authority scope, request membership, and current workflow state. A role name alone will never authorize a decision.

The High Authority decision actor must be linked to the active employee occupying the configured authority position. The Authority Office may review and forward but cannot approve or reject on behalf of the High Authority. The requester may acknowledge an official proposal but cannot issue or confirm the official Authority calendar.

## Implementation sequence

1. Add incremental migrations, models, relations, enum states, and idempotent seed configuration/accounts.
2. Add dedicated FLOW-04 FormRequests, Policies, Actions, Resources, controller, and explicit API routes.
3. Add RTL creation, layered detail, office inbox, authority decision, scheduler workspace, participant panels, configuration UI, and meeting detail rendering.
4. Add automated tests for the 49 required cases, MariaDB concurrency probes, and regression tests for all frozen workflows.
5. Run the five-account manual scenario and negative tests, then update `VERIFICATION.md` only after every Definition of Done gate passes.

## Explicit non-scope

FLOW-04 does not implement FLOW-05 multiple Directorates plus High Authority, FLOW-06 High-Authority-initiated meetings, delegation, acting authority, attendance, biometric terminals, minutes, decisions/action items, SMS, email, or a generic status update endpoint.
