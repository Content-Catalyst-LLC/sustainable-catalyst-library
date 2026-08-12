from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
BOOT = PLUGIN / 'includes' / 'class-sc-library-extension-bootstrap-v402.php'
STUDIO = PLUGIN / 'includes' / 'class-sc-library-citation-studio.php'
CONNECTORS = PLUGIN / 'includes' / 'class-sc-library-scholarly-library-connectors.php'
CONNECTOR_JS = PLUGIN / 'assets' / 'js' / 'sc-library-connectors.js'
STUDIO_JS = PLUGIN / 'assets' / 'js' / 'sc-library-citation-studio.js'
STUDIO_CSS = PLUGIN / 'assets' / 'css' / 'sc-library-citation-studio.css'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.22.html'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_identity_and_citation_studio_page_contract():
    main = text(MAIN)
    page = text(PAGE)
    assert 'Version: 4.3.22' in main
    assert "define('SC_LIBRARY_VERSION', '4.3.22');" in main
    assert 'id="citation-studio"' in page
    assert '[sc_citation_studio limit="100" style="harvard"]' in page
    assert 'Save, Organize, and Cite Sources' in page
    assert 'href="#citation-studio">Citation Studio' in page


def test_citation_studio_is_isolated_extension_and_account_owned():
    boot = text(BOOT)
    studio = text(STUDIO)
    assert 'public const MODULE_COUNT = ' in boot
    import re
    match = re.search(r'public const MODULE_COUNT = (\d+);', boot)
    assert match and int(match.group(1)) >= 27
    assert "'class-sc-library-citation-studio.php' => 'SC_Library_Citation_Studio'" in boot
    for token in [
        "public const META_OWNER = '_sc_source_personal_owner'",
        "public const USER_COLLECTIONS = 'sc_library_source_collections_v4322'",
        "'post_status' => 'private'",
        "'post_author' => $user_id",
        'get_current_user_id() === $owner',
        'Sign in with your Sustainable Catalyst / Workspace account',
    ]:
        assert token in studio


def test_multiple_citation_profiles_are_registered_without_removing_harvard():
    studio = text(STUDIO)
    for token in [
        "'apa-7'", "'mla-9'", "'chicago-author-date'",
        "'chicago-notes-bibliography'", "'ieee'", "'vancouver'",
        "'ama'", "'acs-author-date'",
    ]:
        assert token in studio
    assert "if ( 'harvard' === $style" in studio
    assert "add_filter( 'sc_library_citation_styles'" in studio
    assert "add_filter( 'sc_library_format_citation'" in studio


def test_source_manager_supports_notes_collections_copy_and_exports():
    studio = text(STUDIO)
    js = text(STUDIO_JS)
    for token in [
        'Create collection', 'Private note', 'Copy Citation', 'Copy In-Text',
        'data-sc-export-format="bibtex"', 'data-sc-export-format="ris"',
        'data-sc-export-format="csl-json"', 'to_csl_json', 'export_bibtex', 'export_ris',
    ]:
        assert token in studio
    for token in ['new Blob(', 'download(', 'sc_library_v4322_export_sources', 'navigator.clipboard']:
        assert token in js


def test_research_access_can_save_normalized_results_to_my_sources():
    connectors = text(CONNECTORS)
    js = text(CONNECTOR_JS)
    for token in [
        "wp_ajax_sc_library_v4322_save_result",
        'ajax_save_personal_result',
        "'canSavePersonal'  => is_user_logged_in()",
        'SC_Library_Citation_Studio::save_normalized_result',
    ]:
        assert token in connectors
    for token in [
        'Save to My Sources', 'data-sc-save-source-token',
        'sc_library_v4322_save_result', 'Open Citation Studio →'
    ]:
        assert token in js


def test_source_normalization_and_duplicate_boundaries_are_present():
    studio = text(STUDIO)
    for token in [
        'normalize_doi', 'normalize_isbn', 'find_personal_duplicate',
        'META_DOI', 'META_ISBN', 'rebuild_source_indexes',
        'META_PROVENANCE', 'Saved from Research Access provider',
    ]:
        assert token in studio


def test_citation_studio_ui_is_restrained_and_responsive():
    css = text(STUDIO_CSS)
    for token in [
        '.sc-citation-studio__source-index',
        'border-top:2px solid var(--cs-ink)',
        'grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr)',
        '@media(max-width:900px)', '@media(max-width:620px)'
    ]:
        assert token in css
    assert 'border-radius:0' in css


def test_publications_recovery_course_finder_and_research_access_remain_present():
    page = text(PAGE)
    activator = text(PLUGIN / 'includes' / 'class-sc-library-activator.php')
    field = text(PLUGIN / 'includes' / 'class-sc-library-field-spotlights.php')
    assert '[sc_research_access ' in page
    assert '[sc_open_course_finder' in page
    assert '[sc_research_librarian_orchestrator]' in page
    assert '[sc_library_unified_workspace]' in page
    assert 'sc_library_publications_integrity_repair_v43181' in activator
    assert "public const VERSION = '4.3.21.1'" in field
