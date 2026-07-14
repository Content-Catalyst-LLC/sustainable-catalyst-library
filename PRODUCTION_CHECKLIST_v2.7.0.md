# Production Validation Checklist — Knowledge Library v2.7.0

## Installation

- Confirm the plugin reports v2.7.0.
- Confirm Evidence Notes and Research Claims appear under SC Library.
- Confirm the Evidence and Claims workspace opens.
- Confirm existing Source, Project, connector, holdings, OCR, PDF, and document pages remain available.

## Evidence Notes

- Create a Direct Quotation note.
- Link a Research Source.
- Link a Knowledge Library document.
- Record a single page.
- Record a page range.
- Record a custom locator.
- Add context before and after.
- Add an analytical note.
- Select a Media Library attachment.
- Verify quotation and locator.
- Edit the quotation without re-verifying.
- Confirm verification clears and status returns to review.
- Re-verify explicitly.

## Claims

- Create each major Claim Type.
- Record scope, assumptions, limitations, and counterclaim.
- Set confidence.
- Verify a claim.
- Edit a citation-critical claim field without re-verifying.
- Confirm Verified returns to Under review.
- Re-verify explicitly.

## Links

- Link evidence as Supports.
- Link evidence as Contradicts.
- Link evidence as Qualifies.
- Link evidence as Contextualizes.
- Link evidence as Illustrates.
- Link evidence as Unresolved.
- Confirm duplicate links to the same claim are removed.
- Confirm strength is bounded from 1–5.
- Confirm the Claim reverse evidence index updates.
- Delete an Evidence Note and confirm the Claim index is repaired.
- Delete a Claim and confirm Evidence Note links are repaired.

## Sources and Projects

- Confirm a Source editor shows evidence counts.
- Use Add Evidence from Source.
- Confirm the Source is preselected.
- Confirm a Project editor shows claim and evidence counts.
- Render the Project evidence shortcode.

## Public boundaries

- Publish a note but leave visibility Private.
- Confirm it does not render publicly.
- Set visibility Public while its Source remains unpublished.
- Confirm it does not render publicly.
- Publish the Source and confirm the note appears.
- Retract the note and confirm it disappears.
- Publish a public Claim and confirm its packet renders.
- Retire the Claim and confirm it no longer renders publicly.
- Confirm private review notes never appear publicly.

## Exports

- Copy citation-ready quotation.
- Copy Markdown Evidence Note.
- Copy Claim evidence packet.
- Export note JSON.
- Export claim Markdown.
- Export project Markdown.
- Confirm page locators appear in Harvard in-text citations.

## REST API

- List public Evidence Notes anonymously.
- Confirm private Evidence Notes are excluded.
- Create a note as an editor.
- Reject an invalid Source ID.
- Reject an invalid document ID.
- Reject an invalid attachment ID.
- Update claim links.
- List public Claims anonymously.
- Confirm private Claims are excluded.
- Retrieve a public Project packet.
- Confirm a private Project packet is rejected.

## Interface

- Test adding and removing Claim Link rows.
- Test Media Library selection.
- Test copy controls.
- Test keyboard focus.
- Test mobile layout.
- Test print layout.
