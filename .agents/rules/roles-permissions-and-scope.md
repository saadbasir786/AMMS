---
trigger: always_on
---

# AMMS Roles, Permissions & Organizational Scope Rules

This document is the canonical authorization charter for AMMS. It defines the difference between organizational roles, technical permissions, delegated capabilities, organizational scope, and flow-specific business authority.

**Scope:** FLOW-01 through FLOW-14. FLOW-10B remains deferred.

**Precedence:** this charter defines the approved domain/security model. Backend Policies, Gates, governance resolvers, and current forward migrations implement that model and remain the runtime enforcement authority. Historical migrations are evidence of history, not proof of current canonical authority.

## 1. Core Authorization Principles

1. **Deny by default**: absence of an explicit role, permission, capability, and required scope means access is denied.
2. **Least privilege**: users receive only the authority necessary for their real administrative responsibility.
3. **Role is not scope**: a role such as `DIRECTOR` or `EXECUTIVE_MANAGEMENT` never grants authority over every Directorate. Its `organizational_unit_id` scope must match the governed unit according to the relevant policy.
4. **Permission is not sufficient by itself** when a domain operation is role-sensitive, capability-sensitive, ownership-sensitive, or scope-sensitive.
5. **Scope is not sufficient by itself**: a user must also hold the required active role/permission/capability for the operation.
6. **Frontend is not an authorization boundary**: hiding a button or route is mandatory UX hygiene, but every protected operation must still be enforced by backend authorization.
7. **Business authority is flow-aware**: a role may perform an action in one frozen flow and be unable to perform the same-looking action in another flow.
8. **Status/lifecycle is part of authorization**: even an otherwise authorized actor may act only in the states explicitly allowed by the applicable Policy/Resolver.
9. **Ownership matters** where defined: creator, coordinator, responsible unit, assigned employee, authority office, or authority identity may further constrain authorization.
10. Authorization must never be inferred from route access, UI visibility, broad administrator labels, historical permission rows, or possession of an unrelated role.

## 2. Canonical Organizational Roles

AMMS organizational roles must correspond to real administrative responsibilities, not temporary technical tasks.

| Role | Canonical responsibility | Core boundary |
| --- | --- | --- |
| `SYSTEM_ADMIN` | Technical system administration | No meeting business-workflow authority. It may administer technical configuration, accounts, role assignments, terminals/infrastructure visibility where explicitly permitted, and delegated-capability records. |
| `EMPLOYEE` | Ordinary employee identity | Personal/participant visibility and explicitly assigned work only; no implicit unit-management authority. |
| `DIRECTOR` | Head/Director authority for a scoped Directorate/unit | Approval/review authority only for the scoped unit and only where the frozen flow grants it. |
| `EXECUTIVE_MANAGEMENT` | Executive Management responsibility for operational coordination in a scoped Directorate | Preparation, coordination, scheduling, execution, follow-up, or other operational actions only where the frozen flow grants them; does not inherit Director approval authority. |
| `AUTHORITY_OFFICE` | High Authority office responsibility | Review/forwarding and authority-owned participant/governance responsibilities only where explicitly defined. |
| `EXECUTIVE_SECRETARY` | Executive-secretariat responsibility | Authority-controlled scheduling, execution/minutes/follow-up/exception ownership where explicitly defined. |
| `HIGH_AUTHORITY` | Actual High Authority identity | Explicit decision/approval points only; not a general technical or administrative superuser. |

No new organizational role may be introduced merely to represent an ad-hoc system task.

## 3. Retired / Non-Canonical Roles

The following roles are historical only and must not be reintroduced into active authorization:

| Retired role | Current canonical treatment |
| --- | --- |
| `MEETING_COORDINATOR` | Retired. Its operational responsibilities were consolidated into scoped `EXECUTIVE_MANAGEMENT`. Historical migrations/tests may still reference it, but new code, UI, seed data, or authorization rules must not recreate it. |
| `BIOMETRIC_ENROLLMENT_OFFICER` | Retired. Biometric enrollment is a delegated scoped capability, not an organizational role. |

A role name appearing in a historical migration does not make that role canonical today.

## 4. Organizational Scope Model

Role and capability authority must be bound to an explicit organizational scope whenever the operation is organizationally scoped.

- A Directorate-level scope may cover descendant Managements/Departments only where the applicable Policy/Resolver explicitly uses hierarchical scope.
- A child scope never implies authority over its parent or sibling units.
- Cross-Directorate authority is denied unless a frozen flow explicitly defines a relationship between the units.
- Cross-organization delegated-capability assignment is prohibited.
- Unscoped/global authority must never be inferred from a null scope unless a specific technical role and policy explicitly define that behavior.
- `SYSTEM_ADMIN` being globally scoped for technical administration does not create global business authority.
- Scope checks belong in backend Policies/Resolvers/Services; frontend role-name checks alone are insufficient.

## 5. Multi-Role and Identity Rules

A user may technically hold more than one role, but effective authority is the union only of the **explicitly granted, correctly scoped, currently valid** authorities of those roles. One role must never broaden the scope of another.

Rules:

1. `DIRECTOR` + `EXECUTIVE_MANAGEMENT` does not create organization-wide authority; each role remains bound to its own role scope and flow rules.
2. `SYSTEM_ADMIN` never upgrades or bypasses a user's business role. If one identity also holds a business role, every business action must still satisfy that explicit business role, permission, scope, ownership, and lifecycle condition.
3. Production provisioning should prefer separate technical-administration and operational-business identities where feasible, especially for privileged system administrators.
4. Conflicting role assignments must resolve to the more restrictive applicable domain rule; no implicit privilege escalation is allowed.
5. A direct technical permission must never be treated as a substitute for the role/scope condition required by a Policy/Resolver.

## 6. Delegated / Special Capabilities

A task that exists in the software but does **not** correspond to a real organizational post must be modeled as a delegated capability, not as an artificial organizational role.

Canonical pattern:

```text
Real organizational role
        +
Explicit technical permission(s)
        +
Explicit special capability
        +
Organizational-unit scope
```

A delegated capability must be:

- explicit;
- revocable;
- organizationally scoped;
- auditable (`granted_by`, `granted_at`, `revoked_by`, `revoked_at`);
- independent from the user's real organizational title;
- never granted automatically merely because a user holds a broad role;
- inactive immediately when the user/employee identity is inactive.

No new special capability is canonical unless explicitly approved as a domain/security decision.

## 7. Biometric Enrollment Capability

`BIOMETRIC_ENROLLMENT_OFFICER` is retired and non-canonical. It must not be created, assigned, displayed as a job title, or used as the authorization scope source.

Biometric enrollment is represented by the delegated capability:

```text
BIOMETRIC_ENROLLMENT
```

It maps to the existing technical permissions:

```text
biometric.status.view
biometric.enroll
biometric.reenroll
biometric.revoke
biometric.enrollment.cancel
```

Rules:

1. The capability may be explicitly delegated to an appropriate real organizational user, such as scoped `EXECUTIVE_MANAGEMENT` or another designated employee, without changing that person's organizational role/title.
2. Holding `EXECUTIVE_MANAGEMENT` does not automatically grant biometric enrollment.
3. Holding a biometric permission without an active `BIOMETRIC_ENROLLMENT` scope does not authorize the operation.
4. Holding a capability scope without the required technical permission does not authorize the operation.
5. A Directorate-scoped capability may cover descendant units according to the biometric Policy hierarchy, but never another Directorate/organization.
6. `SYSTEM_ADMIN` may administer delegation records when explicitly authorized, but the SYSTEM_ADMIN identity itself must not receive this operational capability.
7. Revocation preserves the delegation record for audit history; it must not erase historical grants.
8. Raw biometric images/templates must never be exposed merely because the actor can perform enrollment; data visibility remains separately constrained.

## 8. Role × Flow Business-Authority Matrix

This matrix is the canonical **summary of business authority**, not a replacement for exact Policy/Resolver checks. Every action shown below still requires the applicable permission, scope, ownership, meeting/request status, and other runtime conditions.

| Flow | Business authority summary | Scope / special rule |
| --- | --- | --- |
| **FLOW-01 — Internal Directorate** | `EXECUTIVE_MANAGEMENT`: create/edit/submit the internal request, coordinate schedule, and finalize conversion to meeting. `DIRECTOR`: approve/return/reject for the same Directorate. | All business authority is restricted to the request's Directorate. Creator ownership is required for draft/edit/submit where Policy defines it. `SYSTEM_ADMIN` has no business authority. |
| **FLOW-02 — Bilateral / Inter-Directorate** | Requesting `EXECUTIVE_MANAGEMENT`: prepare/edit and lead coordination. Target/participating `EXECUTIVE_MANAGEMENT`: participate in coordination. Scoped `DIRECTOR` actors perform the formal approval/return/reject steps assigned to their Directorate. | Exactly the two formal Directorates govern the flow. No unrelated Directorate gains authority. |
| **FLOW-03 — Multi-Directorate** | Requesting `EXECUTIVE_MANAGEMENT`: prepare/edit and lead coordination. Participating `EXECUTIVE_MANAGEMENT`: unit coordination/participant work. Scoped `DIRECTOR`: formal unit approval/confirmation responsibilities defined by the flow. | Authority is limited to the formal requester/participating units. Cross-unit actions require explicit flow relationships. |
| **FLOW-04 — Directorate + High Authority** | Requesting `EXECUTIVE_MANAGEMENT`: prepare/correct request, requester-side schedule acknowledgement and participant work. Scoped `DIRECTOR`: requester-side formal Directorate authority where the flow requires it. `AUTHORITY_OFFICE`: review/forward/return and authority-owned participant governance. `HIGH_AUTHORITY`: explicit decision/approve/reject/return points. `EXECUTIVE_SECRETARY`: authority-controlled scheduling and final coordination. | Directorate authority remains on the requester side; authority-side actions are bound to the configured authority office / authority identity. |
| **FLOW-05 — Multi-Directorate + High Authority** | Requesting/participating `EXECUTIVE_MANAGEMENT`: coordinate and manage their own unit participant domain. Scoped `DIRECTOR`: confirm/approve their own unit responsibilities. `AUTHORITY_OFFICE`: authority-office review/participant governance. `HIGH_AUTHORITY`: explicit authority decisions. `EXECUTIVE_SECRETARY`: authority-controlled scheduling/final coordination. | Each Directorate owns only its unit domain; the authority office owns only the authority domain. |
| **FLOW-06 — High Authority Initiated** | `AUTHORITY_OFFICE`: prepare the authority-initiated request/directive and authority participant domain where defined. `HIGH_AUTHORITY`: issue/approve the directive and explicit authority decisions. Participating `EXECUTIVE_MANAGEMENT`: nominate/manage their unit participants. Participating `DIRECTOR`: confirm unit participants/required Directorate actions. `EXECUTIVE_SECRETARY`: authority-controlled schedule/final coordination. | Participating Directorates receive authority only after the flow exposes the relevant stage to them. |
| **FLOW-07 — Reschedule Governance** | Non-authority flows: scoped `EXECUTIVE_MANAGEMENT` actors propose/coordinate; `DIRECTOR`/`EXECUTIVE_MANAGEMENT` acknowledge or request alternatives as permitted; lead `EXECUTIVE_MANAGEMENT` confirms. Authority-controlled flows: `EXECUTIVE_SECRETARY` owns authority proposals/resolution/confirmation. | Governance mode (`INTERNAL_SINGLE_UNIT`, `PEER_MULTI_UNIT`, `AUTHORITY_CONTROLLED`) determines the actor set. |
| **FLOW-08 — Participant Change Governance** | Unit-owned domain: scoped `EXECUTIVE_MANAGEMENT` prepares/manages participant changes; scoped `DIRECTOR` confirms/returns/rejects as defined. Authority-owned domain: scoped `AUTHORITY_OFFICE` manages/confirms the authority participant domain. | An actor may modify only the participant domain it owns. Authority and unit domains must not be mixed. |
| **FLOW-09 — Terminal Assignment / Session Foundation** | `SYSTEM_ADMIN`: technical terminal/infrastructure inspection and administration only where explicit. Lead `EXECUTIVE_MANAGEMENT`: assignment/refresh/revoke in non-authority meetings. `EXECUTIVE_SECRETARY`: assignment/refresh/revoke in authority-controlled meetings. `DIRECTOR`/`AUTHORITY_OFFICE` may have governed visibility where Policy permits, without inheriting assignment authority. | Technical terminal administration is separate from meeting-governed terminal assignment. `SYSTEM_ADMIN` cannot perform meeting assignment merely because it manages terminals. |
| **FLOW-10A — Vendor-Independent Biometric / Attendance** | Biometric enrollment is performed only by a user with required biometric permission(s) **and** active scoped `BIOMETRIC_ENROLLMENT` capability. Attendance actions remain governed by their explicit attendance permissions and meeting context. | No artificial biometric organizational role exists. Raw biometric data remains protected. |
| **FLOW-10B — Physical DERMALOG Integration** | **Deferred / not authorized.** | No role, capability, hardware behavior, SDK claim, or production integration may be inferred or documented as implemented until explicitly approved. |
| **FLOW-11 — Meeting Execution / Attendance / Minutes / Finalization** | Non-authority meetings: coordinator/scoped `EXECUTIVE_MANAGEMENT` performs execution start/end and minutes preparation/finalization where permitted; scoped `DIRECTOR` reviews/acknowledges its unit minutes responsibility. Authority-controlled meetings: `EXECUTIVE_SECRETARY` owns execution/minutes preparation/finalization; `HIGH_AUTHORITY` performs explicit authority approval of minutes where required. | Exact execution/minutes actions require the corresponding permission and governance resolver condition. Participants may view only through meeting/participant authority. |
| **FLOW-12 — Decision Follow-Up / Action Items** | Follow-up owner: scoped `EXECUTIVE_MANAGEMENT` for non-authority meetings; scoped `EXECUTIVE_SECRETARY` for authority-controlled meetings. Owner initializes/classifies/creates/verifies/cancels as permitted. Responsible-unit `DIRECTOR`/`EXECUTIVE_MANAGEMENT` manages unit action items. Assigned `EMPLOYEE` may work its own item where permission allows. | Responsible unit, assigned employee, and follow-up owner are distinct authorization dimensions. Changing responsible unit remains owner-controlled. |
| **FLOW-13 — Exceptional Lifecycle Governance** | Non-authority owner: scoped `EXECUTIVE_MANAGEMENT`. Authority-controlled owner: scoped `EXECUTIVE_SECRETARY`. Owner prepares/updates/submits/withdraws/applies/resumes according to state. Scoped `DIRECTOR` performs required unit approval/review for non-authority governance; `HIGH_AUTHORITY` performs required authority approval; formal units acknowledge where authority flow requires acknowledgement rather than approval. | Exception authority is derived from the existing meeting governance; it does not create a new role or global exception manager. |
| **FLOW-14 — Reporting / Dashboard / Secure Exports** | `SYSTEM_ADMIN`: technical dashboard/technical reporting only. `EXECUTIVE_MANAGEMENT`: explicitly granted executive/operational reporting within permitted scope. `DIRECTOR`: Directorate reporting for its scoped Directorate. `HIGH_AUTHORITY`, `AUTHORITY_OFFICE`, `EXECUTIVE_SECRETARY`: authority reporting according to their explicit reporting permissions. `EMPLOYEE`: personal reporting only where explicitly permitted. | Backend owns metric semantics. Frontend must not recompute authoritative metrics. Export access is permission-, owner-, scope-, and expiry-controlled. `SYSTEM_ADMIN` is not an executive business-reporting superuser. |

## 9. Permission Families and Authorization Contract

AMMS technical permissions are implementation primitives. They grant a named capability to be considered by authorization, but they do not override role/scope/ownership/lifecycle constraints.

Current permission families include:

| Domain | Permission namespace / examples | Additional authority condition |
| --- | --- | --- |
| Meeting requests | `meeting-request.*` | Flow type, creator/owner, role scope, request status |
| Inter-/multi-Directorate coordination | `meeting-schedule.*`, `meeting-participant.*` | Formal unit membership and scoped operational/Director role |
| High Authority requests | `authority-request.*`, `authority-schedule.*`, `authority-participant.*`, `authority-meeting.*`, `authority-directive.*`, `authority-initiated-meeting.*` | Authority context, authority office scope, explicit High Authority identity |
| Rescheduling | `meeting-reschedule.*` | Schedule governance mode and governed unit/authority office |
| Participant changes | `meeting-participant-change.*` | Participant-domain ownership and role scope |
| Terminal operations | `terminal.*`, `meeting-terminal.*` | Technical-vs-meeting governance separation |
| Biometrics | `biometric.*` | Active `BIOMETRIC_ENROLLMENT` capability scope for enrollment operations |
| Attendance | `attendance.*` | Meeting visibility/governance and operation-specific permission |
| Execution/minutes | `meeting.execution.*`, `meeting.minutes.*` | Execution/minutes governance, meeting type, unit/authority scope |
| Decision follow-up | `decision.followup.*`, `action-item.*` | Follow-up ownership, responsible unit, assigned employee, item state |
| Exceptions | `meeting.exception.*` | Exception owner/reviewer role, formal unit or authority identity, case state |
| Reporting | `reporting.*` | Dashboard/report type, role scope, export ownership/expiry |
| Technical administration | `organization.manage`, `employee.manage`, `user.manage`, `role.manage` | Scoped technical-admin Policy; never implies meeting business authority |

When adding or changing a permission, developers must verify all five dimensions: **permission + role/capability + scope + ownership + lifecycle/status**.

## 10. Scoped Gate / Policy Enforcement Rule

Scope-sensitive authorization must not be implemented in a way that allows a generic permission hook to short-circuit the scope check.

For Spatie/Laravel specifically:

- A scope-sensitive Gate ability must not reuse a plain Spatie permission name when Spatie's global Gate hook could return `true` before the additional scope/capability Policy executes.
- Use distinct authorization ability names for composite checks. Current biometric composite abilities use the `biometric-scoped.*` namespace.
- Controllers must authorize through the composite scoped ability/Policy, not by checking the technical permission alone.
- Tests must include both negative directions: **permission without scope/capability = denied**, and **scope/capability without permission = denied**.

This rule applies to any future composite authorization pattern, not only biometrics.

## 11. Frontend Visibility Rules

Frontend pages, routes, navigation, and actions must mirror effective backend authorization rather than broad role assumptions.

1. Do not show a business action merely because the user has `DIRECTOR`, `EXECUTIVE_MANAGEMENT`, `HIGH_AUTHORITY`, or `SYSTEM_ADMIN`; applicable scope, flow, ownership, and state matter.
2. Do not expose biometric enrollment actions unless the current user has the active delegated capability for the relevant employee scope.
3. `SYSTEM_ADMIN` business-workflow navigation/actions must remain hidden even if historical permission assignments exist.
4. Protected routes may remain technically addressable, but unauthorized actions/navigation must not be presented as available and backend must still return `403` where applicable.
5. Frontend must consume backend-provided effective role/scope/capability information where available rather than recomputing authorization from role names alone.
6. Reporting UI must display backend-produced metric semantics and must not create a broader reporting scope client-side.

## 12. Authorization Lifecycle

Authorization is not permanent. Role/capability validity must follow the user's current administrative reality.

### User / employee lifecycle

- Inactive user or inactive employee identity must fail protected operational authorization.
- Deactivation must not be bypassed by stale tokens, UI state, historical role rows, or cached permission assumptions.
- Reactivation does not justify restoring previously revoked special capabilities automatically.

### Organizational transfer

When an employee changes Directorate/unit, all role scopes and delegated capability scopes must be reviewed. A previous unit's scope must not silently follow the employee to a new unit unless an explicit administrative grant is made for the new scope.

### Position / responsibility change

When an employee ceases to be Director, Executive Management, Authority Office, Executive Secretary, or High Authority, corresponding active role scopes must be removed/replaced through an auditable administrative action. Role names must not remain active merely because historical records exist.

### Delegated capability lifecycle

- Grant must record who granted it, when, and for which unit.
- Duplicate active grant for the same user/capability/unit should remain idempotent rather than creating parallel active authority.
- Revoke must preserve history and immediately remove effective operational authority for that scope.
- When the last active scope for a delegated capability is revoked, direct technical permissions that exist solely for that capability must not continue to confer operational access.

## 13. Audit and Administrative Delegation Rules

Security-sensitive authorization changes must be attributable.

- Role assignment/removal, scope changes, special-capability grant/revoke, user activation/deactivation, and privileged technical administration should be auditable.
- `granted_by` / `revoked_by` must identify an authorized administrative actor, not a fabricated system identity.
- Cross-organization capability grants are forbidden.
- `SYSTEM_ADMIN` may manage delegation only through explicitly authorized administrative endpoints/policies; it cannot self-grant operational capabilities that violate this charter.
- Historical records must be retained according to the system's audit model rather than rewritten to make current state appear to have always existed.

## 14. Historical Migration and Legacy-Code Rules

AMMS preserves historical migrations for audit/reproducibility. Therefore old files may contain role/permission assignments that are no longer canonical.

Rules:

1. Do not rewrite historical migrations merely to make them look consistent with current policy.
2. Use forward migrations to retire, consolidate, or transform roles/capabilities.
3. Current authorization must be derived from the latest schema/data state plus current Policies/Resolvers, not from an earlier migration's role-permission map.
4. `MEETING_COORDINATOR` and `BIOMETRIC_ENROLLMENT_OFFICER` references in old migrations are historical and must not be copied into new code.
5. Historical `SYSTEM_ADMIN` business permissions do not override the current invariant that SYSTEM_ADMIN is technical-only.
6. Tests/fixtures must use current canonical roles/capabilities unless the test explicitly verifies a migration/legacy transition.

## 15. Forbidden Privilege Escalations

The following are prohibited:

- treating `SYSTEM_ADMIN` as a meeting business superuser;
- interpreting any role as organization-wide unless its approved technical scope is explicitly global;
- inventing an organizational role to model a temporary/special task;
- granting a special capability automatically based only on a role name;
- using a technical permission alone to bypass role, capability, scope, ownership, or lifecycle checks;
- bypassing backend scope because the frontend already hid an action;
- allowing a parent/child/sibling unit relationship to imply authority unless the applicable Policy explicitly defines it;
- converting a scoped delegated capability into an unscoped/global grant during migration;
- reactivating retired roles from historical migrations or copied fixtures;
- letting one role broaden the organizational scope of another role on the same user;
- exposing raw biometric material or sensitive terminal/storage data because an actor has an unrelated administrative permission;
- allowing frontend-computed reporting metrics to replace backend-authoritative reporting semantics.

## 16. Change Governance / AI / IDE Boundary

Roles, permission boundaries, organizational-scope semantics, delegated-capability semantics, business-flow authority, approval paths, ownership rules, lifecycle states, reporting scope, and retired-role decisions are **frozen domain/security rules**.

AI/IDE assistants must not autonomously add, remove, broaden, rename, or reinterpret them. Any such change requires an explicit user/domain decision.

Autonomous rule evolution is permitted only for non-domain implementation conventions such as UI consistency, reusable components, test organization, code organization, or performance patterns, and must never override this authorization charter.

When an explicit domain/security change is approved, implementation must update all affected layers together: backend authorization, migrations/data transition, tests, frontend visibility, API contract where applicable, and this charter.

## 17. Authorization Review Checklist

Before merging any authorization-sensitive change, verify:

- the actor has the correct canonical role or delegated capability;
- the required technical permission is present;
- organizational scope is correct;
- ownership/actor identity constraints are satisfied;
- current workflow/status permits the action;
- unrelated Directorates/organizations are denied;
- `SYSTEM_ADMIN` does not gain meeting business authority;
- retired roles are not reintroduced;
- frontend visibility mirrors the backend result;
- negative authorization tests cover missing permission, wrong scope, wrong role/capability, inactive identity, and cross-unit access;
- historical migrations remain untouched unless the task explicitly requires a forward migration;
- full AMMS CI remains green.
