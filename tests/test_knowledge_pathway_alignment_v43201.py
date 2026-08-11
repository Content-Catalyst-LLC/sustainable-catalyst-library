from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.20.1.html'
CSS = PLUGIN / 'assets' / 'css' / 'sc-library-research-library-page.css'
SHORT = PLUGIN / 'includes' / 'class-sc-library-shortcodes.php'


def text(path):
    return path.read_text(encoding='utf-8')


def test_patch_identity_and_scoped_page_marker():
    main = text(MAIN)
    page = text(PAGE)
    assert 'Version: 4.3.20.1' in main
    assert "define('SC_LIBRARY_VERSION', '4.3.20.1');" in main
    assert 'Research Library v4.3.20.1' in page
    assert 'cc-rl-v43201' in page
    assert 'id="explore-knowledge"' in page


def test_pathway_grid_is_two_columns_without_staggering():
    css = text(CSS)
    assert '#explore-knowledge .cc-rl-pathway-grid' in css
    assert 'grid-template-columns: repeat(2, minmax(0, 1fr)) !important;' in css
    assert 'grid-auto-flow: row !important;' in css
    assert 'justify-items: stretch !important;' in css
    assert 'transform: none !important;' in css
    assert 'position: static !important;' in css
    assert 'margin: 0 !important;' in css


def test_mobile_pathways_collapse_to_one_column():
    css = text(CSS)
    assert '@media (max-width: 760px)' in css
    assert 'grid-template-columns: minmax(0, 1fr) !important;' in css


def test_heading_intro_and_grid_share_left_rail():
    css = text(CSS)
    assert '> .cc-rl-section-heading' in css
    assert '> .cc-rl-section-intro' in css
    assert 'inline-size: 100% !important;' in css
    assert 'margin-inline: 0 !important;' in css
    assert 'text-align: left !important;' in css


def test_alignment_asset_is_enqueued_with_library_surface():
    short = text(SHORT)
    assert "wp_enqueue_style('sc-library-research-library-page'" in short
    assert "assets/css/sc-library-research-library-page.css" in short
    assert "['sc-library-discovery']" in short


def test_eight_question_pathways_and_core_library_remain_present():
    page = text(PAGE)
    assert page.count('<div class="cc-rl-pathway-grid">') == 1
    segment = page.split('<div class="cc-rl-pathway-grid">', 1)[1].split('</div>', 1)[0]
    assert segment.count('<a href=') == 8
    assert 'I want to understand complex systems' in segment
    assert 'I want to explain complex ideas clearly' in segment
    assert '<div class="cc-rl-core-grid">' in page


def test_open_course_and_research_access_are_unchanged():
    page = text(PAGE)
    assert '[sc_research_access ' in page
    assert '[sc_open_course_finder' in page
    assert '[sc_research_librarian_orchestrator' in page
    assert '[sc_library_unified_workspace]' in page
