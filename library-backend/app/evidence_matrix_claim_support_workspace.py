from __future__ import annotations
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION="6.22.0"
BACKEND_VERSION="3.22.0"
WEB_VERSION="2.22.0"
SDK_VERSION="1.22.0"

CONTRACT="sc-library-evidence-matrix-claim-support-workspace/1.0"
READINESS_CONTRACT="sc-library-evidence-matrix-claim-support-readiness/1.0"
BOOTSTRAP_CONTRACT="sc-library-evidence-matrix-claim-support-bootstrap/1.0"
MATRIX_CONTRACT="sc-library-claim-evidence-matrix/1.0"
PROFILE_CONTRACT="sc-library-claim-support-profile/1.0"
CONTRADICTION_CONTRACT="sc-library-claim-contradiction-analysis/1.0"
PROVENANCE_CONTRACT="sc-library-evidence-provenance-coverage/1.0"
DEPENDENCY_CONTRACT="sc-library-evidence-source-dependency-analysis/1.0"
GAP_CONTRACT="sc-library-claim-evidence-gap-analysis/1.0"
EXPORT_CONTRACT="sc-library-evidence-matrix-export/1.0"

RELATIONSHIPS={"supports","contradicts","qualifies","contextualizes","mixed","irrelevant","unknown"}
DIRECTNESS={"direct","indirect","contextual","unknown"}
PROVENANCE_STATES={"complete","partial","missing","unknown"}
EVIDENCE_KINDS={"primary-source","publication","dataset","citation","annotation","timeline","corpus","entity-resolution","measurement","model-output","expert-source","institutional-source","other"}
MAX_CLAIMS=1000
MAX_EVIDENCE=5000
MAX_LINKS=20000

def _canon(v:Any)->str:
    return json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(",",":"),default=str)
def _fp(v:Any)->str:
    return sha256(_canon(v).encode()).hexdigest()
def _dict(v:Any)->dict[str,Any]:
    return dict(v) if isinstance(v,dict) else {}
def _list(v:Any)->list[Any]:
    return list(v) if isinstance(v,(list,tuple)) else []
def _clean(v:Any,n:int=12000)->str:
    return " ".join(str(v or "").split())[:n]

def guardrails()->dict[str,bool]:
    return {
        "workspace_is_analysis_composition_layer":True,
        "workspace_is_new_truth_authority":False,
        "workspace_is_new_evidence_persistence_authority":False,
        "relationship_is_automatically_inferred":False,
        "support_count_is_truth_probability":False,
        "contradiction_count_is_falsity_probability":False,
        "independent_source_count_is_confidence_score":False,
        "source_count_is_evidence_strength_score":False,
        "directness_is_quality_score":False,
        "provenance_completeness_is_truth_score":False,
        "matrix_cell_is_verdict":False,
        "claim_profile_is_verdict":False,
        "mixed_evidence_is_collapsed":False,
        "contradictions_are_automatically_resolved":False,
        "automatic_source_independence_assumption":False,
        "automatic_claim_acceptance":False,
        "automatic_claim_rejection":False,
        "automatic_evidence_promotion":False,
        "automatic_truth_promotion":False,
        "automatic_platform_core_promotion":False,
        "server_side_matrix_persistence":False,
        "database_migration_required":False,
        "wordpress_required":False,
    }

def contract()->dict[str,Any]:
    resources=[
        "claim-inventory","evidence-inventory","explicit-claim-evidence-links",
        "claim-evidence-matrix","descriptive-support-profiles",
        "contradiction-and-qualification-analysis","provenance-coverage",
        "source-dependency-analysis","independent-source-grouping",
        "claim-evidence-gap-analysis","reproducible-analysis-export",
        "research-synthesis-handoff-preview","research-investigation-gap-handoff-preview",
    ]
    basis={"resources":resources,"guardrails":guardrails()}
    return {
        "schema":CONTRACT,"workspace_id":"evidence-matrix:"+_fp(basis)[:32],
        "workspace_fingerprint_sha256":_fp(basis),
        "library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
        "web_version":WEB_VERSION,"sdk_version":SDK_VERSION,
        "state":"authoritative-composition","authority":"python-backend-composition",
        "route":"/research/evidence","api_base":"/api/library/v1/evidence-matrix",
        "resources":resources,
        "limits":{"claims":MAX_CLAIMS,"evidence_items":MAX_EVIDENCE,"links":MAX_LINKS},
        "guardrails":guardrails(),
    }

def readiness()->dict[str,Any]:
    return {
        "schema":READINESS_CONTRACT,"library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,"web_version":WEB_VERSION,"sdk_version":SDK_VERSION,
        "state":"ready","ready":True,"blocking":[],"degraded":[],
        "authority":"python-backend-composition",
        "composes_existing_authorities":[
            "python-provenance-citation-evidence-graph","structured-evidence",
            "research-synthesis-v6.20","research-investigation-v6.21",
            "research-annotation-scholarly-notes","citation-bibliographic-workspace",
        ],
        "server_side_matrix_persistence":False,
        "database_migration_required":False,"wordpress_required":False,
        "guardrails":guardrails(),
    }

def bootstrap()->dict[str,Any]:
    return {
        "schema":BOOTSTRAP_CONTRACT,"library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,"web_version":WEB_VERSION,"sdk_version":SDK_VERSION,
        "route":"/research/evidence","readiness":readiness(),
        "relationships":sorted(RELATIONSHIPS),"directness":sorted(DIRECTNESS),
        "evidence_kinds":sorted(EVIDENCE_KINDS),"provenance_states":sorted(PROVENANCE_STATES),
        "operations":["matrix","support-profiles","contradictions","provenance-coverage","source-dependencies","gaps","export","synthesis-handoff-preview","investigation-handoff-preview"],
        "browser_storage_key":"sc-library-evidence-matrix-v1","guardrails":guardrails(),
    }

def _claim(raw:Any,i:int)->dict[str,Any]:
    r=_dict(raw); text=_clean(r.get("text") or r.get("claim"),20000)
    if not text: raise ValueError(f"claim-{i}-text-required")
    return {"claim_id":_clean(r.get("claim_id") or r.get("id"),1000) or f"claim:{i}",
            "text":text,"label":_clean(r.get("label"),2000) or None,
            "scope":_clean(r.get("scope"),6000) or None,
            "status":_clean(r.get("status"),100).lower() or "open",
            "notes":_clean(r.get("notes"),12000) or None}

def _evidence(raw:Any,i:int)->dict[str,Any]:
    r=_dict(raw); eid=_clean(r.get("evidence_id") or r.get("id"),1000) or f"evidence:{i}"
    kind=_clean(r.get("kind") or r.get("evidence_kind"),100).lower() or "other"
    if kind not in EVIDENCE_KINDS: kind="other"
    ps=_clean(r.get("provenance_state"),100).lower() or "unknown"
    if ps not in PROVENANCE_STATES: ps="unknown"
    source_id=_clean(r.get("source_id"),1000) or None
    return {
        "evidence_id":eid,"title":_clean(r.get("title") or r.get("label"),4000) or eid,
        "kind":kind,"source_id":source_id,
        "independence_group":_clean(r.get("independence_group"),1000) or source_id or eid,
        "record_id":_clean(r.get("record_id"),1000) or None,
        "citation_id":_clean(r.get("citation_id"),1000) or None,
        "annotation_id":_clean(r.get("annotation_id"),1000) or None,
        "dataset_id":_clean(r.get("dataset_id"),1000) or None,
        "uri":_clean(r.get("uri") or r.get("url"),4000) or None,
        "provenance_state":ps,"provenance":_dict(r.get("provenance")),
        "method":_clean(r.get("method"),6000) or None,
        "population":_clean(r.get("population"),4000) or None,
        "geography":_clean(r.get("geography"),2000) or None,
        "time_scope":_clean(r.get("time_scope"),2000) or None,
        "notes":_clean(r.get("notes"),12000) or None,
    }

def _link(raw:Any,i:int)->dict[str,Any]:
    r=_dict(raw); cid=_clean(r.get("claim_id"),1000); eid=_clean(r.get("evidence_id"),1000)
    if not cid or not eid: raise ValueError(f"link-{i}-requires-claim_id-and-evidence_id")
    rel=_clean(r.get("relationship") or r.get("stance"),100).lower() or "unknown"
    if rel not in RELATIONSHIPS: rel="unknown"
    direct=_clean(r.get("directness"),100).lower() or "unknown"
    if direct not in DIRECTNESS: direct="unknown"
    return {
        "link_id":_clean(r.get("link_id") or r.get("id"),1000) or f"link:{i}",
        "claim_id":cid,"evidence_id":eid,"relationship":rel,"directness":direct,
        "rationale":_clean(r.get("rationale"),16000) or None,
        "locator":_dict(r.get("locator")),
        "conditions":[_clean(x,4000) for x in _list(r.get("conditions")) if _clean(x,4000)],
        "asserted_by":_clean(r.get("asserted_by"),1000) or None,
        "asserted_at":_clean(r.get("asserted_at"),200) or None,
        "human_asserted":True,
    }

def normalize_workspace(payload:dict[str,Any])->dict[str,Any]:
    if not isinstance(payload,dict): raise ValueError("payload-must-be-object")
    cr=_list(payload.get("claims")); er=_list(payload.get("evidence")); lr=_list(payload.get("links"))
    if len(cr)>MAX_CLAIMS: raise ValueError(f"claim-limit-exceeded:{MAX_CLAIMS}")
    if len(er)>MAX_EVIDENCE: raise ValueError(f"evidence-item-limit-exceeded:{MAX_EVIDENCE}")
    if len(lr)>MAX_LINKS: raise ValueError(f"link-limit-exceeded:{MAX_LINKS}")
    claims=[_claim(x,i) for i,x in enumerate(cr,1)]
    evidence=[_evidence(x,i) for i,x in enumerate(er,1)]
    links=[_link(x,i) for i,x in enumerate(lr,1)]
    cids={x["claim_id"] for x in claims}; eids={x["evidence_id"] for x in evidence}
    dangling=[x["link_id"] for x in links if x["claim_id"] not in cids or x["evidence_id"] not in eids]
    basis={"title":_clean(payload.get("title"),2000),"research_question":_clean(payload.get("research_question"),16000),"claims":claims,"evidence":evidence,"links":links}
    return {
        "schema":"sc-library-evidence-matrix-input/1.0",
        "workspace_input_id":"evidence-matrix-input:"+_fp(basis)[:32],
        "workspace_input_fingerprint_sha256":_fp(basis),
        "title":basis["title"] or "Evidence matrix",
        "research_question":basis["research_question"] or None,
        "scope":_clean(payload.get("scope"),16000) or None,
        "claims":claims,"evidence":evidence,"links":links,
        "dangling_link_ids":dangling,"metadata":_dict(payload.get("metadata")),
    }

def claim_evidence_matrix(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload); evidence={x["evidence_id"]:x for x in w["evidence"]}
    by_claim={}
    for link in w["links"]: by_claim.setdefault(link["claim_id"],[]).append(link)
    rows=[]
    for claim in w["claims"]:
        cells=[]
        for link in by_claim.get(claim["claim_id"],[]):
            item=evidence.get(link["evidence_id"])
            cells.append({
                "evidence_id":link["evidence_id"],"evidence_title":item.get("title") if item else None,
                "evidence_kind":item.get("kind") if item else None,"source_id":item.get("source_id") if item else None,
                "independence_group":item.get("independence_group") if item else None,
                "provenance_state":item.get("provenance_state") if item else None,
                "relationship":link["relationship"],"directness":link["directness"],
                "rationale":link["rationale"],"locator":link["locator"],"conditions":link["conditions"],
                "human_asserted":True,
            })
        counts={r:sum(1 for x in cells if x["relationship"]==r) for r in sorted(RELATIONSHIPS)}
        dcounts={d:sum(1 for x in cells if x["directness"]==d) for d in sorted(DIRECTNESS)}
        groups=sorted({x["independence_group"] for x in cells if x.get("independence_group")})
        rows.append({"claim_id":claim["claim_id"],"claim_text":claim["text"],"claim_status":claim["status"],
                     "cells":cells,"relationship_counts":counts,"directness_counts":dcounts,
                     "independent_source_group_count":len(groups),"independent_source_groups":groups,
                     "cell_count":len(cells),"verdict":None})
    basis={"input":w["workspace_input_fingerprint_sha256"],"rows":rows}
    return {"schema":MATRIX_CONTRACT,"matrix_id":"claim-evidence-matrix:"+_fp(basis)[:32],
            "matrix_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"rows":rows,"claim_count":len(rows),
            "evidence_count":len(w["evidence"]),"link_count":len(w["links"]),
            "dangling_link_ids":w["dangling_link_ids"],"guardrails":guardrails()}

def support_profiles(payload:dict[str,Any])->dict[str,Any]:
    matrix=claim_evidence_matrix(payload); profiles=[]
    for row in matrix["rows"]:
        c=row["relationship_counts"]
        if c["supports"] and c["contradicts"]: pattern="mixed-support-and-contradiction"
        elif c["supports"] and c["qualifies"]: pattern="support-with-qualifications"
        elif c["supports"]: pattern="support-only"
        elif c["contradicts"]: pattern="contradiction-only"
        elif c["qualifies"]: pattern="qualification-only"
        elif c["contextualizes"]: pattern="context-only"
        elif c["mixed"]: pattern="explicit-mixed"
        elif row["cell_count"]: pattern="neutral-or-unknown"
        else: pattern="no-linked-evidence"
        profiles.append({
            "claim_id":row["claim_id"],"claim_text":row["claim_text"],"pattern":pattern,
            "relationship_counts":row["relationship_counts"],"directness_counts":row["directness_counts"],
            "independent_source_group_count":row["independent_source_group_count"],
            "complete_provenance_cell_count":sum(1 for x in row["cells"] if x.get("provenance_state")=="complete"),
            "confidence_score":None,"truth_probability":None,"verdict":None,
        })
    basis={"matrix":matrix["matrix_fingerprint_sha256"],"profiles":profiles}
    return {"schema":PROFILE_CONTRACT,"profile_set_id":"claim-support-profiles:"+_fp(basis)[:32],
            "profile_set_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"profiles":profiles,"counts_are_descriptive_only":True,
            "guardrails":guardrails()}

def contradiction_analysis(payload:dict[str,Any])->dict[str,Any]:
    matrix=claim_evidence_matrix(payload); items=[]
    for row in matrix["rows"]:
        support=[x for x in row["cells"] if x["relationship"]=="supports"]
        contradict=[x for x in row["cells"] if x["relationship"]=="contradicts"]
        qualify=[x for x in row["cells"] if x["relationship"]=="qualifies"]
        mixed=[x for x in row["cells"] if x["relationship"]=="mixed"]
        if (support and contradict) or qualify or mixed:
            items.append({"claim_id":row["claim_id"],"claim_text":row["claim_text"],
                          "supporting_evidence_ids":[x["evidence_id"] for x in support],
                          "contradicting_evidence_ids":[x["evidence_id"] for x in contradict],
                          "qualifying_evidence_ids":[x["evidence_id"] for x in qualify],
                          "mixed_evidence_ids":[x["evidence_id"] for x in mixed],
                          "automatic_resolution":False,"resolved":False,"verdict":None})
    basis={"matrix":matrix["matrix_fingerprint_sha256"],"items":items}
    return {"schema":CONTRADICTION_CONTRACT,"analysis_id":"claim-contradictions:"+_fp(basis)[:32],
            "analysis_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"items":items,"count":len(items),
            "automatic_resolution":False,"guardrails":guardrails()}

def provenance_coverage(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload); rows=[]
    for item in w["evidence"]:
        refs=[item.get("record_id"),item.get("citation_id"),item.get("annotation_id"),item.get("dataset_id"),item.get("uri")]
        rows.append({"evidence_id":item["evidence_id"],"title":item["title"],
                     "provenance_state":item["provenance_state"],
                     "has_authority_reference":any(bool(x) for x in refs),
                     "has_provenance_payload":bool(item["provenance"]),
                     "source_id":item["source_id"],"independence_group":item["independence_group"]})
    counts={s:sum(1 for x in rows if x["provenance_state"]==s) for s in sorted(PROVENANCE_STATES)}
    basis={"input":w["workspace_input_fingerprint_sha256"],"rows":rows}
    return {"schema":PROVENANCE_CONTRACT,"coverage_id":"evidence-provenance:"+_fp(basis)[:32],
            "coverage_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"rows":rows,"provenance_state_counts":counts,
            "provenance_completeness_is_truth_score":False,"guardrails":guardrails()}

def source_dependencies(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload); groups={}
    for item in w["evidence"]: groups.setdefault(item["independence_group"],[]).append(item)
    rows=[]
    for gid in sorted(groups):
        items=groups[gid]
        rows.append({"independence_group":gid,"evidence_ids":[x["evidence_id"] for x in items],
                     "source_ids":sorted({x["source_id"] for x in items if x.get("source_id")}),
                     "item_count":len(items),"potential_non_independence":len(items)>1,
                     "independence_assumed":False})
    basis={"input":w["workspace_input_fingerprint_sha256"],"rows":rows}
    return {"schema":DEPENDENCY_CONTRACT,"dependency_analysis_id":"evidence-dependencies:"+_fp(basis)[:32],
            "dependency_analysis_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"rows":rows,"group_count":len(rows),
            "automatic_source_independence_assumption":False,"guardrails":guardrails()}

def gap_analysis(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload); matrix=claim_evidence_matrix(payload)
    linked={x["evidence_id"] for row in matrix["rows"] for x in row["cells"]}; gaps=[]
    for row in matrix["rows"]:
        if not row["cells"]:
            gaps.append({"kind":"claim-without-evidence","claim_id":row["claim_id"],"text":row["claim_text"]}); continue
        if all(x["relationship"] in {"unknown","irrelevant","contextualizes"} for x in row["cells"]):
            gaps.append({"kind":"claim-without-directional-evidence","claim_id":row["claim_id"],"text":row["claim_text"]})
        if all(x["directness"]!="direct" for x in row["cells"]):
            gaps.append({"kind":"claim-without-direct-evidence","claim_id":row["claim_id"],"text":row["claim_text"]})
        if all(x.get("provenance_state") in {"missing","unknown",None} for x in row["cells"]):
            gaps.append({"kind":"claim-without-provenance-complete-evidence","claim_id":row["claim_id"],"text":row["claim_text"]})
    for item in w["evidence"]:
        if item["evidence_id"] not in linked:
            gaps.append({"kind":"unlinked-evidence","evidence_id":item["evidence_id"],"title":item["title"]})
    for lid in w["dangling_link_ids"]: gaps.append({"kind":"dangling-link","link_id":lid})
    basis={"input":w["workspace_input_fingerprint_sha256"],"gaps":gaps}
    return {"schema":GAP_CONTRACT,"gap_analysis_id":"claim-evidence-gaps:"+_fp(basis)[:32],
            "gap_analysis_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
            "backend_version":BACKEND_VERSION,"gaps":gaps,"gap_count":len(gaps),
            "missing_evidence_is_inferred":False,"guardrails":guardrails()}

def synthesis_handoff_preview(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload)
    stance_map={"supports":"supports","contradicts":"contradicts","qualifies":"qualifies","contextualizes":"contextualizes","mixed":"unknown","irrelevant":"unknown","unknown":"unknown"}
    out={
        "title":w["title"],"research_question":w["research_question"],"scope":w["scope"],
        "claims":w["claims"],
        "sources":[{"source_id":x["evidence_id"],"kind":x["kind"] if x["kind"] in {"primary-source","publication","dataset","citation","annotation","expert-source","institutional-source","other"} else "other",
                    "title":x["title"],"record_id":x["record_id"],"citation_id":x["citation_id"],
                    "annotation_id":x["annotation_id"],"uri":x["uri"],"provenance":x["provenance"],"notes":x["notes"]} for x in w["evidence"]],
        "relationships":[{"claim_id":x["claim_id"],"source_id":x["evidence_id"],"stance":stance_map[x["relationship"]],
                          "rationale":x["rationale"],"locator":x["locator"],"asserted_by":x["asserted_by"],"asserted_at":x["asserted_at"]} for x in w["links"]],
        "unresolved_questions":[{"question_id":f"gap:{i}","text":g.get("text") or g.get("title") or g["kind"]} for i,g in enumerate(gap_analysis(payload)["gaps"],1)],
    }
    return {"schema":"sc-library-evidence-matrix-synthesis-handoff-preview/1.0",
            "handoff_id":"evidence-to-synthesis:"+_fp(out)[:32],
            "library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
            "endpoint":"/api/library/v1/research-synthesis/synthesize","payload":out,
            "preview_only":True,"automatic_submission":False,"automatic_persistence":False,
            "guardrails":guardrails()}

def investigation_handoff_preview(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload); gaps=gap_analysis(payload)["gaps"]
    needs=[]; tasks=[]
    for i,g in enumerate(gaps,1):
        gid=f"gap:{i}"; desc=g.get("text") or g.get("title") or g["kind"]
        needs.append({"evidence_need_id":gid,"description":f"{g['kind']}: {desc}","kind":"other","priority":"unspecified"})
        tasks.append({"task_id":f"task:{i}","description":f"Investigate evidence gap: {desc}",
                      "task_type":"review","evidence_need_ids":[gid],"priority":"unspecified"})
    out={"title":f"{w['title']} — evidence gaps",
         "research_question":w["research_question"] or "What evidence gaps remain unresolved?",
         "scope":w["scope"],"subquestions":[],"hypotheses":[],"evidence_needs":needs,"tasks":tasks,
         "decision_points":[],"stop_conditions":[],"risks":[],"source_strategy":{}}
    return {"schema":"sc-library-evidence-matrix-investigation-handoff-preview/1.0",
            "handoff_id":"evidence-to-investigation:"+_fp(out)[:32],
            "library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
            "endpoint":"/api/library/v1/research-investigation/build","payload":out,
            "preview_only":True,"automatic_submission":False,"automatic_task_execution":False,
            "automatic_persistence":False,"guardrails":guardrails()}

def export_analysis(payload:dict[str,Any])->dict[str,Any]:
    w=normalize_workspace(payload)
    body={"schema":EXPORT_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
          "web_version":WEB_VERSION,"sdk_version":SDK_VERSION,"workspace":w,
          "matrix":claim_evidence_matrix(payload),"support_profiles":support_profiles(payload),
          "contradictions":contradiction_analysis(payload),"provenance_coverage":provenance_coverage(payload),
          "source_dependencies":source_dependencies(payload),"gaps":gap_analysis(payload),
          "automatic_import":False,"workspace_persisted":False,"guardrails":guardrails()}
    basis={"input":w["workspace_input_fingerprint_sha256"],"matrix":body["matrix"]["matrix_fingerprint_sha256"]}
    body["export_id"]="evidence-matrix-export:"+_fp(basis)[:32]
    body["export_fingerprint_sha256"]=_fp(basis)
    return {**body,"filename":"sustainable-catalyst-evidence-matrix.json","media_type":"application/json",
            "content":json.dumps(body,ensure_ascii=False,sort_keys=True,indent=2)}
