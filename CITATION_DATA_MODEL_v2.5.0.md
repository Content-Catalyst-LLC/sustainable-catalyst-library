# Citation and Research Source Data Model — v2.5.0

## Source record

Post type:

```text
sc_research_source
```

Public archive:

```text
/sources/
```

Primary taxonomies:

```text
sc_source_type
sc_source_topic
```

Source records use WordPress title, excerpt, content, publication status, revisions, taxonomies, and structured post metadata.

## Research project

Post type:

```text
sc_research_project
```

Project records are not directly publicly queryable. Their bibliographies can be exposed through the shortcode or REST API when the record is published and its bibliography visibility is Public.

## Relationship model

Project-to-source:

```text
_sc_project_source_ids
```

Source-to-project reverse index:

```text
_sc_source_project_ids
```

Source-to-Knowledge-Library-document:

```text
_sc_source_related_document_ids
```

Relationships are synchronized during Source and Project saves and cleaned during permanent deletion.

## Duplicate model

Normalized comparison fields:

```text
_sc_source_normalized_doi
_sc_source_normalized_isbn
_sc_source_normalized_url
_sc_source_fingerprint
```

Possible duplicate IDs:

```text
_sc_source_duplicate_matches
```

The fingerprint is a SHA-256 hash of normalized primary creator, year, and title. Duplicate matches are review signals, not automated merge decisions.

## Citation identity

```text
_sc_source_author_year_key
_sc_source_year_suffix
_sc_source_citation_key
```

Same-author, same-year records are title-sorted and assigned alphabetical suffixes.

## Private fields

```text
_sc_source_private_notes
_sc_source_metadata_provenance
_sc_source_duplicate_matches
```

These fields are omitted from public Source responses and pages.
