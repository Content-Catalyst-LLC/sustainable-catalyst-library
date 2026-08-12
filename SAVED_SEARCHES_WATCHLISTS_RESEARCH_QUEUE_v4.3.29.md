# Sustainable Catalyst Library v4.3.29
## Saved Searches, Watchlists & Research Queue

v4.3.29 adds a durable private research-continuity layer to the shared Sustainable Catalyst account.

### Saved Searches
Signed-in users can preserve a search label, query, scope, provider hint, filter/constraint notes, and private notes. Saved searches are instructions to repeat later; saving a search does not execute it automatically.

### Watchlists
Users can keep topics, queries, providers, sources, authors/creators, institutions, collections, courses, and other targets on a private revisit list. A user can mark a watchlist item reviewed to record the last manual review time.

**Watchlists are passive in v4.3.29.** They do not run background monitoring, poll external providers, create alerts, or send notifications. This prevents a saved preference from being misrepresented as live monitoring.

### Research Queue
Users can queue research questions, sources to review, searches to run, research tasks, courses, datasets, and other follow-up work. Queue records support priority and status (`queued`, `active`, `done`, `archived`).

### Privacy and account continuity
All three record families are stored as private WordPress user metadata and remain attached to the same Sustainable Catalyst account already used by Workspace and the private Library tools. No second Library account is introduced.

User-meta contracts:

- `sc_library_saved_searches_v4329`
- `sc_library_watchlists_v4329`
- `sc_library_research_queue_v4329`

### Public interface
Shortcode:

`[sc_research_continuity title="Saved Research & Queue"]`

Authenticated current-user API:

`/wp-json/sc-library/v1/research-continuity`

### Integration boundary
v4.3.29 exposes stable hooks for later cross-product handoff without automatically writing to Workspace:

- `sc_library_save_search`
- `sc_library_add_watchlist_item`
- `sc_library_enqueue_research_item`
- `sc_library_research_continuity_state`

### Preserved boundaries
v4.3.29 does not alter the v4.3.28 My Library storage model, the canonical `/knowledge-libraries/` public route, internal REST namespaces containing `/library/`, Public Library Network, institutional connectors, Access Intelligence, Citation Studio, Research Document Builder, Open Course Finder, or the v4.3.22.4 Publications field stack.
