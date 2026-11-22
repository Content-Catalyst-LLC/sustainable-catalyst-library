# SC-HOME-PUB-GRAPH-02 — Native Publication Knowledge Landscape homepage showcase

## Purpose

This is **not** a new knowledge graph. It exposes an opt-in, homepage-sized version of the existing `SC_Library_Knowledge_Landscape` renderer and its authoritative Publication Library corpus contract.

The full Library shortcode is unaffected unless `showcase="1"` is explicitly provided.

## WordPress integration

Replace only the homepage's prior Featured Publications / provisional Publication Landscape block with:

```text
[sc_library_knowledge_landscape scope="corpus" showcase="1" height="560"]
```

Use the existing homepage spotlight placement if that is the active WordPress homepage assembly. Do not add this shortcode *beside* an existing landscape block.

The current WordPress development integration at `f72fc02` was performed in a local DDEV checkout and was **not available in the connected GitHub repository** when this change was authored. Consequently, integration into that local page/widget remains an explicit step. The source code here does not claim to have deployed to DDEV or production.

## Behavior and data

- Uses the **same** Library backend REST proxy and `sc-library-publication-corpus-knowledge-map/1.0` response as the full analysis.
- Uses the existing Library `Landscape` class, SVG network, Canvas 4D terrain, linked query state, matrix, inspector, and view actions.
- A deterministic bounded projection chooses directly connected, source-provided publication/topic nodes and typed edges for the homepage network. It does not create nodes or connections.
- Preserves the full corpus manifest statistics in the four compact, persistent figures: publications, topics, relationships, and terrain anchors.
- Restricts the homepage region layout and relationship-matrix interactions to the visible selected research objects. The complete graph remains on the Library page.
- Includes the Library's existing six working views: Knowledge Landscape, 4D Knowledge Terrain, Topic Graph, Topic Regions, Temporal Dynamics, and Relationship Matrix.
- Rotation defaults to 12 seconds; pauses on user interaction and when the page is hidden, hovered, keyboard-focused, or reduced motion is requested.
- Contains a small search input backed by the native Visual Query and a publication-time slider backed by the native terrain control.
- Uses the native node inspector for source-backed detail, with links to the full Library and Publications.
- Continues to use the existing escape/validation and async corpus error behavior.
- It does not imply embeddings, reviewed contradictions, scientific consensus, or causal conclusions. No freshness timestamp is fabricated.

## Existing assets

The optional showcase uses:

- `assets/css/sc-library-knowledge-showcase.css`
- `assets/js/sc-library-knowledge-showcase.js`

Both enqueue only when the `showcase` shortcode attribute is enabled and depend on the existing v5.27 Library renderer assets.

The full Library view retains its existing styling and behavior. No homepage-specific renderer is added.

## Verification

Implementation-level checks performed:

- Both JavaScript files pass syntax compilation.
- A synthetic corpus fixture confirms the bounded selection preserves original metrics and terrain data, contains only source nodes and typed edges, links matrix entries only to selected publications, is deterministic, and does not mutate input.
- Existing full-mode calls omit `showcase` and therefore follow their unchanged path.

Required on development before production:

1. Place the shortcode on the *actual* development homepage.
2. Confirm the full Library still renders correctly outside showcase mode.
3. Load and inspect all six homepage modes, controls, pause/rotation, query, selected-node inspector, and 4D terrain pointer controls.
4. Confirm the four metric values match the authoritative manifest and change when the corpus changes.
5. Check 320, 768, and 1440 pixel browser widths, keyboard navigation, reduced motion, and anonymous data access.
6. Compare homepage spacing/type/palette to the Workbench and 4D Biodiversity modules.
7. Verify fallback, canonical/robots, cache purge, and existing feature-flag rollback.

**Status:** source change and review candidate only; no hosted or DDEV deployment from this branch.
