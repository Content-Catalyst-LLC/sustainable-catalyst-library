# Foundation Library Integration — v2.1.3

## Install

Run the installer, upload the generated WordPress ZIP, and choose **Replace current with uploaded**.

## Foundations page

Do not replace the Foundations page HTML. The existing shortcode remains valid:

```text
[sc_library collection="foundations" mode="documentation" title="Foundations Documentation Library" intro="Search current institutional, brand, product, methodology, policy, repository, release, PDF snapshot, and historical documentation." show_header="false" show_featured="true" include_archived="false" per_page="12"]
```

v2.1.3 automatically routes that Foundations-specific shortcode to page-based Foundation Documents.

## Validate

1. Publish a Foundation Document with a PDF.
2. Open the Foundations page.
3. Confirm the document appears in the Foundations Documentation Library.
4. Confirm ordinary blog posts do not appear in that listing.
5. Open the document page.
6. Confirm it uses the same simple Sustainable Catalyst / Research Library style.
7. Confirm the PDF uses the native browser embed rather than an iframe.
8. Test Open PDF, Download PDF, and Back to Foundations.
