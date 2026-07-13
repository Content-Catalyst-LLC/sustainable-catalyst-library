# Library v1.16.0 Knowledge Graph Setup

## 1. Install the upgrade

1. Upload `sustainable-catalyst-library-v1.16.0.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm **Sustainable Catalyst Library 1.16.0** under Installed Plugins.
4. Clear WordPress, page-builder, Cloudflare, and browser caches.

The upgrade creates two normalized WordPress tables:

```text
wp_sc_library_graph_nodes
wp_sc_library_graph_edges
```

Your actual WordPress table prefix may differ.

## 2. Create the public graph page

Create a WordPress page such as **Knowledge Graph** and place this shortcode in the page content:

```text
[sc_library_knowledge_graph]
```

Publish the page, then open **SC Library → Knowledge Graph** and enter its full URL under **Public graph page URL**. Library result cards use that URL for their **View Relationship Graph** action.

## 3. Build the graph projection

Open:

```text
SC Library → Knowledge Graph
```

Choose a batch size and select **Start resumable graph rebuild**. The browser continues bounded WordPress REST batches and can resume a saved rebuild after interruption. The rebuild reads the current Library index and Library metadata. It does not recrawl public pages or alter canonical posts.

For a large Library, begin with batch size 50 and run the rebuild during a low-traffic period. Manual and board-promoted graph objects are preserved across rebuilds.

A Library index rebuild is not required solely for this upgrade. Rebuild the Library index first only when published content is missing from the existing index.

## 4. Review graph quality

After rebuilding, inspect:

- Active entities and relationships
- Orphaned records
- Possible duplicate concept groups
- Dependency cycles
- Provenance gaps
- Low-confidence relationships
- Unverified relationships

An orphan is not automatically an error. It means the record has no graph relationship under the current graph projection.

## 5. Add relationship intelligence

Edit a Library publication and use its relationship controls to record:

- Related publication
- Relationship type
- Confidence
- Confidence basis
- Provenance type
- Provenance URL
- Evidence note
- Visibility

The publication editor also provides dedicated fields for claims, evidence, questions, organizations, dated events, and explicit `Source => Claim` links. Event entries can use `YYYY-MM-DD | Label`; place entries can use `latitude, longitude | Label`.

Private and organization-only relationships are excluded from signed-out public Library responses and the public graph.

## 6. Publish a graph

General graph:

```text
[sc_library_knowledge_graph]
```

Focused neighborhood:

```text
[sc_library_knowledge_graph root="record:123" depth="2" limit="250"]
```

Relationship-quality summary:

```text
[sc_library_relationship_intelligence]
```

Public graph limits and minimum confidence are stored as Library settings. The interface uses native SVG and an accessible relationship list; it does not use an iframe.

## 7. Promote a research board

Open a saved Whiteboard or Chalkboard while signed in as an editor. Select **Promote to Knowledge Graph**.

Promotion:

- Creates or updates graph entities using stable external keys
- Preserves the original board
- Records board provenance and promoting user
- Honors public, organization, or private board visibility
- Does not publish a WordPress post

## 8. Timeline and place data

The timeline endpoint returns public record and event entities that have a publication date.

The place endpoint returns place entities and relationship counts. It is a place registry and relationship view, not a geographic map unless latitude and longitude are present in the entity metadata and a separate map client uses them.

## 9. Export and restore

Open:

```text
SC Library → Portable Data Export
```

Choose the **Knowledge graph, relationship provenance, and diagnostics data** scope.

Available formats:

- PostgreSQL SQL
- CSV ZIP bundle
- JSONL ZIP bundle
- JSON snapshot

Portable schema:

```text
sc-library-portable-export/1.6
```

The export contains graph metadata, not linked media binaries or an entire WordPress database.

## 10. Privacy boundary

The public graph includes only:

- Active public nodes
- Public edges
- Relationships meeting the public minimum-confidence threshold

Private and organization-only edges remain available only to authorized editors through edit-context endpoints and administrative exports.
