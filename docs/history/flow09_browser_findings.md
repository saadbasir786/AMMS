# FLOW-09 Browser Findings

## 2026-08-19 — Initial Registry Gate

The SYSTEM_ADMIN account reached the new RTL `/admin/terminals` page through the real sidebar navigation. The page rendered Persian labels, the Terminal Registry table, and the ثبت ترمینال action. The first live request exposed a backend contract error: `TerminalController::index()` declared `JsonResponse` but returned Laravel's `AnonymousResourceCollection`. No terminal data was leaked and the page remained rendered with an error panel. The fix is to remove the overly narrow return type (or return a concrete JSON response) before repeating the browser gate.

## Registry correction verified

After removing the incorrect `JsonResponse` return type and restarting Laravel, a fresh navigation to `/admin/terminals` rendered the registry page successfully. The Persian/Dari navigation exposes **ترمینال‌ها**, and the page now shows the empty Terminal Registry table and **ثبت ترمینال** action without the previous backend error.

## Registry form gate

The real RTL registry form opened successfully. It exposes organization, code, name, serial, manufacturer, model, platform, OS version, and status fields. The organization selector returned the seeded organization options, and the Prompt 09 metadata defaults (DERMALOG, SVT3000 SE, Android, 7.1.2) were visible and editable.

## Register Terminal browser gate

Using the real SYSTEM_ADMIN RTL UI, terminal `SVT3000-01` was registered with name `ترمینال بایومتریک تالار مقام`, serial `SVT3000-SERIAL-01`, manufacturer `DERMALOG`, model `SVT3000 SE`, platform `Android`, OS `7.1.2`, and status `ACTIVE`. The registry now shows the row with status فعال, no last-seen value before first heartbeat, no active session, and separate detail/credential actions.

## Credential and terminal API gate

The registry issued a credential through the real UI and displayed a one-time opaque token with the explicit Persian warning that it is shown only once and only its hash is stored server-side. Using that token in the authenticated terminal API returned HTTP 200 from `GET /api/terminal/v1/me` with only terminal identity and generic device metadata: terminal UUID, code, name, manufacturer, model, platform, OS version, app version, and status. No user account, email, phone, password, role, or biometric data was returned. The raw token is intentionally not recorded in this evidence file.

## Meeting Terminal Workspace gate

After the policy correction, SYSTEM_ADMIN could view `/meetings/18/terminals` in the real RTL UI. The workspace displayed the Meeting title and status `SCHEDULED`, current Schedule Version `22`, current Participant Version `24`, zero assignments, an ACTIVE terminal selector, explicit valid-from/valid-until fields, and the assignment table. This confirms infrastructure visibility without granting SYSTEM_ADMIN automatic assignment ownership; the assignment action remains governed by the Meeting coordinator/scheduling actor.

## Assignment actor isolation

The real browser session was switched from SYSTEM_ADMIN to `tech.exec@example.test`. The sidebar correctly changed to the scoped Technology executive view and no infrastructure registry navigation was exposed to that user. This confirms the human UI preserves the existing role/scope shell while assignment governance is enforced separately by the backend.

## Assignment workspace data gate

With the authorized Technology executive actor, Meeting 18 workspace loaded successfully. The authenticated meeting API reported official interval `2026-10-14 11:00–12:00`, current Schedule Version `22`, and current Participant Version `24`. The UI showed the same version pointers and one ACTIVE terminal option, with explicit assignment window fields.

## Browser interaction note

The native terminal dropdown returned an encoding error in the browser automation layer because of the Persian option text. The backend/API and rendered UI remained healthy; the same select is being controlled through its DOM value for the evidence run, without changing application code or bypassing the UI state.

## Assignment window entry

In the authorized Technology workspace, `SVT3000-01` was selected and the explicit authorization window was entered as `2026-08-19 00:00` through `2026-10-15 13:00`, covering the official Meeting 18 interval `2026-10-14 11:00–12:00` plus explicit pre/post time. The workspace continued to show Schedule V22 and Participant V24 as the assignment baseline candidates.

## Device activation and manifest gate

The real assignment row was created with status `ASSIGNED`, binding Schedule V22 and Participants V24. Using the one-time terminal credential, the device-facing activation endpoint returned HTTP 201 and created a session UUID. The session manifest returned HTTP 200 with Schedule V22, Participant V24, three participants, and a checksum field; serialized manifest content contained neither `email` nor `phone`. The same session was then closed through the device API with HTTP 200. Session token and raw credential values were not written to the evidence file.

## Final strict manual gate: FLOW-07 schedule stale → refresh

A fresh peer fixture `FLOW09-STRICT-PEER-20` was created with the exact Technology Executive coordinator domain and Terminal B `SVT3000-02`. Through authenticated browser API calls using the official FLOW-07 sequence, the coordinator created a reschedule case, proposed `2026-11-02 15:00–16:00`, all three formal Directorate Directors acknowledged, and the coordinator confirmed the new schedule. The assignment had been bound to Schedule V24 / Participant V26 and became effectively `STALE` after Schedule V25 was applied.

The terminal credential attempted activation before refresh and returned HTTP 422 with code `TERMINAL_ASSIGNMENT_STALE`, `schedule_changed=true`, and `participants_changed=false`. The coordinator then used the explicit human refresh endpoint; it returned HTTP 200 and rebound the assignment to Schedule V25 / Participant V26. A subsequent activation returned HTTP 201 and created a session bound to Schedule V25 / Participant V26. This proves no hidden schedule refresh occurs and that activation succeeds only after explicit refresh.

## Final strict manual gate: FLOW-08 participant stale → refresh

Using the official FLOW-08 browser API sequence on Meeting 18, the Technology Executive created and submitted a participant-change case, added optional employee 12 `هماهنگ‌کننده peer`, and the exact Technology Director confirmed it. The live participant projection advanced from P24 to P27. The terminal assignment remained bound to P24 and activation returned HTTP 422 `TERMINAL_ASSIGNMENT_STALE` with `participants_changed=true` and `schedule_changed=false`. Explicit assignment refresh returned HTTP 200 and rebound the assignment to P27. A separate terminal fixture will be used for the final post-refresh activation so a previous active session cannot contaminate this participant-version evidence.

## Final strict manual gate: participant stale completed

A separate Terminal D assignment was bound to Meeting 18 at Schedule V22 / Participant V27. The Technology Executive removed optional employee 12 through the FLOW-08 API, submitted the case, and the exact Technology Director confirmed it, producing Participant V28. Terminal D activation before refresh returned HTTP 422 `TERMINAL_ASSIGNMENT_STALE` with `participants_changed=true` and `schedule_changed=false`. Explicit refresh returned HTTP 200 and rebound the assignment to V22 / P28; activation then returned HTTP 201 with a session bound to V22 / P28.

## Final strict manual gates: conflict, terminal isolation, revocation

The browser/API gate attempted a second assignment for Terminal B over its existing Meeting 20 authorization window. The endpoint returned HTTP 422 `TERMINAL_ASSIGNMENT_CONFLICT` with safe conflict metadata and no restricted Meeting details. The Terminal A credential then attempted Terminal B's assignment UUID; the device API returned HTTP 404 `TERMINAL_ASSIGNMENT_NOT_FOUND`, while `/assignments/current` returned only Terminal A's own assignment UUID.

For revocation, a fresh Terminal C assignment was activated with HTTP 201 and a session UUID. SYSTEM_ADMIN revoked Terminal C with a required reason and HTTP 200. After the transaction, Terminal C's `/me` credential returned HTTP 401, the previously active session manifest returned HTTP 401, and the admin detail API showed `status=REVOKED` and `current_session_id=null`.

# Final Strict Reviewer Closure — Negative Matrix A–L

| ID | Negative case | Evidence | Result |
|---|---|---|---|
| A | Unauthorized user attempts terminal registration | `Flow09Test::test_unauthorized_human_cannot_register_terminal`; registry policy denies the non-infrastructure actor. | **Passed** |
| B | Unauthorized user attempts Meeting assignment | `Flow09Test` governance/policy coverage and the strict coordinator-scope browser/API gate; assignment is permitted only to the Meeting governance actor. | **Passed** |
| C | Terminal and Meeting belong to different organizations | `Flow09Test::test_cross_organization_and_unavailable_terminal_are_rejected`; returns `TERMINAL_OUTSIDE_MEETING_ORGANIZATION`. | **Passed** |
| D | MAINTENANCE/INACTIVE/REVOKED terminal receives assignment | `Flow09Test::test_cross_organization_and_unavailable_terminal_are_rejected`; unavailable status is rejected with `TERMINAL_NOT_AVAILABLE`. | **Passed** |
| E | Overlapping assignment for the same terminal | Browser/API Meeting 19 attempt over Terminal B's Meeting 20 window returned HTTP 422 `TERMINAL_ASSIGNMENT_CONFLICT` with safe window metadata. | **Passed** |
| F | Schedule V1-bound assignment activates after FLOW-07 V2 | Meeting 20 manual gate returned HTTP 422 `TERMINAL_ASSIGNMENT_STALE`, `schedule_changed=true`; explicit refresh to V25 preceded successful HTTP 201 activation. | **Passed** |
| G | Participant V1-bound assignment activates after FLOW-08 V2 | Meeting 18 manual gate returned HTTP 422 `TERMINAL_ASSIGNMENT_STALE`, `participants_changed=true`; explicit refresh to P28 preceded successful HTTP 201 activation. | **Passed** |
| H | Terminal A credential accesses Terminal B assignment | Browser/API Terminal A credential received HTTP 404 `TERMINAL_ASSIGNMENT_NOT_FOUND`; `/assignments/current` returned only Terminal A's own assignment. | **Passed** |
| I | Revoked credential/session is used after terminal revocation | Terminal C active session was revoked by SYSTEM_ADMIN; subsequent `/me` and manifest calls both returned HTTP 401. | **Passed** |
| J | Two simultaneous activation calls for one assignment | Real MariaDB FLOW-09 probe and `Flow09Test::test_concurrent_style_second_activation_is_rejected_and_session_token_cannot_be_reused` produced one ACTIVE session and one `TERMINAL_SESSION_ALREADY_ACTIVE`. | **Passed** |
| K | Manifest exposes unnecessary/private data | Browser/API manifest contained V22/P24 and participants but no email or phone; automated privacy assertions also cover password/RBAC/biometric absence. | **Passed** |
| L | Final Meeting invariants are silently changed | Final invariant artifact and regression assert Meeting remains `SCHEDULED`, request remains `CONVERTED_TO_MEETING`, no duplicate Meeting-per-request identity exists, and no hidden refresh or replacement Meeting occurs. | **Passed** |

## MariaDB version-race closure

`flow09_mariadb_version_race_probe.php` was executed against real MariaDB/InnoDB with both operation orderings. FLOW-07 schedule V1→V2 versus terminal activation produced either activation rejection after reschedule or activation before reschedule followed by session staleness; no active session remained unknowingly bound to obsolete schedule data. FLOW-08 participant V1→V2 versus activation produced the same two safe outcomes, with no active session surviving against an obsolete participant version.
