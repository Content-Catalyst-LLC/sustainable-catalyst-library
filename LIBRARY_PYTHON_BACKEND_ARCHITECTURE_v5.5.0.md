# Sustainable Catalyst Library v5.5.0
## Python Research Intelligence Backend & Knowledge Evolution Foundation

### Purpose
v5.5.0 turns Library from a predominantly WordPress-local application into a two-tier research platform. WordPress remains the canonical editorial, identity, permissions, publication, and public-page authority. A dedicated Python/FastAPI service becomes the durable research-intelligence data plane.

### New backend capabilities
- PostgreSQL research source registry
- canonical record index with content hashes and revisions
- long-document chunks
- weighted full-text search
- trigram title recovery for misspellings/partial titles
- topic/source/object-type facets
- public related-record discovery
- explicit knowledge-graph edges with provenance and weights
- immutable record-version snapshots
- public knowledge-evolution timelines
- signed server-to-server ingestion and deletion
- health/readiness and capability reporting

### Authority boundary
The backend does **not** become the publication authority. Public reads are restricted to records whose indexed visibility is `public` and publication status is `published`. The WordPress bridge only auto-indexes published records and removes a record from the backend when its WordPress status ceases to be published or it is deleted.

### AI / semantic-search boundary
v1.0.0 deliberately does not make an external embedding or language model mandatory. PostgreSQL search and explicit graph/provenance structures are fully operational without AI. The data model is adapter-ready for later embeddings, reranking, entity extraction, citation analysis, and Research Librarian retrieval.

### Existing Render helper
The legacy `render-workspace-service` is retained in this release for backward compatibility with workspace/document/media contracts. The new `library-backend` is a separate service and is the future research-intelligence backend. No existing frontend route is redirected to it in v5.5.0.
