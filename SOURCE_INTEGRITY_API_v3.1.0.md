# Source Integrity API — Knowledge Library v3.1.0

Base:

```text
/wp-json/sc-library/v1
```

## Read integrity

```http
GET /sources/{id}/integrity
```

Public access requires a published Source with a public notice or high/critical status.

Authorized editors receive private review fields, snapshots, and impact.

## Update integrity

```http
POST /sources/{id}/integrity
```

Example:

```json
{
  "status": "superseded",
  "public_notice": true,
  "notice_date": "2026-07-14",
  "notice_url": "https://publisher.example/notice",
  "reason": "A revised edition replaces this record.",
  "recommended_id": 456,
  "review_status": "verified",
  "relations": [
    {
      "target_id": 123,
      "relation": "supersedes",
      "effective_date": "2026-07-14",
      "note": "Revised edition.",
      "public": true
    }
  ]
}
```

## Versions

```http
GET /sources/{id}/versions
```

Public responses contain the published version family.

Editors also receive saved snapshots.

## Impact

```http
GET /sources/{id}/impact
```

Requires Source edit permission and refreshes the impact report.

## Project Source integrity

```http
GET /projects/{id}/source-integrity
POST /projects/{id}/source-integrity
```

Update example:

```json
{
  "acknowledgements": [
    {
      "source_id": 123,
      "status": "replacement-planned",
      "note": "Replace before public report release."
    }
  ]
}
```

## Alerts

```http
GET /integrity/alerts
```

Optional:

```text
status
page
per_page
include_private
```

## Scan

```http
GET  /integrity/scan
POST /integrity/scan?limit=20
```

Requires administrator permission.
