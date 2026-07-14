# Citation Regression Matrix — v2.5.1

The release contract covers these formatter cases:

| Case | Expected behavior |
|---|---|
| One personal author | Family name and initials in reference; family name in text |
| Two personal authors | `and` joins both names |
| Three or more authors | All retained in reference; `et al.` in text |
| Institutional author | Full institution in reference |
| Short institutional author | Abbreviation used only in text |
| Missing author | Shortened title leads citation |
| Missing date | `n.d.` |
| Same author and year | Alphabetic year suffixes |
| Journal single page | `p.` |
| Journal range | `pp.` and en dash |
| Book numeric edition | Ordinal plus `edn.` |
| Book chapter | `In:` editor statement |
| DOI | Normalized DOI output |
| URL | Canonical URL with access date when supplied |
| Page locator | `p.` |
| Page-range locator | `pp.` |
| Section locator | Preserved |
| Valid ISBN-10 | Accepted |
| Valid ISBN-13 | Accepted |
| Invalid identifier | Reliability error and exclusion from duplicate key |

Additional static contracts cover metadata history, verification invalidation, duplicate decisions, project relationship restoration, rate limiting, idempotency, conflict detection, ETag headers, incremental migration, and compatibility with earlier document/OCR systems.
