# Research Library page update — v4.3.16

Replace the current Research Library page body with `RESEARCH_LIBRARY_PAGE_v4.3.16.html` after deploying the v4.3.16 plugin.

The page preserves the merged v4.3.15 wording, including **Have a Question?**, **Ask the Research Librarian**, and **Search the Library Without Losing the Pathways**. It adds only the copy needed to explain that the Research Librarian can now recommend curated Knowledge Pathways and ordered starting steps.

The Library embed must retain:

```text
[sc_library mode="full" initial_results="0" show_header="false" show_workspace="false" show_librarian="true" librarian_target="#research-front-door"]
```

The front-door Librarian must retain `library_url="#knowledge-explorer"` so the v4.3.15 bidirectional Search ↔ Librarian bridge remains active.
