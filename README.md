# Sustainable Catalyst Library v1.0.1

A compact native WordPress knowledge base for Sustainable Catalyst publications, topics, pathways, and related research records.

## What changed in v1.0.1

- Replaced the archive-style featured-image grid with concise text-based knowledge records.
- Results remain hidden until a visitor searches, selects a topic, or follows a preserved Library URL.
- Added nested topic drawers and index-aware counts.
- Broad domain selections include records from descendant categories.
- Added a contextual record panel with related knowledge.
- Added featured pathways and browser-local recently opened records.
- Added shortcode modes and administration controls for density, initial results, pathways, excerpts, and search wording.
- Added stale-index cleanup during full rebuilds.

## Recommended shortcode

Use a dedicated WordPress Shortcode block:

```text
[sc_library mode="compact" initial_results="0" show_header="false"]
```

Do not place the shortcode inside a Custom HTML block.

## Release contents

- `sustainable-catalyst-library/` — plugin source
- `sustainable-catalyst-library-v1.0.1.zip` — installable WordPress plugin
- `push_library_v1_0_1_to_github.sh` — empty-repository-safe GitHub deployment script
