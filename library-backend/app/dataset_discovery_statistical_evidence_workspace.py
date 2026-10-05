from __future__ import annotations

from hashlib import sha256
import json
import math
import re
from typing import Any

LIBRARY_VERSION = "6.23.0"
BACKEND_VERSION = "3.23.0"
WEB_VERSION = "2.23.0"
SDK_VERSION = "1.23.0"

CONTRACT = "sc-library-dataset-discovery-statistical-evidence-workspace/1.0"
READINESS_CONTRACT = "sc-library-dataset-discovery-statistical-evidence-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-dataset-discovery-statistical-evidence-bootstrap/1.0"
DISCOVERY_CONTRACT = "sc-library-dataset-discovery-results/1.0"
PROFILE_CONTRACT = "sc-library-dataset-profile/1.0"
STAT_TABLE_CONTRACT = "sc-library-statistical-evidence-table/1.0"
UNCERTAINTY_CONTRACT = "sc-library-statistical-uncertainty-audit/1.0"
GAP_CONTRACT = "sc-library-dataset-statistical-gap-analysis/1.0"
EXPORT_CONTRACT = "sc-library-dataset-statistical-export/1.0"

DATASET_KINDS = {"survey","administrative","experimental","observational","panel","time-series","cross-sectional","geospatial","remote-sensing","registry","simulation","repository","other"}
ACCESS_MODES = {"api","download","query","repository","citation-only","restricted","unknown"}
VARIABLE_TYPES = {"numeric","integer","categorical","boolean","date","datetime","string","geospatial","other"}
VARIABLE_ROLES = {"outcome","exposure","treatment","predictor","covariate","confounder","mediator","moderator","identifier","weight","stratum","cluster","time","geography","other"}
PROVENANCE_STATES = {"complete","partial","missing","unknown"}
STATISTIC_TYPES = {"mean","median","proportion","rate","difference","ratio","correlation","regression-coefficient","odds-ratio","risk-ratio","hazard-ratio","standardized-effect","test-statistic","model-fit","other"}
RELATIONSHIPS = {"supports","contradicts","qualifies","contextualizes","mixed","unknown"}
DESIGNS = {"randomized-trial","quasi-experimental","cohort","case-control","cross-sectional","panel","time-series","ecological","survey","simulation","meta-analysis","other","unspecified"}
MAX_DATASETS = 5000
MAX_VARIABLES_PER_DATASET = 5000
MAX_RESULTS = 10000


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
        "workspace_is_new_dataset_persistence_authority": False,
        "dataset_match_score_is_quality_score": False,
        "dataset_match_score_is_truth_probability": False,
        "dataset_provider_implies_endorsement": False,
        "dataset_size_implies_quality": False,
        "statistical_significance_implies_truth": False,
        "statistical_significance_implies_practical_significance": False,
        "p_value_is_probability_null_hypothesis_is_true": False,
        "p_value_is_probability_result_is_due_to_chance": False,
        "confidence_interval_is_probability_parameter_is_inside_interval": False,
        "confidence_interval_excluding_reference_implies_causality": False,
        "correlation_implies_causation": False,
        "association_implies_causation": False,
        "regression_adjustment_guarantees_causal_identification": False,
        "large_sample_implies_valid_design": False,
        "model_fit_implies_truth": False,
        "r_squared_implies_causal_explanation": False,
        "effect_size_is_context_free_evidence_strength": False,
        "multiple_comparisons_are_ignored": False,
        "missing_data_assumptions_are_ignored": False,
        "units_are_discarded": False,
        "denominators_are_discarded": False,
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
        "dataset-inventory","dataset-metadata-discovery","variable-dictionaries",
        "study-design-and-population-context","statistical-result-normalization",
        "uncertainty-and-interval-preservation","effect-size-and-unit-preservation",
        "missingness-and-multiple-comparison-context","statistical-evidence-table",
        "dataset-and-statistical-gap-analysis","evidence-matrix-handoff-preview",
        "research-investigation-handoff-preview","reproducible-analysis-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "dataset-statistical-evidence:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/data",
        "api_base": "/api/library/v1/statistical-evidence",
        "resources": resources,
        "limits": {"datasets": MAX_DATASETS, "variables_per_dataset": MAX_VARIABLES_PER_DATASET, "statistical_results": MAX_RESULTS},
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
            "structured-evidence","scientific-literature","global-knowledge-federation-ii",
            "research-investigation-v6.21","evidence-matrix-v6.22",
        ],
        "automatic_external_fetch": False,
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
        "route": "/research/data",
        "readiness": readiness(),
        "dataset_kinds": sorted(DATASET_KINDS),
        "access_modes": sorted(ACCESS_MODES),
        "variable_types": sorted(VARIABLE_TYPES),
        "variable_roles": sorted(VARIABLE_ROLES),
        "provenance_states": sorted(PROVENANCE_STATES),
        "statistic_types": sorted(STATISTIC_TYPES),
        "relationships": sorted(RELATIONSHIPS),
        "designs": sorted(DESIGNS),
        "operations": ["discover","dataset-profile","statistical-table","uncertainty-audit","gaps","evidence-handoff-preview","investigation-handoff-preview","export"],
        "browser_storage_key": "sc-library-statistical-evidence-v1",
        "guardrails": guardrails(),
    }


def _variable(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    name = _clean(r.get("name") or r.get("variable_id") or r.get("id"), 1000)
    if not name:
        raise ValueError(f"variable-{index}-name-required")
    vtype = _clean(r.get("type"), 100).lower() or "other"
    role = _clean(r.get("role"), 100).lower() or "other"
    if vtype not in VARIABLE_TYPES: vtype = "other"
    if role not in VARIABLE_ROLES: role = "other"
    return {
        "variable_id": _clean(r.get("variable_id") or r.get("id"), 1000) or name,
        "name": name,
        "label": _clean(r.get("label"), 3000) or None,
        "type": vtype,
        "role": role,
        "unit": _clean(r.get("unit"), 500) or None,
        "description": _clean(r.get("description"), 6000) or None,
        "missing_definition": _clean(r.get("missing_definition"), 3000) or None,
        "denominator": _clean(r.get("denominator"), 3000) or None,
        "categories": [_clean(x, 1000) for x in _list(r.get("categories")) if _clean(x, 1000)],
    }


def _dataset(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    did = _clean(r.get("dataset_id") or r.get("id"), 1000) or f"dataset:{index}"
    title = _clean(r.get("title") or r.get("name"), 4000)
    if not title:
        raise ValueError(f"dataset-{index}-title-required")
    kind = _clean(r.get("kind"), 100).lower() or "other"
    access = _clean(r.get("access_mode"), 100).lower() or "unknown"
    ps = _clean(r.get("provenance_state"), 100).lower() or "unknown"
    if kind not in DATASET_KINDS: kind = "other"
    if access not in ACCESS_MODES: access = "unknown"
    if ps not in PROVENANCE_STATES: ps = "unknown"
    variables_raw = _list(r.get("variables"))
    if len(variables_raw) > MAX_VARIABLES_PER_DATASET:
        raise ValueError(f"dataset-{index}-variable-limit-exceeded:{MAX_VARIABLES_PER_DATASET}")
    return {
        "dataset_id": did,
        "title": title,
        "description": _clean(r.get("description") or r.get("abstract"), 16000) or None,
        "kind": kind,
        "source_id": _clean(r.get("source_id"), 1000) or None,
        "provider": _clean(r.get("provider"), 3000) or None,
        "uri": _clean(r.get("uri") or r.get("url"), 4000) or None,
        "license": _clean(r.get("license"), 2000) or None,
        "access_mode": access,
        "geography": _clean(r.get("geography"), 3000) or None,
        "temporal_coverage": _clean(r.get("temporal_coverage"), 3000) or None,
        "population": _clean(r.get("population"), 6000) or None,
        "unit_of_analysis": _clean(r.get("unit_of_analysis"), 3000) or None,
        "update_frequency": _clean(r.get("update_frequency"), 1000) or None,
        "provenance_state": ps,
        "provenance": _dict(r.get("provenance")),
        "tags": [_clean(x, 500).lower() for x in _list(r.get("tags")) if _clean(x, 500)],
        "variables": [_variable(x, i) for i, x in enumerate(variables_raw, 1)],
    }


def normalize_datasets(payload: dict[str, Any]) -> list[dict[str, Any]]:
    rows = _list(payload.get("datasets"))
    if len(rows) > MAX_DATASETS:
        raise ValueError(f"dataset-limit-exceeded:{MAX_DATASETS}")
    return [_dataset(x, i) for i, x in enumerate(rows, 1)]


def _terms(value: Any) -> list[str]:
    return [x for x in re.findall(r"[\w\-]+", _clean(value, 12000).casefold()) if len(x) > 1]


def discover_datasets(payload: dict[str, Any]) -> dict[str, Any]:
    datasets = normalize_datasets(payload)
    query = _clean(payload.get("query"), 6000)
    qterms = sorted(set(_terms(query)))
    filters = _dict(payload.get("filters"))
    source_ids = {_clean(x, 1000) for x in _list(filters.get("source_ids")) if _clean(x, 1000)}
    kinds = {_clean(x, 100).lower() for x in _list(filters.get("kinds")) if _clean(x, 100)}
    tags = {_clean(x, 500).lower() for x in _list(filters.get("tags")) if _clean(x, 500)}
    geography = _clean(filters.get("geography"), 3000).casefold()
    variable_terms = {_clean(x, 1000).casefold() for x in _list(filters.get("variable_terms")) if _clean(x, 1000)}
    results=[]
    for d in datasets:
        if source_ids and d["source_id"] not in source_ids: continue
        if kinds and d["kind"] not in kinds: continue
        if tags and not tags.intersection(set(d["tags"])): continue
        if geography and geography not in (d.get("geography") or "").casefold(): continue
        variable_text = " ".join((v["name"]+" "+(v.get("label") or "")+" "+(v.get("description") or "")) for v in d["variables"]).casefold()
        if variable_terms and not all(t in variable_text for t in variable_terms): continue
        haystack = " ".join([d["title"], d.get("description") or "", d.get("provider") or "", d.get("geography") or "", " ".join(d["tags"]), variable_text]).casefold()
        matched=[t for t in qterms if t in haystack]
        score = len(matched) / max(1, len(qterms)) if qterms else 1.0
        if qterms and not matched: continue
        results.append({
            "dataset_id": d["dataset_id"], "title": d["title"], "kind": d["kind"],
            "source_id": d["source_id"], "provider": d["provider"], "access_mode": d["access_mode"],
            "geography": d["geography"], "temporal_coverage": d["temporal_coverage"],
            "variable_count": len(d["variables"]), "matched_query_terms": matched,
            "metadata_match_score": round(score, 6), "match_score_is_quality_score": False,
            "match_score_is_truth_probability": False,
        })
    results.sort(key=lambda x: (-x["metadata_match_score"], x["title"].casefold(), x["dataset_id"]))
    basis={"query":query,"filters":filters,"results":results}
    return {
        "schema": DISCOVERY_CONTRACT,
        "discovery_id": "dataset-discovery:" + _fp(basis)[:32],
        "discovery_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION, "backend_version": BACKEND_VERSION,
        "query": query, "filters": filters, "result_count": len(results), "results": results,
        "automatic_external_fetch": False, "ranking_is_metadata_match_only": True,
        "guardrails": guardrails(),
    }


def dataset_profile(payload: dict[str, Any]) -> dict[str, Any]:
    datasets=normalize_datasets(payload)
    did=_clean(payload.get("dataset_id"),1000)
    if not did and len(datasets)==1: did=datasets[0]["dataset_id"]
    item=next((x for x in datasets if x["dataset_id"]==did),None)
    if item is None: raise ValueError("dataset-not-found")
    role_counts={r:sum(1 for v in item["variables"] if v["role"]==r) for r in sorted(VARIABLE_ROLES)}
    basis={"dataset":item,"role_counts":role_counts}
    return {
        "schema": PROFILE_CONTRACT,
        "profile_id": "dataset-profile:"+_fp(basis)[:32],
        "profile_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION, "backend_version": BACKEND_VERSION,
        "dataset": item, "variable_role_counts": role_counts,
        "variable_count": len(item["variables"]),
        "dataset_size_implies_quality": False, "guardrails": guardrails(),
    }


def _result(raw: Any, index: int) -> dict[str, Any]:
    r=_dict(raw)
    rid=_clean(r.get("result_id") or r.get("id"),1000) or f"stat-result:{index}"
    st=_clean(r.get("statistic_type"),100).lower() or "other"
    design=_clean(r.get("design"),100).lower() or "unspecified"
    relationship=_clean(r.get("relationship"),100).lower() or "unknown"
    if st not in STATISTIC_TYPES: st="other"
    if design not in DESIGNS: design="other"
    if relationship not in RELATIONSHIPS: relationship="unknown"
    ci=_dict(r.get("confidence_interval")); effect=_dict(r.get("effect_size"))
    alpha=_num(r.get("alpha")); pvalue=_num(r.get("p_value"))
    lower=_num(ci.get("lower")); upper=_num(ci.get("upper")); level=_num(ci.get("level"))
    reference=_num(r.get("reference_value")); estimate=_num(r.get("estimate"))
    interval_excludes_reference=None
    if lower is not None and upper is not None and reference is not None:
        interval_excludes_reference = not (lower <= reference <= upper)
    return {
        "result_id":rid,"dataset_id":_clean(r.get("dataset_id"),1000) or None,
        "analysis_id":_clean(r.get("analysis_id"),1000) or None,
        "claim_id":_clean(r.get("claim_id"),1000) or None,
        "relationship":relationship,"statistic_type":st,"estimate":estimate,
        "unit":_clean(r.get("unit"),500) or None,"standard_error":_num(r.get("standard_error")),
        "confidence_interval":{"level":level,"lower":lower,"upper":upper},
        "p_value":pvalue,"alpha":alpha,
        "p_value_below_alpha": (pvalue < alpha) if pvalue is not None and alpha is not None else None,
        "reference_value":reference,"interval_excludes_reference":interval_excludes_reference,
        "effect_size":{"measure":_clean(effect.get("measure"),500) or None,"value":_num(effect.get("value")),"unit":_clean(effect.get("unit"),500) or None},
        "sample_size": int(r["sample_size"]) if str(r.get("sample_size") or "").isdigit() else None,
        "degrees_of_freedom":_num(r.get("degrees_of_freedom")),
        "design":design,"population":_clean(r.get("population"),6000) or None,
        "outcome":_clean(r.get("outcome"),3000) or None,"exposure_or_treatment":_clean(r.get("exposure_or_treatment"),3000) or None,
        "model_specification":_clean(r.get("model_specification"),12000) or None,
        "covariates":[_clean(x,1000) for x in _list(r.get("covariates")) if _clean(x,1000)],
        "multiple_comparison_method":_clean(r.get("multiple_comparison_method"),2000) or None,
        "missing_data_method":_clean(r.get("missing_data_method"),3000) or None,
        "causal_identification_claimed": bool(r.get("causal_identification_claimed",False)),
        "notes":_clean(r.get("notes"),12000) or None,
        "verdict":None,"truth_probability":None,"causality_inferred":False,
    }


def normalize_results(payload: dict[str, Any]) -> list[dict[str, Any]]:
    rows=_list(payload.get("statistical_results"))
    if len(rows)>MAX_RESULTS: raise ValueError(f"statistical-result-limit-exceeded:{MAX_RESULTS}")
    return [_result(x,i) for i,x in enumerate(rows,1)]


def statistical_table(payload: dict[str, Any]) -> dict[str, Any]:
    rows=normalize_results(payload)
    basis={"rows":rows}
    return {
        "schema": STAT_TABLE_CONTRACT,
        "table_id":"statistical-evidence-table:"+_fp(basis)[:32],
        "table_fingerprint_sha256":_fp(basis),
        "library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
        "rows":rows,"result_count":len(rows),"automatic_claim_support_inference":False,
        "guardrails":guardrails(),
    }


def uncertainty_audit(payload: dict[str, Any]) -> dict[str, Any]:
    rows=normalize_results(payload); findings=[]
    for r in rows:
        rid=r["result_id"]
        if r["standard_error"] is None and all(r["confidence_interval"].get(k) is None for k in ("lower","upper")):
            findings.append({"result_id":rid,"kind":"uncertainty-not-reported"})
        if r["sample_size"] is None:
            findings.append({"result_id":rid,"kind":"sample-size-not-reported"})
        if r["p_value"] is not None and r["multiple_comparison_method"] is None:
            findings.append({"result_id":rid,"kind":"multiple-comparison-context-not-recorded"})
        if r["missing_data_method"] is None:
            findings.append({"result_id":rid,"kind":"missing-data-method-not-recorded"})
        if r["design"] == "unspecified":
            findings.append({"result_id":rid,"kind":"study-design-unspecified"})
        if r["causal_identification_claimed"] and r["design"] not in {"randomized-trial","quasi-experimental"}:
            findings.append({"result_id":rid,"kind":"causal-identification-claim-requires-design-review"})
    basis={"rows":rows,"findings":findings}
    return {
        "schema":UNCERTAINTY_CONTRACT,
        "audit_id":"statistical-uncertainty-audit:"+_fp(basis)[:32],
        "audit_fingerprint_sha256":_fp(basis),
        "library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
        "findings":findings,"finding_count":len(findings),
        "automatic_validity_judgment":False,"guardrails":guardrails(),
    }


def gap_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    datasets=normalize_datasets(payload); results=normalize_results(payload); gaps=[]
    dataset_ids={d["dataset_id"] for d in datasets}
    for d in datasets:
        if not d["variables"]: gaps.append({"kind":"dataset-without-variable-dictionary","dataset_id":d["dataset_id"]})
        if d["provenance_state"] in {"missing","unknown"}: gaps.append({"kind":"dataset-provenance-incomplete","dataset_id":d["dataset_id"]})
        if not d["license"]: gaps.append({"kind":"dataset-license-not-recorded","dataset_id":d["dataset_id"]})
        if not d["uri"] and d["access_mode"] not in {"restricted","citation-only"}: gaps.append({"kind":"dataset-access-location-not-recorded","dataset_id":d["dataset_id"]})
    for r in results:
        if r["dataset_id"] and r["dataset_id"] not in dataset_ids: gaps.append({"kind":"statistical-result-dataset-not-in-inventory","result_id":r["result_id"],"dataset_id":r["dataset_id"]})
        if not r["claim_id"]: gaps.append({"kind":"statistical-result-not-linked-to-claim","result_id":r["result_id"]})
    gaps.extend(uncertainty_audit(payload)["findings"])
    basis={"datasets":datasets,"results":results,"gaps":gaps}
    return {
        "schema":GAP_CONTRACT,"gap_analysis_id":"dataset-statistical-gaps:"+_fp(basis)[:32],
        "gap_analysis_fingerprint_sha256":_fp(basis),"library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,"gaps":gaps,"gap_count":len(gaps),
        "missing_evidence_is_inferred":False,"guardrails":guardrails(),
    }


def evidence_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    datasets={d["dataset_id"]:d for d in normalize_datasets(payload)}; results=normalize_results(payload)
    evidence=[]; links=[]
    for r in results:
        d=datasets.get(r["dataset_id"])
        eid="statistical-result:"+r["result_id"]
        title=(d["title"]+" — " if d else "") + r["statistic_type"]
        evidence.append({
            "evidence_id":eid,"title":title,"kind":"dataset","source_id":d.get("source_id") if d else None,
            "independence_group":r["analysis_id"] or r["dataset_id"] or eid,
            "dataset_id":r["dataset_id"],"uri":d.get("uri") if d else None,
            "provenance_state":d.get("provenance_state") if d else "unknown",
            "provenance":{"statistical_result":r,"dataset_provenance":d.get("provenance") if d else {}},
            "notes":r["notes"],
        })
        if r["claim_id"] and r["relationship"] != "unknown":
            links.append({"claim_id":r["claim_id"],"evidence_id":eid,"relationship":r["relationship"],"directness":"direct" if r["design"] in {"randomized-trial","quasi-experimental"} else "indirect","rationale":"Explicit relationship supplied with statistical result."})
    out={"title":_clean(payload.get("title"),2000) or "Statistical evidence handoff","research_question":_clean(payload.get("research_question"),16000) or None,"scope":_clean(payload.get("scope"),16000) or None,"claims":_list(payload.get("claims")),"evidence":evidence,"links":links}
    return {
        "schema":"sc-library-statistical-evidence-matrix-handoff-preview/1.0",
        "handoff_id":"statistical-to-evidence:"+_fp(out)[:32],"library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,"endpoint":"/api/library/v1/evidence-matrix/matrix",
        "payload":out,"preview_only":True,"automatic_submission":False,
        "automatic_claim_support_inference":False,"automatic_persistence":False,
        "guardrails":guardrails(),
    }


def investigation_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    gaps=gap_analysis(payload)["gaps"]; needs=[]; tasks=[]
    for i,g in enumerate(gaps,1):
        gid=f"stat-gap:{i}"; desc=g["kind"]
        needs.append({"evidence_need_id":gid,"description":desc,"kind":"dataset","priority":"unspecified"})
        tasks.append({"task_id":f"task:{i}","description":f"Resolve statistical evidence gap: {desc}","task_type":"review","evidence_need_ids":[gid],"priority":"unspecified"})
    out={"title":(_clean(payload.get("title"),2000) or "Statistical evidence")+" — investigation gaps","research_question":_clean(payload.get("research_question"),16000) or "What quantitative evidence gaps remain unresolved?","scope":_clean(payload.get("scope"),16000) or None,"subquestions":[],"hypotheses":[],"evidence_needs":needs,"tasks":tasks,"decision_points":[],"stop_conditions":[],"risks":[],"source_strategy":{}}
    return {
        "schema":"sc-library-statistical-evidence-investigation-handoff-preview/1.0",
        "handoff_id":"statistical-to-investigation:"+_fp(out)[:32],"library_version":LIBRARY_VERSION,
        "backend_version":BACKEND_VERSION,"endpoint":"/api/library/v1/research-investigation/build",
        "payload":out,"preview_only":True,"automatic_submission":False,
        "automatic_task_execution":False,"automatic_persistence":False,"guardrails":guardrails(),
    }


def export_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    body={
        "schema":EXPORT_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
        "web_version":WEB_VERSION,"sdk_version":SDK_VERSION,
        "dataset_discovery":discover_datasets(payload),
        "datasets":normalize_datasets(payload),
        "statistical_table":statistical_table(payload),
        "uncertainty_audit":uncertainty_audit(payload),
        "gaps":gap_analysis(payload),
        "automatic_import":False,"workspace_persisted":False,"guardrails":guardrails(),
    }
    basis={"datasets":[x["dataset_id"] for x in body["datasets"]],"statistical_table":body["statistical_table"]["table_fingerprint_sha256"]}
    body["export_id"]="dataset-statistical-export:"+_fp(basis)[:32]
    body["export_fingerprint_sha256"]=_fp(basis)
    return {**body,"filename":"sustainable-catalyst-dataset-statistical-evidence.json","media_type":"application/json","content":json.dumps(body,ensure_ascii=False,sort_keys=True,indent=2)}
