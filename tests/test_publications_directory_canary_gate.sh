#!/usr/bin/env bash
# PUB-RELEASE-01 — no WordPress DB/network or production access needed.
set -euo pipefail
fixture="tests/test_publications_directory_canary_gate.php"
cases=(
  'production unset unset disabled'
  'production 0 1 disabled'
  'production 1 unset disabled'
  'production 1 0 disabled'
  'production 1 1 enabled'
  'local 1 unset enabled'
  'staging 1 unset enabled'
  'local 0 1 disabled'
  'staging unset 1 disabled'
  'development 1 1 disabled'
  'unknown 1 1 disabled'
)
for testcase in "${cases[@]}"; do
  read -r env base prod expected <<< "$testcase"
  actual=$(PUB_TEST_WP_ENV="$env" PUB_TEST_BASE_FLAG="$base" PUB_TEST_PROD_FLAG="$prod" php "$fixture")
  if [[ "$actual" != "$expected" ]]; then
    printf 'FAIL %s => %s (wanted %s)\n' "$testcase" "$actual" "$expected" >&2
    exit 1
  fi
  printf 'PASS %s\n' "$testcase"
done
echo "SC-PUB-RELEASE-01: all 11 double-flag gates passed."
