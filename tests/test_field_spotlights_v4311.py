from pathlib import Path
import json
import subprocess
import textwrap

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"

def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")

def test_v4311_release_and_verified_save_contract():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    template = text(PLUGIN / "templates/field-spotlights.php")
    public_js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    admin_js = text(PLUGIN / "assets/js/sc-library-field-spotlights-admin.js")

    assert "Version: 4.3.11" in main
    assert "SC_LIBRARY_VERSION', '4.3.11" in main
    assert "Stable tag: 4.3.11" in readme
    assert "public const VERSION = '4.3.11'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v4311'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v4311'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v4311'" in src
    assert "admin_post_sc_library_save_field_spotlights" in src
    assert "save_settings_transaction" in src
    assert "check_admin_referer( 'sc_library_save_field_spotlights'" in src
    assert "update_option( self::SETTINGS_OPTION, $incoming, false )" in src
    assert "$persisted === $expected" in src
    assert "Field Spotlight content saved." in src
    assert "admin-post.php" in src
    assert 'action="options.php"' not in src
    assert "settings_fields( self::SETTINGS_GROUP )" not in src
    assert 'data-sc-field-spotlights="v4.3.11"' in template
    assert '[data-sc-field-spotlights="v4.3.11"]' in public_js
    assert '[data-sc-field-spotlights-admin="v4.3.11"]' in admin_js

def test_save_path_does_not_double_sanitize_partial_payload():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    assert "the already-sanitized value would sanitize a second time and lose the" in src
    assert "$expected = $this->sanitize_settings( $incoming );" in src
    assert "update_option( self::SETTINGS_OPTION, $incoming, false );" in src
    assert "update_option( self::SETTINGS_OPTION, $expected, false );" not in src

def test_panel_editor_posts_source_id_url_title_and_verified_action():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    for token in [
        'name="action" value="sc_library_save_field_spotlights"',
        "wp_nonce_field( 'sc_library_save_field_spotlights'",
        "data-source-id",
        "data-source-url",
        "data-source-title",
        "Save Spotlight content",
    ]:
        assert token in src

def test_runtime_panel_save_persists_international_law_slot():
    class_file = (PLUGIN / "includes/class-sc-library-field-spotlights.php").as_posix()
    plugin_dir = (PLUGIN.as_posix() + "/")
    php = textwrap.dedent(f"""\
        <?php
        define('ABSPATH', __DIR__ . '/');
        define('SC_LIBRARY_DIR', {json.dumps(plugin_dir)});
        class WP_Post {{
            public $ID; public $post_status='publish'; public $post_password=''; public $post_type='post'; public $post_title='Treaty Interpretation';
            public function __construct($id) {{ $this->ID=$id; }}
        }}
        class SC_Library_Publications {{
            public static function article_map_registry() {{
                return array('international-law'=>array(
                    'title'=>'International Law','url'=>'/international-law/','field'=>'Global Governance',
                    'field_order'=>1,'group'=>'','order'=>1,'aliases'=>array()
                ));
            }}
        }}
        $GLOBALS['sc_option'] = array();
        $GLOBALS['sc_instance'] = null;
        function add_action(...$args){{}}
        function add_shortcode(...$args){{}}
        function apply_filters($tag,$value,...$args){{ return $value; }}
        function current_user_can($cap){{ return true; }}
        function wp_die($m){{ throw new Exception($m); }}
        function esc_html__($s,$d=null){{ return $s; }}
        function check_admin_referer($a,$n){{ return true; }}
        function wp_unslash($v){{ return $v; }}
        function sanitize_key($v){{ return strtolower(preg_replace('/[^a-z0-9_\\-]/','',(string)$v)); }}
        function sanitize_title($v){{ $v=strtolower((string)$v); $v=preg_replace('/[^a-z0-9]+/','-',$v); return trim($v,'-'); }}
        function sanitize_text_field($v){{ return trim(strip_tags((string)$v)); }}
        function sanitize_textarea_field($v){{ return trim(strip_tags((string)$v)); }}
        function esc_url_raw($v){{ return trim((string)$v); }}
        function absint($v){{ return abs((int)$v); }}
        function get_option($name,$default=array()){{ return array_key_exists($name,$GLOBALS['sc_option']) ? $GLOBALS['sc_option'][$name] : $default; }}
        function update_option($name,$value,$autoload=false){{
            $GLOBALS['sc_option'][$name] = $GLOBALS['sc_instance']->sanitize_settings($value);
            return true;
        }}
        function delete_transient($k){{ return true; }}
        function get_post($id){{ return (int)$id === 123 ? new WP_Post(123) : null; }}
        function get_the_title($post){{ return $post instanceof WP_Post ? $post->post_title : ''; }}
        function get_permalink($post){{ return 'https://sustainablecatalyst.com/treaty-interpretation/'; }}
        function url_to_postid($url){{ return 0; }}
        function wp_parse_url($url,$component=-1){{ return parse_url($url,$component); }}
        function get_posts($args){{ return array(); }}
        function admin_url($path=''){{ return 'https://example.test/wp-admin/' . ltrim($path,'/'); }}
        function add_query_arg($args,$url){{ return $url . '?' . http_build_query($args); }}
        function wp_safe_redirect($url){{
            echo json_encode(array('option'=>$GLOBALS['sc_option'],'redirect'=>$url));
            return true;
        }}
        require {json.dumps(class_file)};
        $GLOBALS['sc_instance'] = new SC_Library_Field_Spotlights();
        $_POST = array(
            '_sc_library_field_spotlight_nonce'=>'ok',
            SC_Library_Field_Spotlights::SETTINGS_OPTION=>array(
                '_context'=>'panel',
                '_panel_key'=>'international-law',
                'panels'=>array(
                    'international-law'=>array(
                        'title'=>'International Law',
                        'order'=>'1',
                        'visible'=>'1',
                        'slot_count'=>'4',
                        'hero_title'=>'International Law',
                        'hero_description'=>'',
                        'hero_cta'=>'Explore Article Map',
                        'articles'=>array(
                            0=>array(
                                'source_id'=>'123',
                                'url'=>'https://sustainablecatalyst.com/treaty-interpretation/',
                                'title'=>'',
                                'enabled'=>'1'
                            )
                        )
                    )
                )
            )
        );
        $GLOBALS['sc_instance']->save_settings_transaction();
    """)
    proc = subprocess.run(["php"], input=php, text=True, capture_output=True, check=True)
    payload = json.loads(proc.stdout)
    option = payload["option"]["sc_library_field_spotlights_settings_v434"]
    panel = option["panels"]["international-law"]
    assert panel["hero_title"] == "International Law"
    assert panel["articles"][0]["source_id"] == 123
    assert panel["articles"][0]["enabled"] == 1
    assert "sc_fs_saved=1" in payload["redirect"]
    assert "panel=international-law" in payload["redirect"]
