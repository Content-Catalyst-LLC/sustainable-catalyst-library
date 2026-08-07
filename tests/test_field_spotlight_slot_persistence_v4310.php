<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_DIR', dirname(__DIR__) . '/sustainable-catalyst-library/');

class WP_Post {
    public int $ID;
    public string $post_status = 'publish';
    public string $post_password = '';
    public string $post_type = 'post';
    public string $post_title;
    public string $post_content = '';
    public function __construct(int $id, string $title) { $this->ID = $id; $this->post_title = $title; }
}
$GLOBALS['sc_test_posts'] = array(
    101 => new WP_Post(101, 'Treaties and Sources of International Law'),
);
function esc_url_raw($v){ return trim((string)$v); }
function absint($v){ return abs((int)$v); }
function url_to_postid($url){ return str_contains((string)$url, 'treaties-and-sources-of-international-law') ? 101 : 0; }
function get_post($id){ return $GLOBALS['sc_test_posts'][(int)$id] ?? null; }
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
function get_the_title($post){ $p = $post instanceof WP_Post ? $post : get_post((int)$post); return $p ? $p->post_title : ''; }
function get_permalink($post){ $p = $post instanceof WP_Post ? $post : get_post((int)$post); return $p ? 'https://sustainablecatalyst.com/treaties-and-sources-of-international-law/' : ''; }
function wp_parse_url($url, $component=-1){ return parse_url((string)$url, $component); }
function sanitize_title($v){ $v = strtolower(trim((string)$v)); $v = preg_replace('/[^a-z0-9]+/', '-', $v); return trim($v, '-'); }
function get_posts($args=array()){ return array(); }

require_once dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-field-spotlights.php';
$instance = new SC_Library_Field_Spotlights();
$method = new ReflectionMethod(SC_Library_Field_Spotlights::class, 'sanitize_article_slots');
$method->setAccessible(true);

$input = array(
    0 => array('source_id' => 101, 'url' => '', 'title' => ''),
    1 => array('source_id' => 0, 'url' => 'https://sustainablecatalyst.com/treaties-and-sources-of-international-law/', 'title' => ''),
    2 => array('source_id' => 0, 'url' => '', 'title' => ''),
);
$out = $method->invoke($instance, $input);
if (($out[0]['enabled'] ?? 0) !== 1) { fwrite(STDERR, "slot 0 not auto-enabled\n"); exit(1); }
if (($out[0]['source_id'] ?? 0) !== 101) { fwrite(STDERR, "slot 0 source id lost\n"); exit(1); }
if (($out[0]['title'] ?? '') !== 'Treaties and Sources of International Law') { fwrite(STDERR, "slot 0 title not resolved\n"); exit(1); }
if (($out[0]['url'] ?? '') !== 'https://sustainablecatalyst.com/treaties-and-sources-of-international-law/') { fwrite(STDERR, "slot 0 permalink not resolved\n"); exit(1); }
if (($out[1]['enabled'] ?? 0) !== 1 || ($out[1]['source_id'] ?? 0) !== 101) { fwrite(STDERR, "URL-only selection did not bind\n"); exit(1); }
if (($out[2]['enabled'] ?? 1) !== 0) { fwrite(STDERR, "empty slot incorrectly enabled\n"); exit(1); }
echo "PASS: selected supporting articles persist and activate by saved record/URL.\n";
