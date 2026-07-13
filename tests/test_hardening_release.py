from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
HARDENING = (PLUGIN / "includes/class-sc-library-hardening.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
CSS = (PLUGIN / "assets/css/sc-library-hardening.css").read_text()
JS = (PLUGIN / "assets/js/sc-library-hardening.js").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers_and_wiring():
    assert "Version: 2.0.1" in MAIN
    assert "SC_LIBRARY_VERSION', '2.0.1'" in MAIN
    assert "Stable tag: 2.0.1" in README
    assert "class-sc-library-hardening.php" in MAIN
    assert "new SC_Library_Hardening" in MAIN
    assert "$hardening->register_hooks();" in MAIN


def test_readiness_admin_routes_and_shortcode():
    assert "sc-library-readiness" in HARDENING
    assert "Production Readiness" in HARDENING
    assert "sc_library_readiness_status" in HARDENING
    for route in [
        "/library/readiness/status",
        "/library/readiness/report",
        "/library/readiness/run",
        "sustainable-catalyst-library/v1",
    ]:
        assert route in HARDENING
    assert "current_user_can('manage_options')" in HARDENING


def test_public_cache_is_bounded_and_private_routes_are_excluded():
    assert "sc_library_enable_public_cache" in HARDENING
    assert "sc_library_public_cache_ttl" in HARDENING
    assert "X-SC-Library-Cache" in HARDENING
    assert "is_user_logged_in()" in HARDENING
    assert "authorization" in HARDENING
    assert "x-sc-library-key" in HARDENING
    assert "x-wp-nonce" in HARDENING
    for fragment in ["protected", "diagnostics", "sessions", "jobs", "webhooks", "extract", "migration", "sync", "reindex"]:
        assert f"'{fragment}'" in HARDENING
    assert "save_post" in HARDENING
    assert "set_object_terms" in HARDENING
    assert "CACHE_GENERATION_OPTION" in HARDENING


def test_rate_limit_and_security_headers():
    assert "sc_library_public_rate_limit" in HARDENING
    assert "sc_library_rate_limited" in HARDENING
    assert "status' => 429" in HARDENING
    assert "X-Content-Type-Options" in HARDENING
    assert "strict-origin-when-cross-origin" in HARDENING
    assert "camera=(), microphone=(), geolocation=()" in HARDENING


def test_accessibility_and_mobile_assets():
    for marker in [
        ".sc-library-skip-link",
        ":focus-visible",
        "prefers-reduced-motion",
        "forced-colors",
        "min-block-size:var(--sc-library-touch)",
        ".sc-library-table-scroll",
        "@media(max-width:480px)",
    ]:
        assert marker in CSS
    for marker in [
        "sc-library-a11y-live",
        "aria-live",
        "Skip to Library content",
        "Scrollable data table",
        "MutationObserver",
        "button:not([type])",
    ]:
        assert marker in JS


def test_readiness_table_defaults_and_cron():
    assert "sc_library_readiness_runs" in ACTIVATOR
    assert "readiness_runs_sql" in ACTIVATOR
    assert "sc_library_hardening_daily" in ACTIVATOR
    for option in [
        "sc_library_enable_hardening",
        "sc_library_enable_public_cache",
        "sc_library_public_cache_ttl",
        "sc_library_public_rate_limit",
        "sc_library_touch_target_px",
    ]:
        assert option in ACTIVATOR


def test_readiness_diagnostics_cover_launch_domains():
    for marker in [
        "WordPress version",
        "Accessibility",
        "Mobile and responsive behavior",
        "Performance and large-library operations",
        "Security and privacy",
        "Preservation, backups, and integrity",
        "API-key secret storage",
        "Foundation PDF extraction",
        "Off-site backups",
    ]:
        assert marker in HARDENING


def test_portability_and_static_schema():
    assert "sc-library-portable-export/3.0" in PORTABILITY
    assert "readiness_runs" in PORTABILITY
    assert "export_readiness_runs" in PORTABILITY
    schema = (ROOT / "docs/postgresql-schema.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS readiness_runs" in schema
    manifest = json.loads((ROOT / "docs/portable-export-manifest.example.json").read_text())
    assert manifest["schema"] == "sc-library-portable-export/3.0"
    assert manifest["plugin_version"] == "2.0.1"
    assert "readiness_runs" in manifest["entities"]


def test_openapi_and_json_schema():
    openapi = json.loads((ROOT / "docs/openapi.json").read_text())
    assert openapi["info"]["version"] == "2.0.1"
    assert "/readiness" in openapi["paths"]
    schema = json.loads((ROOT / "docs/schemas/readiness-report.json").read_text())
    assert schema["properties"]["schema"]["const"] == "sc-library-production-readiness/1.0"


def test_release_documentation_exists():
    assert (ROOT / "ACCESSIBILITY_SECURITY_HARDENING_SETUP_v1.20.0.md").exists()
    assert (ROOT / "RELEASE_NOTES_1.20.0.md").exists()
