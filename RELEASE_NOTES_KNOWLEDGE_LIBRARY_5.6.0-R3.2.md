# Sustainable Catalyst Library v5.6.0 R3.2

## Rendered Interface, Pathways & Action Visibility Repair

R3.2 is a bounded public-interface repair over R3.1. It does not remove Library capabilities and does not change the Python/PostgreSQL backend.

### Repairs
- Replaces nested mini-applications inside **Three Research Front Doors** with three clean navigation surfaces: Knowledge Base, Library Access, and Research Librarian.
- Enqueues the Library public-interface CSS explicitly on `/knowledge-libraries/` so the page does not depend on shortcode-time asset loading.
- Adds last-resort visibility fallbacks to Knowledge Explorer topic controls, Filters/Reset controls, and Complete Library Capability Map **Open →** actions.
- Rebuilds **Knowledge Pathways** as a responsive two-column research index with eight question routes and a distinct field directory.
- Removes browser-generated list numbering/marker artifacts from Knowledge Pathways.
- Keeps Account Continuity as a compact utility strip with expandable governance detail.
- Keeps Open Courses directly visible and preserves the legacy Research Flow deep-link anchors for compatibility.

### Preservation
- 37/37 protected original Library shortcodes preserved.
- 72/72 protected original anchors preserved or compatibility-routed.
- University College Dublin remains a direct visible research source.
- MIT, Harvard, Yale, Princeton, Stanford, Columbia, Berkeley, Copenhagen, Stockholm, Oxford, Cambridge and the wider institutional network remain visible.
- Public Library Network and local-library discovery remain directly visible.

### Backend
Python backend remains **v1.1.0**. No database migration, reindex, DNS, Caddy, port, or API-key change is required.
