# Sustainable Catalyst Library v1.3.0

Library v1.3.0 adds the **Technical Translation Matrix** to the relationship-aware WordPress knowledge base and local-first Research Notebook.

## Release highlights

- Configurable translation matrices with editable rows and columns
- Templates for technical translation, equation-to-code, language comparison, source comparison, and cross-domain translation
- Cell-level validation and review states
- Cell-level source and provenance references
- Notebook, collection, Library-record, and outside-source integration
- JSON, CSV, and landscape print/PDF-ready matrix exports
- Standalone `[sc_library_translation_matrix]` shortcode
- Upgrade-safe migration from the v1.2 browser-local workspace

## Install

Upload `sustainable-catalyst-library-v1.3.0.zip` through WordPress, replace the existing plugin, activate it, and rebuild the Library index.

Recommended Library shortcode:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

Standalone Matrix Studio:

```text
[sc_library_translation_matrix]
```

## Data boundary

Canonical publications remain in WordPress. Personal notebook material and matrices remain in the visitor's browser in v1.3.0 unless explicitly exported.
