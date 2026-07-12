# Sustainable Catalyst Library v1.2.0

A native WordPress knowledge base with structured publication indexing, typed knowledge relationships, and a local-first Research Notebook for saved records, personal notes, external links, physical books, reports, datasets, videos, and portable research collections.

## What v1.2.0 adds

- A browser-local **Research Notebook** integrated into Library result and record panels.
- Working **Save to Notebook** and **Write note** actions.
- Named research collections with a permanent Research Inbox.
- Reusable note types: research note, question, summary, quotation, claim, counterargument, observation, book note, video timestamp, and custom section.
- External source records for websites, journal articles, books, book chapters, reports, datasets, videos, podcasts, interviews, archives, and custom material.
- Physical-book fields for ISBN, edition, chapter, pages, and shelf or archive location.
- Source duplicate checks using URL, DOI, ISBN, title, and creator.
- APA, MLA, Chicago, Harvard, plain-text, BibTeX, RIS, and CSL JSON citation output.
- Versioned JSON workspace import and export using schema `sc-library-workspace/1.0`.
- Local reset controls and a visible storage/privacy boundary.
- A standalone `[sc_library_notebook]` shortcode.
- Public source-type, citation-format, and source-template REST endpoints.
- Upgrade-safe compatibility with v1.1.0 relationships, series, concepts, and record panels.

## Recommended Library shortcode

Use a dedicated WordPress Shortcode block:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

The Research Notebook launcher appears beneath the compact Library controls.

## Standalone Notebook shortcode

```text
[sc_library_notebook]
```

This renders the full notebook workspace without the Library search interface.

## Storage and privacy

v1.2.0 is local-first. Personal collections, notes, saved records, and source records are stored in the visitor's browser with `localStorage`. They are not written into WordPress and are not exposed through public REST endpoints. Users should export the JSON workspace before clearing browser data or moving to another device.

## After installation

1. Open **SC Library** and confirm the indexed post types.
2. Keep **Research Notebook** enabled.
3. Save settings and run **Rebuild Library Index**.
4. Open a Library record and test **Save to Notebook** and **Write note**.
5. Add a website and a physical book source.
6. Export the workspace JSON and import it in a private test browser before relying on it for research.

## Release contents

- `sustainable-catalyst-library/` — plugin source
- `sustainable-catalyst-library-v1.2.0.zip` — installable WordPress plugin
- `push_library_v1_2_0_to_github.sh` — empty-repository-safe GitHub deployment script
- `install_and_push_library_v1_2_0.sh` — Downloads-folder extraction and push helper
