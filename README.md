# Sustainable Catalyst Library v1.8.0

Library v1.8.0 adds the **Foundations and Documentation Library** to the Sustainable Catalyst knowledge base.

The release keeps WordPress pages and repository records as canonical sources while presenting a compact institutional documentation interface on the Foundations page. PDFs become versioned snapshots inside a living documentation system rather than isolated cards or substitutes for current webpages.

## Included

- Curated **Foundations Documentation Library** collection
- Hierarchical Documentation Categories
- Documentation statuses: Living, Current, PDF Snapshot, Draft, Superseded, and Archived
- Explicit authoritative-source designation
- Current webpage, repository, methodology, release, PDF, and archive authority types
- Version, responsible area, last-reviewed date, and review interval fields
- Supersedes, superseded-by, dependency, and related-document records
- Featured living documentation
- Expandable public document panels
- Search across indexed titles, descriptions, metadata, keywords, and document text
- Category, status, responsible-area, archive, and sort filters
- Open webpage, full Library record, PDF, repository, release, history, and correction actions
- Authority warnings for dated PDF snapshots, drafts, superseded documents, archives, and repository-governed technical behavior
- Documentation authority diagnostics in WordPress administration
- `documentation`, `documentation/categories`, `documentation/statuses`, `documentation/{id}`, and `collections/foundations` REST endpoints
- Extended relationship types for documents, implementation, governance, dependencies, snapshots, methodology, policy, and releases

## Source-of-truth model

| Record purpose | Preferred authority |
|---|---|
| Institution and product descriptions | Current public webpage |
| Technical behavior | Repository documentation |
| Methodology and boundaries | Current methodology page |
| Release state | Repository release record |
| Brand or policy snapshot | Published PDF |
| Historical brief | Archived PDF |

## WordPress installation

Upload `sustainable-catalyst-library-v1.8.0.zip`, replace the existing plugin, activate it, open **SC Library**, enable the Foundations Documentation Library, confirm the main Library URL, save settings, and rebuild the Library index.

For each documentation record:

1. Assign **Foundations Documentation Library** under Library Collections.
2. Assign one or more Documentation Categories.
3. Complete the Documentation Authority panel.
4. Identify the authoritative source, version, responsible area, and review date.
5. Mark PDF files as snapshots when a living webpage or repository remains authoritative.

## Shortcodes

Recommended Foundations embed:

```text
[sc_library collection="foundations" mode="documentation"]
```

Convenience alias:

```text
[sc_foundations_library mode="public"]
```

Compact embed without a duplicate heading:

```text
[sc_library collection="foundations" mode="documentation" show_header="false"]
```

The main Research Library shortcode remains:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/documentation`
- `/wp-json/sustainable-catalyst/v1/library/documentation/categories`
- `/wp-json/sustainable-catalyst/v1/library/documentation/statuses`
- `/wp-json/sustainable-catalyst/v1/library/documentation/{id}`
- `/wp-json/sustainable-catalyst/v1/library/collections/foundations`

## Architectural boundary

The Foundations embed is a curated view of canonical Library records. It does not create a second documentation database, duplicate the main Library, or embed an iframe. The full Library retains relationships, sources, annotations, Notebook use, Translation Matrices, Whiteboards, Chalkboards, connected tools, book inclusion, and history.
