# Sustainable Catalyst Knowledge Library v3.7.0

## Research Librarian Document Intelligence

v3.7.0 adds a document-intelligence layer for Knowledge Library documents. It builds deterministic section and chunk indexes, title-aware retrieval profiles, summaries, key points, suggested research questions, recurring-term indexes, citation and knowledge-gap signals, document comparisons, and structured Research Librarian context bundles.

The release does not treat generated summaries, key points, questions, comparisons, or gap signals as authoritative scholarship. They are retrieval, navigation, and review aids that must be checked against the original document.

## Document profiles

Each Knowledge Library document can receive:

- source hash;
- source type and character count;
- page count;
- detected sections;
- bounded retrieval chunks;
- summary;
- key points;
- suggested research questions;
- recurring terms;
- title aliases;
- citation signals;
- knowledge and documentation gaps;
- Topic, Concept, and Named Entity references;
- analyzer version and timestamps.

Status lifecycle:

```text
Pending
Indexing
Ready
Partial
Stale
Failed
Excluded
```

## Section and chunk indexing

The deterministic index supports:

```text
120 sections maximum
500 chunks maximum
220 words per chunk
40 words of overlap
500,000 source characters maximum
```

HTML heading structure is used when available. Flattened PDF text receives a bounded fallback section detector.

Each section and chunk receives an identifier, sequence, word count, and SHA-256 text hash.

## Title-aware retrieval

Retrieval ranking recognizes:

```text
Exact title
Title prefix
Title contains query
Exact alias
Alias contains query
Title-token overlap
Recurring-term overlap
Summary overlap
```

Results include the score and matching reasons.

The retrieval layer is designed to improve Research Librarian behavior when users ask for a known title or title-like phrase.

## Summaries and key points

The built-in analyzer uses deterministic sentence selection and document structure.

It does not call an AI provider by default.

A trusted adapter can enrich the profile through:

```text
sc_library_document_intelligence_analysis
```

Adapters must preserve the base schema, document ID, source hash, and public/private boundaries.

## Research questions

Suggested questions can reference:

- the document title;
- detected sections;
- recurring terms;
- evidence and Sources;
- limitations;
- related Knowledge Library documents.

## Citation signals

The analyzer records structural indicators:

- DOI references;
- URLs;
- numeric citations;
- author-year citations;
- reference headings;
- claim-like sentences;
- possible uncited claim signals.

These are review signals, not determinations that a citation is required or sufficient.

## Knowledge-gap signals

Potential review signals include:

```text
Insufficient readable text
Missing document structure
Methods not detected
Limitations not detected
Citations not detected
Possible citation gaps
Terms not detected
Index truncation
Knowledge Topics missing
Concepts missing
```

## Document comparison

Up to five documents can be compared.

The comparison includes:

- summaries;
- key points;
- shared terms;
- distinctive terms;
- shared section labels;
- pairwise term similarity;
- Topic and Concept identifiers;
- recorded gap signals.

Similarity does not establish agreement, contradiction, quality, or scholarly equivalence.

## Research Librarian integration

The release adds two context filters:

```text
sc_library_research_librarian_document_context
sc_library_research_librarian_project_context
```

The document context contains the intelligence profile, section index, aliases, and recurring terms.

Project context can contain condensed profiles for up to 20 linked Knowledge Library documents.

## Document Intelligence Center

New location:

```text
SC Library → Document Intelligence
```

The Center includes:

- document status metrics;
- ready, stale, pending, failed, and excluded counts;
- section, chunk, and gap totals;
- resumable index migration;
- title-aware retrieval testing;
- document comparison;
- document intelligence register.

Document editors receive:

- public-summary control;
- indexing exclusion control;
- analyze and force-reindex actions;
- summary and key points;
- suggested questions;
- detected terms;
- section index;
- gap signals;
- status and index metrics.

## Reindex jobs

Batch jobs support:

- selected document IDs;
- force-reindex setting;
- queued, running, complete, and failed item states;
- stable cursor;
- attempts;
- messages;
- timestamps;
- bounded job history.

## Migration

Existing Knowledge Library documents are indexed in resumable 20-document batches.

The migration:

- initializes public and exclusion controls;
- builds the first intelligence profile;
- uses stable post-ID cursors;
- uses a 180-second lock;
- preserves bounded failures;
- supports hourly cron, REST, AJAX, and WP-CLI.

A daily stale-document process reindexes up to 10 pending, stale, partial, or failed documents.

## Shortcodes

```text
[sc_document_intelligence document="123"]
[sc_document_key_points document="123"]
[sc_document_research_questions document="123"]
[sc_document_comparison documents="123,456"]
```

Public intelligence requires a published document with the public-intelligence option enabled.

## REST API

```text
GET/POST /wp-json/sc-library/v1/documents/{id}/intelligence
GET      /wp-json/sc-library/v1/documents/{id}/sections
GET      /wp-json/sc-library/v1/documents/{id}/chunks
GET      /wp-json/sc-library/v1/document-intelligence/search
POST     /wp-json/sc-library/v1/document-intelligence/compare
POST     /wp-json/sc-library/v1/document-intelligence/jobs
GET/POST /wp-json/sc-library/v1/document-intelligence/jobs/{id}
GET      /wp-json/sc-library/v1/document-intelligence/dashboard
GET/POST /wp-json/sc-library/v1/document-intelligence/migration
```

## WP-CLI

```text
wp sc-library document-intelligence analyze DOCUMENT_ID
wp sc-library document-intelligence search "query"
wp sc-library document-intelligence compare 123 456
wp sc-library document-intelligence job-create 123 456
wp sc-library document-intelligence job-run JOB_ID
wp sc-library document-intelligence migrate --limit=20
wp sc-library document-intelligence dashboard
```

## Compatibility

v3.7.0 retains:

- v3.6.0 Institutional Collections and Archive Management;
- v3.5.0 Research Quality and Governance Center;
- v3.4.0 cross-product research handoffs;
- v3.3.0 Knowledge Pathways and Article Maps;
- v3.2.0 Topics, Concepts, and Document Relationships;
- v3.1.0 Source versioning and research integrity;
- v3.0.x connected research and migration reliability;
- all retained citation, connector, holdings, OCR, Evidence Note, Claim, PDF, and repository systems.
