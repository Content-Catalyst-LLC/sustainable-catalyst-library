# PDF-to-Document Knowledge Library — v2.2.0 Setup

## Install

Run the included installer. Upload the generated WordPress plugin ZIP and choose **Replace current with uploaded**.

## Create one document

1. Open **SC Library → PDF Documents → Add PDF Document**.
2. Select the authoritative PDF using the existing PDF selector.
3. Choose a Document Family.
4. Click **Create Document from PDF**.
5. Review and edit the generated readable document in the editor below.
6. Add version, publication date, and lifecycle status when appropriate.
7. Confirm that the generated document has been reviewed against the original PDF.
8. Publish.

The public record provides Read Document, View Original PDF, and Download PDF. No second WordPress page and no individual shortcode are required.

## Create directly from the Media Library

In **Media → Library**, switch to list view and locate a PDF. Choose **Create Knowledge Document**. WordPress creates the draft record, attaches the PDF, opens the editor, and begins conversion.

## Import many PDFs

Open **SC Library → Import PDFs**. Select Media Library PDFs, assign a family, and create draft records. Open each draft to convert and review it. Batch extraction is intentionally not automatic in v2.2.0 so large imports do not silently consume server or browser resources.

## Document family pages

Each family receives a public archive:

```text
/documents/family/foundations/
/documents/family/methodology/
```

Embed a family elsewhere with:

```text
[sc_pdf_document_library family="foundations"]
```

## Existing Foundations page

Existing Foundations-specific Library shortcodes remain compatible and are routed to the Foundations family. Existing Foundation Document records remain stored under `sc_foundation_doc` and are migrated in place.

## Extraction states

- Not converted
- Converting
- Ready for review
- Reviewed
- Published
- Needs OCR
- Password protected
- Conversion failed

The PDF remains the authoritative source in every state.
