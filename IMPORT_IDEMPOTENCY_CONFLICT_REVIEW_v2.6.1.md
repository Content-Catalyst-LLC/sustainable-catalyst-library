# Import Idempotency and Metadata Conflict Review — v2.6.1

## Idempotent imports

A discovery import can provide:

```text
Idempotency-Key: source-import-unique-key
```

or include:

```json
{
  "idempotency_key": "source-import-unique-key"
}
```

Keys must contain 8–160 letters, numbers, periods, underscores, colons, or hyphens.

Results are retained for one day and scoped to the WordPress user.

## Provider import fingerprint

Each imported Source stores a fingerprint based on:

```text
provider | provider_record_id
```

Before creating a Source, the connector checks for the same fingerprint.

A matching Source is reused only when the current user can edit it.

## Conflict creation

In `fill_empty` mode, an incoming value creates a conflict when:

- the local field is populated
- the provider value is populated
- the normalized values differ

The local field is not overwritten.

## Conflict fields

Conflicts can cover:

```text
Authors
Organizational author
Editors
Year and publication date
Container
Publisher and place
Edition
Volume, issue, and pages
Chapter
Report or standard number
Jurisdiction
DOI
ISBN
PMID
Canonical and archive URL
Language
Full-text status
Title
Abstract
```

## Resolutions

### Use Provider Value

Updates the Source, marks metadata unverified, rebuilds indexes, and recalculates source reliability.

### Keep Current Value

Retains the researcher-edited value and closes the conflict record.

### Dismiss

Closes the conflict without treating either value as authoritative.

## Audit record

Each conflict keeps:

```text
field
local value
provider value
provider
provider record ID
first seen
last seen
status
resolution
resolved time
resolving user
```
