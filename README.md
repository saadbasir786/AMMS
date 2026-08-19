# AMMS Phase 1 and FLOW-02

AMMS is a Persian/Dari right-to-left Administrative Meeting Management System for an Afghan government organization. The current implementation contains the standardized Foundation from Prompt 01A Final, FLOW-01 for internal directorate meeting requests, and FLOW-02 for formal meetings between exactly two directorates in the same organization.

## Architecture

The backend is a Laravel 13 REST API using Sanctum, Spatie Permission v8.3, Eloquent models, Form Requests, Policies, explicit Action/Use Case classes, database transactions, row locks, stable JSON error envelopes, immutable request-action history, audit events, and in-app notifications. Spatie is used only for general roles and permissions. AMMS `organizational_units` and `amms_role_scopes` remain the authoritative hierarchy and scope context; Spatie Teams are not used.

The frontend is a React 19, TypeScript, and Vite application. It communicates through `/api` endpoints and presents user-facing content in Persian/Dari RTL. Laravel remains the authoritative business layer; React does not implement workflow decisions or authorization rules.

## FLOW-01 lifecycle

The frozen internal-directorate lifecycle is:

```text
DRAFT → PENDING_INTERNAL_APPROVAL → APPROVED
                              ├→ RETURNED_FOR_REVISION → PENDING_INTERNAL_APPROVAL
                              └→ REJECTED
APPROVED → SCHEDULING → SCHEDULE_CONFIRMED → CONVERTED_TO_MEETING
```

A request is separate from an official meeting. No meeting is created before schedule confirmation and finalization, and the database enforces one meeting per request.

## FLOW-02 lifecycle

FLOW-02 supports exactly two distinct directorates under the same organization. A third directorate is rejected with the multi-directorate boundary error; cross-organization and same-directorate pairs are also rejected.

```text
DRAFT
  → PENDING_REQUESTER_APPROVAL
  → REQUESTER_APPROVED → SUBMITTED_TO_TARGET
  → UNDER_TARGET_REVIEW
  → TARGET_APPROVED → BILATERAL_APPROVED
  → SCHEDULING
  → SCHEDULE_CONFIRMED
  → CONVERTED_TO_MEETING
```

Target-side return uses a separate resubmission path and target-side rejection is terminal. Schedule proposals are append-only records; accepting a proposal supersedes older active proposals without overwriting their history. Participant nominations are owned by one of the two directorates, and each side confirms its own final list independently. The final meeting contains exactly two official `meeting_units` rows.

## RBAC and scope model

The authoritative distinction is as follows:

| Layer | Responsibility |
|---|---|
| Spatie Permission | General role and permission capability. |
| AMMS organizational units | Organization hierarchy and directorate boundaries. |
| `amms_role_scopes` | Links a user, Spatie role, and organizational unit. |
| Laravel Policy | Permission, scope, resource, ownership, and active-account authorization. |
| Action / Use Case | State transition, invariant, transaction, row lock, audit, and notification behavior. |

The Foundation migration seeds 20 permissions and five roles: `SYSTEM_ADMIN`, `EXECUTIVE_MANAGEMENT`, `DIRECTOR`, `MEETING_COORDINATOR`, and `EMPLOYEE`. FLOW-02 adds its frozen permission set with `guard_name=sanctum` and attaches permissions only to existing authorized roles. The legacy `user_roles` table is preserved and backfilled rather than dropped. The `meeting-request.cancel` permission and transition are intentionally not present.

## Prerequisites

Use PHP 8.3+, Composer, Node.js 22+, pnpm, and MySQL 8+ or MariaDB 10.11+. SQLite is supported for isolated automated verification. The application is designed for MySQL-compatible foreign keys, indexes, unique constraints, timestamps, JSON metadata, and InnoDB row locking.

## Backend setup

```bash
cd backend
cp .env.example .env
php artisan key:generate
# Configure DB_CONNECTION=mysql and DB_* values in .env.
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

For local SQLite verification:

```bash
touch database/database.sqlite
# Set DB_CONNECTION=sqlite and DB_DATABASE=/absolute/path/to/backend/database/database.sqlite.
php artisan migrate:fresh --seed
```

Use `php artisan migrate --force` for incremental deployment migrations. Do not run `migrate:fresh` against production or any environment containing working data.

## Frontend setup

```bash
cd frontend
pnpm install
pnpm dev
```

The Vite development server normally runs on `http://127.0.0.1:5173`; the local smoke environment used `http://127.0.0.1:5174`. Both proxy `/api` to Laravel at port 8000. Build verification is:

```bash
pnpm build
```

## Development accounts

All seeded development accounts use the non-production password `ChangeMe!2026`. Change or remove these accounts before any production deployment.

| Role | Email | Scope |
|---|---|---|
| System Administrator | `system.admin@example.test` | All organization data |
| Technology Executive Management | `tech.exec@example.test` | Technology Directorate |
| Technology Director | `tech.director@example.test` | Technology Directorate approval |
| Technology Coordinator | `tech.coordinator@example.test` | Technology Directorate scheduling |
| Technology Employee | `employee.a@example.test` | Software Department |
| Finance Executive Management | `finance.exec@example.test` | Finance Directorate |
| Finance Director | `finance.director@example.test` | Finance Directorate approval |

## API highlights

Unauthenticated access is limited to `POST /api/login`. Authenticated endpoints include `/api/me`, `/api/dashboard`, `/api/meeting-requests`, `/api/incoming-meeting-requests`, `/api/approvals`, `/api/meetings`, `/api/notifications`, `/api/organizational-units`, `/api/employees`, and `/api/admin/*` administration endpoints.

FLOW-01 operations are exposed as separate endpoints: `/submit`, `/approve`, `/return`, `/reject`, `/start-scheduling`, `/confirm-schedule`, and `/finalize`. The existing `/submit` endpoint selects `SubmitMeetingRequest` for `DRAFT` and `ResubmitMeetingRequest` for `RETURNED_FOR_REVISION`.

FLOW-02 operations are explicit and do not expose a generic status-update endpoint. The main routes are `/inter-directorate`, `/submit-inter-directorate`, `/requester-approve`, `/requester-return`, `/requester-reject`, `/target-review`, `/target-approve`, `/target-return`, `/target-reject`, `/resubmit-to-target`, `/start-inter-directorate-scheduling`, `/schedule-proposals`, `/participant-nominations`, `/requester-participants/confirm`, `/target-participants/confirm`, `/confirm-inter-directorate-schedule`, and `/finalize-inter-directorate`.

Responses follow `{ success, data, message }` on success and `{ success, code, message }` on errors. Important machine-readable error codes include `UNAUTHORIZED_APPROVER`, `REQUEST_NOT_PENDING_APPROVAL`, `RETURN_REASON_REQUIRED`, `REJECTION_REASON_REQUIRED`, `PARTICIPANT_OUTSIDE_DIRECTORATE`, `REQUEST_NOT_APPROVED`, `SCHEDULE_INCOMPLETE`, `MEETING_ALREADY_CREATED`, `THIRD_DIRECTORATE_REQUIRES_MULTI_DIRECTORATE_FLOW`, and target-side availability errors.

## Tests and verification

Run the local feature suite and frontend build with:

```bash
cd backend
php artisan test --testsuite=Feature

cd ../frontend
pnpm build
```

The current suite passes **18 feature tests with 143 assertions**. It covers FLOW-01 and FLOW-02 creation, bilateral approval, target review, target return/resubmission, rejection, proposal negotiation, participant ownership, two-directorate constraints, single-meeting conversion, duplicate-operation protection, permission denial, directorate scope enforcement, inactive-account enforcement, persisted workflow history, and audit immutability.

The real database concurrency probe is:

```bash
cd backend
php tests/Concurrency/mysql_row_lock_test.php
```

It requires an isolated MySQL/MariaDB database configured through `AMMS_MYSQL_HOST`, `AMMS_MYSQL_PORT`, `AMMS_MYSQL_DATABASE`, `AMMS_MYSQL_USERNAME`, and `AMMS_MYSQL_PASSWORD`. The probe verifies row-lock serialization, one-meeting uniqueness, and FLOW-02 parallel finalization producing one converted meeting.

The final verification report is in [`VERIFICATION.md`](VERIFICATION.md). FLOW-02 browser/API smoke evidence is in [`browser_smoke_flow02.md`](browser_smoke_flow02.md).

## Manual FLOW-02 scenario

Log in as `tech.exec@example.test` and open `meeting-requests/create-inter-directorate`. Select Technology as the requester directorate and Finance as the target directorate, enter a subject, purpose, schedule proposal, location, and participants from both sides, then submit for requester approval. Approve as `tech.director@example.test`, begin target review as `finance.exec@example.test`, and approve as `finance.director@example.test`. Start scheduling, exchange proposals, accept one active proposal, nominate and confirm participants separately for both directorates, confirm the bilateral schedule, and finalize the meeting. The request becomes `CONVERTED_TO_MEETING`, exactly one meeting is generated, and the meeting contains exactly two official directorate units.

## Scope boundary

This phase intentionally does not implement three-or-more-directorate workflows, cross-organization workflows, ministerial or Director General workflows, attendance, biometric terminals, minutes, decisions, action items, SMS, email integration, external integrations, or the unimplemented cancel transition. These remain outside the current FLOW-02 boundary and belong to later prompts.
