# Sustainable Catalyst Library v4.0.6

## Institutional Portal Compact Layout Repair

This patch introduces a compact, page-friendly presentation for Connected Institutional Research.

### Changes

- Adds `compact="true"` and `featured="6"` shortcode options.
- Shows six prioritized institutional records in a two-column catalog.
- Places remaining records inside an accessible collapsed disclosure.
- Removes repeated summaries and repeated “Open record” rows from compact cards.
- Allows `units="0"` so the Research Library page can suppress unit listings.
- Applies the same compact interface when the protected server-rendered fallback is active.
- Keeps the original full portal available when compact mode is not requested.

### Recommended Research Library shortcode

```text
[sc_institutional_research_portal documents="12" units="0" compact="true" featured="6"]
```
