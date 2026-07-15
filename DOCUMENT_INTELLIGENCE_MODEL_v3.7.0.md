# Document Intelligence Model — v3.7.0

## Purpose

Document intelligence helps Research Librarian retrieve, explain, compare, and route Knowledge Library documents.

It is not:

- a replacement for reading the document;
- a source of independent facts;
- a peer-review decision;
- proof that a Claim is correct;
- a legal or ethical assessment;
- a citation-quality certification.

## Source selection

The analyzer prefers:

```text
PDF extracted text
```

When extracted text is unavailable, it converts the Knowledge Library document body to readable text.

The source is bounded to 500,000 characters.

## Source integrity

Every profile stores a SHA-256 hash of:

```text
document title + readable text
```

A changed document is marked Stale after save.

## Sections

HTML headings are canonical when available.

For flattened text, the fallback detector recognizes short heading-like lines, numbered headings, and uppercase headings.

Maximum:

```text
120 sections
```

## Chunks

Chunks are retrieval units, not publication units.

Defaults:

```text
220 words
40-word overlap
500 chunks maximum
```

The overlap preserves limited context across chunk boundaries.

## Generated fields

### Summary

Selected from high-value sentences using title overlap, document position, and structural language.

### Key points

Selected from non-summary sentences using recurring terms and conclusion or evidence language.

### Questions

Generated from the title, sections, terms, evidence needs, limitations, and related-document exploration.

### Terms

Recurring normalized words are counted after stopword removal.

### Aliases

Aliases may include title variants, slug text, version text, heading-qualified titles, and Topic-qualified titles.

## Provider adapters

The deterministic profile can be enriched through:

```text
sc_library_document_intelligence_analysis
```

An adapter should:

- preserve the profile schema;
- preserve document ID and source hash;
- distinguish generated and extracted content;
- avoid transmitting private documents without authorization;
- record the provider and model;
- enforce response size and timeout limits;
- validate output structure;
- preserve deterministic fallback behavior.
