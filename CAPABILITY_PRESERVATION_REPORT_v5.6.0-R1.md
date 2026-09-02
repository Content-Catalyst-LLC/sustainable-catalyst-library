# Capability Preservation Report — v5.6.0 R1

Baseline: restored Research Library v5.4.0 page supplied before the R1 build.

- Protected unique shortcodes: **37**
- Protected named anchors: **72**
- Removal policy: **no protected capability is removed by R1**

## Shortcode preservation map

| Protected shortcode | R1 location |
|---|---|
| `sc_library_account_continuity` | visible page/front door |
| `sc_personal_research_environment` | lazy capability registry |
| `sc_collaborative_research_rooms` | lazy capability registry |
| `sc_institutional_team_libraries` | lazy capability registry |
| `sc_global_research_federation` | lazy capability registry |
| `sc_library_api_interoperability` | lazy capability registry |
| `sc_connected_public_research` | lazy capability registry |
| `sc_global_research_discovery` | lazy capability registry |
| `sc_research_identity_authority` | lazy capability registry |
| `sc_public_evidence_claim_navigation` | lazy capability registry |
| `sc_research_curated_spaces` | lazy capability registry |
| `sc_research_access` | lazy capability registry |
| `sc_institutional_connector_network` | lazy capability registry |
| `sc_public_library_network` | lazy capability registry |
| `sc_access_intelligence_ii` | lazy capability registry |
| `sc_personal_library` | lazy capability registry |
| `sc_research_continuity` | lazy capability registry |
| `sc_unified_research_projects` | lazy capability registry |
| `sc_reading_notebook_workspace` | lazy capability registry |
| `sc_evidence_matrix_workspace` | lazy capability registry |
| `sc_knowledge_graph_evidence_intelligence` | lazy capability registry |
| `sc_library_workspace_continuity` | lazy capability registry |
| `sc_metadata_quality_center` | lazy capability registry |
| `sc_open_course_finder` | lazy capability registry |
| `sc_open_learning_ii` | lazy capability registry |
| `sc_publications_research_graph` | lazy capability registry |
| `sc_research_librarian_orchestrator` | lazy capability registry |
| `sc_library` | visible page/front door + lazy capability registry |
| `sc_knowledge_relationship_browser` | lazy capability registry |
| `sc_pathway_recommendations` | lazy capability registry |
| `sc_research_librarian_ii` | lazy capability registry |
| `sc_citation_studio` | lazy capability registry |
| `sc_research_document_builder` | lazy capability registry |
| `sc_library_unified_workspace` | lazy capability registry |
| `sc_research_portability` | lazy capability registry |
| `sc_library_readiness_status` | lazy capability registry |
| `sc_institutional_research_portal` | lazy capability registry |

## Anchor preservation

All 72 baseline named anchors are represented either directly in the compact R1 page or as a capability-card/alias target in the hub registry. The client opens the matching capability when a legacy hash is visited.

## External research visibility

The Find & Access Research zone keeps the following connections visible before a tool is opened: Internet Archive, MIT, Harvard, Library of Congress, University College Dublin, Crossref, OpenAlex, DataCite, PubMed, PMC, Europe PMC and arXiv. The existing Institutional Research Network, Public Library Network, Access Intelligence II, Global Research Discovery and Research Identity tools are preserved behind explicit capability entry points.

## Rendering boundary

The Dynamic Explorer and account continuity are rendered directly on the page. Heavy application shortcodes are not executed in the initial page response; they are rendered by WordPress only after selection in a same-origin capability frame. This preserves module authentication and asset loading while reducing initial interface sprawl.
