from app.publication_embedding_maps import deterministic_pca_2d, semantic_edges_and_neighborhoods


def test_deterministic_pca_projection_is_repeatable():
    vectors = [[1.0, 0.0, 0.1], [0.9, 0.1, 0.1], [0.0, 1.0, 0.2], [0.1, 0.9, 0.3]]
    a = deterministic_pca_2d(vectors)
    b = deterministic_pca_2d(vectors)
    assert a["coordinates"] == b["coordinates"]
    assert all(-1 <= x <= 1 and -1 <= y <= 1 for x, y in a["coordinates"])


def test_semantic_edge_policy_is_sparse_symmetric_top_k_union():
    edges, neighborhoods = semantic_edges_and_neighborhoods(
        ["a", "b", "c"], [[1.0, 0.0], [0.98, 0.02], [0.0, 1.0]],
        threshold=0.8, neighbors_per_point=2, max_edges=50,
    )
    assert len(edges) == 1
    assert edges[0]["truth_assertion"] is False
    assert neighborhoods["a"][0]["record_id"] == "b"
