# Knowledge Library v5.25.0 — Research Graph Query & Evidence Pathfinding

## Purpose

Version 5.25.0 turns the publication knowledge map into a queryable, auditable research graph. Researchers can locate graph nodes, expand bounded neighborhoods, and trace deterministic paths across publications, citations, topics, findings, claims, evidence relations, and explicit hypothesis membership.

The feature is deliberately descriptive. A path means that typed graph relationships connect the returned nodes under the requested traversal policy. It does **not** mean the path is true, causal, consensual, or evidentially sufficient.

## Contracts

- Research graph query: `sc-library-research-graph-query/1.0`
- Evidence pathfinding: `sc-library-evidence-pathfinding/1.0`
- Existing publication corpus: `sc-library-publication-corpus-knowledge-map/1.0`
- Existing cross-publication synthesis: `sc-library-cross-publication-evidence-synthesis/1.0`

## Backend v2.36.0

New module: `app/research_graph_pathfinding.py`

### Research graph manifest

Each publication corpus now includes a `research_graph` manifest with:

- node and edge counts;
- node-kind distribution;
- relationship-basis distribution;
- relationship-class distribution;
- supported graph-query operations;
- explicit interpretation constraints.

### Graph query

`POST /v1/publication-knowledge-maps/research-graph-query`

The graph query supports:

- deterministic substring search over node labels and governed source fields;
- node-kind filters;
- record filters;
- relationship-basis filters;
- bounded neighborhood expansion from depth 0–3;
- opt-in analytical relationships;
- reproducibility metadata from the canonical corpus request.

The graph query does not perform hidden semantic inference. If analytical similarity/co-occurrence relationships are enabled, they remain explicitly labeled as analytical relationships.

### Evidence pathfinding

`POST /v1/publication-knowledge-maps/evidence-pathfind`

The pathfinder supports:

- one or more source nodes;
- explicit target nodes or target node kinds;
- forward, reverse, or bidirectional traversal;
- bounded traversal from 1–8 hops;
- maximum 1–50 returned paths;
- typed relationship filtering;
- deterministic breadth-first traversal;
- path-level source-grounding and analytical-edge counts;
- node and edge snapshots for auditability.

With one selected source and no target, the default target kind is `publication`. With multiple selected nodes, the first selected node becomes the source and the remaining selected nodes become path targets.

## Relationship policy

Default pathfinding is restricted to source-grounded or reviewed relationship classes:

- explicit citation;
- metadata association;
- reviewed concept association;
- reviewed finding/evidence;
- reviewed claim/evidence;
- reviewed explicit support;
- reviewed explicit contradiction;
- explicit hypothesis membership.

The following analytical relationships are excluded unless the user explicitly opts in:

- publication/topic co-occurrence;
- source-span co-occurrence;
- embedding cosine similarity.

A legacy analytical flag on an explicitly reviewed support/contradiction or explicit hypothesis relationship does not demote that reviewed relationship into analytical similarity. Its explicit reviewed basis remains authoritative for traversal classification.

## Research integrity

v5.25.0 preserves the v5.23–v5.24 evidence rules:

- graph paths create no new claims;
- graph paths do not imply truth;
- graph paths do not imply causality;
- graph paths do not imply consensus;
- support and contradiction require explicit reviewed relationships;
- analytical similarity/co-occurrence is opt-in and is not an evidence relation;
- absence of a returned path does not prove absence of a relationship;
- evidence weighting is not a truth score;
- lack of contradiction does not imply agreement;
- competing hypotheses still require explicit reviewed metadata;
- Platform Core remains the durable authority for governed research objects and synthesis.

## WordPress integration

The plugin adds two REST proxy routes:

- `/backend/publication-research-graph-query`
- `/backend/publication-evidence-pathfind`

The Knowledge Landscape gains an **Evidence Paths** view plus a Research Graph Query panel and Evidence Pathfinder panel. Analytical relationships are disabled by default and require an explicit checkbox opt-in.

## Product boundary

Knowledge Library owns source-grounded graph construction, graph querying, evidence pathfinding, and publication-centric visualization. Platform Core owns durable governed evidence, finding, claim, argument, hypothesis, synthesis, provenance, and cross-product research objects. v5.25.0 performs no automatic Platform Core write.
