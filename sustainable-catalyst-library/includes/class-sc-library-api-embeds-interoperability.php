<?php
/**
 * Library API, Embeds & Interoperability — v4.9.0.
 *
 * A stable, read-only public integration facade over canonical public Library
 * records and explicitly published federation manifests. It does not expose
 * private research stores, raw post meta, credentials, or authenticated
 * governance surfaces.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_API_Embeds_Interoperability {
    public const VERSION = '4.9.0';
    public const SCHEMA = 'sc-library-api-interoperability/1.0';
    public const OBJECT_SCHEMA = 'sc-library-public-object/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-interoperability-manifest/1.0';
    public const EMBED_SCHEMA = 'sc-library-embed-descriptor/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/library-api';
    public const MAX_RESULTS = 50;
    public const DEFAULT_RESULTS = 20;
    public const MAX_SUMMARY = 700;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_library_api_interoperability', array( $this, 'shortcode_console' ) );
        add_shortcode( 'sc_library_embed', array( $this, 'shortcode_embed' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_public_api_response' ), 140, 3 );
        add_filter( 'sc_library_personal_research_environment_state_payload', array( $this, 'filter_personal_environment' ), 40, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'canonical_public_records_reused' => true,
            'legacy_v390_public_api_reused' => true,
            'v480_federation_facade_reused' => true,
            'creates_parallel_public_record_store' => false,
            'creates_parallel_token_store' => false,
            'creates_parallel_federation_registry' => false,
            'public_get_only' => true,
            'raw_post_meta_exposed' => false,
            'private_projects_exposed' => false,
            'personal_library_exposed' => false,
            'notebook_bodies_exposed' => false,
            'matrix_bodies_exposed' => false,
            'research_room_membership_exposed' => false,
            'team_library_membership_exposed' => false,
            'credentials_exposed' => false,
            'authenticated_governance_embeddable' => false,
            'external_embed_requires_allowed_origin' => true,
            'cors_credentials_allowed' => false,
            'wildcard_write_cors' => false,
            'automatic_cross_site_write' => false,
            'automatic_publication' => false,
            'automatic_federation_acceptance' => false,
            'automatic_evidence_promotion' => false,
            'automatic_workspace_write' => false,
        );
    }

    public static function object_profiles() {
        $profiles = array(
            'foundation-document' => array( 'post_type' => 'sc_foundation_doc', 'label' => 'Foundation document' ),
            'publication'         => array( 'post_type' => 'post', 'label' => 'Publication' ),
            'pathway'             => array( 'post_type' => 'sc_knowledge_path', 'label' => 'Knowledge pathway' ),
            'research-source'     => array( 'post_type' => 'sc_research_source', 'label' => 'Research source' ),
            'named-entity'        => array( 'post_type' => 'sc_named_entity', 'label' => 'Named entity' ),
            'concept'             => array( 'post_type' => 'sc_library_concept', 'label' => 'Concept' ),
        );
        return apply_filters( 'sc_library_api_public_object_profiles', $profiles );
    }

    public static function interoperability_profile() {
        return array(
            'schema' => self::MANIFEST_SCHEMA,
            'version' => self::VERSION,
            'api_base' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
            'canonical_library' => class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ),
            'object_schema' => self::OBJECT_SCHEMA,
            'embed_schema' => self::EMBED_SCHEMA,
            'public_object_types' => array_keys( self::object_profiles() ),
            'published_federation_manifests' => class_exists( 'SC_Library_Global_Research_Federation' ),
            'formats' => array( 'application/json', 'application/ld+json' ),
            'writes_supported' => false,
            'credentials_supported' => false,
        );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-api-interoperability-v490', SC_LIBRARY_URL . 'assets/css/sc-library-api-interoperability-v490.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-api-interoperability-v490', SC_LIBRARY_URL . 'assets/js/sc-library-api-interoperability-v490.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_capabilities' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/objects', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_objects' ),
            'args' => array(
                'type' => array( 'sanitize_callback' => 'sanitize_key' ),
                'q' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                'page' => array( 'sanitize_callback' => 'absint' ),
                'per_page' => array( 'sanitize_callback' => 'absint' ),
            ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/objects/(?P<type>[a-z0-9-]+)/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_object' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/manifests/(?P<type>[a-z0-9-]+)/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_manifest' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/federation-manifests', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_federation_manifests' ),
        ) );
    }

    private static function clean_summary( $post ) {
        $value = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
        $value = trim( preg_replace( '/\s+/', ' ', $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_SUMMARY ) : substr( $value, 0, self::MAX_SUMMARY );
    }

    private static function profile_for( $type ) {
        $profiles = self::object_profiles();
        $type = sanitize_key( $type );
        return isset( $profiles[ $type ] ) ? $profiles[ $type ] : null;
    }

    public static function normalize_public_object( $type, $post ) {
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
            return new WP_Error( 'sc_library_api_not_public', __( 'The requested Library object is not publicly available.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $profile = self::profile_for( $type );
        if ( ! $profile || $post->post_type !== $profile['post_type'] ) {
            return new WP_Error( 'sc_library_api_type_mismatch', __( 'The requested Library object type does not match the record.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $permalink = get_permalink( $post );
        $payload = array(
            'schema' => self::OBJECT_SCHEMA,
            'api_version' => self::VERSION,
            'id' => absint( $post->ID ),
            'canonical_id' => 'urn:sc:library-public-object:' . sanitize_key( $type ) . ':' . absint( $post->ID ),
            'type' => sanitize_key( $type ),
            'type_label' => (string) $profile['label'],
            'title' => get_the_title( $post ),
            'summary' => self::clean_summary( $post ),
            'canonical_url' => esc_url_raw( $permalink ),
            'published_at' => get_post_time( 'c', true, $post ),
            'updated_at' => get_post_modified_time( 'c', true, $post ),
            'language' => get_bloginfo( 'language' ),
            'provenance' => array(
                'publisher' => get_bloginfo( 'name' ),
                'library_url' => class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ),
                'source' => 'canonical-wordpress-public-record',
            ),
            'links' => array(
                'self' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/objects/' . sanitize_key( $type ) . '/' . absint( $post->ID ) ) ),
                'manifest' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/manifests/' . sanitize_key( $type ) . '/' . absint( $post->ID ) ) ),
            ),
            'private_fields_included' => false,
            'raw_post_meta_included' => false,
        );
        return apply_filters( 'sc_library_api_public_object_payload', $payload, $type, $post );
    }

    public static function object_manifest( $type, $post ) {
        $object = self::normalize_public_object( $type, $post );
        if ( is_wp_error( $object ) ) { return $object; }
        $canonical = $object;
        ksort( $canonical );
        $sha = hash( 'sha256', wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return array(
            'schema' => self::MANIFEST_SCHEMA,
            'version' => self::VERSION,
            'object' => $object,
            'sha256' => $sha,
            'generated_at' => gmdate( 'c' ),
            'interoperability' => array(
                'read_only' => true,
                'references_only' => true,
                'credentials_included' => false,
                'private_content_included' => false,
            ),
        );
    }

    public function rest_capabilities() {
        return rest_ensure_response( array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'contract' => self::contract(),
            'interoperability' => self::interoperability_profile(),
            'embed' => array(
                'local_shortcode' => '[sc_library_embed type="publication" id="123"]',
                'loader_url' => esc_url_raw( SC_LIBRARY_URL . 'assets/js/sc-library-api-interoperability-v490.js' ),
                'external_requires_allowed_origin' => true,
            ),
        ) );
    }

    public function rest_objects( WP_REST_Request $request ) {
        $type = sanitize_key( (string) $request->get_param( 'type' ) );
        $profile = self::profile_for( $type );
        if ( ! $profile ) { return new WP_Error( 'sc_library_api_unknown_type', __( 'Choose a supported public Library object type.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $per_page = max( 1, min( self::MAX_RESULTS, absint( $request->get_param( 'per_page' ) ?: self::DEFAULT_RESULTS ) ) );
        $page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $query = new WP_Query( array(
            'post_type' => $profile['post_type'], 'post_status' => 'publish', 'posts_per_page' => $per_page,
            'paged' => $page, 's' => sanitize_text_field( (string) $request->get_param( 'q' ) ),
            'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => false,
        ) );
        $items = array();
        foreach ( $query->posts as $post ) {
            $item = self::normalize_public_object( $type, $post );
            if ( ! is_wp_error( $item ) ) { $items[] = $item; }
        }
        return rest_ensure_response( array(
            'schema' => self::SCHEMA, 'version' => self::VERSION, 'type' => $type,
            'page' => $page, 'per_page' => $per_page, 'total' => absint( $query->found_posts ), 'items' => $items,
        ) );
    }

    public function rest_object( WP_REST_Request $request ) {
        $type = sanitize_key( (string) $request['type'] );
        $profile = self::profile_for( $type );
        if ( ! $profile ) { return new WP_Error( 'sc_library_api_unknown_type', __( 'Unsupported Library object type.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $post = get_post( absint( $request['id'] ) );
        $object = self::normalize_public_object( $type, $post );
        return is_wp_error( $object ) ? $object : rest_ensure_response( $object );
    }

    public function rest_manifest( WP_REST_Request $request ) {
        $manifest = self::object_manifest( sanitize_key( (string) $request['type'] ), get_post( absint( $request['id'] ) ) );
        return is_wp_error( $manifest ) ? $manifest : rest_ensure_response( $manifest );
    }

    public function rest_federation_manifests() {
        if ( ! class_exists( 'SC_Library_Global_Research_Federation' ) ) {
            return rest_ensure_response( array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'items' => array() ) );
        }
        $items = array();
        foreach ( SC_Library_Global_Research_Federation::published_manifest_ids() as $id ) {
            $manifest = get_post_meta( absint( $id ), SC_Library_Global_Research_Federation::META_MANIFEST, true );
            if ( is_array( $manifest ) ) { $items[] = $manifest; }
        }
        return rest_ensure_response( array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'items' => array_slice( $items, 0, self::MAX_RESULTS ) ) );
    }

    public static function allowed_origins() {
        $raw = get_option( 'sc_library_api_embed_allowed_origins', array() );
        if ( is_string( $raw ) ) { $raw = preg_split( '/[\r\n,]+/', $raw ); }
        $origins = array();
        foreach ( (array) $raw as $origin ) {
            $origin = untrailingslashit( esc_url_raw( trim( (string) $origin ) ) );
            if ( $origin ) { $origins[] = $origin; }
        }
        $home = wp_parse_url( home_url( '/' ) );
        if ( ! empty( $home['scheme'] ) && ! empty( $home['host'] ) ) {
            $own = $home['scheme'] . '://' . $home['host'] . ( ! empty( $home['port'] ) ? ':' . $home['port'] : '' );
            $origins[] = untrailingslashit( $own );
        }
        return array_values( array_unique( apply_filters( 'sc_library_api_embed_allowed_origins', $origins ) ) );
    }

    public function cors_public_api_response( $response, $server, $request ) {
        $route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
        if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE . self::REST_ROUTE ) || 'GET' !== strtoupper( (string) $request->get_method() ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? untrailingslashit( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) : '';
        if ( ! $origin || ! in_array( $origin, self::allowed_origins(), true ) ) { return $response; }
        if ( method_exists( $response, 'header' ) ) {
            $response->header( 'Access-Control-Allow-Origin', $origin );
            $response->header( 'Vary', 'Origin' );
            $response->header( 'Access-Control-Allow-Credentials', 'false' );
            $response->header( 'Access-Control-Allow-Methods', 'GET' );
        }
        return $response;
    }

    private static function render_embed_card( $type, $id ) {
        $object = self::normalize_public_object( $type, get_post( absint( $id ) ) );
        if ( is_wp_error( $object ) ) { return '<p class="sc-library-embed__empty">' . esc_html__( 'This public Library record is unavailable.', 'sustainable-catalyst-library' ) . '</p>'; }
        ob_start(); ?>
<article class="sc-library-embed__card" data-sc-library-api-embed-card>
<p class="sc-library-embed__type"><?php echo esc_html( (string) $object['type_label'] ); ?></p>
<h4><a href="<?php echo esc_url( (string) $object['canonical_url'] ); ?>"><?php echo esc_html( (string) $object['title'] ); ?></a></h4>
<?php if ( ! empty( $object['summary'] ) ) : ?><p><?php echo esc_html( (string) $object['summary'] ); ?></p><?php endif; ?>
<footer><a href="<?php echo esc_url( (string) $object['canonical_url'] ); ?>"><?php esc_html_e( 'Open in Sustainable Catalyst →', 'sustainable-catalyst-library' ); ?></a><small><?php echo esc_html( (string) $object['updated_at'] ); ?></small></footer>
</article><?php return (string) ob_get_clean();
    }

    public function shortcode_embed( $atts ) {
        $atts = shortcode_atts( array( 'type' => '', 'id' => 0 ), $atts, 'sc_library_embed' );
        wp_enqueue_style( 'sc-library-api-interoperability-v490' );
        return '<div class="sc-library-embed" data-sc-library-embed="v4.9.0">' . self::render_embed_card( sanitize_key( $atts['type'] ), absint( $atts['id'] ) ) . '</div>';
    }

    public function shortcode_console( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Library API, Embeds & Interoperability', 'sustainable-catalyst-library' ) ), $atts, 'sc_library_api_interoperability' );
        wp_enqueue_style( 'sc-library-api-interoperability-v490' );
        wp_enqueue_script( 'sc-library-api-interoperability-v490' );
        $api = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
        ob_start(); ?>
<section class="sc-library-api-console" data-sc-library-api-console="v4.9.0">
<header><p><?php esc_html_e( 'Public integration layer', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Use stable read-only public records, manifests, and embeds without opening private research or governance APIs.', 'sustainable-catalyst-library' ); ?></span></header>
<div class="sc-library-api-console__grid">
<article><strong><?php esc_html_e( 'Public API', 'sustainable-catalyst-library' ); ?></strong><code><?php echo esc_html( $api ); ?></code><a href="<?php echo esc_url( $api ); ?>"><?php esc_html_e( 'View capabilities →', 'sustainable-catalyst-library' ); ?></a></article>
<article><strong><?php esc_html_e( 'Local embed', 'sustainable-catalyst-library' ); ?></strong><code>[sc_library_embed type="publication" id="123"]</code><span><?php esc_html_e( 'Renders only a published canonical record.', 'sustainable-catalyst-library' ); ?></span></article>
<article><strong><?php esc_html_e( 'External embed', 'sustainable-catalyst-library' ); ?></strong><code>&lt;div data-sc-library-api-embed data-type="publication" data-id="123" data-api-base="<?php echo esc_attr( $api ); ?>"&gt;&lt;/div&gt;</code><span><?php esc_html_e( 'The consuming origin must be explicitly allowed. No credentials are sent.', 'sustainable-catalyst-library' ); ?></span></article>
</div>
<p class="sc-library-api-console__boundary"><?php esc_html_e( 'The integration facade never exposes raw post meta, personal research, project bodies, Research Room or Team Library membership, notebook or Evidence Matrix bodies, credentials, or authenticated governance actions.', 'sustainable-catalyst-library' ); ?></p>
</section><?php return (string) ob_get_clean();
    }

    public function filter_personal_environment( $state, $user_id, $project_id ) {
        if ( ! is_array( $state ) ) { return $state; }
        $state['integration'] = array(
            'label' => __( 'API & interoperability', 'sustainable-catalyst-library' ),
            'href' => '#library-api-interoperability',
            'public_facade_only' => true,
            'private_research_exposed' => false,
        );
        return $state;
    }
}
