# Sustainable Catalyst Library v5.6.1

**Release:** Homepage Research Network & Knowledge Discovery Console  
**WordPress plugin:** 5.6.1  
**Python backend:** 1.1.0 unchanged

## Added

- `[sc_library_homepage_console]` as a Library-owned public homepage component.
- `full`, `compact`, and `network` presentation modes.
- live public record, topic, and searchable-passage telemetry through the existing Explorer bootstrap.
- a bounded Research Network ticker driven by the existing connector/network registries.
- homepage capability signals for Research Librarian, public libraries, Open Courses, and provenance.
- homepage research-query handoffs into Knowledge search, Research Access, and Research Librarian.
- source-directory motion controls with hover/focus pause and reduced-motion support.

## Architecture

The homepage component does not duplicate the Research Library's institution registry. `SC_Library_Research_Network_Console` now exposes a governed public source registry and counts for reuse by public Library components.

University College Dublin remains a first-class direct research source through the existing UCD registry entry. MIT, Harvard, Yale, Princeton, Stanford, public libraries, archives, and scholarly systems retain their truthful capability classifications.

## Research Library page

No page replacement is required for v5.6.1. The current page baseline is the v5.6.0 R3.2.1 HTML with Three Research Front Doors removed.

## Backend

No backend deployment, database migration, Caddy change, DNS change, port change, API-key change, or reindex is required.
