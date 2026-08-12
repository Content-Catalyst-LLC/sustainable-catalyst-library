from pathlib import Path
import json, re, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.27.html').read_text()
README=(PLUGIN/'readme.txt').read_text()
FIELD=(PLUGIN/'templates/field-spotlights.php').read_text()


def test_release_identity_and_module_registration():
    assert 'Version: 4.3.27' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.27');" in MAIN
    assert 'Plugin URI: https://sustainablecatalyst.com/knowledge-libraries/' in MAIN
    assert 'class-sc-library-canonical-route-identity.php' in BOOT
    assert 'SC_Library_Canonical_Route_Identity' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==32


def test_canonical_and_legacy_route_contracts_are_explicit():
    assert "public const CANONICAL_SLUG = 'knowledge-libraries';" in ROUTE
    assert "public const LEGACY_SLUG = 'library';" in ROUTE
    assert 'wp_safe_redirect( $target, 301' in ROUTE
    assert 'PHP_URL_QUERY' in ROUTE
    assert "array( 'GET', 'HEAD' )" in ROUTE


def test_redirect_scope_excludes_admin_ajax_and_rest():
    assert 'is_admin()' in ROUTE
    assert 'wp_doing_ajax()' in ROUTE
    assert "defined( 'REST_REQUEST' ) && REST_REQUEST" in ROUTE
    assert 'request_targets_legacy_public_route' in ROUTE


def test_internal_api_library_namespaces_are_preserved():
    assert "public const REST_NAMESPACE = 'sc-library/v1';" in ROUTE
    assert "'api_namespace_preserved' => true" in ROUTE
    # Existing API contracts that intentionally contain /library/ remain present.
    orchestrator=(PLUGIN/'includes/class-sc-library-orchestrator.php').read_text()
    notebook=(PLUGIN/'includes/class-sc-library-notebook.php').read_text()
    assert "'/library/orchestrator/status'" in orchestrator
    assert "'/library/source-types'" in notebook


def test_runtime_identity_health_endpoint_exists():
    assert "public const HEALTH_ROUTE = '/runtime/identity-health';" in ROUTE
    assert 'register_rest_route(' in ROUTE
    assert "'permission_callback' => '__return_true'" in ROUTE
    assert "'page_published'" in ROUTE
    assert "'legacy_page_exists'" in ROUTE


def test_account_continuity_reuses_existing_account_owned_contracts():
    for marker in [
        'sc_library_my_libraries_v4319',
        'sc_library_source_collections_v4322',
        'sc_library_research_documents_v4323',
        'sc_library_course_plan_v4321',
    ]:
        assert marker in ROUTE
    assert "'account_source'                   => 'wordpress'" in ROUTE
    assert "'separate_library_account_required'=> false" in ROUTE
    assert "'external_library_credentials_stored' => false" in ROUTE


def test_account_continuity_shortcode_is_visible_and_workspace_aware():
    assert "add_shortcode( 'sc_library_account_continuity'" in ROUTE
    assert "home_url( '/workspace/' )" in ROUTE
    assert 'no second Library account is required' in ROUTE
    assert '[sc_library_account_continuity]' in PAGE
    assert 'cc-rl-v4327' in PAGE


def test_research_library_page_and_publications_boundary_preserved():
    assert 'Research Library v4.3.27' in PAGE
    assert '[sc_public_library_network title="Public Library Network & Local Access"]' in PAGE
    assert '[sc_research_document_builder limit="100" style="harvard"]' in PAGE
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD


def test_readme_marks_current_stable_tag_and_new_health_route():
    assert 'Stable tag: 4.3.27' in README
    assert '= 4.3.27 =' in README
    assert '/wp-json/sc-library/v1/runtime/identity-health' in README
    assert '[sc_library_account_continuity]' in README


def test_php_fixture_proves_path_matching_without_api_collision():
    r=subprocess.run(['php',str(ROOT/'tests/test_canonical_route_identity_v4327.php')],check=True,capture_output=True,text=True)
    data=json.loads(r.stdout)
    assert data['version']=='4.3.27'
    assert data['canonical']=='https://example.test/knowledge-libraries/'
    assert data['legacy']=='https://example.test/library/'
    assert data['legacy_match'] is True
    assert data['canonical_match'] is True
    assert data['api_is_not_legacy'] is True
    assert data['account']['separate_library_account_required'] is False
    assert data['account']['external_library_credentials_stored'] is False


def test_release_docs_capture_safety_boundary():
    doc=(ROOT/'CANONICAL_ROUTING_IDENTITY_CONTINUITY_v4.3.27.md').read_text()
    notes=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.27.md').read_text()
    assert 'does **not** rename REST routes that contain `/library/`' in doc
    assert 'No second Library account' not in notes  # avoid inventing a parallel account model
    assert 'one Sustainable Catalyst/WordPress account' in notes
