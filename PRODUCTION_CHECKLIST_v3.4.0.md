# Production Checklist — Knowledge Library v3.4.0

## Installation

- Confirm the plugin reports v3.4.0.
- Confirm Research Handoffs appears under SC Library.
- Confirm every Research Project displays the handoff panel.
- Confirm all v3.3.0 and earlier administration screens remain available.

## Project identity

- Run identity migration to Complete.
- Confirm every existing Project receives a UUID and URN.
- Rename and change the slug of a test Project.
- Confirm the UUID and URN remain unchanged.
- Confirm the old URL is added to aliases.
- Interrupt and resume migration.
- Confirm the migration lock prevents concurrent batches.

## Product registry

- Review every product launch route.
- Disable and re-enable a product.
- Test signed REST, local action, and export-only settings.
- Confirm disabled products cannot receive new handoffs.

## Product contracts

- Create every Research Lab handoff type.
- Create every Workbench handoff type.
- Create every Decision Studio handoff type.
- Create every Research Librarian handoff type.
- Create every Site Intelligence handoff type.
- Confirm each bundle contains the correct adapter schema.

## Bundle sections

- Test every section independently.
- Confirm private Sources, Claims, and Evidence Notes appear only in authorized bundles.
- Confirm project bibliography structure.
- Confirm semantic Topics and Concepts.
- Confirm pathways and recommendations.
- Confirm Source-integrity warnings.
- Confirm dataset references.

## Delivery security

- Create a seven-day token.
- Confirm the token is returned once.
- Confirm only the HMAC hash is stored.
- Open the delivery URL and confirm no-store headers.
- Rotate the token and confirm the old link fails.
- Confirm an expired token fails.
- Confirm a token cannot archive a handoff.
- Confirm a token cannot access another handoff.

## Status and return workflow

- Move a handoff through Ready, Sent, Opened, Accepted, In Progress, and Completed.
- Test Failed and retry to Ready.
- Test Cancelled and Archived.
- Confirm invalid transitions are rejected.
- Submit a result URL.
- Confirm the history records product, user, status, note, and time.
- Test the local return action.

## Exports

- Download JSON.
- Download Markdown.
- Download the ZIP bundle.
- Confirm manifest checksum.
- Confirm expected section files.
- Confirm binary private files are not copied unintentionally.

## API and CLI

- Test every REST route.
- Test every WP-CLI command.
- Confirm project permissions.
- Confirm token-authorized status updates.
- Confirm authenticated private responses are not cached.

## Regression

- Run all 24 curated release contracts.
- Confirm Pathways and Article Maps.
- Confirm Topics, Concepts, and Document Relationships.
- Confirm Source Integrity.
- Confirm Production Validation.
- Confirm connected projects, bibliographies, evidence, citations, connectors, holdings, OCR, PDF conversion, and public repository behavior.
