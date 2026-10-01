# Python Catalog, Publication & Research Object Service — v5.70.0

Knowledge Library v5.70.0 cuts publication/catalog mutation authority over to the Python/FastAPI Library service. Existing PostgreSQL `library_records`, `library_record_versions`, `library_record_chunks`, sources, embedding jobs, and downstream lineage remain the single storage/runtime path. No duplicate catalog database is introduced.

## Authority

Python owns validation, normalization, catalog writes, research-object envelopes, record revisioning, publication state, and deletion through signed API v1 routes. WordPress remains a presentation, routing, SEO, lifecycle, and compatibility client. The legacy PHP publication/indexing classes remain retire-candidates until their UI/compatibility responsibilities are separately replaced.

## Safety

Upserts reuse the existing ingest/revision path, so content hashes, revision history, chunk replacement, stale Platform Core bindings, superseded extraction candidates, and embedding re-indexing continue to behave consistently. Normalization trims/deduplicates structured string arrays and adds authority lineage metadata; it does not infer truth, alter source meaning, or auto-promote objects into Platform Core.
