from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
MOD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-connected-public-research-infrastructure.php'
MAIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'
BOOT=ROOT/'sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php'
HARD=ROOT/'sustainable-catalyst-library/includes/class-sc-library-hardening.php'
ROUTE=ROOT/'sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php'
HOME=ROOT/'sustainable-catalyst-library/includes/class-sc-library-unified-personal-research-environment.php'
API=ROOT/'sustainable-catalyst-library/includes/class-sc-library-api-embeds-interoperability.php'
PUB=ROOT/'sustainable-catalyst-library/includes/class-sc-library-publications-research-graph.php'
PATH=ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-pathways-article-maps.php'
KG=ROOT/'sustainable-catalyst-library/includes/class-sc-library-topics-concepts-relationships.php'
FED=ROOT/'sustainable-catalyst-library/includes/class-sc-library-global-research-federation.php'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.0.0.html'
README=ROOT/'sustainable-catalyst-library/readme.txt'
def text(p): return p.read_text()

def test_release_identity_is_v500():
    assert 'Version: 5.0.0' in text(MAIN) and "SC_LIBRARY_VERSION', '5.0.0'" in text(MAIN)
    assert "public const VERSION = '5.0.0'" in text(MOD)
    assert "SCHEMA = 'sc-library-connected-public-research/1.0'" in text(MOD)

def test_capstone_reuses_canonical_public_authorities_without_parallel_stores():
    s=text(MOD)
    for marker in ["'v490_public_api_reused' => true","'v4337_publication_graph_reused' => true","'v330_pathway_graph_reused' => true","'v320_public_knowledge_relationships_reused' => true","'v480_published_federation_manifests_reused' => true","'creates_parallel_public_record_store' => false","'creates_parallel_graph_store' => false","'creates_parallel_federation_registry' => false"]: assert marker in s
    assert 'SC_Library_API_Embeds_Interoperability::normalize_public_object' in s
    assert 'SC_Library_Publications_Research_Graph::build_graph' in s
    assert 'SC_Library_Knowledge_Pathways_Article_Maps::get_pathway_data' in s
    assert 'SC_Library_Topics_Concepts_Relationships::get_node_data' in s
    assert 'SC_Library_Global_Research_Federation::published_manifest_ids()' in s

def test_public_context_is_explicit_one_hop_and_bounded():
    s=text(MOD)
    assert 'MAX_CONNECTIONS = 120' in s
    assert "'explicit_relationships_only' => true" in s and "'one_hop_network_only' => true" in s
    assert "'automatic_semantic_inference' => false" in s
    assert "'one_hop_only' => true" in s

def test_context_carries_provenance_counts_and_sha256():
    s=text(MOD)
    assert "CONTEXT_SCHEMA = 'sc-library-public-research-context/1.0'" in s
    assert "'provenance' => sanitize_key( $source )" in s
    assert "'connection_counts' => $counts" in s and "'connection_total' => count( $connections )" in s
    assert "hash( 'sha256'" in s and "'manifest_sha256'" in s

def test_publication_context_uses_editor_declared_publication_graph():
    s=text(MOD); p=text(PUB)
    assert 'publication_connections' in s and 'SC_Library_Publications_Research_Graph::build_graph' in s
    for marker in ["'cites'","'states-claim'","'linked-pathway'","'article-map'"]: assert marker in s
    assert "'automatic_inference' => false" in p

def test_pathway_context_uses_only_public_steps_and_explicit_prerequisites():
    s=text(MOD)
    assert 'pathway_connections' in s
    assert "empty( $step['public'] )" in s
    assert "'pathway-step'" in s and "'prerequisite'" in s and "'continues-to'" in s
    assert 'derived_project_id' not in s

def test_public_knowledge_context_reuses_only_public_relationships():
    s=text(MOD); k=text(KG)
    assert 'SC_Library_Topics_Concepts_Relationships::get_node_data' in s
    assert "empty( $relation['public'] )" in s
    assert 'relationships_for_node' in k and "if ( ! $include_private && ( ! $public" in k

def test_federation_summary_reuses_published_manifests_without_private_governance():
    s=text(MOD); f=text(FED)
    assert 'published_manifest_ids()' in s and 'manifest_state( absint( $id ), 0, false )' in s
    assert "'public_metadata_only' => true" in s and "'private_governance_excluded' => true" in s
    assert "'private_content_included' => false" in f

def test_rest_surface_is_public_get_only_and_has_no_write_route():
    s=text(MOD)
    assert "REST_ROUTE = '/connected-public-research'" in s
    assert s.count("'permission_callback' => '__return_true'") >= 1
    assert "WP_REST_Server::READABLE" in s
    assert "'public_get_only' => true" in s and "'automatic_publication' => false" in s
    assert 'CREATABLE' not in s and 'EDITABLE' not in s and 'DELETABLE' not in s

def test_cors_reuses_v490_allowlist_without_credentials():
    s=text(MOD); a=text(API)
    assert 'SC_Library_API_Embeds_Interoperability::allowed_origins()' in s
    assert "'Access-Control-Allow-Credentials', 'false'" in s and "'Access-Control-Allow-Methods', 'GET'" in s
    assert 'sc_library_api_embed_allowed_origins' in a

def test_singular_public_records_emit_discovery_link_only():
    s=text(MOD)
    assert 'render_discovery_link' in s
    assert 'application/vnd.sustainable-catalyst.public-research+json' in s
    assert "'publish' !== $post->post_status" in s

def test_front_end_is_accessible_mobile_and_reduced_motion_aware():
    css=text(ROOT/'sustainable-catalyst-library/assets/css/sc-library-connected-public-research-v500.css')
    js=text(ROOT/'sustainable-catalyst-library/assets/js/sc-library-connected-public-research-v500.js')
    assert 'focus-visible' in css and '@media(max-width:760px)' in css and 'prefers-reduced-motion' in css
    assert 'aria-busy' in text(MOD) and "credentials:'omit'" in js and "method:'GET'" in js

def test_extension_identity_and_production_gate_are_version_aligned():
    b=text(BOOT); h=text(HARD); r=text(ROUTE)
    assert 'MODULE_COUNT = 51' in b and 'class-sc-library-connected-public-research-infrastructure.php' in b
    assert "BRANCH_VERSION = '5.0.0'" in h and "BRANCH_SCHEMA = 'sc-library-v50-production-certification/1.0'" in h
    assert 'SC_Library_Connected_Public_Research_Infrastructure' in h
    assert 'assets/js/sc-library-connected-public-research-v500.js' in h and 'assets/css/sc-library-connected-public-research-v500.css' in h
    assert "public const VERSION = '5.0.0'" in r and 'data-sc-library-account-continuity="v5.0.0"' in r

def test_unified_personal_environment_links_out_without_making_public_surface_private():
    assert '#connected-public-research' in text(HOME) and 'Public Research Infrastructure' in text(HOME)
    s=text(MOD)
    for marker in ["'private_projects_exposed' => false","'personal_library_exposed' => false","'notebook_bodies_exposed' => false","'matrix_bodies_exposed' => false","'research_room_membership_exposed' => false","'team_library_membership_exposed' => false","'credentials_exposed' => false"]: assert marker in s

def test_research_library_places_capstone_after_api_before_access():
    s=text(PAGE)
    assert '[sc_connected_public_research title="Connected Public Research Infrastructure"]' in s
    assert s.index('id="library-api-interoperability"') < s.index('id="connected-public-research"') < s.index('id="research-access"')
    assert '<li><a href="#connected-public-research">Connected Public Research Infrastructure</a></li>' in s

def test_readme_docs_and_php_fixture_state_no_private_promotion_boundary():
    blobs=[text(README),text(ROOT/'CONNECTED_PUBLIC_RESEARCH_INFRASTRUCTURE_v5.0.0.md'),text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.0.0.md')]
    assert 'Stable tag: 5.0.0' in blobs[0]
    for blob in blobs: assert 'Connected Public Research Infrastructure' in blob
    result=subprocess.run(['php',str(ROOT/'tests/fixture_v500_contract.php')],capture_output=True,text=True)
    assert result.returncode==0,result.stderr
    assert 'PASS - v5.0.0 Connected Public Research Infrastructure contract fixture' in result.stdout
