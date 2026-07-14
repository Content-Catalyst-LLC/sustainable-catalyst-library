# Sustainable Catalyst Library v2.1.3

## Foundation Library Integration and Viewer Refinement

This release corrects the architectural split introduced by Foundation Document Pages.

### Foundations Library integration

The existing Foundations page can keep its established shortcode:

```text
[sc_library collection="foundations" mode="documentation" ...]
```

The plugin now intercepts that Foundations-specific request and renders published
`sc_foundation_doc` page records directly. It no longer falls through to the ordinary
Library record query that can resemble a blog-post search.

The existing alias is also supported:

```text
[sc_foundations_library mode="public"]
```

No Foundations page HTML replacement is required.

### Viewer refinement

- Removes the iframe-based PDF presentation.
- Uses a native `application/pdf` object embed with a direct fallback.
- Reuses the existing `cc-rl-v2` Sustainable Catalyst hero, buttons, sections, and typography.
- Removes the separate fixed-font design system, oversized title treatment, rounded cards, badges, and large custom button styling.
- Keeps Open PDF, Download PDF, and Back to Foundations actions.

### Library presentation

Foundation Documents now appear as a restrained document index:

- Foundation Document / PDF metadata
- Title and optional introduction
- Read document and Open PDF links
- Search and pagination
- No blog-post cards, categories, dates, authors, or post metadata
