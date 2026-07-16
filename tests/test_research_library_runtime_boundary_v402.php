<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_URL', 'https://example.test/wp-content/plugins/sustainable-catalyst-library/');
define('SC_LIBRARY_DIR', dirname(__DIR__) . '/sustainable-catalyst-library/');
define('SC_LIBRARY_VERSION', '4.0.2');

class ForcedRuntimeFailure extends RuntimeException {}
class SC_Library_Notebook {
    public static function enabled(): bool { throw new ForcedRuntimeFailure('forced interactive renderer failure'); }
    public static function matrix_enabled(): bool { return false; }
}
class WP_Query {
    public int $found_posts = 0;
    public int $max_num_pages = 0;
    public function __construct($args = []) {}
    public function have_posts(): bool { return false; }
}
function add_shortcode(...$args) {}
function add_action(...$args) {}
function sanitize_key($v){ return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function sanitize_title($v){ return sanitize_key(str_replace(' ','-',(string)$v)); }
function sanitize_text_field($v){ return strip_tags((string)$v); }
function wp_unslash($v){ return $v; }
function shortcode_atts($pairs,$atts,$tag=''){ return array_merge($pairs,is_array($atts)?$atts:[]); }
function get_option($key,$default=false){ return $default; }
function filter_var_polyfill($v){ return (bool)$v; }
function absint($v){ return abs((int)$v); }
function post_type_exists($v){ return in_array($v,['post','page'],true); }
function taxonomy_exists($v){ return false; }
function get_permalink($id=0){ return 'https://example.test/research-library/'; }
function get_queried_object_id(){ return 1; }
function home_url($p=''){ return 'https://example.test'.$p; }
function is_wp_error($v){ return false; }
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function esc_html__($v,$d=''){ return $v; }
function esc_html_e($v,$d=''){ echo $v; }
function esc_attr_e($v,$d=''){ echo $v; }
function esc_attr__($v,$d=''){ return $v; }
function __($v,$d=''){ return $v; }
function esc_html($v){ return htmlspecialchars((string)$v,ENT_QUOTES); }
function esc_attr($v){ return esc_html($v); }
function esc_url($v){ return (string)$v; }
function remove_query_arg($keys,$url=''){ return $url ?: 'https://example.test/research-library/'; }
function add_query_arg($args,$url=''){ return $url ?: 'https://example.test/research-library/'; }
function _n($s,$p,$n,$d=''){ return $n===1?$s:$p; }
function number_format_i18n($n){ return (string)$n; }
function current_time($type,$gmt=false){ return '2026-07-16 18:00:00'; }
function update_option(...$args){ return true; }
function wp_json_encode($v){ return json_encode($v); }
function current_user_can($cap){ return false; }
function get_terms($args=[]){ return []; }
function wp_rand($min=0,$max=0){ return 1234; }
function wp_trim_words($text,$num=55,$more=null){ return $text; }
function wp_strip_all_tags($text){ return strip_tags($text); }
function get_post_type_object($type){ return null; }
function get_post_type(){ return 'post'; }
function paginate_links($args=[]){ return ''; }
function wp_kses_post($v){ return $v; }
function get_the_excerpt(){ return ''; }
function get_the_content(){ return ''; }
function get_option_polyfill(){ return false; }

require SC_LIBRARY_DIR . 'includes/class-sc-library-shortcodes.php';
$shortcodes = new SC_Library_Shortcodes();
$html = $shortcodes->render(['show_header' => 'true']);
if (strpos($html, 'data-sc-library-recovery="1"') === false) {
    fwrite(STDERR, "FAIL: recovery HTML marker missing\n");
    exit(1);
}
echo "PASS: forced runtime failure returned protected server-rendered HTML.\n";
