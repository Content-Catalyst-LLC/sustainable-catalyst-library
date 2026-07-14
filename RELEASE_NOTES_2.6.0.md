# Sustainable Catalyst Knowledge Library v2.6.0

## Scholarly and Library Database Connectors

This release adds a federated source-discovery and material-location layer to the v2.5.x Citation and Research Source Manager.

It preserves the existing Source record, Research Project, Harvard formatter, source-reliability, public source routes, document repository, PDF conversion, bulk import, and OCR systems.

## Live metadata connectors

v2.6.0 includes normalized connectors for:

- Crossref
- OpenAlex
- DataCite
- PubMed
- PubMed Central
- Library of Congress digital collections
- Open Library
- Google Books

Each connector maps provider-specific fields into the existing Research Source schema.

Normalized results can include:

```text
Title
Authors or organizational creator
Publication year and date
Source type
Journal, book, collection, or repository
Publisher
Volume, issue, and pages
DOI
ISBN
PMID and PMCID
Provider identifiers
Abstract or description
Topics
Canonical URL
Open-access URL
Preview URL
Full-text status
Citation count where provided
```

## Open-access and material location

The Source locator can check:

- Unpaywall DOI locations
- OpenAlex access locations
- PubMed and PubMed Central records
- Open Library ISBN records
- Google Books previews and reading links
- Configured library catalogs
- OpenURL resolvers
- Library proxy links
- Interlibrary-loan pages

Locations are deduplicated by URL and stored with provider, type, access status, and checked timestamp.

## Google Scholar and WorldCat

Google Scholar and WorldCat are implemented as browser search handoffs.

The plugin does not scrape Google Scholar or automatically harvest Google Scholar results or citation counts.

Available handoffs include:

```text
Search Google Scholar
Search WorldCat
Open DOI
Open PubMed
Search configured library catalogs
Check library holdings
Open through a library proxy
Request through interlibrary loan
```

## Library profiles

The new private `sc_library_profile` record supports:

- Library name
- Service region
- Homepage
- Catalog URL template
- OpenURL resolver base
- Interlibrary-loan page
- Optional proxy prefix
- Enabled/disabled state
- Private administrative notes

Catalog templates support:

```text
{query}
{title}
{author}
{doi}
{isbn}
{pmid}
```

Only published and enabled profiles can appear on public Source pages. Draft and private profiles remain administrative.

## Discovery workspace

Open:

```text
SC Library → Source Discovery
```

The workspace includes:

```text
Discover Sources
Providers
Libraries
Import History
```

Provider searches run independently so one unavailable service does not block the other result groups.

## Provenance-aware imports

Every search result receives a short-lived, user-specific import token.

Imports:

- Create a Draft Research Source by default
- Preserve provider and provider-record identifiers
- Store field-level provenance
- Record an import history
- Preserve empty-field-only updates by default
- Support an explicit overwrite mode through the API
- Reset metadata verification when citation-critical fields change
- Rebuild citation indexes
- Recalculate source reliability
- Re-run duplicate detection
- Add the Source to a Research Project when requested

Provider results never silently overwrite populated fields in the default `fill_empty` mode.

## Connector controls

The connector layer includes:

- HTTPS-only provider requests
- Provider hostname allowlisting
- Bounded response size
- Request timeouts
- Limited redirects
- Per-user or per-IP request limits
- Provider-specific cache records
- Rate-limit and server-error backoff
- User-specific import tokens
- Cached result re-sealing for each user
- Private duplicate-match information for authorized editors only

## Configuration constants

Optional constants:

```php
define( 'SC_LIBRARY_CONNECTOR_EMAIL', 'research@example.org' );
define( 'SC_LIBRARY_OPENALEX_API_KEY', 'replace-with-key' );
define( 'SC_LIBRARY_GOOGLE_BOOKS_API_KEY', 'replace-with-key' );
define( 'SC_LIBRARY_NCBI_API_KEY', 'replace-with-key' );
define( 'SC_LIBRARY_NCBI_TOOL', 'sustainable-catalyst-library' );
```

Constants override values saved through the Provider settings screen.

## Public tools

New shortcode:

```text
[sc_source_discovery]
```

Discovery is restricted to authorized researchers by default. A site owner can explicitly enable anonymous discovery through:

```php
add_filter( 'sc_library_allow_public_discovery', '__return_true' );
```

Public Source pages now include compliant Scholar, WorldCat, DOI, PubMed, and published-library-profile handoffs.

## REST API

New endpoints under:

```text
/wp-json/sc-library/v1
```

```text
GET  /connectors
GET  /discovery/search
POST /discovery/import
GET  /sources/{id}/locate
GET  /library-profiles
```

Discovery, imports, source location, and library profiles require WordPress permissions by default.

## Compatibility

The release retains:

- v2.5.1 Citation Formatting and Source Reliability
- v2.5.0 Citation and Research Source Manager
- v2.4.1 OCR Reliability and Review Recovery
- v2.4.0 OCR and Scanned Document Processing
- v2.3.1 public repository accessibility
- v2.3.0 public document routes
- v2.2.2 bulk import and collection repair
- v2.2.1 PDF conversion and publishing reliability
