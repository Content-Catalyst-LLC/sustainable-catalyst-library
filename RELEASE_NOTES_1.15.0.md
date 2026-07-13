# Sustainable Catalyst Library v1.15.0

## Collaboration, Review, and Editorial Workflow

Library v1.15.0 adds a WordPress-native editorial coordination layer for shared research and publishing work. It builds on persistent workspaces without replacing WordPress post revisions or canonical publishing records.

### Core capabilities

- Editorial review records linked to posts, plans, workspaces, books, boards, documents, and multimedia objects
- Invited participants with Observer, Reviewer, Editor, and Approver roles
- Existing-account access plus expiring email invitation tokens
- Comments with open and resolved states
- Suggested edits with pending, accepted, rejected, and withdrawn states
- Editorial states from Intake through Published and Archived
- Optimistic revision checking and expiring editor locks
- Decision notes, deadlines, priorities, owners, and assignees
- Attributed activity history and contributor manifests
- Shared workspace-role synchronization for workspace-linked reviews
- PostgreSQL, CSV, JSONL, and JSON export entities for all workflow records

### Privacy and authority

WordPress remains the identity and publishing authority. Review metadata does not auto-publish posts, auto-apply suggested edits, or expose private workspace content publicly. Only invited participants, owners, assignees, and administrators can access private review records.

### Shortcode

```text
[sc_library_editorial_workflow]
```

### Admin location

```text
SC Library → Editorial Workflow
```

### Schemas

- `sc-library-editorial-workflow/1.0`
- `sc-library-portable-export/1.5`
