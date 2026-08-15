from pathlib import Path
import subprocess, re
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text()
HARD=(PLUGIN/'includes/class-sc-library-hardening.php').read_text()
ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text()
BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text()
README=(PLUGIN/'readme.txt').read_text()
ROOTREADME=(ROOT/'README.md').read_text()
PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.40.html').read_text()
DOC=(ROOT/'BRANCH_PRODUCTION_HARDENING_v4.3.40.md').read_text()
NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.40.md').read_text()
CSS=(PLUGIN/'assets/css/sc-library-hardening.css').read_text()
FIXTURE=ROOT/'tests/php-fixtures/v4340_branch_readiness_fixture.php'

def test_release_identity_and_canonical_identity_are_v4340():
    assert 'Version: 4.3.40' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.40');" in MAIN
    assert "public const VERSION = '4.3.40'" in ROUTE
    assert 'data-sc-library-account-continuity="v4.3.40"' in ROUTE

def test_hardening_reuses_existing_engine_without_new_extension_module():
    assert "public const BRANCH_VERSION = '4.3.40'" in HARD
    assert "public const BRANCH_SCHEMA = 'sc-library-v43-production-certification/1.0'" in HARD
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==44
    assert 'class-sc-library-branch-production' not in BOOT

def test_new_runtime_readiness_alias_and_admin_details_are_registered():
    assert "'/runtime/production-readiness'" in HARD
    assert "'/runtime/production-readiness/details'" in HARD
    assert "'permission_callback' => '__return_true'" in HARD
    assert "current_user_can('manage_options')" in HARD

def test_branch_release_gate_is_distinct_from_overall_operational_status():
    assert "'branch_release_gate'" in HARD
    assert "'status' => ((int) ($branch['fail'] ?? 0) > 0) ? 'blocked' : 'ready'" in HARD
    assert "'overall_status' => (string) ($report['overall_status'] ?? 'unknown')" in HARD

def test_gate_is_first_party_only_and_upstream_nonblocking():
    for marker in ["'first_party_only' => true", "'network_calls_performed' => false", "'upstream_health_release_blocking' => false", "'private_record_content_inspected' => false"]:
        assert marker in HARD
    assert 'Release certification performs no third-party provider requests' in HARD

def test_branch_checks_runtime_identity_extension_and_module_lineage():
    for marker in ['v43-release-version','v43-identity-version','v43-extension-bootstrap','v43-critical-modules']:
        assert marker in HARD
    for cls in ['SC_Library_Canonical_Route_Identity','SC_Library_Unified_Research_Projects_Source_Bundles','SC_Library_Reading_Notebook_Annotations','SC_Library_Evidence_Matrix_Claim_Intelligence','SC_Library_Research_Portability_Preservation']:
        assert cls in HARD

def test_branch_checks_current_assets_without_network_calls():
    for asset in ['sc-library-personal-library-v4328.js','sc-library-unified-projects-v4330.js','sc-library-evidence-matrix-v4332.js','sc-library-open-learning-v2-v4336.js','sc-library-research-librarian-ii-v4338.js','sc-library-research-portability-v4339.js','sc-library-hardening.css']:
        assert asset in HARD
    assert 'is_readable(SC_LIBRARY_DIR . $relative)' in HARD

def test_shared_account_and_canonical_route_are_release_checks():
    assert 'v43-account-continuity' in HARD and 'v43-canonical-route' in HARD
    assert 'SC_Library_Canonical_Route_Identity::account_contract()' in HARD
    assert 'SC_Library_Canonical_Route_Identity::health_payload()' in HARD
    assert "'shared-sustainable-catalyst-account'" in HARD

def test_private_rest_bases_are_explicitly_certified():
    for route in ['/sc-library/v1/personal-library','/sc-library/v1/research-continuity','/sc-library/v1/research-projects','/sc-library/v1/reading-notebooks','/sc-library/v1/evidence-matrices','/sc-library/v1/workspace-continuity','/sc-library/v1/research-librarian-v2/catalog','/sc-library/v1/research-portability/catalog']:
        assert route in HARD
    assert "'__return_true' === $endpoint['permission_callback']" in HARD
    assert 'v43-private-rest-boundary' in HARD

def test_existing_readiness_shortcode_surfaces_branch_gate():
    assert '4.3 branch release gate' in HARD
    assert 'sc-library-readiness-release-gate' in HARD
    assert '.sc-library-readiness-release-gate' in CSS
    assert '.sc-library-readiness-badge--blocked' in CSS

def test_research_library_embeds_status_inside_existing_infrastructure_section():
    infra=PAGE.index('id="research-infrastructure"')
    status=PAGE.index('[sc_library_readiness_status show_categories="true"]')
    close=PAGE.index('</section>',status)
    assert infra < status < close
    assert PAGE.startswith('<!-- Research Library v4.3.40 — 4.3 Branch Production Hardening -->')

def test_page_preserves_current_4339_research_stack():
    for marker in ['[sc_research_portability','[sc_research_librarian_ii','id="publication-research-graph-section"','id="open-learning-ii"','id="workspace-continuity"','id="evidence-matrix"','id="reading-notebooks"','id="research-projects"']:
        assert marker in PAGE

def test_readmes_and_release_docs_are_current_and_truthful():
    assert 'Stable tag: 4.3.40' in README
    assert '/wp-json/sc-library/v1/runtime/production-readiness' in README
    assert 'v4.3.40 — 4.3 Branch Production Hardening' in ROOTREADME
    assert 'first-party release gate' in DOC.lower()
    assert 'No third-party provider health' in NOTES

def test_release_adds_no_new_private_research_store_or_auto_mutation_claim():
    assert 'No new research store' in README
    assert 'No new research post type, user-meta store, or private research payload is introduced' in DOC
    assert 'automatic publication' in DOC.lower() and 'workspace mutation' in DOC.lower()

def test_php_fixture_proves_ready_blocked_and_private_route_behavior():
    proc=subprocess.run(['php',str(FIXTURE)],capture_output=True,text=True,check=True)
    assert 'PASS - v4.3.40 branch readiness fixture' in proc.stdout

def test_validation_runner_includes_full_current_and_retained_lineage():
    runner=(ROOT/'tests/run_v4340_validation.sh').read_text()
    for marker in ['test_branch_production_hardening_v4340.py','test_research_portability_preservation_v4339.py','test_research_librarian_project_aware_guidance_v4338.py','test_publications_research_graph_v4337.py','test_global_library_access_v4319.py']:
        assert marker in runner
    assert 'php -l' in runner and 'node --check' in runner
