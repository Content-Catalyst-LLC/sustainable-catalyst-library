# Research Librarian Handoff Guide — v3.7.0

## Document context filter

```text
sc_library_research_librarian_document_context
```

Arguments:

```text
existing context
document ID
options
```

Result section:

```text
document_intelligence
```

The bundle can include:

- public or private intelligence profile;
- section index;
- title aliases;
- recurring terms;
- generation time.

## Project context filter

```text
sc_library_research_librarian_project_context
```

The project bundle can include condensed profiles for up to 20 linked documents.

Each condensed record contains:

- document ID;
- title;
- summary;
- up to five key points;
- up to fifteen terms;
- gap signals;
- up to five suggested questions.

## Privacy

Private context requires document edit permission.

Public context omits:

- source hash;
- raw chunks;
- raw section text;
- private aliases;
- internal job state;
- analyzer errors;
- editor identities.

## Recommended Research Librarian behavior

1. resolve a likely title;
2. retrieve the matching intelligence profile;
3. identify the original document;
4. use the summary only as navigation context;
5. cite or link the original document;
6. expose uncertainty and gap signals;
7. suggest related titles or Concepts;
8. avoid presenting generated fields as quotations;
9. avoid claiming the analyzer read content outside the indexed source;
10. route detailed evidence questions to Source and Evidence records.

## Fallback

When the intelligence profile is unavailable or stale, Research Librarian should fall back to the document title, excerpt, original text, Topics, and existing Library retrieval.
