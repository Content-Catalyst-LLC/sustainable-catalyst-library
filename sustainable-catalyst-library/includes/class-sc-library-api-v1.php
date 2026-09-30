<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_API_V1 {
    public const VERSION = '5.63.0';
    public const BASE_PATH = '/api/library/v1';
    public function register_hooks(): void { add_shortcode('sc_library_api_v1_status', [$this, 'shortcode']); }
    private static function service(): array {
        if (!SC_Library_Python_Backend::configured()) return ['state'=>'unavailable','api_version'=>'1.0','base_path'=>self::BASE_PATH,'wordpress_required'=>false];
        $response=wp_remote_get(SC_Library_Python_Backend::base_url().self::BASE_PATH.'/service',['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($response) || 200 !== (int)wp_remote_retrieve_response_code($response)) return ['state'=>'unavailable','api_version'=>'1.0','base_path'=>self::BASE_PATH,'wordpress_required'=>false];
        $body=json_decode((string)wp_remote_retrieve_body($response),true);
        return is_array($body)?$body:['state'=>'unavailable','api_version'=>'1.0','base_path'=>self::BASE_PATH,'wordpress_required'=>false];
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Independent Library API v1'],$atts,'sc_library_api_v1_status'); $s=self::service(); ob_start(); ?>
        <section class="sc-library-api-v1" data-api-version="1.0"><p class="sc-kicker">Knowledge Library v5.63.0</p><h2><?php echo esc_html((string)$atts['title']); ?></h2><p>The Knowledge Library API is an independent service contract. WordPress is a client adapter and is not required for API execution.</p><dl><dt>API version</dt><dd><?php echo esc_html((string)($s['api_version']??'1.0')); ?></dd><dt>Base path</dt><dd><?php echo esc_html((string)($s['base_path']??self::BASE_PATH)); ?></dd><dt>WordPress required</dt><dd><?php echo !empty($s['wordpress_required'])?'yes':'no'; ?></dd></dl></section>
        <?php return (string)ob_get_clean();
    }
}
