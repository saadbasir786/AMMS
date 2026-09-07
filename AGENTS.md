# AMMS Workspace Directives & Development Guidelines

Welcome to the Afghanistan Meetings Management System (AMMS) codebase. All development, refactoring, and feature additions must strictly adhere to the structured rules maintained under `.agents/rules/`.

## Active Workspace Charters

1. **Frontend Architecture & Design Standards**
   - Reference File: [.agents/rules/frontend-architecture-and-design.md](.agents/rules/frontend-architecture-and-design.md)
   - Core Directives:
     - Mandatory Feature-Based Architecture in `frontend/src/features/<feature>/`
     - Mandatory usage of `MultiCalendarDatePicker` (Afghan Solar Hijri) and `TimePicker`
     - Strict humanized Persian UI text using `اقدامات قابل انجام` and zero raw workflow codes (`FLOW-XX`)
     - Smart pre-population of proposed values into subsequent workflow stages
     - Consistent shadcn/tailwind design tokens with `rounded-2xl` and `rounded-xl`

2. **Roles, Permissions & Organizational Scope**
   - Reference File: [.agents/rules/roles-permissions-and-scope.md](.agents/rules/roles-permissions-and-scope.md)
   - Core Directives:
     - Organizational roles must represent real administrative responsibilities.
     - Temporary/special system duties must be modeled as explicit, revocable, auditable scoped capabilities rather than invented organizational roles.
     - `SYSTEM_ADMIN` is technical administration only and is never a meeting-workflow business superuser.
     - Backend authorization is authoritative; frontend visibility must mirror role, permission, capability, ownership, lifecycle, and unit-scope boundaries.
     - The canonical Role × Flow authority matrix covers FLOW-01 through FLOW-14; FLOW-10B remains deferred.
     - `MEETING_COORDINATOR` and `BIOMETRIC_ENROLLMENT_OFFICER` are retired/non-canonical and must not be reintroduced from historical migrations or fixtures.
     - Biometric enrollment uses the scoped `BIOMETRIC_ENROLLMENT` delegated capability.
     - Historical migrations do not override current Policies/Resolvers or the authorization charter.

3. **Backend Architecture, API & Engineering Standards**
   - Reference File: [.agents/rules/backend-architecture-and-api-standards.md](.agents/rules/backend-architecture-and-api-standards.md)
   - Core Directives:
     - Mandatory Server-Side Pagination with defensive bounds (`page`, `per_page`) for dynamic/unbounded lists
     - Standard `JsonResource::collection($paginator)` envelope with `meta` (`current_page`, `last_page`, `total`, `from`, `to`)
     - Authoritative scoped counters (`unread_count`, `total_count`, `stats`) calculated on backend in additional response fields; preserve pagination meta semantics
     - Strict Route Precedence: static endpoints before parameterized `{wildcard}` routes
     - Atomic database transactions (`DB::transaction`) for state transitions and workflows
     - Immutable audit logs for every workflow transition and administrative decision
     - FormRequest validation and explicit input allowlists; reuse canonical Policies/Resolvers for scope and authority
     - Concurrency protection and defined retry behavior for competing workflow writes; external side effects after commit
     - Forward migrations, historical data preservation, private file access and safe error/logging contracts
     - Preserve existing API consumers and follow the backend module-development checklist for new and edited modules
     - Mandatory automated Feature Tests in `backend/tests/Feature/` covering happy path, 401, 403, and user isolation

4. **Autonomous Rule Evolution Directive (AI Self-Evolution)**
   - AI assistants may autonomously evolve implementation conventions such as UI consistency, reusable component patterns, and non-domain architecture guidance.
   - AI assistants must **not** autonomously change roles, permissions, organizational-scope semantics, delegated capabilities, business-flow authority, approval paths, ownership rules, or lifecycle states. These require an explicit user/domain decision and the authorization charter takes precedence over general autonomous-evolution guidance.

5. **Mandatory Per-Feature Git Commit & Push Policy**
   - Once a feature or capability is completed and verified, and upon user approval, a clean, dedicated Git commit must be recorded and pushed to the remote repository BEFORE starting the next feature.
   - Never pile up multiple unrelated refactored features in an uncommitted state.
