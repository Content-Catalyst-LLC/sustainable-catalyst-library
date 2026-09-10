from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-private-organizational-knowledge.php"
JS = PLUGIN / "assets/js/sc-library-private-knowledge-v5110.js"
CSS = PLUGIN / "assets/css/sc-library-private-knowledge-v5110.css"
README = ROOT / "README.md"
WP_README = PLUGIN / "readme.txt"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_backend_and_module_wiring():
    main = text(MAIN)
    assert "Version: 5.11.0" in main
    assert "SC_LIBRARY_VERSION', '5.11.0'" in main
    assert "class-sc-library-private-organizational-knowledge.php" in main
    assert "SC_Library_Private_Organizational_Knowledge" in main
    assert '__version__ = "2.1.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_shortcode_and_private_rest_surface_are_wired():
    module = text(MODULE)
    assert "sc_private_organizational_knowledge" in module
    for route in ["/private-knowledge/search", "/private-knowledge/record", "/private-knowledge/versions", "/private-knowledge/handoff", "/private-knowledge/ingest"]:
        assert route in module
    assert "SC_Library_Python_Backend::signed_request('POST'" in module
    assert JS.exists() and CSS.exists()


def test_private_surface_requires_signed_in_wordpress_access_and_server_side_tenant_configuration():
    module = text(MODULE)
    assert "is_user_logged_in()" in module
    assert "current_user_can('read')" in module
    assert "current_user_can('upload_files')" in module
    assert "sc_library_private_org_key" in module
    assert "SC_LIBRARY_PRIVATE_ORG_KEY" in module
    assert "sc_library_private_knowledge_access" in module
    assert "sc_library_private_knowledge_actor_scopes" in module


def test_browser_never_receives_backend_api_key_and_uses_wordpress_nonce():
    module = text(MODULE)
    js = text(JS)
    assert "sc_library_backend_api_key" not in js
    assert "SC_LIBRARY_BACKEND_API_KEY" not in js
    assert "wp_create_nonce('wp_rest')" in module
    assert "X-WP-Nonce" in js
    assert "credentials: 'same-origin'" in js


def test_interface_states_private_public_separation_and_handoff_boundary():
    module = text(MODULE)
    assert "Private by architecture." in module
    assert "stored outside the public Library corpus" in module
    assert "Handoffs must preserve organization and access scope" in module
    assert "does not claim a new binary parser" in module
    assert "raw query text" in module


def test_private_module_is_not_added_to_public_research_network_console():
    network = text(PLUGIN / "includes/class-sc-library-research-network-console.php")
    homepage = text(PLUGIN / "includes/class-sc-library-homepage-console.php")
    assert "SC_Library_Private_Organizational_Knowledge" not in network
    assert "SC_Library_Private_Organizational_Knowledge" not in homepage


def test_private_ingest_is_normalized_text_only_and_sanitizes_access_boundaries():
    module = text(MODULE)
    assert "normalized source packet" in module
    assert "body_text" in module
    assert "access_level" in module
    assert "access_scopes" in module
    assert "project_key" in module
    assert "analysis_allowed" not in module  # backend policy owns analysis eligibility


def test_readmes_document_current_release_and_additive_database_change():
    root = text(README)
    wp = text(WP_README)
    assert "v5.11.0 — Private Organizational Knowledge Foundation" in root
    assert "Backend v2.1.0" in root
    assert "Stable tag: 5.11.0" in wp
    assert "Private Organizational Knowledge Foundation" in wp


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
