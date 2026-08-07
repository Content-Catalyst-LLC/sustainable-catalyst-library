from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_markers_and_module_bootstrap():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.4' in main
    assert "SC_LIBRARY_VERSION', '4.3.4" in main
    assert 'Stable tag: 4.3.4' in readme
    assert "public const VERSION = '4.3.4'" in src
    assert "class-sc-library-field-spotlights.php" in main
    assert '$field_spotlights = new SC_Library_Field_Spotlights();' in main
    assert '$field_spotlights->register_hooks();' in main


def test_fourteen_major_field_definitions_are_explicit_and_ordered():
    fields = text(PLUGIN / 'includes/data/field-spotlight-fields-v434.php')
    slugs = re.findall(r"^    '([^']+)' => array\($", fields, re.M)
    assert len(slugs) == 14
    assert slugs[:4] == [
        'global-governance',
        'sustainable-systems',
        'technology-systems-intelligence',
        'natural-science',
    ]
    assert slugs[-4:] == ['philosophy', 'thinking', 'meaning', 'problem-solving']
    assert "'/category/global-governance/'" in fields
    assert "'/category/problem-solving/'" in fields


def test_canonical_170_map_registry_is_reused_not_duplicated():
    canonical = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert canonical.count("'url' =>") == 170
    assert 'SC_Library_Publications::article_map_registry()' in src
    assert 'field-spotlight-fields-v434.php' in src
    assert 'publications-article-map-registry-v431.php' not in src


def test_nested_taxonomy_groups_become_peer_panels_but_group_metadata_survives():
    canonical = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert "'roman-law-civil-law-tradition' => array(" in canonical
    assert "'common-law-precedent' => array(" in canonical
    assert canonical.count("'group' => 'Legal Traditions'") >= 8
    assert "'source_group' => (string) ( $map['group'] ?? '' )" in src
    assert "$fields[ $field_slug ]['panels'][]" in src
    assert 'parent_panel' not in src
    assert 'nested_panel' not in src


def test_progressive_disclosure_contract_defaults_to_eight_without_panel_cap():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert 'public const DEFAULT_PANEL_LIMIT = 8;' in src
    assert "? 'primary' : 'additional'" in src
    assert "'additional_panel_count'" in src
    assert "min( 24" in src
    assert 'Remaining panels are marked Additional rather than removed.' in src


def test_article_map_is_permanent_position_zero_and_supporting_slots_are_configurable():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert "public const DEFAULT_SLOT_COUNT = 4;" in src
    assert "public const MIN_SLOT_COUNT = 2;" in src
    assert "public const MAX_SLOT_COUNT = 8;" in src
    assert "'hero_role' => 'article_map'" in src
    assert "'role' => 'article_map'" in src
    assert "'canonical_url' => (string) $canonical['canonical_url']" in src
    assert 'Article Map is permanent position 0' in src
    assert 'min="2" max="8"' in src


def test_field_spotlight_supporting_articles_are_manual_only_with_no_backfill_contract():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert "'selection_mode' => 'manual_only'" in src
    assert "'source_id'" in src
    assert "'articles' => $slots" in src
    assert 'No automatic article backfill is defined' in src
    assert 'spotlight_articles_for_topic' not in src
    assert 'articles_from_category' not in src
    assert 'articles_from_pathway' not in src


def test_admin_surface_controls_global_field_and_panel_model():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    for token in [
        'SC Library',
        "'Field Spotlights'",
        "'sc-library-field-spotlights'",
        'Visible panels before expansion',
        'Default supporting article slots',
        'Panel disclosure threshold',
        'Flattened series panels',
        'Source group',
        'Canonical Article Map',
        'Permanent hero source',
        'Save field and panel model',
    ]:
        assert token in src


def test_publications_and_homepage_spotlight_are_not_replaced_in_v434():
    publications = text(PLUGIN / 'includes/class-sc-library-publications.php')
    homepage = text(PLUGIN / 'includes/class-sc-library-homepage-spotlight.php')
    field_spotlights = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const SHORTCODE = 'sc_publications'" in publications
    assert 'sc_library_publications_topics_v433' in publications
    assert "public const VERSION = '4.2.0'" in homepage
    assert 'sc_library_homepage_spotlight_pages_v420' in homepage
    assert 'add_shortcode' not in field_spotlights
