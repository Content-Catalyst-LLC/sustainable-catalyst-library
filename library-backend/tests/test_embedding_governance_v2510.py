from types import SimpleNamespace

from app.embedding_governance import (
    EMBEDDING_HANDOFF_SCHEMA,
    EMBEDDING_REPRESENTATION_SCHEMA,
    EMBEDDING_SPECIFICATION_SCHEMA,
    build_workspace_handoff_payload,
    current_embedding_specification,
    representation_metadata,
)


def client():
    return SimpleNamespace(provider="gemini", model="gemini-embedding-2", dimensions=768, configured=True)


def record():
    return {
        "record_id": "publication:test-1",
        "content_hash": "a" * 64,
        "title": "A scientific publication",
        "abstract": "An abstract.",
        "body_text": "Body text.",
        "topics": ["sustainability"],
        "tags": ["research"],
    }


def test_embedding_specification_is_deterministic_and_governed():
    first = current_embedding_specification(client())
    second = current_embedding_specification(client())
    assert first == second
    assert first["schema"] == EMBEDDING_SPECIFICATION_SCHEMA
    assert len(first["fingerprint_sha256"]) == 64
    assert first["normalization"] == "l2-unit"
    assert first["similarity_metric"] == "cosine"
    assert first["governance"]["platform_core_owns_governed_representation_contracts"] is True
    assert first["governance"]["embedding_is_evidence"] is False
    assert first["governance"]["similarity_is_truth"] is False
    assert first["governance"]["automatic_platform_core_promotion"] is False


def test_representation_id_is_content_and_specification_bound():
    spec = current_embedding_specification(client())
    first = representation_metadata(
        record_id="publication:test-1",
        content_hash="a" * 64,
        input_hash="b" * 64,
        specification=spec,
        execution_target="local",
        execution_id="run:1",
    )
    second = representation_metadata(
        record_id="publication:test-1",
        content_hash="a" * 64,
        input_hash="b" * 64,
        specification=spec,
        execution_target="workspace",
        execution_id="workspace:1",
    )
    assert first["schema"] == EMBEDDING_REPRESENTATION_SCHEMA
    assert first["representation_id"] == second["representation_id"]
    assert first["execution"]["target"] == "local"
    assert second["execution"]["target"] == "workspace"
    assert second["execution"]["executed_by_workspace"] is True
    assert first["guardrails"]["embedding_is_evidence"] is False
    assert first["guardrails"]["automatic_core_promotion"] is False


def test_workspace_handoff_is_idempotent_and_does_not_change_authority():
    spec = current_embedding_specification(client())
    text = "Title: A scientific publication\nAbstract: An abstract."
    first = build_workspace_handoff_payload(job_id=42, record=record(), input_text=text, specification=spec)
    second = build_workspace_handoff_payload(job_id=42, record=record(), input_text=text, specification=spec)
    assert first == second
    assert first["schema"] == EMBEDDING_HANDOFF_SCHEMA
    assert first["workload"] == "scientific-embedding-compute"
    assert first["source"]["library_job_id"] == 42
    assert len(first["source"]["input_hash"]) == 64
    assert first["requested_output"]["normalized"] is True
    assert first["authority"]["workspace_role"] == "compute-executor"
    assert first["authority"]["platform_core_role"] == "governed-representation-contract-and-cross-product-exchange"
    assert first["guardrails"]["embedding_is_truth"] is False
    assert first["guardrails"]["automatic_platform_core_promotion"] is False
