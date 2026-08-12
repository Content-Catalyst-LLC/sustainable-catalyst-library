# Research Document Builder — v4.3.23

## Purpose

Research Document Builder turns a signed-in user's private Citation Studio source library into portable research outputs without changing the public Research Access model or publishing private research into Sustainable Catalyst's editorial registry.

The workflow is deliberately source-first:

`Research Access → My Sources → Citation Studio → Research Document Builder → DOCX / PDF → Workspace`

## Document types

The builder supports six output structures:

1. Reading List
2. Annotated Bibliography
3. Literature Review Packet
4. Research Brief
5. Evidence Packet
6. Research Notes

Each document can contain a title, document type, citation style, research question or purpose, user-supplied working notes, selected private sources, optional private source notes, and optional identifiers/URLs.

## Personal ownership boundary

- Public research discovery remains available without an account.
- Research Document Builder requires the user's Sustainable Catalyst / Workspace account.
- Source selection is restricted to Citation Studio sources owned by the current user.
- Saved document drafts are stored in user metadata and are private to the account.
- The builder does not promote personal sources into Sustainable Catalyst's institutional editorial source registry.
- Missing annotations or analysis are not generated or inferred.

## Citation Studio handoff

Citation Studio source records now expose `Add to Document`. The action selects the source in Research Document Builder and moves the user into the builder without copying or duplicating the source record.

## Server-side exports

### DOCX

DOCX is produced as a native OOXML package containing the standard Word package relationships, content-types manifest, document body, and styles. Generation uses `ZipArchive` when available and a `PharData` ZIP fallback when necessary.

MIME type:

`application/vnd.openxmlformats-officedocument.wordprocessingml.document`

### PDF

PDF is generated directly on the WordPress server as a multi-page PDF 1.4 document with bounded page wrapping, institutional text hierarchy, source sections, identifiers, and page footers. No browser print dialog or external rendering service is required.

MIME type:

`application/pdf`

Both export endpoints return SHA-256 integrity metadata.

## Research integrity boundary

Every generated output carries the statement:

> This document structures user-selected research materials and user-supplied notes. It does not substitute generated claims for missing analysis.

This keeps document production consistent with Sustainable Catalyst's source, provenance, and human-judgment model.

## Compatibility

v4.3.23 preserves the v4.3.22.4 Publications 14-field stack. The Publications page remains an all-fields-at-once presentation and does not require a page-body replacement for this release.
