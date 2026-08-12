from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')


def test_release_identity_and_runtime_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    pubs = text(PLUGIN / 'includes/class-sc-library-publications.php')
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.22.3' in main
    assert "SC_LIBRARY_VERSION', '4.3.22.3" in main
    assert 'Stable tag: 4.3.22.3' in readme
    assert "public const VERSION = '4.3.22.3'" in pubs
    assert "public const VERSION = '4.3.22.3'" in spots


def test_core_publications_field_links_are_server_authoritative():
    tpl = text(PLUGIN / 'templates/publications.php')
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    assert 'data-sc-publications-navigation="server"' in tpl
    assert "'sc_publications_field' => (string) $field['key']" in tpl
    assert 'data-field-fallback="server"' in tpl
    assert 'major-field tabs are server-authoritative anchors' in js
    assert "link.addEventListener('click'" not in js
    assert 'setField(index, false) && verifyField' not in js
    assert 'prevent native navigation only after a verified switch' not in js


def test_core_article_map_links_are_server_authoritative():
    tpl = text(PLUGIN / 'templates/publications.php')
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    assert "'sc_publications_map' => (string) $topic['key']" in tpl
    assert 'data-area-fallback="server"' in tpl
    rail = js[js.index('function rebuildRail(field)'):js.index('function renderArticles(topic)')]
    assert "link.href = buildFallbackUrl(root, field.key, topic.key) || '#';" in rail
    assert "link.addEventListener('click'" not in rail
    assert 'direct Article Map links are intentionally not intercepted' in rail


def test_core_server_query_state_wins_over_legacy_hash_state():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    assert 'legacy hash state no longer changes fields' in js
    assert '        findHashState();\n' not in js
    assert 'data-initial-field-key' in text(PLUGIN / 'templates/publications.php')
    assert 'data-initial-map-key' in text(PLUGIN / 'templates/publications.php')


def test_field_spotlight_master_field_links_are_never_intercepted():
    tpl = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'data-sc-field-navigation="server"' in tpl
    assert 'data-field-fallback="server"' in tpl
    assert "'sc_publication_field' => (string) $selector_field['key']" in tpl
    master = js[js.index('fieldTabs.forEach((tab) => {', js.index('const initializeMaster')):js.index('fieldSelect?.addEventListener', js.index('const initializeMaster'))]
    assert "tab.addEventListener('click'" not in master
    assert 'do not install a click handler' in master
    assert 'activateField(requested, true)' not in master
    assert "fieldTabs[requested]?.focus" in master


def test_field_spotlight_mobile_select_uses_normal_server_navigation():
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    segment = js[js.index("fieldSelect?.addEventListener('change'", js.index('const initializeMaster')):js.index("root.dataset.activeField", js.index("fieldSelect?.addEventListener('change'", js.index('const initializeMaster')))]
    assert "url.searchParams.set('sc_publication_field', key)" in segment
    assert "url.searchParams.delete('sc_publication_panel')" in segment
    assert 'window.location.assign(url.href)' in segment
    assert 'activateField(i)' not in segment


def test_field_spotlight_direct_panel_tabs_are_server_authoritative():
    tpl = text(PLUGIN / 'templates/field-spotlight-stage.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'data-panel-fallback="server"' in tpl
    assert "'sc_publication_panel' => (string) $panel['key']" in tpl
    segment = js[js.index("panelNav?.addEventListener('click'"):js.index('const disclosure', js.index("panelNav?.addEventListener('click'"))]
    assert 'direct panel tabs are server-authoritative links' in segment
    assert 'event.preventDefault()' not in segment
    assert 'activate(i)' not in segment


def test_both_runtime_assets_are_cache_busted_to_v432223():
    pub_js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    spot_js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    pub_tpl = text(PLUGIN / 'templates/publications.php')
    spot_tpl = text(PLUGIN / 'templates/field-spotlights.php')
    single = text(PLUGIN / 'templates/field-spotlight-single.php')
    assert "var RUNTIME = 'v4.3.22.3';" in pub_js
    assert '[data-sc-field-spotlights="v4.3.22.3"]' in spot_js
    assert 'data-sc-publications="v4.3.22.3"' in pub_tpl
    assert 'data-sc-field-spotlights="v4.3.22.3"' in spot_tpl
    assert 'data-sc-field-spotlights="v4.3.22.3"' in single


def test_registry_and_current_research_features_are_preserved():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    citation = text(PLUGIN / 'includes/class-sc-library-citation-studio.php')
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert reg.count("'url' =>") == 170
    fields = re.findall(r"'field' => '([^']+)'", reg)
    assert len(set(fields)) == 14
    assert "public const META_OWNER = '_sc_source_personal_owner'" in citation
    assert "'global-governance' === $field" in spots
