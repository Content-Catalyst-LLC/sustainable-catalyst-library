from __future__ import annotations

import os
from urllib.parse import quote
from typing import Any

LIBRARY_VERSION = "5.70.0"
BACKEND_VERSION = "2.81.0"
WEB_VERSION = "1.2.0"
CONTRACT = "sc-library-public-routing-seo-embed-bridge/1.0"
READINESS_CONTRACT = "sc-library-public-routing-readiness/1.0"
SEO_CONTRACT = "sc-library-public-seo-descriptor/1.0"
EMBED_CONTRACT = "sc-library-public-embed-descriptor/1.0"
DEFAULT_PUBLIC_ORIGIN = "https://library.sustainablecatalyst.com"

PUBLIC_ROUTES: tuple[dict[str, Any], ...] = (
    {"name":"home","path":"/","index":True,"embed":False},
    {"name":"search","path":"/search","index":False,"embed":False},
    {"name":"record","path":"/record/{record_id}","index":True,"embed":True},
    {"name":"discover","path":"/discover","index":True,"embed":False},
    {"name":"system","path":"/system","index":False,"embed":False},
    {"name":"account","path":"/account","index":False,"embed":False},
)


def public_origin() -> str:
    raw = os.getenv("SC_LIBRARY_PUBLIC_ORIGIN", DEFAULT_PUBLIC_ORIGIN).strip().rstrip("/")
    if not raw.startswith(("https://", "http://")):
        return DEFAULT_PUBLIC_ORIGIN
    return raw


def canonical_url(route: str, *, record_id: str | None = None) -> str:
    origin = public_origin()
    if route == "record":
        if not record_id:
            raise ValueError("record_id required for record canonical URL")
        return f"{origin}/record/{quote(str(record_id), safe='')}"
    mapping = {item["name"]: item["path"] for item in PUBLIC_ROUTES}
    if route not in mapping:
        raise ValueError("unknown public route")
    return origin + ("" if mapping[route] == "/" else mapping[route])


def guardrails() -> dict[str, bool]:
    return {
        "public_origin_is_library_web": True,
        "wordpress_is_canonical_research_runtime": False,
        "wordpress_may_emit_seo_bridge_metadata": True,
        "wordpress_may_emit_launch_links": True,
        "wordpress_may_emit_sandboxed_embeds": True,
        "wordpress_proxy_is_required_for_public_app": False,
        "public_route_changes_research_authority": False,
        "seo_descriptor_implies_research_truth": False,
        "embed_grants_write_authority": False,
        "account_and_system_routes_are_indexable": False,
        "search_result_pages_are_indexable": False,
    }


def contract() -> dict[str, Any]:
    origin = public_origin()
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "public_origin": origin,
        "routing_model": "independent-library-web-canonical-origin",
        "routes": [dict(x, canonical=(canonical_url(x["name"]) if x["name"] != "record" else origin + "/record/{record_id}")) for x in PUBLIC_ROUTES],
        "wordpress_bridge": {
            "role": "thin-adapter-public-bridge",
            "authoritative": False,
            "proxy_required": False,
            "may_emit_canonical_links": True,
            "may_emit_public_metadata": True,
            "may_emit_launch_links": True,
            "may_emit_sandboxed_embeds": True,
        },
        "seo": {
            "record_indexing": True,
            "search_indexing": False,
            "account_indexing": False,
            "system_indexing": False,
            "canonical_authority": "library-public-origin",
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    c = contract()
    origin = c["public_origin"]
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "state": "ready" if origin.startswith("https://") else "degraded",
        "public_origin": origin,
        "route_count": len(PUBLIC_ROUTES),
        "wordpress_required": False,
        "guardrails": c["guardrails"],
    }


def record_seo_descriptor(record_id: str, record: dict[str, Any]) -> dict[str, Any]:
    title = str(record.get("title") or record.get("name") or record.get("label") or record_id)
    description = str(record.get("abstract") or record.get("summary") or record.get("description") or "Research record in the Sustainable Catalyst Knowledge Library.")
    description = " ".join(description.split())[:300]
    return {
        "schema": SEO_CONTRACT,
        "record_id": record_id,
        "title": title,
        "description": description,
        "canonical_url": canonical_url("record", record_id=record_id),
        "robots": "index,follow",
        "open_graph": {"type":"article","title":title,"description":description,"url":canonical_url("record", record_id=record_id)},
        "guardrails": {"descriptor_is_public_metadata": True, "descriptor_implies_research_truth": False},
    }


def record_embed_descriptor(record_id: str, record: dict[str, Any] | None = None) -> dict[str, Any]:
    title = str((record or {}).get("title") or (record or {}).get("name") or "Knowledge Library record")
    src = canonical_url("record", record_id=record_id) + "?embed=1"
    return {
        "schema": EMBED_CONTRACT,
        "record_id": record_id,
        "title": title,
        "src": src,
        "sandbox": "allow-scripts allow-same-origin allow-popups",
        "allow": "clipboard-read; clipboard-write",
        "loading": "lazy",
        "write_authority": False,
        "wordpress_required": False,
    }
