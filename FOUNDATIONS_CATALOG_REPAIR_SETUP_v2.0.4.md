# Sustainable Catalyst Foundations v2.0.4

## Documentation Catalog Server-Rendering Repair

The canonical Foundations page previously depended on a browser-side request to:

`/wp-json/sustainable-catalyst/v1/library/documentation`

When that request failed, visitors saw:

> The documentation library could not be loaded.

v2.0.4 removes that dependency from the canonical Foundations page. Existing
Foundation Document records are rendered directly by WordPress.

### Preserved behavior

- The Knowledge Library documentation REST API remains available elsewhere.
- Existing records, PDFs, metadata, citations, and routes remain intact.
- `/institution/foundations/` remains the canonical institutional page.
- Individual documents remain under `/foundation-documents/<slug>/`.
- Search and type/status filters remain available without JavaScript.
- No page content needs to be edited manually.
