# Sustainable Catalyst Library v4.3.7
## Spotlight-Parity Hero Scale, Autoplay & Eight-Panel Disclosure

Released: August 7, 2026

### Purpose
Align Major Field Spotlights with the established Homepage Spotlight interaction and visual scale.

### Public presentation
- Article Map remains permanent hero position 0, but now uses Homepage Spotlight lead-card scale rather than the oversized v4.3.5 block.
- Desktop Article Map thumbnail: 138 × 94 px; lead row minimum height: 132 px.
- Tablet/mobile hero thumbnails collapse to the same 82 × 62 px and 68 × 54 px scale used by Homepage Spotlight.
- Existing supporting-article thumbnails, metadata, summaries, manual selection, and KL fallbacks remain intact.

### Automatic rotation
- Automatic panel rotation is enabled by default.
- Default interval: 14 seconds.
- Previous, Pause/Play, position, and Next controls are retained.
- AUTO, HOLD, PAUSED, STATIC, and REDUCED MOTION states are exposed.
- Rotation pauses on hover, keyboard focus, touch interaction, hidden browser tabs, and reduced-motion preference.
- Swipe navigation is supported.
- A red progress line uses the same restrained timing language as Homepage Spotlight.

### Eight-panel disclosure
- Exactly the first eight visible panels are exposed in the opening state.
- Panel 9+ is available only through the + Explore additional fields control.
- While collapsed, autoplay and Previous/Next cycle only through the first eight panels.
- Expanding the additional tier includes every visible panel in rotation/navigation.
- Collapsing while an additional panel is active returns the Spotlight to the primary tier.

### Shortcodes
Existing shortcodes remain valid:

    [sc_field_spotlights]
    [sc_field_spotlight field="global-governance"]

Optional playback overrides:

    [sc_field_spotlight field="global-governance" autoplay="true" interval="14000" pause_on_hover="true"]

### Editorial integrity
- Article Map canonical destination remains registry-owned.
- Supporting articles remain manually selected only.
- Empty slots remain empty; no automatic backfill was introduced.
