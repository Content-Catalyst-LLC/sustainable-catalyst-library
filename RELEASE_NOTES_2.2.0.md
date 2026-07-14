# Sustainable Catalyst Library v2.2.0

## PDF-to-Document Knowledge Library

This release replaces the isolated Foundation Page model with a first-class PDF document record that behaves like a Knowledge Library article without becoming an ordinary blog post.

## One permanent record

The existing custom post type is retained:

```text
sc_foundation_doc
```

Each record now contains:

- Editable title and readable document content
- Search summary stored as the excerpt
- Original authoritative PDF attachment
- Hierarchical Document Family
- Version and publication date
- Current, superseded, archived, or historical lifecycle state
- Extraction method and review status
- Page count and page-reference map
- Raw extracted text and SHA-256 checksum
- WordPress revisions, REST access, and a stable public URL

Existing Foundation Documents are migrated in place and assigned to the Foundations family by default.

## PDF-to-document conversion

The editor includes **Create Document from PDF**.

The conversion pipeline tries:

1. A filterable external extractor when one is available.
2. Local `pdftotext` when the host provides it.
3. Bundled PDF.js browser extraction when server extraction is unavailable.

Browser extraction is uploaded in ten-page chunks so larger documents are not sent in one oversized request.

The generated HTML is editable in the normal WordPress editor. Page markers are retained so readers and future search tools can return to the corresponding PDF page.

## Image-only and protected PDFs

The release does not pretend that image-only PDFs contain reliable text. Files with very little extractable text are marked **Needs OCR**. Password-protected PDFs are marked separately. The original PDF remains attached and available in either state.

An external OCR or extraction service can integrate through the `sc_library_pdf_to_document_extraction` filter.

## Document families

The new hierarchical taxonomy is:

```text
sc_document_family
```

Foundations is created as the default family. Additional families can include Research Reports, Methodology, Policies and Governance, Technical Documentation, Platform Documentation, Release Documentation, and Historical Archive.

## Public experience

Each record provides:

- Read Document
- View Original PDF
- Open PDF
- Download PDF
- Back to Family

The readable article is presented first. The original PDF expands to almost the full viewport on the same page. Styling remains Spartan and inherits the existing Sustainable Catalyst Research Library system.

## Library and import tools

- `[sc_pdf_document_library]`
- `[sc_pdf_document_library family="foundations"]`
- `[sc_pdf_library family="methodology"]`
- Existing Foundations shortcodes remain compatible.
- Media Library PDF rows receive **Create Knowledge Document**.
- **SC Library → Import PDFs** creates draft records in batches.
- **SC Library → PDF Document Health** reports attachment, conversion, route, and OCR status.
