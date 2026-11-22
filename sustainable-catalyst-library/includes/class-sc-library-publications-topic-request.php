<?php
/**
 * SC-PUB-TOPIC-01 — staging-only, non-public editorial requests.
 *
 * Anonymous visitors may suggest subjects when Publications discovery doesn't
 * find relevant content. Requests never become published editorial articles.
 * No email, name, fingerprinting cookie or raw IP is stored.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications_Topic_Request {
    public const POST_TYPE = 'sc_pub_topic_req';
    public const ACTION = 'sc_library_request_publication_topic';

    public static function enabled(): bool {
        return defined( 'SC_LIBRARY_PUBLICATIONS_TOPIC_REQUEST' )
            && SC_LIBRARY_PUBLICATIONS_TOPIC_REQUEST
            && function_exists( 'wp_get_environment_type' )
            && in_array( wp_get_environment_type(), array( 'local', 'staging' ), true );
    }

    public function register_hooks(): void {
        if ( ! self::enabled() ) { return; }
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'receive' ) );
        add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'receive' ) );
    }

    public function register_post_type(): void {
        register_post_type( self::POST_TYPE, array(
            'label' => __( 'Publication Topic Requests', 'sustainable-catalyst-library' ),
            'labels' => array(
                'name' => __( 'Publication Topic Requests', 'sustainable-catalyst-library' ),
                'singular_name' => __( 'Topic Request', 'sustainable-catalyst-library' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_in_rest' => false,
            'show_ui' => true,
            'show_in_menu' => 'sc-library',
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array( 'title', 'editor' ),
            'capability_type' => 'post',
        ) );
    }

    /** @return array<string,string>|WP_Error */
    public static function validate( $topic, $description ) {
        if ( ! is_string( $topic ) || ! is_string( $description ) ) {
            return new WP_Error( 'invalid-topic-request' );
        }
        // Bound input before sanitizing. This is an editorial text request,
        // not a link, attachment, HTML, or scientific citation submission.
        if ( strlen( $topic ) > 600 || strlen( $description ) > 2500 ) {
            return new WP_Error( 'topic-request-too-large' );
        }
        $topic = trim( sanitize_text_field( $topic ) );
        $description = trim( sanitize_textarea_field( $description ) );
        $topic_length = function_exists( 'mb_strlen' ) ? mb_strlen( $topic ) : strlen( $topic );
        $description_length = function_exists( 'mb_strlen' ) ? mb_strlen( $description ) : strlen( $description );
        if ( $topic_length < 3 || $topic_length > 160 || $description_length > 600 ) {
            return new WP_Error( 'invalid-topic-request-length' );
        }
        if ( preg_match( '~https?://|www\.~i', $topic ) ) {
            return new WP_Error( 'topic-request-urls-not-accepted' );
        }
        return array( 'topic' => $topic, 'description' => $description );
    }

    private static function redirect_result( string $result ): void {
        $destination = add_query_arg(
            'sc_pub_topic_request',
            $result,
            home_url( '/publications/' )
        );
        wp_safe_redirect( $destination, 303 );
        exit;
    }

    public function receive(): void {
        if ( ! self::enabled() ) { self::redirect_result( 'not_available' ); }
        if ( 'POST' !== (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            self::redirect_result( 'invalid' );
        }
        $nonce = isset( $_POST['sc_pub_topic_nonce'] ) && is_scalar( $_POST['sc_pub_topic_nonce'] )
            ? sanitize_text_field( wp_unslash( (string) $_POST['sc_pub_topic_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
            self::redirect_result( 'invalid' );
        }
        // Invisible bot trap. Never echo submitted values in the response.
        if ( ! empty( $_POST['sc_pub_topic_website'] ) ) {
            self::redirect_result( 'received' );
        }
        $topic = isset( $_POST['sc_pub_topic_title'] ) && is_scalar( $_POST['sc_pub_topic_title'] )
            ? wp_unslash( (string) $_POST['sc_pub_topic_title'] ) : '';
        $description = isset( $_POST['sc_pub_topic_context'] ) && is_scalar( $_POST['sc_pub_topic_context'] )
            ? wp_unslash( (string) $_POST['sc_pub_topic_context'] ) : '';
        $valid = self::validate( $topic, $description );
        if ( is_wp_error( $valid ) ) { self::redirect_result( 'invalid' ); }

        // Only an HMAC digest of the immediate server peer is placed in a
        // short-lived throttle transient. Do not store the raw address or
        // headers in the request post or event logs.
        $peer = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        $peer_hash = hash_hmac( 'sha256', $peer, wp_salt( 'auth' ) );
        $throttle_key = 'sc_pub_topic_limit_' . substr( $peer_hash, 0, 40 );
        $sent = (int) get_transient( $throttle_key );
        if ( $sent >= 3 ) { self::redirect_result( 'rate_limited' ); }

        $id = wp_insert_post( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'pending',
            'post_author' => 0,
            'post_title' => wp_slash( $valid['topic'] ),
            'post_content' => wp_slash( $valid['description'] ),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ), true );
        if ( is_wp_error( $id ) || ! $id ) {
            self::redirect_result( 'unavailable' );
        }
        set_transient( $throttle_key, $sent + 1, HOUR_IN_SECONDS );
        self::redirect_result( 'received' );
    }
}
