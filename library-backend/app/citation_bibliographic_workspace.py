from __future__ import annotations

from hashlib import sha256
import json
import re
from typing import Any

try:
    from .provenance_graph_service import readiness as provenance_citation_readiness
except Exception:  # local/synthetic validation fallback
    provenance_citation_readiness = None

LIBRARY_VERSION = "6.17.0"
BACKEND_VERSION = "3.17.0"
WEB_VERSION = "2.17.0"
SDK_VERSION = "1.17.0"

CONTRACT = "sc-library-citation-workspace-bibliographic-intelligence/1.0"
READINESS_CONTRACT = "sc-library-citation-workspace-bibliographic-intelligence-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-citation-workspace-bibliographic-intelligence-bootstrap/1.0"
ITEM_CONTRACT = "sc-library-bibliographic-item/1.0"
BIBLIOGRAPHY_CONTRACT = "sc-library-bibliography-collection/1.0"
DUPLICATE_CONTRACT = "sc-library-bibliographic-duplicate-analysis/1.0"
EXPORT_CONTRACT = "sc-library-bibliography-export/1.0"
HANDOFF_CONTRACT = "sc-library-citation-authority-handoff-preview/1.0"

MAX_ITEMS = 2000
MAX_AUTHORS = 200
MAX_TAGS = 100

ITEM_TYPES = (
    "article-journal", "book", "chapter", "report", "thesis", "webpage",
    "dataset", "paper-conference", "manuscript", "standard", "patent",
    "legal_case", "entry-encyclopedia", "entry-dictionary", "software",
    "personal_communication", "post", "motion_picture", "other",
)
EXPORT_FORMATS = ("json", "bibtex", "ris")


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 6000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _slug(value: str, limit: int = 40) -> str:
    s = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return s[:limit].strip("-") or "item"


def _normalize_doi(value: Any) -> str | None:
    s = _clean(value, 500).lower()
    s = re.sub(r"^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)", "", s).strip()
    return s or None


def _normalize_isbn(value: Any) -> str | None:
    s = re.sub(r"[^0-9Xx]", "", _clean(value, 100)).upper()
    return s if len(s) in {10, 13} else (s or None)


def _normalize_arxiv(value: Any) -> str | None:
    s = _clean(value, 200)
    s = re.sub(r"^(?:arxiv:|https?://arxiv\.org/(?:abs|pdf)/)", "", s, flags=re.I)
    s = re.sub(r"\.pdf$", "", s, flags=re.I).strip()
    return s or None


def _person(item: Any) -> dict[str, Any] | None:
    if isinstance(item, str):
        raw = _clean(item, 500)
        if not raw:
            return None
        if "," in raw:
            family, given = [x.strip() for x in raw.split(",", 1)]
        else:
            bits = raw.split()
            family, given = (bits[-1], " ".join(bits[:-1])) if len(bits) > 1 else (raw, "")
        return {"family": family or None, "given": given or None, "literal": None}
    row = _dict(item)
    literal = _clean(row.get("literal") or row.get("name"), 500)
    family = _clean(row.get("family") or row.get("last") or row.get("surname"), 300)
    given = _clean(row.get("given") or row.get("first") or row.get("given_name"), 300)
    if not any((literal, family, given)):
        return None
    return {"family": family or None, "given": given or None, "literal": literal or None}


def _year(raw: dict[str, Any]) -> int | None:
    value = raw.get("year") or raw.get("issued_year")
    if isinstance(value, int):
        return value
    s = _clean(value, 20)
    if re.fullmatch(r"-?\d{1,4}", s):
        return int(s)
    issued = raw.get("issued") or raw.get("date")
    if isinstance(issued, dict):
        dp = issued.get("date-parts") or issued.get("date_parts")
        if isinstance(dp, list) and dp and isinstance(dp[0], list) and dp[0] and isinstance(dp[0][0], int):
            return int(dp[0][0])
        literal = _clean(issued.get("literal"), 100)
        m = re.search(r"(?<!\d)(-?\d{4})(?!\d)", literal)
        if m:
            return int(m.group(1))
    else:
        m = re.search(r"(?<!\d)(-?\d{4})(?!\d)", _clean(issued, 100))
        if m:
            return int(m.group(1))
    return None


def guardrails() -> dict[str, bool]:
    return {
        "python_is_bibliographic_workspace_authority": True,
        "existing_provenance_citation_service_remains_durable_citation_authority": True,
        "workspace_creates_durable_citation_edges_automatically": False,
        "bibliographic_item_is_evidence_truth": False,
        "citation_presence_implies_support": False,
        "citation_count_implies_quality": False,
        "identifier_presence_implies_source_validity": False,
        "doi_presence_implies_peer_review": False,
        "metadata_completeness_implies_quality": False,
        "duplicate_candidate_implies_same_work": False,
        "duplicate_candidates_are_auto_merged": False,
        "generated_citation_key_is_globally_unique": False,
        "display_template_is_full_csl_processor": False,
        "export_is_automatic_import": False,
        "bibliography_order_implies_rank": False,
        "annotations_are_auto_promoted_to_citations": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "bibliographic-item-normalization",
        "csl-compatible-metadata-envelope",
        "doi-isbn-pmid-pmcid-arxiv-url-identifier-normalization",
        "deterministic-local-citation-keys",
        "metadata-completeness-observations",
        "duplicate-candidate-analysis-without-auto-merge",
        "bibliography-collection-composition",
        "json-bibtex-ris-export",
        "citation-authority-handoff-preview",
        "standalone-web-route-/research/citations",
    ]
    basis = {"resources": resources, "item_types": ITEM_TYPES, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "workspace_id": "citation-workspace-bibliographic-intelligence:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "state": "authoritative-composition",
        "authority": "python-backend",
        "route": "/research/citations",
        "durable_citation_authority": "python-provenance-citation-service-v6.3-lineage",
        "resources": resources,
        "browser_local_collection": True,
        "browser_local_is_authoritative": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    dep: dict[str, Any] = {"state": "unknown", "authority": "python-backend"}
    if provenance_citation_readiness is not None:
        try:
            raw = provenance_citation_readiness()
            dep = {
                "state": raw.get("state"),
                "library_version": raw.get("library_version"),
                "backend_version": raw.get("backend_version"),
                "database": raw.get("database"),
                "authority": raw.get("authority"),
            }
        except Exception as exc:
            dep = {"state": "degraded", "detail": str(exc), "authority": "python-backend"}
    blocking: list[str] = []
    degraded: list[str] = []
    if dep.get("state") in {"failed", "blocked", "unavailable"}:
        degraded.append("durable-citation-authority:" + str(dep.get("state")))
    elif dep.get("state") not in {"ready", "unknown", None}:
        degraded.append("durable-citation-authority:" + str(dep.get("state")))
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "degraded" if degraded else "ready",
        "ready": True,
        "blocking": blocking,
        "degraded": degraded,
        "dependencies": {"provenance_citation_service": dep},
        "server_side_bibliography_persistence": False,
        "existing_durable_citation_authority_preserved": True,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "route": "/research/citations",
        "readiness": readiness(),
        "item_types": list(ITEM_TYPES),
        "export_formats": list(EXPORT_FORMATS),
        "identifier_schemes": ["doi", "isbn", "issn", "pmid", "pmcid", "arxiv", "url", "other"],
        "duplicate_rules": ["exact-doi", "exact-pmid", "exact-pmcid", "exact-isbn", "exact-arxiv", "exact-url", "normalized-title-first-author-year"],
        "browser_storage_key": "sc-library-bibliography-v1",
        "guardrails": guardrails(),
    }


def normalize_bibliographic_item(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("item") if isinstance(payload, dict) and isinstance(payload.get("item"), dict) else payload)
    title = _clean(raw.get("title"), 4000)
    if not title:
        raise ValueError("bibliographic item title is required")
    item_type = _clean(raw.get("type") or raw.get("item_type"), 100).lower() or "other"
    if item_type not in ITEM_TYPES:
        item_type = "other"
    authors = [p for p in (_person(x) for x in _list(raw.get("author") or raw.get("authors"))[:MAX_AUTHORS]) if p]
    editors = [p for p in (_person(x) for x in _list(raw.get("editor") or raw.get("editors"))[:MAX_AUTHORS]) if p]
    year = _year(raw)
    identifiers = {
        "doi": _normalize_doi(raw.get("doi") or _dict(raw.get("identifiers")).get("doi")),
        "isbn": _normalize_isbn(raw.get("isbn") or _dict(raw.get("identifiers")).get("isbn")),
        "issn": _clean(raw.get("issn") or _dict(raw.get("identifiers")).get("issn"), 100) or None,
        "pmid": _clean(raw.get("pmid") or _dict(raw.get("identifiers")).get("pmid"), 100) or None,
        "pmcid": _clean(raw.get("pmcid") or _dict(raw.get("identifiers")).get("pmcid"), 100) or None,
        "arxiv": _normalize_arxiv(raw.get("arxiv") or _dict(raw.get("identifiers")).get("arxiv")),
        "url": _clean(raw.get("url") or _dict(raw.get("identifiers")).get("url"), 4000) or None,
    }
    first_author = next((a.get("family") or a.get("literal") for a in authors if a.get("family") or a.get("literal")), "anon")
    key_basis = f"{_slug(str(first_author),20)}-{year or 'nd'}-{_slug(title,28)}"
    citation_key = _clean(raw.get("citation_key"), 200) or (key_basis + "-" + _fp({"title": title, "authors": authors, "year": year})[:6])
    issued = {"date-parts": [[year]]} if year is not None else ({"literal": _clean(raw.get("issued") or raw.get("date"), 300)} if _clean(raw.get("issued") or raw.get("date"), 300) else None)
    csl = {
        "id": citation_key,
        "type": item_type,
        "title": title,
        "author": [{k: v for k, v in a.items() if v} for a in authors],
        "editor": [{k: v for k, v in a.items() if v} for a in editors],
        "issued": issued,
        "container-title": _clean(raw.get("container_title") or raw.get("journal") or raw.get("book_title"), 2000) or None,
        "publisher": _clean(raw.get("publisher"), 1000) or None,
        "publisher-place": _clean(raw.get("publisher_place") or raw.get("place"), 1000) or None,
        "volume": _clean(raw.get("volume"), 100) or None,
        "issue": _clean(raw.get("issue"), 100) or None,
        "page": _clean(raw.get("page") or raw.get("pages"), 200) or None,
        "DOI": identifiers["doi"],
        "ISBN": identifiers["isbn"],
        "ISSN": identifiers["issn"],
        "URL": identifiers["url"],
    }
    csl = {k: v for k, v in csl.items() if v not in (None, [], "")}
    completeness_fields = ["title", "type", "author", "issued"]
    optional_fields = ["container-title", "publisher", "DOI", "ISBN", "URL"]
    missing = [f for f in completeness_fields if f not in csl]
    present_optional = [f for f in optional_fields if f in csl]
    basis = {"csl": csl, "identifiers": identifiers, "citation_key": citation_key}
    return {
        "schema": ITEM_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "bibliographic_item_id": _clean(raw.get("bibliographic_item_id"), 500) or "bibliographic-item:" + _fp(basis)[:32],
        "bibliographic_fingerprint_sha256": _fp(basis),
        "citation_key": citation_key,
        "citation_key_globally_unique": False,
        "type": item_type,
        "title": title,
        "authors": authors,
        "editors": editors,
        "year": year,
        "identifiers": identifiers,
        "csl": csl,
        "abstract": _clean(raw.get("abstract"), 12000) or None,
        "notes": _clean(raw.get("notes"), 12000) or None,
        "tags": sorted({_clean(x, 100) for x in _list(raw.get("tags"))[:MAX_TAGS] if _clean(x, 100)}),
        "linked_record_id": _clean(raw.get("linked_record_id") or raw.get("record_id"), 500) or None,
        "linked_annotation_ids": sorted({_clean(x, 500) for x in _list(raw.get("linked_annotation_ids")) if _clean(x, 500)}),
        "metadata_completeness": {"missing_core_fields": missing, "present_optional_fields": present_optional, "is_quality_score": False},
        "truth_status": None,
        "evidence_status": None,
        "persisted": False,
        "guardrails": guardrails(),
    }


def duplicate_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    items = [normalize_bibliographic_item(_dict(x)) for x in _list(raw.get("items"))[:MAX_ITEMS] if isinstance(x, dict)]
    candidates: list[dict[str, Any]] = []
    for i, a in enumerate(items):
        for b in items[i + 1:]:
            rules: list[str] = []
            for key, rule in (("doi", "exact-doi"), ("pmid", "exact-pmid"), ("pmcid", "exact-pmcid"), ("isbn", "exact-isbn"), ("arxiv", "exact-arxiv"), ("url", "exact-url")):
                av = (a.get("identifiers") or {}).get(key); bv = (b.get("identifiers") or {}).get(key)
                if av and bv and str(av).lower() == str(bv).lower():
                    rules.append(rule)
            at = re.sub(r"\W+", "", a["title"].casefold()); bt = re.sub(r"\W+", "", b["title"].casefold())
            aa = ((a.get("authors") or [{}])[0].get("family") or (a.get("authors") or [{}])[0].get("literal") or "").casefold() if a.get("authors") else ""
            ba = ((b.get("authors") or [{}])[0].get("family") or (b.get("authors") or [{}])[0].get("literal") or "").casefold() if b.get("authors") else ""
            if at and at == bt and a.get("year") == b.get("year") and aa and aa == ba:
                rules.append("normalized-title-first-author-year")
            if rules:
                candidates.append({
                    "item_a": a["bibliographic_item_id"], "item_b": b["bibliographic_item_id"],
                    "rules": sorted(set(rules)), "same_work_asserted": False,
                    "auto_merged": False, "human_review_required": True,
                })
    basis = {"items": [x["bibliographic_fingerprint_sha256"] for x in items], "candidates": candidates}
    return {
        "schema": DUPLICATE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "analysis_id": "bibliographic-duplicates:" + _fp(basis)[:32],
        "item_count": len(items),
        "candidate_count": len(candidates),
        "candidates": candidates,
        "automatic_merge": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }


def build_bibliography(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    items = [normalize_bibliographic_item(_dict(x)) for x in _list(raw.get("items"))[:MAX_ITEMS] if isinstance(x, dict)]
    if not items:
        raise ValueError("bibliography requires at least one item")
    seen: dict[str, int] = {}
    for item in items:
        base = item["citation_key"]
        seen[base] = seen.get(base, 0) + 1
        if seen[base] > 1:
            item["citation_key"] = f"{base}-{seen[base]}"
            item["csl"]["id"] = item["citation_key"]
    sort_mode = _clean(raw.get("sort") or "author-year-title", 100).lower()
    if sort_mode == "title":
        ordered = sorted(items, key=lambda x: x["title"].casefold())
    else:
        def k(x: dict[str, Any]):
            auth = x.get("authors") or []
            first = (auth[0].get("family") or auth[0].get("literal") or "") if auth else ""
            return (str(first).casefold(), x.get("year") if x.get("year") is not None else 99999, x["title"].casefold())
        ordered = sorted(items, key=k)
    basis = {"title": _clean(raw.get("title"), 1000), "items": [x["bibliographic_fingerprint_sha256"] for x in ordered], "sort": sort_mode}
    return {
        "schema": BIBLIOGRAPHY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "bibliography_id": "bibliography:" + _fp(basis)[:32],
        "bibliography_fingerprint_sha256": _fp(basis),
        "title": _clean(raw.get("title"), 1000) or "Research bibliography",
        "item_count": len(ordered),
        "sort": sort_mode,
        "items": ordered,
        "duplicate_analysis": duplicate_analysis({"items": ordered}),
        "bibliography_order_implies_rank": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def _author_display(item: dict[str, Any]) -> str:
    names = []
    for a in item.get("authors") or []:
        if a.get("literal"):
            names.append(a["literal"])
        elif a.get("family") and a.get("given"):
            names.append(f"{a['family']}, {a['given']}")
        elif a.get("family"):
            names.append(a["family"])
    return "; ".join(names) or "Anonymous"


def _bibtex_type(item_type: str) -> str:
    return {"article-journal": "article", "book": "book", "chapter": "incollection", "thesis": "phdthesis", "paper-conference": "inproceedings", "report": "techreport"}.get(item_type, "misc")


def _escape_bib(value: Any) -> str:
    return str(value or "").replace("\\", "\\\\").replace("{", "\\{").replace("}", "\\}")


def export_bibliography(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    bibliography = build_bibliography(raw)
    fmt = _clean(raw.get("format") or "json", 40).lower()
    if fmt not in EXPORT_FORMATS:
        raise ValueError("format must be one of: " + ", ".join(EXPORT_FORMATS))
    items = bibliography["items"]
    if fmt == "json":
        content = json.dumps([x["csl"] for x in items], ensure_ascii=False, indent=2, sort_keys=True)
        media_type = "application/json"
    elif fmt == "bibtex":
        chunks = []
        for item in items:
            csl = item["csl"]; fields = [("title", csl.get("title")), ("author", " and ".join(_author_display({"authors": [a]}) for a in item.get("authors") or [])), ("year", item.get("year")), ("journal", csl.get("container-title")), ("publisher", csl.get("publisher")), ("volume", csl.get("volume")), ("number", csl.get("issue")), ("pages", csl.get("page")), ("doi", csl.get("DOI")), ("url", csl.get("URL"))]
            body = ",\n".join(f"  {k} = {{{_escape_bib(v)}}}" for k, v in fields if v not in (None, ""))
            chunks.append(f"@{_bibtex_type(item['type'])}{{{item['citation_key']},\n{body}\n}}")
        content = "\n\n".join(chunks) + "\n"
        media_type = "application/x-bibtex"
    else:
        rows = []
        for item in items:
            rows.extend(["TY  - " + ("JOUR" if item["type"] == "article-journal" else "GEN"), "TI  - " + item["title"]])
            for a in item.get("authors") or []:
                rows.append("AU  - " + (a.get("literal") or ", ".join(x for x in [a.get("family"), a.get("given")] if x)))
            if item.get("year") is not None: rows.append("PY  - " + str(item["year"]))
            ids = item.get("identifiers") or {}
            if ids.get("doi"): rows.append("DO  - " + ids["doi"])
            if ids.get("url"): rows.append("UR  - " + ids["url"])
            rows.append("ER  - "); rows.append("")
        content = "\n".join(rows)
        media_type = "application/x-research-info-systems"
    basis = {"bibliography": bibliography["bibliography_fingerprint_sha256"], "format": fmt, "content_sha256": sha256(content.encode("utf-8")).hexdigest()}
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "export_id": "bibliography-export:" + _fp(basis)[:32],
        "format": fmt,
        "media_type": media_type,
        "filename": f"sustainable-catalyst-bibliography.{ {'json':'json','bibtex':'bib','ris':'ris'}[fmt] }",
        "content": content,
        "content_sha256": sha256(content.encode("utf-8")).hexdigest(),
        "item_count": len(items),
        "persisted": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }


def citation_authority_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    item = normalize_bibliographic_item(_dict(raw.get("item") or raw))
    citing_record_id = _clean(raw.get("citing_record_id"), 500) or None
    cited_record_id = _clean(raw.get("cited_record_id") or item.get("linked_record_id"), 500) or None
    external_target = None if cited_record_id else {"title": item["title"], "identifiers": item["identifiers"], "csl": item["csl"]}
    return {
        "schema": HANDOFF_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-provenance-citation-service-v6.3-lineage",
        "citing_record_id": citing_record_id,
        "cited_record_id": cited_record_id,
        "external_target": external_target,
        "ready_for_durable_record_edge": bool(citing_record_id and cited_record_id),
        "automatic_persistence": False,
        "requires_explicit_signed_write": True,
        "workspace_did_not_create_citation": True,
        "guardrails": guardrails(),
    }
