from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
INSTITUTIONAL = PLUGIN / "includes/class-sc-library-institutional-research-sources.php"
NETWORK = PLUGIN / "includes/class-sc-library-research-network-console.php"
HOME = PLUGIN / "includes/class-sc-library-homepage-console.php"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_and_backend_preservation():
    main = text(MAIN)
    assert "Version: 5.7.1" in main
    assert "SC_LIBRARY_VERSION', '5.7.1'" in main
    assert '__version__ = "1.2.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_jhu_network_record_is_owned_by_institutional_source_layer():
    source = text(INSTITUTIONAL)
    assert "public static function network_source(): array" in source
    assert "'id' => self::SOURCE_JHU" in source
    assert "'name' => 'Johns Hopkins Research Data Repository'" in source
    assert "'type' => 'Institutional research data'" in source
    assert "'mode' => 'LIVE METADATA'" in source
    assert "'connector' => self::SOURCE_JHU" in source
    assert "does not imply endorsement or partnership" in source


def test_research_network_consumes_canonical_jhu_record():
    network = text(NETWORK)
    assert "SC_Library_Institutional_Research_Sources::network_source()" in network
    assert "public static function source_registry(): array" in network
    # Identity stays outside the Research Network module; it consumes the canonical projection.
    assert "Johns Hopkins Research Data Repository" not in network


def test_homepage_prioritizes_jhu_without_copying_source_name():
    home = text(HOME)
    priority = "'mit', 'harvard', 'johns-hopkins-dataverse', 'ucd'"
    assert priority in home
    assert 'data-source-id="<?php echo esc_attr((string) ($row[\'id\'] ?? \'\')); ?>"' in home
    assert "Johns Hopkins Research Data Repository" not in home
    assert "SC_Library_Research_Network_Console::source_registry()" in home


def test_changed_php_modules_are_valid():
    for path in [MAIN, INSTITUTIONAL, NETWORK, HOME]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
