# Evidence Data Model — Knowledge Library v2.7.0

## Evidence Note schema

```text
sc-library-evidence-note/1.0
```

Core fields:

```text
id
title
content
summary
evidence_type
source_id
document_id
project_ids
claim_links
locator_type
locator_start
locator_end
locator_label
context_before
context_after
analysis
transcription_method
quote_verified
locator_verified
visibility
review_status
confidence
attachment_id
content_hash
last_reviewed
modified_gmt
```

## Research Claim schema

```text
sc-library-research-claim/1.0
```

Core fields:

```text
id
title
statement
summary
claim_type
project_ids
evidence_ids
status
confidence
visibility
scope
assumptions
limitations
counterclaim
review_notes
last_reviewed
modified_gmt
```

`review_notes` is returned only to authorized editors.

## Claim-evidence link schema

```text
sc-library-claim-evidence-link/1.0
```

```json
{
  "schema": "sc-library-claim-evidence-link/1.0",
  "claim_id": 456,
  "relation": "supports",
  "strength": 4,
  "note": "Directly supports the causal mechanism.",
  "created_at": "2026-07-14 12:00:00",
  "created_by": 7,
  "updated_at": "2026-07-14 12:00:00",
  "updated_by": 7
}
```

## Evidence packet schema

```text
sc-library-evidence-packet/1.0
```

Claim packets include:

```text
claim
links
link_count
relation_totals
generated_at
```

Project packets include:

```text
project
claims
evidence
claim_count
evidence_count
generated_at
```

## Canonical relationship direction

Evidence Note:

```text
canonical claim links
```

Research Claim:

```text
synchronized evidence ID index
```

The reverse index can be rebuilt from Evidence Notes.

## Deletion behavior

Deleting an Evidence Note removes it from linked Claim indexes.

Deleting a Claim removes its links from Evidence Notes.

Deleting a Source or document clears the relationship but preserves the Evidence Note for review.

Deleting a Project removes the Project ID from Evidence Notes and Claims.

## Hash behavior

Evidence hashes include:

```text
evidence content
Source ID
document ID
locator type
locator start
locator end
custom locator label
```

Claim hashes include:

```text
claim title
statement
scope
assumptions
limitations
counterclaim
```

Hash changes can invalidate prior verification.
