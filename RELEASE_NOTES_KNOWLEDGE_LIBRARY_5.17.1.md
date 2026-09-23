# Sustainable Catalyst Knowledge Library v5.17.1

## Publication Corpus Integration

v5.17.1 corrects the default scientific knowledge-map workflow so the Research Library visualization connects directly to the publications already indexed by the Library Python backend.

### Default shortcode behavior

`[sc_library_knowledge_landscape]` now uses **corpus mode** by default and analyzes public, published records from `source_key=wordpress-main`. It no longer assumes the WordPress page containing the shortcode must itself be an indexed publication.

Single-publication drill-down remains available with:

`[sc_library_knowledge_landscape scope="publication" record_id="wordpress:1:post:1621"]`

### New corpus API

`GET /v1/publication-knowledge-maps/corpus`

Default corpus boundary:
- public records only
- publication_status=published
- source_key=wordpress-main
- newest published records first
- bounded analytical corpus, configurable up to 1,000 publications

### Relationship channels

The corpus landscape distinguishes:
- explicit citations within the corpus
- publication-to-topic metadata associations
- human-reviewed concept associations
- publication-level topic co-occurrence
- source-span concept co-occurrence
- cosine similarity from real current stored embeddings when available

No LLM-inferred edges, guessed citations, semantic fabrication, or automatic truth promotion are introduced.

### Architecture

Knowledge Library owns corpus discovery, publication parsing, topic/concept extraction, citations, embeddings and analytical graph assembly. Platform Core remains the governed visual reasoning, provenance, linked-view, visual-query and reproducibility substrate.

### Versions

- WordPress plugin: 5.17.1
- Library Python backend: 2.28.1
