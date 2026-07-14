# OCR Processing Setup — v2.4.0

## Install

Run the included installer, upload the generated plugin ZIP, and choose **Replace current with uploaded**.

## First validation

1. Open **SC Library → OCR Review**.
2. Select a document already marked Needs OCR.
3. Open its OCR Review workspace.
4. Confirm the page inventory identifies low-text pages.
5. Open Providers and confirm an available provider.
6. Queue one test page.
7. Review confidence, language, provider, and recognized text.
8. Correct the page and mark it reviewed.
9. Apply reviewed OCR to the readable document.
10. Review the complete document before publishing.

## Local provider requirements

The WordPress server needs:

```text
Tesseract OCR
Poppler pdftoppm or pdftocairo
PHP proc_open enabled
OCR language data such as eng
```

Typical Linux package names:

```text
tesseract-ocr
tesseract-ocr-eng
poppler-utils
```

Managed WordPress hosting may not permit system packages or process execution. In that environment, use an external or custom provider.

## Configuration filters

```php
add_filter( 'sc_library_ocr_min_source_characters', fn() => 100 );
add_filter( 'sc_library_ocr_raster_dpi', fn() => 250 );
add_filter( 'sc_library_ocr_max_page_image_bytes', fn() => 40 * 1024 * 1024 );
add_filter( 'sc_library_ocr_page_time_limit', fn() => 300 );
add_filter( 'sc_library_ocr_external_timeout', fn() => 180 );
```

OCR is a derivative reading layer. The original PDF remains attached, downloadable, and authoritative.
