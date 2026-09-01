from app.chunking import ensure_record_chunks, server_chunks
from app.models import RecordPacket


def make_record(body: str, chunks=None) -> RecordPacket:
    return RecordPacket(
        record_id="wordpress:1:post:1",
        source_key="wordpress-main",
        object_type="post",
        title="Test record",
        body_text=body,
        chunks=chunks or [],
    )


def test_server_chunks_match_wordpress_v1_contract():
    body = "Alpha\n\nBeta " + ("x" * 6100)
    chunks = server_chunks(body)
    assert len(chunks) == 2
    assert chunks[0].ordinal == 0
    assert len(chunks[0].text) == 6000
    assert chunks[0].metadata == {"chunker": "wordpress-text-v1"}
    assert chunks[1].ordinal == 1


def test_compact_record_gets_deterministic_server_chunks():
    body = "one   two\nthree " + ("z" * 7000)
    record = make_record(body)
    hydrated = ensure_record_chunks(record)
    expected = server_chunks(body)
    assert hydrated.body_text == body
    assert [c.model_dump() for c in hydrated.chunks] == [c.model_dump() for c in expected]


def test_existing_client_chunks_are_preserved():
    existing = server_chunks("already chunked")
    record = make_record("already chunked", existing)
    hydrated = ensure_record_chunks(record)
    assert [c.model_dump() for c in hydrated.chunks] == [c.model_dump() for c in existing]
