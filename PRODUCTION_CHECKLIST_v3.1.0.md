# Production Checklist — Knowledge Library v3.1.0

## Installation

- Confirm the plugin reports v3.1.0.
- Confirm Source Integrity appears under SC Library.
- Confirm existing Source, Project, Citation, Evidence, Connector, and OCR screens remain available.

## Source versions

- Record a version label and number.
- Assign a family root.
- Add a Version Of relationship.
- Confirm the version family endpoint returns ordered records.
- Change citation-critical metadata and confirm a snapshot is preserved.
- Confirm snapshots remain bounded to 30.

## Integrity statuses

- Test every integrity status.
- Confirm severity mapping.
- Add an official notice URL and date.
- Enable and disable the public notice.
- Confirm the public Source warning appears only when permitted.

## Relationships

- Add Supersedes, Corrects, Replaces, and Retracts relationships.
- Confirm self-relations are rejected.
- Confirm duplicate relations collapse.
- Confirm incoming indexes update.
- Confirm deleting a Source removes stale relations.
- Confirm replacement-chain cycles do not loop.

## Conflict detection

- Create a newer Source that supersedes an older Source.
- Leave the older Source Current.
- Run the scan.
- Confirm a suggested Superseded status is recorded without changing the Source automatically.

## Impact

- Link the Source to a project, document, Evidence Note, and Claim.
- Change the integrity status.
- Confirm impact markers propagate.
- Return the Source to Current and confirm markers clear.
- Confirm Claim and Evidence review statuses are not changed automatically.

## Project review

- Record every acknowledgement state.
- Save reviewer notes.
- Confirm decisions are project-specific.
- Confirm public reports omit private acknowledgements.

## Public output

- Test the Source integrity shortcode.
- Test the project Source-integrity shortcode.
- Test bibliography warnings.
- Confirm historical citation text is preserved.
- Confirm recommended replacement links work.
- Confirm private previews issue no-cache headers.

## Scan and API

- Run scan batches to completion.
- Interrupt and resume a batch.
- Test the scan lock.
- Test all REST routes.
- Test all WP-CLI commands.
- Confirm private REST responses are not cacheable.

## Regression

- Run every retained v2.4.0–v3.0.1 contract.
- Confirm v3.0.1 Production Validation still functions.
- Confirm project migrations and export validation remain intact.
