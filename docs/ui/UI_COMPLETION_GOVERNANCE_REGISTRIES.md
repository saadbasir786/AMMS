# UI Completion — Governance & Registries

This slice modernizes the remaining primary registry and active-governance surfaces for FLOW-07, FLOW-08, FLOW-09 and FLOW-10A.

## Invariants
- Presentation-only refactor; Backend validation and authorization remain authoritative.
- No new role, permission, lifecycle state, endpoint, metric, notification behavior or FLOW.
- FLOW-07 keeps append-only schedule versions, proposal acknowledgement, alternative-request and final confirmation semantics.
- FLOW-08 keeps owner-scoped participant-change items, stale-schedule refresh, submit/confirm/return/reject semantics.
- FLOW-09 remains terminal registry/session foundation only and makes no biometric or attendance claim.
- FLOW-10A exposes only safe biometric metadata/status in the web UI; no raw fingerprint template is requested or rendered.
- FLOW-10B remains deferred.

## Scope
- FLOW-07 active reschedule workspace.
- FLOW-08 active participant-change workspace.
- FLOW-09 Terminal Registry and Meeting Terminal Workspace.
- FLOW-10A Biometric Registry.

Existing detail and attendance surfaces keep the same APIs and frozen contracts.
