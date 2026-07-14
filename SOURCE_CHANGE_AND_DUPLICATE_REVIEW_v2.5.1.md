# Source Change and Duplicate Review — v2.5.1

## Verification protection

When a verified Source record is edited, citation-critical fields are compared with the pre-save snapshot.

Critical fields include:

```text
title
personal and institutional authors
editors
year and publication date
source type
journal, book, publisher, and place
edition, volume, issue, pages, and chapter
report, standard, and jurisdiction identifiers
DOI, ISBN, PMID, URL, and archive URL
access date and language
```

When any of these fields changes, verification is cleared unless the editor checks:

```text
I rechecked citation-critical fields changed in this save.
```

The prior verification timestamp is preserved when verification is invalidated.

## Change history

Open a Source and locate **Metadata Change Review**.

Each retained history item shows:

- When the change occurred
- The WordPress user ID
- Whether it came from admin, REST, migration, or restore
- Which fields changed
- A restore control

The latest 20 structured metadata changes are retained.

## Snapshot restoration

Restoring a snapshot returns:

- WordPress title, excerpt, content, and publication status
- Authors and editors
- Institutional author fields
- Publication metadata
- Identifiers and URLs
- Attached source material
- Topics and source type
- Project relationships
- Private notes and provenance
- Verification and review fields

After restoration, the plugin rebuilds citation keys, author-year suffixes, normalized duplicate keys, project reverse indexes, and reliability status.

## Duplicate reconciliation

Open **Duplicate Reconciliation** on a Source record.

Decisions are review annotations rather than destructive operations:

```text
Same work
Alternate edition or version
Related but distinct work
Not a duplicate
Unreviewed
```

Choose a canonical Source when appropriate. The current release does not merge metadata or delete records automatically.

A `Not a duplicate` decision suppresses the pair during future duplicate-index rebuilding. DOI, ISBN, canonical URL, and author-year-title fingerprints continue to detect new unreviewed candidates.
