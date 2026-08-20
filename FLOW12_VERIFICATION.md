# FLOW-12 Verification Report

## Release status

> **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

FLOW-12 has been implemented and verified as an additive Decision Follow-up and Action Item domain. It is **not automatically APPROVED or FROZEN**. FLOW-10B remains **DEFERRED / WAITING FOR DERMALOG SVT3000 SE**, and no FLOW-13 work has started.

## Scope implemented

FLOW-12 begins only from a Meeting whose status is `COMPLETED` and whose Minutes root is `FINALIZED` with a current finalized version. Each finalized Decision receives at most one immutable Follow-up root. Decision text, target date, responsible unit, responsible employee, finalized version, and official Minutes fields remain source data; operational progress is stored only in additive Follow-up and Action Item tables.

Action Items implement `OPEN → IN_PROGRESS → PENDING_VERIFICATION → COMPLETED`, the `IN_PROGRESS ↔ BLOCKED` loop, controlled completion return, administrative cancellation, derived overdue state, progress 0–100, mandatory completion evidence/note policy, controlled assignment, due-date extension requests, append-only updates, assignment history, private evidence metadata, audit events, notifications, and owner-only verification.

## Evidence summary

| Gate | Result | Evidence |
|---|---:|---|
| Focused FLOW-12 Feature suite | **4 tests / 49 assertions — PASS** | `backend/tests/Feature/Flow12DecisionFollowUpTest.php` |
| Full Laravel Feature regression | **336 tests / 1,369 assertions — PASS** | `flow12_full_regression.log` |
| Frontend production build | **PASS / 1,864 modules transformed** | `flow12_frontend_build.log` |
| Real HTTP manual gate | **18 / 18 — PASS** | `flow12_manual_gate.json` |
| MariaDB action-level races | **PASS** | `flow12_action_race_probe.json` |
| Canonical final invariants | **PASS** | `flow12_final_invariants.json` |
| Negative matrix | **38 documented boundaries** | `flow12_negative_matrix.md` |
| Private-evidence privacy boundary | **PASS** | `flow12_final_invariants.json` |

## Real HTTP manual gate

The manual gate used a fresh disposable completed Meeting and finalized Minutes fixture through the real Laravel HTTP server. It exercised login, finalized-source access, Follow-up initialization, classification, Action Item creation, start, blocked/unblocked transitions, progress to 100%, extension request, owner approval, completion submission, rejection of self-verification, owner verification, and final source immutability.

The final assertion returned `followup_status=CLOSED`, `meeting_status=COMPLETED`, `minutes_status=FINALIZED`, and `source_decision_unchanged=true`. The transcript contains 18 sanitized step records. Passwords, bearer tokens, raw evidence bytes, and biometric data are not recorded.

## MariaDB concurrency

The isolated MariaDB probe verified three race groups. Concurrent Follow-up initialization produced exactly one root row. Concurrent extension requests produced exactly one pending request and one `ACTION_ITEM_EXTENSION_ALREADY_PENDING` rejection. Concurrent completion verification produced exactly one completed Action Item and one `ACTION_ITEM_NOT_PENDING_VERIFICATION` rejection, with exactly one completion audit event.

## Canonical invariant snapshot

| Invariant | Observed value |
|---|---:|
| Meeting status | `COMPLETED` |
| Minutes status | `FINALIZED` |
| Follow-up root rows | `1` |
| Follow-up status | `CLOSED` |
| Action Item status | `COMPLETED` |
| Action Item progress | `100` |
| Action Item update rows | `5` |
| Extension requests | `1 total / 1 approved / 0 pending` |
| Completion audit events | `1` |
| Biometric-sensitive FLOW-12 fields | `0` |
| Public evidence URLs | `0` |
| Raw tokens/passwords in evidence | `0` |

## Authorization and immutability

Every state-changing operation passes through a FormRequest, explicit policy registration, active-user and Spatie permission checks, organizational scope checks, Follow-up ownership or responsible-unit checks, Action Item state validation, a database transaction, and row locking. `SYSTEM_ADMIN` is not treated as a business owner by default. Authority Office and High Authority are not automatically Follow-up Owners; owner mapping remains flow-derived.

There are no normal APIs for editing or deleting update history, assignment history, extension history, evidence metadata, or audit events. Evidence is stored on the private filesystem disk and is downloadable only through an authorized, item-bound route. No finalized Decision row or official Minutes version is updated by FLOW-12.

## Migration and compatibility boundary

The implementation uses only additive migrations with restrictive foreign keys, unique Decision-root linkage, unique Action Item sequences, a pending-extension uniqueness marker, and optimistic `lock_version`. Existing FLOW-01 through FLOW-11 tables and contracts were not redesigned. The full regression suite remains green after migration and frontend integration.

## Verification files

The canonical evidence set is:

- `FLOW12_VERIFICATION.md`
- `FLOW12_PROMPT_REQUIREMENTS.md`
- `FLOW12_SCHEMA_DESIGN.md`
- `flow12_negative_matrix.md`
- `flow12_manual_gate.json`
- `flow12_action_race_probe.json`
- `flow12_final_invariants.json`
- `flow12_full_regression.log`
- `flow12_focused_test.log`
- `flow12_frontend_build.log`
- `backend/tests/Feature/Flow12DecisionFollowUpTest.php`

## Explicit release boundary

FLOW-12 is ready for explicit user review and approval. The implementation must not be described as APPROVED/FROZEN until that approval is provided. FLOW-10B remains deferred, and the next workflow phase has not been started.
