# FLOW-02 + FLOW-03 Role-UX Closure

## Scope and environment

This closure addresses only the remaining role-based presentation findings from the accepted FLOW-02 and FLOW-03 browser journeys. The accepted full journeys were not rerun. Deterministic nonterminal fixtures were prepared through the real Laravel HTTP API solely to place the browser at the lifecycle states required for the focused visibility checks; the decisive role-UX actions were performed through fresh browser sessions and the real React UI.

The environment remained the real React frontend at `http://127.0.0.1:5173`, the real Laravel API at `http://127.0.0.1:8000`, and the isolated MariaDB/InnoDB database `amms_browser_e2e`. No backend source file, workflow state machine, business rule, participant ownership rule, or authorization policy was changed.

## Remediation implemented

The permitted frontend change is limited to `frontend/src/main.tsx`. The FLOW-02 detail now derives a scheduling capability from the existing authenticated scoped-role metadata: only `EXECUTIVE_MANAGEMENT` or `MEETING_COORDINATOR` roles scoped to one of the request’s two formal Directorates may see the scheduling-start control or scheduling workspace link. Directors without that coordinator capability no longer see the misleading scheduling action. The backend remains authoritative and unchanged.

The FLOW-02 scheduling participant selector now filters employees by the selected formal owner Directorate. The requester-owned selection displayed only requester-unit employees, and the target-owned selection displayed only target-unit employees. No unrelated Directorate employee appeared in either visible selector. The existing FLOW-03 selector already used the same unit-scoped filtering pattern and was verified during the prior accepted journey.

## Focused browser checks

| Check | Fresh browser evidence | Result |
|---|---|---:|
| FLOW-02 Finance Director forbidden visibility | Fresh Finance Director login opened `MR-2026-00002`; the converted detail showed no `آغاز مذاکرهٔ زمان‌بندی` control and no scheduling workspace link. | **PASS** |
| FLOW-02 authorized scheduler visibility | Fresh Technology Coordinator login opened deterministic `MR-2026-00005` at `BILATERAL_APPROVED`; `آغاز مذاکرهٔ زمان‌بندی` was visible and enabled. | **PASS** |
| FLOW-02 requester selector scope | Fresh Technology Coordinator opened `/meeting-requests/2/scheduling`; Technology owner showed only `احمد احمدی · ریاست تکنالوژی`. | **PASS** |
| FLOW-02 target selector scope | The same workspace switched to Finance owner and showed only `زلمی حکیمی · ریاست مالی`. | **PASS** |
| FLOW-03 coordinator fresh-session stability | Fresh Technology Coordinator login opened deterministic `MR-2026-00004` at `MULTILATERAL_APPROVED`, saw `آغاز زمان‌بندی چندریاستی`, and clicked it successfully. The request moved to `SCHEDULING` and showed the workspace link. | **PASS** |
| Backend authorization semantics | A post-fix Finance Director scheduling POST on `MR-2026-00005` returned HTTP 403 and the request remained `BILATERAL_APPROVED`. | **PASS** |

No manual permission or cache intervention occurred during the fresh browser action. The prior pre-cache 403 observation is therefore closed by a clean-session browser pass. The fixture setup occurred before the browser action and was not part of the accepted full business journeys.

## Build and change summary

The frontend production build passed with `tsc -b && vite build`; Vite transformed 1,866 modules. Only `frontend/src/main.tsx` changed for this remediation. Backend source changes: none. Business semantics changed: **NO**. Authorization semantics changed: **NO**. The existing backend 403 behavior remains intact.

## Final status

All requested role-UX checks pass. There are no unresolved role-UX findings, no authorization bypass, and no frozen-contract blocker. FLOW-02 and FLOW-03 remain accepted from their prior full browser journeys; this artifact closes the remaining presentation and fresh-session stability items only.

> **FLOW-02 + FLOW-03 ROLE-UX CLOSURE**  
> **VERIFICATION COMPLETE**  
> **READY FOR FLOW-04 / FLOW-05 / FLOW-06 BROWSER ACCEPTANCE**

No FLOW-04, FLOW-10B, or FLOW-15 work was started.
