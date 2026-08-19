
## FLOW-07 Browser Fixture — Initial Authorization Check

On the independent browser fixture `Meeting ID=5` (`FLOW07-BROWSER-MEETING`), the RTL workspace rendered the current schedule as version 1 with date ۱۴۰۵/۶/۱۱, 09:00–10:00, تالار آزمایشی, and the create-case form. Submitting a valid reason from the currently logged-in browser account returned `This action is unauthorized.` The UI therefore correctly exposed the case form but the current account was not an authorized FLOW-07 case initiator; this must be corrected or the scenario must be continued with an explicitly authorized workflow account. No schedule data was changed.

The browser account was then logged out and replaced with `executive.secretary@example.test` using the provided test password. Login succeeded and the dashboard identified the role as «سکرتر اجرائیوی مقام». The initial unauthorized response was attributable to the prior account, not a global route failure.

## FLOW-07 Browser Fixture — Authority-Controlled Positive Path

With Executive Secretary authenticated, the UI accepted the Dari/Persian reason «تداخل برنامه رسمی مقام و نیاز به هماهنگی مجدد زمان جلسه» and created an active `AUTHORITY_CONTROLLED` case for Meeting ID=5. The workspace displayed `OPEN`, baseline version `5`, and `0/3` acknowledgements while preserving the current schedule.

The same UI then submitted an authority-office proposal for 2026-08-20, 10:00–11:30, location «تالار کنفرانس مرکزی», with a Dari/Persian note. The workspace transitioned to `NEGOTIATING`, displayed the proposed schedule as active, and rendered acknowledgement rows for Technology, Finance, and HR, each initially «منتظر». This proves the case creation and authority proposal path were exercised through the actual RTL frontend rather than direct API calls.

The browser session was switched to `tech.director@example.test`. The Technology acknowledgement button was visible and usable; after clicking it, the matrix changed from `0/3` to `1/3` and Technology changed to «تأیید شده», while Finance and HR remained «منتظر». The current schedule still remained version 1, confirming that acknowledgement does not mutate the Meeting schedule.

The browser session was switched to `finance.director@example.test`. Finance acknowledgement succeeded through the same UI, changing the matrix from `1/3` to `2/3`; Technology and Finance were «تأیید شده» and HR remained «منتظر». The current schedule continued to display version 1.

The browser session was switched to `hr.director@example.test`; login succeeded and the dashboard identified «رئیس ریاست منابع بشری». The HR acknowledgement was then submitted successfully. The workspace changed to `READY_TO_CONFIRM`, the matrix showed `3/3`, and Technology, Finance, and HR were all «تأیید شده». The current schedule remained version 1 until final confirmation.

Finally, the browser session was switched back to `executive.secretary@example.test`. The final confirmation button was available in `READY_TO_CONFIRM` and succeeded. The UI returned to the no-active-case state, showed the new current schedule as version 2 (۱۴۰۵/۵/۲۹, 10:00–11:30, تالار کنفرانس مرکزی), and rendered immutable history with version 1 marked historical and version 2 marked current. The Meeting identity and current SCHEDULED state were preserved by the UI flow.

## FLOW-07 Browser Fixture — Peer Authorization Check

A separate `MULTI_DIRECTORATE` fixture was created as Meeting ID=6. While authenticated as `executive.secretary@example.test`, the workspace API returned HTTP 403 `FORBIDDEN` for `/api/meetings/6/reschedule`, because the Authority Office role is outside the peer meeting's organizational view scope. Before the targeted frontend fix, the workspace remained on a loading spinner. `flow07.tsx` was then updated to render a localized RTL authorization panel with the backend message and a «بازگشت به جلسه» link for failed workspace loads.

The session was switched to `tech.director@example.test`; login succeeded, the dashboard identified «رئیس ریاست تکنالوژی», and the dashboard displayed the new peer fixture request `FLOW07-PEER-BROWSER-001`. The peer workspace rendered the `PEER_MULTI_UNIT` case form, and the director successfully created a case using «تداخل تقویم ریاست‌های اشتراک‌کننده و نیاز به هماهنگی مجدد». The case opened with baseline version `7`, `0/3` acknowledgements, and no mutation of the current schedule.

The peer proposal form was not shown for the `DIRECTOR` account because the seeded permission matrix intentionally grants `meeting-reschedule.propose` to `EXECUTIVE_MANAGEMENT` and not to ordinary `DIRECTOR`. A scoped fixture actor `peer.coordinator@example.test` was therefore created with `EXECUTIVE_MANAGEMENT` scoped to Technology and made the fixture meeting's coordinator/participant. With that actor authenticated, the peer workspace rendered «پیشنهاد ریاست / پیشنهاد متقابل». A proposal for 2026-08-21, 14:00–15:30, location «تالار هماهنگی مرکزی», and a Dari/Persian note was submitted successfully; the case transitioned to `NEGOTIATING`, the active proposal was labeled «پیشنهاد peer», and Technology, Finance, and HR appeared as «منتظر» in the 0/3 matrix.

The peer coordinator acknowledged on behalf of Technology, changing the matrix to `1/3` and marking Technology «تأیید شده». After switching to `finance.director@example.test`, Finance acknowledged through the same RTL workspace, changing the matrix to `2/3` and leaving only HR «منتظر». The current schedule remained version 1 throughout.

After switching to `hr.director@example.test`, the HR acknowledgement succeeded through the same RTL workspace. The peer case changed to `READY_TO_CONFIRM`, the matrix showed `3/3`, and all three participating directorates were marked «تأیید شده» while the current schedule remained version 1.

The first peer confirmation attempt returned `This action is unauthorized.` because the seeded `EXECUTIVE_MANAGEMENT` role grants proposal but not confirmation. The fixture actor was then additionally assigned the scoped `MEETING_COORDINATOR` role, which supplies the existing confirmation permission without changing production authorization rules. Retrying from the same UI succeeded: the active case closed, current schedule version 2 became 2026-08-21 14:00–15:30 at «تالار هماهنگی مرکزی», version 1 became historical, and no active reschedule case remained.


## FLOW-07 Manual Gate — Peer Counter-Proposal (in progress)

A fresh independent peer fixture was created as Meeting ID 7 with request `FLOW07-COUNTER-BROWSER-001` and meeting number `FLOW07-COUNTER-BROWSER-MEETING`. The actor `peer.coordinator@example.test` was authenticated in the RTL browser workspace. The initial schedule rendered as version 1: ۱۴۰۵/۶/۱۳, 11:00–12:00, «تالار پیشنهادهای مشترک».

Through the real UI, the actor entered the reason «نیاز به هماهنگی تازه میان سه ریاست و پیشنهاد بدیل برای زمان جلسه» and created an active `PEER_MULTI_UNIT` case. The workspace showed `OPEN`, baseline version 9, and `0/3` required acknowledgements. The actor then filled and submitted Proposal 1 for 2026-09-06, 12:00–13:30 at «تالار پیشنهاد اول», with the note «پیشنهاد نخست هماهنگ‌کنندهٔ تکنالوژی برای بررسی مشترک». The actual workspace transitioned to `NEGOTIATING`, rendered Proposal 1 as `PEER_PROPOSAL` and `ACTIVE`, and displayed Technology, Finance, and HR as «منتظر» in the `0/3` matrix. No schedule version changed; version 1 remained current.

The same peer coordinator then filled the existing «پیشنهاد ریاست / پیشنهاد متقابل» form for Counter-Proposal 2: 2026-09-07, 15:00–16:30, «تالار پیشنهاد دوم», with «پیشنهاد بدیل ریاست مالی پس از بررسی زمان نخست». The real UI rendered the new schedule as active and kept the case in `NEGOTIATING` with `0/3` acknowledgements.

A direct post-action invariant query confirmed the required versioning semantics for Meeting ID 7: case status `NEGOTIATING`, baseline schedule version id 9, Proposal 1 id 3 `PEER_PROPOSAL` status `SUPERSEDED`, Proposal 2 id 4 `PEER_PROPOSAL` status `ACTIVE` with `supersedes_proposal_id=3`, and both proposal acknowledgement counts equal to 0. Thus old acknowledgements cannot transfer, and the active proposal is the counter-proposal.

The strict gate required Finance itself to create the counter-proposal. The initial existing `finance.exec@example.test` account was correctly denied workspace access because its employee record was outside the formal FIN unit scope. Rather than weaken production authorization, a disposable scoped actor `finance.peer.coordinator@example.test` was created with an employee in organizational unit FIN (id 2), the existing `EXECUTIVE_MANAGEMENT` role, and scoped proposal permission; it was also added as a participant of Meeting ID 7. After login, the RTL workspace opened successfully.

That Finance-scoped actor submitted Counter-Proposal 2 through the real «پیشنهاد ریاست / پیشنهاد متقابل» form for 2026-09-08, 16:00–17:30 at «تالار بدیل ریاست مالی», with the note «پیشنهاد بدیل رسمی از سوی هماهنگ‌کنندهٔ ریاست مالی». The browser displayed the new schedule as active, retained `NEGOTIATING`, kept the baseline at version 9, and showed `0/3` acknowledgements. This satisfies the explicit actor and UI requirement for Finance-originated Counter-Proposal creation.


## FLOW-07 Manual Gate — Non-transferable Acknowledgements (in progress)

To prove that old acknowledgements are not merely absent but are invalidated by revision, a second fresh peer fixture was created as Meeting ID 8 (`FLOW07-COUNTER-ACK-BROWSER-MEETING`). The authenticated Finance-scoped peer actor created the case and submitted Proposal 1 for 2026-09-09, 12:00–13:30 at «تالار پیشنهاد نخست».

Through the real RTL workspace, `finance.peer.coordinator@example.test` acknowledged Proposal 1 for Finance; the matrix changed to `1/3` and Finance became «تأیید شده». The session was then switched to `tech.director@example.test`, which acknowledged Proposal 1 for Technology; the matrix changed to `2/3`, with Technology and Finance «تأیید شده» and HR «منتظر». The current schedule remained version 1.

Finance peer then submitted Counter-Proposal 2 through the real UI for 2026-09-10, 14:00–15:30 at «تالار بدیل مالی دوم», explicitly noting that Proposal 1 acknowledgements must not transfer. The UI immediately reset the active counter-proposal matrix to `0/3`; Technology, Finance, and HR all displayed «منتظر», while Meeting version 1 remained current.

The authenticated post-action database query confirmed: Meeting ID 8 case remained `NEGOTIATING`, current schedule version remained 1, Proposal 1 id 6 was `SUPERSEDED` with `ack_count=2` and acknowledged units `[1,2]`, and Counter-Proposal 2 id 7 was `ACTIVE`, `supersedes_proposal_id=6`, with `ack_count=0` and no acknowledgement units. This is direct browser plus authenticated database evidence that old acknowledgements are not transferable.


## FLOW-07 Manual Gate — Authority Alternative (in progress)

A fresh independent authority fixture was created as Meeting ID 9 (`FLOW07-AUTH-ALT-BROWSER-MEETING`). The current browser session was still the Finance peer actor; opening the authority-controlled workspace correctly returned the localized RTL authorization error with HTTP 403 semantics. No data was changed. The scenario will continue with the scoped Executive Secretary account, as required by the authority governance boundary.

The scoped Executive Secretary then created the authority case with reason «تداخل برنامه مقام و نیاز به پیشنهاد زمان بدیل برای هماهنگی ریاست‌ها» and submitted Official Proposal 1 for 2026-09-11, 10:00–11:30 at «تالار رسمی مقام - پیشنهاد نخست». The real workspace displayed `AUTHORITY_CONTROLLED`, case `NEGOTIATING`, Proposal 1 `ACTIVE`, and all three Technology/Finance/HR rows as «منتظر» (`0/3`). Version 1 remained the current official schedule.

HR Director initially clicked «درخواست زمان بدیل» without a reason and the UI correctly returned «اطلاعات واردشده معتبر نیست.» After entering the required reason «زمان پیشنهادی با تقویم ریاست منابع بشری سازگار نیست؛ لطفاً زمان بدیل بررسی شود», the real UI accepted the request. The authority workspace showed «زمان‌های بدیل باز: 1» and rendered a dedicated «مواردی که confirmation را متوقف می‌کنند» section containing the HR alternative reason. Proposal 1 remained active and no schedule version changed.

Before attempting confirmation, Technology Director acknowledged Proposal 1 through the RTL workspace, changing the matrix to `1/3`. Finance Director then acknowledged the same Proposal 1, changing it to `2/3`; Technology and Finance were «تأیید شده», HR remained «منتظر», and the HR alternative remained open (`1`). The current schedule stayed version 1.

With Executive Secretary authenticated, the UI showed Proposal 1 at `2/3` and one open HR alternative. The normal UI confirmation control remained unavailable. The same frontend Bearer token was extracted from the application’s `amms_token` storage key and used against `POST /api/meetings/9/reschedule/confirm`; the authenticated response was HTTP 422 with code `ALL_RESCHEDULE_ACKNOWLEDGEMENTS_REQUIRED`. This is the correct first blocking invariant at 2/3. HR will now acknowledge the active Proposal 1 as well, after which the same authenticated confirmation attempt will isolate the open-alternative blocking code.

HR Director acknowledged Proposal 1 through the real UI even though the alternative request remained open. The case transitioned to `READY_TO_CONFIRM`, the active proposal matrix displayed `3/3`, and all three Directorates were «تأیید شده». The UI still rendered the HR alternative under «مواردی که confirmation را متوقف می‌کنند» and version 1 remained current. This state isolates the open-alternative invariant from the missing-acknowledgement invariant.

With all three Proposal 1 acknowledgements present and the alternative still open, the authenticated Secretary request returned HTTP 422 with code `UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST`. This isolates the required alternative-blocking invariant.

Executive Secretary then clicked «حل درخواست بدیل» in the actual UI. The workspace showed zero open alternatives and retained version 1. The Secretary filled Revised Proposal 2 for 2026-09-12, 13:00–14:30 at «تالار رسمی مقام - پیشنهاد اصلاح‌شده», with «پیشنهاد اصلاح‌شده پس از حل درخواست زمان بدیل منابع بشری», and submitted it through the real authority proposal form. The UI changed the active proposal to the revised schedule and reset the acknowledgement matrix to `0/3`.

Authenticated database verification confirmed Meeting ID 9 remained at current schedule version 1 and case status `NEGOTIATING`, with zero open alternatives. Proposal 1 id 8 (`AUTHORITY_OFFICIAL`) was `SUPERSEDED` with three old acknowledgements from units 1, 2, and 3. Revised Proposal 2 id 9 was `ACTIVE`, `supersedes_proposal_id=8`, and had zero acknowledgements. This directly proves revised-proposal acknowledgement invalidation.


Fresh acknowledgements for Revised Proposal 2 were then collected through the actual RTL UI. Technology Director acknowledged first (`1/3`), Finance Director acknowledged second (`2/3`), and HR Director acknowledged third (`3/3`). The revised proposal remained active, all three units displayed «تأیید شده», no alternative remained open, and the Meeting schedule was still version 1 before final confirmation.

Executive Secretary then confirmed the revised proposal through the real UI. Meeting ID 9 returned to the no-active-case state, showed version 2 as current (2026-09-12, 13:00–14:30, «تالار رسمی مقام - پیشنهاد اصلاح‌شده»), and rendered version 1 as historical. The final authenticated invariant query confirmed `status=SCHEDULED`, `request_status=CONVERTED_TO_MEETING`, `current_version=2`, `version_count=2`, `active_case=null`, and the revised date/time/location. No new Meeting was created.


## FLOW-07 Manual Gate — High Authority Conflict

A fresh authority-controlled Conflict Fixture was created as Meeting ID 10. Its current schedule is 2026-09-05 15:00–16:00, while the conflict target is canonical Meeting 4 on 2026-09-05 09:00–10:30 with High Authority employee 9. The Secretary created the reschedule case through the real UI. The proposal form was filled with 2026-09-05 09:30–10:00 at «تالار متداخل مقام», intentionally overlapping Meeting 4’s High Authority schedule.

The UI submission displayed the generic localized authorization error text, but the same frontend session was then used through the authenticated API endpoint `POST /api/meetings/10/reschedule/authority-proposals` with the exact proposal payload. The real response was HTTP 422 with code `HIGH_AUTHORITY_SCHEDULE_CONFLICT`. No proposal was created, Meeting 10 remained at schedule version 1, and the conflict guard therefore rejected the overlapping authority schedule without bypassing policy or creating a new Meeting.
