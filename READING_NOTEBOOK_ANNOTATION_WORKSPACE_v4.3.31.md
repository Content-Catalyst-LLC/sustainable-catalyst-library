# Sustainable Catalyst Library v4.3.31 — Reading, Notebook & Annotation Workspace

## Purpose

v4.3.31 turns reading activity into durable private research context. It introduces account-persistent reading notebooks that live beside the Library's existing Research Projects, Source Bundles, saved research, Citation Studio sources, documents, courses, and personal Library records.

The release does **not** replace the older browser-local Research Notebook. That legacy surface remains available for compatibility with existing local browser work. v4.3.31 adds a separate authenticated layer for research that must follow the same Sustainable Catalyst account across devices.

## Canonical record model

Reading notebooks use the private `sc_reading_notebook` WordPress record type and WordPress `post_author` as the owner boundary. Each notebook receives a stable UUID and URN:

```text
urn:sc:reading-notebook:{uuid}
```

Notebook notes and annotations are bounded post-meta collections owned by that notebook. They are never exposed as public WordPress records.

## Research Project and Source Bundle context

A notebook can be standalone or point to one canonical v4.3.30 Research Project. It can optionally point to a Source Bundle belonging to that same project. The module validates both the project owner and bundle membership before saving the context.

This is a reference relationship. v4.3.31 does not copy a project's source bundle into the notebook and does not create a second project truth source.

## Reading notes and reusable excerpts

Notes can be classified as:

- note
- reusable excerpt
- research question
- observation
- user summary
- method / procedure

Each note can carry a title, user-authored body, concise selected excerpt, optional Library source reference or external URL, tags, pin state, and explicit ordering position.

Reusable excerpts are intentionally bounded to 4,000 characters. They are user-selected research excerpts, not an automated full-text capture mechanism.

## Source annotations

Annotations can represent highlights, excerpts, comments, or bookmarks. They can point to an existing Library source or an external URL and support:

- PDF/document page locators
- section or heading locators
- audio/video timestamps
- paragraph/passage locators
- custom locators

Each annotation may include a concise selected passage, the user's annotation text, tags, pin state, and explicit ordering.

## Source integrity boundary

When a note or annotation points to a source supported by v4.3.30, the notebook resolves that source on read through the existing reference resolver. The notebook stores the stable reference, not a duplicate of the underlying source record.

The v4.3.31 contract explicitly states:

- account-persistent private notebooks
- same Sustainable Catalyst / Workspace account
- legacy browser-local notebook preserved
- source links are references
- underlying source records are not copied
- private binary files are not copied
- notes and excerpts are user-authored / user-selected
- no automatic AI-generated notes
- no automatic evidence promotion
- no automatic publication
- no automatic Workspace write

## REST API

Authenticated current-user routes:

```text
GET  /wp-json/sc-library/v1/reading-notebooks
POST /wp-json/sc-library/v1/reading-notebooks
GET/PATCH/DELETE /wp-json/sc-library/v1/reading-notebooks/{id}
GET  /wp-json/sc-library/v1/reading-notebooks/{id}/manifest
POST /wp-json/sc-library/v1/reading-notebooks/{id}/notes
PATCH/DELETE /wp-json/sc-library/v1/reading-notebooks/{id}/notes/{note_id}
POST /wp-json/sc-library/v1/reading-notebooks/{id}/annotations
PATCH/DELETE /wp-json/sc-library/v1/reading-notebooks/{id}/annotations/{annotation_id}
```

Ownership is rechecked inside record operations; authentication alone does not grant access to another user's notebook.

## Manifest

Each notebook can produce a private manifest with the notebook state, project context, notes, annotations, source resolution state, generation time, and a SHA-256 checksum. Private binary files remain excluded.

## Front-end shortcode

```text
[sc_reading_notebook_workspace title="Reading, Notebook & Annotation Workspace"]
```

The front-end supports notebook creation and settings, project/source-bundle attachment, note and excerpt creation/editing, annotation creation/editing, tags, pinning, ordering, and deletion.

## Forward compatibility

v4.3.31 deliberately stops before automatic evidence promotion or Workspace synchronization. Those boundaries allow the next evidence/claim and Workspace continuity builds to consume stable notebook records without retroactively changing the meaning of user-authored notes.
