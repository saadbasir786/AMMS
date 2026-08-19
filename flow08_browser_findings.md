# FLOW-08 Browser Findings

## Authority-side UI — Meeting 4

The sandbox browser was authenticated as `executive.secretary@example.test` and opened `/meetings/4/participants/change`. The RTL workspace displayed Meeting 4, schedule version 1, current participant version 1, four current participants, and a visible create-case form. A case was created through the real UI with reason `به دلیل مأموریت رسمی نماینده قبلی، نماینده جدید دفتر مقام معرفی می‌گردد.`.

The Authority-scoped employee options were limited to the authority employee and Authority Office employees: High Authority, the office reviewer, and the executive secretary. An attempted UI REMOVE of High Authority returned HTTP 422 with machine-readable code `HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE`; the UI displayed the localized generic permission panel while the authenticated response preserved the exact code. A positive ADD of Authority Office employee `مریم نورستانی` was submitted and confirmed through the same UI/session. The workspace then displayed participant version 2, five current participants, immutable history versions 1 and 2, and timeline events `PARTICIPANT_CHANGE_CASE_CREATED`, `PARTICIPANT_CHANGE_UPDATED`, `PARTICIPANT_CHANGE_SUBMITTED`, and `MEETING_PARTICIPANTS_CHANGED`.

## Peer-side UI — Meeting 11

An independent fixture `FLOW08-PEER-BROWSER` was created with three formal units and participant version 1. The browser first authenticated as `tech.exec@example.test`, created a Peer-owned case, and displayed only the scoped Technology-domain employee options. The chairperson was adjusted to Finance Director so that Technology could replace its own participant without violating the chairperson invariant.

Through the real RTL UI, Technology management selected `REPLACE`, old participant `احمد احمدی`, and new participant `فاطمه رحیمی`, then submitted the case. The workspace showed `PENDING_CONFIRMATION`, kept the current projection unchanged until confirmation, and displayed the submission event. The browser then authenticated as `finance.director@example.test`; the Director confirmation button was visible. Confirmation succeeded after the governance fix allowing any formal-unit Director to confirm a Peer case owned by another formal unit. The workspace displayed participant version 2 with `فاطمه رحیمی` replacing `احمد احمدی`, version history 1/2, no active case, and `MEETING_PARTICIPANTS_CHANGED` in the timeline.

## Browser-discovered fixes

The first Authority submit attempt exposed that final conflict validation was incorrectly checking all existing participants, including baseline participants whose existing schedule was already accepted. The validator was corrected to re-check only ADD/REPLACE candidate employee IDs, as required by FLOW-08. The first Peer confirmation attempt exposed that confirmation was incorrectly restricted to the case owner unit; governance was corrected so any Director in a formal Peer unit may confirm while preparation remains owner-domain restricted.

## Manual participant conflict — Meetings 12 and 13

A dedicated fixture created Meeting 12 with schedule `2026-09-18 10:00–11:00` and Meeting 13 with schedule `2026-09-18 10:30–11:30`. Employee X (`حامد کریمی`, employee id 3) was already a participant in Meeting 13. In the real RTL workspace for Meeting 12, authenticated as `tech.exec@example.test`, a participant-change case was created and an ADD item for employee X was entered through the UI. The UI submission displayed the localized authorization panel, while the same frontend Bearer session returned HTTP 422 with exact code `PARTICIPANT_SCHEDULE_CONFLICT`. The current projection and participant version remained unchanged.

This closes the previously missing manual participant-conflict fixture.

## Schedule changed mid-case — Meeting 14

The browser authenticated as `authority.office@example.test` created a participant-change case in the real RTL workspace against schedule version 1 and added an Authority Office participant. The case was submitted and displayed `PENDING_CONFIRMATION` with baseline schedule version 1.

The browser then authenticated as `executive.secretary@example.test` and used the real FLOW-07 reschedule workspace to create an official proposal for `2026-09-19 13:00–14:00` at `تالار جدید`. Real director acknowledgements were collected and the proposal was confirmed; Meeting 14 moved to schedule version 2 while preserving the participant case baseline at version 1.

Back in the FLOW-08 workspace, authenticated as Authority Office, the UI displayed the stale schedule warning and the real action returned the case to `DRAFT` after `بازنگری بر اساس زمان فعلی`. The timeline recorded `PARTICIPANT_CHANGE_BASELINE_REFRESHED` with baseline schedule version 2. The case was resubmitted through the UI and confirmed through the same Authority Office workspace. The final UI displayed schedule version 2, participant version 2, five current participants, no active case, and `MEETING_PARTICIPANTS_CHANGED`.

This closes the previously missing manual schedule-change/refresh gate.

## Corrected Peer ownership — Meeting 15

A new Peer fixture was executed after the governance correction. `tech.exec@example.test` created the Technology-owned participant-change case and submitted a REPLACE for the Technology representative through the RTL workspace. The same case was opened by `finance.director@example.test`; the UI displayed the localized access-denied panel, and the same frontend Bearer session returned HTTP 403 with exact code `UNAUTHORIZED_PARTICIPANT_CONFIRMATION`. No projection mutation occurred.

The session was then switched to `tech.director@example.test`. The Technology Director confirmed the same case through the real UI. Meeting 15 displayed participant version 2, `فاطمه رحیمی` as the current Technology representative, no active case, and `MEETING_PARTICIPANTS_CHANGED` in the immutable timeline.

This closes the corrected Peer owner-unit negative and positive browser gate. The earlier Meeting 11 Finance confirmation evidence is superseded and must not be used as release evidence.

## Authority Office protection and Support replacement — Meeting 16

The browser was authenticated as `authority.office@example.test` on the real RTL workspace for `FLOW08-AUTHORITY-PROTECTION-REPLACE`. The Authority Office created the participant-change case with the reason `به دلیل مأموریت رسمی پشتیبان مقام، ابتدا حفاظت مقام و سپس جایگزینی پشتیبان بررسی میشود.` and selected REMOVE for High Authority employee `عبدالقیوم احمدزی` (employee id 9). The authenticated frontend request returned HTTP 422 with exact machine-readable code `HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE`; the official participant projection remained unchanged.

In the same Authority Office case, the UI then selected REPLACE from `مریم نورستانی` (employee id 10, Authority Office support A) to `جمال صدیقی` (employee id 11, Authority Office support B). The case was submitted and confirmed through the real RTL workspace. The final page displayed participant version 2, preserved five participants, showed version 1 as historical and version 2 as current, replaced Maryam with Jamal, cleared the active case, and recorded `MEETING_PARTICIPANTS_CHANGED`. High Authority remained present and immutable.

## Valid REMOVE positive path — Meeting 18

A fresh fixture `FLOW08-REMOVE-POSITIVE-V2` was created before the browser case with Technology, Finance, and HR as formal units. Technology contained both its required Directorate participant `احمد احمدی` and the optional participant `هماهنگ‌کننده peer` (employee id 12) in the exact Technology unit domain. The browser authenticated as `tech.exec@example.test`, created the case, selected REMOVE for the optional participant, and submitted it through the RTL UI. The UI showed `PENDING_CONFIRMATION` while the current four-participant projection remained unchanged.

The browser then authenticated as `tech.director@example.test`, the Director of the exact owner unit. Confirmation succeeded through the real UI. The final workspace displayed participant version 2 with three participants—Technology Director, Finance Director, and HR Director—while the optional peer coordinator was removed. Version 1 remained immutable history, version 2 became current, the active case was cleared, and the timeline recorded `MEETING_PARTICIPANTS_CHANGED`. Meeting 18 remained `SCHEDULED` and its request remained `CONVERTED_TO_MEETING`.

These two scenarios close the final strict-reviewer evidence gates for FLOW-08. The earlier incomplete Meeting 11 Finance-confirmation evidence remains superseded and is not used as release evidence.
