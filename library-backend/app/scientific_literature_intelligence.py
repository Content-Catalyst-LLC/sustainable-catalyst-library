from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
import re
from typing import Any

from .advanced_discovery import readiness as advanced_discovery_readiness
from .literature_review import build_literature_review
from .provenance_graph_service import readiness as provenance_graph_readiness
from .scientific_document_intelligence import build_scientific_document_intelligence
from .structured_evidence_objects import dataset_object as build_structured_dataset
from .structured_evidence_objects import readiness as structured_evidence_readiness

LIBRARY_VERSION = "6.9.0"
BACKEND_VERSION = "3.9.0"
CONTRACT = "sc-library-scientific-literature-intelligence/1.0"
READINESS_CONTRACT = "sc-library-scientific-literature-intelligence-readiness/1.0"
PUBLICATION_CONTRACT = "sc-library-scientific-publication-profile/1.0"
LITERATURE_SET_CONTRACT = "sc-library-scientific-literature-set/1.0"
CITATION_CONTEXT_CONTRACT = "sc-library-scientific-citation-context/1.0"
VALIDATION_CONTRACT = "sc-library-scientific-literature-validation/1.0"

MAX_PUBLICATIONS = 2500
MAX_AUTHORS = 500
MAX_CITATIONS = 10000

DESIGN_PATTERNS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("systematic-review", ("systematic review", "systematic literature review")),
    ("meta-analysis", ("meta-analysis", "meta analysis")),
    ("randomized-controlled-trial", ("randomized controlled trial", "randomised controlled trial", "randomized trial", "randomised trial")),
    ("clinical-trial", ("clinical trial",)),
    ("cohort-study", ("cohort study", "prospective cohort", "retrospective cohort")),
    ("case-control-study", ("case-control", "case control study")),
    ("cross-sectional-study", ("cross-sectional", "cross sectional study")),
    ("case-series", ("case series",)),
    ("case-report", ("case report",)),
    ("in-vivo-study", ("in vivo",)),
    ("in-vitro-study", ("in vitro",)),
    ("simulation-modeling", ("simulation", "modelling study", "modeling study", "computational model")),
    ("qualitative-study", ("qualitative study", "qualitative research")),
    ("survey-study", ("survey study", "questionnaire study")),
)


def _clean(value: Any, limit: int = 4000) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _text(value: Any, limit: int = 50000) -> str:
    return str(value or "").strip()[:limit]


def _list(value: Any) -> list[Any]:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return [value]


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _fp(value: Any) -> str:
    payload = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
    return sha256(payload.encode("utf-8")).hexdigest()


def _doi(value: Any) -> str | None:
    text = _clean(value, 500)
    if not text:
        return None
    text = re.sub(r"^https?://(?:dx\.)?doi\.org/", "", text, flags=re.I)
    text = re.sub(r"^doi\s*:\s*", "", text, flags=re.I)
    text = text.strip().strip(".,;)")
    return text.lower() if text.startswith("10.") and "/" in text else None


def _orcid(value: Any) -> str | None:
    text = _clean(value, 100)
    if not text:
        return None
    text = re.sub(r"^https?://orcid\.org/", "", text, flags=re.I).upper()
    return text if re.fullmatch(r"\d{4}-\d{4}-\d{4}-[\dX]{4}", text) else None


def _identifier(value: Any, prefix: str = "") -> str | None:
    text = _clean(value, 500)
    if not text:
        return None
    if prefix and text.lower().startswith(prefix.lower() + ":"):
        text = text.split(":", 1)[1]
    return text.strip() or None


def _normalize_author(raw: Any) -> dict[str, Any]:
    if isinstance(raw, str):
        return {"name": _clean(raw, 500), "orcid": None, "affiliations": []}
    data = _dict(raw)
    given = _clean(data.get("given") or data.get("given_name") or data.get("first"), 200)
    family = _clean(data.get("family") or data.get("family_name") or data.get("last"), 200)
    name = _clean(data.get("name") or " ".join(x for x in (given, family) if x), 500)
    affiliations = []
    for item in _list(data.get("affiliations") or data.get("affiliation"))[:50]:
        value = _clean(item.get("name") if isinstance(item, dict) else item, 500)
        if value and value not in affiliations:
            affiliations.append(value)
    return {
        "name": name,
        "given": given or None,
        "family": family or None,
        "orcid": _orcid(data.get("orcid") or data.get("ORCID")),
        "affiliations": affiliations,
    }


def _study_design_indicators(title: str, abstract: str, supplied: Any) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    seen: set[str] = set()
    supplied_values = [_clean(x, 160).lower().replace("_", "-") for x in _list(supplied)]
    for value in supplied_values:
        if value and value not in seen:
            seen.add(value)
            out.append({"design": value, "basis": "supplied-metadata", "review_required": True})
    haystack = f"{title}\n{abstract}".casefold()
    for design, phrases in DESIGN_PATTERNS:
        matched = next((phrase for phrase in phrases if phrase.casefold() in haystack), None)
        if matched and design not in seen:
            seen.add(design)
            out.append({"design": design, "basis": "text-indicator", "matched_phrase": matched, "review_required": True})
    return out


def guardrails() -> dict[str, Any]:
    return {
        "scientific_literature_layer_is_composition": True,
        "library_records_remain_publication_authority": True,
        "provenance_graph_remains_citation_authority": True,
        "scientific_document_intelligence_remains_object_extraction_authority": True,
        "literature_review_engine_remains_review_protocol_authority": True,
        "structured_evidence_layer_remains_table_dataset_authority": True,
        "citation_count_implies_quality": False,
        "journal_venue_implies_quality": False,
        "study_design_label_implies_validity": False,
        "study_design_indicator_is_definitive_classification": False,
        "identifier_presence_implies_identity_match": False,
        "extracted_claim_implies_truth": False,
        "retraction_or_correction_metadata_is_complete_without_source_verification": False,
        "review_inclusion_implies_truth": False,
        "review_exclusion_implies_falsehood": False,
        "literature_set_implies_global_completeness": False,
        "automatic_meta_analysis": False,
        "automatic_consensus_inference": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "objects": {
            "publication_profile": PUBLICATION_CONTRACT,
            "literature_set": LITERATURE_SET_CONTRACT,
            "citation_context": CITATION_CONTEXT_CONTRACT,
            "validation": VALIDATION_CONTRACT,
        },
        "capabilities": {
            "publication_identifier_normalization": True,
            "doi_pmid_pmcid_arxiv_orcid": True,
            "study_design_indicators": True,
            "study_design_indicator_provenance": True,
            "scientific_object_summary": True,
            "citation_context_summary": True,
            "correction_retraction_metadata_slots": True,
            "literature_set_deduplication": True,
            "literature_set_coverage_metrics": True,
            "reproducible_review_composition": True,
            "structured_dataset_preview": True,
            "database_migration_required": False,
        },
        "guardrails": guardrails(),
    }


def schema_registry() -> dict[str, Any]:
    return {
        "schema": "sc-library-scientific-literature-schema-registry/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "schemas": {
            "publication_profile": PUBLICATION_CONTRACT,
            "literature_set": LITERATURE_SET_CONTRACT,
            "citation_context": CITATION_CONTEXT_CONTRACT,
            "validation": VALIDATION_CONTRACT,
        },
        "database_migration_required": False,
    }


def normalize_publication(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("publication payload must be an object")
    identifiers = _dict(payload.get("identifiers"))
    title = _clean(payload.get("title"), 2000)
    abstract = _text(payload.get("abstract") or payload.get("description"), 40000)
    record_id = _clean(payload.get("record_id") or payload.get("id"), 500)
    doi = _doi(payload.get("doi") or identifiers.get("doi"))
    pmid = _identifier(payload.get("pmid") or identifiers.get("pmid"), "pmid")
    pmcid = _identifier(payload.get("pmcid") or identifiers.get("pmcid"), "pmcid")
    arxiv = _identifier(payload.get("arxiv") or identifiers.get("arxiv"), "arxiv")
    if not record_id:
        if doi:
            record_id = f"doi:{doi}"
        elif pmid:
            record_id = f"pmid:{pmid}"
        elif arxiv:
            record_id = f"arxiv:{arxiv}"
        else:
            record_id = "scientific-publication:" + _fp({"title": title, "year": payload.get("year"), "url": payload.get("url")})[:24]
    authors = [_normalize_author(x) for x in _list(payload.get("authors"))[:MAX_AUTHORS]]
    authors = [x for x in authors if x.get("name")]
    publication_types = []
    for raw in _list(payload.get("publication_types") or payload.get("publication_type") or payload.get("types")):
        value = _clean(raw.get("label") if isinstance(raw, dict) else raw, 200)
        if value and value not in publication_types:
            publication_types.append(value)
    status = _dict(payload.get("publication_status") or payload.get("status_metadata"))
    correction = {
        "retracted": bool(status.get("retracted") or payload.get("retracted")),
        "corrected": bool(status.get("corrected") or payload.get("corrected")),
        "expression_of_concern": bool(status.get("expression_of_concern") or payload.get("expression_of_concern")),
        "notice_identifier": _clean(status.get("notice_identifier") or payload.get("notice_identifier"), 500) or None,
        "notice_url": _clean(status.get("notice_url") or payload.get("notice_url"), 4000) or None,
        "verification_state": _clean(status.get("verification_state") or "unverified-supplied-metadata", 100),
    }
    designs = _study_design_indicators(title, abstract, payload.get("study_design") or payload.get("study_designs"))
    source = _dict(payload.get("source"))
    profile = {
        "schema": PUBLICATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_id": record_id,
        "title": title or None,
        "abstract": abstract or None,
        "authors": authors,
        "journal": _clean(payload.get("journal") or source.get("journal") or source.get("container_title"), 1000) or None,
        "publisher": _clean(payload.get("publisher") or source.get("publisher"), 1000) or None,
        "published_at": _clean(payload.get("published_at") or payload.get("published") or payload.get("date"), 100) or None,
        "year": payload.get("year"),
        "volume": _clean(payload.get("volume"), 100) or None,
        "issue": _clean(payload.get("issue"), 100) or None,
        "pages": _clean(payload.get("pages") or payload.get("page"), 100) or None,
        "url": _clean(payload.get("url") or payload.get("canonical_url"), 4000) or None,
        "identifiers": {"doi": doi, "pmid": pmid, "pmcid": pmcid, "arxiv": arxiv, "issn": _identifier(payload.get("issn") or identifiers.get("issn"))},
        "publication_types": publication_types,
        "study_design_indicators": designs,
        "correction_retraction": correction,
        "language": _clean(payload.get("original_language") or payload.get("language"), 100) or None,
        "source_key": _clean(payload.get("source_key") or source.get("source_key"), 200) or None,
        "source_version": _clean(payload.get("source_version") or source.get("version"), 200) or None,
        "source_hash": _clean(payload.get("source_hash") or payload.get("content_hash"), 200) or None,
        "source_locator": _dict(payload.get("source_locator")) or None,
        "human_review": {
            "bibliographic_identity_reviewed": bool(payload.get("bibliographic_identity_reviewed", False)),
            "study_design_reviewed": bool(payload.get("study_design_reviewed", False)),
            "correction_retraction_reviewed": bool(payload.get("correction_retraction_reviewed", False)),
        },
        "guardrails": guardrails(),
    }
    profile["publication_fingerprint_sha256"] = _fp({k: v for k, v in profile.items() if k not in {"publication_fingerprint_sha256", "guardrails"}})
    return profile


def _citation_context(citations: list[dict[str, Any]]) -> dict[str, Any]:
    normalized = []
    relation_counts: Counter[str] = Counter()
    for raw in citations[:MAX_CITATIONS]:
        if not isinstance(raw, dict):
            continue
        relation = _clean(raw.get("relation") or raw.get("type") or raw.get("relationship") or "citation", 100).lower().replace("_", "-")
        relation_counts[relation] += 1
        normalized.append({
            "citing_record_id": _clean(raw.get("citing_record_id") or raw.get("source_record_id"), 500) or None,
            "cited_record_id": _clean(raw.get("cited_record_id") or raw.get("target_record_id"), 500) or None,
            "relation": relation,
            "source_locator": _clean(raw.get("source_locator"), 1000) or None,
            "context": _text(raw.get("context") or raw.get("citation_context"), 4000) or None,
            "human_reviewed": bool(raw.get("human_reviewed", False)),
        })
    return {
        "schema": CITATION_CONTEXT_CONTRACT,
        "citation_count": len(normalized),
        "relation_counts": dict(sorted(relation_counts.items())),
        "items": normalized,
        "citation_count_implies_quality": False,
        "citation_relation_implies_support": False,
    }


def analyze_publication(payload: dict[str, Any]) -> dict[str, Any]:
    profile = normalize_publication(payload)
    document = dict(_dict(payload.get("document")) or payload)
    document.setdefault("record_id", profile["record_id"])
    document.setdefault("title", profile.get("title"))
    scientific = build_scientific_document_intelligence(document)
    citations = _citation_context([x for x in _list(payload.get("citations")) if isinstance(x, dict)])
    supplied_claims = []
    for raw in _list(payload.get("claims"))[:1000]:
        if not isinstance(raw, dict):
            continue
        supplied_claims.append({
            "text": _text(raw.get("text") or raw.get("claim"), 10000),
            "source_locator": _clean(raw.get("source_locator"), 1000) or None,
            "supplied_not_inferred": True,
            "truth_determined": False,
        })
    return {
        "schema": "sc-library-scientific-publication-intelligence/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "profile": profile,
        "scientific_document": scientific,
        "citation_context": citations,
        "supplied_claims": supplied_claims,
        "metrics": {
            "author_count": len(profile.get("authors") or []),
            "identifier_count": sum(1 for x in (profile.get("identifiers") or {}).values() if x),
            "study_design_indicator_count": len(profile.get("study_design_indicators") or []),
            "scientific_object_count": (scientific.get("metrics") or {}).get("scientific_object_count", 0),
            "citation_count": citations.get("citation_count", 0),
        },
        "guardrails": guardrails(),
    }


def _dedupe_key(profile: dict[str, Any]) -> str:
    ids = profile.get("identifiers") or {}
    for key in ("doi", "pmid", "pmcid", "arxiv"):
        if ids.get(key):
            return f"{key}:{str(ids[key]).casefold()}"
    return "record:" + str(profile.get("record_id"))


def literature_set(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("literature set payload must be an object")
    raw = [dict(x) for x in _list(payload.get("publications") or payload.get("records")) if isinstance(x, dict)][:MAX_PUBLICATIONS]
    profiles = [normalize_publication(x) for x in raw]
    deduped: dict[str, dict[str, Any]] = {}
    duplicates: list[dict[str, Any]] = []
    for profile in profiles:
        key = _dedupe_key(profile)
        if key in deduped:
            duplicates.append({"duplicate_key": key, "kept_record_id": deduped[key]["record_id"], "duplicate_record_id": profile["record_id"], "automatic_exclusion": False})
            continue
        deduped[key] = profile
    items = list(deduped.values())
    years = Counter(str(x.get("year") or (str(x.get("published_at"))[:4] if x.get("published_at") else "unknown")) for x in items)
    journals = Counter(str(x.get("journal") or "unknown") for x in items)
    designs: Counter[str] = Counter()
    for item in items:
        for design in item.get("study_design_indicators") or []:
            designs[str(design.get("design"))] += 1
    identifiers = Counter()
    for item in items:
        for key, value in (item.get("identifiers") or {}).items():
            if value:
                identifiers[key] += 1
    dataset_records = [{
        "record_id": x["record_id"],
        "title": x.get("title"),
        "published_at": x.get("published_at"),
        "source_type": (x.get("publication_types") or ["scientific-publication"])[0],
        "authors": [a.get("name") for a in x.get("authors") or [] if a.get("name")],
        "status": "retracted" if (x.get("correction_retraction") or {}).get("retracted") else "current-or-unspecified",
    } for x in items]
    dataset = build_structured_dataset({
        "title": _clean(payload.get("title") or "Scientific literature set", 240),
        "records": dataset_records,
        "fields": ["record_id", "title", "published_at", "source_type", "authors", "status"],
    })
    review = None
    if isinstance(payload.get("review"), dict):
        review_payload = dict(payload["review"])
        review_payload.setdefault("records", [{"record_id": x["record_id"], "title": x.get("title")} for x in items])
        review = build_literature_review(review_payload)
    basis = {"publication_fingerprints": [x["publication_fingerprint_sha256"] for x in items], "duplicates": duplicates, "title": payload.get("title")}
    return {
        "schema": LITERATURE_SET_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "literature_set_id": "scientific-literature-set:" + _fp(basis)[:24],
        "literature_set_fingerprint_sha256": _fp(basis),
        "title": _clean(payload.get("title") or "Scientific literature set", 500),
        "publications": items,
        "duplicates": duplicates,
        "metrics": {
            "provided_publication_count": len(raw),
            "unique_publication_count": len(items),
            "duplicate_candidate_count": len(duplicates),
            "year_counts": dict(sorted(years.items())),
            "journal_counts": dict(journals.most_common(100)),
            "study_design_indicator_counts": dict(sorted(designs.items())),
            "identifier_coverage_counts": dict(sorted(identifiers.items())),
            "retracted_supplied_count": sum(1 for x in items if (x.get("correction_retraction") or {}).get("retracted")),
            "corrected_supplied_count": sum(1 for x in items if (x.get("correction_retraction") or {}).get("corrected")),
        },
        "structured_dataset": dataset,
        "review": review,
        "database_persisted": False,
        "guardrails": guardrails(),
    }


def review_intelligence(payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    records = [dict(x) for x in _list(body.get("records") or body.get("publications")) if isinstance(x, dict)]
    normalized = [normalize_publication(x) for x in records]
    review_payload = dict(body.get("review") or body)
    review_payload["records"] = [{"record_id": x["record_id"], "title": x.get("title"), "doi": (x.get("identifiers") or {}).get("doi")} for x in normalized]
    review = build_literature_review(review_payload)
    return {
        "schema": "sc-library-scientific-literature-review-intelligence/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "review": review,
        "publication_profiles": normalized,
        "review_inclusion_implies_truth": False,
        "automatic_meta_analysis": False,
        "automatic_consensus_inference": False,
        "guardrails": guardrails(),
    }


def validate_publication(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    warnings: list[str] = []
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["publication payload must be an object"], "warnings": [], "guardrails": guardrails()}
    profile = normalize_publication(payload)
    if not profile.get("title"):
        warnings.append("publication title is absent")
    if not any((profile.get("identifiers") or {}).values()):
        warnings.append("no DOI/PMID/PMCID/arXiv/ISSN identifier is present")
    if not profile.get("authors"):
        warnings.append("author metadata is absent")
    if (profile.get("correction_retraction") or {}).get("retracted") and not (profile.get("correction_retraction") or {}).get("notice_url"):
        warnings.append("retraction is supplied but no notice URL is recorded")
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "profile": profile,
        "validation_implies_scientific_validity": False,
        "guardrails": guardrails(),
    }


def _usable(component: dict[str, Any]) -> bool:
    if "ready" in component:
        return bool(component.get("ready"))
    return component.get("state") in {"ready", "degraded"}


def readiness() -> dict[str, Any]:
    discovery = advanced_discovery_readiness()
    provenance = provenance_graph_readiness()
    structured = structured_evidence_readiness()
    errors: list[str] = []
    if not _usable(discovery):
        errors.append("advanced-discovery-not-ready")
    if not _usable(provenance):
        errors.append("provenance-graph-not-ready")
    if not _usable(structured):
        errors.append("structured-evidence-not-ready")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if not errors else "blocked",
        "ready": not errors,
        "errors": errors,
        "authority": "python-backend-composition",
        "database_migration_required": False,
        "wordpress_required": False,
        "components": {
            "advanced_discovery": discovery,
            "provenance_graph": provenance,
            "structured_evidence": structured,
            "scientific_document_intelligence": {"state": "ready", "runtime": "python", "persistence": "library-records-and-supplied-document-objects"},
            "literature_review": {"state": "ready", "runtime": "python", "automatic_screening": False},
        },
        "guardrails": guardrails(),
    }
