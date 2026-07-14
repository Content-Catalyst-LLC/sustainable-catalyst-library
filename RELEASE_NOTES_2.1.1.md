# Sustainable Catalyst Library v2.1.1

## Foundation Document Editor and Routing Repair

This maintenance release repairs the two runtime failures found after the v2.1.0 deployment.

### Fixed

- Foundation Document single pages no longer receive an empty `post__in` query and return a false 404.
- `/foundations/{document-slug}/` is registered explicitly and rewrite rules are refreshed once for v2.1.1.
- The PDF selector is rendered directly below the document title instead of depending on a removable metabox.
- The Media Library script and stylesheet use the canonical plugin URL.
- Legacy Foundation Document metadata boxes remain hidden in the simple editor.
- Foundation Docs remain separate from blog categories, tags, feeds, archives, and general post listings.

### Publishing workflow

1. Open **SC Library → Foundation Docs → Add New**.
2. Enter the document title.
3. Click **Select PDF** directly below the title.
4. Choose a PDF from the Media Library.
5. Add an optional introduction.
6. Publish and open the generated `/foundations/{slug}/` page.
