# Sustainable Catalyst Knowledge Library v6.6.0 — Research Graph & Evidence Navigation

## Release generations

- Library: **6.6.0**
- Python backend: **3.6.0**
- Independent Library Web: **2.6.0**
- First-party SDKs: **1.6.0**
- WordPress: optional adapter only

## Purpose

v6.6.0 makes the Library's existing provenance, citation and relationship graph directly navigable for research. It does **not** introduce another graph database or a competing evidence authority. The new service composes the existing Python provenance graph service, citation graph, `library_edges`, visibility rules and native graph runtime into a stable research-navigation contract.

## New API surface

- `GET /api/library/v1/research-graph`
- `GET /api/library/v1/research-graph/readiness`
- `GET /api/library/v1/research-graph/records/{record_id}/neighborhood`
- `GET /api/library/v1/research-graph/records/{record_id}/summary`
- `POST /api/library/v1/research-graph/path`

## Capabilities

- Public-record graph neighborhoods with bounded depth and size.
- Citation and relationship edge-family filtering.
- Deterministic evidence-path discovery between Library records.
- Graph summaries and degree-oriented navigation hints.
- Stable record links for the independent Library reader.
- Existing provenance and public-visibility filtering remain authoritative.

## Interpretation guardrails

Graph navigation is descriptive. Connectivity is not truth, causality or endorsement. A citation edge does not imply support. A relationship edge does not imply causality. A shorter path does not imply stronger evidence. The service does not promote evidence, truth claims or Platform Core objects automatically.

## Persistence

No new database migration is required. v6.6.0 reuses existing graph and provenance persistence.

## Next architecture release

**v6.7.0 — Living Collections & Research Projects**.
