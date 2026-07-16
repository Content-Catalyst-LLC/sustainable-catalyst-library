# Production Checklist — Knowledge Library v4.0.0

## Installation

- Confirm plugin version 4.0.0.
- Confirm Institutional Platform appears under SC Library.
- Confirm Institutions and Research Units appear.
- Confirm v3.9.0 API, Export & Federation remains available.

## Institution and units

- Create an institution.
- Add identifiers and contact context.
- Create representative unit types.
- Assign unit leads.
- Test public and private institution/unit visibility.

## Permissions

- Review administrator capabilities.
- Review editor capabilities.
- Review author read access.
- Test custom roles.
- Confirm unauthorized users cannot access dashboard, permissions, migration, or handoffs.

## Registry

- Run migration to Complete.
- Verify UUIDs and URNs.
- Verify content and registry hashes.
- Assign institutions, units, visibility, governance, and stewards.
- Verify deleted institution and unit cleanup.

## Search

- Test every record type.
- Test public and private boundaries.
- Test institution and unit filters.
- Test governance and visibility filters.
- Test cursor pagination, facets, and ETags.
- Test large-library performance.

## Graph

- Test semantic relationships.
- Test institution and unit edges.
- Test depth one and two.
- Test truncation at 250 nodes.
- Test public exclusion of private relationships and records.

## Handoffs

- Test Research Librarian context.
- Test cross-product sections.
- Test a project handoff to each configured product.
- Verify checksums, sections, delivery permissions, and private-record boundaries.

## Public portal

- Test institutions and units.
- Test documents, pathways, collections, and publications.
- Test mobile, keyboard, print, and theme rendering.
- Confirm no private content or administrative IDs appear.

## Health and operations

- Confirm all subsystems are Ready.
- Confirm migration and health cron events.
- Test REST, AJAX, and WP-CLI.
- Test reverse-proxy and Cloudflare cache behavior.
- Review activity logs and backup recovery.

## Regression

- Run the explicit v4.0.0 release manifest.
- Confirm all retained v2.4.0–v3.9.0 contracts.
- Confirm ZIP integrity and executable permissions.
- Confirm no credentials, private exports, federation imports, or private content are packaged.
