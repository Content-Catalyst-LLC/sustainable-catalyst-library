#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.37.0 / backend v2.48.0 / Research Corpus Builder & Dataset Export validation ==="
grep -q 'Version: 5.37.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.37.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.48.0"' library-backend/app/__init__.py
grep -q 'version  = "0.1.0"' library-backend/go-ingestion-runtime/main.go
grep -q 'version = "0.2.0"' library-backend/native-graph-runtime/Cargo.toml
grep -q '@app.post("/v1/research-corpora/build")' library-backend/app/main.py
grep -q '@app.post("/v1/research-corpora/export")' library-backend/app/main.py
grep -q '@app.post("/v1/publication-knowledge-maps/research-corpus")' library-backend/app/main.py
grep -q '/backend/research-corpus-build' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_research_corpus_builder'" sustainable-catalyst-library/includes/class-sc-library-research-corpus-builder.php
test ! -d library-backend/native-graph-runtime/target
test ! -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: go unavailable locally; Go compilation remains mandatory in production Docker build."
fi
if command -v cargo >/dev/null 2>&1; then
  CARGO_TARGET_DIR_TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v5370-cargo.XXXXXX")"
  trap 'rm -rf "$CARGO_TARGET_DIR_TMP"' EXIT
  CARGO_TARGET_DIR="$CARGO_TARGET_DIR_TMP" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TARGET_DIR_TMP"; trap - EXIT
else
  echo "INFO: cargo unavailable locally; Rust compilation remains mandatory in production Docker build."
fi
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_research_corpus_builder_v2480.py \
  tests/test_research_corpus_builder_dataset_export_v5370.py \
  library-backend/tests/test_ingestion_job_fabric_v2471.py \
  tests/test_go_research_ingestion_job_fabric_v53601.py::test_go_fabric_operations_present \
  tests/test_go_research_ingestion_job_fabric_v53601.py::test_python_api_and_wordpress_surfaces \
  tests/test_go_research_ingestion_job_fabric_v53601.py::test_runtime_boundary_guardrails \
  library-backend/tests/test_native_graph_query_v2460.py \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_backend_query_contract_routes_and_capabilities \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_python_retains_policy_and_guardrails \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_corpus_surface_and_rust_target_cleanup \
  library-backend/tests/test_living_evidence_v2450.py \
  tests/test_living_evidence_research_evolution_v5340.py::test_guardrails_and_rust_continuity \
  library-backend/tests/test_literature_review_v2440.py \
  tests/test_reproducible_literature_review_v5330.py::test_guardrails_and_rust_continuity \
  library-backend/tests/test_native_graph_runtime_v2430.py \
  library-backend/tests/test_research_gap_novelty_v2420.py \
  library-backend/tests/test_methodology_intelligence_v2410.py \
  library-backend/tests/test_temporal_knowledge_v2400.py \
  library-backend/tests/test_retrieval_evaluation_v2390.py \
  library-backend/tests/test_source_identity_resolution_v2380.py \
  library-backend/tests/test_scientific_document_intelligence_v2370.py \
  library-backend/tests/test_research_graph_pathfinding_v2360.py \
  library-backend/tests/test_evidence_synthesis_v2350.py
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-research-corpus-builder.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-research-corpus-v5370.js
python3 - <<'PY'
import json
for p in ['docs/schemas/research-corpus.json','docs/schemas/dataset-export.json']:
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.37 JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.37.0 Research Corpus Builder & Dataset Export validation complete"
