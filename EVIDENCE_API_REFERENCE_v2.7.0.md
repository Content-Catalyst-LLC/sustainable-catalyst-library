# Evidence API Reference — Knowledge Library v2.7.0

Base namespace:

```text
/wp-json/sc-library/v1
```

## List Evidence Notes

```http
GET /evidence-notes
```

Optional query parameters:

```text
search
source_id
document_id
project_id
claim_id
page
per_page
```

Anonymous responses contain only public Evidence Notes.

## Create Evidence Note

```http
POST /evidence-notes
```

Example:

```json
{
  "title": "Boundary quotation",
  "content": "Exact quotation text.",
  "summary": "Why this passage matters.",
  "evidence_type": "direct-quotation",
  "source_id": 123,
  "locator_type": "page",
  "locator_start": "12",
  "visibility": "private",
  "review_status": "draft",
  "confidence": 4,
  "project_ids": [50],
  "claim_links": [
    {
      "claim_id": 456,
      "relation": "supports",
      "strength": 4,
      "note": "Supports the claim's central mechanism."
    }
  ]
}
```

Requires `edit_posts`.

## Read or update Evidence Note

```http
GET  /evidence-notes/{id}
POST /evidence-notes/{id}
```

Private reads and updates require permission to edit the note.

REST updates support:

```json
{
  "reverified": true
}
```

Use `reverified` only after checking changed evidence and locator fields.

## Update links

```http
POST /evidence-notes/{id}/links
```

```json
{
  "links": [
    {
      "claim_id": 456,
      "relation": "qualifies",
      "strength": 3,
      "note": "Limits the claim to high-income contexts."
    }
  ]
}
```

## List Claims

```http
GET /claims
```

Optional:

```text
search
project_id
page
per_page
```

## Create Claim

```http
POST /claims
```

```json
{
  "title": "Short claim label",
  "statement": "Full research claim.",
  "claim_type": "causal",
  "status": "draft",
  "confidence": 65,
  "visibility": "private",
  "scope": "Defined population and time period.",
  "assumptions": "Assumptions used by the claim.",
  "limitations": "Known limitations.",
  "counterclaim": "Plausible alternative explanation.",
  "project_ids": [50]
}
```

## Read or update Claim

```http
GET  /claims/{id}
POST /claims/{id}
```

Use:

```json
{
  "reverified": true
}
```

only after rechecking a changed verified claim.

## Claim evidence packet

```http
GET /claims/{id}/evidence
```

## Project evidence packet

```http
GET /projects/{id}/evidence
```

## Export

```http
POST /evidence/export
```

Examples:

```json
{
  "type": "note",
  "id": 123,
  "format": "markdown"
}
```

```json
{
  "type": "claim",
  "id": 456,
  "format": "json"
}
```

```json
{
  "type": "project",
  "id": 50,
  "format": "markdown"
}
```

Supported record types:

```text
note
claim
project
```

Supported formats:

```text
json
markdown
```

## Public boundary

Public API access never bypasses record visibility, publication status, Source publication, document publication, or project visibility.
