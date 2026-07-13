from __future__ import annotations

import json
import urllib.parse
import urllib.request

BASE = "https://sustainablecatalyst.com/wp-json/sustainable-catalyst-library/v1"


def search_library(search: str, per_page: int = 10) -> dict:
    query = urllib.parse.urlencode({"search": search, "per_page": per_page})
    request = urllib.request.Request(f"{BASE}/records?{query}", headers={"Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=15) as response:
        return json.load(response)


if __name__ == "__main__":
    result = search_library("systems thinking")
    for item in result.get("items", []):
        print(item["title"], item["url"])
