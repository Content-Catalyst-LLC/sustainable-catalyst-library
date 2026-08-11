# Sustainable Catalyst Library v4.3.16

## Pathway-Aware Research Guidance

v4.3.16 connects the Research Librarian to the Library's existing Knowledge Pathway architecture. The Librarian still begins with indexed Sustainable Catalyst records and Knowledge Graph relationships, but it can now identify a curated pathway through a subject and expose the first ordered steps rather than returning an unstructured result list alone.

## What changed

- Adds `pathways` as an additive field in the existing orchestration response contract.
- Ranks public Knowledge Pathways from the research question and compatible selected-record node keys.
- Returns title, URL, summary, level, step count, estimated minutes, recommendation score/reasons, and up to five ordered steps.
- Front-door mode shows the strongest pathway immediately after recommended starting records.
- Full Research Librarian mode shows up to four relevant pathways before applied-tool routing and Workspace actions.
- Deterministic guidance names the strongest curated pathway when one is available.
- Optional remote synthesis receives only the already-bounded pathway packet and remains unable to create actions.

## Boundaries preserved

- No automatic Workspace mutations.
- No automatic publishing or editorial approval.
- No replacement of direct Library search.
- No pathway recommendation when no public pathway earns a match.
- v4.3.15 Search ↔ Research Librarian bridge remains opt-in.
- v4.3.13 Field Spotlight and v4.3.12 supporting-article persistence remain unchanged.

## Page artifact

`RESEARCH_LIBRARY_PAGE_v4.3.16.html` carries forward the user's merged v4.3.15 editorial copy and updates the Research Librarian language to explain pathway-aware guidance.
