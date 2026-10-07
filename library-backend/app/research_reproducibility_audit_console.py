from __future__ import annotations

from copy import deepcopy
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.38.0"
BACKEND_VERSION = "3.38.0"
WEB_VERSION = "2.38.0"
SDK_VERSION = "1.38.0"

CONTRACT = "sc-library-research-reproducibility-audit-console/1.0"
READINESS_CONTRACT = "sc-library-research-reproducibility-audit-console-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-reproducibility-audit-console-bootstrap/1.0"
MANIFEST_CONTRACT = "sc-library-reproducibility-audit-manifest/1.0"
INTEGRITY_AUDIT_CONTRACT = "sc-library-research-integrity-audit/1.0"
REPRODUCIBILITY_AUDIT_CONTRACT = "sc-library-reproducibility-observation-audit/1.0"
LINEAGE_AUDIT_CONTRACT = "sc-library-reproducibility-lineage-audit/1.0"
DRIFT_AUDIT_CONTRACT = "sc-library-reproducibility-drift-audit/1.0"
EXPORT_CONTRACT = "sc-library-reproducibility-audit-export/1.0"

MAX_COMPONENTS_PER_GROUP = 10000
_COMPONENT_GROUPS = (
    "research_objects",
    "source_provenance",
    "dependencies",
    "artifacts",
    "executions",
    "reviews",
)


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, list) else []


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    value = str(value).strip()
    return value or None


def _canonical(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canonical(value).encode("utf-8")).hexdigest()


def _wrapped_group(values: Any, group: str) -> list[dict[str, Any]]:
    raw = _list(values)
    if len(raw) > MAX_COMPONENTS_PER_GROUP:
        raise ValueError(f"{group} limit exceeded: {MAX_COMPONENTS_PER_GROUP}")
    out: list[dict[str, Any]] = []
    for index, item in enumerate(raw):
        exact = deepcopy(item)
        out.append({
            "index": index,
            "payload": exact,
            "payload_fingerprint_sha256": _fp(exact),
            "payload_rewritten": False,
        })
    return out


def _manifest(payload: Any) -> dict[str, Any]:
    manifest = _dict(payload)
    if manifest.get("schema") != MANIFEST_CONTRACT:
        raise ValueError("valid reproducibility audit manifest is required")
    return manifest


def _payload(wrapper: Any) -> dict[str, Any]:
    return _dict(_dict(wrapper).get("payload"))


def _identifier(item: dict[str, Any], fallback: str) -> str:
    for key in (
        "id", "object_id", "record_id", "source_record_id", "artifact_id", "dependency_id",
        "execution_id", "review_id", "name", "url", "doi",
    ):
        value = _clean(item.get(key))
        if value:
            return f"{key}:{value}"
    return fallback


def guardrails() -> dict[str, Any]:
    return {
        "audit_console_is_source_authority": False,
        "audit_console_is_artifact_authority": False,
        "audit_console_is_execution_authority": False,
        "audit_console_is_reproducibility_certification_authority": False,
        "audit_console_is_truth_authority": False,
        "originating_authorities_preserved": True,
        "original_payloads_preserved": True,
        "automatic_external_fetch": False,
        "automatic_artifact_download": False,
        "automatic_code_execution": False,
        "automatic_reexecution": False,
        "automatic_environment_recreation": False,
        "automatic_dependency_installation": False,
        "automatic_artifact_mutation": False,
        "automatic_record_mutation": False,
        "automatic_persistence": False,
        "automatic_reproducibility_certification": False,
        "audit_completeness_implies_reproducibility": False,
        "hash_match_implies_scientific_validity": False,
        "lineage_completeness_implies_truth": False,
        "drift_implies_error": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "authority-preserving-audit-manifest",
        "payload-and-artifact-integrity-audit",
        "reproducibility-observation-checklist",
        "dependency-provenance-execution-lineage-audit",
        "baseline-current-drift-audit",
        "deterministic-audit-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "console_id": "research-reproducibility-audit-console:" + _fp(basis)[:32],
        "console_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/audit",
        "api_base": "/api/library/v1/research-audit",
        "composes_existing_authorities": [
            "research-package-composer",
            "research-package-validation-readiness",
            "research-dependency-lineage-graph",
            "research-review-revision-versioning",
            "portable-research-object-exchange",
            "cross-library-cross-institution-research-federation",
            "execution-lineage",
            "research-package-reproducibility",
        ],
        "resources": resources,
        "next_release": "6.39.0",
        "next_release_name": "Library 7 Production Consolidation & Certification",
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
        "manifest_ready": True,
        "integrity_audit_ready": True,
        "reproducibility_observation_audit_ready": True,
        "lineage_audit_ready": True,
        "drift_audit_ready": True,
        "deterministic_export_ready": True,
        "live_artifact_fetch_configured": False,
        "code_execution_configured": False,
        "automatic_reexecution": False,
        "server_side_audit_persistence": False,
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
        "route": "/research/audit",
        "readiness": readiness(),
        "operations": ["manifest", "integrity-audit", "reproducibility-audit", "lineage-audit", "drift-audit", "export"],
        "browser_storage_key": "sc-library-research-reproducibility-audit-v1",
        "guardrails": guardrails(),
    }


def build_audit_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    subject = deepcopy(_dict(payload.get("subject")))
    subject_id = _clean(
        subject.get("id")
        or subject.get("project_id")
        or subject.get("package_id")
        or subject.get("research_id")
        or payload.get("subject_id")
    )
    if not subject_id:
        raise ValueError("subject.id, subject.project_id, subject.package_id, subject.research_id, or subject_id is required")

    declared_fields = sorted(k for k in (
        *_COMPONENT_GROUPS, "environment", "runtime", "parameters", "federation", "metadata"
    ) if k in payload)
    groups = {group: _wrapped_group(payload.get(group), group) for group in _COMPONENT_GROUPS}
    basis = {
        "subject": subject,
        "groups": groups,
        "environment": deepcopy(_dict(payload.get("environment"))),
        "runtime": deepcopy(_dict(payload.get("runtime"))),
        "parameters": deepcopy(_dict(payload.get("parameters"))),
        "federation": deepcopy(_dict(payload.get("federation"))),
        "metadata": deepcopy(_dict(payload.get("metadata"))),
        "declared_fields": declared_fields,
    }
    fingerprint = _fp(basis)
    return {
        "schema": MANIFEST_CONTRACT,
        "manifest_id": "reproducibility-audit-manifest:" + fingerprint[:32],
        "manifest_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "subject_id": subject_id,
        "subject": subject,
        **groups,
        "environment": basis["environment"],
        "runtime": basis["runtime"],
        "parameters": basis["parameters"],
        "federation": basis["federation"],
        "metadata": basis["metadata"],
        "declared_fields": declared_fields,
        "component_counts": {group: len(groups[group]) for group in _COMPONENT_GROUPS},
        "originating_authorities_preserved": True,
        "payloads_rewritten": False,
        "executed": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def integrity_audit(payload: dict[str, Any]) -> dict[str, Any]:
    manifest = _manifest(_dict(payload).get("manifest") or payload)
    component_findings: list[dict[str, Any]] = []
    mismatch_count = 0
    for group in _COMPONENT_GROUPS:
        for wrapper in _list(manifest.get(group)):
            wrapper = _dict(wrapper)
            exact = deepcopy(wrapper.get("payload"))
            declared = _clean(wrapper.get("payload_fingerprint_sha256"))
            observed = _fp(exact)
            matches = bool(declared and declared == observed)
            if not matches:
                mismatch_count += 1
            component_findings.append({
                "group": group,
                "index": wrapper.get("index"),
                "declared_payload_fingerprint_sha256": declared,
                "observed_payload_fingerprint_sha256": observed,
                "fingerprint_matches": matches,
                "payload_rewritten": bool(wrapper.get("payload_rewritten", False)),
            })

    artifact_findings: list[dict[str, Any]] = []
    artifact_hash_mismatch_count = 0
    for wrapper in _list(manifest.get("artifacts")):
        item = _payload(wrapper)
        declared = _clean(item.get("sha256") or item.get("content_hash_sha256") or item.get("digest_sha256"))
        content = item.get("content")
        observed = sha256(content.encode("utf-8")).hexdigest() if isinstance(content, str) else None
        if declared and observed:
            status = "verified" if declared.lower() == observed.lower() else "mismatch"
        elif declared:
            status = "not-observed"
        elif observed:
            status = "observed-no-declared-hash"
        else:
            status = "unverifiable"
        if status == "mismatch":
            artifact_hash_mismatch_count += 1
        artifact_findings.append({
            "artifact_id": _identifier(item, f"artifact-index:{_dict(wrapper).get('index')}"),
            "declared_sha256": declared,
            "observed_sha256": observed,
            "status": status,
            "download_performed": False,
            "artifact_mutated": False,
        })

    return {
        "schema": INTEGRITY_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "manifest_id": manifest.get("manifest_id"),
        "component_count": len(component_findings),
        "component_fingerprint_mismatch_count": mismatch_count,
        "artifact_count": len(artifact_findings),
        "artifact_hash_mismatch_count": artifact_hash_mismatch_count,
        "component_findings": component_findings,
        "artifact_findings": artifact_findings,
        "integrity_observation_state": "mismatch-observed" if (mismatch_count or artifact_hash_mismatch_count) else "no-mismatch-observed",
        "integrity_observation_is_reproducibility_certification": False,
        "hash_match_implies_scientific_validity": False,
        "external_fetch_performed": False,
        "guardrails": guardrails(),
    }


def _dependency_pinned(item: dict[str, Any]) -> bool:
    identity = any(_clean(item.get(k)) for k in ("id", "dependency_id", "name", "package", "repository"))
    pin = any(_clean(item.get(k)) for k in ("version", "commit", "commit_sha", "sha256", "digest", "image_digest", "lock_hash"))
    return bool(identity and pin)


def _provenance_declared(item: dict[str, Any]) -> bool:
    authority = any(_clean(item.get(k)) for k in ("source_authority", "authority", "institution_id", "repository"))
    identity = any(_clean(item.get(k)) for k in ("source_record_id", "record_id", "id", "doi", "url", "content_hash_sha256"))
    return bool(authority and identity)


def reproducibility_audit(payload: dict[str, Any]) -> dict[str, Any]:
    manifest = _manifest(_dict(payload).get("manifest") or payload)
    declared = set(_list(manifest.get("declared_fields")))
    deps = [_payload(x) for x in _list(manifest.get("dependencies"))]
    prov = [_payload(x) for x in _list(manifest.get("source_provenance"))]
    executions = [_payload(x) for x in _list(manifest.get("executions"))]
    artifacts = [_payload(x) for x in _list(manifest.get("artifacts"))]
    reviews = [_payload(x) for x in _list(manifest.get("reviews"))]

    checks = [
        {"check": "subject-identity", "state": "observed" if _clean(manifest.get("subject_id")) else "missing"},
        {"check": "source-provenance", "state": "observed" if prov and all(_provenance_declared(x) for x in prov) else ("partial" if prov else "missing")},
        {"check": "dependencies-pinned", "state": "observed" if deps and all(_dependency_pinned(x) for x in deps) else ("partial" if deps else "missing")},
        {"check": "parameters-declared", "state": "observed" if "parameters" in declared else "missing"},
        {"check": "environment-declared", "state": "observed" if "environment" in declared and bool(_dict(manifest.get("environment"))) else "missing"},
        {"check": "runtime-declared", "state": "observed" if "runtime" in declared and bool(_dict(manifest.get("runtime"))) else "missing"},
        {"check": "execution-lineage", "state": "observed" if executions else "missing"},
        {"check": "artifacts-declared", "state": "observed" if artifacts else "missing"},
        {"check": "review-lineage", "state": "observed" if reviews else "missing"},
        {"check": "federation-context-declared", "state": "observed" if "federation" in declared and bool(_dict(manifest.get("federation"))) else "not-declared"},
    ]
    observed = sum(1 for x in checks if x["state"] == "observed")
    missing = sum(1 for x in checks if x["state"] == "missing")
    partial = sum(1 for x in checks if x["state"] == "partial")
    return {
        "schema": REPRODUCIBILITY_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "manifest_id": manifest.get("manifest_id"),
        "checks": checks,
        "observed_check_count": observed,
        "missing_check_count": missing,
        "partial_check_count": partial,
        "observation_state": "complete-observation-set" if missing == 0 and partial == 0 else "incomplete-observation-set",
        "observation_state_is_reproducibility_certification": False,
        "automatic_reexecution": False,
        "automatic_environment_recreation": False,
        "guardrails": guardrails(),
    }


def lineage_audit(payload: dict[str, Any]) -> dict[str, Any]:
    manifest = _manifest(_dict(payload).get("manifest") or payload)
    findings: list[dict[str, Any]] = []
    missing_identity = 0
    missing_authority = 0
    for group in ("research_objects", "source_provenance", "dependencies", "artifacts", "executions", "reviews"):
        for wrapper in _list(manifest.get(group)):
            item = _payload(wrapper)
            fallback = f"{group}-index:{_dict(wrapper).get('index')}"
            ident = _identifier(item, fallback)
            has_explicit_identity = ident != fallback
            authority = _clean(item.get("authority") or item.get("source_authority") or item.get("originating_authority") or item.get("institution_id"))
            if not has_explicit_identity:
                missing_identity += 1
            if group in ("research_objects", "source_provenance", "artifacts") and not authority:
                missing_authority += 1
            findings.append({
                "group": group,
                "identity": ident,
                "explicit_identity": has_explicit_identity,
                "authority": authority,
                "authority_declared": bool(authority),
                "lineage_inferred": False,
            })
    return {
        "schema": LINEAGE_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "manifest_id": manifest.get("manifest_id"),
        "finding_count": len(findings),
        "missing_explicit_identity_count": missing_identity,
        "missing_authority_count": missing_authority,
        "findings": findings,
        "automatic_lineage_inference": False,
        "lineage_completeness_implies_truth": False,
        "guardrails": guardrails(),
    }


def _group_map(manifest: dict[str, Any], group: str) -> dict[str, str]:
    out: dict[str, str] = {}
    for wrapper in _list(manifest.get(group)):
        wrapper = _dict(wrapper)
        item = _payload(wrapper)
        key = _identifier(item, f"index:{wrapper.get('index')}")
        out[key] = _clean(wrapper.get("payload_fingerprint_sha256")) or _fp(item)
    return out


def drift_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    baseline = _manifest(payload.get("baseline_manifest"))
    current = _manifest(payload.get("current_manifest"))
    group_findings: dict[str, Any] = {}
    changed_total = added_total = removed_total = 0
    for group in _COMPONENT_GROUPS:
        before = _group_map(baseline, group)
        after = _group_map(current, group)
        added = sorted(set(after) - set(before))
        removed = sorted(set(before) - set(after))
        changed = sorted(k for k in set(before) & set(after) if before[k] != after[k])
        unchanged = sorted(k for k in set(before) & set(after) if before[k] == after[k])
        added_total += len(added)
        removed_total += len(removed)
        changed_total += len(changed)
        group_findings[group] = {"added": added, "removed": removed, "changed": changed, "unchanged_count": len(unchanged)}

    scalar_findings = {}
    for field in ("environment", "runtime", "parameters", "federation"):
        before = _fp(_dict(baseline.get(field)))
        after = _fp(_dict(current.get(field)))
        scalar_findings[field] = {"changed": before != after, "baseline_fingerprint_sha256": before, "current_fingerprint_sha256": after}

    return {
        "schema": DRIFT_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "baseline_manifest_id": baseline.get("manifest_id"),
        "current_manifest_id": current.get("manifest_id"),
        "group_findings": group_findings,
        "scalar_findings": scalar_findings,
        "added_component_count": added_total,
        "removed_component_count": removed_total,
        "changed_component_count": changed_total,
        "drift_observed": bool(added_total or removed_total or changed_total or any(x["changed"] for x in scalar_findings.values())),
        "drift_implies_error": False,
        "automatic_baseline_replacement": False,
        "guardrails": guardrails(),
    }


def export_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    manifest = _manifest(payload.get("manifest"))
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "manifest": manifest,
        "integrity_audit": deepcopy(_dict(payload.get("integrity_audit"))),
        "reproducibility_audit": deepcopy(_dict(payload.get("reproducibility_audit"))),
        "lineage_audit": deepcopy(_dict(payload.get("lineage_audit"))),
        "drift_audit": deepcopy(_dict(payload.get("drift_audit"))),
        "originating_authorities_preserved": True,
        "automatic_external_fetch": False,
        "automatic_reexecution": False,
        "automatic_persistence": False,
        "automatic_reproducibility_certification": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
    canonical = json.dumps(body, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": f"sustainable-catalyst-reproducibility-audit-{manifest.get('subject_id')}.json".replace("/", "-"),
        "media_type": "application/json",
        "content_sha256": sha256(canonical.encode("utf-8")).hexdigest(),
        "content": canonical,
        "originating_authorities_preserved": True,
        "automatic_external_fetch": False,
        "automatic_reexecution": False,
        "automatic_persistence": False,
        "automatic_reproducibility_certification": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
