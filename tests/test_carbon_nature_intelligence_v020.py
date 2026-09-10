from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-carbon-nature-intelligence.php"
JS = PLUGIN / "assets/js/sc-library-carbon-nature-v020.js"
CSS = PLUGIN / "assets/css/sc-library-carbon-nature-v020.css"
BACKEND = ROOT / "library-backend/app/main.py"
ENGINE = ROOT / "library-backend/app/carbon_nature.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_subsystem_identity_advances_without_renumbering_library_release():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "SC_CARBON_NATURE_VERSION', '0.2.0'" in main
    assert '__version__ = "2.3.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_measure_registry_routes_are_wired_end_to_end():
    module = text(MODULE)
    backend = text(BACKEND)
    for path in [
        "/v1/carbon-nature/measures",
        "/v1/carbon-nature/measures/compare",
        "/v1/carbon-nature/measures/{measure_key}",
    ]:
        assert path in backend
    assert "/carbon-nature/measures" in module
    assert "/carbon-nature/measures/compare" in module
    assert "/carbon-nature/measure/(?P<key>[a-z0-9-]+)" in module


def test_carbon_nature_shortcode_uses_v020_registry_assets_and_copy():
    module = text(MODULE)
    assert "public const VERSION = '0.2.0'" in module
    assert "sc_carbon_nature_intelligence" in module
    assert "sc-library-carbon-nature-v020" in module
    assert "Carbon Sequestration Measure Registry" in module
    assert "Registry entry ≠ project eligibility." in module
    assert JS.exists() and CSS.exists()


def test_interface_and_engine_preserve_measure_guardrails():
    module = text(MODULE)
    engine = text(ENGINE)
    for phrase in [
        "does not rank measures",
        "estimate tonnes of CO₂e",
        "select a methodology",
        "issue credits",
        "certify a project",
    ]:
        assert phrase in module
    assert '"automatic_measure_ranking": False' in engine
    assert '"automatic_measure_suitability_determination": False' in engine
    assert '"quantified_sequestration_potential": False' in engine
    assert '"whole_farm_ghg_calculator": False' in engine


def test_health_advertises_measure_registry_without_modeling_claims():
    backend = text(BACKEND)
    assert '"carbon_nature_domain_version": "0.2.0"' in backend
    assert '"carbon_sequestration_measure_registry": True' in backend
    assert '"carbon_measure_comparison_packets": True' in backend
    assert '"carbon_measure_research_context": True' in backend


def test_docs_preserve_subsystem_and_migration_boundary():
    doc = text(ROOT / "CARBON_NATURE_INTELLIGENCE_v0.2.0.md")
    assert "Library stays on the v5.11.x application line" in doc
    assert "No PostgreSQL migration" in doc
    assert "does **not**" in doc
    assert "rank measures" in doc


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
