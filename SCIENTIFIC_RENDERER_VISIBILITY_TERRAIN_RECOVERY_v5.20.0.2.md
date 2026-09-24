# Knowledge Library v5.20.0.2 — Scientific Renderer Visibility & 4D Terrain Recovery

v5.20.0.2 is a renderer-recovery release over v5.20.0.1. It preserves the asynchronous Publication Library corpus transport, canonical publication manifest, linked visual-query contract, 4D terrain contract, and Platform Core handoff boundaries.

## Renderer visibility repair

The terrain canvas and terrain HUD now have explicit `[hidden] { display:none!important; }` rules and a stage-level `is-terrain-view` lifecycle class. JavaScript also sets explicit display state when views change. This prevents the 4D canvas from covering Knowledge Landscape, Topic Graph, Citation Overlay, Topic Regions, Temporal Dynamics, Relationship Matrix, or Semantic Overlay.

## 4D terrain recovery

The prior dense-anchor interpolation normalized each grid cell by accumulated Gaussian weight. With hundreds of anchors this could converge toward a broad, smooth disk. v5.20.0.2 replaces that surface calculation with a peak-preserving model combining local Gaussian maxima with a smaller ridge-energy term. Sigma is tightened, the full 250-topic anchor budget is used, high-prominence anchors receive visible markers and labels, and publication anchors ride above the sampled surface.

The renderer remains descriptive. Terrain height is the selected analytical elevation metric; spatial proximity is not causality; visual prominence is not truth; publication time remains the temporal dimension.
