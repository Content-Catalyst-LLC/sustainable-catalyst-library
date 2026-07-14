# Harvard — Sustainable Catalyst Profile v1.0

Schema:

```text
sc-library-citation-style/1.0
```

Style ID:

```text
harvard
```

## General form

Reference-list entries use an author-date structure:

```text
Creator (Year). Title. Publication details. Identifier or access statement.
```

In-text citations use:

```text
(Creator, Year)
(Creator, Year, p. 44)
(Creator, Year, pp. 44–48)
```

## Creators

One author:

```text
Ahmad, T.
(Ahmad, 2026)
```

Two authors:

```text
Ahmad, T. and Smith, J.
(Ahmad and Smith, 2026)
```

Three or more authors retain all names in the reference list and use `et al.` in text.

Organizational authors are retained as written.

When no author or organization is available, a shortened title is used.

## Dates

The publication year is preferred. The year from the full publication date is used when no standalone year is stored.

No date:

```text
n.d.
```

Sources with the same primary creator and year receive alphabetical suffixes based on title order:

```text
2026a
2026b
```

## Source-specific treatment

- Journal article titles use quotation marks; journal titles are emphasized.
- Book titles are emphasized.
- Book chapters use chapter-title quotation marks and an `in` editor statement.
- Reports, datasets, standards, theses, software, and archival sources use source-specific labels or identifiers.
- DOI is preferred over the canonical URL when both are present.
- URL records can include an access date.
- Page ranges use an en dash in generated output.

## Configuration boundary

Harvard citation rules differ among universities and publishers. This profile is a transparent default. It can be replaced or extended through WordPress filters without rewriting Source records.
