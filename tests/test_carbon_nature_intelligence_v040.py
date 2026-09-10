from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-carbon-nature-intelligence.php"
JS = PLUGIN / "assets/js/sc-library-carbon-nature-v040.js"
CSS = PLUGIN / "assets/css/sc-library-carbon-nature-v040.css"
BACKEND = ROOT / "library-backend/app/main.py"
ENGINE = ROOT / "library-backend/app/carbon_nature.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_subsystem_identity_advances_without_renumbering_library_release():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "SC_CARBON_NATURE_VERSION', '0.4.0'" in main
    assert '__version__ = "2.5.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_project_object_and_provenance_routes_are_wired_end_to_end():
    module = text(MODULE)
    backend = text(BACKEND)
    for path in [
        '/v1/carbon-nature/project-object-model',
        '/v1/carbon-nature/project-object-types',
        '/v1/carbon-nature/project-object-types/{object_type_key}',
        '/v1/carbon-nature/provenance-event-types',
        '/v1/carbon-nature/project-packet-template',
        '/v1/carbon-nature/project-packets/validate',
    ]:
        assert path in backend
    for path in [
        '/carbon-nature/project-object-model',
        '/carbon-nature/project-object-types',
        '/carbon-nature/project-object-type/',
        '/carbon-nature/provenance-event-types',
        '/carbon-nature/project-packet-template',
    ]:
        assert path in module


def test_shortcode_uses_v040_project_assets_and_preserves_prior_explorers():
    module = text(MODULE)
    assert "public const VERSION = '0.4.0'" in module
    assert "sc-library-carbon-nature-v040" in module
    assert "Carbon Project Object Model & Provenance" in module
    assert "Evidence &amp; Methodology Graph" in module
    assert "Measure Registry" in module
    assert "Object validation ≠ scientific verification." in module
    assert JS.exists() and CSS.exists()


def test_health_advertises_object_model_without_persistence_or_claim_automation():
    backend = text(BACKEND)
    assert '"carbon_nature_domain_version": "0.4.0"' in backend
    assert '"carbon_project_object_model": True' in backend
    assert '"carbon_project_provenance_model": True' in backend
    assert '"carbon_project_packet_validation": True' in backend
    assert '"carbon_project_packet_persistence": False' in backend
    assert '"automatic_carbon_project_claim_generation": False' in backend
    assert '"automatic_carbon_project_eligibility_determination": False' in backend


def test_engine_preserves_project_scientific_and_credit_boundaries():
    engine = text(ENGINE)
    for phrase in [
        '"project_packet_persistence": False',
        '"automatic_project_claim_generation": False',
        '"automatic_project_eligibility_determination": False',
        '"cryptographic_attestation_or_signature_service": False',
        '"project_object_validation_is_not_verification": True',
        '"quantified_sequestration_potential": False',
        '"soc_calculation_engine": False',
        '"whole_farm_ghg_calculator": False',
    ]:
        assert phrase in engine


def test_validation_endpoint_uses_existing_signed_request_boundary():
    backend = text(BACKEND)
    section = backend[backend.index('@app.post("/v1/carbon-nature/project-packets/validate")'):]
    section = section[:section.index('@app.get("/v1/carbon-nature/research-context")')]
    assert "authorize_write" in section
    assert "x_sc_signature" in section
    assert "validate_project_packet" in section


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
