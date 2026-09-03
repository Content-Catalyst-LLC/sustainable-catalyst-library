from __future__ import annotations

from datetime import datetime, timedelta, timezone
import json
import re
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

from .biomedical_sources import BiomedicalRegistry, BiomedicalSourceError


class MedicalTerminologyError(RuntimeError):
    """Contained terminology/classification upstream failure."""


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clean(value: Any) -> str:
    if value is None:
        return ""
    return re.sub(r"<[^>]+>", "", str(value)).strip()


class WHOICD11Connector:
    """ICD-11 MMS search connector for WHO ICD API v2 or a local ICD API deployment."""

    def __init__(
        self,
        timeout_seconds: int = 8,
        *,
        base_url: str = "https://id.who.int",
        token_url: str = "https://icdaccessmanagement.who.int/connect/token",
        client_id: str = "",
        client_secret: str = "",
        release_id: str = "2026-01",
        language: str = "en",
        local_mode: bool = False,
    ) -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.base_url = base_url.rstrip("/")
        self.token_url = token_url
        self.client_id = client_id
        self.client_secret = client_secret
        self.release_id = release_id or "2026-01"
        self.language = language or "en"
        self.local_mode = bool(local_mode)
        self._token = ""
        self._token_expires = datetime.min.replace(tzinfo=timezone.utc)

    def descriptor(self) -> dict[str, Any]:
        return {
            "key": "icd11",
            "name": "WHO ICD-11 MMS",
            "steward": "World Health Organization",
            "source_family": "disease-classification",
            "base_url": self.base_url,
            "release_id": self.release_id,
            "language": self.language,
            "capabilities": ["classification-search", "code-resolution", "foundation-linkage", "multilingual", "provenance"],
            "governance": {
                "research_only": True,
                "coding_reference": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
            },
        }

    def configured(self) -> bool:
        return self.local_mode or bool(self.client_id and self.client_secret)

    def _access_token(self) -> str:
        if self.local_mode:
            return ""
        if not self.client_id or not self.client_secret:
            raise MedicalTerminologyError("WHO ICD API credentials are not configured")
        if self._token and datetime.now(timezone.utc) < self._token_expires:
            return self._token
        body = urlencode({
            "client_id": self.client_id,
            "client_secret": self.client_secret,
            "scope": "icdapi_access",
            "grant_type": "client_credentials",
        }).encode("utf-8")
        req = Request(self.token_url, data=body, method="POST", headers={"Content-Type": "application/x-www-form-urlencoded"})
        try:
            with urlopen(req, timeout=self.timeout_seconds) as response:
                payload = json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise MedicalTerminologyError(f"WHO ICD token request failed: {exc.__class__.__name__}") from exc
        token = str((payload or {}).get("access_token") or "")
        if not token:
            raise MedicalTerminologyError("WHO ICD token response did not contain an access token")
        expires = int((payload or {}).get("expires_in") or 3600)
        self._token = token
        self._token_expires = datetime.now(timezone.utc) + timedelta(seconds=max(60, expires - 60))
        return token

    def _get_json(self, path: str, params: dict[str, Any]) -> Any:
        query = urlencode({k: v for k, v in params.items() if v not in (None, "")}, doseq=True)
        target = self.base_url + path + ("?" + query if query else "")
        headers = {
            "Accept": "application/json",
            "Accept-Language": self.language,
            "API-Version": "v2",
            "User-Agent": "SustainableCatalystLibrary/1.5",
        }
        token = self._access_token()
        if token:
            headers["Authorization"] = "Bearer " + token
        req = Request(target, headers=headers)
        try:
            with urlopen(req, timeout=self.timeout_seconds) as response:
                return json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise MedicalTerminologyError(f"WHO ICD request failed: {exc.__class__.__name__}") from exc

    def search(self, query: str, *, limit: int = 10) -> dict[str, Any]:
        query = query.strip()
        if not query:
            raise ValueError("query is required")
        limit = max(1, min(int(limit), 25))
        payload = self._get_json(
            f"/icd/release/11/{self.release_id}/mms/search",
            {"q": query, "useFlexisearch": "true", "flatResults": "true", "medicalCodingMode": "false"},
        )
        rows = []
        if isinstance(payload, dict):
            rows = payload.get("destinationEntities") or payload.get("entities") or payload.get("results") or []
        results: list[dict[str, Any]] = []
        for row in rows[:limit] if isinstance(rows, list) else []:
            if not isinstance(row, dict):
                continue
            uri = _clean(row.get("id") or row.get("uri"))
            code = _clean(row.get("theCode") or row.get("code"))
            title = _clean(row.get("title") or row.get("label"))
            foundation_uri = _clean(row.get("foundationUri") or row.get("foundationURI"))
            results.append({
                "schema": "sc-medical-terminology/1.0",
                "source_key": "icd11",
                "record_type": "disease-classification",
                "label": title or None,
                "identifier": f"ICD11:{code}" if code else uri or None,
                "code": code or None,
                "uri": uri or None,
                "foundation_uri": foundation_uri or None,
                "release_id": self.release_id,
                "language": self.language,
                "score": row.get("score"),
                "provenance": {"steward": "World Health Organization", "api_version": "v2", "release_id": self.release_id, "retrieved_at": _now()},
                "governance": {"classification_reference": True, "diagnosis": False, "human_review_required": True},
                "handoffs": {
                    "research_librarian": {"eligible": True, "mode": "disease-concept-context"},
                    "lab": {"eligible": False, "reason": "classification metadata is not an analysis dataset"},
                },
            })
        return {
            "schema": "sc-medical-terminology-search/1.0",
            "source": self.descriptor(),
            "configured": self.configured(),
            "query": query,
            "limit": limit,
            "results": results,
            "retrieved_at": _now(),
        }


class MedicalTerminologyResolver:
    """Cross-vocabulary resolver that preserves source meaning and avoids claiming equivalence."""

    def __init__(self, icd11: WHOICD11Connector, biomedical: BiomedicalRegistry) -> None:
        self.icd11 = icd11
        self.biomedical = biomedical

    def source_manifest(self) -> list[dict[str, Any]]:
        biomedical = {row["key"]: row for row in self.biomedical.list_sources() if row.get("key") in {"mesh", "rxnorm"}}
        return [self.icd11.descriptor(), biomedical.get("mesh", {}), biomedical.get("rxnorm", {})]

    def resolve(self, query: str, *, limit: int = 5) -> dict[str, Any]:
        query = query.strip()
        if not query:
            raise ValueError("query is required")
        limit = max(1, min(int(limit), 10))
        groups: list[dict[str, Any]] = []
        errors: list[dict[str, str]] = []
        try:
            groups.append(self.icd11.search(query, limit=limit))
        except MedicalTerminologyError as exc:
            errors.append({"source_key": "icd11", "error": str(exc)})
        for key in ("mesh", "rxnorm"):
            try:
                groups.append(self.biomedical.get(key).search(query, limit=limit))
            except (BiomedicalSourceError, KeyError) as exc:
                errors.append({"source_key": key, "error": str(exc)})
        return {
            "schema": "sc-medical-concept-resolution/1.0",
            "query": query,
            "groups": groups,
            "errors": errors,
            "crosswalk": {
                "state": "candidate-alignment",
                "semantic_equivalence_asserted": False,
                "human_review_required": True,
                "notice": "ICD-11, MeSH, and RxNorm results are parallel candidate concepts. Shared labels do not establish semantic equivalence or a clinical diagnosis.",
            },
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "patient_specific_diagnosis": False,
                "patient_specific_treatment": False,
            },
            "retrieved_at": _now(),
        }
