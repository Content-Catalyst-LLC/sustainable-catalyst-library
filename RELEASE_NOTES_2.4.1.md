# Sustainable Catalyst Library v2.4.1

## OCR Reliability and Review Recovery Patch

This patch hardens the v2.4.0 scanned-document workflow without changing the PDF Document record type, document families, public repository routes, or original-PDF authority boundary.

## Source integrity

Each OCR document now stores the SHA-256 checksum of the PDF used for page analysis and queue creation.

When the attachment changes:

- Prior page-level OCR records are archived
- Active OCR use of stale source text is blocked
- Raw extraction and page-map records are cleared
- The document is marked as requiring PDF reconversion
- New OCR jobs cannot start until conversion records match the current PDF
- Every queued page carries the expected source checksum and verifies it again before processing

This prevents OCR results from one PDF version from being silently applied to another.

## Queue leases and recovery

OCR queue processing now uses:

- Browser-specific client identifiers
- Opaque per-page lease tokens
- Five-minute processing leases
- Retry-safe next-item acquisition
- Idempotent process responses after a completed request is retried
- Correct negative-index validation
- Token and client ownership checks
- Cancellation checks after a provider returns
- Synchronized page states when jobs are retried or cancelled

The queue no longer exposes lease tokens in general job-status payloads.

A new **Repair Queue State** control:

- Requeues expired processing leases
- Clears stale lock data
- Marks invalid job references for attention
- Synchronizes repaired page records
- Recalculates the job state

## Provider reliability

Local Tesseract discovery now supports:

```text
SC_LIBRARY_TESSERACT_BINARY
SC_LIBRARY_PDF_RASTERIZER_BINARY
Executable locations in the server PATH
Existing standard paths
```

Local provider diagnostics and installed-language discovery are cached for ten minutes.

The external endpoint now requires:

- A valid URL
- HTTPS by default
- `SC_LIBRARY_OCR_API_KEY`
- HMAC-SHA256 request signatures
- Bounded redirects, response size, timeout, and page-text size

A development filter can explicitly permit HTTP, but production endpoints should remain HTTPS.

## Review and publishing safeguards

Before applying OCR, v2.4.1:

- Verifies the current PDF checksum
- Blocks application while pages remain queued or processing
- Creates a pre-OCR document backup
- Requires explicit confirmation before changing a published document
- Returns a published document to Draft
- Preserves a **Restore Latest Pre-OCR Backup** action
- Keeps the original PDF attached and authoritative

## Administration and maintenance

The OCR workspace now includes:

- Accurate query-level status filtering and pagination
- Cached workspace totals
- Formula-safe CSV exports
- Automatic daily cleanup of expired OCR temporary directories
- Manual temporary-file cleanup
- Active-job-safe history pruning
- External endpoint and API-key diagnostics
- Queue lease-timeout diagnostics
