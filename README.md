# Sustainable Catalyst Library v1.17.0

Library v1.17.0 adds **Research Librarian Workspace Orchestration** to the complete v1.16.0 Library platform.

## Research Librarian orchestration

The orchestrator searches the canonical Library index, expands relevant records through the Knowledge Graph, explains why records were recommended, and builds a controlled research route.

```text
Question
→ Find
→ Explain
→ Collect
→ Organize
→ Route
→ Produce
```

It can prepare user-confirmed actions for:

- Notebook collections, saved records, sources, and research briefs
- Technical Translation Matrices
- Whiteboards
- Custom books and preservation packets
- Workbench analysis
- Decision Studio evidence canvases
- Site Intelligence investigations
- Sustainable Catalyst Lab workflows
- Editorial review and publication coordination

The orchestrator never publishes, approves, schedules, or silently changes canonical content.

## Shortcodes

```text
[sc_research_librarian_orchestrator]
[sc_library_orchestrator]
```

Create a public Research Librarian page, add one shortcode, and save its URL under **SC Library → Research Librarian**.

## REST API

```text
/wp-json/sustainable-catalyst/v1/library/orchestrator/schema
/wp-json/sustainable-catalyst/v1/library/orchestrator/status
/wp-json/sustainable-catalyst/v1/library/orchestrator/query
/wp-json/sustainable-catalyst/v1/library/orchestrator/sessions
/wp-json/sustainable-catalyst/v1/library/orchestrator/sessions/{uuid}
/wp-json/sustainable-catalyst/v1/library/orchestrator/events
```

## Portable data

Portable export schema:

```text
sc-library-portable-export/1.7
```

New normalized entities:

- `orchestration_sessions`
- `orchestration_events`

## Retained systems

- Knowledge Graph and relationship intelligence
- Collaboration, reviews, comments, suggestions, approvals, locks, and attribution
- Multimedia Studio, clips, transcripts, rights, and evidence reels
- Large-Library Index Tools and cursor reconciliation
- Persistent account workspaces and optional Render synchronization
- Server-side book and PDF production
- Content Planner, release coordination, and public registry
- Research Notebook, matrices, boards, annotations, and books
- PostgreSQL, CSV, JSONL, and JSON portability

See `RESEARCH_LIBRARIAN_ORCHESTRATION_SETUP.md` and `RELEASE_NOTES_1.17.0.md`.
