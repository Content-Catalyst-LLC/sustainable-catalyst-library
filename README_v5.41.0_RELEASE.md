# Sustainable Catalyst Knowledge Library v5.41.0

**Semantic Similarity & Representation Search**

This release upgrades the existing hybrid retrieval engine to consume v5.40 governed embedding representations. It adds specification-aware semantic text search, record-to-record semantic neighborhoods that work from stored vectors without live provider compute, representation lineage, stale/current filtering, and WordPress REST proxy surfaces.

Release pairing:

- Library plugin **5.41.0**
- Library backend **2.52.0**
- Go ingestion runtime **0.1.0**
- Rust graph runtime **0.2.0**

Run `tests/run_v5410_validation.sh` before packaging or pushing. Production deployment should use `upgrade_library_backend_v2_52_0_contabo.sh` and then install the WordPress v5.41.0 ZIP.

No embedding backfill or provider activation is performed automatically.
