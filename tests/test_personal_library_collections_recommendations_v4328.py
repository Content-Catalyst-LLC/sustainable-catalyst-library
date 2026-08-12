from pathlib import Path
import json, re, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
PERSONAL=(PLUGIN/'includes/class-sc-library-personal-collections-recommendations.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.28.html').read_text()
README=(PLUGIN/'readme.txt').read_text()
JS=(PLUGIN/'assets/js/sc-library-personal-library-v4328.js').read_text()
CSS=(PLUGIN/'assets/css/sc-library-personal-library-v4328.css').read_text()
FIELD=(PLUGIN/'templates/field-spotlights.php').read_text()


def test_release_identity_and_module_registration():
    assert 'Version: 4.3.28' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.28');" in MAIN
    assert 'class-sc-library-personal-collections-recommendations.php' in BOOT
    assert 'SC_Library_Personal_Collections_Recommendations' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==33


def test_private_account_owned_storage_contracts_are_explicit():
    assert "USER_META_ITEMS = 'sc_library_personal_items_v4328'" in PERSONAL
    assert "USER_META_COLLECTIONS = 'sc_library_personal_collections_v4328'" in PERSONAL
    assert "'origin'       => 'personal'" in PERSONAL
    assert "'visibility'   => 'private'" in PERSONAL
    assert 'MAX_ITEMS = 500' in PERSONAL
    assert 'MAX_COLLECTIONS = 50' in PERSONAL


def test_supported_resource_types_cover_research_learning_and_culture():
    for marker in ["'book'", "'film'", "'music'", "'article'", "'archive'", "'course'", "'dataset'", "'tool'", "'website'", "'podcast'", "'other'"]:
        assert marker in PERSONAL


def test_personal_recommendations_are_separate_from_official_editorial_system():
    assert "'official_editorial_separate'    => true" in PERSONAL
    assert "'automatic_publication'          => false" in PERSONAL
    assert "'automatic_editorial_promotion'  => false" in PERSONAL
    assert "stay separate from Sustainable Catalyst\\'s official editorial recommendations." in PERSONAL
    assert 'Nothing saved here is automatically published, endorsed, or promoted by Sustainable Catalyst.' in PERSONAL


def test_authenticated_private_api_and_nonce_protected_writes_exist():
    assert "REST_ROUTE = '/personal-library'" in PERSONAL
    assert "'permission_callback' => array( $this, 'rest_can_read' )" in PERSONAL
    assert 'return is_user_logged_in();' in PERSONAL
    assert 'check_ajax_referer( self::NONCE_ACTION, \'nonce\' );' in PERSONAL
    for action in ['sc_library_v4328_add_item','sc_library_v4328_update_item','sc_library_v4328_delete_item','sc_library_v4328_create_collection']:
        assert action in PERSONAL
        assert action in JS
    assert "credentials:'same-origin'" in JS


def test_stable_future_handoff_contract_is_present_without_workspace_auto_write():
    assert 'sc_library_save_personal_item' in PERSONAL
    assert 'sc_library_personal_item_saved' in PERSONAL
    assert 'sc_library_personal_library_items' in PERSONAL
    assert 'workspace_account_continuity' in PERSONAL
    assert 'automatic_editorial_promotion' in PERSONAL


def test_account_continuity_health_remains_version_aligned_and_knows_my_library():
    assert "public const VERSION = '4.3.28';" in ROUTE
    assert "'personal_library'   => 'sc_library_personal_items_v4328'" in ROUTE
    assert 'My Library collections remain attached to this account' in ROUTE
    assert "HEALTH_ROUTE = '/runtime/identity-health'" in ROUTE


def test_research_library_page_promotes_my_library_without_replacing_research_tools():
    assert 'Research Library v4.3.28' in PAGE
    assert 'cc-rl-v4328' in PAGE
    assert 'id="personal-library"' in PAGE
    assert '[sc_personal_library title="My Library"]' in PAGE
    assert '<li><a href="#personal-library">My Library</a></li>' in PAGE
    assert PAGE.index('id="research-access"') < PAGE.index('id="personal-library"') < PAGE.index('id="open-course-finder"')
    for marker in ['[sc_research_access ', '[sc_public_library_network ', '[sc_open_course_finder ', '[sc_citation_studio ', '[sc_research_document_builder ', '[sc_library_unified_workspace]']:
        assert marker in PAGE


def test_ui_supports_filtering_collection_management_and_private_reasons():
    for marker in ['data-sc-personal-search','data-sc-personal-type-filter','data-sc-personal-relationship-filter','data-sc-personal-collection-filter','Why I kept this','Private notes','Create collection']:
        assert marker in PERSONAL
    assert 'applyFilters' in JS
    assert 'data-sc-personal-delete' in JS
    assert '.sc-personal-library__grid' in CSS


def test_readme_and_release_docs_capture_current_contract():
    assert 'Stable tag: 4.3.28' in README
    assert '= 4.3.28 =' in README
    assert '[sc_personal_library]' in README
    assert '/wp-json/sc-library/v1/personal-library' in README
    doc=(ROOT/'PERSONAL_LIBRARY_COLLECTIONS_RECOMMENDATIONS_v4.3.28.md').read_text()
    notes=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.28.md').read_text()
    assert 'My Library is **not** the Sustainable Catalyst editorial recommendation system.' in doc
    assert 'No second Library account' in doc
    assert 'automatic publication' in notes


def test_php_fixture_proves_schema_types_and_separation_contract():
    r=subprocess.run(['php',str(ROOT/'tests/test_personal_library_fixture_v4328.php')],check=True,capture_output=True,text=True)
    data=json.loads(r.stdout)
    assert data['version']=='4.3.28'
    assert data['schema']=='sc-library-personal-library/1.0'
    assert data['items_meta']=='sc_library_personal_items_v4328'
    assert data['collections_meta']=='sc_library_personal_collections_v4328'
    assert data['rest_route']=='/personal-library'
    assert data['max_items']==500
    assert set(['book','film','music','article','archive','course','dataset','tool']).issubset(data['types'])
    assert data['separation']['visibility']=='private'
    assert data['separation']['official_editorial_separate'] is True
    assert data['separation']['automatic_publication'] is False


def test_retained_publications_and_canonical_public_route_boundaries_still_hold():
    assert 'Plugin URI: https://sustainablecatalyst.com/knowledge-libraries/' in MAIN
    assert "CANONICAL_SLUG = 'knowledge-libraries'" in ROUTE
    assert "LEGACY_SLUG = 'library'" in ROUTE
    assert 'wp_safe_redirect( $target, 301' in ROUTE
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
