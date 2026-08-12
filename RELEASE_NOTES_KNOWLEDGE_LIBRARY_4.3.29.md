# Release Notes — Sustainable Catalyst Library v4.3.29
## Saved Searches, Watchlists & Research Queue

### Added
- Private account-owned saved searches with repeatable query, scope, provider, constraint, and note fields.
- Passive watchlists for topics, queries, providers, sources, authors, institutions, collections, courses, and other research targets.
- Manual **Mark reviewed** state for watchlist items.
- Private research queue with type, priority, status, URL/reference, and notes.
- `[sc_research_continuity]` shortcode.
- Authenticated `/wp-json/sc-library/v1/research-continuity` current-user state endpoint.
- Stable action/filter boundaries for later Workspace handoff.

### Safety and truthfulness boundary
A v4.3.29 watchlist is a saved revisit list, **not** a background monitoring service. The module declares `background_monitoring => false` and `automatic_notifications => false`; it does not claim that providers are being polled when they are not.

### Account continuity
Saved searches, watchlists, and the research queue use the same Sustainable Catalyst/WordPress user identity as My Library, My Sources, My Libraries, course plans, research documents, and Workspace continuity. No second account or credential store is created.

### Compatibility
The release preserves v4.3.28 Personal Library Collections & Recommendations, v4.3.27 canonical routing, v4.3.26 Public Library Network, v4.3.25 institutional connectors, v4.3.24 Access Intelligence, v4.3.23 Research Document Builder, Citation Studio, Open Course Finder, and the v4.3.22.4 Publications stack.
