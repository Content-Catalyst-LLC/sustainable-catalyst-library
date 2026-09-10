from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-carbon-nature-intelligence.php"
JS = PLUGIN / "assets/js/sc-library-carbon-nature-v050.js"
CSS = PLUGIN / "assets/css/sc-library-carbon-nature-v050.css"
BACKEND = ROOT / "library-backend/app/main.py"
ENGINE = ROOT / "library-backend/app/carbon_nature.py"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_subsystem_identity_advances_without_renumbering_library_release():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in main
    backend_init = text(ROOT / "library-backend/app/__init__.py")
    match = re.search(r'__version__\s*=\s*"(\d+)\.(\d+)\.(\d+)"', backend_init)
    assert match and tuple(map(int, match.groups())) >= (2, 6, 0)


def test_afolu_research_librarian_routes_are_wired_end_to_end():
    module = text(MODULE)
    backend = text(BACKEND)
    for path in [
        '/v1/carbon-nature/research-librarian',
        '/v1/carbon-nature/research-librarian/intents',
        '/v1/carbon-nature/research-librarian/source-roles',
        '/v1/carbon-nature/research-librarian/guidance',
        '/v1/carbon-nature/research-context',
    ]:
        assert path in backend
    for path in [
        '/carbon-nature/research-librarian',
        '/carbon-nature/research-librarian/intents',
        '/carbon-nature/research-librarian/source-roles',
        '/carbon-nature/research-librarian/guidance',
        '/carbon-nature/research-context',
    ]:
        assert path in module


def test_shortcode_opens_on_afolu_research_librarian_and_preserves_prior_explorers():
    module = text(MODULE)
    assert "public const VERSION = '0.5.0'" in module
    assert "sc-library-carbon-nature-v050" in module
    assert "AFOLU Research Librarian Intelligence" in module
    assert 'data-cn-mode="librarian"' in module
    assert "Project Objects &amp; Provenance" in module
    assert "Evidence &amp; Methodology Graph" in module
    assert "Measure Registry" in module
    assert "Research guidance ≠ research conclusion." in module
    assert JS.exists() and CSS.exists()


def test_project_aware_research_librarian_packet_is_augmented_question_only():
    module = text(MODULE)
    assert "sc_library_project_aware_guidance_packet" in module
    assert "augment_project_aware_guidance_packet" in module
    assert "private_project_context_sent_to_library_backend' => false" in module
    assert "question_only_sent_to_library_backend' => true" in module
    assert "carbon_nature_domain_context_included'] = false" in module
    assert "afolu_domain" in module


def test_health_advertises_domain_intelligence_without_automatic_conclusions():
    backend = text(BACKEND)
    assert '"carbon_nature_domain_version": "0.5.0"' in backend
    assert '"afolu_research_librarian_intelligence": True' in backend
    assert '"afolu_research_intent_classification": True' in backend
    assert '"afolu_research_source_planning": True' in backend
    assert '"afolu_evidence_gap_diagnostics": True' in backend
    assert '"afolu_policy_market_freshness_flags": True' in backend
    assert '"automatic_afolu_research_conclusion_generation": False' in backend
    assert '"automatic_carbon_project_eligibility_determination": False' in backend


def test_engine_preserves_non_inference_and_current_rule_boundaries():
    engine = text(ENGINE)
    for phrase in [
        '"automatic_research_conclusion_generation": False',
        '"automatic_source_authority_determination": False',
        '"automatic_current_rule_assertion": False',
        '"research_guidance_is_deterministic_routing": True',
        '"project_packet_persistence": False',
        '"quantified_sequestration_potential": False',
        '"soc_calculation_engine": False',
        '"whole_farm_ghg_calculator": False',
    ]:
        assert phrase in engine


def test_v050_assets_render_intents_sources_gaps_freshness_and_handoffs():
    js = text(JS)
    css = text(CSS)
    for phrase in ["Detected Research Intents", "Research Question Frame", "Source Plan", "Evidence & Scope Gaps", "Freshness Review", "Governed Handoffs", "Answer Contract"]:
        assert phrase in js
    for selector in [".sc-cn__intent-grid", ".sc-cn__source-grid", ".sc-cn__gap-grid", ".sc-cn__handoff-grid", ".sc-cn__freshness-alert"]:
        assert selector in css


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
