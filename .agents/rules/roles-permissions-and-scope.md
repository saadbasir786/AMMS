---
trigger: always_on
---

# AMMS Roles, Permissions & Organizational Scope Rules

This document is the canonical authorization charter for AMMS. It defines the difference between organizational roles, technical permissions, delegated capabilities, and organizational scope. Backend policies are authoritative, and frontend visibility must mirror the same boundaries.

## 1. Core Authorization Principles

1. **Deny by default**: absence of an explicit role/permission/capability/scope grant means access is denied.
2. **Least privilege**: users receive only the authority necessary for their real administrative responsibility.
3. **Role is not scope**: a role such as `DIRECTOR` or `EXECUTIVE_MANAGEMENT` never grants authority over every Directorate. Its `organizational_unit_id` scope must match the governed unit according to the relevant policy.
4. **Permission is not sufficient by itself** when a domain operation is scope-sensitive. The required organizational scope must also be satisfied.
5. **Frontend is not an authorization boundary**: hiding a button is mandatory UX hygiene, but every protected operation must still be enforced by backend Policy/Gate authorization.
6. Authorization must never be inferred from route access, UI visibility, a broad administrator label, or possession of an unrelated role.

## 2. Canonical Organizational Roles

AMMS organizational roles must correspond to real administrative responsibilities, not temporary technical tasks.

- `SYSTEM_ADMIN` — technical system administration only. It may manage technical configuration, users, role assignments, and explicitly authorized delegation records. It has **no meeting business-workflow authority** and must never create, approve, reject, schedule, finalize, cancel, postpone, resume, or otherwise decide a meeting workflow merely because it is SYSTEM_ADMIN.
- `EMPLOYEE` — ordinary employee identity within its organizational scope.
- `DIRECTOR` — Head/Director authority for the scoped Directorate or organizational unit; business actions remain limited to the unit and flow for which policy grants authority.
- `EXECUTIVE_MANAGEMENT` — Executive Management responsibility for operational coordination within its scoped Directorate; it does not inherit Director approval authority unless a specific frozen flow explicitly grants it.
- `AUTHORITY_OFFICE` — scoped responsibility of the High Authority office according to the approved High Authority flows.
- `EXECUTIVE_SECRETARY` — scoped executive-secretariat responsibility according to the approved High Authority flows.
- `HIGH_AUTHORITY` — the actual High Authority identity and its explicitly approved decision points.

No new organizational role may be introduced merely to represent an ad-hoc system task.

## 3. Organizational Scope

Role and capability authority must be bound to an explicit organizational scope whenever the operation is organizationally scoped.

- A Directorate-level scope may cover its descendant Managements/Departments where the relevant policy explicitly uses hierarchical scope.
- A child scope never implies authority over its parent or sibling units.
- Cross-Directorate authority is denied unless the frozen business flow explicitly defines that relationship.
- Cross-organization delegated capability assignment is prohibited.
- Scope checks belong in backend policies/services; UI role-name checks alone are insufficient.

## 4. Delegated / Special Capabilities

A task that exists in the software but does **not** correspond to a real organizational post must be modeled as a delegated capability, not as an artificial organizational role.

Canonical pattern:

```text
Real organizational role
        +
Explicit special capability
        +
Organizational-unit scope
```

A delegated capability is:
- explicit;
- revocable;
- organizationally scoped;
- auditable (`granted_by`, `granted_at`, `revoked_by`, `revoked_at`);
- independent from the user's real organizational title;
- never granted automatically merely because a user holds a broad role.

## 5. Biometric Enrollment Capability

`BIOMETRIC_ENROLLMENT_OFFICER` is **retired and non-canonical**. It must not be created, assigned, displayed as a job title, or used as the authorization scope source.

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
1. The capability may be explicitly delegated to an appropriate real organizational user, such as an `EXECUTIVE_MANAGEMENT` or another designated employee, without changing that person's real role/title.
2. Holding `EXECUTIVE_MANAGEMENT` does **not** automatically grant biometric enrollment.
3. Holding one of the biometric permissions without an active `BIOMETRIC_ENROLLMENT` scope does not authorize the operation.
4. Holding a capability scope without the required technical permission also does not authorize the operation.
5. A capability scoped to a Directorate may cover employees in descendant units of that Directorate, but never another Directorate.
6. `SYSTEM_ADMIN` may administer the delegation record when authorized by `role.manage`, but the SYSTEM_ADMIN identity itself must not receive this operational capability.
7. Revocation preserves the delegation record for audit history; it must not rewrite or erase historical grants.

## 6. Frontend Visibility Rules

Frontend pages and actions must use effective authorization information returned by the backend rather than broad role assumptions.

- Do not show business actions to a user merely because the user has `DIRECTOR`, `EXECUTIVE_MANAGEMENT`, or `SYSTEM_ADMIN`; the applicable unit scope matters.
- Do not expose biometric enrollment actions unless the current user has the active delegated capability for the relevant employee scope.
- A protected route may remain technically reachable, but unauthorized actions and navigation entries must not be presented as available.
- Backend `403` remains the final enforcement boundary.

## 7. Forbidden Privilege Escalations

The following are prohibited:
- treating `SYSTEM_ADMIN` as a business superuser;
- interpreting any role as organization-wide unless its approved scope is explicitly global;
- inventing a role to bypass proper permission/capability modeling;
- granting a special capability automatically based on a user's role name;
- bypassing unit scope because the frontend already hid an action;
- converting a scoped delegated capability into an unscoped/global grant during migration.

## 8. AI / IDE Change Boundary

Roles, permission boundaries, organizational scope semantics, delegated-capability semantics, business-flow authority, approval paths, and lifecycle states are **frozen domain/security rules**.

AI/IDE assistants must not autonomously add, remove, broaden, rename, or reinterpret them. Any such change requires an explicit user/domain decision. Autonomous rule evolution is permitted only for non-domain implementation conventions such as UI consistency or code-organization patterns, and must never override this authorization charter.
