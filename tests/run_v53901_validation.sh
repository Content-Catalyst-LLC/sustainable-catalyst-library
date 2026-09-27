#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.39.0.1 / backend v2.50.1 / Knowledge Landscape Desktop Layout & Semantic Availability Repair validation ==="
grep -q 'Version: 5.39.0.1' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.39.0.1');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.50.1"' library-backend/app/__init__.py
grep -q "public const VERSION = '5.39.0.1';" sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php
grep -q 'data-sc-kl-semantic-notice' sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php
grep -q '@media(min-width:1101px)' sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5270.css
grep -q 'missing_current_embedding_count' library-backend/app/publication_corpus_maps.py
grep -q 'current_indexed_records' library-backend/app/semantic.py
grep -q 'version  = "0.1.0"' library-backend/go-ingestion-runtime/main.go
grep -q 'version = "0.2.0"' library-backend/native-graph-runtime/Cargo.toml
test ! -d library-backend/native-graph-runtime/target
test ! -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: go unavailable locally; Go compilation remains mandatory in production Docker build."
fi
if command -v cargo >/dev/null 2>&1; then
  CARGO_TARGET_DIR_TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v53901-cargo.XXXXXX")"
  trap 'rm -rf "$CARGO_TARGET_DIR_TMP"' EXIT
  CARGO_TARGET_DIR="$CARGO_TARGET_DIR_TMP" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TARGET_DIR_TMP"; trap - EXIT
else
  echo "INFO: cargo unavailable locally; Rust compilation remains mandatory in production Docker build."
fi
PYTHONPATH=library-backend pytest -q \
  tests/test_knowledge_landscape_desktop_semantic_repair_v53901.py \
  library-backend/tests/test_execution_lineage_v2501.py \
  tests/test_cross_runtime_reproducibility_lineage_v5390.py::test_lineage_routes_contracts_and_capabilities \
  tests/test_cross_runtime_reproducibility_lineage_v5390.py::test_guardrails_are_explicit \
  tests/test_cross_runtime_reproducibility_lineage_v5390.py::test_wordpress_console_proxy_and_publication_surface \
  tests/test_cross_runtime_reproducibility_lineage_v5390.py::test_schema_files_parse_and_sources_remain_clean \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_contract_fingerprint_ignores_transient_availability \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_auto_routes_primary_runtime \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_incompatible_explicit_runtime_is_rejected \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_graph_fallback_is_explicit \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_graph_without_fallback_reports_not_ready \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_execute_python_corpus_returns_common_envelope \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_execute_go_returns_accepted_envelope \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_execute_rust_requires_actual_rust_execution \
  library-backend/tests/test_unified_runtime_contract_v2490.py::test_execute_rust_rejects_hidden_native_fallback \
  tests/test_unified_research_runtime_contract_v5380.py::test_contract_routes_and_capabilities \
  tests/test_unified_research_runtime_contract_v5380.py::test_runtime_boundary_guardrails \
  tests/test_unified_research_runtime_contract_v5380.py::test_wordpress_proxy_console_and_publication_surface \
  tests/test_unified_research_runtime_contract_v5380.py::test_runtime_sources_remain_clean \
  library-backend/tests/test_research_corpus_builder_v2480.py \
  tests/test_research_corpus_builder_dataset_export_v5370.py::test_backend_routes_capabilities_and_contracts \
  tests/test_research_corpus_builder_dataset_export_v5370.py::test_guardrails_preserve_library_core_boundary \
  tests/test_research_corpus_builder_dataset_export_v5370.py::test_wordpress_proxy_console_and_publication_surface \
  tests/test_research_corpus_builder_dataset_export_v5370.py::test_schema_files_and_build_artifact_cleanup \
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
php -l sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-unified-runtime.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-execution-lineage.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js
node --check sustainable-catalyst-library/assets/js/sc-library-unified-runtime-v5380.js
node --check sustainable-catalyst-library/assets/js/sc-library-execution-lineage-v5390.js
python3 - <<'PY'
import json
for p in [
 'docs/schemas/research-runtime-contract.json','docs/schemas/runtime-execution-envelope.json',
 'docs/schemas/execution-environment.json','docs/schemas/execution-lineage.json',
 'docs/schemas/reproducibility-record.json','docs/schemas/runtime-verification.json']:
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.39.0.1 JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.39.0.1 Knowledge Landscape Desktop Layout & Semantic Availability Repair validation complete"
