# AMMS Terminal Android — FLOW-10A Foundation

This module is the independent terminal-client foundation required by FLOW-10A. It is designed for the SVT3000 Android terminal boundary but is simulator-first and does not claim physical DERMALOG integration.

## Runtime modes

The client has two explicit modes. **Enrollment Mode** uses a human-created enrollment session, retrieves the assigned enrollment task, captures a synthetic fingerprint through `FakeFingerprintAdapter`, submits only a template payload to the terminal enrollment endpoint, and never stores raw images or probe frames. **Meeting Mode** activates a FLOW-09 assignment/session, downloads the restricted biometric candidate set, performs local synthetic 1:N matching, submits only a sanitized attendance result, closes the session, and clears candidate bytes from memory.

The candidate-set endpoint is intentionally distinct from the ordinary FLOW-09 manifest. It is the only endpoint from which the terminal client may receive active template bytes. Human APIs, audit records, and the ordinary manifest remain byte-free.

## Source layout

| Path | Responsibility |
|---|---|
| `app/src/main/java/com/amms/terminal/TerminalWorkflow.kt` | Android-facing workflow state machine and API transport contract. |
| `app/src/main/java/com/amms/terminal/core/FakeFingerprintAdapter.java` | Synthetic-only local capture and deterministic 1:N matching. |
| `app/src/main/java/com/amms/terminal/core/TerminalSessionState.java` | Enrollment/meeting/closed state transitions and memory-clear boundary. |
| `core-test/FakeFingerprintAdapterContract.java` | JVM contract vectors for match, no-match, low-score, unavailable, and cancelled outcomes. |
| `tools/run_core_contract.py` | Toolchain-independent source/contract check used when Android SDK/Gradle are unavailable. |

## API workflow contract

1. Terminal credential authentication calls `/api/terminal/v1/me` and `/api/terminal/v1/assignments/current`.
2. Meeting Mode activates `/api/terminal/v1/assignments/{assignment}/activate` and stores the one-time session token only in memory.
3. The client downloads `/api/terminal/v1/sessions/{session}/biometric-candidates`; this response contains only the exact active candidate set bound to the session's Participant Version and includes `template_data` for local matching.
4. `FakeFingerprintAdapter` performs local 1:N matching. The server still validates participant membership, template ownership, candidate checksum, session freshness, uniqueness, and idempotency.
5. The client submits `/api/terminal/v1/sessions/{session}/attendance/fingerprint`, then closes `/api/terminal/v1/sessions/{session}/close`.
6. Closing, expiry, stale-version response, terminal revocation, or sign-out clears the session token and candidate template bytes from memory.

## Build boundary

The repository includes a checked-in Gradle wrapper and AndroidX configuration. The strict-closure verification passed `./gradlew --no-daemon --max-workers=1 assembleDebug` with Android SDK API 35 and build-tools 35.0.0. The JVM contract remains independently verifiable through `tools/run_core_contract.py`. This build proves only the software project compiles; it is not a physical device or vendor SDK test. FLOW-10B replaces only the `FakeFingerprintAdapter` implementation with a lawful `DermalogFingerprintAdapter` after an SVT3000 and SDK are supplied; FLOW-10A does not claim hardware evidence.
