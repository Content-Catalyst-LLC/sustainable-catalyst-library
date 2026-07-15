# Document Gap Review Guide — v3.7.0

## Review principle

A gap signal indicates that the deterministic analyzer did not detect a structural element.

It does not prove the element is absent.

## Signals

### Insufficient readable text

The document has too little text for reliable indexing.

Review extraction, OCR, or source-file availability.

### Missing structure

No reliable heading structure was detected.

The document may still be coherent but difficult to retrieve by section.

### Methods not detected

No methods, methodology, approach, or research-design signal was found.

This can be appropriate for narrative, legal, historical, or reference documents.

### Limitations not detected

No explicit limitations, constraints, or caveats signal was found.

Editorial review should determine whether a limitations section is appropriate.

### Citations not detected

No DOI, URL, numeric citation, author-year citation, or reference heading was found.

Some primary records and institutional documents do not require scholarly references.

### Possible citation gaps

Claim-like sentences were found without nearby citation patterns.

This signal requires human review and can produce false positives.

### Topics missing

No canonical Knowledge Topics are assigned.

### Concepts missing

No reusable Concepts are connected.

### Index truncated

The source exceeded bounded section, chunk, or character limits.

Use segmented processing for the complete document.

## Resolution

After reviewing a signal:

1. correct extraction or OCR;
2. improve document structure;
3. add or revise citations;
4. assign Topics and Concepts;
5. record that the signal is inapplicable;
6. force a full reindex;
7. verify the new profile against the original.
