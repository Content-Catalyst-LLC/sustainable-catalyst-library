from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import json
from typing import Any, Callable, Protocol
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


class FDARegulatoryError(RuntimeError):
    """Contained openFDA/FDA source failure safe to surface as a bounded 502."""


@dataclass(frozen=True)
class FDARegulatorySourceDescriptor:
    key: str
    name: str
    endpoint: str
    evidence_class: str
    capabilities: tuple[str, ...]
    description: str
    update_cadence: str
    public: bool = True
    status: str = "active"

    def to_dict(self) -> dict[str, Any]:
        payload = asdict(self)
        payload["capabilities"] = list(self.capabilities)
        payload["steward"] = "U.S. Food and Drug Administration"
        payload["base_url"] = "https://api.fda.gov"
        payload["governance"] = {
            "research_only": True,
            "clinical_decision_support": False,
            "patient_specific_diagnosis": False,
            "patient_specific_treatment": False,
            "causality_inference_from_adverse_events": False,
        }
        return payload


class FDARegulatorySource(Protocol):
    descriptor: FDARegulatorySourceDescriptor
    def search(self, query: str, *, limit: int = 10, cursor: str = "") -> dict[str, Any]: ...


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clean(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, list):
        return "; ".join(_clean(item) for item in value if _clean(item))
    return str(value).strip()


def _list(value: Any) -> list[Any]:
    return value if isinstance(value, list) else []


def _openfda(row: dict[str, Any]) -> dict[str, Any]:
    block = row.get("openfda")
    return block if isinstance(block, dict) else {}


def _first(block: dict[str, Any], key: str) -> str:
    value = block.get(key)
    if isinstance(value, list):
        return _clean(value[0]) if value else ""
    return _clean(value)


def _fda_identity(row: dict[str, Any]) -> dict[str, Any]:
    of = _openfda(row)
    return {
        "brand_names": _list(of.get("brand_name")),
        "generic_names": _list(of.get("generic_name")),
        "substance_names": _list(of.get("substance_name")),
        "manufacturer_names": _list(of.get("manufacturer_name")),
        "application_numbers": _list(of.get("application_number")),
        "product_ndcs": _list(of.get("product_ndc")),
        "package_ndcs": _list(of.get("package_ndc")),
        "rxcuis": _list(of.get("rxcui")),
        "routes": _list(of.get("route")),
        "dosage_forms": _list(of.get("dosage_form")),
        "pharmacologic_classes": _list(of.get("pharm_class_epc")),
    }


class _OpenFDAHTTP:
    BASE = "https://api.fda.gov"

    def __init__(self, timeout_seconds: int = 8, api_key: str = "", user_agent: str = "SustainableCatalystLibrary/1.4") -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.api_key = api_key.strip()
        self.user_agent = user_agent

    def _get_json(self, endpoint: str, params: dict[str, Any] | None = None) -> Any:
        merged = dict(params or {})
        if self.api_key:
            merged["api_key"] = self.api_key
        query = urlencode({k: v for k, v in merged.items() if v not in (None, "")}, doseq=True)
        url = self.BASE + endpoint + ("?" + query if query else "")
        request = Request(url, headers={"Accept": "application/json", "User-Agent": self.user_agent})
        try:
            with urlopen(request, timeout=self.timeout_seconds) as response:
                return json.loads(response.read().decode("utf-8"))
        except HTTPError as exc:
            # openFDA returns 404 for valid searches with no matching records. Treat that
            # as an empty result set rather than a source outage.
            if exc.code == 404:
                try:
                    body = json.loads(exc.read().decode("utf-8"))
                except Exception:
                    body = {}
                error = (body or {}).get("error") if isinstance(body, dict) else None
                if isinstance(error, dict) and str(error.get("code", "")).lower() in {"not_found", "no matches found"}:
                    return {"meta": {}, "results": []}
            raise FDARegulatoryError(f"FDA source request failed: HTTP {exc.code}") from exc
        except (URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise FDARegulatoryError(f"FDA source request failed: {exc.__class__.__name__}") from exc


Normalizer = Callable[[dict[str, Any]], dict[str, Any]]


class OpenFDAConnector(_OpenFDAHTTP):
    def __init__(
        self,
        descriptor: FDARegulatorySourceDescriptor,
        normalizer: Normalizer,
        *,
        timeout_seconds: int = 8,
        api_key: str = "",
    ) -> None:
        super().__init__(timeout_seconds=timeout_seconds, api_key=api_key)
        self.descriptor = descriptor
        self._normalizer = normalizer

    def search(self, query: str, *, limit: int = 10, cursor: str = "") -> dict[str, Any]:
        query = query.strip()
        if not query:
            raise ValueError("query is required")
        limit = max(1, min(int(limit), 20))
        skip = max(0, min(int(cursor or 0), 25000))
        # With no field prefix openFDA searches every field exposed by the endpoint.
        payload = self._get_json(self.descriptor.endpoint, {"search": query, "limit": limit, "skip": skip})
        rows = _list((payload or {}).get("results")) if isinstance(payload, dict) else []
        results = []
        for raw in rows:
            if not isinstance(raw, dict):
                continue
            item = self._normalizer(raw)
            item.update({
                "schema": "sc-fda-regulatory/1.0",
                "source_key": self.descriptor.key,
                "evidence_class": self.descriptor.evidence_class,
                "provenance": {
                    "steward": "U.S. Food and Drug Administration",
                    "dataset": self.descriptor.name,
                    "openfda_endpoint": self.descriptor.endpoint,
                    "retrieved_at": _now(),
                    "source_meta": (payload or {}).get("meta") if isinstance(payload, dict) else None,
                },
            })
            results.append(item)
        total = (((payload or {}).get("meta") or {}).get("results") or {}).get("total") if isinstance(payload, dict) else None
        try:
            total_i = int(total) if total is not None else len(results)
        except (TypeError, ValueError):
            total_i = len(results)
        return {
            "schema": "sc-fda-search/1.0",
            "source": self.descriptor.to_dict(),
            "query": query,
            "limit": limit,
            "cursor": str(skip),
            "next_cursor": str(skip + limit) if skip + limit < min(total_i, 25000) else None,
            "total": total_i,
            "results": results,
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "notice": "FDA/openFDA records support regulatory research. They are not patient-specific medical advice or treatment recommendations.",
            },
            "retrieved_at": _now(),
        }


def _drug_application(row: dict[str, Any]) -> dict[str, Any]:
    products = []
    for product in _list(row.get("products")):
        if not isinstance(product, dict):
            continue
        products.append({
            "product_number": product.get("product_number"),
            "reference_drug": product.get("reference_drug"),
            "brand_name": product.get("brand_name"),
            "active_ingredients": _list(product.get("active_ingredients")),
            "dosage_form": product.get("dosage_form"),
            "route": product.get("route"),
            "marketing_status": product.get("marketing_status"),
            "reference_standard": product.get("reference_standard"),
        })
    submissions = []
    for sub in _list(row.get("submissions")):
        if not isinstance(sub, dict):
            continue
        submissions.append({k: sub.get(k) for k in (
            "submission_type", "submission_number", "submission_status", "submission_status_date",
            "submission_class_code", "submission_class_code_description", "review_priority",
        ) if sub.get(k) is not None})
    application = _clean(row.get("application_number"))
    return {
        "record_type": "fda-drug-application",
        "title": _first(_openfda(row), "brand_name") or (products[0].get("brand_name") if products else "") or application,
        "identifier": application or None,
        "application_number": application or None,
        "sponsor_name": row.get("sponsor_name"),
        "products": products,
        "submissions": submissions,
        "drug_identity": _fda_identity(row),
        "source_url": f"https://www.accessdata.fda.gov/scripts/cder/daf/index.cfm?event=overview.process&ApplNo={application.replace('NDA','').replace('ANDA','').replace('BLA','')}" if application else "https://www.accessdata.fda.gov/scripts/cder/daf/",
        "regulatory_interpretation": {"approval_record": True, "clinical_effect_estimate": False, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "fda-approval-context"}, "lab": {"eligible": False, "reason": "regulatory application metadata is not an analysis dataset"}},
    }


def _drug_label(row: dict[str, Any]) -> dict[str, Any]:
    of = _openfda(row)
    set_id = _clean(row.get("set_id"))
    title = _first(of, "brand_name") or _first(of, "generic_name") or set_id
    def section(name: str) -> list[str]:
        return [str(x) for x in _list(row.get(name)) if _clean(x)]
    return {
        "record_type": "fda-drug-label",
        "title": title,
        "identifier": set_id or _clean(row.get("id")) or None,
        "set_id": set_id or None,
        "effective_time": row.get("effective_time"),
        "drug_identity": _fda_identity(row),
        "sections": {
            "indications_and_usage": section("indications_and_usage"),
            "boxed_warning": section("boxed_warning"),
            "contraindications": section("contraindications"),
            "warnings": section("warnings"),
            "warnings_and_cautions": section("warnings_and_cautions"),
            "adverse_reactions": section("adverse_reactions"),
            "drug_interactions": section("drug_interactions"),
            "dosage_and_administration": section("dosage_and_administration"),
        },
        "source_url": f"https://dailymed.nlm.nih.gov/dailymed/drugInfo.cfm?setid={set_id}" if set_id else "https://labels.fda.gov/",
        "regulatory_interpretation": {"prescribing_information": True, "clinical_effect_estimate": False, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "fda-label-context"}, "lab": {"eligible": False, "reason": "label text is regulatory evidence, not an analysis dataset"}},
    }


def _ndc(row: dict[str, Any]) -> dict[str, Any]:
    ndc = _clean(row.get("product_ndc"))
    return {
        "record_type": "fda-ndc-product",
        "title": _clean(row.get("brand_name")) or _clean(row.get("generic_name")) or ndc,
        "identifier": f"NDC:{ndc}" if ndc else None,
        "product_ndc": ndc or None,
        "generic_name": row.get("generic_name"),
        "brand_name": row.get("brand_name"),
        "dosage_form": row.get("dosage_form"),
        "routes": _list(row.get("route")),
        "marketing_category": row.get("marketing_category"),
        "application_number": row.get("application_number"),
        "active_ingredients": _list(row.get("active_ingredients")),
        "packaging": _list(row.get("packaging")),
        "listing_expiration_date": row.get("listing_expiration_date"),
        "drug_identity": _fda_identity(row),
        "source_url": "https://www.accessdata.fda.gov/scripts/cder/ndc/index.cfm",
        "regulatory_interpretation": {"listing_record": True, "approval_equivalence": False, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "ndc-product-context"}, "lab": {"eligible": False, "reason": "NDC listing metadata is not an analysis dataset"}},
    }


def _adverse_event(row: dict[str, Any]) -> dict[str, Any]:
    patient = row.get("patient") if isinstance(row.get("patient"), dict) else {}
    reactions = []
    for reaction in _list(patient.get("reaction")):
        if isinstance(reaction, dict) and reaction.get("reactionmeddrapt"):
            reactions.append(reaction.get("reactionmeddrapt"))
    drugs = []
    for drug in _list(patient.get("drug")):
        if not isinstance(drug, dict):
            continue
        drugs.append({
            "name": drug.get("medicinalproduct"),
            "characterization": drug.get("drugcharacterization"),
            "openfda": _fda_identity(drug),
        })
    report_id = _clean(row.get("safetyreportid"))
    return {
        "record_type": "fda-adverse-event-report",
        "title": f"FAERS report {report_id}" if report_id else "FDA adverse-event report",
        "identifier": report_id or None,
        "received_date": row.get("receivedate"),
        "receipt_date": row.get("receiptdate"),
        "serious": row.get("serious"),
        "seriousness": {
            "death": row.get("seriousnessdeath"),
            "life_threatening": row.get("seriousnesslifethreatening"),
            "hospitalization": row.get("seriousnesshospitalization"),
            "disabling": row.get("seriousnessdisabling"),
            "congenital_anomaly": row.get("seriousnesscongenitalanomali"),
        },
        "reactions": reactions,
        "drugs": drugs,
        "primary_source": row.get("primarysource"),
        "source_url": "https://fis.fda.gov/sense/app/95239e26-e0be-42d9-a960-9a5f7f1c25ee/sheet/7a47a261-d58b-4203-a8aa-6d3021737452/state/analysis",
        "regulatory_interpretation": {
            "spontaneous_report": True,
            "causality_established": False,
            "incidence_rate_available": False,
            "signal_only": True,
            "human_review_required": True,
            "warning": "A report does not establish that a drug caused the event and cannot by itself establish incidence or risk.",
        },
        "handoffs": {"research_librarian": {"eligible": True, "mode": "safety-signal-context"}, "lab": {"eligible": False, "reason": "single spontaneous report is not an analysis-ready cohort"}},
    }


def _recall(row: dict[str, Any]) -> dict[str, Any]:
    recall = _clean(row.get("recall_number"))
    return {
        "record_type": "fda-drug-recall",
        "title": _clean(row.get("product_description")) or (f"Recall {recall}" if recall else "FDA drug recall"),
        "identifier": recall or None,
        "classification": row.get("classification"),
        "status": row.get("status"),
        "recalling_firm": row.get("recalling_firm"),
        "reason_for_recall": row.get("reason_for_recall"),
        "distribution_pattern": row.get("distribution_pattern"),
        "recall_initiation_date": row.get("recall_initiation_date"),
        "termination_date": row.get("termination_date"),
        "report_date": row.get("report_date"),
        "voluntary_mandated": row.get("voluntary_mandated"),
        "drug_identity": _fda_identity(row),
        "source_url": "https://www.fda.gov/safety/recalls-market-withdrawals-safety-alerts",
        "regulatory_interpretation": {"market_action": True, "classification_is_risk_category": True, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "recall-context"}, "lab": {"eligible": False, "reason": "recall record is regulatory event metadata"}},
    }


def _shortage(row: dict[str, Any]) -> dict[str, Any]:
    name = _clean(row.get("proprietary_name")) or _clean(row.get("generic_name")) or "FDA drug shortage"
    ndc = _clean(row.get("package_ndc"))
    return {
        "record_type": "fda-drug-shortage",
        "title": name,
        "identifier": f"NDC:{ndc}" if ndc else None,
        "generic_name": row.get("generic_name"),
        "proprietary_name": row.get("proprietary_name"),
        "company_name": row.get("company_name"),
        "presentation": row.get("presentation"),
        "availability": row.get("availability"),
        "update_type": row.get("update_type"),
        "related_info": row.get("related_info"),
        "source_url": row.get("related_info_link") or "https://dps.fda.gov/drugshortages",
        "regulatory_interpretation": {"supply_signal": True, "clinical_effect_estimate": False, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "shortage-context"}, "lab": {"eligible": False, "reason": "shortage metadata is not an analysis dataset"}},
    }


def _orange_book(row: dict[str, Any]) -> dict[str, Any]:
    products = _list(row.get("products"))
    first = products[0] if products and isinstance(products[0], dict) else {}
    application = _clean(first.get("application_number")) or _clean(row.get("application_number"))
    title = _clean(first.get("brand_name")) or _clean(first.get("ingredient")) or application
    return {
        "record_type": "fda-orange-book-record",
        "title": title or "Orange Book record",
        "identifier": application or None,
        "application_number": application or None,
        "products": products,
        "patents": _list(row.get("patents")),
        "exclusivity": _list(row.get("exclusivity")),
        "drug_identity": _fda_identity(row),
        "source_url": "https://www.accessdata.fda.gov/scripts/cder/ob/",
        "regulatory_interpretation": {"therapeutic_equivalence_reference": True, "legal_status_action": False, "human_review_required": True},
        "handoffs": {"research_librarian": {"eligible": True, "mode": "orange-book-context"}, "lab": {"eligible": False, "reason": "Orange Book record is regulatory reference metadata"}},
    }


FDA_SOURCE_SPECS: tuple[tuple[FDARegulatorySourceDescriptor, Normalizer], ...] = (
    (FDARegulatorySourceDescriptor("drugsfda", "Drugs@FDA", "/drug/drugsfda.json", "regulatory-approval", ("search", "approvals", "products", "submissions", "harmonized-drug-identifiers", "provenance"), "FDA application, product, submission, and approval history", "Daily Monday-Friday"), _drug_application),
    (FDARegulatorySourceDescriptor("fda-labels", "FDA Drug Labeling", "/drug/label.json", "regulatory-label", ("search", "prescribing-information", "warnings", "indications", "contraindications", "drug-interactions", "provenance"), "Structured drug labeling and prescribing information", "Frequent openFDA refresh"), _drug_label),
    (FDARegulatorySourceDescriptor("fda-ndc", "FDA NDC Directory", "/drug/ndc.json", "regulatory-listing", ("search", "ndc", "product-listing", "packaging", "drug-identifiers", "provenance"), "National Drug Code product listing records", "FDA source refresh"), _ndc),
    (FDARegulatorySourceDescriptor("fda-adverse-events", "FDA Adverse Event Reporting System (FAERS)", "/drug/event.json", "safety-report", ("search", "adverse-event-reports", "reactions", "seriousness", "safety-signals", "provenance"), "Spontaneous post-market adverse-event reports; reports do not establish causality", "Quarterly source updates / openFDA processing"), _adverse_event),
    (FDARegulatorySourceDescriptor("fda-recalls", "FDA Drug Recall Enforcement Reports", "/drug/enforcement.json", "regulatory-enforcement", ("search", "recalls", "classification", "market-actions", "provenance"), "Drug recall and enforcement records", "Weekly"), _recall),
    (FDARegulatorySourceDescriptor("fda-shortages", "FDA Drug Shortages", "/drug/drugshortages.json", "supply-intelligence", ("search", "shortages", "availability", "manufacturer", "supply-signals", "provenance"), "Current and historical drug shortage information", "Daily"), _shortage),
    (FDARegulatorySourceDescriptor("orange-book", "FDA Orange Book", "/drug/orangebook.json", "therapeutic-equivalence-reference", ("search", "therapeutic-equivalence", "patents", "exclusivity", "approved-products", "provenance"), "Approved drug products, therapeutic equivalence evaluations, patents, and exclusivities", "FDA source refresh"), _orange_book),
)


class FDARegulatoryRegistry:
    def __init__(self, sources: list[FDARegulatorySource]) -> None:
        self._sources = {source.descriptor.key: source for source in sources}

    def list_sources(self) -> list[dict[str, Any]]:
        return [source.descriptor.to_dict() for source in self._sources.values()]

    def get(self, key: str) -> FDARegulatorySource:
        if key not in self._sources:
            raise KeyError(key)
        return self._sources[key]

    def unified_search(self, query: str, *, limit: int = 4, source_keys: list[str] | None = None) -> dict[str, Any]:
        if not query.strip():
            raise ValueError("query is required")
        keys = source_keys or list(self._sources.keys())
        groups: list[dict[str, Any]] = []
        errors: list[dict[str, str]] = []
        for key in keys:
            source = self._sources.get(key)
            if source is None:
                continue
            try:
                groups.append(source.search(query, limit=limit))
            except FDARegulatoryError as exc:
                errors.append({"source_key": key, "error": str(exc)})
        return {
            "schema": "sc-fda-unified-search/1.0",
            "query": query,
            "groups": groups,
            "errors": errors,
            "governance": {
                "research_only": True,
                "clinical_decision_support": False,
                "adverse_event_causality_warning": "FAERS/openFDA adverse-event reports do not establish that a drug caused an event and cannot be used alone to estimate incidence or risk.",
                "notice": "Regulatory records must be interpreted according to their evidence class; approval, labeling, safety reports, recalls, and shortages are not interchangeable forms of evidence.",
            },
            "retrieved_at": _now(),
        }


def build_fda_regulatory_registry(timeout_seconds: int = 8, *, api_key: str = "") -> FDARegulatoryRegistry:
    return FDARegulatoryRegistry([
        OpenFDAConnector(descriptor, normalizer, timeout_seconds=timeout_seconds, api_key=api_key)
        for descriptor, normalizer in FDA_SOURCE_SPECS
    ])
