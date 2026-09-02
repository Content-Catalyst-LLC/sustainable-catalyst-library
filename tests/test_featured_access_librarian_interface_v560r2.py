from pathlib import Path
import re, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.6.0-R2.html'
HUB=PLUGIN/'includes/class-sc-library-capability-hub.php'
HUB_JS=PLUGIN/'assets/js/sc-library-capability-hub-v560r2.js'
HUB_CSS=PLUGIN/'assets/css/sc-library-capability-hub-v560r2.css'
CONNECTORS=PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php'
CONNECTOR_CSS=PLUGIN/'assets/css/sc-library-connectors.css'
MAIN=PLUGIN/'sustainable-catalyst-library.php'

def text(p): return p.read_text(encoding='utf-8')

def test_r2_release_identity_and_no_backend_contract_change():
    main=text(MAIN)
    assert 'Version: 5.6.0.32' in main
    assert "SC_LIBRARY_VERSION', '5.6.0.32'" in main
    assert '__version__ = "1.1.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_three_primary_front_doors_are_explicit_and_above_explorer():
    page=text(PAGE)
    for needle in ['Three Research Front Doors','Knowledge Base','Library Access','Research Librarian','featured-library-access','featured-research-librarian']:
        assert needle in page
    assert page.index('primary-research-doors') < page.index('id="knowledge-explorer"')
    assert '[sc_research_access mode="front-door"' in page
    assert '[sc_research_librarian_orchestrator mode="front-door"' in page

def test_library_access_front_door_uses_real_connector_stack_not_placeholder_links():
    src=text(CONNECTORS)
    for needle in ["'mode'              => 'standard'", "'front-door' === $mode", 'data-sc-research-access-form', 'data-sc-research-access-results', 'Open full Library Access', 'My Libraries', 'Access Intelligence']:
        assert needle in src
    for provider in ['internetarchive','mit','harvard','ucd','openalex','europepmc','arxiv']:
        assert provider in src
    css=text(CONNECTOR_CSS)
    assert '.sc-research-access--front-door' in css
    assert 'max-height:360px' in css
    assert 'overflow:auto' in css

def test_capability_map_shows_only_one_group_at_a_time():
    hub=text(HUB); js=text(HUB_JS); css=text(HUB_CSS)
    assert "'default_group' => 'explore'" in hub
    assert 'aria-selected' in hub
    assert 'hidden' in hub
    assert 'is-active-group' in hub
    assert "sec.hidden=!active" in js
    assert '.sc-library-capability-group[hidden]' in css
    assert '[sc_library_capability_hub title="Complete Library Capability Map" default_group="research"]' in text(PAGE)

def test_heavy_capability_workspace_is_bounded_and_scrolls_internally():
    css=text(HUB_CSS); js=text(HUB_JS)
    assert 'height:min(72vh,760px)' in css
    assert 'overflow:hidden' in css
    assert 'height:100%!important' in css
    assert '12000' not in js
    assert 'frame.style.height' not in js
    assert 'iframe scrolls internally' in js

def test_access_and_librarian_are_featured_without_removing_full_capabilities():
    page=text(PAGE); hub=text(HUB)
    assert page.count('Library Access') >= 2
    assert page.count('Research Librarian') >= 3
    assert "'research-access' => self::cap('access'" in hub
    assert "'research-librarian' => self::cap('collaborate'" in hub
    assert "'project-librarian' => self::cap('collaborate'" in hub
    assert 'Global Research Federation' in hub
    assert 'Public Library Network' in hub
    assert 'Institutional & Team Libraries' in hub

def test_r2_changed_php_and_js_parse():
    for p in [MAIN,HUB,CONNECTORS]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    for p in [HUB_JS]:
        r=subprocess.run(['node','--check',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
