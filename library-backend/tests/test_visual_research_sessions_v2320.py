from app.visual_research_sessions import build_visual_research_session_package, sanitize_visual_state

def corpus():
    return {"schema":"sc-library-publication-corpus-knowledge-map/1.0","nodes":[{"id":"wordpress:1:post:1","kind":"publication","source_content_hash":"abc","canonical_url":"https://example.test/a"},{"id":"topic:x","kind":"topic"}],"metrics":{"publication_count":1,"topic_count":1},"corpus":{"selection":"publication-library-manifest","source_key":"wordpress-main"},"visual_query":{"schema":"sc-library-linked-visual-query/1.0"},"knowledge_terrain_4d":{"schema":"sc-library-4d-knowledge-terrain/1.0"}}

def test_visual_session_is_deterministic_for_same_state():
    state={"visual_query":{"view":"knowledge-terrain-4d","selected_node_ids":["wordpress:1:post:1"],"terrain_elevation_metric":"publication_density"},"camera":{"zoom":1.4}}
    a=build_visual_research_session_package(corpus(),state)
    b=build_visual_research_session_package(corpus(),state)
    assert a["session_id"]==b["session_id"]
    assert a["schema"]=="sc-library-visual-research-session/1.0"
    assert a["corpus_snapshot"]["fingerprint_sha256"]
    assert a["reproducibility"]["source_hashes_preserved"] is True

def test_workspace_handoff_is_explicit_references_only():
    p=build_visual_research_session_package(corpus(),{"selected_node_ids":["wordpress:1:post:1"]},target_workspace=True)
    h=p["workspace_handoff"]
    assert h["schema"]=="sc-library-workspace-visual-research-handoff/1.0"
    assert h["target_product"]=="workspace"
    assert h["references_only"] is True
    assert h["requires_user_acceptance"] is True
    assert h["automatic_workspace_write"] is False
    assert h["requested"] is True

def test_visual_state_is_bounded_and_descriptive():
    s=sanitize_visual_state({"selected_node_ids":[str(i) for i in range(500)],"mode":"bad","minimum_relationship_strength":9})
    assert len(s["selected_node_ids"])==250
    assert s["mode"]=="highlight"
    assert s["minimum_relationship_strength"]==1.0
