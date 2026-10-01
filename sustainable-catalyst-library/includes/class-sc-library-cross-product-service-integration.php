<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Cross_Product_Service_Integration {
    public const VERSION = '5.66.0';
    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_rest']);
        add_shortcode('sc_library_cross_product_integration_status', [$this, 'shortcode']);
    }
    public function register_rest(): void {
        register_rest_route('sc-library/v1', '/cross-product-integrations', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'status'],
        ]);
    }
    public function status(): array {
        $url = SC_Library_Python_Backend::base_url() . '/api/library/v1/integrations/readiness';
        $r = wp_remote_get($url, ['timeout'=>8,'redirection'=>1,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['state'=>'backend-unavailable','authoritative'=>false,'wordpress_required'=>false];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        return is_array($body) ? $body : ['state'=>'invalid-response','authoritative'=>false,'wordpress_required'=>false];
    }
    public function shortcode(): string {
        $s=$this->status();
        $state=esc_html((string)($s['state'] ?? 'unknown'));
        $count=esc_html((string)($s['product_count'] ?? 0));
        return '<div class="sc-library-cross-product-status"><strong>Direct Library integrations:</strong> '.$state.' · '.$count.' products</div>';
    }
}
