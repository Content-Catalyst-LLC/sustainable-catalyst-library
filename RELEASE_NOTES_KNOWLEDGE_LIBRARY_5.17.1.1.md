# Knowledge Library v5.17.1.1 — Publication Corpus Scope & Deployment Repair

Backend: **v2.28.2**

This is a narrow corrective release for v5.17.1.

## Canonical Publication Library corpus

`[sc_library_knowledge_landscape]` now obtains its default corpus manifest directly from the publications resolved by `SC_Library_Publications`. The manifest contains the canonical WordPress record IDs currently surfaced through the Publications interface and is passed to the Python corpus endpoint.

This prevents unrelated indexed WordPress content—including pages, support content, Foundation documents, and other custom post types—from being silently classified as publications.

If the backend corpus endpoint is called without a canonical manifest, it uses a conservative `object_type=post` fallback rather than all public `wordpress-main` records.

## Deployment hardening

The Contabo installer no longer pipes large pretty-printed corpus JSON through `head` under `set -o pipefail`. Production verification now prints a bounded corpus summary and continues through the manifest-filter, single-publication, visualization, Platform Core, and hybrid-search checks before emitting the final PASS.

## Research integrity

This patch does not change relationship semantics. Citations remain explicit, concept associations remain human-reviewed, co-occurrence remains analytical rather than causal, and semantic similarity is emitted only from current real embeddings.
