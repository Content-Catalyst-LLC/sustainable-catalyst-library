<?php
/**
 * Source Versioning, Supersession, and Research Integrity.
 *
 * Adds version families, structured Source relationships, corrections,
 * retractions, replacement guidance, immutable metadata snapshots, impact
 * reports, project acknowledgements, public integrity notices, REST routes,
 * and WP-CLI review utilities.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Source_Versioning_Integrity {
    public const VERSION = '3.1.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const INTEGRITY_SCHEMA = 'sc-library-source-integrity/1.0';
    public const RELATION_SCHEMA = 'sc-library-source-version-relation/1.0';
    public const SNAPSHOT_SCHEMA = 'sc-library-source-version-snapshot/1.0';
    public const IMPACT_SCHEMA = 'sc-library-source-integrity-impact/1.0';
    public const PROJECT_REPORT_SCHEMA = 'sc-library-project-source-integrity/1.0';

    public const META_VERSION_LABEL = '_sc_source_version_label';
    public const META_VERSION_NUMBER = '_sc_source_version_number';
    public const META_RELEASE_DATE = '_sc_source_release_date';
    public const META_FAMILY_ID = '_sc_source_version_family_id';
    public const META_RECOMMENDED_ID = '_sc_source_recommended_replacement_id';
    public const META_STATUS = '_sc_source_integrity_status';
    public const META_PUBLIC_NOTICE = '_sc_source_integrity_public_notice';
    public const META_NOTICE_DATE = '_sc_source_integrity_notice_date';
    public const META_NOTICE_URL = '_sc_source_integrity_notice_url';
    public const META_REASON = '_sc_source_integrity_reason';
    public const META_REVIEW_STATUS = '_sc_source_integrity_review_status';
    public const META_RELATIONS = '_sc_source_integrity_relations';
    public const META_INCOMING = '_sc_source_integrity_incoming_relations';
    public const META_SNAPSHOTS = '_sc_source_version_snapshots';
    public const META_CURRENT_HASH = '_sc_source_version_current_hash';
    public const META_IMPACT = '_sc_source_integrity_impact';
    public const META_LAST_REVIEWED = '_sc_source_integrity_last_reviewed';
    public const META_LAST_REVIEWED_BY = '_sc_source_integrity_last_reviewed_by';
    public const META_STATUS_CHANGED_AT = '_sc_source_integrity_status_changed_at';
    public const META_RELATION_CONFLICT = '_sc_source_integrity_relation_conflict';

    public const META_PROJECT_ACKS = '_sc_project_source_integrity_acknowledgements';
    public const META_PROJECT_IMPACTS = '_sc_project_source_integrity_impacts';
    public const META_EVIDENCE_IMPACT = '_sc_evidence_source_integrity_impact';
    public const META_CLAIM_IMPACTS = '_sc_claim_source_integrity_impacts';

    public const OPTION_SCAN_STATE = 'sc_library_v310_integrity_scan_state';
    public const TRANSIENT_SCAN_LOCK = 'sc_library_v310_integrity_scan_lock';
    public const CRON_HOOK = 'sc_library_v310_integrity_scan_tick';

    public const MAX_RELATIONS = 100;
    public const MAX_SNAPSHOTS = 30;
    public const MAX_PROJECT_ACKS = 500;
    public const SCAN_BATCH = 20;
    public const LOCK_SECONDS = 180;

    private static $saving = false;
    private static $before_save = array();
    private static $deleted_source = array();

    public function __construct() {
        add_action( 'init', array( $this, 'schedule_scan' ), 979 );
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_scan' ) );

        add_action( 'admin_menu', array( $this, 'register_integrity_page' ), 256 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 125 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 125 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, array( $this, 'capture_before_source_save' ), 5, 3 );
        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, array( $this, 'save_source_integrity' ), 125, 3 );
        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, array( $this, 'save_project_acknowledgements' ), 125, 3 );

        add_action( 'before_delete_post', array( $this, 'capture_deleted_source' ) );
        add_action( 'deleted_post', array( $this, 'cleanup_deleted_source' ) );

        add_filter( 'sc_library_source_data', array( $this, 'filter_source_data' ), 30, 3 );
        add_filter( 'manage_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE . '_posts_columns', array( $this, 'source_columns' ), 80 );
        add_action( 'manage_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE . '_posts_custom_column', array( $this, 'source_column_content' ), 80, 2 );

        add_shortcode( 'sc_source_integrity', array( $this, 'shortcode_source_integrity' ) );
        add_shortcode( 'sc_project_source_integrity', array( $this, 'shortcode_project_integrity' ) );

        add_action( 'wp_ajax_sc_library_v310_scan_integrity', array( $this, 'ajax_scan_integrity' ) );
        add_action( 'wp_ajax_sc_library_v310_rebuild_source_integrity', array( $this, 'ajax_rebuild_source' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_integrity_rest_responses' ), 35, 3 );

        self::register_cli_commands();
    }

    public static function integrity_status_options() {
        return array(
            'current'               => __( 'Current', 'sustainable-catalyst-library' ),
            'updated'               => __( 'Updated or revised', 'sustainable-catalyst-library' ),
            'corrected'             => __( 'Corrected', 'sustainable-catalyst-library' ),
            'superseded'            => __( 'Superseded', 'sustainable-catalyst-library' ),
            'deprecated'            => __( 'Deprecated', 'sustainable-catalyst-library' ),
            'expression-of-concern' => __( 'Expression of concern', 'sustainable-catalyst-library' ),
            'retracted'             => __( 'Retracted', 'sustainable-catalyst-library' ),
            'withdrawn'             => __( 'Withdrawn', 'sustainable-catalyst-library' ),
            'archived'              => __( 'Archived historical version', 'sustainable-catalyst-library' ),
        );
    }

    public static function relation_options() {
        return array(
            'version-of'      => __( 'Is a version of', 'sustainable-catalyst-library' ),
            'supersedes'      => __( 'Supersedes', 'sustainable-catalyst-library' ),
            'corrects'        => __( 'Corrects', 'sustainable-catalyst-library' ),
            'retracts'        => __( 'Retracts', 'sustainable-catalyst-library' ),
            'replaces'        => __( 'Replaces', 'sustainable-catalyst-library' ),
            'erratum-for'     => __( 'Erratum for', 'sustainable-catalyst-library' ),
            'supplement-to'   => __( 'Supplement to', 'sustainable-catalyst-library' ),
            'translation-of'  => __( 'Translation of', 'sustainable-catalyst-library' ),
            'derived-from'    => __( 'Derived from', 'sustainable-catalyst-library' ),
        );
    }

    public static function review_status_options() {
        return array(
            'unreviewed' => __( 'Unreviewed', 'sustainable-catalyst-library' ),
            'review'     => __( 'Review required', 'sustainable-catalyst-library' ),
            'reviewed'   => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'verified'   => __( 'Verified against notice or version record', 'sustainable-catalyst-library' ),
            'disputed'   => __( 'Disputed', 'sustainable-catalyst-library' ),
        );
    }

    public static function acknowledgement_options() {
        return array(
            'pending'              => __( 'Pending review', 'sustainable-catalyst-library' ),
            'reviewed'             => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'replacement-planned'  => __( 'Replacement planned', 'sustainable-catalyst-library' ),
            'replaced'             => __( 'Citation replaced', 'sustainable-catalyst-library' ),
            'accepted-for-context' => __( 'Retained for historical or critical context', 'sustainable-catalyst-library' ),
            'excluded'             => __( 'Excluded from project bibliography', 'sustainable-catalyst-library' ),
        );
    }

    public static function severity_for_status( $status ) {
        $map = array(
            'current'               => 'none',
            'updated'               => 'info',
            'corrected'             => 'warning',
            'superseded'            => 'warning',
            'deprecated'            => 'warning',
            'expression-of-concern' => 'high',
            'retracted'             => 'critical',
            'withdrawn'             => 'critical',
            'archived'              => 'info',
        );
        return $map[ sanitize_key( $status ) ] ?? 'warning';
    }

    public static function requires_review( $status ) {
        return ! in_array( sanitize_key( $status ), array( 'current', 'archived' ), true );
    }


    private static function suggested_status_from_incoming( $source_id ) {
        $priority = array(
            'retracts'   => 'retracted',
            'corrects'   => 'corrected',
            'supersedes' => 'superseded',
            'replaces'   => 'superseded',
        );
        foreach ( $priority as $relation_type => $status ) {
            foreach ( self::incoming_relations( $source_id ) as $relation ) {
                if ( $relation_type === sanitize_key( $relation['relation'] ?? '' ) ) {
                    return $status;
                }
            }
        }
        return '';
    }

    private static function more_severe( $left, $right ) {
        $rank = array( 'none' => 0, 'info' => 1, 'warning' => 2, 'high' => 3, 'critical' => 4 );
        return ( $rank[ $right ] ?? 0 ) > ( $rank[ $left ] ?? 0 ) ? $right : $left;
    }

    public function register_integrity_page() {
        add_submenu_page(
            'sc-library',
            __( 'Source Integrity', 'sustainable-catalyst-library' ),
            __( 'Source Integrity', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-source-integrity',
            array( $this, 'render_integrity_page' )
        );
    }

    public function register_meta_boxes() {
        $source_type = SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE;
        $project_type = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE;

        add_meta_box(
            'sc-source-version-integrity',
            __( 'Version, Supersession, and Integrity', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_integrity_box' ),
            $source_type,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-source-integrity-impact',
            __( 'Research Impact Review', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_impact_box' ),
            $source_type,
            'side',
            'high'
        );
        add_meta_box(
            'sc-project-source-integrity',
            __( 'Source Integrity Impact', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_integrity_box' ),
            $project_type,
            'normal',
            'high'
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $supported = in_array(
            $screen->post_type,
            array(
                SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-source-integrity' );
        if ( ! $supported && ! $workspace ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-source-versioning-integrity',
            SC_LIBRARY_URL . 'assets/css/sc-library-source-versioning-integrity.css',
            array( 'sc-library-citation-manager', 'sc-library-connected-research' ),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-source-versioning-integrity',
            SC_LIBRARY_URL . 'assets/js/sc-library-source-versioning-integrity.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-source-versioning-integrity',
            'SCLibrarySourceIntegrity',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_source_integrity_v310' ),
                'strings' => array(
                    'working'       => __( 'Working…', 'sustainable-catalyst-library' ),
                    'scanComplete'  => __( 'Integrity scan batch complete.', 'sustainable-catalyst-library' ),
                    'rebuilt'       => __( 'Source integrity indexes rebuilt.', 'sustainable-catalyst-library' ),
                    'confirmScan'   => __( 'Run the next Source integrity scan batch?', 'sustainable-catalyst-library' ),
                    'remove'        => __( 'Remove', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function capture_before_source_save( $post_id, $post, $update ) {
        if (
            ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || isset( self::$before_save[ $post_id ] )
        ) {
            return;
        }
        self::$before_save[ $post_id ] = array(
            'snapshot'  => self::structured_snapshot( $post_id ),
            'relations' => self::relations( $post_id ),
            'status'    => (string) get_post_meta( $post_id, self::META_STATUS, true ),
        );
    }

    public function save_source_integrity( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== $post->post_type
        ) {
            return;
        }

        self::$saving = true;
        $before = self::$before_save[ $post_id ] ?? array(
            'snapshot'  => array(),
            'relations' => self::relations( $post_id ),
            'status'    => (string) get_post_meta( $post_id, self::META_STATUS, true ),
        );

        $submitted = isset( $_POST['sc_library_source_integrity_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_source_integrity_nonce'] ) ),
                'sc_library_save_source_integrity_' . $post_id
            )
            && current_user_can( 'edit_post', $post_id );

        if ( $submitted ) {
            $status = self::allowed_value(
                wp_unslash( $_POST['sc_source_integrity_status'] ?? 'current' ),
                self::integrity_status_options(),
                'current'
            );
            $review_status = self::allowed_value(
                wp_unslash( $_POST['sc_source_integrity_review_status'] ?? 'unreviewed' ),
                self::review_status_options(),
                'unreviewed'
            );

            self::update_or_delete_meta( $post_id, self::META_VERSION_LABEL, sanitize_text_field( wp_unslash( $_POST['sc_source_version_label'] ?? '' ) ) );
            self::update_or_delete_meta( $post_id, self::META_VERSION_NUMBER, sanitize_text_field( wp_unslash( $_POST['sc_source_version_number'] ?? '' ) ) );
            self::update_or_delete_meta( $post_id, self::META_RELEASE_DATE, self::sanitize_date( wp_unslash( $_POST['sc_source_release_date'] ?? '' ) ) );

            $family_id = absint( wp_unslash( $_POST['sc_source_version_family_id'] ?? 0 ) );
            if ( $family_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $family_id ) ) {
                $family_id = 0;
            }
            if ( $family_id === $post_id ) {
                $family_id = 0;
            }
            self::update_or_delete_meta( $post_id, self::META_FAMILY_ID, $family_id );

            $recommended_id = absint( wp_unslash( $_POST['sc_source_recommended_replacement_id'] ?? 0 ) );
            if (
                $recommended_id === $post_id
                || ( $recommended_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $recommended_id ) )
            ) {
                $recommended_id = 0;
            }
            self::update_or_delete_meta( $post_id, self::META_RECOMMENDED_ID, $recommended_id );

            self::update_or_delete_meta( $post_id, self::META_STATUS, $status );
            update_post_meta( $post_id, self::META_PUBLIC_NOTICE, isset( $_POST['sc_source_integrity_public_notice'] ) ? '1' : '0' );
            self::update_or_delete_meta( $post_id, self::META_NOTICE_DATE, self::sanitize_date( wp_unslash( $_POST['sc_source_integrity_notice_date'] ?? '' ) ) );
            self::update_or_delete_meta( $post_id, self::META_NOTICE_URL, esc_url_raw( wp_unslash( $_POST['sc_source_integrity_notice_url'] ?? '' ) ) );
            self::update_or_delete_meta( $post_id, self::META_REASON, sanitize_textarea_field( wp_unslash( $_POST['sc_source_integrity_reason'] ?? '' ) ) );
            self::update_or_delete_meta( $post_id, self::META_REVIEW_STATUS, $review_status );

            $relations = self::sanitize_relations(
                wp_unslash( $_POST['sc_source_integrity_relations'] ?? array() ),
                $before['relations'],
                $post_id
            );
            $relations ? update_post_meta( $post_id, self::META_RELATIONS, $relations ) : delete_post_meta( $post_id, self::META_RELATIONS );

            if ( $status !== ( $before['status'] ?: 'current' ) ) {
                update_post_meta( $post_id, self::META_STATUS_CHANGED_AT, current_time( 'mysql' ) );
            }
            if ( in_array( $review_status, array( 'reviewed', 'verified', 'disputed' ), true ) ) {
                update_post_meta( $post_id, self::META_LAST_REVIEWED, current_time( 'mysql' ) );
                update_post_meta( $post_id, self::META_LAST_REVIEWED_BY, get_current_user_id() );
            }
        }

        $after_snapshot = self::structured_snapshot( $post_id );
        $before_snapshot = is_array( $before['snapshot'] ) ? $before['snapshot'] : array();
        if ( $before_snapshot && ( $before_snapshot['hash'] ?? '' ) !== ( $after_snapshot['hash'] ?? '' ) ) {
            self::append_snapshot( $post_id, $before_snapshot );
        }
        if ( ! empty( $after_snapshot['hash'] ) ) {
            update_post_meta( $post_id, self::META_CURRENT_HASH, $after_snapshot['hash'] );
        }

        self::sync_incoming_relations( $post_id, $before['relations'], self::relations( $post_id ) );
        self::rebuild_source_integrity( $post_id );

        unset( self::$before_save[ $post_id ] );
        self::$saving = false;
    }

    private static function structured_snapshot( $source_id ) {
        $post = get_post( $source_id );
        if ( ! $post || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== $post->post_type ) {
            return array();
        }

        $source_type = wp_get_object_terms( $source_id, SC_Library_Citation_Source_Manager::SOURCE_TYPE_TAXONOMY, array( 'fields' => 'slugs' ) );
        $topics = wp_get_object_terms( $source_id, SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY, array( 'fields' => 'slugs' ) );
        $source_type = is_wp_error( $source_type ) ? array() : array_values( $source_type );
        $topics = is_wp_error( $topics ) ? array() : array_values( $topics );

        $source = array(
            'title'                => get_the_title( $source_id ),
            'post_status'          => $post->post_status,
            'abstract'             => (string) $post->post_excerpt,
            'description'          => (string) $post->post_content,
            'authors'              => get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_AUTHORS, true ) ?: array(),
            'organization'         => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ORGANIZATION, true ),
            'organization_short'   => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ORGANIZATION_SHORT, true ),
            'editors'              => get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_EDITORS, true ) ?: array(),
            'year'                 => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_YEAR, true ),
            'year_suffix'          => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_YEAR_SUFFIX, true ),
            'publication_date'     => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PUBLICATION_DATE, true ),
            'access_date'          => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ACCESS_DATE, true ),
            'container_title'      => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_CONTAINER_TITLE, true ),
            'publisher'            => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PUBLISHER, true ),
            'place'                => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PLACE, true ),
            'edition'              => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_EDITION, true ),
            'volume'               => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VOLUME, true ),
            'issue'                => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ISSUE, true ),
            'pages'                => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PAGES, true ),
            'chapter'              => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_CHAPTER, true ),
            'report_number'        => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_REPORT_NUMBER, true ),
            'standard_number'      => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_STANDARD_NUMBER, true ),
            'jurisdiction'         => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_JURISDICTION, true ),
            'doi'                  => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_DOI, true ),
            'isbn'                 => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ISBN, true ),
            'pmid'                 => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PMID, true ),
            'url'                  => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_URL, true ),
            'archive_url'          => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ARCHIVE_URL, true ),
            'language'             => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LANGUAGE, true ),
            'attachment_id'        => absint( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_ATTACHMENT_ID, true ) ),
            'related_document_ids' => self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_RELATED_DOCUMENT_IDS, true ) ),
            'project_ids'          => self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true ) ),
            'private_notes'        => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_NOTES, true ),
            'metadata_verified'    => '1' === get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, true ),
            'peer_reviewed'        => '1' === get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PEER_REVIEWED, true ),
            'source_level'         => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_SOURCE_LEVEL, true ),
            'full_text_status'     => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_FULL_TEXT_STATUS, true ),
            'citation_key'         => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_CITATION_KEY, true ),
            'provenance'           => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROVENANCE, true ),
            'last_verified'        => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LAST_VERIFIED, true ),
            'source_types'         => $source_type,
            'topics'               => $topics,
            'citation'             => SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'reference' ),
        );

        $record = array(
            'schema'           => self::SNAPSHOT_SCHEMA,
            'source_id'        => $source_id,
            'source'           => $source,
            'version_label'    => (string) get_post_meta( $source_id, self::META_VERSION_LABEL, true ),
            'version_number'   => (string) get_post_meta( $source_id, self::META_VERSION_NUMBER, true ),
            'release_date'     => (string) get_post_meta( $source_id, self::META_RELEASE_DATE, true ),
            'family_id'        => absint( get_post_meta( $source_id, self::META_FAMILY_ID, true ) ),
            'recommended_id'   => absint( get_post_meta( $source_id, self::META_RECOMMENDED_ID, true ) ),
            'integrity_status' => (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current',
            'public_notice'    => '1' === get_post_meta( $source_id, self::META_PUBLIC_NOTICE, true ),
            'notice_date'      => (string) get_post_meta( $source_id, self::META_NOTICE_DATE, true ),
            'notice_url'       => (string) get_post_meta( $source_id, self::META_NOTICE_URL, true ),
            'reason'           => (string) get_post_meta( $source_id, self::META_REASON, true ),
            'review_status'    => (string) get_post_meta( $source_id, self::META_REVIEW_STATUS, true ),
            'relations'        => self::relations( $source_id ),
        );

        $record['hash'] = hash( 'sha256', wp_json_encode( $record ) );
        $record['captured_at'] = current_time( 'mysql' );
        $record['captured_by'] = get_current_user_id();
        return $record;
    }

    private static function append_snapshot( $source_id, $snapshot ) {
        if ( ! is_array( $snapshot ) || empty( $snapshot['hash'] ) ) {
            return;
        }
        $snapshots = get_post_meta( $source_id, self::META_SNAPSHOTS, true );
        $snapshots = is_array( $snapshots ) ? $snapshots : array();
        $last = $snapshots ? end( $snapshots ) : array();
        if ( is_array( $last ) && ( $last['hash'] ?? '' ) === $snapshot['hash'] ) {
            return;
        }
        $snapshot['snapshot_id'] = wp_generate_uuid4();
        $snapshots[] = $snapshot;
        update_post_meta( $source_id, self::META_SNAPSHOTS, array_slice( $snapshots, -self::MAX_SNAPSHOTS ) );
    }

    public static function relations( $source_id ) {
        $relations = get_post_meta( $source_id, self::META_RELATIONS, true );
        return is_array( $relations ) ? array_values( $relations ) : array();
    }

    public static function incoming_relations( $source_id ) {
        $relations = get_post_meta( $source_id, self::META_INCOMING, true );
        return is_array( $relations ) ? array_values( $relations ) : array();
    }

    private static function sanitize_relations( $raw, $old_relations = array(), $source_id = 0 ) {
        $old_map = array();
        foreach ( (array) $old_relations as $old ) {
            if ( ! is_array( $old ) ) {
                continue;
            }
            $key = absint( $old['target_id'] ?? 0 ) . '|' . sanitize_key( $old['relation'] ?? '' );
            $old_map[ $key ] = $old;
        }

        $relations = array();
        $seen = array();
        foreach ( (array) $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $target_id = absint( $item['target_id'] ?? 0 );
            $relation = self::allowed_value( $item['relation'] ?? '', self::relation_options(), '' );
            if (
                ! $target_id
                || $target_id === absint( $source_id )
                || ! $relation
                || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $target_id )
            ) {
                continue;
            }
            $key = $target_id . '|' . $relation;
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $old = $old_map[ $key ] ?? array();
            $relations[] = array(
                'schema'         => self::RELATION_SCHEMA,
                'target_id'      => $target_id,
                'relation'       => $relation,
                'effective_date' => self::sanitize_date( $item['effective_date'] ?? '' ),
                'note'           => sanitize_text_field( $item['note'] ?? '' ),
                'public'         => ! empty( $item['public'] ),
                'created_at'     => sanitize_text_field( $old['created_at'] ?? current_time( 'mysql' ) ),
                'created_by'     => absint( $old['created_by'] ?? get_current_user_id() ),
                'updated_at'     => current_time( 'mysql' ),
                'updated_by'     => get_current_user_id(),
            );
            if ( count( $relations ) >= self::MAX_RELATIONS ) {
                break;
            }
        }
        return $relations;
    }

    private static function sync_incoming_relations( $source_id, $old_relations, $new_relations ) {
        $target_ids = array();
        foreach ( array_merge( (array) $old_relations, (array) $new_relations ) as $relation ) {
            if ( is_array( $relation ) ) {
                $target_ids[] = absint( $relation['target_id'] ?? 0 );
            }
        }
        $target_ids = array_values( array_unique( array_filter( $target_ids ) ) );

        foreach ( $target_ids as $target_id ) {
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $target_id ) ) {
                continue;
            }
            $incoming = self::incoming_relations( $target_id );
            $incoming = array_values(
                array_filter(
                    $incoming,
                    static function ( $item ) use ( $source_id ) {
                        return absint( $item['source_id'] ?? 0 ) !== absint( $source_id );
                    }
                )
            );
            foreach ( (array) $new_relations as $relation ) {
                if ( absint( $relation['target_id'] ?? 0 ) !== $target_id ) {
                    continue;
                }
                $incoming[] = array(
                    'schema'         => self::RELATION_SCHEMA,
                    'source_id'      => absint( $source_id ),
                    'relation'       => sanitize_key( $relation['relation'] ?? '' ),
                    'effective_date' => sanitize_text_field( $relation['effective_date'] ?? '' ),
                    'note'           => sanitize_text_field( $relation['note'] ?? '' ),
                    'public'         => ! empty( $relation['public'] ),
                    'updated_at'     => sanitize_text_field( $relation['updated_at'] ?? current_time( 'mysql' ) ),
                );
            }
            $incoming ? update_post_meta( $target_id, self::META_INCOMING, array_slice( $incoming, -self::MAX_RELATIONS ) ) : delete_post_meta( $target_id, self::META_INCOMING );
            $suggested_status = self::suggested_status_from_incoming( $target_id );
            $current_status = (string) get_post_meta( $target_id, self::META_STATUS, true ) ?: 'current';
            if ( $suggested_status && $suggested_status !== $current_status ) {
                update_post_meta( $target_id, self::META_RELATION_CONFLICT, $suggested_status );
            } else {
                delete_post_meta( $target_id, self::META_RELATION_CONFLICT );
            }
            self::rebuild_impact( $target_id );
        }
    }

    private static function normalize_family( $source_id ) {
        $family_id = absint( get_post_meta( $source_id, self::META_FAMILY_ID, true ) );
        if ( $family_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $family_id ) ) {
            $family_id = 0;
        }

        foreach ( self::relations( $source_id ) as $relation ) {
            if ( 'version-of' !== ( $relation['relation'] ?? '' ) ) {
                continue;
            }
            $target_id = absint( $relation['target_id'] ?? 0 );
            if ( $target_id && $target_id !== $source_id ) {
                $target_family = absint( get_post_meta( $target_id, self::META_FAMILY_ID, true ) );
                $family_id = $target_family ?: $target_id;
                break;
            }
        }

        if ( $family_id === $source_id ) {
            $family_id = 0;
        }
        self::update_or_delete_meta( $source_id, self::META_FAMILY_ID, $family_id );
    }

    public function filter_source_data( $data, $source_id, $include_private ) {
        if ( ! is_array( $data ) ) {
            return $data;
        }
        $data['integrity'] = self::get_integrity_data( $source_id, $include_private );
        return $data;
    }

    public static function get_integrity_data( $source_id, $include_private = false ) {
        $post = get_post( $source_id );
        if ( ! $post || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $source_id ) ) {
            return array();
        }

        $status = (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current';
        $suggested_status = self::suggested_status_from_incoming( $source_id );
        $relationship_conflict = $suggested_status && $suggested_status !== $status;
        $severity = self::severity_for_status( $status );
        if ( $relationship_conflict ) {
            $severity = self::more_severe( $severity, self::severity_for_status( $suggested_status ) );
        }
        $recommended_id = self::resolve_recommended_source( $source_id );
        $family_id = absint( get_post_meta( $source_id, self::META_FAMILY_ID, true ) );
        $public_notice = '1' === get_post_meta( $source_id, self::META_PUBLIC_NOTICE, true );
        $relations = self::relations( $source_id );
        $incoming = self::incoming_relations( $source_id );

        if ( ! $include_private ) {
            $relations = array_values(
                array_filter(
                    $relations,
                    static function ( $relation ) {
                        return ! empty( $relation['public'] )
                            && 'publish' === get_post_status( absint( $relation['target_id'] ?? 0 ) );
                    }
                )
            );
            $incoming = array_values(
                array_filter(
                    $incoming,
                    static function ( $relation ) {
                        return ! empty( $relation['public'] )
                            && 'publish' === get_post_status( absint( $relation['source_id'] ?? 0 ) );
                    }
                )
            );
        }

        $data = array(
            'schema'             => self::INTEGRITY_SCHEMA,
            'source_id'          => $source_id,
            'status'             => $status,
            'status_label'       => self::integrity_status_options()[ $status ] ?? ucfirst( str_replace( '-', ' ', $status ) ),
            'severity'           => $severity,
            'requires_review'    => self::requires_review( $status ) || $relationship_conflict,
            'suggested_status'   => $suggested_status,
            'suggested_label'    => $suggested_status ? ( self::integrity_status_options()[ $suggested_status ] ?? $suggested_status ) : '',
            'relationship_conflict' => (bool) $relationship_conflict,
            'public_notice'      => $public_notice,
            'notice_date'        => (string) get_post_meta( $source_id, self::META_NOTICE_DATE, true ),
            'notice_url'         => (string) get_post_meta( $source_id, self::META_NOTICE_URL, true ),
            'reason'             => $public_notice || $include_private ? (string) get_post_meta( $source_id, self::META_REASON, true ) : '',
            'version_label'      => (string) get_post_meta( $source_id, self::META_VERSION_LABEL, true ),
            'version_number'     => (string) get_post_meta( $source_id, self::META_VERSION_NUMBER, true ),
            'release_date'       => (string) get_post_meta( $source_id, self::META_RELEASE_DATE, true ),
            'family_id'          => $family_id,
            'recommended_id'     => $recommended_id,
            'recommended_title'  => $recommended_id ? get_the_title( $recommended_id ) : '',
            'recommended_url'    => $recommended_id && 'publish' === get_post_status( $recommended_id ) ? get_permalink( $recommended_id ) : '',
            'relations'          => $relations,
            'incoming_relations' => $incoming,
            'review_status'      => (string) get_post_meta( $source_id, self::META_REVIEW_STATUS, true ) ?: 'unreviewed',
            'last_reviewed'      => (string) get_post_meta( $source_id, self::META_LAST_REVIEWED, true ),
            'status_changed_at'  => (string) get_post_meta( $source_id, self::META_STATUS_CHANGED_AT, true ),
        );

        if ( $include_private ) {
            $data['snapshots'] = self::snapshots( $source_id );
            $data['impact'] = self::impact_report( $source_id, true );
            $data['last_reviewed_by'] = absint( get_post_meta( $source_id, self::META_LAST_REVIEWED_BY, true ) );
        }
        return $data;
    }

    public static function resolve_recommended_source( $source_id ) {
        $visited = array();
        $current = absint( $source_id );

        for ( $depth = 0; $depth < 20; $depth++ ) {
            if ( ! $current || isset( $visited[ $current ] ) ) {
                return 0;
            }
            $visited[ $current ] = true;

            $explicit = absint( get_post_meta( $current, self::META_RECOMMENDED_ID, true ) );
            if (
                $explicit
                && $explicit !== $current
                && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $explicit )
            ) {
                $current = $explicit;
                continue;
            }

            $replacement = 0;
            foreach ( self::incoming_relations( $current ) as $incoming ) {
                if (
                    in_array( sanitize_key( $incoming['relation'] ?? '' ), array( 'supersedes', 'corrects', 'replaces' ), true )
                    && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( absint( $incoming['source_id'] ?? 0 ) )
                ) {
                    $replacement = absint( $incoming['source_id'] );
                    break;
                }
            }
            if ( $replacement && $replacement !== $current ) {
                $current = $replacement;
                continue;
            }

            return $current === absint( $source_id ) ? 0 : $current;
        }
        return 0;
    }

    public static function version_family( $source_id, $include_private = false ) {
        $source_id = absint( $source_id );
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            return array();
        }
        $family_id = absint( get_post_meta( $source_id, self::META_FAMILY_ID, true ) ) ?: $source_id;

        $ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'meta_key'       => self::META_FAMILY_ID,
                'meta_value'     => $family_id,
                'orderby'        => 'date',
                'order'          => 'ASC',
            )
        );
        $ids[] = $family_id;
        $ids[] = $source_id;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

        $items = array();
        foreach ( $ids as $id ) {
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $id ) ) {
                continue;
            }
            if ( ! $include_private && 'publish' !== get_post_status( $id ) ) {
                continue;
            }
            $integrity = self::get_integrity_data( $id, $include_private );
            if ( ! $integrity ) {
                continue;
            }
            $items[] = array(
                'source_id'       => $id,
                'title'           => get_the_title( $id ),
                'version_label'   => $integrity['version_label'],
                'version_number'  => $integrity['version_number'],
                'release_date'    => $integrity['release_date'],
                'status'          => $integrity['status'],
                'severity'        => $integrity['severity'],
                'citation'        => SC_Library_Citation_Source_Manager::format_citation( $id, 'harvard', 'reference' ),
                'url'             => 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '',
                'modified_gmt'    => get_post_modified_time( 'c', true, $id ),
            );
        }

        usort(
            $items,
            static function ( $left, $right ) {
                $left_date = strtotime( $left['release_date'] ?: $left['modified_gmt'] ) ?: 0;
                $right_date = strtotime( $right['release_date'] ?: $right['modified_gmt'] ) ?: 0;
                if ( $left_date !== $right_date ) {
                    return $left_date <=> $right_date;
                }
                return strnatcasecmp( $left['version_number'], $right['version_number'] );
            }
        );
        return $items;
    }

    public static function snapshots( $source_id ) {
        $snapshots = get_post_meta( $source_id, self::META_SNAPSHOTS, true );
        return is_array( $snapshots ) ? array_slice( $snapshots, -self::MAX_SNAPSHOTS ) : array();
    }

    public static function impact_report( $source_id, $include_private = false ) {
        $stored = get_post_meta( $source_id, self::META_IMPACT, true );
        if ( is_array( $stored ) && ! empty( $stored['checked_at'] ) ) {
            if ( $include_private ) {
                return $stored;
            }
            return self::public_impact_report( $stored );
        }
        return self::build_impact_report( $source_id, $include_private );
    }

    public static function rebuild_impact( $source_id ) {
        $report = self::build_impact_report( $source_id, true );
        update_post_meta( $source_id, self::META_IMPACT, $report );
        self::propagate_impact( $source_id, $report );
        return $report;
    }

    private static function build_impact_report( $source_id, $include_private ) {
        $status = (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current';
        $suggested_status = self::suggested_status_from_incoming( $source_id );
        $relationship_conflict = $suggested_status && $suggested_status !== $status;
        $severity = self::severity_for_status( $status );
        if ( $relationship_conflict ) {
            $severity = self::more_severe( $severity, self::severity_for_status( $suggested_status ) );
        }
        $project_ids = self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true ) );
        $document_ids = self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_RELATED_DOCUMENT_IDS, true ) );
        $evidence_ids = array();
        $claim_ids = array();

        if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
            $evidence_ids = SC_Library_Evidence_Claim_Linking::evidence_ids_for_source( $source_id, true );
            foreach ( $evidence_ids as $note_id ) {
                $data = SC_Library_Evidence_Claim_Linking::get_evidence_data( $note_id, true );
                foreach ( (array) ( $data['claim_links'] ?? array() ) as $link ) {
                    $claim_ids[] = absint( $link['claim_id'] ?? 0 );
                }
            }
        }

        $claim_ids = array_values( array_unique( array_filter( $claim_ids ) ) );
        $recommended_id = self::resolve_recommended_source( $source_id );
        $report = array(
            'schema'             => self::IMPACT_SCHEMA,
            'source_id'          => absint( $source_id ),
            'status'             => $status,
            'severity'           => $severity,
            'requires_review'    => self::requires_review( $status ) || $relationship_conflict,
            'suggested_status'   => $suggested_status,
            'relationship_conflict' => (bool) $relationship_conflict,
            'recommended_id'     => $recommended_id,
            'project_ids'        => $project_ids,
            'document_ids'       => $document_ids,
            'evidence_note_ids'  => array_values( array_map( 'absint', $evidence_ids ) ),
            'claim_ids'          => $claim_ids,
            'public_projects'    => count( array_filter( $project_ids, array( __CLASS__, 'project_is_public' ) ) ),
            'public_documents'   => count( array_filter( $document_ids, static function ( $id ) { return 'publish' === get_post_status( $id ); } ) ),
            'public_evidence'    => class_exists( 'SC_Library_Evidence_Claim_Linking' )
                ? count( array_filter( $evidence_ids, array( 'SC_Library_Evidence_Claim_Linking', 'evidence_is_public' ) ) )
                : 0,
            'checked_at'         => current_time( 'mysql' ),
        );
        return $include_private ? $report : self::public_impact_report( $report );
    }

    private static function public_impact_report( $report ) {
        return array(
            'schema'          => self::IMPACT_SCHEMA,
            'source_id'       => absint( $report['source_id'] ?? 0 ),
            'status'          => sanitize_key( $report['status'] ?? 'current' ),
            'severity'        => sanitize_key( $report['severity'] ?? 'none' ),
            'requires_review' => ! empty( $report['requires_review'] ),
            'recommended_id'  => absint( $report['recommended_id'] ?? 0 ),
            'public_projects' => absint( $report['public_projects'] ?? 0 ),
            'public_documents'=> absint( $report['public_documents'] ?? 0 ),
            'public_evidence' => absint( $report['public_evidence'] ?? 0 ),
            'checked_at'      => sanitize_text_field( $report['checked_at'] ?? '' ),
        );
    }

    private static function propagate_impact( $source_id, $report ) {
        $impact = array(
            'schema'          => self::IMPACT_SCHEMA,
            'source_id'       => absint( $source_id ),
            'status'          => sanitize_key( $report['status'] ?? 'current' ),
            'severity'        => sanitize_key( $report['severity'] ?? 'none' ),
            'requires_review' => ! empty( $report['requires_review'] ),
            'recommended_id'  => absint( $report['recommended_id'] ?? 0 ),
            'checked_at'      => sanitize_text_field( $report['checked_at'] ?? current_time( 'mysql' ) ),
        );

        foreach ( self::id_list( $report['evidence_note_ids'] ?? array() ) as $note_id ) {
            if ( ! $impact['requires_review'] ) {
                delete_post_meta( $note_id, self::META_EVIDENCE_IMPACT );
            } else {
                update_post_meta( $note_id, self::META_EVIDENCE_IMPACT, $impact );
            }
        }

        foreach ( self::id_list( $report['claim_ids'] ?? array() ) as $claim_id ) {
            $impacts = get_post_meta( $claim_id, self::META_CLAIM_IMPACTS, true );
            $impacts = is_array( $impacts ) ? $impacts : array();
            if ( ! $impact['requires_review'] ) {
                unset( $impacts[ $source_id ] );
            } else {
                $impacts[ $source_id ] = $impact;
            }
            $impacts ? update_post_meta( $claim_id, self::META_CLAIM_IMPACTS, $impacts ) : delete_post_meta( $claim_id, self::META_CLAIM_IMPACTS );
        }

        foreach ( self::id_list( $report['project_ids'] ?? array() ) as $project_id ) {
            $impacts = get_post_meta( $project_id, self::META_PROJECT_IMPACTS, true );
            $impacts = is_array( $impacts ) ? $impacts : array();
            if ( ! $impact['requires_review'] ) {
                unset( $impacts[ $source_id ] );
            } else {
                $impacts[ $source_id ] = $impact;
            }
            $impacts ? update_post_meta( $project_id, self::META_PROJECT_IMPACTS, $impacts ) : delete_post_meta( $project_id, self::META_PROJECT_IMPACTS );
        }
    }

    private static function project_is_public( $project_id ) {
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && 'publish' === get_post_status( $project_id )
            && 'public' === get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
    }

    public function render_source_integrity_box( $post ) {
        wp_nonce_field( 'sc_library_save_source_integrity_' . $post->ID, 'sc_library_source_integrity_nonce' );
        $status = (string) get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'current';
        $review_status = (string) get_post_meta( $post->ID, self::META_REVIEW_STATUS, true ) ?: 'unreviewed';
        $family_id = absint( get_post_meta( $post->ID, self::META_FAMILY_ID, true ) );
        $recommended_id = absint( get_post_meta( $post->ID, self::META_RECOMMENDED_ID, true ) );
        $relations = self::relations( $post->ID );
        $sources = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 300,
                'post__not_in'   => array( $post->ID ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <div class="sc-integrity-editor" data-sc-integrity-editor>
            <div class="sc-integrity-field-grid">
                <label><strong><?php esc_html_e( 'Version label', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_source_version_label" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VERSION_LABEL, true ) ); ?>" placeholder="<?php esc_attr_e( 'Second edition, 2026 revision, v4.2', 'sustainable-catalyst-library' ); ?>"></label>
                <label><strong><?php esc_html_e( 'Version number', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_source_version_number" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VERSION_NUMBER, true ) ); ?>" placeholder="2.1"></label>
                <label><strong><?php esc_html_e( 'Version release date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_source_release_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_RELEASE_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Version family root', 'sustainable-catalyst-library' ); ?></strong><select name="sc_source_version_family_id"><option value="0"><?php esc_html_e( 'This Source is the family root', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>" <?php selected( $family_id, $source->ID ); ?>><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Integrity status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_source_integrity_status"><?php foreach ( self::integrity_status_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Recommended replacement', 'sustainable-catalyst-library' ); ?></strong><select name="sc_source_recommended_replacement_id"><option value="0"><?php esc_html_e( 'No explicit replacement', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>" <?php selected( $recommended_id, $source->ID ); ?>><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Integrity review status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_source_integrity_review_status"><?php foreach ( self::review_status_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $review_status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Notice date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_source_integrity_notice_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_NOTICE_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Official notice URL', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_source_integrity_notice_url" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_NOTICE_URL, true ) ); ?>"></label>
            </div>
            <label class="sc-integrity-public-notice"><input type="checkbox" name="sc_source_integrity_public_notice" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PUBLIC_NOTICE, true ) ); ?>> <?php esc_html_e( 'Display this integrity notice on the public Source page and public project bibliographies.', 'sustainable-catalyst-library' ); ?></label>
            <label><strong><?php esc_html_e( 'Integrity notice, reason, or reviewer explanation', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_source_integrity_reason" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_REASON, true ) ); ?></textarea></label>

            <h3><?php esc_html_e( 'Version and integrity relationships', 'sustainable-catalyst-library' ); ?></h3>
            <p><?php esc_html_e( 'Relationships are directional. For example, the newer Source should use Supersedes and point to the older Source.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-integrity-relation-rows" data-sc-integrity-relation-rows>
                <?php foreach ( $relations as $index => $relation ) : ?>
                    <div class="sc-integrity-relation-row" data-sc-integrity-relation-row>
                        <label><span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span><select name="sc_source_integrity_relations[<?php echo esc_attr( $index ); ?>][relation]"><?php foreach ( self::relation_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $relation['relation'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Related Source', 'sustainable-catalyst-library' ); ?></span><select name="sc_source_integrity_relations[<?php echo esc_attr( $index ); ?>][target_id]"><option value="0"><?php esc_html_e( 'Select Source', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>" <?php selected( absint( $relation['target_id'] ), $source->ID ); ?>><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Effective date', 'sustainable-catalyst-library' ); ?></span><input type="date" name="sc_source_integrity_relations[<?php echo esc_attr( $index ); ?>][effective_date]" value="<?php echo esc_attr( $relation['effective_date'] ); ?>"></label>
                        <label class="sc-integrity-relation-note"><span><?php esc_html_e( 'Relationship note', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_source_integrity_relations[<?php echo esc_attr( $index ); ?>][note]" value="<?php echo esc_attr( $relation['note'] ); ?>"></label>
                        <label class="sc-integrity-relation-public"><input type="checkbox" name="sc_source_integrity_relations[<?php echo esc_attr( $index ); ?>][public]" value="1" <?php checked( ! empty( $relation['public'] ) ); ?>> <?php esc_html_e( 'Public', 'sustainable-catalyst-library' ); ?></label>
                        <button type="button" class="button" data-sc-remove-integrity-relation><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" data-sc-add-integrity-relation><?php esc_html_e( 'Add Relationship', 'sustainable-catalyst-library' ); ?></button>
            <template data-sc-integrity-relation-template>
                <div class="sc-integrity-relation-row" data-sc-integrity-relation-row>
                    <label><span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span><select data-name="relation"><?php foreach ( self::relation_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Related Source', 'sustainable-catalyst-library' ); ?></span><select data-name="target_id"><option value="0"><?php esc_html_e( 'Select Source', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>"><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Effective date', 'sustainable-catalyst-library' ); ?></span><input type="date" data-name="effective_date"></label>
                    <label class="sc-integrity-relation-note"><span><?php esc_html_e( 'Relationship note', 'sustainable-catalyst-library' ); ?></span><input type="text" data-name="note"></label>
                    <label class="sc-integrity-relation-public"><input type="checkbox" data-name="public" value="1"> <?php esc_html_e( 'Public', 'sustainable-catalyst-library' ); ?></label>
                    <button type="button" class="button" data-sc-remove-integrity-relation><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                </div>
            </template>
        </div>
        <?php
    }

    public function render_source_impact_box( $post ) {
        $report = self::impact_report( $post->ID, true );
        $integrity = self::get_integrity_data( $post->ID, true );
        ?>
        <div class="sc-integrity-impact-summary" data-source-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><span class="sc-integrity-badge severity-<?php echo esc_attr( $integrity['severity'] ); ?>"><?php echo esc_html( $integrity['status_label'] ); ?></span></p>
            <dl>
                <div><dt><?php esc_html_e( 'Research Projects', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['project_ids'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['document_ids'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Evidence Notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['evidence_note_ids'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['claim_ids'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Saved versions', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( self::snapshots( $post->ID ) ) ); ?></dd></div>
            </dl>
            <?php if ( ! empty( $integrity['recommended_id'] ) ) : ?><p><strong><?php esc_html_e( 'Recommended:', 'sustainable-catalyst-library' ); ?></strong> <a href="<?php echo esc_url( get_edit_post_link( $integrity['recommended_id'], 'raw' ) ); ?>"><?php echo esc_html( $integrity['recommended_title'] ); ?></a></p><?php endif; ?>
            <button type="button" class="button" data-sc-rebuild-source-integrity><?php esc_html_e( 'Rebuild Impact and Relationships', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-source-integrity-status aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_project_integrity_box( $post ) {
        wp_nonce_field( 'sc_library_save_project_integrity_' . $post->ID, 'sc_library_project_integrity_nonce' );
        $report = self::project_integrity_report( $post->ID, true );
        $acks = self::project_acknowledgements( $post->ID );
        ?>
        <div class="sc-project-integrity-review">
            <header>
                <div><p class="sc-connected-kicker"><?php esc_html_e( 'Research integrity review', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( sprintf( _n( '%d affected Source', '%d affected Sources', $report['affected_count'], 'sustainable-catalyst-library' ), $report['affected_count'] ) ); ?></h3></div>
                <div class="sc-project-integrity-counts"><span><?php echo esc_html( $report['critical_count'] ); ?> <?php esc_html_e( 'critical', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( $report['pending_count'] ); ?> <?php esc_html_e( 'pending', 'sustainable-catalyst-library' ); ?></span></div>
            </header>
            <?php if ( $report['sources'] ) : ?>
                <div class="sc-project-integrity-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Integrity status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Replacement', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Project decision', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Reviewer note', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $report['sources'] as $item ) : $ack = $acks[ $item['source_id'] ] ?? array(); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url( get_edit_post_link( $item['source_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><small><?php echo esc_html( $item['citation'] ); ?></small></td>
                                    <td><span class="sc-integrity-badge severity-<?php echo esc_attr( $item['severity'] ); ?>"><?php echo esc_html( $item['status_label'] ); ?></span></td>
                                    <td><?php if ( $item['recommended_id'] ) : ?><a href="<?php echo esc_url( get_edit_post_link( $item['recommended_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['recommended_title'] ); ?></a><?php else : ?>—<?php endif; ?></td>
                                    <td><select name="sc_project_integrity_acks[<?php echo esc_attr( $item['source_id'] ); ?>][status]"><?php foreach ( self::acknowledgement_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $ack['status'] ?? 'pending', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
                                    <td><input type="text" name="sc_project_integrity_acks[<?php echo esc_attr( $item['source_id'] ); ?>][note]" value="<?php echo esc_attr( $ack['note'] ?? '' ); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p><?php esc_html_e( 'No included project Sources currently require integrity review.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_project_acknowledgements( $post_id, $post, $update ) {
        if (
            ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_project_integrity_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_project_integrity_nonce'] ) ),
                'sc_library_save_project_integrity_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        $raw = wp_unslash( $_POST['sc_project_integrity_acks'] ?? array() );
        $old = self::project_acknowledgements( $post_id );
        $acks = array();
        foreach ( (array) $raw as $source_id => $item ) {
            $source_id = absint( $source_id );
            if (
                ! $source_id
                || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id )
                || ! is_array( $item )
            ) {
                continue;
            }
            $previous = $old[ $source_id ] ?? array();
            $acks[ $source_id ] = array(
                'source_id'  => $source_id,
                'status'     => self::allowed_value( $item['status'] ?? 'pending', self::acknowledgement_options(), 'pending' ),
                'note'       => sanitize_text_field( $item['note'] ?? '' ),
                'created_at' => sanitize_text_field( $previous['created_at'] ?? current_time( 'mysql' ) ),
                'created_by' => absint( $previous['created_by'] ?? get_current_user_id() ),
                'updated_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id(),
            );
            if ( count( $acks ) >= self::MAX_PROJECT_ACKS ) {
                break;
            }
        }
        $acks ? update_post_meta( $post_id, self::META_PROJECT_ACKS, $acks ) : delete_post_meta( $post_id, self::META_PROJECT_ACKS );
    }

    public static function project_acknowledgements( $project_id ) {
        $acks = get_post_meta( $project_id, self::META_PROJECT_ACKS, true );
        return is_array( $acks ) ? $acks : array();
    }

    public static function project_integrity_report( $project_id, $include_private = false ) {
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return array();
        }
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $project_id ) ) {
            return array();
        }

        $entries = class_exists( 'SC_Library_Connected_Research_Environment' )
            ? SC_Library_Connected_Research_Environment::source_entries( $project_id, $include_private )
            : array();
        if ( ! $entries ) {
            foreach ( self::id_list( get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true ) ) as $source_id ) {
                if ( ! $include_private && 'publish' !== get_post_status( $source_id ) ) {
                    continue;
                }
                $entries[] = array(
                    'source_id' => $source_id,
                    'inclusion' => 'included',
                    'citation'  => SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'reference' ),
                );
            }
        }

        $acks = $include_private ? self::project_acknowledgements( $project_id ) : array();
        $sources = array();
        $critical = 0;
        $pending = 0;
        foreach ( $entries as $entry ) {
            if ( 'included' !== ( $entry['inclusion'] ?? 'included' ) ) {
                continue;
            }
            $source_id = absint( $entry['source_id'] ?? 0 );
            $integrity = self::get_integrity_data( $source_id, $include_private );
            if ( ! $integrity || ! $integrity['requires_review'] ) {
                continue;
            }
            if ( 'critical' === $integrity['severity'] ) {
                $critical++;
            }
            $ack = $acks[ $source_id ] ?? array();
            if ( ! $ack || 'pending' === ( $ack['status'] ?? 'pending' ) ) {
                $pending++;
            }
            $sources[] = array(
                'source_id'          => $source_id,
                'title'              => get_the_title( $source_id ),
                'citation'           => $entry['citation'] ?? SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'reference' ),
                'status'             => $integrity['status'],
                'status_label'       => $integrity['status_label'],
                'severity'           => $integrity['severity'],
                'reason'             => $integrity['reason'],
                'notice_date'        => $integrity['notice_date'],
                'notice_url'         => $integrity['notice_url'],
                'recommended_id'     => $integrity['recommended_id'],
                'recommended_title'  => $integrity['recommended_title'],
                'recommended_url'    => $integrity['recommended_url'],
                'acknowledgement'     => $include_private ? $ack : array(),
            );
        }

        return array(
            'schema'         => self::PROJECT_REPORT_SCHEMA,
            'project_id'     => absint( $project_id ),
            'affected_count' => count( $sources ),
            'critical_count' => $critical,
            'pending_count'  => $pending,
            'sources'        => $sources,
            'checked_at'     => current_time( 'mysql' ),
        );
    }

    public static function render_public_integrity_notice( $source_id ) {
        $integrity = self::get_integrity_data( $source_id, false );
        if ( ! $integrity || ( ! $integrity['public_notice'] && ! in_array( $integrity['severity'], array( 'high', 'critical' ), true ) ) ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-source-versioning-integrity' );
        ob_start();
        ?>
        <section class="sc-public-integrity-notice severity-<?php echo esc_attr( $integrity['severity'] ); ?>" role="<?php echo in_array( $integrity['severity'], array( 'high', 'critical' ), true ) ? 'alert' : 'status'; ?>" aria-labelledby="sc-integrity-heading-<?php echo esc_attr( $source_id ); ?>">
            <div>
                <p class="sc-source-record__kicker"><?php esc_html_e( 'Research integrity notice', 'sustainable-catalyst-library' ); ?></p>
                <h2 id="sc-integrity-heading-<?php echo esc_attr( $source_id ); ?>"><?php echo esc_html( $integrity['status_label'] ); ?></h2>
                <?php if ( $integrity['reason'] ) : ?><p><?php echo esc_html( $integrity['reason'] ); ?></p><?php endif; ?>
                <p><?php esc_html_e( 'The historical citation is preserved. Review this notice and any recommended replacement before relying on the Source.', 'sustainable-catalyst-library' ); ?></p>
            </div>
            <div class="sc-public-integrity-notice__actions">
                <?php if ( $integrity['recommended_url'] ) : ?><a href="<?php echo esc_url( $integrity['recommended_url'] ); ?>"><?php echo esc_html( sprintf( __( 'Use recommended Source: %s', 'sustainable-catalyst-library' ), $integrity['recommended_title'] ) ); ?></a><?php endif; ?>
                <?php if ( $integrity['notice_url'] ) : ?><a href="<?php echo esc_url( $integrity['notice_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open official notice', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
                <?php if ( $integrity['notice_date'] ) : ?><span><?php echo esc_html( $integrity['notice_date'] ); ?></span><?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_bibliography_integrity_badge( $source_id ) {
        $integrity = self::get_integrity_data( $source_id, false );
        if ( ! $integrity || ! $integrity['requires_review'] || ( ! $integrity['public_notice'] && ! in_array( $integrity['severity'], array( 'high', 'critical' ), true ) ) ) {
            return '';
        }
        ob_start();
        ?>
        <div class="sc-bibliography-integrity-warning severity-<?php echo esc_attr( $integrity['severity'] ); ?>">
            <strong><?php echo esc_html( $integrity['status_label'] ); ?></strong>
            <?php if ( $integrity['recommended_url'] ) : ?><a href="<?php echo esc_url( $integrity['recommended_url'] ); ?>"><?php esc_html_e( 'Review recommended replacement', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_source_integrity( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'include_private' => 'false' ), $atts, 'sc_source_integrity' );
        $source_id = absint( $atts['id'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $source_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $integrity = self::get_integrity_data( $source_id, $include_private );
        if ( ! $integrity ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-source-versioning-integrity' );
        return self::render_integrity_card( $integrity, $include_private );
    }

    public function shortcode_project_integrity( $atts ) {
        $atts = shortcode_atts( array( 'project' => '', 'include_private' => 'false' ), $atts, 'sc_project_source_integrity' );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $project_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $report = self::project_integrity_report( $project_id, $include_private );
        if ( ! $report ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-source-versioning-integrity' );
        ob_start();
        ?>
        <section class="sc-public-project-integrity">
            <header><p class="sc-connected-kicker"><?php esc_html_e( 'Source integrity review', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( sprintf( _n( '%d Source requires review', '%d Sources require review', $report['affected_count'], 'sustainable-catalyst-library' ), $report['affected_count'] ) ); ?></h2></header>
            <?php foreach ( $report['sources'] as $item ) : ?>
                <article class="severity-<?php echo esc_attr( $item['severity'] ); ?>">
                    <h3><?php echo esc_html( $item['title'] ); ?></h3>
                    <p><?php echo esc_html( $item['citation'] ); ?></p>
                    <p><strong><?php echo esc_html( $item['status_label'] ); ?></strong><?php if ( $item['reason'] ) : ?> — <?php echo esc_html( $item['reason'] ); ?><?php endif; ?></p>
                    <?php if ( $item['recommended_url'] ) : ?><p><a href="<?php echo esc_url( $item['recommended_url'] ); ?>"><?php echo esc_html( sprintf( __( 'Recommended replacement: %s', 'sustainable-catalyst-library' ), $item['recommended_title'] ) ); ?></a></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_integrity_card( $integrity, $include_private ) {
        ob_start();
        ?>
        <section class="sc-integrity-card severity-<?php echo esc_attr( $integrity['severity'] ); ?>">
            <header>
                <div><p class="sc-connected-kicker"><?php esc_html_e( 'Source integrity', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $integrity['status_label'] ); ?></h2></div>
                <?php if ( $integrity['version_label'] || $integrity['version_number'] ) : ?><span><?php echo esc_html( trim( $integrity['version_label'] . ' ' . $integrity['version_number'] ) ); ?></span><?php endif; ?>
            </header>
            <?php if ( $integrity['reason'] ) : ?><p><?php echo esc_html( $integrity['reason'] ); ?></p><?php endif; ?>
            <?php if ( $integrity['recommended_url'] ) : ?><p><a href="<?php echo esc_url( $integrity['recommended_url'] ); ?>"><?php echo esc_html( sprintf( __( 'Recommended replacement: %s', 'sustainable-catalyst-library' ), $integrity['recommended_title'] ) ); ?></a></p><?php endif; ?>
            <?php if ( $include_private && ! empty( $integrity['impact'] ) ) : ?><dl><div><dt><?php esc_html_e( 'Projects', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $integrity['impact']['project_ids'] ?? array() ) ); ?></dd></div><div><dt><?php esc_html_e( 'Evidence Notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $integrity['impact']['evidence_note_ids'] ?? array() ) ); ?></dd></div><div><dt><?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $integrity['impact']['claim_ids'] ?? array() ) ); ?></dd></div></dl><?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function render_integrity_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $state = self::scan_state();
        $alerts = self::integrity_alerts( array( 'include_private' => true, 'per_page' => 100 ) );
        $counts = array_fill_keys( array_keys( self::integrity_status_options() ), 0 );
        foreach ( $alerts['items'] as $item ) {
            $counts[ $item['status'] ] = ( $counts[ $item['status'] ] ?? 0 ) + 1;
        }
        ?>
        <div class="wrap sc-source-integrity-workspace">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge Library v3.1.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Source Versioning, Supersession, and Research Integrity', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Track Source versions and correction histories, surface retractions and supersession, identify affected projects and evidence, and preserve historical citations without silently rewriting research records.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-integrity-workspace-metrics">
                <article><strong><?php echo esc_html( $alerts['total'] ); ?></strong><span><?php esc_html_e( 'Sources requiring review', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['retracted'] + $counts['withdrawn'] ); ?></strong><span><?php esc_html_e( 'Retracted or withdrawn', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['expression-of-concern'] ); ?></strong><span><?php esc_html_e( 'Expressions of concern', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['superseded'] + $counts['corrected'] + $counts['deprecated'] ); ?></strong><span><?php esc_html_e( 'Replacement review', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-integrity-scan-panel">
                <div>
                    <h2><?php esc_html_e( 'Integrity index scan', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d Sources processed · %3$d warnings', 'sustainable-catalyst-library' ), $state['processed'], $state['total'], count( $state['failures'] ) ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-integrity-scan-state><?php echo esc_html( ucfirst( $state['status'] ) ); ?></span></p>
                </div>
                <button type="button" class="button button-primary" data-sc-run-integrity-scan><?php esc_html_e( 'Run Next Scan Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-integrity-scan-status aria-live="polite"></div>
            </section>

            <section>
                <h2><?php esc_html_e( 'Integrity alerts', 'sustainable-catalyst-library' ); ?></h2>
                <?php if ( $alerts['items'] ) : ?>
                    <table class="widefat striped sc-integrity-alert-table">
                        <thead><tr><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Recommended replacement', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Impact', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $alerts['items'] as $item ) : ?>
                                <tr data-source-id="<?php echo esc_attr( $item['source_id'] ); ?>">
                                    <td><a href="<?php echo esc_url( get_edit_post_link( $item['source_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><small><?php echo esc_html( $item['citation'] ); ?></small></td>
                                    <td><span class="sc-integrity-badge severity-<?php echo esc_attr( $item['severity'] ); ?>"><?php echo esc_html( $item['status_label'] ); ?></span></td>
                                    <td><?php echo esc_html( trim( $item['version_label'] . ' ' . $item['version_number'] ) ?: '—' ); ?></td>
                                    <td><?php echo $item['recommended_id'] ? '<a href="' . esc_url( get_edit_post_link( $item['recommended_id'], 'raw' ) ) . '">' . esc_html( $item['recommended_title'] ) . '</a>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td><?php echo esc_html( sprintf( __( '%1$d projects · %2$d evidence · %3$d claims', 'sustainable-catalyst-library' ), count( $item['impact']['project_ids'] ?? array() ), count( $item['impact']['evidence_note_ids'] ?? array() ), count( $item['impact']['claim_ids'] ?? array() ) ) ); ?></td>
                                    <td><button type="button" class="button button-small" data-sc-rebuild-source-integrity><?php esc_html_e( 'Rebuild', 'sustainable-catalyst-library' ); ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'No Sources currently require integrity review.', 'sustainable-catalyst-library' ); ?></p>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public function schedule_scan() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 600, 'hourly', self::CRON_HOOK );
        }
        $state = get_option( self::OPTION_SCAN_STATE, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_SCAN_STATE, self::default_scan_state(), false );
        } elseif ( 'complete' === ( $state['status'] ?? '' ) ) {
            $counts = wp_count_posts( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE );
            $total = 0;
            foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
                $total += absint( $counts->{$status} ?? 0 );
            }
            if ( $total > absint( $state['processed'] ?? 0 ) ) {
                $state['status'] = 'pending';
                $state['total'] = $total;
                update_option( self::OPTION_SCAN_STATE, $state, false );
            }
        }
    }

    public function run_scheduled_scan() {
        $state = self::scan_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_scan_batch( self::SCAN_BATCH );
        }
    }

    public static function run_scan_batch( $limit = self::SCAN_BATCH ) {
        global $wpdb;

        if ( get_transient( self::TRANSIENT_SCAN_LOCK ) ) {
            return new WP_Error( 'integrity_scan_locked', __( 'Another Source integrity scan is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_SCAN_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );

        $state = self::scan_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );

        try {
            $post_type = SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE;
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})";
            $state['total'] = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $post_type ), $statuses ) ) ) );

            $query_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
            $params = array_merge( array( $post_type ), $statuses, array( absint( $state['cursor'] ), max( 1, min( 100, absint( $limit ) ) ) ) );
            $ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $query_sql, $params ) ) );

            if ( ! $ids ) {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
                update_option( self::OPTION_SCAN_STATE, $state, false );
                delete_transient( self::TRANSIENT_SCAN_LOCK );
                return $state;
            }

            foreach ( $ids as $source_id ) {
                try {
                    self::rebuild_source_integrity( $source_id );
                } catch ( Throwable $error ) {
                    $state['failures'][] = array(
                        'source_id' => $source_id,
                        'message'   => sanitize_text_field( $error->getMessage() ),
                        'time'      => current_time( 'mysql' ),
                    );
                    $state['failures'] = array_slice( $state['failures'], -100 );
                }
                $state['cursor'] = $source_id;
                $state['processed']++;
            }

            $state['status'] = $state['processed'] >= $state['total'] ? 'complete' : 'pending';
            if ( 'complete' === $state['status'] ) {
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_SCAN_STATE, $state, false );
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_SCAN_STATE, $state, false );
            delete_transient( self::TRANSIENT_SCAN_LOCK );
            return new WP_Error( 'integrity_scan_failed', $error->getMessage(), array( 'status' => 500 ) );
        }

        delete_transient( self::TRANSIENT_SCAN_LOCK );
        return $state;
    }

    public static function rebuild_source_integrity( $source_id ) {
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            return new WP_Error( 'invalid_integrity_source', __( 'The Source record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        self::rebuild_incoming_index( $source_id );
        self::normalize_family( $source_id );
        $suggested_status = self::suggested_status_from_incoming( $source_id );
        $current_status = (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current';
        if ( $suggested_status && $suggested_status !== $current_status ) {
            update_post_meta( $source_id, self::META_RELATION_CONFLICT, $suggested_status );
        } else {
            delete_post_meta( $source_id, self::META_RELATION_CONFLICT );
        }
        $snapshot = self::structured_snapshot( $source_id );
        if ( ! empty( $snapshot['hash'] ) ) {
            update_post_meta( $source_id, self::META_CURRENT_HASH, $snapshot['hash'] );
        }
        return self::rebuild_impact( $source_id );
    }

    private static function rebuild_incoming_index( $target_id ) {
        $source_ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_RELATIONS,
                'meta_value'     => 'i:' . absint( $target_id ) . ';',
                'meta_compare'   => 'LIKE',
            )
        );
        $incoming = array();
        foreach ( $source_ids as $source_id ) {
            foreach ( self::relations( $source_id ) as $relation ) {
                if ( absint( $relation['target_id'] ?? 0 ) !== absint( $target_id ) ) {
                    continue;
                }
                $incoming[] = array(
                    'schema'         => self::RELATION_SCHEMA,
                    'source_id'      => absint( $source_id ),
                    'relation'       => sanitize_key( $relation['relation'] ?? '' ),
                    'effective_date' => sanitize_text_field( $relation['effective_date'] ?? '' ),
                    'note'           => sanitize_text_field( $relation['note'] ?? '' ),
                    'public'         => ! empty( $relation['public'] ),
                    'updated_at'     => sanitize_text_field( $relation['updated_at'] ?? current_time( 'mysql' ) ),
                );
            }
        }
        $incoming ? update_post_meta( $target_id, self::META_INCOMING, array_slice( $incoming, -self::MAX_RELATIONS ) ) : delete_post_meta( $target_id, self::META_INCOMING );
    }

    public static function integrity_alerts( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'include_private' => false,
                'status'          => '',
                'per_page'        => 20,
                'page'            => 1,
            )
        );
        $include_private = ! empty( $args['include_private'] ) && current_user_can( 'edit_posts' );
        $meta_query = $include_private
            ? array(
                'relation' => 'OR',
                array(
                    'key'     => self::META_STATUS,
                    'value'   => array( 'current', 'archived' ),
                    'compare' => 'NOT IN',
                ),
                array(
                    'key'     => self::META_RELATION_CONFLICT,
                    'compare' => 'EXISTS',
                ),
            )
            : array(
                array(
                    'key'     => self::META_STATUS,
                    'value'   => array( 'current', 'archived' ),
                    'compare' => 'NOT IN',
                ),
            );
        if ( $args['status'] && array_key_exists( sanitize_key( $args['status'] ), self::integrity_status_options() ) ) {
            $meta_query = array(
                array(
                    'key'   => self::META_STATUS,
                    'value' => sanitize_key( $args['status'] ),
                ),
            );
        }
        if ( ! $include_private ) {
            $meta_query[] = array(
                'key'   => self::META_PUBLIC_NOTICE,
                'value' => '1',
            );
        }

        $query = new WP_Query(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $args['per_page'] ) ) ),
                'paged'          => max( 1, absint( $args['page'] ) ),
                'meta_query'     => $meta_query,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $items = array();
        foreach ( $query->posts as $post ) {
            $integrity = self::get_integrity_data( $post->ID, $include_private );
            if ( ! $integrity ) {
                continue;
            }
            $items[] = array_merge(
                $integrity,
                array(
                    'title'    => $post->post_title,
                    'citation' => SC_Library_Citation_Source_Manager::format_citation( $post->ID, 'harvard', 'reference' ),
                    'impact'   => self::impact_report( $post->ID, $include_private ),
                )
            );
        }
        return array(
            'schema' => self::INTEGRITY_SCHEMA,
            'items'  => $items,
            'total'  => absint( $query->found_posts ),
            'pages'  => absint( $query->max_num_pages ),
            'page'   => max( 1, absint( $args['page'] ) ),
        );
    }

    public function ajax_scan_integrity() {
        $this->verify_ajax( 'manage_options' );
        $result = self::run_scan_batch( self::SCAN_BATCH );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'state' => $result ) );
    }

    public function ajax_rebuild_source() {
        $this->verify_ajax( 'edit_posts' );
        $source_id = absint( wp_unslash( $_POST['source_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $source_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot rebuild this Source.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::rebuild_source_integrity( $source_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'impact' => $result ) );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_source_integrity_v310', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    public function source_columns( $columns ) {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'title' === $key ) {
                $result['sc_source_version'] = __( 'Version', 'sustainable-catalyst-library' );
                $result['sc_source_integrity'] = __( 'Integrity', 'sustainable-catalyst-library' );
            }
        }
        return $result;
    }

    public function source_column_content( $column, $post_id ) {
        if ( 'sc_source_version' === $column ) {
            $label = trim(
                (string) get_post_meta( $post_id, self::META_VERSION_LABEL, true )
                . ' '
                . (string) get_post_meta( $post_id, self::META_VERSION_NUMBER, true )
            );
            echo esc_html( $label ?: '—' );
        } elseif ( 'sc_source_integrity' === $column ) {
            $status = (string) get_post_meta( $post_id, self::META_STATUS, true ) ?: 'current';
            echo '<span class="sc-integrity-badge severity-' . esc_attr( self::severity_for_status( $status ) ) . '">' . esc_html( self::integrity_status_options()[ $status ] ?? $status ) . '</span>';
        }
    }

    public function capture_deleted_source( $post_id ) {
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }
        self::$deleted_source[ $post_id ] = array(
            'relations' => self::relations( $post_id ),
            'incoming'  => self::incoming_relations( $post_id ),
            'impact'    => self::impact_report( $post_id, true ),
        );
    }

    public function cleanup_deleted_source( $post_id ) {
        if ( empty( self::$deleted_source[ $post_id ] ) ) {
            return;
        }
        $record = self::$deleted_source[ $post_id ];
        unset( self::$deleted_source[ $post_id ] );

        $affected_sources = array();
        foreach ( array_merge( $record['relations'], $record['incoming'] ) as $relation ) {
            if ( isset( $relation['target_id'] ) ) {
                $affected_sources[] = absint( $relation['target_id'] );
            }
            if ( isset( $relation['source_id'] ) ) {
                $affected_sources[] = absint( $relation['source_id'] );
            }
        }

        $referencing = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => self::META_RELATIONS,
                        'value'   => 'i:' . absint( $post_id ) . ';',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'   => self::META_RECOMMENDED_ID,
                        'value' => absint( $post_id ),
                    ),
                    array(
                        'key'   => self::META_FAMILY_ID,
                        'value' => absint( $post_id ),
                    ),
                ),
            )
        );

        foreach ( $referencing as $source_id ) {
            $relations = array_values(
                array_filter(
                    self::relations( $source_id ),
                    static function ( $relation ) use ( $post_id ) {
                        return absint( $relation['target_id'] ?? 0 ) !== absint( $post_id );
                    }
                )
            );
            $relations ? update_post_meta( $source_id, self::META_RELATIONS, $relations ) : delete_post_meta( $source_id, self::META_RELATIONS );
            if ( absint( get_post_meta( $source_id, self::META_RECOMMENDED_ID, true ) ) === absint( $post_id ) ) {
                delete_post_meta( $source_id, self::META_RECOMMENDED_ID );
            }
            if ( absint( get_post_meta( $source_id, self::META_FAMILY_ID, true ) ) === absint( $post_id ) ) {
                delete_post_meta( $source_id, self::META_FAMILY_ID );
            }
            $affected_sources[] = absint( $source_id );
        }

        foreach ( self::id_list( $record['impact']['project_ids'] ?? array() ) as $project_id ) {
            $acks = self::project_acknowledgements( $project_id );
            unset( $acks[ $post_id ] );
            $acks ? update_post_meta( $project_id, self::META_PROJECT_ACKS, $acks ) : delete_post_meta( $project_id, self::META_PROJECT_ACKS );

            $impacts = get_post_meta( $project_id, self::META_PROJECT_IMPACTS, true );
            $impacts = is_array( $impacts ) ? $impacts : array();
            unset( $impacts[ $post_id ] );
            $impacts ? update_post_meta( $project_id, self::META_PROJECT_IMPACTS, $impacts ) : delete_post_meta( $project_id, self::META_PROJECT_IMPACTS );
        }

        foreach ( self::id_list( $record['impact']['evidence_note_ids'] ?? array() ) as $note_id ) {
            delete_post_meta( $note_id, self::META_EVIDENCE_IMPACT );
        }
        foreach ( self::id_list( $record['impact']['claim_ids'] ?? array() ) as $claim_id ) {
            $impacts = get_post_meta( $claim_id, self::META_CLAIM_IMPACTS, true );
            $impacts = is_array( $impacts ) ? $impacts : array();
            unset( $impacts[ $post_id ] );
            $impacts ? update_post_meta( $claim_id, self::META_CLAIM_IMPACTS, $impacts ) : delete_post_meta( $claim_id, self::META_CLAIM_IMPACTS );
        }

        foreach ( array_values( array_unique( array_filter( $affected_sources ) ) ) as $source_id ) {
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id ) ) {
                self::rebuild_source_integrity( $source_id );
            }
        }
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-source-versioning-integrity',
            SC_LIBRARY_URL . 'assets/css/sc-library-source-versioning-integrity.css',
            array( 'sc-library-citation-manager', 'sc-library-connected-research' ),
            self::VERSION
        );
        wp_register_script(
            'sc-library-source-versioning-integrity',
            SC_LIBRARY_URL . 'assets/js/sc-library-source-versioning-integrity.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)/integrity',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_integrity' ),
                    'permission_callback' => array( $this, 'rest_can_read_source_integrity' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_integrity' ),
                    'permission_callback' => array( $this, 'rest_can_edit_source' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)/versions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_versions' ),
                'permission_callback' => array( $this, 'rest_can_read_source_integrity' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)/impact',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_impact' ),
                'permission_callback' => array( $this, 'rest_can_edit_source' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/source-integrity',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_project_integrity' ),
                    'permission_callback' => array( $this, 'rest_can_read_project_integrity' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_project_integrity' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/integrity/alerts',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_alerts' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/integrity/scan',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_scan_state' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_scan' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
            )
        );
    }

    public function rest_get_integrity( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return rest_ensure_response( self::get_integrity_data( $source_id, current_user_can( 'edit_post', $source_id ) ) );
    }

    public function rest_update_integrity( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::update_integrity_record( $source_id, $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_versions( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        $private = current_user_can( 'edit_post', $source_id );
        return rest_ensure_response(
            array(
                'schema'    => self::SNAPSHOT_SCHEMA,
                'source_id' => $source_id,
                'family'    => self::version_family( $source_id, $private ),
                'snapshots' => $private ? self::snapshots( $source_id ) : array(),
            )
        );
    }

    public function rest_impact( WP_REST_Request $request ) {
        return rest_ensure_response( self::rebuild_impact( absint( $request['id'] ) ) );
    }

    public function rest_project_integrity( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response( self::project_integrity_report( $project_id, current_user_can( 'edit_post', $project_id ) ) );
    }

    public function rest_update_project_integrity( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $raw = is_array( $payload['acknowledgements'] ?? null ) ? $payload['acknowledgements'] : array();
        $old = self::project_acknowledgements( $project_id );
        $acks = array();

        foreach ( $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $source_id = absint( $item['source_id'] ?? 0 );
            if ( ! $source_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                continue;
            }
            $previous = $old[ $source_id ] ?? array();
            $acks[ $source_id ] = array(
                'source_id'  => $source_id,
                'status'     => self::allowed_value( $item['status'] ?? 'pending', self::acknowledgement_options(), 'pending' ),
                'note'       => sanitize_text_field( $item['note'] ?? '' ),
                'created_at' => sanitize_text_field( $previous['created_at'] ?? current_time( 'mysql' ) ),
                'created_by' => absint( $previous['created_by'] ?? get_current_user_id() ),
                'updated_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id(),
            );
        }

        $acks ? update_post_meta( $project_id, self::META_PROJECT_ACKS, array_slice( $acks, 0, self::MAX_PROJECT_ACKS, true ) ) : delete_post_meta( $project_id, self::META_PROJECT_ACKS );
        return rest_ensure_response( self::project_integrity_report( $project_id, true ) );
    }

    public function rest_alerts( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        return rest_ensure_response(
            self::integrity_alerts(
                array(
                    'include_private' => $include_private,
                    'status'          => sanitize_key( $request->get_param( 'status' ) ),
                    'per_page'        => absint( $request->get_param( 'per_page' ) ?: 20 ),
                    'page'            => absint( $request->get_param( 'page' ) ?: 1 ),
                )
            )
        );
    }

    public function rest_scan_state() {
        return rest_ensure_response( self::scan_state() );
    }

    public function rest_run_scan( WP_REST_Request $request ) {
        $limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::SCAN_BATCH ) ) );
        $result = self::run_scan_batch( $limit );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_source_integrity( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        if ( current_user_can( 'edit_post', $source_id ) ) {
            return true;
        }
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) || 'publish' !== get_post_status( $source_id ) ) {
            return false;
        }
        $status = (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current';
        return '1' === get_post_meta( $source_id, self::META_PUBLIC_NOTICE, true )
            || in_array( self::severity_for_status( $status ), array( 'high', 'critical' ), true );
    }

    public function rest_can_edit_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id )
            && current_user_can( 'edit_post', $source_id );
    }

    public function rest_can_read_project_integrity( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id ) || self::project_is_public( $project_id );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && current_user_can( 'edit_post', $project_id );
    }

    private static function update_integrity_record( $source_id, $payload ) {
        if (
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id )
            || ! current_user_can( 'edit_post', $source_id )
        ) {
            return new WP_Error( 'integrity_update_forbidden', __( 'The Source cannot be updated.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $before = self::structured_snapshot( $source_id );
        $old_relations = self::relations( $source_id );
        $simple = array(
            'version_label'  => array( self::META_VERSION_LABEL, 'text' ),
            'version_number' => array( self::META_VERSION_NUMBER, 'text' ),
            'release_date'   => array( self::META_RELEASE_DATE, 'date' ),
            'notice_date'    => array( self::META_NOTICE_DATE, 'date' ),
            'notice_url'     => array( self::META_NOTICE_URL, 'url' ),
            'reason'         => array( self::META_REASON, 'textarea' ),
        );
        foreach ( $simple as $field => $definition ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }
            $value = $payload[ $field ];
            if ( 'date' === $definition[1] ) {
                $value = self::sanitize_date( $value );
            } elseif ( 'url' === $definition[1] ) {
                $value = esc_url_raw( $value );
            } elseif ( 'textarea' === $definition[1] ) {
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
            self::update_or_delete_meta( $source_id, $definition[0], $value );
        }

        if ( array_key_exists( 'status', $payload ) ) {
            $status = self::allowed_value( $payload['status'], self::integrity_status_options(), 'current' );
            $previous = (string) get_post_meta( $source_id, self::META_STATUS, true ) ?: 'current';
            self::update_or_delete_meta( $source_id, self::META_STATUS, $status );
            if ( $status !== $previous ) {
                update_post_meta( $source_id, self::META_STATUS_CHANGED_AT, current_time( 'mysql' ) );
            }
        }
        if ( array_key_exists( 'review_status', $payload ) ) {
            $review = self::allowed_value( $payload['review_status'], self::review_status_options(), 'unreviewed' );
            self::update_or_delete_meta( $source_id, self::META_REVIEW_STATUS, $review );
            if ( in_array( $review, array( 'reviewed', 'verified', 'disputed' ), true ) ) {
                update_post_meta( $source_id, self::META_LAST_REVIEWED, current_time( 'mysql' ) );
                update_post_meta( $source_id, self::META_LAST_REVIEWED_BY, get_current_user_id() );
            }
        }
        if ( array_key_exists( 'public_notice', $payload ) ) {
            update_post_meta( $source_id, self::META_PUBLIC_NOTICE, rest_sanitize_boolean( $payload['public_notice'] ) ? '1' : '0' );
        }
        foreach ( array( 'family_id' => self::META_FAMILY_ID, 'recommended_id' => self::META_RECOMMENDED_ID ) as $field => $meta_key ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }
            $target_id = absint( $payload[ $field ] );
            if (
                $target_id === $source_id
                || ( $target_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $target_id ) )
            ) {
                return new WP_Error( 'invalid_integrity_target', __( 'A related Source ID is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
            }
            self::update_or_delete_meta( $source_id, $meta_key, $target_id );
        }
        if ( array_key_exists( 'relations', $payload ) ) {
            $relations = self::sanitize_relations( $payload['relations'], $old_relations, $source_id );
            $relations ? update_post_meta( $source_id, self::META_RELATIONS, $relations ) : delete_post_meta( $source_id, self::META_RELATIONS );
        }

        $after = self::structured_snapshot( $source_id );
        if ( $before && ( $before['hash'] ?? '' ) !== ( $after['hash'] ?? '' ) ) {
            self::append_snapshot( $source_id, $before );
        }
        self::sync_incoming_relations( $source_id, $old_relations, self::relations( $source_id ) );
        self::rebuild_source_integrity( $source_id );
        update_post_meta( $source_id, self::META_CURRENT_HASH, $after['hash'] ?? '' );
        return self::get_integrity_data( $source_id, true );
    }

    public function protect_integrity_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = (string) $request->get_route();
        $base = '/' . self::API_NAMESPACE . '/';
        if ( 0 !== strpos( $route, $base ) ) {
            return $response;
        }
        $sensitive = false !== strpos( $route, '/impact' )
            || false !== strpos( $route, '/scan' )
            || false !== strpos( $route, '/source-integrity' )
            || ( false !== strpos( $route, '/integrity' ) && is_user_logged_in() );
        if ( $sensitive || is_user_logged_in() ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    private static function resolve_project_id( $value ) {
        if ( is_numeric( $value ) && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function scan_state() {
        $state = get_option( self::OPTION_SCAN_STATE, array() );
        return wp_parse_args( is_array( $state ) ? $state : array(), self::default_scan_state() );
    }

    private static function default_scan_state() {
        return array(
            'version'      => self::VERSION,
            'status'       => 'pending',
            'cursor'       => 0,
            'total'        => 0,
            'processed'    => 0,
            'failures'     => array(),
            'last_error'   => '',
            'started_at'   => '',
            'updated_at'   => '',
            'completed_at' => '',
        );
    }

    private static function sanitize_date( $value ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            return '';
        }
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function update_or_delete_meta( $post_id, $key, $value ) {
        if ( '' === $value || 0 === $value || array() === $value || null === $value ) {
            delete_post_meta( $post_id, $key );
        } else {
            update_post_meta( $post_id, $key, $value );
        }
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        WP_CLI::add_command(
            'sc-library sources integrity-scan',
            static function ( $args, $assoc_args ) {
                $limit = absint( $assoc_args['limit'] ?? self::SCAN_BATCH );
                $result = self::run_scan_batch( $limit );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library sources integrity',
            static function ( $args ) {
                $source_id = absint( $args[0] ?? 0 );
                $data = self::get_integrity_data( $source_id, true );
                if ( ! $data ) {
                    WP_CLI::error( 'Source not found.' );
                }
                WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library sources integrity-rebuild',
            static function ( $args ) {
                $source_id = absint( $args[0] ?? 0 );
                $result = self::rebuild_source_integrity( $source_id );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library projects integrity',
            static function ( $args ) {
                $project_id = absint( $args[0] ?? 0 );
                $report = self::project_integrity_report( $project_id, true );
                if ( ! $report ) {
                    WP_CLI::error( 'Project not found.' );
                }
                WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
    }
}
