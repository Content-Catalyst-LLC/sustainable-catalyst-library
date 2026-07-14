<?php
/**
 * Connector and Holdings Reliability.
 *
 * Provider health, retry/backoff, stale-cache recovery, holdings freshness,
 * profile validation, import idempotency, metadata conflicts, maintenance,
 * diagnostics, and recovery APIs.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Connector_Holdings_Reliability {
    public const VERSION = '2.6.1';
    public const HEALTH_SCHEMA = 'sc-library-connector-health/1.0';
    public const HOLDINGS_SCHEMA = 'sc-library-holdings-reliability/1.0';
    public const CONFLICT_SCHEMA = 'sc-library-connector-conflict/1.0';
    public const PROFILE_SCHEMA = 'sc-library-profile-validation/1.0';

    public const OPTION_HEALTH = 'sc_library_connector_health_v261';
    public const OPTION_HTTP_VALIDATORS = 'sc_library_connector_http_validators_v261';
    public const OPTION_MIGRATION_CURSOR = 'sc_library_connector_reliability_migration_cursor_v261';
    public const OPTION_MIGRATION_DONE = 'sc_library_connector_reliability_migration_done_v261';
    public const OPTION_CACHE_INDEX = 'sc_library_connector_cache_index_v261';

    public const META_CONFLICTS = '_sc_source_connector_conflicts';
    public const META_HOLDINGS_SUMMARY = '_sc_source_holdings_reliability';
    public const META_PROFILE_VALIDATION = '_sc_library_profile_validation';
    public const META_PROFILE_VALIDATED_AT = '_sc_library_profile_validated_at';

    public const CRON_HOOK = 'sc_library_connector_reliability_maintenance';
    public const CACHE_PREFIX = 'sc_lib_v261_';
    public const CIRCUIT_THRESHOLD = 3;
    public const CIRCUIT_COOLDOWN = 10 * MINUTE_IN_SECONDS;
    public const STALE_CACHE_TTL = 7 * DAY_IN_SECONDS;
    public const HTTP_BODY_TTL = 7 * DAY_IN_SECONDS;
    public const MAX_CONFLICTS = 100;
    public const MAX_HEALTH_EVENTS = 25;

    private static $field_map = array(
        'authors'           => SC_Library_Citation_Source_Manager::META_AUTHORS,
        'organization'      => SC_Library_Citation_Source_Manager::META_ORGANIZATION,
        'editors'           => SC_Library_Citation_Source_Manager::META_EDITORS,
        'year'              => SC_Library_Citation_Source_Manager::META_YEAR,
        'publication_date'  => SC_Library_Citation_Source_Manager::META_PUBLICATION_DATE,
        'container_title'   => SC_Library_Citation_Source_Manager::META_CONTAINER_TITLE,
        'publisher'         => SC_Library_Citation_Source_Manager::META_PUBLISHER,
        'place'             => SC_Library_Citation_Source_Manager::META_PLACE,
        'edition'           => SC_Library_Citation_Source_Manager::META_EDITION,
        'volume'            => SC_Library_Citation_Source_Manager::META_VOLUME,
        'issue'             => SC_Library_Citation_Source_Manager::META_ISSUE,
        'pages'             => SC_Library_Citation_Source_Manager::META_PAGES,
        'chapter'           => SC_Library_Citation_Source_Manager::META_CHAPTER,
        'report_number'     => SC_Library_Citation_Source_Manager::META_REPORT_NUMBER,
        'standard_number'   => SC_Library_Citation_Source_Manager::META_STANDARD_NUMBER,
        'jurisdiction'      => SC_Library_Citation_Source_Manager::META_JURISDICTION,
        'doi'               => SC_Library_Citation_Source_Manager::META_DOI,
        'isbn'              => SC_Library_Citation_Source_Manager::META_ISBN,
        'pmid'              => SC_Library_Citation_Source_Manager::META_PMID,
        'url'               => SC_Library_Citation_Source_Manager::META_URL,
        'archive_url'       => SC_Library_Citation_Source_Manager::META_ARCHIVE_URL,
        'language'          => SC_Library_Citation_Source_Manager::META_LANGUAGE,
        'full_text_status'  => SC_Library_Citation_Source_Manager::META_FULL_TEXT_STATUS,
        'title'             => '__post_title',
        'abstract'          => '__post_excerpt',
    );

    public function __construct() {
        add_filter( 'sc_library_discovery_providers', array( $this, 'augment_provider_registry' ), 40, 2 );
        add_action( 'init', array( $this, 'ensure_schedule' ), 998 );
        add_action( 'init', array( $this, 'maybe_migrate_locations' ), 997 );
        add_action( self::CRON_HOOK, array( $this, 'maintenance_run' ) );

        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 75 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 75 );
        add_action( 'admin_notices', array( $this, 'profile_validation_notice' ), 220 );

        add_action( 'wp_ajax_sc_library_v261_reset_provider', array( $this, 'ajax_reset_provider' ) );
        add_action( 'wp_ajax_sc_library_v261_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_sc_library_v261_recheck_holdings', array( $this, 'ajax_recheck_holdings' ) );
        add_action( 'wp_ajax_sc_library_v261_resolve_conflict', array( $this, 'ajax_resolve_conflict' ) );
        add_action( 'wp_ajax_sc_library_v261_validate_profile', array( $this, 'ajax_validate_profile' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function augment_provider_registry( $providers, $settings ) {
        $health = self::health_registry();
        foreach ( $providers as $provider_id => &$provider ) {
            $state = $health[ $provider_id ] ?? self::empty_health_state( $provider_id );
            $provider['health_status'] = $state['status'];
            $provider['last_success'] = $state['last_success'];
            $provider['last_failure'] = $state['last_failure'];
            $provider['cooldown_until'] = $state['cooldown_until'];
            $provider['average_latency_ms'] = $state['average_latency_ms'];
            $provider['consecutive_failures'] = $state['consecutive_failures'];
        }
        unset( $provider );
        return $providers;
    }

    public function ensure_schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
        }
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-connector-conflicts',
            __( 'Connector Metadata Conflicts', 'sustainable-catalyst-library' ),
            array( $this, 'render_conflicts_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-holdings-reliability',
            __( 'Holdings Reliability', 'sustainable-catalyst-library' ),
            array( $this, 'render_holdings_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'side',
            'default'
        );
        add_meta_box(
            'sc-library-profile-validation',
            __( 'Profile Validation', 'sustainable-catalyst-library' ),
            array( $this, 'render_profile_validation_box' ),
            SC_Library_Scholarly_Library_Connectors::PROFILE_POST_TYPE,
            'side',
            'high'
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_source = SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === $screen->post_type;
        $is_profile = SC_Library_Scholarly_Library_Connectors::PROFILE_POST_TYPE === $screen->post_type;
        $is_discovery = false !== strpos( (string) $screen->id, 'sc-library-source-discovery' );
        if ( ! $is_source && ! $is_profile && ! $is_discovery ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-connector-reliability',
            SC_LIBRARY_URL . 'assets/css/sc-library-connector-reliability.css',
            array( 'sc-library-connectors' ),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-connector-reliability',
            SC_LIBRARY_URL . 'assets/js/sc-library-connector-reliability.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-connector-reliability',
            'SCLibraryConnectorReliability',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_connector_reliability_v261' ),
                'strings' => array(
                    'working'        => __( 'Working…', 'sustainable-catalyst-library' ),
                    'providerReset'  => __( 'Provider state reset.', 'sustainable-catalyst-library' ),
                    'cacheCleared'   => __( 'Connector cache cleared.', 'sustainable-catalyst-library' ),
                    'holdingsDone'   => __( 'Holdings recheck complete.', 'sustainable-catalyst-library' ),
                    'conflictDone'   => __( 'Conflict resolved.', 'sustainable-catalyst-library' ),
                    'profileChecked' => __( 'Library profile validation complete.', 'sustainable-catalyst-library' ),
                    'failed'         => __( 'Reliability action failed.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    private function verify_ajax( $capability = 'edit_posts' ) {
        check_ajax_referer( 'sc_library_connector_reliability_v261', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this reliability action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    public static function request_json( $provider_id, $url, $headers, $timeout, $settings ) {
        $provider_id = sanitize_key( $provider_id );
        $validation = self::validate_provider_url( $provider_id, $url );
        if ( is_wp_error( $validation ) ) {
            self::record_failure( $provider_id, $validation->get_error_code(), 0, 0, $validation->get_error_message() );
            return $validation;
        }

        $circuit = self::provider_request_state( $provider_id );
        if ( is_wp_error( $circuit ) ) {
            return $circuit;
        }

        $request_hash = hash( 'sha256', $url );
        $validators = get_option( self::OPTION_HTTP_VALIDATORS, array() );
        $validators = is_array( $validators ) ? $validators : array();
        $validator = is_array( $validators[ $request_hash ] ?? null ) ? $validators[ $request_hash ] : array();

        $user_agent = 'SustainableCatalystLibrary/' . self::VERSION;
        if ( ! empty( $settings['contact_email'] ) ) {
            $user_agent .= ' (mailto:' . sanitize_email( $settings['contact_email'] ) . ')';
        }

        $headers = array_merge(
            array(
                'Accept'     => 'application/json',
                'User-Agent' => $user_agent,
            ),
            is_array( $headers ) ? $headers : array()
        );
        if ( ! empty( $validator['etag'] ) ) {
            $headers['If-None-Match'] = $validator['etag'];
        }
        if ( ! empty( $validator['last_modified'] ) ) {
            $headers['If-Modified-Since'] = $validator['last_modified'];
        }

        $max_attempts = 3;
        $last_error = null;
        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $started = microtime( true );
            $response = wp_safe_remote_get(
                $url,
                array(
                    'timeout'             => max( 5, min( 30, absint( $timeout ) ) ),
                    'redirection'         => 2,
                    'headers'             => $headers,
                    'limit_response_size' => 5 * 1024 * 1024,
                )
            );
            $latency = round( ( microtime( true ) - $started ) * 1000 );

            if ( is_wp_error( $response ) ) {
                $last_error = new WP_Error( 'connector_transport_error', $response->get_error_message(), array( 'status' => 502 ) );
                self::record_failure( $provider_id, 'transport', 0, $latency, $response->get_error_message() );
                if ( $attempt < $max_attempts ) {
                    self::short_retry_delay( $attempt );
                    continue;
                }
                return $last_error;
            }

            $status = absint( wp_remote_retrieve_response_code( $response ) );
            $retry_after = self::retry_after_seconds( wp_remote_retrieve_header( $response, 'retry-after' ) );
            $rate_headers = self::rate_headers( $response );

            if ( 304 === $status ) {
                $cached_body = get_transient( self::CACHE_PREFIX . 'http_body_' . $request_hash );
                if ( is_string( $cached_body ) && '' !== $cached_body ) {
                    $decoded = json_decode( $cached_body, true );
                    if ( is_array( $decoded ) ) {
                        self::record_success( $provider_id, $status, $latency, $rate_headers, true );
                        return $decoded;
                    }
                }
                $last_error = new WP_Error( 'connector_304_cache_miss', __( 'The provider returned Not Modified, but the cached response body was unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 502 ) );
                self::record_failure( $provider_id, '304-cache-miss', $status, $latency, $last_error->get_error_message() );
                unset( $headers['If-None-Match'], $headers['If-Modified-Since'] );
                unset( $validators[ $request_hash ] );
                update_option( self::OPTION_HTTP_VALIDATORS, $validators, false );
                if ( $attempt < $max_attempts ) {
                    self::short_retry_delay( $attempt );
                    continue;
                }
                return $last_error;
            }

            if ( $status >= 200 && $status < 300 ) {
                $body = wp_remote_retrieve_body( $response );
                $decoded = json_decode( $body, true );
                if ( ! is_array( $decoded ) ) {
                    $last_error = new WP_Error( 'connector_invalid_json', __( 'The provider returned invalid JSON.', 'sustainable-catalyst-library' ), array( 'status' => 502 ) );
                    self::record_failure( $provider_id, 'invalid-json', $status, $latency, $last_error->get_error_message() );
                    return $last_error;
                }

                $body_key = self::CACHE_PREFIX . 'http_body_' . $request_hash;
                set_transient( $body_key, $body, self::HTTP_BODY_TTL );
                self::index_cache_record( $body_key, $provider_id, 'http-body' );
                $validators[ $request_hash ] = array(
                    'provider'      => $provider_id,
                    'etag'          => sanitize_text_field( wp_remote_retrieve_header( $response, 'etag' ) ),
                    'last_modified' => sanitize_text_field( wp_remote_retrieve_header( $response, 'last-modified' ) ),
                    'stored_at'     => current_time( 'mysql' ),
                );
                $validators = array_slice( $validators, -500, null, true );
                update_option( self::OPTION_HTTP_VALIDATORS, $validators, false );
                self::record_success( $provider_id, $status, $latency, $rate_headers, false );
                return $decoded;
            }

            $retryable = in_array( $status, array( 408, 425, 429, 500, 502, 503, 504 ), true );
            $message = sprintf(
                __( '%1$s returned HTTP %2$d.', 'sustainable-catalyst-library' ),
                sanitize_text_field( $provider_id ),
                $status
            );
            self::record_failure( $provider_id, 'http', $status, $latency, $message, $retry_after, $rate_headers );
            $last_error = new WP_Error( 'connector_http_error', $message, array( 'status' => 502, 'provider_status' => $status ) );

            if ( $retryable && $attempt < $max_attempts && $retry_after <= 2 ) {
                self::short_retry_delay( $attempt, $retry_after );
                continue;
            }
            return $last_error;
        }

        return $last_error ?: new WP_Error( 'connector_unknown_failure', __( 'The provider request failed.', 'sustainable-catalyst-library' ), array( 'status' => 502 ) );
    }

    private static function validate_provider_url( $provider_id, $url ) {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $allowed_hosts = array(
            'api.crossref.org',
            'api.openalex.org',
            'api.datacite.org',
            'eutils.ncbi.nlm.nih.gov',
            'www.loc.gov',
            'openlibrary.org',
            'www.googleapis.com',
            'api.unpaywall.org',
        );
        $allowed_hosts = apply_filters( 'sc_library_connector_allowed_hosts', $allowed_hosts, $provider_id );
        if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! in_array( $host, $allowed_hosts, true ) ) {
            return new WP_Error( 'connector_url_rejected', __( 'Connector URL was rejected by the provider allowlist.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
        }
        return true;
    }

    private static function short_retry_delay( $attempt, $retry_after = 0 ) {
        $milliseconds = $retry_after > 0
            ? min( 2000, $retry_after * 1000 )
            : min( 1500, ( 200 * ( 2 ** max( 0, $attempt - 1 ) ) ) + wp_rand( 25, 175 ) );
        usleep( $milliseconds * 1000 );
    }

    private static function retry_after_seconds( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return 0;
        }
        if ( ctype_digit( $value ) ) {
            return absint( $value );
        }
        $timestamp = strtotime( $value );
        return $timestamp ? max( 0, $timestamp - time() ) : 0;
    }

    private static function rate_headers( $response ) {
        $names = array(
            'x-rate-limit-limit',
            'x-rate-limit-interval',
            'x-rate-limit-remaining',
            'x-rate-limit-reset',
            'x-concurrency-limit',
            'ratelimit-limit',
            'ratelimit-remaining',
            'ratelimit-reset',
        );
        $headers = array();
        foreach ( $names as $name ) {
            $value = wp_remote_retrieve_header( $response, $name );
            if ( '' !== (string) $value ) {
                $headers[ $name ] = sanitize_text_field( $value );
            }
        }
        return $headers;
    }

    public static function provider_request_state( $provider_id ) {
        $state = self::provider_health( $provider_id );
        if ( 'open' === $state['status'] && ! empty( $state['cooldown_until'] ) && strtotime( $state['cooldown_until'] ) > time() ) {
            return new WP_Error(
                'provider_circuit_open',
                sprintf( __( '%s is temporarily paused while the connector recovers.', 'sustainable-catalyst-library' ), $provider_id ),
                array( 'status' => 503, 'cooldown_until' => $state['cooldown_until'] )
            );
        }
        if ( 'open' === $state['status'] ) {
            $state['status'] = 'half-open';
            self::save_provider_health( $provider_id, $state );
        }
        return true;
    }

    public static function record_success( $provider_id, $http_status, $latency, $rate_headers = array(), $conditional = false ) {
        $state = self::provider_health( $provider_id );
        $state['status'] = 'healthy';
        $state['consecutive_failures'] = 0;
        $state['success_count']++;
        $state['last_success'] = current_time( 'mysql' );
        $state['last_http_status'] = absint( $http_status );
        $state['last_error'] = '';
        $state['cooldown_until'] = '';
        $state['average_latency_ms'] = self::rolling_average( $state['average_latency_ms'], $latency, $state['success_count'] );
        $state['rate_headers'] = is_array( $rate_headers ) ? $rate_headers : array();
        self::append_health_event( $state, array(
            'type'        => $conditional ? 'conditional-cache-hit' : 'success',
            'time'        => current_time( 'mysql' ),
            'http_status' => absint( $http_status ),
            'latency_ms'  => absint( $latency ),
        ) );
        self::save_provider_health( $provider_id, $state );
    }

    public static function record_failure( $provider_id, $kind, $http_status, $latency, $message, $retry_after = 0, $rate_headers = array() ) {
        $state = self::provider_health( $provider_id );
        $state['failure_count']++;
        $state['consecutive_failures']++;
        $state['last_failure'] = current_time( 'mysql' );
        $state['last_http_status'] = absint( $http_status );
        $state['last_error'] = sanitize_text_field( $message );
        $state['rate_headers'] = is_array( $rate_headers ) ? $rate_headers : array();

        $cooldown = max(
            $retry_after,
            $state['consecutive_failures'] >= self::CIRCUIT_THRESHOLD ? self::CIRCUIT_COOLDOWN : 0
        );
        if ( $cooldown > 0 || 429 === absint( $http_status ) ) {
            $cooldown = max( MINUTE_IN_SECONDS, min( HOUR_IN_SECONDS, $cooldown ?: 5 * MINUTE_IN_SECONDS ) );
            $state['status'] = 'open';
            $state['cooldown_until'] = wp_date( 'Y-m-d H:i:s', time() + $cooldown );
        } else {
            $state['status'] = 'degraded';
        }

        self::append_health_event( $state, array(
            'type'        => 'failure',
            'kind'        => sanitize_key( $kind ),
            'time'        => current_time( 'mysql' ),
            'http_status' => absint( $http_status ),
            'latency_ms'  => absint( $latency ),
            'message'     => sanitize_text_field( $message ),
        ) );
        self::save_provider_health( $provider_id, $state );
    }

    private static function rolling_average( $current, $value, $count ) {
        $current = floatval( $current );
        $value = floatval( $value );
        $count = max( 1, absint( $count ) );
        return round( ( ( $current * max( 0, $count - 1 ) ) + $value ) / $count, 2 );
    }

    private static function append_health_event( &$state, $event ) {
        $events = is_array( $state['events'] ?? null ) ? $state['events'] : array();
        $events[] = $event;
        $state['events'] = array_slice( $events, -self::MAX_HEALTH_EVENTS );
    }

    public static function health_registry() {
        $registry = get_option( self::OPTION_HEALTH, array() );
        return is_array( $registry ) ? $registry : array();
    }

    public static function provider_health( $provider_id ) {
        $registry = self::health_registry();
        return isset( $registry[ $provider_id ] ) && is_array( $registry[ $provider_id ] )
            ? wp_parse_args( $registry[ $provider_id ], self::empty_health_state( $provider_id ) )
            : self::empty_health_state( $provider_id );
    }

    private static function save_provider_health( $provider_id, $state ) {
        $registry = self::health_registry();
        $registry[ sanitize_key( $provider_id ) ] = wp_parse_args( $state, self::empty_health_state( $provider_id ) );
        update_option( self::OPTION_HEALTH, $registry, false );
    }

    private static function empty_health_state( $provider_id ) {
        return array(
            'provider'             => sanitize_key( $provider_id ),
            'status'               => 'unknown',
            'success_count'        => 0,
            'failure_count'        => 0,
            'consecutive_failures' => 0,
            'last_success'         => '',
            'last_failure'         => '',
            'last_http_status'     => 0,
            'last_error'           => '',
            'cooldown_until'       => '',
            'average_latency_ms'   => 0,
            'rate_headers'         => array(),
            'events'               => array(),
        );
    }

    private static function index_cache_record( $transient_key, $provider, $kind, $linked_key = '' ) {
        $index = get_option( self::OPTION_CACHE_INDEX, array() );
        $index = is_array( $index ) ? $index : array();
        $index[ $transient_key ] = array(
            'cache_key' => sanitize_text_field( $linked_key ),
            'provider'  => sanitize_key( $provider ),
            'kind'      => sanitize_key( $kind ),
            'stored_at' => current_time( 'mysql' ),
        );
        $index = array_slice( $index, -750, null, true );
        update_option( self::OPTION_CACHE_INDEX, $index, false );
    }

    public static function store_stale_search_cache( $cache_key, $payload ) {
        if ( ! is_array( $payload ) ) {
            return;
        }
        $stale_key = self::CACHE_PREFIX . 'stale_' . md5( $cache_key );
        $payload['stale_saved_at'] = current_time( 'mysql' );
        set_transient( $stale_key, $payload, self::STALE_CACHE_TTL );

        self::index_cache_record(
            $stale_key,
            $payload['provider'] ?? '',
            'stale-search',
            $cache_key
        );
    }

    public static function get_stale_search_cache( $cache_key ) {
        $stale_key = self::CACHE_PREFIX . 'stale_' . md5( $cache_key );
        $payload = get_transient( $stale_key );
        if ( ! is_array( $payload ) ) {
            return array();
        }
        $payload['cached'] = true;
        $payload['stale'] = true;
        $payload['cache_state'] = 'stale';
        $payload['recovery_notice'] = __( 'Live provider access is unavailable. These results came from the last retained cache and may be outdated.', 'sustainable-catalyst-library' );
        return $payload;
    }

    public static function clear_connector_cache( $provider_id = '' ) {
        $provider_id = sanitize_key( $provider_id );
        $index = get_option( self::OPTION_CACHE_INDEX, array() );
        $index = is_array( $index ) ? $index : array();
        $removed = 0;
        foreach ( $index as $transient_key => $meta ) {
            if ( $provider_id && $provider_id !== sanitize_key( $meta['provider'] ?? '' ) ) {
                continue;
            }
            delete_transient( $transient_key );
            if ( ! empty( $meta['cache_key'] ) ) {
                delete_transient( sanitize_text_field( $meta['cache_key'] ) );
            }
            unset( $index[ $transient_key ] );
            $removed++;
        }
        update_option( self::OPTION_CACHE_INDEX, $index, false );

        if ( ! $provider_id ) {
            delete_option( self::OPTION_HTTP_VALIDATORS );
        }
        return $removed;
    }

    public static function normalize_location( $location ) {
        if ( ! is_array( $location ) || empty( $location['url'] ) ) {
            return array();
        }
        $location['url'] = esc_url_raw( $location['url'] );
        if ( ! $location['url'] ) {
            return array();
        }
        $kind = sanitize_key( $location['kind'] ?? 'provider-record' );
        $checked_at = sanitize_text_field( $location['checked_at'] ?? current_time( 'mysql' ) );
        $checked_timestamp = strtotime( $checked_at ) ?: time();
        $fresh_for = self::location_ttl( $kind );

        $location['kind'] = $kind;
        $location['provider'] = sanitize_text_field( $location['provider'] ?? '' );
        $location['label'] = sanitize_text_field( $location['label'] ?? $location['provider'] ?? __( 'Source location', 'sustainable-catalyst-library' ) );
        $location['status'] = sanitize_key( $location['status'] ?? '' );
        $location['checked_at'] = wp_date( 'Y-m-d H:i:s', $checked_timestamp );
        $location['fresh_for_seconds'] = $fresh_for;
        $location['stale_after'] = wp_date( 'Y-m-d H:i:s', $checked_timestamp + $fresh_for );
        $location['stale'] = time() > ( $checked_timestamp + $fresh_for );
        $location['verification'] = sanitize_key( $location['verification'] ?? 'provider-reported' );
        $location['last_http_status'] = absint( $location['last_http_status'] ?? 0 );
        $location['failure_count'] = absint( $location['failure_count'] ?? 0 );
        return $location;
    }

    public static function merge_locations( $existing, $incoming, $limit = 30 ) {
        $indexed = array();
        foreach ( array_merge( (array) $existing, (array) $incoming ) as $location ) {
            $location = self::normalize_location( $location );
            if ( ! $location ) {
                continue;
            }
            $key = md5( strtolower( untrailingslashit( $location['url'] ) ) );
            if ( ! isset( $indexed[ $key ] ) ) {
                $indexed[ $key ] = $location;
                continue;
            }
            $old_time = strtotime( $indexed[ $key ]['checked_at'] ?? '' ) ?: 0;
            $new_time = strtotime( $location['checked_at'] ?? '' ) ?: 0;
            if ( $new_time >= $old_time ) {
                $indexed[ $key ] = array_merge( $indexed[ $key ], $location );
            }
        }
        $locations = array_values( $indexed );
        usort(
            $locations,
            static function ( $left, $right ) {
                return ( strtotime( $right['checked_at'] ?? '' ) ?: 0 ) <=> ( strtotime( $left['checked_at'] ?? '' ) ?: 0 );
            }
        );
        return array_slice( $locations, 0, max( 1, absint( $limit ) ) );
    }

    private static function location_ttl( $kind ) {
        $map = array(
            'open-access'       => 7 * DAY_IN_SECONDS,
            'publisher'         => 7 * DAY_IN_SECONDS,
            'preview'           => 14 * DAY_IN_SECONDS,
            'provider-record'   => 14 * DAY_IN_SECONDS,
            'canonical'         => 30 * DAY_IN_SECONDS,
            'biomedical-record' => 14 * DAY_IN_SECONDS,
            'book-record'       => 14 * DAY_IN_SECONDS,
            'library-catalog'   => DAY_IN_SECONDS,
            'openurl'           => DAY_IN_SECONDS,
            'library-proxy'     => DAY_IN_SECONDS,
            'interlibrary-loan' => DAY_IN_SECONDS,
            'library-search'    => 7 * DAY_IN_SECONDS,
            'scholarly-search'  => 7 * DAY_IN_SECONDS,
        );
        return absint( apply_filters( 'sc_library_location_freshness_ttl', $map[ $kind ] ?? 7 * DAY_IN_SECONDS, $kind ) );
    }

    public static function holdings_summary( $source_id, $save = true ) {
        $locations = get_post_meta( $source_id, SC_Library_Scholarly_Library_Connectors::META_ACCESS_LOCATIONS, true );
        $locations = is_array( $locations ) ? $locations : array();
        $normalized = array_map( array( __CLASS__, 'normalize_location' ), $locations );
        $normalized = array_values( array_filter( $normalized ) );

        $fresh = 0;
        $stale = 0;
        $open_access = 0;
        $library = 0;
        $next_due = 0;
        foreach ( $normalized as $location ) {
            if ( ! empty( $location['stale'] ) ) {
                $stale++;
            } else {
                $fresh++;
            }
            if ( 'open-access' === $location['kind'] || 'open-access' === $location['status'] ) {
                $open_access++;
            }
            if ( in_array( $location['kind'], array( 'library-catalog', 'openurl', 'library-proxy', 'interlibrary-loan', 'library-search' ), true ) ) {
                $library++;
            }
            $due = strtotime( $location['stale_after'] ?? '' ) ?: 0;
            if ( $due && ( ! $next_due || $due < $next_due ) ) {
                $next_due = $due;
            }
        }

        $summary = array(
            'schema'            => self::HOLDINGS_SCHEMA,
            'source_id'         => absint( $source_id ),
            'total'             => count( $normalized ),
            'fresh'             => $fresh,
            'stale'             => $stale,
            'open_access'       => $open_access,
            'library_actions'   => $library,
            'next_recheck_at'   => $next_due ? wp_date( 'Y-m-d H:i:s', $next_due ) : '',
            'last_checked_at'   => get_post_meta( $source_id, SC_Library_Scholarly_Library_Connectors::META_CONNECTOR_LAST_CHECKED, true ),
            'locations'         => $normalized,
        );
        if ( $save ) {
            update_post_meta( $source_id, self::META_HOLDINGS_SUMMARY, $summary );
            if ( $normalized !== $locations ) {
                update_post_meta( $source_id, SC_Library_Scholarly_Library_Connectors::META_ACCESS_LOCATIONS, $normalized );
            }
        }
        return $summary;
    }

    public static function recheck_holdings( $source_id ) {
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            return new WP_Error( 'source_not_found', __( 'Source record not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $connector = new SC_Library_Scholarly_Library_Connectors();
        $result = $connector->locate_source( $source_id, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $summary = self::holdings_summary( $source_id, true );
        $summary['locator_result'] = $result;
        return $summary;
    }

    public static function find_imported_source( $result ) {
        if ( ! is_array( $result ) || empty( $result['provider'] ) || empty( $result['provider_record_id'] ) ) {
            return 0;
        }
        $fingerprint = hash(
            'sha256',
            sanitize_key( $result['provider'] ) . '|' . sanitize_text_field( $result['provider_record_id'] )
        );
        $ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => SC_Library_Scholarly_Library_Connectors::META_IMPORT_FINGERPRINT,
                'meta_value'     => $fingerprint,
            )
        );
        return $ids ? absint( $ids[0] ) : 0;
    }

    public static function idempotency_lookup( $key, $user_id = 0 ) {
        $key = self::normalize_idempotency_key( $key );
        if ( ! $key ) {
            return array();
        }
        $user_id = $user_id ?: get_current_user_id();
        $value = get_transient( self::CACHE_PREFIX . 'idem_' . $user_id . '_' . hash( 'sha256', $key ) );
        return is_array( $value ) ? $value : array();
    }

    public static function idempotency_store( $key, $value, $user_id = 0 ) {
        $key = self::normalize_idempotency_key( $key );
        if ( ! $key || ! is_array( $value ) ) {
            return;
        }
        $user_id = $user_id ?: get_current_user_id();
        set_transient(
            self::CACHE_PREFIX . 'idem_' . $user_id . '_' . hash( 'sha256', $key ),
            $value,
            DAY_IN_SECONDS
        );
    }

    private static function normalize_idempotency_key( $key ) {
        $key = trim( sanitize_text_field( $key ) );
        return preg_match( '/^[A-Za-z0-9._:-]{8,160}$/', $key ) ? $key : '';
    }

    public static function record_conflict( $source_id, $field, $local_value, $incoming_value, $provider, $provider_record_id ) {
        $field = sanitize_key( $field );
        if ( ! isset( self::$field_map[ $field ] ) || self::values_equal( $local_value, $incoming_value ) ) {
            return '';
        }

        $conflicts = get_post_meta( $source_id, self::META_CONFLICTS, true );
        $conflicts = is_array( $conflicts ) ? $conflicts : array();
        $conflict_id = substr(
            hash( 'sha256', $field . '|' . wp_json_encode( $local_value ) . '|' . wp_json_encode( $incoming_value ) . '|' . $provider ),
            0,
            20
        );

        $existing_index = null;
        foreach ( $conflicts as $index => $conflict ) {
            if ( $conflict_id === ( $conflict['id'] ?? '' ) ) {
                $existing_index = $index;
                break;
            }
        }

        $record = array(
            'schema'             => self::CONFLICT_SCHEMA,
            'id'                 => $conflict_id,
            'field'              => $field,
            'local_value'        => $local_value,
            'incoming_value'     => $incoming_value,
            'provider'           => sanitize_key( $provider ),
            'provider_record_id' => sanitize_text_field( $provider_record_id ),
            'status'             => 'open',
            'first_seen'         => current_time( 'mysql' ),
            'last_seen'          => current_time( 'mysql' ),
            'resolved_at'        => '',
            'resolved_by'        => 0,
            'resolution'         => '',
        );

        if ( null !== $existing_index ) {
            $record['first_seen'] = $conflicts[ $existing_index ]['first_seen'] ?? $record['first_seen'];
            $conflicts[ $existing_index ] = array_merge( $conflicts[ $existing_index ], $record );
        } else {
            $conflicts[] = $record;
        }

        update_post_meta( $source_id, self::META_CONFLICTS, array_slice( $conflicts, -self::MAX_CONFLICTS ) );
        return $conflict_id;
    }

    private static function values_equal( $left, $right ) {
        $normalize = static function ( $value ) {
            if ( is_array( $value ) ) {
                return wp_json_encode( $value );
            }
            return strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $value ) ) );
        };
        return $normalize( $left ) === $normalize( $right );
    }

    public static function open_conflicts( $source_id ) {
        $conflicts = get_post_meta( $source_id, self::META_CONFLICTS, true );
        $conflicts = is_array( $conflicts ) ? $conflicts : array();
        return array_values(
            array_filter(
                $conflicts,
                static function ( $conflict ) {
                    return 'open' === ( $conflict['status'] ?? '' );
                }
            )
        );
    }

    public static function resolve_conflict( $source_id, $conflict_id, $resolution ) {
        $resolution = sanitize_key( $resolution );
        if ( ! in_array( $resolution, array( 'keep_local', 'use_provider', 'dismiss' ), true ) ) {
            return new WP_Error( 'invalid_conflict_resolution', __( 'Unknown conflict resolution.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $conflicts = get_post_meta( $source_id, self::META_CONFLICTS, true );
        $conflicts = is_array( $conflicts ) ? $conflicts : array();
        $resolved = null;
        foreach ( $conflicts as &$conflict ) {
            if ( $conflict_id !== ( $conflict['id'] ?? '' ) ) {
                continue;
            }
            if ( 'open' !== ( $conflict['status'] ?? '' ) ) {
                return new WP_Error( 'conflict_already_resolved', __( 'This conflict has already been resolved.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
            }
            if ( 'use_provider' === $resolution ) {
                $field = sanitize_key( $conflict['field'] ?? '' );
                if ( ! isset( self::$field_map[ $field ] ) ) {
                    return new WP_Error( 'conflict_field_unavailable', __( 'The conflicted field cannot be updated.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
                }
                $target = self::$field_map[ $field ];
                if ( '__post_title' === $target ) {
                    wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_title' => sanitize_text_field( $conflict['incoming_value'] ) ) ) );
                } elseif ( '__post_excerpt' === $target ) {
                    wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_excerpt' => sanitize_textarea_field( $conflict['incoming_value'] ) ) ) );
                } else {
                    update_post_meta( $source_id, $target, $conflict['incoming_value'] );
                }
                update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, '0' );
            }
            $conflict['status'] = 'resolved';
            $conflict['resolution'] = $resolution;
            $conflict['resolved_at'] = current_time( 'mysql' );
            $conflict['resolved_by'] = get_current_user_id();
            $resolved = $conflict;
            break;
        }
        unset( $conflict );

        if ( ! $resolved ) {
            return new WP_Error( 'conflict_not_found', __( 'Connector metadata conflict not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }

        update_post_meta( $source_id, self::META_CONFLICTS, $conflicts );
        if ( 'use_provider' === $resolution ) {
            SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
            SC_Library_Citation_Source_Reliability::recalculate_reliability( $source_id );
        }
        return $resolved;
    }

    public static function validate_library_profile( $profile_id, $save = true ) {
        $fields = array(
            'homepage'         => get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_HOMEPAGE, true ),
            'catalog_template' => get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_CATALOG_TEMPLATE, true ),
            'openurl_base'     => get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_OPENURL_BASE, true ),
            'ill_url'          => get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_ILL_URL, true ),
            'proxy_prefix'     => get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_PROXY_PREFIX, true ),
        );
        $errors = array();
        $warnings = array();
        foreach ( $fields as $field => $value ) {
            if ( '' === trim( (string) $value ) ) {
                continue;
            }
            $check_value = 'catalog_template' === $field
                ? strtr( $value, array( '{query}' => 'test', '{title}' => 'test', '{author}' => 'test', '{doi}' => '10.0000/test', '{isbn}' => '9780000000000', '{pmid}' => '1' ) )
                : $value;
            $url_check = self::validate_external_url( $check_value );
            if ( is_wp_error( $url_check ) ) {
                $errors[] = array(
                    'field'   => $field,
                    'code'    => $url_check->get_error_code(),
                    'message' => $url_check->get_error_message(),
                );
            }
        }

        $template = (string) $fields['catalog_template'];
        if ( $template ) {
            preg_match_all( '/\{[A-Za-z0-9_-]+\}/', $template, $matches );
            $allowed = array( '{query}', '{title}', '{author}', '{doi}', '{isbn}', '{pmid}' );
            foreach ( array_unique( $matches[0] ?? array() ) as $token ) {
                if ( ! in_array( $token, $allowed, true ) ) {
                    $errors[] = array(
                        'field'   => 'catalog_template',
                        'code'    => 'unsupported_token',
                        'message' => sprintf( __( 'Unsupported catalog token: %s', 'sustainable-catalyst-library' ), $token ),
                    );
                }
            }
            if ( ! array_intersect( $allowed, $matches[0] ?? array() ) ) {
                $warnings[] = array(
                    'field'   => 'catalog_template',
                    'code'    => 'no_tokens',
                    'message' => __( 'The catalog template contains no source tokens.', 'sustainable-catalyst-library' ),
                );
            }
        }

        if ( '1' === get_post_meta( $profile_id, SC_Library_Scholarly_Library_Connectors::META_PROFILE_ENABLED, true ) && ! $fields['catalog_template'] && ! $fields['openurl_base'] && ! $fields['ill_url'] ) {
            $warnings[] = array(
                'field'   => 'profile',
                'code'    => 'no_actions',
                'message' => __( 'The enabled profile has no catalog, OpenURL, or interlibrary-loan action.', 'sustainable-catalyst-library' ),
            );
        }

        $validation = array(
            'schema'       => self::PROFILE_SCHEMA,
            'profile_id'   => absint( $profile_id ),
            'valid'        => empty( $errors ),
            'errors'       => $errors,
            'warnings'     => $warnings,
            'validated_at' => current_time( 'mysql' ),
        );
        if ( $save ) {
            update_post_meta( $profile_id, self::META_PROFILE_VALIDATION, $validation );
            update_post_meta( $profile_id, self::META_PROFILE_VALIDATED_AT, $validation['validated_at'] );
        }
        return $validation;
    }

    private static function validate_external_url( $url ) {
        $url = trim( (string) $url );
        if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
            return new WP_Error( 'https_required', __( 'Library and holdings URLs must use HTTPS.', 'sustainable-catalyst-library' ) );
        }
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( ! $host || 'localhost' === $host || '.local' === substr( $host, -6 ) ) {
            return new WP_Error( 'private_host_rejected', __( 'Local and private hosts are not allowed.', 'sustainable-catalyst-library' ) );
        }
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            $public = filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
            if ( ! $public ) {
                return new WP_Error( 'private_ip_rejected', __( 'Private and reserved IP addresses are not allowed.', 'sustainable-catalyst-library' ) );
            }
        }
        return true;
    }

    public function render_conflicts_box( $post ) {
        $conflicts = self::open_conflicts( $post->ID );
        if ( ! $conflicts ) {
            echo '<p>' . esc_html__( 'No unresolved connector metadata conflicts.', 'sustainable-catalyst-library' ) . '</p>';
            return;
        }
        echo '<div class="sc-connector-conflicts" data-source-id="' . esc_attr( $post->ID ) . '">';
        foreach ( $conflicts as $conflict ) {
            echo '<article class="sc-connector-conflict" data-conflict-id="' . esc_attr( $conflict['id'] ) . '">';
            echo '<header><strong>' . esc_html( ucwords( str_replace( '_', ' ', $conflict['field'] ) ) ) . '</strong><span>' . esc_html( $conflict['provider'] ) . '</span></header>';
            echo '<div class="sc-connector-conflict__comparison">';
            echo '<section><h4>' . esc_html__( 'Current Source value', 'sustainable-catalyst-library' ) . '</h4><pre>' . esc_html( self::display_value( $conflict['local_value'] ) ) . '</pre></section>';
            echo '<section><h4>' . esc_html__( 'Provider value', 'sustainable-catalyst-library' ) . '</h4><pre>' . esc_html( self::display_value( $conflict['incoming_value'] ) ) . '</pre></section>';
            echo '</div>';
            echo '<div class="sc-connector-conflict__actions">';
            echo '<button type="button" class="button button-primary" data-sc-conflict-resolution="use_provider">' . esc_html__( 'Use Provider Value', 'sustainable-catalyst-library' ) . '</button>';
            echo '<button type="button" class="button" data-sc-conflict-resolution="keep_local">' . esc_html__( 'Keep Current Value', 'sustainable-catalyst-library' ) . '</button>';
            echo '<button type="button" class="button" data-sc-conflict-resolution="dismiss">' . esc_html__( 'Dismiss', 'sustainable-catalyst-library' ) . '</button>';
            echo '<span data-sc-conflict-status aria-live="polite"></span>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
    }

    private static function display_value( $value ) {
        return is_array( $value )
            ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            : (string) $value;
    }

    public function render_holdings_box( $post ) {
        $summary = self::holdings_summary( $post->ID, true );
        ?>
        <div class="sc-holdings-summary" data-sc-holdings-source="<?php echo esc_attr( $post->ID ); ?>">
            <dl>
                <div><dt><?php esc_html_e( 'Locations', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Fresh', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['fresh'] ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Stale', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['stale'] ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Open access', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $summary['open_access'] ) ); ?></dd></div>
            </dl>
            <?php if ( $summary['last_checked_at'] ) : ?><p><?php echo esc_html( sprintf( __( 'Last checked: %s', 'sustainable-catalyst-library' ), $summary['last_checked_at'] ) ); ?></p><?php endif; ?>
            <?php if ( $summary['next_recheck_at'] ) : ?><p><?php echo esc_html( sprintf( __( 'Next due: %s', 'sustainable-catalyst-library' ), $summary['next_recheck_at'] ) ); ?></p><?php endif; ?>
            <button type="button" class="button" data-sc-recheck-holdings><?php esc_html_e( 'Recheck Holdings', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-holdings-status aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_profile_validation_box( $post ) {
        $validation = self::validate_library_profile( $post->ID, false );
        ?>
        <div class="sc-profile-validation" data-profile-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><strong class="<?php echo $validation['valid'] ? 'is-valid' : 'is-invalid'; ?>"><?php echo esc_html( $validation['valid'] ? __( 'Profile structure valid', 'sustainable-catalyst-library' ) : __( 'Profile needs correction', 'sustainable-catalyst-library' ) ); ?></strong></p>
            <?php if ( $validation['errors'] ) : ?><ul><?php foreach ( $validation['errors'] as $error ) : ?><li><?php echo esc_html( $error['message'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ( $validation['warnings'] ) : ?><ul><?php foreach ( $validation['warnings'] as $warning ) : ?><li><?php echo esc_html( $warning['message'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <button type="button" class="button" data-sc-validate-profile><?php esc_html_e( 'Validate Profile', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-profile-validation-status aria-live="polite"></div>
        </div>
        <?php
    }

    public static function render_admin_reliability_panel() {
        $registry = self::health_registry();
        $index = get_option( self::OPTION_CACHE_INDEX, array() );
        $index = is_array( $index ) ? $index : array();
        ?>
        <section class="sc-connector-reliability-panel">
            <header>
                <div>
                    <p class="sc-connector-kicker"><?php esc_html_e( 'Operational reliability', 'sustainable-catalyst-library' ); ?></p>
                    <h2><?php esc_html_e( 'Connector Health and Recovery', 'sustainable-catalyst-library' ); ?></h2>
                </div>
                <button type="button" class="button" data-sc-clear-connector-cache><?php esc_html_e( 'Clear Retained Connector Cache', 'sustainable-catalyst-library' ); ?></button>
            </header>
            <p><?php esc_html_e( 'Health states reflect recent provider responses. Circuit breakers pause repeated failures, while retained stale caches keep reviewed discovery results available with an explicit warning.', 'sustainable-catalyst-library' ); ?></p>
            <p><strong><?php echo esc_html( sprintf( __( '%d retained cache records', 'sustainable-catalyst-library' ), count( $index ) ) ); ?></strong></p>
            <div class="sc-connector-health-grid">
                <?php $provider_labels = array(
                    'crossref'       => 'Crossref',
                    'openalex'       => 'OpenAlex',
                    'datacite'       => 'DataCite',
                    'pubmed'         => 'PubMed',
                    'pmc'            => 'PubMed Central',
                    'loc'            => 'Library of Congress',
                    'openlibrary'    => 'Open Library',
                    'googlebooks'    => 'Google Books',
                    'unpaywall'      => 'Unpaywall',
                    'google_scholar' => 'Google Scholar',
                    'worldcat'       => 'WorldCat',
                ); ?>
                <?php foreach ( $provider_labels as $provider_id => $label ) : ?>
                    <?php $state = isset( $registry[ $provider_id ] ) ? wp_parse_args( $registry[ $provider_id ], self::empty_health_state( $provider_id ) ) : self::empty_health_state( $provider_id ); ?>
                    <article data-sc-provider-health="<?php echo esc_attr( $provider_id ); ?>">
                        <header><h3><?php echo esc_html( $label ); ?></h3><strong class="status-<?php echo esc_attr( $state['status'] ); ?>"><?php echo esc_html( ucfirst( $state['status'] ) ); ?></strong></header>
                        <dl>
                            <div><dt><?php esc_html_e( 'Last success', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $state['last_success'] ?: '—' ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Last failure', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $state['last_failure'] ?: '—' ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Failures', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $state['consecutive_failures'] ) ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Latency', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $state['average_latency_ms'] ? $state['average_latency_ms'] . ' ms' : '—' ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Cooldown', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $state['cooldown_until'] ?: '—' ); ?></dd></div>
                        </dl>
                        <?php if ( $state['last_error'] ) : ?><p class="sc-connector-health-error"><?php echo esc_html( $state['last_error'] ); ?></p><?php endif; ?>
                        <button type="button" class="button" data-sc-reset-provider="<?php echo esc_attr( $provider_id ); ?>"><?php esc_html_e( 'Reset Provider State', 'sustainable-catalyst-library' ); ?></button>
                        <div data-sc-provider-action-status aria-live="polite"></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div data-sc-cache-action-status aria-live="polite"></div>
        </section>
        <?php
    }

    public function ajax_reset_provider() {
        $this->verify_ajax( 'manage_options' );
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
        self::save_provider_health( $provider, self::empty_health_state( $provider ) );
        delete_transient( SC_Library_Scholarly_Library_Connectors::CACHE_PREFIX . 'backoff_' . $provider );
        wp_send_json_success( array( 'provider' => $provider, 'message' => __( 'Provider state reset.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_clear_cache() {
        $this->verify_ajax( 'manage_options' );
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
        $removed = self::clear_connector_cache( $provider );
        wp_send_json_success( array( 'removed' => $removed, 'message' => __( 'Connector cache cleared.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_recheck_holdings() {
        $this->verify_ajax( 'edit_posts' );
        $source_id = absint( wp_unslash( $_POST['source_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $source_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot recheck this Source.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::recheck_holdings( $source_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 500 ) );
        }
        wp_send_json_success( $result );
    }

    public function ajax_resolve_conflict() {
        $this->verify_ajax( 'edit_posts' );
        $source_id = absint( wp_unslash( $_POST['source_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $source_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot edit this Source.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::resolve_conflict(
            $source_id,
            sanitize_text_field( wp_unslash( $_POST['conflict_id'] ?? '' ) ),
            sanitize_key( wp_unslash( $_POST['resolution'] ?? '' ) )
        );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'conflict' => $result, 'message' => __( 'Conflict resolved.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_validate_profile() {
        $this->verify_ajax( 'edit_posts' );
        $profile_id = absint( wp_unslash( $_POST['profile_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $profile_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot validate this profile.', 'sustainable-catalyst-library' ) ), 403 );
        }
        wp_send_json_success( self::validate_library_profile( $profile_id, true ) );
    }

    public function profile_validation_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || SC_Library_Scholarly_Library_Connectors::PROFILE_POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
            return;
        }
        $profile_id = absint( wp_unslash( $_GET['post'] ?? 0 ) );
        if ( ! $profile_id ) {
            return;
        }
        $validation = self::validate_library_profile( $profile_id, false );
        if ( ! $validation['valid'] ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'This library profile contains invalid or unsafe URLs and should not be published until corrected.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/connectors/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_health' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/connectors/(?P<provider>[a-z0-9_-]+)/reset',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_reset_provider' ),
                'permission_callback' => static function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/sources/(?P<id>\d+)/holdings',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_holdings' ),
                'permission_callback' => array( $this, 'rest_can_edit_source' ),
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/sources/(?P<id>\d+)/holdings/recheck',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_recheck_holdings' ),
                'permission_callback' => array( $this, 'rest_can_edit_source' ),
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/sources/(?P<id>\d+)/connector-conflicts',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_conflicts' ),
                'permission_callback' => array( $this, 'rest_can_edit_source' ),
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/sources/(?P<id>\d+)/connector-conflicts/(?P<conflict>[A-Za-z0-9_-]+)',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_resolve_conflict' ),
                'permission_callback' => array( $this, 'rest_can_edit_source' ),
            )
        );
        register_rest_route(
            SC_Library_Scholarly_Library_Connectors::API_NAMESPACE,
            '/library-profiles/(?P<id>\d+)/validation',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_profile_validation' ),
                'permission_callback' => static function ( WP_REST_Request $request ) {
                    return current_user_can( 'edit_post', absint( $request['id'] ) );
                },
            )
        );
    }

    public function rest_health() {
        return rest_ensure_response(
            array(
                'schema'        => self::HEALTH_SCHEMA,
                'version'       => self::VERSION,
                'providers'     => self::health_registry(),
                'cache_records' => count( (array) get_option( self::OPTION_CACHE_INDEX, array() ) ),
                'checked_at'    => current_time( 'mysql' ),
            )
        );
    }

    public function rest_reset_provider( WP_REST_Request $request ) {
        $provider = sanitize_key( $request['provider'] );
        self::save_provider_health( $provider, self::empty_health_state( $provider ) );
        delete_transient( SC_Library_Scholarly_Library_Connectors::CACHE_PREFIX . 'backoff_' . $provider );
        return rest_ensure_response( array( 'provider' => $provider, 'reset' => true ) );
    }

    public function rest_holdings( WP_REST_Request $request ) {
        return rest_ensure_response( self::holdings_summary( absint( $request['id'] ), true ) );
    }

    public function rest_recheck_holdings( WP_REST_Request $request ) {
        return rest_ensure_response( self::recheck_holdings( absint( $request['id'] ) ) );
    }

    public function rest_conflicts( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return rest_ensure_response(
            array(
                'schema'    => self::CONFLICT_SCHEMA,
                'source_id' => $source_id,
                'conflicts' => self::open_conflicts( $source_id ),
            )
        );
    }

    public function rest_resolve_conflict( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        return rest_ensure_response(
            self::resolve_conflict(
                absint( $request['id'] ),
                sanitize_text_field( $request['conflict'] ),
                sanitize_key( $payload['resolution'] ?? '' )
            )
        );
    }

    public function rest_profile_validation( WP_REST_Request $request ) {
        return rest_ensure_response( self::validate_library_profile( absint( $request['id'] ), true ) );
    }

    public function rest_can_edit_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return $source_id
            && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id )
            && current_user_can( 'edit_post', $source_id );
    }

    public function maintenance_run() {
        $lock_key = self::CACHE_PREFIX . 'maintenance_lock';
        if ( get_transient( $lock_key ) ) {
            return;
        }
        set_transient( $lock_key, 1, 20 * MINUTE_IN_SECONDS );
        try {
            self::cleanup_health_registry();
            self::process_due_holdings( 10 );
            $this->maybe_migrate_locations();
        } finally {
            delete_transient( $lock_key );
        }
    }

    private static function cleanup_health_registry() {
        $registry = self::health_registry();
        foreach ( $registry as $provider_id => $state ) {
            $state = wp_parse_args( $state, self::empty_health_state( $provider_id ) );
            if ( 'open' === $state['status'] && $state['cooldown_until'] && strtotime( $state['cooldown_until'] ) <= time() ) {
                $state['status'] = 'half-open';
                $state['cooldown_until'] = '';
                $registry[ $provider_id ] = $state;
            }
        }
        update_option( self::OPTION_HEALTH, $registry, false );
    }

    private static function process_due_holdings( $limit ) {
        $sources = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => max( 50, absint( $limit ) * 5 ),
                'fields'         => 'ids',
                'meta_key'       => SC_Library_Scholarly_Library_Connectors::META_ACCESS_LOCATIONS,
                'orderby'        => 'modified',
                'order'          => 'ASC',
            )
        );
        $processed = 0;
        foreach ( $sources as $source_id ) {
            $summary = self::holdings_summary( $source_id, true );
            if ( $summary['stale'] > 0 || ( $summary['next_recheck_at'] && strtotime( $summary['next_recheck_at'] ) <= time() ) ) {
                self::recheck_holdings( $source_id );
                $processed++;
                if ( $processed >= max( 1, absint( $limit ) ) ) {
                    break;
                }
            }
        }
    }

    public function maybe_migrate_locations() {
        if ( get_option( self::OPTION_MIGRATION_DONE ) === self::VERSION ) {
            return;
        }

        $cursor = absint( get_option( self::OPTION_MIGRATION_CURSOR, 0 ) );
        $ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 40,
                'offset'         => $cursor,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_key'       => SC_Library_Scholarly_Library_Connectors::META_ACCESS_LOCATIONS,
            )
        );

        if ( ! $ids ) {
            update_option( self::OPTION_MIGRATION_DONE, self::VERSION, false );
            delete_option( self::OPTION_MIGRATION_CURSOR );
            return;
        }

        foreach ( $ids as $source_id ) {
            self::holdings_summary( $source_id, true );
        }
        update_option( self::OPTION_MIGRATION_CURSOR, $cursor + count( $ids ), false );
    }
}
