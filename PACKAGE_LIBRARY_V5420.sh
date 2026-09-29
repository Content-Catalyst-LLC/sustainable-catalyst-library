#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/dist-v5.42.0}"
TMP="$(mktemp -d /tmp/sc-library-v5420-package.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
for c in rsync zip python3; do command -v "$c" >/dev/null || { echo "ERROR: $c is required" >&2; exit 1; }; done
rm -rf "$OUT" && mkdir -p "$OUT"

REPO_STAGE="$TMP/sustainable-catalyst-library-v5.42.0"
mkdir -p "$REPO_STAGE"
rsync -a \
  --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' \
  --exclude 'dist-v5.42.0' --exclude 'library-backend/native-graph-runtime/target' \
  --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' \
  "$ROOT/" "$REPO_STAGE/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.42.0-repository.zip" "sustainable-catalyst-library-v5.42.0")

BACKEND_STAGE="$TMP/sustainable-catalyst-library-backend-v2.53.0"
mkdir -p "$BACKEND_STAGE"
for item in app go-ingestion-runtime native-graph-runtime Dockerfile compose.yml requirements.txt requirements-dev.txt .env.example README.md; do
  if [[ -e "$ROOT/library-backend/$item" ]]; then
    rsync -a --exclude '__pycache__' --exclude '*.pyc' --exclude 'target' --exclude 'sc-library-ingestion-runtime' \
      "$ROOT/library-backend/$item" "$BACKEND_STAGE/"
  fi
done
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-backend-v2.53.0.zip" "sustainable-catalyst-library-backend-v2.53.0")

PLUGIN_STAGE="$TMP/sustainable-catalyst-library"
rsync -a --exclude '__pycache__' --exclude '*.pyc' "$ROOT/sustainable-catalyst-library/" "$PLUGIN_STAGE/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.42.0-wordpress.zip" "sustainable-catalyst-library")

for f in \
  install_and_push_sustainable_catalyst_library_v5_42_0_macos.sh \
  upgrade_library_backend_v2_53_0_contabo.sh \
  LIBRARY_V5420_TERMINAL_COMMANDS.txt \
  README_v5.42.0_RELEASE.md \
  RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.42.0.md \
  NEURAL_RERANKING_RETRIEVAL_EVALUATION_v5.42.0.md \
  DEPLOY_CONTABO_LIBRARY_BACKEND_v2.53.0.md \
  BUILD_VALIDATION_5.42.0.txt; do
  cp "$ROOT/$f" "$OUT/$f"
done

python3 - "$OUT" <<'PY'
import hashlib,json,sys
from pathlib import Path
out=Path(sys.argv[1])
files=[
 'sustainable-catalyst-library-v5.42.0-repository.zip',
 'sustainable-catalyst-library-backend-v2.53.0.zip',
 'sustainable-catalyst-library-v5.42.0-wordpress.zip',
]
def sha(p):
 h=hashlib.sha256()
 with p.open('rb') as f:
  for chunk in iter(lambda:f.read(1024*1024),b''): h.update(chunk)
 return h.hexdigest()
(out/'SHA256SUMS_v5.42.0.txt').write_text('\n'.join(f"{sha(out/name)}  {name}" for name in files)+'\n')
manifest={
 'schema':'sc-library-release-manifest/1.0',
 'release':'5.42.0',
 'backend':'2.53.0',
 'go_ingestion_runtime':'0.1.0',
 'rust_graph_runtime':'0.2.0',
 'title':'Neural Reranking & Retrieval Evaluation',
 'artifacts':{name:{'sha256':sha(out/name),'size_bytes':(out/name).stat().st_size} for name in files},
 'capabilities':{
  'provider_neutral_neural_reranking':True,
  'reranker_specification_fingerprint':True,
  'baseline_rank_preservation':True,
  'result_set_preservation':True,
  'transparent_provider_score_lineage':True,
  'baseline_vs_reranked_evaluation':True,
  'neural_reranked_search':True,
  'provider_disabled_fail_open':True,
 },
 'guardrails':{
  'fake_neural_scores':False,
  'automatic_candidate_filtering':False,
  'neural_score_is_probability':False,
  'neural_score_is_evidence':False,
  'neural_score_is_truth':False,
  'automatic_core_promotion':False,
 }
}
(out/'RELEASE_MANIFEST_v5.42.0.json').write_text(json.dumps(manifest,indent=2)+'\n')
PY

BUNDLE_STAGE="$TMP/release-bundle"
mkdir -p "$BUNDLE_STAGE"
rsync -a "$OUT/" "$BUNDLE_STAGE/"
rm -f "$BUNDLE_STAGE/sustainable-catalyst-library-v5.42.0-release-bundle.zip"
(cd "$BUNDLE_STAGE" && zip -qr "$OUT/sustainable-catalyst-library-v5.42.0-release-bundle.zip" .)

echo "PACKAGED: $OUT"
ls -lh "$OUT"
