from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MOD=PLUGIN/'includes/class-sc-library-research-collections-curated-spaces.php'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
BOOT=PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php'
HARD=PLUGIN/'includes/class-sc-library-hardening.php'
ROUTE=PLUGIN/'includes/class-sc-library-canonical-route-identity.php'
UNIFIED=PLUGIN/'includes/class-sc-library-unified-personal-research-environment.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.4.0.html'
README=PLUGIN/'readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v540():
    s=text(MAIN); assert 'Version: 5.4.0' in s and "SC_LIBRARY_VERSION', '5.4.0'" in s
    assert "public const VERSION = '5.4.0'" in text(MOD) and "public const VERSION = '5.4.0'" in text(ROUTE)

def test_editorial_store_uses_explicit_wordpress_draft_publish_authority():
    s=text(MOD)
    assert "POST_TYPE = 'sc_curated_space'" in s and "'capability_type' => 'page'" in s and "'show_ui' => true" in s
    assert "'publicly_queryable' => false" in s and "'show_in_rest' => false" in s
    assert "add_action( 'save_post_' . self::POST_TYPE" in s

def test_contract_is_references_only_and_private_research_is_excluded():
    s=text(MOD)
    for needle in ["'references_only' => true","'ordered_sections' => true","'underlying_record_ownership_transferred' => false","'underlying_record_publication_state_mutated' => false","'private_projects_exposed' => false","'personal_library_exposed' => false","'notebook_bodies_exposed' => false","'matrix_bodies_exposed' => false","'private_binary_copy' => false","'automatic_record_copy' => false","'automatic_publication' => false"]: assert needle in s

def test_three_curated_space_kinds_are_explicit():
    s=text(MOD)
    for kind in ["'research-collection'","'exhibition'","'knowledge-space'"]: assert kind in s
    assert 'kind_registry' in s

def test_reference_registry_reuses_public_api_evidence_and_federation_authorities():
    s=text(MOD)
    assert 'SC_Library_API_Embeds_Interoperability::object_profiles()' in s
    assert "'public-claim'" in s and "'public-evidence'" in s and "'federation-manifest'" in s
    assert 'SC_Library_Public_Evidence_Claim_Navigation::claim_context' in s and 'SC_Library_Public_Evidence_Claim_Navigation::evidence_context' in s
    assert 'SC_Library_Global_Research_Federation::manifest_state' in s

def test_section_editor_is_bounded_and_validates_public_references_before_save():
    s=text(MOD)
    assert 'MAX_SECTIONS = 24' in s and 'MAX_ITEMS_PER_SECTION = 40' in s
    assert 'sanitize_sections' in s and 'resolve_public_reference' in s
    assert "is_wp_error( self::resolve_public_reference( $type, $id ) )" in s
    assert 'section_urn' in s and 'urn:sc:curated-section:' in s

def test_public_reads_reresolve_references_and_omit_records_that_are_no_longer_public():
    s=text(MOD)
    assert 'space_state' in s and "$r = self::resolve_public_reference" in s
    assert '$omitted++' in s and "'omitted_unavailable_references' => $omitted" in s
    assert "'post_status' => 'publish'" in s

def test_manifest_is_deterministic_and_references_only():
    s=text(MOD)
    assert 'manifest_sha256' in s and "hash( 'sha256'" in s and 'canonical_json' in s
    assert "unset( $payload['generated_at'], $payload['manifest_sha256'] )" in s
    assert "'references_only' => true" in s and "'private_content_included' => false" in s

def test_curated_spaces_join_v49_public_profiles_and_v51_discovery_without_parallel_search_store():
    s=text(MOD)
    assert "add_filter( 'sc_library_api_public_object_profiles'" in s
    assert "$profiles['curated-space']" in s and "'post_type' => self::POST_TYPE" in s
    assert 'register_post_type' in s and 'WP_Query' not in s

def test_public_rest_is_get_only_bounded_and_cors_credentials_are_disabled():
    s=text(MOD)
    assert "REST_ROUTE = '/curated-spaces'" in s and 'MAX_INDEX = 48' in s
    assert s.count("'methods' => WP_REST_Server::READABLE") >= 4 and s.count("'permission_callback' => '__return_true'") >= 4
    assert 'CREATABLE' not in s and 'EDITABLE' not in s and 'DELETABLE' not in s
    assert 'SC_Library_API_Embeds_Interoperability::allowed_origins()' in s and "'Access-Control-Allow-Credentials', 'false'" in s

def test_frontend_and_admin_builder_are_accessible_responsive_and_time_bounded():
    js=text(PLUGIN/'assets/js/sc-library-curated-spaces-v540.js'); css=text(PLUGIN/'assets/css/sc-library-curated-spaces-v540.css'); s=text(MOD)
    assert 'AbortController' in js and '12000' in js and "credentials:'omit'" in js
    assert 'data-sc-curated-section-builder' in s and 'aria-live="polite"' in s and 'inputmode="numeric"' in s
    assert ':focus-visible' in css and 'min-height:44px' in css and '@media(max-width:760px)' in css and 'prefers-reduced-motion' in css

def test_extension_bootstrap_adds_exactly_one_v540_module():
    s=text(BOOT)
    assert 'public const MODULE_COUNT = 55;' in s
    assert "'class-sc-library-research-collections-curated-spaces.php' => 'SC_Library_Research_Collections_Curated_Spaces'" in s

def test_production_gate_certifies_module_assets_contract_and_cache_boundary():
    s=text(HARD)
    assert "BRANCH_VERSION = '5.4.0'" in s and "BRANCH_SCHEMA = 'sc-library-v540-curated-spaces-certification/1.0'" in s
    assert "'SC_Library_Research_Collections_Curated_Spaces'" in s and "'/sc-library/v1/curated-spaces'" in s
    assert 'sc-library-curated-spaces-v540.js' in s and 'sc-library-curated-spaces-v540.css' in s
    assert 'v540-curated-spaces-contract' in s and 'v540-curated-spaces-cache-boundary' in s and "'curated_spaces_cacheable'" in s

def test_page_places_curated_spaces_after_public_evidence_before_research_access():
    page=text(PAGE)
    assert 'cc-rl-v540' in page and '[sc_research_curated_spaces title="Research Collections, Exhibitions &amp; Curated Knowledge Spaces"]' in page
    assert page.index('id="public-evidence-claims"') < page.index('id="curated-knowledge-spaces"') < page.index('id="research-access"')
    assert 'href="#curated-knowledge-spaces"' in page

def test_unified_home_hands_off_to_public_curation_without_counting_it_as_private_state():
    s=text(UNIFIED)
    assert 'href="#curated-knowledge-spaces"' in s and "Curated Knowledge Spaces" in s
    assert "'curated_spaces'" not in s and "'curated_spaces'=>" not in s

def test_readme_docs_fixture_and_identity_state_are_current():
    rd=text(README); route=text(ROUTE)
    assert 'Stable tag: 5.4.0' in rd and 'Research Collections, Exhibitions & Curated Knowledge Spaces' in rd
    assert 'data-sc-library-account-continuity="v5.4.0"' in route
    assert (ROOT/'RESEARCH_COLLECTIONS_CURATED_SPACES_v5.4.0.md').exists() and (ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.4.0.md').exists()
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v540_curated_spaces.php')],cwd=ROOT,text=True,capture_output=True,check=True)
    assert 'PASS - v5.4.0 curated spaces contract fixture' in result.stdout
