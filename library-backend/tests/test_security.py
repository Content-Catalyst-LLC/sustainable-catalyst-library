from app.security import sha256_hex, sign_request, valid_timestamp


def test_sha256_is_deterministic():
    assert sha256_hex(b"library") == "b718f1354f7247312eca086d9a024afe5fa717ddea5adeddd6f12bcf945b2e8c"


def test_request_signature_binds_method_path_timestamp_and_body():
    key = "test-secret"
    timestamp = "1788300000"
    body = b'{"hello":"world"}'
    first = sign_request("POST", "/v1/ingest/records", timestamp, body, key)
    assert len(first) == 64
    assert first != sign_request("POST", "/v1/ingest/edges", timestamp, body, key)
    assert first != sign_request("POST", "/v1/ingest/records", timestamp, b"{}", key)


def test_timestamp_window_is_bounded():
    assert valid_timestamp("1000", 300, now=1200)
    assert not valid_timestamp("1000", 300, now=1401)
    assert not valid_timestamp("bad", 300, now=1000)
