from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
SRC=(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.19.html').read_text()
JS=(PLUGIN/'assets/js/sc-library-connectors.js').read_text()
CSS=(PLUGIN/'assets/css/sc-library-connectors.css').read_text()
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
README=(PLUGIN/'readme.txt').read_text()
ACT=(PLUGIN/'includes/class-sc-library-activator.php').read_text()

def test_release_identity_and_page_contract():
    assert 'Version: 4.3.19' in MAIN
    assert "SC_LIBRARY_VERSION', '4.3.19'" in MAIN
    assert 'Stable tag: 4.3.19' in README
    assert 'Research Library v4.3.19 — Global Library Search, My Libraries & Digital Access Resolver' in PAGE
    assert 'Signed-in users can now connect My Libraries and Research Libraries' in PAGE

def test_my_libraries_persistence_and_ajax_are_account_scoped():
    assert "USER_META_MY_LIBRARIES = 'sc_library_my_libraries_v4319'" in SRC
    assert "MY_LIBRARIES_SCHEMA = 'sc-library-my-libraries/1.0'" in SRC
    assert "wp_ajax_sc_library_v4319_save_library" in SRC
    assert "wp_ajax_sc_library_v4319_remove_library" in SRC
    assert "wp_ajax_nopriv_sc_library_v4319_save_library" not in SRC
    assert "if ( ! is_user_logged_in() )" in SRC
    assert 'update_user_meta( get_current_user_id(), self::USER_META_MY_LIBRARIES' in SRC

def test_global_registry_contains_launch_public_and_research_libraries():
    for name in (
        'MIT Libraries','Harvard Library','Stanford University Libraries','Yale University Library',
        'Princeton University Library','Columbia University Libraries','UC Berkeley Library',
        'University College Dublin Library','University of Copenhagen','Stockholm University Library',
        'Wageningen University & Research','Lund University Libraries','ETH Library',
        'Bodleian Libraries / Oxford','Cambridge University Libraries','Chicago Public Library',
        'St. Louis Public Library','New York Public Library','Library of Congress','WorldCat'):
        assert name in SRC

def test_public_access_stays_open_and_account_adds_persistence():
    assert 'Public Research Access remains open.' in SRC
    assert 'An account is not required to search public sources.' in SRC
    assert 'Sign in with your Sustainable Catalyst / Workspace account' in SRC
    assert 'University affiliation is not required for public discovery.' in SRC

def test_my_libraries_interface_and_custom_library_safety():
    assert 'data-sc-my-libraries' in SRC
    assert 'data-sc-connect-library-form' in SRC
    assert 'data-sc-custom-library-form' in SRC
    assert 'Catalog search URL template' in SRC
    assert 'Library passwords stay with the library' in SRC
    assert 'wp_http_validate_url' in SRC
    assert 'sanitize_catalog_template' in SRC
    assert 'proxy' not in SRC.split('public function ajax_save_my_library',1)[1].split('public function ajax_remove_my_library',1)[0].lower()

def test_access_resolver_prioritizes_open_then_connected_library_routes():
    assert 'private function rank_access_locations' in SRC
    block=SRC.split('private function rank_access_locations',1)[1].split('private function research_gateway_registry',1)[0]
    assert "'public-digital' => 10" in block
    assert "'open-access' => 12" in block
    assert "'my-library-search' => 35" in block
    assert "'interlibrary-loan' => 60" in block
    assert 'bestAccessRoute' in JS
    assert 'Best legitimate access route' in JS
    assert 'Check My Libraries:' in JS

def test_live_search_updates_connected_library_queries():
    assert "[data-sc-research-gateway], [data-sc-my-library-search]" in JS
    assert "sc_library_v4319_save_library" in JS
    assert "sc_library_v4319_remove_library" in JS
    assert 'sc-connector-result-card__resolver' in CSS
    assert 'sc-research-access__my-libraries' in CSS

def test_publications_integrity_recovery_remains_in_base():
    assert 'sc_library_publications_integrity_repair_v43181' in ACT
    assert 'repair_publication_surface_integrity' in ACT
