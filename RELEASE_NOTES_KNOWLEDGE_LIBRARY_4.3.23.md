# Sustainable Catalyst Library v4.3.23 — Research Document Builder & DOCX/PDF Export

## Purpose

Complete the next step after Citation Studio: turn personally saved sources, source notes, citations, and research questions into structured research documents that can be saved, reopened, and downloaded as real DOCX or PDF files.

## New capability

- Adds `[sc_research_document_builder]`.
- Adds six research document types: Reading List, Annotated Bibliography, Literature Review Packet, Research Brief, Evidence Packet, and Research Notes.
- Allows signed-in users to select up to 100 of their own Citation Studio sources for a document.
- Adds private saved-document drafts with reopen and delete controls.
- Adds research question/purpose and working-notes fields.
- Allows private source notes and identifiers/URLs to be included or excluded from exports.
- Adds `Add to Document` to Citation Studio source records.

## Export formats

- Native `.docx` generated as OOXML on the WordPress server.
- Native `.pdf` generated on the WordPress server.
- No browser print placeholder is used.
- No external Render service is required for these personal exports.
- Export responses include SHA-256 integrity metadata.

## Ownership and privacy

- Public Research Access stays open to everyone.
- Research Document Builder requires the shared Sustainable Catalyst / Workspace account.
- Personal documents and source selections remain account-owned.
- Only sources owned by the signed-in user can be selected.
- Personal research is not silently promoted into the institutional editorial registry.

## Research integrity

The builder structures selected sources and user-supplied analysis. It does not invent missing annotations, claims, or synthesis. Every generated document carries an explicit research-integrity notice.

## Research Library page

The v4.3.23 page adds Research Document Builder immediately after Citation Studio and changes the compact research flow to:

`Find → Understand → Organize → Produce`

## Publications preservation

The v4.3.22.4 Publications 14-field stack is preserved without modification:

- all 14 major fields remain rendered simultaneously;
- all-fields stack marker remains `v4.3.22.4`;
- the canonical 170 Article Maps remain intact;
- no Publications page-body replacement is required.

## Validation

- 12 dedicated v4.3.23 Research Document Builder tests passed.
- 37 selected retained compatibility tests passed.
- Total selected regression gate: 49 tests passed.
- PHP syntax passed for the plugin tree.
- JavaScript syntax passed for Research Document Builder, Citation Studio, Publications, Field Spotlights, Open Course Finder, and Course Plan.
- One-page and four-page DOCX exports were rendered and visually inspected.
- One-page and four-page PDF exports were rendered and visually inspected.
- Multi-page exports showed no clipping, overlap, or broken pagination in the QA fixtures.
