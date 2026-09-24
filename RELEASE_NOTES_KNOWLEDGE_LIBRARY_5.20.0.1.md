# Release Notes — Sustainable Catalyst Knowledge Library v5.20.0.1

**Release:** Asynchronous Publication Corpus Loading & Transport Repair  
**Backend:** v2.31.1

This patch moves Publication Library corpus analysis off the WordPress page-render path and replaces long GET-manifest transport with JSON POST transport.

### Added
- immediate scientific-shell rendering for corpus mode
- asynchronous browser corpus loading through the WordPress REST proxy
- canonical Publication Library manifest resolution at REST request time
- JSON POST transport from WordPress to Python
- explicit loading, ready, transport-error, backend-error, and retry states
- backend health flags for asynchronous corpus and POST transport
- accessible graph summary hydration after the corpus arrives

### Preserved
- v5.20.0 linked scientific views and visual query
- v5.19.0 4D knowledge terrain and temporal playback
- v5.18.0 cross-publication regions, relationships, matrices, and temporal dynamics
- canonical Publication Library corpus scoping
- Platform Core visual-reasoning and provenance boundaries

No analytical edge, claim, citation, semantic-similarity, or terrain interpretation rule is relaxed by this patch.
