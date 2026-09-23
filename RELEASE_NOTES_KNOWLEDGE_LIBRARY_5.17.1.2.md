# Sustainable Catalyst Knowledge Library v5.17.1.2

**Release:** Corpus Validator Argument-Length Repair  
**Backend:** 2.28.3

v5.17.1.2 is a narrow repair release. It preserves the canonical Publication Library corpus integration introduced in v5.17.1.1 and corrects production deployment validation for large corpus payloads.

## Changed

- Bumped WordPress release identity to 5.17.1.2.
- Bumped Python backend identity to 2.28.3.
- Replaced large JSON command-line arguments in the Contabo validator with temporary response files and stdin parsing.
- Retained compact corpus summaries without truncating response pipelines.
- Added regression tests that reject `$corpus` argv parsing and require file/stdin-backed corpus validation.

## Unchanged

- Canonical Publication Library manifest scoping.
- Safe `post` fallback for direct backend corpus calls.
- Corpus graph schema and visualization renderer contract.
- Citation, concept, topic, semantic, provenance, Platform Core, and research-integrity boundaries.
