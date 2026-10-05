from app.geospatial_place_research_workspace import (
    coverage_audit, evidence_handoff_preview, export_workspace, guardrails,
    investigation_handoff_preview, inventory, relation_preview, temporal_validity,
)

SAMPLE = {
    "title": "Regional infrastructure study",
    "research_question": "How do infrastructure assets overlap flood-hazard areas?",
    "claims": [{"claim_id":"c1","text":"Some infrastructure assets are located within mapped flood-hazard areas."}],
    "claim_id": "c1",
    "places": [
        {"place_id":"p1","name":"Study Area","bbox":[-91.0,38.0,-90.0,39.0],
         "crs":{"authority":"EPSG","code":"4326"},"provenance_state":"complete",
         "valid_from":"2020","temporal_state":"current"},
        {"place_id":"p2","name":"Facility A","point":[-90.5,38.5],
         "crs":{"authority":"EPSG","code":"4326"},"provenance_state":"complete"},
    ],
    "layers": [
        {"layer_id":"l1","title":"Flood hazard","kind":"hazard",
         "bbox":[-90.8,38.2,-90.2,38.8],"crs":{"authority":"EPSG","code":"4326"},
         "temporal_coverage":"2024","provenance_state":"complete"}
    ],
    "features": [
        {"feature_id":"f1","label":"Facility A","geometry_type":"point","point":[-90.5,38.5],
         "crs":{"authority":"EPSG","code":"4326"},"place_id":"p2","provenance_state":"complete"}
    ],
    "relations": [
        {"relation_id":"r1","left_id":"p1","right_id":"l1"},
        {"relation_id":"r2","left_id":"p2","right_id":"f1"}
    ]
}

def test_guardrails():
    g=guardrails()
    assert g["spatial_proximity_implies_causation"] is False
    assert g["map_overlap_implies_relationship"] is False
    assert g["missing_crs_is_silently_assumed"] is False
    assert g["automatic_evidence_promotion"] is False

def test_inventory_preserves_crs():
    out=inventory(SAMPLE)
    assert out["place_count"]==2
    assert out["layer_count"]==1
    assert out["feature_count"]==1
    assert out["missing_crs"]==[]

def test_bbox_relation_preview():
    out=relation_preview(SAMPLE)
    r1=[x for x in out["rows"] if x["relation_id"]=="r1"][0]
    assert r1["computable"] is True
    assert r1["relation"] in {"contains","overlaps","within"}
    assert r1["causality_inferred"] is False

def test_point_distance_is_context_only():
    out=relation_preview(SAMPLE)
    r2=[x for x in out["rows"] if x["relation_id"]=="r2"][0]
    assert r2["computable"] is True
    assert r2["distance_km"]==0.0
    assert r2["identity_inferred"] is False

def test_coverage_complete_does_not_mean_truth():
    out=coverage_audit(SAMPLE)
    assert out["complete"] is True
    assert out["complete_implies_truth"] is False

def test_temporal_validity_preserves_dates():
    out=temporal_validity(SAMPLE)
    row=[x for x in out["rows"] if x["object_id"]=="p1"][0]
    assert row["valid_from"]=="2020"
    assert row["latest_observation_implies_current_truth"] is False

def test_handoffs_are_preview_only():
    e=evidence_handoff_preview(SAMPLE)
    assert e["preview_only"] is True
    assert e["automatic_claim_support_inference"] is False
    i=investigation_handoff_preview(SAMPLE)
    assert i["preview_only"] is True
    assert i["automatic_task_execution"] is False

def test_export_is_nonpersistent():
    out=export_workspace(SAMPLE)
    assert out["automatic_import"] is False
    assert out["workspace_persisted"] is False
    assert out["content"]
