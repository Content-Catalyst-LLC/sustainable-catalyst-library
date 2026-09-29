#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/dist-v5.49.0}"
TMP="$(mktemp -d /tmp/sc-library-package-v5490.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
rm -rf "$OUT"; mkdir -p "$OUT"; cd "$ROOT"
./tests/run_v5490_validation.sh | tee BUILD_VALIDATION_5.49.0.txt
find . -type d \( -name '__pycache__' -o -name '.pytest_cache' \) -prune -exec rm -rf {} + 2>/dev/null || true
find . -type f \( -name '*.pyc' -o -name '*.pyo' \) -delete
rm -rf library-backend/native-graph-runtime/target
rm -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
(cd "$ROOT" && zip -qr "$OUT/sustainable-catalyst-library-v5.49.0-wordpress.zip" sustainable-catalyst-library -x '*/.DS_Store' '*/__MACOSX/*')
mkdir -p "$TMP/sustainable-catalyst-library-backend-v2.60.0"
rsync -a --exclude '.env' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' --exclude 'native-graph-runtime/target' --exclude 'go-ingestion-runtime/sc-library-ingestion-runtime' library-backend/ "$TMP/sustainable-catalyst-library-backend-v2.60.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-backend-v2.60.0.zip" sustainable-catalyst-library-backend-v2.60.0)
mkdir -p "$TMP/sustainable-catalyst-library-v5.49.0"
rsync -a --exclude '.git' --exclude '__pycache__' --exclude '*.pyc' --exclude '.pytest_cache' --exclude 'dist-v5.49.0' --exclude 'library-backend/native-graph-runtime/target' --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' "$ROOT/" "$TMP/sustainable-catalyst-library-v5.49.0/"
(cd "$TMP" && zip -qr "$OUT/sustainable-catalyst-library-v5.49.0-repository.zip" sustainable-catalyst-library-v5.49.0)
cp BUILD_VALIDATION_5.49.0.txt README_v5.49.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.49.0.md DURABLE_RESEARCH_JOB_QUEUE_EXECUTION_STATE_v5.49.0.md DEPLOY_CONTABO_LIBRARY_BACKEND_v2.60.0.md LIBRARY_V5490_TERMINAL_COMMANDS.txt install_and_push_sustainable_catalyst_library_v5_49_0_macos.sh upgrade_library_backend_v2_60_0_contabo.sh PACKAGE_LIBRARY_V5490.sh "$OUT/"
python3 - "$OUT" <<'PY'
from pathlib import Path
import hashlib,json,sys
out=Path(sys.argv[1]); files=['sustainable-catalyst-library-v5.49.0-wordpress.zip','sustainable-catalyst-library-backend-v2.60.0.zip','sustainable-catalyst-library-v5.49.0-repository.zip']
manifest={'schema':'sc-library-release-manifest/1.0','release':'5.49.0','backend':'2.60.0','go_runtime':'0.1.0','rust_runtime':'0.2.0','redis':'7.4-alpine','title':'Durable Research Job Queue & Execution State','contracts':['sc-library-research-job/1.0','sc-library-job-attempt/1.0','sc-library-job-event/1.0','sc-library-job-dispatch/1.0','sc-library-execution-fabric-readiness/1.0','sc-library-research-job-validation/1.0'],'artifacts':[],'guardrails':{'postgresql_authoritative_job_state':True,'redis_authoritative_job_state':False,'redis_dispatch_loss_loses_job':False,'worker_fleet_activated':False,'specialized_worker_target':'5.50.0','job_completion_implies_source_validity':False,'job_completion_implies_evidence_truth':False,'automatic_platform_core_promotion':False,'sample_job_written_during_deploy':False}}
lines=[]
for name in files:
 data=(out/name).read_bytes(); sha=hashlib.sha256(data).hexdigest(); lines.append(f'{sha}  {name}'); manifest['artifacts'].append({'name':name,'sha256':sha,'bytes':len(data)})
(out/'SHA256SUMS_v5.49.0.txt').write_text('\n'.join(lines)+'\n')
(out/'RELEASE_MANIFEST_v5.49.0.json').write_text(json.dumps(manifest,indent=2)+'\n')
PY
(cd "$OUT" && zip -q sustainable-catalyst-library-v5.49.0-release-bundle.zip BUILD_VALIDATION_5.49.0.txt DEPLOY_CONTABO_LIBRARY_BACKEND_v2.60.0.md DURABLE_RESEARCH_JOB_QUEUE_EXECUTION_STATE_v5.49.0.md LIBRARY_V5490_TERMINAL_COMMANDS.txt PACKAGE_LIBRARY_V5490.sh README_v5.49.0_RELEASE.md RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.49.0.md RELEASE_MANIFEST_v5.49.0.json SHA256SUMS_v5.49.0.txt install_and_push_sustainable_catalyst_library_v5_49_0_macos.sh upgrade_library_backend_v2_60_0_contabo.sh sustainable-catalyst-library-v5.49.0-wordpress.zip sustainable-catalyst-library-backend-v2.60.0.zip sustainable-catalyst-library-v5.49.0-repository.zip)
for z in sustainable-catalyst-library-v5.49.0-release-bundle.zip sustainable-catalyst-library-v5.49.0-repository.zip sustainable-catalyst-library-backend-v2.60.0.zip sustainable-catalyst-library-v5.49.0-wordpress.zip; do (cd "$OUT" && unzip -tq "$z" >/dev/null); done
echo "PASS: packaged Knowledge Library v5.49.0 artifacts in $OUT"
