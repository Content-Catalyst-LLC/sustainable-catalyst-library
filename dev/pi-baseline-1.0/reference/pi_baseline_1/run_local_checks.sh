#!/usr/bin/env bash
set -Eeuo pipefail
here=$(cd "$(dirname "$0")" && pwd)
bash -n "$here/baseline.sh"
bash -n "$here/rollback-library.sh"
python3 -m py_compile "$here/adapter.py"
php -l "$here/sc-pi-library-bridge.php"
python3 "$here/verify_package.py"
echo 'PASS: static package tests'
