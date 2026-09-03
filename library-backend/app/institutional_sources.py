from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import json
from typing import Any, Protocol
from urllib.parse import urlencode, quote
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError


class InstitutionalSourceError(RuntimeError):
    """Contained upstream/source failure safe to expose as a bounded 502."""


@dataclass(frozen=True)
class InstitutionalSourceDescriptor:
    key: str
    institution: str
    repository: str
    source_family: str
    base_url: str
    capabilities: tuple[str, ...]
    public: bool = True
    status: str = "active"

    def to_dict(self) -> dict[str, Any]:
        payload = asdict(self)
        payload["capabilities"] = list(self.capabilities)
        return payload


class InstitutionalSource(Protocol):
    descriptor: InstitutionalSourceDescriptor

    def search(self, query: str, *, limit: int = 10, start: int = 0, object_type: str = "dataset") -> dict[str, Any]: ...
    def get_record(self, persistent_id: str) -> dict[str, Any]: ...


def _text(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, list):
        return "; ".join(_text(item) for item in value if _text(item))
    if isinstance(value, dict):
        return json.dumps(value, sort_keys=True, ensure_ascii=False)
    return str(value).strip()


def _normalize_license(name: str, url: str = "") -> dict[str, Any]:
    raw = " ".join(part for part in (name.strip(), url.strip()) if part).strip()
    upper = raw.upper()
    commercial: bool | None = None
    if "CC0" in upper or "CC BY " in upper or "CC-BY " in upper or "CC BY-" not in upper:
        if raw and "CC BY-NC" not in upper and "NONCOMMERCIAL" not in upper:
            commercial = True if ("CC0" in upper or "CC BY" in upper) else None
    if "CC BY-NC" in upper or "NONCOMMERCIAL" in upper:
        commercial = False
    return {
        "name": name.strip() or None,
        "url": url.strip() or None,
        "commercial_reuse": commercial,
        "reuse_requires_review": commercial is None,
    }


def _field_values(fields: list[dict[str, Any]], type_name: str) -> list[Any]:
    for field in fields:
        if field.get("typeName") == type_name:
            value = field.get("value", [])
            return value if isinstance(value, list) else [value]
    return []


def _compound_values(fields: list[dict[str, Any]], type_name: str, child_name: str) -> list[str]:
    values: list[str] = []
    for item in _field_values(fields, type_name):
        if not isinstance(item, dict):
            continue
        child = item.get(child_name)
        if isinstance(child, dict):
            value = _text(child.get("value"))
            if value:
                values.append(value)
    return values


class JohnsHopkinsDataverseConnector:
    descriptor = InstitutionalSourceDescriptor(
        key="johns-hopkins-dataverse",
        institution="Johns Hopkins University",
        repository="Johns Hopkins Research Data Repository",
        source_family="dataverse",
        base_url="https://archive.data.jhu.edu",
        capabilities=("search", "metadata", "dataset", "files", "citation", "license", "provenance"),
    )

    def __init__(self, *, timeout_seconds: int = 8, user_agent: str = "SustainableCatalystLibrary/1.2") -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.user_agent = user_agent

    def _get_json(self, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
        query = urlencode({k: v for k, v in (params or {}).items() if v is not None}, doseq=True)
        url = self.descriptor.base_url.rstrip("/") + path + ("?" + query if query else "")
        request = Request(url, headers={"Accept": "application/json", "User-Agent": self.user_agent})
        try:
            with urlopen(request, timeout=self.timeout_seconds) as response:
                payload = json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise InstitutionalSourceError(f"Johns Hopkins Dataverse request failed: {exc.__class__.__name__}") from exc
        if not isinstance(payload, dict) or payload.get("status") not in {None, "OK"}:
            raise InstitutionalSourceError("Johns Hopkins Dataverse returned an invalid response")
        return payload

    def search(self, query: str, *, limit: int = 10, start: int = 0, object_type: str = "dataset") -> dict[str, Any]:
        limit = max(1, min(int(limit), 50))
        start = max(0, int(start))
        object_type = object_type if object_type in {"dataset", "file", "dataverse"} else "dataset"
        payload = self._get_json("/api/search", {"q": query or "*", "type": object_type, "per_page": limit, "start": start})
        data = payload.get("data", {}) if isinstance(payload.get("data"), dict) else {}
        items = data.get("items", []) if isinstance(data.get("items"), list) else []
        normalized = [self._normalize_search_item(item) for item in items if isinstance(item, dict)]
        return {
            "schema": "sc-institutional-search/1.0",
            "source": self.descriptor.to_dict(),
            "query": query,
            "object_type": object_type,
            "start": start,
            "limit": limit,
            "total": int(data.get("total_count", len(normalized)) or 0),
            "results": normalized,
            "retrieved_at": datetime.now(timezone.utc).isoformat(),
        }

    def _normalize_search_item(self, item: dict[str, Any]) -> dict[str, Any]:
        persistent_id = _text(item.get("global_id") or item.get("persistentId"))
        authors = item.get("authors") if isinstance(item.get("authors"), list) else []
        subjects = item.get("subjects") if isinstance(item.get("subjects"), list) else []
        keywords = item.get("keywords") if isinstance(item.get("keywords"), list) else []
        return {
            "source_key": self.descriptor.key,
            "institution": self.descriptor.institution,
            "repository": self.descriptor.repository,
            "record_type": _text(item.get("type")) or "dataset",
            "title": _text(item.get("name")),
            "persistent_id": persistent_id or None,
            "doi": persistent_id if persistent_id.lower().startswith("doi:") else None,
            "authors": [str(a).strip() for a in authors if str(a).strip()],
            "description": _text(item.get("description")) or None,
            "subjects": [str(s).strip() for s in subjects if str(s).strip()],
            "keywords": [str(k).strip() for k in keywords if str(k).strip()],
            "published_at": _text(item.get("published_at")) or None,
            "updated_at": _text(item.get("updated_at")) or None,
            "source_url": _text(item.get("url")) or None,
            "citation": _text(item.get("citationHtml") or item.get("citation")) or None,
            "access_state": "public-metadata",
            "data_state": "active-source",
            "provenance": {
                "source_family": "dataverse",
                "source_key": self.descriptor.key,
                "retrieved_from": self.descriptor.base_url,
            },
        }

    def get_record(self, persistent_id: str) -> dict[str, Any]:
        persistent_id = persistent_id.strip()
        if not persistent_id or len(persistent_id) > 300:
            raise ValueError("persistent_id is required")
        payload = self._get_json("/api/datasets/:persistentId/", {"persistentId": persistent_id})
        data = payload.get("data", {}) if isinstance(payload.get("data"), dict) else {}
        latest = data.get("latestVersion", {}) if isinstance(data.get("latestVersion"), dict) else {}
        metadata_blocks = latest.get("metadataBlocks", {}) if isinstance(latest.get("metadataBlocks"), dict) else {}
        citation_block = metadata_blocks.get("citation", {}) if isinstance(metadata_blocks.get("citation"), dict) else {}
        fields = citation_block.get("fields", []) if isinstance(citation_block.get("fields"), list) else []
        title = _text((_field_values(fields, "title") or [""])[0])
        descriptions = _compound_values(fields, "dsDescription", "dsDescriptionValue")
        authors = _compound_values(fields, "author", "authorName")
        subjects = [_text(v) for v in _field_values(fields, "subject") if _text(v)]
        keywords = _compound_values(fields, "keyword", "keywordValue")
        depositors = [_text(v) for v in _field_values(fields, "depositor") if _text(v)]
        files = latest.get("files", []) if isinstance(latest.get("files"), list) else []
        license_name = _text(latest.get("license", {}).get("name")) if isinstance(latest.get("license"), dict) else ""
        license_url = _text(latest.get("license", {}).get("uri")) if isinstance(latest.get("license"), dict) else ""
        if not license_name:
            terms = latest.get("termsOfUse") or latest.get("termsOfAccess")
            license_name = _text(terms)
        return {
            "schema": "sc-institutional-record/1.0",
            "source": self.descriptor.to_dict(),
            "record": {
                "source_key": self.descriptor.key,
                "institution": self.descriptor.institution,
                "repository": self.descriptor.repository,
                "record_type": "dataset",
                "title": title or _text(latest.get("datasetPersistentId")) or persistent_id,
                "persistent_id": _text(latest.get("datasetPersistentId")) or persistent_id,
                "doi": persistent_id if persistent_id.lower().startswith("doi:") else None,
                "authors": authors,
                "description": "\n\n".join(descriptions) or None,
                "subjects": subjects,
                "keywords": keywords,
                "depositors": depositors,
                "published_at": _text(latest.get("releaseTime")) or None,
                "version": _text(latest.get("versionNumber")) or None,
                "version_state": _text(latest.get("versionState")) or None,
                "file_count": len(files),
                "files": [self._normalize_file(item) for item in files[:100] if isinstance(item, dict)],
                "license": _normalize_license(license_name, license_url),
                "access_state": "public-metadata",
                "data_state": "active-source",
                "citation": _text(latest.get("citation")) or None,
                "source_url": self.descriptor.base_url + "/dataset.xhtml?persistentId=" + quote(persistent_id, safe=":/"),
                "provenance": {
                    "source_family": "dataverse",
                    "source_key": self.descriptor.key,
                    "retrieved_from": self.descriptor.base_url,
                    "retrieved_at": datetime.now(timezone.utc).isoformat(),
                },
            },
        }

    @staticmethod
    def _normalize_file(item: dict[str, Any]) -> dict[str, Any]:
        data_file = item.get("dataFile", {}) if isinstance(item.get("dataFile"), dict) else {}
        return {
            "id": data_file.get("id"),
            "filename": _text(data_file.get("filename")) or None,
            "content_type": _text(data_file.get("contentType")) or None,
            "size_bytes": data_file.get("filesize"),
            "checksum": data_file.get("checksum") if isinstance(data_file.get("checksum"), dict) else None,
            "restricted": bool(item.get("restricted", False)),
        }


class InstitutionalSourceRegistry:
    def __init__(self, sources: list[InstitutionalSource]) -> None:
        self._sources = {source.descriptor.key: source for source in sources}

    def list_sources(self) -> list[dict[str, Any]]:
        return [source.descriptor.to_dict() for source in self._sources.values()]

    def get(self, key: str) -> InstitutionalSource:
        source = self._sources.get(key)
        if source is None:
            raise KeyError(key)
        return source


def build_registry(timeout_seconds: int = 8) -> InstitutionalSourceRegistry:
    return InstitutionalSourceRegistry([JohnsHopkinsDataverseConnector(timeout_seconds=timeout_seconds)])
