# Production Checklist — Knowledge Library v3.3.0

## Installation

- Confirm the plugin reports v3.3.0.
- Confirm Pathways and Maps appears under SC Library.
- Confirm Knowledge Pathways and Pathway Types appear.
- Confirm the `/pathways/` archive and pathway pages do not return 404.
- Confirm rewrite rules refresh only once for v3.3.0.

## Editor

- Create each pathway type.
- Test every level and stage.
- Add every supported step kind.
- Search for records from the step editor.
- Reorder steps by drag and drop.
- Confirm duplicate records collapse.
- Confirm a pathway cannot include itself.
- Confirm private records remain available to editors but do not render publicly.
- Save outcomes, audience, times, Concepts, Entities, and Topics.

## Prerequisites and continuations

- Add prerequisite pathways.
- Add continuation pathways.
- Confirm direct self-links are rejected.
- Delete a referenced pathway and confirm stale links are removed.
- Review multi-pathway cycles manually.

## Article maps

- Test all map modes.
- Confirm sequence edges.
- Confirm semantic edges among included records.
- Confirm map node links.
- Confirm SVG title and description.
- Confirm keyboard scrolling.
- Confirm the complete text-list fallback.
- Test mobile and print output.

## Project derivation

- Generate a pathway from a Research Project.
- Confirm the result is Draft.
- Confirm the Project, documents, Sources, Claims, and Evidence Notes appear.
- Confirm project objectives become outcomes.
- Confirm project Topics are copied.
- Confirm a second ordinary derivation returns the existing pathway.
- Confirm forced-new derivation creates a separate draft.

## Recommendations

- Test query matching.
- Test Topic, Concept, Entity, node, level, and audience matching.
- Confirm the Research Librarian filter returns structured recommendations.
- Confirm recommendation caching.
- Edit a pathway and confirm cache invalidation.

## Public navigation

- Confirm pathway membership appears on public documents.
- Confirm pathway membership appears on public Research Sources.
- Confirm pathway membership appears on public Claims.
- Test every shortcode.
- Confirm private preview shortcodes require edit permission.

## API and CLI

- Test all REST routes.
- Confirm unauthorized pathway updates fail.
- Confirm private responses use no-store headers.
- Test every WP-CLI command.

## Regression

- Run the curated v3.3.0 release manifest.
- Confirm v3.2.0 Topics and Relationships remain operational.
- Confirm v3.1.0 Source Integrity remains operational.
- Confirm v3.0.1 Production Validation remains operational.
- Confirm citations, connectors, holdings, OCR, Evidence, Claims, Projects, PDFs, and repository systems remain available.
