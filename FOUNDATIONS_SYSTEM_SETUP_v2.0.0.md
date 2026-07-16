# Sustainable Catalyst Foundations v2.0.0

## Canonical Foundation Document System

Foundations v2.0.0 extends the native Knowledge Library `sc_foundation_doc` record. It does not create a second document database and does not replace the existing PDF extraction, citation, preservation, Knowledge Graph, indexing, or Research Librarian integrations.

## Included

- Governed metadata model with stable document IDs.
- Three controlled record types.
- Eight controlled authority statuses.
- Authority, owner, effective date, review date, review cycle, canonical record, supersession, repository, product, correction, and revision fields.
- Full-width custom single-document template.
- Custom Foundation Document archive.
- Automatic table of contents generated from H2 and H3 headings.
- Citation, PDF, print, related-record, authority, correction, and revision-history components.
- Public catalog shortcode: `[sc_foundations_catalog]`.
- Public catalog REST route: `/wp-json/sustainable-catalyst/v1/library/foundations/catalog`.
- Responsive, accessible, reduced-motion, and print presentation.
- JSON Schema and controlled vocabulary records.

## Foundations page integration

Place this shortcode in the Foundations page where the governed catalog should appear:

```text
[sc_foundations_catalog]
```

Optional examples:

```text
[sc_foundations_catalog type="institutional-standard"]
[sc_foundations_catalog status="current-approved-record"]
[sc_foundations_catalog title="Institutional Foundations" limit="50"]
```

The shortcode queries the same `sc_foundation_doc` records indexed and preserved by Knowledge Library.

## Existing Foundation Documents

Existing records remain valid and retain their PDF, extraction, citation, related-record, preservation, and indexing data. After installation, open each record and populate the new authority fields. Until reviewed, records default to `Draft` in the new governance layer.

## Template behavior

The custom template is selected only for:

- Singular `sc_foundation_doc` records.
- The `sc_foundation_doc` archive.
- Pages containing `[sc_foundations_catalog]` for assets.

Other Library content types and public pages are unchanged.

## Recommended first records

- SC-FND-001 — Sustainable Catalyst Institutional Charter.
- SC-FND-002 — Principles and Public Commitments.
- SC-FND-003 — Knowledge and Research Model.
- SC-FND-004 — Platform Architecture and Product Taxonomy.
- SC-FND-005 — Evidence, Claims, and Methodology Standard.
- SC-FND-006 — Responsible AI and Human Review Standard.
- SC-FND-007 — Documentation Authority, Versioning, and Records Policy.
- SC-FND-008 — Open Knowledge, Licensing, and Attribution Policy.

## Installation

Use the supplied macOS installer from the same Downloads folder as the repository overlay ZIP. The installer:

1. Locates the local Knowledge Library Git repository.
2. Refuses to mix the release with uncommitted changes.
3. Creates a timestamped safety backup.
4. Copies the new module files.
5. Adds one `require_once` line to the Library bootstrap.
6. Runs static tests and PHP lint when available.
7. Builds a WordPress plugin ZIP.
8. Commits and pushes the release to `main`.

## Post-installation

1. Upload the generated plugin ZIP to WordPress and choose **Replace current with uploaded**.
2. Clear WordPress, page-builder, Cloudflare, and browser caches.
3. Open an existing Foundation Document and save its authority metadata.
4. Add `[sc_foundations_catalog]` to the Foundations page.
5. Confirm singular records use the new document template.
6. Confirm `/foundation-documents/` uses the new catalog archive.
7. Confirm the catalog REST route returns `sc-foundations-catalog/2.0`.
