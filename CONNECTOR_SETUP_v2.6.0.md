# Connector Setup — Knowledge Library v2.6.0

## First installation check

1. Install the generated WordPress plugin ZIP.
2. Open **SC Library → Source Discovery → Providers**.
3. Add a contact email.
4. Configure provider keys as needed.
5. Save the connector settings.
6. Test Crossref, DataCite, PubMed, Library of Congress, and Open Library.
7. Configure OpenAlex and Google Books before enabling production searches for them.
8. Search a distinctive article title.
9. Import one result as a Draft Source.
10. Review provenance, citation formatting, identifiers, duplicates, and reliability before publishing.

## Contact email

The connector email identifies Sustainable Catalyst to services that request or benefit from contact information.

It is used for:

```text
Crossref polite requests
Open Library User-Agent identification
NCBI E-utilities
Unpaywall
```

Configuration:

```php
define( 'SC_LIBRARY_CONNECTOR_EMAIL', 'research@example.org' );
```

## OpenAlex

Production integration should configure:

```php
define( 'SC_LIBRARY_OPENALEX_API_KEY', 'replace-with-key' );
```

The connector is disabled in the search interface until a key is available.

## Google Books

Configure:

```php
define( 'SC_LIBRARY_GOOGLE_BOOKS_API_KEY', 'replace-with-key' );
```

The connector uses public volume search only. It does not access a user's private Google Books library.

## NCBI

Optional configuration:

```php
define( 'SC_LIBRARY_NCBI_API_KEY', 'replace-with-key' );
define( 'SC_LIBRARY_NCBI_TOOL', 'sustainable-catalyst-library' );
```

PubMed and PubMed Central still work without a key, but NCBI applies lower limits.

## WordPress options versus constants

Administrators can save keys through the Providers tab.

Constants take precedence and are preferable when:

- configuration is deployed through source control or infrastructure
- administrators should not see saved secrets in the WordPress form
- multiple WordPress environments use different provider credentials

## Search cache

Default cache duration:

```text
12 hours
```

The Providers tab can select 1, 6, 12, or 24 hours.

Cached normalized results do not contain reusable import authorization. Every requesting user receives a new short-lived import token.

## Provider backoff

HTTP 429 and provider 5xx responses temporarily pause the affected provider.

The backoff period uses `Retry-After` when available and otherwise defaults to a bounded delay.

Other providers remain available during the pause.

## Public discovery

The discovery shortcode is private by default.

Explicit public enablement:

```php
add_filter( 'sc_library_allow_public_discovery', '__return_true' );
```

Before enabling it publicly, review:

- provider quotas
- caching
- privacy expectations
- traffic volume
- API terms
- abuse controls
- local legal and institutional requirements

Public discovery does not expose internal duplicate Source IDs.
