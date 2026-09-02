# Research Library Page Update v5.6.0

The plugin upgrade enables the Dynamic Library Explorer, but the existing WordPress page can still remain long if it contains the historical inline shortcodes for every Library application.

For the intended v5.6.0 experience, replace the **Research Library page body** with `RESEARCH_LIBRARY_PAGE_v5.6.0.html`.

The replacement keeps only:

1. a concise institutional hero;
2. the Dynamic Library Explorer;
3. compact handoffs to Research Librarian, Workspace, Lab, and Decision Studio;
4. a short research-infrastructure explanation;
5. a closing navigation block.

The historical inline Notebook, rooms, federation, citation, document-builder, portability, and other application embeds are intentionally removed from the front door, not deleted from the plugin.

Primary shortcode:

```text
[sc_library mode="explorer" show_header="false" per_page="12"]
```

Emergency local mode:

```text
?library_legacy=1
```
