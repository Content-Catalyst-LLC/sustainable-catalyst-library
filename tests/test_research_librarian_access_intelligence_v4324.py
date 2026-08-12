from pathlib import Path
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
README = (PLUGIN / "readme.txt").read_text()
BOOT = (PLUGIN / "includes/class-sc-library-extension-bootstrap-v402.php").read_text()
ACCESS = (PLUGIN / "includes/class-sc-library-research-librarian-access-intelligence.php").read_text()
CONNECTORS = (PLUGIN / "includes/class-sc-library-scholarly-library-connectors.php").read_text()
ORCH = (PLUGIN / "includes/class-sc-library-orchestrator.php").read_text()
ORCH_JS = (PLUGIN / "assets/js/sc-library-orchestrator.js").read_text()
CONNECTOR_JS = (PLUGIN / "assets/js/sc-library-connectors.js").read_text()
ORCH_CSS = (PLUGIN / "assets/css/sc-library-orchestrator.css").read_text()
CONNECTOR_CSS = (PLUGIN / "assets/css/sc-library-connectors.css").read_text()
FIELD = (PLUGIN / "templates/field-spotlights.php").read_text()
PAGE = (ROOT / "RESEARCH_LIBRARY_PAGE_v4.3.24.html").read_text()


def test_release_identity_and_module_registration():
    assert "Version: 4.3.24" in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.24');" in MAIN
    assert "Stable tag: 4.3.24" in README
    assert "public const VERSION = '4.3.24';" in ACCESS
    assert "class-sc-library-research-librarian-access-intelligence.php" in BOOT
    assert "SC_Library_Research_Librarian_Access_Intelligence" in BOOT
    match = re.search(r"public const MODULE_COUNT = (\d+);", BOOT)
    assert match and int(match.group(1)) == 29


def test_access_state_model_is_explicit_and_bounded():
    states = [
        "open-now", "public-digital", "library-access", "institution-login",
        "preview", "request-ill", "physical", "catalog-check", "metadata", "unknown",
    ]
    for state in states:
        assert f"'{state}'" in ACCESS
    for label in [
        "OPEN NOW", "PUBLIC DIGITAL COLLECTION", "LIBRARY MEMBERSHIP REQUIRED",
        "INSTITUTION LOGIN REQUIRED", "PREVIEW ONLY", "REQUEST / ILL",
        "PHYSICAL COPY", "CHECK LIBRARY HOLDINGS", "METADATA ONLY", "ACCESS UNCONFIRMED",
    ]:
        assert label in ACCESS


def test_availability_is_separated_from_entitlement():
    for marker in [
        "availability_is_not_entitlement",
        "library_credentials_not_stored",
        "provider_site_is_authoritative_for_access",
        "Availability does not establish entitlement",
        "eligibility",
    ]:
        assert marker in ACCESS or marker in ORCH or marker in ORCH_JS
    assert "availability_is_not_entitlement' => true" in ORCH
    assert "library_credentials_not_stored' => true" in ORCH


def test_access_intelligence_reuses_connector_holdings_instead_of_duplication():
    assert "SC_Library_Connector_Holdings_Reliability::holdings_summary" in ACCESS
    assert "SC_Library_Connector_Holdings_Reliability::recheck_holdings" in ACCESS
    assert "SC_Library_Citation_Source_Manager::get_source_data" in ACCESS
    assert "META_ACCESS_LOCATIONS" not in ACCESS  # holdings layer remains the normalization authority


def test_discovery_token_can_be_checked_without_being_consumed():
    assert "read_sealed_result( $token, $consume = true )" in CONNECTORS
    assert "if ( $consume )" in CONNECTORS
    assert "read_sealed_result( wp_unslash( $_POST['token'] ?? '' ), false )" in CONNECTORS
    assert "sc_library_v4324_access_intelligence_result" in CONNECTORS
    assert "wp_ajax_nopriv_sc_library_v4324_access_intelligence_result" in CONNECTORS


def test_research_access_result_cards_expose_access_check():
    for marker in [
        "Check access",
        "data-sc-check-access-token",
        "sc_library_v4324_access_intelligence_result",
        "Checking access evidence",
        "packet.state_label",
        "packet.entitlement",
        "packet.best_action",
    ]:
        assert marker in CONNECTOR_JS
    assert ".sc-connector-result-card__access-intelligence" in CONNECTOR_CSS
    assert ".sc-access-intelligence__state" in CONNECTOR_CSS


def test_orchestrator_has_access_intent_route_and_packet():
    assert "'access' => __('Find a legitimate access route'" in ORCH
    assert "'research_access' => [" in ORCH
    assert "SC_Library_Research_Librarian_Access_Intelligence::for_records($records)" in ORCH
    assert "'access' => $access" in ORCH
    assert "'access_intelligence_count' => count($access)" in ORCH
    assert "'access' => ['research_access', 'notebook']" in ORCH
    for phrase in ["open access", "full text", "interlibrary loan", "physical copy"]:
        assert phrase in ORCH


def test_orchestrator_ui_renders_access_evidence():
    for marker in [
        "const renderAccess",
        "Can you access it?",
        "Access intelligence",
        "response.access || []",
        "item.state_label",
        "item.availability",
        "item.entitlement",
        "item.best_action",
    ]:
        assert marker in ORCH_JS
    assert ".sc-orchestrator__access-list" in ORCH_CSS
    assert ".sc-orchestrator__access-boundary" in ORCH_CSS


def test_access_intelligence_fixture_classifies_realistic_cases():
    result = subprocess.run(
        ["php", str(ROOT / "tests/test_research_librarian_access_intelligence_fixture_v4324.php")],
        check=True,
        capture_output=True,
        text=True,
    )
    payload = json.loads(result.stdout)
    assert payload["schema"] == "sc-library-research-access-intelligence/1.0"
    assert payload["version"] == "4.3.24"
    cases = payload["cases"]
    assert cases["open"]["state"] == "open-now"
    assert cases["open"]["can_open_now"] is True
    assert cases["public_digital"]["state"] == "public-digital"
    assert cases["institution"]["state"] == "institution-login"
    assert cases["institution"]["requires_authentication"] is True
    assert cases["preview"]["state"] == "preview"
    assert cases["worldcat"]["state"] == "catalog-check"
    assert cases["worldcat"]["can_open_now"] is False
    assert all(item["boundaries"]["availability_is_not_entitlement"] for item in cases.values())


def test_research_library_page_promotes_access_intelligence_without_new_bloat():
    assert "Research Library v4.3.24" in PAGE
    assert "cc-rl-v4324" in PAGE
    assert "Know What “Available” Actually Means" in PAGE
    assert "result-level Check access control" in PAGE
    assert "Understand &amp; Access" in PAGE
    assert "availability with entitlement" in PAGE.lower()
    assert '[sc_research_librarian_orchestrator]' in PAGE
    assert '[sc_research_document_builder limit="100" style="harvard"]' in PAGE


def test_document_builder_and_publications_stack_are_preserved():
    builder = (PLUGIN / "includes/class-sc-library-research-document-builder.php").read_text()
    assert "public const VERSION = '4.3.23';" in builder
    assert "build_docx_binary" in builder and "build_pdf_binary" in builder
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
    assert "foreach ( $field_list as $index => $stack_field )" in FIELD
