# Title-Aware Retrieval Reference — v3.7.0

## Retrieval schema

```text
sc-library-title-aware-retrieval/1.0
```

## Ranking signals

Approximate signal weights:

```text
Exact title             120
Exact alias             100
Title prefix             85
Title contains query     65
Alias match              35
Title-token overlap      18 per token
Document-term overlap     8 per token
Summary overlap           3 per token, capped
```

The score is a ranking mechanism, not a probability.

## Title aliases

Aliases are stored privately because they can contain internal naming variants.

Public search can still use the aliases for ranking while returning only safe result fields.

## Result fields

```text
document ID
title
public URL
summary
score
matching reasons
intelligence status
analysis time
```

## Exact-title behavior

A query equal to the normalized document title should rank above partial and semantic matches.

Normalization removes punctuation, lowercases text, and collapses whitespace.

## Known-title use case

When a Research Librarian user asks:

```text
What does Planetary Boundaries and Earth System Stability say?
```

the title-aware layer should identify the title before relying on broad keyword overlap.

## Limitations

The current index is WordPress metadata based.

Very large libraries may require:

- dedicated relational index tables;
- PostgreSQL or OpenSearch;
- vector retrieval;
- field-aware ranking;
- language-specific tokenization;
- incremental index workers;
- distributed cache invalidation.
