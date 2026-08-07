# Sustainable Catalyst Library v4.3.8
## Field Spotlight Visual Refinement, Editorial Density & Control Simplification

Released: August 7, 2026

### Purpose
Refine the Major Field Spotlight presentation without changing its content architecture or interaction model. v4.3.8 keeps the v4.3.7 Homepage Spotlight-parity Article Map scale, automatic rotation, thumbnails, manual curation, and fixed eight-panel disclosure while reducing visual chrome around those elements.

### Visual refinement
- Removes the decorative masthead gradient and reduces outer shadow/radius weight.
- Converts field telemetry from pill controls to quiet inline status, panel count, and Browse field text.
- Flattens the primary and additional panel selectors into a continuous navigation rail.
- Replaces boxed active-tab treatment with a restrained two-pixel red active rule.
- Reduces selector height and spacing without changing the eight-panel opening limit.
- Converts `+ Explore additional fields` into an integrated disclosure row rather than a separate card-like control.

### Article Map hero
- Preserves the approved v4.3.7 hero scale: 138 × 94 px desktop thumbnail, 82 × 62 tablet, and 68 × 54 mobile.
- Article Map remains permanent hero position 0.
- Replaces heavy rounded framing with a continuous light editorial surface and a restrained three-pixel red lead rule.
- Removes the redundant `· HERO` text while retaining the `Article Map` identity.
- Keeps description, metadata, thumbnail fallback, CTA, and canonical registry-owned destination.

### Curated supporting articles
- Keeps all selected-article thumbnails visible.
- Removes alternating boxed-card treatment in favor of clean white editorial rows with thin dividers.
- Uses a quieter 124 px desktop media column and slightly tighter metadata/summary typography.
- Retains restrained green interaction confirmation on hover/focus.
- Removes the redundant `CURATED FROM THIS SERIES` kicker; `Selected from this series` remains the section heading.
- Preserves 2–8 configurable supporting slots and manual-only article selection.

### Playback controls
- Keeps the 14-second autoplay model, red progress line, Previous/Pause-Play/Next controls, swipe, hover/focus/touch holds, browser visibility handling, and reduced-motion protection.
- Reduces transport controls to transparent secondary controls with borders/background appearing only on interaction.
- Keeps AUTO/HOLD/PAUSED/STATIC/REDUCED MOTION telemetry but removes the status-dot glow.

### Eight-panel disclosure
- Exactly the first eight visible panels remain exposed initially.
- Panel 9+ remains behind the additional-fields disclosure.
- Collapsed autoplay and Previous/Next remain limited to the first eight panels.
- Opening the disclosure adds all visible panels to navigation and rotation.

### Shortcodes
No shortcode changes:

    [sc_field_spotlights]
    [sc_field_spotlight field="global-governance"]

Optional playback overrides remain supported:

    [sc_field_spotlight field="global-governance" autoplay="true" interval="14000" pause_on_hover="true"]

### Compatibility
- Field Spotlight settings continue to use the durable `sc_library_field_spotlights_settings_v434` option.
- Publications remains v4.3.3.
- Homepage Spotlight remains v4.2.0.
- No content migration or re-curation is required.
