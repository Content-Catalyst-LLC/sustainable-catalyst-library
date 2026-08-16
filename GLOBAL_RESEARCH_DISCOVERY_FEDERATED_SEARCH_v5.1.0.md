# v5.1.0 — Global Research Discovery & Federated Search

## Purpose

v5.1.0 creates a unified public discovery layer over canonical published Sustainable Catalyst Library objects and records contained in explicitly published federation manifests. It composes existing authorities rather than creating a new public record database, search index, federation registry, or private research store.

## Canonical authorities reused

- v4.9.0 `SC_Library_API_Embeds_Interoperability` for normalized public Library objects.
- v5.0.x `SC_Library_Connected_Public_Research_Infrastructure` for one-hop public context links.
- v4.8.0 `SC_Library_Global_Research_Federation` for published federation manifests and provenance.
- Existing production hardening for bounded public GET caching and explicit-origin CORS observability.

## Search contract

The public REST base is `/wp-json/sc-library/v1/research-discovery` with GET-only `/search` and `/facets` routes. Search ranking is deterministic lexical matching. Signals are bounded to explicit public metadata such as title, summary, canonical identifiers and type labels. The score is not a truth score, quality score, institutional prestige score, popularity score, access score, or entitlement decision.

Local candidates are capped at 120, published federation manifests at 120, federated record candidates at 240, and one response page at 50 results. Results are sorted by lexical score, then local-before-federated origin, then title and stable result identity for deterministic tie-breaking.

## Federation boundary

Search reads only federation manifests whose local publication state is `published`. It does not poll, crawl, fetch, or query arbitrary remote institutions during the user's request. Remote expansion remains an explicit federation governance operation. Federated results retain node ID, manifest ID/URN, checksum and record provenance where available.

## Privacy boundary

The search corpus excludes My Library, private Research Projects, Reading Notebook bodies, Evidence Matrix bodies, Research Room membership, Team Library membership, private federation governance, credentials/tokens, source binaries and Workspace state. No automatic import, publication, evidence promotion or Workspace write occurs.

## CORS and cache

The discovery facade reuses the v4.9 explicit-origin allowlist and never enables credentialed cross-origin access. `/sc-library/v1/research-discovery` is added as a narrow public-cache prefix; the broader `/sc-library/v1` namespace remains non-cacheable by default.
