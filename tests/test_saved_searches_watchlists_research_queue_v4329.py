from pathlib import Path
import json, re, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
MOD=(PLUGIN/'includes/class-sc-library-saved-searches-watchlists-queue.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
PERSONAL=(PLUGIN/'includes/class-sc-library-personal-collections-recommendations.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.29.html').read_text()
README=(PLUGIN/'readme.txt').read_text()
JS=(PLUGIN/'assets/js/sc-library-research-continuity-v4329.js').read_text()
CSS=(PLUGIN/'assets/css/sc-library-research-continuity-v4329.css').read_text()
FIELD=(PLUGIN/'templates/field-spotlights.php').read_text()


def test_release_identity_and_module_registration():
    assert 'Version: 4.3.29' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.29');" in MAIN
    assert 'class-sc-library-saved-searches-watchlists-queue.php' in BOOT
    assert 'SC_Library_Saved_Searches_Watchlists_Queue' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==34


def test_private_user_meta_contracts_are_explicit_and_bounded():
    assert "USER_META_SEARCHES = 'sc_library_saved_searches_v4329'" in MOD
    assert "USER_META_WATCHLISTS = 'sc_library_watchlists_v4329'" in MOD
    assert "USER_META_QUEUE = 'sc_library_research_queue_v4329'" in MOD
    assert 'MAX_SEARCHES = 100' in MOD
    assert 'MAX_WATCHLISTS = 100' in MOD
    assert 'MAX_QUEUE_ITEMS = 250' in MOD
    assert "'visibility' => 'private'" in MOD


def test_saved_searches_capture_repeatable_research_instructions():
    for marker in ["'query'", "'scope'", "'provider'", "'filters'", "'notes'"]:
        assert marker in MOD
    for scope in ["'all'", "'sustainable-catalyst'", "'libraries'", "'scholarly'", "'courses'", "'external'"]:
        assert scope in MOD
    assert 'sc_library_saved_search_created' in MOD


def test_watchlists_are_explicitly_passive_not_fake_monitoring():
    assert "'watchlists_are_passive'    => true" in MOD
    assert "'background_monitoring'     => false" in MOD
    assert "'automatic_notifications'   => false" in MOD
    assert "'monitoring'      => 'passive'" in MOD
    assert 'A watchlist is a reminder to revisit something. It is not an automated alert.' in MOD
    assert 'Mark reviewed' in MOD
    assert 'sc_library_v4329_mark_watch_reviewed' in MOD


def test_research_queue_supports_work_state_and_priority():
    for kind in ["'question'", "'source'", "'search'", "'task'", "'course'", "'dataset'", "'other'"]:
        assert kind in MOD
    for status in ["'queued'", "'active'", "'done'", "'archived'"]:
        assert status in MOD
    for priority in ["'low'", "'normal'", "'high'"]:
        assert priority in MOD
    assert 'update_queue_item_for_user' in MOD


def test_authenticated_api_nonce_writes_and_integration_hooks_exist():
    assert "REST_ROUTE = '/research-continuity'" in MOD
    assert "'permission_callback' => array( $this, 'rest_can_read' )" in MOD
    assert 'return is_user_logged_in();' in MOD
    assert "check_ajax_referer( self::NONCE_ACTION, 'nonce' );" in MOD
    for action in ['sc_library_v4329_save_search','sc_library_v4329_add_watch','sc_library_v4329_add_queue_item','sc_library_v4329_update_queue_item']:
        assert action in MOD and action in JS
    for hook in ['sc_library_save_search','sc_library_add_watchlist_item','sc_library_enqueue_research_item','sc_library_research_continuity_state']:
        assert hook in MOD
    assert "credentials:'same-origin'" in JS


def test_identity_health_is_version_aligned_and_tracks_new_private_records():
    assert "public const VERSION = '4.3.29';" in ROUTE
    assert "'saved_searches'     => 'sc_library_saved_searches_v4329'" in ROUTE
    assert "'watchlists'         => 'sc_library_watchlists_v4329'" in ROUTE
    assert "'research_queue'     => 'sc_library_research_queue_v4329'" in ROUTE
    assert 'saved searches, watchlists, and the research queue remain attached to this account' in ROUTE


def test_research_library_page_places_saved_research_after_personal_library():
    assert 'Research Library v4.3.29' in PAGE
    assert 'cc-rl-v4329' in PAGE
    assert 'id="saved-research"' in PAGE
    assert '[sc_research_continuity title="Saved Research & Queue"]' in PAGE
    assert '<li><a href="#saved-research">Saved Research &amp; Queue</a></li>' in PAGE
    assert PAGE.index('id="personal-library"') < PAGE.index('id="saved-research"') < PAGE.index('id="open-course-finder"')
    for marker in ['[sc_personal_library ', '[sc_research_access ', '[sc_public_library_network ', '[sc_open_course_finder ', '[sc_citation_studio ', '[sc_research_document_builder ', '[sc_library_unified_workspace]']:
        assert marker in PAGE


def test_ui_assets_support_all_three_record_families_and_accessibility():
    for marker in ['data-sc-continuity-search-form','data-sc-continuity-watch-form','data-sc-continuity-queue-form','data-sc-continuity-update-queue']:
        assert marker in MOD
    assert 'aria-live="polite"' in MOD
    assert 'data-sc-research-continuity' in JS
    assert '.sc-research-continuity__columns' in CSS
    assert '@media(max-width:560px)' in CSS


def test_readme_and_release_docs_capture_truthful_current_contract():
    assert 'Stable tag: 4.3.29' in README
    assert '[sc_research_continuity]' in README
    assert '/wp-json/sc-library/v1/research-continuity' in README
    doc=(ROOT/'SAVED_SEARCHES_WATCHLISTS_RESEARCH_QUEUE_v4.3.29.md').read_text()
    notes=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.29.md').read_text()
    assert '**Watchlists are passive in v4.3.29.**' in doc
    assert 'No second Library account is introduced.' in doc
    assert 'not** a background monitoring service' in notes


def test_php_fixture_proves_schema_contracts_and_no_monitoring_claim():
    r=subprocess.run(['php',str(ROOT/'tests/test_research_continuity_fixture_v4329.php')],check=True,capture_output=True,text=True)
    data=json.loads(r.stdout)
    assert data['version']=='4.3.29'
    assert data['schema']=='sc-library-research-continuity/1.0'
    assert data['searches_meta']=='sc_library_saved_searches_v4329'
    assert data['watchlists_meta']=='sc_library_watchlists_v4329'
    assert data['queue_meta']=='sc_library_research_queue_v4329'
    assert data['rest_route']=='/research-continuity'
    assert data['contract']['visibility']=='private'
    assert data['contract']['watchlists_are_passive'] is True
    assert data['contract']['background_monitoring'] is False
    assert data['contract']['automatic_notifications'] is False


def test_v4328_personal_library_and_publication_route_boundaries_are_preserved():
    assert "public const VERSION = '4.3.28';" in PERSONAL
    assert "USER_META_ITEMS = 'sc_library_personal_items_v4328'" in PERSONAL
    assert 'Plugin URI: https://sustainablecatalyst.com/knowledge-libraries/' in MAIN
    assert "CANONICAL_SLUG = 'knowledge-libraries'" in ROUTE
    assert "LEGACY_SLUG = 'library'" in ROUTE
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
