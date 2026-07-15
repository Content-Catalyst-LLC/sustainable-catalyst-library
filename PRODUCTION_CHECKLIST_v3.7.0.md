# Production Checklist — Knowledge Library v3.7.0

## Installation

- Confirm the plugin reports v3.7.0.
- Confirm Document Intelligence appears under SC Library.
- Confirm v3.6.0 Collections & Archives remains available.
- Confirm document editors display the intelligence panels.

## Source extraction

- Test extracted PDF text.
- Test document-body fallback.
- Test OCR-derived text.
- Test empty and very short documents.
- Test documents over 500,000 characters.
- Confirm source hashes change after content changes.

## Sections and chunks

- Test HTML headings.
- Test flattened PDF headings.
- Test numbered headings.
- Test documents with no headings.
- Confirm 120-section and 500-chunk limits.
- Confirm 220-word chunks and overlap.
- Confirm section and chunk hashes.

## Generated fields

- Review summaries against original documents.
- Review every key point.
- Review suggested questions.
- Review recurring terms.
- Test title aliases.
- Confirm generated text is not presented as a quotation.

## Citation and gap signals

- Test DOI, URL, numeric, and author-year citations.
- Test reference headings.
- Test possible uncited claim signals.
- Test missing methods and limitations.
- Test missing Topics and Concepts.
- Test index truncation.

## Retrieval

- Test exact title.
- Test title prefix.
- Test punctuation and case normalization.
- Test aliases.
- Test term overlap.
- Test public and private search.
- Confirm ranking reasons.

## Comparisons

- Compare two through five documents.
- Confirm shared and distinctive terms.
- Confirm pairwise similarity.
- Confirm public comparison privacy.
- Confirm the limitation statement.

## Research Librarian

- Test document context filter.
- Test project context filter.
- Confirm original document links.
- Confirm private fields are omitted publicly.
- Confirm stale-profile fallback.
- Confirm title-aware questions route to the correct document.

## Jobs and migration

- Run migration to Complete.
- Interrupt and resume.
- Test migration lock.
- Create a force-reindex job.
- Test job retries and failure state.
- Test daily stale reindexing.
- Test exclusion behavior.

## Public output

- Enable public intelligence on a published document.
- Test all four shortcodes.
- Confirm disabled and private documents remain hidden.
- Test keyboard, mobile, and print presentation.
- Review caching.

## REST and WP-CLI

- Test every REST route.
- Confirm permissions and no-store headers.
- Test every WP-CLI command.
- Confirm chunk access requires document edit permission.

## Regression

- Run the explicit v3.7.0 release manifest.
- Confirm all retained v2.4.0–v3.6.0 contracts.
- Confirm plugin ZIP integrity.
- Confirm no credentials or private document content are packaged.
