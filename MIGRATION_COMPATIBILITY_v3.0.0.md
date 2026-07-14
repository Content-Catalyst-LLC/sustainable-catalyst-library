# v3.0.0 Migration and Compatibility

## Baseline

The installer requires:

```text
Knowledge Library v2.7.0
```

A safe v3.0.0 rerun is also supported.

## Non-destructive migration

The release does not replace existing Research Projects or Research Sources.

For each project lacking the augmented Source registry:

1. Read the retained project Source ID list.
2. Create default Source entries.
3. Assign Background role.
4. Assign Core Sources section.
5. Mark existing project Sources Included.
6. Preserve the retained project and Source relationship IDs.
7. Calculate initial workspace health.

The migration processes up to 25 projects per WordPress request until complete.

## Existing bibliography shortcode

The retained shortcode remains available:

```text
[sc_research_bibliography]
```

New v3.0.0 shortcodes provide sections, roles, annotations, and connected packets.

## Existing REST API

Existing Source, citation, project bibliography, connector, holdings, evidence, and claim routes are not removed.

## Source Discovery

The v3.0.0 patch adds a project ID to the existing connector client. Imports without a project continue to behave as before.

## Storage

Project Source entries, snapshots, activity, team records, and document IDs are stored in project postmeta.

Large institutional projects may eventually benefit from dedicated relational tables. v3.0.0 keeps the current WordPress-native architecture for compatibility and transparent administration.

## Rollback

The installer creates a repository backup before applying changes.

Rolling back the plugin code does not delete v3.0.0 metadata. Older releases ignore the additional project fields and continue using the retained project Source ID list.
