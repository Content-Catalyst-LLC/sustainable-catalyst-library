#!/usr/bin/env python3
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
plugin = root / "sustainable-catalyst-library"

main = (plugin / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
connected = (plugin / "includes/class-sc-library-connected-research-environment.php").read_text(encoding="utf-8")
exporter = (plugin / "includes/class-sc-library-public-api-export-federation.php").read_text(encoding="utf-8")
portal = (plugin / "includes/class-sc-library-connected-institutional-platform.php").read_text(encoding="utf-8")
readme = (plugin / "readme.txt").read_text(encoding="utf-8")

checks = {
    "plugin header current release": "Version: 4.0.6" in main,
    "plugin constant current release": "SC_LIBRARY_VERSION', '4.0.6" in main,
    "stable tag current release": "Stable tag: 4.0.6" in readme,
    "project compatibility alias": "public const PROJECT_POST_TYPE = 'sc_research_project';" in connected,
    "canonical project owner used twice": exporter.count("SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE") >= 2,
    "broken project owner removed": "SC_Library_Connected_Research_Environment::PROJECT_POST_TYPE" not in exporter,
    "portal Throwable boundary": "catch ( \\Throwable $error )" in portal,
    "protected fallback method": "render_public_portal_fallback" in portal,
    "protected fallback marker": 'data-sc-library-portal-recovery="4.0.6"' in portal,
    "inner portal method": "private function shortcode_portal_inner" in portal,
    "recovery logging": "Institutional portal recovery" in portal,
}

# Approximate package-wide audit of SC_Library_Class::CONSTANT references.
classes = {}
for path in plugin.rglob("*.php"):
    text = path.read_text(encoding="utf-8", errors="ignore")
    class_names = re.findall(r"\b(?:final\s+)?class\s+(SC_Library_[A-Za-z0-9_]+)", text)
    constants = set(re.findall(r"\b(?:public|private|protected)?\s*const\s+([A-Z][A-Z0-9_]+)\s*=", text))
    for class_name in class_names:
        classes[class_name] = constants

missing = []
for path in plugin.rglob("*.php"):
    text = path.read_text(encoding="utf-8", errors="ignore")
    for class_name, constant in re.findall(r"\b(SC_Library_[A-Za-z0-9_]+)::([A-Z][A-Z0-9_]+)\b", text):
        if class_name in classes and constant not in classes[class_name]:
            missing.append(f"{path.relative_to(plugin)}:{class_name}::{constant}")

checks["no unresolved cross-class constants"] = not missing

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)

if missing:
    print("Unresolved constants:", file=sys.stderr)
    for item in sorted(set(missing)):
        print("  " + item, file=sys.stderr)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} institutional portal containment checks")
