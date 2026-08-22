# FLOW-10A / FLOW-11 UI migration boundary

## Scope

This slice modernizes selected runtime surfaces only. Business workflows, API contracts, authorization, lifecycle states, database behavior, and backend validation remain authoritative and unchanged.

### FLOW-10A

Modernized:
- employee biometric status/detail;
- meeting attendance workspace;
- responsive tables, summaries, error/loading states and manual-attendance presentation.

Preserved without redesign:
- biometric registry list remains the existing implementation;
- existing `/employees/{id}/biometrics` response is authoritative;
- existing `/meetings/{id}/attendance` and `/meetings/{id}/attendance/manual` contracts are unchanged.

Security boundary:
- FLOW-10A remains vendor-independent;
- FLOW-10B physical DERMALOG integration is still deferred;
- no raw biometric template is introduced into browser UI or persisted by this refactor;
- no new capture, matching, enrollment, device, attendance, or biometric capability is added.

### FLOW-11

Modernized:
- meeting execution workspace;
- meeting/session summary;
- start/end execution presentation;
- explicit confirmation before ending a meeting;
- links to attendance and minutes remain unchanged.

Preserved without redesign:
- meeting minutes workspace remains the existing implementation;
- minutes drafts, versions, reviews, acknowledgements, authority approval, finalization and document behavior are untouched;
- execution endpoints remain `/meetings/{id}/execution`, `/meetings/{id}/execution/start`, and `/meetings/{id}/execution/end`.

## Invariants

- Backend authorization remains authoritative.
- No role or permission is added or broadened.
- No FLOW or lifecycle state is added.
- Schedule and participant version semantics are unchanged.
- Attendance history semantics are unchanged.
- FLOW-10B remains outside this software baseline.
- Legacy implementations are retained as `flow10_legacy.tsx` and `flow11_legacy.tsx` for controlled rollback and untouched surfaces.
