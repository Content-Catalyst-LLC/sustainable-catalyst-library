from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.20.0"
BACKEND_VERSION = "3.20.0"
WEB_VERSION = "2.20.0"
SDK_VERSION = "1.20.0"

CONTRACT = "sc-library-research-synthesis-workspace/1.0"
READINESS_CONTRACT = "sc-library-research-synthesis-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-synthesis-bootstrap/1.0"
SYNTHESIS_CONTRACT = "sc-library-research-synthesis/1.0"
MATRIX_CONTRACT = "sc-library-research-synthesis-evidence-matrix/1.0"
CONTRADICTION_CONTRACT = "sc-library-research-synthesis-contradiction-ledger/1.0"
CONVERGENCE_CONTRACT = "sc-library-research-synthesis-convergence-summary/1.0"
GAP_CONTRACT = "sc-library-research-synthesis-gap-analysis/1.0"
EXPORT_CONTRACT = "sc-library-research-synthesis-export/1.0"
HANDOFF_CONTRACT = "sc-library-research-synthesis-publishing-handoff-preview/1.0"

STANCE_TYPES = {"supports","contradicts","contextualizes","qualifies","neutral","unknown"}
SOURCE_KINDS = {
    "library-record","primary-source","publication","dataset","citation","annotation",
    "timeline-event","corpus-analysis","entity-resolution","evidence-graph","external-source","other",
}
MAX_SOURCES = 2000
MAX_CLAIMS = 1000
MAX_RELATIONSHIPS = 10000
MAX_QUESTIONS = 500


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 10000) -> str:
    return " ".join(str(value or "").split())[:limit]


def guardrails() -> dict[str, bool]:
    return {
        "synthesis_is_composition_layer": True,
        "synthesis_is_new_truth_store": False,
        "synthesis_is_new_citation_authority": False,
        "synthesis_is_new_evidence_graph_authority": False,
        "synthesis_is_new_annotation_authority": False,
        "synthesis_relationships_are_human_asserted": True,
        "automatic_relationship_inference": False,
        "support_count_is_truth_probability": False,
        "source_count_is_evidence_strength": False,
        "citation_count_is_quality_score": False,
        "agreement_is_truth": False,
        "disagreement_is_falsity": False,
        "contradiction_is_automatically_resolved": False,
        "mixed_evidence_is_collapsed_to_single_answer": False,
        "unresolved_questions_are_preserved": True,
        "source_attribution_is_preserved": True,
        "source_provenance_is_preserved": True,
        "claim_text_is_rewritten_automatically": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_synthesis_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "source-inventory",
        "claim-inventory",
        "explicit-source-claim-relationships",
        "evidence-matrix",
        "contradiction-ledger",
        "convergence-summary",
        "source-attribution-map",
        "unresolved-question-preservation",
        "gap-analysis",
        "reproducible-synthesis-export",
        "research-package-publishing-handoff-preview",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "research-synthesis:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/synthesis",
        "api_base": "/api/library/v1/research-synthesis",
        "resources": resources,
        "limits": {
            "sources": MAX_SOURCES,
            "claims": MAX_CLAIMS,
            "relationships": MAX_RELATIONSHIPS,
            "unresolved_questions": MAX_QUESTIONS,
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
        "server_side_synthesis_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "composes_existing_authorities": [
            "python-provenance-citation-evidence-graph",
            "research-annotation-scholarly-notes",
            "citation-bibliographic-workspace",
            "historical-event-timeline",
            "corpus-computational-linguistics",
            "entity-place-historical-toponym",
            "python-research-state",
            "research-package-publishing",
        ],
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/synthesis",
        "readiness": readiness(),
        "source_kinds": sorted(SOURCE_KINDS),
        "stance_types": sorted(STANCE_TYPES),
        "operations": [
            "synthesize","evidence-matrix","contradictions","convergence",
            "source-attribution","gaps","export","publishing-handoff-preview",
        ],
        "browser_storage_key": "sc-library-research-synthesis-v1",
        "publishing_handoff": {
            "preview_endpoint": "/api/library/v1/research-package-publishing/preview",
            "persist_endpoint": "/api/library/v1/admin/research-package-publishing/persist",
            "automatic_persistence": False,
            "signed_persistence_required": True,
        },
        "guardrails": guardrails(),
    }


def _normalize_source(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    source_id = _clean(row.get("source_id") or row.get("id"), 1000) or f"source:{index}"
    kind = _clean(row.get("kind") or row.get("source_kind"), 100).lower() or "other"
    if kind not in SOURCE_KINDS:
        kind = "other"
    title = _clean(row.get("title") or row.get("label"), 2000) or source_id
    refs = _dict(row.get("refs"))
    return {
        "source_id": source_id,
        "kind": kind,
        "title": title,
        "record_id": _clean(row.get("record_id") or refs.get("record_id"), 1000) or None,
        "citation_id": _clean(row.get("citation_id") or refs.get("citation_id"), 1000) or None,
        "annotation_id": _clean(row.get("annotation_id") or refs.get("annotation_id"), 1000) or None,
        "timeline_event_id": _clean(row.get("timeline_event_id") or refs.get("timeline_event_id"), 1000) or None,
        "corpus_id": _clean(row.get("corpus_id") or refs.get("corpus_id"), 1000) or None,
        "entity_id": _clean(row.get("entity_id") or refs.get("entity_id"), 1000) or None,
        "uri": _clean(row.get("uri") or row.get("url"), 4000) or None,
        "locator": _dict(row.get("locator")),
        "provenance": _dict(row.get("provenance")),
        "notes": _clean(row.get("notes"), 10000) or None,
    }


def _normalize_claim(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    text = _clean(row.get("text") or row.get("claim"), 20000)
    if not text:
        raise ValueError(f"claim-{index}-text-required")
    claim_id = _clean(row.get("claim_id") or row.get("id"), 1000) or f"claim:{index}"
    return {
        "claim_id": claim_id,
        "text": text,
        "label": _clean(row.get("label"), 1000) or None,
        "scope": _clean(row.get("scope"), 4000) or None,
        "status": _clean(row.get("status"), 100).lower() or "open",
        "notes": _clean(row.get("notes"), 10000) or None,
    }


def _normalize_relationship(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    claim_id = _clean(row.get("claim_id"), 1000)
    source_id = _clean(row.get("source_id"), 1000)
    if not claim_id or not source_id:
        raise ValueError(f"relationship-{index}-requires-claim_id-and-source_id")
    stance = _clean(row.get("stance") or row.get("relationship"), 100).lower() or "unknown"
    if stance not in STANCE_TYPES:
        stance = "unknown"
    return {
        "relationship_id": _clean(row.get("relationship_id") or row.get("id"), 1000) or f"relationship:{index}",
        "claim_id": claim_id,
        "source_id": source_id,
        "stance": stance,
        "rationale": _clean(row.get("rationale"), 12000) or None,
        "locator": _dict(row.get("locator")),
        "asserted_by": _clean(row.get("asserted_by"), 1000) or None,
        "asserted_at": _clean(row.get("asserted_at"), 200) or None,
        "human_asserted": True,
    }


def normalize_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    sources_raw = _list(payload.get("sources"))
    claims_raw = _list(payload.get("claims"))
    rels_raw = _list(payload.get("relationships"))
    questions_raw = _list(payload.get("unresolved_questions"))
    if len(sources_raw) > MAX_SOURCES:
        raise ValueError(f"source-limit-exceeded:{MAX_SOURCES}")
    if len(claims_raw) > MAX_CLAIMS:
        raise ValueError(f"claim-limit-exceeded:{MAX_CLAIMS}")
    if len(rels_raw) > MAX_RELATIONSHIPS:
        raise ValueError(f"relationship-limit-exceeded:{MAX_RELATIONSHIPS}")
    if len(questions_raw) > MAX_QUESTIONS:
        raise ValueError(f"unresolved-question-limit-exceeded:{MAX_QUESTIONS}")
    sources = [_normalize_source(x, i) for i, x in enumerate(sources_raw, start=1)]
    claims = [_normalize_claim(x, i) for i, x in enumerate(claims_raw, start=1)]
    relationships = [_normalize_relationship(x, i) for i, x in enumerate(rels_raw, start=1)]
    source_ids = {x["source_id"] for x in sources}
    claim_ids = {x["claim_id"] for x in claims}
    dangling = []
    for rel in relationships:
        if rel["source_id"] not in source_ids or rel["claim_id"] not in claim_ids:
            dangling.append(rel["relationship_id"])
    unresolved = []
    for i, q in enumerate(questions_raw, start=1):
        if isinstance(q, dict):
            text = _clean(q.get("text") or q.get("question"), 10000)
            qid = _clean(q.get("question_id") or q.get("id"), 1000) or f"question:{i}"
            source_refs = [_clean(x, 1000) for x in _list(q.get("source_refs")) if _clean(x, 1000)]
        else:
            text = _clean(q, 10000)
            qid = f"question:{i}"
            source_refs = []
        if text:
            unresolved.append({"question_id": qid, "text": text, "source_refs": source_refs, "resolved": False})
    basis = {
        "question": _clean(payload.get("research_question") or payload.get("question"), 12000),
        "sources": sources,
        "claims": claims,
        "relationships": relationships,
        "unresolved_questions": unresolved,
    }
    return {
        "schema": "sc-library-research-synthesis-workspace-input/1.0",
        "workspace_input_id": "synthesis-input:" + _fp(basis)[:32],
        "workspace_input_fingerprint_sha256": _fp(basis),
        "title": _clean(payload.get("title"), 2000) or "Research synthesis",
        "research_question": basis["question"] or None,
        "scope": _clean(payload.get("scope"), 12000) or None,
        "sources": sources,
        "claims": claims,
        "relationships": relationships,
        "unresolved_questions": unresolved,
        "dangling_relationship_ids": dangling,
        "metadata": _dict(payload.get("metadata")),
    }


def evidence_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    sources = {x["source_id"]: x for x in w["sources"]}
    rels_by_claim: dict[str, list[dict[str, Any]]] = {}
    for rel in w["relationships"]:
        rels_by_claim.setdefault(rel["claim_id"], []).append(rel)
    rows = []
    for claim in w["claims"]:
        rels = rels_by_claim.get(claim["claim_id"], [])
        cells = []
        for rel in rels:
            src = sources.get(rel["source_id"])
            cells.append({
                "source_id": rel["source_id"],
                "source_title": src.get("title") if src else None,
                "source_kind": src.get("kind") if src else None,
                "stance": rel["stance"],
                "rationale": rel["rationale"],
                "locator": rel["locator"],
                "human_asserted": True,
            })
        counts = {stance: sum(1 for x in cells if x["stance"] == stance) for stance in sorted(STANCE_TYPES)}
        rows.append({
            "claim_id": claim["claim_id"],
            "claim_text": claim["text"],
            "claim_status": claim["status"],
            "relationships": cells,
            "stance_counts": counts,
            "relationship_count": len(cells),
        })
    basis = {"input": w["workspace_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": MATRIX_CONTRACT,
        "matrix_id": "synthesis-matrix:" + _fp(basis)[:32],
        "matrix_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "claim_count": len(rows),
        "source_count": len(w["sources"]),
        "relationship_count": len(w["relationships"]),
        "guardrails": guardrails(),
    }


def contradiction_ledger(payload: dict[str, Any]) -> dict[str, Any]:
    matrix = evidence_matrix(payload)
    items = []
    for row in matrix["rows"]:
        counts = row["stance_counts"]
        has_support = counts.get("supports", 0) > 0
        has_contradiction = counts.get("contradicts", 0) > 0
        if has_support and has_contradiction:
            items.append({
                "claim_id": row["claim_id"],
                "claim_text": row["claim_text"],
                "support_count": counts.get("supports", 0),
                "contradiction_count": counts.get("contradicts", 0),
                "relationships": row["relationships"],
                "resolved": False,
                "automatic_resolution": False,
            })
    basis = {"matrix": matrix["matrix_fingerprint_sha256"], "items": items}
    return {
        "schema": CONTRADICTION_CONTRACT,
        "ledger_id": "contradiction-ledger:" + _fp(basis)[:32],
        "ledger_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "items": items,
        "count": len(items),
        "automatic_resolution": False,
        "guardrails": guardrails(),
    }


def convergence_summary(payload: dict[str, Any]) -> dict[str, Any]:
    matrix = evidence_matrix(payload)
    items = []
    for row in matrix["rows"]:
        counts = row["stance_counts"]
        support = counts.get("supports", 0)
        contradict = counts.get("contradicts", 0)
        contextual = counts.get("contextualizes", 0) + counts.get("qualifies", 0)
        if support and contradict:
            pattern = "mixed"
        elif support:
            pattern = "support-only"
        elif contradict:
            pattern = "contradiction-only"
        elif contextual:
            pattern = "context-only"
        elif row["relationship_count"]:
            pattern = "neutral-or-unknown"
        else:
            pattern = "unlinked"
        items.append({
            "claim_id": row["claim_id"],
            "claim_text": row["claim_text"],
            "pattern": pattern,
            "stance_counts": counts,
            "truth_status": None,
            "confidence_score": None,
            "winner_selected": False,
        })
    basis = {"matrix": matrix["matrix_fingerprint_sha256"], "items": items}
    return {
        "schema": CONVERGENCE_CONTRACT,
        "summary_id": "convergence-summary:" + _fp(basis)[:32],
        "summary_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "items": items,
        "agreement_is_truth": False,
        "guardrails": guardrails(),
    }


def source_attribution(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    usage: dict[str, dict[str, Any]] = {}
    for source in w["sources"]:
        usage[source["source_id"]] = {
            "source_id": source["source_id"],
            "title": source["title"],
            "kind": source["kind"],
            "record_id": source["record_id"],
            "citation_id": source["citation_id"],
            "annotation_id": source["annotation_id"],
            "uri": source["uri"],
            "relationship_count": 0,
            "claims": [],
        }
    for rel in w["relationships"]:
        row = usage.get(rel["source_id"])
        if row is None:
            continue
        row["relationship_count"] += 1
        row["claims"].append({"claim_id": rel["claim_id"], "stance": rel["stance"]})
    rows = sorted(usage.values(), key=lambda x: (-x["relationship_count"], x["source_id"]))
    return {
        "schema": "sc-library-research-synthesis-source-attribution/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "source_count": len(rows),
        "source_count_is_quality_score": False,
        "guardrails": guardrails(),
    }


def gap_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    rels_by_claim: dict[str, list[dict[str, Any]]] = {}
    for rel in w["relationships"]:
        rels_by_claim.setdefault(rel["claim_id"], []).append(rel)
    gaps = []
    for claim in w["claims"]:
        rels = rels_by_claim.get(claim["claim_id"], [])
        if not rels:
            gaps.append({"kind": "unlinked-claim", "claim_id": claim["claim_id"], "text": claim["text"]})
        elif all(rel["stance"] in {"neutral","unknown"} for rel in rels):
            gaps.append({"kind": "claim-without-directional-evidence", "claim_id": claim["claim_id"], "text": claim["text"]})
    for q in w["unresolved_questions"]:
        gaps.append({"kind": "unresolved-question", **q})
    for rid in w["dangling_relationship_ids"]:
        gaps.append({"kind": "dangling-relationship", "relationship_id": rid})
    basis = {"input": w["workspace_input_fingerprint_sha256"], "gaps": gaps}
    return {
        "schema": GAP_CONTRACT,
        "gap_analysis_id": "synthesis-gaps:" + _fp(basis)[:32],
        "gap_analysis_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "gaps": gaps,
        "gap_count": len(gaps),
        "automatic_research_execution": False,
        "guardrails": guardrails(),
    }


def build_synthesis(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    matrix = evidence_matrix(payload)
    contradictions = contradiction_ledger(payload)
    convergence = convergence_summary(payload)
    attribution = source_attribution(payload)
    gaps = gap_analysis(payload)
    basis = {
        "input": w["workspace_input_fingerprint_sha256"],
        "matrix": matrix["matrix_fingerprint_sha256"],
        "contradictions": contradictions["ledger_fingerprint_sha256"],
        "convergence": convergence["summary_fingerprint_sha256"],
        "gaps": gaps["gap_analysis_fingerprint_sha256"],
    }
    return {
        "schema": SYNTHESIS_CONTRACT,
        "synthesis_id": "research-synthesis:" + _fp(basis)[:32],
        "synthesis_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": w["title"],
        "research_question": w["research_question"],
        "scope": w["scope"],
        "source_inventory": w["sources"],
        "claim_inventory": w["claims"],
        "evidence_matrix": matrix,
        "contradiction_ledger": contradictions,
        "convergence_summary": convergence,
        "source_attribution": attribution,
        "unresolved_questions": w["unresolved_questions"],
        "gap_analysis": gaps,
        "final_answer_generated": False,
        "truth_adjudicated": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }


def publishing_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    synthesis = build_synthesis(payload)
    package_payload = {
        "title": synthesis["title"],
        "description": synthesis["research_question"] or "Research synthesis package",
        "research_object_type": "research-synthesis",
        "metadata": {
            "synthesis_id": synthesis["synthesis_id"],
            "synthesis_fingerprint_sha256": synthesis["synthesis_fingerprint_sha256"],
            "claim_count": len(synthesis["claim_inventory"]),
            "source_count": len(synthesis["source_inventory"]),
            "contradiction_count": synthesis["contradiction_ledger"]["count"],
            "gap_count": synthesis["gap_analysis"]["gap_count"],
        },
        "content": synthesis,
    }
    basis = {"synthesis_id": synthesis["synthesis_id"], "endpoint": "/api/library/v1/research-package-publishing/preview"}
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "synthesis-publishing-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "preview_only": True,
        "automatic_persistence": False,
        "preview_endpoint": "/api/library/v1/research-package-publishing/preview",
        "persist_endpoint": "/api/library/v1/admin/research-package-publishing/persist",
        "signed_persistence_required": True,
        "package_payload": package_payload,
        "guardrails": guardrails(),
    }


def export_synthesis(payload: dict[str, Any]) -> dict[str, Any]:
    synthesis = build_synthesis(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "synthesis": synthesis,
        "automatic_import": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {"synthesis_id": synthesis["synthesis_id"], "fingerprint": synthesis["synthesis_fingerprint_sha256"]}
    body["export_id"] = "synthesis-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-research-synthesis.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
