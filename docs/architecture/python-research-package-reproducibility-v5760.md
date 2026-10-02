# Knowledge Library v5.76.0 — Python Research Package & Reproducibility Service

Python/FastAPI is the authoritative service boundary for reproducibility packages. The service composes existing Library systems rather than creating parallel storage or execution infrastructure.

- Existing PostgreSQL state remains authoritative for records, jobs, pipelines, and artifact metadata.
- Existing content-addressed artifact storage remains authoritative for package bytes.
- Package assembly does not rerun research automatically.
- Verification does not replay runtime workloads unless explicitly requested.
- Artifact integrity and matching fingerprints do not prove research truth, source validity, or scientific equivalence.
- WordPress is presentation/API-client compatibility only.
- No automatic Platform Core promotion occurs.
