# Sustainable Catalyst Library v4.3.22.2
## Publications Fail-Open Field Switching & Runtime Hardening

### Fixed
- Reworked core `[sc_publications]` field switching so native fallback navigation is cancelled only after a verified in-place switch.
- Applied the same fail-open rule to Article Map navigation.
- Applied verified fail-open field and panel switching to the Field Spotlight master runtime.
- Preserved modified-click/new-tab behavior for all link-based navigation.
- Delayed `.is-enhanced` activation until the initial core Publications stage successfully verifies.
- Added runtime state markers (`server`, `initializing`, `ready`, `fallback`) and failure diagnostics.
- Cache-busted both Publications runtimes to v4.3.22.2.
- Preserved server-authoritative query routes for selected fields and panels.

### Preserved
- Canonical 14-field / 170-Article-Map registry.
- v4.3.18.1 and v4.3.22.1 integrity/recovery guards.
- v4.3.22 Citation Studio and My Sources.
- Open Course Finder, learning pathways, Research Access, Research Librarian, Workspace, and institutional research infrastructure.

### Deployment
No Research Library or Publications page-body replacement is required. Update the plugin, purge cache layers, then hard-refresh Publications.
