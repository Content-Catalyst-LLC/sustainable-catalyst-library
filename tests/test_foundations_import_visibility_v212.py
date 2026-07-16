#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
importer = (root / "sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php").read_text(encoding="utf-8")
system = (root / "sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php").read_text(encoding="utf-8")

checks = {
    "v2.1.2 importer marker": "private const RELEASE = '2.1.2';" in importer,
    "v2.1.2 system marker": "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.2" in system,
    "Knowledge Library parent": "add_submenu_page(\n            'sc-library'" in importer,
    "visible import label": "'First Edition Import'" in importer,
    "Tools fallback": "add_management_page(" in importer and "'Foundations First Edition'" in importer,
    "admin notice": "public function admin_notice()" in importer,
    "notice action button": "Open First Edition Import" in importer,
    "direct admin URL": "admin.php?page=sc-foundations-first-edition" in importer,
    "broken CPT parent removed": "add_submenu_page('edit.php?post_type=sc_foundation_doc'" not in importer,
    "correct redirect": "wp_safe_redirect(admin_url('admin.php?page=sc-foundations-first-edition'))" in importer,
    "content version preserved": "sc_library_foundations_first_edition_content_version','2.1.0" in importer,
    "import action preserved": "admin_post_sc_foundations_v210_import" in importer,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} v2.1.2 admin visibility checks")
