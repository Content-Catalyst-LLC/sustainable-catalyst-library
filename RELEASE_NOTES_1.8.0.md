# Library v1.8.0 — Foundations and Documentation Library

## Release summary

v1.8.0 adds a curated institutional documentation layer to Sustainable Catalyst Library. The Foundations page can now present current webpages, repository documentation, methodology records, policies, product briefs, release records, PDF snapshots, and archives through one compact searchable interface.

## Major changes

- Added the Foundations Documentation Library collection and documentation-category taxonomy.
- Added the Documentation Authority editor panel.
- Added explicit status, version, responsible area, authority, review, dependency, and history metadata.
- Added featured living documentation and expandable public document panels.
- Added source-of-truth warnings and authoritative-source indicators.
- Added public filters for category, status, area, archives, and update order.
- Added administration diagnostics for missing authority, missing category, missing area, published drafts, overdue reviews, missing replacements, and circular references.
- Added dedicated public REST endpoints and the `[sc_foundations_library]` shortcode.
- Extended the existing `[sc_library]` shortcode with `collection="foundations" mode="documentation"`.
- Extended relationship types for documentation governance and history.

## Upgrade requirement

After installing v1.8.0:

1. Save the SC Library settings.
2. Rebuild the Library index.
3. Assign documentation records to the Foundations collection.
4. Assign Documentation Categories.
5. Complete the Documentation Authority panel for each current record.
6. Clear WordPress, page, Cloudflare, and browser caches.
7. Test current, PDF snapshot, superseded, and archived records in a private browser window.
