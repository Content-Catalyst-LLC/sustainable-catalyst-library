# Sustainable Catalyst Library v5.0.0 — Connected Public Research Infrastructure

v5.0.0 connects the Library's public API, explicit Publication ↔ Research Graph, public Knowledge Pathways, canonical knowledge relationships, and published federation manifests into a bounded public research-context layer.

The new `[sc_connected_public_research]` surface and `/wp-json/sc-library/v1/connected-public-research` REST facade are GET/read-only. Object contexts are one hop, capped, provenance-labeled, and SHA-256 checksummed. `[sc_public_research_context]` can render a compact context card for a known public object.

This release does not create a parallel public record or graph store, infer semantic relationships, expose private Projects/My Library/notebooks/matrices/rooms/team membership, expose federation governance or credentials, or perform cross-site writes.
