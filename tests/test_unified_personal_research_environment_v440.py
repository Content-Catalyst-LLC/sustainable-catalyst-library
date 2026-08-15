from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
MOD=(PLUGIN/'includes/class-sc-library-unified-personal-research-environment.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
HARD=(PLUGIN/'includes/class-sc-library-hardening.php').read_text()
README=(PLUGIN/'readme.txt').read_text()
ROOTREADME=(ROOT/'README.md').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.4.0.html').read_text()
DOC=(ROOT/'UNIFIED_PERSONAL_RESEARCH_ENVIRONMENT_v4.4.0.md').read_text()
CSS=(PLUGIN/'assets/css/sc-library-personal-research-environment-v440.css').read_text()
JS=(PLUGIN/'assets/js/sc-library-personal-research-environment-v440.js').read_text()

def test_release_identity_and_route_alignment_are_v440():
    assert 'Version: 4.4.0' in MAIN and "define('SC_LIBRARY_VERSION', '4.4.0');" in MAIN
    assert "public const VERSION = '4.4.0'" in ROUTE
    assert 'data-sc-library-account-continuity="v4.4.0"' in ROUTE
    assert 'Sustainable Catalyst Library v4.4.0' in ROUTE

def test_new_module_is_registered_without_replacing_43_lineage():
    assert "class-sc-library-unified-personal-research-environment.php' => 'SC_Library_Unified_Personal_Research_Environment'" in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==45
    for cls in ['SC_Library_Research_Portability_Preservation','SC_Library_Research_Librarian_Project_Aware_Guidance','SC_Library_Publications_Research_Graph']:
        assert cls in BOOT

def test_contract_is_composition_only_and_creates_no_replacement_store():
    for marker in ["'composition_only'=>true","'canonical_stores_unchanged'=>true","'new_private_record_store'=>false","'new_project_store'=>false","'new_notebook_store'=>false","'new_evidence_store'=>false","'duplicate_private_content'=>false","'automatic_record_migration'=>false"]:
        assert marker in MOD

def test_contract_preserves_mutation_and_privacy_boundaries():
    for marker in ["'automatic_project_write'=>false","'automatic_notebook_write'=>false","'automatic_evidence_promotion'=>false","'automatic_workspace_write'=>false","'automatic_publication'=>false","'remote_synthesis_receives_private_context'=>false"]:
        assert marker in MOD

def test_state_composes_existing_canonical_modules():
    for marker in ['SC_Library_Personal_Collections_Recommendations::items_for_user','SC_Library_Saved_Searches_Watchlists_Queue::state_for_user','SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user','SC_Library_Reading_Notebook_Annotations::notebooks_for_user','SC_Library_Evidence_Matrix_Claim_Intelligence::matrices_for_user','SC_Library_Open_Learning_II::USER_META']:
        assert marker in MOD

def test_owned_project_selection_is_enforced_and_not_persisted():
    assert "!in_array($selected,$owned,true)" in MOD
    assert "add_query_arg('sc_project'" in MOD
    assert 'update_user_meta' not in MOD and 'wp_insert_post' not in MOD and 'update_post_meta' not in MOD

def test_summary_is_bounded_and_uses_counts_not_duplicate_payload_stores():
    assert 'return array_slice($items,0,8);' in MOD
    for key in ['personal_items','saved_searches','watchlists','research_queue','reading_notebooks','evidence_matrices','learning_routes']:
        assert f"'{key}'" in MOD
    assert "'recent_activity'=>self::recent($recent)" in MOD

def test_authenticated_rest_endpoint_is_registered():
    assert "public const REST_ROUTE='/personal-research-environment'" in MOD
    assert "'permission_callback'=>[$this,'rest_signed_in']" in MOD
    assert 'return is_user_logged_in();' in MOD
    assert "'project_id'=>['sanitize_callback'=>'absint'" in MOD

def test_shortcode_is_private_research_home_with_existing_tool_handoffs():
    assert "add_shortcode('sc_personal_research_environment'" in MOD
    assert 'data-sc-personal-research-home="v4.4.0"' in MOD
    for anchor in ['#personal-library','#saved-research','#research-projects','#reading-notebooks','#evidence-matrix','#research-librarian','#workspace-continuity','#research-portability']:
        assert anchor in MOD

def test_signed_out_state_requires_shared_account_signin():
    assert 'Sign in to open your private research home.' in MOD
    assert "wp_login_url($canonical.'#personal-research-environment')" in MOD
    assert 'same_library_workspace_account' in MOD

def test_front_end_is_mobile_accessible_and_restrained():
    assert 'min-height:44px' in CSS
    assert ':focus-visible' in CSS
    assert '@media(max-width:780px)' in CSS
    assert '@media(prefers-reduced-motion:reduce)' in CSS
    assert '--sc-accent:#9e1b1b' in CSS
    assert 'querySelectorAll' in JS and 'change' in JS

def test_research_library_places_unified_home_immediately_after_hero():
    hero_end=PAGE.index('</section>')
    home=PAGE.index('id="personal-research-environment"')
    access=PAGE.index('id="research-access"')
    assert hero_end < home < access
    assert '[sc_personal_research_environment title="Unified Personal Research Environment"]' in PAGE
    assert PAGE.startswith('<!-- Research Library v4.4.0 — Unified Personal Research Environment -->')

def test_page_preserves_specialized_research_tools_and_links_home():
    for marker in ['[sc_personal_library','[sc_research_continuity','[sc_unified_research_projects','[sc_reading_notebook_workspace','[sc_evidence_matrix_workspace','[sc_library_workspace_continuity','[sc_research_librarian_ii','[sc_research_portability']:
        assert marker in PAGE
    assert '<li><a href="#personal-research-environment">Unified Personal Research Environment</a></li>' in PAGE
    assert PAGE.count('href="#personal-research-environment"')>=3

def test_production_gate_is_version_aligned_and_certifies_new_private_surface():
    assert "BRANCH_VERSION = '4.4.0'" in HARD
    assert "BRANCH_SCHEMA = 'sc-library-v44-production-certification/1.0'" in HARD
    assert 'SC_Library_Unified_Personal_Research_Environment' in HARD
    assert '/sc-library/v1/personal-research-environment' in HARD
    assert 'sc-library-personal-research-environment-v440.js' in HARD and 'sc-library-personal-research-environment-v440.css' in HARD
    assert '4.4 release gate' in HARD

def test_readmes_and_release_docs_describe_no_migration_boundary():
    assert 'Stable tag: 4.4.0' in README
    assert '= Unified Personal Research Environment =' in README
    assert 'composition-only' in ROOTREADME.lower()
    assert 'composition layer rather than a data migration' in DOC
    assert 'No replacement project store' in (ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.4.0.md').read_text()

def test_validation_runner_includes_current_and_retained_hardening_lineage():
    runner=(ROOT/'tests/run_v440_validation.sh').read_text()
    for marker in ['test_unified_personal_research_environment_v440.py','test_branch_production_hardening_v4340.py','test_research_portability_preservation_v4339.py','test_global_library_access_v4319.py']:
        assert marker in runner
    assert 'php -l' in runner and 'node --check' in runner
