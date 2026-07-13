# Sustainable Catalyst Library v1.13.1

## Index Scanner and Rebuild Reliability Patch

This patch restores index rebuilding as a visible, independent Library operation.

### Added

- Dedicated **SC Library → Index Scanner** administration screen
- Resumable, saved batch scans
- Full, missing-only, outdated-only, and repair scan modes
- Per-post-type candidate, indexed, missing, outdated, and freshness counts
- Single-record reindexing by WordPress ID or canonical URL
- Stale-record cleanup
- Relationship cleanup and relationship-aware reindexing
- Record-identifier and outdated-row repair
- Search-index and daily-reconciliation diagnostics
- Index-table and reconciliation-schedule repair
- Downloadable JSON scan logs
- Saved scan state that survives navigation or browser closure
- REST endpoints protected by `manage_options`

### Reliability changes

- The scanner has no dependency on Render, PostgreSQL, account workspaces, or document production.
- Public roadmap plans that no longer meet visibility rules are removed during stale cleanup.
- The legacy synchronous **Rebuild Library Index** action remains available as a no-JavaScript fallback.
- The synchronous rebuild now uses the same eligibility rules as the resumable scanner.
