#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.60.0 / backend v2.71.0 / web v1.0.0 / Independent Library Web Application Foundation validation ==="
python3 - <<'PYTESTS'
import runpy
suite=[
 ('tests/test_independent_library_web_v5600.py',set()),
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
import runpy
suite=[
 ('library-backend/tests/test_web_application_v2710.py',set()),
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
print(f'PASS: {t} backend web/api/runtime-authority/federation/transparency/linking/alignment/compute/pipeline/artifact/worker/execution/language-lineage assertions')
PYBACKEND
python3 -m py_compile library-backend/app/web_application.py library-backend/app/independent_api.py library-backend/app/runtime_authority.py library-backend/app/main.py
python3 - <<'PYJSON'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('*.json'): json.loads(p.read_text())
json.loads(Path('docs/library-api-v1-openapi.json').read_text())
json.loads(Path('library-web/manifest.webmanifest').read_text())
print('PASS: JSON schemas, API v1 OpenAPI and web manifest parse')
PYJSON
python3 - <<'PYTOPOLOGY'
from pathlib import Path
backend=Path('library-backend/compose.yml').read_text(); web=Path('library-web/compose.yml').read_text(); nginx=Path('library-web/nginx.conf').read_text()
for service in ['library-backend','sc-library-redis','sc-library-ingestion','sc-library-worker-python','sc-library-worker-go','sc-library-worker-rust']:
 assert f'  {service}:\n' in backend, service
assert 'sc-library-web:' in web
assert 'sc-internal' in web and 'sc-library-backend:8080' in nginx
assert '/api/library/' in nginx and 'wp-json' not in nginx.lower()
print('PASS: independent web topology proxies directly to Library backend')
PYTOPOLOGY
PYTHONPATH=library-backend python3 - <<'PYCONTRACT'
from app.web_application import application_contract
from app.independent_api import service_contract
w=application_contract(); a=service_contract()
assert w['web_version']=='1.0.0' and w['wordpress']['required'] is False
assert w['api']['base_path']=='/api/library/v1'
assert a['api_version']=='1.0' and a['base_path']=='/api/library/v1'
assert any(r['path']=='/api/library/v1/web-application/readiness' for r in a['routes'])
print('PASS: web foundation is API-v1-native and WordPress-independent')
PYCONTRACT
if command -v node >/dev/null 2>&1; then node --check library-web/assets/app.js; while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.js' -print0); echo 'PASS: web and WordPress JavaScript syntax'; fi
if command -v nginx >/dev/null 2>&1; then TMPNG="$(mktemp -d /tmp/sc-library-v560-nginx.XXXXXX)"; mkdir -p "$TMPNG/logs"; sed "s|/usr/share/nginx/html|$ROOT/library-web|g; s|listen 8080;|listen 18091;|; s|sc-library-backend:8080|127.0.0.1:18080|g" library-web/nginx.conf > "$TMPNG/site.conf"; printf 'events {}\nhttp { access_log off; error_log %s/logs/error.log; include %s/site.conf; }\n' "$TMPNG" "$TMPNG" > "$TMPNG/nginx.conf"; nginx -t -p "$TMPNG" -c "$TMPNG/nginx.conf" >/dev/null; rm -rf "$TMPNG"; echo 'PASS: independent web Nginx configuration'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
if command -v cargo >/dev/null 2>&1; then CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5600-cargo.XXXXXX)"; CARGO_TARGET_DIR="$CARGO_TMP_DIR" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked; rm -rf "$CARGO_TMP_DIR"; else echo 'INFO: cargo unavailable; production Docker build remains the Rust gate'; fi
python3 - <<'PYCLEAN'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
print('PASS: clean repository root')
PYCLEAN
echo 'PASS: Knowledge Library v5.60.0 Independent Library Web Application Foundation validation complete'
