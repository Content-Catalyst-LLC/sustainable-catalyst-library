<?php
/**
 * Canonical public routing and shared-account continuity.
 *
 * v4.3.27 makes /knowledge-libraries/ the authoritative public Library route,
 * redirects only the legacy public /library/ page, and documents the fact that
 * private Library features use the same WordPress account/session as Workspace.
 * Internal REST namespaces that contain /library/ are intentionally untouched.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Canonical_Route_Identity {
    public const VERSION = '4.3.37';
    public const SCHEMA = 'sc-library-route-identity-health/1.0';
    public const ACCOUNT_SCHEMA = 'sc-library-account-continuity/1.0';
    public const CANONICAL_SLUG = 'knowledge-libraries';
    public const LEGACY_SLUG = 'library';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const HEALTH_ROUTE = '/runtime/identity-health';
    public const OPTION_LAST_REDIRECT = 'sc_library_v4327_last_legacy_redirect';

    /** @var array<string,string> */
    private const PRIVATE_ACCOUNT_CONTRACTS = array(
        'my_libraries'       => 'sc_library_my_libraries_v4319',
        'source_collections' => 'sc_library_source_collections_v4322',
        'research_documents' => 'sc_library_research_documents_v4323',
        'course_plan'        => 'sc_library_course_plan_v4321',
        'learning_routes'    => 'sc_library_learning_routes_v4336',
        'personal_library'   => 'sc_library_personal_items_v4328',
        'saved_searches'     => 'sc_library_saved_searches_v4329',
        'watchlists'         => 'sc_library_watchlists_v4329',
        'research_queue'     => 'sc_library_research_queue_v4329',
        'research_projects'  => 'sc_research_project:post_author',
        'project_links'      => '_sc_project_unified_links_v4330',
        'source_bundles'     => '_sc_project_source_bundles_v4330',
        'reading_notebooks'  => 'sc_reading_notebook:post_author',
        'reading_notes'      => '_sc_reading_notebook_notes_v4331',
        'source_annotations' => '_sc_reading_notebook_annotations_v4331',
        'evidence_matrices'   => 'sc_evidence_matrix:post_author',
        'matrix_claims'       => '_sc_evidence_matrix_claims_v4332',
        'matrix_links'        => '_sc_evidence_matrix_links_v4332',
        'workspace_handoffs'   => 'sc_workspace_handoff:_sc_handoff_created_by',
        'workspace_returns'    => 'sc_workspace_handoff:_sc_handoff_result_url',
        'metadata_reviews'     => 'sc_metadata_review:post_author',
    );

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'redirect_legacy_public_route' ), 0 );
        add_filter( 'redirect_canonical', array( $this, 'protect_canonical_public_route' ), 5, 2 );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_library_account_continuity', array( $this, 'shortcode_account_continuity' ) );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-account-continuity-v4327',
            SC_LIBRARY_URL . 'assets/css/sc-library-account-continuity-v4327.css',
            array(),
            SC_LIBRARY_VERSION
        );
    }

    public static function canonical_url() {
        return trailingslashit( home_url( '/' . self::CANONICAL_SLUG . '/' ) );
    }

    public static function legacy_url() {
        return trailingslashit( home_url( '/' . self::LEGACY_SLUG . '/' ) );
    }

    /**
     * Normalize a request URI to the site's public path, preserving subdirectory installs.
     */
    private static function site_public_path( $slug ) {
        $base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $base = '/' . trim( $base, '/' );
        if ( '/' === $base ) {
            $base = '';
        }
        return '/' . trim( $base . '/' . trim( (string) $slug, '/' ), '/' ) . '/';
    }

    public static function request_targets_slug( $request_uri, $slug ) {
        $path = (string) wp_parse_url( (string) $request_uri, PHP_URL_PATH );
        if ( '' === $path ) {
            return false;
        }
        $normalized = '/' . trim( $path, '/' ) . '/';
        return self::site_public_path( $slug ) === $normalized;
    }

    public static function request_targets_legacy_public_route( $request_uri ) {
        return self::request_targets_slug( $request_uri, self::LEGACY_SLUG );
    }

    public static function request_targets_canonical_public_route( $request_uri ) {
        return self::request_targets_slug( $request_uri, self::CANONICAL_SLUG );
    }

    public function redirect_legacy_public_route() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            return;
        }

        $request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( ! self::request_targets_legacy_public_route( $request_uri ) ) {
            return;
        }

        $target = self::canonical_url();
        $query = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
        if ( '' !== $query ) {
            $target .= '?' . $query;
        }

        update_option(
            self::OPTION_LAST_REDIRECT,
            array(
                'from'      => self::legacy_url(),
                'to'        => self::canonical_url(),
                'timestamp' => current_time( 'mysql', true ),
            ),
            false
        );

        nocache_headers();
        wp_safe_redirect( $target, 301, 'Sustainable Catalyst Library v4.3.37' );
        exit;
    }

    /**
     * Prevent WordPress canonical handling from ever steering the canonical
     * Knowledge Library page back to the retired /library/ public path.
     */
    public function protect_canonical_public_route( $redirect_url, $requested_url ) {
        $request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( ! self::request_targets_canonical_public_route( $request_uri ) ) {
            return $redirect_url;
        }
        if ( is_string( $redirect_url ) && self::request_targets_legacy_public_route( $redirect_url ) ) {
            return false;
        }
        return $redirect_url;
    }

    public function register_rest_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            self::HEALTH_ROUTE,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => array( $this, 'rest_health' ),
            )
        );
    }

    public function rest_health() {
        return rest_ensure_response( self::health_payload() );
    }

    public static function account_contract() {
        return array(
            'schema'                           => self::ACCOUNT_SCHEMA,
            'account_source'                   => 'wordpress',
            'library_account'                  => 'shared-sustainable-catalyst-account',
            'workspace_account'                => 'shared-sustainable-catalyst-account',
            'separate_library_account_required'=> false,
            'external_library_credentials_stored' => false,
            'private_account_contracts'        => self::PRIVATE_ACCOUNT_CONTRACTS,
        );
    }

    public static function health_payload() {
        $canonical_page = get_page_by_path( self::CANONICAL_SLUG, OBJECT, 'page' );
        $legacy_page = get_page_by_path( self::LEGACY_SLUG, OBJECT, 'page' );
        $version = defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : '';
        $canonical_ready = $canonical_page instanceof WP_Post && 'publish' === $canonical_page->post_status;
        $version_ready = self::VERSION === $version;

        return array(
            'schema'              => self::SCHEMA,
            'version'             => self::VERSION,
            'plugin_version'      => $version,
            'status'              => ( $canonical_ready && $version_ready ) ? 'ok' : 'attention',
            'canonical'           => array(
                'slug'           => self::CANONICAL_SLUG,
                'url'            => self::canonical_url(),
                'page_present'   => (bool) $canonical_page,
                'page_published' => $canonical_ready,
            ),
            'legacy_public_route' => array(
                'slug'              => self::LEGACY_SLUG,
                'url'               => self::legacy_url(),
                'redirect_status'   => 301,
                'redirect_target'   => self::canonical_url(),
                'legacy_page_exists'=> (bool) $legacy_page,
            ),
            'api_namespace_preserved' => true,
            'account_continuity'      => self::account_contract(),
        );
    }

    private function current_return_url() {
        $request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( '' !== $request_uri && 0 === strpos( $request_uri, '/' ) ) {
            return home_url( $request_uri );
        }
        return self::canonical_url();
    }

    public function shortcode_account_continuity( $atts ) {
        $atts = shortcode_atts(
            array(
                'workspace_url' => home_url( '/workspace/' ),
            ),
            $atts,
            'sc_library_account_continuity'
        );
        wp_enqueue_style( 'sc-library-account-continuity-v4327' );

        $signed_in = is_user_logged_in();
        $user = $signed_in ? wp_get_current_user() : null;
        $workspace_url = esc_url( (string) apply_filters( 'sc_library_workspace_url', $atts['workspace_url'] ) );

        ob_start();
        ?>
        <aside class="sc-library-account-continuity" data-sc-library-account-continuity="v4.3.37">
            <div class="sc-library-account-continuity__state">
                <small><?php esc_html_e( 'Account continuity', 'sustainable-catalyst-library' ); ?></small>
                <?php if ( $signed_in && $user instanceof WP_User ) : ?>
                    <strong><?php echo esc_html( sprintf( __( 'Signed in as %s', 'sustainable-catalyst-library' ), $user->display_name ) ); ?></strong>
                    <span><?php esc_html_e( 'Your Sustainable Catalyst account is the same authenticated account used by Workspace and private Library tools. My Sources, My Libraries, course plans, research documents, My Library collections, saved searches, watchlists, the research queue, private research projects with source bundles, reading notebooks, notes, reusable excerpts, source annotations, evidence matrices, claims, evidence links, and explicit Library ↔ Workspace handoff history and private metadata-review history remain attached to this account. Connected My Libraries relationships can inform Access Intelligence II pathway ranking without storing external-library credentials; Open Learning II can save private learning-route manifests without enrolling the user or changing provider accounts; Publications ↔ Research Graph handoffs add only canonical public-publication references to owned projects and never expose private research; no second Library account is required.', 'sustainable-catalyst-library' ); ?></span>
                <?php else : ?>
                    <strong><?php esc_html_e( 'One Sustainable Catalyst account', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Public research discovery stays open. Sign in once to persist private Library research and continue into Workspace without creating a separate Library account.', 'sustainable-catalyst-library' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="sc-library-account-continuity__actions">
                <?php if ( $signed_in ) : ?>
                    <a href="<?php echo $workspace_url; ?>"><?php esc_html_e( 'Open Workspace →', 'sustainable-catalyst-library' ); ?></a>
                <?php else : ?>
                    <a href="<?php echo esc_url( wp_login_url( $this->current_return_url() ) ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a>
                <?php endif; ?>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
}
