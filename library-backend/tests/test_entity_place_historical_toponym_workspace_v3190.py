from app.entity_place_historical_toponym_workspace import (
    authority_preview, candidate_matrix, decision_preview, export_workspace,
    guardrails, historical_toponym_timeline, persistence_handoff_preview,
    resolution_preview,
)

AUTHORITY = {
    "title": "Historical place authority",
    "entities": [
        {
            "entity_id": "entity:istanbul",
            "entity_type": "place",
            "canonical_name": "Istanbul",
            "country_code": "TR",
            "latitude": 41.0082,
            "longitude": 28.9784,
            "names": [
                {"text":"Istanbul","relation_type":"canonical","language_bcp47":"tr","script_iso15924":"Latn"},
                {"text":"Constantinople","relation_type":"historical","language_bcp47":"en","script_iso15924":"Latn","valid_to_year":1930},
                {"text":"İstanbul","relation_type":"endonym","language_bcp47":"tr","script_iso15924":"Latn"},
            ],
        },
        {
            "entity_id": "entity:other",
            "entity_type": "place",
            "canonical_name": "Other Place",
            "names": [{"text":"Constantinople","relation_type":"alias","language_bcp47":"en","script_iso15924":"Latn","valid_from_year":1800,"valid_to_year":1850}],
        },
    ],
}

def test_guardrails():
    g=guardrails()
    assert g["existing_v548_cross_language_resolution_is_durable_authority"] is True
    assert g["workspace_creates_parallel_entity_store"] is False
    assert g["candidate_rank_is_winner_selection"] is False
    assert g["historical_toponym_overlap_establishes_identity"] is False
    assert g["automatic_resolution"] is False

def test_authority_preview():
    out=authority_preview({"authority":AUTHORITY})
    assert out["workspace_persisted"] is False
    assert out["authority"]["entity_count"]==2
    assert out["authority"]["persisted"] is False

def test_toponym_timeline_preserves_windows():
    out=historical_toponym_timeline({"authority":AUTHORITY,"entity_id":"entity:istanbul","year":1920})
    assert out["entity_count"]==1
    forms=out["entities"][0]["name_forms"]
    hist=[x for x in forms if x["text"]=="Constantinople"][0]
    assert hist["valid_to_year"]==1930
    assert hist["temporal_status"]=="within-window"
    assert out["continuity_inferred"] is False

def test_resolution_and_matrix_preserve_ambiguity():
    payload={"authority":AUTHORITY,"query":{"name":"Constantinople","language_bcp47":"en","entity_type":"place","year":1920}}
    out=resolution_preview(payload)
    assert out["workspace_persisted"] is False
    assert out["automatic_decision"] is False
    assert out["resolution_case"]["candidate_count"]>=1
    matrix=candidate_matrix(payload)
    assert matrix["winner_selected"] is False
    assert all(row["score_is_probability"] is False for row in matrix["rows"])

def test_decision_preview_is_nonpersistent():
    payload={"authority":AUTHORITY,"query":{"name":"Constantinople","year":1920}}
    case=resolution_preview(payload)["resolution_case"]
    selected=case["candidates"][0]["candidate_id"]
    out=decision_preview({**payload,"decision":{"state":"accepted","selected_candidate_id":selected,"rationale":"Reviewed against cited historical source.","adjudicator":"researcher"}})
    assert out["valid"] is True
    assert out["persisted"] is False
    assert out["automatic_decision"] is False

def test_handoff_preview_uses_existing_signed_authority():
    a=persistence_handoff_preview({"handoff_type":"authority","authority":AUTHORITY})
    assert a["endpoint"]=="/api/library/v1/admin/language/authorities"
    assert a["automatic_persistence"] is False
    c=persistence_handoff_preview({"handoff_type":"resolution-case","query":{"name":"Constantinople","year":1920}})
    assert c["endpoint"]=="/api/library/v1/admin/language/entity-resolution/cases"
    assert c["signed_request_required"] is True

def test_export():
    out=export_workspace({"authority":AUTHORITY,"query":{"name":"Constantinople","year":1920}})
    assert out["workspace_persisted"] is False
    assert out["automatic_import"] is False
    assert out["media_type"]=="application/json"
    assert out["content"]
