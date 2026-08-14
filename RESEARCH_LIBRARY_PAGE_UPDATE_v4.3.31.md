# Research Library Page Update — v4.3.31

Replace the WordPress page body for the canonical `/knowledge-libraries/` page with `RESEARCH_LIBRARY_PAGE_v4.3.31.html` after the v4.3.31 plugin is active.

## New section

The page now exposes the account-persistent Reading, Notebook & Annotation Workspace immediately after Research Projects & Source Bundles and before Open Course Finder:

```text
[sc_reading_notebook_workspace title="Reading, Notebook & Annotation Workspace"]
```

The section explains the boundary between the existing browser-local Research Notebook and the new account-persistent v4.3.31 reading notebooks. Existing v4.3.30 Research Projects, v4.3.29 Saved Research, v4.3.28 My Library, Research Access, Citation Studio, Document Builder, and Publications surfaces are retained.

## Post-deploy checks

1. Confirm `/knowledge-libraries/` renders the new Reading & Annotation section.
2. Sign in and create a private reading notebook.
3. Attach it to a Research Project or Source Bundle.
4. Save a note, a reusable excerpt, and a page-locator annotation.
5. Confirm the REST state is private and account-scoped at `/wp-json/sc-library/v1/reading-notebooks`.
6. Confirm identity health remains `ok` at `/wp-json/sc-library/v1/runtime/identity-health`.
