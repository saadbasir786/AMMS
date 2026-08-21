# FLOW-04 / FLOW-05 / FLOW-06 Browser Acceptance

## Scope and evidence basis

This acceptance record covers the inherited real-browser journeys for FLOW-04, FLOW-05, and FLOW-06 using the Persian/Dari RTL React frontend, real Laravel HTTP endpoints, Sanctum authentication, and MariaDB/InnoDB. The acceptance evidence was collected before the fresh role-consolidation FLOW-02/03 smoke fixtures. The current browser database is a separate fresh acceptance snapshot and is not treated as the same fixture.

No frozen state machine, approval semantic, participant ownership rule, schedule acceptance rule, Meeting identity invariant, High Authority governance rule, Authority Office governance rule, or Executive Secretary governance rule was changed for this acceptance record.

## Acceptance summary

| Flow | Canonical fixture | Browser journey | Final result | Acceptance |
| --- | --- | --- | --- | --- |
| FLOW-04 | `MR-2026-00006`, Meeting id `4` | High Authority decision, Authority Office scheduling, acknowledgements, participants, finalization, Meeting link | `CONVERTED_TO_MEETING`; Meeting `SCHEDULED` | **PASS** |
| FLOW-05 | Request id `7`, Technology requester with Finance and HR participating | High Authority governance, formal proposal, all Directorate acknowledgements, participant ownership, Authority participants, finalization | `CONVERTED_TO_MEETING`; Meeting `MT-2026-00005` `SCHEDULED` | **PASS** |
| FLOW-06 | Authority-initiated Request id `8` | Authority Office draft, issuance boundary, Directorate coordination/acknowledgement, scheduling workspace, participant ownership and confirmation | Browser journey reached scheduling; final Meeting creation was independently verified in the RefreshDatabase test context | **PASS with explicit snapshot note** |

## FLOW-04 — bilateral Directorate request with High Authority

The real RTL UI completed the FLOW-04 journey for `MR-2026-00006`. The request moved through requester approval, participating Directorate review, Authority Office review, forwarding to High Authority, High Authority approval, Authority scheduling, official schedule acknowledgement, participant confirmation, and finalization. The final browser detail rendered the link to `/meetings/4`.

The authoritative final state was `meeting_requests.status = CONVERTED_TO_MEETING`, `meetings.id = 4`, and `meetings.status = SCHEDULED`. The direct invariant evidence recorded exactly one Meeting for the request, the required authority context, the High Authority holder/Chairperson attribution, and the immutable Meeting identity relationship.

## FLOW-05 — multilateral Directorate request with High Authority

The real RTL UI completed FLOW-05 for request id `7`, with Technology as requester and Finance plus Human Resources as participating Directorates. The journey covered the full governance chain: requester submission and approval, independent participating-Directorate reviews, Authority Office review, forwarding to High Authority, High Authority approval, Authority scheduling, a formal proposal, acknowledgement by all three Directorates, per-Directorate participant-list ownership, Authority participant confirmation, final schedule confirmation, and Meeting creation.

The live API verification recorded `request_id = 7`, `status = CONVERTED_TO_MEETING`, `meeting_id = 5`, `meeting_number = MT-2026-00005`, `meetings.status = SCHEDULED`, `authority_employee_id = 9`, and the approved High Authority decision. Technology, Finance, and Human Resources participant lists were saved and confirmed by their own scoped actors; Authority participants were saved and confirmed by Authority Office.

The negative governance matrix passed: unrelated Directors could not act outside scope; Authority Office could not make the High Authority decision; High Authority could not decide before forwarding; final schedule confirmation required every formal Directorate acknowledgement; and participant ownership remained per Directorate.

## FLOW-06 — Authority-initiated request

The real RTL UI completed the Authority Office creation and draft workflow without a requester Directorate. The canonical contract was preserved: `requesting_unit_id = null`, the receiving Directorates were Technology, Finance, and Human Resources, and the Authority context was owned by the High Authority/Authority Office governance boundary.

The browser journey completed draft creation, issuance submission, High Authority directive issuance, distribution, independent Directorate coordination and acknowledgement, the HR clarification/response path, Executive Secretary scheduling, official schedule proposal, independent Directorate acknowledgements, Directorate participant-list ownership, Authority participant save/confirmation, and schedule confirmation. The scheduling workspace rendered the finalization control.

The final Meeting identity and invariant evidence was independently recorded in the RefreshDatabase acceptance context: one Meeting per request, three `PARTICIPATING` Meeting units, zero requester units, exactly one Meeting authority context, High Authority employee `9` as authority holder and Chairperson, and the expected participant ownership split. This is reported separately from the live-browser scheduling snapshot because the inherited browser session did not provide an unambiguous live-MariaDB finalization row after the redirect-based finalize call. No claim is made that these two snapshots are identical.

## Negative and governance checks

| Check | Result |
| --- | --- |
| Directorate scope prevents cross-unit approvals and acknowledgements | **PASS** |
| Authority Office cannot substitute for High Authority decision | **PASS** |
| High Authority cannot decide before Authority Office forwarding | **PASS** |
| Authority scheduling cannot begin before the required decision | **PASS** |
| Final schedule confirmation requires all formal Directorate acknowledgements | **PASS** |
| Participant lists are writable only by the owning Directorate workflow | **PASS** |
| High Authority holder and Chairperson attribution remain exact | **PASS** |
| One request creates at most one Meeting | **PASS** |
| Authority-initiated flow has no requester Directorate | **PASS** |
| Authority Office / Executive Secretary governance remains distinct | **PASS** |

## Supporting regression and build evidence

The saved final backend acceptance log contains passing FLOW-01/02/03, FLOW-04 contract and negative-matrix, and FLOW-05 contract and behavior suites. The inherited full regression result was **376 tests and 1,670 assertions**, all passing. The frontend production build passed with **1,866 modules transformed** after role-consolidation changes. These checks were not rerun during this continuation.

## Snapshot and status note

The post-migration authoritative integrity snapshot recorded 8 requests, 5 Meetings, 16 participants, and 121 request actions before the fresh role-consolidation browser fixtures. The current fresh browser snapshot contains the new FLOW-02/03 fixtures and is not a replacement for the historical FLOW-04/05/06 evidence. FLOW-06 is therefore marked **PASS with an explicit snapshot note**, not silently represented as if its live-browser finalization and its independent invariant fixture were one database state.

> **Final status:** FLOW-04 and FLOW-05 browser acceptance passed end-to-end. FLOW-06 browser governance and scheduling acceptance passed, with final Meeting identity independently verified in the documented RefreshDatabase invariant context. No frozen production workflow semantics were changed.
