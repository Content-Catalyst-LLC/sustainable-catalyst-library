# Foundation Document Production Hardening — v2.1.2

## Install

Run the included installer, then upload the generated WordPress plugin ZIP and choose **Replace current with uploaded**.

## Validate in WordPress

1. Open **SC Library → Foundation Docs → Add New**.
2. Confirm the **Foundation PDF** selector appears directly below the title.
3. Attempt to publish without a PDF. The document should remain a draft with a clear error.
4. Select a PDF and publish.
5. Open the generated `/foundations/{slug}/` page.
6. Test **Open PDF**, **Download PDF**, and the embedded viewer on desktop and mobile.
7. Open **SC Library → Foundation Docs Health**.
8. Confirm the route, Media Library selector, and document attachment checks show the expected status.

## Route recovery

Use **Repair Foundation Routes** on the health screen. A manual visit to Settings → Permalinks should no longer be necessary.

## Existing documents

Documents with missing, deleted, non-PDF, or URL-less attachments are marked **Needs PDF** in the admin list and appear on the health screen for repair.
