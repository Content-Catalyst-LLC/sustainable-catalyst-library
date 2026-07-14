# Citation and Research Source API — v2.5.0

Base namespace:

```text
/wp-json/sc-library/v1
```

## List and search sources

```http
GET /sources
GET /search
```

Parameters:

```text
search
type
topic
project
page
per_page
status
```

Public requests are restricted to published sources. Authorized editors may request draft, pending, private, future, or all statuses.

## Read a source

```http
GET /sources/{id}
```

Published sources are public. Non-public records require permission to edit the source.

## Create a source

```http
POST /sources
```

Requires `edit_posts`.

Example JSON:

```json
{
  "title": "Systems for public knowledge",
  "status": "draft",
  "source_type": "journal-article",
  "authors": [
    {
      "family": "Ahmad",
      "given": "Tariq",
      "suffix": "",
      "orcid": ""
    }
  ],
  "year": "2026",
  "container_title": "Journal of Open Systems",
  "volume": "12",
  "issue": "3",
  "pages": "41-58",
  "doi": "10.1234/example",
  "metadata_verified": false,
  "project_ids": [104]
}
```

## Update a source

```http
POST /sources/{id}
PUT /sources/{id}
PATCH /sources/{id}
```

Requires permission to edit the source.

## Format a citation

```http
GET /sources/{id}/citation
```

Parameters:

```text
style=harvard
mode=reference
mode=in-text
mode=citation-key
locator=44
```

The response includes the requested citation, reference, HTML reference, in-text citation, citation key, and style schema.

## Project bibliography

```http
GET /projects/{id}/bibliography
```

A published project with Public bibliography visibility can be read without authentication. Other projects require edit permission.

## Replace project source collection

```http
POST /projects/{id}/sources
```

Example:

```json
{
  "source_ids": [101, 104, 118]
}
```

Requires permission to edit the project.

## Citation styles

```http
GET /citation/styles
```

v2.5.0 ships with `harvard`. Additional formatters can be registered with:

```text
sc_library_citation_styles
sc_library_format_citation
```

## Authentication

The API uses standard WordPress REST authentication. Suitable methods include an authenticated WordPress session with REST nonce or WordPress Application Passwords over HTTPS.

The plugin does not expose private notes or metadata provenance to unauthenticated requests.
