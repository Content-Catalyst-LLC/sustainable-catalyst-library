# Interrupted PDF Conversion Recovery — v2.2.1

A conversion session stores:

- Session identifier
- PDF attachment and checksum
- Last successfully stored page
- Total page count
- Start and update timestamps
- A temporary page-data buffer
- Persistent log entries

Reopening the document editor reads that state and offers **Resume Document Conversion**. Finalization checks for missing pages and refuses to build an incomplete article. If a page is missing, the editor resumes from the first gap.

Use **Cancel Saved Conversion** to remove the temporary buffer and return the record to Not Converted. The original PDF attachment and previously generated article content are not deleted.
