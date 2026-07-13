# Sustainable Catalyst Library v2.0.1

## Unified Discovery Interface Repair

v2.0.1 repairs the native Library topics, relationships, and pathways experience inside `[sc_library]`. It does not remove or hide those capabilities. Dynamic discovery remains part of the main Library interface, while the manually maintained Browse by Topic architecture can remain on the public page until the plugin reaches full editorial parity.

## Install

1. Upload `sustainable-catalyst-library-v2.0.1.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm version **2.0.1** under Installed Plugins.
4. Clear WordPress, Cloudflare, page-builder, and browser caches.
5. Keep the Library shortcode in compact or full mode, for example:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="false"]
```

6. Confirm that **Browse the knowledge architecture**, **Browse series and concepts**, and **Featured pathways** all appear and load.
7. Expand a domain and select a child topic. Confirm the filtered result state appears in the URL.
8. Select a series and a concept. Confirm each produces Library results.
9. Open one featured pathway.
10. Keep the manual Browse by Topic section below the live interface until the dynamic system is ready to replace it.

## Repair scope

- One aggregate public discovery endpoint: `/wp-json/sustainable-catalyst/v1/library/discovery`.
- Schema: `sc-library-discovery/1.0`.
- Server-rendered pathway fallback followed by REST refresh.
- Aggregate loading with fallback to the existing category, series, concept, and pathway endpoints.
- Topic, relationship, and pathway counts.
- Explicit loading, empty, error, and retry states.
- Keyboard-native details/summary controls and pressed-state reporting for series and concepts.
- Automatic opening of ancestors for a selected nested topic.
- Component-width responsive grids through plugin-owned CSS.
- High-specificity isolation from page-level `.cc-research-library-brand` layout rules.

## What remains unchanged

- WordPress remains the canonical publishing source.
- Search, results, record panels, Notebook, Workbench, Decision Studio, Site Intelligence, Lab, books, graph, editorial, API, preservation, and readiness systems remain compatible.
- Existing categories, series, concepts, featured-pathway settings, URLs, and shortcode modes remain valid.
- No index rebuild or database migration is required solely for v2.0.1.
- The manual topic architecture is not deleted or replaced automatically.

## Verification boundary

The release can be validated statically and through automated tests before installation. Live WordPress REST responses, theme interaction, Cloudflare caching, and browser rendering still require confirmation on the production site.
