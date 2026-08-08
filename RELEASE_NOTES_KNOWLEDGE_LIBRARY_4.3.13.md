# Sustainable Catalyst Knowledge Library v4.3.13

## Master Field Spotlight & Dynamic 14-Field Switching

v4.3.13 replaces the stacked public `[sc_field_spotlights]` presentation with a single shared editorial stage.

### Public experience

- All 14 major fields remain directly selectable.
- Only one complete Field Spotlight stage is presented at a time.
- Changing a field swaps the field identity, Article Map panel rail, Article Map hero, supporting publications, telemetry, and playback state in place.
- Desktop uses a compact four-column field index; mobile uses a native field selector.
- Field navigation supports mouse, touch, keyboard arrows, Home, and End.
- Article Map playback remains scoped to the active field.
- Each field still exposes its first eight panels before the additional-panel disclosure is opened.
- The active Article Map panel remains white with a restrained red state marker and square geometry.

### Compatibility

- `[sc_field_spotlights]` now renders the master 14-field experience.
- `[sc_field_spotlight field="global-governance"]` remains available for a standalone single-field surface.
- The dedicated v4.3.12 panel-content option is intentionally preserved, so existing hero copy and supporting-article selections remain intact.
- Homepage Spotlight is not modified.
- Publications v3 page HTML can remain unchanged; the same shortcode automatically receives the new master behavior.
