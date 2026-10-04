from __future__ import annotations

from datetime import date, datetime
from hashlib import sha256
import json
import re
from typing import Any

from .institutional_repository_federation import (
    plan as plan_institutional_repository_federation,
    readiness as institutional_repository_federation_readiness,
    repository_profiles as institutional_repository_profiles,
)
from .ocr_htr_transcription_lineage import readiness as text_derivation_readiness
from .original_language_corpus import readiness as original_language_readiness
from .source_transparency import readiness as source_transparency_readiness

LIBRARY_VERSION = "6.12.0"
BACKEND_VERSION = "3.12.0"

CONTRACT = "sc-library-historical-archive-primary-source-intelligence/1.0"
READINESS_CONTRACT = "sc-library-historical-archive-primary-source-readiness/1.0"
SOURCE_TYPE_REGISTRY_CONTRACT = "sc-library-historical-source-type-registry/1.0"
DATE_ASSERTION_CONTRACT = "sc-library-historical-date-assertion/1.0"
PRIMARY_SOURCE_CONTRACT = "sc-library-primary-source-object/1.0"
PROVENANCE_CHAIN_CONTRACT = "sc-library-primary-source-provenance-chain/1.0"
SOURCE_CRITICISM_CONTRACT = "sc-library-primary-source-criticism/1.0"
COMPARISON_CONTRACT = "sc-library-primary-source-comparison/1.0"
SEARCH_PLAN_CONTRACT = "sc-library-historical-archive-search-plan/1.0"
TIMELINE_CONTRACT = "sc-library-historical-primary-source-timeline/1.0"
PACKET_CONTRACT = "sc-library-primary-source-research-packet/1.0"
HANDOFF_CONTRACT = "sc-library-primary-source-ingestion-handoff/1.0"

MAX_TEXT = 12000
MAX_SOURCES = 500
MAX_COMPARISON_SOURCES = 50

SOURCE_TYPES: tuple[dict[str, Any], ...] = (
    {"key": "government-record", "label": "Government and administrative record", "examples": ["register", "report", "memorandum", "proceedings", "census", "court-record"]},
    {"key": "personal-papers", "label": "Personal papers and correspondence", "examples": ["letter", "diary", "journal", "notebook", "memoir-draft"]},
    {"key": "manuscript", "label": "Manuscript or archival textual item", "examples": ["manuscript", "typescript", "draft", "annotated-copy"]},
    {"key": "periodical", "label": "Contemporaneous newspaper or periodical", "examples": ["newspaper", "magazine", "gazette", "newsletter"]},
    {"key": "oral-history", "label": "Oral history or recorded testimony", "examples": ["interview", "testimony", "oral-history"]},
    {"key": "photograph", "label": "Photograph or visual record", "examples": ["photograph", "negative", "slide", "contact-sheet"]},
    {"key": "cartographic", "label": "Map or cartographic record", "examples": ["map", "plan", "chart", "survey"]},
    {"key": "audiovisual", "label": "Audio, film, or video record", "examples": ["audio", "film", "video", "broadcast"]},
    {"key": "ephemera", "label": "Ephemera and material-publication record", "examples": ["poster", "brochure", "leaflet", "ticket", "advertisement"]},
    {"key": "dataset-register", "label": "Historical dataset, register, or tabular source", "examples": ["ledger", "register", "roll", "table", "dataset"]},
    {"key": "artifact-documentation", "label": "Documentation of a physical artifact", "examples": ["catalog-record", "inscription", "object-record", "field-note"]},
    {"key": "other-primary", "label": "Other candidate primary source", "examples": []},
)

DATE_QUALIFIERS = {"exact", "circa", "before", "after", "between", "range", "decade", "century", "unknown"}
DERIVATION_KINDS = {"original", "facsimile", "scan", "photograph", "microfilm", "ocr", "htr", "transcription", "translation", "edition", "excerpt", "annotation"}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any, limit: int = MAX_TEXT) -> str:
    return " ".join(str(value or "").split())[:limit]


def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        return value.strip().lower() in {"1", "true", "yes", "on"}
    return bool(value)


def guardrails() -> dict[str, bool]:
    return {
        "python_is_historical_archive_intelligence_authority": True,
        "institutional_repository_federation_remains_repository_execution_authority": True,
        "ocr_htr_transcription_lineage_remains_text_derivation_authority": True,
        "original_language_is_preserved_as_canonical_source_representation": True,
        "translation_is_a_derived_representation": True,
        "archive_visibility_implies_authenticity": False,
        "archive_visibility_implies_truth": False,
        "primary_source_label_implies_truth": False,
        "primary_source_label_implies_accuracy": False,
        "repository_description_implies_complete_context": False,
        "catalog_metadata_implies_item_content": False,
        "digitization_implies_original_object_identity": False,
        "ocr_or_htr_text_is_original_text": False,
        "translation_is_original_text": False,
        "date_uncertainty_is_collapsed_to_false_precision": False,
        "source_criticism_is_authenticity_certification": False,
        "source_criticism_is_truth_scoring": False,
        "cross_source_disagreement_is_auto_resolved": False,
        "automatic_archive_harvest": False,
        "automatic_library_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def source_type_registry() -> dict[str, Any]:
    return {
        "schema": SOURCE_TYPE_REGISTRY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_types": [dict(x) for x in SOURCE_TYPES],
        "guardrails": guardrails(),
    }


def _year(value: Any) -> int | None:
    if isinstance(value, int) and -9999 <= value <= 9999:
        return value
    text = _clean(value, 100)
    m = re.search(r"(?<!\d)(-?\d{1,4})(?!\d)", text)
    if not m:
        return None
    try:
        y = int(m.group(1))
    except ValueError:
        return None
    return y if -9999 <= y <= 9999 else None


def _iso_date(value: Any) -> str | None:
    text = _clean(value, 100)
    if not text:
        return None
    for fmt in ("%Y-%m-%d", "%Y-%m", "%Y"):
        try:
            parsed = datetime.strptime(text, fmt)
            return parsed.strftime(fmt)
        except ValueError:
            pass
    return None


def normalize_date_assertion(value: Any) -> dict[str, Any]:
    raw = _dict(value)
    if not raw and value not in (None, ""):
        raw = {"display": _clean(value, 300)}
    display = _clean(raw.get("display") or raw.get("label") or raw.get("date"), 300)
    qualifier = _clean(raw.get("qualifier"), 40).lower()
    if qualifier not in DATE_QUALIFIERS:
        low = display.lower()
        if re.search(r"\b(c\.?|ca\.?|circa)\b", low):
            qualifier = "circa"
        elif "before" in low:
            qualifier = "before"
        elif "after" in low:
            qualifier = "after"
        elif "between" in low:
            qualifier = "between"
        elif re.search(r"\b\d{3}0s\b", low):
            qualifier = "decade"
        else:
            qualifier = "exact" if _iso_date(display) else "unknown"
    start = raw.get("start") or raw.get("earliest")
    end = raw.get("end") or raw.get("latest")
    start_year = _year(raw.get("start_year")) or _year(start)
    end_year = _year(raw.get("end_year")) or _year(end)
    display_year = _year(display)
    if start_year is None and qualifier in {"exact", "circa", "before", "after", "decade"}:
        start_year = display_year
    if end_year is None and qualifier in {"exact", "circa"}:
        end_year = display_year
    if qualifier == "decade" and display_year is not None:
        start_year = (display_year // 10) * 10
        end_year = start_year + 9
    if start_year is not None and end_year is not None and start_year > end_year:
        start_year, end_year = end_year, start_year
    precision = _clean(raw.get("precision"), 40).lower()
    if precision not in {"day", "month", "year", "decade", "century", "range", "unknown"}:
        iso = _iso_date(display)
        precision = "day" if iso and len(iso) == 10 else "month" if iso and len(iso) == 7 else "year" if iso else "range" if start_year != end_year and start_year is not None else "unknown"
    normalized = {
        "schema": DATE_ASSERTION_CONTRACT,
        "display": display or None,
        "qualifier": qualifier,
        "precision": precision,
        "start": _clean(start, 100) or None,
        "end": _clean(end, 100) or None,
        "start_year": start_year,
        "end_year": end_year,
        "calendar": _clean(raw.get("calendar") or "gregorian", 80),
        "original_expression": _clean(raw.get("original_expression") or display, 300) or None,
        "normalized_without_false_precision": True,
    }
    normalized["date_assertion_fingerprint_sha256"] = _fp(normalized)
    return normalized


def _normalize_agents(value: Any) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for item in _list(value):
        row = _dict(item) if isinstance(item, dict) else {"name": item}
        name = _clean(row.get("name") or row.get("label"), 500)
        if not name:
            continue
        out.append({
            "name": name,
            "role": _clean(row.get("role") or "creator", 100),
            "authority_id": _clean(row.get("authority_id") or row.get("identifier"), 500) or None,
            "authority_source": _clean(row.get("authority_source"), 100) or None,
            "as_recorded": _clean(row.get("as_recorded") or name, 500),
        })
    return out


def _normalize_hierarchy(value: Any) -> dict[str, Any]:
    raw = _dict(value)
    levels = []
    for key in ("repository", "fonds", "collection", "record_group", "series", "subseries", "folder", "item"):
        val = _clean(raw.get(key), 1000)
        if val:
            levels.append({"level": key, "label": val})
    for item in _list(raw.get("levels")):
        row = _dict(item)
        level = _clean(row.get("level"), 80)
        label = _clean(row.get("label") or row.get("title"), 1000)
        if level and label and {"level": level, "label": label} not in levels:
            levels.append({"level": level, "label": label})
    return {
        "levels": levels,
        "shelfmark": _clean(raw.get("shelfmark") or raw.get("call_number") or raw.get("reference_code"), 500) or None,
        "collection_identifier": _clean(raw.get("collection_identifier"), 500) or None,
        "parent_identifier": _clean(raw.get("parent_identifier"), 500) or None,
    }


def _normalize_derivations(value: Any) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for idx, item in enumerate(_list(value)):
        row = _dict(item)
        kind = _clean(row.get("kind") or row.get("type"), 80).lower()
        if kind not in DERIVATION_KINDS:
            kind = "annotation" if kind else "scan"
        out.append({
            "sequence": idx,
            "kind": kind,
            "representation_id": _clean(row.get("representation_id") or row.get("id"), 500) or None,
            "source_representation_id": _clean(row.get("source_representation_id") or row.get("derived_from"), 500) or None,
            "language": _clean(row.get("language"), 80) or None,
            "script": _clean(row.get("script"), 80) or None,
            "agent": _clean(row.get("agent") or row.get("engine") or row.get("editor"), 500) or None,
            "method": _clean(row.get("method"), 500) or None,
            "checksum_sha256": _clean(row.get("checksum_sha256"), 64).lower() or None,
            "confidence": row.get("confidence"),
            "notes": _clean(row.get("notes"), 2000) or None,
            "is_original_text": kind == "original",
            "derived_representation": kind != "original",
        })
    return out


def normalize_primary_source(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    title = _clean(raw.get("title") or raw.get("label"), 2000)
    if not title:
        raise ValueError("title is required for a primary-source object")
    source_type = _clean(raw.get("source_type") or raw.get("type") or "other-primary", 100).lower()
    known_types = {x["key"] for x in SOURCE_TYPES}
    if source_type not in known_types:
        source_type = "other-primary"
    repository = _dict(raw.get("repository"))
    digital = _dict(raw.get("digital_surrogate") or raw.get("digital"))
    rights = _dict(raw.get("rights"))
    hierarchy = _normalize_hierarchy(raw.get("archival_context") or raw.get("hierarchy"))
    derivations = _normalize_derivations(raw.get("derivations") or raw.get("representations"))
    languages = []
    for item in _list(raw.get("languages")) + ([raw.get("language")] if raw.get("language") else []):
        value = _clean(item, 80)
        if value and value not in languages:
            languages.append(value)
    identifiers = []
    for item in _list(raw.get("identifiers")):
        row = _dict(item) if isinstance(item, dict) else {"value": item}
        val = _clean(row.get("value") or row.get("identifier"), 1000)
        if val:
            identifiers.append({"scheme": _clean(row.get("scheme") or "local", 100), "value": val})
    for scheme, key in (("ark", "ark"), ("handle", "handle"), ("doi", "doi"), ("uri", "persistent_id")):
        val = _clean(raw.get(key), 1000)
        if val and not any(x["value"] == val for x in identifiers):
            identifiers.append({"scheme": scheme, "value": val})
    creation_date = normalize_date_assertion(raw.get("creation_date") or raw.get("date") or {})
    coverage_dates = [normalize_date_assertion(x) for x in _list(raw.get("coverage_dates"))]
    basis = {
        "title": title,
        "source_type": source_type,
        "repository": repository,
        "hierarchy": hierarchy,
        "identifiers": identifiers,
        "creation_date": creation_date,
        "digital_url": digital.get("url") or raw.get("source_url"),
    }
    object_id = _clean(raw.get("primary_source_id"), 500) or "primary-source:" + _fp(basis)[:32]
    obj = {
        "schema": PRIMARY_SOURCE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "primary_source_id": object_id,
        "primary_source_fingerprint_sha256": _fp(basis),
        "title": title,
        "source_type": source_type,
        "candidate_primary_source": True,
        "creator_agents": _normalize_agents(raw.get("creator_agents") or raw.get("creators") or raw.get("authors")),
        "creation_date": creation_date,
        "coverage_dates": coverage_dates,
        "repository": {
            "name": _clean(repository.get("name") or repository.get("institution"), 1000) or None,
            "source_key": _clean(repository.get("source_key"), 191) or None,
            "repository_url": _clean(repository.get("repository_url") or repository.get("url"), 2000) or None,
            "country": _clean(repository.get("country"), 100) or None,
        },
        "archival_context": hierarchy,
        "identifiers": identifiers,
        "languages": languages,
        "original_language": _clean(raw.get("original_language") or (languages[0] if languages else ""), 80) or None,
        "original_script": _clean(raw.get("original_script") or raw.get("script"), 80) or None,
        "physical_description": _dict(raw.get("physical_description")),
        "digital_surrogate": {
            "url": _clean(digital.get("url") or raw.get("source_url"), 4000) or None,
            "manifest_url": _clean(digital.get("manifest_url") or raw.get("iiif_manifest"), 4000) or None,
            "media_type": _clean(digital.get("media_type"), 200) or None,
            "checksum_sha256": _clean(digital.get("checksum_sha256"), 64).lower() or None,
            "digitization_notes": _clean(digital.get("digitization_notes"), 2000) or None,
        },
        "rights": {
            "statement": _clean(rights.get("statement"), 2000) or None,
            "license": _clean(rights.get("license"), 1000) or None,
            "access": _clean(rights.get("access"), 500) or None,
            "reuse_verified": _bool(rights.get("reuse_verified")),
        },
        "extent": _clean(raw.get("extent"), 1000) or None,
        "abstract_or_scope_note": _clean(raw.get("abstract") or raw.get("scope_note") or raw.get("description"), 5000) or None,
        "derivations": derivations,
        "transcription": _clean(raw.get("transcription"), MAX_TEXT) or None,
        "translation": _clean(raw.get("translation"), MAX_TEXT) or None,
        "notes": _clean(raw.get("notes"), 4000) or None,
        "guardrails": guardrails(),
    }
    obj["object_fingerprint_sha256"] = _fp({k: v for k, v in obj.items() if k != "object_fingerprint_sha256"})
    return obj


def provenance_chain(payload: dict[str, Any]) -> dict[str, Any]:
    source = normalize_primary_source(_dict(payload.get("source") or payload))
    chain = []
    chain.append({
        "sequence": 0,
        "kind": "described-archival-object",
        "representation_id": source["primary_source_id"],
        "derived": False,
        "fingerprint_sha256": source["object_fingerprint_sha256"],
    })
    for idx, row in enumerate(source.get("derivations") or [], start=1):
        item = dict(row)
        item["sequence"] = idx
        item["derived"] = item.get("kind") != "original"
        item["fingerprint_sha256"] = _fp(item)
        chain.append(item)
    basis = {"source": source["primary_source_id"], "chain": chain}
    return {
        "schema": PROVENANCE_CHAIN_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "provenance_chain_id": "primary-source-provenance:" + _fp(basis)[:32],
        "provenance_chain_fingerprint_sha256": _fp(basis),
        "primary_source_id": source["primary_source_id"],
        "chain": chain,
        "derived_representation_count": sum(1 for x in chain if x.get("derived")),
        "original_and_derived_are_distinct": True,
        "guardrails": guardrails(),
    }


def analyze_primary_source(payload: dict[str, Any]) -> dict[str, Any]:
    source = normalize_primary_source(_dict(payload.get("source") or payload))
    flags: list[str] = []
    questions: list[str] = []
    context = source["archival_context"]
    repo = source["repository"]
    if not source["creator_agents"]:
        flags.append("creator-not-specified")
        questions.append("Who created this source, and how is that attribution established?")
    if source["creation_date"]["qualifier"] == "unknown":
        flags.append("creation-date-uncertain")
        questions.append("What is the basis for dating this source, and how precise is that date?")
    if not repo.get("name"):
        flags.append("repository-not-specified")
        questions.append("Where is the described archival object or authoritative surrogate held?")
    if not context.get("levels") and not context.get("shelfmark"):
        flags.append("archival-context-limited")
        questions.append("What fonds, collection, series, folder, or item context surrounds this source?")
    if source.get("translation"):
        flags.append("translation-present")
        questions.append("Which passages depend on translation, and is the original-language text available for comparison?")
    if source.get("transcription"):
        flags.append("transcription-present")
        questions.append("How was the transcription produced, checked, and linked to the image or original object?")
    derivation_kinds = [x.get("kind") for x in source.get("derivations") or []]
    if any(k in {"ocr", "htr"} for k in derivation_kinds):
        flags.append("machine-derived-text-present")
        questions.append("Which quoted or searched passages depend on OCR/HTR output, and have they been checked against the image?")
    if not source["rights"].get("statement") and not source["rights"].get("license"):
        flags.append("rights-status-not-specified")
    if source["digital_surrogate"].get("url") and not source["digital_surrogate"].get("checksum_sha256"):
        flags.append("surrogate-checksum-not-provided")
    mediation_depth = len([x for x in derivation_kinds if x != "original"])
    analysis = {
        "schema": SOURCE_CRITICISM_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "primary_source_id": source["primary_source_id"],
        "source_fingerprint_sha256": source["object_fingerprint_sha256"],
        "observations": {
            "creator_recorded": bool(source["creator_agents"]),
            "date_qualifier": source["creation_date"]["qualifier"],
            "date_precision": source["creation_date"]["precision"],
            "repository_recorded": bool(repo.get("name")),
            "archival_hierarchy_depth": len(context.get("levels") or []),
            "shelfmark_recorded": bool(context.get("shelfmark")),
            "original_language_recorded": bool(source.get("original_language")),
            "derivation_kinds": derivation_kinds,
            "mediation_depth": mediation_depth,
            "transcription_present": bool(source.get("transcription")),
            "translation_present": bool(source.get("translation")),
            "digital_surrogate_present": bool(source["digital_surrogate"].get("url")),
            "rights_or_license_recorded": bool(source["rights"].get("statement") or source["rights"].get("license")),
        },
        "flags": sorted(set(flags)),
        "research_questions": questions,
        "authenticity_certified": False,
        "truth_score": None,
        "accuracy_score": None,
        "interpretive_judgment_required": True,
        "guardrails": guardrails(),
    }
    analysis["analysis_fingerprint_sha256"] = _fp({k: v for k, v in analysis.items() if k != "analysis_fingerprint_sha256"})
    return analysis


def compare_primary_sources(payload: dict[str, Any]) -> dict[str, Any]:
    sources = [normalize_primary_source(_dict(x)) for x in _list(payload.get("sources"))[:MAX_COMPARISON_SOURCES]]
    if len(sources) < 2:
        raise ValueError("at least two sources are required for comparison")
    dimensions: dict[str, Any] = {}
    for field, getter in {
        "source_type": lambda s: s.get("source_type"),
        "repository": lambda s: (s.get("repository") or {}).get("name"),
        "creation_date": lambda s: (s.get("creation_date") or {}).get("display"),
        "date_qualifier": lambda s: (s.get("creation_date") or {}).get("qualifier"),
        "original_language": lambda s: s.get("original_language"),
        "creator_agents": lambda s: tuple(x.get("name") for x in s.get("creator_agents") or []),
    }.items():
        values = [getter(s) for s in sources]
        dimensions[field] = {"values": values, "same_across_sources": len({_canon(v) for v in values}) == 1}
    relationships = []
    for item in _list(payload.get("relationships")):
        row = _dict(item)
        relationships.append({
            "source_a": _clean(row.get("source_a"), 500),
            "source_b": _clean(row.get("source_b"), 500),
            "relationship": _clean(row.get("relationship"), 200),
            "basis": _clean(row.get("basis"), 2000) or None,
            "human_asserted": True,
        })
    disagreement_dimensions = [k for k, v in dimensions.items() if not v["same_across_sources"]]
    basis = {"ids": [s["primary_source_id"] for s in sources], "dimensions": dimensions, "relationships": relationships}
    return {
        "schema": COMPARISON_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "comparison_id": "primary-source-comparison:" + _fp(basis)[:32],
        "comparison_fingerprint_sha256": _fp(basis),
        "source_count": len(sources),
        "source_ids": basis["ids"],
        "dimensions": dimensions,
        "disagreement_dimensions": disagreement_dimensions,
        "relationships": relationships,
        "disagreement_auto_resolved": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }


def _year_bounds(value: Any) -> tuple[int | None, int | None]:
    d = normalize_date_assertion(value)
    return d.get("start_year"), d.get("end_year")


def plan_archive_search(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    q = _clean(raw.get("q") or raw.get("query"), 1000)
    if not q:
        raise ValueError("q is required for historical archive search planning")
    start_year, end_year = _year_bounds(raw.get("date") or {
        "start_year": raw.get("start_year"), "end_year": raw.get("end_year"), "qualifier": "range"
    })
    source_types = sorted({_clean(x, 100).lower() for x in _list(raw.get("source_types")) if _clean(x, 100)})
    languages = sorted({_clean(x, 80) for x in _list(raw.get("languages")) if _clean(x, 80)})
    source_keys = sorted({_clean(x, 191) for x in _list(raw.get("source_keys")) if _clean(x, 191)})
    base_payload = {
        "q": q,
        "source_keys": source_keys,
        "source_families": _list(raw.get("source_families")),
        "protocols": _list(raw.get("protocols")),
        "limit_per_source": raw.get("limit_per_source") or 8,
        "max_sources": raw.get("max_sources") or 20,
    }
    federation_plan = plan_institutional_repository_federation(base_payload)
    local_filters = {
        "source_types": source_types,
        "languages": languages,
        "date": {"start_year": start_year, "end_year": end_year},
        "repository_names": sorted({_clean(x, 500) for x in _list(raw.get("repository_names")) if _clean(x, 500)}),
        "collections": sorted({_clean(x, 500) for x in _list(raw.get("collections")) if _clean(x, 500)}),
        "primary_source_candidates_only": raw.get("primary_source_candidates_only", True) is not False,
        "include_derived_text_for_retrieval": _bool(raw.get("include_derived_text_for_retrieval", True)),
    }
    basis = {"query": q, "federation": federation_plan.get("plan_fingerprint_sha256"), "local_filters": local_filters}
    return {
        "schema": SEARCH_PLAN_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "plan_id": "historical-archive-search-plan:" + _fp(basis)[:32],
        "plan_fingerprint_sha256": _fp(basis),
        "query": q,
        "repository_federation_plan": federation_plan,
        "historical_local_filters": local_filters,
        "execution_notes": [
            "Repository protocols differ; archival date/type/language filters may require post-filtering after source-local retrieval.",
            "OCR, HTR, transcription, and translation text may aid retrieval but remain derived representations linked to the source image/object.",
        ],
        "automatic_execution": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }


def build_timeline(payload: dict[str, Any]) -> dict[str, Any]:
    sources = [normalize_primary_source(_dict(x)) for x in _list(payload.get("sources"))[:MAX_SOURCES]]
    events = []
    for source in sources:
        d = source["creation_date"]
        events.append({
            "primary_source_id": source["primary_source_id"],
            "title": source["title"],
            "display_date": d.get("display"),
            "qualifier": d.get("qualifier"),
            "precision": d.get("precision"),
            "start_year": d.get("start_year"),
            "end_year": d.get("end_year"),
            "false_precision_added": False,
        })
    events.sort(key=lambda x: (x["start_year"] is None, x["start_year"] if x["start_year"] is not None else 10**9, x["title"]))
    basis = {"events": events}
    return {
        "schema": TIMELINE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "timeline_id": "historical-primary-source-timeline:" + _fp(basis)[:32],
        "timeline_fingerprint_sha256": _fp(basis),
        "event_count": len(events),
        "events": events,
        "uncertain_dates_preserved": True,
        "guardrails": guardrails(),
    }


def build_primary_source_packet(payload: dict[str, Any]) -> dict[str, Any]:
    sources = [normalize_primary_source(_dict(x)) for x in _list(payload.get("sources"))[:MAX_SOURCES]]
    if not sources:
        raise ValueError("at least one source is required for a primary-source packet")
    analyses = [analyze_primary_source({"source": s}) for s in sources]
    timeline = build_timeline({"sources": sources})
    project_id = _clean(payload.get("project_id"), 500) or None
    research_question = _clean(payload.get("research_question"), 4000) or None
    basis = {
        "project_id": project_id,
        "research_question": research_question,
        "source_fingerprints": [s["object_fingerprint_sha256"] for s in sources],
        "timeline": timeline["timeline_fingerprint_sha256"],
    }
    packet_id = "primary-source-packet:" + _fp(basis)[:32]
    manifest = [
        {"primary_source_id": s["primary_source_id"], "fingerprint_sha256": s["object_fingerprint_sha256"], "title": s["title"]}
        for s in sources
    ]
    return {
        "schema": PACKET_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "packet_id": packet_id,
        "packet_fingerprint_sha256": _fp(basis),
        "project_id": project_id,
        "research_question": research_question,
        "source_count": len(sources),
        "sources": sources,
        "source_criticism": analyses,
        "timeline": timeline,
        "manifest": manifest,
        "persisted": False,
        "externally_published": False,
        "evidence_promoted": False,
        "guardrails": guardrails(),
    }


def ingestion_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    packet = build_primary_source_packet(payload) if payload.get("sources") else _dict(payload.get("packet"))
    sources = [normalize_primary_source(_dict(x)) for x in _list(packet.get("sources"))[:MAX_SOURCES]]
    if not sources:
        raise ValueError("packet sources are required for ingestion handoff")
    records = []
    for s in sources:
        records.append({
            "external_identity_key": s["primary_source_id"],
            "record_type": "historical-primary-source",
            "title": s["title"],
            "source_url": s["digital_surrogate"].get("url"),
            "repository_source_key": s["repository"].get("source_key"),
            "persistent_identifiers": s["identifiers"],
            "published_at": s["creation_date"].get("display"),
            "language": s.get("original_language"),
            "metadata": {
                "source_type": s["source_type"],
                "archival_context": s["archival_context"],
                "creation_date": s["creation_date"],
                "rights": s["rights"],
                "derivations": s["derivations"],
            },
            "provenance": {
                "primary_source_fingerprint_sha256": s["object_fingerprint_sha256"],
                "repository": s["repository"],
                "digital_surrogate": s["digital_surrogate"],
            },
        })
    basis = {"records": records, "project_id": packet.get("project_id")}
    return {
        "schema": HANDOFF_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff_id": "primary-source-ingestion-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "target": "library-source-ingestion",
        "project_id": packet.get("project_id"),
        "record_count": len(records),
        "records": records,
        "persisted": False,
        "execution_required": True,
        "automatic_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }


def contract() -> dict[str, Any]:
    resources = [
        "historical-source-type-registry",
        "primary-source-object-normalization",
        "archival-hierarchy-and-shelfmark-context",
        "uncertain-date-preservation",
        "original-surrogate-ocr-htr-transcription-translation-lineage",
        "primary-source-criticism-observations",
        "cross-source-comparison-without-auto-resolution",
        "historical-archive-search-planning",
        "uncertainty-preserving-primary-source-timelines",
        "reproducible-primary-source-research-packets",
        "explicit-source-ingestion-handoffs",
    ]
    basis = {"resources": resources, "library_version": LIBRARY_VERSION, "backend_version": BACKEND_VERSION, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "intelligence_id": "historical-archive-primary-source:" + _fp(basis)[:32],
        "intelligence_fingerprint_sha256": _fp(basis),
        "state": "authoritative-composition",
        "authority": "python-backend",
        "repository_execution_authority": "institutional-repository-federation",
        "text_derivation_authority": "ocr-htr-transcription-lineage",
        "resources": resources,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    deps = {
        "institutional_repository_federation": institutional_repository_federation_readiness(),
        "text_derivation_lineage": text_derivation_readiness(),
        "original_language_corpus": original_language_readiness(),
        "source_transparency": source_transparency_readiness(),
    }
    blocking: list[str] = []
    degraded: list[str] = []
    for key, value in deps.items():
        state = str(_dict(value).get("state") or "unknown")
        if state in {"blocked", "unavailable", "failed"} and key == "institutional_repository_federation":
            blocking.append(key + ":" + state)
        elif state not in {"ready", "degraded"}:
            degraded.append(key + ":" + state)
        elif state == "degraded":
            degraded.append(key + ":degraded")
    state = "blocked" if blocking else ("degraded" if degraded else "ready")
    profiles = institutional_repository_profiles()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "ready": not blocking,
        "blocking": blocking,
        "degraded": degraded,
        "repository_profile_count": len(profiles),
        "dependencies": {
            k: {"state": _dict(v).get("state"), "library_version": _dict(v).get("library_version"), "backend_version": _dict(v).get("backend_version")}
            for k, v in deps.items()
        },
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def schema_registry() -> dict[str, Any]:
    return {
        "schema": "sc-library-historical-archive-primary-source-schema-registry/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "schemas": {
            "contract": CONTRACT,
            "readiness": READINESS_CONTRACT,
            "source_type_registry": SOURCE_TYPE_REGISTRY_CONTRACT,
            "date_assertion": DATE_ASSERTION_CONTRACT,
            "primary_source": PRIMARY_SOURCE_CONTRACT,
            "provenance_chain": PROVENANCE_CHAIN_CONTRACT,
            "source_criticism": SOURCE_CRITICISM_CONTRACT,
            "comparison": COMPARISON_CONTRACT,
            "search_plan": SEARCH_PLAN_CONTRACT,
            "timeline": TIMELINE_CONTRACT,
            "packet": PACKET_CONTRACT,
            "ingestion_handoff": HANDOFF_CONTRACT,
        },
        "guardrails": guardrails(),
    }
