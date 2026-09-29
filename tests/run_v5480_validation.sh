#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.48.0 / backend v2.59.0 / Cross-Language Entity, Name & Historical Toponym Resolution validation ==="
python3 - <<'PY'
import runpy
suite=[
 ('tests/test_cross_language_entity_toponym_resolution_v5480.py',set()),
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
total=0
for path in ['library-backend/tests/test_cross_language_resolution_v2590.py','library-backend/tests/test_linguistic_corpus_v2580.py','library-backend/tests/test_ocr_htr_transcription_lineage_v2570.py','library-backend/tests/test_original_language_corpus_v2560.py']:
 ns=runpy.run_path(path); count=0
 for name,fn in sorted(ns.items()):
  if name.startswith('test_') and callable(fn): fn(); count+=1; total+=1
 print(f'PASS: {path}: {count} assertions')
print(f'PASS: {total} backend language-lineage assertions')
PY
python3 -m py_compile library-backend/app/cross_language_resolution.py library-backend/app/linguistic_corpus.py library-backend/app/ocr_htr_transcription_lineage.py library-backend/app/original_language_corpus.py library-backend/app/global_source_federation.py library-backend/app/research_corpus_builder.py library-backend/app/main.py
python3 - <<'PY'
import json
from pathlib import Path
names=['cross-language-entity-authority.json','entity-name-form.json','historical-toponym.json','entity-resolution-candidate.json','entity-resolution-case.json','entity-resolution-decision.json','cross-language-resolution-readiness.json','linguistic-corpus.json','text-derivation-run.json','original-language-capture.json','global-source-federation-registry.json']
for n in names: json.loads((Path('docs/schemas')/n).read_text(encoding='utf-8'))
print('PASS: v5.48.0 and preserved language/federation JSON schemas parse')
PY
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; else echo 'INFO: php unavailable'; fi
if command -v node >/dev/null 2>&1; then while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.js' -print0); echo 'PASS: WordPress JavaScript syntax'; else echo 'INFO: node unavailable'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); else echo 'INFO: go unavailable; production Docker build remains required'; fi
if command -v cargo >/dev/null 2>&1; then CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5480-cargo.XXXXXX)"; CARGO_TARGET_DIR="$CARGO_TMP_DIR" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked; rm -rf "$CARGO_TMP_DIR"; else echo 'INFO: cargo unavailable; production Docker build remains the Rust gate'; fi
echo 'PASS: Knowledge Library v5.48.0 Cross-Language Entity, Name & Historical Toponym Resolution validation complete'
