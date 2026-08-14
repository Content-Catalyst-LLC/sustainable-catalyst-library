from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = (PLUGIN / 'sustainable-catalyst-library.php').read_text()
BOOT = (PLUGIN / 'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
MOD = (PLUGIN / 'includes/class-sc-library-reading-notebook-annotations.php').read_text()
ROUTE = (PLUGIN / 'includes/class-sc-library-canonical-route-identity.php').read_text()
PROJECTS = (PLUGIN / 'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text()
LEGACY_NOTEBOOK = (PLUGIN / 'includes/class-sc-library-notebook.php').read_text()
PAGE = (ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.31.html').read_text()
README = (PLUGIN / 'readme.txt').read_text()
JS = (PLUGIN / 'assets/js/sc-library-reading-notebooks-v4331.js').read_text()
CSS = (PLUGIN / 'assets/css/sc-library-reading-notebooks-v4331.css').read_text()
DOC = (ROOT / 'READING_NOTEBOOK_ANNOTATION_WORKSPACE_v4.3.31.md').read_text()
NOTES = (ROOT / 'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.31.md').read_text()
STACK = (PLUGIN / 'templates/field-spotlights.php').read_text()


def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.31' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.31');" in MAIN
    assert 'class-sc-library-reading-notebook-annotations.php' in BOOT
    assert 'SC_Library_Reading_Notebook_Annotations' in BOOT
    m = re.search(r'public const MODULE_COUNT = (\d+);', BOOT)
    assert m and int(m.group(1)) == 36


def test_account_persistent_private_notebook_record_type_and_owner_boundary():
    assert "POST_TYPE = 'sc_reading_notebook'" in MOD
    assert "'public'              => false" in MOD
    assert "'show_in_rest'        => false" in MOD
    assert "'post_author' => $user_id" in MOD
    assert 'user_owns_notebook' in MOD
    assert 'MAX_NOTEBOOKS_PER_USER = 60' in MOD


def test_stable_notebook_note_and_annotation_identities_exist():
    assert "SCHEMA = 'sc-library-reading-notebook/1.0'" in MOD
    assert "NOTE_SCHEMA = 'sc-library-reading-note/1.0'" in MOD
    assert "ANNOTATION_SCHEMA = 'sc-library-source-annotation/1.0'" in MOD
    assert "'urn'              => 'urn:sc:reading-notebook:' . $uuid" in MOD
    assert "'urn'           => $id ? 'urn:sc:reading-note:' . $id" in MOD
    assert "'urn'            => $id ? 'urn:sc:source-annotation:' . $id" in MOD


def test_project_and_source_bundle_context_reuses_v4330_canonical_layer():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::resolve_reference' in MOD
    assert "VERSION = '4.3.30'" in PROJECTS
    assert "META_BUNDLES = '_sc_project_source_bundles_v4330'" in PROJECTS


def test_notes_support_reusable_excerpts_tags_pinning_and_ordering():
    for note_type in ['note', 'excerpt', 'question', 'observation', 'summary', 'method']:
        assert f"'{note_type}'" in MOD
    assert 'MAX_NOTES_PER_NOTEBOOK = 300' in MOD
    assert 'MAX_EXCERPT_CHARS = 4000' in MOD
    assert "'tags'" in MOD
    assert "'pinned'" in MOD
    assert "'position'" in MOD
    assert 'Reusable excerpt — user selected' in MOD


def test_annotations_support_pdf_and_other_precise_locators():
    assert 'MAX_ANNOTATIONS_PER_NOTEBOOK = 500' in MOD
    for annotation_type in ['highlight', 'excerpt', 'comment', 'bookmark']:
        assert f"'{annotation_type}'" in MOD
    for locator in ['page', 'section', 'timestamp', 'paragraph', 'custom']:
        assert f"'{locator}'" in MOD
    assert 'PDF / document page' in MOD
    assert 'Selected passage' in MOD


def test_no_copy_no_automatic_generation_or_promotion_contract():
    assert "'source_links_are_references'   => true" in MOD
    assert "'copy_underlying_source_record' => false" in MOD
    assert "'copy_private_binary_files'     => false" in MOD
    assert "'user_authored_notes'           => true" in MOD
    assert "'automatic_ai_generation'       => false" in MOD
    assert "'automatic_evidence_promotion'  => false" in MOD
    assert "'automatic_publication'         => false" in MOD
    assert "'automatic_workspace_write'     => false" in MOD


def test_legacy_browser_local_notebook_is_preserved_for_compatibility():
    assert 'SC_Library_Notebook' in MAIN
    assert "'storageKey' => 'scLibraryWorkspaceV120'" in LEGACY_NOTEBOOK
    assert "'legacy_browser_notebook_preserved' => true" in MOD
    assert 'legacy browser-local Research Notebook' in NOTES


def test_authenticated_rest_api_covers_notebooks_manifest_notes_and_annotations():
    assert "REST_ROUTE = '/reading-notebooks'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/manifest'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/notes'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/notes/(?P<note_id>[A-Za-z0-9\\-]+)'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/annotations'" in MOD
    assert "self::REST_ROUTE . '/(?P<id>\\d+)/annotations/(?P<annotation_id>[A-Za-z0-9\\-]+)'" in MOD
    assert 'public function rest_signed_in() { return is_user_logged_in(); }' in MOD


def test_notebook_manifest_is_private_reference_aware_and_checksummed():
    assert 'notebook_manifest' in MOD
    assert "'source_records_referenced_not_copied' => true" in MOD
    assert "'private_binaries_excluded' => true" in MOD
    assert "hash( 'sha256', wp_json_encode( $manifest ) )" in MOD


def test_nonce_protected_front_end_actions_and_integration_hooks_exist():
    assert "NONCE_ACTION = 'sc_library_reading_notebooks_v4331'" in MOD
    actions = [
        'sc_library_v4331_create_notebook', 'sc_library_v4331_update_notebook', 'sc_library_v4331_delete_notebook',
        'sc_library_v4331_add_note', 'sc_library_v4331_update_note', 'sc_library_v4331_delete_note',
        'sc_library_v4331_add_annotation', 'sc_library_v4331_update_annotation', 'sc_library_v4331_delete_annotation'
    ]
    for action in actions:
        assert action in MOD
        assert action in JS
    for hook in ['sc_library_reading_notebook_created', 'sc_library_reading_note_created', 'sc_library_source_annotation_created', 'sc_library_reading_notebook_state', 'sc_library_reading_notebook_manifest']:
        assert hook in MOD


def test_identity_health_is_version_aligned_and_tracks_new_private_records():
    assert "public const VERSION = '4.3.31'" in ROUTE
    assert "'reading_notebooks'  => 'sc_reading_notebook:post_author'" in ROUTE
    assert "'reading_notes'      => '_sc_reading_notebook_notes_v4331'" in ROUTE
    assert "'source_annotations' => '_sc_reading_notebook_annotations_v4331'" in ROUTE
    assert 'reading notebooks, notes, reusable excerpts, and source annotations remain attached to this account' in ROUTE


def test_research_library_page_places_reading_workspace_after_projects_before_courses():
    assert '[sc_reading_notebook_workspace title="Reading, Notebook &amp; Annotation Workspace"]' in PAGE
    assert 'id="reading-notebooks"' in PAGE
    projects = PAGE.index('id="research-projects"')
    reading = PAGE.index('id="reading-notebooks"')
    courses = PAGE.index('id="open-course-finder"')
    assert projects < reading < courses
    assert PAGE.count('href="#reading-notebooks"') >= 3


def test_front_end_assets_cover_accessibility_responsiveness_and_editing():
    for marker in ['data-sc-reading-create-notebook', 'data-sc-reading-update-note', 'data-sc-reading-add-annotation', 'data-sc-reading-update-annotation', 'aria-live="polite"']:
        assert marker in MOD
    assert 'window.location.reload()' in JS
    assert 'window.confirm' in JS
    assert '@media(max-width:780px)' in CSS
    assert ':focus-visible' in CSS
    assert 'min-height:44px' in CSS
    assert '@media(prefers-reduced-motion:reduce)' in CSS


def test_readme_and_release_docs_describe_current_boundary_truthfully():
    assert 'Stable tag: 4.3.31' in README
    assert '[sc_reading_notebook_workspace]' in README
    assert '/wp-json/sc-library/v1/reading-notebooks' in README
    assert 'account-persistent' in DOC
    assert 'no automatic AI-generated notes' in DOC
    assert 'no automatic evidence promotion' in DOC
    assert 'No automatic Workspace write occurs' in NOTES


def test_v4330_and_critical_prior_boundaries_are_preserved():
    assert "VERSION = '4.3.30'" in PROJECTS
    assert "'references_only'                => true" in PROJECTS
    assert "'automatic_workspace_write'       => false" in PROJECTS
    assert "CANONICAL_SLUG = 'knowledge-libraries'" in ROUTE
    assert "LEGACY_SLUG = 'library'" in ROUTE
    assert 'data-sc-field-stack="v4.3.22.4"' in STACK
    assert 'data-sc-field-stack-mode="all-fields"' in STACK
