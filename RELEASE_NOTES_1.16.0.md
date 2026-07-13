# Sustainable Catalyst Library v1.16.0

## Knowledge Graph and Relationship Intelligence

Library v1.16.0 adds a normalized graph projection that connects publications, concepts, article maps, methods, tools, datasets, places, sources, claims, evidence, questions, planning dependencies, and deliberately promoted board objects.

### New graph entities

- Library records
- Concepts
- Categories and domains
- Article maps and series
- Tags
- Places
- Methods
- Tools
- Datasets
- Sources
- Claims
- Evidence
- Questions
- Organizations
- Events
- Other editorial entities
- Explicit source-to-claim assertions

### Relationship intelligence

Graph and publication relationships now preserve:

- Direction and relationship type
- Confidence and confidence basis
- Provenance type and provenance URL
- Evidence note
- Visibility
- Creator and verifier
- Verification date

### Diagnostics

- Orphaned-record detection
- Duplicate-concept candidates
- Dependency-cycle detection
- Provenance-gap detection
- Low-confidence counts
- Unverified-relationship counts

### Large-Library rebuild reliability

- Cursor-based record batches
- Separate relationship and planning-dependency phases
- Saved rebuild state and explicit resume control
- Manual and board-promoted object preservation
- Synchronous no-JavaScript fallback

### Visualization and navigation

- Native responsive SVG graph
- Keyboard-selectable entities
- Accessible relationship list
- Entity and edge inspector
- Search and type filters
- Rooted neighborhood traversal
- Timeline endpoint
- Place-relationship endpoint

### Whiteboard promotion

Editors can deliberately promote research-board nodes and connections into the graph. Promotion is non-destructive and records board provenance.

### REST API

- `/library/graph/schema`
- `/library/graph`
- `/library/graph/diagnostics`
- `/library/graph/timeline`
- `/library/graph/places`
- `/library/graph/rebuild`
- `/library/graph/rebuild/start`
- `/library/graph/rebuild/continue`
- `/library/graph/rebuild/status`
- `/library/graph/board-promotions`
- `/library/graph/edges`

### Portable data

Portable export schema advances to:

```text
sc-library-portable-export/1.6
```

New entities:

- `graph_nodes`
- `graph_edges`

The export also extends canonical publication relationships with confidence, provenance, evidence, and visibility fields.

### Privacy and authority

WordPress remains the publication and identity authority. The graph is rebuildable through bounded, resumable cursor batches and does not replace canonical posts or taxonomies. Public graph responses exclude private and organization-only relationships.

### Retained systems

The release retains v1.15.0 editorial collaboration, v1.14.1 public card repair, Multimedia Studio, large-library scanning, persistent workspaces, server document production, Content Planner, Notebook, boards, annotations, books, and portable exports.
