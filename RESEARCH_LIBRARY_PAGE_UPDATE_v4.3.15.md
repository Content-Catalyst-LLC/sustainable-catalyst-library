# Research Library page update — v4.3.15

Replace the current Research Library page content with `RESEARCH_LIBRARY_PAGE_v4.3.15.html` after deploying Sustainable Catalyst Library v4.3.15.

## Changes from v4.3.14

- Corrects all front-door language to **Ask the Research Librarian**.
- Enables the Library discovery bridge only on the Research Library page with `show_librarian="true"`.
- Direct Library search now offers **Ask the Research Librarian** before a search and **Ask the Research Librarian about these results** after results load.
- Search-to-Librarian handoff preserves the active query and up to eight current result IDs.
- Research Librarian front-door results add **View all matching Library records**, returning the original research question to the Library search.
- Same-page handoffs use browser events and do not reload the page. Cross-page handoffs fall back to query parameters.
- The full Research Librarian, Research Workspace, Field Spotlight, pathways, archive, standards, and connected-platform sections remain intact.

## Required page shortcodes

```text
[sc_research_librarian_orchestrator mode="front-door" title="Ask the Research Librarian" button_label="Ask the Research Librarian" full_url="#research-librarian" library_url="#knowledge-explorer"]

[sc_library mode="full" initial_results="0" show_header="false" show_workspace="false" show_librarian="true" librarian_target="#research-front-door"]
```
