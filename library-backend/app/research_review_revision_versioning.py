from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.32.0"
BACKEND_VERSION = "3.32.0"
WEB_VERSION = "2.32.0"
SDK_VERSION = "1.32.0"

CONTRACT = "sc-library-research-review-revision-versioning/1.0"
READINESS_CONTRACT = "sc-library-research-review-revision-versioning-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-review-revision-versioning-bootstrap/1.0"
SNAPSHOT_CONTRACT = "sc-library-research-version-snapshot/1.0"
COMPARISON_CONTRACT = "sc-library-research-version-comparison/1.0"
REVIEW_CONTRACT = "sc-library-research-review-packet/1.0"
REVISION_CONTRACT = "sc-library-research-revision-proposal/1.0"
DECISION_CONTRACT = "sc-library-research-review-decision/1.0"
HISTORY_CONTRACT = "sc-library-research-version-history/1.0"
CHAIN_VALIDATION_CONTRACT = "sc-library-research-version-chain-validation/1.0"
EXPORT_CONTRACT = "sc-library-research-review-revision-versioning-export/1.0"

REVIEW_STATES = (
    "draft",
    "in-review",
    "changes-requested",
    "approved",
    "rejected",
    "superseded",
)

DECISIONS = ("approve", "request-changes", "reject", "abstain")
CHANGE_KINDS = ("added", "removed", "modified")


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
        "review_system_is_new_domain_object_authority": False,
        "review_system_is_project_persistence_authority": False,
        "review_system_is_execution_authority": False,
        "version_snapshot_changes_originating_authority": False,
        "snapshot_fingerprint_implies_truth": False,
        "review_approval_implies_truth": False,
        "review_approval_implies_scientific_validity": False,
        "review_rejection_implies_falsity": False,
        "revision_count_implies_quality": False,
        "change_count_implies_materiality": False,
        "automatic_review_decision": False,
        "automatic_revision_application": False,
        "automatic_version_promotion": False,
        "automatic_conflict_resolution": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "human_review_decisions_are_explicit": True,
        "supersession_requires_explicit_link": True,
        "existing_object_authorities_preserved": True,
        "python_research_state_postgresql_remains_project_persistence_authority": True,
        "server_side_review_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "immutable-content-fingerprinted-version-snapshots",
        "deterministic-field-level-version-comparison",
        "human-review-packets",
        "explicit-review-decisions",
        "revision-proposals",
        "supersession-and-parent-version-links",
        "version-history-composition",
        "version-chain-validation",
        "lineage-handoff-relations",
        "portable-review-revision-export",
    ]
    basis = {
        "resources": resources,
        "review_states": REVIEW_STATES,
        "decisions": DECISIONS,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "system_id": "research-review-revision-versioning:" + _fp(basis)[:32],
        "system_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/project/review",
        "api_base": "/api/library/v1/research-review-versioning",
        "project_source_contract": "sc-library-unified-research-project/1.0",
        "lineage_handoff_contract": "sc-library-research-dependency-lineage-graph/1.0",
        "project_persistence_authority": "python-research-state-postgresql",
        "resources": resources,
        "review_states": list(REVIEW_STATES),
        "decisions": list(DECISIONS),
        "next_release": "6.33.0",
        "next_release_name": "Research Package Validation & Publication Readiness",
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
        "version_snapshot_ready": True,
        "version_comparison_ready": True,
        "review_packet_ready": True,
        "review_decision_ready": True,
        "revision_proposal_ready": True,
        "version_history_ready": True,
        "version_chain_validation_ready": True,
        "lineage_handoff_ready": True,
        "existing_object_authorities_preserved": True,
        "server_side_review_persistence": False,
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
        "route": "/research/project/review",
        "readiness": readiness(),
        "operations": [
            "snapshot",
            "compare",
            "review-packet",
            "revision-proposal",
            "review-decision",
            "version-history",
            "validate-chain",
            "export",
        ],
        "review_states": list(REVIEW_STATES),
        "decisions": list(DECISIONS),
        "browser_storage_key": "sc-library-research-review-revision-versioning-v1",
        "guardrails": guardrails(),
    }


def _subject(payload: dict[str, Any]) -> tuple[str, str, str, dict[str, Any]]:
    subject = _dict(payload.get("subject"))
    if not subject:
        raise ValueError("subject object is required")
    subject_id = _clean(payload.get("subject_id") or subject.get("object_id") or subject.get("project_manifest_id") or subject.get("id"))
    if not subject_id:
        raise ValueError("subject_id or subject.object_id/project_manifest_id/id is required")
    subject_type = _clean(payload.get("subject_type") or subject.get("type") or subject.get("schema")) or "research-object"
    authority = _clean(payload.get("authority") or subject.get("authority") or subject.get("persistence_authority")) or "originating-authority"
    return subject_id, subject_type, authority, subject


def version_snapshot(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    subject_id, subject_type, authority, subject = _subject(payload)
    version_label = _clean(payload.get("version_label") or payload.get("version"))
    if not version_label:
        raise ValueError("version_label is required")
    parent_version_id = _clean(payload.get("parent_version_id"))
    supersedes_version_id = _clean(payload.get("supersedes_version_id"))
    state = _clean(payload.get("review_state")) or "draft"
    if state not in REVIEW_STATES:
        raise ValueError(f"unsupported review_state: {state}")
    content_fp = _fp(subject)
    basis = {
        "subject_id": subject_id,
        "subject_type": subject_type,
        "authority": authority,
        "version_label": version_label,
        "parent_version_id": parent_version_id,
        "supersedes_version_id": supersedes_version_id,
        "content_fingerprint_sha256": content_fp,
        "review_state": state,
    }
    snapshot_id = "research-version:" + _fp(basis)[:32]
    lineage_relations = []
    if parent_version_id:
        lineage_relations.append({
            "source": snapshot_id,
            "target": parent_version_id,
            "relation": "version-of",
            "authority": authority,
            "explicit": True,
            "inferred": False,
        })
    if supersedes_version_id:
        lineage_relations.append({
            "source": snapshot_id,
            "target": supersedes_version_id,
            "relation": "supersedes",
            "authority": authority,
            "explicit": True,
            "inferred": False,
        })
    return {
        "schema": SNAPSHOT_CONTRACT,
        "snapshot_id": snapshot_id,
        "snapshot_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "subject_id": subject_id,
        "subject_type": subject_type,
        "authority": authority,
        "version_label": version_label,
        "parent_version_id": parent_version_id,
        "supersedes_version_id": supersedes_version_id,
        "review_state": state,
        "content_fingerprint_sha256": content_fp,
        "subject": subject,
        "lineage_relations": lineage_relations,
        "persisted": False,
        "persistence_authority": "python-research-state-postgresql",
        "guardrails": guardrails(),
    }


def _diff(before: Any, after: Any, path: str = "$", out: list[dict[str, Any]] | None = None) -> list[dict[str, Any]]:
    changes = out if out is not None else []
    if isinstance(before, dict) and isinstance(after, dict):
        keys = sorted(set(before) | set(after))
        for key in keys:
            p = f"{path}.{key}"
            if key not in before:
                changes.append({"path": p, "kind": "added", "before": None, "after": after[key]})
            elif key not in after:
                changes.append({"path": p, "kind": "removed", "before": before[key], "after": None})
            else:
                _diff(before[key], after[key], p, changes)
        return changes
    if isinstance(before, list) and isinstance(after, list):
        max_len = max(len(before), len(after))
        for i in range(max_len):
            p = f"{path}[{i}]"
            if i >= len(before):
                changes.append({"path": p, "kind": "added", "before": None, "after": after[i]})
            elif i >= len(after):
                changes.append({"path": p, "kind": "removed", "before": before[i], "after": None})
            else:
                _diff(before[i], after[i], p, changes)
        return changes
    if before != after:
        changes.append({"path": path, "kind": "modified", "before": before, "after": after})
    return changes


def compare_versions(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    before = _dict(payload.get("before_snapshot"))
    after = _dict(payload.get("after_snapshot"))
    if before.get("schema") != SNAPSHOT_CONTRACT or after.get("schema") != SNAPSHOT_CONTRACT:
        raise ValueError("before_snapshot and after_snapshot must be research version snapshots")
    changes = _diff(before.get("subject"), after.get("subject"))
    counts = {kind: sum(1 for c in changes if c["kind"] == kind) for kind in CHANGE_KINDS}
    basis = {
        "before": before.get("snapshot_id"),
        "after": after.get("snapshot_id"),
        "changes": changes,
    }
    return {
        "schema": COMPARISON_CONTRACT,
        "comparison_id": "research-version-comparison:" + _fp(basis)[:32],
        "comparison_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "before_snapshot_id": before.get("snapshot_id"),
        "after_snapshot_id": after.get("snapshot_id"),
        "same_subject": before.get("subject_id") == after.get("subject_id"),
        "before_content_fingerprint_sha256": before.get("content_fingerprint_sha256"),
        "after_content_fingerprint_sha256": after.get("content_fingerprint_sha256"),
        "changed": bool(changes),
        "change_count": len(changes),
        "change_counts": counts,
        "changes": changes,
        "change_count_implies_materiality": False,
        "guardrails": guardrails(),
    }


def review_packet(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    snapshot = _dict(payload.get("snapshot"))
    if snapshot.get("schema") != SNAPSHOT_CONTRACT:
        raise ValueError("snapshot must be a research version snapshot")
    criteria = []
    for raw in _list(payload.get("criteria")):
        if isinstance(raw, dict):
            item = _dict(raw)
            criterion_id = _clean(item.get("criterion_id") or item.get("id"))
            label = _clean(item.get("label") or item.get("name"))
            if criterion_id or label:
                criteria.append({
                    "criterion_id": criterion_id or "criterion:" + _fp(item)[:16],
                    "label": label or criterion_id,
                    "status": _clean(item.get("status")) or "unreviewed",
                    "notes": _clean(item.get("notes")),
                })
        elif _clean(raw):
            text = _clean(raw)
            criteria.append({"criterion_id": "criterion:" + _fp(text)[:16], "label": text, "status": "unreviewed", "notes": None})
    findings = []
    for raw in _list(payload.get("findings")):
        item = _dict(raw)
        text = _clean(item.get("text") or item.get("finding"))
        if text:
            findings.append({
                "finding_id": _clean(item.get("finding_id") or item.get("id")) or "finding:" + _fp(item)[:16],
                "text": text,
                "severity": _clean(item.get("severity")) or "unspecified",
                "locator": _dict(item.get("locator")),
                "resolved": bool(item.get("resolved", False)),
            })
    reviewer = _dict(payload.get("reviewer"))
    basis = {
        "snapshot_id": snapshot.get("snapshot_id"),
        "reviewer": reviewer,
        "criteria": criteria,
        "findings": findings,
        "scope": _clean(payload.get("scope")),
    }
    return {
        "schema": REVIEW_CONTRACT,
        "review_id": "research-review:" + _fp(basis)[:32],
        "review_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "snapshot_id": snapshot.get("snapshot_id"),
        "subject_id": snapshot.get("subject_id"),
        "version_label": snapshot.get("version_label"),
        "reviewer": reviewer,
        "scope": _clean(payload.get("scope")),
        "criteria": criteria,
        "findings": findings,
        "finding_count": len(findings),
        "unresolved_finding_count": sum(1 for x in findings if not x["resolved"]),
        "decision": None,
        "decision_automatic": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def revision_proposal(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    base = _dict(payload.get("base_snapshot"))
    if base.get("schema") != SNAPSHOT_CONTRACT:
        raise ValueError("base_snapshot must be a research version snapshot")
    candidate_subject = _dict(payload.get("candidate_subject"))
    if not candidate_subject:
        raise ValueError("candidate_subject is required")
    candidate = version_snapshot({
        "subject": candidate_subject,
        "subject_id": base.get("subject_id"),
        "subject_type": base.get("subject_type"),
        "authority": base.get("authority"),
        "version_label": _clean(payload.get("candidate_version_label")) or f"{base.get('version_label')}-revision",
        "parent_version_id": base.get("snapshot_id"),
        "supersedes_version_id": base.get("snapshot_id"),
        "review_state": "draft",
    })
    comparison = compare_versions({"before_snapshot": base, "after_snapshot": candidate})
    rationale = _clean(payload.get("rationale"))
    basis = {
        "base": base.get("snapshot_id"),
        "candidate": candidate.get("snapshot_id"),
        "rationale": rationale,
        "changes": comparison.get("changes"),
    }
    return {
        "schema": REVISION_CONTRACT,
        "revision_id": "research-revision:" + _fp(basis)[:32],
        "revision_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "base_snapshot_id": base.get("snapshot_id"),
        "candidate_snapshot": candidate,
        "rationale": rationale,
        "comparison": comparison,
        "status": "proposed",
        "automatic_application": False,
        "lineage_relations": candidate.get("lineage_relations", []),
        "persisted": False,
        "guardrails": guardrails(),
    }


def review_decision(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    review = _dict(payload.get("review"))
    if review.get("schema") != REVIEW_CONTRACT:
        raise ValueError("review must be a research review packet")
    decision = _clean(payload.get("decision"))
    if decision not in DECISIONS:
        raise ValueError(f"decision must be one of: {', '.join(DECISIONS)}")
    decided_by = _dict(payload.get("decided_by"))
    rationale = _clean(payload.get("rationale"))
    basis = {
        "review_id": review.get("review_id"),
        "decision": decision,
        "decided_by": decided_by,
        "rationale": rationale,
    }
    resulting_state = {
        "approve": "approved",
        "request-changes": "changes-requested",
        "reject": "rejected",
        "abstain": "in-review",
    }[decision]
    return {
        "schema": DECISION_CONTRACT,
        "decision_id": "research-review-decision:" + _fp(basis)[:32],
        "decision_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "review_id": review.get("review_id"),
        "snapshot_id": review.get("snapshot_id"),
        "decision": decision,
        "resulting_review_state": resulting_state,
        "decided_by": decided_by,
        "rationale": rationale,
        "human_asserted": True,
        "automatic": False,
        "applied_to_subject": False,
        "guardrails": guardrails(),
    }


def _snapshot_valid(snapshot: dict[str, Any]) -> bool:
    if snapshot.get("schema") != SNAPSHOT_CONTRACT:
        return False
    subject = _dict(snapshot.get("subject"))
    return snapshot.get("content_fingerprint_sha256") == _fp(subject)


def version_history(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    snapshots = [_dict(x) for x in _list(payload.get("snapshots"))]
    if not snapshots:
        raise ValueError("snapshots are required")
    ids = [str(x.get("snapshot_id") or "") for x in snapshots]
    if any(not x for x in ids):
        raise ValueError("every snapshot requires snapshot_id")
    rows = []
    for snap in snapshots:
        rows.append({
            "snapshot_id": snap.get("snapshot_id"),
            "subject_id": snap.get("subject_id"),
            "version_label": snap.get("version_label"),
            "review_state": snap.get("review_state"),
            "parent_version_id": snap.get("parent_version_id"),
            "supersedes_version_id": snap.get("supersedes_version_id"),
            "content_fingerprint_sha256": snap.get("content_fingerprint_sha256"),
            "content_fingerprint_valid": _snapshot_valid(snap),
        })
    basis = {"rows": rows}
    return {
        "schema": HISTORY_CONTRACT,
        "history_id": "research-version-history:" + _fp(basis)[:32],
        "history_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "subject_ids": sorted({str(x.get("subject_id")) for x in snapshots if x.get("subject_id")}),
        "snapshot_count": len(snapshots),
        "rows": rows,
        "guardrails": guardrails(),
    }


def validate_chain(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    snapshots = [_dict(x) for x in _list(payload.get("snapshots"))]
    errors: list[str] = []
    warnings: list[str] = []
    if not snapshots:
        errors.append("snapshots are required")
    ids = [str(x.get("snapshot_id") or "") for x in snapshots]
    if any(not x for x in ids):
        errors.append("every snapshot requires snapshot_id")
    if len([x for x in ids if x]) != len(set(x for x in ids if x)):
        errors.append("duplicate snapshot_id")
    known = set(x for x in ids if x)
    parents: dict[str, str] = {}
    dangling = []
    invalid_fingerprints = []
    subject_ids = {str(x.get("subject_id")) for x in snapshots if x.get("subject_id")}
    for snap in snapshots:
        sid = str(snap.get("snapshot_id") or "")
        if snap.get("schema") != SNAPSHOT_CONTRACT:
            errors.append(f"{sid or 'snapshot'}: invalid schema")
        if not _snapshot_valid(snap):
            invalid_fingerprints.append(sid)
        parent = _clean(snap.get("parent_version_id"))
        if sid and parent:
            parents[sid] = parent
            if parent not in known:
                dangling.append({"snapshot_id": sid, "parent_version_id": parent})
    cycles = []
    for start in known:
        seen: list[str] = []
        current = start
        while current in parents:
            current = parents[current]
            if current in seen:
                cycle = seen[seen.index(current):] + [current]
                if cycle not in cycles:
                    cycles.append(cycle)
                break
            seen.append(current)
    if dangling:
        warnings.append("version chain contains external or missing parent references")
    if invalid_fingerprints:
        errors.append("one or more snapshot content fingerprints do not match their subject payloads")
    if cycles:
        errors.append("version chain contains a cycle")
    if len(subject_ids) > 1:
        warnings.append("version history contains multiple subject_id values")
    return {
        "schema": CHAIN_VALIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "snapshot_count": len(snapshots),
        "dangling_parent_references": dangling,
        "invalid_content_fingerprints": invalid_fingerprints,
        "cycles": cycles,
        "multiple_subjects": len(subject_ids) > 1,
        "automatic_conflict_resolution": False,
        "automatic_version_promotion": False,
        "guardrails": guardrails(),
    }


def export_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    snapshots = [_dict(x) for x in _list(payload.get("snapshots"))]
    reviews = [_dict(x) for x in _list(payload.get("reviews"))]
    revisions = [_dict(x) for x in _list(payload.get("revisions"))]
    decisions = [_dict(x) for x in _list(payload.get("decisions"))]
    history = version_history({"snapshots": snapshots}) if snapshots else None
    validation = validate_chain({"snapshots": snapshots}) if snapshots else None
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "snapshots": snapshots,
        "reviews": reviews,
        "revisions": revisions,
        "decisions": decisions,
        "version_history": history,
        "chain_validation": validation,
        "lineage_relations": [
            rel
            for snap in snapshots
            for rel in _list(snap.get("lineage_relations"))
            if isinstance(rel, dict)
        ],
        "persisted": False,
        "persistence_authority": "python-research-state-postgresql",
        "guardrails": guardrails(),
    }
    content = json.dumps(body, indent=2, sort_keys=True, ensure_ascii=False) + "\n"
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-research-review-revision-versioning.json",
        "media_type": "application/json",
        "content_sha256": hashlib.sha256(content.encode("utf-8")).hexdigest(),
        "content": content,
        "persisted": False,
        "guardrails": guardrails(),
    }
