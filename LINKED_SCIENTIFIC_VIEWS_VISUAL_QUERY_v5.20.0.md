# Knowledge Library v5.20.0 — Linked Scientific Views & Visual Query

v5.20.0 turns the v5.18/v5.19 scientific views into one coordinated analytical instrument.

## Shared visual-query state

The corpus response now includes `sc-library-linked-visual-query/1.0`. It carries deterministic indexes for publication/topic membership, topic/publication membership, topic regions, publication years, and typed adjacency. The state is renderer-neutral and portable to later Workspace/Core workflows.

## Cross-view interactions

Selections can originate from graph nodes, topic regions, relationship-matrix cells, the publication-time control, text queries, or 4D terrain peaks. The same state propagates across the Knowledge Landscape, 4D Knowledge Terrain, Topic Graph, Citation Overlay, Topic Regions, Temporal Dynamics, Relationship Matrix, and Semantic Overlay.

Two modes are supported: **Highlight** preserves context and dims nonmatching research; **Isolate** restricts the visible analytical set to the current linked selection.

## Scientific boundaries

Visual selection does not create a finding, claim, causal conclusion, or truth assertion. Direct-neighbor highlighting represents graph adjacency already present in the corpus model. Text matching is label matching over the loaded corpus. Filters do not mutate Library source records.
