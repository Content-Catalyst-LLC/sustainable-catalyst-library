<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_VERSION', '5.0.1');
function __($s,$d=null){return $s;} function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/','',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));} function absint($v){return abs((int)$v);} function wp_json_encode($v,$f=0){return json_encode($v,$f);} function wp_strip_all_tags($v){return strip_tags((string)$v);} function esc_url_raw($v){return (string)$v;} function home_url($p=''){return 'https://example.test'.$p;} function rest_url($p=''){return 'https://example.test/wp-json/'.ltrim($p,'/');}
function apply_filters($tag,$value){return $value;} function is_wp_error($v){return $v instanceof WP_Error;} function current_user_can($cap){return false;}
class WP_Error { public $code; public $message; public $data; public function __construct($c='',$m='',$d=[]){$this->code=$c;$this->message=$m;$this->data=$d;} }
class SC_Library_API_Embeds_Interoperability { public static function object_profiles(){return ['foundation-document'=>[], 'publication'=>[], 'pathway'=>[], 'research-source'=>[], 'named-entity'=>[], 'concept'=>[]];} public static function allowed_origins(){return ['https://example.test'];} }
class SC_Library_Global_Research_Federation { public static function published_manifest_ids(){return [];} }
require dirname(__DIR__).'/sustainable-catalyst-library/includes/class-sc-library-hardening.php';
require dirname(__DIR__).'/sustainable-catalyst-library/includes/class-sc-library-connected-public-research-infrastructure.php';
$profile=SC_Library_Hardening::public_v5_route_profile();
if(empty($profile['connected_public_research_cacheable'])||empty($profile['library_api_cacheable'])||!empty($profile['private_research_routes_cacheable'])){fwrite(STDERR,"cache profile failed\n");exit(1);}
$report=SC_Library_Connected_Public_Research_Infrastructure::run_production_soak(true);
if(($report['status']??'')!=='pass'||($report['scenario_count']??0)!==10||($report['failed']??1)!==0){fwrite(STDERR,json_encode($report)."\n");exit(1);}
echo "PASS - v5.0.1 production soak contract fixture\n";
