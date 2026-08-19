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
