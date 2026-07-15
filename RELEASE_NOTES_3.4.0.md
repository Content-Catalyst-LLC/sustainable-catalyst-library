# Sustainable Catalyst Knowledge Library v3.4.0

## Cross-Product Research Workspace Handoffs

v3.4.0 connects Knowledge Library Research Projects to Research Lab, Workbench, Decision Studio, Research Librarian, and Site Intelligence through typed, auditable research handoffs.

The release does not require the target product to be active. A handoff can be delivered through an expiring REST link, a local WordPress action, a product launch route, or an exported research bundle.

## Stable cross-product project identity

Every Research Project receives a stable UUID and URN:

```text
urn:sc:research-project:{uuid}
```

The identifier remains stable when the project title, slug, or URL changes. Prior URLs are retained as aliases.

Existing projects are assigned identities through a resumable 25-project migration.

## First-party product registry

Built-in targets:

```text
Research Lab
Workbench
Decision Studio
Research Librarian
Site Intelligence
```

Each product has:

- a versioned handoff contract;
- supported handoff types;
- an enabled/disabled state;
- a configurable launch route;
- signed REST, local action, or export-only delivery mode.

The registry can be extended through:

```php
sc_library_cross_product_registry
```

## Research Lab handoffs

```text
Experiment brief
Notebook context
Dataset analysis
Research report review
```

Payloads can include project questions, objectives, methods, scope, datasets, Claims, Evidence Notes, and selected knowledge records.

## Workbench handoffs

```text
Calculation context
Model context
Visualization context
Technical report context
```

Payloads can include assumptions, units, parameters, methods, datasets, requested outputs, Sources, and project evidence.

## Decision Studio handoffs

```text
Evidence packet
Decision context
Scenario context
Decision review packet
```

Payloads can include the decision question, criteria, assumptions, scenarios, project Claims, Evidence Notes, bibliography, and Source-integrity review.

## Research Librarian handoffs

```text
Research context
Source discovery
Pathway context
Knowledge-gap analysis
```

Payloads can include the project brief, bibliography, semantic context, Topic and Concept coverage, pathway recommendations, evidence, and integrity warnings.

Integration filter:

```php
sc_library_research_librarian_project_context
```

## Site Intelligence handoffs

```text
Dataset reference
Saved intelligence view
Country context
Briefing context
```

Payloads can include provider and dataset identifiers, saved-view URLs, geographic and temporal scope, country codes, indicators, and project evidence.

## Typed handoff lifecycle

Statuses:

```text
Draft
Ready
Sent
Opened
Accepted
In progress
Completed
Failed
Cancelled
Archived
```

Transitions are validated. Expiring delivery tokens may only report operational recipient statuses such as Opened, Accepted, In Progress, Completed, or Failed.

## Handoff history and return records

Every handoff stores a bounded history of up to 200 events:

- prior and next status;
- user and product actor;
- note;
- result URL;
- structured metadata;
- timestamp.

Product plugins can report results through REST or:

```php
do_action( 'sc_library_cross_product_return', $handoff_uuid, $status, $payload );
```

## Research bundle

Schema:

```text
sc-platform-research-bundle/1.0
```

Available sections:

```text
Project brief and identity
Bibliography and Research Sources
Claims and Evidence Notes
Topics, Concepts, Entities, and relationships
Knowledge Pathways and article maps
Source integrity review
Dataset and saved-view references
```

Target-specific adapter payloads are included alongside the shared context.

Exports:

```text
JSON
Markdown
ZIP research bundle
```

ZIP bundles can contain:

```text
manifest.json
handoff.json
README.md
project.json
bibliography.json
evidence-packet.json
semantic-context.json
pathways.json
pathway-recommendations.json
source-integrity.json
dataset-references.json
adapter.json
```

## Delivery security

Delivery links use a random bearer token with:

- HMAC-SHA-256 storage;
- no plaintext token persistence;
- one-to-thirty-day expiry;
- token rotation;
- constant-time comparison;
- no-store REST response headers.

The raw token is returned only when the delivery link is created or rotated.

## Administration

New workspace:

```text
SC Library → Research Handoffs
```

Research Project editors also receive a Cross-Product Research Workspace Handoffs panel.

## Shortcodes

```text
[sc_project_handoff_workspace project="123"]
[sc_platform_project_identity project="123"]
```

The handoff workspace is private and requires project edit permission.

## REST API

```text
GET      /wp-json/sc-library/v1/platform/products
GET      /wp-json/sc-library/v1/projects/{id}/platform-identity
GET/POST /wp-json/sc-library/v1/projects/{id}/handoffs
GET      /wp-json/sc-library/v1/handoffs/{uuid}
POST     /wp-json/sc-library/v1/handoffs/{uuid}/status
POST     /wp-json/sc-library/v1/handoffs/{uuid}/token
GET/POST /wp-json/sc-library/v1/handoff-migration
```

## WP-CLI

```text
wp sc-library handoffs products
wp sc-library handoffs identity PROJECT_ID
wp sc-library handoffs migrate-identities
wp sc-library handoffs create PROJECT_ID PRODUCT TYPE
wp sc-library handoffs show HANDOFF_UUID
wp sc-library handoffs status HANDOFF_UUID STATUS
wp sc-library handoffs bundle HANDOFF_UUID
```

## Compatibility

v3.4.0 preserves:

- v3.3.0 Knowledge Pathways and Article Maps;
- v3.2.0 Topics, Concepts, and Document Relationships;
- v3.1.0 Source Versioning and Research Integrity;
- v3.0.1 Production Validation and Migration Reliability;
- v3.0.0 Connected Research Projects and Bibliographies;
- Evidence Notes, Claims, citations, connectors, holdings, OCR, PDF conversion, repository, and document systems.
