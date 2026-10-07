import copy

from app.portable_research_object_exchange import (
    OBJECT_CONTRACT,
    EXCHANGE_CONTRACT,
    bootstrap,
    build_exchange,
    compatibility_report,
    contract,
    export_exchange,
    import_preview,
    readiness,
    validate_exchange,
    verify_integrity,
    wrap_object,
)


def source_object():
    return {
        "schema": "sc-library-research-package-composition/1.0",
        "composition_id": "research-package-composition:test",
        "authority": "research-package-composer",
        "library_version": "6.25.0",
        "title": "Test package",
        "components": [{"component_id": "analysis:1", "payload": {"value": 42}}],
    }


def test_contract_and_readiness():
    c = contract()
    r = readiness()
    b = bootstrap()
    assert c["library_version"] == "6.34.0"
    assert c["backend_version"] == "3.34.0"
    assert c["route"] == "/research/exchange"
    assert r["ready"] is True
    assert r["automatic_import"] is False
    assert r["database_migration_required"] is False
    assert b["operations"] == ["wrap-object", "build-exchange", "validate", "verify-integrity", "compatibility", "import-preview", "export"]


def test_wrap_object_is_deterministic_and_preserves_authority():
    a = wrap_object({"object": source_object()})
    b = wrap_object({"object": source_object()})
    assert a == b
    assert a["schema"] == OBJECT_CONTRACT
    assert a["source_authority"] == "research-package-composer"
    assert a["payload"] == source_object()
    assert a["authority_changed"] is False
    assert a["payload_rewritten"] is False


def test_build_exchange_uses_explicit_relationships_only():
    one = wrap_object({"object": source_object()})
    second_source = {
        "schema": "sc-library-research-publication-draft/1.0",
        "publication_draft_id": "research-publication-draft:test",
        "authority": "research-publication-studio",
        "title": "Draft",
    }
    two = wrap_object({"object": second_source})
    e = build_exchange({
        "title": "Exchange",
        "objects": [one, two],
        "relationships": [{"source": one["portable_object_id"], "target": two["portable_object_id"], "relation": "source-for"}],
    })
    assert e["schema"] == EXCHANGE_CONTRACT
    assert e["object_count"] == 2
    assert e["relationship_count"] == 1
    assert e["relationships"][0]["explicit"] is True
    assert e["relationships"][0]["inferred"] is False
    assert e["authority_changed"] is False


def test_validate_and_integrity_pass_for_untampered_exchange():
    e = build_exchange({"objects": [{"object": source_object()}]})
    v = validate_exchange({"exchange": e})
    i = verify_integrity({"exchange": e})
    assert v["valid"] is True
    assert v["blocker_count"] == 0
    assert i["valid"] is True
    assert i["finding_count"] == 0


def test_tamper_is_detected_without_truth_claim():
    e = build_exchange({"objects": [{"object": source_object()}]})
    damaged = copy.deepcopy(e)
    damaged["objects"][0]["payload"]["title"] = "Changed after wrapping"
    i = verify_integrity({"exchange": damaged})
    v = validate_exchange({"exchange": damaged})
    assert i["valid"] is False
    assert any(x["kind"] == "content-fingerprint-mismatch" for x in i["findings"])
    assert v["valid"] is False
    assert i["integrity_pass_implies_source_validity"] is False


def test_unknown_schema_is_preserved_for_review_and_import_is_preview_only():
    unknown = {"schema": "external-research-object/9.9", "id": "external:1", "authority": "external-system", "value": 7}
    e = build_exchange({"objects": [{"object": unknown}]})
    c = compatibility_report({"exchange": e})
    p = import_preview({"exchange": e, "target": {"authority": "python-research-state-postgresql"}})
    assert c["review_required_count"] == 1
    assert c["rows"][0]["payload_preserved"] is True
    assert c["rows"][0]["semantic_equivalence_claimed"] is False
    assert p["imported"] is False
    assert p["persisted"] is False
    assert p["automatic_import"] is False
    assert p["actions"][0]["automatic_merge"] is False


def test_export_is_deterministic_and_non_mutating():
    payload = {"objects": [{"object": source_object()}], "metadata": {"purpose": "portable-test"}}
    a = export_exchange(payload)
    b = export_exchange(payload)
    assert a == b
    assert a["exchange"]["imported"] is False
    assert a["automatic_import"] is False
    assert a["automatic_persistence"] is False
    assert a["authority_changed"] is False
    assert a["guardrails"]["automatic_truth_promotion"] is False
