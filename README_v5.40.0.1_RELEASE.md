# Sustainable Catalyst Knowledge Library v5.40.0.1

**Title:** Embedding Backfill Timestamp Repair  
**WordPress:** 5.40.0.1  
**Python backend:** 2.51.1  
**Go ingestion runtime:** 0.1.0  
**Rust graph runtime:** 0.2.0

This is a narrow repair for the v5.40.0 Scientific Embedding Governance & Compute Handoff release. It fixes the production HTTP 500 on signed embedding backfill dry-run/queue requests caused by an invalid `library_records.updated_at` reference.

The repair does not change the embedding object model, Workspace compute handoff, vector store, or governance boundaries.
