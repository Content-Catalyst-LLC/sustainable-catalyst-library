from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MOD=PLUGIN/'includes/class-sc-library-research-identity-authority-network.php'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
BOOT=PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php'
HARD=PLUGIN/'includes/class-sc-library-hardening.php'
ROUTE=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.2.0.html'
README=PLUGIN/'readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v520():
    s=text(MAIN); assert 'Version: 5.2.0' in s and "SC_LIBRARY_VERSION', '5.2.0'" in s
    assert "public const VERSION = '5.2.0'" in text(MOD) and "public const VERSION = '5.2.0'" in text(ROUTE)

def test_module_reuses_canonical_public_and_authority_layers():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::normalize_public_object' in s
    assert 'SC_Library_Citation_Source_Manager::get_source_data' in s
    assert 'SC_Library_Topics_Concepts_Relationships::META_ENTITY_URI' in s
    assert 'SC_Library_Global_Research_Federation::published_manifest_ids' in s

def test_supported_persistent_identifier_schemes_are_explicit():
    s=text(MOD)
    for scheme in ["'doi'","'orcid'","'ror'","'isbn'","'issn'","'wikidata'","'pmid'"]: assert scheme in s
    assert 'scheme_registry' in s and 'normalize_identifier' in s and 'valid_identifier' in s

def test_contract_preserves_ambiguity_and_forbids_identity_inference():
    s=text(MOD)
    for needle in ["'creates_parallel_identity_store' => false","'automatic_entity_merge' => false","'automatic_record_merge' => false","'automatic_identifier_assignment' => false","'automatic_authorship_assertion' => false","'automatic_affiliation_assertion' => false","'automatic_truth_scoring' => false","'access_entitlement_inferred' => false","'identifier_match_is_not_identity_proof' => true","'ambiguity_preserved' => true"]: assert needle in s

def test_resolution_is_local_and_published_federation_only():
    s=text(MOD)
    assert "'remote_network_calls_during_resolution' => false" in s
    assert "'external_registry_verification_performed' => false" in s
    assert "'published' !== (string) ( $state['status'] ?? '' )" in s
    assert 'MAX_FEDERATION_MANIFESTS = 120' in s and 'MAX_FEDERATION_RECORDS = 240' in s

def test_identifier_provenance_is_preserved_without_raw_meta_exposure():
    s=text(MOD)
    assert "'provenance' => self::clean" in s or "'provenance' => array(" in s
    assert "'raw_post_meta_exposed' => false" in s
    assert 'approved-meta:' in s and 'federation-manifest:' in s and 'citation-source:' in s

def test_public_object_payload_is_extended_through_filter_not_rewritten_store():
    s=text(MOD)
    assert "add_filter( 'sc_library_api_public_object_payload'" in s
    assert "$payload['persistent_identifiers']" in s and "$payload['identity_url']" in s
    assert 'register_post_type' not in s and 'USER_META' not in s

def test_rest_surface_is_public_get_only():
    s=text(MOD)
    assert "REST_ROUTE = '/research-identity'" in s
    assert s.count("'methods' => WP_REST_Server::READABLE") >= 5
    assert s.count("'permission_callback' => '__return_true'") >= 5
    assert 'CREATABLE' not in s and 'EDITABLE' not in s and 'DELETABLE' not in s

def test_cors_reuses_explicit_v49_origins_and_disables_credentials():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::allowed_origins()' in s
    assert "'Access-Control-Allow-Credentials', 'false'" in s and "'Access-Control-Allow-Methods', 'GET'" in s

def test_extension_bootstrap_adds_one_identity_module():
    s=text(BOOT)
    assert 'public const MODULE_COUNT = 53;' in s
    assert "'class-sc-library-research-identity-authority-network.php' => 'SC_Library_Research_Identity_Authority_Network'" in s

def test_production_gate_certifies_module_assets_and_cache_boundary():
    s=text(HARD)
    assert "BRANCH_VERSION = '5.2.0'" in s and "BRANCH_SCHEMA = 'sc-library-v520-identity-authority-certification/1.0'" in s
    assert "'SC_Library_Research_Identity_Authority_Network'" in s
    assert "'/sc-library/v1/research-identity'" in s
    assert 'sc-library-research-identity-v520.js' in s and 'sc-library-research-identity-v520.css' in s
    assert 'v520-identity-authority-contract' in s and 'v520-identity-cache-boundary' in s
    assert "'research_identity_cacheable'" in s

def test_private_route_cache_samples_remain_excluded():
    s=text(HARD)
    for route in ['/personal-library','/research-projects','/reading-notebooks','/evidence-matrices','/research-rooms','/team-libraries','/research-federation/catalog']: assert route in s
    assert "'private_research_routes_cacheable' => $private_cacheable" in s

def test_accessible_responsive_frontend_and_timeout():
    js=text(PLUGIN/'assets/js/sc-library-research-identity-v520.js'); css=text(PLUGIN/'assets/css/sc-library-research-identity-v520.css'); s=text(MOD)
    assert 'AbortController' in js and '12000' in js and "credentials:'omit'" in js
    assert 'role="search"' in s and 'aria-live="polite"' in s and 'minlength="2"' in s
    assert ':focus-visible' in css and 'min-height:44px' in css and '@media(max-width:760px)' in css

def test_page_places_identity_after_discovery_before_research_access():
    s=text(PAGE)
    assert 'cc-rl-v520' in s and '[sc_research_identity_authority title="Research Identity, Authority &amp; Persistent Identifier Network"]' in s
    assert s.index('id="global-research-discovery"') < s.index('id="research-identity-authority"') < s.index('id="research-access"')
    assert 'href="#research-identity-authority"' in s

def test_identity_and_readme_are_current_without_private_store():
    r=text(ROUTE); rd=text(README)
    assert 'data-sc-library-account-continuity="v5.2.0"' in r
    assert 'Stable tag: 5.2.0' in rd and 'Research Identity, Authority & Persistent Identifier Network' in rd
    assert 'register_post_type' not in text(MOD) and 'USER_META' not in text(MOD)

def test_php_fixture_proves_normalization_validation_and_nonmerge_contract():
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v520_identity.php')],cwd=ROOT,text=True,capture_output=True,check=True)
    assert 'PASS - v5.2.0 identity authority contract fixture' in result.stdout
