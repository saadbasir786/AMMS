# AMMS FLOW-14 Reporting Scope Matrix

> FLOW-14 authorization is additive and is evaluated as **permission + AMMS organizational scope + resource scope + reporting context**. Role names alone never grant business analytics. The backend query layer constrains rows before serialization; React never filters unauthorized data.

## Scope Vocabulary

| Scope | Meaning |
|---|---|
| `ORGANIZATION` | Distinct organization-wide business totals, available only with an explicit organization reporting permission and authorized role/context. |
| `UNIT_SUBTREE` | The user’s explicitly scoped Directorate and permitted descendants from AMMS organizational hierarchy. |
| `AUTHORITY_MEETINGS` | Meetings with an authoritative frozen authority context for which the user has a valid governance relationship. |
| `COORDINATED_MEETINGS` | Meetings the user is authorized to coordinate under existing Meeting policy and coordinator scope. |
| `PERSONAL` | The authenticated employee’s participant Meetings, personal responsibilities, and personal Action Items. |
| `TECHNICAL_INFRASTRUCTURE` | Terminal, assignment, and session-health aggregates only. No business Meeting content or secrets. |

## Permission Matrix

| Role/context | Dashboard / report permission | Permitted scope | Detail level | Export permission | Explicit denial boundaries |
|---|---|---|---|---|---|
| `SYSTEM_ADMIN` with no additive business-report permission | None for business dashboards | None | None | None | Executive, Directorate, Meeting, Request, Attendance, Minutes, Decisions, Follow-up, and Action Item analytics are denied. |
| `SYSTEM_ADMIN` with `reporting.technical.view` | `reporting.dashboard.technical`, `reporting.technical.view` | `TECHNICAL_INFRASTRUCTURE` | Terminal status/counts, assignment counts, active-session counts, heartbeat/revocation aggregates | Only if explicitly granted `reporting.exports.create` for technical report type | No terminal credential secret, hash, session token, biometric template/probe, Meeting content, attendance detail, minutes, decisions, or business Action Items. |
| `EXECUTIVE_MANAGEMENT` with `reporting.dashboard.executive` and report permissions | Executive dashboard, permitted registers | `ORGANIZATION` when explicit organization scope is present; otherwise assigned AMMS scope | Aggregate business metrics and authorized drill-down rows | `reporting.exports.create` only for permitted scope; download requires ownership or explicit download permission | No access outside organization; no technical secrets; no employee-level attendance unless `reporting.attendance.detail`. |
| `HIGH_AUTHORITY` with `reporting.dashboard.executive` | Executive and authority dashboards | `ORGANIZATION` or `AUTHORITY_MEETINGS` according to explicit seeded scope | Aggregate business metrics; authority detail where policy permits | Same permission plus export ownership checks | No automatic access merely from a name/title; no unrelated technical data. |
| `AUTHORITY_OFFICE` with `reporting.dashboard.authority` | Authority dashboard and authority registers | `AUTHORITY_MEETINGS` tied to frozen authority relationship | Authority-scoped aggregates and rows | Export only when `reporting.exports.create` is explicitly granted and scope is authority-bound | Authority Office role alone never grants organization-wide business analytics. |
| `EXECUTIVE_SECRETARY` with `reporting.dashboard.authority` | Authority dashboard and authorized authority registers | `AUTHORITY_MEETINGS` tied to frozen governance relationship | Authority-scoped aggregates; detail only where resource policy permits | Explicit export permission required | No organization-wide scope by role alone; no unrelated Directorate data. |
| `DIRECTOR` with `reporting.dashboard.directorate` | Directorate dashboard and permitted registers | Own scoped Directorate and permitted subtree (`UNIT_SUBTREE`) | Directorate aggregates; row detail within scope; attendance identity only with detail permission | `reporting.exports.create` for own scope; download ownership/policy required | Explicit unrelated Directorate filter returns `403`; no silent empty result; no organization-wide expansion. |
| `MEETING_COORDINATOR` with `reporting.dashboard.directorate` or report view | Coordination dashboard and coordinated Meeting register | `COORDINATED_MEETINGS` plus existing AMMS scope | Authorized coordinated Meeting rows and aggregates | Explicit export permission required | Being a coordinator does not imply organization-wide analytics or access to unrelated Meetings. |
| `EMPLOYEE` with `reporting.dashboard.personal` | Personal dashboard | `PERSONAL` | Own upcoming/recent participant Meetings, own Action Items, own overdue and pending responsibilities | Personal export only if explicitly granted | Organization, Directorate, unrelated employee, attendance detail, and business-wide reports are denied. |
| Any active user with `reporting.meetings.view` | Meeting Register | Existing Meeting policy scope intersected with requested filters | Allowlisted Meeting fields only | Separate export permission required | No arbitrary SQL fields, no inaccessible Meetings, no secrets. |
| Any active user with `reporting.requests.view` | Meeting Request Register | Existing request scope and resource relationships | Allowlisted request fields | Separate export permission required | No invented request statuses or cross-organization rows. |
| Any active user with `reporting.attendance.summary` | Attendance aggregates | Authorized Meeting/unit scope | Counts and rates only | Separate export permission required | Invalidated rows excluded from official metrics. |
| Any active user with `reporting.attendance.detail` | Attendance Register detail | Authorized Meeting/unit scope | Employee, unit, method, server timestamp, invalidation state where auditor policy allows | Separate export permission required | No template data, probe, raw fingerprint, binary, token, or credential data. |
| Any active user with `reporting.decisions.view` | Decision Register | Authorized Meeting/Decision scope | Final authoritative Minute Version Decisions only | Separate export permission required | Superseded Decisions excluded and source rows immutable. |
| Any active user with `reporting.action-items.view` | Action Item Register | Authorized unit/employee/Meeting scope | Status, progress, due date, derived overdue, extension state, completion timestamps | Separate export permission required | Evidence contents and private storage paths excluded by default. |
| Any active user with `reporting.exceptions.view` | Exception Lifecycle Register | Authorized Meeting scope | Case type, version, reason, status, review summary, applied timestamp, baselines | Separate export permission required | Internal sensitive data is allowlisted and source workflow is read-only. |

## Scope Enforcement Rules

The `ReportingScopeResolver` must first verify the authenticated user is active, then verify the named Spatie permission, then derive AMMS role scopes through `hasScopedRole()` and `scopedRoleUnitIds()`, then intersect the resource relationships and explicit filters. An explicit unrelated unit filter is a scope violation and returns the canonical `403` response; it is not converted into an empty result.

Unqualified requests are automatically constrained to the caller’s permitted scope. A qualified request may only narrow that scope. Search, sort, pagination, and filters use explicit whitelists and never become raw SQL identifiers or unparameterized SQL fragments.

## Export Boundaries

Every export is owned by its requesting user unless an explicit policy grants broader access. The export metadata endpoint and private download endpoint both re-check ownership, organization, permission, and current report scope. The browser receives neither an internal storage path nor a public URL. Export audit payloads contain metadata only and never file bytes, passwords, tokens, biometric data, or private evidence paths.

## Technical Boundary

A technical dashboard is intentionally separate from business reporting. It may expose terminal counts, terminal status, active assignments, active sessions, heartbeat status, and revocation counts. It must never expose terminal credential secrets, credential hashes, session tokens, biometric templates, fingerprint probes, raw fingerprints, candidate templates, Meeting content, Minutes, Decisions, Attendance detail, or Action Item business analytics merely because the caller is `SYSTEM_ADMIN`.
