# FLOW-13 Strict Verification Report

## Release boundary

**FLOW-13 — Exceptional Meeting Lifecycle Governance** has been implemented additively in the AMMS repository. The implemented scope covers formal postponement, cancellation, reopening, and resumption of an existing Meeting, with versioned exception cases, exact governance reviews, atomic Meeting-state transitions, terminal/session revocation, official-attendance invalidation, immutable schedule history, notifications, audit events, and Persian/Dari RTL UI support.

> **Release status: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** FLOW-13 has not been automatically marked APPROVED or FROZEN. FLOW-14 has not been started. FLOW-10B remains explicitly deferred, and FLOW-01 through FLOW-12 were not redesigned.

## Implementation summary

The implementation is additive. Migration `2026_08_20_130000_add_flow13_exception_governance.php` adds exception cases, immutable case versions, version-bound review rows, and attendance-invalidation overlays without rewriting historical Meeting, schedule, participant, attendance, terminal, minutes, or decision rows. Permission migrations `2026_08_20_130010_seed_flow13_permissions.php` and `2026_08_20_130020_seed_flow13_director_approval.php` add FLOW-13 permissions while keeping `SYSTEM_ADMIN` outside business governance.

The backend exposes canonical Meeting, Case, and Review identities through authenticated routes for creation, draft correction, submission, version-specific acknowledgement and approval, correction requests, rejection, withdrawal, application, reopening, and resumption. Each mutation passes through a FormRequest, Policy, governance resolver, explicit Action, transaction, row locks, baseline revalidation, immutable audit/notification handling, and API Resource. Submitted versions are immutable; corrections create version N+1 and preserve prior versions and review outcomes. Cross-unit visibility is limited to required formal-unit Directors and the exact High Authority identity, while operation authority remains review-type and unit scoped.

## State and governance invariants

The active-case marker is unique per Meeting. A scheduled Meeting may enter `POSTPONED` or `CANCELLED` only through an applied, fully reviewed case. A cancelled Meeting may be reopened only through a new approved `REOPENING` case, which returns it to `POSTPONED`; it cannot jump directly to `SCHEDULED`. Resumption creates a new immutable Schedule Version and moves `POSTPONED` to `SCHEDULED` only after exact future-date, conflict, and FLOW-07 schedule validation. Existing schedule versions remain unchanged.

FLOW-01 through FLOW-06 owner mapping is resolved from the frozen request flow type. Directorate reviews are bound to exact organizational units and scoped Directors; authority-controlled flows require the exact High Authority employee identity. Authority Office, High Authority, and `SYSTEM_ADMIN` cannot create or apply a Directorate-owned business exception merely because they possess administrative or review-related privileges.

When postponement, cancellation, or reopening is applied, active FLOW-09 terminal assignments and sessions are revoked atomically. Existing attendance rows remain immutable, while additive invalidation rows exclude them from official attendance through `OfficialAttendanceResolver`. New manual and fingerprint attendance is rejected for `POSTPONED` and `CANCELLED` Meetings with `MEETING_NOT_EXECUTABLE`.

## Verification results

| Gate | Result |
|---|---:|
| Full Laravel Feature regression after reconciliation | **366 tests / 1,614 assertions — PASS** |
| FLOW-13 exception suite | **7 tests / 58 assertions — PASS** |
| FLOW-13 per-flow governance suite | **12 tests / 91 assertions — PASS** |
| Frontend production build after reconciliation | **PASS — 1,865 modules transformed** |
| Earlier real HTTP lifecycle gate | **27 / 27 — PASS** |
| Real HTTP strict closure gate | **57 / 57 — PASS** |
| MariaDB/InnoDB strict race groups | **12 / 12 — PASS** |
| Stable lifecycle canonical fixture | **PASS — one Meeting and one request across six states; V1/V2/V3 verified** |
| Cancellation canonical fixture | **PASS — CANCELLED, raw attendance retained, official attendance 0, terminal revoked** |
| Canonical enriched invariants | **PASS** |
| Negative matrix | **44 boundaries, all classified PASS** |

## Real HTTP strict gates

The strict HTTP artifact `flow13_strict_http_gate.json` contains **57 passing steps** against the running Laravel API. FLOW-03 covers V1 approvals, a version-bound correction request, creation of a draft V2, resubmission with three fresh pending reviews, and independent V2 approvals before application. The artifact proves that V1 outcomes do not transfer to V2.

FLOW-05 covers all three formal-unit acknowledgements, rejection of Authority Office substitution for the High Authority review, exact High Authority approval, and Executive Secretary application. FLOW-06 covers the High Authority-initiated shape with no requester review, three formal-unit acknowledgements, exact High Authority approval, and Executive Secretary application.

The strict gate records a pre-cancellation attendance row, an `ASSIGNED` terminal assignment, and a live terminal manifest before cancellation. It then applies cancellation and proves HTTP denial of the revoked terminal manifest, biometric-candidate retrieval, and fingerprint attendance. It proves the invalidated attendance row is excluded from the official count and rejects new manual attendance with `MEETING_NOT_EXECUTABLE`. The transcript contains no terminal session token.

The same gate creates a real `COMPLETED` Meeting with an ended execution session, `FINALIZED` minutes, a finalized minute version, a decision, and a FLOW-12 follow-up record. It attempts all four exceptional operations—postponement, cancellation, reopening, and resumption—and receives the expected denial for each. It then rereads the Meeting, finalized minutes, and FLOW-12 history and verifies that the source records remain unchanged.

## Canonical lifecycle and cancellation fixtures

The stable lifecycle artifact `flow13_lifecycle_fixture.json` proves one immutable Meeting ID and one converted Meeting Request across the sequence `SCHEDULED → POSTPONED → SCHEDULED → CANCELLED → POSTPONED → SCHEDULED`. It records distinct immutable schedule versions V1, V2, and V3, their IDs and version numbers, and verifies that the historical V1 and V2 snapshots remain unchanged. It also explicitly records that no old terminal session was restored and no old attendance invalidation was removed.

The cancellation artifact `flow13_cancellation_fixture.json` is generated from a real cancellation Action. It records non-null Meeting Request, Meeting, exception-case, attendance, invalidation, terminal-assignment, and terminal-session IDs. The raw attendance row remains present with the same identity and payload after cancellation; the additive invalidation row has reason `MEETING_CANCELLED`; official attendance is zero; the assignment and session are `REVOKED`; and active terminal counts are zero.

## MariaDB concurrency gate

The authoritative artifact `flow13_exception_race_probe_strict.json` records **twelve MariaDB/InnoDB race groups**, all passing. In addition to the earlier execution, schedule, attendance, reopening, resumption, and duplicate-application groups, the artifact now includes cancellation versus FLOW-08 participant confirmation and cancellation versus FLOW-09 terminal activation. The concurrent resumption loser now returns the canonical `MEETING_EXCEPTION_ALREADY_APPLIED` error.

| Race evidence | Required invariant | Result |
|---|---|---:|
| Active-case creation | Exactly one active case; loser rejected | **PASS** |
| Duplicate application | Exactly one terminal application; second returns `MEETING_EXCEPTION_ALREADY_APPLIED` | **PASS** |
| Approval versus correction | Version-bound review and case remain coherent | **PASS** |
| Cancellation/execution ordering | No invalid execution or cancellation transition | **PASS** |
| FLOW-07/postponement ordering | Baseline and lifecycle state remain coherent | **PASS** |
| Reopening and resumption races | One serialized winner; loser rejected with canonical semantics | **PASS** |
| Cancellation versus FLOW-08 confirmation | Participant-first makes cancellation baseline-stale; cancellation-first blocks confirmation | **PASS** |
| Cancellation versus FLOW-09 activation | Activation-first is revoked; cancellation-first blocks activation; no active resource remains | **PASS** |
| Attendance orderings | Raw rows remain immutable and official count excludes invalidated rows | **PASS** |

## Canonical enriched invariants

The regenerated `flow13_final_invariants.json` includes request provenance, current and historical schedule/participant pointers, applied exception counts, duplicate-case and duplicate-application counters, duplicate schedule-version-number detection, terminal and attendance safety counters, source-mutation counters, finalized-minutes and finalized-decision counters, FLOW-12 history count, lifecycle identity and immutability fields, cancellation attendance identity fields, and sensitive-field exposure counters. Its mutation semantics distinguish append-only version creation from in-place historical mutation: two new schedule versions are created, while zero historical schedule rows are mutated in place.

| Invariant family | Result |
|---|---:|
| Request identity and originating flow type | **Present** |
| Current and historical schedule versions | **Present; V1/V2/V3 immutable** |
| Historical schedule rows mutated in place | **0** |
| New Schedule Versions appended | **2; total version count 3; version numbers [1,2,3]** |
| Current and historical participant versions | **Present; append-only; historical mutation count 0** |
| Applied postponement/cancellation/reopening/resumption counts | **Present** |
| Duplicate active case and duplicate apply counts | **0 / 0** |
| Duplicate schedule version number count | **0** |
| Active terminal assignment/session counts after cancellation | **0 / 0** |
| Raw versus official attendance | **Raw retained; official invalidated rows excluded** |
| Attendance row identity before/after cancellation | **Same non-null record ID; payload unchanged** |
| Request-to-Meeting duplicate count | **0** |
| Terminal-secret and biometric-sensitive exposure counters | **0** |
| MariaDB race result | **12 groups; PASS** |
| Fingerprint and biometric-candidate denial classification | **REAL HTTP + ACTION PASS; strict artifact referenced by N28** |

## Negative matrix

`flow13_negative_matrix.md` contains the complete **44-boundary** matrix. N28 now cites the real HTTP strict artifact for revoked-session fingerprint and biometric-candidate denial. N35 describes cancellation versus FLOW-08 participant confirmation rather than incorrectly describing resumption creation. N43 covers cancellation versus FLOW-09 terminal activation, and N44 covers all four exceptional operations against a `COMPLETED` Meeting with finalized minutes and preserved FLOW-12 history. All MariaDB rows cite the twelve-group strict race artifact and are classified **PASS**. The matrix retains explicit repository-audit boundaries confirming that FLOW-10B hardware integration remains deferred and FLOW-14 has not started.

## UI verification

The React frontend includes a Persian/Dari RTL FLOW-13 workspace at `/meetings/{id}/exceptions` and a direct action link from MeetingDetail. The workspace displays Meeting state, active exception cases, current version and review matrix, immutable history context, correction/withdrawal controls, and guarded apply/resume controls. The browser client never bypasses backend authorization; every button calls the canonical API route and renders the server result.

The final frontend production build completed successfully with **1,865 transformed modules** after this evidence-only and probe correction pass. The existing FLOW-01 through FLOW-12 RTL workspaces remain present, and the build contains no FLOW-14 route or component.

## Evidence references

| Artifact | Purpose |
|---|---|
| `flow13_full_regression_strict.log` | Full Laravel Feature regression transcript |
| `flow13_frontend_build_strict.log` | Frontend production build transcript |
| `flow13_per_flow_governance_strict_pass.log` | FLOW-01–FLOW-06 behavioral and resolver governance evidence |
| `flow13_strict_http_gate.json` | 57-step real HTTP strict closure transcript |
| `flow13_manual_gate.json` | Earlier 27-step real HTTP lifecycle transcript |
| `flow13_exception_race_probe_strict.json` | Twelve-group MariaDB/InnoDB race transcript |
| `flow13_lifecycle_fixture.json` | Stable one-Meeting lifecycle with V1/V2/V3 schedule evidence |
| `flow13_cancellation_fixture.json` | Canonical cancelled Meeting, immutable attendance, invalidation, and terminal revocation evidence |
| `flow13_final_invariants.json` | Enriched canonical invariant artifact |
| `flow13_negative_matrix.md` | 44-boundary negative matrix with strict evidence classifications |

## Explicit approval gate

The implementation and verification evidence are being reconciled for this strict-review closure. The release boundary remains intentionally open: **FLOW-13 requires explicit user approval before it may be declared APPROVED/FROZEN**. No FLOW-14 work has been initiated, and FLOW-10B remains deferred.
