<?php
define('ABSPATH', __DIR__ . '/'); define('SC_LIBRARY_URL','https://example.test/wp-content/plugins/sustainable-catalyst-library/');
function add_action(){} function add_shortcode(){} function register_rest_route(){} function wp_register_style(){} function wp_register_script(){} function apply_filters($tag,$value){return $value;} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));} function wp_strip_all_tags($v){return strip_tags((string)$v);} function esc_url_raw($v){return (string)$v;}
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-public-library-network.php';
$r=SC_Library_Public_Library_Network::registry();
$out=['schema'=>SC_Library_Public_Library_Network::SCHEMA,'version'=>SC_Library_Public_Library_Network::VERSION,'count'=>count($r),'cpl'=>SC_Library_Public_Library_Network::resolve_search_url('cpl','climate justice'),'worldcat'=>SC_Library_Public_Library_Network::resolve_search_url('worldcat','energy systems'),'types'=>array_count_values(array_map(fn($x)=>$x['type'],$r))];
echo json_encode($out, JSON_UNESCAPED_SLASHES);
