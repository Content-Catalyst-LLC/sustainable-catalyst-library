from pathlib import Path
import json, re, subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
CONSOLE = PLUGIN / 'includes/class-sc-library-homepage-console.php'
NETWORK = PLUGIN / 'includes/class-sc-library-research-network-console.php'
CSS = PLUGIN / 'assets/css/sc-library-homepage-console-v561.css'
JS = PLUGIN / 'assets/js/sc-library-homepage-console-v561.js'
NETWORK_JS = PLUGIN / 'assets/js/sc-library-research-network-console-v560r3.js'
ORCH_JS = PLUGIN / 'assets/js/sc-library-orchestrator.js'
CURRENT_PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v5.6.0-R3.2.1.html'
BASELINE = ROOT / 'tests/fixtures/research-library-v5.4-source-of-truth.html'
MANIFEST = ROOT / 'LIBRARY_CAPABILITY_MANIFEST_v5.6.1.json'


def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')


def uniq(xs):
    return list(dict.fromkeys(xs))


def test_release_identity_and_backend_contract():
    main = text(MAIN)
    assert 'Version: 5.6.1' in main
    assert "SC_LIBRARY_VERSION', '5.6.1'" in main
    assert '__version__ = "1.1.0"' in text(ROOT / 'library-backend/app/__init__.py')
    assert "class-sc-library-homepage-console.php" in main
    assert '$homepage_console->register_hooks();' in main


def test_shortcode_is_library_owned_and_reusable():
    src = text(CONSOLE)
    assert "public const SHORTCODE = 'sc_library_homepage_console'" in src
    assert "['full', 'compact', 'network']" in src
    assert "add_shortcode(self::SHORTCODE" in src
    assert "has_shortcode($content, self::SHORTCODE)" in src
    assert 'sc-library-homepage-console-v561.css' in src
    assert 'sc-library-homepage-console-v561.js' in src


def test_console_reuses_governed_network_registry_instead_of_copying_source_names():
    src = text(CONSOLE)
    network = text(NETWORK)
    assert 'SC_Library_Research_Network_Console::source_registry()' in src
    assert 'SC_Library_Research_Network_Console::source_counts()' in src
    assert 'public static function source_registry()' in network
    assert 'public static function source_counts()' in network
    # Priority is by governed source IDs; names remain owned by the research-network registries.
    for source_id in ['mit', 'harvard', 'ucd', 'yale', 'princeton', 'stanford', 'nypl', 'loc', 'internetarchive', 'openalex', 'crossref', 'europepmc', 'arxiv', 'worldcat']:
        assert f"'{source_id}'" in src
    for source_name in ['MIT Libraries', 'Harvard Library', 'University College Dublin', 'Yale University Library', 'New York Public Library']:
        assert source_name not in src
        assert source_name in network or source_name in text(PLUGIN / 'includes/class-sc-library-institutional-connector-expansion.php') or source_name in text(PLUGIN / 'includes/class-sc-library-public-library-network.php')


def test_live_metrics_use_existing_progressive_explorer_endpoint_and_fail_open():
    src = text(CONSOLE)
    js = text(JS)
    assert "SC_Library_Dynamic_Explorer::REST_NAMESPACE . '/explorer/bootstrap'" in src
    for key in ['public_records', 'topics', 'chunks']:
        assert key in js
    assert 'Live counts unavailable' in src or 'Live counts unavailable' in js
    assert 'LOCAL' in js
    assert 'fetch(cfg.bootstrapUrl' in js
    # There is no direct browser call to the Contabo hostname; WordPress remains the public proxy/fallback boundary.
    assert 'library-api.sustainablecatalyst.com' not in js


def test_homepage_console_surfaces_real_library_capabilities():
    src = text(CONSOLE)
    for phrase in ['Research Librarian', 'Public libraries', 'Open courses', 'Provenance', 'Explore the Research Library', 'Find a Public Library']:
        assert phrase in src
    assert 'SC_Library_Open_Course_Finder::launch_catalog()' in src
    assert 'SC_Library_Public_Library_Network::registry()' in src
    assert 'ENTITLEMENT: NEVER ASSUMED' in src


def test_homepage_search_handoffs_land_in_real_library_tools():
    js = text(JS)
    assert "url.searchParams.set('library_q', query)" in js
    assert "url.searchParams.set('research_query', query)" in js
    assert "url.searchParams.set('librarian_query', query)" in js
    assert "url.hash = 'knowledge-explorer'" in js
    assert "url.hash = 'research-access'" in js
    assert "url.hash = 'research-front-door'" in js
    assert "get('research_query')" in text(NETWORK_JS)
    assert "get('librarian_query')" in text(ORCH_JS)


def test_console_motion_is_bounded_and_accessible():
    css = text(CSS)
    js = text(JS)
    assert 'prefers-reduced-motion:reduce' in css
    assert "prefers-reduced-motion: reduce" in js
    assert 'mouseenter' in js and 'focusin' in js
    assert 'height:306px' in css
    assert 'scrollTo' in js


def test_homepage_controls_have_defensive_visibility_under_site_css():
    css = text(CSS)
    for needle in ['visibility:visible!important', 'opacity:1!important', '-webkit-text-fill-color', 'appearance:none!important']:
        assert needle in css
    assert '.sc-library-home-console__search button' in css
    assert '.sc-library-home-console__actions a' in css


def test_current_research_library_page_is_r321_baseline_and_front_doors_stay_removed():
    page = text(CURRENT_PAGE)
    assert 'R3.2.1 preserves the visible breadth restored in R3.' in page
    assert 'id="research-network"' in page
    assert 'id="research-access"' in page
    assert 'id="public-library-network"' in page
    assert 'id="knowledge-explorer"' in page
    assert 'id="open-course-finder"' in page
    assert 'primary-research-doors' not in page
    assert 'THREE RESEARCH FRONT DOORS' not in page


def test_37_shortcode_and_72_anchor_preservation_gate_remains_intact():
    baseline = text(BASELINE)
    manifest = json.loads(text(MANIFEST))
    shortcodes = uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]', baseline))
    anchors = uniq(re.findall(r'\bid=["\']([^"\']+)["\']', baseline))
    assert len(shortcodes) == 37
    assert len(anchors) == 72
    assert manifest['protected_shortcodes'] == shortcodes
    assert manifest['protected_anchors'] == anchors
    combined = text(CURRENT_PAGE) + '\n' + text(PLUGIN / 'includes/class-sc-library-capability-hub.php')
    for shortcode in shortcodes:
        assert '[' + shortcode in combined, shortcode
    for anchor in anchors:
        assert anchor in combined, anchor


def test_js_and_php_syntax():
    for path in [JS, NETWORK_JS, ORCH_JS]:
        result = subprocess.run(['node', '--check', str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stderr
    for path in [CONSOLE, NETWORK]:
        result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
