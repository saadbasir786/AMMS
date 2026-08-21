# FLOW-05 UI manual evidence

- Login with `tech.exec@example.test` succeeded.
- RTL route `/meeting-requests/create-multi-high-authority` loaded successfully.
- Form displayed requester Directorate, participating Directorate matrix, justification, requested outcome, proposed schedule, and requester participant picker.
- Tech Directorate was selected as requester and Finance as participating Directorate.
- Request subject: جلسه هماهنگی تحول دیجیتال با مقام عالی.
- The UI created request ID 2 and navigated to `/meeting-requests/2`.
- Request status after UI submit: `PENDING_REQUESTER_APPROVAL`.
- Detail view showed one requester unit, one participating unit, authority context with position `رئیس عمومی`, and timeline actions `CREATED` and `SUBMITTED_FOR_REQUESTER_APPROVAL`.
- The UI is Persian/Dari and RTL; next manual step is login as Tech Director for requester approval.


## Requester approval evidence

With `tech.director@example.test`, the detail view displayed the requester approval controls only for the scoped Technology Directorate. Clicking «تأیید requester» succeeded. The request transitioned to `SUBMITTED_TO_PARTICIPATING_DIRECTORATES`, and the immutable timeline recorded `REQUESTER_APPROVED` followed by `REQUEST_SENT_TO_PARTICIPATING_DIRECTORATES`. The detail view continued to show the separate Finance participating Directorate and the authority context card.


## Participating Directorate hand-off

After requester approval, the status displayed as `ارسال‌شده به ریاست‌های اشتراک‌کننده` and the action panel identified Finance as the participating Directorate. A finance executive login succeeded. Direct navigation to `/meeting-requests/2` remained on a loading spinner, so the next diagnostic step is to inspect the authenticated detail request and Laravel log for a possible FLOW-05 resource/eager-load issue.


## Scoped participating-unit visibility fix

The initial finance detail request returned HTTP 403 because the shared `view` policy checked `authority-request.view` before evaluating FLOW-05 formal-unit membership. The policy was corrected so scoped requester/participating Directorate users are evaluated first; authority-office visibility remains protected by the authority permission. After refresh, the finance executive saw the Finance-specific «آغاز بررسی این ریاست» action. Clicking it succeeded and produced status `UNDER_PARTICIPATING_REVIEW` with timeline action `PARTICIPATING_UNIT_REVIEW_STARTED`.


## Participating Directorate approval evidence

With `finance.director@example.test`, the detail page displayed only the Finance-specific approval controls. The director approved the participating unit successfully. The status changed to `DIRECTORATES_APPROVED`; the matrix showed Finance as `APPROVED`, and the immutable timeline recorded `PARTICIPATING_UNIT_APPROVED` followed by `DIRECTORATES_APPROVED`.


## Authority Office review and forward evidence

The Authority Office inbox listed the FLOW-05 request as `REQUEST_SENT_TO_AUTHORITY_OFFICE`. With `authority.office@example.test`, the page exposed «آغاز بررسی دفتر مقام» and changed the authority-office state to `UNDER_REVIEW`. The same role then exposed «ارسال به مقام عالی» and successfully forwarded the request. The page changed to «منتظر تصمیم مقام عالی», recorded `FORWARDED_TO_HIGH_AUTHORITY`, marked the office review `COMPLETED`, and displayed a resolved holder snapshot (`عبدالقیوم احمدزی`) from the authority position rather than a hard-coded workflow peer.


## High Authority decision and scheduling evidence

With `high.authority@example.test`, the detail page exposed only the High Authority decision controls after the office forward. Clicking «تأیید مقام عالی» succeeded, recorded `HIGH_AUTHORITY_APPROVED`, and showed the authority decision as `APPROVED`. The Authority Office/Executive Secretary account then exposed «آغاز زمان‌بندی رسمی»; clicking it recorded `AUTHORITY_SCHEDULING` and `AUTHORITY_SCHEDULING_STARTED`. In the dedicated scheduling workspace, the secretary entered ISO date `2026-08-25`, 10:00–11:00, and «تالار جلسات مقام». The official proposal was created with status `PROPOSED`, and the matrix showed both formal Directorates awaiting acknowledgement. The Finance executive clicked «تأیید این ریاست»; the backend created one per-unit row for organizational unit 2. A subsequent API/UI diagnostic found that `MeetingRequestController::show()` had not eager-loaded `authorityScheduleUnitAcknowledgements`; it was fixed using the model's actual `unit` relation. After refresh, the UI correctly displayed Finance as «تأیید شده» and the final-control counter as `acknowledgements 1/2`, while Technology remained pending. The Technology executive then clicked the Technology-specific «تأیید این ریاست» control. The matrix displayed both Directorates as «تأیید شده», and the final-control counter reached `acknowledgements 2/2`. An Authority Office account attempted «تأیید نهایی زمان‌بندی» and received the expected `این عملیات برای شما مجاز نیست` response; final schedule confirmation is correctly reserved for the Executive Secretary role. After switching to `executive.secretary@example.test`, the official proposal was confirmed successfully: status became `ACCEPTED`, the request became `SCHEDULE_CONFIRMED`, the 2/2 acknowledgement prerequisite remained visible, and the finalization panel appeared only after schedule confirmation. Tech Executive then managed only the Technology Directorate participant panel, selected Ahmed Ahmadi (Technology Directorate) and Fatima Rahimi (Technology executive), and used that panel's «ذخیرهٔ فهرست» and «تأیید فهرست» controls. The Finance and Authority-side panels were not writable under the Tech account. Finance Executive then selected Zalmay Hakimi and Samira Sadeqi in the Finance-only panel, saved the list, and confirmed it. Database verification showed four FINAL nominations total: employees 1–2 owned by unit 1 and employees 5–6 owned by unit 2, plus two confirmed unit records for organizational units 1 and 2. Authority Office then managed only the authority-side panel, selected Jamal Sediqi (Office) and Abdulqayum Ahmadi (the resolved High Authority holder), saved the list, and confirmed it. Database verification showed two authority nominations with `participant_stage=FINAL` owned by unit 9 and one authority participant confirmation record.


## Canonical three-Directorate / nine-account scenario — Request ID 3

The canonical FLOW-05 scenario was completed through the live Persian/Dari RTL UI with all nine required accounts: `tech.exec@example.test`, `tech.director@example.test`, `finance.exec@example.test`, `finance.director@example.test`, `hr.exec@example.test`, `hr.director@example.test`, `authority.office@example.test`, `executive.secretary@example.test`, and `high.authority@example.test`. Technology was the requester; Finance and HR were independent participating Directorates; the High Authority was represented through `authority_context`, not as a peer Directorate.

Technology Executive created and submitted Request ID 3. Technology Director approved the requester side. Finance Executive and HR Executive each started their own review. HR Director returned the request with a reason, Technology Executive made a non-material correction, and the request was successfully resubmitted. Finance and HR review rows reset independently to `PENDING_REVIEW`; the frontend guarded the resubmit call with `resubmitEligible`. Finance Director and HR Director then approved their respective units. Authority Office reviewed and forwarded, High Authority approved, and Executive Secretary started official scheduling. Finance, HR, and Technology acknowledged the official proposal independently; the UI counter reached `3/3`. Executive Secretary confirmed the schedule. Technology, Finance, and HR each managed only their own participant panel. Authority Office managed the authority-side panel and included the position-resolved High Authority holder. Executive Secretary finalized the request.

The final database invariant log confirms Request ID 3 converted to Meeting ID 3 exactly once, with three formal units (`REQUESTING` Technology and `PARTICIPATING` Finance/HR), one authority context, chairperson employee id 9, eight participants, and confirmed participant rows for unit ids 1, 2, and 3.

## Request ID 4 — explicit negative return/resubmit UI evidence

With `hr.exec@example.test`, direct navigation to `/meeting-requests/4` displayed the HR-specific «آغاز بررسی این ریاست» control. Clicking it changed the HR row from `PENDING_REVIEW` to `UNDER_REVIEW` and recorded `PARTICIPATING_UNIT_REVIEW_STARTED`.

After switching to `hr.director@example.test`, the detail page displayed the HR-specific approval, return, and reject controls. The Persian reason «لطفاً متن شرح جلسه را با مسئولیت‌های اجرایی منابع بشری تکمیل کنید.» was entered and «برگشت» was clicked. The request displayed `نیازمند اصلاح از سوی ریاست اشتراک‌کننده`, the HR row became `RETURNED_FOR_CLARIFICATION`, and the timeline recorded `PARTICIPATING_UNIT_RETURNED` with the reason.

After switching to `tech.exec@example.test`, the edit form allowed a description-only change while the subject, participating units, and other material fields remained unchanged. Clicking «ذخیره و ارسال مجدد» succeeded. The UI returned to `در حال بررسی ریاست‌های اشتراک‌کننده`, the timeline recorded `UPDATED` and `PARTICIPATING_UNIT_RESUBMITTED`, and both Finance and HR rows were reset to `PENDING_REVIEW`. No unauthorized resubmit request was issued after this non-material correction.

## Negative UI Matrix evidence summary

The live browser explicitly verified that Authority Office cannot confirm the official schedule (`این عملیات برای شما مجاز نیست`) and that Finance Executive cannot save Technology's participant list (`This action is unauthorized`). The automated FLOW-05 behavioral, policy, contract, and MariaDB concurrency coverage records the remaining matrix cases: cross-scope directorate access, Office/High Authority ordering, pre-approval scheduling, single-Directorate rejection, duplicate-finalization uniqueness, per-unit review/approval ownership, pending acknowledgement blocking, non-participating visibility denial, and inactive-user privileged-action denial. These references are consolidated as the A–L table in `VERIFICATION.md`.
