# AMMS Documentation

`docs/` contains only current, high-value project documentation. Historical implementation notes, verification transcripts, screenshots, logs, temporary acceptance artifacts, and superseded UI phase documents are intentionally not kept in the live documentation tree; they remain recoverable from Git history and CI records when needed.

## Current authoritative documentation

- [`release/RELEASE_BASELINE.md`](release/RELEASE_BASELINE.md) — frozen AMMS product/release baseline for FLOW-01 through FLOW-14. FLOW-10B remains deferred.
- [`../AGENTS.md`](../AGENTS.md) — workspace development directives and active rule charters.
- [`../.agents/rules/frontend-architecture-and-design.md`](../.agents/rules/frontend-architecture-and-design.md) — canonical frontend architecture and UI/UX rules.
- [`../.agents/rules/roles-permissions-and-scope.md`](../.agents/rules/roles-permissions-and-scope.md) — canonical authorization charter for roles, permissions, capabilities, scope, ownership, lifecycle, and flow authority.

## Documentation policy

Keep `docs/` intentionally small. Add a document here only when it is a current operational, architectural, release, security, deployment, or product reference that developers or operators are expected to consult repeatedly.

Do not commit one-off debug notes, generated logs, screenshots, browser-failure evidence, temporary test transcripts, superseded implementation plans, phase-completion notes, or duplicated verification records to `docs/`. Use Git history, pull requests, CI artifacts, test fixtures, or issue/PR discussions for historical evidence instead.

When a current document is superseded, update or replace the canonical document rather than accumulating another historical copy in `docs/`.
