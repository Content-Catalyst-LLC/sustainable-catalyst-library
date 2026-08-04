# Sustainable Catalyst Library v4.1.1
## Homepage Spotlight Source Discovery Repair

Release date: August 4, 2026

## Purpose

v4.1.1 repairs the Homepage Spotlight administrator selector when valid published articles do not appear in **Selected Library record**.

## Fixed

- Isolates Spotlight source searches from front-end WordPress search filters.
- Searches eligible published records by title and content without allowing unrelated query filters to remove results.
- Accepts a canonical Sustainable Catalyst article URL in the search box.
- Supports direct lookup by WordPress post ID and post slug.
- Adds a direct title-and-slug fallback when WordPress relevance search misses a record.
- Displays the resolved canonical URL beside each search result.
- Preserves explicit click-to-select behavior; typing alone never silently selects a record.
- Rejects unpublished and password-protected source records.

## Installation

Upload `sustainable-catalyst-library-v4.1.1.zip` in WordPress and choose **Replace current with uploaded**, or run the included macOS installer against the local Git repository.

After activation, open **SC Library → Homepage Spotlight**, refresh the page, and search for the article title or paste its canonical URL.

## Expected result for the reported article

Searching either of the following should return the published record:

- `Poverty, Deprivation, and Multidimensional Development`
- `https://sustainablecatalyst.com/poverty-deprivation-and-multidimensional-development/`
