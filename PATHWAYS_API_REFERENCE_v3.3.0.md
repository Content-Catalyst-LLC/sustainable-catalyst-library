# Knowledge Pathways API — v3.3.0

Base:

```text
/wp-json/sc-library/v1
```

## List pathways

```http
GET /knowledge/pathways
```

Parameters:

```text
search
page
per_page
include_private
```

## Read or update pathway

```http
GET  /knowledge/pathways/{id}
POST /knowledge/pathways/{id}
```

Update fields:

```text
level
audience
estimated_minutes
outcomes
steps
prerequisite_ids
continuation_ids
map_mode
recommendation_terms
topic_ids
concept_ids
entity_ids
```

## Article map

```http
GET /knowledge/pathways/{id}/map
```

## Recommendations

```http
POST /knowledge/pathways/recommendations
```

Example:

```json
{
  "query": "climate governance evidence",
  "topic_ids": [12],
  "concept_ids": [44],
  "node_keys": ["document:123"],
  "level": "foundational",
  "limit": 6
}
```

## Pathways containing a node

```http
GET /knowledge/nodes/{kind}/{id}/pathways
```

## Derive from Research Project

```http
POST /projects/{id}/pathway
```

Optional JSON:

```json
{
  "title": "Climate Governance Evidence Path",
  "force_new": false,
  "include_evidence": true
}
```

The created pathway remains Draft.

## Privacy

Public endpoints expose only published pathways and public steps.

Private or authenticated responses use no-store cache headers.
