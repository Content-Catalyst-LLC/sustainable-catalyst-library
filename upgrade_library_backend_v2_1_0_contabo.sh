#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.1.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v210.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.1.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.1.0-$ts"
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || { echo "ERROR: could not find backend package root" >&2; exit 1; }
rsync -a --delete --exclude='.env' "$source_dir/" "$LIVE/"
cp "$env_backup" "$LIVE/.env"
chmod 600 "$LIVE/.env"

cd "$LIVE"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate

container="sc-library-backend"
healthy=0
for _ in $(seq 1 45); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || true)"
  case "$state" in
    healthy) healthy=1; break;;
    unhealthy|exited|dead)
      echo "ERROR: $container entered state: $state" >&2
      docker logs --tail=150 "$container" >&2 || true
      exit 1
      ;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker logs --tail=150 "$container" >&2 || true; exit 1; }

health_json="$(curl -fsS http://127.0.0.1:8087/health)"
manifest_json="$(curl -fsS http://127.0.0.1:8087/v1/private-organizational-knowledge)"
printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$manifest_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.1.0" ]] || { echo "ERROR: expected backend v2.1.0, got: $version" >&2; exit 1; }

python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("private_organizational_knowledge") is True; assert c.get("private_organization_scoping") is True; assert c.get("private_access_scope_enforcement") is True; assert c.get("private_version_lineage") is True; assert c.get("private_audit_events") is True; assert c.get("private_public_search_separation") is True' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); g=d.get("framework",{}).get("governance",{}); s=d.get("storage",{}); i=d.get("ingestion",{}); assert d.get("schema")=="sc-private-organizational-knowledge-manifest/1.0"; assert g.get("public_search_includes_private_records") is False; assert g.get("cross_organization_search") is False; assert g.get("automatic_publication") is False; assert g.get("server_signed_private_requests_required") is True; assert s.get("separate_private_tables") is True; assert s.get("public_query_path_reads_private_tables") is False; assert i.get("binary_parser_claimed") is False' <<<"$manifest_json"

echo "=== VERIFYING ADDITIVE PRIVATE KNOWLEDGE TABLES ==="
docker exec -i "$container" python - <<'PY'
from app.db import get_pool
required = {
    "library_private_organizations",
    "library_private_sources",
    "library_private_records",
    "library_private_record_versions",
    "library_private_ingest_events",
    "library_private_access_events",
}
pool = get_pool()
with pool.connection() as conn, conn.cursor() as cur:
    cur.execute("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname='public' AND tablename LIKE 'library_private_%'")
    found = {row["tablename"] for row in cur.fetchall()}
missing = sorted(required - found)
if missing:
    raise SystemExit("missing private knowledge tables: " + ", ".join(missing))
print("PASS: private knowledge tables present:", ", ".join(sorted(required)))
PY

printf '%s\n' 'PASS - Library backend v2.1.0 upgraded; Private Organizational Knowledge Foundation is active.'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
