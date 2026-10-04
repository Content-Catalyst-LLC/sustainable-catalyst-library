from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .historical_archive_primary_source import (
    analyze_primary_source,
    compare_primary_sources,
    normalize_primary_source,
    provenance_chain,
)
from .historical_archive_workspace import readiness as historical_archives_workspace_readiness

LIBRARY_VERSION = "6.14.0"
BACKEND_VERSION = "3.14.0"
WEB_VERSION = "2.14.0"
SDK_VERSION = "1.14.0"

CONTRACT = "sc-library-primary-source-comparison-source-criticism-workspace/1.0"
READINESS_CONTRACT = "sc-library-primary-source-comparison-source-criticism-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-primary-source-comparison-source-criticism-bootstrap/1.0"
ANALYSIS_CONTRACT = "sc-library-primary-source-criticism-analysis/1.0"
MATRIX_CONTRACT = "sc-library-primary-source-criticism-matrix/1.0"
CORROBORATION_CONTRACT = "sc-library-primary-source-corroboration-ledger/1.0"
MAX_SOURCES = 50
MAX_CLAIMS = 200
MAX_OBSERVATIONS = 1000

DIMENSIONS: tuple[dict[str, Any], ...] = (
    {"key":"origin","label":"Origin & authorship","question":"Who created the source, under what identity or institutional authority, and how is attribution established?"},
    {"key":"temporal_context","label":"Temporal context","question":"When was the source created relative to the events it describes, and how certain is that date?"},
    {"key":"archival_context","label":"Archival context","question":"What fonds, collection, series, file, item, shelfmark, or custodial context surrounds the source?"},
    {"key":"purpose_audience","label":"Purpose & audience","question":"For whom was the source created, for what purpose, and what incentives or constraints may have shaped it?"},
    {"key":"position_access","label":"Position & access","question":"What could the creator plausibly observe, know, or access, and what remained outside that position?"},
    {"key":"mediation","label":"Transmission & mediation","question":"What copies, scans, editions, OCR/HTR, transcriptions, translations, excerpts, or annotations mediate the source?"},
    {"key":"language_representation","label":"Language & representation","question":"Is the original language/script available, and which interpretations depend on derived representations?"},
    {"key":"completeness","label":"Completeness & omission","question":"Is the surviving or digitized record complete, partial, selected, redacted, damaged, or otherwise limited?"},
    {"key":"corroboration","label":"Corroboration & conflict","question":"Which independent sources support, complicate, contextualize, contradict, or remain silent on the relevant claim?"},
    {"key":"rights_access","label":"Rights & access conditions","question":"What access, rights, reuse, or repository restrictions affect inspection and reproducibility?"},
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 6000) -> str:
    return " ".join(str(value or "").split())[:limit]


def guardrails() -> dict[str, bool]:
    return {
        "python_is_source_criticism_workspace_authority": True,
        "historical_archive_v612_remains_primary_source_intelligence_authority": True,
        "historical_archives_v613_remains_archive_workspace_foundation": True,
        "source_criticism_is_truth_scoring": False,
        "source_criticism_is_authenticity_certification": False,
        "source_criticism_is_reliability_score": False,
        "corroboration_count_is_truth_probability": False,
        "contradiction_count_is_falsity_probability": False,
        "agreement_is_independence": False,
        "repository_custody_is_authenticity_certification": False,
        "provenance_completeness_is_authenticity_certification": False,
        "derived_representation_replaces_original": False,
        "translation_is_original_text": False,
        "ocr_htr_transcription_are_original_text": False,
        "human_asserted_relationships_remain_explicit": True,
        "disagreements_are_auto_resolved": False,
        "claims_are_auto_adjudicated": False,
        "sources_are_auto_ranked": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "source-criticism-rubric",
        "provenance-aware-source-analysis",
        "side-by-side-source-criticism-matrix",
        "human-asserted-source-relationships",
        "claim-source-corroboration-ledger",
        "agreement-contradiction-contextualization-silence-observations",
        "unresolved-question-preservation",
        "standalone-web-route-/research/archives/compare",
    ]
    basis={"resources":resources,"dimensions":[d["key"] for d in DIMENSIONS],"guardrails":guardrails()}
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "workspace_id": "primary-source-comparison-criticism:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "state": "authoritative-composition",
        "authority": "python-backend",
        "route": "/research/archives/compare",
        "resources": resources,
        "dimension_count": len(DIMENSIONS),
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    archive=historical_archives_workspace_readiness()
    state=str(archive.get("state") or "unknown")
    blocking=[] if state in {"ready","degraded"} else ["historical-archives-workspace:"+state]
    degraded=[] if state == "ready" else (["historical-archives-workspace:degraded"] if state == "degraded" else [])
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "blocked" if blocking else ("degraded" if degraded else "ready"),
        "ready": not blocking,
        "blocking": blocking,
        "degraded": degraded,
        "dependency": {"historical_archives_workspace":{"state":state,"library_version":archive.get("library_version"),"backend_version":archive.get("backend_version")}},
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
        "route": "/research/archives/compare",
        "readiness": readiness(),
        "dimensions": [dict(x) for x in DIMENSIONS],
        "relationship_types": ["supports","contradicts","contextualizes","derives-from","quotes","responds-to","duplicates","independent-parallel","unknown"],
        "claim_observation_relations": ["supports","contradicts","contextualizes","silent","ambiguous","not-assessed"],
        "guardrails": guardrails(),
    }


def analyze_source(payload: dict[str, Any]) -> dict[str, Any]:
    raw=_dict(payload)
    source=normalize_primary_source(_dict(raw.get("source") or raw))
    base=analyze_primary_source({"source":source})
    prov=provenance_chain({"source":source})
    assessment=_dict(raw.get("assessment"))
    context=source.get("archival_context") or {}
    repo=source.get("repository") or {}
    date=source.get("creation_date") or {}
    derivations=source.get("derivations") or []
    dimension_rows={
        "origin":{"documented":bool(source.get("creator_agents")),"observation":assessment.get("origin") or None},
        "temporal_context":{"documented":date.get("qualifier") != "unknown","observation":assessment.get("temporal_context") or None},
        "archival_context":{"documented":bool(context.get("levels") or context.get("shelfmark")),"observation":assessment.get("archival_context") or None},
        "purpose_audience":{"documented":bool(assessment.get("purpose_audience")),"observation":assessment.get("purpose_audience") or None},
        "position_access":{"documented":bool(assessment.get("position_access")),"observation":assessment.get("position_access") or None},
        "mediation":{"documented":bool(source.get("digital_surrogate",{}).get("url") or derivations),"observation":assessment.get("mediation") or None,"derivation_kinds":[x.get("kind") for x in derivations]},
        "language_representation":{"documented":bool(source.get("original_language")),"observation":assessment.get("language_representation") or None,"original_language":source.get("original_language"),"original_script":source.get("original_script")},
        "completeness":{"documented":bool(assessment.get("completeness")),"observation":assessment.get("completeness") or None},
        "corroboration":{"documented":bool(assessment.get("corroboration")),"observation":assessment.get("corroboration") or None},
        "rights_access":{"documented":bool(source.get("rights",{}).get("statement") or source.get("rights",{}).get("license")),"observation":assessment.get("rights_access") or None,"repository":repo.get("name")},
    }
    open_questions=[]
    for d in DIMENSIONS:
        row=dimension_rows[d["key"]]
        if not row.get("documented"):
            open_questions.append({"dimension":d["key"],"question":d["question"]})
    basis={"source":source.get("object_fingerprint_sha256"),"assessment":assessment,"dimensions":dimension_rows}
    return {
        "schema": ANALYSIS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "analysis_id": "source-criticism-analysis:"+_fp(basis)[:32],
        "analysis_fingerprint_sha256": _fp(basis),
        "source": source,
        "base_source_criticism": base,
        "provenance": prov,
        "dimensions": dimension_rows,
        "open_questions": open_questions,
        "human_assessment": assessment,
        "truth_score": None,
        "reliability_score": None,
        "authenticity_certified": False,
        "interpretive_judgment_required": True,
        "guardrails": guardrails(),
    }


def comparison_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    raw=_dict(payload)
    source_inputs=[_dict(x) for x in _list(raw.get("sources"))[:MAX_SOURCES] if isinstance(x,dict)]
    if len(source_inputs)<2:
        raise ValueError("at least two sources are required for a source-criticism matrix")
    assessment_by_id=_dict(raw.get("assessments"))
    analyses=[]
    for source_input in source_inputs:
        normalized=normalize_primary_source(source_input)
        sid=normalized["primary_source_id"]
        analyses.append(analyze_source({"source":normalized,"assessment":_dict(assessment_by_id.get(sid))}))
    base=compare_primary_sources({"sources":[a["source"] for a in analyses],"relationships":_list(raw.get("relationships"))})
    matrix=[]
    for d in DIMENSIONS:
        cells=[]
        for analysis in analyses:
            row=analysis["dimensions"][d["key"]]
            cells.append({"primary_source_id":analysis["source"]["primary_source_id"],"title":analysis["source"]["title"],**row})
        matrix.append({"dimension":d["key"],"label":d["label"],"question":d["question"],"cells":cells})
    relationships=[]
    for rel in _list(raw.get("relationships"))[:MAX_OBSERVATIONS]:
        r=_dict(rel)
        relationships.append({"source_a":_clean(r.get("source_a"),500),"source_b":_clean(r.get("source_b"),500),"relationship":_clean(r.get("relationship"),100) or "unknown","basis":_clean(r.get("basis"),4000) or None,"human_asserted":True})
    basis={"sources":[a["analysis_fingerprint_sha256"] for a in analyses],"relationships":relationships}
    return {
        "schema": MATRIX_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "matrix_id":"source-criticism-matrix:"+_fp(basis)[:32],
        "matrix_fingerprint_sha256":_fp(basis),
        "source_count":len(analyses),
        "sources":[{"primary_source_id":a["source"]["primary_source_id"],"title":a["source"]["title"]} for a in analyses],
        "matrix":matrix,
        "base_comparison":base,
        "relationships":relationships,
        "truth_determination":None,
        "automatic_ranking":False,
        "disagreement_auto_resolved":False,
        "guardrails":guardrails(),
    }


def corroboration_ledger(payload: dict[str, Any]) -> dict[str, Any]:
    raw=_dict(payload)
    sources=[normalize_primary_source(_dict(x)) for x in _list(raw.get("sources"))[:MAX_SOURCES] if isinstance(x,dict)]
    source_ids={s["primary_source_id"] for s in sources}
    claims=[]
    for raw_claim in _list(raw.get("claims"))[:MAX_CLAIMS]:
        c=_dict(raw_claim)
        text=_clean(c.get("text") or c.get("claim"),6000)
        if not text:
            continue
        claim_id=_clean(c.get("claim_id"),500) or "claim:"+_fp({"text":text})[:24]
        observations=[]
        for raw_obs in _list(c.get("observations"))[:MAX_OBSERVATIONS]:
            o=_dict(raw_obs)
            sid=_clean(o.get("primary_source_id") or o.get("source_id"),500)
            relation=_clean(o.get("relation"),100).lower() or "not-assessed"
            if relation not in {"supports","contradicts","contextualizes","silent","ambiguous","not-assessed"}:
                relation="not-assessed"
            observations.append({"primary_source_id":sid,"source_known":sid in source_ids,"relation":relation,"basis":_clean(o.get("basis"),6000) or None,"locator":_clean(o.get("locator"),1000) or None,"human_asserted":True})
        counts={k:sum(1 for o in observations if o["relation"]==k) for k in ["supports","contradicts","contextualizes","silent","ambiguous","not-assessed"]}
        claims.append({"claim_id":claim_id,"text":text,"observations":observations,"counts":counts,"adjudicated":False,"truth_status":None,"unresolved":True})
    basis={"sources":[s["primary_source_id"] for s in sources],"claims":claims}
    return {
        "schema":CORROBORATION_CONTRACT,
        "library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,
        "ledger_id":"primary-source-corroboration-ledger:"+_fp(basis)[:32],
        "ledger_fingerprint_sha256":_fp(basis),
        "source_count":len(sources),
        "claim_count":len(claims),
        "claims":claims,
        "truth_determination":None,
        "automatic_adjudication":False,
        "independence_inferred":False,
        "guardrails":guardrails(),
    }
