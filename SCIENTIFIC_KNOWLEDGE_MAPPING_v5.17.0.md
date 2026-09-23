# Knowledge Library v5.17.0 — Scientific Knowledge Mapping & Interactive Semantic Analysis

v5.17.0 advances publication visualization from static reviewed graph specifications into an interactive scientific knowledge-analysis surface designed for the Research Library and later reuse in Sustainable Catalyst Workspaces.

## Analytical model

The knowledge map treats every visible relationship according to its actual evidence basis. It does not collapse all links into a generic "related" edge.

- **Explicit citation** — declared/resolved scholarly citation relationships.
- **Metadata association** — topics/tags explicitly attached to a publication record.
- **Reviewed concept association** — human-accepted concept candidates from v5.15 extraction.
- **Source-span co-occurrence** — concepts observed within the same stored source chunk; this is an analytical co-occurrence measure, not a causal claim.
- **Embedding cosine similarity** — only produced when current stored vectors exist for the publications being compared. No synthetic or placeholder semantic scores are generated.

## Research Library interface

The new shortcode is:

`[sc_library_knowledge_landscape]`

It automatically resolves the current WordPress publication record. An explicit record can also be supplied:

`[sc_library_knowledge_landscape record_id="wordpress:1:post:1621"]`

The module provides:

- interactive network and radial layouts;
- zoom, pan, node drag/focus and fit-to-view;
- publication/topic layer controls;
- citation, metadata, reviewed-concept, co-occurrence and semantic relationship filters;
- relationship-strength filtering;
- node provenance and graph-metric inspection;
- Knowledge Landscape, Topic Graph, Citation Overlay and Semantic Overlay views;
- accessible graph-data fallback;
- dark scientific presentation aligned with Research Lab visual language.

## Platform Core alignment

The Library remains responsible for publication ingestion, source parsing, metadata, reviewed concept extraction, citations and stored embeddings. Platform Core remains the authority for governed visual research objects, scene/view composition, analytical visualization grammar, linked views, visual query/exploration, reproducibility and cross-product reasoning.

The v5.17 response advertises renderer-neutral targets corresponding to Core's existing visual-runtime surfaces. v5.17 does not claim that the Library itself executes Platform Core reasoning.

## Research-integrity boundaries

- No LLM-inferred graph edges.
- No automatic truth promotion.
- No guessed unresolved citations.
- Semantic similarity requires real current embeddings.
- Semantic similarity is explicitly analytical and is not a truth, evidence or causal assertion.
- Extracted concepts must be human accepted before entering the publication knowledge map.

## Workspace portability

The response contract `sc-library-publication-knowledge-map/1.0` is renderer-neutral and intended to be portable into Workspaces later, where the same publication/topic graph can participate in multi-document research analysis, linked views, visual queries and reproducible research packages.
