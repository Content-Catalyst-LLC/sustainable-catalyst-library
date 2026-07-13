# Sustainable Catalyst Library v1.19.0
## Preservation, Integrity, and Institutional Archive

This release adds an institutional preservation layer without changing WordPress's role as the canonical publishing system.

### Added

- Immutable record snapshots with stable UUIDs
- SHA-256 canonical-payload and manifest checksums
- Frozen HTML and normalized text editions
- Version chains and snapshot-to-snapshot comparison
- Downloadable preservation manifests
- Record-level institutional state, retention date, legal hold, and archive note
- Append-only documentation authority history
- Bounded resumable integrity audits
- Content-drift checks
- Attachment existence and checksum verification
- Authority URL and optional external-link checks
- Supersession-chain and relationship-endpoint diagnostics
- Public Institutional Archive shortcode
- Public integrity-status shortcode
- Archive and manifest REST routes in both Library namespaces
- Preservation and integrity webhook events
- PostgreSQL-ready preservation entities

### New WordPress tables

```text
wp_sc_library_preservation_snapshots
wp_sc_library_integrity_checks
wp_sc_library_authority_history
```

### New schemas

```text
sc-library-preservation/1.0
sc-library-preservation-manifest/1.0
sc-library-integrity-audit/1.0
sc-library-portable-export/2.0
```

### Data safety

- Canonical WordPress content is never replaced by a snapshot.
- Automatic snapshots are deduplicated by canonical payload checksum.
- Current and legal-hold snapshots are protected from retention cleanup.
- Public routes do not expose draft, private, deleted, or password-protected records.
- External-link checks remain disabled by default.
- API credentials, webhook secrets, private workspaces, and editorial comments are not included in preservation snapshots.
