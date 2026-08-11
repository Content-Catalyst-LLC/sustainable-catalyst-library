# Sustainable Catalyst Library v4.3.18.1
## Publications Field Spotlight Stack Recovery

Released: August 11, 2026

### Purpose
Restore the approved Publications architecture after the v4.3.13 master-field presentation reduced `[sc_field_spotlights]` to one shared field stage. The Publications page again renders all 14 major field surfaces while retaining the refined light editorial presentation and every newer v4.3.18 Research Access capability.

### Publications recovery
- `[sc_field_spotlights]` again renders all 14 major fields on the Publications page.
- Every field owns an independent Spotlight runtime, Article Map hero, panel rail, supporting-article stage, playback controls, and progressive disclosure.
- The first eight Article Map panels remain immediately exposed for each field.
- Panel 9+ remains behind `+ Explore additional fields`.
- Automatic 14-second rotation, Previous/Pause/Play/Next, keyboard navigation, swipe handling, reduced-motion behavior, and interaction holds are retained.
- The dedicated single-field shortcode `[sc_field_spotlight field="..."]` remains compatible.

### Visual continuity
- Does not restore the old dark rounded Field Spotlight presentation.
- Each stacked field uses the existing refined white/cream editorial master-stage surface, square geometry, restrained red active state, and thumbnail presentation.

### Persistence and compatibility
- Preserves `sc_library_field_spotlights_settings_v434`.
- Preserves the durable v4.3.12 panel-content store `sc_library_field_spotlight_panel_content_v4312`.
- No re-curation or content migration is required.
- Preserves all v4.3.18 scholarly Research Access connectors, university gateways, Research Librarian routing, Workspace confirmation boundaries, and other Library capabilities.

### Regression boundary
Catalyst Data v2.2.0 does not own or modify this presentation. Its front-end assets remain scoped to the Catalyst Data shortcode surface. This repair is entirely within Sustainable Catalyst Library's Field Spotlight renderer.
