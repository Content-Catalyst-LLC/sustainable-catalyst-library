# Sustainable Catalyst Knowledge Library v4.0.0

## Connected Institutional Knowledge and Research Platform

v4.0.0 consolidates the Knowledge Library’s document, source, evidence, project, semantic, pathway, collection, governance, intelligence, review, publication, export, and federation layers into one institutional operating environment.

## Institutional records

New public record types:

```text
sc_institution
sc_research_unit
```

Institutions can preserve status, short name, description, public identifiers, contact context, public visibility, and a stable institutional identity.

Research units can preserve institution membership, unit type, status, description, research scope, lead users, public visibility, and a stable unit identity.

## Unified institutional record registry

The registry connects at least the following record families:

```text
Documents
Sources
Claims
Evidence notes
Research projects
Concepts
Named entities
Controlled vocabularies
Knowledge relationships
Knowledge pathways
Institutional collections
Archive components
Accessions
Research reviews
Research publications
Research policies
Quality issues
Workspace handoffs
API exports
Federation peers
Institutions
Research units
```

Every supported post record can receive:

- UUIDv4 identity;
- stable Sustainable Catalyst URN;
- institution assignment;
- research-unit assignments;
- visibility classification;
- governance state;
- steward user;
- content hash;
- registry hash;
- registration and update timestamps.

## Institutional permissions

Capabilities:

```text
sc_library_read_institutional
sc_library_manage_institutional
sc_library_manage_institutional_records
sc_library_publish_institutional
sc_library_manage_institutional_permissions
sc_library_manage_institutional_handoffs
sc_library_view_institutional_health
sc_library_export_institutional
```

Administrators receive the complete set. Editors receive operating capabilities. Authors receive institutional read access.

## Unified search

The institutional search contract supports:

- text query;
- one or more record types;
- institution filter;
- research-unit filter;
- visibility filter;
- governance-state filter;
- private or public boundary;
- opaque cursor pagination;
- type facets;
- stable ETags;
- a 25-record default and 100-record maximum.

## Institutional knowledge graph

The graph combines existing typed semantic relationships with institutional edges:

```text
governed-by-institution
managed-by-unit
part-of-institution
```

Graph responses preserve seed, depth, nodes, edges, truncation state, and SHA-256 graph checksum. Public graph requests exclude restricted records and private relationships.

## Platform health

The health center verifies the connected release layers from Foundation Documents through v3.9.0 Public API, Export, and Federation.

It also checks:

- plugin version;
- migration state;
- scheduled migration and health jobs;
- record-registry completeness.

## Cross-product handoffs

The institutional handoff envelope can include:

- project identity;
- institutions and units;
- selected records;
- target product;
- handoff type;
- requested sections;
- platform health summary;
- envelope SHA-256 checksum.

When a valid Research Project, product, and handoff type are supplied, the envelope is passed through the existing v3.4.0 cross-product handoff system.

## Public research portal

Shortcodes:

```text
[sc_institutional_research_portal]
[sc_institutional_search]
[sc_institutional_platform_status]
[sc_institutional_record id="123"]
```

The public portal can expose public institutions, research units, documents, pathways, collections, and reviewed publications without exposing restricted content or administrative metadata.

## REST API

```text
GET      /wp-json/sc-library/v1/institutional/platform
GET      /wp-json/sc-library/v1/institutional/health
GET      /wp-json/sc-library/v1/institutional/registry
GET      /wp-json/sc-library/v1/institutional/search
GET      /wp-json/sc-library/v1/institutional/records/{type}/{id}
GET      /wp-json/sc-library/v1/institutional/graph
POST     /wp-json/sc-library/v1/institutional/handoffs
GET      /wp-json/sc-library/v1/institutional/dashboard
GET      /wp-json/sc-library/v1/institutional/permissions
GET/POST /wp-json/sc-library/v1/institutional/migration
```

## WP-CLI

```text
wp sc-library institutional health
wp sc-library institutional registry
wp sc-library institutional record RECORD_ID
wp sc-library institutional search
wp sc-library institutional graph
wp sc-library institutional handoff
wp sc-library institutional migrate --limit=25
wp sc-library institutional dashboard
```

## Compatibility

v4.0.0 retains all v2.4.0–v3.9.0 subsystems and release contracts.
