# Sustainable Catalyst Knowledge Library v3.1.0

## Source Versioning, Supersession, and Research Integrity

v3.1.0 adds a structured integrity layer to Research Sources without silently rewriting historical citations, Evidence Notes, Claims, or project bibliographies.

## Version identity

Each Research Source can now store:

- version label
- version number
- version release date
- version-family root
- recommended replacement
- structured version and integrity relationships

## Integrity statuses

```text
Current
Updated or revised
Corrected
Superseded
Deprecated
Expression of concern
Retracted
Withdrawn
Archived historical version
```

Statuses map to bounded public and administrative severities:

```text
None
Information
Warning
High
Critical
```

## Source relationships

Directional relationships include:

```text
Is a version of
Supersedes
Corrects
Retracts
Replaces
Erratum for
Supplement to
Translation of
Derived from
```

The newer or corrective Source points to the earlier Source. Incoming relationship indexes are synchronized for reverse navigation and replacement resolution.

## Replacement guidance

The system can resolve a recommended Source through:

1. an explicit recommended replacement;
2. an incoming Supersedes, Corrects, or Replaces relationship;
3. a bounded replacement chain with cycle protection.

The original citation remains preserved. The platform presents a warning and replacement link rather than automatically editing the research record.

## Relationship-status conflict detection

When a Source is targeted by a public or private correction, supersession, replacement, or retraction relationship but its recorded integrity status has not been updated, the integrity scan records a relationship conflict and recommends review.

The scan does not silently change the Source status.

## Structured version snapshots

Citation-critical and integrity-critical Source metadata is hashed and preserved when it changes.

Snapshots include:

- title, abstract, description, authors, editors, and organization
- publication and container metadata
- identifiers and URLs
- Source type and topics
- attachment, project, and document relationships
- citation key and rendered Harvard citation
- version identity
- integrity status and notice
- version relationships
- SHA-256 hash
- capture user and time

Up to 30 snapshots are retained per Source.

## Research impact reports

Integrity changes identify affected:

- Research Projects
- Knowledge Library documents
- Evidence Notes
- Research Claims
- public projects, documents, and evidence records

Impact markers are propagated to connected records without changing their review status or retracting them automatically.

## Project integrity review

Research Projects receive a Source Integrity Impact panel with project-specific decisions:

```text
Pending review
Reviewed
Replacement planned
Citation replaced
Retained for historical or critical context
Excluded from project bibliography
```

Each decision can include a reviewer note and audit metadata.

## Public integrity notices

Published Source pages can display:

- integrity status
- notice explanation
- notice date
- official notice link
- recommended replacement
- explicit statement that the historical citation is preserved

Public project bibliographies display integrity warnings beside affected citations.

Critical retractions and withdrawals remain visible as warnings rather than disappearing from the historical record.

## Source Integrity workspace

New location:

```text
SC Library → Source Integrity
```

The workspace shows:

- Sources requiring review
- retracted and withdrawn Sources
- expressions of concern
- correction and supersession review
- version identity
- recommended replacements
- project, evidence, and claim impact
- resumable integrity scan status

## Resumable integrity scan

The scan uses:

- persistent cursor
- 20-Source batches
- 180-second lock
- hourly WordPress cron continuation
- bounded failure history
- manual scan controls
- WP-CLI operation

## Shortcodes

```text
[sc_source_integrity id="123"]

[sc_project_source_integrity project="project-slug"]
```

Private previews require explicit permission:

```text
[sc_source_integrity id="123" include_private="true"]
```

## REST API

```text
GET/POST /wp-json/sc-library/v1/sources/{id}/integrity
GET      /wp-json/sc-library/v1/sources/{id}/versions
GET      /wp-json/sc-library/v1/sources/{id}/impact
GET/POST /wp-json/sc-library/v1/projects/{id}/source-integrity
GET      /wp-json/sc-library/v1/integrity/alerts
GET/POST /wp-json/sc-library/v1/integrity/scan
```

## WP-CLI

```text
wp sc-library sources integrity-scan
wp sc-library sources integrity SOURCE_ID
wp sc-library sources integrity-rebuild SOURCE_ID
wp sc-library projects integrity PROJECT_ID
```

## Compatibility

v3.1.0 preserves:

- v3.0.1 production validation and migration reliability
- v3.0.0 connected research projects and bibliographies
- v2.7.0 quotations, Evidence Notes, and Claims
- v2.6.x scholarly connectors and holdings
- v2.5.x citations and Research Sources
- v2.4.x OCR
- v2.3.x public document repository
- v2.2.x PDF conversion and import
