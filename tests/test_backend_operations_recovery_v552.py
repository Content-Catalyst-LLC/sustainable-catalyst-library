from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BRIDGE = PLUGIN / "includes/class-sc-library-python-backend.php"
OPS = PLUGIN / "includes/class-sc-library-python-operations.php"
BACKEND = ROOT / "library-backend"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_v552_and_backend_is_v102():
    main = text(MAIN)
    bridge = text(BRIDGE)
    assert "Version: 5.5.2" in main
    assert "SC_LIBRARY_VERSION', '5.5.2'" in main
    assert "public const VERSION = '5.5.2'" in bridge
    assert '__version__ = "1.0.2"' in text(BACKEND / "app/__init__.py")


def test_operations_console_is_wired_into_wordpress():
    main = text(MAIN)
    ops = text(OPS)
    assert "class-sc-library-python-operations.php" in main
    assert "new SC_Library_Python_Operations()" in main
    assert "$python_operations->register_hooks();" in main
    for needle in [
        "Backend Operations",
        "Run integrity audit",
        "Repair %d missing/stale records",
        "Prune %d verified orphans",
        "Reindex selected records",
        "Reindex collection",
    ]:
        assert needle in ops


def test_wordpress_truth_audit_and_recovery_are_explicit():
    ops = text(OPS)
    for needle in [
        "expected_states",
        "source_updated_at",
        "/v1/admin/integrity",
        "repair_record_ids",
        "orphan_record_ids",
        "/v1/admin/prune",
        "post_id_from_record_id",
        "sc_library_backend_last_integrity_audit",
    ]:
        assert needle in ops


def test_backend_exposes_signed_operations_endpoints():
    main = text(BACKEND / "app/main.py")
    operations = text(BACKEND / "app/operations.py")
    for path in ["/v1/admin/status", "/v1/admin/integrity", "/v1/admin/prune"]:
        assert path in main
    assert main.count("authorize_write(request") >= 6
    for needle in [
        "missing_record_ids",
        "stale_record_ids",
        "orphan_record_ids",
        "chunkless_record_ids",
        "repair_record_ids",
        "recent_ingest",
    ]:
        assert needle in operations


def test_recovery_operations_are_source_scoped_and_explicit():
    operations = text(BACKEND / "app/operations.py")
    assert "WHERE r.source_key=%s" in operations
    assert "WHERE source_key=%s AND record_id = ANY(%s)" in operations
    assert '"source_key": payload.source_key' in operations


def test_ingestion_hardening_and_network_contract_are_retained():
    bridge = text(BRIDGE)
    compose = text(BACKEND / "compose.yml")
    main = text(BACKEND / "app/main.py")
    for needle in ["DEFAULT_BATCH_RECORDS = 25", "DEFAULT_TARGET_PAYLOAD_MB = 6", "send_records_resilient", "http_413_splits"]:
        assert needle in bridge
    assert '"adaptive_ingestion": True' in main
    assert '"operations_recovery": True' in main
    assert '"integrity_audit": True' in main
    assert '"127.0.0.1:8087:8080"' in compose
    assert "external: true" in compose and "sc-internal" in compose


def test_operation_ids_last_success_and_checkpoint_lineage_are_retained():
    bridge = text(BRIDGE)
    for needle in [
        "operation_id",
        "sc_library_backend_last_successful_sync",
        "finished_at",
        "sc_library_backend_sync_checkpoint",
    ]:
        assert needle in bridge


def test_no_database_schema_migration_is_required_for_v552():
    schema = text(BACKEND / "app/schema.sql")
    assert "library_ingest_events" in schema
    assert "library_operation_runs" not in schema
    assert "library_integrity_audits" not in schema


def test_php_files_parse():
    for path in [MAIN, BRIDGE, OPS]:
        result = subprocess.run(["php", "-l", str(path)], text=True, capture_output=True)
        assert result.returncode == 0, result.stderr + result.stdout
