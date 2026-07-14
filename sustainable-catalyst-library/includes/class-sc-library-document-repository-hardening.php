<?php
/**
 * Repository interface, accessibility, and performance hardening.
 *
 * Provides generation-based repository caches, targeted invalidation,
 * accessible result-link helpers, and administration controls.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Document_Repository_Hardening {
    public const VERSION = '2.3.1';
    public const CACHE_GENERATION_OPTION = 'sc_library_document_repository_cache_generation';
    public const CACHE_TTL = 21600;

    public function __construct() {
        add_action( 'save_post_sc_foundation_doc', array( $this, 'invalidate_for_document' ), 220, 3 );
        add_action( 'before_delete_post', array( $this, 'invalidate_for_deleted_post' ), 220 );
        add_action( 'set_object_terms', array( $this, 'invalidate_for_terms' ), 220, 6 );
        add_action( 'created_sc_document_family', array( $this, 'invalidate' ), 220 );
        add_action( 'edited_sc_document_family', array( $this, 'invalidate' ), 220 );
        add_action( 'delete_sc_document_family', array( $this, 'invalidate' ), 220 );
        add_action( 'created_sc_document_type', array( $this, 'invalidate' ), 220 );
        add_action( 'edited_sc_document_type', array( $this, 'invalidate' ), 220 );
        add_action( 'delete_sc_document_type', array( $this, 'invalidate' ), 220 );

        add_action( 'admin_post_sc_library_v231_clear_repository_cache', array( $this, 'clear_repository_cache' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 160 );
    }

    public static function cache_generation() {
        $generation = absint( get_option( self::CACHE_GENERATION_OPTION, 1 ) );
        if ( $generation < 1 ) {
            $generation = 1;
            update_option( self::CACHE_GENERATION_OPTION, $generation, false );
        }
        return $generation;
    }

    public static function cache_key( $suffix ) {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        return 'sc_repo_v231_' . self::cache_generation() . '_' . md5( $locale . '|' . (string) $suffix );
    }

    public static function cache_get( $suffix ) {
        return get_transient( self::cache_key( $suffix ) );
    }

    public static function cache_set( $suffix, $value, $ttl = self::CACHE_TTL ) {
        set_transient( self::cache_key( $suffix ), $value, max( 60, absint( $ttl ) ) );
        return $value;
    }

    public static function result_fragment( $instance_id ) {
        return '#' . sanitize_html_class( $instance_id ) . '-results';
    }

    public static function append_result_fragment( $url, $instance_id ) {
        $url = (string) $url;
        $fragment = self::result_fragment( $instance_id );
        $url = preg_replace( '/#.*$/', '', $url );
        return $url . $fragment;
    }

    public function invalidate_for_document( $post_id, $post, $update ) {
        if ( $post instanceof WP_Post && 'sc_foundation_doc' === $post->post_type && ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
            $this->invalidate();
        }
    }

    public function invalidate_for_deleted_post( $post_id ) {
        if ( 'sc_foundation_doc' === get_post_type( $post_id ) ) {
            $this->invalidate();
        }
    }

    public function invalidate_for_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        if ( 'sc_foundation_doc' === get_post_type( $object_id ) && in_array( $taxonomy, array( 'sc_document_family', 'sc_document_type' ), true ) ) {
            $this->invalidate();
        }
    }

    public function invalidate() {
        $generation = self::cache_generation() + 1;
        if ( $generation > 999999999 ) {
            $generation = 1;
        }
        update_option( self::CACHE_GENERATION_OPTION, $generation, false );
    }

    public function clear_repository_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to clear repository caches.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v231_clear_repository_cache' );
        $this->invalidate();

        wp_safe_redirect(
            add_query_arg(
                'sc_v231_notice',
                'cache_cleared',
                admin_url( 'admin.php?page=sc-library-public-document-repository' )
            )
        );
        exit;
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-public-document-repository' ) ) {
            return;
        }

        $notice = isset( $_GET['sc_v231_notice'] ) ? sanitize_key( wp_unslash( $_GET['sc_v231_notice'] ) ) : '';
        if ( 'cache_cleared' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Public repository caches were cleared.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }
}
