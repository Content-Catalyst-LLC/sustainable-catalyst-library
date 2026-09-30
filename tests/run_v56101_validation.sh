#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.61.0.1 / Library Web Port Allocation & Deployment Collision Repair validation ==="
./tests/run_v5610_validation.sh
python3 - <<'PY'
import importlib.util
from pathlib import Path
p=Path('tests/test_library_web_port_repair_v56101.py')
spec=importlib.util.spec_from_file_location('v56101_test', p)
m=importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
m.test_compose_port_is_configurable(); m.test_deployment_architecture_documented()
print('PASS: tests/test_library_web_port_repair_v56101.py: 2 assertions')
PY
python3 - <<'PY'
from pathlib import Path
text=Path('library-web/compose.yml').read_text()
assert '${SC_LIBRARY_WEB_BIND_PORT:-8092}' in text
assert '8091:8080' not in text
print('PASS: Library Web host port is configurable and no longer hardcoded to 8091')
PY
printf 'PASS: Knowledge Library v5.61.0.1 deployment collision repair validation complete\n'
