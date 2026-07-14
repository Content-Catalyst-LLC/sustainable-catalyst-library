# Citation Formatting Reliability — v2.5.1

## Personal authors

Preferred editor input remains:

```text
Family name | Given names | Suffix | ORCID
```

The parser also accepts:

```text
Family name, Given names
```

Examples:

```text
de la Cruz, Ana María
O'Neil | Shaun | Jr. | 0000-0002-1825-0097
Smith-Jones | Renée
```

Names retain particles, apostrophes, hyphens, and Unicode characters. ORCID values are stored only after checksum validation.

## Institutional authors

Use **Organizational author** for the complete reference-list name:

```text
World Health Organization
```

Use **Short institutional author** only when a recognized abbreviation improves in-text citations:

```text
WHO
```

Generated results:

```text
World Health Organization (2026). ...
(WHO, 2026)
```

## Missing values

No author:

```text
Title of the source (2026). ...
```

No date:

```text
Author (n.d.). ...
```

A missing creator or year produces a review warning but does not prevent saving the record.

## Locators

```text
44          → p. 44
44-48       → pp. 44–48
p. 44       → p. 44
pp. 44–48   → pp. 44–48
para. 7     → para. 7
ch. 3       → ch. 3
sec. 3.2    → sec. 3.2
§ 12        → § 12
```

## Editions

```text
2
2nd
2nd edition
```

are normalized to:

```text
2nd edn.
```

Free-text editions that cannot be reduced to a number retain their wording and receive the `edn.` suffix once.

## Pages

Single pages use `p.`. Page ranges and comma-separated page sequences use `pp.`. Hyphen, en dash, and em dash separators are normalized to an en dash.

## Identifiers

DOI values are cleaned of `doi:` and DOI resolver prefixes. ISBN values are stored without separators. Reliability checks verify DOI syntax and ISBN checksums before identifiers participate in duplicate matching.

## Citation cache

Generated citations are cached by source, style, mode, and locator. Any structured `_sc_source_` metadata change invalidates the cache. The cache is bounded to 40 entries per source.
