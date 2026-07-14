# Sustainable Catalyst Library v2.4.0

## OCR and Scanned Document Processing

This release adds a controlled OCR layer for scanned, image-only, and low-text PDF pages without changing the PDF Document record model or treating OCR output as authoritative.

## Page-level scan detection

The plugin uses the page map created by PDF conversion to measure extracted text on each page. Pages below the configurable text threshold are marked **Needs OCR** while pages with usable embedded text remain **Text available**.

Refreshing the inventory preserves existing OCR results, corrections, confidence records, warnings, and reviewer decisions.

## OCR Review workspace

Open:

```text
SC Library → OCR Review
```

The workspace includes:

- Document-level OCR status and totals
- Page-level source-text measurements
- Side-by-side original PDF and editable page text
- Confidence, language, provider, attempts, warnings, and messages
- Reviewer identity and review timestamps
- Selected-page reprocessing
- Document and job CSV exports

## Persistent OCR queue

OCR pages use private internal WordPress job records. The browser requests one page at a time while the server runs the selected provider.

Queue controls include pause, resume, retry, cancel, stale-lock recovery, persistent attempts, and CSV export. Closing the queue page does not delete the job.

## Provider architecture

### Local Tesseract

The free local provider is available when the WordPress server provides:

```text
tesseract
pdftoppm or pdftocairo
PHP proc_open
```

Each page is rasterized to a temporary PNG and processed through Tesseract TSV output. Word-level confidence is aggregated into a page score. Temporary files are removed after processing.

The plugin does not bundle Tesseract, Poppler, OCR language models, or system binaries.

### External endpoint

Define:

```php
define( 'SC_LIBRARY_OCR_ENDPOINT', 'https://example.com/ocr' );
define( 'SC_LIBRARY_OCR_API_KEY', 'replace-with-a-secret' );
```

The endpoint receives a signed JSON request containing the PDF URL, document ID, page number, checksum, requested language, and timestamp.

### Custom provider

WordPress integrations can register providers through:

```text
sc_library_ocr_providers
sc_library_ocr_process_page
```

## Confidence and language

Each page stores confidence from 0 to 100, the requested language, a detected language or script hint, provider identity, warnings, and attempt count. The default low-confidence threshold is 75%.

## Applying reviewed OCR

Applying reviewed OCR:

- Rebuilds the page-aware readable document
- Updates raw text and page maps
- Returns the document to **Ready for review**
- Clears final document review confirmation
- Preserves the original PDF attachment
- Adds a public OCR provenance notice
- Preserves warnings for unreviewed or low-confidence pages

The complete rebuilt document must be reviewed before publication.
