from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
DEV = PLUGIN / "includes" / "class-sc-library-developer-api.php"
ACTIVATOR = PLUGIN / "includes" / "class-sc-library-activator.php"
PORTABILITY = PLUGIN / "includes" / "class-sc-library-portability.php"
MAIN = PLUGIN / "sustainable-catalyst-library.php"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_version_and_bootstrap() -> None:
    main = text(MAIN)
    assert "Version: 1.18.1" in main
    assert "SC_LIBRARY_VERSION', '1.18.1'" in main
    assert "class-sc-library-developer-api.php" in main
    assert "new SC_Library_Developer_API" in main
    assert "$developer_api->register_hooks()" in main


def test_versioned_routes_and_documentation() -> None:
    dev = text(DEV)
    assert "sustainable-catalyst-library/v1" in dev
    for route in [
        "/status",
        "/records",
        "/records/(?P<id>",
        "/relationships",
        "/graph",
        "/roadmap",
        "/media/reels",
        "/media/reels/(?P<uuid>",
        "/openapi.json",
        "/schemas",
        "/protected/export-manifest",
        "/protected/reindex",
        "/protected/webhooks/test",
    ]:
        assert route in dev
    assert "sc_library_developer_portal" in dev
    assert "OpenAPI" in text(ROOT / "DEVELOPER_API_SETUP.md")


def test_key_security_and_scopes() -> None:
    dev = text(DEV)
    assert "hash_hmac('sha256', $plaintext, wp_salt('auth'))" in dev
    assert "hash_equals" in dev
    assert "X-SC-Library-Key" in dev
    assert "Authorization" in dev
    assert "scopes_json" in dev
    assert "rate_limit_per_hour" in dev
    assert "secret_hash" in text(ACTIVATOR)
    assert "plaintext key is shown once" in dev.lower()
    assert "secret_hash' =>" not in text(PORTABILITY)


def test_webhook_signature_retry_and_ssrf_boundaries() -> None:
    dev = text(DEV)
    assert "X-SC-Timestamp" in dev
    assert "X-SC-Signature" in dev
    assert "hash_hmac('sha256', $timestamp . '.' . $payload, $secret)" in dev
    assert "wp_safe_remote_post" in dev
    assert "reject_unsafe_urls" in dev
    assert "FILTER_FLAG_NO_PRIV_RANGE" in dev
    assert "wp_schedule_single_event" in dev
    assert "sc_library_deliver_webhook" in dev
    assert "redirection' => 0" in dev


def test_webhook_tables_and_event_bridges() -> None:
    activator = text(ACTIVATOR)
    for table in [
        "sc_library_api_keys",
        "sc_library_webhooks",
        "sc_library_webhook_deliveries",
    ]:
        assert table in activator
    dev = text(DEV)
    for event in [
        "record.published",
        "record.updated",
        "record.archived",
        "plan.created",
        "plan.transitioned",
        "documentation.updated",
        "graph.rebuilt",
        "workspace.revised",
        "review.transitioned",
        "review.approved",
        "book.rendered",
        "media.clip.completed",
    ]:
        assert event in dev
    assert "do_action('sc_library_workspace_revised'" in text(PLUGIN / "includes" / "class-sc-library-workspaces.php")
    assert "do_action('sc_library_review_transitioned'" in text(PLUGIN / "includes" / "class-sc-library-collaboration.php")
    assert "do_action('sc_library_document_rendered'" in text(PLUGIN / "includes" / "class-sc-library-document-production.php")
    assert "do_action('sc_library_media_clip_completed'" in text(PLUGIN / "includes" / "class-sc-library-multimedia.php")


def test_public_privacy_boundaries() -> None:
    dev = text(DEV)
    assert "status = 'publish'" in dev
    assert "visibility = 'public'" in dev
    assert "public_event_data" in dev
    for blocked in ["workspace_json", "response_json", "invitation_token", "api_key"]:
        assert blocked in dev
    portal = text(PLUGIN / "templates" / "library-developer-portal.php")
    assert "does not expose private workspaces" in portal
    assert "<iframe" not in portal.lower()


def test_openapi_and_json_schemas_are_valid_json() -> None:
    openapi = json.loads(text(ROOT / "docs" / "openapi.json"))
    assert openapi["openapi"] == "3.1.0"
    assert openapi["info"]["version"] == "1.1.0"
    assert "/records" in openapi["paths"]
    assert "/media/reels" in openapi["paths"]
    assert "/media/reels/{uuid}" in openapi["paths"]
    assert "LibraryApiKey" in openapi["components"]["securitySchemes"]
    for name in ["record", "relationship", "webhook-event", "error"]:
        schema = json.loads(text(ROOT / "docs" / "schemas" / f"{name}.json"))
        assert schema["type"] == "object"


def test_portable_schema_excludes_secrets() -> None:
    portability = text(PORTABILITY)
    assert "sc-library-portable-export/1.9" in portability
    for entity in ["api_keys", "webhooks", "webhook_deliveries"]:
        assert entity in portability
    assert "secret_exported' => false" in portability
    assert "payload_exported' => false" in portability
    assert "signature_exported' => false" in portability
    static_schema = text(ROOT / "docs" / "postgresql-schema.sql")
    assert "CREATE TABLE IF NOT EXISTS api_keys" in static_schema
    assert "CREATE TABLE IF NOT EXISTS webhooks" in static_schema
    assert "CREATE TABLE IF NOT EXISTS webhook_deliveries" in static_schema


def test_examples_do_not_contain_real_credentials() -> None:
    examples = ROOT / "docs" / "examples"
    combined = "\n".join(text(path) for path in examples.iterdir() if path.is_file())
    assert "scl_live_" not in combined
    assert "whsec_" not in combined
    assert "SC_LIBRARY_WEBHOOK_SECRET" in combined


def test_public_collections_are_bounded_and_storage_failures_are_handled() -> None:
    dev = text(DEV)
    assert "'per_page' => ['sanitize_callback' => 'absint', 'default' => 50]" in dev
    assert "array_slice($records" in dev
    assert "key-storage-failed" in dev
    assert "webhook-storage-failed" in dev
    assert "FIND_IN_SET" in dev
