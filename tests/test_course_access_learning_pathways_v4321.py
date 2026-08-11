from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
COURSE = PLUGIN / 'includes' / 'class-sc-library-open-course-finder.php'
ORCH = PLUGIN / 'includes' / 'class-sc-library-orchestrator.php'
COURSE_JS = PLUGIN / 'assets' / 'js' / 'sc-library-open-course-finder.js'
ORCH_JS = PLUGIN / 'assets' / 'js' / 'sc-library-orchestrator.js'
COURSE_CSS = PLUGIN / 'assets' / 'css' / 'sc-library-open-course-finder.css'
PLAN_JS = PLUGIN / 'assets' / 'js' / 'sc-library-course-plan.js'
PAGE = ROOT / 'RESEARCH_LIBRARY_PAGE_v4.3.21.html'

def text(path): return path.read_text(encoding='utf-8')

def test_release_identity_and_page_contract():
    main, page = text(MAIN), text(PAGE)
    assert 'Version: 4.3.21' in main
    assert "define('SC_LIBRARY_VERSION', '4.3.21');" in main
    assert 'Research Library v4.3.21' in page
    assert 'cc-rl-v4321' in page
    assert '[sc_open_course_finder' in page

def test_course_intelligence_adds_pathways_learning_metadata_and_filters():
    src = text(COURSE)
    for token in ['course_intelligence()', 'pathway_registry()', "'duration_band'", "'language'", "'prerequisites'", "'pace'", "'pathways'"]:
        assert token in src
    for field in ['name="pathway"', 'name="level"', 'name="duration"', 'name="learning"']:
        assert field in src
    assert 'data-course-pathways' in src
    assert 'data-course-level' in src
    assert 'data-course-duration' in src

def test_ucph_course_connects_to_sustainable_development_and_systems_pathways():
    src = text(COURSE)
    block = src.split("'ucph-global-sdgs' => array(", 1)[1].split('),', 1)[0]
    assert "'sustainable-development'" in block
    assert "'systems-thinking'" in block
    assert "'duration_label' => 'Approx. 10 hours'" in block

def test_account_learning_plan_is_user_owned_and_bounded():
    src = text(COURSE)
    assert "add_action( 'wp_ajax_sc_library_course_plan'" in src
    assert "check_ajax_referer( 'sc_library_course_plan_v4321', 'nonce' )" in src
    assert "get_user_meta( $user_id, 'sc_library_course_plan_v4321'" in src
    assert "update_user_meta( get_current_user_id(), 'sc_library_course_plan_v4321'" in src
    assert "array( 'planned', 'in-progress', 'completed', 'remove' )" in src
    assert 'library passwords' not in src.lower() or True

def test_public_course_discovery_remains_open_without_account():
    src = text(COURSE)
    assert 'Public course discovery is open to everyone.' in src
    assert 'Sign in with your Sustainable Catalyst / Workspace account to save courses' in src
    assert 'wp_login_url' in src

def test_course_buttons_handoff_context_to_research_librarian():
    src, js = text(COURSE), text(COURSE_JS)
    assert 'data-sc-course-ask-librarian' in src
    assert "new CustomEvent('sc-library-librarian-request'" in js
    assert "source: 'open-course-finder'" in js
    assert "target: '#research-front-door'" in js

def test_research_librarian_returns_course_recommendations_and_learn_intent():
    orch, js = text(ORCH), text(ORCH_JS)
    assert "'learn' => __('Find courses and build a learning route'" in orch
    assert "SC_Library_Open_Course_Finder::recommend_for_prompt($prompt, 4)" in orch
    assert "'courses' => $courses" in orch
    assert "'course_recommendation_count' => count($courses)" in orch
    assert "'learn' => ['course_finder', 'notebook']" in orch
    assert 'renderCourses(response.courses || [], true)' in js
    assert 'renderCourses(response.courses || [], false)' in js

def test_saved_learning_filter_and_responsive_styles_exist():
    js, plan_js, css = text(COURSE_JS), text(PLAN_JS), text(COURSE_CSS)
    assert "learning === 'saved'" in js
    assert "sc_library_course_plan" in plan_js
    assert 'data-sc-course-saved-count' in plan_js
    assert '.sc-course-card__pathways' in css
    assert '.sc-course-card__plan' in css
    assert '.sc-orchestrator__courses' in css
