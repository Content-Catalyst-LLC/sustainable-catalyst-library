# Library v1.13.3 — Large-Library Index Discovery and Batch Reliability Patch

This patch replaces the v1.13.1–v1.13.2 scanner queue architecture with direct, cursor-based WordPress database discovery suitable for Libraries containing thousands of records.

## Corrections

- Removes the unbounded `WP_Query` candidate discovery path from resumable scanning.
- Stops storing the complete post-ID queue in a WordPress option.
- Discovers published records directly from the WordPress posts table.
- Uses `ID > last_cursor_id` batches with a bounded `LIMIT`.
- Prevents `pre_get_posts`, theme filters, and front-end query customizations from reducing scanner discovery.
- Adds automatic discovery of Posts, Pages, and appropriate editorial custom post types.
- Warns when recommended post types contain published content but are not configured.
- Adds “Select recommended types,” “Select all discovered,” and “Reset scanner state” controls.
- Persists selected post types as the Library index configuration when requested.
- Adds a normalized scan audit table containing every processed post ID, outcome, and reason.
- Records explicit exclusion reasons separately from failures.
- Adds processed/indexed/excluded/failed accounting and refuses to report a clean completion when the accounting does not reconcile.
- Protects records from other configured post types when scanning only a subset.
- Converts the synchronous fallback rebuild to cursor batches as well.

## Scan state

The scanner state schema is now:

```text
sc-library-index-scan/2.0
```

The state stores counters, selected post types, and the last processed WordPress ID. It does not contain a candidate queue.

## Audit report

The scan report schema is now:

```text
sc-library-index-scan-log/2.0
```

The downloadable JSON report contains every processed record for the current scan, including indexed, excluded, and failed outcomes.
