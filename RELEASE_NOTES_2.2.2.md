# Sustainable Catalyst Library v2.2.2

## Bulk Import and Collection Repair

This release adds the operational layer needed to bring a large existing PDF collection into the PDF-to-Document Knowledge Library without creating duplicate records or requiring one-at-a-time setup.

## PDF inventory

**SC Library → Bulk Import & Repair → Import PDFs** scans Media Library PDFs in pages of 50. Each file is classified as:

- Represented by an existing document record
- Unlinked and available for import
- A possible duplicate by attachment ID or SHA-256 checksum

Checksums are cached against the source file modification time so unchanged files do not need to be hashed repeatedly.

## Batch record creation

Selected PDFs can:

- Create draft records only
- Create draft records and enter the conversion queue

The batch assigns one document family and lifecycle status. Existing or checksum-duplicate PDFs are skipped rather than creating another record.

## Persistent conversion queue

Conversion jobs are stored as private internal WordPress records. The queue processes one PDF at a time in the administrator's browser and reuses the resumable v2.2.1 extraction endpoints.

Queue controls include:

- Pause after the active document
- Resume
- Retry failed items
- Cancel queued work
- Recover stale processing locks after a browser closes
- Export item states and errors as CSV

Closing the queue page does not delete the job. Reopening it resumes the current document from the page checkpoint maintained by v2.2.1.

## Collection repair

The Collection Repair tab reports:

- Missing or invalid PDF attachments
- Missing document families
- Missing lifecycle or conversion status
- Missing checksums
- Missing title or listing summary
- Possible duplicate document records
- Records still requiring conversion

Safe repair normalizes compatible PDF metadata, assigns the default Foundations family, adds current lifecycle state, restores conversion state, calculates checksums, and derives a missing title from the attachment.

Bulk actions can also assign a family, change lifecycle status, queue incomplete conversions, or queue full reprocessing.

## Reports

- Every import or conversion job exports a CSV with item state, attempts, messages, attachment, and document IDs.
- The entire PDF Document collection exports a repair report CSV containing family, lifecycle, PDF state, conversion state, checksum, and issues.
