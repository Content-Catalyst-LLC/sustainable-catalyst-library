# Library v1.18.1 Embedded Document Records Setup

## Install

1. Upload `sustainable-catalyst-library-v1.18.1.zip` through WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm version **1.18.1**.
4. Clear WordPress, page-builder, CDN, and browser caches.

## Create a Foundation Document

1. Open **SC Library → Foundation Documents → Add New**.
2. Add the public title, summary, categories, concepts, and collection terms.
3. Select a PDF from the WordPress Media Library.
4. Enter the version, publication date, author or institution, publisher, DOI, language, and related record IDs.
5. Keep the inline viewer and download controls enabled when appropriate.
6. Publish or update the record.
7. Select **Extract and index full PDF text**. Keep the editor tab open until the progress bar completes.

The extraction runs in the browser with the bundled PDF.js library. Text is submitted to WordPress in small page batches and stored in `wp_sc_library_pdf_pages`.

## Verify indexing

Search the public Library for a phrase found only inside the PDF. The result should show one or more **PDF page matches** with links that open the document at the matching page. Research Librarian recommendations should include the same page-aware evidence.

## Migrate existing Foundation links

1. Open **SC Library → PDF Migration**.
2. Review discovered Foundation pages containing direct `.pdf` links or existing documentation PDF metadata.
3. Select the records to migrate.
4. Create the new Foundation Document records.
5. Import URL-only PDFs into the Media Library when extraction is required.
6. Extract and verify each migrated document.

The migration does not delete or rewrite the source page. It creates a related Foundation Document record and records the original source ID.

## Citations

Each public document offers plain-text, BibTeX, RIS, and CSL JSON exports. Review metadata before relying on automated citations.

## Diagnostics

The document editor reports extraction status, page count, character count, extraction date, and the most recent error. Retry extraction after replacing an unreadable PDF, correcting permissions, or importing a remote PDF into the Media Library.

## Mobile behavior

The inline canvas reader is available on capable mobile browsers. A compact fallback always preserves explicit Open PDF and Download PDF controls.

## Privacy and rights

Only upload or link PDFs you are authorized to publish. Full extracted text becomes searchable wherever the Foundation Document itself is public. Private and draft records are not exposed through public endpoints.
