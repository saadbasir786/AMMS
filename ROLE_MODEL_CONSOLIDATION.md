# AMMS Role Model Consolidation

## Decision

`MEETING_COORDINATOR` was absorbed into `EXECUTIVE_MANAGEMENT` through the incremental migration `2026_08_20_184000_consolidate_meeting_coordinator_role.php`. The change is a **role-model consolidation only**. It does not introduce a new workflow, alter a state machine, change approval semantics, or widen organizational scope.

The migration is transactional and idempotent. It transfers the complete permission set attached to the deprecated role to `EXECUTIVE_MANAGEMENT` using Spatie's idempotent `givePermissionTo` operation, migrates user-role assignments and AMMS organizational scopes, rewrites only legacy `user_roles` rows for the deprecated role, and retires the deprecated role after its assignments, scopes, permissions, and role row have been handled. Its `down()` method is deliberately non-destructive and does not guess historical assignments.

## Role inventory

The final seven active human/application roles are `SYSTEM_ADMIN`, `EMPLOYEE`, `DIRECTOR`, `EXECUTIVE_MANAGEMENT`, `AUTHORITY_OFFICE`, `EXECUTIVE_SECRETARY`, and `HIGH_AUTHORITY`. The database also retains `BIOMETRIC_ENROLLMENT_OFFICER` as a frozen FLOW-10 subsystem role with zero user assignments and five permissions. It is not part of the seven-role human/application consolidation target and is therefore preserved rather than removed.

| Check | Result |
| --- | --- |
| `MEETING_COORDINATOR` role exists after migration | **No** |
| Deprecated Spatie assignments | **0** |
| Deprecated AMMS scope rows | **0** |
| Deprecated legacy `user_roles` rows | **0** |
| Runtime `MEETING_COORDINATOR` references in `backend/app` and `frontend/src` | **0** |
| Final active human/application role count | **7** |
| Preserved FLOW-10 subsystem role | `BIOMETRIC_ENROLLMENT_OFFICER` |

The saved post-migration database verification recorded `tech.exec@example.test` and the former test-only `tech.coordinator@example.test` under `EXECUTIVE_MANAGEMENT`, both scoped to Technology. No ambiguous non-test assignment was found. The coordinator account is not a separate coordinator role after migration.

## Permission and scope migration

The former coordinator's base permissions were transferred to `EXECUTIVE_MANAGEMENT` without duplicates. The complete former-role permission set also includes the permissions seeded for the later FLOW-04 through FLOW-14 subsystems; the migration intentionally enumerates the old role's actual permission rows rather than maintaining a brittle hard-coded list.

| Permission family | Representative permissions |
| --- | --- |
| Core meeting requests | `meeting-request.view`, `meeting-request.attachment-download`, `meeting.schedule`, `meeting.create`, `meeting.view`, `notification.view`, `notification.mark-read` |
| FLOW-04 through FLOW-14 | All permissions previously attached to `MEETING_COORDINATOR` by the corresponding incremental permission migrations, including authority scheduling, directive coordination, rescheduling, participant changes, terminal/attendance, execution/minutes, decision/follow-up, exception, and reporting permissions |

`givePermissionTo` is idempotent and the post-migration verification found **zero duplicate permission rows attributable to the transfer**. AMMS organizational scopes were preserved by `(user, role, organizational unit)` identity. The consolidation therefore preserves own-directorate authority without permitting cross-directorate scheduling.

## Browser smoke gates

The real Persian/Dari RTL React UI, Laravel HTTP API, Sanctum session, and MariaDB/InnoDB environment were used for the acceptance smoke gates.

### FLOW-01

The inherited FLOW-01 smoke gate passed. Technology Executive created `MR-2026-00001`, Technology Director approved it, and Technology Executive reached the scheduling workspace. The Director-side view did not expose coordinator scheduling controls.

### FLOW-02

A fresh Technology Executive session created `MR-2026-00002` (request id `5`) as Technology requester with Finance target. Technology Director approved the requester side; Finance Executive started target review; and Finance Director approved the target side. The request reached `BILATERAL_APPROVED`. Technology Executive saw and activated **آغاز مذاکرهٔ زمان‌بندی**. A separate Technology Director session opened the same request and rendered no scheduling action.

### FLOW-03

A fresh Technology Executive session created `MR-2026-00003` (request id `6`) as Technology requester with Finance and HR targets. Technology Director approved the requester side. Finance Executive started Finance review and Finance Director approved Finance. HR Executive started HR review and HR Director approved HR. The request reached `MULTILATERAL_APPROVED`. Technology Executive saw and activated **آغاز زمان‌بندی چندریاستی**; the request entered `SCHEDULING` and exposed the multilateral scheduling workspace.

| Browser assertion | Result |
| --- | --- |
| Executive Management can create and submit requester workflows | **Pass** |
| Executive Management can start own-scope FLOW-02 scheduling | **Pass** |
| Director cannot start FLOW-02 scheduling | **Pass** |
| Executive Management can start own-scope FLOW-03 scheduling | **Pass** |
| Target reviews remain independently scoped to each Directorate | **Pass** |
| Persian/Dari RTL UI rendered throughout the smoke gates | **Pass** |

## Regression and build evidence

The inherited verification run completed **376 backend tests with 1,670 assertions**, all passing. The focused `RoleModelConsolidationTest` passed all three tests and 16 assertions. The frontend TypeScript/Vite build passed with **1,866 modules transformed**.

| Gate | Result |
| --- | --- |
| Full backend regression | **376 / 376 tests pass; 1,670 assertions** |
| Focused role-consolidation tests | **3 / 3 tests pass; 16 assertions** |
| Frontend build | **Pass; 1,866 modules transformed** |
| Deprecated role assignment validation | **Pass; HTTP 422** |
| Own-scope coordination authorization | **Pass** |
| Cross-scope coordination denial | **Pass** |

These tests and the build were already completed in the inherited execution; they were not rerun during this continuation.

## Preservation guarantees

The change preserves the frozen workflow and governance contracts. Historical migrations remain unchanged, audit history is not rewritten, and no business-state transition was introduced. The post-migration authoritative snapshot recorded 8 meeting requests, 5 meetings, 16 participants, and 121 request actions; these rows were unchanged by the role migration. The fresh browser-smoke snapshot is a distinct acceptance fixture containing the new FLOW-02/03 rows and should not be compared as if it were the same database snapshot.

| Invariant | Result |
| --- | --- |
| Historical business rows mutated by the migration | **0** |
| Historical audit rows rewritten | **0** |
| Workflow semantics changed | **No** |
| Business state machine changed | **No** |
| Organizational scope semantics changed | **No** |
| High Authority governance changed | **No** |
| Authority Office governance changed | **No** |
| Executive Secretary governance changed | **No** |
| Unresolved blockers | **None** |

> **Conclusion:** The authorized `MEETING_COORDINATOR → EXECUTIVE_MANAGEMENT` consolidation passes the role-model, database, authorization, regression, build, and FLOW-01/02/03 browser-smoke gates. The preserved `BIOMETRIC_ENROLLMENT_OFFICER` subsystem role is documented and is not a blocker.
