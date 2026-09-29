#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/dist-v5.45.0}"
TMP="$(mktemp -d /tmp/sc-library-package-v5450.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
rm -rf "$OUT"
mkdir -p "$OUT"
cd "$ROOT"
./tests/run_v5450_validation.sh | tee BUILD_VALIDATION_5.45.0.txt

find . -type d \( -name '__pycache__' -o -name '.pytest_cache' \) -prune -exec rm -rf {} + 2>/dev/null || true
find . -type f \( -name '*.pyc' -o -name '*.pyo' \) -delete
rm -rf library-backend/native-graph-runtime/target
rm -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime

(cd "$ROOT" && zip -qr "$OUT/sustainable-catalyst-library-v5.45.0-wordpress.zip" sustainable-catalyst-library \
  -x '*/.DS_Store' '*/__MACOSX/*')

mkdir -p "$TMP/sustainable-catalyst-library-backend-v2.56.0"
rsync -a \
  --exclude '.env' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' \
  --exclude 'native-graph-runtime/target' --exclude 'go-ingestion-runtime/sc-library-ingestion-runtime' \
  library-backend/ "$TMP/sustainable-catalyst-library-backend-v2.56.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-backend-v2.56.0.zip" sustainable-catalyst-library-backend-v2.56.0)

mkdir -p "$TMP/sustainable-catalyst-library-v5.45.0"
rsync -a \
  --exclude '.git' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' \
  --exclude 'dist-v5.45.0' --exclude 'library-backend/native-graph-runtime/target' \
  --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' \
  "$ROOT/" "$TMP/sustainable-catalyst-library-v5.45.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.45.0-repository.zip" sustainable-catalyst-library-v5.45.0)

cp BUILD_VALIDATION_5.45.0.txt "$OUT/"
cp README_v5.45.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.45.0.md \
   ORIGINAL_LANGUAGE_CORPUS_INGESTION_PRESERVATION_v5.45.0.md \
   DEPLOY_CONTABO_LIBRARY_BACKEND_v2.56.0.md LIBRARY_V5450_TERMINAL_COMMANDS.txt \
   install_and_push_sustainable_catalyst_library_v5_45_0_macos.sh \
   upgrade_library_backend_v2_56_0_contabo.sh PACKAGE_LIBRARY_V5450.sh "$OUT/"

python3 - "$OUT" <<'PY'
from pathlib import Path
import hashlib,json,sys
out=Path(sys.argv[1])
files=[
 'sustainable-catalyst-library-v5.45.0-wordpress.zip',
 'sustainable-catalyst-library-backend-v2.56.0.zip',
 'sustainable-catalyst-library-v5.45.0-repository.zip',
]
manifest={
 'schema':'sc-library-release-manifest/1.0',
 'release':'5.45.0','backend':'2.56.0','go_runtime':'0.1.0','rust_runtime':'0.2.0',
 'title':'Original-Language Corpus Ingestion & Preservation',
 'contracts':[
   'sc-library-original-language-corpus/1.0','sc-library-original-language-capture/1.0',
   'sc-library-text-representation/1.0','sc-library-text-transformation/1.0'
 ],
 'artifacts':[],'guardrails':{
   'original_language_is_canonical':True,
   'raw_source_bytes_preserved':True,
   'raw_source_text_preserved':True,
   'unicode_normalization_is_derived':True,
   'normalized_text_replaces_original':False,
   'translation_is_derived':True,
   'automatic_translation':False,
   'automatic_evidence_promotion':False,
   'automatic_truth_promotion':False,
   'automatic_platform_core_promotion':False,
   'sample_capture_written_during_deploy':False,
 }
}
lines=[]
for name in files:
    data=(out/name).read_bytes(); sha=hashlib.sha256(data).hexdigest()
    lines.append(f'{sha}  {name}')
    manifest['artifacts'].append({'name':name,'sha256':sha,'bytes':len(data)})
(out/'SHA256SUMS_v5.45.0.txt').write_text('\n'.join(lines)+'\n')
(out/'RELEASE_MANIFEST_v5.45.0.json').write_text(json.dumps(manifest,indent=2)+'\n')
PY

(cd "$OUT" && zip -q sustainable-catalyst-library-v5.45.0-release-bundle.zip \
  BUILD_VALIDATION_5.45.0.txt DEPLOY_CONTABO_LIBRARY_BACKEND_v2.56.0.md \
  ORIGINAL_LANGUAGE_CORPUS_INGESTION_PRESERVATION_v5.45.0.md \
  LIBRARY_V5450_TERMINAL_COMMANDS.txt PACKAGE_LIBRARY_V5450.sh \
  README_v5.45.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.45.0.md \
  RELEASE_MANIFEST_v5.45.0.json SHA256SUMS_v5.45.0.txt \
  install_and_push_sustainable_catalyst_library_v5_45_0_macos.sh \
  upgrade_library_backend_v2_56_0_contabo.sh \
  sustainable-catalyst-library-v5.45.0-wordpress.zip \
  sustainable-catalyst-library-backend-v2.56.0.zip \
  sustainable-catalyst-library-v5.45.0-repository.zip)

(cd "$OUT" && unzip -tq sustainable-catalyst-library-v5.45.0-release-bundle.zip >/dev/null)
(cd "$OUT" && unzip -tq sustainable-catalyst-library-v5.45.0-repository.zip >/dev/null)
(cd "$OUT" && unzip -tq sustainable-catalyst-library-backend-v2.56.0.zip >/dev/null)
(cd "$OUT" && unzip -tq sustainable-catalyst-library-v5.45.0-wordpress.zip >/dev/null)
echo "PASS: packaged Knowledge Library v5.45.0 artifacts in $OUT"
