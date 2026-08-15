<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){ return $s; }
function apply_filters($tag,$value){ return $value; }
function esc_url_raw($s){ return $s; }
function rest_url($s=''){ return 'https://example.test/wp-json/' . ltrim($s,'/'); }
function home_url($s='/'){ return 'https://example.test' . $s; }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-api-embeds-interoperability.php';
$c=SC_Library_API_Embeds_Interoperability::contract();
$p=SC_Library_API_Embeds_Interoperability::object_profiles();
$i=SC_Library_API_Embeds_Interoperability::interoperability_profile();
$checks=[
 SC_Library_API_Embeds_Interoperability::VERSION==='4.9.0',
 $c['canonical_public_records_reused']===true,
 $c['legacy_v390_public_api_reused']===true,
 $c['v480_federation_facade_reused']===true,
 $c['creates_parallel_public_record_store']===false,
 $c['public_get_only']===true,
 $c['raw_post_meta_exposed']===false,
 $c['private_projects_exposed']===false,
 $c['team_library_membership_exposed']===false,
 $c['credentials_exposed']===false,
 $c['external_embed_requires_allowed_origin']===true,
 $c['cors_credentials_allowed']===false,
 $c['automatic_cross_site_write']===false,
 isset($p['foundation-document'],$p['publication'],$p['pathway'],$p['research-source'],$p['named-entity'],$p['concept']),
 $i['writes_supported']===false,
 $i['credentials_supported']===false,
];
if(in_array(false,$checks,true)){fwrite(STDERR,"v4.9.0 contract fixture failed\n");exit(1);} echo "PASS - v4.9.0 Library API, Embeds & Interoperability contract fixture\n";
