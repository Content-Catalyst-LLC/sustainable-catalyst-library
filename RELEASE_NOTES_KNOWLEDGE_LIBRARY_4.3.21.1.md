# Sustainable Catalyst Library v4.3.21.1
## Publications Runtime Recovery & Multi-Panel Resilience

This patch repairs the recurring Publications failure in which the first Field Spotlight / Article Map panel renders but the remaining Publications surface cannot be reached.

### Root cause hardened
The Field Spotlight public runtime was still pinned to the historical `4.3.13` asset version. Later plugin releases could therefore continue serving a stale browser/CDN copy of `sc-library-field-spotlights.js`, while the server-rendered master template contained only the initial field stage. If that JavaScript was stale, blocked, or failed to initialize, the visible experience degraded to the first field/panel.

### Changes
- Bumps the Field Spotlight runtime asset version to `4.3.21.1` so WordPress, browser, proxy, and CDN caches request a fresh CSS/JavaScript URL.
- Adds a render-time Publications integrity guard. If the canonical multi-field/multi-panel registry is healthy but the public model collapses to one field or panel, the bounded v4.3.18.1 visibility/cache repair is rerun and the public model is rebuilt.
- Converts field and panel tab controls into real fallback links. JavaScript now progressively enhances these links into the shared-stage interface instead of being the only navigation path.
- Adds query-driven server-side field/panel selection so a fallback link reloads the requested field/panel correctly even without JavaScript.
- Adds a `noscript` presentation fallback so field and additional-panel navigation remains discoverable on mobile and desktop.
- Preserves all editorial titles, descriptions, order, hero copy, canonical Article Map URLs, curated supporting articles, and the durable v4.3.12 panel-content store.
- Preserves v4.3.21 Course Access Intelligence & Learning Pathways, Research Access, Research Librarian, Workspace, and the Research Library page.

### Deployment
This is a plugin-only patch. The Research Library page and Publications page body do not need replacement. After WordPress plugin upload, purge page/object/CDN caches and hard-refresh Publications once so the new `v4.3.21.1` Field Spotlight assets are fetched.
