# AMMS Release Baseline

**Baseline date:** 22 August 2026 (Asia/Kabul)  
**Baseline source:** `main@31d892a1920d0024e15c912afc2a319a927996e0`  
**Release position:** **CURRENT PRODUCT SCOPE FROZEN — NO NEW BUSINESS CAPABILITIES**

## 1. Release decision

The current AMMS product scope is frozen. The direct independent audit and consolidation cycle is complete, and the validated audit changes have been merged into `main`.

FLOW-14 is explicitly **APPROVED / FROZEN** by the user as part of this release-baseline decision. Its existing verification artifacts remain historical evidence and are not rewritten to make earlier `PENDING EXPLICIT USER APPROVAL` statements appear retroactive.

FLOW-01 through FLOW-14 are therefore treated as the current frozen software baseline, subject only to bug fixes, security fixes, regression fixes, deployment hardening, documentation corrections, and operational maintenance.

No FLOW-15 feature development is authorized. No new business capability is part of this baseline.

## 2. Deferred hardware boundary

FLOW-10B remains **DEFERRED**. Physical DERMALOG SVT3000 SE integration, lawful vendor SDK integration, real device capture/matching behavior, and hardware acceptance are outside the current release baseline until the required device and lawful SDK access are available.

The vendor-independent FLOW-10A terminal/biometric software foundation remains part of the frozen baseline.

## 3. Frozen functional baseline

The release includes the existing AMMS capabilities already implemented and verified across FLOW-01 through FLOW-14, including:

- internal, bilateral, multilateral, high-authority, and authority-initiated meeting governance;
- schedule and participant versioning with stale-state protection;
- terminal registration, assignment, session, and vendor-independent biometric foundation;
- meeting execution, attendance, Minutes, decisions, review, finalization, and official documents;
- decision follow-up and Action Item governance;
- exceptional meeting lifecycle governance;
- reporting, dashboards, registers, management analytics, and secure private PDF/XLSX exports;
- Persian/Dari RTL frontend;
- authorization based on permission + AMMS organizational scope + resource relationship + lifecycle validity.

`SYSTEM_ADMIN` is a technical administration role and does not receive business-governance authority merely from system administration status.

## 4. CI release gate

The authoritative CI gate for this baseline is the three-part AMMS workflow:

1. **Backend** — Laravel Feature suite on MariaDB/InnoDB-compatible MariaDB service.
2. **Frontend** — dependency install, lint, and production build.
3. **Terminal Android** — vendor-independent core contract and `assembleDebug`.

The final audit/consolidation gate completed successfully before this baseline was declared. Future release, bug-fix, security-fix, and maintenance changes must preserve all three gates.

## 5. Allowed changes after freeze

Allowed without redefining product scope:

- defect fixes;
- security and authorization corrections;
- regression-test additions;
- database migration corrections that preserve intended semantics;
- CI/CD and release-process hardening;
- documentation synchronization;
- dependency/security maintenance after regression verification;
- performance improvements that preserve observable business semantics;
- deployment configuration and operational hardening.

Not allowed without a new explicit product decision:

- new meeting workflow types;
- new business roles or governance powers;
- new approval paths;
- new business lifecycle states;
- delegation or acting-authority features;
- new notification/integration products such as SMS/email workflow behavior;
- new reporting meanings that change frozen metric contracts;
- physical DERMALOG behavior claimed without FLOW-10B evidence.

## 6. Evidence preservation

Verification reports, negative matrices, canonical fixtures, HTTP transcripts, concurrency artifacts, and historical release-status statements are evidence. They must not be rewritten merely to make historical states look current.

This file is the authoritative release-level statement that supersedes earlier `PENDING EXPLICIT USER APPROVAL` wording for FLOW-14 while preserving those earlier reports as historical verification evidence.

## 7. Current next phase

The next phase is **release and production readiness**, not feature development:

- keep CI green on pull requests and protected release branches;
- synchronize top-level documentation with the frozen baseline;
- establish deployment/environment/secrets/backup/restore/runbook checks;
- remove or neutralize development-only credentials before production use;
- perform final release smoke testing against a production-like environment;
- tag a release only after the release-readiness checklist is complete.
