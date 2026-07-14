# Provider Reference — Knowledge Library v2.6.0

## Crossref

Mode:

```text
Scholarly search and DOI metadata
```

Configuration:

```text
Contact email recommended
No key required for the public/polite service
```

Mapped fields include DOI, title, contributors, publication dates, container title, publisher, volume, issue, pages, abstract, language, ISBN, and ISSN.

## OpenAlex

Mode:

```text
Works search
DOI location lookup
Open-access locations
Citation count and topics
```

Configuration:

```text
OpenAlex API key
```

The connector reads rate-limit behavior from HTTP status responses and applies local backoff.

## DataCite

Mode:

```text
Current /dois JSON:API search
```

Mapped resources include datasets, software, books, chapters, reports, conference materials, theses, and journal articles.

The connector does not use the deprecated DataCite `/works` endpoint.

## PubMed

Mode:

```text
NCBI ESearch + ESummary
```

Mapped identifiers include PMID, PMCID, DOI, and PII when available.

## PubMed Central

Mode:

```text
NCBI ESearch + ESummary
Open biomedical archive routing
```

A PMC record is treated as an open-access location.

## Library of Congress

Mode:

```text
loc.gov JSON digital-collection search
```

This connector searches material represented through loc.gov digital collections. It should not be interpreted as a complete search of all physical Library of Congress catalog holdings.

## Open Library

Mode:

```text
Low-volume, human-facing book search and ISBN lookup
```

Requests identify the application and contact email. Responses are cached.

The connector is not intended as a bulk book-data backend.

## Google Books

Mode:

```text
Volume search and ISBN lookup
Preview and reading links
```

Configuration:

```text
Google Books API key
```

The connector does not use OAuth or modify a user's Google Books shelves.

## Unpaywall

Mode:

```text
DOI-based open-access location lookup
```

Configuration:

```text
Contact email
```

Stored location details can include license, version, host type, URL, and checked time.

## Google Scholar

Mode:

```text
Outbound browser search handoff
```

No automated result harvesting, scraping, or citation-count storage is performed.

Researchers can use Scholar's own citation export controls and later import standardized citation files in a future release.

## WorldCat

Mode:

```text
Outbound public search handoff
```

The public handoff requires no stored WorldCat credentials. Licensed OCLC APIs are not included in this release.

## Provider extension filter

Additional connectors can be described through:

```text
sc_library_discovery_providers
```

Allowed request hosts can be extended cautiously through:

```text
sc_library_connector_allowed_hosts
```

A custom connector must still implement server-side normalization and follow the common discovery-result schema.
