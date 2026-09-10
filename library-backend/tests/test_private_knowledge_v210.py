from __future__ import annotations

from pathlib import Path
import pytest
from pydantic import ValidationError

from app.private_knowledge import (
    PrivateKnowledgeIngestRequest,
    PrivateKnowledgeSearchRequest,
    PrivateOrganizationalKnowledge,
    PrivateRecordPacket,
    access_scope_allows,
    lab_handoff_eligible,
    normalize_org_key,
    private_record_key,
)

ROOT = Path(__file__).resolve().parents[2]


def test_manifest_declares_hard_private_boundaries():
    payload = PrivateOrganizationalKnowledge().manifest()
    assert payload["schema"] == "sc-private-organizational-knowledge-manifest/1.0"
    gov = payload["framework"]["governance"]
    assert gov["public_search_includes_private_records"] is False
    assert gov["cross_organization_search"] is False
    assert gov["cross_organization_identity_merge"] is False
    assert gov["automatic_publication"] is False
    assert gov["browser_backend_secret_exposure"] is False
    assert gov["server_signed_private_requests_required"] is True
    assert gov["organization_scope_required"] is True
    assert gov["access_scope_enforced"] is True
    assert gov["raw_search_query_written_to_audit_log"] is False


def test_manifest_is_honest_about_binary_ingestion_boundary():
    payload = PrivateOrganizationalKnowledge().manifest()
    ingestion = payload["ingestion"]
    assert ingestion["mode"] == "normalized-text-packets"
    assert ingestion["binary_parser_claimed"] is False
    assert {"pdf", "docx", "txt", "md", "csv", "json"} <= set(ingestion["supported_original_formats"])


def test_org_key_normalization_and_private_identity_are_deterministic_and_org_scoped():
    assert normalize_org_key(" Content Catalyst LLC ") == "content-catalyst-llc"
    a = private_record_key("content-catalyst", "policy:1")
    b = private_record_key("content-catalyst", "policy:1")
    c = private_record_key("other-org", "policy:1")
    assert a == b
    assert len(a) == 64
    assert a != c


def test_access_scope_policy_requires_intersection_for_restricted_and_project_records():
    assert access_scope_allows("organization", [], []) is True
    assert access_scope_allows("restricted", ["role:editor"], ["role:editor"]) is True
    assert access_scope_allows("restricted", ["role:editor"], ["role:subscriber"]) is False
    assert access_scope_allows("project", ["project:apollo"], ["project:apollo", "role:editor"]) is True
    assert access_scope_allows("project", ["project:apollo"], ["project:zeus"]) is False


def test_restricted_and_project_packets_require_explicit_scopes():
    base = dict(record_id="r1", object_type="document", title="Private report")
    with pytest.raises(ValidationError):
        PrivateRecordPacket(**base, access_level="restricted")
    with pytest.raises(ValidationError):
        PrivateRecordPacket(**base, access_level="project", access_scopes=["role:editor"])
    row = PrivateRecordPacket(**base, access_level="project", access_scopes=["role:editor"], project_key="apollo")
    assert row.project_key == "apollo"


def test_lab_handoff_requires_data_like_record_and_explicit_analysis_permission():
    assert lab_handoff_eligible("dataset", {"analysis_allowed": True}) is True
    assert lab_handoff_eligible("csv", {"analysis_allowed": True}) is True
    assert lab_handoff_eligible("dataset", {}) is False
    assert lab_handoff_eligible("policy", {"analysis_allowed": True}) is False


def test_ingest_schema_requires_actor_organization_source_and_records():
    packet = PrivateKnowledgeIngestRequest.model_validate({
        "schema": "sc-private-organizational-knowledge-ingest/1.0",
        "actor": {"actor_id": "wordpress-user:1", "scopes": ["role:administrator"]},
        "organization": {"org_key": "Content Catalyst", "name": "Content Catalyst LLC", "metadata": {}},
        "source": {"source_key": "policy-library", "name": "Policy Library", "source_type": "internal", "metadata": {}},
        "records": [{
            "record_id": "policy:travel",
            "object_type": "policy",
            "title": "Travel Policy",
            "body_text": "Internal policy text",
            "access_level": "organization",
        }],
    })
    assert packet.organization.org_key == "content-catalyst"
    assert packet.records[0].title == "Travel Policy"


def test_private_search_request_normalizes_tenant_and_bounds_page_size():
    row = PrivateKnowledgeSearchRequest.model_validate({
        "organization_key": "Content Catalyst",
        "actor": {"actor_id": "wordpress-user:2", "scopes": ["role:editor"]},
        "q": "energy transition",
        "limit": 100,
        "offset": 3,
    })
    assert row.organization_key == "content-catalyst"
    assert row.limit == 100
    with pytest.raises(ValidationError):
        PrivateKnowledgeSearchRequest.model_validate({
            "organization_key": "content-catalyst",
            "actor": {"actor_id": "wordpress-user:2", "scopes": []},
            "limit": 101,
        })


def test_schema_uses_physically_separate_private_tables_and_version_audit_lineage():
    schema = (ROOT / "library-backend/app/schema.sql").read_text(encoding="utf-8")
    assert "CREATE TABLE IF NOT EXISTS library_private_organizations" in schema
    assert "CREATE TABLE IF NOT EXISTS library_private_sources" in schema
    assert "CREATE TABLE IF NOT EXISTS library_private_records" in schema
    assert "CREATE TABLE IF NOT EXISTS library_private_record_versions" in schema
    assert "CREATE TABLE IF NOT EXISTS library_private_ingest_events" in schema
    assert "CREATE TABLE IF NOT EXISTS library_private_access_events" in schema
    assert "UNIQUE (org_key, record_id)" in schema
    assert "access_level IN ('organization','restricted','project')" in schema


def test_public_query_module_never_references_private_tables():
    query = (ROOT / "library-backend/app/query.py").read_text(encoding="utf-8")
    assert "library_private_" not in query
    assert "library_records" in query


def test_private_service_search_sql_requires_org_and_scope_filter_and_minimizes_audit_query_storage():
    source = (ROOT / "library-backend/app/private_knowledge.py").read_text(encoding="utf-8")
    assert 'clauses = ["r.org_key=%s", self._access_sql("r")]' in source
    assert "jsonb_array_elements_text" in source
    assert '"raw_query_logged": False' in source
    assert '"raw_query_logged_to_audit": False' in source
    assert "request_fingerprint=fingerprint" in source


def test_private_routes_are_signed_and_not_public_get_data_routes():
    main = (ROOT / "library-backend/app/main.py").read_text(encoding="utf-8")
    assert '@app.get("/v1/private-organizational-knowledge")' in main
    for path in ["ingest", "search", "record", "versions", "handoff"]:
        assert f'@app.post("/v1/private-organizational-knowledge/{path}")' in main
    assert main.count("await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)") >= 8


def test_backend_identity_and_health_capabilities_are_v210():
    init = (ROOT / "library-backend/app/__init__.py").read_text(encoding="utf-8")
    main = (ROOT / "library-backend/app/main.py").read_text(encoding="utf-8")
    assert '__version__ = "2.1.0"' in init
    for capability in [
        '"private_organizational_knowledge": True',
        '"private_organization_scoping": True',
        '"private_access_scope_enforcement": True',
        '"private_version_lineage": True',
        '"private_audit_events": True',
        '"private_public_search_separation": True',
    ]:
        assert capability in main
