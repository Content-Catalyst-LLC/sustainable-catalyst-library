# OCR Reliability and Recovery — v2.4.1

## Changed PDF recovery

When a PDF attachment or its file contents change, the OCR workspace blocks further OCR work until the current PDF is converted again.

Recommended recovery:

1. Open the PDF Document record.
2. Run the standard PDF conversion process.
3. Return to **SC Library → OCR Review**.
4. Confirm that the reconversion warning has cleared.
5. Review the rebuilt page inventory.
6. Create a new OCR job only for pages that still require OCR.

The previous page-level OCR records are retained in a limited internal stale-source archive for diagnostic recovery. They are not automatically reused.

## Stalled queue recovery

Open:

```text
SC Library → OCR Review → OCR Queue
```

Select the affected job and choose **Repair Queue State**.

The repair operation:

- Returns expired processing leases to Queued
- Clears obsolete client and token data
- Synchronizes the page inventory
- Marks missing document/page references as Failed
- Recomputes Running, Complete, or Complete with attention

The patch does not run OCR as an unattended server worker. The queue page still requests one server-side page operation at a time.

## Published-document recovery

Applying reviewed OCR to an already published record requires confirmation. The document returns to Draft and a pre-OCR backup is created.

To reverse the operation:

```text
OCR Review → Restore Latest Pre-OCR Backup
```

The restore returns content, excerpt, publication status, raw text, page map, extraction status, review state, and public OCR warning to the latest saved pre-apply state.

Up to five backups are retained per document.

## Temporary files

Temporary OCR rasterization directories older than the configured retention period are removed daily.

Manual cleanup:

```text
SC Library → OCR Review → Providers
Clean Expired OCR Temporary Files
```

Default retention is 24 hours and can be changed through:

```php
add_filter( 'sc_library_ocr_temp_retention_seconds', fn() => 12 * HOUR_IN_SECONDS );
```
