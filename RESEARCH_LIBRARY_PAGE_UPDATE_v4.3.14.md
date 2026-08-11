# Research Library page update — v4.3.14

## Safe update order

1. Install/upgrade **Sustainable Catalyst Library v4.3.14** first.
2. Clear WordPress/page-builder caches if the old plugin assets remain visible.
3. Edit the existing **Research Library** WordPress page.
4. Replace the page's current custom HTML/content with `RESEARCH_LIBRARY_PAGE_v4.3.14.html`.
5. Update/publish the page.
6. Clear page cache and browser cache.
7. Verify the page on desktop and mobile.

## Expected top-of-page order

1. Research Library hero
2. Guided Discovery / Research Librarian front door
3. Research Flow
4. Research Library Map
5. What This Library Is Built to Do
6. Search the Living Library

The Institutional Research Portal should no longer appear near the top. It now follows Documents and Institutional Archive, before Research Library Standards.

## Front-door behavior to verify

- Hero primary action scrolls to **Ask the Research Librarian**.
- The front-door Librarian shows one question field, one primary action, example prompts, and a full-Librarian continuation link.
- Example prompts populate the question field but do not submit automatically.
- A front-door query shows bounded starting records and continuation routes.
- The front-door result does not expose Workspace action buttons.
- **Continue in the full Research Librarian** scrolls to the full orchestrator later on the page.
- The full orchestrator still exposes Workspace actions and still requires explicit confirmation before applying them.

## Regression checks

- `[sc_library mode="full" initial_results="0" show_header="false" show_workspace="false"]` still renders in the Knowledge Explorer.
- `[sc_library_unified_workspace]` still renders in Research Workspace.
- Workbench, Decision Studio, Site Intelligence, and Lab routes are unchanged.
- Publications Field Spotlight behavior is unchanged from v4.3.13.
