from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')


def test_release_identity_and_stack_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.22.4' in main
    assert "SC_LIBRARY_VERSION', '4.3.22.4" in main
    assert 'Stable tag: 4.3.22.4' in readme
    assert "public const VERSION = '4.3.22.4'" in spots
    assert 'restores the complete 14-field Publications stack' in main


def test_stack_template_renders_every_field_not_one_shared_stage():
    tpl = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'data-sc-field-stack="v4.3.22.4"' in tpl
    assert 'data-sc-field-stack-mode="all-fields"' in tpl
    assert 'foreach ( $field_list as $index => $stack_field )' in tpl
    assert '$field = $stack_field;' in tpl
    assert '$include_data = true;' in tpl
    assert 'sc-field-spotlights--stack-item' in tpl
    assert 'data-sc-field-spotlights-mode="single"' in tpl
    assert "include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php';" in tpl
    assert 'sc-field-spotlights__master-data' not in tpl
    assert 'sc-field-spotlight--master-stage' not in tpl
    assert '$initial_field = $field_list[0]' not in tpl


def test_stack_index_is_jump_navigation_not_a_field_switcher():
    tpl = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'sc-field-stack__jump-grid' in tpl
    assert 'href="#<?php echo esc_attr( $field_anchor ); ?>"' in tpl
    assert 'All major fields are rendered below.' in tpl
    assert 'data-field-select-key' not in tpl
    assert 'data-field-select' not in tpl
    assert '<select' not in tpl


def test_canonical_registry_still_contains_14_fields_and_170_maps():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    fields = re.findall(r"'field' => '([^']+)'", reg)
    assert len(set(fields)) == 14
    assert reg.count("'url' =>") == 170
    definitions = text(PLUGIN / 'includes/data/field-spotlight-fields-v434.php')
    assert definitions.count("'title' =>") == 14


def test_first_eight_panel_tier_and_additional_disclosure_are_preserved_per_field():
    partial = text(PLUGIN / 'templates/field-spotlight-stage.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert '$limit = 8;' in partial
    assert 'array_slice( $panels, 0, $limit )' in partial
    assert 'array_slice( $panels, $limit )' in partial
    assert 'data-more-toggle' in partial
    assert 'data-additional-tabs' in partial
    assert 'secondaryExpanded = !secondaryExpanded' in js
    assert 'all.slice(0, 8)' in js


def test_each_stacked_field_is_an_independent_runtime_root():
    tpl = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'data-sc-field-spotlights="v4.3.22.4"' in tpl
    assert 'data-sc-field-spotlights-mode="single"' in tpl
    assert 'class="sc-field-spotlight__data"' in text(PLUGIN / 'templates/field-spotlight-stage.php')
    assert '[data-sc-field-spotlights="v4.3.22.4"]' in js
    assert 'initializeSingle(root)' in js
    assert '[sc_field_spotlights] now emits 14 independent single-field roots' in js


def test_stack_uses_current_institutional_visual_treatment():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    for token in [
        'v4.3.22.4 — Publications 14-Field Stack Restoration',
        '.sc-field-stack__fields{display:grid;gap:46px}',
        '.sc-field-spotlights--stack-item>.sc-field-spotlight',
        'border-top:6px solid #000',
        '.sc-field-spotlights--stack-item .sc-field-spotlight__masthead',
        '.sc-field-spotlights--stack-item .sc-field-spotlight__tab.is-active',
        'box-shadow:inset 0 -3px 0 var(--scfs-red)',
    ]:
        assert token in css


def test_stale_global_governance_shortcode_promotes_to_full_stack_only_on_publications():
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert "if ( 'global-governance' === $field && $this->is_canonical_publications_page() )" in spots
    assert "return $this->render_public( '', $atts );" in spots
    assert 'public function shortcode_single' in spots
    assert 'return $this->render_public( $field, $atts );' in spots



def test_legacy_sc_publications_shortcode_is_promoted_to_stack_on_canonical_page():
    pubs = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert "if ( $this->is_canonical_publications_page() && class_exists( 'SC_Library_Field_Spotlights', false ) )" in pubs
    assert '$stack = new SC_Library_Field_Spotlights();' in pubs
    assert "return $stack->shortcode_stack( array( 'autoplay' => 'true', 'pause_on_hover' => 'true' ) );" in pubs
    assert "return 'publications' === sanitize_title" in pubs

def test_editorial_data_and_current_research_features_are_not_rewritten():
    spots = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    citation = text(PLUGIN / 'includes/class-sc-library-citation-studio.php')
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in spots
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in spots
    assert 'update_option( self::PANEL_CONTENT_OPTION, $store, false )' in spots
    assert "public const META_OWNER = '_sc_source_personal_owner'" in citation
