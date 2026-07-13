# Sustainable Catalyst Library v1.19.0

Library v1.19.0 adds **Preservation, Integrity, and the Institutional Archive** to the complete v1.18.1 platform.

## Preservation layer

- Immutable snapshots of canonical WordPress records
- SHA-256 content and manifest checksums
- Version and supersession chains
- Public historical-edition browsing and comparison
- Downloadable preservation manifests
- Attachment and PDF checksum verification
- Authority-history capture
- Bounded record and relationship integrity audits
- Optional external-link checks with safe WordPress HTTP handling
- Record-level retention dates and legal holds
- Explicit cleanup that protects current and held snapshots

## Public interfaces

```text
[sc_library_institutional_archive]
[sc_library_integrity_status]
```

```text
/wp-json/sustainable-catalyst/v1/library/preservation/status
/wp-json/sustainable-catalyst/v1/library/archive
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}/manifest
/wp-json/sustainable-catalyst-library/v1/archive
```

Public routes return snapshots only while the canonical WordPress record remains public. Private or deleted records are not exposed.

## Portable data

Portable export schema:

```text
sc-library-portable-export/2.0
```

New entities:

```text
preservation_snapshots
integrity_checks
authority_history
```

## Installation

Upload `sustainable-catalyst-library-v1.19.0.zip` through WordPress and choose **Replace current with uploaded**. Open **SC Library → Preservation & Archive**, configure the archive page URL and retention policy, create a test snapshot, then start the bounded integrity audit.

See `PRESERVATION_ARCHIVE_SETUP_v1.19.0.md` and `RELEASE_NOTES_1.19.0.md`.
