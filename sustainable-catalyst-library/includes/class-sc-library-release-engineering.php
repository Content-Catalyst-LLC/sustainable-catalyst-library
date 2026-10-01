<?php
if (!defined('ABSPATH')) exit;

final class SC_Library_Release_Engineering {
    public const VERSION = '5.66.0';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_shortcode('sc_library_release_status', [$this, 'shortcode']);
    }

    public function register_rest_routes(): void {
        register_rest_route('sc-library/v1', '/release-engineering', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'status'],
        ]);
    }

    public function status(): array {
        $backend = trailingslashit((string) get_option('sc_library_backend_url', 'https://library.sustainablecatalyst.com')) . 'api/library/v1/release-engineering/readiness';
        $response = wp_remote_get($backend, ['timeout' => 5]);
        $remote = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            if (is_array($decoded)) $remote = $decoded;
        }
        return [
            'schema' => 'sc-library-wordpress-release-console/1.0',
            'library_version' => SC_LIBRARY_VERSION,
            'role' => 'read-only-release-status-adapter',
            'release_authority' => false,
            'deployment_execution' => false,
            'wordpress_required_for_release_engineering' => false,
            'backend_release_readiness' => $remote,
        ];
    }

    public function shortcode(): string {
        $s = $this->status();
        $state = is_array($s['backend_release_readiness'] ?? null) ? ($s['backend_release_readiness']['state'] ?? 'unknown') : 'unavailable';
        return '<div class="sc-library-release-status"><strong>Library release engineering:</strong> '.esc_html((string)$state).'</div>';
    }
}
