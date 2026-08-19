# FLOW-09 Architecture Audit

## Frozen boundaries

AMMS currently exposes Laravel 13 REST APIs under `/api`, uses Laravel Sanctum for human users, Spatie Permission with scoped roles, and React 19/TypeScript/Vite for the Persian/Dari RTL frontend. FLOW-01 through FLOW-08 are frozen. FLOW-09 must be additive and must not reopen `MeetingRequest`, change Meeting identity or status, mutate schedule/participant versions, or implement FLOW-10 biometric attendance.

## Existing authoritative model seams

`Meeting` already owns `current_schedule_version_id`, `current_participant_version_id`, and the active FLOW-07/FLOW-08 case pointers. `MeetingScheduleVersion` and `MeetingParticipantVersion` are immutable version records; participant members are stored in `MeetingParticipantVersionMember`. FLOW-09 should reference these pointers at assignment creation and at session activation, not copy mutable Meeting or participant state into a new governance model.

The existing `MeetingResource`, `DirectoryController`, and Meeting detail API are the appropriate integration seams for exposing assignment/session summaries. A dedicated `/meetings/{meeting}/terminals` workspace should follow the FLOW-07/FLOW-08 meeting-scoped controller pattern. The current frontend router is centralized in `frontend/src/main.tsx`; new RTL routes should be added there without redesigning existing pages.

## Existing authorization seams

Human operations are policy/action based. `MeetingPolicy` delegates FLOW-07 and FLOW-08 governance to resolver classes and checks active user, permission, resource visibility, scope, and state. `User::isActive()`, `hasScopedRole()`, and `scopedRoleUnitIds()` are the established human authorization primitives. FLOW-09 must add separate policy abilities and permissions for terminal registry, assignment, refresh, and revoke. `SYSTEM_ADMIN` terminal infrastructure authority must not automatically confer Meeting assignment authority.

## Authentication boundary

The current human API is grouped under `auth:sanctum` in `routes/api.php`. Terminal endpoints must be placed in a separate `/api/terminal/v1` namespace and use a dedicated terminal credential resolver/middleware. A terminal is not a User, Employee, or Sanctum token subject. Permanent terminal credentials and short-lived session credentials must be stored only as hashes and must never be logged or returned after first issuance.

## Migration precedent

FLOW-07 and FLOW-08 use additive migrations, indexed current pointers, late foreign keys where needed, and non-destructive historical handling. FLOW-09 needs four distinct tables: `terminals`, `terminal_credentials`, `meeting_terminal_assignments`, and `terminal_meeting_sessions`. Existing Meetings need no fake assignment backfill. Any optional current-session pointer must be additive and nullable.

## Required lock order

Actions that interact across resources must use the deterministic order `Meeting → Terminal → Assignment → Session`, matching the Prompt 09 contract. Assignment creation/refresh/revocation and activation must re-check organization, status, time window, current version pointers, and conflict state inside the transaction.

## Main implementation risks

The main risks are accidentally treating stale assignments as valid because status is still `ASSIGNED`, leaking employee personal data through the terminal manifest, mixing human and terminal authentication, allowing a revoked terminal/session to survive a concurrent revocation, and adding assignment semantics directly into frozen FLOW-07/FLOW-08 Actions. These will be addressed with dedicated support components, policy methods, terminal middleware, version-bound manifest generation, and MariaDB concurrency probes.

## Phase-2 design decision

Implement FLOW-09 as a standalone post-Meeting subsystem with explicit Actions and resources. Keep assignment status separate from session status, use immutable UUIDs, enforce organization and flow governance through a `TerminalAssignmentGovernanceResolver`, and add only narrowly scoped additions to Meeting resources, policies, routes, and the RTL router.
