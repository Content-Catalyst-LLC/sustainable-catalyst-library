# Sustainable Catalyst Knowledge Library v5.14.0

**Release:** Citation Graph & Scholarly Lineage  
**WordPress:** 5.14.0  
**Python backend:** 2.25.0  
**Platform Core dependency:** v3.3+ research-lineage and scholarly-interoperability capabilities

## Added

- Library-owned citation registry with stable citation identities.
- Exact DOI, PMID, PMCID, ISBN, ISSN and declared-identifier normalization/resolution.
- Preservation of unresolved documentary references without guessed matches.
- Incoming, outgoing and combined citation views.
- Bounded citation-neighborhood graph traversal.
- Platform Core binding context on resolved citation graph nodes.
- Metadata citation/reference import endpoint.
- Explicit governed Platform Core scholarly-citation handoff.
- Citation readiness diagnostics in the Python backend and WordPress admin bridge.

## Preserved boundaries

- Raw documents, chunks, citation extraction/indexing and search remain Library concerns.
- Governed research lineage, evidence/provenance, scholarly packages and reasoning remain Platform Core concerns.
- No LLM-inferred citation edges.
- No title-similarity citation resolution.
- No automatic claim/truth promotion.
- Existing hybrid retrieval and semantic fallback behavior are preserved.
