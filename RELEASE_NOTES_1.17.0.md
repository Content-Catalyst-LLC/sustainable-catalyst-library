# Library v1.17.0 — Research Librarian Workspace Orchestration

Library v1.17.0 adds a controlled, site-scoped orchestration layer across the complete Sustainable Catalyst Library platform.

## Added

- Research Librarian orchestration admin page and public shortcode.
- Indexed Library retrieval with transparent relevance reasons.
- Knowledge Graph neighborhood expansion.
- Automatic intent classification with manual override.
- Routes to Notebook, Translation Matrix, Whiteboard, Book Builder, Editorial Workflow, Workbench, Decision Studio, Site Intelligence, and Lab.
- User-confirmed action packets for collections, records, notes, matrices, boards, books, handoffs, review packets, and workspace exports.
- Optional server-to-server synthesis constrained to supplied Library records.
- Saved account sessions and attributed action-event tables.
- Public discovery rate limiting and private-session permissions.
- Focused **Ask Research Librarian** links on Library record cards.
- Sustainable Catalyst Lab as a first-class integration target.
- Portable PostgreSQL entities for orchestration sessions and events.
- Portable export schema `sc-library-portable-export/1.7`.

## Safety boundaries

- No general-purpose chatbot behavior.
- No automatic publication, scheduling, approval, or canonical editing.
- Remote synthesis cannot modify action packets.
- Every workspace change requires explicit user confirmation.
- Public retrieval uses only indexed records and public graph relationships.

## Retained

- Large-library cursor indexing and Index Tools.
- Knowledge Graph and Relationship Intelligence.
- Editorial collaboration and review.
- Multimedia Studio and evidence reels.
- Persistent workspaces and optional Render synchronization.
- Server-side books, PDFs, and frozen editions.
- Content Planner, release coordination, public registry, Notebook, matrices, boards, annotations, books, and portable exports.
