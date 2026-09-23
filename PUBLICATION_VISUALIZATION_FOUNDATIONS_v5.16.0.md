# Knowledge Library v5.16.0 — Publication Visualization Foundations

v5.16.0 makes publication knowledge visualizations first-class Research Library objects while keeping responsibility separated between the Library and Platform Core.

## Library-owned responsibilities

- build renderer-neutral visualization specifications from Library-owned source intelligence;
- represent declared citation relationships without inventing unresolved links;
- represent only human-accepted concept, finding, and claim candidates;
- bind visualization specifications to the publication content hash;
- store draft/published/rejected/superseded review state;
- deliver reviewed visualization specifications to the Research Library WordPress surface;
- expose the `[sc_library_publication_visualizations]` shortcode with an accessible data fallback.

Initial visualization kinds:

1. `citation-network`
2. `concept-map`
3. `finding-map`
4. `claim-map`

## Platform Core responsibilities

Platform Core remains authoritative for governed visual research objects, visual reasoning, provenance, reproducibility, cross-product composition, renderer contracts, and exchange. A reviewed Library visualization can be explicitly queued as a `visual-research-object.create` operation to Core's `/v1/cross-product-visual-research` contract.

The handoff is explicit and does not tell Core to infer truth, create claims, create findings, infer citation relationships, execute layouts, or render browser graphics.

## Research integrity boundaries

- No title-similarity citation guessing.
- No machine-inferred graph edges.
- No automatic finding/claim promotion.
- No automatic truth designation.
- Draft visualizations are not public.
- Source-content changes supersede older draft/published Library visualization specifications.
- Publication visualizations are not themselves governed Core objects until an explicit Core handoff succeeds.

## Research Library delivery

WordPress can retrieve only published visualization specifications through the backend bridge. The shortcode:

`[sc_library_publication_visualizations]`

automatically derives the canonical backend record ID for the current WordPress post, or accepts an explicit `record_id`. The initial renderer is intentionally lightweight and accessible; richer interactive visual exploration remains a later visualization build.
