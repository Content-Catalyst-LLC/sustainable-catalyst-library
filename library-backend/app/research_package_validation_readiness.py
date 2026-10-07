from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.33.0"
BACKEND_VERSION = "3.33.0"
WEB_VERSION = "2.33.0"
SDK_VERSION = "1.33.0"

CONTRACT = "sc-library-research-package-validation-publication-readiness/1.0"
READINESS_CONTRACT = "sc-library-research-package-validation-publication-readiness-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-package-validation-publication-readiness-bootstrap/1.0"
VALIDATION_CONTRACT = "sc-library-research-package-validation-report/1.0"
COMPLETENESS_CONTRACT = "sc-library-research-package-publication-completeness/1.0"
PROVENANCE_CONTRACT = "sc-library-research-package-publication-provenance-audit/1.0"
REVIEW_CONTRACT = "sc-library-research-package-review-readiness-audit/1.0"
PUBLICATION_READINESS_CONTRACT = "sc-library-research-publication-readiness-gate/1.0"
HANDOFF_CONTRACT = "sc-library-research-publication-readiness-handoff/1.0"
EXPORT_CONTRACT = "sc-library-research-package-validation-readiness-export/1.0"

PACKAGE_SCHEMA = "sc-library-research-package-composition/1.0"
PUBLICATION_DRAFT_SCHEMA = "sc-library-research-publication-draft/1.0"
REVIEW_DECISION_SCHEMA = "sc-library-research-review-decision/1.0"
VERSION_CHAIN_VALIDATION_SCHEMA = "sc-library-research-version-chain-validation/1.0"

DEFAULT_POLICY = {
    "require_package_identity": True,
    "require_nonempty_components": True,
    "require_no_missing_dependencies": True,
    "require_declared_requirements_complete": True,
    "require_complete_component_provenance": True,
    "require_publication_draft": True,
    "require_publication_profile_ready": True,
    "require_explicit_review_approval": False,
    "require_valid_version_chain": False,
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


def guardrails() -> dict[str, Any]:
    return {
        "validation_layer_is_new_package_authority": False,
        "publication_readiness_layer_is_publishing_authority": False,
        "publication_readiness_layer_is_project_persistence_authority": False,
        "existing_package_composer_authority_preserved": True,
        "existing_publication_studio_authority_preserved": True,
        "existing_research_package_publishing_authority_preserved": True,
        "existing_artifact_store_authority_preserved": True,
        "existing_review_versioning_authority_preserved": True,
        "ready_for_handoff_implies_truth": False,
        "ready_for_handoff_implies_scientific_validity": False,
        "ready_for_handoff_implies_peer_review": False,
        "ready_for_handoff_implies_editorial_quality": False,
        "ready_for_handoff_implies_publication_acceptance": False,
        "package_completeness_implies_truth": False,
        "provenance_completeness_implies_source_validity": False,
        "review_approval_implies_truth": False,
        "review_approval_implies_scientific_validity": False,
        "automatic_publication": False,
        "automatic_external_submission": False,
        "automatic_doi_registration": False,
        "automatic_artifact_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_validation_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "package-identity-and-schema-validation",
        "component-and-section-integrity",
        "dependency-integrity",
        "declared-requirement-completeness",
        "component-provenance-coverage",
        "human-review-readiness",
        "version-chain-observation",
        "publication-profile-readiness-observation",
        "configurable-publication-readiness-policy",
        "explicit-publishing-handoff-gate",
        "portable-validation-readiness-export",
    ]
    basis = {"resources": resources, "default_policy": DEFAULT_POLICY, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "system_id": "research-package-validation-readiness:" + _fp(basis)[:32],
        "system_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/package/readiness",
        "api_base": "/api/library/v1/research-package-readiness",
        "package_source_contract": PACKAGE_SCHEMA,
        "publication_draft_contract": PUBLICATION_DRAFT_SCHEMA,
        "review_decision_contract": REVIEW_DECISION_SCHEMA,
        "publication_handoff_endpoint": "/api/library/v1/research-package-publishing/preview",
        "resources": resources,
        "default_policy": dict(DEFAULT_POLICY),
        "next_release": "6.34.0",
        "next_release_name": "Portable Research Object & Exchange Format",
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
        "package_validation_ready": True,
        "completeness_audit_ready": True,
        "provenance_audit_ready": True,
        "review_readiness_audit_ready": True,
        "publication_readiness_gate_ready": True,
        "publishing_handoff_gate_ready": True,
        "automatic_publication": False,
        "server_side_validation_persistence": False,
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
        "route": "/research/package/readiness",
        "readiness": readiness(),
        "operations": [
            "validate-package",
            "completeness",
            "provenance",
            "review-audit",
            "publication-readiness",
            "publishing-handoff",
            "export",
        ],
        "default_policy": dict(DEFAULT_POLICY),
        "browser_storage_key": "sc-library-research-package-publication-readiness-v1",
        "guardrails": guardrails(),
    }


def _package(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("package_composition"))
    if not raw:
        raw = _dict(payload.get("package"))
    if not raw:
        raw = _dict(payload.get("research_package"))
    if not raw and payload.get("schema") == PACKAGE_SCHEMA:
        raw = dict(payload)
    return raw


def _publication(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("publication_draft"))
    if not raw:
        raw = _dict(payload.get("publication"))
    return raw


def _policy(payload: dict[str, Any]) -> dict[str, bool]:
    supplied = _dict(payload.get("policy"))
    out: dict[str, bool] = {}
    for key, default in DEFAULT_POLICY.items():
        value = supplied.get(key, default)
        out[key] = bool(value)
    return out


def validate_package(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    package = _package(payload)
    blockers: list[dict[str, Any]] = []
    advisories: list[dict[str, Any]] = []
    if not package:
        blockers.append({"kind": "package-composition-required"})
    schema = _clean(package.get("schema")) if package else None
    if package and schema != PACKAGE_SCHEMA:
        blockers.append({"kind": "unexpected-package-schema", "observed": schema, "expected": PACKAGE_SCHEMA})
    composition_id = _clean(package.get("composition_id")) if package else None
    fingerprint = _clean(package.get("composition_fingerprint_sha256")) if package else None
    if package and not composition_id:
        blockers.append({"kind": "composition-id-missing"})
    if package and not fingerprint:
        blockers.append({"kind": "composition-fingerprint-missing"})
    components = [_dict(x) for x in _list(package.get("components"))] if package else []
    ids = [_clean(x.get("component_id")) for x in components]
    ids_nonempty = [x for x in ids if x]
    if package and not components:
        blockers.append({"kind": "package-components-empty"})
    if len(ids_nonempty) != len(set(ids_nonempty)):
        blockers.append({"kind": "duplicate-component-id"})
    for index, value in enumerate(ids):
        if not value:
            blockers.append({"kind": "component-id-missing", "index": index})
    known = set(ids_nonempty)
    section_order = [x for x in _list(package.get("section_order")) if isinstance(x, str)] if package else []
    unknown_sections = [x for x in section_order if x not in known]
    for item in unknown_sections:
        blockers.append({"kind": "section-order-references-unknown-component", "component_id": item})
    unlisted = [x for x in ids_nonempty if x not in section_order]
    if unlisted:
        advisories.append({"kind": "components-not-explicitly-ordered", "component_ids": unlisted})
    dependency_map = _dict(package.get("dependency_map")) if package else {}
    missing_dependencies = _list(dependency_map.get("missing_dependencies"))
    for item in missing_dependencies:
        blockers.append({"kind": "missing-component-dependency", "detail": item})
    result_basis = {
        "composition_id": composition_id,
        "fingerprint": fingerprint,
        "blockers": blockers,
        "advisories": advisories,
    }
    return {
        "schema": VALIDATION_CONTRACT,
        "validation_id": "research-package-validation:" + _fp(result_basis)[:32],
        "validation_fingerprint_sha256": _fp(result_basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not blockers,
        "blockers": blockers,
        "blocker_count": len(blockers),
        "advisories": advisories,
        "advisory_count": len(advisories),
        "composition_id": composition_id,
        "composition_fingerprint_sha256": fingerprint,
        "component_count": len(components),
        "package_authority_changed": False,
        "guardrails": guardrails(),
    }


def completeness_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    package = _package(payload)
    composer_audit = _dict(package.get("completeness_audit"))
    findings = [_dict(x) for x in _list(composer_audit.get("findings"))]
    components = [_dict(x) for x in _list(package.get("components"))]
    required_draft = []
    empty_required = []
    for component in components:
        if not bool(component.get("required")):
            continue
        cid = _clean(component.get("component_id"))
        state = _clean(component.get("state")) or "unknown"
        if state in {"draft", "unknown"}:
            required_draft.append(cid)
        if component.get("payload") in ({}, [], "", None):
            empty_required.append(cid)
    blockers = list(findings)
    blockers.extend({"kind": "required-component-not-review-ready", "component_id": x} for x in required_draft if x)
    blockers.extend({"kind": "required-component-payload-empty", "component_id": x} for x in empty_required if x)
    basis = {"composer_audit": composer_audit, "required_draft": required_draft, "empty_required": empty_required}
    return {
        "schema": COMPLETENESS_CONTRACT,
        "audit_id": "publication-completeness:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "composer_complete_against_declared_requirements": bool(composer_audit.get("complete_against_declared_requirements", False)),
        "composer_finding_count": int(composer_audit.get("finding_count") or len(findings)),
        "required_component_review_state_findings": [x for x in required_draft if x],
        "required_component_empty_payload_findings": [x for x in empty_required if x],
        "blockers": blockers,
        "blocker_count": len(blockers),
        "complete_for_selected_policy": len(blockers) == 0,
        "completeness_implies_truth": False,
        "guardrails": guardrails(),
    }


def provenance_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    package = _package(payload)
    composer_audit = _dict(package.get("provenance_audit"))
    findings = [_dict(x) for x in _list(composer_audit.get("findings"))]
    components = [_dict(x) for x in _list(package.get("components"))]
    rows = []
    additional = []
    for component in components:
        cid = _clean(component.get("component_id"))
        state = (_clean(component.get("provenance_state")) or "unknown").lower()
        source_identity = bool(component.get("source_route") or component.get("source_contract") or component.get("source_object_id"))
        row = {"component_id": cid, "provenance_state": state, "source_identity_recorded": source_identity}
        rows.append(row)
        if state not in {"complete", "partial"}:
            additional.append({"kind": "component-provenance-unresolved", "component_id": cid, "provenance_state": state})
        if not source_identity:
            additional.append({"kind": "component-source-identity-not-recorded", "component_id": cid})
    blockers = findings + additional
    basis = {"composer_audit": composer_audit, "rows": rows, "blockers": blockers}
    return {
        "schema": PROVENANCE_CONTRACT,
        "audit_id": "publication-provenance:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "composer_finding_count": int(composer_audit.get("finding_count") or len(findings)),
        "blockers": blockers,
        "blocker_count": len(blockers),
        "complete_for_selected_policy": len(blockers) == 0,
        "provenance_completeness_implies_source_validity": False,
        "guardrails": guardrails(),
    }


def review_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    decisions = [_dict(x) for x in _list(payload.get("review_decisions"))]
    review_bundle = _dict(payload.get("review_bundle"))
    decisions.extend(_dict(x) for x in _list(review_bundle.get("decisions")))
    valid_decisions = []
    invalid_decisions = []
    approvals = 0
    requested_changes = 0
    rejections = 0
    for item in decisions:
        if item.get("schema") not in {None, REVIEW_DECISION_SCHEMA}:
            invalid_decisions.append(item)
            continue
        decision = _clean(item.get("decision"))
        if decision not in {"approve", "request-changes", "reject", "abstain"}:
            invalid_decisions.append(item)
            continue
        valid_decisions.append(item)
        approvals += int(decision == "approve")
        requested_changes += int(decision == "request-changes")
        rejections += int(decision == "reject")
    chain = _dict(payload.get("version_chain_validation"))
    chain_valid = None
    if chain:
        chain_valid = bool(chain.get("valid")) if "valid" in chain else None
    basis = {"valid": valid_decisions, "invalid": invalid_decisions, "chain": chain}
    return {
        "schema": REVIEW_CONTRACT,
        "audit_id": "package-review-readiness:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "review_decision_count": len(valid_decisions),
        "approval_count": approvals,
        "request_changes_count": requested_changes,
        "rejection_count": rejections,
        "invalid_decision_count": len(invalid_decisions),
        "explicit_approval_present": approvals > 0,
        "unresolved_change_request_present": requested_changes > 0,
        "rejection_present": rejections > 0,
        "version_chain_validation_present": bool(chain),
        "version_chain_valid": chain_valid,
        "approval_implies_truth": False,
        "approval_implies_scientific_validity": False,
        "guardrails": guardrails(),
    }


def publication_readiness(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    package = _package(payload)
    publication = _publication(payload)
    policy = _policy(payload)
    validation = validate_package(payload)
    completeness = completeness_audit(payload)
    provenance = provenance_audit(payload)
    review = review_audit(payload)
    blockers: list[dict[str, Any]] = []
    review_required: list[dict[str, Any]] = []
    advisories: list[dict[str, Any]] = []

    if policy["require_package_identity"] and (not _clean(package.get("composition_id")) or not _clean(package.get("composition_fingerprint_sha256"))):
        blockers.append({"kind": "package-identity-required"})
    if policy["require_nonempty_components"] and not _list(package.get("components")):
        blockers.append({"kind": "package-components-required"})
    if validation["blocker_count"]:
        blockers.extend({"kind": "package-validation-blocker", "detail": x} for x in validation["blockers"])
    missing_dep_count = int(_dict(package.get("dependency_map")).get("missing_dependency_count") or len(_list(_dict(package.get("dependency_map")).get("missing_dependencies"))))
    if policy["require_no_missing_dependencies"] and missing_dep_count:
        blockers.append({"kind": "missing-dependencies", "count": missing_dep_count})
    if policy["require_declared_requirements_complete"] and not completeness["complete_for_selected_policy"]:
        blockers.append({"kind": "declared-requirements-or-required-components-incomplete", "count": completeness["blocker_count"]})
    if policy["require_complete_component_provenance"] and not provenance["complete_for_selected_policy"]:
        blockers.append({"kind": "component-provenance-incomplete", "count": provenance["blocker_count"]})

    publication_ready = False
    publication_readiness_audit = _dict(publication.get("readiness_audit")) if publication else {}
    if publication:
        publication_ready = bool(publication_readiness_audit.get("ready_against_selected_profile", False))
        if publication.get("schema") not in {None, PUBLICATION_DRAFT_SCHEMA}:
            blockers.append({"kind": "unexpected-publication-draft-schema", "observed": publication.get("schema")})
    if policy["require_publication_draft"] and not publication:
        blockers.append({"kind": "publication-draft-required"})
    if policy["require_publication_profile_ready"]:
        if not publication:
            blockers.append({"kind": "publication-profile-readiness-unavailable"})
        elif not publication_ready:
            blockers.append({"kind": "publication-profile-not-ready", "blocker_count": int(publication_readiness_audit.get("blocker_count") or 0)})

    if review["unresolved_change_request_present"]:
        review_required.append({"kind": "review-change-request-present", "count": review["request_changes_count"]})
    if review["rejection_present"]:
        review_required.append({"kind": "review-rejection-present", "count": review["rejection_count"]})
    if policy["require_explicit_review_approval"] and not review["explicit_approval_present"]:
        blockers.append({"kind": "explicit-review-approval-required"})
    elif not review["review_decision_count"]:
        advisories.append({"kind": "no-explicit-review-decision-supplied"})
    if policy["require_valid_version_chain"]:
        if not review["version_chain_validation_present"]:
            blockers.append({"kind": "version-chain-validation-required"})
        elif review["version_chain_valid"] is not True:
            blockers.append({"kind": "version-chain-invalid"})

    ready_for_handoff = not blockers and not review_required
    basis = {
        "package": package.get("composition_id"),
        "publication": publication.get("publication_draft_id") if publication else None,
        "policy": policy,
        "blockers": blockers,
        "review_required": review_required,
        "advisories": advisories,
    }
    return {
        "schema": PUBLICATION_READINESS_CONTRACT,
        "readiness_id": "publication-readiness-gate:" + _fp(basis)[:32],
        "readiness_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "policy": policy,
        "package_validation": validation,
        "completeness_audit": completeness,
        "provenance_audit": provenance,
        "review_audit": review,
        "publication_draft_present": bool(publication),
        "publication_profile_ready": publication_ready,
        "publication_readiness_audit": publication_readiness_audit,
        "blockers": blockers,
        "blocker_count": len(blockers),
        "review_required": review_required,
        "review_required_count": len(review_required),
        "advisories": advisories,
        "advisory_count": len(advisories),
        "ready_for_publication_handoff": ready_for_handoff,
        "published": False,
        "automatic_publication": False,
        "truth_adjudicated": False,
        "scientific_validity_adjudicated": False,
        "peer_review_status_adjudicated": False,
        "guardrails": guardrails(),
    }


def publishing_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    package = _package(payload)
    publication = _publication(payload)
    gate = publication_readiness(payload)
    handoff_payload = {
        "title": _clean(publication.get("title") if publication else package.get("title")) or "Research package",
        "description": _clean(package.get("description")) or "Validated Sustainable Catalyst research package handoff.",
        "profile": "portable-research-package",
        "research_package": package,
        "metadata": {
            "validation_readiness_schema": CONTRACT,
            "publication_readiness_id": gate["readiness_id"],
            "publication_readiness_fingerprint_sha256": gate["readiness_fingerprint_sha256"],
            "publication_draft_id": publication.get("publication_draft_id") if publication else None,
        },
    }
    basis = {"endpoint": "/api/library/v1/research-package-publishing/preview", "payload": handoff_payload, "gate": gate["readiness_fingerprint_sha256"]}
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "publication-readiness-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/research-package-publishing/preview",
        "ready_for_handoff": gate["ready_for_publication_handoff"],
        "blocked": not gate["ready_for_publication_handoff"],
        "blockers": gate["blockers"],
        "review_required": gate["review_required"],
        "payload": handoff_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_persistence": False,
        "automatic_external_publication": False,
        "guardrails": guardrails(),
    }


def export_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "package_validation": validate_package(payload),
        "completeness_audit": completeness_audit(payload),
        "provenance_audit": provenance_audit(payload),
        "review_audit": review_audit(payload),
        "publication_readiness": publication_readiness(payload),
        "publishing_handoff": publishing_handoff(payload),
        "automatic_publication": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {
        "package_validation": body["package_validation"]["validation_fingerprint_sha256"],
        "publication_readiness": body["publication_readiness"]["readiness_fingerprint_sha256"],
    }
    body["export_id"] = "package-validation-readiness-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-research-package-validation-publication-readiness.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
