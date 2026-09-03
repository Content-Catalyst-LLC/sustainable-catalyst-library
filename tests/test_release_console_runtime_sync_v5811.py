from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
CONSOLE = (PLUGIN / "includes/class-sc-library-homepage-console.php").read_text()
JS = (PLUGIN / "assets/js/sc-library-homepage-console-v561.js").read_text()
CSS = (PLUGIN / "assets/css/sc-library-homepage-console-v561.css").read_text()
BACKEND_VERSION = (ROOT / "library-backend/app/__init__.py").read_text()


def test_release_identity_is_v5811_and_backend_stays_v140():
    assert "Version: 5.8.1.1" in MAIN
    assert "define('SC_LIBRARY_VERSION', '5.8.1.1');" in MAIN
    assert '__version__ = "1.4.0"' in BACKEND_VERSION


def test_console_preserves_module_provenance_but_uses_canonical_runtime_version():
    assert "public const VERSION = '5.7.1';" in CONSOLE
    assert "public static function runtime_version(): string" in CONSOLE
    assert "defined('SC_LIBRARY_VERSION') ? (string) SC_LIBRARY_VERSION : self::VERSION" in CONSOLE
    assert "'version' => self::runtime_version()" in CONSOLE
    assert "data-library-version=\"<?php echo esc_attr(self::runtime_version()); ?>\"" in CONSOLE


def test_runtime_release_route_reports_library_backend_and_sync_state():
    assert "'/runtime/release'" in CONSOLE
    assert "'schema' => 'sc-library-runtime-release/1.0'" in CONSOLE
    assert "'installed_version' => $installed_version" in CONSOLE
    assert "'synchronized' => $installed_version === $library_version" in CONSOLE
    assert "'backend' => [" in CONSOLE
    assert "'version' => isset($backend['version'])" in CONSOLE
    assert "Cache-Control', 'no-store, max-age=0'" in CONSOLE


def test_console_visibly_separates_library_and_backend_versions():
    assert "data-sc-home-library-version" in CONSOLE
    assert "data-sc-home-backend-version" in CONSOLE
    assert "LIBRARY:" in CONSOLE
    assert "BACKEND:" in CONSOLE
    assert "runtimeUrl" in CONSOLE


def test_browser_runtime_check_is_no_store_and_detects_drift():
    assert "const loadRuntimeRelease" in JS
    assert "cache: 'no-store'" in JS
    assert "data?.library?.version" in JS
    assert "data?.backend?.version" in JS
    assert "has-release-drift" in JS
    assert "loadRuntimeRelease(root);" in JS
    assert ".has-release-drift" in CSS


def test_release_patch_does_not_relabel_fda_or_biomedical_module_origins():
    fda = (PLUGIN / "includes/class-sc-library-fda-regulatory-intelligence.php").read_text()
    biomedical = (PLUGIN / "includes/class-sc-library-biomedical-evidence.php").read_text()
    institutional = (PLUGIN / "includes/class-sc-library-institutional-research-sources.php").read_text()
    assert "public const VERSION = '5.8.1';" in fda
    assert "public const VERSION = '5.8.0';" in biomedical
    assert "public const VERSION = '5.7.1';" in institutional
