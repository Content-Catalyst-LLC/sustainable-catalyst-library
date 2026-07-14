# PDF Conversion and Publishing Reliability — v2.2.1

## Install

Run the included installer, upload the generated WordPress plugin ZIP, and choose **Replace current with uploaded**.

## Validate one normal PDF

1. Open **SC Library → PDF Documents**.
2. Edit a draft or create a document from a Media Library PDF.
3. Click **Create Document from PDF**.
4. Confirm progress advances in saved batches.
5. Review the generated title, summary, headings, paragraphs, and page markers.
6. Check the review confirmation.
7. Publish.
8. Test Read Document, View Original PDF, and Download PDF.

## Validate recovery

Start a conversion on a multi-page PDF, close the editor after several pages have been stored, and reopen the record. The panel should report the last saved page and the primary conversion button should resume from the next missing page.

## Validate duplicate protection

Attempt to create a second document from a PDF already assigned to another record. The conversion and publication paths should identify the existing record rather than create a silent duplicate.

## Health and logs

Open **SC Library → PDF Document Health**. The bottom of the screen now contains the v2.2.1 migration audit and recent conversion events.

## Deployment filters

```php
add_filter( 'sc_library_pdf_conversion_max_bytes', fn() => 300 * 1024 * 1024 );
add_filter( 'sc_library_pdf_conversion_max_pages', fn() => 7000 );
add_filter( 'sc_library_pdf_conversion_chunk_pages', fn() => 4 );
add_filter( 'sc_library_pdf_conversion_chunk_characters', fn() => 200000 );
add_filter( 'sc_library_pdf_conversion_request_retries', fn() => 4 );
```
