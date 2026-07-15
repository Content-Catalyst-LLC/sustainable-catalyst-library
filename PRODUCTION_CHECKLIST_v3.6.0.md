# Production Checklist — Knowledge Library v3.6.0

## Installation

- Confirm the plugin reports v3.6.0.
- Confirm Collections & Archives appears under SC Library.
- Confirm Institutional Collections and Archive Components appear.
- Confirm the public collection archive resolves.
- Confirm v3.5.0 Quality & Governance remains available.

## Collection records

- Create a collection with every metadata field.
- Confirm UUID stability after title and slug changes.
- Test every collection status.
- Test every access level.
- Test embargo behavior.
- Enable and disable the public finding aid.

## Components

- Create Collection, Series, Subseries, Box, Folder, Item, and Digital Object levels.
- Test parent-child hierarchy.
- Confirm maximum display-depth protection.
- Link documents, Sources, and Projects.
- Delete a parent and confirm children are detached safely.

## Accessions

- Test every accession method.
- Test every processing status.
- Add donor, agreement, restriction, and extent fields.
- Add multiple custody events.
- Confirm chronological ordering.
- Confirm private accession data is not public.

## Preservation

- Add SHA-256 and SHA-512 checksums.
- Test missing checksums.
- Test Stable, Monitor, At Risk, Critical, and Missing states.
- Run a collection audit.
- Confirm audit persistence.
- Confirm daily cron selection.
- Verify checksums operationally against real files.

## Retention and disposition

- Test every retention class.
- Test review dates and triggers.
- Enable legal hold.
- Create every disposition action.
- Confirm destructive, transfer, and deaccession approval is blocked by hold.
- Test every disposition status.
- Confirm approval and completion histories.
- Confirm the plugin does not delete files.

## Public discovery

- Test the institutional collection shortcode.
- Test the finding-aid shortcode.
- Test collection browser filters.
- Test preservation-status shortcode.
- Verify private components and embargoes.
- Test mobile, keyboard, and print rendering.

## REST and WP-CLI

- Test every archive REST route.
- Confirm private no-store headers.
- Test every archive WP-CLI command.
- Test unauthorized disposition changes.
- Test migration locking and resume.

## Regression

- Run the explicit v3.6.0 release manifest.
- Confirm all retained v2.4.0–v3.5.0 contracts.
- Confirm plugin ZIP integrity.
- Confirm no secrets or private archival content are included in the release bundle.
