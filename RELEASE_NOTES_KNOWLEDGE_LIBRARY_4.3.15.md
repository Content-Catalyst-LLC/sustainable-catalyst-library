# Sustainable Catalyst Library v4.3.15

## Unified Search & Guided Discovery

v4.3.15 connects conventional Library search and Research Librarian guidance as two coordinated discovery modes. It does not replace direct search with AI and does not enable the bridge globally. The Research Library page opts into the feature explicitly.

### Unified discovery bridge

The `[sc_library]` shortcode adds two optional attributes:

```text
show_librarian="true"
librarian_target="#research-front-door"
```

When enabled:

- the search command offers **Search Library** and **Ask the Research Librarian** as parallel actions;
- a populated result set exposes **Ask the Research Librarian about these results**;
- the handoff preserves the active query plus up to eight current result IDs;
- same-page handoffs use a scoped browser event and scroll to the configured Librarian target;
- cross-page handoffs use `librarian_prompt` and `record_ids` query parameters.

The feature defaults to **off**, preserving all existing `[sc_library]` embeds.

### Research Librarian return path

`[sc_research_librarian_orchestrator]` adds:

```text
initial_prompt=""
record_ids=""
library_url="#knowledge-explorer"
```

Front-door results now include **View all matching Library records**. On the Research Library page, that action sends the original research question into the existing Library search without a page reload. External Librarian pages can fall back to `library_search` query parameters.

### Naming correction

All v4.3.15 front-door UI uses **Ask the Research Librarian**. The temporary v4.3.14 wording **Ask the Library** is removed from the live template and revised page artifact.

### Safety and compatibility

- Workspace actions remain excluded from front-door results.
- Full Research Librarian Workspace actions still require explicit user confirmation.
- The standalone Research Librarian AI repository is not modified.
- v4.3.14 front-door behavior remains compatible.
- v4.3.13 Field Spotlight remains on its existing data/runtime contract.
- v4.3.12 Field Spotlight panel-content persistence remains unchanged.
- No Library reindex is required solely for v4.3.15.

### Page artifact

Use `RESEARCH_LIBRARY_PAGE_v4.3.15.html` after deploying the plugin.
