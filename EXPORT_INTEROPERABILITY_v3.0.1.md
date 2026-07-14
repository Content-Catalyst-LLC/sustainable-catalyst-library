# Export Interoperability — v3.0.1

## BibTeX

Checks for at least one valid entry header when content exists and balanced braces.

## RIS

Checks matching `TY  -` and `ER  -` records and valid record ordering.

## CSL JSON

Requires every record to contain:

```text
id
type
title
```

Missing authors or issued dates generate warnings rather than structural failure.

## Connected JSON

Requires a JSON-serializable structure and a schema identifier.

## Required production testing

Import representative exports into the intended citation manager, LaTeX workflow, document generator, or institutional repository. Different downstream systems may apply additional constraints.
