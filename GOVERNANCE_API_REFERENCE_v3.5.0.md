# Governance API Reference — v3.5.0

Base:

```text
/wp-json/sc-library/v1
```

## Project quality

Read:

```http
GET /projects/{id}/quality
```

Re-evaluate:

```http
POST /projects/{id}/quality
```

## Project governance

Read:

```http
GET /projects/{id}/governance
```

Update:

```http
POST /projects/{id}/governance
```

Example:

```json
{
  "profile": "high-assurance",
  "policies": [101, 102],
  "public_summary": true
}
```

## Create review

```http
POST /projects/{id}/reviews
```

Example:

```json
{
  "domain": "methodology",
  "outcome": "conditional",
  "findings": "The method is suitable with one limitation.",
  "actions": "Add a sensitivity analysis.",
  "due_date": "2026-08-15"
}
```

## Create issue or exception

```http
POST /projects/{id}/issues
```

Example:

```json
{
  "title": "Missing corroborating source",
  "domain": "evidence",
  "severity": "high",
  "description": "The central claim relies on one source.",
  "actions": "Add independent corroboration.",
  "due_date": "2026-08-01",
  "exception": false
}
```

## Transition gate

```http
POST /projects/{id}/gate
```

Example:

```json
{
  "gate": "quality-review",
  "note": "Ready for formal evidence and methodology review."
}
```

Approval or publication may return HTTP 409 when quality controls block the transition.

## Transparency

```http
GET /projects/{id}/transparency
```

Anonymous access requires a public project with public transparency enabled.

## Dashboard

```http
GET /governance/dashboard
```

Requires editor permission.

## Migration

```http
GET  /governance/migration
POST /governance/migration?limit=20
```

Requires administrator permission.

## Cache boundaries

Private governance responses receive:

```text
Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
Pragma: no-cache
Vary: Cookie, Authorization
```
