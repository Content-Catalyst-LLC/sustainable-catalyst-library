# Library v1.13.3 Large-Library Index Scanner Setup

## Open the scanner

```text
SC Library → Index Scanner
```

Direct administration path:

```text
/wp-admin/admin.php?page=sc-library-scanner
```

## First reconciliation after upgrading

1. Open **SC Library → Index Scanner**.
2. Select **Select recommended types**.
3. Keep **Save selected types as the Library index configuration** enabled.
4. Choose **Complete safe rebuild and reconciliation**.
5. Start with a batch size of **50**.
6. Select **Start cursor scan**.

For a site with more than 2,000 published posts, **Discovered published** should reflect the selected post types before the scan begins. The exact indexed total may be lower only when records are explicitly excluded, such as private Content Planner records.

## How discovery works

The scanner reads WordPress records directly from the posts table in ascending ID order:

```sql
SELECT ID, post_type
FROM wp_posts
WHERE post_status = 'publish'
  AND post_type IN (...)
  AND ID > last_cursor_id
ORDER BY ID ASC
LIMIT 50;
```

The actual WordPress table prefix is used automatically. This discovery path is not affected by `pre_get_posts`, theme archive settings, search filters, or front-end query limits.

## Completion accounting

A full scan is clean only when:

```text
Indexed + Explicitly excluded + Failed = Processed
```

The scanner reports **Complete with errors** when failures exist and **Incomplete** when the accounting does not reconcile. A clean full scan updates the Library’s last-full-index timestamp.

## Post-type discovery

The scanner automatically discovers:

- Posts
- Pages
- Public and publicly queryable custom post types
- Appropriate editorial custom post types with an administration interface
- Public Content Planner records when the planner is enabled

It excludes attachments, revisions, menus, templates, global styles, navigation internals, and other technical WordPress records.

## Scan modes

- **Complete safe rebuild and reconciliation** — walks every published record in the selected types, indexes eligible records, records exclusion reasons, and removes stale rows.
- **Missing and outdated records** — repairs eligible records that are absent or older than their WordPress source.
- **Missing records only** — indexes eligible records absent from the Library index.
- **Outdated records only** — refreshes eligible records modified after their last index timestamp.

## Pause, resume, and reset

The scanner saves a small cursor state after each batch. Closing the page does not lose progress.

- **Pause** stops automatic batch requests.
- **Resume** continues from the saved WordPress ID.
- **Cancel** preserves completed work and the audit report.
- **Reset scanner state** clears only the saved cursor and counters; it does not delete the Library index.

## Audit report

Select **Download full scan report** to export JSON containing:

- Scan configuration and counters
- Completion accounting
- Every processed post ID
- Post type
- Indexed, excluded, or failed outcome
- Exclusion or failure reason
- Diagnostics and recent scanner events

## Recommended interpretation

After a clean full reconciliation:

- **Discovered published** equals all published records in the selected post types.
- **Eligible records** equals discovered records minus explicit eligibility exclusions.
- **Indexed records** should closely match eligible records.
- **Missing records** should be zero.
- **Failed in last scan** should be zero.

The scanner remains independent of Render, PostgreSQL, workspaces, and document production.
