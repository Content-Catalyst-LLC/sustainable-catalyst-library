# Holdings Freshness and Library Profile Validation — v2.6.1

## Holdings record

Every access location can include:

```json
{
  "kind": "open-access",
  "provider": "unpaywall",
  "label": "Best open-access copy",
  "url": "https://example.org/document.pdf",
  "status": "open-access",
  "checked_at": "2026-07-14 12:00:00",
  "fresh_for_seconds": 604800,
  "stale_after": "2026-07-21 12:00:00",
  "stale": false,
  "verification": "provider-reported",
  "last_http_status": 0,
  "failure_count": 0
}
```

## Default freshness windows

```text
Library catalog / OpenURL / proxy / ILL: 1 day
Open access / publisher location: 7 days
Scholar and library search handoffs: 7 days
Preview / provider record / biomedical record / book record: 14 days
Canonical location: 30 days
```

The `sc_library_location_freshness_ttl` filter can change the duration by location kind.

## Manual recheck

Open a Research Source and use:

```text
Holdings Reliability → Recheck Holdings
```

The recheck invokes the v2.6.0 locator workflow with cache bypass enabled.

## Automated recheck

The hourly maintenance process rechecks a maximum of 10 due Sources per run.

The system does not claim that a catalog or OpenURL link guarantees a current holding. These actions route the researcher to the library, which remains the authority for availability and access.

## Profile validation

A profile is structurally valid only when its configured URLs:

- use HTTPS
- have an external host
- do not use localhost or `.local`
- do not use private or reserved IP addresses
- use supported catalog tokens

Supported tokens:

```text
{query}
{title}
{author}
{doi}
{isbn}
{pmid}
```

Unknown tokens produce a validation error.

## Public profile boundary

A Library Profile appears publicly only when it is:

```text
Published
Enabled
Structurally valid
```

Draft and private profiles remain available to authorized administrators but do not appear on public Source pages.
