# Bulk Conversion Queue — v2.2.2

Each queue is stored as the private internal post type:

```text
sc_pdf_bulk_job
```

A queue item contains the Media Library attachment, PDF Document record, current state, attempt count, latest message, timestamps, and a temporary processing lock.

Supported item states:

```text
queued
processing
complete
created
failed
needs_ocr
skipped_existing
skipped_duplicate
cancelled
```

Supported job states:

```text
created
running
paused
complete
complete_with_errors
cancelled
```

The queue uses the v2.2.1 conversion session for page checkpoints. The job record manages which document is processed next; the conversion session manages which PDF page should resume next.
