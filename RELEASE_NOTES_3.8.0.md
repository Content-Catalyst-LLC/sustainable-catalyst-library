# Sustainable Catalyst Knowledge Library v3.8.0

## Collaborative Review and Research Publishing

v3.8.0 adds structured collaborative review cycles and auditable research publication packages.

### Review cycles

New record:

```text
sc_review_cycle
```

Review lifecycle:

```text
Draft
Reviewers invited
In review
Changes requested
Revised
Approved
Closed
Archived
```

Review types:

```text
Editorial
Methodology
Evidence and claims
Citations and sources
Governance and integrity
Accessibility
Privacy and confidentiality
Legal and rights
Publication readiness
```

Each cycle can link Knowledge Library documents and Research Projects, record assignments, preserve document snapshots, collect structured notes, record decisions and conflict disclosures, and calculate approval readiness.

### Reviewer assignments

Roles:

```text
Author
Editor
Reviewer
Approver
Observer
```

Decisions:

```text
Pending
Approve
Approve with minor changes
Request changes
Reject
Recuse
```

Assignments preserve reviewer identity, role, review domain, decision, decision note, conflict disclosure, invitation time, and response time.

### Snapshot protection

Document title and readable content are hashed with SHA-256 when the review snapshot is created.

Approval is blocked when a reviewed document changes or disappears after snapshot.

### Structured notes

New hidden record:

```text
sc_review_note
```

Types include comments, questions, required changes, suggestions, citation issues, evidence issues, integrity issues, and accessibility issues.

Notes preserve document, parent note, section or anchor, quotation, body, author, assignee, severity, status, resolution, and resolution time.

### Review readiness

A review is blocked by:

- rejected decisions;
- unresolved high or critical notes;
- documents changed after snapshot;
- disclosed unresolved conflicts;
- requested changes;
- insufficient approvals;
- unresolved notes.

### Publication packages

New record:

```text
sc_pub_package
```

Publication lifecycle:

```text
Draft
Assembling
Publication review
Approved
Scheduled
Published
Withdrawn
Archived
```

Packages can preserve:

- document, project, and review links;
- release version;
- release notes;
- license or rights statement;
- DOI;
- canonical URL;
- embargo date;
- scheduled publication time;
- approvals;
- readiness report;
- immutable-style publication manifest;
- publication history.

### Readiness checks

Publication approval is blocked by missing documents, unpublished documents, missing review approval, missing version, missing license, or other critical/high failures.

The package manifest records linked document IDs, titles, modified times, SHA-256 content hashes, document-intelligence state, quality state, integrity state, review IDs, rights, identifier, and publication metadata.

### Public transparency

Shortcodes:

```text
[sc_review_transparency review="123"]
[sc_publication_record package="456"]
[sc_research_release_history]
[sc_collaborative_review_dashboard]
```

Public review transparency excludes private reviewer identities, annotations, conflicts, and deliberations.

### Scheduled publishing

Hourly WordPress cron can transition eligible Scheduled packages to Published at the recorded publication time.

The plugin changes package status and publication records. It does not submit DOIs, upload files to external repositories, or distribute publications automatically.

### Admin workspace

```text
SC Library → Review & Publishing
```

The workspace provides review and publication metrics, migration controls, review register, package register, readiness indicators, note counts, document-change alerts, and release scheduling.

### REST API

```text
POST     /wp-json/sc-library/v1/reviews
GET/POST /wp-json/sc-library/v1/reviews/{id}
POST     /wp-json/sc-library/v1/reviews/{id}/notes
POST     /wp-json/sc-library/v1/review-notes/{id}
POST     /wp-json/sc-library/v1/reviews/{id}/decision
GET      /wp-json/sc-library/v1/reviews/{id}/transparency
POST     /wp-json/sc-library/v1/publication-packages
GET/POST /wp-json/sc-library/v1/publication-packages/{id}
POST     /wp-json/sc-library/v1/publication-packages/{id}/evaluate
POST     /wp-json/sc-library/v1/publication-packages/{id}/transition
GET      /wp-json/sc-library/v1/review-publishing/dashboard
GET/POST /wp-json/sc-library/v1/review-publishing/migration
```

### WP-CLI

```text
wp sc-library reviews evaluate REVIEW_ID
wp sc-library reviews note REVIEW_ID
wp sc-library reviews decision REVIEW_ID
wp sc-library publishing evaluate PACKAGE_ID
wp sc-library publishing transition PACKAGE_ID
wp sc-library reviews migrate --limit=20
wp sc-library reviews dashboard
```

### Compatibility

v3.8.0 retains v3.7.0 Document Intelligence, v3.6.0 Collections and Archives, v3.5.0 Quality and Governance, v3.4.0 handoffs, v3.3.0 pathways, v3.2.0 semantics, v3.1.0 source integrity, and all earlier Knowledge Library systems.
