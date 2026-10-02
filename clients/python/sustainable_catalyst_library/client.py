from __future__ import annotations
import hashlib, hmac, json, time
from dataclasses import dataclass
from typing import Any
from urllib import error, parse, request

PRODUCT_KEYS = (
    "research-librarian", "workspace", "research-lab",
    "workbench", "decision-studio", "site-intelligence",
)


def _canonical_body(payload: Any | None) -> bytes:
    if payload is None:
        return b""
    return json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")


def sign_request(method: str, path: str, timestamp: str, body: bytes, key: str) -> str:
    body_hash = hashlib.sha256(body).hexdigest()
    base = f"{method.upper()}\n{path}\n{timestamp}\n{body_hash}".encode("utf-8")
    return hmac.new(key.encode("utf-8"), base, hashlib.sha256).hexdigest()


class LibraryError(RuntimeError):
    def __init__(self, message: str, *, status: int | None = None, code: str | None = None, details: Any = None):
        super().__init__(message)
        self.status = status
        self.code = code
        self.details = details


@dataclass(frozen=True)
class ProductAdapter:
    client: "LibraryClient"
    product_key: str

    def contract(self) -> dict[str, Any]:
        return self.client.get(f"/integrations/{parse.quote(self.product_key)}")

    def validate_exchange(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self.client.post_signed(f"/integrations/{parse.quote(self.product_key)}/exchange/validate", payload)


class LibraryClient:
    def __init__(self, base_url: str, *, api_key: str | None = None, timeout: float = 20.0, max_retries: int = 2):
        self.base_url = base_url.rstrip("/")
        if not self.base_url.endswith("/api/library/v1"):
            self.base_url += "/api/library/v1"
        self.api_key = api_key
        self.timeout = float(timeout)
        self.max_retries = max(0, int(max_retries))

    def _decode_error(self, exc: error.HTTPError) -> LibraryError:
        try:
            payload = json.loads(exc.read().decode("utf-8"))
            detail = payload.get("error") or payload.get("detail") or payload
        except Exception:
            detail = None
        if isinstance(detail, dict):
            inner = detail.get("error") if isinstance(detail.get("error"), dict) else detail
            return LibraryError(str(inner.get("message") or exc.reason), status=exc.code, code=inner.get("code"), details=inner.get("details"))
        return LibraryError(str(detail or exc.reason), status=exc.code)

    def request(self, method: str, path: str, *, query: dict[str, Any] | None = None, payload: Any | None = None, signed: bool = False) -> Any:
        path = "/" + path.lstrip("/")
        url = self.base_url + path
        if query:
            q = {k: v for k, v in query.items() if v is not None}
            if q:
                url += "?" + parse.urlencode(q, doseq=True)
        body = _canonical_body(payload)
        headers = {"Accept": "application/json"}
        if payload is not None:
            headers["Content-Type"] = "application/json"
        if signed:
            if not self.api_key:
                raise LibraryError("api_key is required for signed requests")
            ts = str(int(time.time()))
            headers.update({
                "Authorization": f"Bearer {self.api_key}",
                "X-SC-Timestamp": ts,
                "X-SC-Signature": sign_request(method, path, ts, body, self.api_key),
            })
        retryable = {429, 502, 503, 504}
        for attempt in range(self.max_retries + 1):
            try:
                req = request.Request(url, data=body if payload is not None else None, headers=headers, method=method.upper())
                with request.urlopen(req, timeout=self.timeout) as resp:
                    raw = resp.read()
                    return json.loads(raw.decode("utf-8")) if raw else None
            except error.HTTPError as exc:
                if exc.code in retryable and attempt < self.max_retries:
                    time.sleep(min(0.25 * (2 ** attempt), 1.0))
                    continue
                raise self._decode_error(exc) from exc
            except error.URLError as exc:
                if attempt < self.max_retries:
                    time.sleep(min(0.25 * (2 ** attempt), 1.0))
                    continue
                raise LibraryError(str(exc.reason)) from exc

    def get(self, path: str, query: dict[str, Any] | None = None) -> Any:
        return self.request("GET", path, query=query)

    def get_signed(self, path: str, query: dict[str, Any] | None = None) -> Any:
        return self.request("GET", path, query=query, signed=True)

    def post_signed(self, path: str, payload: Any) -> Any:
        return self.request("POST", path, payload=payload, signed=True)

    def delete_signed(self, path: str) -> Any:
        return self.request("DELETE", path, signed=True)

    def health(self): return self.get("/health")
    def readiness(self): return self.get("/readiness")
    def service(self): return self.get("/service")
    def capabilities(self): return self.get("/capabilities")
    def routes(self): return self.get("/routes")
    def client_framework(self): return self.get("/client-framework")
    def catalog(self): return self.get("/catalog")
    def catalog_readiness(self): return self.get("/catalog/readiness")
    def research_state(self): return self.get("/research-state")
    def research_state_readiness(self): return self.get("/research-state/readiness")
    def ingestion(self): return self.get("/ingestion")
    def ingestion_readiness(self): return self.get("/ingestion/readiness")
    def retrieval(self): return self.get("/retrieval")
    def retrieval_readiness(self): return self.get("/retrieval/readiness")
    def provenance(self): return self.get("/provenance")
    def provenance_readiness(self): return self.get("/provenance/readiness")
    def language(self): return self.get("/language")
    def language_readiness(self): return self.get("/language/readiness")
    def language_capture(self, capture_id: str, *, include_text: bool=False): return self.get("/language/captures/" + parse.quote(capture_id,safe=""), {"include_text":str(include_text).lower()})
    def language_derivation(self, run_id: str, *, include_text: bool=False): return self.get("/language/derivations/" + parse.quote(run_id,safe=""), {"include_text":str(include_text).lower()})
    def linguistic_corpus(self, corpus_id: str, *, include_tokens: bool=False): return self.get("/language/corpora/" + parse.quote(corpus_id,safe=""), {"include_tokens":str(include_tokens).lower()})
    def linguistic_kwic(self, corpus_id: str, q: str, **options): return self.get("/language/corpora/" + parse.quote(corpus_id,safe="") + "/kwic", {"q":q, **options})
    def language_resolution_case(self, case_id: str): return self.get("/language/entity-resolution/" + parse.quote(case_id,safe=""))
    def language_alignment(self, matrix_id: str): return self.get("/language/alignments/" + parse.quote(matrix_id,safe=""))
    def scientific_document_intelligence(self, record_id: str): return self.get("/language/documents/" + parse.quote(record_id,safe="") + "/intelligence")
    def validate_language_capture(self, payload: dict[str, Any]): return self.post_signed("/admin/language/captures/validate",payload)
    def create_language_capture(self, payload: dict[str, Any]): return self.post_signed("/admin/language/captures",payload)
    def validate_language_derivation(self, payload: dict[str, Any]): return self.post_signed("/admin/language/derivations/validate",payload)
    def create_language_derivation(self, payload: dict[str, Any]): return self.post_signed("/admin/language/derivations",payload)
    def validate_linguistic_corpus(self, payload: dict[str, Any]): return self.post_signed("/admin/language/corpora/validate",payload)
    def create_linguistic_corpus(self, payload: dict[str, Any]): return self.post_signed("/admin/language/corpora",payload)
    def create_language_authority(self, payload: dict[str, Any]): return self.post_signed("/admin/language/authorities",payload)
    def create_language_resolution_case(self, payload: dict[str, Any]): return self.post_signed("/admin/language/entity-resolution/cases",payload)
    def create_language_resolution_decision(self, case_id: str, payload: dict[str, Any]): return self.post_signed("/admin/language/entity-resolution/" + parse.quote(case_id,safe="") + "/decisions",payload)
    def validate_language_alignment(self, payload: dict[str, Any]): return self.post_signed("/admin/language/alignments/validate",payload)
    def create_language_alignment(self, payload: dict[str, Any]): return self.post_signed("/admin/language/alignments",payload)
    def record_provenance(self, record_id: str, *, version_limit: int = 25): return self.get("/provenance/records/" + parse.quote(record_id, safe=""), {"version_limit": version_limit})
    def citations(self, record_id: str, *, direction: str = "both", limit: int = 100): return self.get("/citations/" + parse.quote(record_id, safe=""), {"direction": direction, "limit": limit})
    def evidence_graph(self, record_id: str, *, depth: int = 2, limit: int = 250, include_core: bool = True): return self.get("/evidence-graph/" + parse.quote(record_id, safe=""), {"depth": depth, "limit": limit, "include_core": str(include_core).lower()})
    def create_citation(self, payload: dict[str, Any]): return self.post_signed("/admin/citations", payload)
    def import_citations(self, record_id: str): return self.post_signed("/admin/citations/" + parse.quote(record_id, safe="") + "/import-metadata", {})
    def citation_core_handoff(self, payload: dict[str, Any]): return self.post_signed("/admin/citations/core-handoff", payload)
    def retrieval_facets(self): return self.get("/retrieval/facets")
    def retrieval_search(self, q: str = "", **options): return self.get("/retrieval/search", {"q": q, **options})
    def retrieval_plan(self, payload: dict[str, Any]): return self.post_signed("/admin/retrieval/plan", payload)
    def retrieval_search_advanced(self, payload: dict[str, Any]): return self.post_signed("/admin/retrieval/search", payload)
    def normalize_ingestion(self, payload: dict[str, Any]): return self.post_signed("/admin/ingestion/normalize", payload)
    def ingest_source_records(self, payload: dict[str, Any]): return self.post_signed("/admin/ingestion/records", payload)
    def source_ingestion_state(self, source_key: str): return self.get_signed("/admin/ingestion/sources/" + parse.quote(source_key, safe=""))
    def research_state_for_owner(self, owner_identity_id: str): return self.get_signed("/admin/research-state/owners/" + parse.quote(owner_identity_id, safe=""))
    def create_research_project(self, payload: dict[str, Any]): return self.post_signed("/admin/research-state/projects", payload)
    def add_project_reference(self, project_id: str, payload: dict[str, Any]): return self.post_signed("/admin/research-state/projects/" + parse.quote(project_id, safe="") + "/references", payload)
    def create_source_bundle(self, project_id: str, payload: dict[str, Any]): return self.post_signed("/admin/research-state/projects/" + parse.quote(project_id, safe="") + "/bundles", payload)
    def save_search(self, payload: dict[str, Any]): return self.post_signed("/admin/research-state/saved-searches", payload)
    def save_watchlist(self, payload: dict[str, Any]): return self.post_signed("/admin/research-state/watchlists", payload)
    def enqueue_research(self, payload: dict[str, Any]): return self.post_signed("/admin/research-state/queue", payload)
    def create_collection(self, payload: dict[str, Any]): return self.post_signed("/admin/research-state/collections", payload)
    def add_collection_item(self, collection_id: str, payload: dict[str, Any]): return self.post_signed("/admin/research-state/collections/" + parse.quote(collection_id, safe="") + "/items", payload)
    def research_object(self, record_id: str, *, include_body: bool = True): return self.get("/research-objects/" + parse.quote(record_id, safe=""), {"include_body": str(include_body).lower()})
    def validate_catalog_record(self, payload: dict[str, Any]): return self.post_signed("/admin/catalog/records/validate", payload)
    def upsert_catalog_record(self, payload: dict[str, Any]): return self.post_signed("/admin/catalog/records", payload)
    def delete_catalog_record(self, record_id: str): return self.delete_signed("/admin/catalog/records/" + parse.quote(record_id, safe=""))
    def search(self, q: str = "", **filters): return self.get("/search", {"q": q, **filters})
    def record(self, record_id: str, *, include_body: bool = True): return self.get("/records/" + parse.quote(record_id, safe=""), {"include_body": str(include_body).lower()})
    def stats(self): return self.get("/stats")
    def artifacts_readiness(self): return self.get("/artifacts/readiness")
    def pipelines_readiness(self): return self.get("/pipelines/readiness")
    def compute_readiness(self): return self.get("/compute/readiness")
    def integrations(self): return self.get("/integrations")
    def submit_research_job(self, payload: dict[str, Any]): return self.post_signed("/research-jobs", payload)

    def product(self, product_key: str) -> ProductAdapter:
        if product_key not in PRODUCT_KEYS:
            raise ValueError(f"unsupported product_key: {product_key}")
        return ProductAdapter(self, product_key)

    def research_librarian(self): return self.product("research-librarian")
    def workspace(self): return self.product("workspace")
    def research_lab(self): return self.product("research-lab")
    def workbench(self): return self.product("workbench")
    def decision_studio(self): return self.product("decision-studio")
    def site_intelligence(self): return self.product("site-intelligence")
