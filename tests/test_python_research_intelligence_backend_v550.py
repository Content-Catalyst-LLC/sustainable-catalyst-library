from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BRIDGE = PLUGIN / "includes/class-sc-library-python-backend.php"
BACKEND = ROOT / "library-backend"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_and_bridge_are_v550():
    main = text(MAIN)
    assert "Version: 5.5.0" in main
    assert "SC_LIBRARY_VERSION', '5.5.0'" in main
    assert "class-sc-library-python-backend.php" in main
    assert "new SC_Library_Python_Backend()" in main
    assert "$python_backend->register_hooks();" in main
    assert "public const VERSION = '5.5.0'" in text(BRIDGE)


def test_backend_has_dedicated_fastapi_postgresql_data_plane():
    main = text(BACKEND / "app/main.py")
    schema = text(BACKEND / "app/schema.sql")
    req = text(BACKEND / "requirements.txt")
    for needle in ["FastAPI", '"/health"', '"/ready"', '"/v1/ingest/records"', '"/v1/search"', '"/v1/graph/{record_id}"', '"/v1/records/{record_id}/timeline"']:
        assert needle in main
    for table in ["library_sources", "library_records", "library_record_chunks", "library_record_versions", "library_edges", "library_ingest_events"]:
        assert f"CREATE TABLE IF NOT EXISTS {table}" in schema
    assert "tsvector" in schema and "pg_trgm" in schema
    assert "psycopg[binary]" in req and "psycopg-pool" in req


def test_public_read_boundary_and_signed_ingestion_are_explicit():
    query = text(BACKEND / "app/query.py")
    security = text(BACKEND / "app/security.py")
    main = text(BACKEND / "app/main.py")
    assert "visibility='public'" in query and "publication_status='published'" in query
    assert "sign_request" in security and "hmac.new" in security
    assert "Authorization" not in main  # Header variable is named authorization; secrets are not reflected in payloads.
    assert "invalid request signature" in main


def test_wordpress_bridge_keeps_secret_server_side_and_indexes_only_published_records():
    bridge = text(BRIDGE)
    assert "never exposed to the browser" in bridge
    assert "'publish' !== $post->post_status" in bridge
    assert "before_delete_post" in bridge
    assert "transition_post_status" in bridge
    assert "hash_hmac('sha256'" in bridge
    assert "X-SC-Signature" in bridge and "X-SC-Timestamp" in bridge
    assert "sc_library_backend_api_key" in bridge
    assert "wp_localize_script" not in bridge


def test_backend_deployment_is_localhost_bound_and_reuses_sc_internal_network():
    compose = text(BACKEND / "compose.yml")
    assert '"127.0.0.1:8087:8080"' in compose
    assert "external: true" in compose and "sc-internal" in compose
    assert "sc-postgres" in text(BACKEND / ".env.example")


def test_php_files_parse():
    for path in [MAIN, BRIDGE, PLUGIN / "includes/class-sc-library-canonical-route-identity.php", PLUGIN / "includes/class-sc-library-hardening.php"]:
        result = subprocess.run(["php", "-l", str(path)], text=True, capture_output=True)
        assert result.returncode == 0, result.stderr + result.stdout
