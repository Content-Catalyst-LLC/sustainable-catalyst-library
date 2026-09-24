from app.publication_corpus_maps import _linked_visual_query_analysis


def test_linked_visual_query_contract_indexes_observed_structure():
    nodes={
        "p1":{"id":"p1","kind":"publication","label":"Paper One"},
        "p2":{"id":"p2","kind":"publication","label":"Paper Two"},
        "topic:a":{"id":"topic:a","kind":"topic","label":"Resilience","region_id":"region:1"},
        "topic:b":{"id":"topic:b","kind":"topic","label":"Energy","region_id":"region:1"},
    }
    edges=[
        {"source":"p1","target":"topic:a","relationship_basis":"metadata-association","weight":1.0,"directed":False},
        {"source":"p2","target":"topic:b","relationship_basis":"reviewed-concept-association","weight":0.9,"directed":False},
        {"source":"topic:a","target":"topic:b","relationship_basis":"publication-topic-cooccurrence","weight":2.0,"directed":False},
        {"source":"p1","target":"p2","relationship_basis":"explicit-citation","weight":1.0,"directed":True},
    ]
    records={
        "p1":{"published_at":"2024-01-02"},
        "p2":{"published_at":"2025-02-03"},
    }
    multi={"topic_regions":[{"id":"region:1","topic_ids":["topic:a","topic:b"]}]}
    q=_linked_visual_query_analysis(nodes,edges,records,multi)
    assert q["schema"]=="sc-library-linked-visual-query/1.0"
    assert q["capabilities"]["cross_view_selection"] is True
    assert q["capabilities"]["terrain_peak_selection"] is True
    assert q["indexes"]["publication_to_topics"]["p1"]==["topic:a"]
    assert q["indexes"]["topic_to_publications"]["topic:b"]==["p2"]
    assert q["indexes"]["region_to_publications"]["region:1"]==["p1","p2"]
    assert q["indexes"]["year_to_publications"]["2025"]==["p2"]
    assert any(x["node_id"]=="p2" and x["relationship_basis"]=="explicit-citation" for x in q["indexes"]["adjacency"]["p1"])
    assert q["boundaries"]["selection_is_research_conclusion"] is False
    assert q["boundaries"]["neighbor_highlight_implies_causality"] is False
