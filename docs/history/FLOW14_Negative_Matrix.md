# FLOW-14 Negative Matrix and Boundary Contract

## Release Position

> **FLOW-14 remains VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** This matrix records denial, privacy, temporal, export, concurrency, and read-only boundaries. It does not approve or freeze FLOW-14 automatically.

The matrix is strictly additive to reporting. It does not redesign FLOW-01 through FLOW-13, does not enable physical DERMALOG SVT3000 SE integration, and does not begin FLOW-15. The original Prompt-14 IDs **A–Z and AA–AN are preserved exactly**. Additional useful authentication checks are placed after AN and do not redefine the original contract.

| ID | Boundary | Expected result | Evidence type | Artifact/reference | Result |
|---|---|---|---|---|---:|
| A | SYSTEM_ADMIN without business permission requests business analytics | Denied | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` SYSTEM_ADMIN business denial | **PASS** |
| B | Director requests another Directorate | Denied with `403 REPORTING_SCOPE_DENIED` | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` unrelated-unit denial | **PASS** |
| C | Employee requests an organization-wide report | Denied; personal scope cannot widen | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` employee organization denial | **PASS** |
| D | Authority Office assumes organization-wide scope | Denied | Runtime scope gate | `flow14_exact_http_metric_gate.json` Authority Office boundary | **PASS** |
| E | Invalidated attendance counted as official | Impossible; invalidated row excluded from official numerator | Runtime metric and drill-down gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| F | Superseded Minutes Decision double-counted | Impossible; only authoritative current finalized version counts | Runtime query/feature gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| G | `CANCELLED` Action Item included in completion denominator | Impossible; cancelled items excluded | Runtime exact metric gate | `flow14_exact_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| H | `COMPLETED` Action Item counted overdue | Impossible; terminal completed items excluded | Runtime register/drill-down gate | `flow14_exact_metric_gate.json`; Action Item register | **PASS** |
| I | `POSTPONED` Meeting counted upcoming | Impossible; upcoming requires `SCHEDULED` and current schedule | Runtime exact metric gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| J | `CANCELLED` Meeting counted conducted | Impossible; conducted requires `HELD`/`COMPLETED` and ended execution | Runtime exact metric gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| K | Historical Schedule Version used as current upcoming schedule | Impossible; current schedule relation is authoritative | Runtime feature and register gate | `Flow14ReportingTest.php`; `flow14_exact_metric_gate.json` | **PASS** |
| L | FLOW-13 `RESUMPTION` counted as FLOW-07 reschedule | Impossible; event types and histories remain distinct | Runtime event-history gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| M | Cross-unit Meeting double-counted in organization distinct total | Impossible; organization total uses distinct Meeting identity | Runtime canonical invariant | `flow14_canonical_fixture.json`; `flow14_final_metric_invariants.json` | **PASS** |
| N | `INVOLVED_UNIT` grouped counts assumed equal to organization total | Attribution must be disclosed; grouped counts are not silently presented as organization total | Runtime attribution gate | `flow14_exact_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| O | Unscoped unit-filter bypass | Denied or returns only authorized rows; no unauthorized scope rows | Runtime HTTP and invariant gate | `flow14_exact_http_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| P | Arbitrary sort SQL injection | Denied with validation error | Runtime validation gate | `flow14_manual_http_gate.json` sort allowlist check | **PASS** |
| Q | Search SQL injection | Safe literal/bounded search; no query execution | Runtime validation gate | `flow14_manual_http_gate.json` filter contract | **PASS** |
| R | Unauthorized export download | Denied | Runtime export-policy gate | `flow14_export_strict_gate.json`; `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| S | Public export URL | Impossible; artifacts remain private | Runtime storage/policy gate | `flow14_export_strict_gate.json`; `flow14_export_invariants.json` | **PASS** |
| T | Expired export download | Denied; no file served | True application race | `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| U | Duplicate export job creates conflicting artifacts | Impossible; one UUID/checksum/path and one authoritative artifact | True two-process MariaDB race | `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| V | Terminal token exposed | Impossible; no terminal secret in business analytics | Runtime resource/export inspection | `flow14_export_strict_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| W | Biometric template exposed | Impossible; biometric material is outside business reporting | Runtime resource/export inspection | `flow14_export_strict_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| X | React recomputes official business metric | Impossible; server-defined cards and metrics are authoritative | Frontend build and architecture inspection | `flow14_frontend_build.txt`; `FLOW14_VERIFICATION.md` | **PASS** |
| Y | FLOW-14 mutates frozen business state | Impossible; dashboard/register/export reads are read-only | Source immutability artifact | `flow14_source_immutability.json`; `flow14_export_strict_gate.json` | **PASS** |
| Z | FLOW-15 or FLOW-10B implementation begins | Must not occur | Repository/release audit | `FLOW14_VERIFICATION.md`; final repository diff | **PASS** |
| AA | Formula injection | Formula-like values are apostrophe-prefixed inert text; no `<f>` nodes | Raw XLSX XML inspection | `flow14_export_strict_gate.json`; `flow14_export_invariants.json` | **PASS** |
| AB | Date/timezone boundary | Half-open interval is applied consistently: `from <= timestamp < toExclusive` | Runtime feature and exact metric gate | `Flow14ReportingTest.php`; `flow14_exact_metric_gate.json` | **PASS** |
| AC | Dashboard ↔ drill-down mismatch | Cancelled, overdue, Decision, and official-attendance totals agree exactly | Runtime consistency proof | `flow14_exact_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AD | Duplicate finalized Decision | `duplicate_finalized_decision_count = 0`; superseded V1 excluded | Runtime database invariant | `flow14_final_metric_invariants.json`; `Flow14ReportingTest.php` | **PASS** |
| AE | Duplicate Meeting | `duplicate_meeting_count = 0`; canonical identities are unique | Runtime canonical invariant | `flow14_canonical_fixture.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AF | Empty denominator rendered as `0%` | `null`, never fabricated `0%`, `NaN`, or divide-by-zero output | Runtime metric contract test | `Flow14ReportingTest.php`; `flow14_final_metric_invariants.json` | **PASS** |
| AG | Raw invalid attendance privacy | Raw audit count may exist, but invalidated attendance is excluded from official count and sensitive detail is allowlisted | Runtime resource and metric gate | `flow14_exact_metric_gate.json`; `flow14_export_strict_gate.json` | **PASS** |
| AH | Evidence/private path leakage | Private storage paths and evidence internals are not exposed in business API resources | Runtime resource/export inspection | `flow14_export_strict_gate.json`; `flow14_export_invariants.json` | **PASS** |
| AI | Technical dashboard leaks business content | Technical and business dashboards remain separate; no terminal/business secret leakage | Runtime HTTP/resource gate | `flow14_exact_http_metric_gate.json`; `flow14_export_strict_gate.json` | **PASS** |
| AJ | Reporting query bypasses AMMS scope | Unauthorized scope rows remain zero | Runtime authorization and invariant counter | `flow14_exact_http_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AK | Export worker bypasses requesting scope | Worker uses persisted owner scope and cannot broaden filter payload | Runtime export ownership/scope gate | `flow14_export_strict_gate.json`; `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| AL | Report cache mixes scopes | No cross-user scope cache is used; each request resolves current authenticated scope | Runtime request isolation and architecture inspection | `flow14_exact_http_metric_gate.json`; Reporting service | **PASS** |
| AM | Concurrent snapshot inconsistency | Dashboard is entirely before or entirely after the committed mutation; no hybrid snapshot | True two-process MariaDB race | `flow14_mariadb_true_concurrency_probe.json` probes A/B | **PASS** |
| AN | Historical trend reconstructed from current status | Historical event metrics use applied event timestamps and event types, not current status | Runtime event-history feature gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |

## Supplemental Authentication and Lifecycle Matrix

These rows are intentionally supplemental and do not redefine the original A–AN contract.

| ID | Boundary | Expected result | Evidence type | Artifact/reference | Result |
|---|---|---|---|---|---:|
| AO | Unauthenticated dashboard request | `401` | Runtime HTTP gate | `flow14_manual_http_gate.json` | **PASS** |
| AP | Inactive user dashboard request | `401` | Runtime authorization gate | `flow14_manual_http_gate.json` | **PASS** |
| AQ | Missing reporting permission | `403` | Runtime HTTP gate | `flow14_manual_http_gate.json` | **PASS** |
| AR | Pending export download | `403` | Runtime export-policy gate | `flow14_manual_http_gate.json`; `flow14_export_strict_gate.json` | **PASS** |
| AS | Audit update/delete through normal API | `LogicException`; audit remains immutable | Runtime model gate | `flow14_export_strict_gate.json` | **PASS** |
| AT | Unsupported report type/format or invalid date range | `422` | Runtime validation gate | `flow14_manual_http_gate.json` | **PASS** |
| AU | Zero-denominator percentage safety | `null` | Runtime metric contract gate | `Flow14ReportingTest.php` | **PASS** |

## Required Runtime Counter Set

The final machine-readable invariant artifact exposes explicit counters rather than replacing them with aggregate booleans. Required zero-value counters are `duplicate_meeting_count`, `duplicate_finalized_decision_count`, `invalidated_attendance_counted_official`, `cancelled_action_items_in_completion_denominator`, `terminal_secret_exposure_count`, `biometric_sensitive_field_exposure_count`, and `unauthorized_scope_row_count`.

The drill-down invariants are `executive_cancelled_card_equals_drilldown_total`, `executive_overdue_card_equals_drilldown_total`, `decision_card_equals_drilldown_total`, `attendance_summary_equals_official_resolver`, and `unit_attribution_mode_declared`. The export counters are `public_export_url_count`, `unauthorized_export_download_count`, `duplicate_completed_artifact_count`, `export_sensitive_field_exposure_count`, `formula_injection_vulnerability_count`, and `expired_export_download_success_count`.

## Evidence Status

| Evidence artifact | Required status |
|---|---:|
| `flow14_canonical_fixture.json` | **PASS** |
| `flow14_exact_http_metric_gate.json` | **PASS** |
| `flow14_mariadb_true_concurrency_probe.json` | **PASS** |
| `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| `flow14_mariadb_nplus1_explain_gate.json` | **PASS** |
| `flow14_export_strict_gate.json` | **PASS** |
| `flow14_export_invariants.json` | **PASS** |
| `flow14_source_immutability.json` | **PASS** |

The release position remains **VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL**. FLOW-01 through FLOW-13 remain frozen, FLOW-10B remains deferred, and FLOW-15 remains unstarted.
