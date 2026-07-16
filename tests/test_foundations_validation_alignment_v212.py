#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
first_test = (root / "tests/test_foundations_first_edition_v210.py").read_text(encoding="utf-8")
system = (root / "sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php").read_text(encoding="utf-8")
importer = (root / "sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php").read_text(encoding="utf-8")

checks = {
    "First Edition test expects v2.1.2": "\"SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.2\"" in first_test,
    "stale v2.1.0 assertion removed": "\"SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.0\"" not in first_test,
    "system reports v2.1.2": "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.2" in system,
    "importer reports v2.1.2": "private const RELEASE = '2.1.2';" in importer,
    "content edition stays v2.1.0": "sc_library_foundations_first_edition_content_version','2.1.0" in importer,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} validation alignment checks")
