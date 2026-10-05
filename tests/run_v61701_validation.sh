#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-.}"
cd "$REPO"

echo "=== Sustainable Catalyst Library v6.17.0.1 validation ==="
echo "Library 6.17.0.1 / backend 3.17.0.1 / web 2.17.0 / SDK 1.17.0"

python3 -m compileall -q library-backend/app
echo "PASS: Python compile"

python3 - <<'PY'
from pathlib import Path
root=Path(".")
main=(root/"library-backend/app/main.py").read_text()
assert '@app.get("/api/library/v1/citations/workspace")' in main
assert '@app.get("/api/library/v1/citations/workspace/readiness")' in main
assert '@app.get("/api/library/v1/citations/workspace/bootstrap")' in main
assert '@app.get("/api/library/v1/citations/{record_id:path}")' in main
assert main.index('@app.get("/api/library/v1/citations/workspace")') < main.index('@app.get("/api/library/v1/citations/{record_id:path}")')
assert '__version__ = "3.17.0.1"' in (root/"library-backend/app/__init__.py").read_text()
cw=(root/"library-backend/app/citation_bibliographic_workspace.py").read_text()
assert 'LIBRARY_VERSION = "6.17.0.1"' in cw
assert 'BACKEND_VERSION = "3.17.0.1"' in cw
assert 'WEB_VERSION = "2.17.0"' in cw
assert 'SDK_VERSION = "1.17.0"' in cw
print("PASS: citation workspace static routes precede legacy citation catch-all")
print("PASS: v6.17.0.1 backend/library repair markers synchronized")
PY

PYTHONPATH=library-backend python3 - <<'PY'
from app.citation_bibliographic_workspace import normalize_bibliographic_item, duplicate_analysis, export_bibliography
item=normalize_bibliographic_item({
    "title":"Repair validation",
    "type":"article-journal",
    "authors":["Doe, Jane"],
    "year":2026,
    "doi":"https://doi.org/10.1000/REPAIR"
})
assert item["identifiers"]["doi"]=="10.1000/repair"
assert item["persisted"] is False
assert item["truth_status"] is None
dups=duplicate_analysis({"items":[
    {"title":"Same Work","authors":["Doe, Jane"],"year":2020,"doi":"10.1/abc"},
    {"title":"Same Work","authors":["Doe, Jane"],"year":2020,"doi":"https://doi.org/10.1/abc"}
]})
assert dups["candidate_count"]==1
assert dups["candidates"][0]["auto_merged"] is False
exp=export_bibliography({"format":"bibtex","items":[item]})
assert exp["format"]=="bibtex" and exp["content"]
assert exp["persisted"] is False and exp["automatic_import"] is False
print("PASS: v6.17 bibliographic normalization/duplicate/export behavior preserved")
PY

git diff --check
echo "PASS: git diff --check"
echo "PASS: Library v6.17.0.1 validation complete"
