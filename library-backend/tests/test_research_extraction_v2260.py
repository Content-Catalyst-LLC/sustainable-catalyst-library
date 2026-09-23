from __future__ import annotations

import json
import sys
import types

import httpx
import pytest
from pydantic import ValidationError

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
from app.research_extraction import (
    CandidatePromotionRequest,
    ExtractionRequest,
    Segment,
    _core_payload,
    _metadata_entities,
    _sentence_candidates,
    extraction_readiness,
)


def test_metadata_entities_are_explicit_candidates_not_governed_objects():
    record = {
        "record_id": "pub:1",
        "authors": ["Jane Doe"],
        "topics": ["Sustainable Energy"],
        "tags": ["Climate Policy"],
        "identifiers": {"doi": "10.1234/example"},
    }
    items = list(_metadata_entities(record))
    assert {x["entity_type"] for x in items} >= {"person", "concept", "identifier"}
    assert all(x["candidate_type"] == "entity" for x in items)
    assert all(x["metadata"]["machine_generated"] is True for x in items)
    assert all(x["metadata"]["governed"] is False for x in items)


def test_finding_and_claim_candidates_keep_source_spans():
    text = (
        "Our results show that annual demand declined by 12 percent. "
        "The paper argues that institutional design affects adoption."
    )
    seg = [Segment(locator="chunk:3:Results", text=text, source_chunk_ordinal=3)]
    findings = list(_sentence_candidates("pub:1", seg, "finding"))
    claims = list(_sentence_candidates("pub:1", seg, "claim"))
    assert len(findings) == 1
    assert len(claims) == 1
    assert findings[0]["source_locator"] == "chunk:3:Results"
    assert findings[0]["source_chunk_ordinal"] == 3
    assert findings[0]["char_end"] > findings[0]["char_start"]
    assert claims[0]["metadata"]["truth_determined"] is False


def test_extraction_request_rejects_unknown_candidate_type():
    with pytest.raises(ValidationError):
        ExtractionRequest(record_id="pub:1", candidate_types=["truth"])


def test_core_payload_requires_governed_project_but_does_not_assert_truth():
    candidate = {
        "candidate_id": 7,
        "candidate_key": "a" * 64,
        "record_id": "pub:1",
        "candidate_type": "claim",
        "candidate_text": "The paper argues that design affects adoption.",
        "source_locator": "abstract",
        "source_chunk_ordinal": None,
        "char_start": 0,
        "char_end": 47,
        "source_content_hash": "b" * 64,
        "extraction_method": "rule-based",
        "confidence": 0.74,
        "review_state": "accepted",
        "reviewer": "reviewer@example.test",
    }
    req = CandidatePromotionRequest(candidate_id=7, core_project_id="entity:research-project:abc")
    operation, payload = _core_payload(candidate, req)
    assert operation == "research-claim.create"
    assert payload["project_id"] == "entity:research-project:abc"
    assert payload["data"]["status"] == "proposed"
    assert payload["data"]["polarity"] == "not_applicable"
    assert payload["data"]["generate_claim_by_core"] is False
    assert payload["data"]["infer_truth_by_core"] is False
    assert payload["data"]["provenance"]["human_review_state"] == "accepted"


def test_entity_candidates_are_not_misrepresented_as_core_findings_or_claims():
    candidate = {
        "candidate_id": 8,
        "candidate_key": "c" * 64,
        "record_id": "pub:1",
        "candidate_type": "entity",
        "candidate_text": "University College Dublin",
        "source_locator": "metadata:authors:0",
        "source_chunk_ordinal": None,
        "char_start": 0,
        "char_end": 25,
        "source_content_hash": "d" * 64,
        "extraction_method": "metadata",
        "confidence": 0.98,
        "review_state": "accepted",
        "reviewer": "operator",
    }
    req = CandidatePromotionRequest(candidate_id=8, core_project_id="entity:research-project:abc")
    with pytest.raises(ValueError, match="entity candidates remain Library-owned"):
        _core_payload(candidate, req)


def test_platform_core_dynamic_finding_claim_paths_are_allowlisted_and_wrapped():
    seen = []
    def transport(request: httpx.Request) -> httpx.Response:
        seen.append((request.method, request.url.path, json.loads(request.content.decode("utf-8"))))
        return httpx.Response(201, json={"id": "core:created:1"})
    client = PlatformCoreClient(
        base_url="https://core.example.test",
        write_api_key="write-key",
        transport=httpx.MockTransport(transport),
    )
    response = client.execute(
        "research-finding.create",
        {"project_id": "entity:research-project:abc", "data": {"finding_key": "x", "title": "T", "statement": "S"}},
    )
    assert response.status_code == 201
    assert seen[0][0] == "POST"
    assert seen[0][1] == "/v1/research/intelligence/projects/entity:research-project:abc/findings"
    assert seen[0][2] == {"data": {"finding_key": "x", "title": "T", "statement": "S"}}


def test_dynamic_core_operation_rejects_unbounded_payload_shape():
    client = PlatformCoreClient(base_url="https://core.example.test", write_api_key="write-key", transport=httpx.MockTransport(lambda r: httpx.Response(200, json={})))
    with pytest.raises(RuntimeError, match="only project_id and data"):
        client.execute("research-claim.create", {"project_id": "entity:1", "data": {}, "url": "https://evil.example"})
