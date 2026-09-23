from app.publication_corpus_maps import _multi_publication_analysis


def test_multi_publication_analysis_regions_time_and_matrix():
    nodes = {
        "p1": {"id": "p1", "kind": "publication", "label": "Paper One", "metrics": {"weighted_degree": 3}},
        "p2": {"id": "p2", "kind": "publication", "label": "Paper Two", "metrics": {"weighted_degree": 2}},
        "t1": {"id": "t1", "kind": "topic", "label": "Climate", "metrics": {"weighted_degree": 4, "publication_count": 2}},
        "t2": {"id": "t2", "kind": "topic", "label": "Energy", "metrics": {"weighted_degree": 4, "publication_count": 2}},
    }
    edges = [
        {"source": "p1", "target": "t1", "relationship_basis": "metadata-association", "weight": 1},
        {"source": "p1", "target": "t2", "relationship_basis": "metadata-association", "weight": 1},
        {"source": "p2", "target": "t1", "relationship_basis": "metadata-association", "weight": 1},
        {"source": "p2", "target": "t2", "relationship_basis": "metadata-association", "weight": 1},
        {"source": "t1", "target": "t2", "relationship_basis": "publication-topic-cooccurrence", "weight": 2, "evidence_count": 2},
        {"source": "p1", "target": "p2", "relationship_basis": "explicit-citation", "weight": 1},
    ]
    records = {"p1": {"published_at": "2022-01-01T00:00:00Z"}, "p2": {"published_at": "2024-01-01T00:00:00Z"}}
    out = _multi_publication_analysis(nodes, edges, records)
    assert out["analytical_dimensions"]["four_dimensional_ready"] is True
    assert len(out["topic_regions"]) == 1
    assert out["topic_regions"][0]["publication_count"] == 2
    assert out["publication_relationships"][0]["topic_jaccard"] == 1.0
    assert out["publication_relationships"][0]["explicit_citation"] is True
    assert out["temporal_dynamics"]["years"] == [2022, 2024]
    assert out["linked_views"]["relationship_matrix"]["entries"]
