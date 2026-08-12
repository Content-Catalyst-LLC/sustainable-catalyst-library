from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'

def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')

def test_release_identity_and_shared_runtime_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    pubs = text(PLUGIN / 'includes/class-sc-library-publications.php')
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.22.2' in main
    assert "SC_LIBRARY_VERSION', '4.3.22.2" in main
    assert 'Stable tag: 4.3.22.2' in readme
    assert "public const VERSION = '4.3.22.2'" in pubs
    assert "public const VERSION = '4.3.22.2'" in spots


def test_core_publications_server_links_are_authoritative():
    tpl = text(PLUGIN / 'templates/publications.php')
    for token in [
        'sc_publications_field', 'sc_publications_map',
        'data-field-key="<?php echo esc_attr( (string) $field[\'key\'] ); ?>"',
        'data-sc-publications-runtime-state="server"',
        'data-field-key="<?php echo esc_attr( (string) $initial_field[\'key\'] ); ?>"',
        'data-map-key="<?php echo esc_attr( (string) $initial_topic[\'key\'] ); ?>"',
    ]:
        assert token in tpl


def test_core_field_click_prevents_default_only_after_verified_switch():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    segment = js[js.index("fieldTabs.forEach(function (link)"):js.index("root.querySelectorAll('[data-area-previous]')")]
    assert 'if (!isPlainPrimaryClick(event)) return;' in segment
    assert 'setField(index, false) && verifyField(data.fields[index])' in segment
    assert 'event.preventDefault();' in segment
    assert segment.index('setField(index, false)') < segment.index('event.preventDefault();')
    assert 'event.preventDefault(); setField' not in js
    assert "reportFailure(root, 'field-switch-exception'" in js


def test_core_topic_click_is_also_fail_open_and_links_remain_real_urls():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    assert "link.href = buildFallbackUrl(root, field.key, topic.key) || '#';" in js
    assert 'setTopic(index, true) && verifyTopic(field, topic)' in js
    topic_segment = js[js.index("link.addEventListener('click'"):js.index('rail.appendChild(link)')]
    assert topic_segment.index('setTopic(index, true)') < topic_segment.index('event.preventDefault();')


def test_core_enhancement_is_committed_only_after_initial_verification():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    initial = js.index("if (!renderField(false, false))")
    enhanced = js.index("root.classList.add('is-enhanced')")
    assert initial < enhanced
    assert "markRuntime(root, 'ready')" in js
    assert "root.classList.remove('is-enhanced')" in js
    assert 'data-sc-publications-runtime-state' in js


def test_field_spotlight_master_field_switch_is_fail_open():
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    tpl = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'data-sc-field-spotlights="v4.3.22.2"' in tpl
    assert 'data-sc-field-spotlights-runtime-state="server"' in tpl
    assert 'data-field-fallback="server"' in tpl
    segment = js[js.index("tab.addEventListener('click', (event) => {"):js.index("tab.addEventListener('keydown'", js.index("tab.addEventListener('click', (event) => {"))]
    assert 'activateField(i) && root.dataset.activeField === tab.dataset.fieldSelectKey' in segment
    assert segment.index('activateField(i)') < segment.index('event.preventDefault();')
    assert "runtimeFailure(root, 'field-switch-exception'" in segment


def test_field_spotlight_panel_switch_is_fail_open():
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    segment = js[js.index("panelNav?.addEventListener('click'"):js.index("panelNav?.addEventListener('keydown'")]
    assert 'plainPrimaryClick(event)' in segment
    assert 'activate(i) && spotlight.dataset.activePanelKey === tab.dataset.panelKey' in segment
    assert segment.index('activate(i)') < segment.index('event.preventDefault();')
    assert 'panel-switch-exception' in segment


def test_both_browser_runtimes_are_cache_busted_to_v43222():
    pub_js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    spot_js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    pub_tpl = text(PLUGIN / 'templates/publications.php')
    spot_tpl = text(PLUGIN / 'templates/field-spotlights.php')
    single = text(PLUGIN / 'templates/field-spotlight-single.php')
    assert "var RUNTIME = 'v4.3.22.2';" in pub_js
    assert '[data-sc-field-spotlights="v4.3.22.2"]' in spot_js
    assert 'data-sc-publications="v4.3.22.2"' in pub_tpl
    assert 'data-sc-field-spotlights="v4.3.22.2"' in spot_tpl
    assert 'data-sc-field-spotlights="v4.3.22.2"' in single


def test_registry_citation_studio_and_previous_integrity_repairs_are_preserved():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    pubs = text(PLUGIN / 'includes/class-sc-library-publications.php')
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    citation = text(PLUGIN / 'includes/class-sc-library-citation-studio.php')
    assert reg.count("'url' =>") == 170
    fields = re.findall(r"'field' => '([^']+)'", reg)
    assert len(set(fields)) == 14
    assert 'public_surface_appears_collapsed' in pubs
    assert "'global-governance' === $field" in spots
    assert "public const META_OWNER = '_sc_source_personal_owner'" in citation
