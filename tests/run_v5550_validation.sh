#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.55.0 / backend v2.66.0 / Cross-Civilizational Evidence & Scientific Data Linking validation ==="
python3 - <<'PY_VALID1'
import runpy
suite=[('tests/test_cross_civilizational_linking_v5550.py',set()),('tests/test_translation_alignment_v5540.py',{'test_release_identity_routes_and_plugin'}),('tests/test_distributed_compute_broker_v5530.py',{'test_release_identity_routes_and_wordpress_surface'}),('tests/test_checkpointed_pipeline_v5520.py',{'test_release_identity_routes_and_plugin'}),('tests/test_research_artifact_storage_v5510.py',{'test_release_identity_and_routes'}),('tests/test_specialized_worker_runtime_v5500.py',{'test_release_identity'}),('tests/test_durable_research_job_queue_v5490.py',{'test_release_identity_and_routes','test_worker_fleet_is_explicitly_deferred_to_v550'}),('tests/test_cross_language_entity_toponym_resolution_v5480.py',{'test_release_identity_routes_and_health_capabilities'}),('tests/test_linguistic_corpus_concordance_kwic_v5470.py',{'test_release_identity_and_routes'}),('tests/test_ocr_htr_transcription_lineage_v5460.py',{'test_release_identity_and_routes'}),('tests/test_original_language_corpus_v5450.py',{'test_release_identity_and_surfaces'}),('tests/test_global_source_federation_v5440.py',{'test_release_identity'}),('tests/test_publication_embedding_maps_v5430.py',{'test_release_identity'}),('tests/test_neural_reranking_v5420.py',{'test_release_identity'}),('tests/test_semantic_similarity_representation_search_v5410.py',{'test_release_identity'}),('tests/test_scientific_embedding_governance_v5400.py',{'test_release_identity'}),('tests/test_embedding_backfill_timestamp_repair_v54001.py',{'test_release_identity'})]
t=0
for path,skip in suite:
 ns=runpy.run_path(path); n=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn) and name not in skip: fn(); n+=1; t+=1
 print(f'PASS: {path}: {n} assertions')
print(f'PASS: {t} mandatory Python release assertions')
PY_VALID1
PYTHONPATH=library-backend python3 - <<'PY_VALID2'
import runpy
suite=[
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
print(f'PASS: {t} backend cross-civilizational/alignment/compute/pipeline/artifact/worker/execution/language-lineage assertions')
PY_VALID2
python3 -m py_compile library-backend/app/cross_civilizational_linking.py library-backend/app/main.py library-backend/app/specialized_worker_runtime.py
python3 - <<'PY_SCHEMA'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('*.json'): json.loads(p.read_text())
print('PASS: JSON schemas parse')
PY_SCHEMA
python3 - <<'PY_COMPOSE'
from pathlib import Path
text=Path('library-backend/compose.yml').read_text()
for service in ['library-backend','sc-library-redis','sc-library-ingestion','sc-library-worker-python','sc-library-worker-go','sc-library-worker-rust']: assert f'  {service}:\n' in text, service
assert 'sc-library-artifact-data:' in text and text.count('sc-library-artifact-data:/data/artifacts') >= 4
print('PASS: Compose topology and shared artifact volume')
PY_COMPOSE
if command -v php >/dev/null; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v node >/dev/null; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.js' -print0); echo 'PASS: WordPress JavaScript syntax'; fi
if command -v go >/dev/null; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
python3 - <<'PY_ROOT'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
print('PASS: clean repository root')
PY_ROOT
echo 'PASS: Knowledge Library v5.55.0 Cross-Civilizational Evidence & Scientific Data Linking validation complete'
