from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_markers():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-publications.php')
    assert 'Version: 4.3.2' in main
    assert "SC_LIBRARY_VERSION', '4.3.2" in main
    assert 'Stable tag: 4.3.2' in readme
    assert "public const VERSION = '4.3.2'" in src
    assert "sc_library_publications_topics_v432" in src


def test_full_registry_is_preserved():
    reg = text(PLUGIN / 'includes/data/publications-article-map-registry-v431.php')
    assert reg.count("'url' =>") == 170
    fields = set(re.findall(r"'field' => '([^']+)'", reg))
    assert len(fields) == 14


def test_article_map_is_first_row_and_four_articles_follow():
    tpl = text(PLUGIN / 'templates/publications.php')
    board = tpl.index('sc-publications__board')
    hero = tpl.index('sc-publications__map-hero')
    articles = tpl.index('sc-publications__articles')
    assert board < hero < articles
    assert '>MAP<' in tpl
    assert "Explore map" in tpl
    assert "Read %s" in tpl
    assert 'reading time' not in tpl.lower()
    assert 'blog roll' not in tpl.lower()


def test_dense_two_by_two_grid_is_removed():
    css = text(PLUGIN / 'assets/css/sc-library-publications.css')
    assert '.sc-publications__board' in css
    assert '.sc-publications__articles li{' in css
    assert 'grid-template-columns:1fr 1fr' not in css
    assert 'min-height:106px' in css
    assert 'li:nth-child(even){background:var(--sc-pub-cream)}' in css


def test_spotlight_parity_lead_treatment():
    css = text(PLUGIN / 'assets/css/sc-library-publications.css')
    for token in [
        '#090909', '#f6f1e7', '#e00000', '#168a4a', 'ui-monospace',
        'linear-gradient(90deg,#f1f1ee 0%,#fff 42%,#fbf7ef 100%)',
        'box-shadow:inset 4px 0 0 var(--sc-pub-red)',
        'box-shadow:inset 4px 0 0 var(--sc-pub-green)',
    ]:
        assert token in css


def test_mobile_rows_remain_linear_and_readable():
    css = text(PLUGIN / 'assets/css/sc-library-publications.css')
    assert '@media(max-width:760px)' in css
    assert 'grid-template-columns:38px minmax(0,1fr)' in css
    assert '.sc-publications__row-action{grid-column:2' in css


def test_spotlight_module_remains_isolated():
    spotlight = text(PLUGIN / 'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.2.0'" in spotlight
    assert 'sc_library_homepage_spotlight_pages_v420' in spotlight
