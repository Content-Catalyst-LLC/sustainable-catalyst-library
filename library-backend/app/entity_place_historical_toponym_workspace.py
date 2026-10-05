from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .cross_language_resolution import (
    build_authority_package,
    build_resolution_case,
    get_resolution_case,
    readiness as cross_language_resolution_readiness,
    validate_decision_payload,
)

LIBRARY_VERSION = "6.19.0"
BACKEND_VERSION = "3.19.0"
WEB_VERSION = "2.19.0"
SDK_VERSION = "1.19.0"

CONTRACT = "sc-library-entity-place-historical-toponym-workspace/1.0"
READINESS_CONTRACT = "sc-library-entity-place-historical-toponym-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-entity-place-historical-toponym-bootstrap/1.0"
TIMELINE_CONTRACT = "sc-library-historical-toponym-timeline/1.0"
MATRIX_CONTRACT = "sc-library-entity-resolution-candidate-matrix/1.0"
DECISION_PREVIEW_CONTRACT = "sc-library-entity-resolution-decision-preview/1.0"
EXPORT_CONTRACT = "sc-library-entity-place-research-package/1.0"
HANDOFF_CONTRACT = "sc-library-entity-place-persistence-handoff-preview/1.0"

TOPONYM_RELATIONS = {"canonical","historical","former","endonym","exonym","transliteration","alias","variant","other"}
MAX_ENTITIES = 500
MAX_CANDIDATES = 100

def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}

def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []

def _clean(value: Any, limit: int = 10000) -> str:
    return str(value or "").strip()[:limit]

def guardrails() -> dict[str, bool]:
    return {
        "existing_v548_cross_language_resolution_is_durable_authority": True,
        "workspace_creates_parallel_entity_store": False,
        "workspace_preview_is_persisted": False,
        "persistence_requires_explicit_signed_existing_authority": True,
        "original_name_forms_are_preserved": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "candidate_score_is_probability": False,
        "candidate_rank_is_truth": False,
        "candidate_rank_is_winner_selection": False,
        "name_similarity_establishes_identity": False,
        "same_coordinates_establish_identity": False,
        "country_code_match_establishes_identity": False,
        "historical_toponym_overlap_establishes_identity": False,
        "historical_validity_window_establishes_identity": False,
        "toponym_timeline_asserts_continuous_usage": False,
        "missing_validity_date_is_inferred": False,
        "automatic_entity_merge": False,
        "automatic_resolution": False,
        "automatic_translation": False,
        "automatic_transliteration": False,
        "ambiguity_preserved": True,
        "explicit_human_or_external_adjudication_required": True,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }

def contract() -> dict[str, Any]:
    resources = [
        "entity-authority-preview","multilingual-name-forms","place-and-jurisdiction-context",
        "historical-toponym-timeline","temporal-resolution-preview","candidate-comparison-matrix",
        "ambiguity-preservation","decision-preview","persisted-case-inspection",
        "research-package-export","signed-persistence-handoff-preview",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "entity-place-historical-toponym:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "durable_resolution_authority": "v5.48.0-python-cross-language-resolution-postgresql",
        "route": "/research/entities",
        "api_base": "/api/library/v1/entity-place-workspace",
        "resources": resources,
        "limits": {"preview_entities": MAX_ENTITIES, "candidate_limit": MAX_CANDIDATES},
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    dep = cross_language_resolution_readiness()
    dep_state = str(dep.get("state") or "unknown")
    blocking = [] if dep_state == "ready" else ["v5.48-cross-language-resolution-not-ready"]
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready" if not blocking else "degraded",
        "ready": not blocking,
        "blocking": blocking,
        "degraded": [],
        "authority": "python-backend-composition",
        "durable_resolution_authority": "v5.48.0-python-cross-language-resolution-postgresql",
        "server_side_workspace_state": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "dependencies": {"cross_language_resolution_v5_48": dep},
        "guardrails": guardrails(),
    }

def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/entities",
        "readiness": readiness(),
        "entity_types": ["person","organization","place","work","event","concept","group","jurisdiction","other"],
        "name_relations": ["canonical","alias","variant","historical","endonym","exonym","transliteration","abbreviation","former","other"],
        "decision_states": ["accepted","rejected","ambiguous","unresolved"],
        "operations": ["authority-preview","resolve-preview","toponym-timeline","candidate-matrix","decision-preview","persisted-case","export","persistence-handoff-preview"],
        "persistence": {
            "workspace_auto_persists": False,
            "authority_endpoint": "/api/library/v1/admin/language/authorities",
            "resolution_case_endpoint": "/api/library/v1/admin/language/entity-resolution/cases",
            "resolution_decision_template": "/api/library/v1/admin/language/entity-resolution/{case_id}/decisions",
            "signed_request_required": True,
        },
        "guardrails": guardrails(),
    }

def _authority_payload(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    authority = payload.get("authority") if isinstance(payload.get("authority"), dict) else payload
    entities = _list(authority.get("entities"))
    if not entities:
        raise ValueError("authority-entities-required")
    if len(entities) > MAX_ENTITIES:
        raise ValueError(f"preview-entity-limit-exceeded:{MAX_ENTITIES}")
    return dict(authority)

def authority_preview(payload: dict[str, Any]) -> dict[str, Any]:
    package = build_authority_package(_authority_payload(payload))
    return {
        "schema": "sc-library-entity-authority-preview/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": package,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }

def _query(payload: dict[str, Any]) -> dict[str, Any]:
    q = payload.get("query") if isinstance(payload.get("query"), dict) else {}
    if not q:
        q = {
            "name": payload.get("name"),
            "alternate_forms": payload.get("alternate_forms") or [],
            "language_bcp47": payload.get("language_bcp47") or payload.get("language"),
            "script_iso15924": payload.get("script_iso15924"),
            "entity_type": payload.get("entity_type"),
            "year": payload.get("year"),
        }
    if not _clean(q.get("name") or q.get("query"), 1000):
        raise ValueError("query-name-required")
    return q

def resolution_preview(payload: dict[str, Any]) -> dict[str, Any]:
    authority = _authority_payload(payload)
    query = _query(payload)
    limit = max(1, min(MAX_CANDIDATES, int(payload.get("limit") or 25)))
    case = build_resolution_case(authority, query, limit=limit)
    return {
        "schema": "sc-library-entity-place-resolution-preview/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "resolution_case": case,
        "workspace_persisted": False,
        "automatic_decision": False,
        "guardrails": guardrails(),
    }

def _temporal_status(valid_from: int | None, valid_to: int | None, year: int | None) -> str:
    if year is None:
        return "not-evaluated"
    if valid_from is not None and year < valid_from:
        return "outside-window"
    if valid_to is not None and year > valid_to:
        return "outside-window"
    if valid_from is None and valid_to is None:
        return "undated"
    return "within-window"

def historical_toponym_timeline(payload: dict[str, Any]) -> dict[str, Any]:
    package = build_authority_package(_authority_payload(payload))
    entity_id_filter = _clean(payload.get("entity_id"), 500) or None
    raw_year = payload.get("year")
    year = int(raw_year) if raw_year not in (None, "") else None
    entities = []
    for entity in package.get("entities") or []:
        if entity_id_filter and entity.get("entity_id") != entity_id_filter:
            continue
        if entity.get("entity_type") not in {"place","jurisdiction"}:
            continue
        names = []
        for form in entity.get("names") or []:
            relation = form.get("relation_type")
            if relation not in TOPONYM_RELATIONS:
                continue
            names.append({
                "form_id": form.get("form_id"),
                "text": form.get("text"),
                "relation_type": relation,
                "language_bcp47": form.get("language_bcp47"),
                "script_iso15924": form.get("script_iso15924"),
                "transliteration_system": form.get("transliteration_system"),
                "valid_from_year": form.get("valid_from_year"),
                "valid_to_year": form.get("valid_to_year"),
                "temporal_status": _temporal_status(form.get("valid_from_year"), form.get("valid_to_year"), year),
                "source_reference": form.get("source_reference"),
            })
        names.sort(key=lambda x: (
            x["valid_from_year"] is None,
            x["valid_from_year"] if x["valid_from_year"] is not None else 999999,
            x["valid_to_year"] is None,
            x["valid_to_year"] if x["valid_to_year"] is not None else 999999,
            str(x["text"] or "").casefold(),
        ))
        entities.append({
            "entity_id": entity.get("entity_id"),
            "entity_type": entity.get("entity_type"),
            "canonical_name": entity.get("canonical_name"),
            "country_code": entity.get("country_code"),
            "latitude": entity.get("latitude"),
            "longitude": entity.get("longitude"),
            "name_forms": names,
        })
    basis = {"authority": package.get("authority_fingerprint_sha256"), "entity_id": entity_id_filter, "year": year, "entities": entities}
    return {
        "schema": TIMELINE_CONTRACT,
        "timeline_id": "toponym-timeline:" + _fp(basis)[:32],
        "timeline_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "query_year": year,
        "entity_count": len(entities),
        "entities": entities,
        "continuity_inferred": False,
        "missing_dates_inferred": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }

def candidate_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    case = resolution_preview(payload)["resolution_case"]
    rows = []
    for candidate in case.get("candidates") or []:
        rows.append({
            "rank": candidate.get("rank"),
            "candidate_id": candidate.get("candidate_id"),
            "entity_id": candidate.get("entity_id"),
            "entity_type": candidate.get("entity_type"),
            "canonical_name": candidate.get("canonical_name"),
            "matched_form": candidate.get("matched_form"),
            "relation_type": candidate.get("relation_type"),
            "language_bcp47": candidate.get("language_bcp47"),
            "script_iso15924": candidate.get("script_iso15924"),
            "valid_from_year": candidate.get("valid_from_year"),
            "valid_to_year": candidate.get("valid_to_year"),
            "temporal_status": candidate.get("temporal_status"),
            "score": candidate.get("score"),
            "signals": list(candidate.get("signals") or []),
            "score_is_probability": False,
            "candidate_is_resolved_identity": False,
        })
    basis = {"query": case.get("query"), "authority": case.get("authority_fingerprint_sha256"), "rows": rows}
    return {
        "schema": MATRIX_CONTRACT,
        "matrix_id": "entity-resolution-matrix:" + _fp(basis)[:32],
        "matrix_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "query": case.get("query"),
        "candidate_count": len(rows),
        "rows": rows,
        "ambiguity": case.get("ambiguity"),
        "winner_selected": False,
        "automatic_resolution": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }

def decision_preview(payload: dict[str, Any]) -> dict[str, Any]:
    case = resolution_preview(payload)["resolution_case"]
    decision = payload.get("decision") if isinstance(payload.get("decision"), dict) else {}
    validation = validate_decision_payload(case, decision)
    normalized = validation.get("normalized") if validation.get("valid") else None
    basis = {"case_id": case.get("case_id"), "decision": normalized or decision}
    return {
        "schema": DECISION_PREVIEW_CONTRACT,
        "preview_id": "resolution-decision-preview:" + _fp(basis)[:32],
        "preview_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "case_id": case.get("case_id"),
        "valid": bool(validation.get("valid")),
        "errors": list(validation.get("errors") or []),
        "decision": normalized,
        "persisted": False,
        "automatic_decision": False,
        "guardrails": {**guardrails(), **_dict(validation.get("guardrails"))},
    }

def persisted_case(case_id: str) -> dict[str, Any]:
    case_id = _clean(case_id, 1000)
    if not case_id:
        raise ValueError("case-id-required")
    case = get_resolution_case(case_id)
    return {
        "schema": "sc-library-entity-place-persisted-resolution-case/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "resolution_case": case,
        "guardrails": guardrails(),
    }

def persistence_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    handoff_type = _clean(payload.get("handoff_type"), 100) or "authority"
    if handoff_type not in {"authority","resolution-case","decision"}:
        raise ValueError("handoff-type-must-be-authority-resolution-case-or-decision")
    if handoff_type == "authority":
        authority = _authority_payload(payload)
        preview = build_authority_package(authority)
        endpoint = "/api/library/v1/admin/language/authorities"
        handoff_payload = authority
        expected_object_id = preview.get("registry_id")
    elif handoff_type == "resolution-case":
        query = _query(payload)
        endpoint = "/api/library/v1/admin/language/entity-resolution/cases"
        handoff_payload = {"query": query, "limit": max(1, min(MAX_CANDIDATES, int(payload.get("limit") or 25)))}
        expected_object_id = None
    else:
        case_id = _clean(payload.get("case_id"), 1000)
        if not case_id:
            raise ValueError("case-id-required-for-decision-handoff")
        decision = payload.get("decision") if isinstance(payload.get("decision"), dict) else {}
        endpoint = f"/api/library/v1/admin/language/entity-resolution/{case_id}/decisions"
        handoff_payload = decision
        expected_object_id = case_id
    basis = {"handoff_type": handoff_type, "endpoint": endpoint, "payload": handoff_payload}
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "entity-place-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff_type": handoff_type,
        "preview_only": True,
        "automatic_persistence": False,
        "signed_request_required": True,
        "existing_authority": "v5.48.0-python-cross-language-resolution-postgresql",
        "endpoint": endpoint,
        "payload": handoff_payload,
        "expected_object_id": expected_object_id,
        "guardrails": guardrails(),
    }

def export_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    authority = authority_preview(payload)
    timeline = historical_toponym_timeline(payload)
    resolution = None
    matrix = None
    decision = None
    try:
        _query(payload)
        has_query = True
    except ValueError:
        has_query = False
    if has_query:
        resolution = resolution_preview(payload)
        matrix = candidate_matrix(payload)
        if isinstance(payload.get("decision"), dict):
            decision = decision_preview(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "authority": authority["authority"],
        "toponym_timeline": timeline,
        "resolution": resolution,
        "candidate_matrix": matrix,
        "decision_preview": decision,
        "workspace_persisted": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }
    basis = {k: v for k, v in body.items() if k not in {"schema","library_version","backend_version","web_version","sdk_version"}}
    body["package_id"] = "entity-place-research:" + _fp(basis)[:32]
    body["package_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-entity-place-historical-toponym-research.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
