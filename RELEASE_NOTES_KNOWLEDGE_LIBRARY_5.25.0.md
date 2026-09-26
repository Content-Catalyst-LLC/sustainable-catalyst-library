# Sustainable Catalyst Knowledge Library v5.25.0

**Release:** Research Graph Query & Evidence Pathfinding  
**WordPress plugin:** 5.25.0  
**Python backend:** 2.36.0

## Added

- Deterministic research-graph query contract and backend service.
- Auditable evidence-pathfinding contract and bounded breadth-first traversal.
- Typed relationship classes that distinguish explicit/reviewed research relationships from analytical similarity/co-occurrence.
- Default source-grounded traversal policy with analytical relationships opt-in only.
- Direction-aware traversal for directed citation lineage.
- Path metrics including hop count, reviewed-edge count, analytical-edge count, and source-grounded path count.
- Canonical `research_graph` manifest in publication corpus responses.
- WordPress REST proxies for research graph query and evidence pathfinding.
- Knowledge Landscape **Evidence Paths** view.
- Research Graph Query and Evidence Pathfinder controls in the publication visualization interface.
- Path highlighting in the interactive research visualization.

## Preserved

- Cross-publication evidence synthesis contract from v5.24.0.
- v5.24.0.1 release-certification compatibility rule allowing the installed plugin to be at or newer than the component being certified.
- Explicit-reviewed-only support and contradiction semantics.
- Evidence weighting is not truth scoring.
- Absence of contradiction is not agreement.
- Competing hypotheses require explicit reviewed metadata.
- Platform Core remains the durable authority for governed research objects and synthesis.

## Safety and interpretation

Evidence paths are descriptive graph routes. They do not create new claims and do not assert truth, causality, consensus, or agreement. Analytical similarity and co-occurrence edges are excluded by default and can only enter a path after explicit user opt-in.
