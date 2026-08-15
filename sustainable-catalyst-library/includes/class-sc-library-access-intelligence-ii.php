<?php
/**
 * Access Intelligence II — v4.3.35.
 *
 * Adds transparent access-path ranking, confidence/freshness diagnostics,
 * connected-library fit, and fallback sequencing above the bounded v4.3.24
 * Access Intelligence classifier. It never treats catalog search, membership,
 * or a holdings signal as proof that the current user is entitled to access.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Access_Intelligence_II {
    public const VERSION = '4.3.35';
    public const SCHEMA = 'sc-library-access-intelligence-ii/1.0';
    public const REST_ROUTE = '/access-intelligence-v2';
    public const MY_LIBRARIES_META = 'sc_library_my_libraries_v4319';
    public const MAX_PATHS = 12;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_access_intelligence_ii', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_access_intelligence_ii', array( __CLASS__, 'filter_plan' ), 10, 3 );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-access-intelligence-ii-v4335', SC_LIBRARY_URL . 'assets/css/sc-library-access-intelligence-ii-v4335.css', array(), self::VERSION );
        wp_register_script( 'sc-library-access-intelligence-ii-v4335', SC_LIBRARY_URL . 'assets/js/sc-library-access-intelligence-ii-v4335.js', array(), self::VERSION, true );
    }

    public static function filter_plan( $plan, $subject = array(), $context = array() ) {
        if ( is_array( $plan ) && self::SCHEMA === ( $plan['schema'] ?? '' ) ) { return $plan; }
        return self::plan( is_array( $subject ) ? $subject : array(), is_array( $context ) ? $context : array() );
    }

    public static function plan( $subject = array(), $context = array() ) {
        $subject = is_array( $subject ) ? $subject : array();
        $context = is_array( $context ) ? $context : array();
        $source_id = absint( $subject['source_id'] ?? 0 );
        $query = self::subject_query( $subject );

        if ( $source_id && class_exists( 'SC_Library_Research_Librarian_Access_Intelligence' ) ) {
            $base = SC_Library_Research_Librarian_Access_Intelligence::evaluate_source( $source_id, array( 'refresh' => ! empty( $context['refresh'] ) ) );
            if ( ! $query ) { $query = trim( (string) ( $base['title'] ?? '' ) ); }
        } elseif ( class_exists( 'SC_Library_Research_Librarian_Access_Intelligence' ) ) {
            $normalized = array(
                'title' => sanitize_text_field( (string) ( $subject['title'] ?? $query ?: __( 'Research source', 'sustainable-catalyst-library' ) ) ),
                'doi' => sanitize_text_field( (string) ( $subject['doi'] ?? '' ) ),
                'isbn' => sanitize_text_field( (string) ( $subject['isbn'] ?? '' ) ),
                'pmid' => sanitize_text_field( (string) ( $subject['pmid'] ?? '' ) ),
                'record_url' => esc_url_raw( (string) ( $subject['record_url'] ?? '' ) ),
                'open_access_url' => esc_url_raw( (string) ( $subject['open_access_url'] ?? '' ) ),
                'preview_url' => esc_url_raw( (string) ( $subject['preview_url'] ?? '' ) ),
                'full_text_status' => sanitize_key( (string) ( $subject['full_text_status'] ?? '' ) ),
                'provider' => sanitize_key( (string) ( $subject['provider'] ?? '' ) ),
                'discovery_links' => is_array( $subject['discovery_links'] ?? null ) ? $subject['discovery_links'] : array(),
            );
            $base = SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result( $normalized, $context );
        } else {
            $base = self::empty_base_packet( $query );
        }

        $paths = self::paths_from_base_packet( $base );
        $connected = self::connected_library_paths( $query, $subject );
        $paths = array_merge( $paths, $connected['paths'], self::public_fallback_paths( $query, $subject ) );
        $paths = self::rank_paths( $paths );
        $best = $paths[0] ?? self::empty_path();
        $confidence = self::plan_confidence( $best, $base );
        $unresolved = self::unresolved_questions( $best, $base, $connected );

        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'query' => $query,
            'source_id' => $source_id,
            'base_access_schema' => sanitize_text_field( (string) ( $base['schema'] ?? '' ) ),
            'base_access_version' => sanitize_text_field( (string) ( $base['version'] ?? '' ) ),
            'state' => sanitize_key( (string) ( $base['state'] ?? 'unknown' ) ),
            'state_label' => sanitize_text_field( (string) ( $base['state_label'] ?? __( 'ACCESS UNCONFIRMED', 'sustainable-catalyst-library' ) ) ),
            'availability' => sanitize_text_field( (string) ( $base['availability'] ?? __( 'No reliable access route is currently identified.', 'sustainable-catalyst-library' ) ) ),
            'entitlement' => sanitize_text_field( (string) ( $base['entitlement'] ?? __( 'Access entitlement is unresolved.', 'sustainable-catalyst-library' ) ) ),
            'confidence' => $confidence,
            'best_path' => $best,
            'paths' => array_slice( $paths, 0, self::MAX_PATHS ),
            'fallback_sequence' => array_values( array_map( static fn( $path ) => (string) ( $path['path_id'] ?? '' ), array_slice( $paths, 0, 6 ) ) ),
            'connected_libraries_considered' => absint( $connected['count'] ?? 0 ),
            'unresolved_questions' => $unresolved,
            'checked_at' => sanitize_text_field( (string) ( $base['checked_at'] ?? current_time( 'mysql' ) ) ),
            'boundaries' => array(
                'availability_is_not_entitlement' => true,
                'catalog_search_is_not_a_holding' => true,
                'holding_is_not_user_eligibility' => true,
                'connected_library_is_user_declared_relationship' => true,
                'external_library_credentials_stored' => false,
                'provider_and_library_sites_remain_authoritative' => true,
                'automatic_access_claim' => false,
                'automatic_subscription_claim' => false,
            ),
        );
    }

    private static function subject_query( $subject ) {
        foreach ( array( 'query', 'title', 'doi', 'isbn', 'pmid' ) as $key ) {
            $value = trim( sanitize_text_field( (string) ( $subject[ $key ] ?? '' ) ) );
            if ( $value ) { return $value; }
        }
        return '';
    }

    private static function empty_base_packet( $query ) {
        return array(
            'schema' => 'sc-library-research-access-intelligence/1.0',
            'version' => '4.3.24',
            'title' => $query ?: __( 'Research source', 'sustainable-catalyst-library' ),
            'state' => 'unknown',
            'state_label' => __( 'ACCESS UNCONFIRMED', 'sustainable-catalyst-library' ),
            'availability' => __( 'No reliable access route is currently identified.', 'sustainable-catalyst-library' ),
            'entitlement' => __( 'Access entitlement is unresolved. Verify rights and availability on the provider or library website.', 'sustainable-catalyst-library' ),
            'routes' => array(), 'checked_at' => '', 'fresh' => false, 'stale_count' => 0,
        );
    }

    private static function paths_from_base_packet( $base ) {
        $paths = array();
        foreach ( (array) ( $base['routes'] ?? array() ) as $route ) {
            if ( ! is_array( $route ) ) { continue; }
            $state = sanitize_key( (string) ( $route['state'] ?? 'unknown' ) );
            $paths[] = self::make_path(
                $state,
                (string) ( $route['label'] ?? '' ),
                (string) ( $route['url'] ?? '' ),
                (string) ( $route['provider'] ?? 'access-intelligence' ),
                ! empty( $route['stale'] ),
                'access-evidence',
                '',
                absint( $route['priority'] ?? 0 )
            );
        }
        return $paths;
    }

    private static function connected_library_paths( $query, $subject ) {
        if ( ! is_user_logged_in() ) { return array( 'count' => 0, 'paths' => array() ); }
        $stored = get_user_meta( get_current_user_id(), self::MY_LIBRARIES_META, true );
        $stored = is_array( $stored ) ? $stored : array();
        $public = class_exists( 'SC_Library_Public_Library_Network' ) ? SC_Library_Public_Library_Network::registry() : array();
        $institutions = class_exists( 'SC_Library_Institutional_Connector_Expansion' ) ? SC_Library_Institutional_Connector_Expansion::registry() : array();
        $paths = array(); $count = 0;
        foreach ( array_slice( $stored, -20 ) as $item ) {
            if ( ! is_array( $item ) ) { continue; }
            $id = sanitize_key( (string) ( $item['id'] ?? '' ) );
            if ( ! $id ) { continue; }
            $relation = in_array( (string) ( $item['relation'] ?? '' ), array( 'member', 'research' ), true ) ? (string) $item['relation'] : 'research';
            $library = array(); $url = ''; $ill_url = '';
            if ( isset( $public[ $id ] ) ) {
                $library = $public[ $id ]; $url = SC_Library_Public_Library_Network::resolve_search_url( $id, $query ); $ill_url = (string) ( $library['ill_url'] ?? '' );
            } elseif ( isset( $institutions[ $id ] ) ) {
                $library = $institutions[ $id ]; $url = SC_Library_Institutional_Connector_Expansion::resolve_search_url( $id, $query ); $ill_url = (string) ( $library['ill_url'] ?? '' );
            } elseif ( 0 === strpos( $id, 'custom-' ) ) {
                $library = array( 'name' => sanitize_text_field( (string) ( $item['name'] ?? __( 'Connected library', 'sustainable-catalyst-library' ) ) ) );
                $url = self::resolve_custom_template( (string) ( $item['catalog_template'] ?? '' ), $query, $subject );
                if ( ! $url ) { $url = esc_url_raw( (string) ( $item['homepage'] ?? '' ) ); }
                $ill_url = esc_url_raw( (string) ( $item['ill_url'] ?? '' ) );
            }
            if ( ! $library ) { continue; }
            $count++;
            $name = sanitize_text_field( (string) ( $library['name'] ?? $library['region'] ?? $id ) );
            if ( $url ) {
                $paths[] = self::make_path( 'catalog-check', sprintf( __( 'Search %s', 'sustainable-catalyst-library' ), $name ), $url, 'my-library:' . $id, false, 'catalog-search-only', $relation, 32 );
            }
            if ( $ill_url ) {
                $paths[] = self::make_path( 'request-ill', sprintf( __( 'Request through %s', 'sustainable-catalyst-library' ), $name ), $ill_url, 'my-library:' . $id, false, 'request-route', $relation, 48 );
            }
        }
        return array( 'count' => $count, 'paths' => $paths );
    }

    private static function resolve_custom_template( $template, $query, $subject ) {
        $template = trim( (string) $template );
        if ( ! $template ) { return ''; }
        $tokens = array(
            '{query}' => rawurlencode( $query ),
            '{title}' => rawurlencode( (string) ( $subject['title'] ?? $query ) ),
            '{doi}' => rawurlencode( (string) ( $subject['doi'] ?? '' ) ),
            '{isbn}' => rawurlencode( (string) ( $subject['isbn'] ?? '' ) ),
            '{pmid}' => rawurlencode( (string) ( $subject['pmid'] ?? '' ) ),
            '{author}' => rawurlencode( (string) ( $subject['author'] ?? '' ) ),
        );
        return esc_url_raw( strtr( $template, $tokens ) );
    }

    private static function public_fallback_paths( $query, $subject ) {
        if ( ! $query ) { return array(); }
        $paths = array();
        if ( class_exists( 'SC_Library_Public_Library_Network' ) ) {
            $worldcat = SC_Library_Public_Library_Network::resolve_search_url( 'worldcat', $query );
            if ( $worldcat ) { $paths[] = self::make_path( 'catalog-check', __( 'Check worldwide holdings in WorldCat', 'sustainable-catalyst-library' ), $worldcat, 'worldcat', false, 'global-holdings-search', '', 32 ); }
            $loc = SC_Library_Public_Library_Network::resolve_search_url( 'loc', $query );
            if ( $loc ) { $paths[] = self::make_path( 'catalog-check', __( 'Search Library of Congress', 'sustainable-catalyst-library' ), $loc, 'library-of-congress', false, 'public-catalog-search', '', 32 ); }
        }
        return $paths;
    }

    private static function make_path( $state, $label, $url, $provider, $stale, $evidence_grade, $user_relation = '', $base_priority = 0 ) {
        $state = sanitize_key( (string) $state ) ?: 'unknown';
        $provider = sanitize_text_field( (string) $provider );
        $url = esc_url_raw( (string) $url );
        $path_id = 'path-' . substr( hash( 'sha256', $state . '|' . $provider . '|' . $url . '|' . (string) $label ), 0, 16 );
        return array(
            'path_id' => $path_id,
            'state' => $state,
            'label' => sanitize_text_field( (string) $label ),
            'url' => $url,
            'provider' => $provider,
            'stale' => (bool) $stale,
            'evidence_grade' => sanitize_key( (string) $evidence_grade ),
            'user_relation' => sanitize_key( (string) $user_relation ),
            'base_priority' => absint( $base_priority ),
            'score' => 0,
            'rank_reasons' => array(),
            'entitlement_class' => self::entitlement_class_for_state( $state ),
        );
    }

    public static function rank_paths( $paths ) {
        $seen = array(); $ranked = array();
        foreach ( (array) $paths as $path ) {
            if ( ! is_array( $path ) ) { continue; }
            $key = hash( 'sha256', (string) ( $path['state'] ?? '' ) . '|' . (string) ( $path['url'] ?? '' ) . '|' . (string) ( $path['provider'] ?? '' ) );
            if ( isset( $seen[ $key ] ) ) { continue; }
            $seen[ $key ] = true;
            $score = absint( $path['base_priority'] ?? 0 ); $reasons = array();
            $state = sanitize_key( (string) ( $path['state'] ?? 'unknown' ) );
            $grade = sanitize_key( (string) ( $path['evidence_grade'] ?? '' ) );
            $relation = sanitize_key( (string) ( $path['user_relation'] ?? '' ) );
            if ( in_array( $state, array( 'open-now', 'public-digital' ), true ) ) { $score += 28; $reasons[] = 'direct-public-route'; }
            if ( ! empty( $path['url'] ) ) { $score += 8; $reasons[] = 'actionable-url'; }
            if ( empty( $path['stale'] ) ) { $score += 7; $reasons[] = 'not-marked-stale'; } else { $score -= 22; $reasons[] = 'stale-route-penalty'; }
            if ( 'member' === $relation ) { $score += 18; $reasons[] = 'connected-library-membership'; }
            elseif ( 'research' === $relation ) { $score += 10; $reasons[] = 'connected-research-library'; }
            if ( 'access-evidence' === $grade ) { $score += 10; $reasons[] = 'existing-access-evidence'; }
            if ( in_array( $grade, array( 'catalog-search-only', 'global-holdings-search', 'public-catalog-search' ), true ) ) { $score -= 8; $reasons[] = 'search-does-not-confirm-holding'; }
            if ( 'metadata' === $state ) { $score -= 18; $reasons[] = 'metadata-only-penalty'; }
            $path['score'] = max( 0, min( 150, $score ) );
            $path['rank_reasons'] = $reasons;
            $path['confidence'] = self::path_confidence( $path );
            $ranked[] = $path;
        }
        usort( $ranked, static function ( $a, $b ) {
            $score = absint( $b['score'] ?? 0 ) <=> absint( $a['score'] ?? 0 );
            return 0 !== $score ? $score : strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
        } );
        foreach ( $ranked as $index => &$path ) { $path['rank'] = $index + 1; }
        unset( $path );
        return $ranked;
    }

    private static function path_confidence( $path ) {
        if ( ! empty( $path['stale'] ) ) { return 'stale-route'; }
        $state = sanitize_key( (string) ( $path['state'] ?? '' ) );
        $grade = sanitize_key( (string) ( $path['evidence_grade'] ?? '' ) );
        if ( in_array( $state, array( 'open-now', 'public-digital' ), true ) && ! empty( $path['url'] ) ) { return 'direct-route-identified'; }
        if ( 'access-evidence' === $grade ) { return 'provider-route-identified'; }
        if ( ! empty( $path['user_relation'] ) ) { return 'connected-library-search-path'; }
        if ( in_array( $grade, array( 'global-holdings-search', 'public-catalog-search' ), true ) ) { return 'discovery-fallback'; }
        return 'unconfirmed';
    }

    private static function plan_confidence( $best, $base ) {
        $path_confidence = sanitize_key( (string) ( $best['confidence'] ?? 'unconfirmed' ) );
        $map = array(
            'direct-route-identified' => array( 'level' => 'high', 'basis' => __( 'A direct, non-stale public route is identified. The provider still remains authoritative for current availability and rights.', 'sustainable-catalyst-library' ) ),
            'provider-route-identified' => array( 'level' => 'moderate', 'basis' => __( 'A provider or holdings route is identified. Authentication, borrowing, or current eligibility still requires provider confirmation.', 'sustainable-catalyst-library' ) ),
            'connected-library-search-path' => array( 'level' => 'limited', 'basis' => __( 'A user-connected library can be searched, but the search route does not confirm a holding or entitlement.', 'sustainable-catalyst-library' ) ),
            'discovery-fallback' => array( 'level' => 'limited', 'basis' => __( 'A public discovery fallback is available, but it does not confirm a usable copy.', 'sustainable-catalyst-library' ) ),
            'stale-route' => array( 'level' => 'low', 'basis' => __( 'The leading access route is stale and should be rechecked before relying on it.', 'sustainable-catalyst-library' ) ),
            'unconfirmed' => array( 'level' => 'unknown', 'basis' => __( 'No reliable access route is currently confirmed.', 'sustainable-catalyst-library' ) ),
        );
        $out = $map[ $path_confidence ] ?? $map['unconfirmed'];
        $out['path_confidence'] = $path_confidence;
        $out['base_confidence'] = sanitize_key( (string) ( $base['confidence'] ?? 'unconfirmed' ) );
        return $out;
    }

    private static function entitlement_class_for_state( $state ) {
        if ( in_array( $state, array( 'open-now', 'public-digital' ), true ) ) { return 'public-route'; }
        if ( 'library-access' === $state || 'catalog-check' === $state || 'physical' === $state ) { return 'library-eligibility-required'; }
        if ( 'institution-login' === $state ) { return 'institution-authentication-required'; }
        if ( 'request-ill' === $state ) { return 'request-eligibility-required'; }
        if ( 'preview' === $state ) { return 'preview-not-full-access'; }
        return 'unresolved';
    }

    private static function unresolved_questions( $best, $base, $connected ) {
        $questions = array();
        $state = sanitize_key( (string) ( $best['state'] ?? $base['state'] ?? 'unknown' ) );
        if ( in_array( $state, array( 'library-access', 'catalog-check', 'physical', 'request-ill' ), true ) ) { $questions[] = __( 'Does an eligible library actually hold or license a usable copy for you right now?', 'sustainable-catalyst-library' ); }
        if ( 'institution-login' === $state ) { $questions[] = __( 'Does your current institutional affiliation include this provider or title?', 'sustainable-catalyst-library' ); }
        if ( ! empty( $best['stale'] ) || ! empty( $base['stale_count'] ) ) { $questions[] = __( 'Has the provider or holding changed since the last check?', 'sustainable-catalyst-library' ); }
        if ( empty( $best['url'] ) ) { $questions[] = __( 'Which provider or library can supply a legitimate access route?', 'sustainable-catalyst-library' ); }
        if ( 0 === absint( $connected['count'] ?? 0 ) && ! in_array( $state, array( 'open-now', 'public-digital' ), true ) ) { $questions[] = __( 'Would connecting a library you already belong to create a more relevant catalog or request path?', 'sustainable-catalyst-library' ); }
        return array_values( array_unique( $questions ) );
    }

    private static function empty_path() {
        return array( 'path_id' => '', 'rank' => 0, 'state' => 'unknown', 'label' => __( 'Access unconfirmed', 'sustainable-catalyst-library' ), 'url' => '', 'provider' => '', 'stale' => false, 'evidence_grade' => 'none', 'user_relation' => '', 'score' => 0, 'rank_reasons' => array(), 'confidence' => 'unconfirmed', 'entitlement_class' => 'unresolved' );
    }

    public function register_rest_routes() {
        register_rest_route( 'sc-library/v1', self::REST_ROUTE, array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_plan' ),
        ) );
        register_rest_route( 'sc-library/v1', self::REST_ROUTE . '/source/(?P<id>\d+)', array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_can_read_source' ), 'callback' => array( $this, 'rest_source' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_can_refresh_source' ), 'callback' => array( $this, 'rest_refresh_source' ) ),
        ) );
    }

    public function rest_plan( WP_REST_Request $request ) {
        return rest_ensure_response( self::plan( array(
            'query' => sanitize_text_field( (string) $request->get_param( 'q' ) ),
            'title' => sanitize_text_field( (string) $request->get_param( 'title' ) ),
            'doi' => sanitize_text_field( (string) $request->get_param( 'doi' ) ),
            'isbn' => sanitize_text_field( (string) $request->get_param( 'isbn' ) ),
            'pmid' => sanitize_text_field( (string) $request->get_param( 'pmid' ) ),
        ) ) );
    }
    public function rest_can_read_source( $request ) { $id = absint( $request['id'] ); return 'publish' === get_post_status( $id ) || current_user_can( 'edit_post', $id ); }
    public function rest_can_refresh_source( $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); }
    public function rest_source( $request ) { return rest_ensure_response( self::plan( array( 'source_id' => absint( $request['id'] ) ) ) ); }
    public function rest_refresh_source( $request ) { return rest_ensure_response( self::plan( array( 'source_id' => absint( $request['id'] ) ), array( 'refresh' => true ) ) ); }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => 'Access Intelligence II', 'source_id' => 0 ), $atts, 'sc_access_intelligence_ii' );
        wp_enqueue_style( 'sc-library-access-intelligence-ii-v4335' );
        wp_enqueue_script( 'sc-library-access-intelligence-ii-v4335' );
        wp_localize_script( 'sc-library-access-intelligence-ii-v4335', 'SCAccessIntelligenceII', array(
            'restUrl' => esc_url_raw( rest_url( 'sc-library/v1/access-intelligence-v2' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'signedIn' => is_user_logged_in(),
        ) );
        $source_id = absint( $atts['source_id'] );
        $query = isset( $_GET['sc_access_query'] ) ? sanitize_text_field( wp_unslash( $_GET['sc_access_query'] ) ) : '';
        $plan = $source_id ? self::plan( array( 'source_id' => $source_id ) ) : ( $query ? self::plan( array( 'query' => $query, 'title' => $query ) ) : array() );
        ob_start(); ?>
        <section class="sc-access-ii" data-sc-access-intelligence-ii="v4.3.35">
          <header class="sc-access-ii__header"><p class="sc-access-ii__kicker"><?php esc_html_e( 'Research Access', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><p><?php esc_html_e( 'Rank legitimate access paths without confusing discovery, holdings, membership, or authentication with entitlement. Connected libraries can improve path relevance, but Sustainable Catalyst does not store their passwords or claim that a catalog search proves access.', 'sustainable-catalyst-library' ); ?></p></header>
          <?php if ( ! $source_id ) : ?><form class="sc-access-ii__form" method="get" data-sc-access-ii-form><label><span><?php esc_html_e( 'Title, DOI, ISBN, PMID, or research query', 'sustainable-catalyst-library' ); ?></span><input type="search" name="sc_access_query" value="<?php echo esc_attr( $query ); ?>" required></label><button type="submit"><?php esc_html_e( 'Plan access routes', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?>
          <div class="sc-access-ii__status" data-sc-access-ii-status aria-live="polite"><?php echo $plan ? wp_kses_post( self::render_plan( $plan ) ) : '<p>' . esc_html__( 'Enter a source or identifier to rank direct, connected-library, catalog, request, and fallback routes.', 'sustainable-catalyst-library' ) . '</p>'; ?></div>
          <p class="sc-access-ii__boundary"><?php esc_html_e( 'Availability is not entitlement. A holdings or catalog signal is not proof of user eligibility. Provider and library sites remain authoritative for current access, rights, authentication, borrowing, and request conditions.', 'sustainable-catalyst-library' ); ?></p>
        </section><?php
        return (string) ob_get_clean();
    }

    private static function render_plan( $plan ) {
        $confidence = is_array( $plan['confidence'] ?? null ) ? $plan['confidence'] : array();
        $paths = is_array( $plan['paths'] ?? null ) ? $plan['paths'] : array();
        ob_start(); ?>
        <div class="sc-access-ii__summary"><strong><?php echo esc_html( (string) ( $plan['state_label'] ?? 'ACCESS UNCONFIRMED' ) ); ?></strong><span><?php echo esc_html( ucfirst( (string) ( $confidence['level'] ?? 'unknown' ) ) . ' confidence' ); ?></span><p><?php echo esc_html( (string) ( $confidence['basis'] ?? '' ) ); ?></p></div>
        <?php if ( $paths ) : ?><ol class="sc-access-ii__paths"><?php foreach ( $paths as $path ) : ?><li><div><small><?php echo esc_html( '#' . absint( $path['rank'] ?? 0 ) . ' · ' . (string) ( $path['confidence'] ?? 'unconfirmed' ) ); ?></small><strong><?php echo esc_html( (string) ( $path['label'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $path['entitlement_class'] ?? 'unresolved' ) ); ?></span></div><?php if ( ! empty( $path['url'] ) ) : ?><a href="<?php echo esc_url( $path['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open route →', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></li><?php endforeach; ?></ol><?php endif;
        return (string) ob_get_clean();
    }
}
