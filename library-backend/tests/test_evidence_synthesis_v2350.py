from app.evidence_synthesis import build_cross_publication_synthesis, SYNTHESIS_CONTRACT


def _item(i, record, kind, weight, **metadata):
    return {
        "id": f"{kind}:{i}",
        "record_id": record,
        "kind": kind,
        "label": f"{kind} {i}",
        "evidence_traceability_weight": weight,
        "metadata": metadata,
    }


def test_support_components_and_cross_publication_relations_are_descriptive_only():
    items = [
        _item(1, "pub:a", "finding", .9),
        _item(2, "pub:b", "claim", .8),
        _item(3, "pub:c", "claim", .7),
    ]
    relations = [
        {"source": "finding:1", "target": "claim:2", "relationship_family": "support", "relationship_basis": "reviewed-explicit-support"},
        {"source": "claim:2", "target": "claim:3", "relationship_family": "contradiction", "relationship_basis": "reviewed-explicit-contradiction"},
    ]
    out = build_cross_publication_synthesis({"items": items, "relations": relations}, {})
    assert out["schema"] == SYNTHESIS_CONTRACT
    assert out["metrics"]["support_structure_count"] == 1
    assert out["metrics"]["explicit_contradiction_relation_count"] == 1
    assert len(out["publication_synthesis"]) == 2
    assert out["interpretation"]["consensus_inferred"] is False
    assert out["interpretation"]["evidence_balance_is_truth_score"] is False
    assert out["platform_core"]["durable_synthesis_authority"] == "platform-core"


def test_hypotheses_require_explicit_metadata_and_competition_is_not_inferred():
    items = [
        _item(1, "pub:a", "claim", .9, hypothesis_key="h1", hypothesis_label="Hypothesis One", competing_hypothesis_set="set-a", hypothesis_stance="support"),
        _item(2, "pub:b", "finding", .8, hypothesis_key="h2", hypothesis_label="Hypothesis Two", competing_hypothesis_set="set-a", hypothesis_stance="challenge"),
        _item(3, "pub:c", "claim", .7),
    ]
    out = build_cross_publication_synthesis({"items": items, "relations": []}, {})
    assert out["metrics"]["explicit_hypothesis_count"] == 2
    assert out["metrics"]["explicit_competing_hypothesis_set_count"] == 1
    assert len(out["hypothesis_membership_edges"]) == 2
    assert all(edge["relationship_basis"] == "explicit-hypothesis-membership" for edge in out["hypothesis_membership_edges"])
    assert out["interpretation"]["hypotheses_inferred"] is False
    assert out["interpretation"]["competing_hypotheses_require_explicit_metadata"] is True


def test_no_explicit_hypothesis_metadata_means_no_hypothesis_nodes():
    items = [_item(1, "pub:a", "claim", .9), _item(2, "pub:b", "finding", .8)]
    out = build_cross_publication_synthesis({"items": items, "relations": []}, {})
    assert out["hypotheses"] == []
    assert out["competing_hypothesis_sets"] == []
