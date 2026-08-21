# AMMS Documentation

AMMS documentation is separated into current release material and historical verification evidence.

## Current release

- [`release/RELEASE_BASELINE.md`](release/RELEASE_BASELINE.md) — authoritative frozen product baseline.

The current product scope is FLOW-01 through FLOW-14. FLOW-10B remains deferred pending real DERMALOG SVT3000 SE hardware and lawful SDK access. No FLOW-15 or other new business capability is authorized by the current baseline.

## Historical verification and audit evidence

`history/` contains the preserved architecture notes, verification reports, browser acceptance records, negative matrices, HTTP/MariaDB/concurrency evidence, build logs, generated verification artifacts, and earlier audit material.

These files are retained as evidence. Historical release-status wording is intentionally preserved rather than rewritten after later approvals.

The former top-level `evidence/` and `browser_failures/` directories now live under `history/`, which keeps historical relative links stable for documents moved from the repository root.

## Repository documentation policy

New current documentation should be placed under `docs/` rather than the repository root. The root should remain limited to the project entry point (`README.md`), configuration, and executable source directories.

Generated local logs, build output, temporary databases, screenshots, archives, and one-off debug artifacts should not be committed unless they are intentionally retained as release/audit evidence.
