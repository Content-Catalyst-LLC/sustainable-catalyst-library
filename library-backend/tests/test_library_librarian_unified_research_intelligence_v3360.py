from app.library_librarian_unified_research_intelligence import (
    advisory_preview,
    action_plan,
    bootstrap,
    build_context_packet,
    export_bundle,
    grounding_audit,
    librarian_request,
    readiness,
    validate_context,
)


def context_payload():
    return {
        "question": "What evidence should I review next?",
        "intent": "research-guidance",
        "project": {"project_id": "research-project:1", "title": "Project"},
        "room": {"room_id": "research-room:1"},
        "research_objects": [
            {"object_id": "analysis:1", "object_type": "statistical-result", "source_schema": "sc-library-statistical-evidence/1.0", "source_authority": "statistical-evidence"},
            {"object_id": "evidence:1", "object_type": "evidence-matrix", "source_schema": "sc-library-evidence-matrix/1.0", "source_authority": "evidence-matrix"},
        ],
        "citations": [{"citation_id": "cite:1", "title": "Study", "doi": "10.1234/example", "source_authority": "citation-workspace"}],
        "provenance": [{"source_id": "source:1", "relation": "supports-context"}],
    }


def test_readiness_and_bootstrap():
    r = readiness()
    assert r["library_version"] == "6.36.0"
    assert r["backend_version"] == "3.36.0"
    assert r["ready"] is True
    assert r["transport_configured"] is False
    assert r["automatic_external_transport"] is False
    assert r["database_migration_required"] is False
    assert bootstrap()["operations"] == ["context-packet", "validate-context", "librarian-request", "advisory-preview", "grounding-audit", "action-plan", "export"]


def test_context_packet_preserves_authority():
    c = build_context_packet(context_payload())
    assert c["schema"] == "sc-library-librarian-research-context/1.0"
    assert c["context_source_count"] == 3
    assert c["source_authority_changed"] is False
    assert c["source_objects_mutated"] is False
    assert c["persisted"] is False
    assert c["components"]["research_objects"][0]["source_authority"] == "statistical-evidence"


def test_context_validation():
    c = build_context_packet(context_payload())
    v = validate_context({"context": c})
    assert v["valid"] is True
    assert v["blocker_count"] == 0
    assert v["source_authority_changed"] is False


def test_librarian_request_is_explicit_and_not_delivered():
    c = build_context_packet(context_payload())
    req = librarian_request({"context": c, "task": "Identify research gaps", "mode": "gap-analysis"})
    assert req["target_product"] == "research-librarian-ai"
    assert req["delivered"] is False
    assert req["automatic_transport"] is False
    assert req["automatic_execution"] is False
    assert req["context_fingerprint_sha256"] == c["context_fingerprint_sha256"]


def test_advisory_and_grounding_are_non_authoritative():
    c = build_context_packet(context_payload())
    req = librarian_request({"context": c})
    refs = [c["context_sources"][0]["context_source_id"], c["context_sources"][2]["context_source_id"]]
    adv = advisory_preview({"request": req, "response": {"answer": "Review the statistical result and cited study.", "source_refs": refs, "suggestions": [{"action": "open-source", "target": {"context_source_id": refs[1]}, "rationale": "Inspect the cited study."}]}})
    assert adv["advisory_only"] is True
    assert adv["accepted_into_library_state"] is False
    assert adv["truth_adjudicated"] is False
    audit = grounding_audit({"request": req, "advisory": adv})
    assert audit["fully_grounded_to_declared_refs"] is True
    assert audit["coverage_implies_truth"] is False
    assert audit["coverage_implies_context_completeness"] is False


def test_action_plan_never_executes():
    c = build_context_packet(context_payload())
    req = librarian_request({"context": c})
    adv = advisory_preview({"request": req, "response": {"answer": "Suggestion", "suggestions": [{"action": "search", "parameters": {"q": "new evidence"}}]}})
    plan = action_plan({"advisory": adv})
    assert plan["action_count"] == 1
    assert plan["actions"][0]["proposed_only"] is True
    assert plan["actions"][0]["executed"] is False
    assert plan["automatic_execution"] is False
    assert plan["automatic_search_execution"] is False
    assert plan["automatic_source_inclusion"] is False


def test_export_preserves_guardrails():
    c = build_context_packet(context_payload())
    req = librarian_request({"context": c})
    adv = advisory_preview({"request": req, "response": {"answer": "Advisory only", "source_refs": []}})
    audit = grounding_audit({"request": req, "advisory": adv})
    plan = action_plan({"advisory": adv})
    x = export_bundle({"context": c, "request": req, "advisory": adv, "grounding": audit, "action_plan": plan})
    assert x["media_type"] == "application/json"
    assert x["originating_authority_preserved"] is True
    assert x["automatic_transport"] is False
    assert x["automatic_execution"] is False
    assert x["automatic_persistence"] is False
    assert x["automatic_truth_promotion"] is False
