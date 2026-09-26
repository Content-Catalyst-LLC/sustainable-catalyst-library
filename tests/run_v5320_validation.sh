#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.32.0 / backend v2.43.0 / Rust graph runtime 0.1.0 validation ==="
grep -q 'Version: 5.32.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.32.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.43.0"' library-backend/app/__init__.py
grep -q 'sc-library-native-graph-runtime/1.0' library-backend/app/native_graph_runtime.py
grep -q 'sc-library-native-graph-runtime/1.0' library-backend/native-graph-runtime/src/main.rs
grep -q 'FROM rust:1.90-slim-bookworm AS rust-builder' library-backend/Dockerfile
grep -q '@app.get("/v1/runtime/native-graph/status")' library-backend/app/main.py
grep -q '@app.post("/v1/runtime/native-graph/pathfind")' library-backend/app/main.py
grep -q '/backend/native-graph-runtime-status' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_native_graph_runtime'" sustainable-catalyst-library/includes/class-sc-library-native-graph-runtime.php
if command -v cargo >/dev/null 2>&1; then
  cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
else
  echo "INFO: cargo unavailable locally; native compile is mandatory in production Docker build."
fi
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_native_graph_runtime_v2430.py \
  tests/test_rust_research_graph_runtime_foundation_v5320.py \
  library-backend/tests/test_research_gap_novelty_v2420.py \
  library-backend/tests/test_methodology_intelligence_v2410.py \
  library-backend/tests/test_temporal_knowledge_v2400.py \
  library-backend/tests/test_retrieval_evaluation_v2390.py \
  library-backend/tests/test_source_identity_resolution_v2380.py \
  library-backend/tests/test_scientific_document_intelligence_v2370.py \
  library-backend/tests/test_research_graph_pathfinding_v2360.py \
  library-backend/tests/test_evidence_synthesis_v2350.py \
  tests/test_research_gap_novelty_v5310.py::test_guardrails_are_literal_release_contracts
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-native-graph-runtime.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-native-graph-runtime-v5320.js
bash -n install_and_push_sustainable_catalyst_library_v5_32_0_macos.sh
bash -n upgrade_library_backend_v2_43_0_contabo.sh
python3 - <<'PYCHECK'
import json
for p in ('docs/schemas/native-graph-runtime-status.json','docs/schemas/native-graph-runtime-path-request.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.32 JSON schemas parse')
PYCHECK
echo "PASS: Knowledge Library v5.32.0 Rust Research Graph Runtime Foundation validation complete"
