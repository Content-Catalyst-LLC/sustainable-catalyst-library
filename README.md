# Sustainable Catalyst Library v1.16.0

Library v1.16.0 adds a provenance-aware Knowledge Graph and Relationship Intelligence layer to the complete v1.15.0 Library platform.

## Knowledge Graph

Open:

```text
SC Library → Knowledge Graph
```

The graph is a normalized, cursor-rebuildable projection of canonical WordPress and Library data. It connects:

- Publications, pages, plans, and documentation
- Concepts, domains, categories, tags, article maps, and series
- Explicit publication-to-publication relationships
- Content Planner dependencies
- Methods, tools, datasets, sources, claims, evidence, questions, places, organizations, and events recorded in post metadata
- Explicit source-to-claim assertions recorded in publication metadata
- Whiteboard and Chalkboard entities deliberately promoted by an editor

The graph does not replace WordPress posts, taxonomies, the Library index, or editorial records. Large graph rebuilds run in resumable batches and preserve manual and board-promoted objects.

## Relationship intelligence

Every graph relationship can preserve:

- Relationship type and direction
- Confidence score and confidence basis
- Provenance type and source URL
- Evidence note
- Public, organization, or private visibility
- Creator, verifier, and verification timestamp

Diagnostics identify:

- Orphaned Library records
- Possible duplicate concepts
- Content-plan dependency cycles
- Relationships missing provenance
- Low-confidence and unverified relationships

## Public views

Create a WordPress page containing the graph shortcode, then save that page URL under **SC Library → Knowledge Graph → Public graph page URL**. Library record cards use it for focused graph links.

```text
[sc_library_knowledge_graph]
[sc_library_knowledge_graph root="record:123" depth="2" limit="250"]
[sc_library_relationship_intelligence]
```

The public graph returns only active public nodes and public relationships meeting the configured minimum confidence threshold.

## Whiteboard promotion

Saved research boards can be promoted into the graph by an editor. Promotion is explicit and non-destructive. Original boards remain unchanged, and promoted nodes and edges preserve their board provenance.

## REST API

```text
/wp-json/sustainable-catalyst/v1/library/graph/schema
/wp-json/sustainable-catalyst/v1/library/graph
/wp-json/sustainable-catalyst/v1/library/graph/diagnostics
/wp-json/sustainable-catalyst/v1/library/graph/timeline
/wp-json/sustainable-catalyst/v1/library/graph/places
/wp-json/sustainable-catalyst/v1/library/graph/rebuild
/wp-json/sustainable-catalyst/v1/library/graph/rebuild/start
/wp-json/sustainable-catalyst/v1/library/graph/rebuild/continue
/wp-json/sustainable-catalyst/v1/library/graph/rebuild/status
/wp-json/sustainable-catalyst/v1/library/graph/board-promotions
/wp-json/sustainable-catalyst/v1/library/graph/edges
```

## Portable data

Portable export schema:

```text
sc-library-portable-export/1.6
```

New normalized entities:

- `graph_nodes`
- `graph_edges`

The dedicated Knowledge Graph export scope preserves graph identifiers, confidence, provenance, verification, visibility, source identifiers, and metadata without copying raw WordPress tables.

## Retained systems

v1.16.0 retains:

- Collaboration, review, comments, suggested edits, approvals, locks, and attribution
- Public record-card responsive repair
- Multimedia Studio, clips, transcripts, rights, and evidence reels
- Large-Library Index Tools and cursor reconciliation
- Persistent account workspaces and optional Render synchronization
- Server-side book and PDF production
- Content Planner, release coordination, and public registry
- Research Notebook, matrices, boards, annotations, and books
- PostgreSQL, CSV, JSONL, and JSON portability

See `KNOWLEDGE_GRAPH_SETUP.md`, `RELEASE_NOTES_1.16.0.md`, and the retained system setup guides.
