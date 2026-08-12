from pathlib import Path
import re
import json
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
INST=(PLUGIN/'includes/class-sc-library-institutional-connector-expansion.php').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.25.html').read_text()
FIELD=(PLUGIN/'templates/field-spotlights.php').read_text()

def test_release_identity_and_module_registration():
    assert 'Version: 4.3.25' in MAIN
    assert "define('SC_LIBRARY_VERSION', '4.3.25');" in MAIN
    assert 'class-sc-library-institutional-connector-expansion.php' in BOOT
    assert 'SC_Library_Institutional_Connector_Expansion' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==30

def test_capability_labels_are_truthful_and_explicit():
    for key in ["'direct'", "'open-repository'", "'standards'", "'gateway'"]:
        assert key in INST
    for label in ['Direct Connector','Open Repository','Standards-Capable','Research Gateway']:
        assert label in INST
    assert 'A gateway is never presented as a direct connector' in INST
    assert 'public discovery is never presented as proof of licensed access' in INST

def test_network_has_expected_institutional_breadth():
    ids=['mit','harvard','berkeley','ucd','stanford','yale','princeton','columbia','copenhagen','stockholm','wageningen','lund','eth','oxford','cambridge','iiasa','sei','unu']
    for ident in ids: assert f"'{ident}' => array(" in INST
    assert len(ids)==18

def test_query_routes_and_protocol_metadata_exist():
    for marker in ['resolve_search_url','{query}','rawurlencode','protocols','repository','access','search_template']:
        assert marker in INST
    for protocol in ['TIMDEX','LibraryCloud','DSpace','SearchWorks','Quicksearch','DataSpace','CLIO','DiVA','ORA','Apollo repository']:
        assert protocol in INST

def test_public_read_only_registry_endpoint_exists():
    assert "'/institutional-connectors'" in INST
    assert "'permission_callback' => '__return_true'" in INST
    assert "'schema'=>self::SCHEMA" in INST
    assert "'institutions'=>$out" in INST

def test_research_library_page_embeds_network_in_research_access():
    assert 'Research Library v4.3.25' in PAGE
    assert 'cc-rl-v4325' in PAGE
    assert 'id="institutional-research-network"' in PAGE
    assert '[sc_institutional_connector_network title="Institutional Research Network"]' in PAGE
    assert PAGE.index('[sc_research_access') < PAGE.index('[sc_institutional_connector_network') < PAGE.index('id="open-course-finder"')

def test_existing_access_intelligence_document_builder_and_publications_are_preserved():
    assert (PLUGIN/'includes/class-sc-library-research-librarian-access-intelligence.php').exists()
    assert (PLUGIN/'includes/class-sc-library-research-document-builder.php').exists()
    assert 'data-sc-field-stack="v4.3.22.4"' in FIELD
    assert 'data-sc-field-stack-mode="all-fields"' in FIELD
    assert '[sc_research_document_builder limit="100" style="harvard"]' in PAGE

def test_no_external_library_password_storage_contract():
    assert 'licensed resources' in INST
    assert 'entitlement' in INST
    assert 'password' not in INST.lower() or 'passwords' not in INST.lower()
    doc=(ROOT/'INSTITUTIONAL_CONNECTOR_EXPANSION_v4.3.25.md').read_text()
    assert 'No external-library credentials are stored.' in doc


def test_php_fixture_resolves_query_aware_institution_routes():
    r=subprocess.run(["php",str(ROOT/"tests/test_institutional_connector_fixture_v4325.php")],check=True,capture_output=True,text=True)
    data=json.loads(r.stdout)
    assert data["schema"]=="sc-library-institutional-connector-network/1.0"
    assert data["version"]=="4.3.25"
    assert data["count"]==18
    assert "climate%20justice" in data["stanford"]
    assert "energy%20systems" in data["ucd"]
    assert data["types"]["direct"]==4
    assert data["types"]["standards"]==4
