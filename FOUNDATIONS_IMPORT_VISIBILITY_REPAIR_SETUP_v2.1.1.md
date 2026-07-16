# Sustainable Catalyst Foundations v2.1.1

## First Edition Import Admin Visibility Repair

Foundations v2.1.0 registered its importer beneath:

`edit.php?post_type=sc_foundation_doc`

The Knowledge Library registers Foundation Documents beneath its own
`sc-library` admin menu. Because the importer used a different parent slug, the
screen existed but no visible menu item led to it.

v2.1.1 repairs the admin navigation without changing the 13 authored documents.

### Import locations

Primary:

**WordPress Admin → Knowledge Library → First Edition Import**

Fallback:

**WordPress Admin → Tools → Foundations First Edition**

Administrators also receive a dismissible-style information notice with an
**Open First Edition Import** button until the import succeeds.

### Direct URL

`/wp-admin/admin.php?page=sc-foundations-first-edition`

### Import behavior

- Safe default: all 13 records are created as WordPress drafts.
- Optional: publish immediately with metadata status `Under Review`.
- Re-running the importer updates records by stable document ID.
- The complete v2.1.0 authored collection and PDFs are unchanged.
