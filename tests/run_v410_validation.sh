#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

printf 'Sustainable Catalyst Library v4.1.0 validation\n\n'

grep -q 'Version: 4.1.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "SC_LIBRARY_VERSION', '4.1.0" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q 'Stable tag: 4.1.0' sustainable-catalyst-library/readme.txt
printf 'PASS: release version markers\n'

pytest -q tests/test_homepage_spotlight_v410.py
python3 tests/test_research_library_fatal_recovery_v402.py
python3 tests/test_pdf_source_compatibility_v404.py
python3 tests/test_institutional_portal_fatal_containment_v405.py
python3 tests/test_institutional_portal_compact_layout_v406.py
bash tests/test_library_package_syntax_v405.sh

printf '\nPASS: v4.1.0 current release validation completed.\n'
