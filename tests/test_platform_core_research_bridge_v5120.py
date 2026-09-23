from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BACKEND = ROOT / "library-backend"
CORE = BACKEND / "app/platform_core.py"
SCHEMA = BACKEND / "app/schema.sql"
SETTINGS = BACKEND / "app/settings.py"
BRIDGE = PLUGIN / "includes/class-sc-library-python-backend.php"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_monotonic_v5120_backend_v2230():
    main = text(MAIN)
    assert "SC_LIBRARY_VERSION" in main


def test_core_bridge_is_real_and_not_a_generic_proxy():
    core = text(CORE)
    for operation in [
        "research-object.create",
        "exchange-package.create",
        "scholarly-package.create",
        "runtime-contract.create",
    ]:
        assert operation in core
    assert "CORE_OPERATIONS" in core
    assert "arbitrary" not in text(ROOT / "PLATFORM_CORE_RESEARCH_BRIDGE_v5.12.0.md").lower().split("arbitrary core urls are not accepted")[0]
    assert "X-SC-API-Key" in core
    assert "automatic_truth_promotion" in core
    assert '"automatic_truth_promotion": False' in core
    assert '"raw_chunks_remain_library_local": True' in core


def test_core_v330_capability_surfaces_are_probed():
    core = text(CORE)
    for route in [
        "/v1/research-objects/readiness",
        "/v1/research/lineage/readiness",
        "/v1/research/intelligence/readiness",
        "/v1/exchange/readiness",
        "/v1/research/scholarly-packages/readiness",
        "/v1/research/runtime-contract/readiness",
        "/v1/visual-runtime/unified/readiness",
        "/v1/analytics/statistical-reasoning/readiness",
    ]:
        assert route in core


def test_postgresql_binding_and_outbox_are_additive_and_idempotent():
    schema = text(SCHEMA)
    assert "CREATE TABLE IF NOT EXISTS library_core_bindings" in schema
    assert "UNIQUE(library_record_id, core_object_id)" in schema
    assert "CREATE TABLE IF NOT EXISTS library_core_sync_outbox" in schema
    assert "idempotency_key varchar(128) NOT NULL UNIQUE" in schema
    assert "FOR UPDATE SKIP LOCKED" in text(CORE)


def test_core_secret_is_backend_only_and_wordpress_only_reads_bridge_readiness():
    settings = text(SETTINGS)
    bridge = text(BRIDGE)
    assert "SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY" in settings
    assert "SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY" not in bridge
    assert "/v1/platform-core/readiness" in bridge
    assert "/backend/platform-core/readiness" in bridge
    assert "manage_options" in bridge


def test_python_and_php_parse():
    result = subprocess.run(["python", "-m", "compileall", "-q", str(BACKEND / "app")], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    for path in [MAIN, BRIDGE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
