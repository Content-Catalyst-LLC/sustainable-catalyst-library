# Deploy Library backend v1.9.0 to Contabo

No database migration or new credential is required.

From macOS, copy the generated backend ZIP and upgrader to the VPS, then run the upgrader on the VPS. The script preserves the existing `.env`, creates application/environment backups, rebuilds `sc-library-backend`, waits for health, verifies backend version `1.9.0`, and checks the biomedical evidence graph reliability manifest.

Expected new capabilities include `evidence_graph_reliability`, `graph_provenance_ledger`, `graph_content_fingerprint`, and `graph_partial_failure_containment`.
