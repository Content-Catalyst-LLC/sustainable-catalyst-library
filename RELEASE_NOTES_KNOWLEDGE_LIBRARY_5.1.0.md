# Sustainable Catalyst Library v5.1.0
## Global Research Discovery & Federated Search

- Adds `[sc_global_research_discovery]` and public GET-only `/wp-json/sc-library/v1/research-discovery` API.
- Searches canonical public Library records plus records from explicitly published federation manifests already present on the local node.
- Uses deterministic lexical ranking with visible local/federated origin and provenance; no semantic inference, truth scoring, prestige scoring, popularity scoring or access-entitlement inference.
- Adds bounded `/search` and `/facets` endpoints, 12-second front-end timeout, accessible status reporting and mobile layout.
- Reuses v4.9 public object profiles, v5 Connected Public Research context, v4.8 federation publication authority, v4.9 explicit-origin CORS and v5.0.1 public cache hardening.
- Performs no remote crawling during a search and no automatic import/publication/Workspace write.
- Private personal, project, notebook, evidence, room, team-membership and federation-governance data remain outside the search corpus.
