# Library v1.13.2 Index Scanner Setup

## Open the scanner

After installing the plugin, open:

```text
SC Library → Index Scanner
```

Direct administration path:

```text
/wp-admin/admin.php?page=sc-library-scanner
```

## Recommended first scan

1. Confirm the expected post types are selected under **SC Library**.
2. Open **Index Scanner**.
3. Leave all configured post types selected.
4. Choose **Complete safe rebuild**.
5. Use a batch size of **50** initially.
6. Select **Start scan**.

The scanner saves progress after each batch. If the page closes or the browser disconnects, reopen the scanner and select **Resume**.

## Scan modes

- **Complete safe rebuild** — reindexes every eligible record and removes stale rows.
- **Missing and outdated records** — repairs only records detected as absent or older than WordPress content.
- **Missing records only** — adds eligible WordPress records absent from the index.
- **Outdated records only** — refreshes records whose indexed timestamp predates the WordPress modification time.

## Targeted repair

Enter a WordPress post ID or canonical URL to repair one record.

Additional actions:

- **Repair index schema**
- **Remove stale records**
- **Repair relationships**
- **Repair identifiers and outdated rows**

## Diagnostics

The scanner reports:

- Index-table availability
- Full-text index availability
- Daily reconciliation schedule
- Eligible and indexed totals
- Missing, outdated, stale, duplicate, and invalid records
- Per-post-type counts and last-indexed timestamps
- Sample records requiring attention

Use **Download scan log** to save a JSON diagnostic bundle.

## Fallback

The original synchronous rebuild remains under the main **SC Library** settings screen. It can be used if JavaScript is unavailable, although the resumable scanner is recommended for larger libraries.
