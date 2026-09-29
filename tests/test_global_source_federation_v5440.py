from pathlib import Path
import ast
import importlib.util
import json
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def load_module():
    path = ROOT / "library-backend/app/global_source_federation.py"
    spec = importlib.util.spec_from_file_location("sc_global_source_federation_test", path)
    mod = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = mod
    spec.loader.exec_module(mod)
    return mod


def test_release_identity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.44.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.44.0');" in plugin
    assert '__version__ = "2.55.0"' in read("library-backend/app/__init__.py")


def test_registry_contract_and_counts():
    mod = load_module()
    ready = mod.registry.readiness()
    assert ready["schema"] == "sc-library-global-source-federation-readiness/1.0"
    assert ready["state"] == "ready"
    assert ready["sources"] == 26
    assert ready["connector_contracts"] == 26
    assert ready["collections"] == 6
    assert len(ready["registry_fingerprint_sha256"]) == 64


def test_every_source_resolves_one_institution_and_primary_connector():
    mod = load_module()
    source_ids = {source.source_id for source in mod.SOURCES}
    institution_ids = {institution.institution_id for institution in mod.INSTITUTIONS}
    connector_ids = {connector.connector_id for connector in mod.CONNECTORS}
    assert len(source_ids) == len(mod.SOURCES)
    assert len(connector_ids) == len(mod.CONNECTORS)
    for source in mod.SOURCES:
        assert source.institution_id in institution_ids
        assert source.connector_id in connector_ids
        connector = next(item for item in mod.CONNECTORS if item.connector_id == source.connector_id)
        assert connector.source_id == source.source_id


def test_registry_fingerprint_is_deterministic():
    mod = load_module()
    first = mod.GlobalSourceFederationRegistry().fingerprint()
    second = mod.GlobalSourceFederationRegistry().fingerprint()
    assert first == second
    assert len(first) == 64


def test_registry_filters_are_contract_aware():
    mod = load_module()
    search = mod.registry.snapshot(capability="search")
    assert 0 < search["counts"]["sources"] < 26
    assert all("search" in connector["capabilities"] for connector in search["connectors"])
    biomedical = mod.registry.snapshot(collection="biomedical-clinical")
    assert biomedical["counts"]["sources"] >= 8
    assert all("biomedical-clinical" in source["collection_ids"] for source in biomedical["sources"])
    browser = mod.registry.snapshot(authority="browser-handoff")
    assert {source["source_id"] for source in browser["sources"]} == {"google-scholar", "worldcat"}


def test_connector_contract_preserves_language_and_forbids_truth_promotion():
    mod = load_module()
    for connector in mod.CONNECTORS:
        payload = connector.to_dict()
        assert payload["language_policy"] == "preserve-as-received"
        assert payload["translation_behavior"] == "none"
        assert payload["raw_source_preservation"] is True
        assert payload["automatic_import"] is False
        assert payload["automatic_evidence_promotion"] is False
        assert payload["automatic_truth_promotion"] is False
        assert payload["source_membership_implies_endorsement"] is False


def test_connector_manifest_validation_accepts_safe_contract():
    mod = load_module()
    payload = {
        "connector_id": "example-safe",
        "source_id": "example-source",
        "execution_authority": "python-backend-example",
        "transport": "rest-json",
        "capabilities": ["search", "metadata"],
        "authentication": "none",
        "pagination": "cursor",
        "rate_limit_policy": "source-policy-bounded",
        "provenance_fields": ["source_id", "source_record_id", "retrieved_at"],
    }
    result = mod.registry.validate_connector_manifest(payload)
    assert result["valid"] is True
    assert result["errors"] == []
    assert result["normalized_contract"]["automatic_truth_promotion"] is False


def test_connector_manifest_validation_rejects_guardrail_promotion():
    mod = load_module()
    payload = {
        "connector_id": "unsafe",
        "source_id": "example-source",
        "execution_authority": "example",
        "transport": "rest-json",
        "capabilities": ["search"],
        "authentication": "none",
        "pagination": "none",
        "rate_limit_policy": "bounded",
        "provenance_fields": ["source_id"],
        "automatic_truth_promotion": True,
    }
    result = mod.registry.validate_connector_manifest(payload)
    assert result["valid"] is False
    assert "governance-guardrail-violation" in result["errors"]
    assert "automatic_truth_promotion" in result["guardrail_violations"]


def test_backend_routes_and_health_capabilities_exist():
    main = read("library-backend/app/main.py")
    ast.parse(main)
    for route in [
        '/v1/global-source-federation/readiness',
        '/v1/global-source-federation/registry',
        '/v1/global-source-federation/collections',
        '/v1/global-source-federation/sources/{source_id}',
        '/v1/global-source-federation/connectors/{connector_id}',
        '/v1/global-source-federation/connectors/validate',
    ]:
        assert route in main
    for capability in [
        '"global_source_federation_registry": True',
        '"global_source_connector_contracts": True',
        '"global_source_registry_parallel_execution_stack": False',
        '"global_source_registry_membership_implies_endorsement": False',
        '"global_source_connector_health_implies_source_quality": False',
        '"global_source_automatic_truth_promotion": False',
        '"global_source_original_language_preserved_as_received": True',
        '"global_source_automatic_translation": False',
    ]:
        assert capability in main


def test_wordpress_proxy_and_registry_surface_exist():
    proxy = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    surface = read("sustainable-catalyst-library/includes/class-sc-library-global-source-federation-registry.php")
    hub = read("sustainable-catalyst-library/includes/class-sc-library-capability-hub.php")
    for route in [
        "/backend/global-source-federation/readiness",
        "/backend/global-source-federation/registry",
        "/backend/global-source-federation/collections",
        "/backend/global-source-federation/sources/",
        "/backend/global-source-federation/connectors/",
    ]:
        assert route in proxy
    assert "SC_Library_Global_Source_Federation_Registry" in plugin
    assert "sc_global_source_registry" in surface
    assert "global-source-registry" in hub


def test_registry_governance_reuses_legacy_systems_without_parallel_stack():
    mod = load_module()
    governance = mod.registry.governance()
    assert governance["legacy_v4_8_federation_transport_reused"] is True
    assert governance["legacy_v2_6_scholarly_connectors_reused"] is True
    assert governance["parallel_connector_execution_stack_created"] is False
    assert governance["registry_membership_implies_endorsement"] is False
    assert governance["registry_membership_implies_partnership"] is False
    assert governance["source_quality_score_assigned"] is False
    assert governance["user_trust_score_assigned"] is False


def test_v544_contract_schemas_parse():
    for name in [
        "global-source-federation-registry.json",
        "global-source.json",
        "global-source-connector-contract.json",
        "global-source-connector-validation.json",
    ]:
        data = json.loads(read(f"docs/schemas/{name}"))
        assert data["$schema"].endswith("2020-12/schema")
        assert data["type"] == "object"


def test_v543_semantic_map_and_v542_rerank_guardrails_remain_intact():
    maps = read("library-backend/app/publication_embedding_maps.py")
    rerank = read("library-backend/app/neural_reranking.py")
    assert '"spatial_proximity_is_evidence": False' in maps
    assert '"automatic_platform_core_promotion": False' in maps
    assert '"rerank_score_is_evidence": False' in rerank
    assert '"rerank_score_is_truth": False' in rerank
    assert '"automatic_candidate_filtering": False' in rerank
