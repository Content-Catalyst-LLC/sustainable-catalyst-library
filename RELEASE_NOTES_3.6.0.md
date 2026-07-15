# Sustainable Catalyst Knowledge Library v3.6.0

## Institutional Collections and Archive Management

v3.6.0 adds a structured archival-management layer for institutional records, foundation documents, research materials, accession histories, custody records, retention schedules, preservation monitoring, and public finding aids.

## Institutional collections

New public record type:

```text
sc_inst_collection
```

Collections support:

- stable UUIDs and local collection identifiers;
- institution and department;
- creator or originating body;
- inclusive date ranges;
- extent and languages;
- scope and content;
- arrangement;
- provenance and custodial history;
- acquisition information;
- rights;
- access and use restrictions;
- preferred citation;
- collection lifecycle status;
- public finding-aid control.

## Archive components

New hierarchical record type:

```text
sc_archive_component
```

Levels of description:

```text
Collection
Fonds
Record group
Series
Subseries
Box
Folder
Item
Digital object
```

Components can connect to:

- Knowledge Library documents;
- Research Sources;
- Research Projects;
- digital objects and checksums;
- parent and child archive components.

## Access controls

Collection and component access levels:

```text
Public
Reading room only
Restricted
Embargoed
Confidential
```

Public responses require a public record, Public access level, and an expired or absent embargo date.

Private REST responses receive no-store cache headers.

## Accessions and custody

New hidden accession records support:

```text
Institutional transfer
Donation
Deposit
Purchase
Born-digital intake
Legacy or undocumented accession
Other
```

Processing states:

```text
Received
Quarantined
Inventory
Processing
Cataloged
Closed
```

Accession records can preserve donor, transfer source, agreement, rights, restrictions, extent, and ordered custody events.

## Digital objects and preservation

Component digital objects store:

- stable object ID;
- label;
- URI or path;
- media type;
- byte size;
- checksum;
- SHA-256, SHA-512, or legacy MD5 algorithm;
- preservation status.

Preservation states:

```text
Not assessed
Stable
Monitor
At risk
Critical
Missing digital object
```

Collection audits count digital objects, checksums, missing checksums, at-risk objects, missing objects, legal holds, and retention reviews.

## Retention and disposition

Retention classes:

```text
Permanent retention
Review before disposition
Retain for specified years
Transfer to another repository
Destroy after authorization
```

Collections can store retention years, trigger, next review date, and legal or administrative hold.

Disposition actions:

```text
Retain
Review
Transfer
Deaccession
Destroy
```

Destructive, deaccession, and transfer actions are blocked while a legal or administrative hold is active.

Disposition history preserves proposals, reviews, approvals, rejections, completion, cancellation, approvers, timestamps, and notes.

## Public finding aids

Finding aids expose collection description and an ordered hierarchical component tree.

Shortcode:

```text
[sc_archive_finding_aid id="123"]
```

Other shortcodes:

```text
[sc_institutional_collection id="123"]
[sc_archive_collection_browser]
[sc_archive_preservation_status id="123"]
```

## Collections and Archives Center

New location:

```text
SC Library → Collections & Archives
```

The Center displays:

- collection counts;
- public and restricted counts;
- at-risk preservation counts;
- retention reviews due;
- legal holds;
- digital-object and checksum metrics;
- collection register;
- resumable archive migration.

## Migration

Existing v3.6.0 collection, component, and accession records are normalized in three stages.

The migration:

- issues missing collection UUIDs;
- assigns default collection status and access;
- assigns default permanent retention;
- initializes component levels, access, and preservation status;
- initializes accession method and processing status;
- performs initial preservation audits;
- runs in 20-record batches;
- uses stable post-ID cursors;
- preserves bounded failures;
- supports cron, REST, AJAX, and WP-CLI.

## REST API

```text
GET      /wp-json/sc-library/v1/archives/collections
GET      /wp-json/sc-library/v1/archives/collections/{id}
GET      /wp-json/sc-library/v1/archives/collections/{id}/finding-aid
GET/POST /wp-json/sc-library/v1/archives/collections/{id}/preservation
POST     /wp-json/sc-library/v1/archives/collections/{id}/dispositions
POST     /wp-json/sc-library/v1/archives/dispositions/{id}/status
GET      /wp-json/sc-library/v1/archives/dashboard
GET/POST /wp-json/sc-library/v1/archives/migration
```

## WP-CLI

```text
wp sc-library archives collection COLLECTION_ID
wp sc-library archives finding-aid COLLECTION_ID
wp sc-library archives audit COLLECTION_ID
wp sc-library archives disposition COLLECTION_ID --action=review
wp sc-library archives migrate --limit=20
wp sc-library archives dashboard
```

## Compatibility

v3.6.0 retains:

- v3.5.0 Research Quality and Governance Center;
- v3.4.0 cross-product research handoffs;
- v3.3.0 Knowledge Pathways and Article Maps;
- v3.2.0 Topics, Concepts, and Document Relationships;
- v3.1.0 Source versioning and research integrity;
- v3.0.x connected research and production reliability;
- all retained citation, connector, holdings, OCR, Evidence Note, Claim, PDF, and repository systems.
