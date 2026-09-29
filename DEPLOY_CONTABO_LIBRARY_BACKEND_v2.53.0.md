# Deploy Knowledge Library backend v2.53.0

From the Mac release directory, copy the backend archive and installer to Contabo, then run the installer with sudo. The installer rebuilds the Python/Rust backend and Go ingestion runtime, applies additive schema initialization, verifies v2.53.0 health, checks neural-reranking readiness and baseline fallback, confirms evaluation integration, and rechecks Python/Go/Rust runtime continuity.

The deployment does **not** require a neural reranking provider. `SC_LIBRARY_RERANK_PROVIDER=disabled` is the production-safe default.
