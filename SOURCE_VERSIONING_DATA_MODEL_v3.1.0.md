# Source Versioning and Integrity Data Model — v3.1.0

## Integrity schema

```text
sc-library-source-integrity/1.0
```

Fields include:

```text
source_id
status
status_label
severity
requires_review
suggested_status
suggested_label
relationship_conflict
public_notice
notice_date
notice_url
reason
version_label
version_number
release_date
family_id
recommended_id
relations
incoming_relations
review_status
last_reviewed
status_changed_at
```

Private responses may include snapshots and the full impact report.

## Relationship schema

```text
sc-library-source-version-relation/1.0
```

```json
{
  "target_id": 101,
  "relation": "supersedes",
  "effective_date": "2026-07-14",
  "note": "Revised methodology and corrected tables.",
  "public": true,
  "created_at": "2026-07-14 12:00:00",
  "created_by": 7,
  "updated_at": "2026-07-14 12:00:00",
  "updated_by": 7
}
```

The outgoing record is canonical. Target Sources maintain a synchronized incoming index.

## Snapshot schema

```text
sc-library-source-version-snapshot/1.0
```

Snapshots are immutable historical metadata records with a SHA-256 hash and UUID.

Retention is bounded to 30 snapshots per Source.

## Impact schema

```text
sc-library-source-integrity-impact/1.0
```

Private impact reports include IDs for affected projects, documents, Evidence Notes, and Claims.

Public impact reports expose counts only.

## Project report schema

```text
sc-library-project-source-integrity/1.0
```

Project reports contain affected included Sources, statuses, replacement guidance, and private acknowledgement decisions.

## Propagated markers

```text
_sc_evidence_source_integrity_impact
_sc_claim_source_integrity_impacts
_sc_project_source_integrity_impacts
```

These markers flag review obligations. They do not change Claim or Evidence Note status automatically.
