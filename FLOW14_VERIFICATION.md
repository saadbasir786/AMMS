# AMMS FLOW-14 Verification Report

## Release Status

> **FLOW-14: VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**

FLOW-14 has been implemented additively as the reporting, dashboards, management analytics, and secure exports layer. It has not been marked APPROVED or FROZEN automatically. FLOW-01 through FLOW-13 remain frozen and unchanged in meaning. FLOW-10B physical DERMALOG SVT3000 SE integration remains deferred. FLOW-15 has not started.

## Implemented Boundary

The implementation follows the established AMMS pipeline:

> **Controller → FormRequest → Policy/permission and AMMS scope → read-only Query Service → API Resource → Persian/Dari RTL UI**

Reporting queries are read-only. They use server-side half-open date ranges, configured timezone metadata, explicit metric IDs, allowlisted filters and sorts, distinct Meeting identities, authoritative frozen versions, and `OfficialAttendanceResolver` for official attendance. The React interface does not recompute business metrics.

## Authoritative Artifacts

| Artifact | Purpose | Result |
|---|---|---:|
| `FLOW14_METRIC_CATALOG.md` | Versioned formulas, sources, timestamps, attribution, exclusions, null semantics | **PASS** |
| `FLOW14_REPORTING_SCOPE_MATRIX.md` | Role, permission, organizational scope, detail, export, and denial matrix | **PASS** |
| `flow14_manual_http_gate.json` | Sanitized real HTTP authorization, dashboard, drill-down, redaction, export, and ownership evidence | **30 / 30 PASS** |
| `flow14_performance_report.json` | Fixture size, query counts, timings, slowest query, and EXPLAIN evidence | **PASS** |
| `flow14_final_invariants.json` | Cross-artifact catalog, scope, read-only, export, HTTP, and performance invariants | **PASS** |
| `FLOW14_VERIFICATION.md` | This release report | **Synchronized** |

## Backend Implementation

FLOW-14 adds `report_exports` and immutable `report_export_audits` through MariaDB-compatible incremental migrations. The reporting permission migration creates dashboard, register, attendance, decision, Action Item, exception, technical, and export permissions. SYSTEM_ADMIN receives only the explicitly technical reporting permissions; the role does not receive organization business analytics by default.

`ReportingScopeResolver` evaluates active-user state, named permission, AMMS scoped roles, employee identity, authority relationship, and resource relationship. A requested unit outside the user’s permitted scope returns `403 REPORTING_SCOPE_DENIED`; it is not silently converted into an empty result. Report resources use allowlists and do not serialize storage paths, terminal credentials, terminal session tokens, biometric templates, fingerprint probes, or private evidence paths.

The API surface is additive under `/api/reporting`:

| Endpoint group | Contract |
|---|---|
| `/dashboard/executive` | Executive/high-authority scoped dashboard |
| `/dashboard/directorate` | Scoped Directorate dashboard |
| `/dashboard/personal` | Employee personal dashboard |
| `/dashboard/authority` | Authority-scoped dashboard |
| `/dashboard/technical` | Technical infrastructure aggregates only |
| `/meetings`, `/meeting-requests`, `/attendance`, `/decisions`, `/action-items`, `/exceptions` | Paginated read-only registers |
| `/high-authority-meetings` | Authority-context Meeting register |
| `/exports`, `/exports/{uuid}`, `/exports/{uuid}/download` | Queued private PDF/XLSX export lifecycle |

Queued export generation is idempotent under row locking. Export metadata contains definition version, filters, scope descriptor, status, timestamps, filename, size, and SHA-256 checksum. Files are stored privately and are streamed only after ownership, permission, status, expiry, and file-existence checks. Export audit records are append-only and metadata-only.

## Metrics and Temporal Semantics

The catalog is versioned as `AMMS_REPORTING_V1`. Date filters are interpreted as `[from 00:00, to + 1 day 00:00)` in the server-declared timezone. Zero is a measured zero; percentages become `null` when the denominator is zero. FLOW-13 invalidated attendance rows are excluded from official metrics. Finalized Decisions are read only from the authoritative current Minute Version of FINALIZED Minutes. FLOW-13 RESUMPTION remains separate from FLOW-07 rescheduling.

## Real HTTP Gate

The sanitized `flow14_manual_http_gate.json` records **30 passing checks**. It proves executive dashboard response metadata, server-defined metric cards, sensitive-field redaction, denial of business analytics to SYSTEM_ADMIN without the business permission, scoped Director access, Employee personal access, technical dashboard access, register behavior, allowlisted-sort enforcement, bounded-search enforcement, reversed-date rejection, queued PDF/XLSX creation, completion, private download, PDF parsing, XLSX package integrity, formula-tag absence, distinct export UUIDs, and cross-owner download denial.

The gate artifact contains no live bearer token. It stores only statuses, public export UUID presence, filenames, checksums, and sanitized outcomes.

## Performance Evidence

The performance artifact records actual clone-database fixture size, query counts, execution times, slowest captured query, and an EXPLAIN result for the measured path. The register query path satisfies the current no-N+1 gate of no more than twelve captured queries for the measured 50-row request. Performance evidence is a measured baseline for the current repository fixture, not a fabricated production SLA.

## Regression and Frontend Build

The existing Feature suite remains green after the additive implementation:

> **370 tests / 1,626 assertions — PASS**

The Persian/Dari RTL frontend production build remains green after adding the Reports Center, dashboards, drill-down register views, filters, status/empty/null states, responsive charts, and private export center:

> **1,866 modules transformed — PASS**

## Explicit Non-Goals

FLOW-14 does not modify frozen workflow meanings, rewrite source transactional records, infer qualitative employee or Directorate rankings, expose biometric material, expose terminal secrets, expose private storage paths, fabricate missing historical timestamps, or start FLOW-15. The reporting layer is a read-only observation and export boundary over authoritative AMMS state.
