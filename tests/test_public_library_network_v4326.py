from pathlib import Path
import json, re, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
PUBLIC=(PLUGIN/'includes/class-sc-library-public-library-network.php').read_text()
SCHOLAR=(PLUGIN/'includes/class-sc-library-scholarly-library-connectors.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.26.html').read_text()
FIELD=(PLUGIN/'templates/field-spotlights.php').read_text()
JS=(PLUGIN/'assets/js/sc-library-public-library-network.js').read_text()

def test_release_identity_and_module_registration():
    assert 'Version: 4.3.26' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.26');" in MAIN
    assert 'class-sc-library-public-library-network.php' in BOOT
    assert 'SC_Library_Public_Library_Network' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==31

def test_public_network_has_expected_breadth_and_types():
    ids=['cpl','slpl','nypl','bpl','lapl','sfpl','spl','flp','tpl','loc','worldcat']
    for ident in ids: assert f"'{ident}' => array(" in PUBLIC
    assert len(ids)==11
    for label in ['Public Library System','Public + Research Library','National Library','Global Holdings Network']:
        assert label in PUBLIC

def test_search_card_digital_and_access_routes_are_explicit():
    for marker in ['search_template','digital_url','card_url','ill_url','access','services','resolve_search_url','rawurlencode']:
        assert marker in PUBLIC
    assert 'WorldCat identifies holdings' in PUBLIC
    assert 'not proof that a user can borrow or open the item' in PUBLIC

def test_membership_persists_into_existing_my_libraries_contract():
    assert "public const USER_META = 'sc_library_my_libraries_v4319';" in PUBLIC
    assert 'sc_library_v4326_connect_public_library' in PUBLIC
    assert "array( 'id' => $id, 'relation' => $relation )" in PUBLIC
    assert "array( 'member', 'research' )" in PUBLIC
    for ident in ['bpl','lapl','sfpl','spl','flp','tpl']:
        assert f"'{ident}' => array(" in SCHOLAR
    assert 'USER_META_MY_LIBRARIES' in SCHOLAR
    assert 'user_library_actions' in SCHOLAR

def test_no_external_password_storage_and_public_discovery_boundary():
    assert 'never stores external-library passwords' in PUBLIC.lower()
    assert 'Public catalog discovery remains open.' in PUBLIC
    assert 'Sign in only if you want to persist library memberships' in PUBLIC
    assert 'password' not in JS.lower()

def test_public_read_only_registry_endpoint_exists():
    assert "'/public-library-network'" in PUBLIC
    assert "'permission_callback' => '__return_true'" in PUBLIC
    assert "'schema' => self::SCHEMA" in PUBLIC
    assert "'libraries' => $out" in PUBLIC

def test_research_library_page_promotes_public_network_inside_research_access():
    assert 'Research Library v4.3.26' in PAGE
    assert 'cc-rl-v4326' in PAGE
    assert 'id="public-library-network"' in PAGE
    assert '[sc_public_library_network title="Public Library Network & Local Access"]' in PAGE
    assert PAGE.index('[sc_institutional_connector_network') < PAGE.index('[sc_public_library_network') < PAGE.index('id="open-course-finder"')
    assert '<li><a href="#public-library-network">Public Library Network</a></li>' in PAGE

def test_access_intelligence_document_builder_institutional_network_and_publications_preserved():
    assert (PLUGIN/'includes/class-sc-library-research-librarian-access-intelligence.php').exists()
    assert (PLUGIN/'includes/class-sc-library-research-document-builder.php').exists()
    assert (PLUGIN/'includes/class-sc-library-institutional-connector-expansion.php').exists()
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
    assert '[sc_research_document_builder limit="100" style="harvard"]' in PAGE

def test_client_connect_action_is_account_scoped_and_non_destructive():
    assert "body.set('action','sc_library_v4326_connect_public_library')" in JS
    assert "credentials:'same-origin'" in JS
    assert 'data-sc-connect-public-library' in JS
    assert 'fetch(' in JS

def test_php_fixture_resolves_query_aware_public_library_routes():
    r=subprocess.run(['php',str(ROOT/'tests/test_public_library_network_fixture_v4326.php')],check=True,capture_output=True,text=True)
    data=json.loads(r.stdout)
    assert data['schema']=='sc-library-public-library-network/1.0'
    assert data['version']=='4.3.26'
    assert data['count']==11
    assert 'climate%20justice' in data['cpl']
    assert 'energy%20systems' in data['worldcat']
    assert data['types']['public-system']==7
    assert data['types']['research-public']==2
    assert data['types']['national']==1
    assert data['types']['global-holdings']==1

def test_architecture_document_states_membership_and_entitlement_boundary():
    doc=(ROOT/'PUBLIC_LIBRARY_NETWORK_LOCAL_ACCESS_v4.3.26.md').read_text()
    assert 'No external-library credentials are stored.' in doc
    assert 'Availability is not entitlement.' in doc
    assert 'My Libraries' in doc
    assert 'WorldCat' in doc
