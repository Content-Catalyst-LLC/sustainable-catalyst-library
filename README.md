# Sustainable Catalyst Library v1.18.1

Library v1.18.1 adds **Embedded Document Records and Full-Text PDF Indexing** to the complete v1.18.0 platform.

## Foundation Document system

- Native `sc_foundation_doc` WordPress record type
- WordPress Media Library PDF selection
- Bundled PDF.js inline viewer without an iframe
- Explicit Open PDF and Download PDF controls
- Browser-local page-aware text extraction
- Search snippets linked to exact PDF pages
- Research Librarian synchronization
- Metadata, version history, checksums, and related records
- BibTeX, RIS, CSL JSON, and plain-text citations
- Extraction status, retries, and failure diagnostics
- Mobile fallback presentation
- Migration of existing direct-download Foundation links

## Data boundaries

WordPress remains canonical. PDF binaries remain in the Media Library or at an explicitly recorded source URL. Extracted text is stored page by page in `sc_library_pdf_pages`; version manifests are stored in `sc_library_foundation_versions`. Browser extraction uses the bundled PDF.js library and does not upload the PDF to an external extraction provider.

## Public interfaces

```text
[sc_foundation_document id="123"]
```

```text
/wp-json/sustainable-catalyst/v1/library/foundation-documents
/wp-json/sustainable-catalyst/v1/library/foundation-documents/{id}
/wp-json/sustainable-catalyst/v1/library/foundation-documents/{id}/pages
/wp-json/sustainable-catalyst/v1/library/foundation-documents/{id}/citation
```

The versioned developer namespace also exposes public Foundation Document routes under `/wp-json/sustainable-catalyst-library/v1`.

## Portable data

Portable export schema:

```text
sc-library-portable-export/1.9
```

New entities:

```text
foundation_documents
pdf_pages
foundation_versions
```

PDF binaries are referenced rather than embedded.

## Installation

Upload `sustainable-catalyst-library-v1.18.1.zip` through WordPress and choose **Replace current with uploaded**. Existing Library records and indexes are preserved. Create or migrate Foundation Document records, extract each PDF, and then verify page-aware search.

See `EMBEDDED_DOCUMENT_RECORDS_SETUP_v1.18.1.md` and `RELEASE_NOTES_1.18.1.md`.
