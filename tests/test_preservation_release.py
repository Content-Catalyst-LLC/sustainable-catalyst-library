from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
PRESERVATION = (PLUGIN / "includes/class-sc-library-preservation.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
DEVELOPER = (PLUGIN / "includes/class-sc-library-developer-api.php").read_text()
README = (PLUGIN / "readme.txt").read_text()
CSS = (PLUGIN / "assets/css/sc-library-preservation.css").read_text()
JS = (PLUGIN / "assets/js/sc-library-preservation.js").read_text()


def test_release_markers_and_bootstrap():
    assert "Version: 1.20.0" in MAIN
    assert "SC_LIBRARY_VERSION', '1.20.0'" in MAIN
    assert "Stable tag: 1.20.0" in README
    assert "class-sc-library-preservation.php" in MAIN
    assert "new SC_Library_Preservation" in MAIN
    assert "$preservation->register_hooks()" in MAIN


def test_preservation_schemas_and_tables():
    for marker in [
        "sc-library-preservation/1.0",
        "sc-library-preservation-manifest/1.0",
        "sc-library-integrity-audit/1.0",
    ]:
        assert marker in PRESERVATION
    for table in [
        "sc_library_preservation_snapshots",
        "sc_library_integrity_checks",
        "sc_library_authority_history",
    ]:
        assert table in ACTIVATOR
    assert "FULLTEXT KEY sc_library_snapshot_search" in ACTIVATOR
    assert "UNIQUE KEY snapshot_uuid" in ACTIVATOR


def test_immutable_snapshot_and_checksum_model():
    for marker in [
        "hash('sha256', $encoded)",
        "manifest_hash",
        "source_hash",
        "supersedes_uuid",
        "is_current",
        "legal_hold",
        "retention_until",
        "canonical_payload",
        "build_manifest",
    ]:
        assert marker in PRESERVATION
    assert "UPDATE ' . self::snapshots_table() . ' SET is_current = 0" in PRESERVATION
    assert "current snapshot" in PRESERVATION.lower()


def test_snapshot_privacy_and_canonical_boundaries():
    assert "$post->post_status === 'publish'" in PRESERVATION
    assert "post_password_required" in PRESERVATION
    assert "return false;" in PRESERVATION
    for blocked in ["secret", "token", "password", "api_key", "private", "internal"]:
        assert blocked in PRESERVATION
    assert "WordPress remains canonical" in PRESERVATION


def test_integrity_audit_is_bounded_and_resumable():
    assert "sc_library_integrity_state" in PRESERVATION
    assert "process_integrity_batch" in PRESERVATION
    assert "LIMIT %d" in PRESERVATION
    assert "cursor" in PRESERVATION
    assert "phase" in PRESERVATION
    assert "records" in PRESERVATION
    assert "relationships" in PRESERVATION
    assert "complete_with_errors" in PRESERVATION


def test_integrity_checks_cover_required_failures():
    for marker in [
        "snapshot_presence",
        "canonical_checksum",
        "attachment_file",
        "supersession_chain",
        "authority_url",
        "content_link",
        "relationship_targets",
        "wp_safe_remote_head",
        "wp_safe_remote_get",
        "FILTER_FLAG_NO_PRIV_RANGE",
    ]:
        assert marker in PRESERVATION


def test_retention_and_hold_controls_are_protective():
    assert "_sc_library_retention_until" in PRESERVATION
    assert "_sc_library_legal_hold" in PRESERVATION
    assert "is_current = 0 AND legal_hold = 0" in PRESERVATION
    assert "confirm_purge" in PRESERVATION
    assert "Purge expired unprotected snapshots" in PRESERVATION


def test_authority_history_and_supersession():
    assert "capture_authority_history" in PRESERVATION
    for marker in [
        "_sc_library_doc_authority_type",
        "_sc_library_doc_authority_url",
        "_sc_library_doc_supersedes_id",
        "_sc_library_doc_superseded_by_id",
    ]:
        assert marker in PRESERVATION
    assert "authority_uuid" in PRESERVATION


def test_public_archive_shortcodes_and_version_compare():
    assert "sc_library_institutional_archive" in PRESERVATION
    assert "sc_library_integrity_status" in PRESERVATION
    assert "Frozen historical edition" in PRESERVATION
    assert "Open current canonical record" in PRESERVATION
    assert "Version comparison" in PRESERVATION
    assert "wp_text_diff" in PRESERVATION
    assert "sc-library-archive__content" in CSS
    assert "@media print" in CSS
    assert "<iframe" not in PRESERVATION.lower()


def test_rest_openapi_and_json_schema():
    for marker in [
        "/preservation/status",
        "/archive",
        "/archive/(?P<uuid>",
        "/manifest",
        "/preservation/diagnostics",
    ]:
        assert marker in PRESERVATION
    openapi = json.loads((ROOT / "docs/openapi.json").read_text())
    schema = json.loads((ROOT / "docs/schemas/preservation-snapshot.json").read_text())
    assert openapi["info"]["version"] == "1.3.0"
    assert "/archive" in openapi["paths"]
    assert "/archive/{uuid}/manifest" in openapi["paths"]
    assert schema["properties"]["schema"]["const"] == "sc-library-preservation/1.0"


def test_webhook_events_are_bridged():
    for event in ["preservation.snapshot.created", "integrity.audit.completed"]:
        assert event in DEVELOPER
    assert "sc_library_preservation_snapshot_created" in DEVELOPER
    assert "sc_library_integrity_audit_completed" in DEVELOPER


def test_portable_schema_and_entities():
    assert "sc-library-portable-export/2.1" in PORTABILITY
    for entity in ["preservation_snapshots", "integrity_checks", "authority_history"]:
        assert entity in PORTABILITY
    static_schema = (ROOT / "docs/postgresql-schema.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS preservation_snapshots" in static_schema
    assert "CREATE TABLE IF NOT EXISTS integrity_checks" in static_schema
    assert "CREATE TABLE IF NOT EXISTS authority_history" in static_schema


def test_release_docs_and_assets_exist():
    assert (ROOT / "PRESERVATION_ARCHIVE_SETUP_v1.19.0.md").exists()
    assert (ROOT / "RELEASE_NOTES_1.19.0.md").exists()
    assert (PLUGIN / "assets/css/sc-library-preservation.css").exists()
    assert (PLUGIN / "assets/js/sc-library-preservation.js").exists()
    assert "navigator.clipboard" in JS
