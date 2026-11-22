#!/usr/bin/env bash
# Capture a small anonymous GET-only Publications baseline, then the site's sitemap shards.
set -euo pipefail

origin=${1:?Usage: capture-publications-source.sh ORIGIN OUTPUT_DIRECTORY}
output=${2:?Specify a directory for captures}
origin=${origin%/}
mkdir -p "$output"

capture() {
    local name=$1 url=$2
    curl --silent --show-error --location --max-redirs 5 --max-time 40 \
        --dump-header "$output/$name.headers" --output "$output/$name.body" \
        --write-out "$name\t%{http_code}\t%{url_effective}\t%{size_download}\t%{time_starttransfer}\n" "$url"
}

{
    capture publications "$origin/publications/"
    capture panel "$origin/publications/?sc_publication_field=global-governance&sc_publication_panel=common-law-precedent"
    capture panel-invalid "$origin/publications/?sc_publication_panel=does-not-exist"
    capture hub-page2 "$origin/publications/page/2/"
    capture field "$origin/category/global-governance/"
    capture field-page2 "$origin/category/global-governance/page/2/"
    capture map "$origin/international-law/"
    capture article "$origin/jus-cogens-erga-omnes-and-peremptory-norms-in-international-law/"
    capture research "$origin/research/"
    capture library "$origin/library/"
    capture robots.txt "$origin/robots.txt"
    capture sitemap_index.xml "$origin/sitemap_index.xml"
    capture wp-sitemap.xml "$origin/wp-sitemap.xml"
} | tee "$output/http.tsv"

python3 - "$origin" "$output/sitemap_index.xml.body" > "$output/sitemap-children.tsv" <<'PY'
import sys
from urllib.parse import urlsplit
from xml.etree import ElementTree

origin = urlsplit(sys.argv[1])
tree = ElementTree.parse(sys.argv[2])
for index, node in enumerate(tree.getroot().findall("{*}sitemap/{*}loc"), 1):
    url = urlsplit(node.text)
    if (url.scheme, url.netloc) == (origin.scheme, origin.netloc):
        print(f"child-{index}-sitemap.xml\t{node.text}")
PY
while IFS=$'\t' read -r name url; do
    capture "$name" "$url"
done < "$output/sitemap-children.tsv" | tee -a "$output/http.tsv"
