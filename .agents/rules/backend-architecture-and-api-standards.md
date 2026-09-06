# AMMS Backend Architecture, API & Engineering Standards

This document establishes the mandatory backend architecture, API standards, and coding conventions for the Afghanistan Meetings Management System (AMMS) Laravel codebase. All future developments, refactorings, and bug fixes must strictly adhere to these standards.

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

3. **Atomic Database Transactions**:
   - All state-changing workflows, batch updates, approvals, schedule confirmations, or exception applications must execute inside `DB::transaction(function () { ... })`.
   - If an unexpected error or constraint violation occurs, the transaction rolls back cleanly, leaving zero orphan or corrupted records.

4. **Immutable Audit Logging**:
   - Every business action (approval, return for revision, rejection, rescheduling, participant swap, delegation grant/revocation, exception) must record an immutable audit row (`actor_id`, `action`, `comment`, `metadata`, `created_at`).

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

3. **Authoritative Counters in Metadata**:
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

4. **Server-Side Filtering & Search**:
   - Filters (e.g. `status`, `search`, `unit_id`, `filter=UNREAD`) must be evaluated as SQL `WHERE` clauses on the Eloquent builder before calling `paginate()`.
   - Never fetch unfiltered collections into PHP memory to perform `Collection::filter()`.

---

## 3. Data Isolation, Security & Authorization

All endpoints must enforce defensive authorization in strict alignment with `.agents/rules/roles-permissions-and-scope.md`:

1. **Active User Enforcement**:
   - Every protected API endpoint must verify user active status:
     ```php
     abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
     ```

2. **Resource Ownership & Scope Isolation**:
   - Private or user-specific resources (e.g. notifications, personal action items) must be strictly isolated to `$request->user()->id`:
     ```php
     abort_unless($notification->user_id === $request->user()->id, 403, 'FORBIDDEN');
     ```
   - Organizational resources (meetings, requests, delegations) must be scoped to the user's explicit organizational unit ID:
     ```php
     $scopedUnitIds = $user->scopedRoleUnitIds('DIRECTOR');
     $query->whereIn('organizational_unit_id', $scopedUnitIds ?: [-1]);
     ```
   - Cross-unit leaks, cross-user mutations, or unscoped access are strictly prohibited.

3. **Technical Admin Separation**:
   - `SYSTEM_ADMIN` is technical infrastructure administration only. It has zero business approval or coordinator authority over meeting workflows.

---

## 4. Date, Time & Calendar Standards

AMMS follows a strict boundary between storage contracts and presentation formatting:

1. **Backend Storage & Wire Contract**:
   - All dates and timestamps are stored in database columns in standard Gregorian format (`YYYY-MM-DD`, `HH:mm:ss`, or ISO 8601).
   - API endpoints receive and return dates in standard ISO `YYYY-MM-DD` and times in `HH:mm`.
   - Never store formatted Persian or Solar Hijri strings directly into database date/time columns.
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

---

## 6. Mandatory Automated Testing Directives (Definition of Done)

No backend feature or bug fix is complete without automated regression coverage:

1. **Feature Test Location**:
   - Every domain controller, route, or action class must have a dedicated test file under `backend/tests/Feature/<FeatureName>Test.php`.

2. **Required Test Scenarios**:
   - **Happy Path**: Successful creation, listing, transition, or update.
   - **Server-Side Pagination & Meta**: Verify `current_page`, `last_page`, `total`, `per_page` when records span multiple pages.
   - **Unauthenticated Check**: Assert `401 Unauthorized` when called without token.
   - **Unauthorized Role / Scope Check**: Assert `403 Forbidden` when called by an invalid role or out-of-scope user.
   - **Data Isolation Check**: Verify User A cannot access, mutate, or read User B's private resources.

3. **Verification Command**:
   ```bash
   php artisan test tests/Feature/<FeatureName>Test.php
   ```
