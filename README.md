# Sustainable Catalyst Library v1.15.0

Library v1.15.0 adds WordPress-native collaboration, review, and editorial workflow to the complete v1.14.1 Library platform.

## Editorial Workflow

Open:

```text
SC Library → Editorial Workflow
```

Core capabilities:

- Reviews linked to WordPress records, persistent workspaces, books, boards, documents, plans, and multimedia objects
- Observer, Reviewer, Editor, and Approver roles
- Existing-account access and expiring email invitations
- Comments with resolution states
- Suggested edits with accept, reject, and withdraw decisions
- Intake, drafting, internal review, revision, fact-check, accessibility, approval, scheduling, publication, hold, and archive states
- Optimistic revision checks and expiring editor locks
- Decision notes, deadlines, ownership, assignment, and priority
- Attributed activity history and contributor manifests
- Workspace-role synchronization for reviews linked to persistent workspaces

Shortcode:

```text
[sc_library_editorial_workflow]
```

Direct review:

```text
[sc_library_editorial_workflow review="REVIEW-UUID"]
```

## Authority and safety boundaries

WordPress remains the publishing and identity authority. The editorial layer does not automatically publish posts, apply suggested text, replace WordPress revisions, or expose private workspace content. Review participants receive only the role granted to them.

## Portable data

Portable export schema:

```text
sc-library-portable-export/1.5
```

New entities:

- `editorial_reviews`
- `editorial_participants`
- `editorial_comments`
- `editorial_suggestions`
- `editorial_events`

## Retained systems

v1.15.0 retains:

- v1.14.1 public record-card layout repair
- Multimedia Studio and evidence reels
- Large-Library Index Tools and cursor reconciliation
- Persistent account workspaces and optional Render synchronization
- Server-side book and PDF production
- Content Planner and release coordination
- Research Notebook, matrices, boards, annotations, and books
- PostgreSQL, CSV, JSONL, and JSON portability

See `EDITORIAL_WORKFLOW_SETUP.md`, `RELEASE_NOTES_1.15.0.md`, and the retained system setup guides.
