# FLOW-10A Manual Software Gate Evidence

**Execution date:** 20 August 2026 GMT+4:30
**Environment:** isolated copy of the AMMS SQLite database, Laravel HTTP server at `127.0.0.1:8010`, seeded accounts, real REST routes, and the clearly labeled `FakeFingerprintAdapter` payload contract.
**Raw sanitized transcript:** `flow10a_manual_api_gate.json`
**Runner:** `backend/scripts/flow10a_manual_api_gate.py`

## Result

> `MANUAL_FLOW10A_API_GATE_PASS` — 35 real HTTP/API checks completed.

This is an operator-style API transcript, not a Laravel Feature Test. The fixture was created in a separate SQLite copy so the live application database was not reset or modified. Authentication used the real `/api/login` endpoint, human calls used Sanctum bearer tokens, terminal calls used real terminal credentials/session tokens, and all responses were recorded with secrets and template bytes redacted.

| Manual scenario | Observed result |
|---|---|
| Unauthorized human biometric metadata | HTTP 403, `FORBIDDEN`. |
| Scoped human metadata | HTTP 200; status initially `NOT_ENROLLED`; no `template_data`. |
| Start enrollment | HTTP 200; two-finger `PENDING_CAPTURE` session returned. |
| Terminal identity/current enrollment | HTTP 200; terminal credential accepted and current task returned with the full finger-position list. |
| Raw image during enrollment | HTTP 422, `RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED`. |
| Right and left enrollment | Both HTTP 200; `RIGHT_INDEX` V1 and `LEFT_INDEX` V1 created with checksums. |
| Complete two-finger enrollment | HTTP 200; status `COMPLETED`, active finger count `2`. |
| Explicit re-enrollment | HTTP 200 with `replace_active=true`; `RIGHT_INDEX` V1 became `SUPERSEDED` and V2 became `ACTIVE`. |
| Ordinary FLOW-09 manifest | HTTP 200; manifest contained participant/schedule metadata but no biometric template bytes. |
| Terminal candidate set | HTTP 200; `total_participants=2`, `biometric_ready=1`, and candidates contained redacted `<bytes-present>` markers only in the terminal response. |
| Valid fingerprint attendance | HTTP 201; `attendance_method=FINGERPRINT`. |
| Duplicate employee scan | HTTP 409, `ATTENDANCE_ALREADY_RECORDED`. |
| Unknown template UUID | HTTP 422, `BIOMETRIC_TEMPLATE_STALE`. |
| Raw attendance probe/image | HTTP 422, `RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED`. |
| Session close | HTTP 200; session status `CLOSED`. |
| Authorized manual override | HTTP 201; `attendance_method=MANUAL_OVERRIDE`, mandatory reason stored. |
| FLOW-07 stale candidate access | After a real schedule mutation and terminal-assignment refresh, HTTP 422, `TERMINAL_SESSION_STALE`, with `schedule_changed=true`. |
| FLOW-08 stale candidate access | After a real participant-version mutation, HTTP 422, `TERMINAL_SESSION_STALE`, with `participants_changed=true`. |

## Manual findings and implementation corrections

The manual run exposed two genuine HTTP defects that were not covered by the earlier Feature suite. The terminal current-enrollment endpoint called a nonexistent `BiometricFingerPosition::all()` method; it now returns the defined `BiometricFingerPosition::ALL` constant. The human completion route authorized `enroll` while the registered Gate was `biometric.enroll`; it now uses the registered Gate. The terminal enrollment request now exposes the already-implemented explicit `replace_active` flag, allowing safe, deliberate re-enrollment instead of silently replacing an active template.

The manual run also confirmed that terminal activation is throttled. The transcript respects the production throttle by waiting between activations rather than disabling or bypassing the route middleware. Assignment refresh after FLOW-07 schedule mutation was performed through the real human API using the governed meeting coordinator account.

## Privacy and boundary observations

Human biometric metadata responses never exposed `template_data`. The ordinary FLOW-09 manifest never exposed biometric bytes. Only the session-authenticated terminal candidate endpoint returned template bytes, and the saved transcript redacts those bytes. No fingerprint image, raw sensor frame, or probe template was stored in the evidence artifact.

FLOW-10B remains outside this evidence: no physical SVT3000 capture, DERMALOG SDK, vendor extraction, or hardware matching is claimed. FLOW-11 was not started.
