# API Pagination Contract

Dynamic and growing list endpoints use Laravel length-aware pagination with
`page` (default `1`) and `per_page`. API clients must consume the top-level
`data`, `meta`, and `links` fields. Backend bounds are defensive and may be
lower than a client request.

Authoritative counters such as meeting `stats` and notification
`unread_count`/`total_count` are returned outside pagination `meta`. The
pagination `meta.total` value is the total for the filtered listing.

The following administrative and directory listings are paginated:

- meetings, meeting requests, notifications, employees, action items, report
  exports, terminals, organizations, organizational-unit administration,
  attendance records, biometric template history, delegation history,
  decision follow-up history, and reporting registers;
- incoming request inboxes and meeting workspace histories.

Meeting terminal assignment history is returned by the meeting terminal
workspace with the compatibility `assignments` array plus
`assignments_meta`, `assignments_links`, and `stats`; available terminals in
that workspace remain a bounded lookup snapshot.

Delegation history endpoints return a compatibility `delegations` array plus
`delegations_meta`, `delegations_links`, and authoritative unfiltered status
counts under `stats`. Attendance and biometric history follow the same
compatibility pattern with endpoint-specific metadata fields.

`GET /api/organizational-units` is intentionally retained as a bounded
hierarchical snapshot because its contract contains nested `children` and is
used to render the complete organization tree and selector metadata. It is
not a general-purpose unbounded employee or record listing. Administrative
unit management uses `GET /api/admin/organizational-units`, which is paginated.

List queries use a deterministic unique tie-breaker (normally `id`) after the
business sort columns so records do not move unpredictably between pages.

Embedded reference collections such as active organizational units, eligible
delegates, and available meeting terminals remain bounded lookup snapshots
(eligible delegates and available terminals are capped at 50 records);
they are not history/register listings and are filtered by the caller's
authorization scope.

The terminal device synchronization endpoint (`/api/assignments/current`)
is a protocol snapshot: it returns only currently valid assignments required
by the device, preserving its array response contract. It is intentionally
not a user-facing register pagination endpoint.
