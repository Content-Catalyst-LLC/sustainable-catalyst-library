#!/usr/bin/env bash
set -euo pipefail
cd "${1:?Pass the dedicated fixture directory containing wp/ and router.php}"
port=18765
wp --path=wp eval "(new SC_Library_Field_Spotlights())->public_model();" >/dev/null 2>fixture-model.log
for mode in 0 1; do
  if curl --noproxy '*' -s --max-time 1 "http://127.0.0.1:$port/" >/dev/null; then
    echo "Fixture port already in use" >&2
    exit 1
  fi
  SEO031_DIRECTORY="$mode" php -d display_errors=0 -S "127.0.0.1:$port" -t wp router.php >"php-$mode.log" 2>&1 &
  server=$!
  trap 'kill "$server" 2>/dev/null || true' EXIT
  for j in {1..30}; do
    if curl --noproxy '*' -s --max-time 3 "http://127.0.0.1:$port/publications/" -o "hub-$mode.html" -D "hub-$mode.headers"; then break; fi
    sleep .2
  done
  grep -q 'HTTP/1.1 200' "hub-$mode.headers"
  curl --noproxy '*' -fsS "http://127.0.0.1:$port/international-law/" -o "map-$mode.html"
  curl --noproxy '*' -fsS "http://127.0.0.1:$port/publications/?sc_publication_field=global-governance&sc_publication_panel=ancient-near-eastern-law-early-legal-codes" -o "parameter-$mode.html" -D "parameter-$mode.headers"
  kill "$server"
  wait "$server" || true
  trap - EXIT
done
SEO031_DIRECTORY=1 wp --path=wp eval 'require_once SC_LIBRARY_DIR . "includes/class-sc-library-publications-directory.php"; echo wp_json_encode((new SC_Library_Publications_Directory())->classify((new SC_Library_Field_Spotlights())->public_model()));' > classifications.json
