from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = (PLUGIN / 'sustainable-catalyst-library.php').read_text()
BOOT = (PLUGIN / 'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
MOD = (PLUGIN / 'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text()
ROUTE = (PLUGIN / 'includes/class-sc-library-canonical-route-identity.php').read_text()
CONT = (PLUGIN / 'includes/class-sc-library-saved-searches-watchlists-queue.php').read_text()
PERSONAL = (PLUGIN / 'includes/class-sc-library-personal-collections-recommendations.php').read_text()
HANDOFF = (PLUGIN / 'includes/class-sc-library-cross-product-research-handoffs.php').read_text()
PAGE = (ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.30.html').read_text()
README = (PLUGIN / 'readme.txt').read_text()
JS = (PLUGIN / 'assets/js/sc-library-unified-projects-v4330.js').read_text()
CSS = (PLUGIN / 'assets/css/sc-library-unified-projects-v4330.css').read_text()
STACK = (PLUGIN / 'templates/field-spotlights.php').read_text()
DOC = (ROOT / 'UNIFIED_RESEARCH_PROJECTS_SOURCE_BUNDLES_v4.3.30.md').read_text()
NOTES = (ROOT / 'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.30.md').read_text()


def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.30' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.30');" in MAIN
    assert 'class-sc-library-unified-research-projects-source-bundles.php' in BOOT
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles' in BOOT
    m = re.search(r'public const MODULE_COUNT = (\d+);', BOOT)
    assert m and int(m.group(1)) == 35


def test_reuses_canonical_project_type_and_stable_cross_product_identity():
    assert "canonical_project_type'         => 'sc_research_project'" in MOD
    assert "project_identity'               => 'sc-platform-project-identity/1.0'" in MOD
    assert 'SC_Library_Cross_Product_Research_Handoffs::project_identity' in MOD
    assert "PROJECT_IDENTITY_SCHEMA = 'sc-platform-project-identity/1.0'" in HANDOFF
    assert "META_PROJECT_UUID = '_sc_platform_project_uuid'" in HANDOFF


def test_project_storage_is_post_scoped_private_and_bounded():
    assert "META_LINKS = '_sc_project_unified_links_v4330'" in MOD
    assert "META_BUNDLES = '_sc_project_source_bundles_v4330'" in MOD
    assert 'MAX_PROJECTS_PER_USER = 50' in MOD
    assert 'MAX_LINKS_PER_PROJECT = 300' in MOD
    assert 'MAX_BUNDLES_PER_PROJECT = 60' in MOD
    assert "'post_author'  => $user_id" in MOD
    assert "update_post_meta( $project_id, $visibility_key, 'private' )" in MOD
    assert 'user_owns_project' in MOD


def test_reference_families_cover_existing_library_research_systems():
    for family in ['source', 'personal_library', 'saved_search', 'watchlist', 'research_queue', 'source_collection', 'research_document', 'course', 'pathway', 'external']:
        assert f"'{family}'" in MOD
    assert 'SC_Library_Personal_Collections_Recommendations::items_for_user' in MOD
    assert 'SC_Library_Saved_Searches_Watchlists_Queue::searches_for_user' in MOD
    assert "'sc_library_research_documents_v4323'" in MOD
    assert "'sc_library_course_plan_v4321'" in MOD


def test_links_are_references_only_and_do_not_duplicate_underlying_records():
    assert "'references_only'                => true" in MOD
    assert "'duplicate_linked_records'        => false" in MOD
    assert "'copy_source_content'             => false" in MOD
    assert "'copy_private_binary_files'       => false" in MOD
    assert "'automatic_publication'           => false" in MOD
    assert "'automatic_workspace_write'       => false" in MOD
    assert 'References, not duplicates' in MOD


def test_source_bundles_have_stable_ids_urns_and_reference_membership():
    assert "BUNDLE_SCHEMA = 'sc-library-source-bundle/1.0'" in MOD
    assert "'urn'         => $bundle_id ? 'urn:sc:source-bundle:' . $bundle_id" in MOD
    assert 'MAX_LINKS_PER_BUNDLE = 120' in MOD
    assert "'link_ids'" in MOD
    assert 'create_bundle_for_user' in MOD
    assert 'delete_bundle_for_user' in MOD


def test_bundle_manifest_resolves_live_references_and_checksums_current_manifest():
    assert 'bundle_manifest' in MOD
    assert "'resolution' => self::resolve_reference" in MOD
    assert "hash( 'sha256', wp_json_encode( $manifest ) )" in MOD
    assert "'resolved_on_read'" in MOD
    assert "'missing_references_retained'" in MOD


def test_authenticated_rest_api_covers_projects_links_and_bundles():
    assert "REST_ROUTE = '/research-projects'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/links'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/bundles'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/bundles/(?P<bundle_id>[A-Za-z0-9\\-]+)'" in MOD
    assert "'permission_callback'=>array($this,'rest_signed_in')" in MOD
    assert 'public function rest_signed_in() { return is_user_logged_in(); }' in MOD


def test_nonce_protected_front_end_actions_and_future_integration_hooks_exist():
    assert "NONCE_ACTION = 'sc_library_unified_projects_v4330'" in MOD
    for action in ['sc_library_v4330_create_project', 'sc_library_v4330_update_project', 'sc_library_v4330_add_link', 'sc_library_v4330_delete_link', 'sc_library_v4330_create_bundle', 'sc_library_v4330_delete_bundle']:
        assert action in MOD
        assert action in JS
    for hook in ['sc_library_unified_project_created', 'sc_library_project_reference_linked', 'sc_library_source_bundle_created', 'sc_library_unified_project_state', 'sc_library_source_bundle_manifest']:
        assert hook in MOD


def test_identity_health_is_version_aligned_and_tracks_project_continuity():
    assert "public const VERSION = '4.3.30'" in ROUTE
    assert "'research_projects'  => 'sc_research_project:post_author'" in ROUTE
    assert "'project_links'      => '_sc_project_unified_links_v4330'" in ROUTE
    assert "'source_bundles'     => '_sc_project_source_bundles_v4330'" in ROUTE
    assert 'private research projects with source bundles' in ROUTE


def test_research_library_page_places_projects_after_saved_research_and_before_courses():
    assert '[sc_unified_research_projects title="Research Projects & Source Bundles"]' in PAGE
    assert 'id="research-projects"' in PAGE
    saved = PAGE.index('id="saved-research"')
    projects = PAGE.index('id="research-projects"')
    courses = PAGE.index('id="open-course-finder"')
    assert saved < projects < courses
    assert PAGE.count('href="#research-projects"') >= 3


def test_ui_assets_expose_project_link_and_bundle_workflows_accessibly():
    for marker in ['data-sc-project-create', 'data-sc-project-update', 'data-sc-project-add-link', 'data-sc-project-create-bundle', 'aria-live="polite"']:
        assert marker in MOD
    assert 'window.location.reload()' in JS
    assert '@media(max-width:780px)' in CSS
    assert ':focus-visible' in CSS
    assert 'min-height:44px' in CSS


def test_readme_and_release_docs_describe_current_no_duplication_contract():
    assert 'Stable tag: 4.3.30' in README
    assert 'references-only Source Bundles' in README
    assert 'references-only' in DOC
    assert 'source content is not copied' in DOC
    assert 'no Workspace write happens automatically' in DOC
    assert 'Unified Research Projects & Source Bundles' in NOTES


def test_v4329_and_v4328_private_storage_contracts_are_preserved():
    assert "VERSION = '4.3.29'" in CONT
    assert "USER_META_SEARCHES = 'sc_library_saved_searches_v4329'" in CONT
    assert "USER_META_WATCHLISTS = 'sc_library_watchlists_v4329'" in CONT
    assert "USER_META_QUEUE = 'sc_library_research_queue_v4329'" in CONT
    assert "VERSION = '4.3.28'" in PERSONAL
    assert "USER_META_ITEMS = 'sc_library_personal_items_v4328'" in PERSONAL
    assert "USER_META_COLLECTIONS = 'sc_library_personal_collections_v4328'" in PERSONAL


def test_canonical_route_publications_and_watchlist_truthfulness_remain_preserved():
    assert "CANONICAL_SLUG = 'knowledge-libraries'" in ROUTE
    assert "LEGACY_SLUG = 'library'" in ROUTE
    assert "'background_monitoring'     => false" in CONT
    assert "'automatic_notifications'   => false" in CONT
    assert 'data-sc-field-stack="v4.3.22.4"' in STACK
    assert 'data-sc-field-stack-mode="all-fields"' in STACK
