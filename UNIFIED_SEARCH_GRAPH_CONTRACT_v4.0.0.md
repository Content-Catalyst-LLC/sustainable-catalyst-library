# Unified Search and Graph Contract — v4.0.0

## Search

Default page size: 25.

Maximum page size: 100.

Cursor type:

```text
opaque-offset
```

Response fields:

```text
schema
query
types
count
limit
cursor_type
offset
next_cursor
facets
records
generated_at
etag
```

Public searches include only public record families and publicly visible records.

## Graph

Maximum depth: 2.

Maximum nodes: 250.

Graph node kinds include Knowledge Library record types and topics.

Graph edges combine v3.2.0 semantic relationships with institutional registry relationships.

Response fields:

```text
schema
seed
depth
node_count
edge_count
truncated
nodes
edges
generated_at
graph_sha256
```

Graph checksums support reproducibility of the returned metadata graph. They do not establish truth or provenance beyond the source records.
