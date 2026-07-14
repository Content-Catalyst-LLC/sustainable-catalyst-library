# OCR Review Workflow — v2.4.0

## Page states

```text
Text available
Needs OCR
Queued
Processing
OCR complete
Low confidence
Reviewed
Failed
Provider unavailable
Cancelled
```

## Recommended workflow

1. Analyze the document.
2. Queue only pages that need OCR.
3. Process the queue.
4. Review low-confidence pages first.
5. Compare each page with the original PDF.
6. Correct recognition errors.
7. Mark the page reviewed.
8. Re-run pages when another language or provider is needed.
9. Apply reviewed OCR to the readable document.
10. Review and publish the complete rebuilt document.

The apply form includes an explicit option to use completed but unreviewed OCR. Those pages remain identified in the public provenance warning and should be verified later.
