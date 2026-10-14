---
trigger: always_on
---

# AMMS Roles, Permissions & Scope Rules

Canonical authorization charter for AMMS. Scope: FLOW-01 through FLOW-14. FLOW-10B remains deferred. Backend Policies/Gates/Resolvers are the runtime authority; historical migrations are history, not current authority.

## 1. Core Rules
- Deny by default; apply least privilege.
- Authorization is composite: **permission + canonical role/capability + organizational scope + ownership/relationship + lifecycle/status** where applicable.
- Role never implies global scope. Scope never substitutes for role/permission/capability.
- Frontend visibility must mirror backend authorization, but backend enforcement is authoritative.
- No new role, capability, approval path, lifecycle state, or business authority without explicit domain approval.
- `SYSTEM_ADMIN` is technical administration only; it is never a meeting-workflow business superuser.
- A technical permission must never bypass scoped Policy/Gate checks.
- One role/capability must not broaden the scope of another.

## 2. Canonical Roles
| Role | Responsibility / boundary |
| --- | --- |
| `SYSTEM_ADMIN` | Technical administration only. May manage accounts, technical configuration, infrastructure and delegated-capability records where explicitly permitted. No meeting business authority. |
| `EMPLOYEE` | Personal/participant visibility and explicitly assigned work only. |
| `DIRECTOR` | Formal approval/review authority for the scoped Directorate where the frozen flow grants it. |
| `DEPUTY_DIRECTOR` | Real deputy post of a scoped Directorate. It receives no Director approval authority merely from its role; it may review minutes only through an active, explicit delegation from that Directorate's Director. |
| `EXECUTIVE_MANAGEMENT` | Operational coordination for the scoped Directorate: preparation, scheduling, execution or follow-up only where the flow grants it. Does not inherit Director approval authority. |
| `AUTHORITY_OFFICE` | High-Authority-office review/forwarding and authority-owned participant/governance work where defined. |
| `EXECUTIVE_SECRETARY` | Authority-controlled scheduling, execution, minutes, follow-up and exception ownership where defined. |
| `HIGH_AUTHORITY` | Explicit High Authority decision/approval points only. |

Retired roles:
- `MEETING_COORDINATOR` → retired; responsibilities consolidated into scoped `EXECUTIVE_MANAGEMENT`.
- `BIOMETRIC_ENROLLMENT_OFFICER` → retired; replaced by scoped delegated capability `BIOMETRIC_ENROLLMENT`.
Do not recreate retired roles in new code, UI, seeders or tests except explicit legacy/migration tests.

## 3. Scope & Multi-Role Rules
- Organizational authority is bound to `organizational_unit_id` or another explicit approved scope.
- Parent/descendant coverage exists only when the relevant Policy/Resolver explicitly defines hierarchical scope.
- Child scope never implies parent/sibling authority.
- Cross-Directorate authority requires an explicit frozen-flow relationship.
- Null/unscoped values never imply global business authority.
- Multi-role users receive only the union of separately valid, correctly scoped authorities.
- `SYSTEM_ADMIN` never upgrades or bypasses a business role. Prefer separate technical-admin and operational identities in production.
- On conflicting assignments, apply the more restrictive valid domain rule.

## 4. Delegated Capabilities
Use a delegated capability when a software duty is not a real organizational post.

Required properties: explicit, revocable, auditable, unit-scoped, independent from job title, inactive when the user/employee is inactive. Grant/revoke history must record actor and time. Duplicate active grants should be idempotent.

### BIOMETRIC_ENROLLMENT
General biometric enrollment requires:
1. required `biometric.*` permission;
2. active `BIOMETRIC_ENROLLMENT` capability;
3. valid organizational scope;
4. active user/employee and applicable lifecycle conditions.

Relevant permissions: `biometric.status.view`, `biometric.enroll`, `biometric.reenroll`, `biometric.revoke`, `biometric.enrollment.cancel`.

Rules:
- `EXECUTIVE_MANAGEMENT` or another real organizational user may receive the capability explicitly; role alone never grants it.
- `SYSTEM_ADMIN` may administer delegation where authorized but must not receive this operational capability.
- Revocation preserves audit history and immediately removes effective authority.
- Raw biometric templates/images remain protected independently from enrollment authority.
- Narrow FLOW-10A exception: a valid active terminal meeting session may authorize initial fingerprint capture only for an employee in that session's bound participant version, within the meeting/terminal organization scope, and only to add missing templates up to the configured minimum. It cannot replace an active template, enroll non-participants, or authorize general enrollment, re-enrollment, template revocation, or biometric metadata access. This meeting-bound capture does not create a separate operator-started `BiometricEnrollmentSession`; its terminal session and immutable audit record are the authorization/provenance context.

### INTERNAL_MEETING_REQUEST_APPROVAL
`DIRECTOR` may explicitly delegate only the approval of a same-Directorate internal meeting request to an active, scoped `EXECUTIVE_MANAGEMENT` or `DEPUTY_DIRECTOR` user.

Rules:
- The delegation is unit-scoped, time-bound, revocable and requires a recorded grant reason; revocation requires a recorded reason.
- It applies only to FLOW-01 (`INTERNAL_DIRECTORATE`) while the request is `PENDING_INTERNAL_APPROVAL`.
- It grants **approve only**. It never grants return-for-revision, reject, scheduling, finalization, another Directorate's approval, or any authority-controlled flow action.
- The delegate must hold either the `EXECUTIVE_MANAGEMENT` or `DEPUTY_DIRECTOR` role scoped to the same Directorate; a child management employment unit does not expand the delegated scope.
- The approval audit record must identify the delegated capability scope and the delegating Director. Expiry or revocation immediately removes the effective authority without deleting history.
- `SYSTEM_ADMIN` cannot grant or receive this business capability.

### DIRECTORATE_MINUTES_APPROVAL
`DIRECTOR` may explicitly delegate a Directorate's official-minutes review to an active, scoped `DEPUTY_DIRECTOR` or `EXECUTIVE_MANAGEMENT` of that same Directorate.

Rules:
- The delegation is unit-scoped, time-bound, revocable and requires recorded grant and revocation reasons.
- At most one active minutes-approval delegation may exist for a Directorate; repeating a grant to that same deputy or manager is idempotent.
- It applies only to that Directorate's pending `UNIT` review row of a submitted minutes version. The delegate may acknowledge it or request correction.
- It never grants drafting, submission, finalization, approval for another Directorate, or the `HIGH_AUTHORITY` approval. A required High Authority approval remains separate.
- The Director retains their original review authority; the first valid action resolves that unit review row.
- Audit records must identify a delegated acknowledgement or correction and contain the capability scope and delegating Director.
- `SYSTEM_ADMIN` cannot grant or receive this business capability.

## 5. Role × Flow Authority Summary
This matrix is a summary only; exact permission, scope, ownership and status checks remain mandatory.

| Flow | Authority summary |
| --- | --- |
| FLOW-01 Internal | Scoped `EXECUTIVE_MANAGEMENT`: create/edit/submit, coordinate schedule, finalize meeting conversion. Same-unit `DIRECTOR`: approve/return/reject. An active holder of same-unit `INTERNAL_MEETING_REQUEST_APPROVAL` (`EXECUTIVE_MANAGEMENT` or `DEPUTY_DIRECTOR`) may approve only. |
| FLOW-02 Bilateral | Requesting/target scoped `EXECUTIVE_MANAGEMENT`: coordination. Each scoped `DIRECTOR`: formal approval for its own Directorate as defined. Exactly two formal Directorates. |
| FLOW-03 Multi-Directorate | Requesting/participating `EXECUTIVE_MANAGEMENT`: unit coordination. Each scoped `DIRECTOR`: unit approval/confirmation as defined. No unrelated unit authority. |
| FLOW-04 Directorate + High Authority | Requester-side `EXECUTIVE_MANAGEMENT`/`DIRECTOR`; `AUTHORITY_OFFICE` for office review/authority participant domain; `HIGH_AUTHORITY` for explicit decisions; `EXECUTIVE_SECRETARY` for authority scheduling/final coordination. |
| FLOW-05 Multi-Directorate + High Authority | Each Directorate owns only its unit domain; authority office owns authority domain. `HIGH_AUTHORITY` decides; `EXECUTIVE_SECRETARY` controls authority scheduling. |
| FLOW-06 High Authority Initiated | `AUTHORITY_OFFICE` prepares authority request/directive; `HIGH_AUTHORITY` issues/decides; participating `EXECUTIVE_MANAGEMENT`/`DIRECTOR` handle their unit responsibilities; `EXECUTIVE_SECRETARY` controls authority scheduling. |
| FLOW-07 Reschedule | Non-authority: scoped `EXECUTIVE_MANAGEMENT` leads governed rescheduling with permitted Director/unit responses. Authority-controlled: `EXECUTIVE_SECRETARY` owns resolution/confirmation. |
| FLOW-08 Participant Change | Unit domain: scoped `EXECUTIVE_MANAGEMENT` manages changes and scoped `DIRECTOR` confirms/reviews where defined. Authority domain: scoped `AUTHORITY_OFFICE`. Domains must not be mixed. |
| FLOW-09 Terminal Assignment | `SYSTEM_ADMIN`: technical terminal administration only. Meeting assignment/revoke: lead `EXECUTIVE_MANAGEMENT` for non-authority meetings or `EXECUTIVE_SECRETARY` for authority-controlled meetings. |
| FLOW-10A Biometric/Attendance | General enrollment/re-enrollment/revocation requires scoped `BIOMETRIC_ENROLLMENT` + required permissions. Narrow initial fingerprint capture for a participant is allowed within the active, non-stale terminal meeting session, only to add missing templates up to the configured minimum and never to replace an active template. Terminal attendance sessions may activate while the meeting is `SCHEDULED` or `IN_PROGRESS`, within the valid non-stale assignment window. Attendance remains governed by attendance permission and meeting context, and cannot be recorded after the meeting is `POSTPONED`, `CANCELLED`, `HELD` or `COMPLETED`. |
| FLOW-10B DERMALOG | Deferred/not authorized. No production SDK/device claim or new authority. |
| FLOW-11 Execution/Minutes | Non-authority: scoped `EXECUTIVE_MANAGEMENT` owns execution/minutes where permitted; scoped `DIRECTOR` reviews/acknowledges as defined. An active same-unit holder of `DIRECTORATE_MINUTES_APPROVAL` (`DEPUTY_DIRECTOR` or `EXECUTIVE_MANAGEMENT`) may acknowledge or request correction for that unit only. Authority-controlled: `EXECUTIVE_SECRETARY`; `HIGH_AUTHORITY` performs explicit approval where required. |
| FLOW-12 Follow-Up | Follow-up owner: scoped `EXECUTIVE_MANAGEMENT` or authority `EXECUTIVE_SECRETARY`. Responsible-unit `DIRECTOR`/`EXECUTIVE_MANAGEMENT` manages unit items; assigned `EMPLOYEE` may work its own item where permitted. |
| FLOW-13 Exceptions | Non-authority owner: scoped `EXECUTIVE_MANAGEMENT`; authority owner: `EXECUTIVE_SECRETARY`. `DIRECTOR` or `HIGH_AUTHORITY` performs required review/approval according to governance mode. |
| FLOW-14 Reporting | Reporting is role/permission/scope specific. `SYSTEM_ADMIN` gets technical reporting only, not executive business dashboards. Backend owns metric semantics; export ownership/expiry/security remain enforced. |

## 6. Permission Families
Current namespaces include: `meeting-request.*`, `meeting-schedule.*`, `meeting-participant.*`, `authority-*`, `meeting-reschedule.*`, `meeting-participant-change.*`, `terminal.*`, `meeting-terminal.*`, `biometric.*`, `attendance.*`, `meeting.execution.*`, `meeting.minutes.*`, `decision.followup.*`, `action-item.*`, `meeting.exception.*`, `reporting.*`, and technical administration permissions.

Permissions are implementation primitives, not complete authorization. Any scope-sensitive operation must use a composite ability/Policy whose name cannot be short-circuited by a plain Spatie permission with the same name.

## 7. Frontend & API Rules
- Do not render business actions for users that backend authorization would deny.
- Route access alone is not authority.
- Do not infer authorization from role name only; use scope/capability/resource context.
- API endpoints must enforce current Policy/Gate/Resolver rules even if the UI hides controls.
- FLOW-14 frontend must not recompute authoritative metrics.
- Sensitive biometric, terminal, storage and export data remains separately protected.

## 8. Authorization Lifecycle & Audit
On transfer, role change, deactivation, termination or delegated-duty end:
- remove/revoke obsolete active role/capability scopes;
- preserve historical/audit records;
- ensure stale direct permissions cannot keep operational access;
- keep grants/revokes attributable (`granted_by`, `granted_at`, `revoked_by`, `revoked_at`).

Cross-organization capability grants are forbidden. `SYSTEM_ADMIN` cannot self-grant an operational capability that violates this charter.

## 9. Legacy & Change Governance
- Do not rewrite historical migrations merely to match current policy; use forward migrations.
- Historical role/permission rows do not override current Policies/Resolvers.
- Old references to retired roles must not be copied into new implementation.
- Authorization/domain semantics are frozen: AI/IDE may not autonomously add, remove, broaden, rename or reinterpret roles, permissions, scope, capabilities, approval paths, ownership rules, lifecycle states or reporting authority.
- Explicitly approved domain/security changes must update backend authorization, migration/data transition, tests, frontend visibility/API contract where applicable, and this charter together.

## 10. Merge Checklist
Before merging authorization-sensitive work verify:
- correct canonical role/capability and permission;
- correct organizational scope and ownership/resource relationship;
- valid lifecycle/status;
- wrong-role, missing-permission, wrong-scope, inactive-user and cross-unit negative tests;
- no `SYSTEM_ADMIN` business escalation or retired-role reintroduction;
- frontend visibility matches backend behavior;
- full AMMS CI is green.
