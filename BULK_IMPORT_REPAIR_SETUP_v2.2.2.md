# Bulk Import and Collection Repair — v2.2.2 Setup

## Install

Run the included installer, upload the generated plugin ZIP, and choose **Replace current with uploaded**.

## Import existing PDFs

1. Open **SC Library → Bulk Import & Repair**.
2. Use the Import PDFs tab.
3. Filter to **Unlinked**.
4. Select PDFs on the current inventory page.
5. Choose a document family and lifecycle status.
6. Choose **Create draft records only** or **Create records and queue conversion**.
7. Apply the batch.

The import tool never creates a second record for a represented attachment or matching checksum.

## Run a conversion queue

Keep the Conversion Queue tab open while browser conversion is active. The page processes one document at a time. Pause, resume, retry, and cancel controls update the persistent job record.

The job can be reopened later. v2.2.1 page checkpoints allow an interrupted document to resume rather than restart.

## Repair the collection

Use Collection Repair to find missing PDFs, missing families, incomplete metadata, duplicate records, and documents needing conversion. **Repair safe metadata** performs only non-destructive normalization. It does not delete attachments, documents, or extracted content.

## Export reports

- Use **Export CSV** on a queue for import and conversion results.
- Use **Export Collection Report CSV** for a complete repair inventory.

## Scale note

The inventory is paginated at 50 PDFs per page. Conversion remains browser-driven to avoid turning a WordPress request into an unbounded background process. Large batches can be divided into multiple jobs for easier review.
