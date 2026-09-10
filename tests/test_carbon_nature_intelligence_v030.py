from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-carbon-nature-intelligence.php"
JS = PLUGIN / "assets/js/sc-library-carbon-nature-v030.js"
CSS = PLUGIN / "assets/css/sc-library-carbon-nature-v030.css"
BACKEND = ROOT / "library-backend/app/main.py"
ENGINE = ROOT / "library-backend/app/carbon_nature.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_subsystem_identity_advances_without_renumbering_library_release():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "SC_CARBON_NATURE_VERSION', '0.3.0'" in main
    assert '__version__ = "2.4.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_evidence_methodology_routes_are_wired_end_to_end():
    module = text(MODULE)
    backend = text(BACKEND)
    for path in [
        '/v1/carbon-nature/evidence',
        '/v1/carbon-nature/evidence/{evidence_key}',
        '/v1/carbon-nature/methodologies',
        '/v1/carbon-nature/methodologies/{methodology_key}',
        '/v1/carbon-nature/evidence-graph',
        '/v1/carbon-nature/evidence-graph/neighborhood/{node_key}',
    ]:
        assert path in backend
    for path in [
        '/carbon-nature/evidence',
        '/carbon-nature/methodologies',
        '/carbon-nature/evidence-graph',
        '/carbon-nature/evidence-graph/neighborhood/',
    ]:
        assert path in module


def test_shortcode_uses_v030_graph_assets_and_preserves_measure_registry():
    module = text(MODULE)
    assert "public const VERSION = '0.3.0'" in module
    assert "sc-library-carbon-nature-v030" in module
    assert "Carbon Evidence & Methodology Graph" in module
    assert "Carbon Sequestration Measure Registry" in module
    assert "Graph edge ≠ proof or eligibility." in module
    assert JS.exists() and CSS.exists()


def test_health_advertises_graph_without_automatic_selection_claims():
    backend = text(BACKEND)
    assert '"carbon_nature_domain_version": "0.3.0"' in backend
    assert '"carbon_evidence_registry": True' in backend
    assert '"carbon_methodology_registry": True' in backend
    assert '"carbon_evidence_methodology_graph": True' in backend
    assert '"automatic_carbon_methodology_selection": False' in backend
    assert '"automatic_carbon_claim_validation": False' in backend


def test_engine_preserves_scientific_and_project_boundaries():
    engine = text(ENGINE)
    for phrase in [
        '"automatic_methodology_selection": False',
        '"automatic_methodology_eligibility_determination": False',
        '"automatic_claim_validation": False',
        '"quantified_sequestration_potential": False',
        '"soc_calculation_engine": False',
        '"whole_farm_ghg_calculator": False',
    ]:
        assert phrase in engine


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
