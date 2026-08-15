from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-institutional-team-libraries.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
BOOT=ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
HOME=ROOT/'sustainable-catalyst-library/includes/class-sc-library-unified-personal-research-environment.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v4.7.0.html'
README=ROOT/'sustainable-catalyst-library/readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v470():
    s=text(MAIN); m=text(MOD)
    assert 'Version: 4.7.0' in s and "SC_LIBRARY_VERSION', '4.7.0'" in s
    assert "public const VERSION = '4.7.0'" in m and "POST_TYPE = 'sc_team_library'" in m

def test_private_versioned_team_library_storage_is_explicit():
    s=text(MOD)
    for marker in ['_sc_team_library_urn_v470','_sc_team_library_institution_id_v470','_sc_team_library_unit_id_v470','_sc_team_library_members_v470','_sc_team_library_collections_v470','_sc_team_library_references_v470','_sc_team_library_activity_v470']:
        assert marker in s
    assert "'public' => false" in s and "'show_ui' => false" in s

def test_canonical_institution_and_unit_registries_are_reused_not_duplicated():
    s=text(MOD)
    assert "'canonical_institution_registry_reused' => true" in s
    assert "'canonical_unit_registry_reused' => true" in s
    assert "'creates_parallel_institution_registry' => false" in s
    assert "'sc_institution' === $post->post_type" in s and "'sc_research_unit' === $post->post_type" in s

def test_institution_binding_is_context_not_entitlement_or_legal_ownership():
    s=text(MOD)
    assert "'institutional_binding_is_context_not_entitlement' => true" in s
    assert 'do not prove legal ownership' in s or 'does not prove legal ownership' in s
    assert 'subscription entitlement' in s

def test_membership_does_not_expose_personal_or_project_or_room_access():
    s=text(MOD)
    for marker in ["'membership_grants_personal_library_access' => false","'membership_grants_project_access' => false","'membership_grants_research_room_access' => false"]:
        assert marker in s
    assert "'copy_personal_library_contents' => false" in s and "'copy_notebook_bodies' => false" in s and "'copy_matrix_bodies' => false" in s

def test_role_matrix_supports_governed_team_stewardship():
    s=text(MOD)
    for role in ["'owner'","'steward'","'editor'","'contributor'","'reader'"]:
        assert role in s
    assert "'steward'     => array( 'manage_members' => true" in s
    assert "'reader'      => array( 'manage_members' => false" in s

def test_explicit_contribution_is_references_only_and_bounded():
    s=text(MOD)
    assert "'explicit_contribution_required' => true" in s and "'references_only' => true" in s
    assert "'references_only' => true" in s[s.index('contribute_reference'):]
    for marker in ['MAX_LIBRARIES_PER_OWNER = 30','MAX_MEMBERS = 80','MAX_COLLECTIONS = 80','MAX_REFERENCES = 600','MAX_ACTIVITY = 500']:
        assert marker in s

def test_team_collections_are_team_curatorial_structures_not_personal_collection_migration():
    s=text(MOD)
    assert "COLLECTION_SCHEMA = 'sc-library-team-library-collection/1.0'" in s
    assert 'add_collection' in s and 'META_COLLECTIONS' in s
    assert "'copy_personal_library_contents' => false" in s

def test_rest_surface_is_authenticated_and_role_checked():
    s=text(MOD)
    assert "REST_ROUTE = '/team-libraries'" in s
    assert s.count("'permission_callback' => array( $this, 'rest_signed_in' )") >= 3
    assert 'is_user_logged_in()' in s
    for cb in ['rest_members','rest_collections','rest_references']:
        assert cb in s

def test_shortcode_assets_mobile_accessibility_and_nonce_boundary_exist():
    s=text(MOD); js=text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-team-libraries-v470.js'); css=text(ROOT/'sustainable-catalyst-library/assets/css/sc-library-team-libraries-v470.css')
    assert 'sc_institutional_team_libraries' in s and 'data-sc-team-libraries="v4.7.0"' in s
    assert 'aria-live="polite"' in s and 'X-WP-Nonce' in js
    assert 'focus-visible' in css and 'prefers-reduced-motion' in css and '@media(max-width:760px)' in css

def test_extension_bootstrap_registers_module_without_replacing_rooms_or_graph():
    s=text(BOOT)
    assert 'MODULE_COUNT = 48' in s
    assert 'class-sc-library-institutional-team-libraries.php' in s
    assert 'SC_Library_Collaborative_Research_Rooms' in s and 'SC_Library_Knowledge_Graph_Evidence_Intelligence' in s

def test_identity_health_tracks_team_library_ownership_and_membership():
    s=text(ROUTE)
    assert "public const VERSION = '4.7.0'" in s
    assert "'team_libraries'         => 'sc_team_library:post_author'" in s
    assert "'team_library_members'  => '_sc_team_library_members_v470'" in s
    assert 'data-sc-library-account-continuity="v4.7.0"' in s

def test_production_gate_certifies_module_assets_and_private_route():
    s=text(HARD)
    assert "BRANCH_VERSION = '4.7.0'" in s and "BRANCH_SCHEMA = 'sc-library-v47-production-certification/1.0'" in s
    assert 'SC_Library_Institutional_Team_Libraries' in s and '/sc-library/v1/team-libraries' in s
    assert 'assets/js/sc-library-team-libraries-v470.js' in s and 'assets/css/sc-library-team-libraries-v470.css' in s

def test_unified_personal_environment_surfaces_team_libraries_without_store_migration():
    s=text(HOME)
    assert "team_libraries'=>__('Team libraries'" in s
    assert '#institutional-team-libraries' in s and 'Team Libraries' in s

def test_research_library_places_team_libraries_after_rooms_before_access():
    s=text(PAGE)
    assert '[sc_institutional_team_libraries title="Institutional &amp; Team Libraries"]' in s
    assert s.index('id="collaborative-research-rooms"') < s.index('id="institutional-team-libraries"') < s.index('id="research-access"')
    assert '[sc_collaborative_research_rooms title="Collaborative Research Rooms"]' in s

def test_readme_docs_and_php_fixture_prove_boundaries():
    s=text(README); d=text(ROOT/'INSTITUTIONAL_TEAM_LIBRARIES_v4.7.0.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.7.0.md')
    assert 'Stable tag: 4.7.0' in s
    for blob in [s,d,n]: assert 'Institutional & Team Libraries' in blob
    assert 'references-only' in d.lower() or 'references only' in d.lower()
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v470_contract.php')],capture_output=True,text=True)
    assert result.returncode==0,result.stderr
    assert 'PASS - v4.7.0 institutional/team library contract fixture' in result.stdout
