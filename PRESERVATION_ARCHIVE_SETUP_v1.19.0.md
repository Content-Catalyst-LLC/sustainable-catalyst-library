# Sustainable Catalyst Library v1.19.0
## Preservation, Integrity, and Institutional Archive setup

### Purpose

The preservation layer records immutable, checksum-backed snapshots of public Library records while WordPress remains the canonical publishing system. A snapshot documents what a record said at a particular time; it does not replace or republish the current post.

### First installation test

1. Install `sustainable-catalyst-library-v1.19.0.zip` and choose **Replace current with uploaded**.
2. Confirm **Sustainable Catalyst Library 1.19.0** under Installed Plugins.
3. Open **SC Library → Preservation & Archive**.
4. Leave automatic snapshots enabled.
5. Set the default retention period to 10 years.
6. Enter the public archive page URL.
7. Create a WordPress page named **Institutional Archive** with:

```text
[sc_library_institutional_archive]
```

8. Publish the page.
9. In Preservation & Archive, enter the ID of one public Library record and create a frozen snapshot.
10. Download its manifest and confirm that it includes the record ID, canonical URL, SHA-256 checksums, attachment metadata, authority metadata, and creation time.

### Integrity audit

Start with the default batch size of 50. Each continuation processes a bounded set of indexed records or relationships and saves its cursor. The audit checks:

- Presence of a frozen snapshot
- Drift between current canonical content and the latest snapshot
- Local attachment existence and SHA-256 checksums
- Documentation authority URLs
- Superseded records with missing replacement records
- Missing relationship endpoints
- Optional external links when that setting is deliberately enabled

External-link checking is off by default because it adds network requests and can be slow on a large Library.

### Retention and legal holds

Each supported record has a **Preservation and Archive** meta box with:

- Institutional state
- Retention-until date
- Legal or institutional hold
- Archive note

Cleanup only removes snapshots that are all of the following:

- Not the current snapshot
- Past their retention date
- Not protected by a legal hold

Current snapshots and held snapshots cannot be removed by retention cleanup.

### Historical browsing

The archive page can show preserved, superseded, and archived editions. A snapshot page clearly identifies itself as a frozen historical edition and links to the current canonical record. Version chains include comparison links.

Focused archive:

```text
[sc_library_institutional_archive record="123"]
```

Public integrity summary:

```text
[sc_library_integrity_status]
```

### Privacy boundary

A snapshot is available publicly only while its canonical WordPress record remains published and not password protected. Private, draft, deleted, or password-protected records are not exposed by public archive routes.

### REST routes

```text
/wp-json/sustainable-catalyst/v1/library/preservation/status
/wp-json/sustainable-catalyst/v1/library/archive
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}/manifest
/wp-json/sustainable-catalyst/v1/library/preservation/diagnostics
/wp-json/sustainable-catalyst-library/v1/archive
```

The diagnostics route requires an administrator. Public archive routes return only public snapshots.

### Portable export

Use **SC Library → Portable Data Export → Preservation** to export:

```text
preservation_snapshots
integrity_checks
authority_history
```

Portable schema:

```text
sc-library-portable-export/2.0
```

Snapshot content and manifests are included. WordPress attachments remain external file references; their checksums and URLs are preserved.
