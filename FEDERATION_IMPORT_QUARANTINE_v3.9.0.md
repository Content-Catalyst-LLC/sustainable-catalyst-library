# Federation Import Quarantine — v3.9.0

## Principle

Remote metadata is untrusted input.

No federation payload creates or updates a public Knowledge Library record automatically.

## Validation

The quarantine layer checks:

```text
Maximum encoded size: 5 MiB
Top-level object
Schema field
Records array
Maximum 5,000 records
Required record ID
Required record type
Required record title
Peer trust
SHA-256 payload hash
```

## Status

```text
Quarantined
Rejected
Approved metadata
Archived
```

## Decisions

### Approve metadata

Records that the metadata package passed review.

It does not publish or import content.

### Reject

Records that the package should not be used.

### Archive

Preserves the package for historical or forensic context.

## Review questions

- Is the peer authorized?
- Is the schema expected?
- Is the source node identifiable?
- Are record identifiers stable?
- Are rights and licenses compatible?
- Does the package contain personal or restricted information?
- Are hashes and counts consistent?
- Does the content duplicate or conflict with existing records?
