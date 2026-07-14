# PDF-to-Document Architecture — v2.2.0

```text
sc_foundation_doc
├── post_title             Editable document title
├── post_content           Generated and editable readable document
├── post_excerpt           Search and listing summary
├── revisions              WordPress revision history
├── PDF attachment         Authoritative Media Library source
├── sc_document_family     Hierarchical non-blog family taxonomy
├── extraction metadata    Method, status, date, page map, raw text
├── publication metadata   Version, publication date, lifecycle
└── public URL             /documents/{slug}/
```

## Storage

The record remains in WordPress custom-post-type storage. This provides dependable editing, revisions, permissions, REST access, and export without classifying the record as a blog post.

The original PDF remains a Media Library attachment under WordPress uploads. The record stores the attachment ID in compatible post-meta keys so existing Foundation Document records continue to work.

## Conversion

Text-based PDFs are converted into page-marked HTML. The extraction result is stored in `post_content`, while raw text and page offsets remain in post meta for search, citation, and future page-aware retrieval.

PDF.js conversion occurs locally in the authenticated administrator's browser. Extracted pages are transmitted to WordPress in small chunks and assembled server-side. No PDF or extracted text is sent to a third-party service by this release.

## OCR boundary

v2.2.0 detects image-only PDFs but does not bundle an OCR engine. The record is marked Needs OCR, preserving the original PDF and preventing low-confidence invented content. A later OCR service can use the documented extraction filter.
