from app.publication_corpus_maps import _multi_publication_analysis, _knowledge_terrain_analysis


def test_4d_terrain_contract_is_deterministic_and_temporal():
    nodes={
      "p1":{"id":"p1","kind":"publication","label":"P1","metrics":{"weighted_degree":2}},
      "p2":{"id":"p2","kind":"publication","label":"P2","metrics":{"weighted_degree":2}},
      "t1":{"id":"t1","kind":"topic","label":"Climate","metrics":{"weighted_degree":5,"publication_count":2}},
      "t2":{"id":"t2","kind":"topic","label":"Energy","metrics":{"weighted_degree":3,"publication_count":2}},
    }
    edges=[
      {"source":"p1","target":"t1","relationship_basis":"metadata-association","weight":1},
      {"source":"p1","target":"t2","relationship_basis":"metadata-association","weight":1},
      {"source":"p2","target":"t1","relationship_basis":"metadata-association","weight":1},
      {"source":"p2","target":"t2","relationship_basis":"metadata-association","weight":1},
      {"source":"t1","target":"t2","relationship_basis":"publication-topic-cooccurrence","weight":2,"evidence_count":2},
    ]
    records={
      "p1":{"title":"P1","published_at":"2021-01-01T00:00:00Z","canonical_url":"https://example.test/p1"},
      "p2":{"title":"P2","published_at":"2024-01-01T00:00:00Z","canonical_url":"https://example.test/p2"},
    }
    multi=_multi_publication_analysis(nodes,edges,records)
    a=_knowledge_terrain_analysis(nodes,records,multi)
    b=_knowledge_terrain_analysis(nodes,records,multi)
    assert a==b
    assert a["schema"]=="sc-library-4d-knowledge-terrain/1.0"
    assert a["coordinate_system"]["t"]=="publication year"
    assert [m["key"] for m in a["elevation_metrics"]]==["relationship_density","publication_density","temporal_activity"]
    assert a["time_playback"]["years"]==[2021,2024]
    assert a["time_playback"]["available"] is True
    assert a["topic_anchors"]
    assert len(a["temporal_keyframes"])==2
    assert a["interpretation"]["terrain_height_is_evidence_of_truth"] is False
