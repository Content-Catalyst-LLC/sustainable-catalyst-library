# Document Intelligence API — v3.7.0

Base:

```text
/wp-json/sc-library/v1
```

## Read or analyze a document

```http
GET  /documents/{id}/intelligence
POST /documents/{id}/intelligence
```

Optional POST parameter:

```text
force=true
```

## Sections

```http
GET /documents/{id}/sections
```

Public results omit section text.

## Chunks

```http
GET /documents/{id}/chunks
```

Requires document edit permission.

## Search

```http
GET /document-intelligence/search?q=planetary%20boundaries
```

Optional:

```text
limit
include_private
```

## Compare

```http
POST /document-intelligence/compare
```

Example:

```json
{
  "document_ids": [123, 456],
  "persist": true
}
```

## Create a reindex job

```http
POST /document-intelligence/jobs
```

Example:

```json
{
  "document_ids": [123, 456, 789],
  "force": true,
  "label": "July full reindex"
}
```

## Read or run a job

```http
GET  /document-intelligence/jobs/{id}
POST /document-intelligence/jobs/{id}?limit=5
```

## Dashboard

```http
GET /document-intelligence/dashboard
```

Requires editor permission.

## Migration

```http
GET  /document-intelligence/migration
POST /document-intelligence/migration?limit=20
```

Requires administrator permission.

## Private caching

Private responses receive:

```text
Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
Pragma: no-cache
Vary: Cookie, Authorization
```
