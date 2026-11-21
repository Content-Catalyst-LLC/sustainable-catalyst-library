#!/usr/bin/env bash
set -euo pipefail
cd "${1:?Pass the dedicated fixture with wp/ and router.php}"
[[ "$(wp --path=wp option get blogname)" == 'Synthetic staging fixture' ]]
[[ "$(wp --path=wp option get home)" == 'http://127.0.0.1:18765' ]]
if curl --noproxy '*' -s --max-time 1 'http://127.0.0.1:18765/' >/dev/null; then
  echo 'Fixture port already in use' >&2
  exit 1
fi
# Fresh benchmark groups use the same PHP worker and disposable database.
: > perf-032.tsv
printf 'flag\tcache\trun\tstatus\tttfb_seconds\ttotal_seconds\tbytes\n' >> perf-032.tsv
for mode in 0 1; do
  SEO031_DIRECTORY="$mode" php -d display_errors=0 -S 127.0.0.1:18765 -t wp router.php >"perf-032-$mode.log" 2>&1 &
  server=$!
  trap 'kill "$server" 2>/dev/null || true' EXIT
  for attempt in {1..20}; do
    curl --noproxy '*' -fsS 'http://127.0.0.1:18765/publications/' -o /dev/null 2>/dev/null && break
    sleep .2
  done
  for cache in cold warm; do
    for n in {1..7}; do
      if [[ "$cache" == cold ]]; then
        wp --path=wp eval '(new SC_Library_Field_Spotlights())->invalidate_model();' --quiet
      fi
      printf '%s\t%s\t%s\t' "$mode" "$cache" "$n" >> perf-032.tsv
      curl --noproxy '*' -fsS --max-time 15 'http://127.0.0.1:18765/publications/' -o /dev/null \
        -w '%{http_code}\t%{time_starttransfer}\t%{time_total}\t%{size_download}\n' >> perf-032.tsv
    done
  done
  kill "$server"
  wait "$server" || true
  trap - EXIT
done
if rg -q 'PHP (Warning|Fatal error|Notice|Deprecated)' perf-032-{0,1}.log; then
  echo 'Unexpected PHP diagnostics in fixture logs' >&2
  exit 1
fi
