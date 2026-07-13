# Library v1.19.0 Portable Data Setup

Library v1.19.0 preserves every earlier export scope and advances the portable schema to `sc-library-portable-export/2.0`. WordPress remains the canonical publishing system; the export is a normalized, application-oriented representation suitable for PostgreSQL, CSV, JSONL, JSON, preservation review, and disaster-recovery testing.

## Preservation entities

The Preservation scope adds:

- `preservation_snapshots` — immutable content editions, metadata, resources, relationships, source hashes, manifest hashes, supersession links, retention dates, and hold flags
- `integrity_checks` — bounded audit outcomes for records, relationships, attachments, authority URLs, and optional external links
- `authority_history` — append-only documentation authority, version, status, and supersession changes

Snapshot HTML, normalized text, and manifests are included. WordPress attachments and PDF binaries remain external file references; their URLs, metadata, and SHA-256 checksums are preserved.

## Complete normalized model

The v2.0 schema also retains the earlier entities for:

- Canonical records, terms, relationships, resources, documentation, plans, and dependencies
- Persistent account workspaces, revisions, collaborators, and synchronization logs
- Server document jobs and frozen PDF editions
- Multimedia assets, clips, reels, rights metadata, and processing jobs
- Editorial reviews, participants, comments, suggestions, and attributed events
- Knowledge Graph nodes and edges with confidence, provenance, visibility, and verification
- Research Librarian orchestration sessions and action events
- Foundation Documents, page-aware PDF text, and version manifests
- Developer API keys, webhooks, and delivery metadata without exporting credentials or signing secrets

## Install and validate

1. Upload `sustainable-catalyst-library-v1.19.0.zip` and replace the current plugin.
2. Open **SC Library → Portable Data Export**.
3. Export **Schema only** as PostgreSQL SQL and confirm the preservation tables are present.
4. Export the **Preservation** scope as JSONL or CSV and inspect `manifest.json` and `checksums.sha256`.
5. Restore the SQL into a disposable PostgreSQL database before treating the export as an operational recovery source.
6. Store exports containing private workspaces, editorial data, or administrative audit details as private institutional records.

A PostgreSQL restore is optional for normal WordPress use. It is recommended as a periodic recovery test.

## Security and privacy boundaries

Portable exports never include plaintext API keys, API-key hashes, encrypted webhook secrets, delivery signatures, authentication tokens, or provider credentials. Public-registry exports exclude private workspaces, private graph objects, internal editorial comments, and nonpublic planning records. Administrative scopes may contain sensitive research and institutional history and should be access-controlled.
