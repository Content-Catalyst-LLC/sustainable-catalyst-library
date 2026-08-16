# Sustainable Catalyst Library v5.2.0

## Research Identity, Authority & Persistent Identifier Network

v5.2.0 introduces a public persistent-identifier and authority-resolution facade for DOI, ORCID, ROR, ISBN, ISSN, Wikidata and PMID. It reuses canonical public Library records, Citation Studio metadata, Named Entity/Concept external URIs, metadata-quality/entity-resolution boundaries, and explicitly published federation manifests.

The release is intentionally conservative. Identifier normalization and checksum/syntax validation are deterministic and local. No external registry is contacted during resolution. Matching identifiers are discovery/reconciliation evidence, not proof of identity, authorship, affiliation, ownership, truth, institutional authority, quality, or access entitlement.

Ambiguity is preserved. Multiple public records declaring the same normalized identifier are returned as separate candidates. No automatic merge, canonical assignment, metadata rewrite, federation acceptance, publication, or Workspace write occurs.

Private Projects, My Library, notebook and Evidence Matrix bodies, Research Room and Team Library membership, credentials, private federation governance, and Workspace state remain outside the public identity corpus.
