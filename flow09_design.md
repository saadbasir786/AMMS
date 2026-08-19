# FLOW-09 Design

## Persistent concepts

| Table | Role | Security/state notes |
|---|---|---|
| `terminals` | Generic physical terminal registry | Immutable server UUID, organization/code uniqueness, `ACTIVE/INACTIVE/MAINTENANCE/REVOKED`, nullable current-session pointer. |
| `terminal_credentials` | Long-lived device credential metadata | Only SHA-256 hash stored; issue/rotate/revoke are immutable-history operations. |
| `meeting_terminal_assignments` | Authorization for one terminal to serve one Meeting in a window | Captures current schedule and participant version IDs; `ASSIGNED/STALE/REVOKED/EXPIRED`; UUID exposed publicly. |
| `terminal_meeting_sessions` | Bound active device session | Stores only session-token hash; binds terminal, assignment, Meeting, schedule version and participant version; `ACTIVE/CLOSED/EXPIRED/REVOKED`. |

Existing Meetings receive no fake assignment backfill. Meeting receives only a nullable `current_terminal_session_id` pointer if needed for efficient lookup; the authoritative assignment/session rows remain separate.

## Status semantics

Assignment effective staleness is derived from its baseline IDs versus current Meeting pointers, regardless of stored status. Any activation or manifest request re-checks the binding. A stale assignment returns `TERMINAL_ASSIGNMENT_STALE` with safe `schedule_changed` and `participants_changed` flags. Refresh is explicit and audited.

A session is effectively expired if `now >= expires_at`, even if its database status still says `ACTIVE`. A current session is valid only when its terminal, assignment, Meeting, and both version bindings remain valid. FLOW-07/08 version changes invalidate subsequent session calls with `TERMINAL_SESSION_STALE`.

## Human authorization

Terminal infrastructure uses `terminal.view`, `terminal.register`, `terminal.manage`, `terminal.rotate-credential`, and `terminal.revoke`. Meeting assignment uses `meeting-terminal.view`, `meeting-terminal.assign`, `meeting-terminal.refresh`, and `meeting-terminal.revoke`. Session history uses `terminal-session.view`.

`TerminalPolicy` requires active human user plus permission and `SYSTEM_ADMIN` scope for registry operations. `MeetingPolicy` gains FLOW-09 methods. Assignment ownership is resolved by `TerminalAssignmentGovernanceResolver`: internal/peer flows use the meeting coordinator or a scoped `MEETING_COORDINATOR`; authority-controlled flows use `EXECUTIVE_SECRETARY` in the authority-office scope. System administration does not automatically confer assignment ownership.

## Terminal authentication

Human routes remain under `auth:sanctum`. Terminal routes use a dedicated middleware that extracts `Authorization: Bearer`, hashes the raw credential, resolves a non-revoked, non-expired credential belonging to an `ACTIVE` terminal, touches `last_used_at` and `last_seen_at` without audit spam, and injects `request->attributes->set('terminal', $terminal)`. Terminal endpoints never call `auth()->user()` and never expose users, roles, organizations, or unrelated assignments.

## Assignment invariants

The Meeting and terminal must share `organization_id`. Meeting must be `SCHEDULED` and have current schedule/participant pointers. The terminal must be `ACTIVE`. `valid_from < valid_until` and the window must cover the official Meeting interval in `Asia/Kabul` unless organization timezone is later configured. A terminal cannot have two overlapping non-revoked/non-expired assignments; adjacent windows are valid. A Meeting may have multiple terminals. A duplicate active Meeting/terminal assignment is rejected.

## Activation invariants

Activation locks in order `Meeting → Terminal → Assignment → Session`. It validates ownership of assignment by the authenticated terminal, terminal availability, assignment state/window, current version pointers, Meeting status, and absence of an active session. Exactly one active session can be created. The returned raw session token is one-time display in the response; only its hash is persisted and it expires no later than `valid_until`.

## Manifest

Manifest is generated solely from the session-bound schedule version and participant version. It contains session/terminal/Meeting identifiers, subject, bound version IDs, schedule fields, and participant employee ID, display name, organizational unit ID/name, and participant role. It excludes email, phone, passwords, RBAC, and biometric data. A canonical JSON SHA-256 checksum is returned.

## Explicit actions and routes

Human routes: terminal registry CRUD/status/credential routes; meeting terminal workspace, assignment create/refresh/revoke. Terminal routes: `/api/terminal/v1/me`, `/heartbeat`, `/assignments/current`, `/assignments/{assignment}/activate`, `/sessions/{session}/manifest`, `/sessions/{session}/close`.

All writes use named Actions and immutable audit events. No cancellation, attendance, biometric, SDK, or FLOW-10 behavior is included.
