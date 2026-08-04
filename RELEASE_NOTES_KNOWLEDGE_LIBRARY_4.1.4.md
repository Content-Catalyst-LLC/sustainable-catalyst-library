# Sustainable Catalyst Library v4.1.4
## Knowledge Library Progress Indicator Refinement

### Purpose

Refine the Homepage Spotlight rotation timer so it reads as neutral elapsed-time feedback rather than a changing red-to-green health or performance signal.

### Changes

- Replaces the mixed red-and-green progress gradient with one solid red progress fill.
- Changes the unfilled progress track to a visible neutral gray.
- Reserves green for the small `AUTO` operational status indicator and related interaction states.
- Keeps the progress motion linear and tied to the existing 14-second default interval.
- Preserves pause, hold, manual navigation, reduced-motion, browser-visibility, touch, hover, and keyboard behavior.
- Preserves the v4.1.3 thumbnail resolution and runtime fallback chain.
- Preserves all categories, card selections, schedules, positions, source links, and no-backfill rules.

### Public shortcode

```text
[sc_homepage_spotlight]
```

No shortcode change is required.

### Upgrade notes

This is a presentation-only patch. Existing Spotlight data does not need migration or re-entry. Purge WordPress, page-builder, hosting, CDN, and browser caches after installation so the revised stylesheet is served.
