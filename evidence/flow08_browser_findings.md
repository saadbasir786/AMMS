# FLOW-08 Browser Findings

## Authority-side UI — Meeting 4

The sandbox browser was authenticated as `executive.secretary@example.test` and opened `/meetings/4/participants/change`. The RTL workspace displayed Meeting 4, schedule version 1, current participant version 1, four current participants, and a visible create-case form. A case was created through the real UI with reason `به دلیل مأموریت رسمی نماینده قبلی، نماینده جدید دفتر مقام معرفی می‌گردد.`.

The Authority-scoped employee options were limited to the authority employee and Authority Office employees: High Authority, the office reviewer, and the executive secretary. An attempted UI REMOVE of High Authority returned HTTP 422 with machine-readable code `HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE`; the UI displayed the localized generic permission panel while the authenticated response preserved the exact code. A positive ADD of Authority Office employee `مریم نورستانی` was submitted and confirmed through the same UI/session. The workspace then displayed participant version 2, five current participants, immutable history versions 1 and 2, and timeline events `PARTICIPANT_CHANGE_CASE_CREATED`, `PARTICIPANT_CHANGE_UPDATED`, `PARTICIPANT_CHANGE_SUBMITTED`, and `MEETING_PARTICIPANTS_CHANGED`.

## Peer-side UI — Meeting 11

An independent fixture `FLOW08-PEER-BROWSER` was created with three formal units and participant version 1. The browser first authenticated as `tech.exec@example.test`, created a Peer-owned case, and displayed only the scoped Technology-domain employee options. The chairperson was adjusted to Finance Director so that Technology could replace its own participant without violating the chairperson invariant.

Through the real RTL UI, Technology management selected `REPLACE`, old participant `احمد احمدی`, and new participant `فاطمه رحیمی`, then submitted the case. The workspace showed `PENDING_CONFIRMATION`, kept the current projection unchanged until confirmation, and displayed the submission event. The browser then authenticated as `finance.director@example.test`; the Director confirmation button was visible. Confirmation succeeded after the governance fix allowing any formal-unit Director to confirm a Peer case owned by another formal unit. The workspace displayed participant version 2 with `فاطمه رحیمی` replacing `احمد احمدی`, version history 1/2, no active case, and `MEETING_PARTICIPANTS_CHANGED` in the timeline.

## Browser-discovered fixes

The first Authority submit attempt exposed that final conflict validation was incorrectly checking all existing participants, including baseline participants whose existing schedule was already accepted. The validator was corrected to re-check only ADD/REPLACE candidate employee IDs, as required by FLOW-08. The first Peer confirmation attempt exposed that confirmation was incorrectly restricted to the case owner unit; governance was corrected so any Director in a formal Peer unit may confirm while preparation remains owner-domain restricted.
