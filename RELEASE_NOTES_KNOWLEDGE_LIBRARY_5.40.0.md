# Release Notes — Knowledge Library v5.40.0

**Scientific Embedding Governance & Compute Handoff**

This release formalizes semantic embeddings as reproducible analytical representations. Backend v2.51.0 adds specification fingerprints, representation lineage, execution provenance, deterministic backfill planning and a durable Workspace compute-handoff protocol while preserving the v5.39.0.1 Knowledge Landscape semantic-availability repair and all v5.39 cross-runtime reproducibility contracts.

The default deployment remains backward compatible: `SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=local`. No legacy vector is deleted and no backfill is automatically launched during upgrade.
