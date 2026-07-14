# Citation and Research Source Manager Setup — v2.5.0

## Installation

Run the included upgrade installer. It generates a WordPress plugin ZIP in `~/Downloads`.

Upload:

```text
sustainable-catalyst-library-v2.5.0.zip
```

Choose **Replace current with uploaded**.

## First source

1. Open **SC Library → Research Sources → Add Source**.
2. Enter the source title in the WordPress title field.
3. Add authors using:

```text
Family name | Given names | Suffix | ORCID
```

Example:

```text
Ahmad | Tariq
Smith | Jane M. | | 0000-0000-0000-0000
```

4. Select a Source Type.
5. Enter the publication year and relevant journal, publisher, DOI, ISBN, URL, pages, or access date.
6. Add an abstract in the Excerpt field.
7. Add a public description in the Editor when needed.
8. Attach a source file from the Media Library.
9. Mark metadata verified only after comparing it with the source.
10. Save the source and review the generated Harvard citation.

## First research project

1. Open **SC Library → Research Projects → Add New**.
2. Enter the project title and description.
3. Set a project code and research status.
4. Choose Private or Public bibliography visibility.
5. Select Source records.
6. Publish the project only when its bibliography may be displayed publicly.
7. Add the project bibliography to a page:

```text
[sc_research_bibliography project="project-slug"]
```

## Public source library

Use:

```text
[sc_source_library]
```

Optional examples:

```text
[sc_source_library type="journal-article"]
[sc_source_library topic="planetary-boundaries" limit="30"]
[sc_source_library showform="false"]
```

## Inline citations

Reference-list entry:

```text
[sc_source_citation id="123" mode="reference"]
```

In-text citation:

```text
[sc_source_citation id="123" mode="in-text"]
```

Page-specific citation:

```text
[sc_source_citation id="123" mode="in-text" locator="44"]
```

## Public/private boundary

Public API and public pages include published bibliographic metadata, citations, identifiers, source status, and public relationships.

Private research notes, duplicate-review records, and field-level provenance are returned only to users who can edit the Source record.
