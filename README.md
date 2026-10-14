# AMMS — Administrative Meeting Management System

AMMS is a Persian/Dari right-to-left Administrative Meeting Management System for an Afghan government organization.

## Current release position

The current product scope is frozen at **FLOW-01 through FLOW-14**. The independent audit and consolidation cycle is complete. No new business capability or FLOW-15 development is part of the current release plan.

The authoritative release statement is [`docs/release/RELEASE_BASELINE.md`](docs/release/RELEASE_BASELINE.md). The documentation index is [`docs/README.md`](docs/README.md).

**FLOW-10B** — formal production acceptance of physical DERMALOG SVT3000 SE integration — remains explicitly deferred. The Android terminal client and backend workflow do not by themselves certify production device support.

## Architecture

### Backend

- Laravel 13 REST API
- Sanctum authentication
- Spatie Permission for general capability assignment
- AMMS organizational units and role scopes for business scope
- Form Requests, Policies, explicit Action / Use Case classes
- database transactions and row locking
- immutable/versioned workflow records where required
- audit and notification records
- MariaDB/MySQL production-oriented persistence

Laravel is the authoritative business and authorization layer. The frontend does not implement workflow decisions or substitute for backend authorization.

### Frontend

- React 19
- TypeScript
- Vite
- Persian/Dari RTL user interface

### Terminal Android

- AMMS Android terminal client under `terminal-android/`
- targets the terminal's Android API 25 and ARMv7 runtime constraints
- includes the terminal app's DERMALOG integration artifacts
- CI runs `assembleDebug`; this is a compile check, not hardware or production acceptance
- formal FLOW-10B production acceptance remains deferred

## Frozen functional areas

The current baseline includes the already implemented and verified capabilities for:

- internal, bilateral, multilateral, high-authority, and authority-initiated meeting governance;
- scheduling and participant versioning with stale-state protection;
- terminal registration, assignment, session management, and vendor-independent biometric foundation;
- meeting execution and attendance;
- Minutes, decisions, reviews, finalization, and official document generation;
- Decision follow-up and Action Item governance;
- exceptional meeting lifecycle governance;
- reporting, dashboards, management analytics, registers, and secure private PDF/XLSX exports.

Historical workflow contracts and audit evidence are preserved under [`docs/history/`](docs/history/).

## Authorization model

AMMS separates capability from business authority:

| Layer | Responsibility |
|---|---|
| Spatie Permission | General named capabilities |
| AMMS organizational units | Organization hierarchy and business boundaries |
| AMMS role scopes | User/role/unit scope relationships |
| Laravel Policy | Permission + scope + resource/ownership + active-account authorization |
| Action / Use Case | State transition, invariant, transaction, row lock, audit, and notification behavior |

`SYSTEM_ADMIN` is a technical administration role. System-administration status alone must not provide meeting business-governance authority.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 22+
- pnpm 10+
- MySQL 8+ or MariaDB 10.11+
- Java 21 for the Android terminal build

SQLite is used only where explicitly required for isolated automated fixtures/verification. MariaDB remains part of the authoritative backend CI gate.

## Backend setup

```bash
cd backend
cp .env.example .env
php artisan key:generate
# Configure DB_CONNECTION=mysql and DB_* for an isolated database.
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

For incremental deployments, use migrations. **Do not run `migrate:fresh` against production or any environment containing working data.**

## Frontend setup

```bash
cd frontend
pnpm install --frozen-lockfile
pnpm dev
```

Production verification:

```bash
pnpm run lint
pnpm run build
```

## Terminal Android verification

```bash
cd terminal-android
./gradlew --no-daemon --max-workers=1 assembleDebug
```

## CI gate

`.github/workflows/ci.yml` defines the authoritative three-part AMMS CI gate:

1. Laravel Feature suite on MariaDB;
2. frontend lint and production build;
3. terminal Android `assembleDebug` compile check (not hardware acceptance).

The workflow protects pull requests and pushes to `main`, `release/**`, and retained `audit/**` branches.

## Development/test accounts

The repository contains seeded development/test identities. They are not production credentials and must be changed, disabled, or removed as part of production deployment hardening. Do not deploy known development passwords into a production environment.

## Release boundary

The current phase is **release/production readiness**, not feature development. Allowed work is limited to bug fixes, security fixes, regression protection, documentation synchronization, CI/CD hardening, dependency maintenance with regression verification, performance work that preserves semantics, and deployment/operational hardening.

No new workflow, role, approval path, business lifecycle state, delegation/acting-authority behavior, notification product, or other new business capability should be introduced without a separate explicit product decision.
