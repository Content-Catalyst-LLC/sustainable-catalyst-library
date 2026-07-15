# Article Map Architecture — v3.3.0

## Map schema

```text
sc-library-article-map/1.0
```

## Nodes

Every public pathway step becomes a map node:

```text
id
node_key
kind
node_id
label
url
stage
stage_label
difficulty
required
minutes
x
y
order
```

Private records are excluded from public map data.

## Edges

### Sequence edges

The saved pathway order generates `sequence` edges between adjacent public steps.

### Semantic edges

If two included records already have a public v3.2.0 Knowledge Relationship, the map can include a `semantic` edge.

The map does not create or modify semantic relationships.

## Layout

Rows represent pathway stages. Columns represent records within a stage.

The layout is deterministic and generated from saved order and stage assignment. It is not a force-directed graph.

## Accessibility

The public renderer provides:

- `<svg role="img">`;
- a map title and description;
- linked public nodes;
- keyboard-scrollable viewport;
- text-list fallback containing every node;
- no information conveyed only by color.

## Limits

The visual map is an editorial navigation aid. It does not represent:

- factual certainty;
- causal proof;
- citation strength;
- learning completion;
- reader progress;
- a comprehensive knowledge graph.

Large pathways should be divided into prerequisite and continuation pathways rather than relying on a single oversized map.
