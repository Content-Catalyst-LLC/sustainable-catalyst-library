# Publications ↔ Research Graph Integration — v4.3.37

## Purpose
v4.3.37 connects Sustainable Catalyst's public Publications layer to the existing Research Graph without creating a second graph database. A publication remains a normal public WordPress publication. Editors can explicitly associate it with canonical Knowledge Topics, Concepts, Named Entities, public Research Sources, public Research Claims, a canonical Article Map, and published Knowledge Pathways.

## Public graph contract
The public graph is intentionally bounded. It can expose only records that are already public under their canonical subsystem rules. Private Research Projects, Source Bundles, Reading Notebooks, notes, annotations, Evidence Matrices, My Library records, saved searches, watchlists, research-queue entries, private metadata-review history, and Workspace handoff state are excluded.

The graph does not infer a source, claim, concept, entity, or relationship from article text. There is no embedding-based or generative extraction path in v4.3.37. Editorial mappings are explicit and reviewable.

## Reused canonical systems
- `SC_Library_Publications::article_map_registry()` for Article Map identity.
- `sc_library_topic` for Knowledge Topics.
- `_sc_library_concept_ids` and `sc_library_concept` for canonical concepts.
- `_sc_library_entity_ids` and `sc_named_entity` for named entities.
- `sc_research_source` plus Citation Studio public-source rules for Research Sources.
- `sc_research_claim` plus the canonical `claim_is_public()` rule for public Research Claims.
- `sc_knowledge_path` for published Knowledge Pathways.
- v4.3.30 Research Projects for the authenticated publication-to-project handoff.

## Editorial mapping
The WordPress publication editor receives a **Publication ↔ Research Graph** panel. Editors can choose an Article Map and select public sources, claims, pathways, concepts, and entities. Knowledge Topics use the canonical Knowledge Topics taxonomy UI.

Publication graph metadata stores references only. It does not copy source bodies, claim evidence, private files, or private research state.

## Publications presentation
The canonical Field Spotlight model is decorated with `research_graph_url` only when a selected article resolves to a publication with explicit public graph context. Both the server-authoritative template and the JavaScript panel renderer support the conditional **Research graph →** action. Publications with no explicit graph context render exactly as before.

## Public Research Library surface
Shortcode:

`[sc_publications_research_graph title="Publications ↔ Research Graph"]`

The surface accepts a Sustainable Catalyst publication URL or `publication_id` query parameter and renders only its declared public graph.

Public endpoints:
- `GET /wp-json/sc-library/v1/publications-research-graph?id={post_id}`
- `GET /wp-json/sc-library/v1/publications-research-graph?url={url}`
- `GET /wp-json/sc-library/v1/publications-research-graph/{post_id}`

The response includes a SHA-256 manifest checksum over the public graph payload.

## Private project continuation
Signed-in users may explicitly link the publication itself to an owned Research Project:

`POST /wp-json/sc-library/v1/publications-research-graph/{post_id}/project-link`

with `project_id`.

The handoff uses the v4.3.30 references-only project link boundary. It stores the canonical publication URL as an external project reference. It does not copy the graph into the project, expose project content publicly, or write to Workspace.

## Truthfulness and privacy boundaries
- Public publication does not imply every connected internal research record is public; only canonical public records are returned.
- Public graph context is editorially declared, not machine-inferred.
- A public claim remains subject to the existing Claim visibility/status rules.
- A public Research Source remains subject to Citation Studio public-source rules.
- Private research never becomes public because a publication references the Research Graph.
- Project handoff requires authentication and ownership of the destination project.
- No automatic publication, Workspace write, claim generation, entity inference, source inference, evidence promotion, or private-data exposure occurs.
