# Sustainable Catalyst Library v5.6.0
## Dynamic Library Explorer & Progressive Discovery

v5.6.0 turns the public Research Library from a long, multi-application page into a compact research front door backed by the hardened Python/PostgreSQL index.

### Public experience

- new Dynamic Library Explorer for `[sc_library mode="explorer"]` and bare `[sc_library]` usage;
- bounded initial bootstrap: metrics, facets, four recent/new records rather than the entire catalog;
- search with topic, type, source, year range, and sorting filters;
- 12-record default result windows with explicit **Load more**;
- shareable URL state for search and filters;
- progressive quick-view drawer instead of navigating or rendering full record detail immediately;
- related research and record timeline loaded only when their drawer tabs are opened;
- provenance and identifiers shown on demand;
- responsive/mobile layout and reduced-motion support;
- WordPress-local fallback when the Python service is unavailable.

### Page-length reduction

`RESEARCH_LIBRARY_PAGE_v5.6.0.html` is the recommended public page replacement. It removes the large set of inline private/research applications from the public front door and reduces the page source from the prior 500+ line layout to a focused Explorer plus compact handoffs to Research Librarian, Workspace, Lab, and Decision Studio.

All deeper Library functions remain available through their existing pages and shortcodes; they are no longer required to render simultaneously on the public Library landing page.

### Backend v1.1.0

- `GET /v1/explorer/bootstrap`;
- `/v1/search` adds topic, source, type, year-range, and explicit sort filters;
- facets add publication/update years;
- record search results include update/provenance metadata needed by the Explorer;
- `GET /v1/records/{record_id}?include_body=false` provides bounded progressive detail without returning the full body/chunk set;
- capabilities advertise dynamic explorer and progressive discovery.

### Compatibility and safety

- WordPress remains editorial/account/publication authority;
- existing v5.5 ingestion, adaptive batching, integrity audit, targeted repair, and pruning remain unchanged;
- no PostgreSQL migration;
- no Caddy/DNS change;
- localhost binding remains `127.0.0.1:8087` on `sc-internal`;
- explicit legacy shortcode modes remain available;
- append `?library_legacy=1` to the Library URL for an emergency local-catalog view.
