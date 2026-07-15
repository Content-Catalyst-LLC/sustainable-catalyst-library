# Document Intelligence Migration — v3.7.0

## Purpose

The migration creates initial intelligence profiles for existing Knowledge Library documents.

## Defaults

Existing documents receive:

```text
Public intelligence disabled
Index exclusion disabled
```

The migration does not publish generated fields automatically.

## Batch behavior

```text
20 documents per batch
Stable post-ID cursor
180-second lock
Bounded failure history
Hourly cron
REST
AJAX
WP-CLI
```

## Stale documents

A document with an existing profile is marked Stale when the document is saved.

A daily task processes up to 10 documents with:

```text
Pending
Stale
Partial
Failed
```

## Excluded documents

Excluded documents are not analyzed by migration or stale reindexing.

## Resume

Interrupted migration resumes after the last processed post ID.

## Non-destructive behavior

The migration does not:

- modify document text;
- change publication status;
- replace PDF files;
- publish intelligence summaries;
- rewrite citations;
- change Topics or Concepts;
- delete old Knowledge Library data.
