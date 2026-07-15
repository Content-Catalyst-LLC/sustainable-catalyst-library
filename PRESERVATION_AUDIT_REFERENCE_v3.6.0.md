# Preservation Audit Reference — v3.6.0

## Audit inputs

The audit evaluates recorded archive metadata:

```text
Digital-object count
Checksum presence
Preservation states
Missing-object states
Retention review date
Legal-hold state
```

## Score

The score begins at 100 and deducts for:

- missing checksums;
- at-risk or critical objects;
- missing objects;
- overdue retention review.

The score is operational triage, not preservation certification.

## Status bands

```text
Stable     90–100
Monitor    70–89
At risk    40–69
Critical    0–39
```

## Checksum limitations

The plugin records checksums but does not fetch every external URI or recalculate every file checksum during the metadata audit.

Checksum verification requires access to the actual stored files.

## Recommended preservation workflow

- preserve original files;
- create strong checksums;
- verify checksums on a schedule;
- maintain redundant storage;
- monitor file formats;
- document migrations;
- preserve technical metadata;
- test restores;
- separate preservation and access copies;
- record missing or corrupt objects immediately.

## Legacy MD5

MD5 remains available only for legacy records.

Use SHA-256 or SHA-512 for new preservation records.

## Daily audit

WordPress cron audits up to 25 collections per scheduled run, prioritizing records with older audit timestamps.
