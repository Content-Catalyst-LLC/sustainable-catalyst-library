# Sustainable Catalyst Library v4.3.9
## Field Spotlight Interaction Parity, White Active State & Sharp-Edge Refinement

Released: August 7, 2026

### Purpose
Correct the remaining visual and interaction mismatch between the Major Field Spotlight surfaces and the approved Homepage Knowledge Library Spotlight behavior.

### Additional-fields accordion
- Fixes the CSS cascade that allowed panel 9+ to remain visible even while the `hidden` attribute was present.
- `Explore additional fields` now begins genuinely collapsed and reveals the additional panel grid only when activated.
- The disclosure now carries `aria-controls`, `aria-expanded`, and synchronized `aria-hidden` state.
- The plus control changes to minus while open and the label changes to `Hide additional fields`.
- Collapsing while an additional panel is active returns playback to the first primary panel.
- With the accordion closed, autoplay and Previous/Next remain confined to the first eight panels; while open, all visible panels participate.

### Homepage Spotlight active-state parity
- The currently playing panel selector is now white with black text.
- Its numeric index remains red and the two-pixel red progress/selection rule remains visible.
- Hover does not darken the active white selector.
- The additional-field selectors use the same active treatment when the disclosure is open.

### Sharp-edge geometry
- Removes the remaining 12px radius from the outer Major Field Spotlight container.
- Explicitly keeps Article Map hero, selected-publication surface, hero media, CTA, and transport controls square.
- The additional-fields disclosure uses a square bordered +/- indicator instead of a circular control.
- Shadow weight is reduced while retaining enough separation from the page background.

### Preserved behavior
- Fourteen major fields and the canonical Article Map registry are unchanged.
- The first eight panels remain the initial visible tier.
- 14-second autoplay, progress line, pause/play, previous/next, hover/focus hold, swipe, reduced-motion handling, Article Map thumbnails, and manual supporting-article curation remain intact.
- Durable Field Spotlight settings continue to use `sc_library_field_spotlights_settings_v434`; no reconfiguration is required.
- Publications remains v4.3.3 and Homepage Spotlight remains v4.2.0.

### Shortcodes
No shortcode changes:

    [sc_field_spotlights]
    [sc_field_spotlight field="global-governance"]
