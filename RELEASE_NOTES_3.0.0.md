# Sustainable Catalyst Knowledge Library v3.0.0

## Connected Research Project and Bibliography Environment

v3.0.0 connects the Knowledge Library's Research Projects, Research Sources, citations, scholarly discovery, library holdings, documents, quotations, evidence notes, and research claims into one inspectable project environment.

The release preserves every retained v2.2.0–v2.7.0 subsystem and extends the existing `sc_research_project` record rather than replacing it.

## Connected project brief

Research Projects can now store:

- research question
- research objectives
- methods and research approach
- scope, boundaries, and exclusions
- start date
- target date
- project team and roles
- directly connected Knowledge Library documents

Existing project code, visibility, status, citation style, Source IDs, and bibliography title remain authoritative.

## Project Source registry

Each project Source receives a project-specific entry:

```text
Source
Role
Bibliography section
Inclusion status
Priority
Project annotation
Added time and user
Updated time and user
```

Source roles include:

```text
Primary source
Background
Theory or framework
Method
Data or dataset
Law or policy
Standard or guidance
Counterevidence
Case study
Other
```

Inclusion states:

```text
Included in bibliography
Candidate for review
Excluded from bibliography
```

The existing project Source ID list remains synchronized for backward compatibility.

## Bibliography sections

Default sections:

```text
Core Sources
Background and Context
Methods and Data
Law, Policy, and Standards
Counterevidence and Alternative Views
```

Editors can add, rename, describe, and remove sections.

## Sorting

Bibliographies can be ordered by:

```text
Section, then author and year
Author and year
Newest year first
Title
Priority, then author
```

## Source Discovery handoff

Opening Source Discovery with a project:

```text
SC Library → Research Environment → Discover and Import Sources
```

passes the project ID to the connector import.

Imported Sources are attached to the project as:

```text
Candidate for review
Background role
Core Sources section
```

The editor can then review metadata, assign a role and section, and include the Source in the bibliography.

## Bibliography health

The project environment calculates:

- total Sources
- included, candidate, and excluded Sources
- metadata-verified Sources
- incomplete Source records
- duplicate warnings
- Sources with an access location
- claims
- evidence notes
- documents
- bibliography readiness score

The readiness score is a bounded operational indicator. It does not prove that the bibliography is academically complete or that the project's conclusions are correct.

## Bibliography snapshots

Editors can create up to 20 named snapshots.

A snapshot records:

- citation style
- sort mode
- included Source IDs
- citation keys
- rendered citations
- sections
- Source roles
- project annotations
- SHA-256 content hash
- creating user and time

Snapshots are historical reference records. They do not replace WordPress revisions or restore the full project automatically.

## Connected exports

Available formats:

```text
Markdown
Plain text
HTML
BibTeX
RIS
CSL JSON
Connected project JSON
```

Markdown and connected JSON can include the v2.7.0 project evidence packet.

## Research Environment workspace

New admin location:

```text
SC Library → Research Environment
```

Tabs:

```text
Overview
Sources
Bibliography
Claims and Evidence
Documents
Exports
```

## Public shortcodes

```text
[sc_connected_research_project project="project-slug"]

[sc_project_bibliography_environment project="project-slug"]
```

Annotations can be displayed with:

```text
[sc_project_bibliography_environment project="project-slug" annotations="true"]
```

Private previews require explicit permission and opt-in:

```text
[sc_connected_research_project project="project-slug" include_private="true"]
```

A public project requires a Published project record and project visibility set to Public.

## REST API

New routes:

```text
GET/POST /wp-json/sc-library/v1/projects/{id}/workspace
GET      /wp-json/sc-library/v1/projects/{id}/bibliography-environment
GET/POST /wp-json/sc-library/v1/projects/{id}/bibliography-snapshots
GET      /wp-json/sc-library/v1/projects/{id}/export
GET      /wp-json/sc-library/v1/projects/{id}/activity
```

## Compatibility

v3.0.0 retains:

- v2.7.0 quotations, evidence notes, and claim linking
- v2.6.1 connector and holdings reliability
- v2.6.0 scholarly and library connectors
- v2.5.1 citation formatting and Source reliability
- v2.5.0 Citation and Research Source Manager
- v2.4.x OCR
- v2.3.x public document repository
- v2.2.x PDF conversion and import
