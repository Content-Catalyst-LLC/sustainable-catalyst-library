# Sustainable Catalyst Library v5.6.0 R3.1
## Interface CSS, Account Continuity & Open Courses Repair

R3.1 is a bounded repair over the R3 visible-research-network release. It preserves the R3 page hierarchy and complete Library capability contract while fixing the specific visual and information-density regressions identified in the rendered public page.

### Public interface repairs
- Repairs the Three Research Front Doors layout with an unequal desktop grid that gives Library Access and Research Librarian enough room for their real embedded applications.
- Adds strongly scoped component overrides so site-level/Astra link and button CSS cannot collapse or hide the R3 front-door controls.
- Converts Complete Library Capability Map `Open →` actions into explicit, keyboard-visible bordered controls.
- Keeps the bounded lazy-mounted capability workspace from R2/R3.

### Account Continuity
- Replaces the default long governance paragraph with a compact signed-in/signed-out status panel.
- Shows Private Research, My Libraries credential boundary, and Workspace shared-account state at a glance.
- Preserves the full governance explanation under `How account continuity works` using native `<details>` progressive disclosure.
- No account model, user-meta key, authentication path, or privacy boundary changes.

### Open Courses
- Replaces the redundant visible Research Flow band with a directly visible Open Courses section.
- Adds `mode="featured"` and `featured_limit` to `[sc_open_course_finder]` without changing standard mode.
- Featured discovery starts with MIT, Harvard, Yale, Princeton, Stanford, and University of Copenhagen courses.
- Search and filters expose the full verified launch catalog; `Explore all launch-catalog courses` removes the initial featured limit.
- The provider network remains available for discovery beyond the launch catalog.
- Legacy `#research-flow` and `#research-flow-title` anchors remain as compatibility targets.

### Preservation
- Original restored Library baseline remains 37 protected shortcodes and 72 protected anchors.
- University/library access, Public Library Network, Access Intelligence, Research Librarian, Knowledge Base, Knowledge Pathways, and Open Courses are Tier-1 directly visible experiences.
- No protected capability is removed.

### Infrastructure
- WordPress plugin version: `5.6.0.31`
- Python Library backend: `1.1.0` unchanged
- PostgreSQL migration: none
- Reindex required: no
- Caddy/DNS/port/API-key changes: none
- Site-wide CSS modification required: no
