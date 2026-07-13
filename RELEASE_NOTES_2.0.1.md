# Release notes — Sustainable Catalyst Library v2.0.1

## Unified Discovery Interface Repair

v2.0.1 restores the native topics, relationships, and pathways interface as a first-class plugin component. The repair removes dependence on page-specific layout CSS and preserves the transitional public-page model in which dynamic discovery appears above the Library search/results experience while the complete manual Browse by Topic architecture remains available below.

### Added

- `sc-library-discovery/1.0` public response contract.
- `/wp-json/sustainable-catalyst/v1/library/discovery`.
- Plugin-owned `sc-library-discovery.css`.
- Live domain/topic, series/concept, and pathway counts.
- Retryable loading and error states.
- Server-rendered pathway fallback and REST hydration.
- Aggregate discovery loading with legacy endpoint fallback.
- Selected nested-topic ancestor expansion.
- ARIA busy and pressed states.

### Repaired

- Topic grid collapse caused by external site CSS.
- Relationship browser chip overflow and narrow-column behavior.
- Featured pathway cards forced into tall or unusually narrow layouts.
- Blank discovery panels after REST failures.
- Ambiguous discovery loading state.

### Compatibility

- No database migration.
- No Library index rebuild required.
- Existing shortcode parameters remain valid.
- Existing REST endpoints remain valid.
- All v2.0.0 Living Knowledge System and retained v1.x modules remain present.
