from pathlib import Path
import json,re,subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.6.0-R3.2.html'
BASELINE=ROOT/'tests/fixtures/research-library-v5.4-source-of-truth.html'
MANIFEST=ROOT/'LIBRARY_CAPABILITY_MANIFEST_v5.6.0-R3.2.json'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
ASSETS=PLUGIN/'includes/class-sc-library-public-interface-assets.php'
HUB=PLUGIN/'includes/class-sc-library-capability-hub.php'
ACCOUNT=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
PAGE_CSS=PLUGIN/'assets/css/sc-library-public-interface-v560r3.css'
ACCOUNT_CSS=PLUGIN/'assets/css/sc-library-account-continuity-v4327.css'
DYNAMIC_CSS=PLUGIN/'assets/css/sc-library-dynamic-explorer-v560.css'
DYNAMIC_JS=PLUGIN/'assets/js/sc-library-dynamic-explorer-v560.js'
EXPLORER_TEMPLATE=PLUGIN/'templates/library-explorer.php'
HUB_CSS=PLUGIN/'assets/css/sc-library-capability-hub-v560r3.css'
def text(p): return p.read_text(encoding='utf-8')
def uniq(xs): return list(dict.fromkeys(xs))
def test_identity_and_backend_unchanged():
    main=text(MAIN)
    assert 'Version: 5.6.0.32' in main
    assert "SC_LIBRARY_VERSION', '5.6.0.32'" in main
    assert '__version__ = "1.1.0"' in text(ROOT/'library-backend/app/__init__.py')
def test_preservation_contract():
    base=text(BASELINE); page=text(PAGE); hub=text(HUB); m=json.loads(text(MANIFEST))
    sc=uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]',base)); ids=uniq(re.findall(r'\bid=["\']([^"\']+)["\']',base))
    assert len(sc)==37 and len(ids)==72
    assert m['protected_shortcodes']==sc and m['protected_anchors']==ids
    combined=page+'\n'+hub
    for s in sc: assert '['+s in combined,s
    for a in ids: assert a in combined,a
def test_critical_assets_enqueue_before_head_on_library_page():
    s=text(ASSETS); main=text(MAIN)
    assert "add_action('wp_enqueue_scripts', [$this,'enqueue'], 40)" in s
    assert "is_page('knowledge-libraries')" in s
    for h in ['sc-library-public-interface-v560r3','sc-library-account-continuity-v4327','sc-library-capability-hub-v560r3','sc-library-open-course-finder']:
        assert h in s
    assert 'class-sc-library-public-interface-assets.php' in main
    assert '$public_interface_assets->register_hooks();' in main
def test_front_doors_are_clean_navigation_surfaces():
    p=text(PAGE); css=text(PAGE_CSS)
    door=p[p.index('id="primary-research-doors"'):p.index('id="research-network"')]
    assert '[sc_research_access' not in door
    assert '[sc_research_librarian_orchestrator' not in door
    assert 'MIT · Harvard · UCD' in door
    assert 'Ask the Research Librarian →' in door
    assert 'grid-template-columns:repeat(3,minmax(0,1fr))!important' in css
    assert 'border-radius:0!important' in css
def test_capability_actions_have_markup_level_fallback():
    h=text(HUB)
    assert 'class="sc-library-capability-open" data-open-capability=' in h
    assert 'id="sc-library-capability-hub-critical"' in h
    assert 'border:1px solid #171717!important' in h
    assert 'visibility:visible!important' in h and 'opacity:1!important' in h
def test_account_continuity_is_utility_strip_not_status_card_wall():
    s=text(ACCOUNT); css=text(ACCOUNT_CSS)
    assert 'Library and Workspace use the same Sustainable Catalyst account.' in s
    assert 'Private research stays private' in s
    assert 'Library credentials stay external' in s
    assert 'Account details' in s
    assert '<dl class="sc-library-account-continuity__status"' not in s
    assert 'border-top:1px solid #d9ddd9!important' in css
    assert 'background:transparent!important' in css
def test_open_courses_replaces_visible_research_flow_but_anchors_survive():
    p=text(PAGE)
    assert 'Learn from Universities and Public Learning Systems' in p
    assert '[sc_open_course_finder mode="featured"' in p
    assert 'Find → Understand → Organize → Produce' not in p
    assert 'id="research-flow"' in p and 'id="research-flow-title"' in p
def test_changed_php_js_parse():
    for p in [MAIN,ASSETS,HUB,ACCOUNT]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
    for p in [PLUGIN/'assets/js/sc-library-capability-hub-v560r3.js',PLUGIN/'assets/js/sc-library-research-network-console-v560r3.js']:
        r=subprocess.run(['node','--check',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr


def test_explore_topic_controls_have_explicit_visible_text_color():
    css=text(DYNAMIC_CSS)
    assert 'topic control visibility under global button CSS' in css
    assert '-webkit-text-fill-color:#151515!important' in css
    assert 'visibility:visible!important' in css
    assert 'button.is-active' in css and '-webkit-text-fill-color:#fff!important' in css

def test_capability_css_has_no_literal_newline_escape_corruption_and_visible_nav_labels():
    css=text(HUB_CSS)
    assert '\\n' not in css
    assert 'rendered button visibility repair' in css
    assert '.sc-library-capability-hub__group-nav button' in css
    assert '-webkit-text-fill-color:#151715!important' in css
    assert '.sc-library-capability-card>.sc-library-capability-open' in css

def test_library_page_early_enqueues_dynamic_explorer_and_connector_css():
    s=text(ASSETS)
    assert "wp_enqueue_style('sc-library-dynamic-explorer-v560'" in s
    assert "wp_enqueue_style('sc-library-connectors'" in s


def test_explore_topic_controls_have_inline_last_resort_fallback():
    js=text(DYNAMIC_JS); tpl=text(EXPLORER_TEMPLATE)
    assert 'data-topic="${esc(topic.topic)}"' in js
    assert 'visibility:visible!important' in js
    assert '-webkit-text-fill-color:#151515!important' in js
    assert 'data-explorer-filter-toggle' in tpl and 'visibility:visible!important' in tpl
    assert 'data-explorer-reset' in tpl and '-webkit-text-fill-color:#151515!important' in tpl

def test_capability_actions_have_inline_last_resort_fallback():
    h=text(HUB)
    needle='data-open-capability="<?php echo esc_attr($key); ?>"'
    assert needle in h
    chunk=h[h.index(needle):h.index(needle)+900]
    assert 'style="display:inline-flex!important' in chunk
    assert 'visibility:visible!important' in chunk
    assert '-webkit-text-fill-color:#171717!important' in chunk

def test_knowledge_pathways_are_structured_and_styled_as_cards():
    p=text(PAGE); css=text(PAGE_CSS)
    section=p[p.index('id="explore-knowledge"'):p.index('id="open-course-finder"')]
    assert 'cc-rl-pathway-index__list' in section
    assert section.count('cc-rl-pathway-index__number') == 8
    assert 'cc-rl-pathway-index__field-list' in section
    assert section.count('/category/') >= 5
    assert 'v5.6.0 R3.2 — Knowledge Pathways' in css
    assert 'list-style:none!important' in css
    assert 'grid-template-columns:44px minmax(0,1fr) 20px!important' in css
    assert '.cc-rl-pathway-index__fields' in css
    assert 'background:#111311!important' in css
