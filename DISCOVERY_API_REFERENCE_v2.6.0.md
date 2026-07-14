# Discovery API Reference — Knowledge Library v2.6.0

Base namespace:

```text
/wp-json/sc-library/v1
```

## Connector registry

```http
GET /connectors
```

Returns connector names, modes, enabled state, availability, and supported operations. It does not expose API keys.

## Search one provider

```http
GET /discovery/search?provider=crossref&query=planetary%20boundaries&limit=8
```

Default permission:

```text
edit_posts
```

Response:

```json
{
  "schema": "sc-library-federated-search/1.0",
  "provider": "crossref",
  "provider_name": "Crossref",
  "query": "planetary boundaries",
  "result_count": 8,
  "cached": false,
  "results": [
    {
      "schema": "sc-library-discovery-result/1.0",
      "provider": "crossref",
      "provider_record_id": "10.xxxx/example",
      "title": "Example title",
      "authors": [],
      "year": "2026",
      "source_type": "journal-article",
      "doi": "10.xxxx/example",
      "url": "https://doi.org/10.xxxx/example",
      "import_token": "short-lived-user-token"
    }
  ]
}
```

Each request searches one provider. Clients can execute several provider requests concurrently.

## Import a result

```http
POST /discovery/import
```

JSON:

```json
{
  "token": "short-lived-user-token",
  "mode": "fill_empty",
  "source_id": 0,
  "project_id": 104
}
```

Modes:

```text
fill_empty
overwrite
```

`fill_empty` is the default.

The token is bound to the WordPress user who performed the search and expires after one hour.

## Locate a Source

```http
GET /sources/{id}/locate
```

Optional:

```text
force=true
```

Location responses can include open access, previews, provider records, library catalogs, OpenURL holdings checks, proxy actions, interlibrary loan, Google Scholar, and WorldCat.

## Library profiles

```http
GET /library-profiles
```

Requires `edit_posts`.

The response excludes private profile notes.

## Public discovery

A site owner can enable public search with:

```php
add_filter( 'sc_library_allow_public_discovery', '__return_true' );
```

Imports and Source-specific location checks still require WordPress permissions.

## Error behavior

Common codes:

```text
provider_unavailable
provider_backoff
connector_rate_limited
connector_transport_error
connector_http_error
connector_invalid_json
expired_import_token
import_token_owner
source_not_found
```

HTTP 429 is used for local request limits. Provider failures generally return 502 or 503 while preserving other provider results.
