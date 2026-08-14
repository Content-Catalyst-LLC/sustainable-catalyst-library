#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
printf 'Sustainable Catalyst Library v4.1.2 validation\n\n'
grep -q 'Version: 4.1.2' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "SC_LIBRARY_VERSION', '4.1.2" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q 'Stable tag: 4.1.2' sustainable-catalyst-library/readme.txt
printf 'PASS: release version markers\n'
FILES=(
  tests/test_homepage_spotlight_v410.py
  tests/test_homepage_spotlight_source_search_v411.py
  tests/test_homepage_spotlight_console_v412.py
)
if python3 -c 'import pytest' >/dev/null 2>&1; then
  python3 -m pytest -q "${FILES[@]}"
else
  python3 - <<'PYTEST_FALLBACK'
from pathlib import Path
import runpy

for filename in [
    Path('tests/test_homepage_spotlight_v410.py'),
    Path('tests/test_homepage_spotlight_source_search_v411.py'),
    Path('tests/test_homepage_spotlight_console_v412.py'),
]:
    namespace = runpy.run_path(str(filename))
    tests = sorted((name, value) for name, value in namespace.items() if name.startswith('test_') and callable(value))
    for name, test in tests:
        test()
        print(f'PASS: {filename.name}::{name}')
PYTEST_FALLBACK
fi
python3 tests/test_research_library_fatal_recovery_v402.py
python3 tests/test_pdf_source_compatibility_v404.py
python3 tests/test_institutional_portal_fatal_containment_v405.py
python3 tests/test_institutional_portal_compact_layout_v406.py
find sustainable-catalyst-library -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-homepage-spotlight-admin.js
node --check sustainable-catalyst-library/assets/js/sc-library-homepage-spotlight.js
printf '\nPASS: v4.1.2 Knowledge Library Console validation completed.\n'
