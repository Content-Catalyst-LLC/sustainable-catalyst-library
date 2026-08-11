# Sustainable Catalyst Library v4.3.14

## Research Librarian Front Door, Guided Discovery & Research Flow

v4.3.14 promotes the Research Librarian from a deep-page capability into a primary public discovery route while preserving the Research Library as the institutional knowledge architecture.

### Research Library page

- The hero now presents **Ask the Research Librarian** as the primary action, with direct Library search and Knowledge Pathways alongside it.
- A new **Guided Discovery** front door appears immediately below the hero.
- The front door embeds a restrained Research Librarian query surface rather than the full orchestration workspace.
- Direct search, pathways, and Workspace remain visible as alternative routes into the knowledge system.
- A new four-stage research-flow model makes the platform sequence explicit: **Discover → Ask → Organize → Analyze / Apply**.
- The full Research Librarian remains deeper on the page for expanded recommendations, diagnostics, and user-confirmed Workspace actions.
- The Institutional Research Portal moves into the later documents/archive/standards portion of the page so reader discovery leads the experience.
- Existing Reader Pathways, Featured Knowledge Pathways, Core Libraries, technical knowledge architecture, methods/code, Research Layer, Workspace, connected tools, archive, standards, principles, and closing statement are retained.

### Research Librarian front-door mode

`[sc_research_librarian_orchestrator]` remains backward compatible.

A new presentation mode is available:

```text
[sc_research_librarian_orchestrator mode="front-door" title="Ask the Library" button_label="Ask the Library" full_url="#research-librarian"]
```

Front-door behavior:

- hides advanced intent and record-count controls from the initial public interaction;
- uses a bounded five-record request;
- provides example research prompts;
- shows a concise answer, up to three recommended starting records, and up to two continuation routes;
- links into the full Research Librarian for deeper work;
- does not expose or apply Workspace action controls in the compact front-door result;
- preserves the full orchestrator's explicit confirmation requirement for any Workspace action.

### Compatibility boundary

- The standalone Sustainable Catalyst Research Librarian AI repository is **not modified by this release**.
- Existing Library deterministic site-scoped orchestration remains the fallback/operating contract.
- v4.3.13 Master Field Spotlight behavior is preserved.
- v4.3.12 dedicated Field Spotlight panel-content persistence remains unchanged.
- Homepage Spotlight behavior is unchanged.
- Existing Library indexes do not require rebuilding solely for v4.3.14.

### Included page artifact

`RESEARCH_LIBRARY_PAGE_v4.3.14.html` contains the complete revised Research Library page content and can replace the current WordPress page content after the plugin upgrade.
