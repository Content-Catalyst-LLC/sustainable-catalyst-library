from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"


def read(rel: str) -> str:
    return (PLUGIN / rel).read_text(encoding="utf-8")


def test_release_identity_is_52401():
    main = read("sustainable-catalyst-library.php")
    assert "Version: 5.24.0.1" in main
    assert "define('SC_LIBRARY_VERSION', '5.24.0.1');" in main


def test_readiness_uses_current_release_not_historical_version_lock():
    hardening = read("includes/class-sc-library-hardening.php")
    assert "Current release certification and readiness" in hardening
    assert "Release/runtime alignment" in hardening
    assert "Plugin runtime: %s" in hardening
    assert "5.3.0 release version alignment" not in hardening
    assert "Reinstall the complete v5.3.0 package" not in hardening
    assert "SC_LIBRARY_VERSION === self::BRANCH_VERSION" not in hardening


def test_canonical_identity_treats_component_version_as_lineage():
    identity = read("includes/class-sc-library-canonical-route-identity.php")
    assert "'component_version'" in identity
    assert "'component_compatible'" in identity
    assert "version_compare($version, '4.3.27', '>=')" in identity
    assert "self::VERSION === $version" not in identity


def test_connected_public_soak_is_compatible_with_newer_release():
    connected = read("includes/class-sc-library-connected-public-research-infrastructure.php")
    assert "version_compare( SC_LIBRARY_VERSION, self::VERSION, '>=' )" in connected
    assert "self::VERSION === SC_LIBRARY_VERSION" not in connected


def test_soak_gate_uses_registered_scenario_count_and_current_endpoint_wording():
    hardening = read("includes/class-sc-library-hardening.php")
    assert "$soak_count > 0" in hardening
    assert "0 === $soak_failed" in hardening
    assert "every registered scenario" in hardening
    assert "current connected-public-research soak details endpoint" in hardening
    assert "scenario_count'] ?? 0) === 10" not in hardening


def test_wp_cron_disabled_is_advisory_not_release_failure():
    hardening = read("includes/class-sc-library-hardening.php")
    assert "$wp_cron_disabled ? 'info' : 'ready'" in hardening
    assert "scheduled Library jobs require a real system cron" in hardening


def test_current_identity_health_endpoint_is_referenced():
    hardening = read("includes/class-sc-library-hardening.php")
    assert "/sc-library/v1/runtime/identity-health" in hardening
    assert "v5.4.0 identity-health endpoint" not in hardening
