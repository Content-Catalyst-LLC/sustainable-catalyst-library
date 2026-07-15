# Archive Migration Guide — v3.6.0

## Purpose

v3.6.0 introduces new archival record types.

The migration normalizes records created during deployment, import, or staged testing.

## Stages

```text
Institutional Collections
Archive Components
Archive Accessions
```

## Collection migration

Missing values receive:

```text
UUIDv4
Draft status
Public access
Permanent retention
Initial preservation audit
```

Existing values are preserved.

## Component migration

Missing values receive:

```text
Series level
Public access
Not assessed preservation status
```

## Accession migration

Missing values receive:

```text
Legacy accession method
Received processing status
```

## Reliability

The migration uses:

- 20-record batches;
- stable post-ID cursors;
- 180-second lock;
- three independent step states;
- bounded failure history;
- hourly cron;
- AJAX;
- REST;
- WP-CLI.

## Resume

Interrupted installations can continue from the stored stage and cursor.

## Non-destructive behavior

The migration does not:

- delete archive records;
- change titles or slugs;
- make private posts public;
- remove restrictions;
- remove embargoes;
- create disposition actions;
- destroy files;
- alter existing Knowledge Library documents.
