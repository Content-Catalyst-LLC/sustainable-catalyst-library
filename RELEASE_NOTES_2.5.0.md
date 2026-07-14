# Sustainable Catalyst Knowledge Library v2.5.0

## Citation and Research Source Manager

This release adds a structured citation and research-source layer to the Knowledge Library while preserving the document repository, PDF conversion, bulk import, OCR review, accessibility, and public document routes.

## Research Source records

The new `sc_research_source` record stores reusable bibliographic metadata for:

- Journal articles
- Books and book chapters
- Reports
- Webpages
- Datasets
- Legislation
- Standards
- Conference papers
- Theses and dissertations
- Videos and podcasts
- Software
- Archival material

Each source can store personal authors, an organizational author, editors, publication dates, container titles, publishers, editions, volumes, issues, pages, DOI, ISBN, PMID, URLs, access dates, language, attached source material, related Knowledge Library documents, source topics, metadata provenance, private research notes, review status, and full-text status.

## Harvard citation profile

v2.5.0 includes the **Harvard — Sustainable Catalyst** author-date profile.

It generates:

```text
(Ahmad, 2026)
(Ahmad and Smith, 2026, p. 44)
(Ahmad et al., 2026)
```

and formatted reference-list entries for the supported source types.

The formatter handles:

- Personal and organizational authors
- Editors for book chapters
- Missing authors
- Missing years using `n.d.`
- Same-author, same-year suffixes such as 2026a and 2026b
- Editions
- Journal volumes and issues
- Page ranges
- DOI and URL statements
- Access dates
- Page and page-range locators

Harvard conventions vary by institution. The built-in profile is filterable and is not presented as the only possible Harvard implementation.

## Research Projects

The new `sc_research_project` record provides a reusable project Source Library.

A project stores:

- Project code
- Research status
- Public or private bibliography visibility
- Citation style
- Bibliography heading
- Ordered source relationships

Source-to-project relationships are synchronized in both directions. Deleting a source or project cleans up the related records.

## Duplicate detection

Sources are compared using:

- Normalized DOI
- Normalized ISBN
- Normalized canonical URL
- Author-year-title fingerprint

Possible duplicates are displayed for review. The system does not automatically merge or overwrite records.

## Source material and relationships

Each Source can attach a Media Library file and connect to Foundation/PDF Documents. Public source pages retain a clear distinction between the structured citation record and the attached original or supporting material.

## Public tools

New shortcodes:

```text
[sc_source_library]
[sc_research_bibliography project="project-slug"]
[sc_source_citation id="123" mode="reference"]
```

Public Source records are available under:

```text
/sources/
/sources/{source-slug}/
/sources/type/{type}/
/sources/topic/{topic}/
```

## REST API

v2.5.0 adds a permission-controlled API under:

```text
/wp-json/sc-library/v1/
```

Endpoints include source search, source creation and updates, citation formatting, project bibliographies, project-source assignment, and citation-style discovery.

Published Source metadata is publicly readable. Draft records, private project bibliographies, private notes, metadata provenance, and write operations require appropriate WordPress permissions.

## Compatibility

The release preserves:

- v2.4.1 OCR reliability and recovery
- v2.4.0 OCR and scanned-document processing
- v2.3.1 public repository accessibility
- v2.3.0 public document repository routes
- v2.2.2 bulk import and collection repair
- v2.2.1 PDF conversion and publishing reliability
