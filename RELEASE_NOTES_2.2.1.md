# Sustainable Catalyst Library v2.2.1

## PDF Conversion and Publishing Reliability

This maintenance release stabilizes the v2.2.0 PDF-to-Document Knowledge Library without replacing its record model, URLs, document families, or public presentation.

## Resumable conversion

Browser extraction now stores small page batches under a persistent conversion session. If the browser closes, WordPress times out, or the network connection fails, the document editor can resume after the last successfully stored page instead of starting over.

The editor also provides **Cancel Saved Conversion** when a partial session should be discarded.

## Large-document safeguards

- Dynamic batches default to five pages or approximately 240,000 extracted characters.
- Failed AJAX requests retry with exponential backoff.
- PDF.js falls back to worker-free compatibility mode when browser worker loading fails.
- The default conversion ceiling is 5,000 pages and 250 MB, with WordPress filters available for deployment-specific limits.
- Server-side `pdftotext` is skipped for files above 50 MB by default so large documents can use resumable browser processing.

## Duplicate protection

The release checks both Media Library attachment IDs and SHA-256 file checksums. A PDF already represented by another record cannot silently create or publish a duplicate Knowledge Document. The Media Library points editors to the existing record.

## Publishing gate

A PDF Document cannot publish until it has:

1. A valid PDF attachment
2. Completed or accepted readable document content
3. A supported conversion state
4. Explicit review confirmation against the original PDF
5. No duplicate attachment or checksum conflict

Failed validation keeps the record as a draft and explains what must be corrected.

## Better readable documents

Browser extraction now records line position, font size, and bold state. The server uses those signals to improve heading reconstruction. Repeated headers and footers found across most pages are removed from the generated article. Hyphenated line breaks and paragraph joining are also improved.

## Persistent logs and migration audit

Each record retains its latest 50 conversion events. The PDF Document Health screen displays recent conversion history and a v2.2.1 migration audit covering families, extraction states, checksums, and possible duplicates.
