# Cross-Product Handoff API — v3.4.0

Base:

```text
/wp-json/sc-library/v1
```

## Product registry

```http
GET /platform/products
```

Returns enabled product contracts and launch routes.

## Project identity

```http
GET /projects/{id}/platform-identity
```

A public project can expose its stable identity. Private project identity requires project permission.

## Project handoffs

```http
GET /projects/{id}/handoffs
POST /projects/{id}/handoffs
```

Creation example:

```json
{
  "target_product": "decision-studio",
  "handoff_type": "evidence-packet",
  "status": "ready",
  "issue_token": true,
  "sections": ["project", "bibliography", "evidence", "integrity"],
  "request": {
    "instructions": "Evaluate the evidence for the proposed decision.",
    "criteria": ["Cost", "Equity", "Climate impact"],
    "scenarios": ["Baseline", "Accelerated transition"]
  }
}
```

## Delivery

```http
GET /handoffs/{uuid}?token={token}
```

Project editors may read without a token. External recipients use the expiring token.

## Recipient status

```http
POST /handoffs/{uuid}/status?token={token}
```

Example:

```json
{
  "status": "completed",
  "product": "workbench",
  "note": "Calculation report generated.",
  "result_url": "https://example.org/workbench/report/123",
  "metadata": {
    "report_id": "123"
  }
}
```

## Rotate token

```http
POST /handoffs/{uuid}/token
```

Requires project edit permission.

Optional:

```text
days=7
```

## Identity migration

```http
GET  /handoff-migration
POST /handoff-migration?limit=25
```

Requires administrator permission.
