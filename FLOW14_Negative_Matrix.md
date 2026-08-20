# FLOW-14 Negative Matrix and Boundary Contract

## Release Position

> **FLOW-14 remains VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** This matrix records denial, privacy, temporal, export, concurrency, and read-only boundaries. It does not approve or freeze FLOW-14 automatically.

The matrix is strictly additive to reporting. It does not redesign FLOW-01 through FLOW-13, does not enable physical DERMALOG SVT3000 SE integration, and does not begin FLOW-15. Every row below identifies a runtime or artifact-level verification rather than relying only on an implementation statement.

| ID | Boundary | Expected result | Evidence type | Artifact/reference | Result |
|---|---|---|---|---|---:|
| A | Unauthenticated dashboard request | `401` | Runtime HTTP gate | `flow14_manual_http_gate.json` authentication checks | **PASS** |
| B | Inactive user dashboard request | `401` | Runtime authorization gate | `flow14_manual_http_gate.json` active-user boundary | **PASS** |
| C | User without the named dashboard permission | `403` | Runtime HTTP gate | `flow14_manual_http_gate.json` permission denial | **PASS** |
| D | `SYSTEM_ADMIN` requests business executive analytics | `403` | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` SYSTEM_ADMIN business denial | **PASS** |
| E | Authority Office requests organization-wide business analytics | `403` | Runtime scope gate | `flow14_exact_http_metric_gate.json` authority-office organization boundary | **PASS** |
| F | Director requests an unrelated Directorate | `403 REPORTING_SCOPE_DENIED` | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` director unrelated-unit denial | **PASS** |
| G | Director requests the explicitly scoped own Directorate | `200` with only scoped rows | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` own-unit authorization | **PASS** |
| H | Employee adds an organization-wide unit filter to a personal request | `403`; query cannot widen identity scope | Runtime HTTP gate | `flow14_exact_http_metric_gate.json` employee organization denial | **PASS** |
| I | Scope-filter bypass through `unit_id`, attribution, or alternate register route | `403` or constrained result; no unauthorized rows | Runtime HTTP and feature gates | `flow14_exact_http_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| J | Arbitrary SQL sort expression | `422` | Runtime validation gate | `flow14_manual_http_gate.json` sort allowlist check | **PASS** |
| K | Search/filter injection payload | `422` or literal bounded search; no query execution | Runtime validation gate | `flow14_manual_http_gate.json` filter contract | **PASS** |
| L | Unauthorized export view/download by another user | `403` | Runtime export-policy gate | `flow14_export_strict_gate.json`; `flow14_mariadb_true_export_race_probe.json` | **PASS** |
| M | Public export URL or direct unauthenticated storage access | No public artifact; authenticated private download only | Runtime storage/policy gate | `flow14_export_strict_gate.json` private-storage checks | **PASS** |
| N | Expired export download | `403`; no file served | Runtime expiry gate | `flow14_export_strict_gate.json`; true expiry race artifact | **PASS** |
| O | Duplicate export workers | One export UUID, one checksum/path, one authoritative artifact, one start audit, one completion audit | True two-process MariaDB race | `flow14_mariadb_true_export_race_probe.json` probe C | **PASS** |
| P | Terminal credentials, session tokens, or private storage metadata in business reports | Not serialized | Runtime resource/XLSX/PDF inspection | `flow14_export_strict_gate.json`; allowlisted reporting resources | **PASS** |
| Q | Biometric templates, fingerprint probes, or DERMALOG hardware fields in business analytics | Not serialized; FLOW-10B remains deferred | Runtime resource inspection | `flow14_export_strict_gate.json`; FLOW-10B boundary checks | **PASS** |
| R | Frontend formula duplication or unsafe formula-like export cell | Inert text; no executable formula; no duplicated formula-derived content | XLSX XML inspection and frontend build | `flow14_export_strict_gate.json`; `flow14_frontend_build.txt` | **PASS** |
| S | FLOW-14 business mutation through dashboard/register/export endpoints | Read-only reporting; no source business row mutation | Source immutability artifact | `flow14_source_immutability.json` | **PASS** |
| T | FLOW-15 or physical FLOW-10B boundary crossing | No route, code path, or claim starts either scope | Repository and release audit | `FLOW14_VERIFICATION.md`; repository diff | **PASS** |
| U | Invalidated attendance included in official attendance | Invalidated row excluded; `invalidated_attendance_counted_official = 0` | Runtime metric and drill-down gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| V | Superseded Minute Version decision included as finalized | Excluded; only authoritative current finalized version counts | Runtime query/feature gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| W | `CANCELLED` Action Items included in completion denominator | Excluded; denominator remains 8 and completion rate is `37.5%` | Runtime exact metric gate | `flow14_exact_metric_gate.json` | **PASS** |
| X | `COMPLETED` Action Items counted as overdue | Excluded from overdue set | Runtime drill-down gate | `flow14_exact_metric_gate.json`; Action Item register | **PASS** |
| Y | `POSTPONED` meetings counted as upcoming | Excluded; upcoming is status- and current-schedule-based | Runtime exact metric gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| Z | `CANCELLED` meetings counted as conducted | Excluded; conducted requires `HELD`/`COMPLETED` plus ended execution | Runtime exact metric gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |
| AA | Formula injection with cells beginning `=`, `+`, `-`, or `@` | Apostrophe-prefixed inert inline text; no `<f>` formula nodes | Raw XLSX XML inspection | `flow14_export_strict_gate.json`; `flow14_export_invariants.json` | **PASS** |
| AB | Timezone and half-open date-boundary behavior | `from <= timestamp < toExclusive`; no boundary leakage across timezone conversion | Runtime feature and exact metric gate | `Flow14ReportingTest.php`; `flow14_exact_metric_gate.json` | **PASS** |
| AC | Dashboard card versus drill-down/register consistency | Cancelled, overdue, decision, and official-attendance totals agree exactly | Runtime consistency proof | `flow14_exact_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AD | Duplicate finalized Decision or superseded duplicate Decision | `duplicate_finalized_decision_count = 0`; superseded V1 excluded | Runtime database invariant | `flow14_final_metric_invariants.json`; `Flow14ReportingTest.php` | **PASS** |
| AE | Duplicate Meeting identity | `duplicate_meeting_count = 0`; nine canonical rows have unique primary key and meeting number | Runtime canonical fixture invariant | `flow14_canonical_fixture.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AF | Zero-denominator percentage | `null`, never `NaN`, fabricated zero, or divide-by-zero output | Runtime metric contract test | `Flow14ReportingTest.php`; `flow14_final_metric_invariants.json` | **PASS** |
| AG | Invalid attendance detail privacy | Invalidated attendance may be counted for audit but is excluded from official numerator and sensitive detail is allowlisted | Runtime resource and metric gate | `flow14_export_strict_gate.json`; `flow14_exact_metric_gate.json` | **PASS** |
| AH | Evidence path leakage | Private storage paths/checksums are not exposed through business API resources | Runtime resource/XLSX/PDF inspection | `flow14_export_strict_gate.json`; `flow14_export_invariants.json` | **PASS** |
| AI | Technical dashboard leaks business content, or business dashboard leaks terminal content | Technical and business analytics remain separate; no terminal secret fields | Runtime HTTP/resource gate | `flow14_exact_http_metric_gate.json`; `flow14_export_strict_gate.json` | **PASS** |
| AJ | Role-scope query bypass through a direct register or alternate attribution mode | Unauthorized scope rows remain zero | Runtime authorization and invariant counter | `flow14_exact_http_metric_gate.json`; `flow14_final_metric_invariants.json` | **PASS** |
| AK | Export worker scope bypass | Export worker uses persisted owner scope and cannot broaden filter payload | Runtime export ownership/scope gate | `flow14_export_strict_gate.json`; true export race artifact | **PASS** |
| AL | Cache scope mixing | No shared cross-user reporting cache; each request resolves current authenticated scope | Runtime request isolation and architecture audit | `flow14_exact_http_metric_gate.json`; controller/service code | **PASS** |
| AM | Concurrent snapshot consistency | Dashboard is entirely before or entirely after the committed mutation; no hybrid cancellation/attendance or completion/status view | True two-process MariaDB race | `flow14_mariadb_true_concurrency_probe.json` probes A/B | **PASS** |
| AN | Historical event timestamp semantics | FLOW-13 event counts use `applied_at`/historical event rows; `RESUMPTION` is not FLOW-07 reschedule and current status is not substituted | Runtime event-history feature gate | `flow14_exact_metric_gate.json`; `Flow14ReportingTest.php` | **PASS** |

## Required Runtime Counter Set

The final machine-readable invariant artifact must expose explicit counters rather than replacing them with only aggregate booleans. Required zero-value security and duplication counters are `duplicate_meeting_count`, `duplicate_finalized_decision_count`, `invalidated_attendance_counted_official`, `cancelled_action_items_in_completion_denominator`, `terminal_secret_exposure_count`, `biometric_sensitive_field_exposure_count`, and `unauthorized_scope_row_count`.

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
