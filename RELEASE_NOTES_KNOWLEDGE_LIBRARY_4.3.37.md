# Sustainable Catalyst Library v4.3.37 — Publications ↔ Research Graph Integration

v4.3.37 connects the canonical Publications Field Spotlight to the Research Graph while preserving the distinction between public editorial knowledge and private research work.

## Added
- New `SC_Library_Publications_Research_Graph` extension module.
- New `[sc_publications_research_graph]` Research Library surface.
- New public `/wp-json/sc-library/v1/publications-research-graph` lookup and per-publication graph routes.
- New authenticated references-only publication-to-owned-project reference handoff.
- Publication editor panel for explicit Article Map, public source, public claim, pathway, concept, and named-entity mapping.
- Knowledge Topics enabled on publication posts through the existing canonical topic taxonomy.
- Conditional **Research graph →** actions on canonical Field Spotlight cards when explicit public graph context exists.
- SHA-256 graph manifests and cache invalidation when editorial mappings change.

## Public/private boundary
The graph returns public records only. Private projects, source bundles, notebooks, notes, annotations, evidence matrices, My Library records, saved searches, watchlists, research-queue entries, private review history, and Workspace state are excluded. v4.3.37 does not infer sources, claims, concepts, entities, or relationships from article text.

## Preserved
Open Learning II, Access Intelligence II, Metadata Quality & Entity Resolution, Library ↔ Workspace Continuity, Evidence Matrix & Claim Intelligence, Reading Notebooks, Unified Research Projects & Source Bundles, Saved Research, My Library, Citation Studio, Research Document Builder, the v4.3.22.4 Publications restoration, and canonical `/knowledge-libraries/` routing remain intact.
