# AMMS — SYSTEM_ADMIN Business-Governance Bypass Closure

## Final status

> **SYSTEM_ADMIN BUSINESS-GOVERNANCE BYPASS — SECURITY CORRECTION VERIFIED**
>
> **ROLE MODEL CONSOLIDATION — FINAL VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

The correction removed the pre-existing shared business-policy bypass that allowed `SYSTEM_ADMIN` to mutate meeting workflow state without Directorate, Authority Office, Executive Secretary, or High Authority scope. The correction was limited to authorization hardening and corresponding React capability visibility. No frozen workflow state machine, approval semantic, organizational-scope rule, High Authority rule, Authority Office rule, Executive Secretary rule, participant-ownership rule, meeting identity invariant, schedule-version rule, Minutes/Decision semantic, or FLOW-10B boundary was redesigned.

## Audit and architecture decision

The audit searched active backend policies, services, governance resolvers, controllers, and frontend role predicates for `SYSTEM_ADMIN`, `before(User ...)`, role-global allow shortcuts, and equivalent unconditional bypasses.

| Classification | Active locations | Decision |
|---|---|---|
| **A — technical administration** | `EmployeePolicy`, `OrganizationalUnitPolicy`, `UserPolicy`, `TerminalPolicy`, and terminal infrastructure visibility in `MeetingPolicy::flow09View()` / `TerminalAssignmentGovernanceResolver::mayView()` | Retained. These paths administer users, roles, organization configuration, terminals, or terminal infrastructure rather than meeting governance. |
| **B — business governance** | `MeetingRequestPolicy::before()`, explicit meeting-request SYSTEM_ADMIN clauses, `MeetingRequestService`, `MeetingPolicy::before()`, `MeetingRequestAttachmentPolicy::before()`, and `ParticipantGovernanceResolver` | Removed. These paths now require the ordinary permission, organizational scope, resource policy, and lifecycle/action chain. |
| **C — ambiguous** | Terminal visibility for the terminal subsystem | Retained only for technical terminal visibility. Terminal assignment mutation continues to require a governed business actor and denies SYSTEM_ADMIN. |

The architectural correction was made at the shared authorization layer. It did not add endpoint-specific exceptions. `SYSTEM_ADMIN` is no longer an automatic business actor merely because the role exists.

## Implemented correction

`MeetingRequestPolicy::before()` now returns no global business authorization. All active business-operation clauses that previously accepted `SYSTEM_ADMIN` were removed from request creation, review, coordination, scheduling, schedule confirmation, finalization, High Authority/Authority Office governance, FLOW-06 coordination, acknowledgement, clarification, participant management, and authority-initiated finalization.

The corresponding `MeetingRequestService` shortcuts were removed for creation, review, scheduling, schedule confirmation, finalization, and editability. `MeetingPolicy` and `MeetingRequestAttachmentPolicy` no longer grant global business visibility through `before()`. Participant preparation and confirmation now use the existing Authority Office, Executive Management, and Directorate ownership rules only.

The React role predicates for FLOW-03, FLOW-04, FLOW-05, FLOW-06, FLOW-07, and FLOW-08 no longer treat `SYSTEM_ADMIN` as a business role. Main navigation hides meeting requests, meetings, action items, approvals, reporting, and authority workflow menus from a technical administrator while retaining technical administration menus for organization, employees, roles, terminals, and biometrics.

## Effective negative authorization evidence

All entries below were exercised through authenticated Laravel HTTP requests against lifecycle-valid resources. Every denied operation returned `403` and recorded `state_mutated=false`. The focused test file is `backend/tests/Feature/SystemAdminBusinessBypassSecurityTest.php`; existing FLOW-11/12/13 regression tests also remained green.

| ID | Operation | Flow | Expected | Actual | State mutated |
|---|---|---|---:|---:|---:|
| A | `POST /api/meeting-requests/{id}/start-scheduling` | FLOW-01 | 403 | 403 | false |
| B1 | `POST /api/meeting-requests/{id}/start-inter-directorate-scheduling` | FLOW-02 | 403 | 403 | false |
| B2 | `POST /api/meeting-requests/{id}/start-multi-scheduling` | FLOW-03 | 403 | 403 | false |
| C | `POST /api/meeting-requests/{id}/authority/approve` | FLOW-04/05 High Authority decision family | 403 | 403 | false |
| D | `POST /api/meeting-requests/{id}/authority-scheduling/start` | FLOW-04/05 Executive Secretary scheduling family | 403 | 403 | false |
| E | `POST /api/meeting-requests/{id}/authority-participants` | FLOW-04/05 Authority Office participant administration | 403 | 403 | false |
| F | `POST /api/meeting-requests/{id}/authority-initiated/issue` | FLOW-06 High Authority issue | 403 | 403 | false |
| G1 | `POST /api/meeting-requests/{id}/authority-initiated/scheduling/start` | FLOW-06 Executive Secretary scheduling | 403 | 403 | false |
| G2 | `POST /api/meeting-requests/{id}/authority-initiated/finalize` | FLOW-06 executive finalization | 403 | 403 | false |
| H | `POST /api/meetings/{id}/minutes/finalize` | FLOW-11 Minutes finalization | 403 | 403 | false |
| I | `POST /api/action-items/{id}/completion/verify` | FLOW-12 completion verification | 403 | 403 | false |
| J | `POST /api/meetings/{id}/exceptions` | FLOW-13 exception application entry | 403 | 403 | false |

The focused test also checks that the request status, Minutes status, action-item status, decision classification, and exception-case count remain unchanged after each denial. This distinguishes authorization denial from invalid-state rejection.

## Legitimate positive paths

The correction was checked against legitimate actors through the focused real-HTTP test and the full feature suite.

| Actor | Positive path | Result |
|---|---|---|
| `EXECUTIVE_MANAGEMENT` | Own Directorate FLOW-01 scheduling and own Directorate coordination | PASS |
| `DIRECTOR` | Own Directorate requester and target approval | PASS |
| `AUTHORITY_OFFICE` | Authority Office review, distribution, participant ownership, and administrative workflow paths | PASS in existing FLOW-04/05/06 suite |
| `EXECUTIVE_SECRETARY` | Authority-controlled scheduling and finalization paths | PASS in focused and existing FLOW-04/05/06 suite |
| `HIGH_AUTHORITY` | Exact holder decision and FLOW-06 issue | PASS in focused and existing FLOW-04/05/06 suite |
| `SYSTEM_ADMIN` | `/api/admin/users` technical administration | PASS; technical capability preserved |

## Frontend real-browser smoke

The live React/Laravel browser session confirmed the role UX after the correction. The SYSTEM_ADMIN dashboard showed only technical administration navigation: organization structure, employees, users and permissions, terminals, biometrics, notifications, and dashboard. Meeting requests, incoming workflow, meetings, action items, approvals, reporting, and authority workflow menus were absent.

After switching to `tech.exec@example.test`, the normal Executive Management navigation and request creation control remained visible. In the real browser, FLOW-01 request 4 displayed `ورود به فضای زمان‌بندی`, FLOW-02 request 5 displayed `آغاز مذاکرهٔ زمان‌بندی`, and FLOW-03 request 6 displayed `ورود به workspace چندریاستی` after `MULTILATERAL_APPROVED` and `SCHEDULING_STARTED`.

The requested short browser smoke was therefore completed for SYSTEM_ADMIN navigation and Executive Management own-scope scheduling. High Authority decision and Executive Secretary authority scheduling positives were additionally verified through authenticated real Laravel HTTP tests on valid fixtures; the existing browser FLOW-04/05/06 evidence remains pre-consolidation evidence and was not reopened.

## Regression and role-model recheck

| Measure | Result |
|---|---:|
| Focused security/consolidation tests | 5 |
| Focused assertions | 94 |
| Full backend tests | 381 |
| Full backend assertions | 1,764 |
| Backend result | PASS |
| Frontend production build | PASS |
| Vite modules transformed | 1,866 |

The final live MariaDB recheck found no `MEETING_COORDINATOR` role, no Spatie assignment, and no `amms_role_scopes` row for that role. The former `tech.coordinator@example.test` test account has no active business role or scope in the live browser database; the user row is not present there. The active role inventory remains the consolidated model plus the zero-assignment `BIOMETRIC_ENROLLMENT_OFFICER` subsystem role.

Historical business rows were not modified by the security correction. The live browser database retained its existing snapshot counts; the focused and full test suites used isolated `RefreshDatabase` fixtures.

## Invariants and final classification

| Invariant | Result |
|---|---|
| `meeting_coordinator_role_exists` | false |
| `meeting_coordinator_assignment_count` | 0 |
| `meeting_coordinator_scope_count` | 0 |
| `old_coordinator_test_account_active_business_authority` | false |
| `system_admin_business_authority_denied` | true |
| `technical_admin_capability_preserved` | true |
| `workflow_semantics_changed` | false |
| `scope_semantics_changed` | false |
| `business_state_machine_changed` | false |
| `unresolved_blockers` | [] |
| `overall_pass` | true |

No new FLOW-04 browser acceptance was started. FLOW-10B and FLOW-15 were not touched. No automatic freeze was applied.
