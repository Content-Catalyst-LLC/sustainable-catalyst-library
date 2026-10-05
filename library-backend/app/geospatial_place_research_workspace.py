from __future__ import annotations

from hashlib import sha256
import json
import math
from typing import Any

LIBRARY_VERSION = "6.24.0"
BACKEND_VERSION = "3.24.0"
WEB_VERSION = "2.24.0"
SDK_VERSION = "1.24.0"

CONTRACT = "sc-library-geospatial-place-research-workspace/1.0"
READINESS_CONTRACT = "sc-library-geospatial-place-research-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-geospatial-place-research-bootstrap/1.0"
INVENTORY_CONTRACT = "sc-library-geospatial-place-inventory/1.0"
RELATION_CONTRACT = "sc-library-geospatial-relation-preview/1.0"
COVERAGE_CONTRACT = "sc-library-geospatial-coverage-audit/1.0"
TEMPORAL_CONTRACT = "sc-library-place-temporal-validity-analysis/1.0"
EXPORT_CONTRACT = "sc-library-geospatial-place-export/1.0"

GEOMETRY_TYPES = {"point","line-string","polygon","multi-polygon","bbox","raster","place-record","unknown"}
LAYER_KINDS = {"administrative","environmental","infrastructure","demographic","economic","hazard","land-use","remote-sensing","transport","hydrology","custom","other"}
PROVENANCE_STATES = {"complete","partial","missing","unknown"}
TEMPORAL_STATES = {"current","historical","future-scenario","unknown"}
RELATION_TYPES = {"near","overlaps","contains","within","intersects","disjoint","same-place-assertion","unknown"}

MAX_PLACES = 5000
MAX_LAYERS = 5000
MAX_FEATURES = 20000
MAX_RELATIONS = 20000


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 12000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _num(value: Any) -> float | None:
    if value is None or value == "":
        return None
    try:
        n = float(value)
        return n if math.isfinite(n) else None
    except (TypeError, ValueError):
        return None


def guardrails() -> dict[str, bool]:
    return {
        "workspace_is_analysis_composition_layer": True,
        "workspace_is_new_geospatial_persistence_authority": False,
        "automatic_external_geocoding": False,
        "automatic_external_tile_fetch": False,
        "missing_crs_is_silently_assumed": False,
        "coordinate_precision_implies_accuracy": False,
        "map_visualization_implies_evidence": False,
        "map_overlap_implies_relationship": False,
        "spatial_overlap_implies_causation": False,
        "spatial_proximity_implies_causation": False,
        "spatial_cluster_implies_mechanism": False,
        "administrative_boundary_implies_natural_boundary": False,
        "administrative_boundary_implies_identity": False,
        "same_name_implies_same_place": False,
        "same_coordinates_imply_same_place": False,
        "containment_implies_jurisdiction": False,
        "raster_resolution_implies_accuracy": False,
        "vector_geometry_implies_precision": False,
        "latest_observation_implies_current_truth": False,
        "spatial_interpolation_implies_observation": False,
        "coordinate_transformation_erases_lineage": False,
        "automatic_claim_support_inference": False,
        "automatic_causal_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_workspace_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "place-inventory","spatial-feature-inventory","layer-inventory","crs-and-coordinate-lineage",
        "temporal-validity","bounding-box-and-point-relations","proximity-preview",
        "containment-and-overlap-preview","spatial-coverage-audit","place-linked-evidence-handoff",
        "research-investigation-handoff","reproducible-geospatial-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "geospatial-place:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/geospatial",
        "api_base": "/api/library/v1/geospatial-research",
        "resources": resources,
        "limits": {
            "places": MAX_PLACES,
            "layers": MAX_LAYERS,
            "features": MAX_FEATURES,
            "relations": MAX_RELATIONS,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "ready": True,
        "blocking": [],
        "degraded": [],
        "authority": "python-backend-composition",
        "composes_existing_authorities": [
            "entity-place-historical-toponym-v6.19",
            "dataset-statistical-evidence-v6.23",
            "structured-evidence",
            "global-knowledge-federation-ii",
            "research-investigation-v6.21",
            "evidence-matrix-v6.22",
        ],
        "automatic_external_geocoding": False,
        "server_side_workspace_persistence": False,
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
        "sdk_version": SDK_VERSION,
        "route": "/research/geospatial",
        "readiness": readiness(),
        "geometry_types": sorted(GEOMETRY_TYPES),
        "layer_kinds": sorted(LAYER_KINDS),
        "provenance_states": sorted(PROVENANCE_STATES),
        "temporal_states": sorted(TEMPORAL_STATES),
        "relation_types": sorted(RELATION_TYPES),
        "operations": [
            "inventory","relation-preview","coverage-audit","temporal-validity",
            "evidence-handoff-preview","investigation-handoff-preview","export",
        ],
        "browser_storage_key": "sc-library-geospatial-research-v1",
        "guardrails": guardrails(),
    }


def _crs(raw: Any) -> dict[str, Any]:
    r = _dict(raw)
    authority = _clean(r.get("authority"), 200) or None
    code = _clean(r.get("code"), 200) or None
    name = _clean(r.get("name"), 1000) or None
    explicit = bool(authority or code or name)
    return {
        "authority": authority,
        "code": code,
        "name": name,
        "explicit": explicit,
        "assumed": False,
    }


def _bbox(raw: Any) -> list[float] | None:
    vals = _list(raw)
    if len(vals) != 4:
        return None
    nums = [_num(x) for x in vals]
    if any(x is None for x in nums):
        return None
    minx, miny, maxx, maxy = [float(x) for x in nums]
    if minx > maxx or miny > maxy:
        return None
    return [minx, miny, maxx, maxy]


def _point(raw: Any) -> list[float] | None:
    vals = _list(raw)
    if len(vals) < 2:
        return None
    x, y = _num(vals[0]), _num(vals[1])
    if x is None or y is None:
        return None
    return [float(x), float(y)]


def _place(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    pid = _clean(r.get("place_id") or r.get("id"), 1000) or f"place:{index}"
    name = _clean(r.get("name") or r.get("label"), 4000)
    if not name:
        raise ValueError(f"place-{index}-name-required")
    temporal_state = _clean(r.get("temporal_state"), 100).lower() or "unknown"
    if temporal_state not in TEMPORAL_STATES:
        temporal_state = "unknown"
    ps = _clean(r.get("provenance_state"), 100).lower() or "unknown"
    if ps not in PROVENANCE_STATES:
        ps = "unknown"
    return {
        "place_id": pid,
        "name": name,
        "place_type": _clean(r.get("place_type"), 1000) or None,
        "alternate_names": [_clean(x, 3000) for x in _list(r.get("alternate_names")) if _clean(x, 3000)],
        "entity_resolution_case_id": _clean(r.get("entity_resolution_case_id"), 1000) or None,
        "parent_place_id": _clean(r.get("parent_place_id"), 1000) or None,
        "point": _point(r.get("point")),
        "bbox": _bbox(r.get("bbox")),
        "crs": _crs(r.get("crs")),
        "valid_from": _clean(r.get("valid_from"), 200) or None,
        "valid_to": _clean(r.get("valid_to"), 200) or None,
        "temporal_state": temporal_state,
        "provenance_state": ps,
        "provenance": _dict(r.get("provenance")),
        "notes": _clean(r.get("notes"), 12000) or None,
    }


def _feature(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    fid = _clean(r.get("feature_id") or r.get("id"), 1000) or f"feature:{index}"
    gtype = _clean(r.get("geometry_type"), 100).lower() or "unknown"
    if gtype not in GEOMETRY_TYPES:
        gtype = "unknown"
    ps = _clean(r.get("provenance_state"), 100).lower() or "unknown"
    if ps not in PROVENANCE_STATES:
        ps = "unknown"
    return {
        "feature_id": fid,
        "label": _clean(r.get("label") or r.get("name"), 4000) or fid,
        "geometry_type": gtype,
        "point": _point(r.get("point")),
        "bbox": _bbox(r.get("bbox")),
        "crs": _crs(r.get("crs")),
        "place_id": _clean(r.get("place_id"), 1000) or None,
        "dataset_id": _clean(r.get("dataset_id"), 1000) or None,
        "layer_id": _clean(r.get("layer_id"), 1000) or None,
        "valid_from": _clean(r.get("valid_from"), 200) or None,
        "valid_to": _clean(r.get("valid_to"), 200) or None,
        "spatial_resolution": _clean(r.get("spatial_resolution"), 1000) or None,
        "positional_accuracy": _clean(r.get("positional_accuracy"), 1000) or None,
        "provenance_state": ps,
        "provenance": _dict(r.get("provenance")),
        "properties": _dict(r.get("properties")),
    }


def _layer(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    lid = _clean(r.get("layer_id") or r.get("id"), 1000) or f"layer:{index}"
    kind = _clean(r.get("kind"), 100).lower() or "other"
    if kind not in LAYER_KINDS:
        kind = "other"
    ps = _clean(r.get("provenance_state"), 100).lower() or "unknown"
    if ps not in PROVENANCE_STATES:
        ps = "unknown"
    return {
        "layer_id": lid,
        "title": _clean(r.get("title") or r.get("name"), 4000) or lid,
        "kind": kind,
        "dataset_id": _clean(r.get("dataset_id"), 1000) or None,
        "source_id": _clean(r.get("source_id"), 1000) or None,
        "crs": _crs(r.get("crs")),
        "bbox": _bbox(r.get("bbox")),
        "temporal_coverage": _clean(r.get("temporal_coverage"), 3000) or None,
        "spatial_resolution": _clean(r.get("spatial_resolution"), 1000) or None,
        "provenance_state": ps,
        "provenance": _dict(r.get("provenance")),
        "style": _dict(r.get("style")),
    }


def normalize_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    places_raw = _list(payload.get("places"))
    layers_raw = _list(payload.get("layers"))
    features_raw = _list(payload.get("features"))
    relations_raw = _list(payload.get("relations"))
    if len(places_raw) > MAX_PLACES:
        raise ValueError(f"place-limit-exceeded:{MAX_PLACES}")
    if len(layers_raw) > MAX_LAYERS:
        raise ValueError(f"layer-limit-exceeded:{MAX_LAYERS}")
    if len(features_raw) > MAX_FEATURES:
        raise ValueError(f"feature-limit-exceeded:{MAX_FEATURES}")
    if len(relations_raw) > MAX_RELATIONS:
        raise ValueError(f"relation-limit-exceeded:{MAX_RELATIONS}")
    places = [_place(x, i) for i, x in enumerate(places_raw, 1)]
    layers = [_layer(x, i) for i, x in enumerate(layers_raw, 1)]
    features = [_feature(x, i) for i, x in enumerate(features_raw, 1)]
    relations = [_dict(x) for x in relations_raw]
    basis = {
        "title": _clean(payload.get("title"), 2000),
        "research_question": _clean(payload.get("research_question"), 16000),
        "places": places, "layers": layers, "features": features, "relations": relations,
    }
    return {
        "schema": "sc-library-geospatial-place-input/1.0",
        "workspace_input_id": "geospatial-input:" + _fp(basis)[:32],
        "workspace_input_fingerprint_sha256": _fp(basis),
        "title": basis["title"] or "Geospatial research",
        "research_question": basis["research_question"] or None,
        "scope": _clean(payload.get("scope"), 16000) or None,
        "places": places,
        "layers": layers,
        "features": features,
        "relations": relations,
        "metadata": _dict(payload.get("metadata")),
    }


def inventory(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    missing_crs = []
    for p in w["places"]:
        if (p["point"] or p["bbox"]) and not p["crs"]["explicit"]:
            missing_crs.append({"kind": "place", "id": p["place_id"]})
    for f in w["features"]:
        if (f["point"] or f["bbox"]) and not f["crs"]["explicit"]:
            missing_crs.append({"kind": "feature", "id": f["feature_id"]})
    for l in w["layers"]:
        if l["bbox"] and not l["crs"]["explicit"]:
            missing_crs.append({"kind": "layer", "id": l["layer_id"]})
    basis = {"input": w["workspace_input_fingerprint_sha256"], "missing_crs": missing_crs}
    return {
        "schema": INVENTORY_CONTRACT,
        "inventory_id": "geospatial-inventory:" + _fp(basis)[:32],
        "inventory_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "places": w["places"],
        "layers": w["layers"],
        "features": w["features"],
        "place_count": len(w["places"]),
        "layer_count": len(w["layers"]),
        "feature_count": len(w["features"]),
        "missing_crs": missing_crs,
        "guardrails": guardrails(),
    }


def _same_crs(a: dict[str, Any], b: dict[str, Any]) -> bool:
    if not a.get("explicit") or not b.get("explicit"):
        return False
    return (
        (a.get("authority") or "").casefold(),
        (a.get("code") or "").casefold(),
        (a.get("name") or "").casefold(),
    ) == (
        (b.get("authority") or "").casefold(),
        (b.get("code") or "").casefold(),
        (b.get("name") or "").casefold(),
    )


def _is_wgs84(crs: dict[str, Any]) -> bool:
    text = " ".join(str(crs.get(k) or "") for k in ("authority","code","name")).casefold()
    return "4326" in text or "wgs 84" in text or "wgs84" in text


def _haversine_km(a: list[float], b: list[float]) -> float:
    lon1, lat1 = map(math.radians, a[:2])
    lon2, lat2 = map(math.radians, b[:2])
    dlon, dlat = lon2-lon1, lat2-lat1
    h = math.sin(dlat/2)**2 + math.cos(lat1)*math.cos(lat2)*math.sin(dlon/2)**2
    return 6371.0088 * 2 * math.asin(min(1.0, math.sqrt(h)))


def _bbox_relation(a: list[float], b: list[float]) -> str:
    aminx, aminy, amaxx, amaxy = a
    bminx, bminy, bmaxx, bmaxy = b
    if amaxx < bminx or bmaxx < aminx or amaxy < bminy or bmaxy < aminy:
        return "disjoint"
    if aminx <= bminx and aminy <= bminy and amaxx >= bmaxx and amaxy >= bmaxy:
        return "contains"
    if bminx <= aminx and bminy <= aminy and bmaxx >= amaxx and bmaxy >= amaxy:
        return "within"
    return "overlaps"


def relation_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    objects: dict[str, dict[str, Any]] = {}
    for p in w["places"]:
        objects[p["place_id"]] = {"kind": "place", **p}
    for f in w["features"]:
        objects[f["feature_id"]] = {"kind": "feature", **f}
    for l in w["layers"]:
        objects[l["layer_id"]] = {"kind": "layer", **l}

    req = _list(payload.get("relations"))
    rows = []
    for i, raw in enumerate(req, 1):
        r = _dict(raw)
        left_id = _clean(r.get("left_id"), 1000)
        right_id = _clean(r.get("right_id"), 1000)
        left, right = objects.get(left_id), objects.get(right_id)
        row = {
            "relation_id": _clean(r.get("relation_id") or r.get("id"), 1000) or f"relation:{i}",
            "left_id": left_id,
            "right_id": right_id,
            "computable": False,
            "relation": "unknown",
            "distance_km": None,
            "reason": None,
            "causality_inferred": False,
            "identity_inferred": False,
            "jurisdiction_inferred": False,
        }
        if not left or not right:
            row["reason"] = "object-not-found"
        elif not _same_crs(left["crs"], right["crs"]):
            row["reason"] = "crs-mismatch-or-missing"
        elif left.get("bbox") and right.get("bbox"):
            row["computable"] = True
            row["relation"] = _bbox_relation(left["bbox"], right["bbox"])
        elif left.get("point") and right.get("point") and _is_wgs84(left["crs"]):
            row["computable"] = True
            row["relation"] = "near"
            row["distance_km"] = round(_haversine_km(left["point"], right["point"]), 6)
        else:
            row["reason"] = "unsupported-geometry-combination"
        rows.append(row)
    basis = {"input": w["workspace_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": RELATION_CONTRACT,
        "relation_preview_id": "geospatial-relations:" + _fp(basis)[:32],
        "relation_preview_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "automatic_coordinate_transformation": False,
        "guardrails": guardrails(),
    }


def coverage_audit(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    findings = []
    for p in w["places"]:
        if not p["point"] and not p["bbox"]:
            findings.append({"kind": "place-without-spatial-geometry", "id": p["place_id"]})
        if (p["point"] or p["bbox"]) and not p["crs"]["explicit"]:
            findings.append({"kind": "place-without-explicit-crs", "id": p["place_id"]})
        if p["provenance_state"] in {"missing","unknown"}:
            findings.append({"kind": "place-without-complete-provenance", "id": p["place_id"]})
    for l in w["layers"]:
        if not l["bbox"]:
            findings.append({"kind": "layer-without-bbox", "id": l["layer_id"]})
        if l["bbox"] and not l["crs"]["explicit"]:
            findings.append({"kind": "layer-without-explicit-crs", "id": l["layer_id"]})
        if not l["temporal_coverage"]:
            findings.append({"kind": "layer-without-temporal-coverage", "id": l["layer_id"]})
    for f in w["features"]:
        if not f["point"] and not f["bbox"]:
            findings.append({"kind": "feature-without-supported-preview-geometry", "id": f["feature_id"]})
        if (f["point"] or f["bbox"]) and not f["crs"]["explicit"]:
            findings.append({"kind": "feature-without-explicit-crs", "id": f["feature_id"]})
    basis = {"input": w["workspace_input_fingerprint_sha256"], "findings": findings}
    return {
        "schema": COVERAGE_CONTRACT,
        "coverage_audit_id": "geospatial-coverage:" + _fp(basis)[:32],
        "coverage_audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "findings": findings,
        "finding_count": len(findings),
        "complete": len(findings) == 0,
        "complete_implies_truth": False,
        "guardrails": guardrails(),
    }


def temporal_validity(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    rows = []
    for p in w["places"]:
        rows.append({
            "object_kind": "place",
            "object_id": p["place_id"],
            "valid_from": p["valid_from"],
            "valid_to": p["valid_to"],
            "temporal_state": p["temporal_state"],
            "latest_observation_implies_current_truth": False,
        })
    for f in w["features"]:
        rows.append({
            "object_kind": "feature",
            "object_id": f["feature_id"],
            "valid_from": f["valid_from"],
            "valid_to": f["valid_to"],
            "temporal_state": "unknown",
            "latest_observation_implies_current_truth": False,
        })
    basis = {"input": w["workspace_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": TEMPORAL_CONTRACT,
        "temporal_analysis_id": "place-temporal-validity:" + _fp(basis)[:32],
        "temporal_analysis_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "guardrails": guardrails(),
    }


def evidence_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    inv = inventory(payload)
    evidence = []
    links = []
    claim_id = _clean(payload.get("claim_id"), 1000) or None
    for p in w["places"]:
        eid = f"place-evidence:{p['place_id']}"
        evidence.append({
            "evidence_id": eid,
            "title": p["name"],
            "kind": "other",
            "source_id": p.get("place_id"),
            "independence_group": p.get("place_id"),
            "provenance_state": p["provenance_state"],
            "provenance": p["provenance"],
            "notes": "Geospatial place context; no claim relationship inferred automatically.",
        })
        if claim_id:
            links.append({
                "claim_id": claim_id,
                "evidence_id": eid,
                "relationship": "unknown",
                "directness": "contextual",
                "rationale": "Spatial context only; user review required.",
            })
    handoff_payload = {
        "title": w["title"] + " — geospatial evidence",
        "research_question": w["research_question"],
        "scope": w["scope"],
        "claims": _list(payload.get("claims")),
        "evidence": evidence,
        "links": links,
        "metadata": {"geospatial_inventory_id": inv["inventory_id"]},
    }
    return {
        "schema": "sc-library-geospatial-evidence-handoff-preview/1.0",
        "handoff_id": "geospatial-to-evidence:" + _fp(handoff_payload)[:32],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/evidence-matrix/matrix",
        "payload": handoff_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_claim_support_inference": False,
        "automatic_persistence": False,
        "guardrails": guardrails(),
    }


def investigation_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    audit = coverage_audit(payload)
    needs = []
    tasks = []
    for i, finding in enumerate(audit["findings"], 1):
        gid = f"geo-gap:{i}"
        desc = f"{finding['kind']}: {finding['id']}"
        needs.append({
            "evidence_need_id": gid,
            "description": desc,
            "kind": "other",
            "priority": "unspecified",
        })
        tasks.append({
            "task_id": f"geo-task:{i}",
            "description": "Investigate geospatial gap: " + desc,
            "task_type": "review",
            "evidence_need_ids": [gid],
            "priority": "unspecified",
        })
    handoff_payload = {
        "title": w["title"] + " — geospatial gaps",
        "research_question": w["research_question"] or "What geospatial coverage gaps remain unresolved?",
        "scope": w["scope"],
        "subquestions": [],
        "hypotheses": [],
        "evidence_needs": needs,
        "tasks": tasks,
        "decision_points": [],
        "stop_conditions": [],
        "risks": [],
        "source_strategy": {},
    }
    return {
        "schema": "sc-library-geospatial-investigation-handoff-preview/1.0",
        "handoff_id": "geospatial-to-investigation:" + _fp(handoff_payload)[:32],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/research-investigation/build",
        "payload": handoff_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_task_execution": False,
        "automatic_persistence": False,
        "guardrails": guardrails(),
    }


def export_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "workspace": w,
        "inventory": inventory(payload),
        "relation_preview": relation_preview(payload),
        "coverage_audit": coverage_audit(payload),
        "temporal_validity": temporal_validity(payload),
        "automatic_import": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {
        "input": w["workspace_input_fingerprint_sha256"],
        "inventory": body["inventory"]["inventory_fingerprint_sha256"],
    }
    body["export_id"] = "geospatial-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-geospatial-place-research.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
