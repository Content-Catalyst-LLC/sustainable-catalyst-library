#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.52.0 / backend v2.63.0 / Checkpointed Ingestion & Research Pipeline Engine validation ==="
python3 - <<'PY'
import runpy
suite=[
 ('tests/test_checkpointed_pipeline_v5520.py',set()),
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
total=0
for path,skip in suite:
 ns=runpy.run_path(path); count=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn) and name not in skip: fn(); count+=1; total+=1
 print(f'PASS: {path}: {count} assertions')
print(f'PASS: {total} mandatory Python release assertions')
PY
PYTHONPATH=library-backend python3 - <<'PY'
import runpy
suite=[
 ('library-backend/tests/test_checkpointed_pipeline_v2630.py',set()),
 ('library-backend/tests/test_artifact_storage_v2620.py',set()),
 ('library-backend/tests/test_specialized_worker_runtime_v2610.py',{'test_catalog'}),
 ('library-backend/tests/test_durable_job_queue_v2600.py',{'test_worker_fleet_is_not_faked_by_foundation_release'}),
 ('library-backend/tests/test_cross_language_resolution_v2590.py',set()),
 ('library-backend/tests/test_linguistic_corpus_v2580.py',set()),
 ('library-backend/tests/test_ocr_htr_transcription_lineage_v2570.py',set()),
 ('library-backend/tests/test_original_language_corpus_v2560.py',set()),
]
total=0
for path,skip in suite:
 ns=runpy.run_path(path); count=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn) and name not in skip: fn(); count+=1; total+=1
 print(f'PASS: {path}: {count} assertions')
print(f'PASS: {total} backend pipeline/artifact/worker/execution/language-lineage assertions')
PY
python3 -m py_compile library-backend/app/checkpointed_pipeline.py library-backend/app/artifact_storage.py library-backend/app/specialized_worker_runtime.py library-backend/app/worker_agent.py library-backend/app/durable_job_queue.py library-backend/app/main.py library-backend/app/settings.py
python3 - <<'PY'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('*.json'): json.loads(p.read_text())
print('PASS: JSON schemas parse')
PY
python3 - <<'PY'
from pathlib import Path
text=Path('library-backend/compose.yml').read_text(encoding='utf-8')
for service in ['library-backend','sc-library-redis','sc-library-ingestion','sc-library-worker-python','sc-library-worker-go','sc-library-worker-rust']: assert f"  {service}:\n" in text, service
assert 'sc-library-artifact-data:' in text and text.count('sc-library-artifact-data:/data/artifacts') >= 4
print('PASS: Compose topology and shared artifact volume')
PY
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; else echo 'INFO: php unavailable'; fi
if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.js' -print0); echo 'PASS: WordPress JavaScript syntax'; else echo 'INFO: node unavailable'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); else echo 'INFO: go unavailable'; fi
if command -v cargo >/dev/null 2>&1; then CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5520-cargo.XXXXXX)"; CARGO_TARGET_DIR="$CARGO_TMP_DIR" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked; rm -rf "$CARGO_TMP_DIR"; else echo 'INFO: cargo unavailable; production Docker build remains the Rust gate'; fi
python3 - <<'PY'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
print('PASS: cleaned repository root preserved')
PY
echo 'PASS: Knowledge Library v5.52.0 Checkpointed Ingestion & Research Pipeline Engine validation complete'
