from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.34.0"
BACKEND_VERSION = "3.34.0"
WEB_VERSION = "2.34.0"
SDK_VERSION = "1.34.0"

CONTRACT = "sc-library-portable-research-object-exchange/1.0"
READINESS_CONTRACT = "sc-library-portable-research-object-exchange-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-portable-research-object-exchange-bootstrap/1.0"
OBJECT_CONTRACT = "sc-library-portable-research-object/1.0"
EXCHANGE_CONTRACT = "sc-library-research-object-exchange/1.0"
VALIDATION_CONTRACT = "sc-library-research-object-exchange-validation/1.0"
INTEGRITY_CONTRACT = "sc-library-research-object-exchange-integrity/1.0"
COMPATIBILITY_CONTRACT = "sc-library-research-object-exchange-compatibility/1.0"
IMPORT_PREVIEW_CONTRACT = "sc-library-research-object-import-preview/1.0"
EXPORT_CONTRACT = "sc-library-research-object-exchange-export/1.0"

EXCHANGE_FORMAT_VERSION = "1.0"
MAX_OBJECTS = 5000
MAX_RELATIONSHIPS = 20000
MAX_PROVENANCE_ITEMS = 5000
MAX_LINEAGE_ITEMS = 5000
MAX_CITATION_ITEMS = 5000

KNOWN_SCHEMAS = {
    "sc-library-unified-research-project/1.0",
    "sc-library-research-package-composition/1.0",
    "sc-library-research-publication-draft/1.0",
    "sc-library-research-dependency-lineage-graph/1.0",
    "sc-library-research-version-snapshot/1.0",
    "sc-library-research-review-packet/1.0",
    "sc-library-research-revision-proposal/1.0",
    "sc-library-research-review-decision/1.0",
    "sc-library-research-package-validation-readiness-export/1.0",
    "sc-library-research-synthesis/1.0",
    "sc-library-research-investigation/1.0",
    "sc-library-evidence-matrix/1.0",
    "sc-library-statistical-evidence/1.0",
    "sc-library-geospatial-research/1.0",
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return hashlib.sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return list(value)
    if value is None:
        return []
    return [value]


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def _bounded_dicts(value: Any, limit: int) -> list[dict[str, Any]]:
    return [dict(x) for x in _list(value) if isinstance(x, dict)][:limit]


def guardrails() -> dict[str, Any]:
    return {
        "exchange_layer_is_new_domain_object_authority": False,
        "exchange_layer_is_project_persistence_authority": False,
        "exchange_layer_is_package_authority": False,
        "exchange_layer_is_publication_authority": False,
        "exchange_wrapper_changes_source_authority": False,
        "exchange_wrapper_changes_source_schema": False,
        "exchange_wrapper_rewrites_payload": False,
        "exchange_wrapper_normalizes_semantics": False,
        "exchange_relationships_are_explicit_only": True,
        "automatic_relationship_inference": False,
        "automatic_object_merge": False,
        "automatic_deduplication": False,
        "automatic_schema_migration": False,
        "automatic_import": False,
        "automatic_persistence": False,
        "automatic_external_submission": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "fingerprint_match_implies_truth": False,
        "integrity_pass_implies_source_validity": False,
        "compatibility_implies_semantic_equivalence": False,
        "originating_authority_is_preserved": True,
        "exact_payload_is_preserved": True,
        "unknown_schema_is_preserved_for_review": True,
        "server_side_exchange_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "portable-research-object-envelope",
        "originating-schema-and-authority-preservation",
        "exact-payload-content-fingerprints",
        "provenance-lineage-citation-reference-carry-forward",
        "deterministic-multi-object-exchange-manifest",
        "explicit-only-exchange-relationships",
        "object-and-manifest-integrity-verification",
        "schema-compatibility-observation",
        "non-mutating-import-preview",
        "deterministic-portable-exchange-export",
    ]
    basis = {
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "system_id": "portable-research-object-exchange:" + _fp(basis)[:32],
        "system_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/exchange",
        "api_base": "/api/library/v1/research-object-exchange",
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "portable_object_schema": OBJECT_CONTRACT,
        "exchange_schema": EXCHANGE_CONTRACT,
        "resources": resources,
        "known_source_schemas": sorted(KNOWN_SCHEMAS),
        "limits": {
            "objects": MAX_OBJECTS,
            "relationships": MAX_RELATIONSHIPS,
            "provenance_items_per_object": MAX_PROVENANCE_ITEMS,
            "lineage_items_per_object": MAX_LINEAGE_ITEMS,
            "citation_items_per_object": MAX_CITATION_ITEMS,
        },
        "next_release": "6.35.0",
        "next_release_name": "Collaborative Research Rooms II",
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
        "portable_object_envelope_ready": True,
        "exchange_manifest_ready": True,
        "integrity_verification_ready": True,
        "compatibility_observation_ready": True,
        "import_preview_ready": True,
        "deterministic_export_ready": True,
        "automatic_import": False,
        "server_side_exchange_persistence": False,
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
        "route": "/research/exchange",
        "readiness": readiness(),
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "operations": [
            "wrap-object",
            "build-exchange",
            "validate",
            "verify-integrity",
            "compatibility",
            "import-preview",
            "export",
        ],
        "browser_storage_key": "sc-library-portable-research-object-exchange-v1",
        "guardrails": guardrails(),
    }


def wrap_object(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    source = _dict(payload.get("object") or payload.get("payload") or payload.get("source_object"))
    if not source:
        raise ValueError("object/payload/source_object is required")

    source_object_id = _clean(
        payload.get("source_object_id")
        or source.get("object_id")
        or source.get("project_manifest_id")
        or source.get("composition_id")
        or source.get("publication_draft_id")
        or source.get("snapshot_id")
        or source.get("id")
    )
    if not source_object_id:
        raise ValueError("source object identifier is required")

    source_schema = _clean(payload.get("source_schema") or source.get("schema"))
    if not source_schema:
        raise ValueError("source schema is required")

    source_authority = _clean(
        payload.get("source_authority")
        or source.get("authority")
        or source.get("persistence_authority")
        or source.get("source_authority")
    ) or "originating-authority-unspecified"

    provenance = _bounded_dicts(payload.get("provenance") or source.get("provenance"), MAX_PROVENANCE_ITEMS)
    lineage = _bounded_dicts(payload.get("lineage") or source.get("lineage_relations"), MAX_LINEAGE_ITEMS)
    citations = _bounded_dicts(payload.get("citations") or source.get("citations"), MAX_CITATION_ITEMS)
    content_fingerprint = _fp(source)

    identity_basis = {
        "source_object_id": source_object_id,
        "source_schema": source_schema,
        "source_authority": source_authority,
        "content_fingerprint_sha256": content_fingerprint,
    }
    portable_object_id = "portable-research-object:" + _fp(identity_basis)[:32]
    return {
        "schema": OBJECT_CONTRACT,
        "portable_object_id": portable_object_id,
        "portable_object_fingerprint_sha256": _fp(identity_basis),
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_object_id": source_object_id,
        "source_schema": source_schema,
        "source_authority": source_authority,
        "source_version": _clean(payload.get("source_version") or source.get("version") or source.get("library_version")),
        "content_fingerprint_sha256": content_fingerprint,
        "payload": source,
        "provenance": provenance,
        "lineage": lineage,
        "citations": citations,
        "metadata": _dict(payload.get("metadata")),
        "known_source_schema": source_schema in KNOWN_SCHEMAS,
        "authority_changed": False,
        "payload_rewritten": False,
        "semantic_normalization_performed": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def _normalize_envelope(raw: Any) -> dict[str, Any]:
    item = _dict(raw)
    if item.get("schema") == OBJECT_CONTRACT:
        return item
    if "object" in item or "payload" in item or "source_object" in item:
        return wrap_object(item)
    return wrap_object({"object": item})


def build_exchange(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    raw_objects = _list(payload.get("objects"))
    if not raw_objects:
        raise ValueError("objects is required")
    if len(raw_objects) > MAX_OBJECTS:
        raise ValueError(f"object limit exceeded: {MAX_OBJECTS}")

    objects = [_normalize_envelope(x) for x in raw_objects]
    portable_ids = [x.get("portable_object_id") for x in objects]
    if len(portable_ids) != len(set(portable_ids)):
        raise ValueError("duplicate portable_object_id")

    relationships = _bounded_dicts(payload.get("relationships"), MAX_RELATIONSHIPS)
    if len(_list(payload.get("relationships"))) > MAX_RELATIONSHIPS:
        raise ValueError(f"relationship limit exceeded: {MAX_RELATIONSHIPS}")

    explicit_relationships = []
    for index, raw in enumerate(relationships, 1):
        source = _clean(raw.get("source") or raw.get("from"))
        target = _clean(raw.get("target") or raw.get("to"))
        relation = _clean(raw.get("relation") or raw.get("type"))
        if not source or not target or not relation:
            raise ValueError(f"relationship {index} requires source, target, and relation")
        explicit_relationships.append({
            "source": source,
            "target": target,
            "relation": relation,
            "authority": _clean(raw.get("authority")) or "exchange-declared",
            "explicit": True,
            "inferred": False,
            "metadata": _dict(raw.get("metadata")),
        })

    object_index = [
        {
            "portable_object_id": x.get("portable_object_id"),
            "source_object_id": x.get("source_object_id"),
            "source_schema": x.get("source_schema"),
            "source_authority": x.get("source_authority"),
            "content_fingerprint_sha256": x.get("content_fingerprint_sha256"),
        }
        for x in objects
    ]
    basis = {
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "object_index": object_index,
        "relationships": explicit_relationships,
        "metadata": _dict(payload.get("metadata")),
    }
    exchange_fingerprint = _fp(basis)
    return {
        "schema": EXCHANGE_CONTRACT,
        "exchange_id": "research-object-exchange:" + exchange_fingerprint[:32],
        "exchange_fingerprint_sha256": exchange_fingerprint,
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": _clean(payload.get("title")) or "Portable research object exchange",
        "description": _clean(payload.get("description")),
        "origin": _dict(payload.get("origin")),
        "objects": objects,
        "object_index": object_index,
        "relationships": explicit_relationships,
        "metadata": _dict(payload.get("metadata")),
        "object_count": len(objects),
        "relationship_count": len(explicit_relationships),
        "relationships_explicit_only": True,
        "persisted": False,
        "imported": False,
        "authority_changed": False,
        "guardrails": guardrails(),
    }


def _exchange(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("exchange"))
    if not raw and payload.get("schema") == EXCHANGE_CONTRACT:
        raw = dict(payload)
    if not raw and payload.get("objects"):
        raw = build_exchange(payload)
    return raw


def verify_integrity(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    exchange = _exchange(payload)
    findings: list[dict[str, Any]] = []
    if not exchange:
        findings.append({"kind": "exchange-required"})
        objects: list[dict[str, Any]] = []
    else:
        objects = [_dict(x) for x in _list(exchange.get("objects"))]

    object_results = []
    for obj in objects:
        pid = _clean(obj.get("portable_object_id"))
        actual_content = _fp(obj.get("payload"))
        expected_content = _clean(obj.get("content_fingerprint_sha256"))
        identity_basis = {
            "source_object_id": obj.get("source_object_id"),
            "source_schema": obj.get("source_schema"),
            "source_authority": obj.get("source_authority"),
            "content_fingerprint_sha256": expected_content,
        }
        actual_object_fp = _fp(identity_basis)
        expected_object_fp = _clean(obj.get("portable_object_fingerprint_sha256"))
        content_match = expected_content == actual_content
        object_match = expected_object_fp == actual_object_fp
        object_results.append({
            "portable_object_id": pid,
            "content_fingerprint_match": content_match,
            "object_fingerprint_match": object_match,
        })
        if not content_match:
            findings.append({"kind": "content-fingerprint-mismatch", "portable_object_id": pid})
        if not object_match:
            findings.append({"kind": "portable-object-fingerprint-mismatch", "portable_object_id": pid})

    if exchange:
        object_index = [
            {
                "portable_object_id": x.get("portable_object_id"),
                "source_object_id": x.get("source_object_id"),
                "source_schema": x.get("source_schema"),
                "source_authority": x.get("source_authority"),
                "content_fingerprint_sha256": x.get("content_fingerprint_sha256"),
            }
            for x in objects
        ]
        basis = {
            "exchange_format_version": exchange.get("exchange_format_version"),
            "object_index": object_index,
            "relationships": _list(exchange.get("relationships")),
            "metadata": _dict(exchange.get("metadata")),
        }
        actual_exchange_fp = _fp(basis)
        exchange_match = _clean(exchange.get("exchange_fingerprint_sha256")) == actual_exchange_fp
        if not exchange_match:
            findings.append({"kind": "exchange-fingerprint-mismatch"})
    else:
        actual_exchange_fp = None
        exchange_match = False

    result_basis = {"object_results": object_results, "exchange_match": exchange_match, "findings": findings}
    return {
        "schema": INTEGRITY_CONTRACT,
        "integrity_id": "research-object-exchange-integrity:" + _fp(result_basis)[:32],
        "integrity_fingerprint_sha256": _fp(result_basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not findings,
        "object_results": object_results,
        "exchange_fingerprint_match": exchange_match,
        "computed_exchange_fingerprint_sha256": actual_exchange_fp,
        "findings": findings,
        "finding_count": len(findings),
        "integrity_pass_implies_source_validity": False,
        "guardrails": guardrails(),
    }


def validate_exchange(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    exchange = _exchange(payload)
    blockers: list[dict[str, Any]] = []
    advisories: list[dict[str, Any]] = []
    if not exchange:
        blockers.append({"kind": "exchange-required"})
        objects: list[dict[str, Any]] = []
        relationships: list[dict[str, Any]] = []
    else:
        if exchange.get("schema") != EXCHANGE_CONTRACT:
            blockers.append({"kind": "unexpected-exchange-schema", "observed": exchange.get("schema"), "expected": EXCHANGE_CONTRACT})
        if str(exchange.get("exchange_format_version") or "") != EXCHANGE_FORMAT_VERSION:
            blockers.append({"kind": "unsupported-exchange-format-version", "observed": exchange.get("exchange_format_version"), "expected": EXCHANGE_FORMAT_VERSION})
        objects = [_dict(x) for x in _list(exchange.get("objects"))]
        relationships = [_dict(x) for x in _list(exchange.get("relationships"))]

    if not objects and exchange:
        blockers.append({"kind": "exchange-objects-empty"})
    if len(objects) > MAX_OBJECTS:
        blockers.append({"kind": "object-limit-exceeded", "limit": MAX_OBJECTS, "actual": len(objects)})
    if len(relationships) > MAX_RELATIONSHIPS:
        blockers.append({"kind": "relationship-limit-exceeded", "limit": MAX_RELATIONSHIPS, "actual": len(relationships)})

    portable_ids = []
    source_ids = []
    for index, obj in enumerate(objects):
        if obj.get("schema") != OBJECT_CONTRACT:
            blockers.append({"kind": "unexpected-object-envelope-schema", "index": index, "observed": obj.get("schema")})
        pid = _clean(obj.get("portable_object_id"))
        sid = _clean(obj.get("source_object_id"))
        if not pid:
            blockers.append({"kind": "portable-object-id-missing", "index": index})
        else:
            portable_ids.append(pid)
        if not sid:
            blockers.append({"kind": "source-object-id-missing", "index": index})
        else:
            source_ids.append(sid)
        if not _clean(obj.get("source_schema")):
            blockers.append({"kind": "source-schema-missing", "index": index})
        if not _clean(obj.get("source_authority")):
            blockers.append({"kind": "source-authority-missing", "index": index})
        if not bool(obj.get("known_source_schema", False)):
            advisories.append({"kind": "unknown-source-schema-preserved-for-review", "index": index, "source_schema": obj.get("source_schema")})

    if len(portable_ids) != len(set(portable_ids)):
        blockers.append({"kind": "duplicate-portable-object-id"})

    valid_endpoints = set(portable_ids) | set(source_ids)
    for index, rel in enumerate(relationships):
        source = _clean(rel.get("source"))
        target = _clean(rel.get("target"))
        relation = _clean(rel.get("relation"))
        if not source or not target or not relation:
            blockers.append({"kind": "relationship-fields-missing", "index": index})
            continue
        if source not in valid_endpoints:
            blockers.append({"kind": "relationship-source-not-in-exchange", "index": index, "source": source})
        if target not in valid_endpoints:
            blockers.append({"kind": "relationship-target-not-in-exchange", "index": index, "target": target})
        if rel.get("explicit") is not True or rel.get("inferred") is not False:
            blockers.append({"kind": "relationship-must-be-explicit-not-inferred", "index": index})

    integrity = verify_integrity({"exchange": exchange}) if exchange else verify_integrity({})
    if not integrity.get("valid"):
        blockers.extend(integrity.get("findings") or [])

    basis = {"blockers": blockers, "advisories": advisories, "integrity": integrity.get("integrity_fingerprint_sha256")}
    return {
        "schema": VALIDATION_CONTRACT,
        "validation_id": "research-object-exchange-validation:" + _fp(basis)[:32],
        "validation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not blockers,
        "blockers": blockers,
        "blocker_count": len(blockers),
        "advisories": advisories,
        "advisory_count": len(advisories),
        "integrity": integrity,
        "authority_changed": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }


def compatibility_report(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    exchange = _exchange(payload)
    if not exchange:
        raise ValueError("exchange is required")
    objects = [_dict(x) for x in _list(exchange.get("objects"))]
    rows = []
    review_required = []
    for obj in objects:
        schema = _clean(obj.get("source_schema"))
        known = schema in KNOWN_SCHEMAS
        row = {
            "portable_object_id": obj.get("portable_object_id"),
            "source_object_id": obj.get("source_object_id"),
            "source_schema": schema,
            "known_schema": known,
            "payload_preserved": True,
            "authority_preserved": True,
            "semantic_equivalence_claimed": False,
        }
        rows.append(row)
        if not known:
            review_required.append({
                "kind": "unknown-source-schema",
                "portable_object_id": obj.get("portable_object_id"),
                "source_schema": schema,
                "action": "preserve-and-review",
            })
    basis = {"rows": rows, "review_required": review_required}
    return {
        "schema": COMPATIBILITY_CONTRACT,
        "report_id": "research-object-exchange-compatibility:" + _fp(basis)[:32],
        "report_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "review_required": review_required,
        "review_required_count": len(review_required),
        "lossless_transport_ready": True,
        "semantic_equivalence_claimed": False,
        "automatic_schema_migration": False,
        "guardrails": guardrails(),
    }


def import_preview(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    exchange = _exchange(payload)
    if not exchange:
        raise ValueError("exchange is required")
    validation = validate_exchange({"exchange": exchange})
    compatibility = compatibility_report({"exchange": exchange})
    target = _dict(payload.get("target"))
    actions = []
    for obj in _list(exchange.get("objects")):
        o = _dict(obj)
        actions.append({
            "action": "review-for-explicit-import",
            "portable_object_id": o.get("portable_object_id"),
            "source_object_id": o.get("source_object_id"),
            "source_schema": o.get("source_schema"),
            "source_authority": o.get("source_authority"),
            "target_authority": _clean(target.get("authority")),
            "automatic_import": False,
            "automatic_persistence": False,
            "automatic_merge": False,
        })
    basis = {
        "exchange_id": exchange.get("exchange_id"),
        "target": target,
        "validation": validation.get("validation_fingerprint_sha256"),
        "compatibility": compatibility.get("report_fingerprint_sha256"),
        "actions": actions,
    }
    return {
        "schema": IMPORT_PREVIEW_CONTRACT,
        "preview_id": "research-object-import-preview:" + _fp(basis)[:32],
        "preview_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "exchange_id": exchange.get("exchange_id"),
        "target": target,
        "validation": validation,
        "compatibility": compatibility,
        "actions": actions,
        "ready_for_explicit_import_review": bool(validation.get("valid")),
        "imported": False,
        "persisted": False,
        "authority_changed": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }


def export_exchange(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    exchange = _exchange(payload)
    if not exchange:
        exchange = build_exchange(payload)
    validation = validate_exchange({"exchange": exchange})
    compatibility = compatibility_report({"exchange": exchange})
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "exchange_format_version": EXCHANGE_FORMAT_VERSION,
        "exchange": exchange,
        "validation": validation,
        "compatibility": compatibility,
        "automatic_import": False,
        "automatic_persistence": False,
        "authority_changed": False,
        "guardrails": guardrails(),
    }
    basis = {
        "exchange_id": exchange.get("exchange_id"),
        "exchange_fingerprint_sha256": exchange.get("exchange_fingerprint_sha256"),
        "validation_fingerprint_sha256": validation.get("validation_fingerprint_sha256"),
        "compatibility_fingerprint_sha256": compatibility.get("report_fingerprint_sha256"),
    }
    body["export_id"] = "research-object-exchange-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-portable-research-object-exchange.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
