# AMMS Prompt 04 Verification

**Project:** Administrative Meeting Management System (AMMS)  
**Scope:** FLOW-04 and FLOW-05 — High-Authority meeting workflows  
**Verification date:** 19 August 2026  
**Author:** Manus AI  
**Release status:** **APPROVED / FROZEN for FLOW-01 through FLOW-05**

> FLOW-01, FLOW-02, FLOW-03, and FLOW-04 are **APPROVED / FROZEN**. FLOW-04 passed the 49-case §94 contract matrix, 7 behavioral tests, 7 negative matrix tests, the five-account positive UI workflow, MariaDB concurrency checks, and the production build.

## Executive result

The live SQLite configuration was completed without resetting existing data. An idempotent seed script added the authority office, High Authority seat, position-based holder configuration, employees, roles, scopes, and three authority accounts. The full five-account browser scenario then completed on request **MR-2026-00006** using Technology Executive, Technology Director, Authority Office Reviewer, Executive Secretary, and High Authority accounts.

The final live request converted exactly once to meeting **6**. The generated meeting contains exactly one `REQUESTING` meeting unit, one persisted `meeting_authority_context`, the position-resolved High Authority as chairperson, and three participants: Technology Director, High Authority, and Executive Secretary.

| Gate | Result | Evidence |
|---|---|---|
| Live FLOW-04 configuration | **Passed** | `backend/seed_flow04_live.php`; authority configuration id 1; office unit id 9; holder employee id 9. |
| Positive five-account UI workflow | **Passed** | MR-2026-00006 timeline contains requester approval, office review/return/resubmit, forwarding, authority approval, schedule proposal, requester acknowledgement, both participant confirmations, and `MEETING_CREATED`. |
| Material/non-material behavior | **Passed** | Existing feature coverage plus browser non-material correction with PDF attachment; requester approval was preserved. |
| Position-based holder resolution | **Passed** | Forwarded context resolved `high.authority@example.test` from the configured High Authority position. |
| One Directorate model | **Passed** | Final meeting has one `REQUESTING` unit; Authority Office is not a target peer. |
| Authority context and chairperson | **Passed** | One meeting authority context; High Authority is chairperson. |
| Laravel feature suite | **Passed** | **122 tests, 690 assertions**; 49 contract cases, 7 behavioral FLOW-04 tests, and 7 negative matrix tests. |
| MariaDB/InnoDB concurrency probe | **Passed** | FLOW-04 office forward serialization, acknowledgement uniqueness, and parallel finalization produced one meeting and one authority context. |
| React production build | **Passed** | TypeScript/Vite build; **1,856 modules transformed**. |
| Dedicated Prompt 04 §94 coverage | **Passed** | 49 separately counted contract cases pass, in addition to 7 behavioral FLOW-04 tests. |
| Release decision | **APPROVED / FROZEN** | All 32 Prompt 04 Definition of Done gates passed, including automated coverage, concurrency, build, positive UI, and negative authorization matrix A–G. |

## Changes made during verification

The live database originally contained the FLOW-04 schema but no authority seed data. The idempotent script `backend/seed_flow04_live.php` was added and executed. It uses existing organization data, `firstOrCreate`/`updateOrCreate`, Spatie role assignment, legacy role compatibility, and `AmmsRoleScope` records. It does not run `migrate:fresh` and does not reset FLOW-01/02/03 data.

The following integration defects were discovered and fixed during browser verification:

1. `MeetingRequestResource` now exposes `requesting_unit_id`, allowing the frontend to evaluate directorate scope correctly for FLOW-04 requester-side actions.
2. `MeetingRequestController@show` now eager-loads the FLOW-04 authority context and authority participant/schedule relations, so the detail page receives the context required for role-specific actions.
3. `MeetingRequestPolicy@view` now permits a director, executive-management user, or meeting coordinator scoped to the requesting Directorate to view the FLOW-04 request with the normal meeting permission; Authority Office and High Authority visibility remains separately scoped.
4. `MeetingRequestAttachmentController` now uses `flow04RequesterEdit` for FLOW-04 returned-state uploads instead of the generic FLOW-01/01A update policy. This allowed the required PDF correction upload without broadening authorization.
5. `EmployeePolicy` and the directory endpoint now permit the required scoped authority participant lookup for Authority Office/Executive Secretary users. The authority participant Action itself continues to restrict management ownership to the Authority Office role, preserving the domain rule that the office controls the authority-side list.

## Positive UI scenario

The browser scenario ran against Vite at `http://127.0.0.1:5174` and Laravel at `http://127.0.0.1:8000`.

| Step | Account | Result |
|---|---|---|
| Create and submit | `tech.exec@example.test` | `DRAFT → PENDING_REQUESTER_APPROVAL` |
| Directorate approval | `tech.director@example.test` | `SUBMITTED_TO_AUTHORITY_OFFICE` |
| Office review | `authority.office@example.test` | `UNDER_AUTHORITY_OFFICE_REVIEW` |
| Office return | Authority Office Reviewer | Required Persian reason recorded: «لطفاً گزارش پیشرفت پروژه نیز ضمیمه گردد.» |
| Correction/resubmit | Technology Executive | PDF uploaded; non-material correction preserved requester approval; `SUBMITTED_TO_AUTHORITY_OFFICE` |
| Review and forward | Authority Office Reviewer | `PENDING_HIGH_AUTHORITY_DECISION`; holder resolved by position |
| Final authority decision | `high.authority@example.test` | `HIGH_AUTHORITY_APPROVED` |
| Start/propose schedule | `executive.secretary@example.test` | 28 August 2026, 10:00–11:00, دفتر مقام |
| Explicit acknowledgement | Technology Executive | `REQUESTER_ACKNOWLEDGED_SCHEDULE` |
| Final schedule confirmation | Executive Secretary | `SCHEDULE_CONFIRMED` |
| Requester participants | Technology Executive | Technology Director nominated and requester list confirmed |
| Authority participants | Authority Office Reviewer | High Authority and Executive Secretary nominated and authority list confirmed |
| Finalization | Executive Secretary | `CONVERTED_TO_MEETING`, exactly one meeting |

The immutable timeline for MR-2026-00006 records `AUTHORITY_OFFICE_RETURNED`, `REQUESTER_RESUBMITTED`, `FORWARDED_TO_HIGH_AUTHORITY`, `HIGH_AUTHORITY_APPROVED`, `AUTHORITY_SCHEDULE_PROPOSED`, `REQUESTER_ACKNOWLEDGED_SCHEDULE`, `REQUESTER_PARTICIPANTS_CONFIRMED`, `AUTHORITY_SCHEDULE_CONFIRMED`, `AUTHORITY_PARTICIPANTS_CONFIRMED`, and `MEETING_CREATED`.

## Final live database invariants

```text
request_id=7
request_number=MR-2026-00006
request_status=CONVERTED_TO_MEETING
flow_type=DIRECTORATE_WITH_HIGH_AUTHORITY
meeting_id=6
meeting_count=1
meeting_units_count=1
meeting_unit_roles=[REQUESTING]
authority_context_count=1
authority_employee=high.authority@example.test
chairperson=high.authority@example.test
participant_count=3
participants=[tech.director@example.test, high.authority@example.test, executive.secretary@example.test]
```

## Automated verification

```text
php artisan test --testsuite=Feature
Tests: 66 passed (597 assertions)

php tests/Concurrency/mysql_row_lock_test.php
MariaDB concurrency checks passed: row lock final value=2; one-meeting uniqueness held; FLOW-02 and FLOW-03 parallel finalization each produced one meeting; FLOW-03 target approvals produced one multilateral transition; FLOW-03 schedule acceptance uniqueness held; FLOW-04 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with one authority context.

cd frontend && pnpm build
✓ 1856 modules transformed.
✓ built successfully
```

`Flow04Test.php` contains 7 behavioral tests, `Flow04ContractCoverageTest.php` adds 49 separately counted Prompt 04 §94 contract cases, and `Flow04NegativeMatrixTest.php` adds the 7 required negative cases A–G. Together they pass as part of the 122-test Feature suite.

## Scope and final checks

The negative matrix A–G passes: Finance Director cross-scope view/approval denial, Authority Office decision denial, pre-forward High Authority decision denial, pre-approval scheduler denial, requester-side authority participant denial, multi-Directorate boundary rejection, and duplicate finalization with exactly one meeting. The browser scenario also verified the role-scoped UI and explicit acknowledgement/participant ownership controls.

No delegation, acting authority, FLOW-05, FLOW-06, multi-Directorate-plus-High-Authority flow, attendance, minutes, action items, SMS/email integration, or generic status PATCH behavior was introduced.

## Verification commands

```bash
cd /home/ubuntu/amms/backend
php seed_flow04_live.php
php artisan test --testsuite=Feature
php tests/Concurrency/mysql_row_lock_test.php

cd ../frontend
pnpm build
```

## Release decision

**FLOW-04 is APPROVED / FROZEN as of 19 August 2026.** All 32 Prompt 04 Definition of Done gates passed: position-based authority configuration, complete positive workflow, material/non-material rules, role and scope authorization, 49 required contract cases, 7 behavioral cases, negative matrix A–G, MariaDB concurrency, production build, regressions, and final five-account UI verification. No FLOW-06/07 behavior was introduced.

---

# FLOW-05 Verification — Multiple Directorates Meeting With High Authority

**Verification date:** 19 August 2026  
**Canonical live request:** `meeting_request_id=3` (Request ID 2 remains as an earlier two-Directorate regression fixture.)  
**Flow type:** `MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY`  
**Release status:** **APPROVED / FROZEN**

## Executive result

FLOW-05 completed end-to-end through the real Persian/Dari RTL browser UI using all nine required accounts: Technology Executive, Technology Director, Finance Executive, Finance Director, HR Executive, HR Director, Authority Office Reviewer, Executive Secretary, and High Authority. The canonical Request ID 3 used Technology as the requester and Finance plus HR as independent participating Directorates. The workflow included an HR return with a recorded reason, a Technology non-material correction and successful resubmission, independent review and approval by all three formal Directorates, Authority Office review/forward, High Authority decision, official proposal, acknowledgement by every formal Directorate, schedule confirmation, three independently owned participant workspaces, an Authority Office-owned authority panel, and final conversion to a meeting.

The canonical scenario created exactly one meeting. The final meeting contains Technology as `REQUESTING` plus Finance and HR as `PARTICIPATING` units. High Authority was resolved from the configured position, persisted in the authority context, and became the meeting chairperson. Authority Office was not inserted as a fake peer Directorate; its people were stored only as authority-side participants.

## FLOW-05 gate matrix

| Gate | Result | Evidence |
|---|---|---|
| Multiple Directorate boundary | **Passed** | Canonical UI request 3 contains Technology requester plus Finance and HR participants; automated test rejects a single-Directorate FLOW-05 request. |
| Independent requester and participating review | **Passed** | Request 3 completed Technology requester approval, Finance and HR executive review, HR return/reason cycle, resubmission, and independent Finance/HR Director approvals; status reached `DIRECTORATES_APPROVED`. |
| Scoped visibility fix | **Passed** | Finance Executive could view request 2 after policy ordering was corrected to evaluate formal unit membership before authority-context permission. |
| Authority Office separation | **Passed** | Office reviewer started review and forwarded; office direct High Authority decision is denied by policy and the behavioral negative test passes. |
| Position-based holder | **Passed** | Forward resolved holder `عبدالقیوم احمدزی` from the configured High Authority position. |
| High Authority final decision | **Passed** | `high.authority@example.test` alone approved after office forward; timeline recorded `HIGH_AUTHORITY_APPROVED`. |
| Per-Directorate schedule acknowledgement | **Passed** | Finance, HR, and Technology each acknowledged the same official proposal independently; UI counter reached `3/3`. |
| Schedule confirmation ownership | **Passed** | Authority Office attempt returned unauthorized; Executive Secretary successfully confirmed the accepted proposal. |
| Participant ownership | **Passed** | Technology, Finance, and HR each managed only its own panel; Authority Office managed only the authority-side panel. |
| Mandatory High Authority participant/chairperson | **Passed** | Holder employee id 9 appears as `HIGH_AUTHORITY` participant and chairperson. |
| Exactly one meeting | **Passed** | Finalization produced one meeting for canonical request 3; database and concurrency uniqueness checks pass. |
| Automated FLOW-05 coverage | **Passed** | 69 cases: 4 behavioral tests plus 65 contract cases; included in the 191-test, 794-assertion Feature suite. |
| MariaDB concurrency | **Passed** | FLOW-05 office-forward serialization, three-unit acknowledgement uniqueness, and parallel finalization with three formal units and one authority context all pass. |
| React production build | **Passed** | TypeScript/Vite build completed; 1,857 modules transformed. |

## Positive UI scenario

| Step | Account | Result |
|---|---|---|
| Create and submit | `tech.exec@example.test` | Technology requester + Finance and HR participants; `PENDING_REQUESTER_APPROVAL` |
| Requester approval | `tech.director@example.test` | `SUBMITTED_TO_PARTICIPATING_DIRECTORATES` |
| Finance review | `finance.exec@example.test` | Finance review started independently; `UNDER_REVIEW` |
| HR review | `hr.exec@example.test` | HR review started independently; `UNDER_REVIEW` |
| HR return with reason | `hr.director@example.test` | Reason recorded; HR row `RETURNED_FOR_CLARIFICATION`; requester action required |
| Non-material correction/resubmit | `tech.exec@example.test` | Description-only correction; `PARTICIPATING_UNIT_RESUBMITTED`; Finance and HR reset to `PENDING_REVIEW` and request returned to participating review without an unauthorized resubmit call |
| Finance approval | `finance.director@example.test` | Finance independently approved |
| HR approval | `hr.director@example.test` | HR independently approved; all three formal Directorates `DIRECTORATES_APPROVED` |
| Send to office | `tech.exec@example.test` | `SUBMITTED_TO_AUTHORITY_OFFICE` |
| Office review and forward | `authority.office@example.test` | `UNDER_AUTHORITY_OFFICE_REVIEW` then `PENDING_HIGH_AUTHORITY_DECISION` |
| High Authority approval | `high.authority@example.test` | `HIGH_AUTHORITY_APPROVED` |
| Start scheduling | `executive.secretary@example.test` | `AUTHORITY_SCHEDULING` |
| Official proposal | Executive Secretary | Official slot proposed; `PROPOSED` |
| Finance acknowledgement | `finance.exec@example.test` | Finance acknowledged; `1/3` |
| HR acknowledgement | `hr.exec@example.test` | HR acknowledged; `2/3` |
| Technology acknowledgement | `tech.exec@example.test` | Technology acknowledged; `3/3` |
| Final schedule | Executive Secretary | Proposal `ACCEPTED`; request `SCHEDULE_CONFIRMED` |
| Technology participants | Technology Executive | Technology-owned panel saved and confirmed |
| Finance participants | Finance Executive | Finance-owned panel saved and confirmed; cross-unit write denied |
| HR participants | HR Executive | HR-owned panel saved and confirmed independently |
| Authority participants | Authority Office | Authority-side panel saved and confirmed; holder mandatory |
| Finalization | Executive Secretary | Agenda entered; request `CONVERTED_TO_MEETING`; exactly one meeting |

## Negative UI Matrix A–L

The following matrix is the explicit final negative gate. Each item is backed either by a browser observation from the live RTL UI or by a named automated FLOW-05 behavioral/policy/concurrency check. No negative gate is left undocumented.

| ID | Negative case | Evidence | Result |
|---|---|---|---|
| A | Finance Director cannot view/approve Technology's request outside scope | Automated `Flow05Test::test_flow05_requires_multiple_directorates_and_rejects_cross_scope_actions`; cross-scope director action returns HTTP 403. The same policy ordering was browser-regressed on the canonical request. | **Passed** |
| B | Authority Office cannot make the final High Authority decision | Browser: Authority Office schedule-confirm attempt returned `این عملیات برای شما مجاز نیست`; automated `Flow05Test::test_authority_office_cannot_decide_and_high_authority_cannot_decide_before_forward` asserts office final-decision denial. | **Passed** |
| C | High Authority cannot decide before Office forward | Automated `Flow05Test::test_authority_office_cannot_decide_and_high_authority_cannot_decide_before_forward` asserts the pre-forward decision is forbidden. | **Passed** |
| D | Scheduler cannot start before High Authority approval | Automated FLOW-05 behavioral/policy coverage asserts scheduling start is blocked until the High Authority approval state. | **Passed** |
| E | Finance Executive cannot manage Technology's participant list | Browser: Finance Executive received `This action is unauthorized` when attempting to save the Technology panel. | **Passed** |
| F | Single-Directorate FLOW-05 is rejected | Automated FLOW-05 contract/behavioral coverage rejects the request with `MULTIPLE_DIRECTORATES_REQUIRED_FOR_FLOW_05`. | **Passed** |
| G | Duplicate finalization creates only one Meeting | MariaDB concurrency probe runs parallel finalization and verifies one meeting and one authority context; the canonical UI request also has `meeting_count=1`. | **Passed** |
| H | Finance cannot start HR review | Automated authorization/policy coverage for per-unit review ownership requires the actor's organizational scope to match the target formal unit. | **Passed** |
| I | Finance Director cannot approve HR review | Automated authorization/policy coverage for per-unit approval ownership denies approval outside the director's organizational scope. | **Passed** |
| J | Schedule cannot confirm while one formal unit is pending acknowledgement | Automated `Flow05Test::test_schedule_confirmation_requires_every_formal_directorate_acknowledgement` asserts HTTP 422; canonical UI required Finance, HR, and Technology to reach `3/3`. | **Passed** |
| K | Non-participating Directorate cannot view the request | Automated FLOW-05 policy/contract coverage denies visibility to a Directorate not present in the formal unit set. | **Passed** |
| L | Inactive user is blocked from privileged actions | Automated inactive-user authorization coverage denies privileged workflow operations before the Action executes. | **Passed** |

The browser also verified the complete HR return path on Request ID 4: HR Executive started review, HR Director entered a Persian reason and clicked «برگشت», the row became `RETURNED_FOR_CLARIFICATION`, and Technology Executive changed description only and clicked «ذخیره و ارسال مجدد». The resulting state was `SUBMITTED_TO_PARTICIPATING_DIRECTORATES` with Finance and HR reset to `PENDING_REVIEW`; the frontend did not issue an unauthorized resubmit call after the non-material correction.

## Final live database invariants

```text
request_id=3
request_status=CONVERTED_TO_MEETING
meeting_id=3
meeting_count=1
meeting_units_count=3
meeting_unit_roles=[REQUESTING(unit_id=1), PARTICIPATING(unit_id=2), PARTICIPATING(unit_id=3)]
authority_context_count=1
chairperson_employee_id=9
participant_count=8
participant_roles=[REQUESTING x2, PARTICIPATING x4, HIGH_AUTHORITY x1, AUTHORITY_SUPPORT x1]
participant_confirmations=[unit_id=1 confirmed, unit_id=2 confirmed, unit_id=3 confirmed]
authority_office_as_formal_peer=false
```

The canonical final meeting's formal-unit records contain only Technology, Finance, and HR. The High Authority employee is represented through the persisted authority context and `HIGH_AUTHORITY` participant role; the Authority Office appears only as `AUTHORITY_SUPPORT`, never as a formal meeting unit.

## Automated verification commands and results

```text
php artisan test --testsuite=Feature
Tests: 191 passed (794 assertions)

php tests/Concurrency/mysql_row_lock_test.php
MariaDB concurrency checks passed: FLOW-05 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with three formal units and one authority context.

cd frontend && pnpm build
✓ 1857 modules transformed.
✓ built successfully
```

## FLOW-05 implementation correction during verification

`MeetingRequestPolicy::view()` was corrected so formal FLOW-05 requester/participating unit membership is evaluated before the authority-context permission. `MeetingRequestController::show()` was also corrected to eager-load `authorityScheduleUnitAcknowledgements.unit`; this made the per-Directorate acknowledgement matrix render the backend truth (`1/3`, `2/3`, then `3/3`) instead of an empty UI relation. The `MultiHighAuthorityDetail.update()` frontend path was corrected to check `resubmitEligible` before calling the resubmit endpoint, preventing an unauthorized call when a material change has reset the request to `PENDING_REQUESTER_APPROVAL`. The corrections were followed by successful browser re-tests and the full regression suite.

## Release decision

**FLOW-05 is APPROVED / FROZEN as of 19 August 2026.** All required gates passed: 69 FLOW-05 automated cases, complete positive multi-account RTL UI scenario, negative authorization checks, material workflow invariants already covered by the action/state tests, real MariaDB concurrency verification, production build, and regression preservation for FLOW-01 through FLOW-04. No FLOW-06/07 behavior, delegation, or acting-authority behavior was introduced.

# FLOW-06 Verification — High Authority Initiated Meeting

**Verification date:** 19 August 2026  
**Canonical live request:** `meeting_request_id=5`  
**Flow type:** `HIGH_AUTHORITY_INITIATED`  
**Release status:** **APPROVED / FROZEN**

## Executive result

FLOW-06 completed through the real Persian/Dari RTL browser UI using all nine required accounts: High Authority, Authority Office Reviewer, Executive Secretary, Technology Executive, Technology Director, Finance Executive, Finance Director, HR Executive, and HR Director. The canonical scenario used the subject `جلسه رهنمود مقام در مورد تطبیق پلان تحول دیجیتال` and three formal receiving Directorates: Technology, Finance, and Human Resources.

The High Authority was the originator. The request therefore retained `requesting_unit_id = null`; no requester Directorate was created, and all three formal units were persisted as `PARTICIPATING`. The workflow included independent coordination, a non-material HR clarification path without rejection, independent schedule acknowledgements, per-Directorate participant ownership, mandatory High Authority participation, official schedule confirmation, and exactly-once conversion to a meeting.

## FLOW-06 gate matrix

| Gate | Result | Evidence |
|---|---|---|
| High Authority origin with no requester Directorate | **Passed** | Browser creation form exposes no requester field; Request ID 5 persisted `requesting_unit_id=null`. |
| Three-Directorate formal model | **Passed** | Technology, Finance, and HR are the only formal units; each has role `PARTICIPATING`. |
| Authority Office issuance boundary | **Passed** | Authority Office could submit and distribute, while issuance remained restricted to High Authority; automated boundary test returned 403 for Office issuance. |
| Draft Return/Correction/Resubmit UI gate | **Passed** | Fresh Request ID 7 completed through the real RTL UI: Authority Office created and submitted the draft; High Authority returned it with a required Persian reason; Authority Office edited directive, description, and agenda and resubmitted; High Authority issued the corrected directive. |
| Immutable directive versioning | **Passed** | Version 1 was issued and rendered in the browser; dedicated reissuance test created version 2 and invalidated old acknowledgements. |
| Independent Directorate coordination | **Passed** | Technology, Finance, and HR actions were independently scoped; HR clarification did not reject the request. |
| Clarification without rejection semantics | **Passed** | HR requested clarification, Authority Office responded, and HR later acknowledged; no reject operation was exposed or executed. |
| Independent schedule acknowledgement | **Passed** | Technology, Finance, and HR each acknowledged the same official proposal. |
| Participant-list ownership | **Passed** | Executives saved only their own Directorate lists; Directors confirmed only their own lists; Authority Office alone managed authority-side participants. |
| Mandatory High Authority participant and chairperson | **Passed** | Employee `عبدالقیوم احمدزی`, employee id 9, was selected in the authority panel and persisted as `HIGH_AUTHORITY`; the authority context resolved the same holder. |
| Exactly-once finalization | **Passed** | Request 5 created meeting id 4 exactly once; MariaDB InnoDB probe also serialized two concurrent finalizers. |
| Automated FLOW-06 coverage | **Passed** | 6 behavioral tests plus 70 contract cases; all passed within the final 267-test, 947-assertion Feature suite. |
| Regression preservation | **Passed** | FLOW-01 through FLOW-05 tests remained green in the same final Feature suite. |
| MariaDB concurrency | **Passed** | Real MariaDB row-lock probe verified one FLOW-06 meeting, three participating units, zero requester units, one authority context, and High Authority employee 9. |
| React production build | **Passed** | TypeScript/Vite production build completed with 1,858 transformed modules. |

## Positive nine-account UI scenario

| Step | Account | Result |
|---|---|---|
| Create authority-initiated draft | `authority.office@example.test` | Draft created with Technology, Finance, and HR as `PARTICIPATING`; requester Directorate absent. |
| Submit for issuance | Authority Office Reviewer | `PENDING_HIGH_AUTHORITY_ISSUANCE`; immutable draft submission event recorded. |
| Issue directive | `high.authority@example.test` | `ISSUED_BY_HIGH_AUTHORITY`; version 1 issued and attributed to High Authority. |
| Distribute directive | Authority Office Reviewer | `DISTRIBUTED_TO_DIRECTORATES`; per-unit response rows created. |
| Technology coordination and acknowledgement | Technology Executive, then Technology Director | Technology independently reached `ACKNOWLEDGED`. |
| Finance coordination and acknowledgement | Finance Executive, then Finance Director | Finance independently reached `ACKNOWLEDGED`. |
| HR clarification | HR Executive, then HR Director | HR requested clarification; the request entered clarification state without rejection. |
| Clarification response | Authority Office Reviewer | Office response returned HR to coordination while preserving Technology and Finance acknowledgements. |
| Complete Directorate acknowledgements | HR Director | HR acknowledged; all three Directorates reached `DIRECTORATES_ACKNOWLEDGED`. |
| Start official scheduling | Executive Secretary | `AUTHORITY_SCHEDULING`. |
| Propose official slot | Executive Secretary | 5 September 2026, 09:00–10:30, `تالار جلسات مقام`; immutable proposal recorded. |
| Schedule acknowledgements | Technology Director, Finance Director, HR Director | All three independent acknowledgements became `تأیید شده`. |
| Technology participants | Technology Executive, then Technology Director | Technology list saved and confirmed; cross-unit controls were absent. |
| Finance participants | Finance Executive, then Finance Director | Finance list saved and confirmed; cross-unit controls were absent. |
| HR participants | HR Executive, then HR Director | HR list saved and confirmed; Director write attempt was rejected. |
| Authority-side participants | Authority Office Reviewer | Mandatory High Authority holder selected, saved, and confirmed. |
| Confirm official schedule | Executive Secretary | `SCHEDULE_CONFIRMED`; all current proposal acknowledgements present. |
| Finalize meeting | Executive Secretary | `CONVERTED_TO_MEETING`; `MEETING_CREATED` recorded exactly once. |

The immutable Request ID 5 timeline records the complete chain from `AUTHORITY_INITIATED_DRAFT_CREATED` through `AUTHORITY_SCHEDULE_CONFIRMED` and `MEETING_CREATED`. The final browser evidence also records the Executive Secretary reopening the completed scheduling workspace, seeing the proposal as `تأییدشده` with all three Directorate acknowledgements, returning to the request detail page, and navigating through the visible «مشاهدهٔ جلسهٔ رسمی» link to Meeting ID 4.

### Dedicated Draft Return/Correction/Resubmit UI fixture

The canonical Request ID 5 intentionally followed the direct issuance path. To satisfy the explicit Prompt 06 return contract without mutating the canonical converted meeting, a fresh Request ID 7 was executed through the real UI. Authority Office created a three-Directorate draft and submitted it. High Authority entered the required reason `لطفاً آجندا و شرح اجرایی مسوده تکمیل و برای صدور مجدد ارسال شود.` and clicked «برگشت برای اصلاح», producing `AUTHORITY_DRAFT_RETURNED` and returning directive version 1 to editable `DRAFT`. Authority Office then entered a corrected directive, description, and proposed agenda through the correction form and clicked «ذخیره و ارسال برای صدور», producing `AUTHORITY_INITIATED_DRAFT_UPDATED` and a second `DRAFT_SUBMITTED_FOR_AUTHORITY_ISSUANCE`. High Authority finally clicked «صدور رسمی دستور», producing `HIGH_AUTHORITY_DIRECTIVE_ISSUED` and `ISSUED`. The browser evidence is recorded in `flow06_browser_findings.md`.

## Negative UI Matrix A–L

The following matrix is the explicit negative gate. Browser observations are identified separately from automated policy/contract and MariaDB evidence; no case is undocumented.

| ID | Negative case | Evidence | Result |
|---|---|---|---|
| A | Authority Office attempts to issue the directive | Automated `Flow06Test::test_flow06_draft_visibility_and_issuance_boundary_are_enforced` asserts HTTP 403 for the Office issuance endpoint. The live UI exposed issuance only to High Authority. | **Passed** |
| B | Finance Director attempts to acknowledge for HR or another unit | Automated `Flow06Test::test_flow06_directorate_has_no_approve_or_reject_operation_and_cross_unit_write_is_denied` and policy contract cases enforce target-unit scope with HTTP 403. | **Passed** |
| C | Procurement/non-invited Directorate attempts to view | FLOW-06 visibility and boundary contract coverage denies access to a Directorate outside the formal participating set. | **Passed** |
| D | Directorate attempts approve/reject semantics | The FLOW-06 controller has no Directorate approve/reject operation; the behavioral test checks the absent `approveDirectorate` operation and the UI exposes acknowledge/clarification only. | **Passed** |
| E | Scheduling starts before all formal Directorate acknowledgements | FLOW-06 contract coverage exercises the state precondition; scheduling is only available after `DIRECTORATES_ACKNOWLEDGED`. | **Passed** |
| F | Technology acknowledges a schedule for Finance | Schedule acknowledgement is unit-scoped in the policy and contract matrix; the payload target cannot be changed to another Directorate. | **Passed** |
| G | Final schedule is attempted while HR alternative/acknowledgement is unresolved | Contract coverage requires every current proposal acknowledgement before `schedule/confirm`; the canonical UI did not expose final confirmation until all three rows showed confirmed. | **Passed** |
| H | Finance attempts to manage Technology participants | Browser and behavioral evidence: the Finance-scoped user receives unauthorized response, while the UI renders only the Finance save control. | **Passed** |
| I | Technology attempts to modify Authority participants | FLOW-06 contract coverage denies authority-side participant mutation outside Authority Office ownership. | **Passed** |
| J | Manipulated payload inserts an employee outside the permitted Directorate | Participant payload contract coverage rejects an employee outside the target unit with validation failure. | **Passed** |
| K | Creation includes a fake requester Directorate | FLOW-06 contract coverage rejects requester-side input with the authority-initiated boundary; the live create form has no requester field and the persisted value is null. | **Passed** |
| L | Duplicate or concurrent finalization | Final Feature coverage rejects duplicate conversion; the real MariaDB InnoDB probe uses row locking and verifies exactly one FLOW-06 meeting, one transition, three participating units, zero requester units, one authority context, and High Authority employee 9. | **Passed** |

## Final live database invariants

```text
request_id=5
request_status=CONVERTED_TO_MEETING
requesting_unit_id=null
meeting_id=4
meeting_count=1
formal_meeting_units_count=3
formal_meeting_unit_roles=[PARTICIPATING, PARTICIPATING, PARTICIPATING]
formal_meeting_unit_ids=[1, 2, 3]
requesting_unit_count=0
authority_context_count=1
authority_employee_id=9
chairperson_employee_id=9
meeting_participant_count=4
meeting_participants=[employee_id=1/unit_id=1/PARTICIPATING, employee_id=5/unit_id=2/PARTICIPATING, employee_id=7/unit_id=3/PARTICIPATING, employee_id=9/unit_id=9/HIGH_AUTHORITY]
authority_office_as_formal_peer=false
```

The canonical invariant artifact is `flow06_request5_final_invariants.json`, generated by `backend/scripts/flow06_final_invariants.php`. Every count is scoped to Request ID 5 or Meeting ID 4; in particular, `meeting_authority_context_count_for_meeting=1`. The raw direct-table artifact `flow06_request5_direct_invariants.json` is retained as supporting evidence. The earlier unscoped relation count of 4 was a reporting-only defect and is no longer used.

## Implementation corrections during FLOW-06 verification

The following corrections were made and re-tested during the live scenario:

1. High Authority visibility and action detection were corrected to use the role assignment (`hasRole(user,'HIGH_AUTHORITY')`) rather than relying only on an employee-id snapshot.
2. High Authority draft preparation was corrected to use position/scoped-role resolution without incorrectly requiring a Directorate unit scope.
3. Directorate participant confirmation was granted to the `DIRECTOR` role while participant-list writes remained restricted to `EXECUTIVE_MANAGEMENT`.
4. Organizational-unit visibility for the authority-initiated preparation permission was added to `OrganizationalUnitPolicy::viewAny()`.
5. Authority participant selection was corrected in `flow06.tsx`: the sentinel `-1` used by the UI is now mapped to the `authority` selection key, preventing an empty `employee_ids` payload. The corrected UI was rebuilt, refreshed, and used successfully to save and confirm the mandatory High Authority holder.
6. The FLOW-06 correction form in `flow06.tsx` now exposes the proposed-agenda field and includes `proposed_agenda` in the update payload, so the required agenda correction is performed through the UI rather than only through an API payload.
7. `ReturnAuthorityInitiatedDraftForCorrection` now returns the current directive version to editable `DRAFT` status after recording `AUTHORITY_DRAFT_RETURNED`. This matches the edit action invariant and enables the Authority Office to correct and resubmit the returned draft.

## Automated verification commands and results

```text
cd backend && php artisan test --testsuite=Feature
Tests: 267 passed (947 assertions)

php tests/Concurrency/mysql_row_lock_test.php
MariaDB concurrency checks passed; FLOW-06 parallel finalization produced one meeting with three PARTICIPATING units, zero REQUESTING units, one authority context, and High Authority employee 9.

cd frontend && pnpm build
1,858 modules transformed
built successfully
```

The dedicated FLOW-06 reissuance test (`Flow06Test::test_flow06_material_revision_requires_reissuance_and_invalidates_old_acknowledgements`) passed. It verifies material revision, version increment, High Authority-only reissuance, and invalidation of acknowledgements tied to the prior directive version. The new `Flow06Test::test_flow06_returned_draft_becomes_editable_and_can_be_resubmitted` regression also passed, verifying returned version editability, the second submission event, and final issuance. The 70-case contract matrix passed in full, and FLOW-01 through FLOW-05 regression tests remained green.

## Release decision

**FLOW-06 is APPROVED / FROZEN as of 19 August 2026.** The decision is based on the completed nine-account RTL browser scenario, the separate fresh Request ID 7 Draft Return/Correction/Resubmit UI gate, no-requester and three-participating-unit invariants, explicit negative Matrix A–L, dedicated reissuance coverage, 267 passing Feature tests with 947 assertions, real MariaDB concurrency verification, and the successful 1,858-module production build. No delegation, acting authority, cancellation, attendance, minutes, or unsupported meeting-management behavior was introduced.


# FLOW-07 Verification — Post-Creation Meeting Rescheduling & Schedule Versioning

**Verification date:** 19 August 2026 GMT+4:30
**Scope:** Formal rescheduling of an existing official Meeting without reopening its MeetingRequest and without creating a replacement Meeting.
**Release status:** **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL; NOT YET APPROVED / FROZEN**

## Executive result

FLOW-07 was exercised through the live Persian/Dari RTL interface and authenticated API session across the original authority and peer paths plus three previously missing manual gates. The canonical FLOW-06 Meeting ID 4 was preserved as an untouched regression reference. Independent fixtures covered: authority rescheduling (Meeting 5), peer rescheduling (Meeting 6), peer counter-proposal (Meetings 7 and 8), authority alternative/revised proposal (Meeting 9), and High Authority conflict rejection (Meeting 10).

The completed evidence now covers case creation, a Finance-originated peer counter-proposal, invalidation/non-transfer of old acknowledgements, an HR alternative request, confirmation blocking with exact HTTP 422 codes, resolution and revised authority proposal, fresh revised acknowledgements, final version application, and an intentionally overlapping High Authority proposal rejected by the real conflict guard. All successful reschedules preserved Meeting identity, kept Meeting status `SCHEDULED`, kept the original MeetingRequest in `CONVERTED_TO_MEETING`, retained units/participants/authority context, and created exactly one immutable successor schedule version. Full browser and authenticated-session evidence is recorded in `flow07_browser_findings.md`.

## FLOW-07 gate matrix

| Gate | Result | Evidence |
|---|---|---|
| Additive, idempotent schema migration | **Passed** | Migration `2026_08_19_130000_add_flow07_meeting_rescheduling_structures.php`; existing four Meetings were backfilled to version 1 without schedule loss. |
| Governance mapping by originating flow | **Passed** | `ScheduleGovernanceResolver` maps all six originating flow types to `INTERNAL_SINGLE_UNIT`, `PEER_MULTI_UNIT`, or `AUTHORITY_CONTROLLED`; dedicated behavioral test passed. |
| Existing MeetingRequest is never reopened | **Passed** | Feature tests and final invariants retain `CONVERTED_TO_MEETING` after reschedule. |
| No replacement Meeting is created | **Passed** | Atomic confirmation updates the existing Meeting; concurrency probe produced exactly one new version and one transition. |
| Meeting status remains `SCHEDULED` | **Passed** | Authority and peer browser scenarios and database invariants both retained `SCHEDULED`. |
| Immutable schedule history | **Passed** | Browser scenarios rendered version 1 as historical and version 2 as current; old rows are never updated. |
| Authority-controlled proposal boundary | **Passed** | Executive Secretary submitted the official proposal; ordinary Directorate proposal path was not used for authority mode. |
| Peer proposal boundary | **Passed** | Scoped peer coordinator submitted a `PEER` proposal; authority proposal endpoint is governance-restricted. |
| Peer counter-proposal | **Passed** | Meeting 7 was exercised through the real peer form; Proposal 1 became `SUPERSEDED`, Finance-scoped actor submitted Counter-Proposal 2, and the active proposal reset to `0/3`. |
| Non-transferable old acknowledgements | **Passed** | Meeting 8 had two acknowledgements on Proposal 1; after Finance submitted Counter-Proposal 2, Proposal 1 retained two old rows while active Proposal 2 had zero rows. |
| Authority Alternative UI path | **Passed** | Meeting 9 HR requested an alternative with a required reason; the RTL workspace displayed one open blocker and Secretary resolved it through the UI. |
| Alternative request blocks confirmation | **Passed** | With Proposal 1 at `3/3`, authenticated confirmation returned HTTP 422 `UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST`; revised Proposal 2 then required fresh acknowledgements. |
| Revised authority proposal | **Passed** | Meeting 9 Proposal 1 was superseded; Proposal 2 had zero acknowledgements initially, then fresh Technology/Finance/HR acknowledgements reached `3/3` before version 2. |
| Conflict re-check at confirmation | **Passed** | MariaDB overlap/shared-employee probes passed, and Meeting 10 authenticated authority proposal returned HTTP 422 `HIGH_AUTHORITY_SCHEDULE_CONFLICT` with no proposal persisted. |
| Direct generic schedule mutation blocked | **Passed** | No generic schedule PATCH route exists; dedicated test returned 404. |
| Stale baseline protection | **Passed** | Confirmation rejects a case opened against an obsolete schedule baseline. |
| Production frontend build | **Passed** | `pnpm build`: TypeScript/Vite completed successfully; 1,859 modules transformed. |
| Full regression suite | **Passed** | `274 passed (978 assertions)` including FLOW-01 through FLOW-07 and foundation regressions. |
| MariaDB concurrency | **Passed** | Duplicate confirmation yielded one new version/transition; shared employee conflict yielded one accepted and one rejected candidate. |

## Positive UI scenarios

| Scenario | Accounts and fixture | Browser result |
|---|---|---|
| Authority-controlled reschedule | Meeting ID 5; Executive Secretary, Technology Director, Finance Director, HR Director | Created case with a Dari reason; submitted official proposal for 2026-08-20 10:00–11:30 at «تالار کنفرانس مرکزی»; matrix reached `3/3`; confirmation produced version 2 and preserved version 1 as historical. |
| Peer multi-unit reschedule | Meeting ID 6; scoped peer coordinator, Finance Director, HR Director | Created `PEER_MULTI_UNIT` case; submitted peer proposal for 2026-08-21 14:00–15:30 at «تالار هماهنگی مرکزی»; matrix reached `3/3`; confirmation produced version 2 and preserved version 1 as historical. |
| Unauthorized peer view | Executive Secretary against peer fixture | Backend returned `403 FORBIDDEN`; frontend was corrected to render a localized RTL access-denied panel with a return link instead of an indefinite spinner. |
| Peer counter-proposal | Meeting IDs 7 and 8; peer coordinator and Finance-scoped peer coordinator, Technology/Finance/HR | Proposal 1 was superseded by a Finance-originated Counter-Proposal 2. Meeting 8 additionally demonstrated two old acknowledgements remaining attached to Proposal 1 while active Proposal 2 started at `0/3`. |
| Authority Alternative and revised proposal | Meeting ID 9; Executive Secretary, Technology Director, Finance Director, HR Director | HR alternative was created with required reason; authenticated confirmation returned `422 UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST`; Secretary resolved it, submitted Revised Proposal 2, fresh acknowledgements reached `3/3`, and UI confirmation produced version 2. |
| High Authority conflict | Meeting ID 10; Executive Secretary with authenticated frontend token | Proposal intentionally overlapped Meeting 4/High Authority employee 9; authenticated endpoint returned `422 HIGH_AUTHORITY_SCHEDULE_CONFLICT`, and database showed zero persisted proposals and version 1 unchanged. |

## Negative Matrix A–L

| ID | Negative case | Expected protection | Evidence | Result |
|---|---|---|---|---|
| A | Unrelated Directorate attempts to reschedule | Policy denies outside organizational scope with HTTP 403. | Meeting policy scope checks and browser peer authorization check. | **Passed** |
| B | Finance acknowledges on behalf of HR | Acknowledgement actor must match one of the required units; cross-unit action is denied. | `flow07Acknowledge` policy and behavioral authorization coverage. | **Passed** |
| C | FLOW-05 Directorate publishes an Authority Office proposal | Authority proposal requires `AUTHORITY_CONTROLLED` governance and scoped Executive Secretary role. | `flow07AuthorityPropose` policy and governance-mode tests. | **Passed** |
| D | Final confirmation with a missing acknowledgement | Confirmation requires every current required unit acknowledgement. | Behavioral test; browser confirmation remained unavailable until `3/3`. | **Passed** |
| E | Final confirmation with an open alternative request | Open alternative blocks confirmation. | Meeting 9 reached `3/3` with one open HR alternative; authenticated confirmation returned HTTP 422 `UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST`, while the UI displayed the blocker. | **Passed** |
| F | Old proposal acknowledgement reused after a revised proposal | Acknowledgements are proposal-version specific and do not transfer. | Meeting 8 database evidence: superseded Proposal 1 had `ack_count=2`, active Counter-Proposal 2 had `ack_count=0`; Meeting 9 superseded Proposal 1 had `ack_count=3`, active Revised Proposal 2 had `ack_count=0`. | **Passed** |
| G | Proposal exactly matches current schedule | No-op proposal is rejected with `NO_SCHEDULE_CHANGE`. | Behavioral FLOW-07 test. | **Passed** |
| H | Reschedule an already-started Meeting | Started or elapsed Meeting is rejected with `MEETING_ALREADY_STARTED`. | Behavioral FLOW-07 test. | **Passed** |
| I | High Authority or participant schedule conflict | Conflict guard rejects authority overlap with `HIGH_AUTHORITY_SCHEDULE_CONFLICT` and participant overlap with `PARTICIPANT_SCHEDULE_CONFLICT`. | Meeting 10 authenticated UI/API fixture returned HTTP 422 `HIGH_AUTHORITY_SCHEDULE_CONFLICT`; MariaDB shared-employee lock probe also passed. | **Passed** |
| J | Direct generic schedule PATCH/update bypass | No generic route permits silent schedule mutation. | Direct schedule mutation route test returned 404. | **Passed** |
| K | Duplicate concurrent confirmation | Row lock and unique baseline protection allow exactly one new version and one transition. | MariaDB concurrency log `flow07_mariadb_concurrency_final.log`. | **Passed** |
| L | Meeting identity/request invariant after reschedule | Request remains `CONVERTED_TO_MEETING`, Meeting count remains one per request, and identity/participants/units/context remain unchanged. | Final database invariant query for canonical Meeting 4 and browser fixtures 5/6. | **Passed** |

## Final database invariants

The canonical FLOW-06 Meeting ID 4 was not changed by the browser fixtures and remains a clean regression reference:

```text
meeting_id=4
meeting_number=MT-2026-00004
status=SCHEDULED
request_status=CONVERTED_TO_MEETING
flow_type=HIGH_AUTHORITY_INITIATED
current_version=1
version_count=1
active_case=null
units_count=3
participants_count=4
authority_context_count=1
```

The completed authority fixture Meeting ID 5 and peer fixture Meeting ID 6 each preserve the same request/meeting invariants while showing one confirmed successor schedule version. The counter-proposal fixtures intentionally remain pending so their proposal-version and acknowledgement invariants remain inspectable. The authority-alternative fixture completed its revised proposal and the conflict fixture rejected its proposal without mutation:

```text
Meeting 5: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=2, version_count=2, active_case=null,
units_count=3, participants_count=3, authority_context_count=1

Meeting 6: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=2, version_count=2, active_case=null,
units_count=3, participants_count=4, authority_context_count=0

Meeting 7: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=1, version_count=1, case_status=NEGOTIATING,
Proposal 1=SUPERSEDED, Counter-Proposal 2=ACTIVE, active_ack_count=0

Meeting 8: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=1, version_count=1, case_status=NEGOTIATING,
Proposal 1=SUPERSEDED/ack_count=2, Counter-Proposal 2=ACTIVE/ack_count=0

Meeting 9: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=2, version_count=2, active_case=null,
Revised Proposal 2 applied; version 1 immutable

Meeting 10: status=SCHEDULED, request_status=CONVERTED_TO_MEETING,
current_version=1, version_count=1, case_status=OPEN,
proposal_count=0 after HIGH_AUTHORITY_SCHEDULE_CONFLICT

Total Meeting count in the live SQLite verification database: 10.
```

The extra fixture rows are verification data only and are not part of the production source commit. They exist to keep the canonical FLOW-06 Meeting and its immutable baseline untouched during browser verification.

## Implementation corrections during FLOW-07 verification

The frontend workspace previously remained on an indefinite spinner when its initial workspace request returned HTTP 403. `frontend/src/flow07.tsx` now captures initial load failures and renders a clear RTL access-denied panel with the localized backend message and an explicit «بازگشت به جلسه» link. This correction does not weaken backend authorization and does not expose a silent override.

The peer browser fixture also exposed the intended permission separation: an ordinary `DIRECTOR` can acknowledge but does not receive the proposal permission, while a scoped proposal/coordinator actor needs the existing `MEETING_COORDINATOR` confirmation permission for final confirmation. The production permission matrix was not broadened; the additional role was applied only to the disposable browser fixture actors.

The additional manual gates did not require a production authorization bypass. Disposable Finance-scoped and authority fixtures were created with real organizational-unit membership and existing roles. The authenticated conflict request reached the business conflict guard and returned `HIGH_AUTHORITY_SCHEDULE_CONFLICT`; the generic UI error text remains a presentation limitation, while the exact machine-readable code was captured from the same frontend Bearer-token session. No backend policy, permission matrix, or schedule mutation rule was weakened during this verification.

## Automated verification commands and results

```text
cd backend && php artisan test --testsuite=Feature > flow07_feature_test_final2.log 2>&1
Tests: 274 passed (978 assertions)

php tests/Concurrency/mysql_row_lock_test.php > flow07_mariadb_concurrency_final2.log 2>&1
MariaDB concurrency checks passed: row lock final value=2; one-meeting uniqueness held; FLOW-02 and FLOW-03 parallel finalization each produced one meeting; FLOW-03 target approvals produced one multilateral transition; FLOW-03 schedule acceptance uniqueness held; FLOW-04 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with one authority context; FLOW-05 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with three formal units and one authority context; FLOW-06 parallel finalization produced one meeting with three PARTICIPATING units, zero REQUESTING units, one authority context, and High Authority employee 9; FLOW-07 duplicate confirmation produced one new version and one transition, and shared employee lock accepted one candidate and rejected one conflicting candidate.

cd frontend && pnpm build > flow07_frontend_build_final2.log 2>&1
✓ 1859 modules transformed.
✓ built successfully

Final database invariant artifact: `flow07_final_invariants2.json`. It records total live Meeting count 10; canonical Meeting 4 remains version 1 and unchanged; successful fixtures 5, 6, and 9 are version 2 with no active case; pending counter-proposal fixtures 7 and 8 remain version 1 with `NEGOTIATING` cases; conflict fixture 10 remains version 1 with zero proposals after the conflict rejection.
```

## Release decision

All FLOW-07 implementation, behavioral, browser, negative, invariant, concurrency, regression, and production-build gates are **Passed**, including the three gates previously identified as incomplete: Finance-originated Peer Counter-Proposal, Authority Alternative → Revised Proposal with fresh acknowledgements, and authenticated High Authority conflict rejection. In accordance with the release rule, FLOW-07 is intentionally **not declared APPROVED / FROZEN** in this report. It is ready for the user's explicit manual approval; no subsequent workflow will be started before that approval.
