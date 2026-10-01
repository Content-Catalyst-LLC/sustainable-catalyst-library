# Python Domain Service Migration Foundation — v5.69.0

The Knowledge Library now treats Python/FastAPI as the default authority for Library domain behavior. WordPress/PHP is retained for presentation, routing, SEO, lifecycle integration, and compatibility surfaces.

## Rule

If functionality concerns research objects, data, computation, intelligence, search, provenance, ingestion, persistence, federation, language processing, or research workflows, its authoritative implementation belongs in Python (or a specialized runtime governed through the Python service boundary). PHP may render or adapt that capability, but must not become its source of truth.

## Migration safety

Legacy PHP is not mass-rewritten. A domain cuts over only after API parity, backend readiness, rollback coverage, and WordPress-independent certification. The checked-in PHP retirement inventory is a baseline: new PHP files require explicit classification, making architectural drift visible in validation.

## v5.69 authority

Existing API v1 record/search reads, identity/session/access, artifacts/pipelines/compute, client integration, and several runtime families are declared Python-authoritative. Publication/catalog writes, research-project state, ingestion normalization, and remaining provenance/evidence behavior migrate in subsequent releases.
