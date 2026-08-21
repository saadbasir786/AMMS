# SYSTEM_ADMIN Effective Authority Proof

## Final classification

> **ROLE MODEL CONSOLIDATION**  
> **VERIFICATION INCOMPLETE**  
> **ARCHITECTURE/SECURITY BLOCKER FOUND**

The required effective-authority proof was executed without modifying production code, rerunning the full regression, or starting FLOW-04/05/06, FLOW-10B, or FLOW-15.

The result is not equivalent to “the coordinator migration granted no permissions to SYSTEM_ADMIN.” The real effective authorization stack was tested on valid seeded resources. `SYSTEM_ADMIN` can execute representative frozen business operations because the existing global `MeetingRequestPolicy::before()` bypass returns authorization success for an active System Admin with `meeting-request.view`. This is a **pre-existing business-authority bypass** and is now explicitly recorded rather than reinterpreted.

## Representative real-route results

The probe used the seeded `system.admin@example.test` account and real Laravel HTTP routes. The valid-resource probe used a transactional test fixture and rolled the fixture back after the checks. The browser-authenticated probe also confirmed the current live request boundary behavior; no production state was retained from the probe fixture.

| Operation | Actor | Method and path | Resource flow/state | Expected | Actual | Business code | State mutated |
| --- | --- | --- | --- | ---: | ---: | --- | --- |
| High Authority decision | `system.admin@example.test` | `POST /api/meeting-requests/1/authority/approve` | `DIRECTORATE_WITH_HIGH_AUTHORITY / PENDING_HIGH_AUTHORITY_DECISION` | 403 | **403** | `UNAUTHORIZED_HIGH_AUTHORITY_DECISION` | No |
| Executive Secretary-only authority scheduling | `system.admin@example.test` | `POST /api/meeting-requests/1/authority-scheduling/start` | `DIRECTORATE_WITH_HIGH_AUTHORITY / HIGH_AUTHORITY_APPROVED` | 403 | **200** | None | **Yes** — entered authority scheduling |
| Coordinator-owned FLOW-01 scheduling | `system.admin@example.test` | `POST /api/meeting-requests/2/start-scheduling` | `INTERNAL_DIRECTORATE / APPROVED` | 403 | **200** | None | **Yes** — entered scheduling |

The High Authority decision endpoint correctly denied the System Admin. However, the two successful operations are sufficient to establish that System Admin has effective business authority beyond pure administration. The successful scheduling operation was performed against a valid state and changed the request state, so it is not merely a route-discovery or prerequisite-state artifact.

A FLOW-12 completion-verification check was not added because no deterministic eligible FLOW-12 resource was convenient and the minimum A–C gate already established the contradiction. No FLOW-12 workflow was started.

## Interpretation

`system_admin_business_authority_denied` is **false**. `preexisting_business_bypass_exists` is **true**. The bypass predates the coordinator consolidation and was not modified because the instruction explicitly prohibited changing production code without approval. The role consolidation itself did not grant the System Admin a new role, scope, or permission; nevertheless, the current effective policy stack permits the two successful business operations.

This is therefore an **architecture/security blocker for the final closure**, not a failure of the coordinator permission-transfer migration. It requires an explicit architectural decision before any policy or production-code correction is attempted.

## Change and test boundaries

| Boundary | Result |
| --- | --- |
| Production code changed for this proof | **No** |
| Full backend regression rerun | **No** |
| Frontend build rerun | **No** |
| FLOW-04/05/06 started or continued | **No** |
| FLOW-10B touched | **No** |
| FLOW-15 touched | **No** |
| Probe fixture retained | **No — rolled back** |
| Overall closure status | **Incomplete; blocker found** |

> The coordinator consolidation should not be auto-frozen. The final closure remains pending an explicit decision on whether the pre-existing System Admin business bypass is intended or must be removed in a separately approved security change.
