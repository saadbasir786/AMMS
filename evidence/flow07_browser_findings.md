
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
