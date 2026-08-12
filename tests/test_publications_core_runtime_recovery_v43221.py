from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')


def test_release_identity_and_publications_asset_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    publications = text(PLUGIN / 'includes/class-sc-library-publications.php')
    field_spotlights = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.22.1' in main
    assert "SC_LIBRARY_VERSION', '4.3.22.1" in main
    assert 'Stable tag: 4.3.22.1' in readme
    assert "public const VERSION = '4.3.22.1'" in publications
    assert "public const VERSION = '4.3.22.1'" in field_spotlights


def test_original_publications_runtime_is_cache_busted_and_no_longer_v433_only():
    tpl = text(PLUGIN / 'templates/publications.php')
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert 'data-sc-publications="v4.3.22.1"' in tpl
    assert '[data-sc-publications="v4.3.22.1"]' in js
    assert "assets/js/sc-library-publications.js', array(), self::VERSION" in src
    assert 'data-sc-publications="v4.3.3"' not in tpl


def test_original_publications_field_and_map_controls_have_server_fallbacks():
    tpl = text(PLUGIN / 'templates/publications.php')
    for token in [
        'sc_publications_field',
        'sc_publications_map',
        '<a role="tab" class="sc-publications__field-tab',
        '<a role="tab" href="<?php echo esc_url( $topic_href ); ?>" data-area-index=',
        'data-initial-field-key',
        'data-initial-map-key',
    ]:
        assert token in tpl


def test_original_publications_javascript_progressively_enhances_fallback_links():
    js = text(PLUGIN / 'assets/js/sc-library-publications.js')
    css = text(PLUGIN / 'assets/css/sc-library-publications.css')
    for token in [
        "root.classList.add('is-enhanced')",
        "event.preventDefault(); setField(index, false)",
        "event.preventDefault(); setTopic(index, true)",
        "document.createElement('a')",
        "fallback.searchParams.set('sc_publications_field', field.key)",
        "fallback.searchParams.set('sc_publications_map', topic.key)",
    ]:
        assert token in js
    assert '.sc-publications.is-enhanced .sc-publications__area-rail{display:none}' in css
    assert '.sc-publications__area-rail{display:none}' not in css.replace('.sc-publications.is-enhanced .sc-publications__area-rail{display:none}', '')


def test_original_publications_runtime_has_structural_integrity_guard():
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    for token in [
        'public_surface_appears_collapsed',
        'count( $canonical_fields ) > 1 && count( $fields ) <= 1',
        '$canonical_count > 1 && $public_count <= 1',
        'SC_Library_Activator::repair_publication_surface_integrity_runtime();',
        '$this->invalidate_cache();',
    ]:
        assert token in src


def test_single_field_template_marker_matches_current_field_spotlight_runtime():
    single = text(PLUGIN / 'templates/field-spotlight-single.php')
    master = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'data-sc-field-spotlights="v4.3.22.1"' in single
    assert 'data-sc-field-spotlights="v4.3.22.1"' in master
    assert '[data-sc-field-spotlights="v4.3.22.1"]' in js
    assert 'data-sc-field-spotlights="v4.3.13"' not in single


def test_canonical_publications_route_promotes_stale_global_governance_single_shortcode():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    for token in [
        "'global-governance' === $field",
        '$this->is_canonical_publications_page()',
        "is_page( 'publications' )",
        "'publications' === sanitize_title",
        "return $this->render_public( '', $atts );",
    ]:
        assert token in src


def test_canonical_registry_and_v4322_citation_studio_are_preserved():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    citation = text(PLUGIN / 'includes/class-sc-library-citation-studio.php')
    assert reg.count("'url' =>") == 170
    fields = re.findall(r"'field' => '([^']+)'", reg)
    assert len(set(fields)) == 14
    assert fields.count('Global Governance') == 13
    assert 'class-sc-library-publications.php' in main
    assert 'class-sc-library-field-spotlights.php' in main
    assert "public const META_OWNER = '_sc_source_personal_owner'" in citation
    assert "public const USER_COLLECTIONS = 'sc_library_source_collections_v4322'" in citation
