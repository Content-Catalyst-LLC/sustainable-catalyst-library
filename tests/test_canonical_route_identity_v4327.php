<?php
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_VERSION', '4.3.27');
define('SC_LIBRARY_URL', 'https://example.test/wp-content/plugins/sustainable-catalyst-library/');
function add_action(){} function add_filter(){} function add_shortcode(){}
function home_url($path='/'){ return 'https://example.test' . $path; }
function trailingslashit($value){ return rtrim((string)$value,'/') . '/'; }
function wp_parse_url($url,$component=-1){ return parse_url($url,$component); }
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php';
$out = [
  'version' => SC_Library_Canonical_Route_Identity::VERSION,
  'canonical' => SC_Library_Canonical_Route_Identity::canonical_url(),
  'legacy' => SC_Library_Canonical_Route_Identity::legacy_url(),
  'legacy_match' => SC_Library_Canonical_Route_Identity::request_targets_legacy_public_route('/library/?q=energy'),
  'canonical_match' => SC_Library_Canonical_Route_Identity::request_targets_canonical_public_route('/knowledge-libraries/'),
  'api_is_not_legacy' => !SC_Library_Canonical_Route_Identity::request_targets_legacy_public_route('/wp-json/sustainable-catalyst/v1/library/system/status'),
  'account' => SC_Library_Canonical_Route_Identity::account_contract(),
];
echo json_encode($out, JSON_UNESCAPED_SLASHES);
