# Publications Core Runtime Recovery v4.3.22.1

## Failure signature
The canonical Publications page renders Global Governance, but the remaining major fields or Article Map panels do not become available.

## Root causes corrected
1. The original `[sc_publications]` dynamic runtime was still versioned at `4.3.3`, so its assets could remain stale long after the plugin advanced.
2. The original Publications template used JavaScript-only field and Article Map controls. If the runtime did not initialize, the server-rendered first field (Global Governance) was the only usable field.
3. The standalone Field Spotlight template still emitted `data-sc-field-spotlights="v4.3.13"` while the repaired JavaScript booted only `v4.3.21.1`, so standalone fields could render the first panel without initializing their panel controller.
4. A stale Publications page body containing `[sc_field_spotlight field="global-governance"]` could permanently constrain the canonical Publications route to one field even though the 14-field registry remained healthy.

## v4.3.22.1 recovery layers
- Cache-bust the original Publications CSS/JS through `SC_Library_Publications::VERSION = 4.3.22.1`.
- Move the original Publications DOM/runtime marker to `v4.3.22.1`.
- Add server-side `sc_publications_field` and `sc_publications_map` selection.
- Render field and Article Map controls as ordinary links first; JavaScript progressively enhances them.
- Keep fallback links visible when JavaScript fails, including mobile layouts.
- Add a render-time structural guard to the original Publications model and reuse the bounded v4.3.18.1 integrity repair.
- Align both Field Spotlight master and single-template markers to `v4.3.22.1`.
- On the canonical `/publications/` page only, promote a stale standalone Global Governance shortcode to the full Field Spotlight master stack.

## Preservation boundaries
The repair does not delete or rewrite field titles, descriptions, ordering, hero copy, Article Map URLs, supporting publication selections, Citation Studio data, Course Finder data, or Research Library page content.
