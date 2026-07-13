from app.core import WORKSPACE_SCHEMA, canonical_json, content_hash, signature, valid_workspace_schema


def test_canonical_hash_is_stable() -> None:
    left = {"schema": WORKSPACE_SCHEMA, "notes": [{"id": "a", "title": "One"}], "collections": []}
    right = {"collections": [], "notes": [{"title": "One", "id": "a"}], "schema": WORKSPACE_SCHEMA}
    assert canonical_json(left) == canonical_json(right)
    assert content_hash(left) == content_hash(right)


def test_signature_changes_with_request_context() -> None:
    first = b"PUT\n/api/v1/workspaces/example\n1700000000\n{\"a\":1}"
    changed_path = b"PUT\n/api/v1/workspaces/other\n1700000000\n{\"a\":1}"
    changed_time = b"PUT\n/api/v1/workspaces/example\n1700000001\n{\"a\":1}"
    assert signature(first, "secret") != signature(changed_path, "secret")
    assert signature(first, "secret") != signature(changed_time, "secret")


def test_supported_workspace_schema() -> None:
    assert valid_workspace_schema("sc-library-workspace/1.8")
    assert valid_workspace_schema("sc-library-workspace/1.7")
    assert valid_workspace_schema("sc-library-workspace/1.0")
    assert not valid_workspace_schema("sc-library-workspace/2.0")
