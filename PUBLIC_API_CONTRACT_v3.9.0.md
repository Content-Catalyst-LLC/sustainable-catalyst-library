# Public API Contract — v3.9.0

## Stability

The v1 namespace is a compatibility contract.

Breaking field removal or semantic change requires a new API namespace or schema version.

Additive fields may be introduced within v1.

## Pagination

Public collections use opaque cursors.

Defaults:

```text
Default limit: 25
Maximum limit: 100
```

Clients must not decode or construct cursors as a long-term integration strategy.

## Conditional requests

Public GET responses can return:

```text
ETag
Cache-Control: public, max-age=300, stale-while-revalidate=600
Vary: Accept, Accept-Encoding
```

A matching `If-None-Match` can produce HTTP 304.

## Security headers

Knowledge Library API responses include:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
X-SC-API-Version: 1.0
X-SC-Plugin-Version: 3.9.0
```

Private responses receive no-store cache controls.

## Public record boundary

A public record can contain title, slug, URL, excerpt, publication time, modification time, public taxonomy terms, public source-file metadata, content hash, and approved public intelligence fields.

It must not contain private text, internal workflow notes, raw API credentials, reviewer identity, conflict details, private filesystem paths, or administrative author IDs.

## Error behavior

Use standard HTTP classes:

```text
400 invalid request
401 missing or invalid authentication
403 insufficient scope or permission
404 record not public or not found
409 state conflict
413 import too large
429 rate limited
500 internal export failure
502 incompatible federation peer
```
