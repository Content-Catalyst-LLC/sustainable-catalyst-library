# Knowledge Library Backend Architecture

## Responsibility boundary

The Knowledge Library retrieves, preserves, structures, indexes, and processes knowledge. Platform Core remains the governed object/meaning layer for claims, findings, evidence relationships, reproducibility, model objects, and cross-product exchange.

## Current runtime topology

```text
WordPress Library UI
        |
        v
Library FastAPI Backend
        |
        +-- Research / Search APIs
        +-- Source Federation APIs
        +-- Corpus / Linguistic APIs
        +-- Semantic / Graph APIs
        +-- Preservation APIs
        +-- Durable Job API
                |
                v
        Execution Coordination
        PostgreSQL + Redis
                |
        +-------+-------+
        |       |       |
      Python    Go     Rust
        |       |       |
        +-------+-------+
                |
                v
      PostgreSQL / pgvector
      research artifact stores
                |
                v
          Platform Core
```

## Authority rules

1. **FastAPI/Python** owns Library orchestration and research semantics.
2. **PostgreSQL** is authoritative for durable metadata, provenance, and research-job state.
3. **Redis** is dispatch/wake-up coordination only and may be rebuilt without losing authoritative job state.
4. **Go** handles ingestion/network-oriented runtime work.
5. **Rust** is used selectively for deterministic CPU-heavy graph/data operations.
6. **Workspace** remains the destination for generic heavy/GPU computational execution rather than the Library becoming a general compute platform.
7. **Platform Core** defines governed research objects and cross-product exchange semantics.

## Release-artifact policy

Generated validation logs, release manifests, checksums, installer scripts, deployment notes, release-specific README files, and historical page snapshots belong in release bundles and Git tags. They are not living source-tree documentation and should not accumulate at the repository root.
