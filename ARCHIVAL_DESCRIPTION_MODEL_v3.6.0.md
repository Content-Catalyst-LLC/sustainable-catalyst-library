# Archival Description Model — v3.6.0

## Collection schema

```text
sc-library-institutional-collection/1.0
```

A collection is the principal archival aggregate.

Fields include identity, institution, creator, dates, extent, language, scope, arrangement, provenance, rights, restrictions, retention, preservation, and public-discovery state.

## Component schema

```text
sc-library-archive-component/1.0
```

Components form a bounded hierarchical tree with a maximum display depth of 20.

Canonical direction:

```text
Collection → Series → Subseries → Box → Folder → Item
```

The model also supports fonds, record groups, and digital objects.

## Stable identity

Every institutional collection receives a UUIDv4 stored separately from the title, slug, and local collection identifier.

The UUID remains stable when descriptive metadata changes.

## Document and research links

Components may reference:

```text
Knowledge Library documents
Research Sources
Research Projects
```

These links do not move or duplicate the underlying records.

## Digital-object records

Digital-object metadata is stored as a bounded structured array under the archive component.

The record contains identity, location, media type, bytes, checksum, algorithm, preservation state, and update audit fields.

## Hierarchy limitations

v3.6.0 stores hierarchy through WordPress parent relationships and explicit component-parent metadata.

Very large archives may eventually require dedicated archival tables or external archival systems.

## Description principles

Use the highest meaningful level of description.

Avoid repeating identical descriptive content at every component level.

Record restrictions at the narrowest applicable level.

Do not expose private donor, agreement, path, or custody information in public finding aids.
