#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
plugin = root / "sustainable-catalyst-library"
main = (plugin / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
readme = (plugin / "readme.txt").read_text(encoding="utf-8")
portal = (plugin / "includes/class-sc-library-connected-institutional-platform.php").read_text(encoding="utf-8")
css = (plugin / "assets/css/sc-library-connected-institutional-platform.css").read_text(encoding="utf-8")

checks = {
    "plugin header 4.0.6": "Version: 4.0.6" in main,
    "plugin constant 4.0.6": "SC_LIBRARY_VERSION', '4.0.6" in main,
    "stable tag 4.0.6": "Stable tag: 4.0.6" in readme,
    "compact attribute": "'compact'   => 'false'" in portal,
    "featured attribute": "'featured'  => 6" in portal,
    "fallback receives attributes": "render_public_portal_fallback( $atts )" in portal,
    "compact renderer": "render_compact_public_portal" in portal,
    "prioritized records": "prioritize_compact_records" in portal,
    "collapsed complete catalog": "Browse all %d institutional records" in portal,
    "recovery marker 4.0.6": 'data-sc-library-portal-recovery=\"4.0.6\"' in portal,
    "compact portal css": ".sc-inst-public-portal--compact" in css,
    "two-column compact grid": "grid-template-columns: repeat(2, minmax(0, 1fr));" in css,
    "mobile one-column grid": ".sc-inst-compact-grid {\n    grid-template-columns: 1fr;" in css,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)
if failed:
    raise SystemExit("FAILED: " + ", ".join(failed))
print(f"PASS: {len(checks)} compact institutional portal checks")
