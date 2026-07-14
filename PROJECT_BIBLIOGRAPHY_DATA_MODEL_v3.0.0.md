# Connected Project and Bibliography Data Model — v3.0.0

## Project schema

```text
sc-library-connected-project/1.0
```

Project response fields:

```text
id
title
description
summary
project_code
visibility
status
citation_style
bibliography_title
research_question
objectives
methods
scope
start_date
target_date
sections
source_entries
document_ids
claim_ids
evidence_ids
health
public
modified_gmt
```

Authorized private responses can also include:

```text
team
snapshots
activity
```

## Source-entry schema

```text
sc-library-project-source-entry/1.0
```

```json
{
  "source_id": 123,
  "role": "method",
  "section": "methods-data",
  "inclusion": "included",
  "priority": 4,
  "annotation": "Defines the primary analytical method.",
  "added_at": "2026-07-14 12:00:00",
  "added_by": 7,
  "updated_at": "2026-07-14 12:00:00",
  "updated_by": 7
}
```

## Bibliography schema

```text
sc-library-project-bibliography/1.0
```

```text
project_id
project_title
bibliography_title
citation_style
sort
entry_count
sections
generated_at
health
```

## Snapshot schema

```text
sc-library-bibliography-snapshot/1.0
```

A snapshot stores a maximum of the current included bibliography, not full Source records.

## Export schema

```text
sc-library-project-export/1.0
```

Connected JSON contains:

```text
project
bibliography
evidence_packet
```

## Compatibility relationship

Canonical simple relationship:

```text
Project → META_PROJECT_SOURCE_IDS
Source → META_PROJECT_IDS
```

v3.0.0 augmentation:

```text
Project → META_SOURCE_ENTRIES
```

The augmented registry is synchronized with the retained canonical Source ID relationship.

## Privacy

Public outputs exclude:

- team membership
- snapshots
- activity
- unpublished Sources
- private Source metadata
- unpublished documents
- private claims
- private evidence notes
