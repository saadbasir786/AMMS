# FLOW-12 Verification Report

## Release status

> **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

FLOW-12 has been implemented and verified as an additive Decision Follow-up and Action Item domain. It is **not automatically APPROVED or FROZEN**. FLOW-10B remains **DEFERRED / WAITING FOR DERMALOG SVT3000 SE**, and no FLOW-13 work has started.

## Scope implemented

FLOW-12 begins only from a Meeting whose status is `COMPLETED` and whose Minutes root is `FINALIZED` with a current finalized version. Each finalized Decision receives at most one immutable Follow-up root. The public API now uses the immutable `MeetingMinuteVersionDecision` identifier in `/api/decisions/{decision}/...` routes; the controller resolves the associated Follow-up internally. Decision text, target date, responsible unit, responsible employee, finalized version, and official Minutes fields remain source data; operational progress is stored only in additive Follow-up and Action Item tables.

Classification and operational lifecycle are separate concerns. `classification` is `UNCLASSIFIED`, `ACTION_REQUIRED`, or `NO_ACTION_REQUIRED`; `status` is the operational lifecycle `OPEN`, `ACTIVE`, or `CLOSED`. `ACTION_REQUIRED` remains `OPEN` until its first Action Item is created, then becomes `ACTIVE`; `NO_ACTION_REQUIRED` closes immediately. This prevents classification from being incorrectly represented as an operational state.

Action Items implement `OPEN → IN_PROGRESS → PENDING_VERIFICATION → COMPLETED`, the `IN_PROGRESS ↔ BLOCKED` loop, controlled completion return, administrative cancellation, derived overdue state, progress 0–100, mandatory completion evidence/note policy, controlled assignment, due-date extension requests, append-only updates, assignment history, private evidence metadata, audit events, notifications, and owner-only verification.

## Evidence summary

| Gate | Result | Evidence |
|---|---:|---|
| Focused FLOW-12 Feature suite | **9 tests / 99 assertions — PASS** | `backend/tests/Feature/Flow12DecisionFollowUpTest.php` |
| Independent per-flow FLOW-01–FLOW-06 governance suite | **6 tests / 31 assertions — PASS** | `backend/tests/Feature/Flow12PerFlowGovernanceTest.php` |
| Full Laravel regression | **347 tests / 1,450 assertions — PASS** | `flow12_full_regression.log` |
| Frontend production build | **PASS / 1,864 modules transformed** | `flow12_frontend_build.log` |
| Extended real HTTP manual gate | **31 / 31 — PASS** | `flow12_manual_gate.json` |
| FLOW-05/FLOW-06 ownership HTTP gate | **14 / 14 — PASS** | `flow12_manual_governance_gate.json` |
| MariaDB action-level races | **7 race groups — PASS** | `flow12_action_race_probe.json` |
| Canonical final invariants | **PASS** | `flow12_final_invariants.json` |
| Negative matrix | **38 documented boundaries** | `flow12_negative_matrix.md` |
| Private-evidence privacy boundary | **PASS** | `flow12_final_invariants.json` |

## Real HTTP manual gate

The extended manual gate used a fresh disposable completed Meeting and finalized Minutes fixture through the real Laravel HTTP server. It exercised login, finalized-source access, canonical Decision-ID route usage, Follow-up initialization, separate classification and status behavior, no-action closure, Action Item creation, blocked/unblocked transitions, progress bounds, return/resubmit, extension request and approval, completion submission, self-verification denial, owner verification, overdue derivation, reassignment boundaries, private evidence upload/download, and final source immutability. All **31 of 31** steps passed.

A separate ownership gate covered FLOW-05 and FLOW-06. The Executive Secretary successfully performed the mapped Follow-up owner operations, while Authority Office, High Authority, and SYSTEM_ADMIN were denied where required. All **14 of 14** steps passed.

The final manual assertions returned closed Follow-ups, terminal Action Items, completed Meeting state, finalized Minutes state, and unchanged source Decision data. Passwords, bearer tokens, raw evidence bytes, and biometric data are not recorded in the transcripts.

## MariaDB concurrency

The isolated MariaDB probe verified seven race groups with transactional row locking and uniqueness protections: concurrent Follow-up initialization produced exactly one root row; concurrent Action Item creation produced unique sequence allocation; concurrent extension requests produced one pending request and one `ACTION_ITEM_EXTENSION_ALREADY_PENDING` rejection; concurrent extension approval versus rejection produced one terminal review and one `ACTION_ITEM_EXTENSION_NOT_PENDING` rejection; progress versus cancellation serialized into a valid outcome; completion verification versus reassignment produced one completion and rejected the invalid competing mutation; and concurrent completion verification produced exactly one completion audit event. The canonical probe artifact reports `pass=true`.

## Canonical invariant snapshot

| Invariant | Observed value |
|---|---:|
| Meeting Request ID | `35` |
| Request status | `CONVERTED_TO_MEETING` |
| Meeting status | `COMPLETED` |
| Minutes status | `FINALIZED` |
| Final Minutes version | `12` |
| Decision rows | `2` |
| Follow-up root rows | `2` |
| Unclassified Follow-ups | `0` |
| Action-required Follow-ups | `1` |
| No-action-required Follow-ups | `1` |
| Active / Open / Closed Follow-ups | `0 / 0 / 2` |
| Action Item status counts | `COMPLETED: 1` |
| Duplicate Follow-up roots | `0` |
| Duplicate Action Item sequences | `0` |
| Duplicate completion records | `0` |
| Duplicate pending-extension markers | `0` |
| Request-to-Meeting duplicates | `0` |
| Assignment history rows | `2` |
| Progress update rows | `5` |
| Completion submissions / verifications | `2 / 1` |
| Extension requests | `1 total / 1 approved / 0 pending` |
| Source Decision mutations | `0` |
| Meeting / Minutes status mutations | `0 / 0` |
| Documentary responsibility / target-date mutations | `0 / 0` |
| Biometric-sensitive FLOW-12 fields | `0` |
| Public evidence URLs | `0` |
| Raw tokens/passwords in evidence | `0` |

## Authorization and immutability

Every state-changing operation passes through a FormRequest, explicit policy registration, active-user and Spatie permission checks, organizational scope checks, Follow-up ownership or responsible-unit checks, Action Item state validation, a database transaction, and row locking. `SYSTEM_ADMIN` is not treated as a business owner by default. Authority Office and High Authority are not automatically Follow-up Owners; owner mapping remains flow-derived.

There are no normal APIs for editing or deleting update history, assignment history, extension history, evidence metadata, or audit events. Evidence is stored on the private filesystem disk and is downloadable only through an authorized, item-bound route. No finalized Decision row or official Minutes version is updated by FLOW-12.

## Migration and compatibility boundary

The implementation uses only additive migrations with restrictive foreign keys, unique Decision-root linkage, unique Action Item sequences, a pending-extension uniqueness marker, and optimistic `lock_version`. Existing FLOW-01 through FLOW-11 tables and contracts were not redesigned. The additive classification migration was applied to the isolated MariaDB concurrency schema; the full regression suite and frontend production build remain green after the correction.

## Verification files

The canonical evidence set is:

- `FLOW12_VERIFICATION.md`
- `FLOW12_PROMPT_REQUIREMENTS.md`
- `FLOW12_SCHEMA_DESIGN.md`
- `flow12_negative_matrix.md`
- `flow12_manual_gate.json`
- `flow12_manual_gate_summary.log`
- `flow12_manual_governance_gate.json`
- `flow12_manual_governance_gate_summary.log`
- `flow12_action_race_probe.json`
- `flow12_final_invariants.json`
- `flow12_full_regression.log`
- `flow12_focused_test.log`
- `flow12_per_flow_governance.log`
- `flow12_strict_focused_test.log`
- `flow12_frontend_build.log`
- `backend/database/migrations/2026_08_20_120020_separate_flow12_classification.php`
- `backend/tests/Feature/Flow12DecisionFollowUpTest.php`
- `backend/tests/Feature/Flow12PerFlowGovernanceTest.php`

## Explicit release boundary

FLOW-12 is ready for explicit user review and approval. The implementation must not be described as APPROVED/FROZEN until that approval is provided. FLOW-10B remains deferred, and the next workflow phase has not been started.

## References

[1]: flow12_full_regression.log "Full Laravel regression evidence"
[2]: flow12_frontend_build.log "Frontend production build evidence"
[3]: flow12_manual_gate.json "Extended real HTTP manual gate transcript"
[4]: flow12_manual_governance_gate.json "FLOW-05/FLOW-06 ownership gate transcript"
[5]: flow12_action_race_probe.json "MariaDB action-level race probe"
[6]: flow12_final_invariants.json "Canonical before/after invariant artifact"
[7]: flow12_negative_matrix.md "FLOW-12 negative boundary matrix"
[8]: backend/tests/Feature/Flow12DecisionFollowUpTest.php "FLOW-12 Feature tests"
[9]: backend/tests/Feature/Flow12PerFlowGovernanceTest.php "Per-flow governance tests"

The evidence files above are repository-local artifacts generated by the verification commands and are intended to be reviewed together with this report.
