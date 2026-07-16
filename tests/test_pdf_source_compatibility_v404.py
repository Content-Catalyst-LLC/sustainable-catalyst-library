#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
plugin = root / "sustainable-catalyst-library"

main = (plugin / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
pdf_class = (plugin / "includes/class-sc-library-pdf-to-document.php").read_text(encoding="utf-8")
exporter = (plugin / "includes/class-sc-library-public-api-export-federation.php").read_text(encoding="utf-8")
readme = (plugin / "readme.txt").read_text(encoding="utf-8")

checks = {
    "plugin header 4.0.4": "Version: 4.0.4" in main,
    "plugin constant 4.0.4": "SC_LIBRARY_VERSION', '4.0.4" in main,
    "stable tag 4.0.4": "Stable tag: 4.0.4" in readme,
    "canonical PDF meta constant": "public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';" in pdf_class,
    "compatibility alias": "public const META_SOURCE_ATTACHMENT = self::META_PDF_ID;" in pdf_class,
    "exporter uses canonical constant": "SC_Library_PDF_To_Document::META_PDF_ID" in exporter,
    "fatal exporter reference removed": "SC_Library_PDF_To_Document::META_SOURCE_ATTACHMENT" not in exporter,
    "single compatibility alias": pdf_class.count("META_SOURCE_ATTACHMENT") == 1,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(("PASS" if passed else "FAIL") + ": " + name)

if failed:
    print("FAILED: " + ", ".join(failed), file=sys.stderr)
    raise SystemExit(1)

print(f"PASS: {len(checks)} PDF source compatibility checks")
