from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MOD=PLUGIN/'includes/class-sc-library-public-evidence-claim-navigation.php'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
BOOT=PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php'
HARD=PLUGIN/'includes/class-sc-library-hardening.php'
ROUTE=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
UNIFIED=PLUGIN/'includes/class-sc-library-unified-personal-research-environment.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.3.0.html'
README=PLUGIN/'readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v530():
    s=text(MAIN); assert 'Version: 5.3.0' in s and "SC_LIBRARY_VERSION', '5.3.0'" in s
    assert "public const VERSION = '5.3.0'" in text(MOD) and "public const VERSION = '5.3.0'" in text(ROUTE)

def test_module_reuses_canonical_evidence_publication_and_source_authorities():
    s=text(MOD)
    assert 'SC_Library_Evidence_Claim_Linking::claim_packet' in s
    assert 'SC_Library_Evidence_Claim_Linking::get_evidence_data' in s
    assert 'SC_Library_Publications_Research_Graph::build_graph' in s
    assert 'SC_Library_Citation_Source_Manager::get_source_data' in s

def test_contract_is_public_only_and_forbids_parallel_stores_or_mutation():
    s=text(MOD)
    for needle in ["'creates_parallel_claim_store' => false","'creates_parallel_evidence_store' => false","'public_claims_only' => true","'public_evidence_notes_only' => true","'private_evidence_matrix_bodies_excluded' => true","'private_notebook_bodies_excluded' => true","'private_review_notes_excluded' => true","'truth_scoring' => false","'automatic_evidence_promotion' => false","'automatic_claim_status_change' => false","'automatic_confidence_change' => false"]: assert needle in s
    assert 'register_post_type' not in s and 'USER_META' not in s

def test_canonical_relation_vocabulary_is_preserved_without_inference():
    s=text(MOD)
    for relation in ["'supports'","'qualifies'","'contradicts'","'contextualizes'","'illustrates'","'unresolved'"]: assert relation in s
    assert 'relation_registry' in s and 'relation_totals' in s
    assert "'relation_semantics_preserved' => true" in s

def test_evidence_output_is_minimized_and_does_not_expose_private_fields():
    s=text(MOD)
    assert "'full_evidence_body_exposed' => false" in s and "'private_analysis_exposed' => false" in s and "'private_relation_note_exposed' => false" in s
    assert "$data['analysis']" not in s and "$data['context_before']" not in s and "$data['context_after']" not in s
    assert "$item['link']['note']" not in s

def test_claim_navigation_uses_public_visibility_and_does_not_mutate_claim_state():
    s=text(MOD)
    assert 'SC_Library_Evidence_Claim_Linking::claim_is_public' in s
    assert 'SC_Library_Evidence_Claim_Linking::evidence_is_public' in s
    assert "'status_and_confidence_are_not_truth_scores' => true" in s
    for forbidden in ['update_post_meta(', 'wp_update_post(', 'wp_insert_post(']: assert forbidden not in s

def test_publication_context_uses_only_explicit_publication_research_graph_links():
    s=text(MOD)
    assert 'SC_Library_Publications_Research_Graph::build_graph' in s
    assert "'explicit_publication_links_only' => true" in s
    assert "'provenance' => 'explicit-publication-research-graph'" in s

def test_source_context_uses_canonical_public_evidence_source_links():
    s=text(MOD)
    assert 'SC_Library_Evidence_Claim_Linking::evidence_ids_for_source' in s
    assert 'MAX_SOURCE_EVIDENCE = 120' in s
    assert "'public_only' => true" in s

def test_public_api_payload_adds_bounded_evidence_handoffs_without_new_object_type():
    s=text(MOD)
    assert "add_filter( 'sc_library_api_public_object_payload'" in s
    assert "$payload['public_evidence']" in s and "'publication' === $type" in s and "'research-source' === $type" in s
    assert 'sc_library_api_public_object_profiles' not in s

def test_rest_surface_is_public_get_only_and_bounded():
    s=text(MOD)
    assert "REST_ROUTE = '/public-evidence'" in s
    assert s.count("'methods' => WP_REST_Server::READABLE") >= 3
    assert s.count("'permission_callback' => '__return_true'") >= 3
    assert 'CREATABLE' not in s and 'EDITABLE' not in s and 'DELETABLE' not in s
    assert 'MAX_EVIDENCE_PER_CLAIM = 80' in s and 'MAX_CLAIMS_PER_CONTEXT = 40' in s

def test_cors_reuses_explicit_v49_origins_and_disables_credentials():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::allowed_origins()' in s
    assert "'Access-Control-Allow-Credentials', 'false'" in s and "'Access-Control-Allow-Methods', 'GET'" in s

def test_extension_bootstrap_adds_one_public_evidence_module():
    s=text(BOOT)
    assert 'public const MODULE_COUNT = 54;' in s
    assert "'class-sc-library-public-evidence-claim-navigation.php' => 'SC_Library_Public_Evidence_Claim_Navigation'" in s

def test_production_gate_certifies_module_assets_contract_and_cache_boundary():
    s=text(HARD)
    assert "BRANCH_VERSION = '5.3.0'" in s and "BRANCH_SCHEMA = 'sc-library-v530-public-evidence-certification/1.0'" in s
    assert "'SC_Library_Public_Evidence_Claim_Navigation'" in s and "'/sc-library/v1/public-evidence'" in s
    assert 'sc-library-public-evidence-v530.js' in s and 'sc-library-public-evidence-v530.css' in s
    assert 'v530-public-evidence-contract' in s and 'v530-public-evidence-cache-boundary' in s and "'public_evidence_cacheable'" in s

def test_accessible_responsive_frontend_and_bounded_timeout():
    js=text(PLUGIN/'assets/js/sc-library-public-evidence-v530.js'); css=text(PLUGIN/'assets/css/sc-library-public-evidence-v530.css'); s=text(MOD)
    assert 'AbortController' in js and '12000' in js and "credentials:'omit'" in js
    assert 'role="search"' in s and 'aria-live="polite"' in s and 'inputmode="numeric"' in s
    assert ':focus-visible' in css and 'min-height:44px' in css and '@media(max-width:760px)' in css

def test_page_and_unified_home_surface_public_evidence_without_private_migration():
    page=text(PAGE); unified=text(UNIFIED)
    assert 'cc-rl-v530' in page and '[sc_public_evidence_claim_navigation title="Public Evidence &amp; Claim Navigation"]' in page
    assert page.index('id="research-identity-authority"') < page.index('id="public-evidence-claims"') < page.index('id="research-access"')
    assert 'href="#public-evidence-claims"' in page and 'href="#public-evidence-claims"' in unified

def test_readme_docs_fixture_and_identity_state_truthful_public_boundary():
    rd=text(README); route=text(ROUTE)
    assert 'Stable tag: 5.3.0' in rd and 'Public Evidence & Claim Navigation' in rd
    assert 'data-sc-library-account-continuity="v5.3.0"' in route
    assert (ROOT/'PUBLIC_EVIDENCE_CLAIM_NAVIGATION_v5.3.0.md').exists() and (ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.3.0.md').exists()
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v530_public_evidence.php')],cwd=ROOT,text=True,capture_output=True,check=True)
    assert 'PASS - v5.3.0 public evidence contract fixture' in result.stdout
