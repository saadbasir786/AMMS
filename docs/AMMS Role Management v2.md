Implement AMMS Role Management v2 on top of the current centralized RBAC architecture already present in the codebase.

The following components already exist and are authoritative:

* `App\Support\AmmsRole`
* `App\Support\AmmsPermissionRegistry`
* `App\Support\OrganizationalUnitType`
* `App\Support\PositionType`
* `App\Support\RolePositionCompatibility`
* `App\Support\SpecialCapability`
* `App\Services\AmmsRbacSynchronizer`
* AMMS metadata endpoint
* Spatie Roles & Permissions
* `AmmsRoleScope`
* `AmmsCapabilityScope`
* user access auditing

Do NOT redesign these components.
Do NOT replace the current authorization model.
Extend the existing architecture safely.

The goal is to allow SYSTEM_ADMIN to manage custom roles and their allowed permissions from the admin UI while keeping core AMMS system roles protected and preserving all workflow-specific authorization rules.

## 1. Role model concept

AMMS must distinguish between:

```text
SYSTEM ROLES
→ defined by code
→ protected
→ workflow/domain-aware

CUSTOM ROLES
→ created by SYSTEM_ADMIN
→ permission-based
→ organizationally scoped
→ must not impersonate protected workflow authority

SPECIAL CAPABILITIES
→ delegated/special authority
→ continue using existing capability architecture
```

Canonical system roles remain:

```text
SYSTEM_ADMIN
EMPLOYEE
DIRECTOR
DEPUTY_DIRECTOR
EXECUTIVE_MANAGEMENT
AUTHORITY_OFFICE
EXECUTIVE_SECRETARY
HIGH_AUTHORITY
```

These roles must remain owned by `AmmsRole`.

Custom roles must NOT be added to `AmmsRole::all()`.

`AmmsRole` represents protected system/domain roles only.

---

## 2. Custom role persistence

Use Spatie's existing `roles` table for custom roles.

Add minimal metadata needed to distinguish custom and protected roles.

Preferred approach: add a dedicated metadata table such as:

```text
amms_role_definitions
```

Suggested fields:

```text
id
role_id
label
description nullable
role_kind
status
created_by nullable
updated_by nullable
created_at
updated_at
```

Recommended values:

```text
role_kind:
SYSTEM
CUSTOM

status:
ACTIVE
INACTIVE
```

Use a unique foreign key to the Spatie role.

Do not duplicate the role name itself if the Spatie `roles` record already owns it.

System role definitions should be synchronized/provisioned automatically.

Custom roles are admin-managed.

Avoid modifying vendor Spatie tables unless strongly justified.

---

## 3. Protected system roles

System roles must be protected from destructive administration.

SYSTEM_ADMIN may view their permission matrix, but must NOT be able to:

* rename them
* delete them
* deactivate them
* convert them to CUSTOM
* arbitrarily change protected permissions
* remove permissions required by existing flows

System roles remain synchronized from:

```php
AmmsRole
AmmsPermissionRegistry
```

`amms:sync-rbac` must continue to restore their canonical permissions.

---

## 4. Custom role naming

Allow SYSTEM_ADMIN to create names such as:

```text
REPORT_VIEWER
DOCUMENT_OFFICER
AUDITOR
MEETING_OBSERVER
```

Validation:

* uppercase snake case preferred
* unique within `sanctum` guard
* must not collide with `AmmsRole::all()`
* must not reuse deprecated/reserved role names
* max reasonable length
* label required
* description optional

Store machine name separately from human-readable Dari label.

Example:

```text
name:
REPORT_VIEWER

label:
مشاهده‌کننده گزارش‌ها
```

---

## 5. Permission classification

Do NOT allow every existing permission to be assigned to custom roles.

Extend the central permission architecture with permission metadata.

Create a dedicated registry or companion metadata structure, preferably:

```php
App\Support\AmmsPermissionMetadata
```

or extend `AmmsPermissionRegistry` cleanly.

Each permission should be classifiable with at least:

```text
category
custom_role_assignable
protected
delegatable
```

Conceptually:

```text
reporting.meetings.view
→ custom_role_assignable = true

reporting.requests.view
→ custom_role_assignable = true

reporting.exports.download
→ custom_role_assignable = true

meeting-request.approve
→ protected = true
→ custom_role_assignable = false

meeting-request.approve-target
→ protected = true
→ custom_role_assignable = false

meeting-request.approve-delegated
→ delegatable = true
→ custom_role_assignable = false

meeting.minutes.approve_authority
→ protected = true

authority-request.decide
→ protected = true

role.manage
→ protected = true

user.manage
→ protected = true

organization.manage
→ protected = true
```

Do not determine these classifications casually.

Audit every permission in `AmmsPermissionRegistry`.

Default security rule:

```text
If uncertain whether a permission should be custom-role assignable:
treat it as protected.
```

---

## 6. Initial custom-role permission policy

For the first implementation, prioritize safe read-oriented and limited operational permissions.

Candidate custom-role-assignable categories may include:

```text
reporting read permissions
reporting export permissions
meeting view
meeting-request view
notification view / mark-read
action-item view
decision followup view
attendance view if appropriate
document/evidence view
```

Be conservative with create/update/approve/reject/finalize/manage permissions.

Do NOT make workflow approval or authority permissions assignable to custom roles in v2 unless there is explicit existing business evidence supporting it.

---

## 7. Custom role authorization semantics

Custom roles are permission-based and scoped.

They do NOT satisfy checks such as:

```php
hasScopedRole(AmmsRole::DIRECTOR, ...)
hasScopedRole(AmmsRole::HIGH_AUTHORITY, ...)
hasScopedRole(AmmsRole::EXECUTIVE_MANAGEMENT, ...)
```

unless they literally are those protected system roles, which custom roles cannot be.

Therefore a custom role may grant low-level permission:

```text
meeting.view
```

but cannot impersonate a Director or High Authority workflow identity.

Preserve all current policy/action checks.

Do NOT change:

```text
permission + canonical scoped role
```

into permission-only authorization where current workflow logic requires both.

---

## 8. Custom role scope

Custom roles must use the existing:

```text
AmmsRoleScope
```

architecture.

When assigning a custom role to a user:

* organizational_unit_id is required
* role is valid only within that scope
* user must be active
* assigned organizational unit must be active
* scope must be valid for the user's organizational context according to current AMMS scope rules

Do not create a second custom-role scope mechanism.

Do not auto-propagate a custom role globally.

---

## 9. Role-position compatibility

System/business roles continue using:

```php
RolePositionCompatibility
```

Custom roles should NOT require a matching position type.

Example:

```text
Employee position:
GENERAL_MANAGER

Custom role:
REPORT_VIEWER
```

is valid if scope and permission policy allow it.

However SYSTEM_ADMIN remains a technical unscoped role and must preserve its current special restrictions.

Update assignment validation accordingly:

```text
SYSTEM ROLE
→ apply existing compatibility rules

CUSTOM ROLE
→ no position-role identity match
→ still require valid active employee and organizational scope unless explicit technical-role exception exists
```

Do not weaken existing SYSTEM_ADMIN separation rules.

---

## 10. API design

Add dedicated role-management endpoints.

Recommended:

```http
GET    /api/admin/roles
GET    /api/admin/roles/{role}
POST   /api/admin/roles
PUT    /api/admin/roles/{role}
POST   /api/admin/roles/{role}/permissions
POST   /api/admin/roles/{role}/activate
POST   /api/admin/roles/{role}/deactivate
```

Avoid hard delete initially.

Prefer deactivation for custom roles.

If delete is implemented at all, reject deletion when role has assignments or audit history.

Also provide:

```http
GET /api/admin/permissions
```

returning permission metadata grouped by category.

All endpoints require:

```text
active user
+ SYSTEM_ADMIN scoped role
+ role.manage permission
```

Reuse policies where practical.

---

## 11. Permission update behavior

When editing a custom role's permissions:

* validate every permission exists in `AmmsPermissionRegistry`
* validate every selected permission is `custom_role_assignable`
* reject protected permissions
* audit added/removed permissions
* clear Spatie permission cache
* use transaction
* return the effective permission list

Do not allow arbitrary permission names from the client.

---

## 12. Audit trail

Role management must be fully auditable.

Use the existing access audit architecture if appropriate, or add a dedicated role audit model only if current schema cannot represent this cleanly.

Record at least:

```text
CUSTOM_ROLE_CREATED
CUSTOM_ROLE_UPDATED
CUSTOM_ROLE_ACTIVATED
CUSTOM_ROLE_DEACTIVATED
CUSTOM_ROLE_PERMISSIONS_CHANGED
CUSTOM_ROLE_ASSIGNED
CUSTOM_ROLE_REVOKED
```

Permission changes should capture:

```text
added_permissions
removed_permissions
actor
role
timestamp
```

Do not store secrets.

---

## 13. User assignment integration

Update the current user role assignment workflow so the admin can assign:

```text
protected system role
or
active custom role
```

Rules differ:

### System role

Use existing:

```text
AmmsRole
RolePositionCompatibility
business-role rules
SYSTEM_ADMIN restrictions
```

### Custom role

Require:

```text
active custom role
active user
employee exists
valid organizational scope
```

but no role-position match.

Do not allow custom roles on technical SYSTEM_ADMIN-only accounts without employee linkage unless explicitly designed and tested.

---

## 14. Metadata endpoint

Extend existing AMMS metadata safely.

Do NOT place database-created custom roles inside static `AmmsRole::all()`.

Instead metadata may return:

```json
{
  "system_roles": [...],
  "custom_roles": [...]
}
```

or custom roles can come from `/api/admin/roles`.

Keep static canonical definitions separate from dynamic database state.

---

## 15. Frontend: dedicated Roles & Permissions page

Currently `/admin/roles` maps to `UsersPage`.

Change this.

Create a dedicated page, for example:

```text
RolesPermissionsPage
```

The page should provide:

### System Roles section

Read-only cards/table:

```text
Role
Label
Type = System
Permissions count
Status = Protected
```

Allow viewing permission matrix.

No edit/delete.

### Custom Roles section

Allow:

```text
Create
Edit label/description
Manage permissions
Activate
Deactivate
View assigned users count
```

Use clear visual distinction between SYSTEM and CUSTOM.

---

## 16. Permission matrix UI

When creating/editing a custom role, show permissions grouped by functional area.

Example:

```text
Reporting
☑ View meeting reports
☑ View request reports
☑ Download exports

Meetings
☑ View meetings

Action Items
☑ View action items

Notifications
☑ View notifications
☑ Mark notifications read
```

Do NOT show protected permissions as selectable checkboxes.

Either:

* omit them entirely from custom-role editor
  or
* show disabled with "Protected system permission"

Prefer clarity over exposing unnecessary sensitive implementation details.

---

## 17. User Management UI

Keep the current Users page for account and assignment management.

In the Manage Roles dialog:

show:

```text
System Roles
Custom Roles
```

Only show valid system roles based on current position compatibility.

Show all ACTIVE custom roles the selected user can receive.

Custom role assignment must require selecting the organizational scope.

Show role type badge:

```text
System
Custom
Special Capability
```

Special Capability management remains separate from roles.

---

## 18. Do not merge SpecialCapability into custom roles

Preserve:

```php
SpecialCapability
```

as its own domain concept.

Custom roles must not replace delegation.

Specifically do not allow custom role assignment to reproduce:

```text
INTERNAL_MEETING_REQUEST_APPROVAL
DIRECTORATE_MINUTES_APPROVAL
BIOMETRIC_ENROLLMENT
```

unless the existing capability architecture explicitly says otherwise.

---

## 19. RBAC synchronizer behavior

Review `AmmsRbacSynchronizer`.

After Role Management v2:

* synchronize canonical system roles
* synchronize canonical permissions
* do NOT delete custom roles
* do NOT overwrite custom role permissions
* do NOT modify custom role scopes
* do NOT modify user assignments
* clear permission cache

System role permissions remain canonical.

Custom role permissions remain admin-managed.

Important:

Unknown permissions attached to SYSTEM roles must not become a permanent bypass mechanism.

Audit current preservation behavior and establish a safe rule.

Preferred direction:

```text
SYSTEM roles
→ canonical permissions controlled by registry

CUSTOM roles
→ admin-managed permissions limited by assignability metadata
```

Preserve backward compatibility carefully; if legacy unknown permissions exist, report them rather than silently deleting production data.

---

## 20. Database and migration safety

Use forward-only migrations.

Do NOT modify historical RBAC migrations.

Do NOT modify Spatie migration history.

Do NOT run destructive production migrations.

All new role metadata migration must be reversible.

Add indexes/unique constraints appropriately.

---

## 21. Security invariants

Add explicit tests proving:

```text
CUSTOM ROLE cannot impersonate DIRECTOR
CUSTOM ROLE cannot impersonate HIGH_AUTHORITY
CUSTOM ROLE cannot receive role.manage
CUSTOM ROLE cannot receive organization.manage
CUSTOM ROLE cannot receive approval-delegated permission
CUSTOM ROLE cannot receive authority decision permission
CUSTOM ROLE cannot bypass organizational scope
CUSTOM ROLE cannot be assigned if inactive
SYSTEM ROLE cannot be edited/deactivated
Last SYSTEM_ADMIN protection remains intact
```

Also prove:

```text
REPORT_VIEWER with reporting permission
can view allowed reporting data inside scope

REPORT_VIEWER outside scope
cannot access the other unit's scoped data
```

---

## 22. Scope-aware permission enforcement audit

This is important.

Some existing application code may use only:

```php
$user->can('permission.name')
```

without checking organizational scope.

Before enabling custom roles, audit every permission selected as `custom_role_assignable`.

For each such permission ensure access is additionally constrained by:

```text
organizational scope
resource ownership
policy/query scope
or equivalent domain restriction
```

If a permission currently has global behavior when granted through Spatie, do NOT expose it to custom roles until scope-safe enforcement is implemented.

This audit is mandatory before finalizing the custom-role assignable whitelist.

---

## 23. Tests

Add dedicated feature tests such as:

```text
CustomRoleManagementTest
CustomRolePermissionPolicyTest
CustomRoleScopeTest
SystemRoleProtectionTest
RoleManagementAuditTest
```

Cover:

```text
create custom role
duplicate name rejected
reserved system role name rejected
assign allowed permission
protected permission rejected
edit custom role
deactivate custom role
inactive role assignment rejected
assign custom role with scope
revoke custom role
permission cache refresh
audit log written
```

---

## 24. Regression testing

Run all existing relevant tests including:

```bash
php artisan test tests/Feature/RoleModelConsolidationTest.php
php artisan test tests/Feature/OrganizationalUnitManagementTest.php
php artisan test tests/Feature/CanonicalOrganizationSeederTest.php
php artisan test tests/Feature/InternalMeetingApprovalDelegationTest.php
php artisan test tests/Feature/InterDirectorateMeetingRequestTest.php
php artisan test tests/Feature/MultiDirectorateMeetingRequestTest.php
php artisan test tests/Feature/HighAuthorityMeetingRequestTest.php
php artisan test tests/Feature/MultiHighAuthorityMeetingRequestTest.php
php artisan test tests/Feature/AuthorityInitiatedMeetingRequestTest.php
```

Then run:

```bash
php artisan test
```

Frontend:

```bash
npx tsc --noEmit
npm run build
```

Report exact results.

---

## 25. Before coding

First perform a read-only audit and report:

1. current role-management endpoints
2. current Spatie role schema
3. current role scope architecture
4. all places where canonical role identity is required in workflow logic
5. all candidate custom-role-assignable permissions
6. scope-safety status of each candidate
7. proposed database changes
8. proposed API changes
9. proposed frontend changes

Then implement only after the audit confirms the design fits the current codebase.

Do not introduce broad architectural changes beyond Role Management v2.

---

## 26. Final acceptance criteria

The implementation is complete when:

```text
SYSTEM_ADMIN can create a CUSTOM role
SYSTEM_ADMIN can choose safe assignable permissions
SYSTEM_ADMIN can modify/deactivate that role
SYSTEM_ADMIN can assign it to a user within organizational scope

SYSTEM roles remain protected
Workflow roles retain semantic authority
SpecialCapability remains separate
Scope restrictions remain enforced
Permission cache remains correct
All access changes are audited
Existing workflows behave identically
Full backend tests pass
Frontend typecheck/build pass
```

At the end provide a concise report with:

```text
files changed
migrations added
new models/services/controllers
new routes
new UI pages/components
permission classification decisions
scope-safety findings
tests and exact results
remaining deliberate limitations
```
