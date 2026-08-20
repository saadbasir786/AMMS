# FLOW-13 Verification Report

## Release boundary

**FLOW-13 — Exceptional Meeting Lifecycle Governance** has been implemented additively in the AMMS repository. The implemented scope covers formal postponement, cancellation, reopening, and resumption of an existing Meeting, with versioned exception cases, exact governance reviews, atomic Meeting-state transitions, terminal/session revocation, official-attendance invalidation, immutable schedule history, notifications, audit events, and Persian/Dari RTL UI support.

> **Release status: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** FLOW-13 has not been automatically marked APPROVED or FROZEN. FLOW-14 has not been started. FLOW-10B remains explicitly deferred, and FLOW-01 through FLOW-12 were not redesigned.

## Implementation summary

The implementation is additive. Migration `2026_08_20_130000_add_flow13_exception_governance.php` adds exception cases, immutable case versions, version-bound review rows, and attendance-invalidation overlays without rewriting historical Meeting, schedule, participant, attendance, terminal, minutes, or decision rows. Permission migrations `2026_08_20_130010_seed_flow13_permissions.php` and `2026_08_20_130020_seed_flow13_director_approval.php` add FLOW-13 permissions while keeping `SYSTEM_ADMIN` outside business governance.

The backend exposes canonical Meeting/Case/Review identities through authenticated routes for creation, draft correction, submission, version-specific acknowledgement and approval, correction requests, rejection, withdrawal, application, and resumption. Each mutation passes through a FormRequest, Policy, governance resolver, explicit Action, transaction, row locks, baseline revalidation, immutable audit/notification handling, and API Resource. Submitted versions are immutable; corrections create version N+1 and preserve prior versions and review outcomes. Cross-unit visibility is limited to required formal-unit Directors and the exact High Authority identity, while operation authority remains review-type and unit scoped.

## State and governance invariants

The active-case marker is unique per Meeting. A scheduled Meeting may enter `POSTPONED` or `CANCELLED` only through an applied, fully reviewed case. A cancelled Meeting may be reopened only through a new approved `REOPENING` case, which returns it to `POSTPONED`; it cannot jump directly to `SCHEDULED`. Resumption creates a new immutable Schedule Version and moves `POSTPONED` to `SCHEDULED` only after exact future-date, conflict, and FLOW-07 schedule validation. Existing schedule versions remain unchanged.

FLOW-01 through FLOW-06 owner mapping is resolved from the frozen request flow type. Directorate reviews are bound to exact organizational units and scoped Directors; authority-controlled flows require the exact High Authority employee identity. Authority Office, High Authority, and `SYSTEM_ADMIN` cannot create or apply a Directorate-owned business exception merely because they possess administrative or review-related privileges.

When postponement, cancellation, or reopening is applied, active FLOW-09 terminal assignments and sessions are revoked atomically. Existing attendance rows remain immutable, while additive invalidation rows exclude them from official attendance through `OfficialAttendanceResolver`. New manual and fingerprint attendance is rejected for `POSTPONED` and `CANCELLED` Meetings with `MEETING_NOT_EXECUTABLE`.

## Verification results

| Gate | Result |
|---|---:|
| Full Laravel Feature regression | **366 tests / 1,614 assertions — PASS** |
| FLOW-13 exception suite | **7 tests / 58 assertions — PASS** |
| FLOW-13 per-flow governance suite | **12 tests / 91 assertions — PASS** |
| Frontend production build | **PASS — 1,865 modules transformed** |
| Real HTTP lifecycle gate | **27 / 27 — PASS** |
| Real HTTP strict closure gate | **43 / 43 — PASS** |
| MariaDB/InnoDB strict race groups | **10 / 10 — PASS** |
| Stable lifecycle canonical fixture | **PASS — one Meeting ID across six labeled states** |
| Cancellation canonical fixture | **PASS — CANCELLED, official attendance 0** |
| Canonical enriched invariants | **PASS** |
| Negative matrix | **42 boundaries, all classified PASS** |

## Real HTTP strict gates

The strict HTTP artifact `flow13_strict_http_gate.json` proves the remaining governance and lifecycle boundaries through the running Laravel API. FLOW-03 covers V1 approvals, a version-bound correction request, creation of a draft V2, resubmission with three fresh pending reviews, and independent V2 approvals before application. The artifact proves that V1 outcomes do not transfer to V2.

FLOW-05 covers all three formal-unit acknowledgements, rejection of Authority Office substitution for the High Authority review, exact High Authority approval, and Executive Secretary application. FLOW-06 covers the High Authority-initiated shape with no requester review, three formal-unit acknowledgements, exact High Authority approval, and Executive Secretary application.

The same strict gate records a pre-cancellation attendance row, applies cancellation, proves that the terminal session is denied after revocation, proves the invalidated attendance row is excluded from the official count, rejects new manual attendance with `MEETING_NOT_EXECUTABLE`, and rejects exception creation for a `COMPLETED` Meeting. The transcript contains no passwords or session tokens.

## Canonical lifecycle and cancellation fixtures

The stable lifecycle artifact `flow13_lifecycle_fixture.json` uses one Meeting ID and records the labeled sequence `initial`, `postponement applied`, `first resumption applied`, `cancellation applied`, `reopening applied`, and `second resumption applied`. The resulting status path is `SCHEDULED → POSTPONED → SCHEDULED → CANCELLED → POSTPONED → SCHEDULED`, with each resumption producing a distinct replacement schedule.

The cancellation artifact `flow13_cancellation_fixture.json` ends with a single Meeting in `CANCELLED`, retains the raw attendance record, reports `official_count = 0`, and records the cancellation invalidation lifecycle. The independent strict HTTP gate additionally proves terminal-session revocation on the same class of transition.

## MariaDB concurrency gate

The authoritative artifact `flow13_exception_race_probe_strict.json` records ten MariaDB/InnoDB race groups. The groups cover active-case creation, duplicate application, approval versus correction, cancellation versus attendance, cancellation versus execution, FLOW-07 confirmation versus postponement, concurrent reopening application, concurrent resumption, and both cancellation-first and postponement-first attendance orderings.

| Race evidence | Required invariant | Result |
|---|---|---:|
| Active-case creation | Exactly one active case; loser rejected | **PASS** |
| Duplicate application | Exactly one terminal application; second returns `MEETING_EXCEPTION_ALREADY_APPLIED` | **PASS** |
| Approval versus correction | Version-bound review and case remain coherent | **PASS** |
| Cancellation/execution ordering | No invalid execution or cancellation transition | **PASS** |
| FLOW-07/postponement ordering | Baseline and lifecycle state remain coherent | **PASS** |
| Reopening and resumption races | One serialized winner; loser rejected | **PASS** |
| Attendance orderings | Raw rows remain immutable and official count excludes invalidated rows | **PASS** |

## Canonical enriched invariants

The regenerated `flow13_final_invariants.json` includes request provenance, current and historical schedule/participant pointers, applied exception counts, duplicate-case and duplicate-application counters, terminal and attendance safety counters, source-mutation counters, finalized-minutes and finalized-decision counters, FLOW-12 history count, and sensitive-field exposure counters.

| Invariant family | Result |
|---|---:|
| Request identity and originating flow type | **Present** |
| Current and historical schedule versions | **Present; source immutable** |
| Current and historical participant versions | **Present; append-only** |
| Applied postponement/cancellation/reopening/resumption counts | **Present** |
| Duplicate active case and duplicate apply counts | **0 / 0** |
| Active terminal assignment/session counts after cancellation | **0 / 0** |
| Raw versus official attendance | **Raw retained; official invalidated rows excluded** |
| Request-to-Meeting duplicate count | **0** |
| Terminal-secret and biometric-sensitive exposure counters | **0** |
| MariaDB race result | **10 groups; PASS** |

## Negative matrix

`flow13_negative_matrix.md` contains the complete 42-boundary matrix. All four previously stale MariaDB rows now cite the strict race artifact directly and are classified **PASS**. The matrix retains explicit repository-audit boundaries confirming that FLOW-10B hardware integration remains deferred and FLOW-14 has not started.

## UI verification

The React frontend includes a Persian/Dari RTL FLOW-13 workspace at `/meetings/{id}/exceptions` and a direct action link from MeetingDetail. The workspace displays Meeting state, active exception cases, current version and review matrix, immutable history context, correction/withdrawal controls, and guarded apply/resume controls. The browser client never bypasses backend authorization; every button calls the canonical API route and renders the server result.

The production build completed successfully with **1,865 transformed modules**. The existing FLOW-01 through FLOW-12 RTL workspaces remain present, and the build contains no FLOW-14 route or component.

## Evidence references

| Artifact | Purpose |
|---|---|
| `flow13_full_regression_strict.log` | Full Laravel Feature regression transcript, 366 tests / 1,614 assertions |
| `flow13_frontend_build_strict.log` | Frontend production build transcript |
| `flow13_per_flow_governance_strict_pass.log` | FLOW-01–FLOW-06 behavioral and resolver governance evidence |
| `flow13_strict_http_gate.json` | 43-step real HTTP strict closure transcript |
| `flow13_manual_gate.json` | Earlier 27-step real HTTP lifecycle transcript |
| `flow13_exception_race_probe_strict.json` | Ten-group MariaDB/InnoDB race transcript |
| `flow13_lifecycle_fixture.json` | Stable one-Meeting lifecycle sequence |
| `flow13_cancellation_fixture.json` | Canonical cancelled Meeting and attendance invalidation evidence |
| `flow13_final_invariants.json` | Enriched canonical invariant artifact |
| `flow13_negative_matrix.md` | 42-boundary negative matrix with strict evidence classifications |

## Explicit approval gate

The implementation and verification evidence are complete for this development stage. The release boundary remains intentionally open: **FLOW-13 requires explicit user approval before it may be declared APPROVED/FROZEN**. No FLOW-14 work has been initiated, and FLOW-10B remains deferred.
