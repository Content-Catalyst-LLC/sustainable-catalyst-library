#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.36.0.1 / backend v2.47.1 / Go ingestion runtime 0.1.0 / Rust graph runtime 0.2.0 validation ==="
grep -q 'Version: 5.36.0.1' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.36.0.1');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.47.1"' library-backend/app/__init__.py
grep -q 'version  = "0.1.0"' library-backend/go-ingestion-runtime/main.go
grep -q 'contract = "sc-library-go-ingestion-runtime/1.0"' library-backend/go-ingestion-runtime/main.go
grep -q 'version = "0.2.0"' library-backend/native-graph-runtime/Cargo.toml
grep -q '@app.get("/v1/runtime/ingestion-fabric/status")' library-backend/app/main.py
grep -q '@app.post("/v1/ingestion-jobs")' library-backend/app/main.py
grep -q '/backend/ingestion-fabric-status' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_ingestion_job_fabric'" sustainable-catalyst-library/includes/class-sc-library-ingestion-job-fabric.php
test ! -d library-backend/native-graph-runtime/target
test ! -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: go unavailable locally; Go compilation remains mandatory in production Docker build."
fi
if command -v cargo >/dev/null 2>&1; then
  CARGO_TARGET_DIR_TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v53601-cargo.XXXXXX")"
  trap 'rm -rf "$CARGO_TARGET_DIR_TMP"' EXIT
  CARGO_TARGET_DIR="$CARGO_TARGET_DIR_TMP" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TARGET_DIR_TMP"; trap - EXIT
else
  echo "INFO: cargo unavailable locally; Rust compilation remains mandatory in production Docker build."
fi
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_ingestion_job_fabric_v2471.py \
  tests/test_go_research_ingestion_job_fabric_v53601.py \
  library-backend/tests/test_native_graph_query_v2460.py \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_backend_query_contract_routes_and_capabilities \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_python_retains_policy_and_guardrails \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_wordpress_proxy_and_console \
  tests/test_rust_evidence_graph_native_query_v5350.py::test_corpus_surface_and_rust_target_cleanup \
  library-backend/tests/test_living_evidence_v2450.py \
  tests/test_living_evidence_research_evolution_v5340.py::test_api_wordpress_and_corpus_surfaces \
  tests/test_living_evidence_research_evolution_v5340.py::test_guardrails_and_rust_continuity \
  library-backend/tests/test_literature_review_v2440.py \
  tests/test_reproducible_literature_review_v5330.py::test_api_and_wordpress_surfaces \
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
php -l sustainable-catalyst-library/includes/class-sc-library-ingestion-job-fabric.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-ingestion-fabric-v53601.js
python3 - <<'PY'
import json
json.load(open('docs/schemas/go-ingestion-job.json',encoding='utf-8'))
print('PASS: v5.36 JSON schema parses')
PY
echo "PASS: Knowledge Library v5.36.0.1 Go Research Ingestion & Job Fabric validation complete"
