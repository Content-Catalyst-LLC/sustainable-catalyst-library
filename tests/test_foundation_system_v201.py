#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php"
CSS = ROOT / "sustainable-catalyst-library/assets/css/sc-library-foundation-system-v200.css"
JS = ROOT / "sustainable-catalyst-library/assets/js/sc-library-foundation-system-v200.js"
SINGLE = ROOT / "sustainable-catalyst-library/templates/single-sc_foundation_doc-v200.php"
ARCHIVE = ROOT / "sustainable-catalyst-library/templates/archive-sc_foundation_doc-v200.php"
SCHEMA = ROOT / "docs/foundations/foundation-document-v2.schema.json"
PATCHER = ROOT / "apply_foundations_v2_0_1.py"

errors: list[str] = []
for path in [PHP, CSS, JS, SINGLE, ARCHIVE, SCHEMA, PATCHER]:
    if not path.is_file() or path.stat().st_size == 0:
        errors.append(f"missing or empty: {path.relative_to(ROOT)}")

php = PHP.read_text(encoding="utf-8") if PHP.exists() else ""
checks = {
    "version constant": "SC_LIBRARY_FOUNDATIONS_VERSION" in php and "'2.0.1'" in php,
    "native post type": "sc_foundation_doc" in php,
    "catalog shortcode": "sc_foundations_catalog" in php,
    "catalog REST schema": "sc-foundations-catalog/2.0" in php,
    "three variants": all(v in php for v in ["institutional-standard", "policy-legal-record", "product-system-brief"]),
    "controlled statuses": all(v in php for v in ["current-approved-record", "superseded", "historical-record"]),
    "custom templates": all(v in php for v in ["single-sc_foundation_doc-v200.php", "archive-sc_foundation_doc-v200.php"]),
    "revision history": "revision_history" in php,
    "supersession": "supersedes_ids" in php and "superseded_by_id" in php,
    "toc": "prepare_body" in php and "sc-fnd-toc" in php,
    "one-time rewrite refresh": "maybe_refresh_rewrite_rules" in php and "flush_rewrite_rules(false)" in php,
    "legacy page route recovery": "recover_legacy_foundations_route" in php and "institution/foundations" in php,
}
for label, ok in checks.items():
    if not ok:
        errors.append(f"PHP check failed: {label}")

css = CSS.read_text(encoding="utf-8") if CSS.exists() else ""
for token in ["@media print", "prefers-reduced-motion", "focus-visible", ".sc-fnd-catalog-grid"]:
    if token not in css:
        errors.append(f"CSS check failed: {token}")

js = JS.read_text(encoding="utf-8") if JS.exists() else ""
for token in ["navigator.clipboard", "window.print", "aria-expanded"]:
    if token not in js:
        errors.append(f"JS check failed: {token}")

try:
    schema = json.loads(SCHEMA.read_text(encoding="utf-8"))
    if schema.get("title") != "Sustainable Catalyst Foundation Document":
        errors.append("schema title mismatch")
    required = set(schema.get("required", []))
    if not {"document_id", "record_type", "status", "version"}.issubset(required):
        errors.append("schema required fields incomplete")
except Exception as exc:
    errors.append(f"schema parse failed: {exc}")

patcher = PATCHER.read_text(encoding="utf-8") if PATCHER.exists() else ""
if "class-sc-library-foundation-system-v200.php" not in patcher:
    errors.append("patcher missing module include")

if errors:
    print("FAIL")
    for error in errors:
        print(f"- {error}")
    sys.exit(1)

print("PASS: Sustainable Catalyst Foundations v2.0.1 static suite")
print(f"PASS: {len(checks)} PHP capability checks")
print("PASS: responsive, accessibility, print, schema, and patcher checks")
