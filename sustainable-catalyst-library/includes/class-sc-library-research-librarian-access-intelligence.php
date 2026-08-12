<?php
/**
 * Research Librarian Access Intelligence.
 *
 * Interprets Research Access and Source-location evidence into a bounded,
 * human-readable access state without treating availability as entitlement.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Research_Librarian_Access_Intelligence {
    public const VERSION = '4.3.24';
    public const SCHEMA = 'sc-library-research-access-intelligence/1.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_source_access_intelligence', array( $this, 'shortcode_source_access_intelligence' ) );
        add_filter( 'sc_library_research_librarian_access_intelligence', array( __CLASS__, 'filter_access_intelligence' ), 10, 3 );
    }

    public static function state_definitions() {
        return array(
            'open-now' => array(
                'label' => __( 'OPEN NOW', 'sustainable-catalyst-library' ),
                'priority' => 100,
                'open' => true,
                'auth' => false,
                'request' => false,
            ),
            'public-digital' => array(
                'label' => __( 'PUBLIC DIGITAL COLLECTION', 'sustainable-catalyst-library' ),
                'priority' => 92,
                'open' => true,
                'auth' => false,
                'request' => false,
            ),
            'library-access' => array(
                'label' => __( 'LIBRARY MEMBERSHIP REQUIRED', 'sustainable-catalyst-library' ),
                'priority' => 78,
                'open' => false,
                'auth' => true,
                'request' => false,
            ),
            'institution-login' => array(
                'label' => __( 'INSTITUTION LOGIN REQUIRED', 'sustainable-catalyst-library' ),
                'priority' => 76,
                'open' => false,
                'auth' => true,
                'request' => false,
            ),
            'preview' => array(
                'label' => __( 'PREVIEW ONLY', 'sustainable-catalyst-library' ),
                'priority' => 58,
                'open' => false,
                'auth' => false,
                'request' => false,
            ),
            'request-ill' => array(
                'label' => __( 'REQUEST / ILL', 'sustainable-catalyst-library' ),
                'priority' => 48,
                'open' => false,
                'auth' => false,
                'request' => true,
            ),
            'physical' => array(
                'label' => __( 'PHYSICAL COPY', 'sustainable-catalyst-library' ),
                'priority' => 40,
                'open' => false,
                'auth' => false,
                'request' => false,
            ),
            'catalog-check' => array(
                'label' => __( 'CHECK LIBRARY HOLDINGS', 'sustainable-catalyst-library' ),
                'priority' => 32,
                'open' => false,
                'auth' => false,
                'request' => false,
            ),
            'metadata' => array(
                'label' => __( 'METADATA ONLY', 'sustainable-catalyst-library' ),
                'priority' => 16,
                'open' => false,
                'auth' => false,
                'request' => false,
            ),
            'unknown' => array(
                'label' => __( 'ACCESS UNCONFIRMED', 'sustainable-catalyst-library' ),
                'priority' => 5,
                'open' => false,
                'auth' => false,
                'request' => false,
            ),
        );
    }

    public static function filter_access_intelligence( $packet, $subject = array(), $context = array() ) {
        if ( is_array( $packet ) && ! empty( $packet['schema'] ) ) {
            return $packet;
        }
        if ( is_numeric( $subject ) ) {
            return self::evaluate_source( absint( $subject ), $context );
        }
        return self::evaluate_normalized_result( is_array( $subject ) ? $subject : array(), $context );
    }

    public static function evaluate_normalized_result( $result, $context = array() ) {
        $result = is_array( $result ) ? $result : array();
        $routes = array();
        $signals = array();
        $status = sanitize_key( (string) ( $result['full_text_status'] ?? '' ) );

        if ( ! empty( $result['open_access_url'] ) ) {
            $routes[] = self::route(
                ( 'public-digital' === $status ) ? 'public-digital' : 'open-now',
                ( 'public-digital' === $status ) ? __( 'Open digital resource', 'sustainable-catalyst-library' ) : __( 'Open accessible copy', 'sustainable-catalyst-library' ),
                $result['open_access_url'],
                'discovery-result',
                false
            );
            $signals[] = ( 'public-digital' === $status )
                ? __( 'The discovery record identifies a public digital resource.', 'sustainable-catalyst-library' )
                : __( 'The discovery record identifies an open-access URL.', 'sustainable-catalyst-library' );
        }

        if ( ! empty( $result['preview_url'] ) ) {
            $routes[] = self::route( 'preview', __( 'Open preview', 'sustainable-catalyst-library' ), $result['preview_url'], 'discovery-result', false );
            $signals[] = __( 'A preview is available, but preview availability does not establish full-text access.', 'sustainable-catalyst-library' );
        }

        $mapped_status = self::state_from_status( $status );
        if ( $mapped_status && ! in_array( $mapped_status, array( 'open-now', 'public-digital', 'preview' ), true ) ) {
            $signals[] = self::signal_for_state( $mapped_status );
        }

        if ( ! empty( $result['record_url'] ) ) {
            $routes[] = self::route( 'metadata', __( 'Open source record', 'sustainable-catalyst-library' ), $result['record_url'], sanitize_key( (string) ( $result['provider'] ?? 'provider' ) ), false );
        }

        foreach ( (array) ( $result['discovery_links'] ?? array() ) as $link ) {
            if ( empty( $link['url'] ) ) {
                continue;
            }
            $kind = sanitize_key( (string) ( $link['kind'] ?? '' ) );
            $provider = sanitize_key( (string) ( $link['provider'] ?? '' ) );
            $state = in_array( $kind, array( 'library-search', 'openurl' ), true ) || 'worldcat' === $provider ? 'catalog-check' : 'metadata';
            $routes[] = self::route( $state, sanitize_text_field( (string) ( $link['label'] ?? __( 'Open discovery route', 'sustainable-catalyst-library' ) ) ), $link['url'], $provider ?: 'discovery', false );
        }

        if ( $mapped_status && ! self::routes_have_state( $routes, $mapped_status ) ) {
            $routes[] = self::route( $mapped_status, self::state_definitions()[ $mapped_status ]['label'], '', 'status-signal', false );
        }

        return self::build_packet(
            sanitize_text_field( (string) ( $result['title'] ?? __( 'Research source', 'sustainable-catalyst-library' ) ) ),
            $routes,
            $signals,
            array(
                'subject_type' => 'discovery-result',
                'source_id' => 0,
                'checked_at' => current_time( 'mysql' ),
                'fresh' => true,
                'stale_count' => 0,
                'identifiers' => array_filter(
                    array(
                        'doi' => sanitize_text_field( (string) ( $result['doi'] ?? '' ) ),
                        'isbn' => sanitize_text_field( (string) ( $result['isbn'] ?? '' ) ),
                        'pmid' => sanitize_text_field( (string) ( $result['pmid'] ?? '' ) ),
                    )
                ),
            )
        );
    }

    public static function evaluate_source( $source_id, $context = array() ) {
        $source_id = absint( $source_id );
        if ( ! $source_id || ! class_exists( 'SC_Library_Citation_Source_Manager' ) ) {
            return self::empty_packet( __( 'Research source', 'sustainable-catalyst-library' ), 'source-unavailable' );
        }
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, true );
        if ( ! $data ) {
            return self::empty_packet( __( 'Research source', 'sustainable-catalyst-library' ), 'source-not-found' );
        }

        $refresh = ! empty( $context['refresh'] ) && current_user_can( 'edit_post', $source_id );
        if ( $refresh && class_exists( 'SC_Library_Connector_Holdings_Reliability' ) ) {
            $fresh = SC_Library_Connector_Holdings_Reliability::recheck_holdings( $source_id );
            if ( ! is_wp_error( $fresh ) ) {
                $summary = $fresh;
            }
        }
        if ( ! isset( $summary ) ) {
            $summary = class_exists( 'SC_Library_Connector_Holdings_Reliability' )
                ? SC_Library_Connector_Holdings_Reliability::holdings_summary( $source_id, true )
                : array();
        }

        $locations = is_array( $summary['locations'] ?? null ) ? $summary['locations'] : array();
        $routes = array();
        $signals = array();
        foreach ( $locations as $location ) {
            if ( ! is_array( $location ) ) {
                continue;
            }
            $state = self::state_from_location( $location );
            $routes[] = self::route(
                $state,
                sanitize_text_field( (string) ( $location['label'] ?? self::state_definitions()[ $state ]['label'] ) ),
                (string) ( $location['url'] ?? '' ),
                sanitize_key( (string) ( $location['provider'] ?? 'library' ) ),
                ! empty( $location['stale'] )
            );
        }

        $source_status = self::state_from_status( sanitize_key( (string) ( $data['full_text_status'] ?? '' ) ) );
        if ( $source_status ) {
            $signals[] = self::signal_for_state( $source_status );
            if ( ! self::routes_have_state( $routes, $source_status ) ) {
                $fallback_url = in_array( $source_status, array( 'open-now', 'public-digital' ), true )
                    ? (string) ( $data['archive_url'] ?: $data['url'] )
                    : (string) ( $data['url'] ?? '' );
                $routes[] = self::route( $source_status, self::state_definitions()[ $source_status ]['label'], $fallback_url, 'source-metadata', false );
            }
        }

        if ( ! empty( $data['attachment_url'] ) ) {
            $routes[] = self::route( 'open-now', __( 'Open attached source file', 'sustainable-catalyst-library' ), $data['attachment_url'], 'sustainable-catalyst-library', false );
            $signals[] = __( 'A source attachment is stored with this Source record.', 'sustainable-catalyst-library' );
        }
        if ( ! empty( $data['archive_url'] ) ) {
            $routes[] = self::route( 'public-digital', __( 'Open archived source', 'sustainable-catalyst-library' ), $data['archive_url'], 'source-metadata', false );
        }
        if ( ! empty( $data['url'] ) ) {
            $routes[] = self::route( 'metadata', __( 'Open canonical source URL', 'sustainable-catalyst-library' ), $data['url'], 'source-metadata', false );
        }

        $stale_count = absint( $summary['stale'] ?? 0 );
        if ( $stale_count > 0 ) {
            $signals[] = sprintf(
                _n( '%d access route is stale and should be rechecked.', '%d access routes are stale and should be rechecked.', $stale_count, 'sustainable-catalyst-library' ),
                $stale_count
            );
        }

        return self::build_packet(
            sanitize_text_field( (string) ( $data['title'] ?? __( 'Research source', 'sustainable-catalyst-library' ) ) ),
            $routes,
            $signals,
            array(
                'subject_type' => 'source',
                'source_id' => $source_id,
                'checked_at' => sanitize_text_field( (string) ( $summary['last_checked_at'] ?? $data['last_verified'] ?? '' ) ),
                'fresh' => 0 === $stale_count,
                'stale_count' => $stale_count,
                'identifiers' => array_filter(
                    array(
                        'doi' => sanitize_text_field( (string) ( $data['doi'] ?? '' ) ),
                        'isbn' => sanitize_text_field( (string) ( $data['isbn'] ?? '' ) ),
                        'pmid' => sanitize_text_field( (string) ( $data['pmid'] ?? '' ) ),
                    )
                ),
            )
        );
    }

    public static function for_records( $records, $context = array() ) {
        $items = array();
        foreach ( (array) $records as $record ) {
            if ( ! is_array( $record ) || empty( $record['id'] ) ) {
                continue;
            }
            if ( class_exists( 'SC_Library_Citation_Source_Manager' ) && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === (string) ( $record['post_type'] ?? '' ) ) {
                $packet = self::evaluate_source( absint( $record['id'] ), $context );
            } elseif ( ! empty( $record['url'] ) ) {
                $packet = self::build_packet(
                    sanitize_text_field( (string) ( $record['title'] ?? __( 'Library record', 'sustainable-catalyst-library' ) ) ),
                    array( self::route( 'open-now', __( 'Open Sustainable Catalyst record', 'sustainable-catalyst-library' ), $record['url'], 'sustainable-catalyst-library', false ) ),
                    array( __( 'This indexed Sustainable Catalyst record is publicly routed by the Library.', 'sustainable-catalyst-library' ) ),
                    array( 'subject_type' => 'library-record', 'source_id' => absint( $record['id'] ), 'checked_at' => current_time( 'mysql' ), 'fresh' => true, 'stale_count' => 0, 'identifiers' => array() )
                );
            } else {
                continue;
            }
            $packet['record_id'] = absint( $record['id'] );
            $items[] = $packet;
        }
        return array_slice( $items, 0, 8 );
    }

    private static function build_packet( $title, $routes, $signals, $meta ) {
        $routes = self::dedupe_routes( $routes );
        usort(
            $routes,
            static function ( $a, $b ) {
                $a_priority = absint( $a['priority'] ?? 0 ) - ( ! empty( $a['stale'] ) ? 12 : 0 );
                $b_priority = absint( $b['priority'] ?? 0 ) - ( ! empty( $b['stale'] ) ? 12 : 0 );
                return $b_priority <=> $a_priority;
            }
        );
        $best = $routes[0] ?? self::route( 'unknown', __( 'Access has not been confirmed', 'sustainable-catalyst-library' ), '', 'access-intelligence', false );
        $state = sanitize_key( (string) ( $best['state'] ?? 'unknown' ) );
        $definitions = self::state_definitions();
        if ( ! isset( $definitions[ $state ] ) ) {
            $state = 'unknown';
        }
        $definition = $definitions[ $state ];
        $fresh_routes = array_values( array_filter( $routes, static fn( $route ) => empty( $route['stale'] ) ) );
        $has_direct = in_array( $state, array( 'open-now', 'public-digital' ), true );
        $confidence = $has_direct && ! empty( $best['url'] ) && empty( $best['stale'] ) ? 'confirmed-route' : ( $fresh_routes ? 'route-identified' : 'unconfirmed' );

        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'title' => $title,
            'state' => $state,
            'state_label' => $definition['label'],
            'can_open_now' => (bool) $definition['open'],
            'requires_authentication' => (bool) $definition['auth'],
            'requires_request' => (bool) $definition['request'],
            'confidence' => $confidence,
            'availability' => self::availability_statement( $state ),
            'entitlement' => self::entitlement_statement( $state ),
            'best_action' => array(
                'label' => sanitize_text_field( (string) ( $best['label'] ?? '' ) ),
                'url' => esc_url_raw( (string) ( $best['url'] ?? '' ) ),
                'state' => $state,
                'provider' => sanitize_text_field( (string) ( $best['provider'] ?? '' ) ),
                'stale' => ! empty( $best['stale'] ),
            ),
            'routes' => array_slice( $routes, 0, 8 ),
            'evidence' => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $signals ) ) ) ),
            'next_steps' => self::next_steps( $state, $routes ),
            'checked_at' => sanitize_text_field( (string) ( $meta['checked_at'] ?? '' ) ),
            'fresh' => ! empty( $meta['fresh'] ),
            'stale_count' => absint( $meta['stale_count'] ?? 0 ),
            'subject_type' => sanitize_key( (string) ( $meta['subject_type'] ?? 'source' ) ),
            'source_id' => absint( $meta['source_id'] ?? 0 ),
            'identifiers' => is_array( $meta['identifiers'] ?? null ) ? $meta['identifiers'] : array(),
            'boundaries' => array(
                'availability_is_not_entitlement' => true,
                'library_credentials_not_stored' => true,
                'provider_site_is_authoritative_for_access' => true,
                'stale_routes_should_be_rechecked' => true,
            ),
        );
    }

    private static function empty_packet( $title, $reason ) {
        $packet = self::build_packet( $title, array(), array( sanitize_text_field( $reason ) ), array( 'subject_type' => 'source', 'fresh' => false ) );
        return $packet;
    }

    private static function route( $state, $label, $url, $provider, $stale ) {
        $definitions = self::state_definitions();
        $state = isset( $definitions[ $state ] ) ? $state : 'unknown';
        return array(
            'state' => $state,
            'state_label' => $definitions[ $state ]['label'],
            'priority' => absint( $definitions[ $state ]['priority'] ),
            'label' => sanitize_text_field( (string) $label ),
            'url' => esc_url_raw( (string) $url ),
            'provider' => sanitize_text_field( (string) $provider ),
            'stale' => (bool) $stale,
        );
    }

    private static function state_from_status( $status ) {
        $map = array(
            'open-access' => 'open-now',
            'public-digital' => 'public-digital',
            'library-holding' => 'library-access',
            'subscription-access' => 'institution-login',
            'institutional-auth' => 'institution-login',
            'preview-only' => 'preview',
            'interlibrary-loan' => 'request-ill',
            'physical' => 'physical',
            'public-catalog' => 'catalog-check',
            'library-search' => 'catalog-check',
            'holdings-check' => 'catalog-check',
            'metadata-only' => 'metadata',
            'discovery' => 'metadata',
        );
        return $map[ sanitize_key( (string) $status ) ] ?? '';
    }

    private static function state_from_location( $location ) {
        $kind = sanitize_key( (string) ( $location['kind'] ?? '' ) );
        $status = sanitize_key( (string) ( $location['status'] ?? '' ) );
        if ( 'open-access' === $kind || 'open-access' === $status ) {
            return 'open-now';
        }
        if ( 'public-digital' === $status ) {
            return 'public-digital';
        }
        if ( 'library-proxy' === $kind || in_array( $status, array( 'subscription-access', 'institutional-auth' ), true ) ) {
            return 'institution-login';
        }
        if ( in_array( $kind, array( 'library-catalog', 'openurl', 'library-search' ), true ) || in_array( $status, array( 'library-search', 'holdings-check' ), true ) ) {
            return 'catalog-check';
        }
        if ( 'interlibrary-loan' === $kind || 'interlibrary-loan' === $status ) {
            return 'request-ill';
        }
        if ( 'preview' === $kind || 'preview-only' === $status ) {
            return 'preview';
        }
        if ( 'physical' === $kind || 'physical' === $status ) {
            return 'physical';
        }
        if ( 'library-holding' === $status ) {
            return 'library-access';
        }
        return self::state_from_status( $status ) ?: 'metadata';
    }

    private static function routes_have_state( $routes, $state ) {
        foreach ( (array) $routes as $route ) {
            if ( $state === ( $route['state'] ?? '' ) ) {
                return true;
            }
        }
        return false;
    }

    private static function signal_for_state( $state ) {
        $signals = array(
            'open-now' => __( 'An open-access full-text signal is present.', 'sustainable-catalyst-library' ),
            'public-digital' => __( 'A public digital collection signal is present.', 'sustainable-catalyst-library' ),
            'library-access' => __( 'A library-access signal is present; eligibility still needs to be confirmed.', 'sustainable-catalyst-library' ),
            'institution-login' => __( 'An institution or proxy authentication signal is present.', 'sustainable-catalyst-library' ),
            'preview' => __( 'Only preview access is currently identified.', 'sustainable-catalyst-library' ),
            'request-ill' => __( 'An interlibrary-loan request route is available.', 'sustainable-catalyst-library' ),
            'physical' => __( 'A physical-copy signal is present.', 'sustainable-catalyst-library' ),
            'catalog-check' => __( 'A library holdings or catalog check is available, but a holding has not been confirmed here.', 'sustainable-catalyst-library' ),
            'metadata' => __( 'The current record identifies the source but does not establish full-text access.', 'sustainable-catalyst-library' ),
            'unknown' => __( 'Access has not been confirmed.', 'sustainable-catalyst-library' ),
        );
        return $signals[ $state ] ?? $signals['unknown'];
    }

    private static function availability_statement( $state ) {
        $map = array(
            'open-now' => __( 'A direct open-access route is identified.', 'sustainable-catalyst-library' ),
            'public-digital' => __( 'A direct public digital collection route is identified.', 'sustainable-catalyst-library' ),
            'library-access' => __( 'A library-access route is identified, but access depends on membership or local eligibility.', 'sustainable-catalyst-library' ),
            'institution-login' => __( 'A subscription or proxy route is identified and requires authentication.', 'sustainable-catalyst-library' ),
            'preview' => __( 'A preview is identified; full-text availability remains unresolved.', 'sustainable-catalyst-library' ),
            'request-ill' => __( 'A request route is identified; immediate digital access is not confirmed.', 'sustainable-catalyst-library' ),
            'physical' => __( 'A physical-copy signal is identified; digital access is not confirmed.', 'sustainable-catalyst-library' ),
            'catalog-check' => __( 'A catalog or holdings-check route is available; a copy is not yet confirmed.', 'sustainable-catalyst-library' ),
            'metadata' => __( 'The source is identified, but no full-text access route is confirmed.', 'sustainable-catalyst-library' ),
            'unknown' => __( 'No reliable access route is currently identified.', 'sustainable-catalyst-library' ),
        );
        return $map[ $state ] ?? $map['unknown'];
    }

    private static function entitlement_statement( $state ) {
        if ( in_array( $state, array( 'open-now', 'public-digital' ), true ) ) {
            return __( 'The identified route is public and does not require Sustainable Catalyst to hold your library credentials.', 'sustainable-catalyst-library' );
        }
        if ( in_array( $state, array( 'library-access', 'institution-login', 'catalog-check', 'request-ill', 'physical' ), true ) ) {
            return __( 'The provider or library remains authoritative for membership, authentication, borrowing, request, and geographic eligibility.', 'sustainable-catalyst-library' );
        }
        return __( 'Access entitlement is unresolved. Verify rights and availability on the provider or library website.', 'sustainable-catalyst-library' );
    }

    private static function next_steps( $state, $routes ) {
        $steps = array();
        if ( in_array( $state, array( 'open-now', 'public-digital' ), true ) ) {
            $steps[] = __( 'Open the identified copy and verify that its version and rights fit the research use.', 'sustainable-catalyst-library' );
        } elseif ( 'institution-login' === $state ) {
            $steps[] = __( 'Use the institution or library login route if you are eligible.', 'sustainable-catalyst-library' );
            $steps[] = __( 'If authentication fails, check an open-access route or interlibrary loan.', 'sustainable-catalyst-library' );
        } elseif ( 'library-access' === $state || 'catalog-check' === $state ) {
            $steps[] = __( 'Check your library holdings or catalog route.', 'sustainable-catalyst-library' );
            $steps[] = __( 'If no usable copy is available, use a request or interlibrary-loan route.', 'sustainable-catalyst-library' );
        } elseif ( 'request-ill' === $state ) {
            $steps[] = __( 'Submit an interlibrary-loan or document-delivery request through an eligible library.', 'sustainable-catalyst-library' );
        } elseif ( 'preview' === $state ) {
            $steps[] = __( 'Use the preview for identification only, then continue to library or open-access checks for the complete source.', 'sustainable-catalyst-library' );
        } else {
            $steps[] = __( 'Search Research Access and your connected libraries using the title and persistent identifiers.', 'sustainable-catalyst-library' );
        }
        if ( array_filter( (array) $routes, static fn( $route ) => ! empty( $route['stale'] ) ) ) {
            $steps[] = __( 'Recheck stale access routes before relying on availability.', 'sustainable-catalyst-library' );
        }
        return array_values( array_unique( $steps ) );
    }

    private static function dedupe_routes( $routes ) {
        $seen = array();
        $output = array();
        foreach ( (array) $routes as $route ) {
            if ( ! is_array( $route ) ) {
                continue;
            }
            $key = hash( 'sha256', (string) ( $route['state'] ?? '' ) . '|' . (string) ( $route['url'] ?? '' ) . '|' . (string) ( $route['label'] ?? '' ) );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $output[] = $route;
        }
        return $output;
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/access-intelligence/source/(?P<id>\d+)',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'rest_source_access' ),
                    'permission_callback' => array( $this, 'rest_can_read_source' ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array( $this, 'rest_refresh_source_access' ),
                    'permission_callback' => array( $this, 'rest_can_refresh_source' ),
                ),
            )
        );
    }

    public function rest_can_read_source( $request ) {
        $source_id = absint( $request['id'] );
        return 'publish' === get_post_status( $source_id ) || current_user_can( 'edit_post', $source_id );
    }

    public function rest_can_refresh_source( $request ) {
        return current_user_can( 'edit_post', absint( $request['id'] ) );
    }

    public function rest_source_access( $request ) {
        return rest_ensure_response( self::evaluate_source( absint( $request['id'] ) ) );
    }

    public function rest_refresh_source_access( $request ) {
        return rest_ensure_response( self::evaluate_source( absint( $request['id'] ), array( 'refresh' => true ) ) );
    }

    public function shortcode_source_access_intelligence( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sc_source_access_intelligence' );
        $source_id = absint( $atts['id'] ?: get_the_ID() );
        if ( ! $source_id || ! class_exists( 'SC_Library_Citation_Source_Manager' ) || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            return '';
        }
        if ( 'publish' !== get_post_status( $source_id ) && ! current_user_can( 'edit_post', $source_id ) ) {
            return '';
        }
        $packet = self::evaluate_source( $source_id );
        $best = $packet['best_action'] ?? array();
        ob_start();
        ?>
        <aside class="sc-source-access-intelligence" data-sc-access-state="<?php echo esc_attr( $packet['state'] ); ?>">
            <p class="sc-source-access-intelligence__kicker"><?php esc_html_e( 'Research Librarian Access Intelligence', 'sustainable-catalyst-library' ); ?></p>
            <h3><?php echo esc_html( $packet['state_label'] ); ?></h3>
            <p><?php echo esc_html( $packet['availability'] ); ?></p>
            <p><small><?php echo esc_html( $packet['entitlement'] ); ?></small></p>
            <?php if ( ! empty( $best['url'] ) ) : ?>
                <p><a class="button" href="<?php echo esc_url( $best['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $best['label'] ?: __( 'Open access route', 'sustainable-catalyst-library' ) ); ?></a></p>
            <?php endif; ?>
        </aside>
        <?php
        return (string) ob_get_clean();
    }
}
