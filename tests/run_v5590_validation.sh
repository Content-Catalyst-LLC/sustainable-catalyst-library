#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.59.0 / backend v2.70.0 / Independent Library API v1 & Service Contract validation ==="
python3 - <<'PY'
import runpy
suite=[
 ('tests/test_independent_library_api_v5590.py',set()),
 ('tests/test_library_runtime_authority_v5580.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_global_knowledge_federation_v5570.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_source_transparency_v5560.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_cross_civilizational_linking_v5550.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_translation_alignment_v5540.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_distributed_compute_broker_v5530.py',{'test_release_identity_routes_and_wordpress_surface'}),
 ('tests/test_checkpointed_pipeline_v5520.py',{'test_release_identity_routes_and_plugin'}),
 ('tests/test_research_artifact_storage_v5510.py',{'test_release_identity_and_routes'}),
 ('tests/test_specialized_worker_runtime_v5500.py',{'test_release_identity'}),
 ('tests/test_durable_research_job_queue_v5490.py',{'test_release_identity_and_routes','test_worker_fleet_is_explicitly_deferred_to_v550'}),
 ('tests/test_cross_language_entity_toponym_resolution_v5480.py',{'test_release_identity_routes_and_health_capabilities'}),
 ('tests/test_linguistic_corpus_concordance_kwic_v5470.py',{'test_release_identity_and_routes'}),
 ('tests/test_ocr_htr_transcription_lineage_v5460.py',{'test_release_identity_and_routes'}),
 ('tests/test_original_language_corpus_v5450.py',{'test_release_identity_and_surfaces'}),
 ('tests/test_global_source_federation_v5440.py',{'test_release_identity'}),
 ('tests/test_publication_embedding_maps_v5430.py',{'test_release_identity'}),
 ('tests/test_neural_reranking_v5420.py',{'test_release_identity'}),
 ('tests/test_semantic_similarity_representation_search_v5410.py',{'test_release_identity'}),
 ('tests/test_scientific_embedding_governance_v5400.py',{'test_release_identity'}),
 ('tests/test_embedding_backfill_timestamp_repair_v54001.py',{'test_release_identity'}),
]
t=0
for path,skip in suite:
 ns=runpy.run_path(path); n=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn) and name not in skip: fn(); n+=1; t+=1
 print(f'PASS: {path}: {n} assertions')
print(f'PASS: {t} mandatory Python release assertions')
PY
PYTHONPATH=library-backend python3 - <<'PY'
import runpy
suite=[
 ('library-backend/tests/test_independent_api_v2700.py',set()),
 ('library-backend/tests/test_runtime_authority_v2690.py',set()),
 ('library-backend/tests/test_global_knowledge_federation_v2680.py',set()),
 ('library-backend/tests/test_source_transparency_v2670.py',set()),
 ('library-backend/tests/test_cross_civilizational_linking_v2660.py',set()),
 ('library-backend/tests/test_translation_alignment_v2650.py',set()),
 ('library-backend/tests/test_distributed_compute_broker_v2640.py',set()),
 ('library-backend/tests/test_checkpointed_pipeline_v2630.py',set()),
 ('library-backend/tests/test_artifact_storage_v2620.py',set()),
 ('library-backend/tests/test_specialized_worker_runtime_v2610.py',{'test_catalog'}),
 ('library-backend/tests/test_durable_job_queue_v2600.py',{'test_worker_fleet_is_not_faked_by_foundation_release'}),
 ('library-backend/tests/test_cross_language_resolution_v2590.py',set()),
 ('library-backend/tests/test_linguistic_corpus_v2580.py',set()),
 ('library-backend/tests/test_ocr_htr_transcription_lineage_v2570.py',set()),
 ('library-backend/tests/test_original_language_corpus_v2560.py',set()),
]
t=0
for path,skip in suite:
 ns=runpy.run_path(path); n=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn) and name not in skip: fn(); n+=1; t+=1
 print(f'PASS: {path}: {n} assertions')
print(f'PASS: {t} backend api/runtime-authority/federation/transparency/linking/alignment/compute/pipeline/artifact/worker/execution/language-lineage assertions')
PY
python3 -m py_compile library-backend/app/independent_api.py library-backend/app/runtime_authority.py library-backend/app/main.py
python3 - <<'PY'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('*.json'): json.loads(p.read_text())
json.loads(Path('docs/library-api-v1-openapi.json').read_text())
print('PASS: JSON schemas and API v1 OpenAPI contract parse')
PY
python3 - <<'PY'
from pathlib import Path
text=Path('library-backend/compose.yml').read_text()
for service in ['library-backend','sc-library-redis','sc-library-ingestion','sc-library-worker-python','sc-library-worker-go','sc-library-worker-rust']:
 assert f'  {service}:\n' in text, service
assert 'sc-library-artifact-data:' in text and text.count('sc-library-artifact-data:/data/artifacts') >= 4
print('PASS: Compose topology and shared artifact volume')
PY
PYTHONPATH=library-backend python3 - <<'PY'
from app.independent_api import service_contract, guardrails
from app.runtime_authority import dependency_graph
c=service_contract(); g=guardrails(); d=dependency_graph()
assert c['api_version']=='1.0' and c['base_path']=='/api/library/v1' and c['state']=='stable'
assert g['api_v1_independent_of_wordpress'] and not g['wordpress_required_for_api_v1']
assert d['wordpress_dependency_count']==0
assert all(r['path'].startswith('/api/library/v1') for r in c['routes'])
print('PASS: independent Library API v1 is stable and WordPress-independent')
PY
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.js' -print0); echo 'PASS: WordPress JavaScript syntax'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
if command -v cargo >/dev/null 2>&1; then CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5590-cargo.XXXXXX)"; CARGO_TARGET_DIR="$CARGO_TMP_DIR" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked; rm -rf "$CARGO_TMP_DIR"; else echo 'INFO: cargo unavailable; production Docker build remains the Rust gate'; fi
python3 - <<'PY'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
print('PASS: clean repository root')
PY
echo 'PASS: Knowledge Library v5.59.0 Independent Library API v1 & Service Contract validation complete'
