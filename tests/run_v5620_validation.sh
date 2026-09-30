#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.62.0 / backend v2.73.0 / WordPress Thin Adapter validation ==="
python3 - <<'PYTESTS'
import runpy
suite=[
 ('tests/test_wordpress_thin_adapter_v5620.py',set()),
 ('tests/test_library_identity_access_v5610.py',{'test_release_identity_and_versions'}),
 ('tests/test_independent_library_web_v5600.py',{'test_release_identity_and_artifacts','test_wordpress_is_adapter_not_app_runtime'}),
 ('tests/test_independent_library_api_v5590.py',{'test_release_identity_routes_and_plugin'}),
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
PYTESTS
PYTHONPATH=library-backend python3 - <<'PYBACKEND'
import importlib.util, runpy
argon2_available=importlib.util.find_spec('argon2') is not None
suite=[
 ('library-backend/tests/test_wordpress_thin_adapter_v2730.py',set()),
 ('library-backend/tests/test_identity_access_v2720.py',({'test_contract_identity_and_authority'} | (set() if argon2_available else {'test_password_hash_is_argon2id_and_verifies'}))),
 ('library-backend/tests/test_web_application_v2710.py',{'test_contract_identity','test_foundation_surfaces','test_readiness'}),
 ('library-backend/tests/test_independent_api_v2700.py',{'test_contract_identity'}),
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
print(f'PASS: {t} backend adapter/identity/web/api/runtime assertions')
if not argon2_available: print('INFO: argon2-cffi unavailable locally; production Docker verification remains the Argon2 execution gate')
PYBACKEND
python3 -m py_compile library-backend/app/wordpress_thin_adapter.py library-backend/app/identity_access.py library-backend/app/web_application.py library-backend/app/independent_api.py library-backend/app/main.py
python3 - <<'PYJSON'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('*.json'): json.loads(p.read_text())
json.loads(Path('docs/library-api-v1-openapi.json').read_text())
json.loads(Path('library-web/manifest.webmanifest').read_text())
print('PASS: JSON schemas, API v1 OpenAPI and web manifest parse')
PYJSON
PYTHONPATH=library-backend python3 - <<'PYCONTRACT'
from app.wordpress_thin_adapter import contract, readiness
from app.independent_api import service_contract
c=contract(); r=readiness(); a=service_contract()
assert c['adapter']['role']=='thin-adapter' and c['adapter']['authoritative'] is False
assert r['state']=='ready' and r['wordpress_dependency_count']==0
assert any(x['path']=='/api/library/v1/wordpress-adapter' for x in a['routes'])
assert 'wordpress-adapter' in a['capabilities']
print('PASS: backend-authoritative WordPress thin-adapter contract')
PYCONTRACT
python3 - <<'PYTOPOLOGY'
from pathlib import Path
adapter=Path('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php').read_text()
nginx=Path('library-web/nginx.conf').read_text(); compose=Path('library-web/compose.yml').read_text()
assert 'WP_REST_Server::READABLE' in adapter and "'methods' => WP_REST_Server::READABLE" in adapter
assert "'methods' => 'POST'" not in adapter and "'methods' => 'PUT'" not in adapter and "'methods' => 'DELETE'" not in adapter
assert 'sc-library-backend:8080' in nginx and 'wp-json' not in nginx.lower()
assert '${SC_LIBRARY_WEB_BIND_PORT:-8092}' in compose
print('PASS: WordPress adapter exposes read-only local status while independent Web bypasses WordPress')
PYTOPOLOGY
if command -v node >/dev/null 2>&1; then node --check library-web/assets/app.js; while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.js' -print0); echo 'PASS: JavaScript syntax'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
if command -v cargo >/dev/null 2>&1; then TMP="$(mktemp -d /tmp/sc-library-v5620-cargo.XXXXXX)"; CARGO_TARGET_DIR="$TMP" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked; rm -rf "$TMP"; else echo 'INFO: cargo unavailable; production Docker build remains the Rust gate'; fi
python3 - <<'PYCLEAN'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
print('PASS: clean repository root')
PYCLEAN
echo 'PASS: Knowledge Library v5.62.0 WordPress Thin Adapter validation complete'
