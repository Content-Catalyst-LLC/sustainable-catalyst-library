#!/usr/bin/env python3
"""Static validation for Foundations v2.0.4 server-rendered catalog repair."""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
MODULE = ROOT / "sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php"
text = MODULE.read_text(encoding="utf-8")

checks = {
    "v2.0.4 marker": "SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.4" in text,
    "pre-shortcode hook": "pre_do_shortcode_tag" in text,
    "server rendering method": "server_render_foundations_library" in text,
    "canonical page detection": "is_foundations_page_request" in text,
    "legacy foundations shortcode": "sc_foundations_library" in text,
    "legacy generic library mode": "$tag === 'sc_library'" in text,
    "foundations collection detection": "$collection === 'foundations'" in text,
    "REST-independent catalog": "return $this->catalog_shortcode([" in text,
    "full catalog limit": "'limit'        => 250" in text,
    "header compatibility": "'show_header'  => $show_header" in text,
    "catalog show_header option": "'show_header' => 'yes'" in text,
    "Foundations page assets": "$is_foundations_page = $this->is_foundations_page_request();" in text,
    "REST requests excluded": "defined('REST_REQUEST') && REST_REQUEST" in text,
    "canonical path": "'institution/foundations'" in text,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(("PASS" if ok else "FAIL") + ": " + name)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} server-rendered catalog checks")
