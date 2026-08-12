# Release Notes — Sustainable Catalyst Library v4.3.22.1
## Publications Core Runtime Recovery

v4.3.22.1 fixes the recurring production failure where Publications remains stranded on Global Governance even though the canonical 14-field / 170-Article-Map registry is intact.

### Corrected
- Cache-busts the original `[sc_publications]` CSS/JavaScript runtime, which had remained pinned to v4.3.3.
- Adds server-rendered fallback navigation for every Publications field and Article Map.
- Adds server-side query-state resolution with `sc_publications_field` and `sc_publications_map`.
- Keeps fallback navigation visible when JavaScript fails rather than presenting only the first server-rendered field.
- Adds a structural render-time integrity guard to the original Publications model.
- Fixes the standalone Field Spotlight DOM marker mismatch (`v4.3.13` template vs later JavaScript runtime).
- Aligns Field Spotlight master, single, JavaScript, and asset versions at v4.3.22.1.
- Adds a canonical `/publications/` safety net: if stale page content still calls only `[sc_field_spotlight field="global-governance"]`, the canonical Publications route renders the complete master field stack while standalone field embeds elsewhere remain unchanged.

### Preserved
- Canonical 14 fields and 170 Article Maps.
- v4.3.18.1 bounded visibility repair.
- v4.3.22 Citation Studio & Source Manager.
- Open Course Finder and Course Access Intelligence.
- Research Access, My Libraries, Digital Access Resolver, Research Librarian, Workspace, and Publications editorial content.

### Deployment
Upload the v4.3.22.1 plugin and clear page/CDN/browser caches. Neither the Research Library page nor the Publications page body needs to be replaced for this patch.
