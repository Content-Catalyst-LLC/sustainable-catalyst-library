from pathlib import Path
import json, re, shutil, subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
MOD=(PLUGIN/'includes/class-sc-library-knowledge-graph-evidence-intelligence.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
HARD=(PLUGIN/'includes/class-sc-library-hardening.php').read_text()
README=(PLUGIN/'readme.txt').read_text()
ROOTREADME=(ROOT/'README.md').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.5.0.html').read_text()
DOC=(ROOT/'KNOWLEDGE_GRAPH_EVIDENCE_INTELLIGENCE_v4.5.0.md').read_text()
CSS=(PLUGIN/'assets/css/sc-library-knowledge-graph-evidence-v450.css').read_text()
JS=(PLUGIN/'assets/js/sc-library-knowledge-graph-evidence-v450.js').read_text()

def test_release_identity_and_canonical_route_are_v450():
    assert 'Version: 4.5.0' in MAIN and "define('SC_LIBRARY_VERSION', '4.5.0');" in MAIN
    assert "public const VERSION = '4.5.0'" in ROUTE
    assert 'data-sc-library-account-continuity="v4.5.0"' in ROUTE
    assert 'Sustainable Catalyst Library v4.5.0' in ROUTE

def test_graph_module_is_registered_without_replacing_prior_lineage():
    assert "class-sc-library-knowledge-graph-evidence-intelligence.php' => 'SC_Library_Knowledge_Graph_Evidence_Intelligence'" in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==46
    for cls in ['SC_Library_Unified_Personal_Research_Environment','SC_Library_Research_Portability_Preservation','SC_Library_Evidence_Matrix_Claim_Intelligence']:
        assert cls in BOOT

def test_contract_is_projection_only_and_explicit_relationships_only():
    for marker in ["'canonical_stores_unchanged'          => true","'graph_projection_rebuildable'        => true","'new_private_record_store'            => false","'explicit_relationships_only'         => true","'machine_inferred_relationships'      => false"]:
        assert marker in MOD

def test_contract_forbids_truth_scoring_and_automatic_mutation():
    for marker in ["'truth_scoring'                       => false","'automatic_claim_generation'          => false","'automatic_claim_status_change'       => false","'automatic_confidence_scoring'        => false","'automatic_evidence_promotion'        => false","'automatic_publication'               => false","'automatic_workspace_write'           => false"]:
        assert marker in MOD

def test_project_graph_requires_owned_project_and_reuses_canonical_project_state():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::project_state' in MOD
    assert "new WP_Error( 'sc_graph_project'" in MOD
    assert "'status' => 403" in MOD

def test_graph_projects_explicit_project_links_and_source_bundles():
    for marker in ["'explicit-project-link'","'explicit-source-bundle'","'contains_bundle'","'includes_reference'","$project['references']","$project['source_bundles']"]:
        assert marker in MOD

def test_graph_projects_notebooks_notes_annotations_and_explicit_source_links():
    for marker in ['SC_Library_Reading_Notebook_Annotations::notebooks_for_user','SC_Library_Reading_Notebook_Annotations::notebook_state',"'contains_note'","'contains_annotation'","'explicit-reading-note-source'"]:
        assert marker in MOD

def test_graph_projects_matrix_claims_and_user_created_evidence_relations():
    for marker in ['SC_Library_Evidence_Matrix_Claim_Intelligence::matrices_for_user','SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state',"'contains_claim'","'explicit-evidence-matrix-link'","$link['relation']"]:
        assert marker in MOD

def test_evidence_intelligence_reuses_matrix_diagnostics_without_conclusions():
    assert "$matrix['diagnostics']" in MOD
    for marker in ["'claims_with_counterevidence'","'relation_totals'","'claim_patterns'","'gap_totals'","'interpretation'                 => 'descriptive-only'","'scores_truth'                    => false","'infers_missing_relationships'    => false"]:
        assert marker in MOD

def test_public_private_boundary_is_explicit_in_contract_and_documentation():
    assert "'public_private_graph_boundary'       => 'preserved'" in MOD
    assert "'private_context_remote_synthesis'    => false" in MOD
    assert 'The existing public `SC_Library_Knowledge_Graph` and v4.3.37 Publications ↔ Research Graph remain separate public projections.' in DOC

def test_rest_surface_is_authenticated_and_bounded():
    assert "public const REST_ROUTE = '/knowledge-graph-evidence'" in MOD
    assert MOD.count("'permission_callback' => array( $this, 'rest_signed_in' )")>=2
    assert 'public const MAX_NODES = 240' in MOD and 'public const MAX_EDGES = 600' in MOD
    assert 'return is_user_logged_in();' in MOD

def test_shortcode_visualization_is_accessible_responsive_and_restrained():
    assert "add_shortcode( 'sc_knowledge_graph_evidence_intelligence'" in MOD
    assert 'data-sc-kge="v4.5.0"' in MOD
    assert 'Accessible graph record' in MOD
    assert 'role="note"' in MOD
    assert 'min-height:44px' in CSS and ':focus-visible' in CSS
    assert '@media(max-width:620px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS
    assert "createElementNS(ns,'svg')" in JS and "aria-label','Explicit project research graph'" in JS

def test_research_library_places_graph_after_evidence_before_workspace():
    matrix=PAGE.index('id="evidence-matrix"')
    graph=PAGE.index('id="knowledge-graph-evidence"')
    workspace=PAGE.index('id="workspace-continuity"')
    assert matrix < graph < workspace
    assert '[sc_knowledge_graph_evidence_intelligence title="Knowledge Graph &amp; Evidence Intelligence"]' in PAGE
    assert '<li><a href="#knowledge-graph-evidence">Knowledge Graph &amp; Evidence Intelligence</a></li>' in PAGE
    assert PAGE.startswith('<!-- Research Library v4.5.0 — Knowledge Graph & Evidence Intelligence -->')

def test_production_gate_certifies_new_private_surface_and_assets():
    assert "BRANCH_VERSION = '4.5.0'" in HARD
    assert "BRANCH_SCHEMA = 'sc-library-v45-production-certification/1.0'" in HARD
    assert 'SC_Library_Knowledge_Graph_Evidence_Intelligence' in HARD
    assert '/sc-library/v1/knowledge-graph-evidence' in HARD
    assert 'sc-library-knowledge-graph-evidence-v450.js' in HARD and 'sc-library-knowledge-graph-evidence-v450.css' in HARD

def test_readmes_and_release_docs_state_no_inference_or_new_graph_store():
    assert 'Stable tag: 4.5.0' in README
    assert '= Knowledge Graph & Evidence Intelligence =' in README
    assert 'private, account-scoped graph projection' in ROOTREADME
    assert 'not a second knowledge-graph database' in DOC
    notes=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.5.0.md').read_text()
    assert 'No semantic relationship is inferred from private text.' in notes

def test_php_contract_fixture_proves_safety_flags_when_php_available():
    php=shutil.which('php')
    if not php: return
    out=subprocess.check_output([php,str(ROOT/'tests/fixtures/knowledge_graph_evidence_contract_v450.php')],text=True)
    contract=json.loads(out)
    assert contract['explicit_relationships_only'] is True
    assert contract['machine_inferred_relationships'] is False
    assert contract['truth_scoring'] is False
    assert contract['new_private_record_store'] is False
    assert contract['automatic_workspace_write'] is False
