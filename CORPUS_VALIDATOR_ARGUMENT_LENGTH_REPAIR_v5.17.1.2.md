# Knowledge Library v5.17.1.2 — Corpus Validator Argument-Length Repair

This release is a narrow release-engineering repair on top of v5.17.1.1.

## Problem

The v5.17.1.1 production backend deployed successfully, but its Contabo validator stored the live 250-publication corpus JSON in a shell variable and passed that full JSON value to `python3 -c` as a command-line argument. On production this exceeded Linux `ARG_MAX` and stopped the validator at `=== CORPUS SUMMARY ===` with `Argument list too long`.

## Repair

v5.17.1.2 / backend v2.28.3 writes HTTP response bodies to temporary JSON files and parses them through Python standard input. Large response bodies are never supplied through `argv`.

The repair covers:

- backend health response;
- knowledge-map readiness;
- 250-publication fallback corpus;
- canonical Publication Library manifest-filter response;
- single-publication drill-down;
- publication-visualization readiness;
- Platform Core readiness; and
- hybrid-search regression validation.

## Preserved corpus behavior

No research or visualization semantics change in this patch. v5.17.1.1 behavior remains authoritative:

- WordPress passes the canonical Publication Library record manifest to Python;
- manifest mode is `publication-library-manifest`;
- direct backend fallback is `wordpress-post-fallback`;
- fallback object type is `post`;
- pages and custom document types remain excluded from fallback analysis;
- semantic edges still require real current embeddings;
- no LLM-inferred edges, guessed citations, or automatic truth promotion are introduced.

## Expected production result

The installer should continue beyond `=== CORPUS SUMMARY ===`, complete manifest, drill-down, visualization, Core, and search checks, and terminate with:

`PASS: Library backend v2.28.3 Corpus Validator Argument-Length Repair deployed.`
