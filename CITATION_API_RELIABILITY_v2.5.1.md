# Citation API Reliability — v2.5.1

Base namespace:

```text
/wp-json/sc-library/v1
```

## Optimistic concurrency

Source update requests may include:

```json
{
  "expected_modified_gmt": "2026-07-14T18:30:00+00:00"
}
```

or the HTTP header:

```text
If-Unmodified-Since: Tue, 14 Jul 2026 18:30:00 GMT
```

When the stored Source changed after the supplied timestamp, the API returns HTTP `409` with the current `modified_gmt` value.

## Idempotent source creation

Authenticated source-creation requests may include:

```text
Idempotency-Key: project-104-source-crossref-10.1234-example
```

A repeated request from the same WordPress user within 24 hours returns the original Source record rather than creating a duplicate.

## Write limits

Citation write endpoints are limited per authenticated WordPress user. The default is:

```text
60 operations per hour per operation class
```

The API returns HTTP `429` when the limit is exceeded.

Filters:

```text
sc_library_citation_write_limit
sc_library_citation_write_window
sc_library_citation_disable_write_rate_limit
```

## Reliability endpoint

```http
GET /sources/{id}/reliability
```

Public published sources return:

```json
{
  "schema": "sc-library-source-reliability/1.0",
  "source_id": 123,
  "status": "ready",
  "score": 92
}
```

Editors also receive field-level validation issues.

## Change history

```http
GET /sources/{id}/history
```

Requires permission to edit the Source. History can include private metadata snapshots and is never public.

## Duplicate review

```http
GET /sources/{id}/duplicates
POST /sources/{id}/duplicates
```

Example update:

```json
{
  "canonical_id": 123,
  "decisions": {
    "456": "same-work",
    "789": "not-duplicate"
  }
}
```

Requires permission to edit the Source.

## Response headers

Citation API responses include:

```text
ETag
Cache-Control: private, max-age=0, must-revalidate
Last-Modified
```

`Last-Modified` is included for source-specific routes.
