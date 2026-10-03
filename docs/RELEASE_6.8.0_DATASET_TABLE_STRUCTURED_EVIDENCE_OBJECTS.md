# Sustainable Catalyst Knowledge Library v6.8.0

## Dataset, Table & Structured Evidence Objects

v6.8.0 promotes the Library's existing research-corpus and dataset-export lineage into first-class dataset, table, column, cell, and structured-evidence contracts.

### Generations

- Library: 6.8.0
- Python backend: 3.8.0
- Independent Web: 2.8.0
- Python SDK / JavaScript client: 1.8.0
- Optional WordPress adapter: 6.8.0

### Architecture

The release is a composition layer over existing Library authority. `research_corpus_builder.py` continues to supply deterministic corpus selection, rows, and row provenance. v6.8 does not introduce a second dataset persistence service and requires no database migration.

### New contracts

- `sc-library-dataset-object/1.0`
- `sc-library-table-object/1.0`
- `sc-library-table-column/1.0`
- `sc-library-table-cell/1.0`
- `sc-library-structured-evidence-object/1.0`
- `sc-library-structured-evidence-validation/1.0`

### API

- `GET /api/library/v1/structured-evidence`
- `GET /api/library/v1/structured-evidence/readiness`
- `GET /api/library/v1/structured-evidence/schemas`
- `POST /api/library/v1/structured-evidence/datasets`
- `POST /api/library/v1/structured-evidence/tables`
- `POST /api/library/v1/structured-evidence/objects`
- `POST /api/library/v1/structured-evidence/validate`

### Guardrails

Dataset shape, row ordering, table position, numeric precision, missing values, column types, and evidence annotations are descriptive structure. They do not establish truth, certainty, causality, scientific validity, semantic equivalence, or evidence strength. Unit labels are preserved but do not perform unit conversion. Structured-evidence annotations must be explicit; the service does not infer evidentiary meaning.

### Web surface

The independent Library Web app can create a non-persistent structured-dataset preview from the browser working set. The preview is informational and does not save, promote, or reinterpret records.

### Packaging

The release produces separate backend, Web, full-source, Python SDK, JavaScript SDK, and WordPress-installable ZIP artifacts. The WordPress artifact is rooted at `sustainable-catalyst-library/` and contains the plugin bootstrap at the path expected by WordPress.

### Next release

v6.9.0 — Scientific Literature Intelligence.
