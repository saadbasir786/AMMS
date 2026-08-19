# FLOW-10 DERMALOG SVT3000 SE Capability Discovery

**Date:** 19 August 2026
**Method:** Non-destructive repository and local-environment inspection only.

## Observed project boundary

The AMMS repository contains the Laravel backend and React RTL frontend used by FLOW-01 through FLOW-09. No `terminal-android/` module is present in the repository at the start of FLOW-10. Existing terminal routes are limited to FLOW-09 identity, heartbeat, assignment, activation, manifest, and close operations. No enrollment, biometric-candidate, fingerprint-attendance, or manual-attendance endpoint exists yet.

The existing FLOW-09 manifest remains a non-biometric payload. Its architecture is therefore suitable for a separate biometric-candidate contract rather than adding template bytes to the ordinary manifest.

## Local hardware/tooling observations

The sandbox inspection found no attached SVT3000 SE device available through ADB and no confirmed DERMALOG SDK/JAR/AAR/native library supplied to the project. Android build tooling/device connectivity is therefore not yet hardware-verified in this environment. No proprietary package, service, capture API, template format, matcher semantics, PAD capability, threshold, licensing requirement, or custom-app compatibility claim is made from absence of evidence.

The physical hardware status is **FLOW-10B DEFERRED / WAITING FOR PHYSICAL DEVICE**. This does not block FLOW-10A software verification or a future FLOW-10A software-foundation approval. Software implementation proceeds with an explicit adapter boundary and a clearly labeled FakeFingerprintAdapter; fake-adapter results must never be reported as physical DERMALOG success.

## Safety boundary adopted

The implementation must not reverse-engineer protected software, bypass licensing, modify legacy Voter Verification software, store fingerprint images or probe templates, expose template bytes to browser APIs, or invent DERMALOG quality/match thresholds. Real-device gates remain mandatory for final FLOW-10 approval.
