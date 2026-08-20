# FLOW-14 Negative Matrix

## Release Position

> **FLOW-14 remains VERIFICATION COMPLETE — PENDING EXPLICIT USER APPROVAL.** This matrix records denial and boundary behavior; it does not approve or freeze FLOW-14 automatically.

The negative contract is intentionally additive. It does not change the meaning of FLOW-01 through FLOW-13, does not enable physical DERMALOG SVT3000 SE integration, and does not begin FLOW-15.

| ID | Boundary under test | Expected result | Evidence or implementation contract |
|---|---|---|---|
| A | Unauthenticated dashboard request | `401` | Active-user requirement in `ReportingScopeResolver` |
| B | Inactive user dashboard request | `401` | `requirePermission()` rejects inactive users |
| C | Missing dashboard permission | `403` | Named reporting permission is mandatory |
| D | SYSTEM_ADMIN executive business dashboard | `403` | Technical administrator is excluded from business analytics |
| E | SYSTEM_ADMIN technical dashboard without technical permission | `403` | Technical permission remains explicit |
| F | Director requests unrelated Directorate unit | `403 REPORTING_SCOPE_DENIED` | Unit filter is rejected, not silently emptied |
| G | Director requests own scoped unit | `200` | AMMS role scope and permission both pass |
| H | Employee requests organization-wide unit filter | `403` | Personal scope cannot be widened by query parameters |
| I | Employee personal dashboard without unit filter | `200` | Employee identity constrains visible resources |
| J | High-authority register without required permission | `403` | Register permission remains independent |
| K | Other user views an export they do not own | `403` | Exact `requested_by_user_id` ownership is enforced |
| L | Other user downloads an export they do not own | `403` | Download policy includes ownership |
| M | Owner downloads a pending export | `403` | Download requires `COMPLETED` status |
| N | Owner downloads an expired export | `403` | Download requires future-or-null `expires_at` |
| O | Expiry-first download after storage deletion | `403` before file lookup | Policy denies expired artifact |
| P | Missing private export file after valid metadata | `404` | Controller checks private storage existence |
| Q | Export request with unsupported report type | `422` | Allowlisted report types only |
| R | Export request with unsupported format | `422` | PDF/XLSX allowlist only |
| S | Export request with invalid date range | `422` | Half-open date range validator rejects reversal |
| T | Export request with oversized page/search input | `422` | Bounded filter contract |
| U | Arbitrary SQL sort expression | `422` | Allowlisted sort fields only |
| V | Invalid attribution mode | `422` | `INVOLVED_UNIT`, `REQUESTING_UNIT`, and `PARTICIPATING_UNIT` only |
| W | Sensitive API resource field request | Not serialized | Resources are server-side allowlists |
| X | Storage path or private checksum in API metadata | Not serialized | `ReportExport` hides private storage fields |
| Y | Terminal credentials/session tokens in business analytics | Not serialized | Technical and business boundaries are separate |
| Z | Biometric templates or fingerprint probes in reports | Not serialized | FLOW-10B remains outside business analytics |
| AA | Formula-like XLSX cell beginning with `=` | Inert text with apostrophe prefix | `safeCell()` neutralization; no `<f>` tags |
| AB | Formula-like XLSX cell beginning with `+` | Inert text with apostrophe prefix | Same spreadsheet injection boundary |
| AC | Formula-like XLSX cell beginning with `-` | Inert text with apostrophe prefix | Same spreadsheet injection boundary |
| AD | Formula-like XLSX cell beginning with `@` | Inert text with apostrophe prefix | Same spreadsheet injection boundary |
| AE | PDF export with Persian/Dari RTL content | Rendered readable RTL page | mPDF RTL visual gate passed |
| AF | Export generation job dispatched twice | One authoritative artifact | Row lock and completed-artifact idempotency |
| AG | Export retry after `COMPLETED` | Existing artifact returned | Manager short-circuits completed row |
| AH | Export retry after `EXPIRED` | No regeneration | Expired terminal state is preserved |
| AI | Export audit update through normal model API | `LogicException` | Audit model blocks update/delete |
| AJ | Dashboard or register read | Source rows unchanged | Source immutability hash before/after matched |
| AK | Superseded Minute Version decision | Excluded | Decision query joins authoritative current finalized version only |
| AL | Invalidated attendance record | Excluded from official attendance | Official attendance resolver and invalidation relation |
| AM | FLOW-13 `RESUMPTION` event | Counted as its own event | Event history uses applied event type, not current status or reschedule |
| AN | Zero-denominator percentage | `null`, not `NaN` or fabricated zero | Metric catalog null semantics |

## Observed Gate Results

The matrix is backed by the exact HTTP gate, canonical metric feature suite, MariaDB consistency probes, 50-row N+1/EXPLAIN gate, export strict gate, and source-immutability artifact. A successful denial is treated as a security invariant, not as a UI-only behavior.

| Evidence artifact | Result |
|---|---:|
| `flow14_exact_http_metric_gate.json` | **PASS** |
| `flow14_mariadb_consistency_probe_ab.json` | **PASS** |
| `flow14_mariadb_export_consistency_probe_cd.json` | **PASS** |
| `flow14_mariadb_nplus1_explain_gate.json` | **PASS** |
| `flow14_export_strict_gate.json` | **PASS** |
| `flow14_export_invariants.json` | **PASS** |

The final release status remains explicitly pending user approval.
