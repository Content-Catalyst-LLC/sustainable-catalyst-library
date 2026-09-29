#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/dist-v5.48.0}"
TMP="$(mktemp -d /tmp/sc-library-package-v5480.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
rm -rf "$OUT"; mkdir -p "$OUT"; cd "$ROOT"
./tests/run_v5480_validation.sh | tee BUILD_VALIDATION_5.48.0.txt
find . -type d \( -name '__pycache__' -o -name '.pytest_cache' \) -prune -exec rm -rf {} + 2>/dev/null || true
find . -type f \( -name '*.pyc' -o -name '*.pyo' \) -delete
rm -rf library-backend/native-graph-runtime/target
rm -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
(cd "$ROOT" && zip -qr "$OUT/sustainable-catalyst-library-v5.48.0-wordpress.zip" sustainable-catalyst-library -x '*/.DS_Store' '*/__MACOSX/*')
mkdir -p "$TMP/sustainable-catalyst-library-backend-v2.59.0"
rsync -a --exclude '.env' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' --exclude 'native-graph-runtime/target' --exclude 'go-ingestion-runtime/sc-library-ingestion-runtime' library-backend/ "$TMP/sustainable-catalyst-library-backend-v2.59.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-backend-v2.59.0.zip" sustainable-catalyst-library-backend-v2.59.0)
mkdir -p "$TMP/sustainable-catalyst-library-v5.48.0"
rsync -a --exclude '.git' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' --exclude 'dist-v5.48.0' --exclude 'library-backend/native-graph-runtime/target' --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' "$ROOT/" "$TMP/sustainable-catalyst-library-v5.48.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.48.0-repository.zip" sustainable-catalyst-library-v5.48.0)
cp BUILD_VALIDATION_5.48.0.txt README_v5.48.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.48.0.md CROSS_LANGUAGE_ENTITY_NAME_HISTORICAL_TOPONYM_RESOLUTION_v5.48.0.md DEPLOY_CONTABO_LIBRARY_BACKEND_v2.59.0.md LIBRARY_V5480_TERMINAL_COMMANDS.txt install_and_push_sustainable_catalyst_library_v5_48_0_macos.sh upgrade_library_backend_v2_59_0_contabo.sh PACKAGE_LIBRARY_V5480.sh "$OUT/"
python3 - "$OUT" <<'PY'
from pathlib import Path
import hashlib,json,sys
out=Path(sys.argv[1]); files=['sustainable-catalyst-library-v5.48.0-wordpress.zip','sustainable-catalyst-library-backend-v2.59.0.zip','sustainable-catalyst-library-v5.48.0-repository.zip']
manifest={'schema':'sc-library-release-manifest/1.0','release':'5.48.0','backend':'2.59.0','go_runtime':'0.1.0','rust_runtime':'0.2.0','title':'Cross-Language Entity, Name & Historical Toponym Resolution','contracts':['sc-library-cross-language-entity-authority/1.0','sc-library-entity-name-form/1.0','sc-library-historical-toponym/1.0','sc-library-entity-resolution-candidate/1.0','sc-library-entity-resolution-case/1.0','sc-library-entity-resolution-decision/1.0','sc-library-cross-language-resolution-readiness/1.0'],'artifacts':[],'guardrails':{'candidate_score_is_probability':False,'candidate_rank_is_truth':False,'automatic_entity_merge':False,'automatic_resolution':False,'automatic_translation':False,'automatic_transliteration':False,'ambiguity_preserved':True,'automatic_evidence_promotion':False,'automatic_truth_promotion':False,'automatic_platform_core_promotion':False,'sample_resolution_data_written_during_deploy':False}}
lines=[]
for name in files:
 data=(out/name).read_bytes(); sha=hashlib.sha256(data).hexdigest(); lines.append(f'{sha}  {name}'); manifest['artifacts'].append({'name':name,'sha256':sha,'bytes':len(data)})
(out/'SHA256SUMS_v5.48.0.txt').write_text('\n'.join(lines)+'\n')
(out/'RELEASE_MANIFEST_v5.48.0.json').write_text(json.dumps(manifest,indent=2)+'\n')
PY
(cd "$OUT" && zip -q sustainable-catalyst-library-v5.48.0-release-bundle.zip BUILD_VALIDATION_5.48.0.txt DEPLOY_CONTABO_LIBRARY_BACKEND_v2.59.0.md CROSS_LANGUAGE_ENTITY_NAME_HISTORICAL_TOPONYM_RESOLUTION_v5.48.0.md LIBRARY_V5480_TERMINAL_COMMANDS.txt PACKAGE_LIBRARY_V5480.sh README_v5.48.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.48.0.md RELEASE_MANIFEST_v5.48.0.json SHA256SUMS_v5.48.0.txt install_and_push_sustainable_catalyst_library_v5_48_0_macos.sh upgrade_library_backend_v2_59_0_contabo.sh sustainable-catalyst-library-v5.48.0-wordpress.zip sustainable-catalyst-library-backend-v2.59.0.zip sustainable-catalyst-library-v5.48.0-repository.zip)
for z in sustainable-catalyst-library-v5.48.0-release-bundle.zip sustainable-catalyst-library-v5.48.0-repository.zip sustainable-catalyst-library-backend-v2.59.0.zip sustainable-catalyst-library-v5.48.0-wordpress.zip; do (cd "$OUT" && unzip -tq "$z" >/dev/null); done
echo "PASS: packaged Knowledge Library v5.48.0 artifacts in $OUT"
