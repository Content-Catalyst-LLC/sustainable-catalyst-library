#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/dist-v5.41.0}"
TMP="$(mktemp -d /tmp/sc-library-v5410-package.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
for c in rsync zip python3; do command -v "$c" >/dev/null || { echo "ERROR: $c is required" >&2; exit 1; }; done
rm -rf "$OUT" && mkdir -p "$OUT"

REPO_STAGE="$TMP/sustainable-catalyst-library-v5.41.0"
mkdir -p "$REPO_STAGE"
rsync -a \
  --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' \
  --exclude 'dist-v5.41.0' --exclude 'library-backend/native-graph-runtime/target' \
  --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' \
  "$ROOT/" "$REPO_STAGE/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.41.0-repository.zip" "sustainable-catalyst-library-v5.41.0")

BACKEND_STAGE="$TMP/sustainable-catalyst-library-backend-v2.52.0"
mkdir -p "$BACKEND_STAGE"
for item in app go-ingestion-runtime native-graph-runtime Dockerfile compose.yml requirements.txt requirements-dev.txt .env.example README.md; do
  if [[ -e "$ROOT/library-backend/$item" ]]; then
    rsync -a --exclude '__pycache__' --exclude '*.pyc' --exclude 'target' --exclude 'sc-library-ingestion-runtime' \
      "$ROOT/library-backend/$item" "$BACKEND_STAGE/"
  fi
done
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-backend-v2.52.0.zip" "sustainable-catalyst-library-backend-v2.52.0")

PLUGIN_STAGE="$TMP/sustainable-catalyst-library"
rsync -a --exclude '__pycache__' --exclude '*.pyc' "$ROOT/sustainable-catalyst-library/" "$PLUGIN_STAGE/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.41.0-wordpress.zip" "sustainable-catalyst-library")

for f in \
  install_and_push_sustainable_catalyst_library_v5_41_0_macos.sh \
  upgrade_library_backend_v2_52_0_contabo.sh \
  LIBRARY_V5410_TERMINAL_COMMANDS.txt \
  README_v5.41.0_RELEASE.md \
  RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.41.0.md \
  SEMANTIC_SIMILARITY_REPRESENTATION_SEARCH_v5.41.0.md \
  DEPLOY_CONTABO_LIBRARY_BACKEND_v2.52.0.md \
  BUILD_VALIDATION_5.41.0.txt; do
  cp "$ROOT/$f" "$OUT/$f"
done

python3 - "$OUT" <<'PY'
import hashlib,json,sys
from pathlib import Path
out=Path(sys.argv[1])
files=[
 'sustainable-catalyst-library-v5.41.0-repository.zip',
 'sustainable-catalyst-library-backend-v2.52.0.zip',
 'sustainable-catalyst-library-v5.41.0-wordpress.zip',
]
def sha(p):
 h=hashlib.sha256()
 with p.open('rb') as f:
  for chunk in iter(lambda:f.read(1024*1024),b''): h.update(chunk)
 return h.hexdigest()
checks=[]
for name in files:
 checks.append(f"{sha(out/name)}  {name}")
(out/'SHA256SUMS_v5.41.0.txt').write_text('\n'.join(checks)+'\n')
manifest={
 'schema':'sc-library-release-manifest/1.0',
 'release':'5.41.0',
 'backend':'2.52.0',
 'go_ingestion_runtime':'0.1.0',
 'rust_graph_runtime':'0.2.0',
 'title':'Semantic Similarity & Representation Search',
 'artifacts':{name:{'sha256':sha(out/name),'size_bytes':(out/name).stat().st_size} for name in files},
 'capabilities':{
  'semantic_text_search':True,
  'record_to_record_similarity':True,
  'representation_descriptors':True,
  'hybrid_rrf_governed_semantic_candidates':True,
  'current_content_filtering':True,
  'current_specification_text_search':True,
  'provider_independent_seed_similarity':True,
 },
 'guardrails':{
  'automatic_embedding_backfill':False,
  'semantic_similarity_is_evidence':False,
  'semantic_similarity_is_truth':False,
  'semantic_similarity_is_causality':False,
  'automatic_core_promotion':False,
 }
}
(out/'RELEASE_MANIFEST_v5.41.0.json').write_text(json.dumps(manifest,indent=2)+'\n')
PY

BUNDLE_STAGE="$TMP/release-bundle"
mkdir -p "$BUNDLE_STAGE"
rsync -a "$OUT/" "$BUNDLE_STAGE/"
rm -f "$BUNDLE_STAGE/sustainable-catalyst-library-v5.41.0-release-bundle.zip"
(cd "$BUNDLE_STAGE" && zip -qr "$OUT/sustainable-catalyst-library-v5.41.0-release-bundle.zip" .)

echo "PACKAGED: $OUT"
ls -lh "$OUT"
