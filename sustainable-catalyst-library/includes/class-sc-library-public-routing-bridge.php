<?php
if (!defined('ABSPATH')) { exit; }

final class SC_Library_Public_Routing_Bridge {
    public const VERSION = '5.63.0';
    public const SCHEMA = 'sc-library-public-routing-seo-embed-bridge/1.0';
    public const DEFAULT_ORIGIN = 'https://library.sustainablecatalyst.com';

    public function register_hooks(): void {
        add_shortcode('sc_library_public_launch', [$this, 'launch_shortcode']);
        add_shortcode('sc_library_public_embed', [$this, 'embed_shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_head', [$this, 'emit_bridge_meta'], 2);
    }

    public static function public_origin(): string {
        $origin = defined('SC_LIBRARY_PUBLIC_ORIGIN') ? (string)SC_LIBRARY_PUBLIC_ORIGIN : self::DEFAULT_ORIGIN;
        $origin = (string)apply_filters('sc_library_public_origin', $origin);
        return rtrim(esc_url_raw($origin), '/');
    }

    public static function canonical_url(string $route='search', string $record_id=''): string {
        $origin = self::public_origin();
        if ($route === 'record' && $record_id !== '') return $origin . '/record/' . rawurlencode($record_id);
        $paths = ['home'=>'/','search'=>'/search','discover'=>'/discover','system'=>'/system','account'=>'/account'];
        $path = $paths[$route] ?? '/search';
        return $origin . ($path === '/' ? '' : $path);
    }

    public static function local_contract(): array {
        return [
            'schema'=>self::SCHEMA,
            'library_version'=>SC_LIBRARY_VERSION,
            'public_origin'=>self::public_origin(),
            'wordpress_role'=>'thin-adapter-public-bridge',
            'authoritative'=>false,
            'proxy_required'=>false,
            'routes'=>['home','search','record','discover','system','account'],
            'record_canonical_template'=>self::public_origin().'/record/{record_id}',
            'wordpress_cookie_is_library_session'=>false,
            'wordpress_research_authority'=>false,
        ];
    }

    private static function backend_contract(): array {
        if (!SC_Library_Python_Backend::configured()) return ['state'=>'unavailable','public_origin'=>self::public_origin()];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/api/library/v1/public-routing/readiness',['timeout'=>8,'redirection'=>1,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r) || 200 !== (int)wp_remote_retrieve_response_code($r)) return ['state'=>'unavailable','public_origin'=>self::public_origin()];
        $body=json_decode((string)wp_remote_retrieve_body($r),true);
        return is_array($body)?$body:['state'=>'unavailable','public_origin'=>self::public_origin()];
    }

    public function register_rest_routes(): void {
        register_rest_route('sc-library/v1','/public-bridge',[
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>static fn()=>rest_ensure_response(['contract'=>self::local_contract(),'backend'=>self::backend_contract()]),
            'permission_callback'=>'__return_true',
        ]);
    }

    public function emit_bridge_meta(): void {
        echo '<meta name="sc-library-public-origin" content="'.esc_attr(self::public_origin()).'" />' . "\n";
        echo '<meta name="sc-library-public-routing-authority" content="library-web" />' . "\n";
    }

    public function launch_shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['route'=>'search','record_id'=>'','label'=>'Open Knowledge Library'],$atts,'sc_library_public_launch');
        $url=self::canonical_url(sanitize_key((string)$atts['route']),sanitize_text_field((string)$atts['record_id']));
        return '<a class="sc-library-public-launch" href="'.esc_url($url).'" rel="noopener">'.esc_html((string)$atts['label']).'</a>';
    }

    public function embed_shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['record_id'=>'','title'=>'Knowledge Library record','height'=>'720'],$atts,'sc_library_public_embed');
        $record_id=sanitize_text_field((string)$atts['record_id']);
        if ($record_id==='') return '';
        $src=self::canonical_url('record',$record_id).'?embed=1';
        $height=max(320,min(1200,(int)$atts['height']));
        return '<iframe class="sc-library-public-embed" src="'.esc_url($src).'" title="'.esc_attr((string)$atts['title']).'" loading="lazy" height="'.esc_attr((string)$height).'" width="100%" sandbox="allow-scripts allow-same-origin allow-popups" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
    }
}
