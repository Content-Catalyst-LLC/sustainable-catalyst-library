<?php
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_URL','https://example.test/wp-content/plugins/sustainable-catalyst-library/');
function add_action(){} function add_shortcode(){} function register_rest_route(){} function wp_register_style(){} function apply_filters($tag,$value){return $value;} function sanitize_key($v){$v=strtolower((string)$v);return preg_replace('/[^a-z0-9_\-]/','',$v);} function wp_strip_all_tags($v){return strip_tags((string)$v);} function esc_url_raw($v){return (string)$v;} function rawurlencode_safe($v){return rawurlencode($v);} 
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-institutional-connector-expansion.php';
$registry=SC_Library_Institutional_Connector_Expansion::registry();
$out=['schema'=>SC_Library_Institutional_Connector_Expansion::SCHEMA,'version'=>SC_Library_Institutional_Connector_Expansion::VERSION,'count'=>count($registry),'stanford'=>SC_Library_Institutional_Connector_Expansion::resolve_search_url('stanford','climate justice'),'ucd'=>SC_Library_Institutional_Connector_Expansion::resolve_search_url('ucd','energy systems'),'types'=>array_count_values(array_map(fn($x)=>$x['type'],$registry))];
echo json_encode($out, JSON_UNESCAPED_SLASHES);
