
from pathlib import Path
import json
import subprocess
import textwrap

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"

def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")

def test_release_markers_and_dedicated_panel_content_store():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    template = text(PLUGIN / "templates/field-spotlights.php")
    public_js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    admin_js = text(PLUGIN / "assets/js/sc-library-field-spotlights-admin.js")

    assert "Version: 4.3.12" in main
    assert "SC_LIBRARY_VERSION', '4.3.12" in main
    assert "Stable tag: 4.3.12" in readme
    assert "public const VERSION = '4.3.12'" in src
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v4312'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v4312'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v4312'" in src
    assert "update_option( self::PANEL_CONTENT_OPTION, $store, false )" in src
    assert "panel_content_store()" in src
    assert "sanitize_panel_content" in src
    assert "sc_fs_slots" in src
    assert "supporting article persisted" in src
    assert 'data-sc-field-spotlights="v4.3.12"' in template
    assert '[data-sc-field-spotlights="v4.3.12"]' in public_js
    assert '[data-sc-field-spotlights-admin="v4.3.12"]' in admin_js

def test_panel_editor_uses_dedicated_content_transaction_not_generic_settings_write():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    panel_branch = src[src.index("if ( 'panel' === $context ) {"):src.index("} else {", src.index("if ( 'panel' === $context ) {"))]
    assert "update_option( self::PANEL_CONTENT_OPTION, $store, false )" in panel_branch
    assert "update_option( self::SETTINGS_OPTION" not in panel_branch
    assert "The requested Field Spotlight panel is not registered." in panel_branch

def test_runtime_panel_save_persists_international_law_in_dedicated_store():
    class_file = (PLUGIN / "includes/class-sc-library-field-spotlights.php").as_posix()
    plugin_dir = PLUGIN.as_posix() + "/"
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
        $GLOBALS['sc_options'] = array(
            'sc_library_field_spotlights_settings_v434'=>array(
                'general'=>array(),
                'fields'=>array(),
                'panels'=>array(
                    'international-law'=>array('title'=>'International Law','order'=>1,'visible'=>1,'slot_count'=>4)
                )
            )
        );
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
        function get_option($name,$default=array()){{ return array_key_exists($name,$GLOBALS['sc_options']) ? $GLOBALS['sc_options'][$name] : $default; }}
        function update_option($name,$value,$autoload=false){{ $GLOBALS['sc_options'][$name]=$value; return true; }}
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
            echo json_encode(array('options'=>$GLOBALS['sc_options'],'redirect'=>$url));
            return true;
        }}
        require {json.dumps(class_file)};
        $instance = new SC_Library_Field_Spotlights();
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
                        'hero_description'=>'International law pathway.',
                        'hero_cta'=>'Explore Article Map',
                        'articles'=>array(
                            0=>array(
                                'source_id'=>'123',
                                'url'=>'https://sustainablecatalyst.com/treaty-interpretation/',
                                'title'=>'',
                                'enabled'=>'1'
                            ),
                            1=>array('source_id'=>'0','url'=>'','title'=>'','enabled'=>'0'),
                            2=>array('source_id'=>'0','url'=>'','title'=>'','enabled'=>'0'),
                            3=>array('source_id'=>'0','url'=>'','title'=>'','enabled'=>'0')
                        )
                    )
                )
            )
        );
        $instance->save_settings_transaction();
    """)
    proc = subprocess.run(["php"], input=php, text=True, capture_output=True, check=True)
    payload = json.loads(proc.stdout)
    store = payload["options"]["sc_library_field_spotlight_panel_content_v4312"]
    panel = store["international-law"]
    assert panel["hero_title"] == "International Law"
    assert panel["hero_description"] == "International law pathway."
    assert panel["articles"][0]["source_id"] == 123
    assert panel["articles"][0]["url"] == "https://sustainablecatalyst.com/treaty-interpretation/"
    assert panel["articles"][0]["enabled"] == 1
    assert "sc_fs_saved=1" in payload["redirect"]
    assert "sc_fs_slots=1" in payload["redirect"]
    assert "panel=international-law" in payload["redirect"]

def test_settings_overlay_prefers_dedicated_panel_content_over_legacy_panel_content():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    assert "$content_store = $this->panel_content_store();" in src
    assert "array( 'hero_title', 'hero_description', 'hero_cta', 'articles' )" in src
