# Sustainable Catalyst Library v2.3.0

## Document Families and Public Repository

This release turns the PDF-to-Document system into a complete public repository while preserving the established document records, conversion pipeline, recovery sessions, and bulk import tools.

## Generated public repository

The plugin now serves:

```text
/documents/
```

No manually created WordPress page is required. The repository displays published PDF Document records as compact institutional rows rather than oversized cards.

## Public document families

Each `sc_document_family` term receives an editorial landing page:

```text
/documents/family/foundations/
/documents/family/research-reports/
/documents/family/methodology/
```

The standard WordPress term Description becomes the public family introduction. Additional family controls provide a short kicker, featured-family status, and repository order.

The release seeds these recommended families when they do not already exist:

- Foundations
- Research Reports
- Methodology
- Policies and Governance
- Platform Documentation
- Technical Documentation
- Release Documentation
- Historical Archive

Existing families and assignments are not deleted or replaced.

## Repository search and filters

The repository searches titles, summaries, and the readable document content generated from PDFs. Public filters include:

- Document family
- Document type
- Lifecycle status
- Publication year
- Version
- Sort order

Filtering uses normal URLs and forms, so the interface remains accessible and works without client-side JavaScript.

## Document types

The release adds the hierarchical `sc_document_type` taxonomy with initial types for foundation documents, research reports, methodology documents, policies, platform documentation, technical documentation, release documentation, archives, and general documents.

Existing records receive a conservative initial type based on their assigned family. Editors can change the type from the PDF Document editor.

## Featured and pinned records

Each document can be featured and assigned a repository order. Featured documents appear above the standard results when a repository or family page is not actively filtered.

Families also support featured status and explicit ordering in the main family index.

## Lifecycle presentation

Current, superseded, archived, and historical records remain publicly available but are clearly labeled and grouped. The original PDF remains accessible through Open PDF and Download PDF controls.

## Compatibility

These public interfaces use the new repository renderer:

```text
[sc_pdf_document_repository]
[sc_document_repository]
[sc_pdf_document_library]
[sc_pdf_document_library family="foundations"]
[sc_pdf_library family="methodology"]
[sc_foundation_documents]
```

Existing Foundations-specific and `sc_library` documentation shortcodes continue to work.

## Administration

**SC Library → Public Repository** provides:

- Repository route status
- Direct public repository link
- Family and type counts
- Family public links
- Shortcode references
- Route repair
- Recommended family and type seeding
