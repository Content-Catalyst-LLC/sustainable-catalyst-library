# Library v1.15.0 Editorial Workflow Setup

## Installation

1. Install `sustainable-catalyst-library-v1.15.0.zip` and replace the current plugin.
2. Open **SC Library → Editorial Workflow**.
3. Leave editorial workflow enabled.
4. Set an edit-lock duration between 5 and 120 minutes.
5. Set invitation lifetime between 1 and 90 days.
6. Create a private test review linked to a post or workspace.
7. Invite an existing WordPress user as Reviewer or Editor.
8. Add a comment and suggested edit, then test resolution and approval.

No Library index rebuild is required. The upgrade creates five workflow tables without changing indexed records.

## Roles

- **Observer:** view and comment
- **Reviewer:** view, comment, and suggest edits
- **Editor:** reviewer permissions plus review editing and status transitions
- **Approver:** editor permissions plus approval, scheduling, and publication-state transitions

The review owner and WordPress administrators retain management access.

## Editorial states

`Intake → Drafting → Internal review → Author revision → Fact check → Accessibility review → Approval pending → Approved → Scheduled → Published`

`Changes requested`, `On hold`, and `Archived` are also available.

## Workspace collaboration

For a review linked to a persistent workspace, accepted Editor or Approver participants are synchronized as workspace Editors. Observer and Reviewer participants are synchronized as workspace Viewers. WordPress remains the permissions authority.

## Suggested edits

Suggestions are recorded and attributed but are not applied automatically to WordPress content. This prevents a review decision from silently overwriting canonical posts or workspace revisions.

## Invitation delivery

Email invitations use WordPress `wp_mail()`. The site must have working outbound email or SMTP configuration. Existing WordPress users are granted access immediately; new-email invitations expire after the configured period and require signing in with the invited email address.

## Shortcode

```text
[sc_library_editorial_workflow]
```

Optional direct review:

```text
[sc_library_editorial_workflow review="REVIEW-UUID"]
```

## Portable export

Use **SC Library → Portable Data Export** and select the Editorial scope. v1.15.0 exports:

- `editorial_reviews`
- `editorial_participants`
- `editorial_comments`
- `editorial_suggestions`
- `editorial_events`

Review exports can contain private research, comments, email addresses, and editorial decisions. Store them accordingly.
