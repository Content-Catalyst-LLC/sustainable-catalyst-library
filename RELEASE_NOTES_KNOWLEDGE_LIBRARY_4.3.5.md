# Sustainable Catalyst Library v4.3.5
## Major Field Spotlight Shell, Thumbnail Presentation & Progressive Disclosure

### Public presentation
- Adds `[sc_field_spotlights]` for the complete major-field Spotlight stack.
- Adds `[sc_field_spotlight field="global-governance"]` for one standalone field.
- Preserves 14 major fields and the 170 canonical Article Map panel registry introduced in v4.3.4.
- Renders Article Map as permanent hero position 0.
- Reuses the established Spotlight thumbnail resolution order: featured image, Library attachment metadata, PDF preview, attached image, content image, image URL, then KL placeholder.
- Renders manually selected supporting articles beneath the hero with thumbnail, title, metadata, and summary.
- Displays the first eight panels initially and reveals panel 9+ through an accessible + additional-fields control.
- Supports previous/next panel navigation and keyboard Left/Right/Home/End navigation.
- Does not autoplay Field Spotlights.

### Editorial administration
- Extends SC Library -> Field Spotlights with per-panel content editing.
- Article Map canonical URL remains registry-owned and cannot be replaced.
- Supporting slots accept canonical Library URLs and optional title overrides.
- Supporting slots remain manual-only with no automatic backfill.
- Per-panel supporting slot count remains configurable from 2 through 8.

### Compatibility
- `[sc_publications]` remains v4.3.3.
- `[sc_homepage_spotlight]` remains v4.2.0.
- Existing v4.3.4 Field Spotlight settings use the same option and require no migration.
