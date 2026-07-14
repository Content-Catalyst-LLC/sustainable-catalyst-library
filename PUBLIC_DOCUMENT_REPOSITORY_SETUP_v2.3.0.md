# Public Document Repository Setup — v2.3.0

## Install

Run the included installer, upload the generated plugin ZIP, and choose **Replace current with uploaded**.

## First validation

1. Open **SC Library → Public Repository**.
2. Confirm the `/documents/` route reports Ready.
3. Open the public repository link.
4. Test repository search and each available filter.
5. Open a family and confirm its description, featured documents, lifecycle groupings, and related-family navigation.
6. Open a document and test Read Document, View Original PDF, Open PDF, and Download PDF.

## Edit a family landing page

Open **SC Library → Document Families** and edit a family.

- Name becomes the family title.
- Description becomes the public editorial introduction.
- Public kicker appears above the family title.
- Repository order controls family placement.
- Featured family places the family near the top of the repository index.

## Feature a document

Edit a PDF Document and expand **Public repository settings**.

- Choose a document type.
- Enable the feature-and-pin checkbox.
- Enter a repository order when explicit ordering is useful.

## Shortcodes

Complete repository:

```text
[sc_pdf_document_repository]
```

One family:

```text
[sc_pdf_document_library family="foundations"]
```

One document type:

```text
[sc_pdf_document_repository type="research-report"]
```

Minimal embedded index:

```text
[sc_pdf_document_repository show_header="false" families="false" featured="false"]
```

## Route repair

Use **SC Library → Public Repository → Repair Repository Routes** after changing permalink settings or when `/documents/` returns a 404.

## Caching

After installation or family edits, clear WordPress page caches and Cloudflare cache so the generated repository and family pages update immediately.
