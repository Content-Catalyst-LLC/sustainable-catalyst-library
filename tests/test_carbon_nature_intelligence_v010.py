from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-carbon-nature-intelligence.php"
JS = PLUGIN / "assets/js/sc-library-carbon-nature-v010.js"
CSS = PLUGIN / "assets/css/sc-library-carbon-nature-v010.css"
BACKEND = ROOT / "library-backend/app/main.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_subsystem_identity_does_not_renumber_library_release():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "SC_CARBON_NATURE_VERSION', '0.1.0'" in main
    assert '__version__ = "2.2.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_carbon_nature_module_shortcode_and_assets_are_wired():
    main = text(MAIN)
    module = text(MODULE)
    assert "class-sc-library-carbon-nature-intelligence.php" in main
    assert "SC_Library_Carbon_Nature_Intelligence" in main
    assert "sc_carbon_nature_intelligence" in module
    assert JS.exists() and CSS.exists()


def test_wordpress_proxy_surface_matches_backend_routes():
    module = text(MODULE)
    backend = text(BACKEND)
    for path in ["/v1/carbon-nature", "/v1/carbon-nature/concepts", "/v1/carbon-nature/relationships", "/v1/carbon-nature/research-context"]:
        assert path in module
        assert path in backend
    assert "/v1/carbon-nature/concepts/{concept_key}" in backend


def test_interface_states_foundation_guardrails():
    module = text(MODULE)
    for phrase in ["Knowledge foundation, not a carbon-credit calculator.", "do not establish project eligibility", "SOC calculation", "AFOLU Research Librarian reasoning"]:
        assert phrase in module


def test_health_advertises_capability_without_clinical_or_credit_automation_claims():
    backend = text(BACKEND)
    assert '"carbon_nature_intelligence": True' in backend
    assert '"carbon_nature_domain_version": "0.1.0"' in backend
    assert '"afolu_knowledge_foundation": True' in backend
    assert '"nature_based_solutions_knowledge_foundation": True' in backend


def test_docs_preserve_subsystem_release_boundary():
    doc = text(ROOT / "CARBON_NATURE_INTELLIGENCE_v0.1.0.md")
    assert "Library remains on the v5.11.x application line" in doc
    assert "No PostgreSQL migration" in doc
    assert "does not issue credits" in doc


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
