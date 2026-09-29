# Knowledge Library v5.44.0 Release

**Title:** Global Source Federation Registry & Connector Contracts  
**Backend:** 2.55.0  
**Go runtime:** 0.1.0  
**Rust runtime:** 0.2.0

This release creates the canonical registry and connector-contract layer for the Library's global source ecosystem while reusing existing connector implementations and v4.8 federation transport.

Run `tests/run_v5440_validation.sh` before synchronization. Production deployment must build the Go and Rust runtimes in Docker and verify `/v1/global-source-federation/readiness` plus the safe/unsafe connector-validation probes.

No connector credentials are created or rotated by this release. No source query, embedding backfill, external embedding request, neural reranking request, or automatic translation is initiated by the installer.
