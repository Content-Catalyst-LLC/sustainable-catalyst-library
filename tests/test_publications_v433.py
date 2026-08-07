from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_markers_and_cache_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert 'Version: 4.3.3' in main
    assert "SC_LIBRARY_VERSION', '4.3.3" in main
    assert 'Stable tag: 4.3.3' in readme
    assert "public const VERSION = '4.3.3'" in src
    assert 'sc_library_publications_topics_v433' in src


def test_canonical_fourteen_field_170_map_registry_is_preserved():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    assert reg.count("'url' =>") == 170
    fields = re.findall(r"'field' => '([^']+)'", reg)
    counts = {field: fields.count(field) for field in set(fields)}
    assert len(counts) == 14
    assert counts['Global Governance'] == 13
    assert counts['Natural Science'] == 7
    assert counts['Psychology'] == 19
    assert counts['Philosophy'] == 27
    assert counts['Problem Solving'] == 14


def test_public_page_is_one_dynamic_stage_not_170_rendered_boards():
    tpl = text(PLUGIN / 'templates/publications.php')
    assert 'sc-publications__field-deck' in tpl
    assert 'sc-publications__viewport' in tpl
    assert tpl.count('class="sc-publications__stage"') == 1
    assert tpl.count('class="sc-publications__board"') == 1
    assert 'sc-publications__area-rail' in tpl
    assert 'sc-publications__area-select' in tpl
    assert 'data-area-previous' in tpl and 'data-area-next' in tpl
    assert 'sc-publications__field+' not in tpl
    assert 'Back to fields' not in tpl


def test_dynamic_javascript_supports_field_flip_area_jump_keyboard_swipe_and_no_autoplay():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    for token in ['setField', 'setTopic', 'step(delta)', 'ArrowLeft', 'ArrowRight', 'touchstart', 'touchend', 'data-area-index', 'history.replaceState']:
        assert token in js
    assert 'setInterval' not in js
    assert 'autoplay' not in js.lower()


def test_spotlight_visual_language_is_sharpened_and_compact():
    css = text(PLUGIN / 'assets/css/sc-library-publications.css')
    for token in [
        '#090909', '#f6f1e7', '#e00000', '#168a4a', 'ui-monospace',
        'grid-template-columns:repeat(4,minmax(0,1fr))',
        'sc-publications__field-tab.is-active',
        'linear-gradient(90deg,#efefec 0%,#fff 43%,#fbf7ef 100%)',
        'box-shadow:inset 5px 0 0 var(--sc-pub-red)',
        'min-height:570px',
    ]:
        assert token in css
    assert '.sc-publications__field+.sc-publications__field' not in css


def test_article_map_hero_plus_four_article_contract_remains():
    tpl = text(PLUGIN / 'templates/publications.php')
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert 'sc-publications__map-hero' in tpl
    assert 'for ( $i = 0; $i < 4; $i++ )' in tpl
    assert 'min( 4, $limit )' in src
    assert 'reading time' not in tpl.lower()
    assert 'blog roll' not in tpl.lower()


def test_editorial_customization_console_covers_visible_copy_fields_maps_and_manual_four():
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    for token in [
        'SETTINGS_OPTION',
        'sc-library-publications',
        'register_settings',
        'sanitize_settings',
        "'eyebrow' =>",
        "'fields_label' =>",
        "'areas_label' =>",
        "'map_label' =>",
        "'map_cta' =>",
        "'previous_label' =>",
        "'next_label' =>",
        "'default_map' =>",
        "'description' =>",
        "'visible' =>",
        "'articles' => array()",
        'Optional manual four',
    ]:
        assert token in src


def test_manual_curation_is_optional_and_resolver_cascade_remains():
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert 'manual_articles' in src
    assert "'manual'" in src
    for token in ['spotlight_articles_for_topic', 'articles_from_map_post', 'articles_from_pathway', 'articles_from_category']:
        assert token in src
    assert 'sc_library_publications_articles_for_topic' in src


def test_homepage_spotlight_remains_isolated_at_v420():
    spotlight = text(PLUGIN / 'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.2.0'" in spotlight
    assert 'sc_library_homepage_spotlight_pages_v420' in spotlight
