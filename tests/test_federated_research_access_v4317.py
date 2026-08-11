from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.17.html'
def text(p): return p.read_text(encoding='utf-8')

def test_release_identity_and_access_is_first():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt'); page=text(PAGE)
    assert 'Version: 4.3.17' in main
    assert "SC_LIBRARY_VERSION', '4.3.17" in main
    assert 'Stable tag: 4.3.17' in readme
    assert 'href="#research-access">Search Research Access</a>' in page
    assert page.index('id="research-access"') < page.index('id="research-front-door"')
    assert '[sc_research_access providers="internetarchive,mit,harvard,loc"' in page
    assert 'university affiliation is not required' in page.lower()

def test_public_research_access_shortcode_and_public_ajax_are_bounded():
    src=text(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php')
    assert "add_shortcode( 'sc_research_access'" in src
    assert "wp_ajax_nopriv_sc_library_v4317_research_access_search" in src
    assert "$public_providers = array( 'internetarchive', 'mit', 'harvard', 'loc' );" in src
    assert "max( 1, min( 10" in src
    assert "enforce_rate_limit( 'research-access', 36, 10 * MINUTE_IN_SECONDS )" in src
    assert 'University affiliation is not required' in src
    assert 'Library passwords stay with the library' in src

def test_launch_direct_connectors_are_real_methods():
    src=text(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php')
    rel=text(PLUGIN/'includes/class-sc-library-connector-holdings-reliability.php')
    for method in ['search_internetarchive','search_mit','search_harvard','search_loc']:
        assert f'function {method}' in src
    assert 'https://archive.org/advancedsearch.php' in src
    assert 'https://timdex.mit.edu/graphql' in src
    assert 'https://api.lib.harvard.edu/v2/items.dc.json' in src
    assert 'https://www.loc.gov/search/' in src
    for host in ['archive.org','timdex.mit.edu','api.lib.harvard.edu','escholarship.org']:
        assert f"'{host}'" in rel
    assert 'request_json_post' in rel

def test_berkeley_is_visible_but_capability_honest():
    src=text(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php')
    assert "'berkeley' => array(" in src
    block=src[src.index("'berkeley' => array("):src.index("'loc' => array(")]
    assert "'search'            => false" in block
    assert 'Public repository access route; deeper federated adapter staged' in block
    assert 'https://escholarship.org/search/?q={query}' in src

def test_access_states_and_public_ui_contract():
    js=text(PLUGIN/'assets/js/sc-library-connectors.js'); css=text(PLUGIN/'assets/css/sc-library-connectors.css')
    assert "'public-digital': 'Open digital access'" in js
    assert "'public-catalog': 'Public catalog record'" in js
    assert "document.querySelectorAll('[data-sc-research-access]')" in js
    assert "sc_library_v4317_research_access_search" in js
    assert 'Open resources are shown with explicit access labels.' in js
    assert '.sc-research-access' in css
    assert 'border-top: 6px solid #000' in css
    assert 'grid-template-columns: repeat(5,minmax(0,1fr))' in css

def test_prior_research_librarian_and_field_spotlight_boundaries_remain():
    page=text(PAGE); js=text(PLUGIN/'assets/js/sc-library-orchestrator.js'); field=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'button_label="Ask the Research Librarian"' in page
    assert 'show_librarian="true" librarian_target="#research-front-door"' in page
    assert 'Recommended Knowledge Pathways' in js
    assert 'window.confirm(cfg.strings?.confirmAction' in js
    assert "public const VERSION = '4.3.13'" in field


def test_internet_archive_advanced_search_uses_repeated_field_parameters():
    src = text(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php')
    assert "fl%5B%5D=" in src
    assert "http_build_query( $params" not in src


def test_berkeley_handoff_uses_public_escholarship_search_route():
    src = text(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php')
    assert "https://escholarship.org/search/?q={query}" in src
