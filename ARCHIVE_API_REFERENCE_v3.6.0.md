# Archive API Reference — v3.6.0

Base:

```text
/wp-json/sc-library/v1
```

## Collections

List:

```http
GET /archives/collections
```

Optional parameters:

```text
search
page
per_page
include_private
```

Read:

```http
GET /archives/collections/{id}
```

## Finding aid

```http
GET /archives/collections/{id}/finding-aid
```

Anonymous access requires public finding-aid eligibility.

## Preservation

Read current audit:

```http
GET /archives/collections/{id}/preservation
```

Run and save audit:

```http
POST /archives/collections/{id}/preservation
```

Requires collection edit permission.

## Create disposition

```http
POST /archives/collections/{id}/dispositions
```

Example:

```json
{
  "component_id": 456,
  "action": "review",
  "reason": "Scheduled appraisal after project closure.",
  "due_date": "2027-07-15"
}
```

## Transition disposition

```http
POST /archives/dispositions/{id}/status
```

Example:

```json
{
  "status": "approved",
  "note": "Approved by the institutional records committee."
}
```

A legal hold can return HTTP 409.

## Dashboard

```http
GET /archives/dashboard
```

Requires editor permission.

## Migration

```http
GET  /archives/migration
POST /archives/migration?limit=20
```

Requires administrator permission.

## Cache boundaries

Private archive responses receive:

```text
Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
Pragma: no-cache
Vary: Cookie, Authorization
```
