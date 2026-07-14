# Sustainable Catalyst Library v2.1.2

## Foundation Document Production Hardening

This release stabilizes the Foundation Document workflow introduced in v2.1.0 and repaired in v2.1.1.

### Publishing safeguards

- Foundation Documents cannot be published, scheduled, or made private without a valid Media Library PDF.
- Invalid or missing PDFs keep the document in draft status and display a clear editor notice.
- WordPress duplicate-slug adjustments are surfaced before the URL is shared.
- The Foundation Document editor is standardized on the reliable page-style editor so the PDF selector remains visible regardless of site-wide block-editor settings.

### Foundation Docs Health

A new **SC Library → Foundation Docs Health** screen provides:

- Total, ready, and attention-needed document counts
- PDF attachment validation
- Rewrite-rule and route-version status
- Media Library selector availability
- One-click **Repair Foundation Routes**
- One-click **Normalize PDF Metadata**
- Direct edit links for documents that need a PDF

### Public document hardening

- Accessible embedded PDF iframe with a descriptive title
- Always-visible fallback guidance when a browser blocks inline PDF viewing
- Open PDF and Download PDF controls
- Filename and local file-size display when available
- Better mobile viewer heights and full-width controls
- Reduced-motion support

### Listing hardening

- Reliable search form action
- Search reset link
- Stable pagination URLs that preserve the active search
- Clear PDF-ready and PDF-unavailable states
