from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BRIDGE = PLUGIN / "includes/class-sc-library-python-backend.php"
BACKEND = ROOT / "library-backend"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_v551_and_backend_is_v101():
    main = text(MAIN)
    bridge = text(BRIDGE)
    assert "Version: 5.5.1" in main
    assert "SC_LIBRARY_VERSION', '5.5.1'" in main
    assert "public const VERSION = '5.5.1'" in bridge
    assert '__version__ = "1.0.1"' in text(BACKEND / "app/__init__.py")


def test_wordpress_ingestion_is_payload_aware_and_adaptive():
    bridge = text(BRIDGE)
    for needle in [
        "DEFAULT_BATCH_RECORDS = 25",
        "DEFAULT_TARGET_PAYLOAD_MB = 6",
        "target_payload_bytes",
        "encoded_payload",
        "strlen($body)",
        "send_records_resilient",
        "preflight_splits",
        "http_413_splits",
        "split_posts",
        "413 === $status",
    ]:
        assert needle in bridge
    assert "array_chunk($ids, 100)" in bridge  # seed groups only; leaf batches are adaptive.


def test_retries_are_bounded_and_413_is_split_not_blindly_retried():
    bridge = text(BRIDGE)
    assert "DEFAULT_RETRY_ATTEMPTS = 2" in bridge
    assert "[408, 425, 429, 500, 502, 503, 504]" in bridge
    assert "usleep(250000 * $attempt)" in bridge
    assert "HTTP 413 is split instead of retried" in bridge


def test_sync_reporting_and_resume_checkpoint_are_explicit():
    bridge = text(BRIDGE)
    for needle in [
        "'completed' => 0",
        "'changed' => 0",
        "'unchanged' => 0",
        "'failed' => 0",
        "'requests' => 0",
        "'splits' => 0",
        "'retries' => 0",
        "'payload_bytes_sent' => 0",
        "'largest_payload_bytes' => 0",
        "failed_record_ids",
        "sc_library_backend_sync_checkpoint",
        "handle_resume_sync",
        "Resume failed records",
    ]:
        assert needle in bridge


def test_backend_reports_limits_and_returns_them_on_413():
    main = text(BACKEND / "app/main.py")
    assert '"adaptive_ingestion": True' in main
    assert '"server_chunk_fallback": True' in main
    assert '"ingest_limits"' in main
    assert '"X-SC-Max-Body-Bytes"' in main
    assert '"X-SC-Max-Batch-Records"' in main
    assert "request body exceeds configured limit" in main
    assert "record batch exceeds configured maximum" in main


def test_compact_single_record_fallback_preserves_chunkability():
    bridge = text(BRIDGE)
    repo = text(BACKEND / "app/repository.py")
    chunking = text(BACKEND / "app/chunking.py")
    assert "compact_single_record_packets" in bridge
    assert "'chunks' => $compact ? [] : self::chunks($body)" in bridge
    assert "ensure_record_chunks(record)" in repo
    assert 'metadata={"chunker": "wordpress-text-v1"}' in chunking
    assert "size = 6000" in chunking


def test_localhost_binding_and_shared_network_are_unchanged():
    compose = text(BACKEND / "compose.yml")
    assert '"127.0.0.1:8087:8080"' in compose
    assert "external: true" in compose and "sc-internal" in compose


def test_php_files_parse():
    for path in [MAIN, BRIDGE]:
        result = subprocess.run(["php", "-l", str(path)], text=True, capture_output=True)
        assert result.returncode == 0, result.stderr + result.stdout
