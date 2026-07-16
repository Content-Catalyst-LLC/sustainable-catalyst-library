# Unified Record Registry — v4.0.0

## Purpose

The registry provides one typed inventory across the Knowledge Library architecture.

## Core fields

```text
Schema
WordPress ID
UUID
URN
Record type
Post type
Title
Slug
URL
Excerpt
WordPress status
Visibility
Governance state
Institution
Research units
Publication time
Modification time
Content SHA-256
Registry SHA-256
Registration time
Registry update time
```

Private readers can additionally receive raw content, author ID, steward ID, unit leads, and institutional contact context where appropriate.

## Registry hashing

The registry hash is calculated over canonical sorted record metadata after excluding volatile registry timestamps and the previous registry hash.

The hash detects metadata change. It is not a digital signature.

## Assignment

Supported records receive an Institutional Record Context metabox. Institution, unit, visibility, governance, and steward values are explicit and independently editable.
