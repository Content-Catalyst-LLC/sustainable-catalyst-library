# OCR Production Validation Checklist — v2.4.1

## Provider checks

- Confirm the selected provider reports Available.
- For local OCR, confirm Tesseract, the rasterizer, and requested languages are listed.
- For external OCR, confirm both endpoint and API key are configured.
- Confirm the external endpoint uses HTTPS.
- Process one page before starting a large job.

## Queue checks

- Start a two-page OCR job.
- Reload the queue page and verify the active lease eventually recovers or can be repaired.
- Pause and resume the queue.
- Cancel a test job and verify queued/processing page states become Cancelled.
- Retry a low-confidence or failed page.
- Use **Repair Queue State** on a deliberately interrupted job.
- Confirm page progress reaches the total for complete, failed, low-confidence, and cancelled terminal states.

## Source-integrity checks

- Queue a test page.
- Replace or modify the attached PDF.
- Confirm the old job cannot process against the changed file.
- Confirm OCR Review requires reconversion.
- Run PDF conversion again.
- Confirm the page inventory can be rebuilt from the current PDF.

## Review and publishing checks

- Correct OCR text and mark the page reviewed.
- Apply OCR to a Draft document.
- Confirm a backup is created.
- Apply OCR to a Published test document.
- Confirm explicit draft-return approval is required.
- Confirm the document becomes Draft.
- Restore the latest pre-OCR backup.
- Confirm content and publication status are restored.

## Export and cleanup checks

- Export document OCR CSV and job CSV.
- Open the CSV files and confirm values beginning with spreadsheet-formula characters are neutralized.
- Run temporary-file cleanup.
- Confirm public document pages still include the original PDF controls and OCR provenance notice where applicable.
