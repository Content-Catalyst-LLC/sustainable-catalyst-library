from pathlib import Path
import json,re,subprocess

ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.6.0-R3.1.html'
BASELINE=ROOT/'tests/fixtures/research-library-v5.4-source-of-truth.html'
MANIFEST=ROOT/'LIBRARY_CAPABILITY_MANIFEST_v5.6.0-R3.1.json'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
PAGE_CSS=PLUGIN/'assets/css/sc-library-public-interface-v560r3.css'
HUB=PLUGIN/'includes/class-sc-library-capability-hub.php'
HUB_CSS=PLUGIN/'assets/css/sc-library-capability-hub-v560r3.css'
ACCOUNT=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
ACCOUNT_CSS=PLUGIN/'assets/css/sc-library-account-continuity-v4327.css'
COURSES=PLUGIN/'includes/class-sc-library-open-course-finder.php'
COURSE_CSS=PLUGIN/'assets/css/sc-library-open-course-finder.css'
COURSE_JS=PLUGIN/'assets/js/sc-library-open-course-finder.js'
PY_BRIDGE=PLUGIN/'includes/class-sc-library-python-backend.php'
EXPLORER=PLUGIN/'includes/class-sc-library-dynamic-explorer.php'

def text(p): return p.read_text(encoding='utf-8')
def uniq(xs): return list(dict.fromkeys(xs))

def test_r31_release_identity_backend_unchanged():
    main=text(MAIN)
    assert 'Version: 5.6.1.1' in main
    assert "SC_LIBRARY_VERSION', '5.6.1.1'" in main
    assert "public const VERSION = '5.6.0.32'" in text(PY_BRIDGE)
    assert "public const VERSION = '5.6.0.32'" in text(EXPLORER)
    assert '__version__ = "1.1.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_r31_preserves_original_37_shortcodes_and_72_anchor_contract():
    baseline=text(BASELINE); manifest=json.loads(text(MANIFEST)); page=text(PAGE); hub=text(HUB)
    shortcodes=uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]',baseline))
    anchors=uniq(re.findall(r'\bid=["\']([^"\']+)["\']',baseline))
    assert len(shortcodes)==37 and len(anchors)==72
    assert manifest['protected_shortcodes']==shortcodes
    assert manifest['protected_anchors']==anchors
    combined=page+'\n'+hub
    for shortcode in shortcodes:
        assert '['+shortcode in combined, shortcode
    for anchor in anchors:
        assert anchor in combined, f'missing protected anchor/compatibility target: {anchor}'
    # R3.1 directly preserves the legacy Research Flow deep links while replacing the visible section.
    assert 'id="research-flow"' in page and 'id="research-flow-title"' in page
    assert 'id="open-course-finder"' in page and 'id="open-course-finder-title"' in page

def test_front_door_layout_is_repaired_and_defensively_scoped():
    css=text(PAGE_CSS); page=text(PAGE)
    assert 'grid-template-columns:minmax(220px,.78fr) minmax(390px,1.42fr) minmax(320px,1.08fr)!important' in css
    assert '.cc-rl-primary-door--access .sc-research-access__search{display:grid!important;grid-template-columns:1fr!important' in css
    assert '.cc-rl-primary-door--access .sc-research-access__header h2' in css
    assert 'word-break:normal!important' in css
    assert '@media(max-width:1120px)' in css and '@media(max-width:760px)' in css
    assert 'id="featured-library-access"' in page and 'id="featured-research-librarian"' in page

def test_capability_map_open_actions_are_real_visible_controls():
    css=text(HUB_CSS); page=text(PAGE)
    assert 'button[data-open-capability]' in css
    for needle in ['min-width:126px!important','min-height:38px!important','border:1px solid var(--rch-ink)!important','visibility:visible!important','opacity:1!important']:
        assert needle in css
    assert 'display="expanded"' in page
    assert 'open-courses' in re.search(r'exclude_capabilities="([^"]+)"',page).group(1).split(',')

def test_account_continuity_default_is_compact_and_details_preserve_governance():
    src=text(ACCOUNT); css=text(ACCOUNT_CSS)
    assert 'How account continuity works' in src
    assert '<details class="sc-library-account-continuity__details">' in src
    assert 'Your Sustainable Catalyst account keeps private Library research connected with Workspace' in src
    for label in ['Private research','My Libraries','Workspace','Account-scoped','Passwords stay external','Shared account']:
        assert label in src
    long_phrase='My Sources, My Libraries, course plans, research documents'
    assert long_phrase in src
    assert src.index('How account continuity works') < src.index(long_phrase)
    assert 'grid-template-columns:repeat(3,minmax(0,1fr))' in css
    assert '.sc-library-account-continuity__details' in css
    assert '@media(max-width:640px)' in css

def test_research_flow_is_replaced_by_visible_dynamic_open_courses():
    page=text(PAGE); src=text(COURSES); css=text(COURSE_CSS); js=text(COURSE_JS)
    assert '<p class="cc-rl-section-kicker">Open Courses</p>' in page
    assert 'Learn from Universities and Public Learning Systems' in page
    assert '[sc_open_course_finder mode="featured"' in page
    assert 'Find → Understand → Organize → Produce' not in page
    assert "'mode' => 'standard'" in src and "'featured_limit' => '6'" in src
    for cid in ['mit-6-100l','cs50x','yale-wellbeing','princeton-algorithms-1','stanford-cs101','ucph-global-sdgs']:
        assert cid in src
    assert 'data-course-mode=' in src and 'data-featured-limit=' in src
    assert 'data-sc-course-show-all' in src
    assert 'featuredMode' in js and 'featuredLimit' in js and 'showAllButton' in js
    assert '.sc-course-finder--featured' in css and '.sc-course-finder__show-all' in css

def test_open_courses_visibility_is_promoted_in_manifest():
    manifest=json.loads(text(MANIFEST))
    t1=manifest['visibility_tiers']['tier_1_directly_visible']
    assert 'Open Courses' in t1
    assert 'Open Course Finder' not in manifest['visibility_tiers']['tier_2_visible_capability_directory']
    guards=manifest['r31_guards']
    assert guards['front_door_css_repair'] is True
    assert guards['capability_actions_visible'] is True
    assert guards['account_continuity_compact'] is True
    assert guards['open_courses_replaces_research_flow'] is True
    assert guards['backend_version']=='1.1.0' and guards['database_migration'] is False

def test_changed_php_and_js_parse():
    for p in [MAIN,HUB,ACCOUNT,COURSES,PY_BRIDGE,EXPLORER]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    for p in [COURSE_JS,PLUGIN/'assets/js/sc-library-capability-hub-v560r3.js',PLUGIN/'assets/js/sc-library-research-network-console-v560r3.js',PLUGIN/'assets/js/sc-library-dynamic-explorer-v560.js']:
        r=subprocess.run(['node','--check',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
