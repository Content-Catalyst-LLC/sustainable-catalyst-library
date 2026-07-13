# Sustainable Catalyst Library v2.1.0

## Foundation Document Pages

This release converts Foundation Documents into a dedicated page-like publishing workflow inside the Knowledge Library.

### Simple editor

- Foundation Docs remain the existing `sc_foundation_doc` content type.
- The default editor contains only title, optional introduction, PDF selector, revisions, and publishing controls.
- Selecting a Media Library PDF automatically creates the public embedded reader and Open PDF / Download PDF actions.
- A blank title is filled from the selected Media Library attachment.
- Legacy extraction, citation, version, and indexing controls remain available through an explicit Advanced Library tools link.

### Separation from blog content

- No WordPress post categories or tags.
- No Library topic, concept, series, or collection taxonomy boxes.
- No post archive, author archive, date archive, blog feed, related-post feed, or navigation-menu exposure.
- Foundation Docs are excluded from general WordPress search and unrelated front-end Library queries.
- Public pages remain directly available under `/foundations/{document-slug}/`.

### Foundations-only public interface

- New shortcode: `[sc_foundation_documents]`.
- The shortcode lists only published Foundation Documents.
- Search, pagination, title ordering, page cards, and direct document links are included.
- Individual document pages use a Knowledge Library-owned template with an automatic PDF embed and Foundations return link.

### Compatibility

- Existing `sc_foundation_doc` records are retained.
- Existing Media Library attachment relationships are read from compatible legacy meta keys.
- Existing PDF extraction and page-aware indexing infrastructure remains in the repository and can be reached from Advanced Library tools.
- Existing taxonomy relationships are not deleted from the database, but Foundation Docs no longer expose or use those taxonomies publicly.
