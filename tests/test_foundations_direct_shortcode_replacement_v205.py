#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
module = (root / "sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php").read_text(encoding="utf-8")

checks = {
    "v2.0.5 marker": "SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.5" in module,
    "late shortcode replacement": "replace_foundations_library_shortcode" in module and ", 999);" in module,
    "old pre-shortcode filter removed": "pre_do_shortcode_tag" not in module,
    "original shortcode removed": "remove_shortcode('sc_foundations_library')" in module,
    "replacement registered": "add_shortcode('sc_foundations_library'" in module,
    "canonical page renderer": "render_foundations_collection" in module,
    "collection queried directly": "get_objects_in_term" in module,
    "search index not required": "SC_Library_Indexer" not in module,
    "REST not required": "data-foundations-server-rendered=\"1\"" in module,
    "all collection object types": "$taxonomy_object->object_type" in module,
    "legacy metadata supported": "_sc_library_doc_status" in module and "_sc_library_doc_type" in module,
    "new metadata supported": "self::META_PREFIX . 'status'" in module,
    "archived filter supported": "Include superseded and archived" in module,
    "published records only": "'post_status'            => 'publish'" in module,
    "other pages retain original interface": "SC_Library_Documentation::render_shortcode" in module,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} direct Foundations shortcode replacement checks")
