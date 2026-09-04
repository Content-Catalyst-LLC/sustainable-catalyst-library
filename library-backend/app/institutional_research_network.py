from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib
import json
import re
from typing import Any, Iterable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from xml.etree import ElementTree as ET


class InstitutionalResearchNetworkError(RuntimeError):
    """Contained institutional-network failure."""


@dataclass(frozen=True)
class InstitutionalNetworkSourceDescriptor:
    key: str
    institution: str
    repository: str
    source_family: str
    base_url: str
    search_mode: str
    capabilities: tuple[str, ...]
    public_metadata: bool = True
    affiliation_asserted: bool = False
    endorsement_asserted: bool = False

    def to_dict(self) -> dict[str, Any]:
        row = asdict(self)
        row["capabilities"] = list(self.capabilities)
        return row


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _text(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, list):
        return "; ".join(item for item in (_text(v) for v in value) if item)
    if isinstance(value, dict):
        return json.dumps(value, sort_keys=True, ensure_ascii=False)
    return str(value).strip()


def _unique(values: Iterable[str]) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for value in values:
        item = str(value or "").strip()
        key = item.casefold()
        if item and key not in seen:
            seen.add(key)
            out.append(item)
    return out


def normalize_doi(value: str | None) -> str | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    raw = re.sub(r"^https?://(?:dx\.)?doi\.org/", "", raw, flags=re.I)
    raw = re.sub(r"^doi:\s*", "", raw, flags=re.I).strip()
    match = re.search(r"10\.\d{4,9}/[-._;()/:A-Z0-9]+", raw, flags=re.I)
    if not match:
        return None
    return match.group(0).rstrip(".,;)").lower()


def _license_observation(name: str | None = None, url: str | None = None) -> dict[str, Any]:
    label = str(name or "").strip()
    link = str(url or "").strip()
    haystack = f"{label} {link}".upper()
    commercial: bool | None = None
    if "CC0" in haystack or "CC BY 4" in haystack or "CC-BY-4" in haystack or "/BY/4" in haystack:
        commercial = True
    if "BY-NC" in haystack or "NONCOMMERCIAL" in haystack:
        commercial = False
    return {
        "name": label or None,
        "url": link or None,
        "commercial_reuse": commercial,
        "reuse_requires_review": commercial is None,
    }


def _stable_hash(payload: Any) -> str:
    data = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    return hashlib.sha256(data).hexdigest()


class _HTTPConnector:
    descriptor: InstitutionalNetworkSourceDescriptor

    def __init__(self, *, timeout_seconds: int = 8, user_agent: str = "SustainableCatalystLibrary/2.0") -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.user_agent = user_agent

    def _get_json(self, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
        query = urlencode({k: v for k, v in (params or {}).items() if v is not None}, doseq=True)
        url = self.descriptor.base_url.rstrip("/") + path + ("?" + query if query else "")
        req = Request(url, headers={"Accept": "application/json", "User-Agent": self.user_agent})
        try:
            with urlopen(req, timeout=self.timeout_seconds) as response:
                return json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise InstitutionalResearchNetworkError(
                f"{self.descriptor.key} request failed: {exc.__class__.__name__}"
            ) from exc

    def _get_xml(self, path: str, params: dict[str, Any] | None = None) -> bytes:
        query = urlencode({k: v for k, v in (params or {}).items() if v is not None}, doseq=True)
        url = self.descriptor.base_url.rstrip("/") + path + ("?" + query if query else "")
        req = Request(url, headers={"Accept": "application/xml,text/xml;q=0.9,*/*;q=0.1", "User-Agent": self.user_agent})
        try:
            with urlopen(req, timeout=self.timeout_seconds) as response:
                return response.read()
        except (HTTPError, URLError, TimeoutError) as exc:
            raise InstitutionalResearchNetworkError(
                f"{self.descriptor.key} request failed: {exc.__class__.__name__}"
            ) from exc


class DataverseNetworkConnector(_HTTPConnector):
    def __init__(self, descriptor: InstitutionalNetworkSourceDescriptor, *, timeout_seconds: int = 8) -> None:
        self.descriptor = descriptor
        super().__init__(timeout_seconds=timeout_seconds)

    def search(self, query: str, *, limit: int = 8) -> dict[str, Any]:
        limit = max(1, min(int(limit), 25))
        payload = self._get_json("/api/search", {"q": query or "*", "type": "dataset", "per_page": limit, "start": 0})
        if not isinstance(payload, dict) or payload.get("status") not in {None, "OK"}:
            raise InstitutionalResearchNetworkError(f"{self.descriptor.key} returned an invalid Dataverse response")
        data = payload.get("data", {}) if isinstance(payload.get("data"), dict) else {}
        items = data.get("items", []) if isinstance(data.get("items"), list) else []
        records = [self._normalize(item) for item in items if isinstance(item, dict)]
        return {
            "records": records,
            "total": int(data.get("total_count", len(records)) or 0),
            "search_mode": "native-search",
            "search_limitations": [],
        }

    def _normalize(self, item: dict[str, Any]) -> dict[str, Any]:
        pid = _text(item.get("global_id") or item.get("persistentId"))
        doi = normalize_doi(pid or _text(item.get("url")))
        return {
            "source_key": self.descriptor.key,
            "institution": self.descriptor.institution,
            "repository": self.descriptor.repository,
            "source_family": self.descriptor.source_family,
            "record_type": (_text(item.get("type")) or "dataset").lower(),
            "title": _text(item.get("name")) or "Untitled research record",
            "persistent_id": pid or None,
            "doi": doi,
            "authors": _unique(str(v) for v in (item.get("authors") if isinstance(item.get("authors"), list) else [])),
            "description": _text(item.get("description")) or None,
            "subjects": _unique(str(v) for v in (item.get("subjects") if isinstance(item.get("subjects"), list) else [])),
            "keywords": _unique(str(v) for v in (item.get("keywords") if isinstance(item.get("keywords"), list) else [])),
            "published_at": _text(item.get("published_at")) or None,
            "updated_at": _text(item.get("updated_at")) or None,
            "source_url": _text(item.get("url")) or None,
            "citation": _text(item.get("citation") or item.get("citationHtml")) or None,
            "license": _license_observation(_text(item.get("license")) or None),
            "access_state": "public-metadata",
            "provenance": {
                "source_key": self.descriptor.key,
                "source_family": self.descriptor.source_family,
                "repository": self.descriptor.repository,
                "retrieved_from": self.descriptor.base_url,
            },
        }


def _metadata_values(metadata: Any, names: Iterable[str]) -> list[str]:
    if not isinstance(metadata, dict):
        return []
    out: list[str] = []
    for name in names:
        rows = metadata.get(name)
        if not isinstance(rows, list):
            continue
        for row in rows:
            if isinstance(row, dict):
                value = _text(row.get("value"))
            else:
                value = _text(row)
            if value:
                out.append(value)
    return _unique(out)


class DSpaceNetworkConnector(_HTTPConnector):
    def __init__(self, descriptor: InstitutionalNetworkSourceDescriptor, *, timeout_seconds: int = 8) -> None:
        self.descriptor = descriptor
        super().__init__(timeout_seconds=timeout_seconds)

    def search(self, query: str, *, limit: int = 8) -> dict[str, Any]:
        limit = max(1, min(int(limit), 25))
        payload = self._get_json("/server/api/discover/search/objects", {"query": query, "page": 0, "size": limit})
        embedded = payload.get("_embedded", {}) if isinstance(payload, dict) else {}
        result = embedded.get("searchResult", {}) if isinstance(embedded, dict) else {}
        result_embedded = result.get("_embedded", {}) if isinstance(result, dict) else {}
        objects = result_embedded.get("objects", []) if isinstance(result_embedded, dict) else []
        if not objects and isinstance(embedded, dict):
            objects = embedded.get("objects", []) if isinstance(embedded.get("objects"), list) else []
        if not isinstance(objects, list):
            objects = []
        records: list[dict[str, Any]] = []
        for wrapper in objects:
            if not isinstance(wrapper, dict):
                continue
            obj = wrapper.get("indexableObject") if isinstance(wrapper.get("indexableObject"), dict) else wrapper
            if not isinstance(obj, dict):
                continue
            if _text(obj.get("type")).lower() not in {"", "item"}:
                continue
            records.append(self._normalize(obj))
        page = payload.get("page", {}) if isinstance(payload, dict) else {}
        total = int(page.get("totalElements", len(records)) or len(records)) if isinstance(page, dict) else len(records)
        return {
            "records": records[:limit],
            "total": total,
            "search_mode": "native-search",
            "search_limitations": [],
        }

    def _normalize(self, item: dict[str, Any]) -> dict[str, Any]:
        metadata = item.get("metadata") if isinstance(item.get("metadata"), dict) else {}
        title = _text(item.get("name")) or (_metadata_values(metadata, ["dc.title"])[0] if _metadata_values(metadata, ["dc.title"]) else "Untitled research record")
        identifiers = _metadata_values(metadata, ["dc.identifier.uri", "dc.identifier", "dc.identifier.doi"])
        doi = None
        for identifier in identifiers:
            doi = normalize_doi(identifier)
            if doi:
                break
        handle = next((v for v in identifiers if "hdl.handle.net" in v or re.search(r"\b\d+/\d+\b", v)), None)
        uuid = _text(item.get("uuid") or item.get("id"))
        source_url = next((v for v in identifiers if v.startswith("http://") or v.startswith("https://")), None)
        if not source_url and handle:
            source_url = handle if handle.startswith("http") else f"https://hdl.handle.net/{handle}"
        return {
            "source_key": self.descriptor.key,
            "institution": self.descriptor.institution,
            "repository": self.descriptor.repository,
            "source_family": self.descriptor.source_family,
            "record_type": (_metadata_values(metadata, ["dc.type"])[0] if _metadata_values(metadata, ["dc.type"]) else "research-item"),
            "title": title,
            "persistent_id": handle or uuid or source_url,
            "doi": doi,
            "authors": _metadata_values(metadata, ["dc.contributor.author", "dc.creator"]),
            "description": (_metadata_values(metadata, ["dc.description.abstract", "dc.description"])[0] if _metadata_values(metadata, ["dc.description.abstract", "dc.description"]) else None),
            "subjects": _metadata_values(metadata, ["dc.subject"]),
            "keywords": [],
            "published_at": (_metadata_values(metadata, ["dc.date.issued", "dc.date.available"])[0] if _metadata_values(metadata, ["dc.date.issued", "dc.date.available"]) else None),
            "updated_at": _text(item.get("lastModified")) or None,
            "source_url": source_url,
            "citation": (_metadata_values(metadata, ["dc.identifier.citation"])[0] if _metadata_values(metadata, ["dc.identifier.citation"]) else None),
            "license": _license_observation(
                (_metadata_values(metadata, ["dc.rights"])[0] if _metadata_values(metadata, ["dc.rights"]) else None),
                (_metadata_values(metadata, ["dc.rights.uri"])[0] if _metadata_values(metadata, ["dc.rights.uri"]) else None),
            ),
            "access_state": "public-metadata",
            "provenance": {
                "source_key": self.descriptor.key,
                "source_family": self.descriptor.source_family,
                "repository": self.descriptor.repository,
                "retrieved_from": self.descriptor.base_url,
            },
        }


class OAIPMHNetworkConnector(_HTTPConnector):
    NS = {
        "oai": "http://www.openarchives.org/OAI/2.0/",
        "dc": "http://purl.org/dc/elements/1.1/",
        "oai_dc": "http://www.openarchives.org/OAI/2.0/oai_dc/",
    }

    def __init__(self, descriptor: InstitutionalNetworkSourceDescriptor, *, timeout_seconds: int = 8, max_harvest_records: int = 120) -> None:
        self.descriptor = descriptor
        self.max_harvest_records = max(25, min(int(max_harvest_records), 250))
        super().__init__(timeout_seconds=timeout_seconds)

    def search(self, query: str, *, limit: int = 8) -> dict[str, Any]:
        limit = max(1, min(int(limit), 25))
        query_terms = [term.casefold() for term in re.findall(r"[\w-]+", query) if len(term) > 1]
        candidates: list[dict[str, Any]] = []
        token: str | None = None
        harvested = 0
        pages = 0
        while harvested < self.max_harvest_records and pages < 4:
            params = {"verb": "ListRecords", "metadataPrefix": "oai_dc"} if not token else {"verb": "ListRecords", "resumptionToken": token}
            raw = self._get_xml("/server/oai/request", params)
            try:
                root = ET.fromstring(raw)
            except ET.ParseError as exc:
                raise InstitutionalResearchNetworkError(f"{self.descriptor.key} returned invalid OAI-PMH XML") from exc
            for record in root.findall(".//oai:record", self.NS):
                if harvested >= self.max_harvest_records:
                    break
                harvested += 1
                normalized = self._normalize(record)
                searchable = " ".join(
                    [normalized.get("title") or "", normalized.get("description") or ""]
                    + list(normalized.get("authors") or [])
                    + list(normalized.get("subjects") or [])
                    + list(normalized.get("keywords") or [])
                ).casefold()
                if not query_terms or all(term in searchable for term in query_terms):
                    candidates.append(normalized)
                    if len(candidates) >= limit:
                        break
            if len(candidates) >= limit:
                break
            token_el = root.find(".//oai:resumptionToken", self.NS)
            token = _text(token_el.text if token_el is not None else "") or None
            pages += 1
            if not token:
                break
        return {
            "records": candidates[:limit],
            "total": None,
            "search_mode": "bounded-metadata-harvest",
            "search_limitations": [
                "OAI-PMH is a harvesting protocol, not an arbitrary repository full-text search API.",
                f"This request locally filtered at most {self.max_harvest_records} harvested metadata records; absence from results does not establish absence from the repository.",
            ],
            "harvested_record_count": harvested,
        }

    def _normalize(self, record: ET.Element) -> dict[str, Any]:
        header_id = _text(record.findtext("oai:header/oai:identifier", default="", namespaces=self.NS))
        datestamp = _text(record.findtext("oai:header/oai:datestamp", default="", namespaces=self.NS))
        metadata = record.find("oai:metadata/oai_dc:dc", self.NS)

        def values(name: str) -> list[str]:
            if metadata is None:
                return []
            return _unique(_text(el.text) for el in metadata.findall(f"dc:{name}", self.NS) if _text(el.text))

        titles = values("title")
        identifiers = values("identifier")
        doi = next((normalize_doi(v) for v in identifiers if normalize_doi(v)), None)
        source_url = next((v for v in identifiers if v.startswith("http://") or v.startswith("https://")), None)
        rights = values("rights")
        return {
            "source_key": self.descriptor.key,
            "institution": self.descriptor.institution,
            "repository": self.descriptor.repository,
            "source_family": self.descriptor.source_family,
            "record_type": (values("type")[0] if values("type") else "research-item"),
            "title": titles[0] if titles else header_id or "Untitled research record",
            "persistent_id": header_id or (identifiers[0] if identifiers else source_url),
            "doi": doi,
            "authors": values("creator") + [v for v in values("contributor") if v not in values("creator")],
            "description": (values("description")[0] if values("description") else None),
            "subjects": values("subject"),
            "keywords": [],
            "published_at": (values("date")[0] if values("date") else None),
            "updated_at": datestamp or None,
            "source_url": source_url,
            "citation": None,
            "license": _license_observation(rights[0] if rights else None, next((v for v in rights if v.startswith("http")), None)),
            "access_state": "public-metadata",
            "provenance": {
                "source_key": self.descriptor.key,
                "source_family": self.descriptor.source_family,
                "repository": self.descriptor.repository,
                "retrieved_from": self.descriptor.base_url,
                "oai_identifier": header_id or None,
            },
        }


class InstitutionalResearchNetwork:
    SCHEMA = "sc-institutional-research-network/2.0"

    def __init__(self, *, timeout_seconds: int = 8, connectors: list[Any] | None = None) -> None:
        if connectors is None:
            connectors = build_network_connectors(timeout_seconds)
        self.connectors = {connector.descriptor.key: connector for connector in connectors}

    def manifest(self) -> dict[str, Any]:
        return {
            "schema": "sc-institutional-research-network-manifest/2.0",
            "framework": {
                "key": "institutional-research-network-ii",
                "name": "Institutional Research Network II",
                "version": "2.0",
                "capabilities": [
                    "cross-repository-bounded-search",
                    "institutional-research-object-normalization",
                    "exact-doi-deduplication",
                    "source-scoped-persistent-identity",
                    "per-record-license-observation",
                    "source-provenance-ledger",
                    "source-local-failure-containment",
                    "deterministic-content-fingerprint",
                    "institution-repository-record-graph",
                    "source-bundle-handoff",
                    "research-project-handoff",
                    "research-librarian-handoff",
                    "lab-dataset-metadata-handoff",
                ],
                "governance": {
                    "public_metadata_only": True,
                    "repository_discovery_is_entitlement": False,
                    "metadata_visibility_is_reuse_permission": False,
                    "title_only_identity_merge": False,
                    "cross_source_author_identity_inferred": False,
                    "affiliation_asserted": False,
                    "partnership_asserted": False,
                    "endorsement_asserted": False,
                    "human_rights_review_required_when_license_unknown": True,
                },
            },
            "sources": [self.connectors[key].descriptor.to_dict() for key in sorted(self.connectors)],
            "object_model": [
                "institution", "repository", "source_family", "record_type", "title", "persistent_id", "doi",
                "authors", "description", "subjects", "keywords", "published_at", "updated_at", "source_url",
                "citation", "license", "access_state", "provenance",
            ],
            "identity_policy": {
                "priority": ["exact-normalized-doi", "source+persistent-id", "source+url", "source-scoped-observation"],
                "title_only_merge_used": False,
                "cross_source_author_identity_used": False,
            },
            "graph": {
                "node_types": ["research-question", "institution", "repository", "research-record", "license"],
                "edge_types": ["retrieved-for-question", "held-by-repository", "repository-belongs-to-institution", "licensed-under"],
            },
            "retrieved_at": _now(),
        }

    def search(self, query: str, *, source_keys: list[str] | None = None, limit_per_source: int = 8) -> dict[str, Any]:
        q = str(query or "").strip()
        if not q or len(q) > 500:
            raise ValueError("q is required and must be at most 500 characters")
        limit = max(1, min(int(limit_per_source), 25))
        selected = sorted(set(source_keys or self.connectors.keys()))
        unknown = [key for key in selected if key not in self.connectors]
        if unknown:
            raise ValueError("unknown institutional source: " + ", ".join(unknown))

        observations: list[dict[str, Any]] = []
        source_status: dict[str, dict[str, Any]] = {}
        errors: list[dict[str, str]] = []
        for key in selected:
            connector = self.connectors[key]
            started_at = _now()
            try:
                result = connector.search(q, limit=limit)
                rows = result.get("records", []) if isinstance(result, dict) else []
                rows = rows if isinstance(rows, list) else []
                observations.extend(row for row in rows if isinstance(row, dict))
                source_status[key] = {
                    "state": "available",
                    "record_count": len(rows),
                    "search_mode": result.get("search_mode", connector.descriptor.search_mode),
                    "search_limitations": result.get("search_limitations", []),
                    "harvested_record_count": result.get("harvested_record_count"),
                    "checked_at": started_at,
                }
            except (InstitutionalResearchNetworkError, ValueError) as exc:
                source_status[key] = {
                    "state": "unavailable",
                    "record_count": 0,
                    "search_mode": connector.descriptor.search_mode,
                    "search_limitations": [],
                    "checked_at": started_at,
                    "error": exc.__class__.__name__,
                }
                errors.append({"source_key": key, "error": exc.__class__.__name__})

        records, duplicate_count = self._consolidate(observations)
        content_payload = {
            "query": q,
            "selected_sources": selected,
            "records": [self._stable_record(row) for row in records],
            "source_states": {key: source_status[key].get("state") for key in sorted(source_status)},
        }
        fingerprint = _stable_hash(content_payload)
        states = [source_status[k]["state"] for k in selected]
        network_state = "available" if states and all(s == "available" for s in states) else ("partial" if any(s == "available" for s in states) else "unavailable")
        return {
            "schema": self.SCHEMA,
            "query": q,
            "network_state": network_state,
            "selected_sources": selected,
            "source_status": source_status,
            "record_count": len(records),
            "observation_count": len(observations),
            "duplicate_observation_consolidation_count": duplicate_count,
            "records": records,
            "errors": errors,
            "reproducibility": {
                "algorithm": "institutional-network-identity-v2",
                "content_fingerprint": fingerprint,
                "retrieval_timestamps_excluded_from_fingerprint": True,
                "title_only_merge_used": False,
                "cross_source_author_identity_used": False,
                "limit_per_source": limit,
            },
            "handoffs": self._handoffs(),
            "retrieved_at": _now(),
        }

    def graph(self, query: str, *, source_keys: list[str] | None = None, limit_per_source: int = 8) -> dict[str, Any]:
        result = self.search(query, source_keys=source_keys, limit_per_source=limit_per_source)
        nodes: dict[str, dict[str, Any]] = {}
        edges: dict[tuple[str, str, str], dict[str, Any]] = {}
        qid = "question:" + _stable_hash({"q": result["query"].casefold()})[:20]
        nodes[qid] = {"id": qid, "type": "research-question", "label": result["query"]}
        for record in result["records"]:
            institution_id = "institution:" + _stable_hash(record["institution"].casefold())[:20]
            repository_id = "repository:" + _stable_hash((record["institution"] + "|" + record["repository"]).casefold())[:20]
            record_id = "record:" + record["identity_key"]
            nodes[institution_id] = {"id": institution_id, "type": "institution", "label": record["institution"]}
            nodes[repository_id] = {"id": repository_id, "type": "repository", "label": record["repository"], "institution": record["institution"]}
            nodes[record_id] = {
                "id": record_id,
                "type": "research-record",
                "label": record["title"],
                "identity_key": record["identity_key"],
                "doi": record.get("doi"),
                "record_type": record.get("record_type"),
                "source_keys": record.get("source_keys", []),
                "source_url": record.get("source_url"),
            }
            self._edge(edges, qid, record_id, "retrieved-for-question", record)
            self._edge(edges, record_id, repository_id, "held-by-repository", record)
            self._edge(edges, repository_id, institution_id, "repository-belongs-to-institution", record)
            license_row = record.get("license") if isinstance(record.get("license"), dict) else {}
            license_label = _text(license_row.get("name") or license_row.get("url"))
            if license_label:
                license_id = "license:" + _stable_hash(license_label.casefold())[:20]
                nodes[license_id] = {"id": license_id, "type": "license", "label": license_label, "license": license_row}
                self._edge(edges, record_id, license_id, "licensed-under", record)
        node_rows = sorted(nodes.values(), key=lambda x: (x["type"], x["id"]))
        edge_rows = sorted(edges.values(), key=lambda x: (x["type"], x["source"], x["target"]))
        graph_fingerprint = _stable_hash({
            "nodes": [self._strip_volatile(v) for v in node_rows],
            "edges": [self._strip_volatile(v) for v in edge_rows],
        })
        return {
            "schema": "sc-institutional-research-network-graph/2.0",
            "query": result["query"],
            "network_state": result["network_state"],
            "source_status": result["source_status"],
            "errors": result["errors"],
            "graph": {
                "nodes": node_rows,
                "edges": edge_rows,
                "node_count": len(node_rows),
                "edge_count": len(edge_rows),
                "content_fingerprint": graph_fingerprint,
            },
            "records": result["records"],
            "reproducibility": {
                **result["reproducibility"],
                "graph_fingerprint": graph_fingerprint,
            },
            "handoffs": result["handoffs"],
            "retrieved_at": _now(),
        }

    def _consolidate(self, observations: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int]:
        groups: dict[str, list[dict[str, Any]]] = {}
        for row in observations:
            identity = self._identity_key(row)
            row = dict(row)
            row["identity_key"] = identity
            groups.setdefault(identity, []).append(row)
        records: list[dict[str, Any]] = []
        duplicate_count = 0
        for identity in sorted(groups):
            rows = sorted(groups[identity], key=lambda r: (str(r.get("source_key") or ""), str(r.get("persistent_id") or "")))
            duplicate_count += max(0, len(rows) - 1)
            base = dict(rows[0])
            base["identity_key"] = identity
            base["source_keys"] = sorted(_unique(str(r.get("source_key") or "") for r in rows))
            base["authors"] = _unique(v for r in rows for v in (r.get("authors") or []))
            base["subjects"] = _unique(v for r in rows for v in (r.get("subjects") or []))
            base["keywords"] = _unique(v for r in rows for v in (r.get("keywords") or []))
            base["provenance_ledger"] = [
                {
                    "source_key": r.get("source_key"),
                    "institution": r.get("institution"),
                    "repository": r.get("repository"),
                    "persistent_id": r.get("persistent_id"),
                    "source_url": r.get("source_url"),
                    "provenance": r.get("provenance"),
                }
                for r in rows
            ]
            if len(rows) > 1:
                base["duplicate_observation_count"] = len(rows)
            records.append(base)
        records.sort(key=lambda r: (str(r.get("title") or "").casefold(), r["identity_key"]))
        return records, duplicate_count

    def _identity_key(self, row: dict[str, Any]) -> str:
        doi = normalize_doi(row.get("doi") or row.get("persistent_id") or row.get("source_url"))
        if doi:
            return "doi:" + doi
        source = str(row.get("source_key") or "unknown").strip().lower()
        pid = str(row.get("persistent_id") or "").strip()
        if pid:
            return "source-pid:" + _stable_hash({"source": source, "pid": pid.casefold()})[:32]
        url = str(row.get("source_url") or "").strip()
        if url:
            return "source-url:" + _stable_hash({"source": source, "url": url})[:32]
        stable = {
            "source": source,
            "title": str(row.get("title") or "").casefold(),
            "published_at": row.get("published_at"),
            "record_type": row.get("record_type"),
        }
        return "source-observation:" + _stable_hash(stable)[:32]

    def _stable_record(self, row: dict[str, Any]) -> dict[str, Any]:
        keep = {k: v for k, v in row.items() if k not in {"retrieved_at"}}
        return self._strip_volatile(keep)

    @classmethod
    def _strip_volatile(cls, value: Any) -> Any:
        if isinstance(value, dict):
            return {k: cls._strip_volatile(v) for k, v in sorted(value.items()) if k not in {"retrieved_at", "checked_at"}}
        if isinstance(value, list):
            return [cls._strip_volatile(v) for v in value]
        return value

    @staticmethod
    def _edge(edges: dict[tuple[str, str, str], dict[str, Any]], source: str, target: str, edge_type: str, record: dict[str, Any]) -> None:
        key = (source, target, edge_type)
        provenance = [
            {
                "source_key": p.get("source_key"),
                "persistent_id": p.get("persistent_id"),
                "source_url": p.get("source_url"),
            }
            for p in (record.get("provenance_ledger") or [])
            if isinstance(p, dict)
        ]
        edges[key] = {
            "source": source,
            "target": target,
            "type": edge_type,
            "provenance_ledger": provenance,
        }

    @staticmethod
    def _handoffs() -> dict[str, Any]:
        return {
            "source_bundle": {"available": True, "write_is_explicit_user_action": True},
            "research_project": {"available": True, "write_is_explicit_user_action": True},
            "research_librarian": {"available": True, "metadata_context_only": True},
            "lab": {
                "available": True,
                "dataset_metadata_only_until_access_verified": True,
                "underlying_file_access_inferred": False,
                "reuse_permission_inferred": False,
            },
        }


def build_network_connectors(timeout_seconds: int = 8) -> list[Any]:
    return [
        DSpaceNetworkConnector(
            InstitutionalNetworkSourceDescriptor(
                key="mit-dspace",
                institution="Massachusetts Institute of Technology",
                repository="DSpace@MIT",
                source_family="dspace-rest",
                base_url="https://dspace.mit.edu",
                search_mode="native-search",
                capabilities=("search", "metadata", "persistent-identity", "doi", "provenance"),
            ),
            timeout_seconds=timeout_seconds,
        ),
        DataverseNetworkConnector(
            InstitutionalNetworkSourceDescriptor(
                key="harvard-dataverse",
                institution="Harvard University",
                repository="Harvard Dataverse",
                source_family="dataverse",
                base_url="https://dataverse.harvard.edu",
                search_mode="native-search",
                capabilities=("search", "metadata", "dataset", "doi", "citation", "license-observation", "provenance"),
            ),
            timeout_seconds=timeout_seconds,
        ),
        DataverseNetworkConnector(
            InstitutionalNetworkSourceDescriptor(
                key="johns-hopkins-dataverse",
                institution="Johns Hopkins University",
                repository="Johns Hopkins Research Data Repository",
                source_family="dataverse",
                base_url="https://archive.data.jhu.edu",
                search_mode="native-search",
                capabilities=("search", "metadata", "dataset", "doi", "citation", "license-observation", "provenance"),
            ),
            timeout_seconds=timeout_seconds,
        ),
        OAIPMHNetworkConnector(
            InstitutionalNetworkSourceDescriptor(
                key="ucd-research-repository",
                institution="University College Dublin",
                repository="Research Repository UCD",
                source_family="oai-pmh",
                base_url="https://researchrepository.ucd.ie",
                search_mode="bounded-metadata-harvest",
                capabilities=("metadata-harvest", "oai-pmh", "persistent-identity", "doi-when-present", "provenance", "search-limitation-declared"),
            ),
            timeout_seconds=timeout_seconds,
        ),
    ]
