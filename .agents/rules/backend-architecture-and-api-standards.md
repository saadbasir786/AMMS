---
trigger: always_on
---

# AMMS Backend Architecture, API & Engineering Standards

This document establishes the mandatory backend architecture, API standards, and coding conventions for the Afghanistan Meetings Management System (AMMS) Laravel codebase. All future developments, refactorings, and bug fixes must strictly adhere to these standards.

## Scope, Precedence & Existing Modules

- Read this document and `roles-permissions-and-scope.md` before changing a backend module. The authorization charter takes precedence over implementation examples here.
- These rules govern implementation, not business authority. They do not authorize changes to roles, permissions, capabilities, organizational scope, ownership, approval paths, lifecycle states, or metric semantics.
- Before editing, trace the affected route, FormRequest, Policy/Gate, Action/Service, Resolver, Model, Resource, tests, and frontend consumers. Reuse the domain's established implementation rather than creating a second source of truth.
- Apply these standards to new and changed behavior. Existing deviations are not precedent; document relevant gaps and address them within the task scope. Do not silently change unrelated endpoints or public contracts to enforce a convention.

---

## 1. Modular Controller & Action Architecture

AMMS separates HTTP presentation from domain execution:

1. **Modular Routing (`routes/api/`)**:
   - Routes are partitioned by domain into `routes/api/*.php` (`auth.php`, `organization.php`, `meeting-requests.php`, `meetings.php`, `action-items.php`, `attendance-biometrics.php`, `reporting.php`).
   - **Route Precedence Rule**: Explicit static routes (e.g. `/notifications/read-all`, `/notifications/unread-count`, `/meetings/export`) must **always be registered BEFORE parameterized wildcard routes** (e.g. `/notifications/{notification}`, `/meetings/{meeting}`). Otherwise, Laravel router captures the subpath as an entity ID parameter.

2. **Lean Controllers & Domain Actions (`app/Actions/`)**:
   - Controllers under `app/Http/Controllers/Api/<Domain>/` are responsible for HTTP input extraction, policy/gate authorization, and returning formatted API responses.
   - Multi-step business workflows, lifecycle transitions, state machines, and cross-model updates must live inside dedicated Action classes under `app/Actions/<Domain>/`.
   - Controllers must never perform direct raw SQL mutations across multiple domain entities inline.
   - Reuse existing domain Services when Actions delegate to them. Transaction ownership must be clear; do not duplicate workflow orchestration between controller, Action and Service.
   - FormRequests own input validation, Policies/Gates own authorization, Resolvers own reusable governance decisions, and Resources own serialization. Models own relationships, casts and persistence behavior, not HTTP responses.
   - Preserve existing domain organization (`app/Reporting/`, for example). Do not introduce generic repository layers, packages, or a new directory scheme without a concrete need.

3. **Atomic Database Transactions**:
   - All state-changing workflows, batch updates, approvals, schedule confirmations, or exception applications must execute inside `DB::transaction(function () { ... })`.
   - If an unexpected error or constraint violation occurs, the transaction rolls back cleanly, leaving zero orphan or corrupted records.

4. **Immutable Audit Logging**:
   - Every business action (approval, return for revision, rejection, rescheduling, participant swap, delegation grant/revocation, exception) must record an immutable audit row (`actor_id`, `action`, `comment`, `metadata`, `created_at`).
   - Use the domain's existing audit schema and logger; the fields above describe required information, not mandatory column names. Record the affected entity/version and delegation context where applicable.
   - Persist successful-action audit rows in the same transaction as the change. Never edit or delete historical audit rows through normal application operations, and never store credentials or biometric payloads in metadata.

---

## 2. Server-Side Pagination & Query Standards (Best Practice)

In enterprise administrative systems, data records (meetings, requests, notifications, audit logs, action items) grow continuously. Client-side pagination or unbounded fetching is strictly forbidden.

1. **Mandatory Server-Side Pagination**:
   - All dynamic collection endpoints must accept query parameters:
     - `page` (integer, default 1)
     - `per_page` or `limit` (integer, default 15 or 20)
   - Enforce defensive bounds on `per_page` to protect database resources:
     ```php
     $perPage = min(max($request->integer('per_page', $request->integer('limit', 15)), 1), 50);
     $paginator = $query->paginate($perPage);
     ```
   - Prefer `per_page` for new APIs; retain `limit` where compatibility requires it. Document each endpoint's default and maximum and preserve existing bounds unless deliberately changing its contract.
   - Validate pagination input as integers and normalize or reject nonpositive `page` consistently. Do not assume integer casting alone is input validation. Empty or out-of-range pages must retain the normal collection envelope.
   - Use stable ordering with a unique tie-breaker (for example `latest()->orderByDesc('id')`) so equal timestamps do not cause records to shift unpredictably between pages.
   - Finite, explicitly bounded reference data may be returned without pagination when its bound is documented. Employees and other growing selectors require server search/pagination; exports use bounded batches or streaming, not an unlimited `get()` or a browser loop that loads every page.

2. **Standard Resource Collection Envelope**:
   - Always return paginated responses using Laravel's `JsonResource::collection($paginator)`.
   - This automatically provides the standardized JSON pagination metadata:
     ```json
     {
       "data": [ ... ],
       "meta": {
         "current_page": 1,
         "from": 1,
         "last_page": 5,
         "per_page": 15,
         "to": 15,
         "total": 68
       },
       "links": {
         "first": "...",
         "last": "...",
         "prev": null,
         "next": "..."
       }
     }
     ```

3. **Authoritative Counters in Additional Response Fields**:
   - When listings require global aggregate badges (e.g. `unread_count`, `total_count`, `pending_approval_count`), the backend MUST calculate and return them via `->additional([...])`:
     ```php
     return NotificationResource::collection($paginator)->additional([
         'success' => true,
         'message' => 'اعلان‌های شما.',
         'unread_count' => $unreadCount,
         'total_count' => $totalCount,
     ]);
     ```
   - The frontend must consume these authoritative counts directly and must never calculate approximations in browser memory.
   - The example adds top-level fields; it does not put counters inside Laravel's pagination `meta`. Preserve current contracts (`stats`, `unread_count`, `total_count`) and reserve pagination `meta.total` for the filtered result count.
   - Derive rows and aggregates from the same authorized base scope. Document whether each aggregate ignores or follows search/status/date filters; use cloned builders to prevent filters leaking between queries.
   - Reuse canonical status/metric definitions from the relevant domain. Do not invent status groups or count joined records twice; test aggregate semantics independently of page size.

4. **Server-Side Filtering & Search**:
   - Filters (e.g. `status`, `search`, `unit_id`, `filter=UNREAD`) must be evaluated as SQL `WHERE` clauses on the Eloquent builder before calling `paginate()`.
   - Never fetch unfiltered collections into PHP memory to perform `Collection::filter()`.
   - Allowlist filter values and sortable columns/directions; bind values and never interpolate client input into raw SQL or column names. Group search `OR` clauses so they cannot escape the authorized scope.
   - Eager-load relationships required by Resources and use database aggregates for counts. Avoid per-row queries and unbounded nested collections; paginate large child resources separately.
   - Add or review indexes for actual scope/filter/order patterns. Measure expensive queries on representative data rather than assuming pagination alone prevents expensive scans.

---

## 3. Data Isolation, Security & Authorization

All endpoints must enforce defensive authorization in strict alignment with `.agents/rules/roles-permissions-and-scope.md`:

1. **Active User Enforcement**:
   - User-authenticated routes must use the established authentication middleware and enforce active user/employee lifecycle through the existing Policy, Resolver or guard. The following guard assumes an authenticated user:
     ```php
     abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
     ```
   - Terminal/device routes use their established credential/session middleware and meeting constraints; do not substitute user authentication or weaken that separate boundary.

2. **Resource Ownership & Scope Isolation**:
   - Private or user-specific resources (e.g. notifications, personal action items) must be strictly isolated to `$request->user()->id`:
     ```php
     abort_unless($notification->user_id === $request->user()->id, 403, 'FORBIDDEN');
     ```
   - Organizational resources must use their canonical Policy/Resolver scope, including approved ownership and cross-unit relationships. A simple unit-only endpoint may use:
     ```php
     $scopedUnitIds = $user->scopedRoleUnitIds('DIRECTOR');
     $query->whereIn('organizational_unit_id', $scopedUnitIds ?: [-1]);
     ```
   - Cross-unit leaks, cross-user mutations, or unscoped access are strictly prohibited.
   - `viewAny` and route model binding do not authorize every returned record. Apply scope to listings, details, nested records, search, counts, downloads and exports. Verify that nested IDs belong to the authorized parent.
   - Use composite abilities as defined in the charter; a plain permission with the same name must not short-circuit a scope-sensitive Policy. Capability responses and execution must use the same governance rules, and execution must recheck current authority.

3. **Technical Admin Separation**:
   - `SYSTEM_ADMIN` is technical infrastructure administration only. It has zero business approval or coordinator authority over meeting workflows.

---

## 4. Date, Time & Calendar Standards

AMMS follows a strict boundary between storage contracts and presentation formatting:

1. **Backend Storage & Wire Contract**:
   - All dates and timestamps are stored in database columns in standard Gregorian format (`YYYY-MM-DD`, `HH:mm:ss`, or ISO 8601).
   - API endpoints receive and return dates in standard ISO `YYYY-MM-DD` and times in `HH:mm`.
   - Never store formatted Persian or Solar Hijri strings directly into database date/time columns.
   - Distinguish date-only fields, local meeting times and absolute timestamps. Preserve the existing application/domain timezone; serialize absolute timestamps with an explicit offset or `Z`. Never rely on the workstation timezone or shift date-only values through UTC conversion.
   - Validate date/time format and the domain's existing start/end, expiry and duration constraints on the server. Test midnight and expiry boundaries with a controlled clock; do not introduce new scheduling rules implicitly.
2. **Frontend Presentation**:
   - Afghan Solar Hijri (*حمل، ثور، جوزا، ...*) conversions are performed strictly on the presentation layer via `@/lib/calendar-utils`.

---

## 5. Standard JSON API Response Envelope & Persian Messaging

All API endpoints must return a predictable, uniform JSON structure:

1. **Standard Envelope**:
   ```json
   {
     "success": true,
     "data": { ... },
     "message": "عملیات با موفقیت انجام شد."
   }
   ```
2. **Persian Administrative Messaging**:
   - Messages must be professional, respectful, clear, and in administrative Persian.
   - Never expose raw database column names, internal class names, or technical codes (`FLOW-01`, `FLOW-02`, etc.) in user-facing message strings.
   - Machine-readable status/action codes may remain in structured fields for clients; user-facing messages and validation labels must be humanized Persian.

3. **Errors & HTTP Semantics**:
   - Preserve the central API error contract: `success: false`, stable `code`, safe Persian `message`, and field-level `errors` for validation. Render errors centrally instead of implementing a different envelope in each controller.
   - Use appropriate HTTP status codes: `201` for creation, `401` for missing/invalid authentication, `403` for denied authority, `404` for missing resources, and `422` for invalid input. Preserve established domain conflict statuses; document and test any new `409` contract.
   - Do not return `200` with a concealed failure or catch an exception and continue after a partial workflow. Unexpected failures must roll back, be logged safely, and return a generic message without SQL, stack traces or exception internals.
   - Explicit Resource fields must prevent exposure of passwords, tokens, raw biometrics and internal storage paths. Download/stream responses and established `204` responses are documented exceptions to the JSON success envelope.
   - Keep response fields, types, nullability and pagination shape compatible with existing consumers. Update frontend types/consumers and contract tests together for intentional changes.

---

## 6. Mandatory Automated Testing Directives (Definition of Done)

No backend feature or bug fix is complete without automated regression coverage:

1. **Feature Test Location**:
   - Cover each changed behavior through domain Feature tests under `backend/tests/Feature/<FeatureName>Test.php`; extend existing suites when appropriate. A separate test file for every class is not required. Pure deterministic helpers may also have Unit tests.

2. **Required Test Scenarios**:
   - **Happy Path**: Successful creation, listing, transition, or update.
   - **Server-Side Pagination & Meta**: Verify `current_page`, `last_page`, `total`, `per_page` when records span multiple pages.
   - **Unauthenticated Check**: Assert `401 Unauthorized` when called without token.
   - **Unauthorized Role / Scope Check**: Assert `403 Forbidden` when called by an invalid role or out-of-scope user.
   - **Data Isolation Check**: Verify User A cannot access, mutate, or read User B's private resources.
   - As applicable: inactive users/employees, missing permissions, revoked/expired delegations, multi-role scope isolation and `SYSTEM_ADMIN` non-escalation.
   - For writes: invalid input, wrong lifecycle/version, repeated requests, atomic rollback, audit attribution and unchanged database state after denial/failure.
   - For lists: lower/upper pagination bounds, empty/out-of-range pages, stable order, grouped search, foreign-scope exclusion and authoritative aggregates across pages/filters.
   - Match the established endpoint contract where a scoped lookup deliberately returns `404` instead of `403`; never broaden visibility just to satisfy a generic test expectation.

3. **Targeted Verification Command & Fast Feedback Loop**:
   ```bash
   php artisan test tests/Feature/<FeatureName>Test.php --filter=<method_name>
   ```
   - When verifying incremental changes, bug fixes, or new endpoints, **strictly use `--filter` to run only the specific test methods or class** directly affected by the change.
   - **Never run large, unrelated test suites blindly** during incremental feature development, as full database resets and unrelated heavy integration suites (such as PDF generators or complete workflow runs) cause severe, unnecessary delays.
   - Run from `backend/` with an isolated testing database. Check `phpunit.xml`, test environment and cached configuration before commands that reset data; never point `RefreshDatabase`, migrations or seeders at a development/production database.
   - Fake external mail, storage and network effects as appropriate. Use deterministic fixtures and controlled time. Locking/concurrency guarantees require checks on the supported database engine; SQLite-only tests cannot establish them.
   - Run affected suites and broaden to related/full backend suites for shared authorization, schema or workflow changes. Run frontend checks when API consumers change. Report actual results and environment failures separately; do not call an incomplete or failed run green.

---

## 7. Input Validation & Trusted Fields

- Use domain FormRequests for nontrivial write inputs and query validation. Persist only validated, explicitly allowlisted fields; never mass-assign `$request->all()`.
- Derive actor IDs, ownership, audit attribution, generated identifiers and workflow-controlled state from authenticated/server context. Accept target user/unit IDs only for explicitly authorized operations and validate their scope.
- Validate enums against canonical values, string lengths against schema limits, array sizes, distinct IDs, nested structures and related record existence. An `exists` rule alone does not prove scope or parent membership.
- Separate optional, nullable and omitted fields, especially on partial updates; do not erase existing values merely because a field was absent. Define normalization consistently without silently altering meaningful user text.
- Recheck mutable domain invariants in the Action/Service so jobs, commands and concurrent HTTP requests cannot bypass them.

## 8. Concurrency, Idempotency & Side Effects

- Transactions alone do not prevent two actors from approving the same state. Where requests can compete, lock the governing row with `lockForUpdate()` inside the transaction, or use an established optimistic version/conditional-update mechanism.
- Reload and check current lifecycle/version and applicable authority inside that protected operation before mutation. Acquire related locks in a consistent order. Do not authorize a write solely from a stale route-bound model.
- Define repeat-request behavior using the existing domain contract: return the existing outcome where idempotent, or reject a stale/duplicate action. Do not create duplicate meetings, grants, attendance records or audit transitions on retries.
- Enforce uniqueness with database constraints where expressible; application prechecks are not a concurrency guarantee. Handle constraint conflicts without exposing SQL errors.
- Keep successful workflow data and required database audit/notification rows atomic. Dispatch external notifications/jobs only after commit; do not perform slow network/device calls while holding row locks.
- Database rollback cannot undo file writes or external calls. Use the existing staged-file/cleanup or durable delivery pattern where required, with bounded retries and idempotent handlers. After-commit dispatch alone does not guarantee durable delivery.

## 9. Schema Changes, Models & Seed Data

- Use new forward migrations for schema/data changes; do not rewrite migrations already shared or deployed. Preserve historical workflow and audit data.
- Plan changes against existing rows: backfill before enforcing new non-null/unique constraints, identify duplicates explicitly, and avoid silent data deletion. Document destructive or irreversible migrations and deployment ordering.
- Use correct foreign keys, intentional delete behavior, indexes and model casts. Do not cascade-delete business history to simplify account or employee maintenance.
- Keep model fillable/guarded fields, casts, relationships, validation and Resources consistent with the schema. Prefer additive, backward-compatible changes when old and new application versions may overlap.
- Seeders must be repeatable for their intended environment. Keep test/demo identities out of production provisioning, do not reset real credentials, and never recreate retired roles from historical fixtures.
- Test relevant migrations against existing-data cases and document rollback limitations. Never run destructive reset commands against shared data as a verification shortcut.

## 10. Files, Integrations, Configuration & Logging

- Validate uploaded file size/type and authorized parent ownership; generate storage names server-side and use private storage for attachments, minutes, exports and biometric data. Never trust a client path or original filename as a storage destination.
- Authorize upload, download and delete separately using existing domain rules. Enforce export ownership/expiry and issue short-lived links only after authorization; possession of a record ID is insufficient.
- Never log or serialize passwords, access tokens, terminal credentials, raw biometric templates/images or unrestricted request bodies. Log safe operation/entity identifiers and enough context to diagnose failures; operational logs do not replace immutable business audits.
- Read environment variables through configuration files and `config()` in application code. Keep secrets outside version control; never hardcode credentials or enable production debug output.
- External clients must use configured trusted destinations, explicit timeouts and bounded retries only where safe for the operation. Background jobs/commands must enforce the same domain invariants and applicable current scope/authority as the HTTP flow.
- Scope caches by the relevant user/unit/filter and authorization context. Invalidate affected entries after writes or access changes; cached capabilities must never replace current execution authorization.

## 11. Module Development & Completion Checklist

For a new module or a change to an existing module:

1. Identify affected behavior, existing consumers, canonical domain rules and relevant tests before implementation. Resolve domain changes through the authorization charter's explicit decision process.
2. Implement in the established route/FormRequest/Policy/Action-or-Service/Resolver/Resource structure; add schema changes only through forward migrations.
3. Check scope on every read/write and aggregate, input allowlists, pagination/query cost, transactional invariants, concurrent/repeated requests and audit preservation as applicable.
4. Keep API contracts and frontend visibility/types synchronized; document new endpoint parameters, aggregate semantics and significant compatibility or deployment requirements in the relevant project documentation.
5. Verify changed behavior with the required tests and checks. Documentation-only edits need consistency/link/diff checks rather than an unrelated application test run.
6. Review the diff for secrets, accidental generated files and unrelated changes. Report what changed, verification results and any remaining limitations.
7. Follow `AGENTS.md`: after feature completion, verification and user approval, record a clean, dedicated Git commit locally before starting the next feature. Never execute `git push`; remote push is strictly manual by the user.

## 12. Autonomous Implementation-Rule Evolution

- Maintain reusable backend implementation conventions here when a task establishes a concrete pattern; record material rule changes in the task handoff.
- Never autonomously modify business authority, scope, roles, permissions, delegated capabilities, ownership, approval paths, lifecycle states or metric definitions. The authorization charter remains authoritative.
- Keep rules consistent with each other and distinguish a desired standard from verified current compliance. Adding a rule does not certify that every existing endpoint already follows it.

## 13. Business Version Inspection

- Implement business snapshot inspection through a shared read-only service/controller contract with explicit domain adapters for summaries, full views, current-version identity and semantic comparisons.
- Scope version queries through the authorized parent entity. Validate both comparison IDs against the same parent; never compare by accepting two unscoped version IDs.
- Reuse each domain's existing Policy/Gate/Resolver. Do not create a global version permission that broadens business scope.
- Paginate lightweight history summaries and load immutable snapshot relations only for a requested View or Compare. Avoid serializing all nested snapshots in workspace responses.
- Comparators must be deterministic, directional and domain-aware. Match collections by stable business identity and exclude database-only fields from comparison semantics.
- Keep inspection endpoints side-effect free. Never update version pointers, projections, workflows, notifications or audits as a consequence of reading a version.
- Exclude sensitive technical versions such as biometric templates. If later audit metadata is needed, define an explicit safe adapter that never returns payloads, images, secrets or sensitive checksums.
