from app.dataset_discovery_statistical_evidence_workspace import (
    discover_datasets, dataset_profile, statistical_table, uncertainty_audit,
    gap_analysis, evidence_handoff_preview, investigation_handoff_preview,
    export_analysis, guardrails,
)

SAMPLE={
 "title":"Accelerated weathering statistical evidence",
 "research_question":"What quantitative evidence supports durable net carbon removal?",
 "claims":[{"claim_id":"c1","text":"Net carbon removal remains positive after lifecycle emissions."}],
 "query":"carbon removal lifecycle",
 "datasets":[
  {"dataset_id":"d1","title":"Enhanced weathering field trial dataset","description":"Carbon removal field measurements and lifecycle variables.","kind":"experimental","source_id":"trial-a","provider":"University consortium","uri":"https://example.invalid/d1","license":"CC-BY-4.0","access_mode":"download","geography":"Ireland","temporal_coverage":"2024-2026","population":"Field plots","unit_of_analysis":"plot","provenance_state":"complete","tags":["carbon","weathering"],"variables":[{"name":"net_removal","type":"numeric","role":"outcome","unit":"tCO2e/ha","denominator":"hectare"},{"name":"transport_emissions","type":"numeric","role":"covariate","unit":"kgCO2e"}]},
  {"dataset_id":"d2","title":"Agricultural soils archive","kind":"observational","source_id":"archive-b","access_mode":"api","geography":"Europe","provenance_state":"partial","variables":[]}
 ],
 "statistical_results":[
  {"result_id":"r1","dataset_id":"d1","analysis_id":"a1","claim_id":"c1","relationship":"supports","statistic_type":"difference","estimate":1.8,"unit":"tCO2e/ha","standard_error":0.5,"confidence_interval":{"level":0.95,"lower":0.8,"upper":2.8},"p_value":0.004,"alpha":0.05,"reference_value":0,"effect_size":{"measure":"mean-difference","value":1.8,"unit":"tCO2e/ha"},"sample_size":64,"design":"randomized-trial","outcome":"net carbon removal","exposure_or_treatment":"enhanced weathering","multiple_comparison_method":"Holm","missing_data_method":"complete-case","model_specification":"difference in means","causal_identification_claimed":True}
 ]
}

def test_guardrails():
 g=guardrails(); assert g["statistical_significance_implies_truth"] is False; assert g["correlation_implies_causation"] is False; assert g["p_value_is_probability_null_hypothesis_is_true"] is False

def test_dataset_discovery_is_metadata_matching():
 d=discover_datasets(SAMPLE); assert d["result_count"]==1; assert d["results"][0]["dataset_id"]=="d1"; assert d["results"][0]["match_score_is_quality_score"] is False

def test_dataset_profile_preserves_units_and_denominators():
 p=dataset_profile({**SAMPLE,"dataset_id":"d1"}); v=p["dataset"]["variables"][0]; assert v["unit"]=="tCO2e/ha"; assert v["denominator"]=="hectare"

def test_statistical_table_is_nonverdict():
 t=statistical_table(SAMPLE); r=t["rows"][0]; assert r["p_value_below_alpha"] is True; assert r["verdict"] is None; assert r["truth_probability"] is None; assert r["causality_inferred"] is False

def test_uncertainty_audit_records_missing_context_without_validity_judgment():
 u=uncertainty_audit(SAMPLE); assert u["automatic_validity_judgment"] is False

def test_gaps_detect_dataset_metadata_gaps():
 g=gap_analysis(SAMPLE); assert any(x["kind"]=="dataset-without-variable-dictionary" and x["dataset_id"]=="d2" for x in g["gaps"])

def test_handoffs_are_preview_only():
 e=evidence_handoff_preview(SAMPLE); assert e["preview_only"] is True; assert e["automatic_claim_support_inference"] is False
 i=investigation_handoff_preview(SAMPLE); assert i["preview_only"] is True; assert i["automatic_task_execution"] is False

def test_export_nonpersistent():
 e=export_analysis(SAMPLE); assert e["automatic_import"] is False; assert e["workspace_persisted"] is False; assert e["content"]
