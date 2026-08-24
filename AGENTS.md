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
     - Backend authorization is authoritative; frontend visibility must mirror role, permission, capability, and unit-scope boundaries.
     - `BIOMETRIC_ENROLLMENT_OFFICER` is retired; biometric enrollment uses the scoped `BIOMETRIC_ENROLLMENT` delegated capability.

3. **Autonomous Rule Evolution Directive (AI Self-Evolution)**
   - AI assistants may autonomously evolve implementation conventions such as UI consistency, reusable component patterns, and non-domain architecture guidance.
   - AI assistants must **not** autonomously change roles, permissions, organizational-scope semantics, delegated capabilities, business-flow authority, approval paths, or lifecycle states. These require an explicit user/domain decision and the authorization charter takes precedence over general autonomous-evolution guidance.

4. **Mandatory Per-Feature Git Commit & Push Policy**
   - Once a feature or capability is completed and verified, and upon user approval, a clean, dedicated Git commit must be recorded and pushed to the remote repository BEFORE starting the next feature.
   - Never pile up multiple unrelated refactored features in an uncommitted state.
