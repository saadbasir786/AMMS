# FLOW-12 Verification Report

## Release status

> **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

FLOW-12 has been implemented and verified as an additive Decision Follow-up and Action Item domain. It is **not automatically APPROVED or FROZEN**. FLOW-10B remains **DEFERRED / WAITING FOR DERMALOG SVT3000 SE**, and no FLOW-13 work has started.

## Scope implemented

FLOW-12 begins only from a Meeting whose status is `COMPLETED` and whose Minutes root is `FINALIZED` with a current finalized version. Each finalized Decision receives at most one immutable Follow-up root. The public API uses the immutable `MeetingMinuteVersionDecision` identifier in `/api/decisions/{decision}/...` routes; the controller resolves the associated Follow-up internally. Decision text, target date, responsible unit, responsible employee, finalized version, and official Minutes fields remain source data; operational progress is stored only in additive Follow-up and Action Item tables.

Classification and operational lifecycle are separate concerns. `classification` is `UNCLASSIFIED`, `ACTION_REQUIRED`, or `NO_ACTION_REQUIRED`; `status` is the operational lifecycle `OPEN`, `ACTIVE`, or `CLOSED`. `ACTION_REQUIRED` remains `OPEN` until its first Action Item is created, then becomes `ACTIVE`; `NO_ACTION_REQUIRED` closes immediately. This prevents classification from being incorrectly represented as an operational state.

Action Items implement `OPEN → IN_PROGRESS → PENDING_VERIFICATION → COMPLETED`, the `IN_PROGRESS ↔ BLOCKED` loop, controlled completion return, administrative cancellation, derived overdue state, progress 0–100, mandatory completion note policy, controlled assignment, due-date extension requests, append-only updates, assignment history, private evidence metadata, audit events, notifications, and owner-only verification. Completion submission now revalidates the locked Action Item’s current assignee and governance scope inside the transaction, so a stale assignee cannot submit after reassignment wins a concurrent race. Completion verification also atomically withdraws every pending extension as `WITHDRAWN` with reason `ACTION_ITEM_COMPLETED_BEFORE_EXTENSION_REVIEW`, review metadata, notification, and audit event; therefore `COMPLETED + PENDING extension` is impossible.

## Evidence summary

| Gate | Result | Evidence |
|---|---:|---|
| Focused FLOW-12 Feature suite | **10 tests / 115 assertions — PASS** | `backend/tests/Feature/Flow12DecisionFollowUpTest.php` |
| Independent per-flow FLOW-01–FLOW-06 governance suite | **6 tests / 31 assertions — PASS** | `backend/tests/Feature/Flow12PerFlowGovernanceTest.php` |
| Full Laravel regression | **347 tests / 1,465 assertions — PASS** | [`flow12_full_regression.log`][1] |
| Frontend production build | **PASS / 1,864 modules transformed** | [`flow12_frontend_build.log`][2] |
| Extended real HTTP manual gate | **42 / 42 — PASS** | [`flow12_manual_gate.json`][3] |
| FLOW-05/FLOW-06 completion-governance HTTP gate | **27 / 27 — PASS** | [`flow12_manual_governance_gate.json`][4] |
| MariaDB action-level races | **11 deterministic race groups — PASS** | [`flow12_action_race_probe.json`][5] |
| Canonical final invariants | **PASS** | [`flow12_final_invariants.json`][6] |
| Negative matrix | **42 documented boundaries; static-only rows explicitly labeled** | [`flow12_negative_matrix.md`][7] |
| Private-evidence privacy boundary | **PASS; public URL false; unauthorized and cross-item downloads rejected; audit attachment bytes 0** | [`flow12_manual_gate.json`][3] [`flow12_final_invariants.json`][6] |

## Real HTTP manual gate

The extended manual gate used a fresh disposable completed Meeting and finalized Minutes fixture through the real Laravel HTTP server. It exercised login, finalized-source access, canonical Decision-ID route usage, Follow-up initialization, separate classification and status behavior, no-action closure, Action Item creation, blocked/unblocked transitions, progress bounds, return/resubmit, extension request and approval, completion submission, self-verification denial, owner verification, overdue derivation, reassignment boundaries, private evidence upload/download, and final source immutability.

The privacy matrix additionally executed a forbidden MIME upload and an oversized upload, both rejected with application validation; uploaded valid evidence to two different Action Items; rejected an Authority Office download; rejected a cross-Action-Item evidence lookup with `404`; verified authorized owner downloads before and after completion return; and recorded `public_url=false` in the evidence resource. All **42 of 42** steps passed. The oversized test used a disposable test-server limit of 1 KB so the request reached application validation rather than the PHP request-size ceiling; this does not change the production configuration.

A separate completion-governance gate covered FLOW-05 and FLOW-06 with fresh authority-controlled fixtures. The responsible Directorate employee started, progressed, and submitted completion. The assigned employee, Authority Office, High Authority, and SYSTEM_ADMIN were denied completion verification where required, while the Executive Secretary owner successfully verified completion. The final HTTP assertions confirmed that the Meeting remained `COMPLETED` and Minutes remained `FINALIZED`. All **27 of 27** steps passed.

Passwords, bearer tokens, raw evidence bytes, and biometric data are not recorded in the transcripts. The full evidence classification is recorded in the negative matrix; rows marked `STATIC INSPECTION ONLY` are not represented as runtime passes.

## MariaDB concurrency

The isolated MariaDB probe verified **11 deterministic race groups** with transactional row locking and uniqueness protections. Concurrent Follow-up initialization produced exactly one root row. Concurrent Action Item creation produced distinct sequence identities, emitted Action Item IDs, sequence numbers, and Action Numbers, and produced `duplicate_sequence_count=0`. Concurrent extension requests produced one pending request and one `ACTION_ITEM_EXTENSION_ALREADY_PENDING` rejection. Concurrent extension approval versus rejection produced one terminal review and one `ACTION_ITEM_EXTENSION_NOT_PENDING` rejection. Progress versus cancellation serialized into a valid outcome.

Both submission-versus-reassignment orderings are now forced and recorded. In ordering A, reassignment commits first, the new employee remains authoritative, and the old assignee’s submission is rejected with `UNAUTHORIZED_ACTION_ITEM_UPDATE`. In ordering B, completion submission commits first, the Action Item becomes `PENDING_VERIFICATION` with the original assignee, and the later reassignment is rejected with `ACTION_ITEM_INVALID_STATE`.

Both extension-versus-completion orderings are now forced and recorded. In ordering A, extension approval commits first, the due date changes to the requested date, completion follows, and the extension is `APPROVED` with zero pending extensions. In ordering B, completion commits first, the due date remains unchanged, the pending extension is atomically resolved as `WITHDRAWN`, an `ACTION_ITEM_EXTENSION_WITHDRAWN` audit event is recorded, and later approval is rejected with `ACTION_ITEM_EXTENSION_NOT_PENDING`. The probe reports `completed_items_with_pending_extensions=0` and `pending_extensions_on_terminal_items=0`.

Completion verification versus reassignment produced one completion and rejected the invalid competing mutation. Concurrent completion verification produced exactly one completion audit event. Overdue is explicitly a read-time derived property in this implementation; no scheduled overdue checker or notification command exists, so an overdue-check-versus-completion race is recorded as `NOT IMPLEMENTED / NOT APPLICABLE`, not as a falsely claimed runtime pass. The authoritative final probe reports `race_group_count=11` and `pass=true`.

## Canonical invariant snapshot

| Invariant | Observed value |
|---|---:|
| Meeting ID / number | `53` / `F12-HTTP-WFRRMB5O-MEETING` |
| Meeting Request ID | `56` |
| Request status | `CONVERTED_TO_MEETING` |
| Meeting status | `COMPLETED` |
| Minutes status | `FINALIZED` |
| Final Minutes version | `33` |
| Decision rows | `2` |
| Follow-up root rows | `2` |
| Unclassified / action-required / no-action-required Follow-ups | `0 / 1 / 1` |
| Active / open / closed Follow-ups | `0 / 0 / 2` |
| Action Item count | `2` |
| Action Item status counts | `OPEN: 0; IN_PROGRESS: 0; BLOCKED: 0; PENDING_VERIFICATION: 0; COMPLETED: 1; CANCELLED: 1` |
| Derived overdue count | `0` |
| Duplicate Follow-up roots | `0` |
| Duplicate Action Item sequences | `0` |
| Duplicate completion records | `0` |
| Duplicate pending-extension markers | `0` |
| Completed Items with pending extensions | `0` |
| Pending extensions on terminal Items | `0` |
| Request-to-Meeting duplicates | `0` |
| Assignment history rows | `2` |
| Progress update rows | `5` |
| Completion submissions / verifications | `2 / 1` |
| Extension requests | `1 total / 1 approved / 0 pending` |
| Source Decision mutations | `0` |
| Meeting / Minutes status mutations | `0 / 0` |
| Documentary responsibility / target-date mutations | `0 / 0` |
| Audit attachment bytes / base64 / body content | `0` |
| Extension/completion ordering A / B | `PASS / PASS` |
| Submission/reassignment ordering A / B | `PASS / PASS` |
| Sequence race IDs / numbers | `33, 32` / `3, 2`; duplicate count `0` |
| Biometric-sensitive FLOW-12 fields | `0` |
| Public evidence URLs | `0`; resource explicitly returns `public_url=false` |
| Raw tokens/passwords in evidence | `0` |

The complete before/after source snapshots, all explicit zero-valued Action Item status keys, terminal-extension counters, forced ordering invariants, mutation counters, duplicate counters, sequence identities, and privacy counters are stored in [`flow12_final_invariants.json`][6] and [`flow12_action_race_probe.json`][5]. The artifacts report `pass=true`.

## Authorization and immutability

Every state-changing operation passes through a FormRequest, explicit policy registration, active-user and Spatie permission checks, organizational scope checks, Follow-up ownership or responsible-unit checks, Action Item state validation, a database transaction, and row locking. `SYSTEM_ADMIN` is not treated as a business owner by default. Authority Office and High Authority are not automatically Follow-up Owners; owner mapping remains flow-derived.

There are no normal APIs for editing or deleting update history, assignment history, extension history, evidence metadata, or audit events. Evidence is stored on the private filesystem disk and is downloadable only through an authorized, item-bound route. The resource exposes the authorized `download_url` separately from `public_url=false`; no finalized Decision row or official Minutes version is updated by FLOW-12.

## Migration and compatibility boundary

The implementation uses only additive migrations with restrictive foreign keys, unique Decision-root linkage, unique Action Item sequences, a pending-extension uniqueness marker, and optimistic `lock_version`. Existing FLOW-01 through FLOW-11 tables and contracts were not redesigned. The additive classification migration was applied to the isolated MariaDB concurrency schema. The full Feature regression and frontend production build remain green after the final correction.

## Verification files

The canonical evidence set is:

- [`FLOW12_VERIFICATION.md`](FLOW12_VERIFICATION.md)
- [`FLOW12_PROMPT_REQUIREMENTS.md`](FLOW12_PROMPT_REQUIREMENTS.md)
- [`FLOW12_SCHEMA_DESIGN.md`](FLOW12_SCHEMA_DESIGN.md)
- [`flow12_negative_matrix.md`](flow12_negative_matrix.md)
- [`flow12_manual_gate.json`](flow12_manual_gate.json)
- [`flow12_manual_gate_summary.log`](flow12_manual_gate_summary.log)
- [`flow12_manual_governance_gate.json`](flow12_manual_governance_gate.json)
- [`flow12_manual_governance_gate_summary.log`](flow12_manual_governance_gate_summary.log)
- [`flow12_action_race_probe.json`](flow12_action_race_probe.json)
- [`flow12_final_invariants.json`](flow12_final_invariants.json)
- [`flow12_full_regression.log`](flow12_full_regression.log)
- [`flow12_focused_test.log`](flow12_focused_test.log)
- [`flow12_per_flow_governance.log`](flow12_per_flow_governance.log)
- [`flow12_strict_focused_test.log`](flow12_strict_focused_test.log)
- [`flow12_frontend_build.log`](flow12_frontend_build.log)
- `backend/database/migrations/2026_08_20_120020_separate_flow12_classification.php`
- `backend/app/Actions/Flow12/SubmitActionItemCompletion.php`
- `backend/app/Actions/Flow12/VerifyActionItemCompletion.php`
- `backend/app/Actions/Flow12/Concerns/Flow12ActionSupport.php`
- `backend/app/Http/Resources/MeetingActionItemEvidenceResource.php`
- [`backend/tests/Feature/Flow12DecisionFollowUpTest.php`](backend/tests/Feature/Flow12DecisionFollowUpTest.php)
- [`backend/tests/Feature/Flow12PerFlowGovernanceTest.php`](backend/tests/Feature/Flow12PerFlowGovernanceTest.php)

## Explicit release boundary

FLOW-12 is ready for explicit user review and approval. The implementation must not be described as APPROVED/FROZEN until that approval is provided. FLOW-10B remains deferred, FLOW-01 through FLOW-11 remain unchanged, and FLOW-13 has not been started.

## References

[1]: flow12_full_regression.log "Full Laravel regression evidence"
[2]: flow12_frontend_build.log "Frontend production build evidence"
[3]: flow12_manual_gate.json "Extended real HTTP manual gate transcript"
[4]: flow12_manual_governance_gate.json "FLOW-05/FLOW-06 completion-governance gate transcript"
[5]: flow12_action_race_probe.json "MariaDB action-level race probe"
[6]: flow12_final_invariants.json "Canonical before/after invariant artifact"
[7]: flow12_negative_matrix.md "FLOW-12 negative boundary matrix"
