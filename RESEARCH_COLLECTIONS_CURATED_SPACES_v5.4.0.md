# Research Collections, Exhibitions & Curated Knowledge Spaces — v5.4.0

## Purpose
v5.4.0 adds a durable editorial curation layer over records that are already public in Sustainable Catalyst Library. It supports research collections, exhibition-style narratives, and curated knowledge spaces without turning personal/private research into public material.

## Canonical model
- Post type: `sc_curated_space`
- Version: `5.4.0`
- Public facade: `/wp-json/sc-library/v1/curated-spaces`
- Editor surface: normal WordPress draft/publish workflow.
- Public types: `research-collection`, `exhibition`, `knowledge-space`.
- Ordered sections contain public curator narrative plus references-only items.
- Stable space URN: `urn:sc:curated-space:{uuid}`.
- Stable section URN: `urn:sc:curated-section:{uuid}`.

## Public reference authorities
Curated items may reference canonical public object profiles exposed by v4.9, plus:
- public Research Claims from v5.3;
- public Evidence Notes from v5.3;
- explicitly published v4.8 federation manifests.

The underlying record remains authoritative. Curated spaces do not copy private bodies or transfer ownership. A reference is re-resolved when public output is built; records that are no longer public are omitted.

## Publication governance
Curated spaces use WordPress page-level editorial capabilities and the normal draft/publish lifecycle. Publishing a space is an explicit editorial action. Publishing the space does not publish, unpublish, modify, merge, or otherwise change any referenced record.

## Interoperability
The module adds `curated-space` to the v4.9 public-object profile registry. This lets the existing v5.1 discovery facade discover published curated spaces without adding another search database. Public curated-space payloads carry a deterministic SHA-256 manifest and references-only provenance.

## Privacy and meaning boundaries
- Personal Library content is not copied.
- Research Projects are not exposed.
- Notebook bodies are not exposed.
- Evidence Matrix bodies are not exposed.
- Research Room and Team Library membership are not exposed.
- Private federation governance is not exposed.
- Credentials and private binaries are not copied.
- Inclusion is an editorial selection, not a truth, consensus, authority, or access score.
- No automatic publication, evidence promotion, federation acceptance, or Workspace write occurs.

## UI
- `[sc_research_curated_spaces]` renders the public index and selected space.
- `[sc_curated_space id="123"]` renders one published space.
- The admin section builder supports bounded ordered sections and public references.
- Public controls retain 44px minimum targets, visible focus, responsive layouts, and reduced-motion behavior.
