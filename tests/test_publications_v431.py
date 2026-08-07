from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'

def text(p): return p.read_text(encoding='utf-8')

def test_release_markers():
    main=text(PLUGIN/'sustainable-catalyst-library.php')
    readme=text(PLUGIN/'readme.txt')
    assert 'Version: 4.3.1' in main
    assert "SC_LIBRARY_VERSION', '4.3.1" in main
    assert 'Stable tag: 4.3.1' in readme

def test_full_registry_contract():
    reg=text(PLUGIN/'includes/data/publications-article-map-registry-v431.php')
    assert reg.count("'url' =>") == 170
    fields=set(re.findall(r"'field' => '([^']+)'", reg))
    assert len(fields) == 14
    for field in ['Global Governance','Sustainable Systems','Technology & Systems Intelligence','Natural Science','Cultural Anthropology','Literature & Cultural Memory','Mythology','Religious Studies','Healing Traditions','Psychology','Philosophy','Thinking','Meaning','Problem Solving']:
        assert field in fields

def test_nested_maps_and_required_routes_present():
    reg=text(PLUGIN/'includes/data/publications-article-map-registry-v431.php')
    for route in [
        '/international-law/','/ancient-near-eastern-law-early-legal-codes/',
        '/artificial-intelligence-systems/','/energy-systems/','/physics/',
        '/positive-psychology/','/behavioral-science-behavioral-psychology/',
        '/political-philosophy-and-justice/','/metaphysics/',
        '/mathematical-modeling/','/calculus-for-systems-modeling/',
        '/arduino-projects-sustainable-development/'
    ]:
        assert route in reg

def test_resolver_cascade_and_four_article_limit():
    src=text(PLUGIN/'includes/class-sc-library-publications.php')
    assert "public const VERSION = '4.3.1'" in src
    assert 'spotlight_articles_for_topic' in src
    assert 'articles_from_map_post' in src
    assert 'articles_from_pathway' in src
    assert 'articles_from_category' in src
    assert "min( 4, $limit )" in src
    assert 'sc_library_publications_articles_for_topic' in src
    assert 'sc_library_publications_registry' in src

def test_all_maps_remain_visible_without_fake_fillers():
    src=text(PLUGIN/'includes/class-sc-library-publications.php')
    tpl=text(PLUGIN/'templates/publications.php')
    assert "'unresolved'" in src
    assert 'is-incomplete' in tpl
    assert 'sc-publications__map-hero' in tpl
    assert 'if ( $topic[\'articles\'] )' in tpl
    assert 'reading time' not in tpl.lower()
    assert 'blog roll' not in tpl.lower()

def test_spotlight_visual_contract_preserved():
    css=text(PLUGIN/'assets/css/sc-library-publications.css')
    for token in ['#090909','#f6f1e7','#e00000','ui-monospace','sc-publications__map-hero','sc-publications__articles']:
        assert token in css
    spotlight=text(PLUGIN/'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.2.0'" in spotlight
