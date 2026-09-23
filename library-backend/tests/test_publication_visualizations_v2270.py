from __future__ import annotations

import json
import sys
import types

import httpx
import pytest

if "psycopg" not in sys.modules:
    psycopg = types.ModuleType("psycopg"); psycopg.__path__ = []
    rows = types.ModuleType("psycopg.rows"); rows.dict_row = object()
    types_pkg = types.ModuleType("psycopg.types"); types_pkg.__path__ = []
    json_pkg = types.ModuleType("psycopg.types.json")
    class Jsonb:
        def __init__(self, value): self.value = value
    json_pkg.Jsonb = Jsonb
    pool_pkg = types.ModuleType("psycopg_pool")
    class ConnectionPool: pass
    pool_pkg.ConnectionPool = ConnectionPool
    sys.modules.update({
        "psycopg": psycopg,
        "psycopg.rows": rows,
        "psycopg.types": types_pkg,
        "psycopg.types.json": json_pkg,
        "psycopg_pool": pool_pkg,
    })

from app.platform_core import PlatformCoreClient
from app.publication_visualizations import (
    VisualizationBuildRequest,
    VisualizationCoreHandoffRequest,
    _base_spec,
    _core_visual_payload,
)


def sample_record():
    return {
        "record_id": "wordpress:1:post:1621",
        "title": "Sustainable Systems Study",
        "canonical_url": "https://example.test/study",
        "content_hash": "a" * 64,
    }


def test_build_request_rejects_unknown_visualization_kind():
    with pytest.raises(ValueError):
        VisualizationBuildRequest(record_id="pub:1", kinds=["truth-map"])


def test_renderer_neutral_spec_keeps_research_boundaries():
    spec = _base_spec(
        sample_record(),
        "concept-map",
        "Concept Map",
        "Reviewed concepts",
        [{"id": "pub:1", "kind": "publication", "label": "Paper", "root": True}],
        [],
    )
    assert spec["schema"] == "sc-library-publication-visualization-spec/1.0"
    assert spec["renderer_neutral"] is True
    assert spec["boundaries"]["inferred_edges"] is False
    assert spec["boundaries"]["inferred_truth"] is False
    assert spec["boundaries"]["automatic_claim_promotion"] is False
    assert spec["provenance"]["governed_visual_reasoning_authority"] == "platform-core"


def test_core_visual_payload_requires_published_human_review_state():
    viz = {
        "visualization_id": 9,
        "visualization_key": "b" * 64,
        "record_id": "wordpress:1:post:1621",
        "visualization_kind": "citation-network",
        "title": "Citation Network",
        "description": "Declared citations",
        "source_content_hash": "a" * 64,
        "spec_hash": "c" * 64,
        "review_state": "draft",
        "reviewer": None,
        "specification": {"renderer_neutral": True},
    }
    req = VisualizationCoreHandoffRequest(visualization_id=9, core_project_id="entity:research-project:abc")
    with pytest.raises(ValueError, match="human-reviewed and published"):
        _core_visual_payload(viz, req)


def test_core_visual_payload_uses_cross_product_visual_research_contract_without_truth_claims():
    viz = {
        "visualization_id": 9,
        "visualization_key": "b" * 64,
        "record_id": "wordpress:1:post:1621",
        "visualization_kind": "citation-network",
        "title": "Citation Network",
        "description": "Declared citations",
        "source_content_hash": "a" * 64,
        "spec_hash": "c" * 64,
        "review_state": "published",
        "reviewer": "reviewer",
        "specification": {"renderer_neutral": True, "nodes": [], "edges": []},
    }
    req = VisualizationCoreHandoffRequest(visualization_id=9, core_project_id="entity:research-project:abc")
    payload = _core_visual_payload(viz, req)
    data = payload["data"]
    assert data["project_entity_id"] == "entity:research-project:abc"
    assert data["object_kind"] == "research-composite"
    assert data["source_products"] == ["knowledge-library"]
    assert data["metadata"]["automatic_truth_promotion"] is False
    assert data["provenance"]["visualization_contract"] == "sc-library-publication-visualization-spec/1.0"


def test_core_visual_operation_is_allowlisted_and_posts_expected_envelope():
    seen = []
    def transport(request: httpx.Request) -> httpx.Response:
        seen.append((request.method, request.url.path, json.loads(request.content.decode("utf-8"))))
        return httpx.Response(201, json={"id": "visual:1"})
    client = PlatformCoreClient(
        base_url="https://core.example.test",
        write_api_key="write-key",
        transport=httpx.MockTransport(transport),
    )
    response = client.execute("visual-research-object.create", {"data": {"project_entity_id": "entity:1", "object_key": "x", "name": "X"}})
    assert response.status_code == 201
    assert seen == [("POST", "/v1/cross-product-visual-research", {"data": {"project_entity_id": "entity:1", "object_key": "x", "name": "X"}})]
