# Sustainable Catalyst Library v1.1.0

A native WordPress knowledge base for structured publications, ordered series, concepts, typed relationships, technical resources, and contextual research navigation.

## What v1.1.0 adds

- A dedicated `wp_sc_library_relationships` table with typed directional relationships.
- **Library Series** and **Library Concepts** taxonomies.
- Stable record identifiers such as `sc:library:post:1842`.
- Primary-domain, series-order, evidence-status, GitHub, dataset, video, and Workbench metadata.
- A publication-editor **Library Relationships** panel.
- Ordered previous/next navigation inside a series.
- Rich knowledge record panels with hierarchy, concepts, resources, relationships, and suggested connections.
- Workbench handoffs that preserve the Library record ID and stable identifier.
- REST endpoints for records, related knowledge, series, concepts, and pathways.
- Upgrade-safe migration from v1.0.1.

## Recommended shortcode

Use a dedicated WordPress Shortcode block:

```text
[sc_library mode="compact" initial_results="0" show_header="false"]
```

## After installation

1. Open **SC Library** and confirm the indexed post types.
2. Save settings and run **Rebuild Library Index**.
3. Edit publications and assign **Library Series** and **Library Concepts**.
4. Use the **Library Relationships** panel to define explicit links and resources.

## Release contents

- `sustainable-catalyst-library/` — plugin source
- `sustainable-catalyst-library-v1.1.0.zip` — installable WordPress plugin
- `push_library_v1_1_0_to_github.sh` — empty-repository-safe GitHub deployment script
- `install_and_push_library_v1_1_0.sh` — Downloads-folder extraction and push helper
