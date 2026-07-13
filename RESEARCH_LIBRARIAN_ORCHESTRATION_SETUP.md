# Library v1.17.0 Research Librarian Workspace Orchestration

## Purpose

Research Librarian Workspace Orchestration turns the Library's existing search, Knowledge Graph, Notebook, connected-tool handoffs, books, and editorial systems into one controlled research route.

The orchestrator is not a general-purpose chatbot. It is restricted to the Sustainable Catalyst Library index and public Knowledge Graph. It can recommend and package actions, but it cannot publish, approve, schedule, or silently change a workspace.

## Install

1. Upload `sustainable-catalyst-library-v1.17.0.zip` through WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm **Sustainable Catalyst Library 1.17.0** under Installed Plugins.
4. Confirm the Library index is healthy under **SC Library → Index Tools**.
5. Confirm the Knowledge Graph has been rebuilt under **SC Library → Knowledge Graph**.
6. Open **SC Library → Research Librarian** and save the orchestration settings.

No Library index rebuild is required solely for v1.17.0.

## Public page

Create a WordPress page named **Research Librarian** and add:

```text
[sc_research_librarian_orchestrator]
```

Alternative alias:

```text
[sc_library_orchestrator]
```

Save the page URL under **SC Library → Research Librarian → Public page URL**. Library record cards can then open a focused route with `?record=POST_ID`.

## Retrieval model

Each request follows this bounded sequence:

```text
Question
→ indexed-title/excerpt/text search
→ explicit selected-record context
→ Knowledge Graph neighborhood expansion
→ transparent recommendation reasons
→ workflow routing
→ user-confirmed action packets
```

The response includes:

- inferred or selected research intent;
- recommended records;
- a reason for each recommendation;
- graph-connected records;
- recommended Sustainable Catalyst tools;
- explicit workspace actions;
- provider and retrieval diagnostics;
- safety boundaries.

## Supported routes

- Research Notebook
- Technical Translation Matrix
- Whiteboard
- Custom Book Builder
- Editorial Workflow
- Workbench
- Decision Studio
- Site Intelligence
- Sustainable Catalyst Lab

## Workspace actions

The browser can apply the following actions only after a user clicks **Apply to workspace**:

- create a collection;
- save recommended records;
- create a research brief;
- seed a Technical Translation Matrix;
- create a Whiteboard map;
- create a custom-book outline;
- create a connected-tool handoff;
- create an editorial review packet and open the existing workflow;
- export the complete browser workspace before preservation.

All actions remain editable. None are canonical publications.

## Optional synthesis endpoint

The orchestrator works without an AI provider. Its deterministic mode retrieves and routes records using WordPress and the Knowledge Graph.

An optional server-to-server Research Librarian endpoint can be configured. The endpoint receives only:

- the user's research question;
- the inferred intent;
- retrieved Library titles, excerpts, URLs, concepts, and recommendation reasons;
- proposed routes;
- a deterministic fallback answer.

The endpoint may return a concise synthesis. It cannot add, remove, modify, or apply action packets.

## Account sessions

Signed-in users can save orchestration sessions. Saved sessions include the question, intent, retrieved records, routes, actions, diagnostics, provider information, and timestamps.

Attributed action events can be recorded for saved sessions. Signed-out public discovery is read-only from the server perspective; local browser actions still require confirmation.

## REST endpoints

```text
/wp-json/sustainable-catalyst/v1/library/orchestrator/schema
/wp-json/sustainable-catalyst/v1/library/orchestrator/status
/wp-json/sustainable-catalyst/v1/library/orchestrator/query
/wp-json/sustainable-catalyst/v1/library/orchestrator/sessions
/wp-json/sustainable-catalyst/v1/library/orchestrator/sessions/{uuid}
/wp-json/sustainable-catalyst/v1/library/orchestrator/events
```

## Portable data

Portable export schema `sc-library-portable-export/1.7` adds:

- `orchestration_sessions`
- `orchestration_events`

These exports may contain private research questions and action history. Treat them as private research data.

## Boundaries

- Retrieval is site-scoped.
- Recommended records are not automatically authoritative.
- AI synthesis is visibly identified.
- Workspace actions require a user click.
- Suggested edits remain suggestions.
- Editorial approval remains in Editorial Workflow.
- Publication and scheduling remain in WordPress.
- Canonical Library records are never changed by the orchestrator.

## Confirmation boundary

Every action packet requires explicit confirmation in the browser before it changes the local Research Notebook or opens a connected workflow. The orchestrator cannot publish, approve, schedule, or silently modify canonical records.
