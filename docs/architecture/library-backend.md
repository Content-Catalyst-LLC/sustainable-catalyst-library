# Knowledge Library Backend Architecture

```text
WordPress Library UI
        |
        v
Library FastAPI API
        |
        v
PostgreSQL Job / Worker Authority <--> Redis dispatch wakeups
        |
        +--> Python Research Worker
        +--> Go Ingestion Handoff Worker --> Go runtime
        +--> Rust Graph Worker -----------> native Rust runtime
        +--> OCR / HTR / Speech profiles (standby)
        +--> Neural profile (standby)
        +--> Workspace profile (standby)
        |
        v
Platform Core governed research objects
```

Worker failures are isolated to the owning attempt and worker registration. Exhausted/permanent failures create durable dead-letter records. Quarantined workers cannot lease new work. PostgreSQL remains authoritative; Redis is never authoritative worker or job state.

## Artifact and object storage

Library v5.51.0 separates heavyweight research bytes from authoritative relational metadata. PostgreSQL stores artifact identity, SHA-256 integrity, provenance, derivation lineage, lifecycle state, and execution linkage. Artifact bytes live in a content-addressed object store.

The safe baseline backend is a shared filesystem volume mounted at `/data/artifacts`. An S3-compatible adapter uses the same deterministic `sha256/<prefix>/<digest>` key contract, so switching storage providers does not change artifact identity. S3-compatible configuration may target MinIO, Cloudflare R2, AWS S3, or another compatible provider.

Artifacts are immutable by content identity. Lifecycle tombstoning does not silently delete bytes. Physical retention and purge are separate governance concerns.
