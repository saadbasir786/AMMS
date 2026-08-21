# AMMS — Post-Consolidation Master Browser E2E Acceptance

## FLOW-04, FLOW-05, and FLOW-06

**Acceptance status: PASS — verification complete; no automatic freeze performed.**

This report records the fresh post-consolidation browser acceptance of FLOW-04, FLOW-05, and FLOW-06 using the real Persian/Dari RTL React interface, the real Laravel HTTP API, the authenticated Chromium browser, and the isolated MariaDB/InnoDB database `amms_browser_e2e`. The acceptance business path did not use mocked API responses, direct Action invocation, SQLite substitution, or test-fixture replacement for the final Meeting creation gate. The final FLOW-06 Meeting was created through the live browser and then opened in that same browser session; its persisted rows were independently checked in the same live MariaDB database. [1]

> The acceptance decision is based on completed governance journeys, normal work-queue discoverability, role-separated UI controls, stale-state refresh behavior, direct HTTP authorization boundaries, exact Meeting invariants, and zero unresolved HIGH/BLOCKER findings.

## 1. Scope and environment

| Dimension | Acceptance value |
|---|---|
| Frontend | `http://127.0.0.1:5173` — React 19, TypeScript, Vite |
| Backend | `http://127.0.0.1:8000` — Laravel 13, PHP 8.3, Sanctum |
| Database | MariaDB / InnoDB, `amms_browser_e2e` |
| Browser | Chromium sandbox browser |
| Locale and interface | Persian/Dari RTL throughout the exercised journeys |
| Acceptance mode | Real React → Laravel HTTP → MariaDB/InnoDB |
| SQLite substitution | Not used for the accepted business journeys |
| Scope exclusions | FLOW-07, FLOW-15, and FLOW-10B were not started or modified |
| Automatic freeze | Not performed |

The two post-consolidation implementation corrections were limited to FLOW-06. The Authority Office inbox now includes the formal HIGH_AUTHORITY position scope and EXECUTIVE_SECRETARY role scope so High Authority and Executive Secretary users discover work through normal navigation. The draft-correction React path now sends `preferred_start_time` as `HH:MM`, matching Laravel’s `H:i` validation contract. [5] [6]

## 2. Executive result

| Flow | Request | Meeting | Result | Live browser completion | MariaDB verification |
|---|---:|---|---|---|---|
| FLOW-04 | 7 | `MT-2026-00001` / Meeting 4 | PASS | Complete | PASS |
| FLOW-05 | 8 | `MT-2026-00002` / Meeting 5 | PASS | Complete | PASS |
| FLOW-06 | 9 | `MT-2026-00003` / Meeting 6 | PASS | Complete | PASS |

All three flows completed their formal governance chains. The final database contained exactly three Meetings for the three accepted requests, with exactly one Meeting per request and no duplicate Meeting created by finalization. For FLOW-06 specifically, request 9 has `requesting_unit_id = NULL`, three formal `PARTICIPATING` Directorate units, one `DIRECTOR_GENERAL` authority context, chairperson employee 42 `عبدالقیوم احمدزی`, and exactly one persisted Meeting. [1]

## 3. FLOW-04 acceptance — Directorate with High Authority

FLOW-04 used request ID 7, request number `MR-2026-00004`, with Technology as the requesting Directorate and a `DIRECTORATE_WITH_HIGH_AUTHORITY` governance path. The fresh browser journey began with Technology Executive Management creating the request and continued through Technology Director approval, Authority Office review and forwarding, High Authority decision, Executive Secretary scheduling, requester acknowledgement, official schedule confirmation, requester participant confirmation, authority participant confirmation, and Executive Secretary finalization.

| Stage | Actor and evidence | Acceptance result |
|---|---|---|
| Creation | Technology Executive Management | Persian/Dari request created through the real React form |
| Requester approval | Technology Director | Director approval only; no scheduling or High Authority decision control exposed |
| Office review | Authority Office | Review and forwarding controls only; office could not decide for High Authority |
| Formal decision | High Authority `عبدالقیوم احمدزی` | Exact High Authority approval recorded; decision controls disappeared after completion |
| Scheduling | Executive Secretary | Official slot proposed and confirmed after requester acknowledgement |
| Participants | Technology Executive Management and Authority Office | Requester participant and mandatory authority participant were separately owned and confirmed |
| Finalization | Executive Secretary | One Meeting created through the live UI |

Meeting 4 rendered as `MT-2026-00001`, status `زمان‌بندی‌شده`, with Technology as the requesting/organizing Directorate, chairperson `عبدالقیوم احمدزی`, and exactly two participants: `احمد احمدی` with role `REQUESTING` and `عبدالقیوم احمدزی` with role `HIGH_AUTHORITY`. The formal unit set contains no peer `PARTICIPATING` Directorate, as required by FLOW-04. The complete browser evidence and timeline are recorded in the acceptance notes. [4]

## 4. FLOW-05 acceptance — Two Directorates with High Authority

FLOW-05 used request ID 8 with Technology as `REQUESTING` and Finance as `PARTICIPATING`. The real browser journey independently exercised Technology requester approval, Finance review initiation, Finance Director approval, Technology submission to Authority Office, Authority Office review and forwarding, High Authority approval, Executive Secretary scheduling, independent Technology and Finance schedule acknowledgements, official schedule confirmation, separate participant-list ownership, authority participant confirmation, and finalization.

| Stage | Actor and evidence | Acceptance result |
|---|---|---|
| Request creation | Technology Executive Management | Multi-High-Authority request created through the RTL UI |
| Requester approval | Technology Director | Technology approval completed in its own scope |
| Participating approval | Finance Executive Management and Finance Director | Finance review and approval completed independently |
| Office boundary | Authority Office | Review and forwarding only; no decision substitution |
| Formal decision | High Authority `عبدالقیوم احمدزی` | Exact authority decision completed |
| Schedule | Executive Secretary | `2026-10-20`, `09:30–11:00`, `تالار کنفرانس مرکزی`; both Directorates acknowledged before confirmation |
| Participants | Technology Executive, Finance Executive, Technology Director, Finance Director, Authority Office | Each Directorate managed its own list; Directors confirmed their own lists; office managed the authority list |
| Finalization | Executive Secretary | One Meeting created through the live UI |

Meeting 5 rendered as `MT-2026-00002`, status `زمان‌بندی‌شده`, with Technology as the requesting/organizing Directorate, Finance as the single `PARTICIPATING` Directorate, chairperson `عبدالقیوم احمدزی`, and exactly three participants: `احمد احمدی` as `REQUESTING`, `زلمی حکیمی` as `PARTICIPATING`, and `عبدالقیوم احمدزی` as `HIGH_AUTHORITY`. No duplicate finalization was observed. [4]

## 5. FLOW-06 acceptance — High Authority initiated, no requester Directorate

FLOW-06 used request ID 9, flow type `HIGH_AUTHORITY_INITIATED`, and no requesting Directorate. Technology, Finance, and Human Resources were formal participating Directorates. The canonical path deliberately included the mandatory draft-return branch before issuance: Authority Office created and submitted the draft; High Authority returned it with a reason; Authority Office corrected the directive, description, agenda, and HR measurable indicators; Authority Office resubmitted; and High Authority issued the corrected directive.

### 5.1 Issuance and coordination

| Stage | Actor | Evidence and result |
|---|---|---|
| Draft creation | Authority Office | Draft created with `requesting_unit_id = NULL` and three participating Directorates |
| Draft submission | Authority Office | Submitted for High Authority issuance |
| Mandatory return | High Authority | Returned once with a required Persian/Dari reason; `AUTHORITY_DRAFT_RETURNED` recorded |
| Correction | Authority Office | HR responsibilities, measurable indicators, revised description, and revised agenda entered through the real UI |
| Resubmission | Authority Office | Corrected draft resubmitted; `AUTHORITY_INITIATED_DRAFT_UPDATED` and a second submission event recorded |
| Formal issue | High Authority | Corrected directive issued; immutable directive version became `ISSUED` |
| Distribution | Authority Office | Directive distributed to Technology, Finance, and HR |
| Coordination | Technology, Finance, HR Executive Management | Each Executive Management role started coordination only within its own Directorate |
| Clarification | HR Director and Authority Office | HR requested clarification; Authority Office responded; no rejection or cancellation occurred |
| Acknowledgement | Technology, Finance, HR Directors | Each Director acknowledged only its own Directorate; `ALL_DIRECTORATES_ACKNOWLEDGED` recorded |

The High Authority return/correction/resubmit path is visible in the FLOW-06 timeline and was completed before the issue event. The inbox correction ensures that both High Authority and Executive Secretary discover their work through `/incoming-authority-initiated` rather than relying on an unlinked deep URL. [4] [5]

### 5.2 Scheduling, participant ownership, and finalization

Executive Secretary discovered FLOW-06 through the normal authority-initiated queue, started scheduling, and proposed `2026-10-25`, `10:00–12:00`, at `تالار کنفرانس مرکزی`. Technology, Finance, and HR Directors independently acknowledged the active proposal. Executive Secretary then confirmed the official schedule only after all three acknowledgements were visible in the refreshed workspace.

The participant sequence enforced the required ownership split. Technology, Finance, and HR Executive Management each saved only its own Directorate list; Technology, Finance, and HR Directors each confirmed only their own Directorate list. Authority Office then selected, saved, and confirmed the mandatory authority-side participant `عبدالقیوم احمدزی`. The timeline contains the expected paired events for all four ownership boundaries: three `PARTICIPANTS_UPDATED` events, three `UNIT_PARTICIPANTS_CONFIRMED` events, `AUTHORITY_PARTICIPANTS_UPDATED`, and `AUTHORITY_PARTICIPANTS_CONFIRMED`.

Executive Secretary rediscovered the fully prepared request through the normal queue and finalized it. The browser immediately returned to the request detail, showed `مشاهدهٔ جلسهٔ رسمی`, and displayed `MEETING_CREATED`. The same live browser session opened `/meetings/6`; the rendered page showed `MT-2026-00003`, `زمان‌بندی‌شده`, the official date and time, the central conference hall, chairperson `عبدالقیوم احمدزی`, three participating Directorates, and the High Authority participant. [1] [4]

## 6. Mandatory live FLOW-06 MariaDB gate

The final FLOW-06 gate was verified after browser finalization, not substituted with a refresh-reset or isolated test fixture. The direct MariaDB query against `amms_browser_e2e` returned the following authoritative facts. [1]

| Invariant | Observed value | Result |
|---|---|---|
| Meeting row | ID 6, `MT-2026-00003` | PASS |
| Meeting status | `SCHEDULED` | PASS |
| Request linkage | Meeting 6 → request 9 | PASS |
| Requesting Directorate | `requesting_unit_id = NULL` | PASS |
| Formal units | Technology, Finance, HR | PASS |
| Formal unit roles | All three `PARTICIPATING` | PASS |
| Authority context | Exactly one `DIRECTOR_GENERAL` context | PASS |
| Chairperson | Employee 42, `عبدالقیوم احمدزی` | PASS |
| High Authority participant | Employee 42, role `HIGH_AUTHORITY` | PASS |
| Directorate participants | احمد احمدی, زلمی حکیمی, حسن احمدی | PASS |
| Participants total | Four: three Directorate participants plus High Authority | PASS |
| Meeting uniqueness | Exactly one Meeting for request 9 | PASS |

The database contains three total Meetings because FLOW-04, FLOW-05, and FLOW-06 each created one accepted Meeting. The relevant uniqueness invariant is per request, and it is satisfied: request 9 maps to one and only one Meeting. [1]

## 7. Role separation and cross-role visibility

The acceptance did not treat backend authorization alone as sufficient. Each representative actor was logged out and re-authenticated through the RTL login surface, then rediscovered work through normal dashboard or authority-initiated queue navigation. Major pages were reopened after mutation, and role-specific controls were checked after the refreshed response. The observed visibility matrix is summarized below.

| Role | Allowed responsibility observed | Forbidden responsibility not exposed or denied |
|---|---|---|
| Executive Management | Create/coordinate within its own Directorate; save its own participant list | High Authority decision, formal schedule confirmation, finalization, other Directorate writes |
| Director | Directorate approval/acknowledgement and own-list confirmation | Coordination start, High Authority issue/decision, other Directorate approval or participant confirmation |
| Authority Office | Administrative review, forwarding/distribution, clarification response, authority-side participant ownership | High Authority issue/decision, Directorate approval, Executive Secretary finalization |
| Executive Secretary | Authority scheduling, schedule confirmation, finalization | Directorate approval, High Authority issue/decision, Directorate participant-list writes |
| High Authority | Exact formal issue/decision for the formal authority holder | Directorate approval, Authority Office distribution, Executive Secretary scheduling/finalization |
| SYSTEM_ADMIN | Technical administration only | All tested FLOW-04/05/06 business governance operations |

No unresolved incorrect visibility issue remained for the accepted FLOW-04, FLOW-05, or FLOW-06 surfaces. Where a backend boundary was intentionally exercised directly, the API returned HTTP 403 before business mutation. [2] [3]

## 8. Direct HTTP negative authorization matrix

The negative checks were executed against the live Laravel API with real account authentication. All login operations returned HTTP 200, and every forbidden business operation returned HTTP 403 with `code = FORBIDDEN`. The FLOW-04 evidence is retained in its original NDJSON artifact; the FLOW-06 evidence was generated after finalization and is retained separately. [2] [3]

| Flow | Actor | Forbidden operation | HTTP status | Result |
|---|---|---|---:|---|
| FLOW-04 | Authority Office | High Authority decision | 403 | PASS |
| FLOW-04 | Technology Executive Management | High Authority decision | 403 | PASS |
| FLOW-04 | Technology Executive Management | Authority scheduling start | 403 | PASS |
| FLOW-04 | SYSTEM_ADMIN | Business scheduling | 403 | PASS |
| FLOW-06 | Authority Office | High Authority issue | 403 | PASS |
| FLOW-06 | Technology Director | High Authority issue | 403 | PASS |
| FLOW-06 | Technology Executive Management | Authority-initiated scheduling start | 403 | PASS |
| FLOW-06 | SYSTEM_ADMIN | Authority-initiated finalization | 403 | PASS |

The FLOW-05 browser journey independently verified the same role boundaries through the real UI: Directorate roles could not decide for High Authority, could not finalize, and could only acknowledge or manage their own Directorate-specific controls. The consolidated backend contract suite also covers FLOW-05 cross-scope action denial and the exact High Authority requirement. [4]

## 9. Stale state, refresh, and discoverability

After each major FLOW-04, FLOW-05, and FLOW-06 mutation, the acceptance reopened the relevant detail or workspace and used the server-rendered response as the source of truth. State changes were not accepted solely from local checkbox or button state. The final FLOW-06 journey specifically reauthenticated every actor, used the normal work queue, reopened the request, and verified the resulting timeline before the next role acted.

| Freshness/discoverability check | Result |
|---|---|
| Authority Office normal queue discovery | PASS |
| High Authority normal queue discovery | PASS after position-scope correction |
| Executive Secretary normal queue discovery | PASS after role-scope correction |
| Directorate dashboard/request discovery | PASS for Technology, Finance, and HR |
| Post-acknowledgement schedule refresh | PASS |
| Post-participant-save timeline refresh | PASS |
| Post-participant-confirmation refresh | PASS |
| Post-finalization request refresh | PASS |
| Same-session Meeting navigation | PASS for `/meetings/6` |

## 10. Code changes included in this acceptance

| File | Change | Scope |
|---|---|---|
| `backend/app/Http/Controllers/Api/AuthorityInitiatedMeetingRequestController.php` | Extended `officeInbox()` to include formal HIGH_AUTHORITY position scope and EXECUTIVE_SECRETARY role scope | FLOW-06 discoverability only |
| `frontend/src/flow06.tsx` | Corrected draft correction resubmission to send `preferred_start_time?.slice(0,5)` | FLOW-06 validation compatibility only |
| `backend/scripts/flow06_negative_http_checks.py` | File-based live HTTP probe used to record the four FLOW-06 403 checks | Acceptance evidence |
| `flow06_negative_http_checks.ndjson` | Machine-readable FLOW-06 negative-check results | Acceptance evidence |
| `evidence/flow06_live_mariadb_verification.txt` | Direct live MariaDB result for Meeting 6 and request 9 | Acceptance evidence |

No FLOW-07, FLOW-15, or FLOW-10B production code or acceptance path was started or touched.

## 11. Final gate decision

| Gate | Required condition | Result |
|---|---|---|
| FLOW-04 | Complete browser journey and one valid Meeting | PASS |
| FLOW-05 | Complete browser journey and one valid Meeting | PASS |
| FLOW-06 | Complete browser journey and one valid Meeting | PASS |
| FLOW-06 live Meeting gate | Same live browser/MariaDB session | PASS |
| Requester invariant | FLOW-06 has no requesting Directorate | PASS |
| Formal units | FLOW-06 has exactly three participating Directorates | PASS |
| High Authority invariant | Exact holder is chairperson and participant | PASS |
| Duplicate Meetings | Zero duplicate Meetings per accepted request | PASS |
| Role visibility | Unresolved incorrect visibility issues = 0 | PASS |
| SYSTEM_ADMIN business leaks | 0 | PASS |
| Negative authorization | All representative checks return 403 | PASS |
| Unresolved HIGH/BLOCKER findings | 0 | PASS |

**Overall result: `overall_pass = true`.** The post-consolidation browser acceptance for FLOW-04, FLOW-05, and FLOW-06 is complete and verified. This report does not perform or imply an automatic freeze.

## References

[1]: evidence/flow06_live_mariadb_verification.txt "Live FLOW-06 MariaDB verification"
[2]: flow04_negative_http_checks.ndjson "FLOW-04 direct HTTP negative authorization evidence"
[3]: flow06_negative_http_checks.ndjson "FLOW-06 direct HTTP negative authorization evidence"
[4]: master_post_consolidation_acceptance_notes.md "Master post-consolidation browser acceptance notes"
[5]: backend/app/Http/Controllers/Api/AuthorityInitiatedMeetingRequestController.php "Authority-initiated controller inbox scope correction"
[6]: frontend/src/flow06.tsx "FLOW-06 React time-format correction"
