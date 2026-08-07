from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'

def text(p): return p.read_text(encoding='utf-8')

def test_release_markers():
    main=text(PLUGIN/'sustainable-catalyst-library.php')
    assert 'Version: 4.3.0' in main
    assert "SC_LIBRARY_VERSION', '4.3.0" in main
    assert "class-sc-library-publications.php" in main

def test_publications_shortcode_and_no_blog_roll():
    src=text(PLUGIN/'includes/class-sc-library-publications.php')
    assert "SHORTCODE = 'sc_publications'" in src
    assert "page_articles( $page->ID, 4 )" in src
    assert 'blog roll' not in src.lower().replace('no blog-roll mode','')

def test_article_map_hero_and_four_articles_template():
    tpl=text(PLUGIN/'templates/publications.php')
    assert 'sc-publications__map-hero' in tpl
    assert 'sc-publications__articles' in tpl
    assert "Article Map" in tpl
    assert 'reading time' not in tpl.lower()

def test_spotlight_style_tokens_reused():
    css=text(PLUGIN/'assets/css/sc-library-publications.css')
    for token in ['#090909','#f6f1e7','#e00000','ui-monospace']:
        assert token in css

def test_current_spotlight_subject_maps_present():
    src=text(PLUGIN/'includes/class-sc-library-publications.php')
    for slug in ['sustainable-development','planetary-boundaries','international-law','biology','systems-thinking','economics','artificial-intelligence','physics','embedded-edge-systems','psychology','decision-science','data-systems-analytics']:
        assert slug in src
