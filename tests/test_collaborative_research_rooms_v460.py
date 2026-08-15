from pathlib import Path
import re, subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-collaborative-research-rooms.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
BOOT=ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
HOME=ROOT/'sustainable-catalyst-library/includes/class-sc-library-unified-personal-research-environment.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v4.6.0.html'
README=ROOT/'sustainable-catalyst-library/readme.txt'

def text(p): return p.read_text()

def test_release_identity_is_v460():
    s=text(MAIN); m=text(MOD)
    assert 'Version: 4.6.0' in s and "SC_LIBRARY_VERSION', '4.6.0'" in s
    assert "public const VERSION = '4.6.0'" in m
    assert "POST_TYPE = 'sc_research_room'" in m

def test_private_room_storage_is_explicit_and_versioned():
    s=text(MOD)
    for marker in ['_sc_research_room_project_id_v460','_sc_research_room_urn_v460','_sc_research_room_members_v460','_sc_research_room_references_v460','_sc_research_room_notes_v460','_sc_research_room_decisions_v460','_sc_research_room_activity_v460']:
        assert marker in s
    assert "'public' => false" in s and "'show_ui' => false" in s

def test_room_is_project_anchored_but_does_not_transfer_ownership():
    s=text(MOD)
    assert 'project_ownership_transferred' in s and '=> false' in s
    assert 'room_membership_grants_project_access' in s
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in s
    assert "'room_owner_is_post_author'" in s

def test_explicit_share_boundary_blocks_private_copying():
    s=text(MOD)
    for marker in ["'explicit_share_required'", "'references_only'", "'copy_private_source_binaries'", "'copy_personal_library_contents'", "'copy_notebook_bodies_automatically'", "'copy_matrix_bodies_automatically'"]:
        assert marker in s
    assert "'references_only' => true" in s

def test_role_matrix_is_narrow_and_owner_only_membership():
    s=text(MOD)
    for role in ["'owner'", "'editor'", "'reviewer'", "'observer'"]:
        assert role in s
    assert "self::can( $room_id, $actor_id, 'manage_members' )" in s
    assert "'owner' === $role" in s

def test_notes_decisions_and_activity_are_human_lineage_records():
    s=text(MOD)
    assert "NOTE_SCHEMA = 'sc-library-research-room-note/1.0'" in s
    assert "DECISION_SCHEMA = 'sc-library-research-room-decision/1.0'" in s
    assert "ACTIVITY_SCHEMA = 'sc-library-research-room-activity/1.0'" in s
    assert 'append_activity' in s and 'MAX_ACTIVITY' in s
    assert "'decisions_are_human_recorded'" in s

def test_limits_bound_room_growth():
    s=text(MOD)
    for marker in ['MAX_ROOMS_PER_OWNER = 40','MAX_MEMBERS = 30','MAX_REFERENCES = 160','MAX_NOTES = 240','MAX_DECISIONS = 120','MAX_ACTIVITY = 320']:
        assert marker in s

def test_rest_surface_is_authenticated_and_role_checked():
    s=text(MOD)
    assert "REST_ROUTE = '/research-rooms'" in s
    assert s.count("'permission_callback' => array( $this, 'rest_signed_in' )") >= 3
    assert 'is_user_logged_in()' in s
    for cb in ['rest_members','rest_references','rest_notes','rest_decisions']:
        assert cb in s

def test_shortcodes_assets_and_accessibility_boundary_exist():
    s=text(MOD); js=text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-research-rooms-v460.js'); css=text(ROOT/'sustainable-catalyst-library/assets/css/sc-library-research-rooms-v460.css')
    assert 'sc_collaborative_research_rooms' in s
    assert 'data-sc-research-rooms="v4.6.0"' in s
    assert 'aria-live="polite"' in s
    assert 'focus-visible' in css and 'prefers-reduced-motion' in css
    assert 'X-WP-Nonce' in js

def test_extension_bootstrap_registers_new_module_without_replacing_existing_modules():
    s=text(BOOT)
    assert 'MODULE_COUNT = 47' in s
    assert "class-sc-library-collaborative-research-rooms.php" in s
    assert 'SC_Library_Knowledge_Graph_Evidence_Intelligence' in s
    assert 'SC_Library_Unified_Personal_Research_Environment' in s

def test_identity_health_tracks_room_account_contract():
    s=text(ROUTE)
    assert "public const VERSION = '4.6.0'" in s
    assert "'research_rooms'        => 'sc_research_room:post_author'" in s
    assert "'_sc_research_room_members_v460'" in s
    assert 'data-sc-library-account-continuity="v4.6.0"' in s

def test_production_gate_certifies_module_assets_and_private_route():
    s=text(HARD)
    assert "BRANCH_VERSION = '4.6.0'" in s
    assert "BRANCH_SCHEMA = 'sc-library-v46-production-certification/1.0'" in s
    assert 'SC_Library_Collaborative_Research_Rooms' in s
    assert '/sc-library/v1/research-rooms' in s
    assert 'assets/js/sc-library-research-rooms-v460.js' in s
    assert 'assets/css/sc-library-research-rooms-v460.css' in s

def test_unified_personal_research_environment_surfaces_rooms_without_new_migration():
    s=text(HOME)
    assert "research_rooms'=>__('Research rooms'" in s
    assert '#collaborative-research-rooms' in s
    assert 'Research Rooms' in s

def test_research_library_places_rooms_after_personal_home_and_preserves_graph():
    s=text(PAGE)
    assert '[sc_collaborative_research_rooms title="Collaborative Research Rooms"]' in s
    assert s.index('id="personal-research-environment"') < s.index('id="collaborative-research-rooms"') < s.index('id="research-access"')
    assert '[sc_knowledge_graph_evidence_intelligence title="Knowledge Graph &amp; Evidence Intelligence"]' in s
    assert '[sc_research_portability title="Research Portability & Preservation"]' in s

def test_readme_and_release_docs_state_non_public_non_transfer_boundary():
    s=text(README); d=text(ROOT/'COLLABORATIVE_RESEARCH_ROOMS_v4.6.0.md'); n=text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.6.0.md')
    assert 'Stable tag: 4.6.0' in s
    for blob in [s,d,n]:
        assert 'Collaborative Research Rooms' in blob
    assert 'does not transfer project ownership' in d or 'does not transfer Research Project ownership' in n
    assert 'No automatic publication' in d or 'No automatic publication' in n

def test_php_contract_fixture_proves_role_and_privacy_invariants():
    php='php'
    result=subprocess.run([php, str(ROOT/'tests/fixture_v460_contract.php')], capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    assert 'PASS - v4.6.0 collaboration contract fixture' in result.stdout
