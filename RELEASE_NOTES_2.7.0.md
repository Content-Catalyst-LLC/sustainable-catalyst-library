# Sustainable Catalyst Knowledge Library v2.7.0

## Quotations, Evidence Notes, and Claim Linking

This release adds a structured evidence layer above the v2.5.x citation system and v2.6.x scholarly and library connectors.

It preserves the existing Research Source, Research Project, citation, connector, holdings, OCR, PDF, and public document systems.

## New record types

### Evidence Note

Private WordPress record type:

```text
sc_evidence_note
```

An Evidence Note can represent:

- direct quotation
- paraphrase
- data point
- definition
- method or procedure
- observation
- counterevidence
- contextual evidence

The main editor stores the evidence text. The excerpt stores a concise research note.

### Research Claim

Private WordPress record type:

```text
sc_research_claim
```

A Research Claim stores:

- short claim label
- full claim statement
- claim type
- review status
- confidence
- scope
- assumptions
- limitations
- counterclaim or alternative explanation
- private review notes
- project relationships
- linked evidence index

## Precise locators

Evidence Notes support:

```text
Page
Page range
Paragraph
Section
Chapter
Figure
Table
Timecode
Record or dataset row
Custom locator
```

The locator is included in citation-ready Harvard in-text citations.

Examples:

```text
p. 12
pp. 44–46
para. 8
§ 4.1
fig. 3
00:14:32
```

## Claim-evidence relationships

An Evidence Note can link to one or more Research Claims through:

```text
Supports
Contradicts
Qualifies
Contextualizes
Illustrates
Unresolved
```

Each link stores:

- claim ID
- relationship
- strength from 1–5
- relationship rationale
- creation and update timestamps
- creating and updating user

The Evidence Note is the canonical link record. Research Claims maintain a synchronized reverse evidence index.

## Evidence review

Evidence Notes track:

- wording or transcription verification
- locator verification
- confidence from 1–5
- capture method
- review status
- visibility
- content hash
- last review time and user

Editing quotation text, source relationships, or locator fields invalidates previous verification unless the editor explicitly confirms re-verification.

## Claim review

Research Claims track:

```text
Draft
Under review
Reviewed
Verified
Disputed
Retired
```

A verified claim returns to review when its statement, scope, assumptions, limitations, or counterclaim changes without explicit re-verification.

## Public boundaries

Evidence Notes and Research Claims remain private WordPress record types.

A note can render publicly only when:

- its post status is Published
- visibility is Public
- it is not Retracted
- any linked Research Source is Published
- any linked Knowledge Library document is Published

A claim can render publicly only when:

- its post status is Published
- visibility is Public
- it is not Retired

A project evidence packet can render publicly only when the Research Project is Published and its project visibility is Public.

## Source-page integration

Published Research Source pages can display public Evidence Notes drawn from that Source.

Private notes, private claims, private projects, and unpublished source relationships are excluded.

## Evidence packets

Claim packets group evidence by relationship.

Project packets combine:

- project metadata
- claims
- linked evidence
- evidence linked directly to the project
- evidence not yet attached to a claim

Exports are available as normalized JSON and citation-ready Markdown.

## Shortcodes

```text
[sc_evidence_note id="123"]
[sc_claim_evidence id="456"]
[sc_project_evidence project="project-slug"]
```

Authorized editors can also render a Markdown project packet:

```text
[sc_project_evidence project="project-slug" format="markdown"]
```

## REST API

New endpoints under:

```text
/wp-json/sc-library/v1
```

```text
GET/POST  /evidence-notes
GET/POST  /evidence-notes/{id}
POST      /evidence-notes/{id}/links

GET/POST  /claims
GET/POST  /claims/{id}
GET       /claims/{id}/evidence

GET       /projects/{id}/evidence
POST      /evidence/export
```

Private reads and all writes require WordPress permissions.

## Compatibility

v2.7.0 retains:

- v2.6.1 Connector and Holdings Reliability
- v2.6.0 Scholarly and Library Database Connectors
- v2.5.1 Citation Formatting and Source Reliability
- v2.5.0 Citation and Research Source Manager
- v2.4.x OCR systems
- v2.3.x public document repository
- v2.2.x PDF conversion and bulk import


Private shortcode rendering is opt-in and permission-checked:

```text
[sc_evidence_note id="123" include_private="true"]
[sc_claim_evidence id="456" include_private="true"]
[sc_project_evidence project="project-slug" include_private="true"]
```

The default shortcode behavior remains public-only even for logged-in editors, reducing the risk of private evidence entering cached public pages.
