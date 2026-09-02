from pathlib import Path
import json,re,subprocess

ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.6.0-R3.html'
BASELINE=ROOT/'tests/fixtures/research-library-v5.4-source-of-truth.html'
MANIFEST=ROOT/'LIBRARY_CAPABILITY_MANIFEST_v5.6.0-R3.json'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
HUB=PLUGIN/'includes/class-sc-library-capability-hub.php'
NETWORK=PLUGIN/'includes/class-sc-library-research-network-console.php'
NETWORK_JS=PLUGIN/'assets/js/sc-library-research-network-console-v560r3.js'
NETWORK_CSS=PLUGIN/'assets/css/sc-library-research-network-console-v560r3.css'
PAGE_CSS=PLUGIN/'assets/css/sc-library-public-interface-v560r3.css'
HUB_JS=PLUGIN/'assets/js/sc-library-capability-hub-v560r3.js'
HUB_CSS=PLUGIN/'assets/css/sc-library-capability-hub-v560r3.css'
INSTITUTIONS=PLUGIN/'includes/class-sc-library-institutional-connector-expansion.php'
PUBLIC_LIBS=PLUGIN/'includes/class-sc-library-public-library-network.php'
RESEARCH_ACCESS=PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php'
PY_BRIDGE=PLUGIN/'includes/class-sc-library-python-backend.php'
EXPLORER=PLUGIN/'includes/class-sc-library-dynamic-explorer.php'

def text(p): return p.read_text(encoding='utf-8')
def uniq(xs): return list(dict.fromkeys(xs))

def test_r3_release_identity_and_backend_contract_stays_11():
    main=text(MAIN)
    assert 'Version: 5.6.1.1' in main
    assert "SC_LIBRARY_VERSION', '5.6.1.1'" in main
    assert "public const VERSION = '5.6.0.32'" in text(PY_BRIDGE)
    assert "public const VERSION = '5.6.0.32'" in text(EXPLORER)
    assert '__version__ = "1.1.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_original_37_shortcodes_and_72_anchors_remain_the_preservation_baseline():
    baseline=text(BASELINE); manifest=json.loads(text(MANIFEST))
    shortcodes=uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]',baseline))
    anchors=uniq(re.findall(r'\bid=["\']([^"\']+)["\']',baseline))
    assert len(shortcodes)==37 and len(anchors)==72
    assert manifest['protected_shortcodes']==shortcodes
    assert manifest['protected_anchors']==anchors
    combined=text(PAGE)+'\n'+text(HUB)
    for shortcode in shortcodes:
        assert '['+shortcode in combined, f'missing protected shortcode: {shortcode}'

def test_tier_one_capabilities_are_direct_page_sections_not_only_hub_cards():
    page=text(PAGE)
    direct={
        'research-access':'[sc_research_access providers=',
        'institutional-research-network':'[sc_institutional_connector_network',
        'public-library-network':'[sc_public_library_network',
        'access-intelligence-ii':'[sc_access_intelligence_ii',
        'research-front-door':'[sc_research_librarian_orchestrator title="Research Librarian"',
        'knowledge-explorer':'[sc_library mode="explorer"',
        'explore-knowledge':'Knowledge Pathways',
    }
    for anchor,needle in direct.items():
        assert f'id="{anchor}"' in page
        assert needle in page
    assert 'Find a Library Near You' in text(PUBLIC_LIBS)

def test_full_capability_directory_is_expanded_not_single_tab_buried():
    page=text(PAGE); hub=text(HUB); css=text(HUB_CSS); js=text(HUB_JS)
    assert 'display="expanded"' in page
    assert 'exclude_capabilities="research-access,institutional-network,public-library-network,access-intelligence,research-librarian"' in page
    assert "'display' => 'tabbed'" in hub and "'exclude_capabilities' => ''" in hub
    assert 'sc-library-capability-hub--expanded' in css
    assert "var expanded=hub.dataset.display==='expanded'" in js
    for label in ['My Library','Saved Research & Queue','Research Projects & Source Bundles','Evidence Matrix','Knowledge Graph & Evidence','Collaborative Research Rooms','Institutional & Team Libraries','Global Research Federation','Citation Studio','Research Document Builder','Research Workspace','Open Course Finder','Open Learning II','Research Infrastructure']:
        assert label in hub

def test_research_network_console_is_real_registry_driven_and_searchable():
    src=text(NETWORK); js=text(NETWORK_JS); css=text(NETWORK_CSS); page=text(PAGE)
    assert '[sc_research_network_console title="Research Network Console"]' in page
    assert 'SC_Library_Institutional_Connector_Expansion::registry()' in src
    assert 'SC_Library_Public_Library_Network::registry()' in src
    assert 'data-sc-network-query' in src and 'data-sc-network-filter' in src
    assert 'integratedSearch' in js and "#research-access [data-sc-research-access]" in js
    assert 'setInterval(next,2600)' in js
    assert 'prefers-reduced-motion' in js and 'prefers-reduced-motion' in css

def test_university_college_dublin_is_first_class_direct_research_source():
    network=text(NETWORK); institutions=text(INSTITUTIONS); access=text(RESEARCH_ACCESS); page=text(PAGE)
    assert "['id'=>'ucd','name'=>'University College Dublin'" in network
    assert "'ucd' => array('name'=>'University College Dublin'" in institutions
    assert "'type'=>'direct'" in institutions.split("'ucd' =>",1)[1].split('\n',1)[0]
    assert "'ucd' => array(" in access
    assert 'Research Repository UCD' in access
    assert 'University College Dublin' in page
    assert '#institution-ucd' in page

def test_major_universities_from_original_library_are_visible_and_searchable():
    combined=text(INSTITUTIONS)+'\n'+text(PAGE)+'\n'+text(NETWORK)
    required=['MIT Libraries','Harvard Library','Yale University Library','Princeton University Library','Stanford University Libraries','Columbia University Libraries','UC Berkeley / eScholarship','University College Dublin','University of Copenhagen','Stockholm University','University of Oxford','University of Cambridge']
    for name in required:
        assert name in combined, name
    page=text(PAGE)
    for anchor in ['institution-mit','institution-harvard','institution-yale','institution-princeton','institution-stanford','institution-columbia','institution-berkeley','institution-ucd','institution-copenhagen','institution-stockholm','institution-oxford','institution-cambridge']:
        assert '#'+anchor in page

def test_public_library_network_and_nearby_discovery_are_visible():
    libs=text(PUBLIC_LIBS); page=text(PAGE); network=text(NETWORK)
    required=['New York Public Library','Chicago Public Library','St. Louis Public Library','Boston Public Library','Los Angeles Public Library','San Francisco Public Library','Seattle Public Library','Free Library of Philadelphia','Toronto Public Library','Library of Congress','WorldCat']
    for name in required:
        assert name in libs
    assert 'Find a Library Near You' in libs
    assert 'https://search.worldcat.org/libraries' in libs
    assert '#public-library-nypl' in page
    assert '#public-library-worldcat' in page
    assert 'public-library' in network

def test_page_preserves_access_librarian_knowledge_and_pathways_as_primary_story():
    page=text(PAGE)
    order=['id="primary-research-doors"','id="research-network"','id="research-access"','id="institutional-research-network"','id="public-library-network"','id="access-intelligence-ii"','id="research-front-door"','id="knowledge-explorer"','id="library-capability-map"','id="explore-knowledge"']
    positions=[page.index(x) for x in order]
    assert positions==sorted(positions)
    assert page.count('University College Dublin') >= 2
    assert page.count('Research Librarian') >= 5

def test_r3_css_replaces_beige_density_with_sleek_dynamic_layout():
    css=text(PAGE_CSS)
    for needle in ['--r3-soft:#f4f6f4','cc-rl-source-jump','grid-template-columns:repeat(2,minmax(0,1fr))','sc-inst-network__list','sc-public-library-network__list','scroll-margin-top:72px']:
        assert needle in css
    assert '@media(max-width:760px)' in css
    assert '@media(prefers-reduced-motion:reduce)' in css

def test_direct_page_ids_are_unique_and_direct_capabilities_are_excluded_from_hub_emission():
    page=text(PAGE)
    ids=re.findall(r'\bid=["\']([^"\']+)["\']',page)
    assert len(ids)==len(set(ids)), 'duplicate id in R3 page source'
    exclude=re.search(r'exclude_capabilities="([^"]+)"',page).group(1).split(',')
    assert set(exclude)=={'research-access','institutional-network','public-library-network','access-intelligence','research-librarian'}
    hub=text(HUB)
    assert '$excluded_caps' in hub and 'unset($registry[$cap_key])' in hub

def test_changed_php_and_javascript_parse():
    for p in [MAIN,HUB,NETWORK,INSTITUTIONS,PUBLIC_LIBS,RESEARCH_ACCESS,PY_BRIDGE,EXPLORER]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    for p in [NETWORK_JS,HUB_JS,PLUGIN/'assets/js/sc-library-dynamic-explorer-v560.js']:
        r=subprocess.run(['node','--check',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
