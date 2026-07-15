# Review and Publishing API — v3.8.0

Base:

```text
/wp-json/sc-library/v1
```

## Review cycles

Create:

```http
POST /reviews
```

Read or update:

```http
GET  /reviews/{id}
POST /reviews/{id}
```

Add note:

```http
POST /reviews/{id}/notes
```

Resolve note:

```http
POST /review-notes/{id}
```

Record decision:

```http
POST /reviews/{id}/decision
```

Public transparency:

```http
GET /reviews/{id}/transparency
```

## Publication packages

Create:

```http
POST /publication-packages
```

Read or update:

```http
GET  /publication-packages/{id}
POST /publication-packages/{id}
```

Evaluate:

```http
POST /publication-packages/{id}/evaluate
```

Transition:

```http
POST /publication-packages/{id}/transition
```

Example:

```json
{
  "status": "scheduled",
  "note": "Approved for the institutional release window."
}
```

## Operations

```http
GET /review-publishing/dashboard
GET /review-publishing/migration
POST /review-publishing/migration?limit=20
```

Private responses use no-store cache headers.
