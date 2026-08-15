<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){return $s;} function add_action(){} function add_shortcode(){} function add_filter(){}
function apply_filters($tag,$value){return $value;} function home_url($s='/'){return 'https://example.test'.$s;}
function rest_url($s=''){return 'https://example.test/wp-json/'.ltrim($s,'/');} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$s));}
require dirname(__DIR__).'/sustainable-catalyst-library/includes/class-sc-library-connected-public-research-infrastructure.php';
$c=SC_Library_Connected_Public_Research_Infrastructure::contract();
$p=SC_Library_Connected_Public_Research_Infrastructure::infrastructure_profile();
$checks=[
 SC_Library_Connected_Public_Research_Infrastructure::VERSION==='5.0.0',
 $c['v490_public_api_reused']===true,$c['v4337_publication_graph_reused']===true,$c['v330_pathway_graph_reused']===true,$c['v320_public_knowledge_relationships_reused']===true,$c['v480_published_federation_manifests_reused']===true,
 $c['creates_parallel_public_record_store']===false,$c['creates_parallel_graph_store']===false,$c['creates_parallel_federation_registry']===false,
 $c['explicit_relationships_only']===true,$c['one_hop_network_only']===true,$c['public_get_only']===true,
 $c['private_projects_exposed']===false,$c['personal_library_exposed']===false,$c['research_room_membership_exposed']===false,$c['team_library_membership_exposed']===false,$c['private_federation_governance_exposed']===false,$c['credentials_exposed']===false,
 $c['automatic_semantic_inference']===false,$c['automatic_publication']===false,$c['automatic_workspace_write']===false,
 $p['connection_policy']==='explicit-one-hop-only',$p['writes_supported']===false,$p['private_research_supported']===false,
];
if(in_array(false,$checks,true)){fwrite(STDERR,"v5.0.0 contract fixture failed\n");exit(1);} echo "PASS - v5.0.0 Connected Public Research Infrastructure contract fixture\n";
