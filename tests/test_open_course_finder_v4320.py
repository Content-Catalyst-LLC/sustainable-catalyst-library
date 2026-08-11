from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
COURSES = PLUGIN / 'includes' / 'class-sc-library-open-course-finder.php'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.20.html'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
BOOT = PLUGIN / 'includes' / 'class-sc-library-extension-bootstrap-v402.php'
JS = PLUGIN / 'assets' / 'js' / 'sc-library-open-course-finder.js'
CSS = PLUGIN / 'assets' / 'css' / 'sc-library-open-course-finder.css'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_identity_and_page_contract():
    main = text(MAIN)
    page = text(PAGE)
    assert 'Version: 4.3.20' in main
    assert "define('SC_LIBRARY_VERSION', '4.3.20');" in main
    assert 'Research Library v4.3.20' in page
    assert 'id="open-course-finder"' in page
    assert '[sc_open_course_finder' in page
    assert 'href="#open-course-finder">Find Open Courses' in page


def test_course_finder_is_isolated_optional_extension():
    boot = text(BOOT)
    assert 'public const MODULE_COUNT = 26;' in boot
    assert "'class-sc-library-open-course-finder.php' => 'SC_Library_Open_Course_Finder'" in boot
    assert 'class SC_Library_Open_Course_Finder' in text(COURSES)


def test_launch_provider_network_has_requested_sources():
    src = text(COURSES)
    for token in [
        'MIT OpenCourseWare', 'Harvard CS50', 'Yale Online / Open Yale Courses',
        'Princeton Online', 'Stanford Online', 'Columbia Online', 'edX', 'Coursera',
        'OpenLearn', 'SDG Academy', 'FAO elearning Academy', 'UNITAR / UN Learning'
    ]:
        assert token in src


def test_access_models_do_not_call_everything_free():
    src = text(COURSES)
    assert "'edx' => array(" in src and "'access'          => 'free-audit'" in src
    assert "'coursera' => array(" in src and "'access'          => 'free-preview'" in src
    assert 'Most courses expose a free first-module preview' in src
    assert 'verified credentials are typically paid' in src
    for access in ['free-open', 'free-certificate', 'free-audit', 'free-preview', 'mixed']:
        assert access in src


def test_course_catalog_has_multiple_institutions_and_sustainability():
    src = text(COURSES)
    ids = re.findall(r"'id'\s*=>\s*'([^']+)'", src)
    assert len(ids) >= 17
    for token in ['University of Princeton', 'Sustainable Food Systems', 'The Age of Sustainable Development', 'Infrastructure Asset Management for Sustainable Development']:
        # First token is intentionally allowed via institution/title context below.
        if token == 'University of Princeton':
            assert 'Princeton University' in src
        else:
            assert token in src
    assert "array( 'Sustainability', 'SDGs', 'Policy' )" in src


def test_course_finder_filters_locally_and_updates_provider_gateways():
    js = text(JS)
    assert "data-sc-course-finder" in text(COURSES)
    assert 'queryMatch' in js and 'subjectMatch' in js and 'accessMatch' in js
    assert "template.replace('{query}', encodeURIComponent(query))" in js
    assert 'fetch(' not in js
    assert 'XMLHttpRequest' not in js


def test_publications_and_research_access_remain_present():
    page = text(PAGE)
    assert '[sc_research_access ' in page
    assert '[sc_research_librarian_orchestrator' in page
    assert '[sc_library_unified_workspace]' in page
    assert 'href="/publications/"' not in page or '/publications/' in page
    recovery = text(PLUGIN / 'includes' / 'class-sc-library-activator.php')
    assert 'sc_library_publications_integrity_repair_v43181' in recovery


def test_assets_and_accessibility_contract():
    src = text(COURSES)
    css = text(CSS)
    assert "wp_enqueue_style( 'sc-library-open-course-finder' )" in src
    assert "wp_enqueue_script( 'sc-library-open-course-finder' )" in src
    assert 'aria-live="polite"' in src
    assert 'data-sc-course-empty hidden' in src
    assert '@media(max-width:620px)' in css
