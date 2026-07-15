# Knowledge Graph API — v3.2.0

Base: `/wp-json/sc-library/v1`.

```text
GET      /knowledge/nodes/{kind}/{id}
GET/POST /knowledge/relationships
GET      /knowledge/topics
GET      /knowledge/concepts
GET      /knowledge/entities
GET      /knowledge/coverage
GET      /projects/{id}/knowledge-coverage
GET/POST /knowledge/migration
```

Node kinds: document, source, claim, evidence, project, concept, topic, entity, vocabulary.

Relationship reads accept `kind`, `id`, and `direction=both|outgoing|incoming`.

Example write:

```json
{
  "from_kind": "document",
  "from_id": 123,
  "relation": "continues",
  "to_kind": "document",
  "to_id": 122,
  "note": "Second report in the series.",
  "weight": 5,
  "public": true,
  "status": "active"
}
```

Topic, Concept, and Entity routes support search, paging, and authorized private inclusion. Private/authenticated/migration responses receive no-store cache headers.
