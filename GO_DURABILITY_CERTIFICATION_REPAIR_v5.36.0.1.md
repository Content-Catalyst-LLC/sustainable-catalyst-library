# Knowledge Library v5.36.0.1 — Go Durability Status & Deployment Certification Repair

This hotfix aligns the Go ingestion runtime health contract with the state-file durability implementation already present in v5.36.0. Queue state persists at `/data/jobs.json` on the dedicated Docker volume. Running jobs recovered after coordinator restart return to `retry_wait`.

## Fixed
- Runtime health now reports `durability: state-file`.
- Backend identity is v2.47.1.
- WordPress plugin identity is v5.36.0.1.
- Production verification continues to require create → restart → read persistence before PASS.

No research semantics, job types, Go runtime version (0.1.0), Rust runtime version (0.2.0), or Platform Core governance are changed.
