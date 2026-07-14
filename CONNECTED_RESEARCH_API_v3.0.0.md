# Connected Research API — Knowledge Library v3.0.0

Base:

```text
/wp-json/sc-library/v1
```

## Project workspace

```http
GET /projects/{id}/workspace
```

Public projects can be read anonymously.

Private project data requires project edit permission or explicit project team membership.

Update:

```http
POST /projects/{id}/workspace
```

Requires permission to edit the project.

Example:

```json
{
  "research_question": "How should climate evidence inform governance?",
  "objectives": [
    "Review core evidence",
    "Evaluate counterevidence"
  ],
  "methods": "Structured literature review.",
  "scope": "Global climate governance.",
  "start_date": "2026-07-01",
  "target_date": "2026-12-31",
  "sort": "section-author",
  "document_ids": [81, 82],
  "source_entries": [
    {
      "source_id": 123,
      "role": "primary",
      "section": "core-sources",
      "inclusion": "included",
      "priority": 5,
      "annotation": "Central evidence."
    }
  ]
}
```

## Bibliography environment

```http
GET /projects/{id}/bibliography-environment
```

Returns the live grouped bibliography and health summary.

## Bibliography snapshots

```http
GET /projects/{id}/bibliography-snapshots
```

Requires project edit permission.

Create:

```http
POST /projects/{id}/bibliography-snapshots
```

```json
{
  "label": "Pre-review bibliography"
}
```

## Export

```http
GET /projects/{id}/export?format=markdown
```

Formats:

```text
markdown
text
html
bibtex
ris
csl-json
json
```

## Activity

```http
GET /projects/{id}/activity
```

Requires project edit permission.

## Existing connected routes

The environment also uses retained routes for:

```text
Project bibliography
Project Source updates
Source discovery imports
Project evidence packets
Claim evidence packets
Holdings
Connector health
```
