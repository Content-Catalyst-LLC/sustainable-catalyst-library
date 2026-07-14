# Sustainable Catalyst Knowledge Library v2.5.1

## Citation Formatting and Source Reliability

This patch hardens the v2.5.0 Citation and Research Source Manager without changing the Research Source record type, Research Project record type, public `/sources/` routes, shortcode names, or `sc-library/v1` API namespace.

## Citation formatting repairs

The Harvard — Sustainable Catalyst formatter now provides:

- More reliable personal-name parsing from pipe-delimited and `Family, Given` input
- Preserved apostrophes, particles, diacritics, hyphenated names, suffixes, and initials
- ORCID checksum validation
- A short institutional author field for in-text citations while retaining the full institution in reference lists
- Singular `p.` and plural `pp.` handling
- Normalized en-dash page ranges
- Page, paragraph, chapter, section, and statutory locators
- Numeric edition normalization such as `2` → `2nd edn.`
- A consistent `In:` editor statement for book chapters
- Reliable `n.d.` behavior when no publication year exists
- Bounded citation caching with automatic invalidation after source changes

## Identifier and URL reliability

The source manager now validates and normalizes:

- DOI syntax
- ISBN-10 checksums
- ISBN-13 checksums
- ORCID checksums
- PMID digit format
- Canonical URLs

Canonical URL normalization removes fragments, common tracking parameters, duplicate slashes, a leading `www.`, default ports, and unstable query ordering. Invalid DOI or ISBN values remain visible for correction but are excluded from duplicate-matching keys.

## Citation reliability status

Each Research Source now receives:

- A citation completeness score
- `Citation ready`, `Needs review`, `Invalid metadata`, or `Not checked` status
- Field-specific validation issues
- A last reliability-check timestamp
- Public reliability status and score
- Private detailed validation issues for editors

Validation is source-type aware. For example, journal articles are checked for a containing journal, book chapters for a containing book, and web sources for access dates.

## Metadata change review

v2.5.1 records bounded structured metadata history for source edits. Each history record includes:

- Change time
- WordPress user ID
- Update context
- Changed fields
- The pre-change source snapshot
- The resulting metadata hash

Editors can restore a previous metadata snapshot. Restoration also repairs project-to-source relationships and rebuilds normalized identifiers, citation keys, year suffixes, duplicate candidates, and reliability status.

Previously verified metadata is automatically returned to **Unverified** when citation-critical fields change unless the editor explicitly confirms that those fields were rechecked during the save.

## Duplicate reconciliation

Possible duplicate records can now be classified as:

- Same work
- Alternate edition or version
- Related but distinct work
- Not a duplicate
- Unreviewed

Editors can select a canonical record. Reconciliation never silently deletes, merges, or overwrites source metadata. A reviewed `Not a duplicate` decision suppresses the match from both directions after indexes are rebuilt.

## API reliability

Citation API write operations now include:

- Per-user rate limiting
- `429` responses with retry guidance
- `Idempotency-Key` support for source creation
- Optional `expected_modified_gmt` or `If-Unmodified-Since` conflict detection
- `409` responses for stale source updates
- ETag headers
- Last-Modified headers
- Private cache-revalidation headers

New endpoints:

```text
GET  /wp-json/sc-library/v1/sources/{id}/reliability
GET  /wp-json/sc-library/v1/sources/{id}/history
GET  /wp-json/sc-library/v1/sources/{id}/duplicates
POST /wp-json/sc-library/v1/sources/{id}/duplicates
```

History and duplicate-review endpoints require source-edit permissions. Public reliability responses expose status and score but not private issue details.

## Existing-source migration

An incremental administrator-only migration processes up to 25 existing Source records per admin request. It rebuilds normalized identifiers, duplicate candidates, citation caches, citation keys, reliability status, and metadata hashes without creating duplicate Source records.

## Compatibility retained

- v2.5.0 Research Source and Research Project models
- v2.5.0 public source routes and shortcodes
- v2.5.0 citation API namespace
- v2.4.1 OCR reliability and recovery
- v2.4.0 OCR processing
- v2.3.1 repository accessibility
- v2.3.0 public document routes
- v2.2.x PDF conversion and bulk import systems
