# Library v1.13.3 Portable Data Setup

Library v1.13.3 preserves all earlier export formats and upgrades the portable schema to `sc-library-portable-export/1.3`.

## New planning fields

The normalized `plans` table now includes:

- Release group and release track
- Milestone and capacity owner
- Estimated and actual effort
- Progress percentage
- Planned and actual start dates
- Dependency policy
- Manual blocker state and blocker note
- Actual publication date

A new normalized `plan_dependencies` table stores each dependency as an individual relation.

## Install

1. Upload `sustainable-catalyst-library-v1.13.3.zip`.
2. Replace the existing plugin.
3. Rebuild the Library index.
4. Open **SC Library → Portable Data Export**.

## Recommended validation

1. Export **Schema only** as PostgreSQL SQL.
2. Confirm the `plans` and `plan_dependencies` tables are present.
3. Export the **Content Planner and roadmap** scope.
4. Restore into a disposable PostgreSQL database before using the export operationally.

A PostgreSQL restore is optional for normal WordPress use.


## Persistent account workspaces and document editions in v1.13.0

Administrator server exports can now include normalized `account_workspaces`, `account_workspace_revisions`, `account_workspace_collaborators`, and `account_workspace_sync_log` entities. These exports contain user-authored research and should be handled as private data. Public registry exports exclude them.


## Server document production records

Portable exports can include normalized document-job and frozen-edition manifests. PDF binary files are not embedded; WordPress attachment URLs and SHA-256 checksums are preserved.

## v1.14.0 multimedia export addendum

Portable export schema `sc-library-portable-export/1.4` adds normalized `media_assets`, `media_clips`, `media_reels`, and `media_jobs` entities. The multimedia scope exports rights, provenance, transcripts, clip boundaries, reel order, output references, and diagnostics without embedding media binaries.

## v1.15.0 editorial workflow entities

Portable export schema `sc-library-portable-export/1.5` adds `editorial_reviews`, `editorial_participants`, `editorial_comments`, `editorial_suggestions`, and `editorial_events`. Use the Editorial export scope for a focused review archive. These records can include private comments, email addresses, invitation state, and editorial decisions.
