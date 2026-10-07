from app.cross_library_cross_institution_research_federation import (
    bootstrap,
    export_bundle,
    failure_containment,
    identity_candidates,
    institution_manifest,
    provenance_audit,
    query_plan,
    readiness,
    result_bundle,
)


def institutions():
    return [
        institution_manifest({
            "institution_id": "inst:alpha",
            "name": "Alpha University",
            "source_authority": "alpha-repository",
            "capabilities": ["search", "record-lookup", "citation-export"],
            "endpoints": {"search": "https://alpha.example/search"},
            "languages": ["en"],
        }),
        institution_manifest({
            "institution_id": "inst:beta",
            "name": "Beta Institute",
            "source_authority": "beta-library",
            "capabilities": ["search", "dataset-discovery"],
            "endpoints": {"search": "https://beta.example/search"},
            "languages": ["en", "de"],
        }),
    ]


def plan():
    return query_plan({"query": "accelerated weathering carbon removal", "institutions": institutions(), "capabilities": ["search", "record-lookup"]})


def bundle():
    return result_bundle({
        "query_plan": plan(),
        "source_results": [
            {
                "institution_id": "inst:alpha",
                "status": "ok",
                "records": [
                    {"record_id": "alpha:1", "title": "Study A", "doi": "10.1000/shared", "schema": "alpha-record/1", "extra": {"keep": "exact"}},
                    {"record_id": "alpha:2", "title": "Study B", "url": "https://alpha.example/b"},
                ],
            },
            {
                "institution_id": "inst:beta",
                "status": "ok",
                "records": [
                    {"record_id": "beta:9", "title": "Study A repository copy", "doi": "10.1000/shared", "schema": "beta-record/4"},
                ],
            },
        ],
    })


def test_readiness_and_bootstrap():
    r = readiness()
    assert r["library_version"] == "6.37.0"
    assert r["backend_version"] == "3.37.0"
    assert r["ready"] is True
    assert r["live_network_transport_configured"] is False
    assert r["automatic_external_fetch"] is False
    assert r["database_migration_required"] is False
    assert bootstrap()["operations"] == ["institution-manifest", "query-plan", "result-bundle", "identity-candidates", "provenance-audit", "failure-containment", "export"]


def test_institution_manifest_preserves_authority_and_policy():
    m = institutions()[0]
    assert m["source_authority"] == "alpha-repository"
    assert m["source_authority_changed"] is False
    assert m["registered"] is False
    assert m["network_verified"] is False
    assert m["persisted"] is False


def test_query_plan_is_explicit_and_non_executing():
    p = plan()
    assert p["institution_count"] == 2
    assert p["network_requests_executed"] == 0
    assert p["automatic_external_fetch"] is False
    assert all(x["executed"] is False and x["planned_only"] is True for x in p["requests"])
    alpha = next(x for x in p["requests"] if x["institution_id"] == "inst:alpha")
    beta = next(x for x in p["requests"] if x["institution_id"] == "inst:beta")
    assert "record-lookup" in alpha["requested_capabilities"]
    assert "record-lookup" in beta["unavailable_capabilities"]


def test_result_bundle_preserves_exact_payload_and_authority():
    b = bundle()
    assert b["record_count"] == 3
    assert b["source_authority_changed"] is False
    assert b["source_payloads_rewritten"] is False
    assert b["import_performed"] is False
    assert b["persisted"] is False
    first = b["records"][0]
    assert first["source_authority"] == "alpha-repository"
    assert first["source_payload"]["extra"]["keep"] == "exact"
    assert first["source_payload_rewritten"] is False


def test_identity_candidates_never_merge():
    c = identity_candidates({"result_bundle": bundle()})
    assert c["candidate_count"] == 1
    assert c["candidates"][0]["match_kind"] == "doi"
    assert c["candidates"][0]["merge_performed"] is False
    assert c["candidates"][0]["human_review_required"] is True
    assert c["automatic_merge"] is False
    assert c["automatic_deduplication"] is False
    assert c["semantic_equivalence_claimed"] is False


def test_provenance_audit_and_failure_containment_are_descriptive():
    b = bundle()
    audit = provenance_audit({"result_bundle": b})
    assert audit["incomplete_record_count"] == 0
    assert audit["complete_fraction"] == 1.0
    assert audit["complete_fraction_is_truth_probability"] is False
    assert audit["complete_fraction_is_source_quality_score"] is False
    partial = result_bundle({
        "query_plan": plan(),
        "source_results": [
            {"institution_id": "inst:alpha", "status": "ok", "records": [{"record_id": "a:1", "title": "A"}]},
            {"institution_id": "inst:beta", "status": "failed", "error": {"message": "timeout"}, "records": []},
        ],
    })
    f = failure_containment({"result_bundle": partial})
    assert f["successful_source_count"] == 1
    assert f["failed_source_count"] == 1
    assert f["successful_sources_preserved"] is True
    assert f["failure_invalidates_successful_sources"] is False
    assert f["automatic_retry"] is False


def test_export_preserves_guardrails():
    inst = institutions()
    p = plan()
    b = bundle()
    c = identity_candidates({"result_bundle": b})
    a = provenance_audit({"result_bundle": b})
    f = failure_containment({"result_bundle": b})
    x = export_bundle({"institutions": inst, "query_plan": p, "result_bundle": b, "identity_candidates": c, "provenance_audit": a, "failure_containment": f})
    assert x["media_type"] == "application/json"
    assert x["originating_authorities_preserved"] is True
    assert x["automatic_external_fetch"] is False
    assert x["automatic_import"] is False
    assert x["automatic_merge"] is False
    assert x["automatic_persistence"] is False
    assert x["automatic_truth_promotion"] is False
