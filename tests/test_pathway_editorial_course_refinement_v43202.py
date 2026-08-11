from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.20.2.html'
CSS = PLUGIN / 'assets/css' / 'sc-library-research-library-page.css'
COURSE = PLUGIN / 'includes' / 'class-sc-library-open-course-finder.php'
COURSE_CSS = PLUGIN / 'assets/css' / 'sc-library-open-course-finder.css'

def text(path): return path.read_text(encoding='utf-8')

def test_release_identity_and_page_marker():
    main, page = text(MAIN), text(PAGE)
    assert 'Version: 4.3.20.2' in main
    assert "define('SC_LIBRARY_VERSION', '4.3.20.2');" in main
    assert 'Research Library v4.3.20.2' in page
    assert 'cc-rl-v43202' in page

def test_pathways_are_editorial_index_not_card_grid():
    page = text(PAGE)
    segment = page.split('id="explore-knowledge"',1)[1].split('id="research-librarian"',1)[0]
    assert 'cc-rl-pathway-index' in segment
    assert 'Explore by Question' in segment
    assert 'Explore by Field' in segment
    assert 'cc-rl-pathway-grid' not in segment
    assert 'cc-rl-core-grid' not in segment

def test_eight_numbered_questions_and_five_fields_remain():
    page = text(PAGE)
    segment = page.split('<ol class="cc-rl-pathway-index__list">',1)[1].split('</ol>',1)[0]
    assert segment.count('<li>') == 8
    assert '01</span>' in segment and '08</span>' in segment
    fields = page.split('class="cc-rl-pathway-index__field-list"',1)[1].split('</nav>',1)[0]
    assert fields.count('<a href=') == 5
    assert 'Sustainable Systems' in fields
    assert 'Global Governance' in fields

def test_editorial_css_uses_rules_and_split_layout():
    css = text(CSS)
    assert '.cc-rl-pathway-index {' in css
    assert 'grid-template-columns: minmax(0, 1.65fr) minmax(250px, .75fr);' in css
    assert 'border-top: 2px solid #111;' in css
    assert 'border-inline-start: 1px solid #bdb8af;' in css
    assert '@media (max-width: 780px)' in css
    assert 'grid-template-columns: minmax(0, 1fr);' in css

def test_ucph_sdg_course_is_course_level_verified_free():
    source = text(COURSE)
    assert "'id' => 'ucph-global-sdgs'" in source
    assert "'institution' => 'University of Copenhagen'" in source
    assert "'title' => 'The Sustainable Development Goals – A global, transdisciplinary vision for the future'" in source
    assert "'provider' => 'coursera'" in source
    assert "'access' => 'free-course'" in source
    assert "'access_label' => 'Free Course'" in source
    assert 'https://www.coursera.org/learn/global-sustainable-development' in source

def test_course_level_free_filter_and_badge_exist():
    source, css = text(COURSE), text(COURSE_CSS)
    assert 'value="free-course"' in source
    assert 'Verified free course' in source
    assert 'Verified Free Course' in source
    assert '.is-free-open,.is-free-course,.is-free-certificate' in css
    assert 'sc-course-card__access-note' in source

def test_provider_default_is_not_globally_changed_to_free():
    source = text(COURSE)
    coursera = source.split("'coursera' => array(",1)[1].split('),',1)[0]
    assert "'access'          => 'free-preview'" in coursera
    assert "'access_label'    => 'Free Preview'" in coursera

def test_research_access_course_finder_librarian_workspace_preserved():
    page = text(PAGE)
    assert '[sc_research_access ' in page
    assert '[sc_open_course_finder' in page
    assert '[sc_research_librarian_orchestrator' in page
    assert '[sc_library_unified_workspace]' in page
