from app.research_gap_novelty import GAP_SCHEMA, build_research_gap_novelty


def sample_records():
    return [
        {"record_id":"r1","title":"Study A","publication_year":2025,"topics":["Carbon pricing","Industrial emissions"],"metadata":{"methodology":{"study_design":"Difference-in-differences","population":"Manufacturing firms"}}},
        {"record_id":"r2","title":"Study B","publication_year":2025,"topics":["Carbon pricing","Energy demand"],"metadata":{"methodology":{"study_design":"Difference-in-differences"}}},
        {"record_id":"r3","title":"Study C","publication_year":2015,"topics":["Legacy process emissions"],"metadata":{"methodology":{"study_design":"Difference-in-differences"}}},
        {"record_id":"r4","title":"Study D","publication_year":2014,"topics":["Legacy process emissions"],"metadata":{"methodology":{"study_design":"Difference-in-differences"}}},
        {"record_id":"r5","title":"Study E","publication_year":2025,"topics":["Energy demand"],"metadata":{"methodology":{"study_design":"Randomized controlled trial","population":"Households"}}},
        {"record_id":"r6","title":"Study F","publication_year":2013,"topics":["Legacy process emissions"],"metadata":{"methodology":{"study_design":"Difference-in-differences"}}},
    ]


def methodology():
    return {"profiles":[
        {"record_id":"r1","study_design":{"family":"quasi-experimental"},"fields":{"population":{"reported":True},"geography":{"reported":False}}},
        {"record_id":"r2","study_design":{"family":"quasi-experimental"},"fields":{"population":{"reported":False},"geography":{"reported":False}}},
        {"record_id":"r3","study_design":{"family":"quasi-experimental"},"fields":{"population":{"reported":False},"geography":{"reported":False}}},
        {"record_id":"r4","study_design":{"family":"quasi-experimental"},"fields":{"population":{"reported":False},"geography":{"reported":False}}},
        {"record_id":"r5","study_design":{"family":"randomized-interventional"},"fields":{"population":{"reported":True},"geography":{"reported":False}}},
        {"record_id":"r6","study_design":{"family":"quasi-experimental"},"fields":{"population":{"reported":False},"geography":{"reported":False}}},
    ]}


def test_gap_and_novelty_are_candidate_only_and_external_verification_required():
    nodes=[{"id":"claim-1","kind":"claim","label":"Claim 1","record_id":"r1"}]
    d=build_research_gap_novelty(sample_records(), nodes=nodes, edges=[], methodology_intelligence=methodology())
    assert d["schema"]==GAP_SCHEMA
    assert d["gap_candidates"]
    assert all(x["candidate_only"] is True and x["requires_external_verification"] is True for x in d["gap_candidates"])
    assert all(x["candidate_only"] is True and x["requires_external_search"] is True and x["novelty_claim"] is False for x in d["novelty_candidates"])
    assert d["guardrails"]["gap_signal_proves_global_absence"] is False
    assert d["guardrails"]["novelty_candidate_is_novelty_claim"] is False


def test_unlinked_claim_creates_indexed_evidence_linkage_gap_not_global_absence_claim():
    nodes=[{"id":"claim-1","kind":"claim","label":"Claim 1","record_id":"r1"}]
    d=build_research_gap_novelty(sample_records(), nodes=nodes, edges=[], methodology_intelligence=methodology())
    x=next(g for g in d["gap_candidates"] if g["kind"]=="indexed-evidence-linkage-gap")
    assert x["basis"]["reviewed_evidence_edge_count"]==0
    assert "does not establish" in x["caveat"].lower()


def test_explicit_support_and_contradiction_create_review_priority_not_consensus_claim():
    nodes=[{"id":"claim-1","kind":"claim","label":"Claim 1","record_id":"r1"}]
    edges=[
      {"source":"finding-a","target":"claim-1","relationship_basis":"reviewed-support"},
      {"source":"finding-b","target":"claim-1","relationship_basis":"reviewed-contradiction"},
    ]
    d=build_research_gap_novelty(sample_records(), nodes=nodes, edges=edges, methodology_intelligence=methodology())
    x=next(g for g in d["gap_candidates"] if g["kind"]=="competing-evidence-review-priority")
    assert x["basis"]["support_edge_count"]==1 and x["basis"]["contradiction_edge_count"]==1
    assert "not a conclusion" in x["caveat"].lower()


def test_methodology_and_reporting_gap_are_corpus_scoped():
    d=build_research_gap_novelty(sample_records(), methodology_intelligence=methodology())
    kinds={x["kind"] for x in d["gap_candidates"]}
    assert "methodology-diversity-gap" in kinds
    assert "geography-reporting-gap" in kinds
    assert all(x["scope"] in {"topic","research-object"} for x in d["gap_candidates"])


def test_rare_topic_combination_is_only_novelty_lead():
    d=build_research_gap_novelty(sample_records(), methodology_intelligence=methodology())
    leads=[x for x in d["novelty_candidates"] if x["kind"]=="rare-topic-combination"]
    assert leads
    assert all(x["novelty_claim"] is False for x in leads)


def test_temporal_coverage_signal_is_relative_to_corpus():
    d=build_research_gap_novelty(sample_records(), methodology_intelligence=methodology())
    xs=[x for x in d["gap_candidates"] if x["kind"]=="corpus-relative-temporal-coverage-gap"]
    assert xs
    assert any(x["basis"]["corpus_latest_publication_year"]==2025 for x in xs)


def test_overlay_never_enters_default_evidence_path():
    d=build_research_gap_novelty(sample_records(), methodology_intelligence=methodology())
    assert all(e["analytical"] is True for e in d["graph_overlay"]["edges"])
    assert all(e["default_evidence_path"] is False for e in d["graph_overlay"]["edges"])
    assert all(e["truth_assertion"] is False for e in d["graph_overlay"]["edges"])
