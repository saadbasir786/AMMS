# FLOW-13 Verification Report

## Release boundary

**FLOW-13 — Exceptional Meeting Lifecycle Governance** has been implemented additively in the AMMS repository. The implemented scope covers formal postponement, cancellation, reopening, and resumption of an existing Meeting, with versioned exception cases, exact governance reviews, atomic Meeting-state transitions, terminal/session revocation, official-attendance invalidation, immutable schedule history, notifications, audit events, and Persian/Dari RTL UI support.

> **Release status: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** FLOW-13 has not been automatically marked APPROVED or FROZEN. FLOW-14 has not been started. FLOW-10B remains explicitly deferred, and FLOW-01 through FLOW-12 were not redesigned.

## Implementation summary

The implementation is additive. Migration `2026_08_20_130000_add_flow13_exception_governance.php` adds exception cases, immutable case versions, version-bound review rows, and attendance-invalidation overlays without rewriting historical Meeting, schedule, participant, attendance, terminal, minutes, or decision rows. Permission migrations `2026_08_20_130010_seed_flow13_permissions.php` and `2026_08_20_130020_seed_flow13_director_approval.php` add FLOW-13 permissions while keeping `SYSTEM_ADMIN` outside business governance.

The backend exposes canonical Meeting/Case/Review identities through the following authenticated routes:

| Capability | Route |
|---|---|
| List exception cases | `GET /api/meetings/{meeting}/exceptions` |
| Create case | `POST /api/meetings/{meeting}/exceptions` |
| Show case and current version reviews | `GET /api/meeting-exceptions/{case}` |
| Create correction version | `PUT /api/meeting-exceptions/{case}/draft` |
| Submit current version | `POST /api/meeting-exceptions/{case}/submit` |
| Acknowledge version-specific review | `POST /api/meeting-exception-reviews/{review}/acknowledge` |
| Approve version-specific review | `POST /api/meeting-exception-reviews/{review}/approve` |
| Request correction | `POST /api/meeting-exception-reviews/{review}/correction` |
| Reject or withdraw case | `POST /api/meeting-exceptions/{case}/reject` and `/withdraw` |
| Apply postponement/cancellation/reopening | `POST /api/meeting-exceptions/{case}/apply` |
| Resume postponed Meeting | `POST /api/meeting-exceptions/{case}/resume` |

Each mutation passes through a FormRequest, Policy, governance resolver, explicit Action, transaction, row locks, baseline revalidation, immutable audit/notification handling, and API Resource. Submitted versions are immutable. Corrections create version N+1 and preserve prior reviews and review outcomes.

## State and governance invariants

The active-case marker is unique per Meeting. A scheduled Meeting may enter `POSTPONED` or `CANCELLED` only through an applied, fully reviewed case. A cancelled Meeting may be reopened only through a new approved `REOPENING` case, which returns it to `POSTPONED`; it cannot jump directly to `SCHEDULED`. Resumption creates a new immutable Schedule Version and moves `POSTPONED` to `SCHEDULED` only after exact future-date, conflict, and FLOW-07 schedule validation. Existing schedule versions remain unchanged.

FLOW-01 through FLOW-06 owner mapping is resolved from the frozen request flow type. Directorate reviews are bound to exact organizational units and scoped Directors; authority-controlled flows require the exact High Authority employee identity. Authority Office, High Authority, and `SYSTEM_ADMIN` cannot create or apply a Directorate-owned business exception merely because they possess administrative or review-related privileges.

When postponement, cancellation, or reopening is applied, active FLOW-09 terminal assignments and sessions are revoked atomically. Existing attendance rows remain immutable, while additive invalidation rows exclude them from official attendance through `OfficialAttendanceResolver`. New manual and fingerprint attendance is rejected for `POSTPONED` and `CANCELLED` Meetings with `MEETING_NOT_EXECUTABLE`.

## Verification results

| Gate | Result |
|---|---:|
| Full Laravel Feature regression | **359 tests / 1,550 assertions — PASS** |
| FLOW-12 compatibility suite | **10 tests / 115 assertions — PASS** |
| FLOW-13 Feature suite | **6 tests / 52 assertions — PASS** |
| FLOW-13 per-flow governance suite | **6 tests / 33 assertions — PASS** |
| FLOW-13 dedicated backend coverage | **12 tests / 85 assertions — PASS** |
| Frontend production build | **PASS — 1,865 modules transformed** |
| Real HTTP FLOW-13 governance gate | **27 / 27 — PASS** |
| MariaDB/InnoDB FLOW-13 race groups | **4 / 4 — PASS** |
| Canonical FLOW-13 invariants | **PASS** |
| Negative matrix | **42 boundaries classified** |

## Real HTTP gate

The fresh disposable HTTP fixture exercised the complete owner/reviewer path. The owner created and submitted a postponement case; High Authority and `SYSTEM_ADMIN` were denied creation; an unrelated Authority Office actor was denied unit-review approval; the exact scoped Director approved; and the owner applied postponement. The same fixture then created and applied resumption with a new exact schedule, created and applied cancellation, and finally created and applied formal reopening.

The final HTTP result was **27/27 PASS**, with the Meeting ending in `POSTPONED` after reopening. The transcript is sanitized and contains no access tokens or passwords.

## MariaDB concurrency gate

The isolated `amms_concurrency_test` MariaDB schema was rebuilt from all additive migrations and seeded independently. The real Actions were executed with concurrent workers under InnoDB row locking.

| Race group | Required invariant | Result |
|---|---|---:|
| Active-case creation | Exactly one active case; loser rejected | **PASS** |
| Duplicate application | Exactly one terminal application; second rejected; Meeting `CANCELLED` | **PASS** |
| Review approval versus correction | Version-bound review and case remain coherent | **PASS** |
| Cancellation versus manual attendance | Cancellation wins or attendance wins serially; final cancelled Meeting has no official attendance | **PASS** |

The race artifact records one serialized winner and one rejected loser for each group. No concurrent path created duplicate active cases or an invalid final Meeting state.

## Canonical invariants

The regenerated invariant artifact reports the fresh HTTP Meeting as follows:

| Invariant | Value |
|---|---:|
| Initial Meeting status | `SCHEDULED` |
| Final Meeting status after reopening | `POSTPONED` |
| Schedule Version count | `2` |
| Exception case count | `4` |
| Exception version count | `4` |
| Pending reviews on terminal cases | `0` |
| Duplicate active-case count | `0` |
| Source mutation count | `0` |
| MariaDB race groups | `4` |
| MariaDB race result | `PASS` |

The source Meeting Request identity, historical Schedule Versions, participant versions, frozen FLOW-12 decision sources, and immutable attendance records remain separate from the additive exception overlay. Audit actions contain metadata only and do not store biometric bytes, attendance payloads, credentials, or secrets.

## UI verification

The React frontend includes a Persian/Dari RTL FLOW-13 workspace at `/meetings/{id}/exceptions` and a direct action link from MeetingDetail. The workspace displays Meeting state, active exception cases, current version and review matrix, immutable history context, correction/withdrawal controls, and guarded apply/resume controls. The browser client never bypasses backend authorization; every button calls the canonical API route and renders the server result.

The production build completed successfully with **1,865 transformed modules**. The existing FLOW-01 through FLOW-12 RTL workspaces remain present and the build contains no FLOW-14 route or component.

## Evidence references

| Artifact | Purpose |
|---|---|
| `flow13_full_regression.log` | Full Laravel Feature regression transcript |
| `flow13_frontend_build.log` | Frontend production build transcript |
| `flow13_feature_with_terminal_retry.log` | FLOW-13 Feature evidence, 6 tests / 52 assertions |
| `flow13_per_flow_governance_final.log` | FLOW-01–FLOW-06 resolver governance evidence |
| `flow13_manual_gate.json` | Sanitized 27-step real HTTP transcript |
| `flow13_exception_race_probe.json` | MariaDB/InnoDB four-race transcript |
| `flow13_final_invariants.json` | Canonical before/after invariant artifact |
| `flow13_negative_matrix.md` | 42-boundary negative matrix with evidence classifications |

## Explicit approval gate

The implementation and verification evidence are complete for this development stage. The release boundary remains intentionally open: **FLOW-13 requires explicit user approval before it may be declared APPROVED/FROZEN**. No FLOW-14 work has been initiated.
