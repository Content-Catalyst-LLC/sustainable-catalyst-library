<?php
/**
 * Collaborative Review and Research Publishing.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Collaborative_Review_Publishing {
    public const VERSION = '3.8.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const REVIEW_POST_TYPE = 'sc_review_cycle';
    public const NOTE_POST_TYPE = 'sc_review_note';
    public const PACKAGE_POST_TYPE = 'sc_pub_package';

    public const REVIEW_SCHEMA = 'sc-library-collaborative-review/1.0';
    public const ASSIGNMENT_SCHEMA = 'sc-library-review-assignment/1.0';
    public const NOTE_SCHEMA = 'sc-library-review-note/1.0';
    public const DECISION_SCHEMA = 'sc-library-review-decision/1.0';
    public const PACKAGE_SCHEMA = 'sc-library-research-publication-package/1.0';
    public const TRANSPARENCY_SCHEMA = 'sc-library-review-transparency/1.0';
    public const DASHBOARD_SCHEMA = 'sc-library-collaborative-publishing-dashboard/1.0';
    public const HANDOFF_SCHEMA = 'sc-platform-handoff/collaborative-review-publishing/1.0';

    public const META_REVIEW_UUID = '_sc_review_uuid';
    public const META_REVIEW_STATUS = '_sc_review_status';
    public const META_REVIEW_TYPE = '_sc_review_type';
    public const META_REVIEW_TITLE = '_sc_review_title';
    public const META_REVIEW_DOCUMENT_IDS = '_sc_review_document_ids';
    public const META_REVIEW_PROJECT_IDS = '_sc_review_project_ids';
    public const META_REVIEW_ASSIGNMENTS = '_sc_review_assignments';
    public const META_REVIEW_SNAPSHOTS = '_sc_review_snapshots';
    public const META_REVIEW_DECISIONS = '_sc_review_decisions';
    public const META_REVIEW_GATE = '_sc_review_gate';
    public const META_REVIEW_REQUIRED_APPROVALS = '_sc_review_required_approvals';
    public const META_REVIEW_DUE_DATE = '_sc_review_due_date';
    public const META_REVIEW_STARTED_AT = '_sc_review_started_at';
    public const META_REVIEW_COMPLETED_AT = '_sc_review_completed_at';
    public const META_REVIEW_PUBLIC = '_sc_review_public';
    public const META_REVIEW_PUBLIC_SUMMARY = '_sc_review_public_summary';
    public const META_REVIEW_COI_POLICY = '_sc_review_coi_policy';
    public const META_REVIEW_CHANGE_STATE = '_sc_review_change_state';
    public const META_REVIEW_READINESS = '_sc_review_readiness';
    public const META_REVIEW_HISTORY = '_sc_review_history';

    public const META_NOTE_REVIEW_ID = '_sc_review_note_review_id';
    public const META_NOTE_DOCUMENT_ID = '_sc_review_note_document_id';
    public const META_NOTE_PARENT_ID = '_sc_review_note_parent_id';
    public const META_NOTE_TYPE = '_sc_review_note_type';
    public const META_NOTE_SEVERITY = '_sc_review_note_severity';
    public const META_NOTE_STATUS = '_sc_review_note_status';
    public const META_NOTE_SECTION = '_sc_review_note_section';
    public const META_NOTE_ANCHOR = '_sc_review_note_anchor';
    public const META_NOTE_QUOTE = '_sc_review_note_quote';
    public const META_NOTE_BODY = '_sc_review_note_body';
    public const META_NOTE_AUTHOR = '_sc_review_note_author';
    public const META_NOTE_ASSIGNEE = '_sc_review_note_assignee';
    public const META_NOTE_RESOLUTION = '_sc_review_note_resolution';
    public const META_NOTE_RESOLVED_AT = '_sc_review_note_resolved_at';

    public const META_PACKAGE_UUID = '_sc_pub_package_uuid';
    public const META_PACKAGE_STATUS = '_sc_pub_package_status';
    public const META_PACKAGE_DOCUMENT_IDS = '_sc_pub_package_document_ids';
    public const META_PACKAGE_PROJECT_IDS = '_sc_pub_package_project_ids';
    public const META_PACKAGE_REVIEW_IDS = '_sc_pub_package_review_ids';
    public const META_PACKAGE_VERSION = '_sc_pub_package_version';
    public const META_PACKAGE_RELEASE_NOTES = '_sc_pub_package_release_notes';
    public const META_PACKAGE_LICENSE = '_sc_pub_package_license';
    public const META_PACKAGE_DOI = '_sc_pub_package_doi';
    public const META_PACKAGE_CANONICAL_URL = '_sc_pub_package_canonical_url';
    public const META_PACKAGE_EMBARGO_UNTIL = '_sc_pub_package_embargo_until';
    public const META_PACKAGE_PUBLISH_AT = '_sc_pub_package_publish_at';
    public const META_PACKAGE_READINESS = '_sc_pub_package_readiness';
    public const META_PACKAGE_APPROVALS = '_sc_pub_package_approvals';
    public const META_PACKAGE_MANIFEST = '_sc_pub_package_manifest';
    public const META_PACKAGE_PUBLISHED_AT = '_sc_pub_package_published_at';
    public const META_PACKAGE_HISTORY = '_sc_pub_package_history';
    public const META_PACKAGE_PUBLIC = '_sc_pub_package_public';

    public const OPTION_MIGRATION = 'sc_library_v380_collaborative_review_migration';
    public const TRANSIENT_LOCK = 'sc_library_v380_review_migration_lock';
    public const TRANSIENT_DASHBOARD = 'sc_library_v380_review_dashboard';
    public const CRON_MIGRATION = 'sc_library_v380_review_migration_tick';
    public const CRON_PUBLICATION = 'sc_library_v380_publication_tick';

    public const MIGRATION_BATCH = 20;
    public const LOCK_SECONDS = 180;
    public const MAX_ASSIGNMENTS = 100;
    public const MAX_NOTES = 1000;
    public const MAX_HISTORY = 200;
    public const MAX_LINKS = 500;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_record_types' ), 360 );
        add_action( 'init', array( $this, 'schedule_operations' ), 998 );
        add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
        add_action( self::CRON_PUBLICATION, array( $this, 'run_scheduled_publications' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 360 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 260 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 260 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'save_post_' . self::REVIEW_POST_TYPE, array( $this, 'save_review' ), 260, 3 );
        add_action( 'save_post_' . self::PACKAGE_POST_TYPE, array( $this, 'save_package' ), 260, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_record' ) );

        add_action( 'wp_ajax_sc_library_v380_run_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_sc_library_v380_refresh_review', array( $this, 'ajax_refresh_review' ) );
        add_action( 'wp_ajax_sc_library_v380_add_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_sc_library_v380_record_decision', array( $this, 'ajax_record_decision' ) );
        add_action( 'wp_ajax_sc_library_v380_evaluate_package', array( $this, 'ajax_evaluate_package' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 110, 3 );

        add_filter( 'sc_library_research_librarian_project_context', array( $this, 'filter_project_context' ), 60, 3 );
        add_filter( 'sc_library_cross_product_handoff_sections', array( $this, 'filter_handoff_sections' ), 60, 3 );

        add_shortcode( 'sc_review_transparency', array( $this, 'shortcode_review_transparency' ) );
        add_shortcode( 'sc_publication_record', array( $this, 'shortcode_publication_record' ) );
        add_shortcode( 'sc_research_release_history', array( $this, 'shortcode_release_history' ) );
        add_shortcode( 'sc_collaborative_review_dashboard', array( $this, 'shortcode_dashboard' ) );

        self::register_cli_commands();
    }

    public static function review_statuses() {
        return array(
            'draft'             => __( 'Draft', 'sustainable-catalyst-library' ),
            'invited'           => __( 'Reviewers invited', 'sustainable-catalyst-library' ),
            'in-review'         => __( 'In review', 'sustainable-catalyst-library' ),
            'changes-requested' => __( 'Changes requested', 'sustainable-catalyst-library' ),
            'revised'           => __( 'Revised', 'sustainable-catalyst-library' ),
            'approved'          => __( 'Approved', 'sustainable-catalyst-library' ),
            'closed'            => __( 'Closed', 'sustainable-catalyst-library' ),
            'archived'          => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public static function review_types() {
        return array(
            'editorial'      => __( 'Editorial', 'sustainable-catalyst-library' ),
            'methodology'    => __( 'Methodology', 'sustainable-catalyst-library' ),
            'evidence'       => __( 'Evidence and claims', 'sustainable-catalyst-library' ),
            'citations'      => __( 'Citations and sources', 'sustainable-catalyst-library' ),
            'governance'     => __( 'Governance and integrity', 'sustainable-catalyst-library' ),
            'accessibility'  => __( 'Accessibility', 'sustainable-catalyst-library' ),
            'privacy'        => __( 'Privacy and confidentiality', 'sustainable-catalyst-library' ),
            'legal'          => __( 'Legal and rights', 'sustainable-catalyst-library' ),
            'publication'    => __( 'Publication readiness', 'sustainable-catalyst-library' ),
        );
    }

    public static function reviewer_roles() {
        return array(
            'author'     => __( 'Author', 'sustainable-catalyst-library' ),
            'editor'     => __( 'Editor', 'sustainable-catalyst-library' ),
            'reviewer'   => __( 'Reviewer', 'sustainable-catalyst-library' ),
            'approver'   => __( 'Approver', 'sustainable-catalyst-library' ),
            'observer'   => __( 'Observer', 'sustainable-catalyst-library' ),
        );
    }

    public static function decision_values() {
        return array(
            'pending'           => __( 'Pending', 'sustainable-catalyst-library' ),
            'approve'           => __( 'Approve', 'sustainable-catalyst-library' ),
            'approve-minor'     => __( 'Approve with minor changes', 'sustainable-catalyst-library' ),
            'request-changes'   => __( 'Request changes', 'sustainable-catalyst-library' ),
            'reject'            => __( 'Reject', 'sustainable-catalyst-library' ),
            'recuse'            => __( 'Recuse', 'sustainable-catalyst-library' ),
        );
    }

    public static function note_types() {
        return array(
            'comment'       => __( 'Comment', 'sustainable-catalyst-library' ),
            'question'      => __( 'Question', 'sustainable-catalyst-library' ),
            'required-change'=> __( 'Required change', 'sustainable-catalyst-library' ),
            'suggestion'    => __( 'Suggestion', 'sustainable-catalyst-library' ),
            'citation'      => __( 'Citation issue', 'sustainable-catalyst-library' ),
            'evidence'      => __( 'Evidence issue', 'sustainable-catalyst-library' ),
            'integrity'     => __( 'Integrity issue', 'sustainable-catalyst-library' ),
            'accessibility' => __( 'Accessibility issue', 'sustainable-catalyst-library' ),
        );
    }

    public static function publication_statuses() {
        return array(
            'draft'       => __( 'Draft', 'sustainable-catalyst-library' ),
            'assembling'  => __( 'Assembling', 'sustainable-catalyst-library' ),
            'review'      => __( 'Publication review', 'sustainable-catalyst-library' ),
            'approved'    => __( 'Approved', 'sustainable-catalyst-library' ),
            'scheduled'   => __( 'Scheduled', 'sustainable-catalyst-library' ),
            'published'   => __( 'Published', 'sustainable-catalyst-library' ),
            'withdrawn'   => __( 'Withdrawn', 'sustainable-catalyst-library' ),
            'archived'    => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public function register_record_types() {
        register_post_type(
            self::REVIEW_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Research Reviews', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Research Review', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Research Review', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Research Review', 'sustainable-catalyst-library' ),
                ),
                'public' => false, 'show_ui' => true, 'show_in_menu' => false,
                'show_in_rest' => false, 'supports' => array( 'title', 'editor', 'author', 'revisions' ),
                'capability_type' => 'post', 'map_meta_cap' => true, 'exclude_from_search' => true,
            )
        );
        register_post_type(
            self::NOTE_POST_TYPE,
            array(
                'labels' => array(
                    'name' => __( 'Review Notes', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Review Note', 'sustainable-catalyst-library' ),
                ),
                'public' => false, 'show_ui' => false, 'show_in_rest' => false,
                'supports' => array( 'title', 'editor', 'author' ),
                'capability_type' => 'post', 'map_meta_cap' => true, 'exclude_from_search' => true,
            )
        );
        register_post_type(
            self::PACKAGE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Publication Packages', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Publication Package', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Publication Package', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Publication Package', 'sustainable-catalyst-library' ),
                ),
                'public' => false, 'show_ui' => true, 'show_in_menu' => false,
                'show_in_rest' => false, 'supports' => array( 'title', 'editor', 'author', 'revisions' ),
                'capability_type' => 'post', 'map_meta_cap' => true, 'exclude_from_search' => true,
            )
        );
    }

    public function schedule_operations() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_MIGRATION ) ) {
            wp_schedule_event( time() + 900, 'hourly', self::CRON_MIGRATION );
        }
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_PUBLICATION ) ) {
            wp_schedule_event( time() + 1800, 'hourly', self::CRON_PUBLICATION );
        }
        if ( ! is_array( get_option( self::OPTION_MIGRATION, null ) ) ) {
            update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
        }
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Collaborative Review and Research Publishing', 'sustainable-catalyst-library' ),
            __( 'Review & Publishing', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-review-publishing',
            array( $this, 'render_workspace' )
        );
        add_submenu_page(
            'sc-library',
            __( 'Research Reviews', 'sustainable-catalyst-library' ),
            __( 'Research Reviews', 'sustainable-catalyst-library' ),
            'edit_posts',
            'edit.php?post_type=' . self::REVIEW_POST_TYPE
        );
        add_submenu_page(
            'sc-library',
            __( 'Publication Packages', 'sustainable-catalyst-library' ),
            __( 'Publication Packages', 'sustainable-catalyst-library' ),
            'edit_posts',
            'edit.php?post_type=' . self::PACKAGE_POST_TYPE
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-review-cycle-configuration',
            __( 'Review Cycle Configuration', 'sustainable-catalyst-library' ),
            array( $this, 'render_review_meta_box' ),
            self::REVIEW_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-review-cycle-status',
            __( 'Review Status and Readiness', 'sustainable-catalyst-library' ),
            array( $this, 'render_review_status_box' ),
            self::REVIEW_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-publication-package-configuration',
            __( 'Research Publication Package', 'sustainable-catalyst-library' ),
            array( $this, 'render_package_meta_box' ),
            self::PACKAGE_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-publication-package-status',
            __( 'Publication Readiness', 'sustainable-catalyst-library' ),
            array( $this, 'render_package_status_box' ),
            self::PACKAGE_POST_TYPE,
            'side',
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
            array( self::REVIEW_POST_TYPE, self::PACKAGE_POST_TYPE ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-review-publishing' );
        if ( ! $supported && ! $workspace ) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style( 'sc-library-collaborative-review-publishing' );
        wp_enqueue_script( 'sc-library-collaborative-review-publishing' );
        wp_localize_script(
            'sc-library-collaborative-review-publishing',
            'SCLibraryReviewPublishing',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_review_publishing_v380' ),
                'strings' => array(
                    'working'  => __( 'Working…', 'sustainable-catalyst-library' ),
                    'complete' => __( 'Operation complete.', 'sustainable-catalyst-library' ),
                    'error'    => __( 'The review or publishing operation failed.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        $this->register_assets();
    }

    private function register_assets() {
        wp_register_style(
            'sc-library-collaborative-review-publishing',
            SC_LIBRARY_URL . 'assets/css/sc-library-collaborative-review-publishing.css',
            array(),
            self::VERSION
        );
        wp_register_script(
            'sc-library-collaborative-review-publishing',
            SC_LIBRARY_URL . 'assets/js/sc-library-collaborative-review-publishing.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function save_review( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::REVIEW_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_review_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_review_nonce'] ) ),
                'sc_library_save_review_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        self::ensure_uuid( $post_id, self::META_REVIEW_UUID );

        $status = self::allowed_value(
            wp_unslash( $_POST['sc_review_status'] ?? 'draft' ),
            self::review_statuses(),
            'draft'
        );
        $type = self::allowed_value(
            wp_unslash( $_POST['sc_review_type'] ?? 'editorial' ),
            self::review_types(),
            'editorial'
        );
        $document_ids = self::id_list( wp_unslash( $_POST['sc_review_document_ids'] ?? array() ) );
        $project_ids = self::id_list( wp_unslash( $_POST['sc_review_project_ids'] ?? array() ) );

        update_post_meta( $post_id, self::META_REVIEW_STATUS, $status );
        update_post_meta( $post_id, self::META_REVIEW_TYPE, $type );
        update_post_meta( $post_id, self::META_REVIEW_DOCUMENT_IDS, array_slice( $document_ids, 0, self::MAX_LINKS ) );
        update_post_meta( $post_id, self::META_REVIEW_PROJECT_IDS, array_slice( $project_ids, 0, self::MAX_LINKS ) );
        update_post_meta( $post_id, self::META_REVIEW_GATE, sanitize_key( wp_unslash( $_POST['sc_review_gate'] ?? 'editorial-review' ) ) );
        update_post_meta( $post_id, self::META_REVIEW_REQUIRED_APPROVALS, max( 1, absint( wp_unslash( $_POST['sc_review_required_approvals'] ?? 1 ) ) ) );
        update_post_meta( $post_id, self::META_REVIEW_DUE_DATE, self::sanitize_date( wp_unslash( $_POST['sc_review_due_date'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_REVIEW_PUBLIC, isset( $_POST['sc_review_public'] ) ? '1' : '0' );
        update_post_meta( $post_id, self::META_REVIEW_PUBLIC_SUMMARY, sanitize_textarea_field( wp_unslash( $_POST['sc_review_public_summary'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_REVIEW_COI_POLICY, sanitize_textarea_field( wp_unslash( $_POST['sc_review_coi_policy'] ?? '' ) ) );

        if ( in_array( $status, array( 'invited', 'in-review' ), true ) && ! get_post_meta( $post_id, self::META_REVIEW_STARTED_AT, true ) ) {
            update_post_meta( $post_id, self::META_REVIEW_STARTED_AT, current_time( 'mysql' ) );
        }
        if ( in_array( $status, array( 'approved', 'closed' ), true ) ) {
            update_post_meta( $post_id, self::META_REVIEW_COMPLETED_AT, current_time( 'mysql' ) );
        }

        $assignments = self::sanitize_assignments( wp_unslash( $_POST['sc_review_assignments'] ?? array() ) );
        update_post_meta( $post_id, self::META_REVIEW_ASSIGNMENTS, $assignments );

        if ( ! get_post_meta( $post_id, self::META_REVIEW_SNAPSHOTS, true ) || isset( $_POST['sc_review_refresh_snapshots'] ) ) {
            update_post_meta( $post_id, self::META_REVIEW_SNAPSHOTS, self::build_snapshots( $document_ids ) );
        }

        self::append_history(
            $post_id,
            self::META_REVIEW_HISTORY,
            array(
                'event'  => 'review-saved',
                'status' => $status,
                'type'   => $type,
            )
        );
        self::evaluate_review( $post_id, true );
        delete_transient( self::TRANSIENT_DASHBOARD );
        self::$saving = false;
    }

    public function save_package( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::PACKAGE_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_package_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_package_nonce'] ) ),
                'sc_library_save_package_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        self::ensure_uuid( $post_id, self::META_PACKAGE_UUID );

        $status = self::allowed_value(
            wp_unslash( $_POST['sc_package_status'] ?? 'draft' ),
            self::publication_statuses(),
            'draft'
        );
        update_post_meta( $post_id, self::META_PACKAGE_STATUS, $status );
        update_post_meta( $post_id, self::META_PACKAGE_DOCUMENT_IDS, array_slice( self::id_list( wp_unslash( $_POST['sc_package_document_ids'] ?? array() ) ), 0, self::MAX_LINKS ) );
        update_post_meta( $post_id, self::META_PACKAGE_PROJECT_IDS, array_slice( self::id_list( wp_unslash( $_POST['sc_package_project_ids'] ?? array() ) ), 0, self::MAX_LINKS ) );
        update_post_meta( $post_id, self::META_PACKAGE_REVIEW_IDS, array_slice( self::id_list( wp_unslash( $_POST['sc_package_review_ids'] ?? array() ) ), 0, self::MAX_LINKS ) );
        update_post_meta( $post_id, self::META_PACKAGE_VERSION, sanitize_text_field( wp_unslash( $_POST['sc_package_version'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_RELEASE_NOTES, sanitize_textarea_field( wp_unslash( $_POST['sc_package_release_notes'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_LICENSE, sanitize_text_field( wp_unslash( $_POST['sc_package_license'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_DOI, sanitize_text_field( wp_unslash( $_POST['sc_package_doi'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_CANONICAL_URL, esc_url_raw( wp_unslash( $_POST['sc_package_canonical_url'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_EMBARGO_UNTIL, self::sanitize_date( wp_unslash( $_POST['sc_package_embargo_until'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_PUBLISH_AT, self::sanitize_datetime( wp_unslash( $_POST['sc_package_publish_at'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PACKAGE_PUBLIC, isset( $_POST['sc_package_public'] ) ? '1' : '0' );

        self::append_history(
            $post_id,
            self::META_PACKAGE_HISTORY,
            array(
                'event'  => 'package-saved',
                'status' => $status,
            )
        );
        self::evaluate_package( $post_id, true );
        delete_transient( self::TRANSIENT_DASHBOARD );
        self::$saving = false;
    }

    public static function sanitize_assignments( $raw ) {
        $assignments = array();
        foreach ( (array) $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $user_id = absint( $item['user_id'] ?? 0 );
            $email = sanitize_email( $item['email'] ?? '' );
            if ( ! $user_id && ! $email ) {
                continue;
            }
            $role = self::allowed_value( $item['role'] ?? 'reviewer', self::reviewer_roles(), 'reviewer' );
            $decision = self::allowed_value( $item['decision'] ?? 'pending', self::decision_values(), 'pending' );
            $assignments[] = array(
                'assignment_id' => sanitize_text_field( $item['assignment_id'] ?? wp_generate_uuid4() ),
                'user_id'       => $user_id,
                'email'         => $email,
                'display_name'  => sanitize_text_field( $item['display_name'] ?? '' ),
                'role'          => $role,
                'review_type'   => self::allowed_value( $item['review_type'] ?? 'editorial', self::review_types(), 'editorial' ),
                'status'        => sanitize_key( $item['status'] ?? 'invited' ),
                'decision'      => $decision,
                'decision_note' => sanitize_textarea_field( $item['decision_note'] ?? '' ),
                'conflict'      => rest_sanitize_boolean( $item['conflict'] ?? false ),
                'conflict_note' => sanitize_textarea_field( $item['conflict_note'] ?? '' ),
                'invited_at'    => sanitize_text_field( $item['invited_at'] ?? current_time( 'mysql' ) ),
                'responded_at'  => sanitize_text_field( $item['responded_at'] ?? '' ),
            );
            if ( count( $assignments ) >= self::MAX_ASSIGNMENTS ) {
                break;
            }
        }
        return $assignments;
    }

    public static function build_snapshots( $document_ids ) {
        $snapshots = array();
        foreach ( array_slice( self::id_list( $document_ids ), 0, self::MAX_LINKS ) as $document_id ) {
            if ( SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
                continue;
            }
            $title = (string) get_the_title( $document_id );
            $content = (string) get_post_field( 'post_content', $document_id );
            $raw = class_exists( 'SC_Library_PDF_To_Document' )
                ? (string) get_post_meta( $document_id, SC_Library_PDF_To_Document::META_RAW_TEXT, true )
                : '';
            $source = trim( $raw ) ?: wp_strip_all_tags( $content );
            $snapshots[] = array(
                'document_id'   => $document_id,
                'title'         => $title,
                'post_modified' => (string) get_post_modified_time( 'Y-m-d H:i:s', false, $document_id, true ),
                'content_hash'  => hash( 'sha256', $title . "\n" . $source ),
                'intelligence_hash' => (string) get_post_meta( $document_id, '_sc_document_intelligence_source_hash', true ),
                'snapshot_at'   => current_time( 'mysql' ),
            );
        }
        return $snapshots;
    }

    public static function detect_snapshot_changes( $review_id ) {
        $snapshots = get_post_meta( $review_id, self::META_REVIEW_SNAPSHOTS, true );
        $snapshots = is_array( $snapshots ) ? $snapshots : array();
        $changes = array();
        foreach ( $snapshots as $snapshot ) {
            $document_id = absint( $snapshot['document_id'] ?? 0 );
            if ( SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
                $changes[] = array(
                    'document_id' => $document_id,
                    'state'       => 'missing',
                    'message'     => __( 'The reviewed document no longer exists.', 'sustainable-catalyst-library' ),
                );
                continue;
            }
            $title = (string) get_the_title( $document_id );
            $content = (string) get_post_field( 'post_content', $document_id );
            $raw = class_exists( 'SC_Library_PDF_To_Document' )
                ? (string) get_post_meta( $document_id, SC_Library_PDF_To_Document::META_RAW_TEXT, true )
                : '';
            $current_hash = hash( 'sha256', $title . "\n" . ( trim( $raw ) ?: wp_strip_all_tags( $content ) ) );
            $changed = ! hash_equals( (string) ( $snapshot['content_hash'] ?? '' ), $current_hash );
            $changes[] = array(
                'document_id'   => $document_id,
                'title'         => $title,
                'state'         => $changed ? 'changed' : 'unchanged',
                'snapshot_hash' => (string) ( $snapshot['content_hash'] ?? '' ),
                'current_hash'  => $current_hash,
                'message'       => $changed
                    ? __( 'The document changed after the review snapshot.', 'sustainable-catalyst-library' )
                    : __( 'The document matches the review snapshot.', 'sustainable-catalyst-library' ),
            );
        }
        return $changes;
    }

    public static function create_note( $review_id, $args = array() ) {
        $review_id = absint( $review_id );
        if ( self::REVIEW_POST_TYPE !== get_post_type( $review_id ) ) {
            return new WP_Error( 'invalid_review', __( 'The research review is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $review_id ) ) {
            return new WP_Error( 'review_note_forbidden', __( 'You cannot add a note to this review.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $args = wp_parse_args(
            $args,
            array(
                'document_id' => 0, 'parent_id' => 0, 'type' => 'comment',
                'severity' => 'info', 'status' => 'open', 'section' => '',
                'anchor' => '', 'quote' => '', 'body' => '', 'assignee_id' => 0,
            )
        );
        $body = sanitize_textarea_field( $args['body'] );
        if ( '' === $body ) {
            return new WP_Error( 'review_note_empty', __( 'A review note requires text.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $note_id = wp_insert_post(
            array(
                'post_type' => self::NOTE_POST_TYPE,
                'post_status' => 'publish',
                'post_title' => wp_trim_words( $body, 12, '…' ),
                'post_content' => $body,
                'post_author' => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $note_id ) ) {
            return $note_id;
        }

        update_post_meta( $note_id, self::META_NOTE_REVIEW_ID, $review_id );
        update_post_meta( $note_id, self::META_NOTE_DOCUMENT_ID, absint( $args['document_id'] ) );
        update_post_meta( $note_id, self::META_NOTE_PARENT_ID, absint( $args['parent_id'] ) );
        update_post_meta( $note_id, self::META_NOTE_TYPE, self::allowed_value( $args['type'], self::note_types(), 'comment' ) );
        update_post_meta( $note_id, self::META_NOTE_SEVERITY, self::allowed_value( $args['severity'], array( 'info' => 'Info', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical' ), 'info' ) );
        update_post_meta( $note_id, self::META_NOTE_STATUS, sanitize_key( $args['status'] ) ?: 'open' );
        update_post_meta( $note_id, self::META_NOTE_SECTION, sanitize_text_field( $args['section'] ) );
        update_post_meta( $note_id, self::META_NOTE_ANCHOR, sanitize_text_field( $args['anchor'] ) );
        update_post_meta( $note_id, self::META_NOTE_QUOTE, sanitize_textarea_field( $args['quote'] ) );
        update_post_meta( $note_id, self::META_NOTE_BODY, $body );
        update_post_meta( $note_id, self::META_NOTE_AUTHOR, get_current_user_id() );
        update_post_meta( $note_id, self::META_NOTE_ASSIGNEE, absint( $args['assignee_id'] ) );

        self::append_history( $review_id, self::META_REVIEW_HISTORY, array( 'event' => 'note-created', 'note_id' => $note_id ) );
        self::evaluate_review( $review_id, true );
        delete_transient( self::TRANSIENT_DASHBOARD );
        return self::note_data( $note_id );
    }

    public static function resolve_note( $note_id, $resolution = '', $status = 'resolved' ) {
        $note_id = absint( $note_id );
        if ( self::NOTE_POST_TYPE !== get_post_type( $note_id ) ) {
            return new WP_Error( 'invalid_review_note', __( 'The review note is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $review_id = absint( get_post_meta( $note_id, self::META_NOTE_REVIEW_ID, true ) );
        if ( ! current_user_can( 'edit_post', $review_id ) ) {
            return new WP_Error( 'review_note_forbidden', __( 'You cannot resolve this review note.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $allowed = array( 'open', 'in-progress', 'resolved', 'accepted-risk', 'dismissed' );
        $status = in_array( sanitize_key( $status ), $allowed, true ) ? sanitize_key( $status ) : 'resolved';
        update_post_meta( $note_id, self::META_NOTE_STATUS, $status );
        update_post_meta( $note_id, self::META_NOTE_RESOLUTION, sanitize_textarea_field( $resolution ) );
        update_post_meta( $note_id, self::META_NOTE_RESOLVED_AT, current_time( 'mysql' ) );
        self::append_history( $review_id, self::META_REVIEW_HISTORY, array( 'event' => 'note-updated', 'note_id' => $note_id, 'status' => $status ) );
        self::evaluate_review( $review_id, true );
        return self::note_data( $note_id );
    }

    public static function note_data( $note_id ) {
        if ( self::NOTE_POST_TYPE !== get_post_type( $note_id ) ) {
            return array();
        }
        return array(
            'schema' => self::NOTE_SCHEMA,
            'note_id' => absint( $note_id ),
            'review_id' => absint( get_post_meta( $note_id, self::META_NOTE_REVIEW_ID, true ) ),
            'document_id' => absint( get_post_meta( $note_id, self::META_NOTE_DOCUMENT_ID, true ) ),
            'parent_id' => absint( get_post_meta( $note_id, self::META_NOTE_PARENT_ID, true ) ),
            'type' => (string) get_post_meta( $note_id, self::META_NOTE_TYPE, true ),
            'severity' => (string) get_post_meta( $note_id, self::META_NOTE_SEVERITY, true ),
            'status' => (string) get_post_meta( $note_id, self::META_NOTE_STATUS, true ),
            'section' => (string) get_post_meta( $note_id, self::META_NOTE_SECTION, true ),
            'anchor' => (string) get_post_meta( $note_id, self::META_NOTE_ANCHOR, true ),
            'quote' => (string) get_post_meta( $note_id, self::META_NOTE_QUOTE, true ),
            'body' => (string) get_post_meta( $note_id, self::META_NOTE_BODY, true ),
            'author_id' => absint( get_post_meta( $note_id, self::META_NOTE_AUTHOR, true ) ),
            'assignee_id' => absint( get_post_meta( $note_id, self::META_NOTE_ASSIGNEE, true ) ),
            'resolution' => (string) get_post_meta( $note_id, self::META_NOTE_RESOLUTION, true ),
            'resolved_at' => (string) get_post_meta( $note_id, self::META_NOTE_RESOLVED_AT, true ),
            'created_at' => (string) get_post_time( 'Y-m-d H:i:s', true, $note_id ),
        );
    }

    public static function review_notes( $review_id ) {
        $ids = get_posts(
            array(
                'post_type' => self::NOTE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => self::MAX_NOTES,
                'fields' => 'ids',
                'meta_key' => self::META_NOTE_REVIEW_ID,
                'meta_value' => absint( $review_id ),
                'orderby' => 'date',
                'order' => 'ASC',
            )
        );
        return array_values( array_filter( array_map( array( __CLASS__, 'note_data' ), $ids ) ) );
    }

    public static function record_decision( $review_id, $args = array() ) {
        $review_id = absint( $review_id );
        if ( self::REVIEW_POST_TYPE !== get_post_type( $review_id ) ) {
            return new WP_Error( 'invalid_review', __( 'The research review is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $review_id ) ) {
            return new WP_Error( 'review_decision_forbidden', __( 'You cannot record a decision for this review.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $args = wp_parse_args(
            $args,
            array( 'assignment_id' => '', 'decision' => 'pending', 'note' => '', 'conflict' => false, 'conflict_note' => '' )
        );
        $decision = self::allowed_value( $args['decision'], self::decision_values(), 'pending' );
        $assignment_id = sanitize_text_field( $args['assignment_id'] );
        $assignments = get_post_meta( $review_id, self::META_REVIEW_ASSIGNMENTS, true );
        $assignments = is_array( $assignments ) ? $assignments : array();
        $matched = false;

        foreach ( $assignments as &$assignment ) {
            $authorized = ( $assignment_id && $assignment_id === (string) ( $assignment['assignment_id'] ?? '' ) )
                || ( get_current_user_id() && absint( $assignment['user_id'] ?? 0 ) === get_current_user_id() );
            if ( ! $authorized ) {
                continue;
            }
            $assignment['decision'] = $decision;
            $assignment['decision_note'] = sanitize_textarea_field( $args['note'] );
            $assignment['conflict'] = rest_sanitize_boolean( $args['conflict'] );
            $assignment['conflict_note'] = sanitize_textarea_field( $args['conflict_note'] );
            $assignment['status'] = 'recuse' === $decision ? 'recused' : 'responded';
            $assignment['responded_at'] = current_time( 'mysql' );
            $assignment_id = (string) $assignment['assignment_id'];
            $matched = true;
            break;
        }
        unset( $assignment );

        if ( ! $matched ) {
            return new WP_Error( 'review_assignment_not_found', __( 'The reviewer assignment was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }

        update_post_meta( $review_id, self::META_REVIEW_ASSIGNMENTS, $assignments );
        $decisions = get_post_meta( $review_id, self::META_REVIEW_DECISIONS, true );
        $decisions = is_array( $decisions ) ? $decisions : array();
        $decisions[] = array(
            'schema' => self::DECISION_SCHEMA,
            'decision_id' => wp_generate_uuid4(),
            'assignment_id' => $assignment_id,
            'reviewer_id' => get_current_user_id(),
            'decision' => $decision,
            'note' => sanitize_textarea_field( $args['note'] ),
            'conflict' => rest_sanitize_boolean( $args['conflict'] ),
            'conflict_note' => sanitize_textarea_field( $args['conflict_note'] ),
            'created_at' => current_time( 'mysql' ),
        );
        update_post_meta( $review_id, self::META_REVIEW_DECISIONS, array_slice( $decisions, -self::MAX_HISTORY ) );
        self::append_history( $review_id, self::META_REVIEW_HISTORY, array( 'event' => 'decision-recorded', 'decision' => $decision, 'assignment_id' => $assignment_id ) );
        return self::evaluate_review( $review_id, true );
    }

    public static function evaluate_review( $review_id, $persist = true ) {
        $review_id = absint( $review_id );
        if ( self::REVIEW_POST_TYPE !== get_post_type( $review_id ) ) {
            return array();
        }
        $assignments = get_post_meta( $review_id, self::META_REVIEW_ASSIGNMENTS, true );
        $assignments = is_array( $assignments ) ? $assignments : array();
        $notes = self::review_notes( $review_id );
        $changes = self::detect_snapshot_changes( $review_id );
        $required = max( 1, absint( get_post_meta( $review_id, self::META_REVIEW_REQUIRED_APPROVALS, true ) ?: 1 ) );

        $approval_count = 0;
        $minor_count = 0;
        $changes_requested = 0;
        $rejected = 0;
        $recused = 0;
        $conflicts = 0;
        $eligible_approvers = 0;
        foreach ( $assignments as $assignment ) {
            if ( in_array( $assignment['role'] ?? '', array( 'reviewer', 'approver', 'editor' ), true ) ) {
                $eligible_approvers++;
            }
            if ( ! empty( $assignment['conflict'] ) ) {
                $conflicts++;
            }
            switch ( $assignment['decision'] ?? 'pending' ) {
                case 'approve': $approval_count++; break;
                case 'approve-minor': $minor_count++; break;
                case 'request-changes': $changes_requested++; break;
                case 'reject': $rejected++; break;
                case 'recuse': $recused++; break;
            }
        }

        $open_notes = 0;
        $critical_notes = 0;
        foreach ( $notes as $note ) {
            if ( in_array( $note['status'], array( 'open', 'in-progress' ), true ) ) {
                $open_notes++;
                if ( in_array( $note['severity'], array( 'high', 'critical' ), true ) ) {
                    $critical_notes++;
                }
            }
        }
        $changed_documents = count(
            array_filter(
                $changes,
                static function ( $item ) {
                    return in_array( $item['state'] ?? '', array( 'changed', 'missing' ), true );
                }
            )
        );

        $approved_total = $approval_count + $minor_count;
        $blocked = $rejected > 0 || $critical_notes > 0 || $changed_documents > 0 || $conflicts > 0;
        $ready = ! $blocked && $approved_total >= $required && 0 === $changes_requested && 0 === $open_notes;
        $status = (string) get_post_meta( $review_id, self::META_REVIEW_STATUS, true ) ?: 'draft';
        if ( $ready ) {
            $status = 'approved';
        } elseif ( $rejected > 0 || $changes_requested > 0 || $critical_notes > 0 ) {
            $status = 'changes-requested';
        } elseif ( $status !== 'draft' && $status !== 'archived' ) {
            $status = 'in-review';
        }

        $report = array(
            'schema' => self::REVIEW_SCHEMA,
            'review_id' => $review_id,
            'status' => $status,
            'status_label' => self::review_statuses()[ $status ] ?? $status,
            'required_approvals' => $required,
            'eligible_approvers' => $eligible_approvers,
            'approval_count' => $approval_count,
            'minor_approval_count' => $minor_count,
            'changes_requested_count' => $changes_requested,
            'rejection_count' => $rejected,
            'recused_count' => $recused,
            'conflict_count' => $conflicts,
            'open_note_count' => $open_notes,
            'critical_note_count' => $critical_notes,
            'changed_document_count' => $changed_documents,
            'ready' => $ready,
            'blocked' => $blocked,
            'change_state' => $changes,
            'evaluated_at' => current_time( 'mysql' ),
        );

        if ( $persist ) {
            update_post_meta( $review_id, self::META_REVIEW_STATUS, $status );
            update_post_meta( $review_id, self::META_REVIEW_CHANGE_STATE, $changes );
            update_post_meta( $review_id, self::META_REVIEW_READINESS, $report );
            if ( $ready ) {
                update_post_meta( $review_id, self::META_REVIEW_COMPLETED_AT, current_time( 'mysql' ) );
            }
            delete_transient( self::TRANSIENT_DASHBOARD );
        }
        return $report;
    }

    public static function review_data( $review_id, $include_private = true ) {
        if ( self::REVIEW_POST_TYPE !== get_post_type( $review_id ) ) {
            return array();
        }
        $readiness = get_post_meta( $review_id, self::META_REVIEW_READINESS, true );
        if ( ! is_array( $readiness ) ) {
            $readiness = self::evaluate_review( $review_id, $include_private );
        }
        $data = array(
            'schema' => self::REVIEW_SCHEMA,
            'review_id' => absint( $review_id ),
            'uuid' => self::ensure_uuid( $review_id, self::META_REVIEW_UUID ),
            'title' => get_the_title( $review_id ),
            'description' => (string) get_post_field( 'post_content', $review_id ),
            'status' => (string) get_post_meta( $review_id, self::META_REVIEW_STATUS, true ) ?: 'draft',
            'type' => (string) get_post_meta( $review_id, self::META_REVIEW_TYPE, true ) ?: 'editorial',
            'gate' => (string) get_post_meta( $review_id, self::META_REVIEW_GATE, true ),
            'due_date' => (string) get_post_meta( $review_id, self::META_REVIEW_DUE_DATE, true ),
            'started_at' => (string) get_post_meta( $review_id, self::META_REVIEW_STARTED_AT, true ),
            'completed_at' => (string) get_post_meta( $review_id, self::META_REVIEW_COMPLETED_AT, true ),
            'readiness' => $readiness,
            'public' => '1' === get_post_meta( $review_id, self::META_REVIEW_PUBLIC, true ),
            'public_summary' => (string) get_post_meta( $review_id, self::META_REVIEW_PUBLIC_SUMMARY, true ),
        );
        if ( $include_private ) {
            $data['document_ids'] = self::id_list( get_post_meta( $review_id, self::META_REVIEW_DOCUMENT_IDS, true ) );
            $data['project_ids'] = self::id_list( get_post_meta( $review_id, self::META_REVIEW_PROJECT_IDS, true ) );
            $data['assignments'] = (array) get_post_meta( $review_id, self::META_REVIEW_ASSIGNMENTS, true );
            $data['snapshots'] = (array) get_post_meta( $review_id, self::META_REVIEW_SNAPSHOTS, true );
            $data['decisions'] = (array) get_post_meta( $review_id, self::META_REVIEW_DECISIONS, true );
            $data['notes'] = self::review_notes( $review_id );
            $data['history'] = (array) get_post_meta( $review_id, self::META_REVIEW_HISTORY, true );
            $data['conflict_policy'] = (string) get_post_meta( $review_id, self::META_REVIEW_COI_POLICY, true );
        }
        return $data;
    }

    public static function evaluate_package( $package_id, $persist = true ) {
        $package_id = absint( $package_id );
        if ( self::PACKAGE_POST_TYPE !== get_post_type( $package_id ) ) {
            return array();
        }

        $document_ids = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_DOCUMENT_IDS, true ) );
        $project_ids = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_PROJECT_IDS, true ) );
        $review_ids = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_REVIEW_IDS, true ) );
        $checks = array();
        $manifest_documents = array();
        $approved_reviews = 0;
        $blocked_reviews = 0;

        foreach ( $document_ids as $document_id ) {
            $exists = SC_Library_Foundation_Pages::POST_TYPE === get_post_type( $document_id );
            $published = $exists && 'publish' === get_post_status( $document_id );
            $intelligence = get_post_meta( $document_id, '_sc_document_intelligence_profile', true );
            $quality = get_post_meta( $document_id, '_sc_research_quality_profile', true );
            $source_integrity = get_post_meta( $document_id, '_sc_source_integrity_profile', true );
            $manifest_documents[] = array(
                'document_id' => $document_id,
                'title' => $exists ? get_the_title( $document_id ) : '',
                'exists' => $exists,
                'published' => $published,
                'modified' => $exists ? get_post_modified_time( 'Y-m-d H:i:s', false, $document_id, true ) : '',
                'content_hash' => $exists ? self::document_hash( $document_id ) : '',
                'intelligence_status' => is_array( $intelligence ) ? (string) ( $intelligence['status'] ?? '' ) : '',
                'quality_status' => is_array( $quality ) ? (string) ( $quality['readiness'] ?? '' ) : '',
                'integrity_status' => is_array( $source_integrity ) ? (string) ( $source_integrity['status'] ?? '' ) : '',
            );
            $checks[] = self::readiness_check(
                'document-' . $document_id,
                $exists,
                $exists ? __( 'Document exists.', 'sustainable-catalyst-library' ) : __( 'Document is missing.', 'sustainable-catalyst-library' ),
                $exists ? 'info' : 'critical'
            );
            $checks[] = self::readiness_check(
                'document-published-' . $document_id,
                $published,
                $published ? __( 'Document is published.', 'sustainable-catalyst-library' ) : __( 'Document is not published.', 'sustainable-catalyst-library' ),
                $published ? 'info' : 'high'
            );
        }

        foreach ( $review_ids as $review_id ) {
            $review = self::evaluate_review( $review_id, false );
            if ( ! $review ) {
                $checks[] = self::readiness_check(
                    'review-' . $review_id,
                    false,
                    __( 'A linked review could not be evaluated.', 'sustainable-catalyst-library' ),
                    'critical'
                );
                $blocked_reviews++;
                continue;
            }
            if ( ! empty( $review['ready'] ) ) {
                $approved_reviews++;
            } else {
                $blocked_reviews++;
            }
            $checks[] = self::readiness_check(
                'review-' . $review_id,
                ! empty( $review['ready'] ),
                ! empty( $review['ready'] )
                    ? sprintf( __( 'Review %d is approved.', 'sustainable-catalyst-library' ), $review_id )
                    : sprintf( __( 'Review %d is not ready.', 'sustainable-catalyst-library' ), $review_id ),
                ! empty( $review['ready'] ) ? 'info' : 'critical'
            );
        }

        $version = (string) get_post_meta( $package_id, self::META_PACKAGE_VERSION, true );
        $license = (string) get_post_meta( $package_id, self::META_PACKAGE_LICENSE, true );
        $release_notes = (string) get_post_meta( $package_id, self::META_PACKAGE_RELEASE_NOTES, true );
        $canonical_url = (string) get_post_meta( $package_id, self::META_PACKAGE_CANONICAL_URL, true );
        $doi = (string) get_post_meta( $package_id, self::META_PACKAGE_DOI, true );
        $embargo = (string) get_post_meta( $package_id, self::META_PACKAGE_EMBARGO_UNTIL, true );
        $publish_at = (string) get_post_meta( $package_id, self::META_PACKAGE_PUBLISH_AT, true );

        $checks[] = self::readiness_check( 'documents-selected', ! empty( $document_ids ), __( 'At least one document is selected.', 'sustainable-catalyst-library' ), 'critical' );
        $checks[] = self::readiness_check( 'reviews-selected', ! empty( $review_ids ), __( 'At least one review cycle is linked.', 'sustainable-catalyst-library' ), 'high' );
        $checks[] = self::readiness_check( 'version', '' !== trim( $version ), __( 'A publication version is recorded.', 'sustainable-catalyst-library' ), 'high' );
        $checks[] = self::readiness_check( 'license', '' !== trim( $license ), __( 'A license or rights statement is recorded.', 'sustainable-catalyst-library' ), 'critical' );
        $checks[] = self::readiness_check( 'release-notes', strlen( trim( $release_notes ) ) >= 20, __( 'Release notes describe the publication.', 'sustainable-catalyst-library' ), 'medium' );
        $checks[] = self::readiness_check( 'identifier', '' !== trim( $doi ) || '' !== trim( $canonical_url ), __( 'A DOI or canonical URL is recorded.', 'sustainable-catalyst-library' ), 'medium' );

        $critical = 0;
        $high = 0;
        $passed = 0;
        foreach ( $checks as $check ) {
            if ( ! empty( $check['passed'] ) ) {
                $passed++;
            } elseif ( 'critical' === $check['severity'] ) {
                $critical++;
            } elseif ( 'high' === $check['severity'] ) {
                $high++;
            }
        }
        $score = $checks ? (int) round( ( $passed / count( $checks ) ) * 100 ) : 0;
        $ready = 0 === $critical && 0 === $high && ! empty( $document_ids ) && ! empty( $review_ids ) && 0 === $blocked_reviews;
        $status = (string) get_post_meta( $package_id, self::META_PACKAGE_STATUS, true ) ?: 'draft';
        if ( $ready && in_array( $status, array( 'draft', 'assembling', 'review' ), true ) ) {
            $status = 'approved';
        } elseif ( ! $ready && in_array( $status, array( 'approved', 'scheduled' ), true ) ) {
            $status = 'review';
        }

        $manifest = array(
            'schema' => self::PACKAGE_SCHEMA,
            'package_id' => $package_id,
            'uuid' => self::ensure_uuid( $package_id, self::META_PACKAGE_UUID ),
            'title' => get_the_title( $package_id ),
            'version' => $version,
            'document_ids' => $document_ids,
            'project_ids' => $project_ids,
            'review_ids' => $review_ids,
            'documents' => $manifest_documents,
            'license' => $license,
            'doi' => $doi,
            'canonical_url' => $canonical_url,
            'embargo_until' => $embargo,
            'publish_at' => $publish_at,
            'generated_at' => current_time( 'mysql' ),
        );

        $report = array(
            'schema' => self::PACKAGE_SCHEMA,
            'package_id' => $package_id,
            'status' => $status,
            'status_label' => self::publication_statuses()[ $status ] ?? $status,
            'score' => $score,
            'ready' => $ready,
            'critical_failures' => $critical,
            'high_failures' => $high,
            'approved_reviews' => $approved_reviews,
            'blocked_reviews' => $blocked_reviews,
            'checks' => $checks,
            'manifest' => $manifest,
            'evaluated_at' => current_time( 'mysql' ),
        );

        if ( $persist ) {
            update_post_meta( $package_id, self::META_PACKAGE_STATUS, $status );
            update_post_meta( $package_id, self::META_PACKAGE_READINESS, $report );
            update_post_meta( $package_id, self::META_PACKAGE_MANIFEST, $manifest );
            delete_transient( self::TRANSIENT_DASHBOARD );
        }
        return $report;
    }

    private static function readiness_check( $id, $passed, $message, $severity ) {
        return array(
            'check_id' => sanitize_key( $id ),
            'passed' => (bool) $passed,
            'message' => sanitize_text_field( $message ),
            'severity' => sanitize_key( $severity ),
        );
    }

    public static function approve_package( $package_id, $note = '' ) {
        $package_id = absint( $package_id );
        if ( self::PACKAGE_POST_TYPE !== get_post_type( $package_id ) ) {
            return new WP_Error( 'invalid_publication_package', __( 'The publication package is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $package_id ) ) {
            return new WP_Error( 'publication_package_forbidden', __( 'You cannot approve this publication package.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $report = self::evaluate_package( $package_id, true );
        if ( empty( $report['ready'] ) ) {
            return new WP_Error( 'publication_package_not_ready', __( 'The publication package is not ready for approval.', 'sustainable-catalyst-library' ), array( 'status' => 409, 'readiness' => $report ) );
        }
        $approvals = get_post_meta( $package_id, self::META_PACKAGE_APPROVALS, true );
        $approvals = is_array( $approvals ) ? $approvals : array();
        $approvals[] = array(
            'approval_id' => wp_generate_uuid4(),
            'user_id' => get_current_user_id(),
            'note' => sanitize_textarea_field( $note ),
            'approved_at' => current_time( 'mysql' ),
        );
        update_post_meta( $package_id, self::META_PACKAGE_APPROVALS, array_slice( $approvals, -self::MAX_HISTORY ) );
        update_post_meta( $package_id, self::META_PACKAGE_STATUS, 'approved' );
        self::append_history( $package_id, self::META_PACKAGE_HISTORY, array( 'event' => 'package-approved', 'note' => sanitize_textarea_field( $note ) ) );
        return self::package_data( $package_id, true );
    }

    public static function transition_package( $package_id, $status, $note = '' ) {
        $package_id = absint( $package_id );
        if ( self::PACKAGE_POST_TYPE !== get_post_type( $package_id ) ) {
            return new WP_Error( 'invalid_publication_package', __( 'The publication package is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $package_id ) ) {
            return new WP_Error( 'publication_package_forbidden', __( 'You cannot update this publication package.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $status = self::allowed_value( $status, self::publication_statuses(), '' );
        if ( ! $status ) {
            return new WP_Error( 'invalid_publication_status', __( 'The publication status is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        if ( in_array( $status, array( 'approved', 'scheduled', 'published' ), true ) ) {
            $report = self::evaluate_package( $package_id, true );
            if ( empty( $report['ready'] ) ) {
                return new WP_Error( 'publication_package_not_ready', __( 'Readiness checks block this transition.', 'sustainable-catalyst-library' ), array( 'status' => 409, 'readiness' => $report ) );
            }
        }
        $previous = (string) get_post_meta( $package_id, self::META_PACKAGE_STATUS, true ) ?: 'draft';
        update_post_meta( $package_id, self::META_PACKAGE_STATUS, $status );
        if ( 'published' === $status ) {
            update_post_meta( $package_id, self::META_PACKAGE_PUBLISHED_AT, current_time( 'mysql' ) );
        }
        self::append_history(
            $package_id,
            self::META_PACKAGE_HISTORY,
            array(
                'event' => 'package-transition',
                'previous_status' => $previous,
                'status' => $status,
                'note' => sanitize_textarea_field( $note ),
            )
        );
        delete_transient( self::TRANSIENT_DASHBOARD );
        return self::package_data( $package_id, true );
    }

    public static function package_data( $package_id, $include_private = true ) {
        if ( self::PACKAGE_POST_TYPE !== get_post_type( $package_id ) ) {
            return array();
        }
        $readiness = get_post_meta( $package_id, self::META_PACKAGE_READINESS, true );
        if ( ! is_array( $readiness ) ) {
            $readiness = self::evaluate_package( $package_id, $include_private );
        }
        $data = array(
            'schema' => self::PACKAGE_SCHEMA,
            'package_id' => absint( $package_id ),
            'uuid' => self::ensure_uuid( $package_id, self::META_PACKAGE_UUID ),
            'title' => get_the_title( $package_id ),
            'description' => (string) get_post_field( 'post_content', $package_id ),
            'status' => (string) get_post_meta( $package_id, self::META_PACKAGE_STATUS, true ) ?: 'draft',
            'version' => (string) get_post_meta( $package_id, self::META_PACKAGE_VERSION, true ),
            'release_notes' => (string) get_post_meta( $package_id, self::META_PACKAGE_RELEASE_NOTES, true ),
            'license' => (string) get_post_meta( $package_id, self::META_PACKAGE_LICENSE, true ),
            'doi' => (string) get_post_meta( $package_id, self::META_PACKAGE_DOI, true ),
            'canonical_url' => (string) get_post_meta( $package_id, self::META_PACKAGE_CANONICAL_URL, true ),
            'embargo_until' => (string) get_post_meta( $package_id, self::META_PACKAGE_EMBARGO_UNTIL, true ),
            'publish_at' => (string) get_post_meta( $package_id, self::META_PACKAGE_PUBLISH_AT, true ),
            'published_at' => (string) get_post_meta( $package_id, self::META_PACKAGE_PUBLISHED_AT, true ),
            'public' => '1' === get_post_meta( $package_id, self::META_PACKAGE_PUBLIC, true ),
            'readiness' => $readiness,
        );
        if ( $include_private ) {
            $data['document_ids'] = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_DOCUMENT_IDS, true ) );
            $data['project_ids'] = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_PROJECT_IDS, true ) );
            $data['review_ids'] = self::id_list( get_post_meta( $package_id, self::META_PACKAGE_REVIEW_IDS, true ) );
            $data['approvals'] = (array) get_post_meta( $package_id, self::META_PACKAGE_APPROVALS, true );
            $data['manifest'] = (array) get_post_meta( $package_id, self::META_PACKAGE_MANIFEST, true );
            $data['history'] = (array) get_post_meta( $package_id, self::META_PACKAGE_HISTORY, true );
        }
        return $data;
    }

    public static function transparency_data( $review_id ) {
        $review = self::review_data( $review_id, false );
        if ( ! $review || empty( $review['public'] ) ) {
            return array();
        }
        $readiness = (array) ( $review['readiness'] ?? array() );
        return array(
            'schema' => self::TRANSPARENCY_SCHEMA,
            'review_id' => absint( $review_id ),
            'title' => $review['title'],
            'review_type' => $review['type'],
            'status' => $review['status'],
            'status_label' => self::review_statuses()[ $review['status'] ] ?? $review['status'],
            'public_summary' => $review['public_summary'],
            'approval_count' => absint( $readiness['approval_count'] ?? 0 ) + absint( $readiness['minor_approval_count'] ?? 0 ),
            'required_approvals' => absint( $readiness['required_approvals'] ?? 0 ),
            'open_note_count' => absint( $readiness['open_note_count'] ?? 0 ),
            'changed_document_count' => absint( $readiness['changed_document_count'] ?? 0 ),
            'started_at' => $review['started_at'],
            'completed_at' => $review['completed_at'],
        );
    }

    public function run_scheduled_publications() {
        $ids = get_posts(
            array(
                'post_type' => self::PACKAGE_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 50,
                'fields' => 'ids',
                'meta_key' => self::META_PACKAGE_STATUS,
                'meta_value' => 'scheduled',
            )
        );
        $now = current_time( 'timestamp' );
        foreach ( $ids as $package_id ) {
            $publish_at = (string) get_post_meta( $package_id, self::META_PACKAGE_PUBLISH_AT, true );
            if ( $publish_at && strtotime( $publish_at ) <= $now ) {
                self::transition_package( $package_id, 'published', __( 'Scheduled publication executed by WordPress cron.', 'sustainable-catalyst-library' ) );
            }
        }
    }

    public static function dashboard_report( $include_private = true ) {
        if ( ! $include_private ) {
            $cached = get_transient( self::TRANSIENT_DASHBOARD );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }
        $review_ids = get_posts(
            array(
                'post_type' => self::REVIEW_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'fields' => 'ids',
                'orderby' => 'modified',
                'order' => 'DESC',
            )
        );
        $package_ids = get_posts(
            array(
                'post_type' => self::PACKAGE_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'fields' => 'ids',
                'orderby' => 'modified',
                'order' => 'DESC',
            )
        );

        $reviews = array();
        $review_counts = array_fill_keys( array_keys( self::review_statuses() ), 0 );
        $open_notes = 0;
        $conflicts = 0;
        foreach ( $review_ids as $review_id ) {
            $report = self::evaluate_review( $review_id, false );
            $status = (string) ( $report['status'] ?? 'draft' );
            if ( isset( $review_counts[ $status ] ) ) {
                $review_counts[ $status ]++;
            }
            $open_notes += absint( $report['open_note_count'] ?? 0 );
            $conflicts += absint( $report['conflict_count'] ?? 0 );
            $reviews[] = array(
                'review_id' => $review_id,
                'title' => get_the_title( $review_id ),
                'type' => (string) get_post_meta( $review_id, self::META_REVIEW_TYPE, true ),
                'status' => $status,
                'due_date' => (string) get_post_meta( $review_id, self::META_REVIEW_DUE_DATE, true ),
                'ready' => ! empty( $report['ready'] ),
                'open_notes' => absint( $report['open_note_count'] ?? 0 ),
                'changed_documents' => absint( $report['changed_document_count'] ?? 0 ),
            );
        }

        $packages = array();
        $package_counts = array_fill_keys( array_keys( self::publication_statuses() ), 0 );
        foreach ( $package_ids as $package_id ) {
            $report = self::evaluate_package( $package_id, false );
            $status = (string) ( $report['status'] ?? 'draft' );
            if ( isset( $package_counts[ $status ] ) ) {
                $package_counts[ $status ]++;
            }
            $packages[] = array(
                'package_id' => $package_id,
                'title' => get_the_title( $package_id ),
                'version' => (string) get_post_meta( $package_id, self::META_PACKAGE_VERSION, true ),
                'status' => $status,
                'score' => absint( $report['score'] ?? 0 ),
                'ready' => ! empty( $report['ready'] ),
                'publish_at' => (string) get_post_meta( $package_id, self::META_PACKAGE_PUBLISH_AT, true ),
            );
        }

        $report = array(
            'schema' => self::DASHBOARD_SCHEMA,
            'review_count' => count( $reviews ),
            'active_review_count' => absint( $review_counts['invited'] ) + absint( $review_counts['in-review'] ) + absint( $review_counts['changes-requested'] ) + absint( $review_counts['revised'] ),
            'approved_review_count' => absint( $review_counts['approved'] ),
            'open_note_count' => $open_notes,
            'conflict_count' => $conflicts,
            'package_count' => count( $packages ),
            'ready_package_count' => count( array_filter( $packages, static function ( $item ) { return ! empty( $item['ready'] ); } ) ),
            'scheduled_package_count' => absint( $package_counts['scheduled'] ),
            'published_package_count' => absint( $package_counts['published'] ),
            'reviews' => $reviews,
            'packages' => $packages,
            'migration' => self::migration_state(),
            'generated_at' => current_time( 'mysql' ),
        );
        if ( ! $include_private ) {
            set_transient( self::TRANSIENT_DASHBOARD, $report, 10 * MINUTE_IN_SECONDS );
        }
        return $report;
    }

    public function render_review_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_review_' . $post->ID, 'sc_library_review_nonce' );
        $status = (string) get_post_meta( $post->ID, self::META_REVIEW_STATUS, true ) ?: 'draft';
        $type = (string) get_post_meta( $post->ID, self::META_REVIEW_TYPE, true ) ?: 'editorial';
        $assignments = get_post_meta( $post->ID, self::META_REVIEW_ASSIGNMENTS, true );
        $assignments = is_array( $assignments ) ? $assignments : array();
        $notes = self::review_notes( $post->ID );
        ?>
        <div class="sc-review-editor" data-sc-review-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-review-field-grid">
                <label><strong><?php esc_html_e( 'Review status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_review_status"><?php foreach ( self::review_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Review type', 'sustainable-catalyst-library' ); ?></strong><select name="sc_review_type"><?php foreach ( self::review_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Review gate', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_review_gate" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_REVIEW_GATE, true ) ?: 'editorial-review' ); ?>"></label>
                <label><strong><?php esc_html_e( 'Required approvals', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="1" name="sc_review_required_approvals" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_REVIEW_REQUIRED_APPROVALS, true ) ?: 1 ); ?>"></label>
                <label><strong><?php esc_html_e( 'Due date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_review_due_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_REVIEW_DUE_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Knowledge Library document IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_review_document_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_REVIEW_DOCUMENT_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Research Project IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_review_project_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_REVIEW_PROJECT_IDS, true ) ) ) ); ?>"></label>
            </div>

            <label><strong><?php esc_html_e( 'Conflict-of-interest policy', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_review_coi_policy" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_REVIEW_COI_POLICY, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Public review summary', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_review_public_summary" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_REVIEW_PUBLIC_SUMMARY, true ) ); ?></textarea></label>
            <label><input type="checkbox" name="sc_review_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_REVIEW_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Publish a limited review-transparency summary', 'sustainable-catalyst-library' ); ?></label>
            <label><input type="checkbox" name="sc_review_refresh_snapshots" value="1"> <?php esc_html_e( 'Replace review snapshots with current document versions on save', 'sustainable-catalyst-library' ); ?></label>

            <section data-sc-review-assignment-editor>
                <div class="sc-review-section-heading"><h3><?php esc_html_e( 'Reviewer assignments', 'sustainable-catalyst-library' ); ?></h3><button type="button" class="button" data-sc-add-assignment><?php esc_html_e( 'Add Assignment', 'sustainable-catalyst-library' ); ?></button></div>
                <div data-sc-assignment-rows>
                    <?php foreach ( $assignments as $index => $assignment ) : ?>
                        <?php echo self::render_assignment_row( $index, $assignment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
                <template data-sc-assignment-template><?php echo self::render_assignment_row( '__INDEX__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
            </section>

            <section class="sc-review-note-editor">
                <h3><?php esc_html_e( 'Add review note', 'sustainable-catalyst-library' ); ?></h3>
                <div class="sc-review-note-form">
                    <select data-sc-note-type><?php foreach ( self::note_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                    <select data-sc-note-severity><option value="info"><?php esc_html_e( 'Info', 'sustainable-catalyst-library' ); ?></option><option value="low"><?php esc_html_e( 'Low', 'sustainable-catalyst-library' ); ?></option><option value="medium"><?php esc_html_e( 'Medium', 'sustainable-catalyst-library' ); ?></option><option value="high"><?php esc_html_e( 'High', 'sustainable-catalyst-library' ); ?></option><option value="critical"><?php esc_html_e( 'Critical', 'sustainable-catalyst-library' ); ?></option></select>
                    <input type="number" min="0" data-sc-note-document placeholder="<?php esc_attr_e( 'Document ID', 'sustainable-catalyst-library' ); ?>">
                    <input type="text" data-sc-note-section placeholder="<?php esc_attr_e( 'Section or anchor', 'sustainable-catalyst-library' ); ?>">
                    <textarea data-sc-note-body rows="3" placeholder="<?php esc_attr_e( 'Review note', 'sustainable-catalyst-library' ); ?>"></textarea>
                    <button type="button" class="button" data-sc-add-note><?php esc_html_e( 'Add Note', 'sustainable-catalyst-library' ); ?></button>
                    <span data-sc-review-action-status aria-live="polite"></span>
                </div>
            </section>

            <section>
                <h3><?php esc_html_e( 'Review notes', 'sustainable-catalyst-library' ); ?></h3>
                <?php echo self::render_note_list( $notes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        </div>
        <?php
    }

    private static function render_assignment_row( $index, $assignment ) {
        $assignment = wp_parse_args(
            is_array( $assignment ) ? $assignment : array(),
            array(
                'assignment_id' => '', 'user_id' => 0, 'email' => '', 'display_name' => '',
                'role' => 'reviewer', 'review_type' => 'editorial', 'status' => 'invited',
                'decision' => 'pending', 'decision_note' => '', 'conflict' => false,
                'conflict_note' => '', 'invited_at' => '', 'responded_at' => '',
            )
        );
        ob_start();
        ?>
        <div class="sc-review-assignment-row" data-sc-assignment-row>
            <input type="hidden" data-name="assignment_id" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][assignment_id]" value="<?php echo esc_attr( $assignment['assignment_id'] ); ?>">
            <label><?php esc_html_e( 'User ID', 'sustainable-catalyst-library' ); ?><input type="number" min="0" data-name="user_id" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][user_id]" value="<?php echo esc_attr( $assignment['user_id'] ); ?>"></label>
            <label><?php esc_html_e( 'Email', 'sustainable-catalyst-library' ); ?><input type="email" data-name="email" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][email]" value="<?php echo esc_attr( $assignment['email'] ); ?>"></label>
            <label><?php esc_html_e( 'Display name', 'sustainable-catalyst-library' ); ?><input type="text" data-name="display_name" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][display_name]" value="<?php echo esc_attr( $assignment['display_name'] ); ?>"></label>
            <label><?php esc_html_e( 'Role', 'sustainable-catalyst-library' ); ?><select data-name="role" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][role]"><?php foreach ( self::reviewer_roles() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $assignment['role'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><?php esc_html_e( 'Review type', 'sustainable-catalyst-library' ); ?><select data-name="review_type" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][review_type]"><?php foreach ( self::review_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $assignment['review_type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><?php esc_html_e( 'Decision', 'sustainable-catalyst-library' ); ?><select data-name="decision" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][decision]"><?php foreach ( self::decision_values() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $assignment['decision'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label class="sc-review-assignment-note"><?php esc_html_e( 'Decision note', 'sustainable-catalyst-library' ); ?><input type="text" data-name="decision_note" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][decision_note]" value="<?php echo esc_attr( $assignment['decision_note'] ); ?>"></label>
            <label><input type="checkbox" data-name="conflict" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][conflict]" value="1" <?php checked( ! empty( $assignment['conflict'] ) ); ?>> <?php esc_html_e( 'Conflict disclosed', 'sustainable-catalyst-library' ); ?></label>
            <label class="sc-review-assignment-note"><?php esc_html_e( 'Conflict note', 'sustainable-catalyst-library' ); ?><input type="text" data-name="conflict_note" name="sc_review_assignments[<?php echo esc_attr( $index ); ?>][conflict_note]" value="<?php echo esc_attr( $assignment['conflict_note'] ); ?>"></label>
            <button type="button" class="button-link-delete" data-sc-remove-row><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_note_list( $notes ) {
        if ( ! $notes ) {
            return '<p>' . esc_html__( 'No review notes have been recorded.', 'sustainable-catalyst-library' ) . '</p>';
        }
        ob_start();
        echo '<ol class="sc-review-note-list">';
        foreach ( $notes as $note ) {
            echo '<li class="severity-' . esc_attr( $note['severity'] ) . ' status-' . esc_attr( $note['status'] ) . '">';
            echo '<header><strong>' . esc_html( self::note_types()[ $note['type'] ] ?? $note['type'] ) . '</strong><span>' . esc_html( ucfirst( $note['severity'] ) ) . ' · ' . esc_html( ucfirst( str_replace( '-', ' ', $note['status'] ) ) ) . '</span></header>';
            if ( $note['section'] ) {
                echo '<p class="sc-review-note-anchor">' . esc_html( $note['section'] ) . '</p>';
            }
            if ( $note['quote'] ) {
                echo '<blockquote>' . esc_html( $note['quote'] ) . '</blockquote>';
            }
            echo '<p>' . esc_html( $note['body'] ) . '</p>';
            if ( $note['resolution'] ) {
                echo '<footer><strong>' . esc_html__( 'Resolution:', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html( $note['resolution'] ) . '</footer>';
            }
            echo '</li>';
        }
        echo '</ol>';
        return ob_get_clean();
    }

    public function render_review_status_box( $post ) {
        $report = self::evaluate_review( $post->ID, false );
        ?>
        <div class="sc-review-status-box">
            <p><span class="sc-review-state status-<?php echo esc_attr( $report['status'] ?? 'draft' ); ?>"><?php echo esc_html( $report['status_label'] ?? __( 'Draft', 'sustainable-catalyst-library' ) ); ?></span></p>
            <dl>
                <div><dt><?php esc_html_e( 'Approvals', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['approval_count'] ?? 0 ) + absint( $report['minor_approval_count'] ?? 0 ) ); ?>/<?php echo esc_html( absint( $report['required_approvals'] ?? 1 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Open notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['open_note_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Critical notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['critical_note_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Changed documents', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['changed_document_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Conflicts', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['conflict_count'] ?? 0 ) ); ?></dd></div>
            </dl>
            <button type="button" class="button" data-sc-refresh-review data-review-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Refresh Review Readiness', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-review-status aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_package_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_package_' . $post->ID, 'sc_library_package_nonce' );
        $status = (string) get_post_meta( $post->ID, self::META_PACKAGE_STATUS, true ) ?: 'draft';
        ?>
        <div class="sc-publication-editor" data-sc-package-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-review-field-grid">
                <label><strong><?php esc_html_e( 'Package status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_package_status"><?php foreach ( self::publication_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Release version', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_version" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PACKAGE_VERSION, true ) ); ?>" placeholder="1.0.0"></label>
                <label><strong><?php esc_html_e( 'Document IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_document_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_PACKAGE_DOCUMENT_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Project IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_project_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_PACKAGE_PROJECT_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Review IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_review_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_PACKAGE_REVIEW_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'License or rights statement', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_license" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PACKAGE_LICENSE, true ) ); ?>" placeholder="MIT; CC BY 4.0; All rights reserved"></label>
                <label><strong><?php esc_html_e( 'DOI', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_package_doi" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PACKAGE_DOI, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Canonical URL', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_package_canonical_url" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PACKAGE_CANONICAL_URL, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Embargo until', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_package_embargo_until" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PACKAGE_EMBARGO_UNTIL, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Publish at', 'sustainable-catalyst-library' ); ?></strong><input type="datetime-local" name="sc_package_publish_at" value="<?php echo esc_attr( str_replace( ' ', 'T', get_post_meta( $post->ID, self::META_PACKAGE_PUBLISH_AT, true ) ) ); ?>"></label>
            </div>
            <label><strong><?php esc_html_e( 'Release notes', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_package_release_notes" rows="7"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PACKAGE_RELEASE_NOTES, true ) ); ?></textarea></label>
            <label><input type="checkbox" name="sc_package_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PACKAGE_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Publish the release record after publication', 'sustainable-catalyst-library' ); ?></label>
            <div class="sc-review-actions">
                <button type="button" class="button button-primary" data-sc-evaluate-package><?php esc_html_e( 'Evaluate Publication Readiness', 'sustainable-catalyst-library' ); ?></button>
                <span data-sc-package-action-status aria-live="polite"></span>
            </div>
        </div>
        <?php
    }

    public function render_package_status_box( $post ) {
        $report = self::evaluate_package( $post->ID, false );
        ?>
        <div class="sc-review-status-box">
            <p><span class="sc-review-state status-<?php echo esc_attr( $report['status'] ?? 'draft' ); ?>"><?php echo esc_html( $report['status_label'] ?? __( 'Draft', 'sustainable-catalyst-library' ) ); ?></span></p>
            <div class="sc-publication-score"><strong><?php echo esc_html( absint( $report['score'] ?? 0 ) ); ?></strong><span>/100</span></div>
            <dl>
                <div><dt><?php esc_html_e( 'Critical failures', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['critical_failures'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'High failures', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['high_failures'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Approved reviews', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $report['approved_reviews'] ?? 0 ) ); ?></dd></div>
            </dl>
            <?php if ( ! empty( $report['checks'] ) ) : ?><ul class="sc-publication-checks"><?php foreach ( $report['checks'] as $check ) : ?><li class="<?php echo $check['passed'] ? 'passed' : 'failed'; ?> severity-<?php echo esc_attr( $check['severity'] ); ?>"><?php echo esc_html( $check['message'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <?php
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $report = self::dashboard_report( true );
        ?>
        <div class="wrap sc-review-center">
            <p class="sc-review-kicker"><?php esc_html_e( 'Knowledge Library v3.8.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Collaborative Review and Research Publishing', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Coordinate structured review, resolve annotations, detect post-review document changes, enforce approvals, and assemble auditable research publication packages.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-review-metrics">
                <article><strong><?php echo esc_html( $report['review_count'] ); ?></strong><span><?php esc_html_e( 'Review cycles', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['active_review_count'] ); ?></strong><span><?php esc_html_e( 'Active reviews', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['approved_review_count'] ); ?></strong><span><?php esc_html_e( 'Approved reviews', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['open_note_count'] ); ?></strong><span><?php esc_html_e( 'Open notes', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['ready_package_count'] ); ?></strong><span><?php esc_html_e( 'Ready packages', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['published_package_count'] ); ?></strong><span><?php esc_html_e( 'Published releases', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-review-migration">
                <div>
                    <h2><?php esc_html_e( 'Review and publishing migration', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d records processed', 'sustainable-catalyst-library' ), $report['migration']['processed'], $report['migration']['total'] ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-review-migration-state><?php echo esc_html( ucfirst( $report['migration']['status'] ) ); ?></span></p>
                </div>
                <button type="button" class="button button-primary" data-sc-review-run-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-review-migration-status aria-live="polite"></div>
            </section>

            <div class="sc-review-section-heading">
                <div><h2><?php esc_html_e( 'Research review register', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Approvals remain blocked by unresolved high-risk notes, conflicts, rejected decisions, or documents changed after snapshot.', 'sustainable-catalyst-library' ); ?></p></div>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::REVIEW_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Review Cycle', 'sustainable-catalyst-library' ); ?></a>
            </div>
            <?php if ( $report['reviews'] ) : ?>
                <div class="sc-review-table-wrap"><table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Review', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Open notes', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Changed documents', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Due', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody><?php foreach ( $report['reviews'] as $item ) : ?><tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $item['review_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                        <td><?php echo esc_html( self::review_types()[ $item['type'] ] ?? $item['type'] ); ?></td>
                        <td><span class="sc-review-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( self::review_statuses()[ $item['status'] ] ?? $item['status'] ); ?></span></td>
                        <td><?php echo esc_html( $item['open_notes'] ); ?></td>
                        <td><?php echo esc_html( $item['changed_documents'] ); ?></td>
                        <td><?php echo esc_html( $item['due_date'] ?: '—' ); ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php else : ?><p><?php esc_html_e( 'No review cycles have been created.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>

            <div class="sc-review-section-heading">
                <div><h2><?php esc_html_e( 'Publication packages', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Packages assemble documents, projects, review approvals, rights, release notes, identifiers, embargoes, schedules, and an immutable publication manifest.', 'sustainable-catalyst-library' ); ?></p></div>
                <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::PACKAGE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Publication Package', 'sustainable-catalyst-library' ); ?></a>
            </div>
            <?php if ( $report['packages'] ) : ?>
                <div class="sc-review-table-wrap"><table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Package', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Readiness', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Publish at', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody><?php foreach ( $report['packages'] as $item ) : ?><tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $item['package_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                        <td><?php echo esc_html( $item['version'] ?: '—' ); ?></td>
                        <td><span class="sc-review-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( self::publication_statuses()[ $item['status'] ] ?? $item['status'] ); ?></span></td>
                        <td><?php echo esc_html( $item['score'] ); ?>/100</td>
                        <td><?php echo esc_html( $item['publish_at'] ?: '—' ); ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php else : ?><p><?php esc_html_e( 'No publication packages have been created.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
        </div>
        <?php
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $this->send_ajax_result( self::run_migration_batch( self::MIGRATION_BATCH ), 'migration' );
    }

    public function ajax_refresh_review() {
        $this->verify_ajax( 'edit_posts' );
        $review_id = absint( wp_unslash( $_POST['review_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $review_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot evaluate this review.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $this->send_ajax_result( self::evaluate_review( $review_id, true ), 'review' );
    }

    public function ajax_add_note() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::create_note(
            absint( wp_unslash( $_POST['review_id'] ?? 0 ) ),
            array(
                'document_id' => wp_unslash( $_POST['document_id'] ?? 0 ),
                'type' => wp_unslash( $_POST['type'] ?? 'comment' ),
                'severity' => wp_unslash( $_POST['severity'] ?? 'info' ),
                'section' => wp_unslash( $_POST['section'] ?? '' ),
                'body' => wp_unslash( $_POST['body'] ?? '' ),
            )
        );
        $this->send_ajax_result( $result, 'note' );
    }

    public function ajax_record_decision() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::record_decision(
            absint( wp_unslash( $_POST['review_id'] ?? 0 ) ),
            array(
                'assignment_id' => wp_unslash( $_POST['assignment_id'] ?? '' ),
                'decision' => wp_unslash( $_POST['decision'] ?? 'pending' ),
                'note' => wp_unslash( $_POST['note'] ?? '' ),
                'conflict' => wp_unslash( $_POST['conflict'] ?? false ),
                'conflict_note' => wp_unslash( $_POST['conflict_note'] ?? '' ),
            )
        );
        $this->send_ajax_result( $result, 'review' );
    }

    public function ajax_evaluate_package() {
        $this->verify_ajax( 'edit_posts' );
        $package_id = absint( wp_unslash( $_POST['package_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $package_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot evaluate this package.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $this->send_ajax_result( self::evaluate_package( $package_id, true ), 'package' );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_review_publishing_v380', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    private function send_ajax_result( $result, $key ) {
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'data' => $data ), $status );
        }
        wp_send_json_success( array( $key => $result ) );
    }

    public function run_scheduled_migration() {
        $state = self::migration_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public static function run_migration_batch( $limit = self::MIGRATION_BATCH ) {
        global $wpdb;
        if ( get_transient( self::TRANSIENT_LOCK ) ) {
            return new WP_Error( 'review_migration_locked', __( 'Another review migration batch is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );

        try {
            $post_types = array( self::REVIEW_POST_TYPE, self::PACKAGE_POST_TYPE );
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
            $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status IN ({$status_placeholders})";
            $state['total'] = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( $post_types, $statuses ) ) ) );

            $query_sql = "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status IN ({$status_placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
            $rows = (array) $wpdb->get_results(
                $wpdb->prepare(
                    $query_sql,
                    array_merge( $post_types, $statuses, array( absint( $state['cursor'] ), max( 1, min( 100, absint( $limit ) ) ) ) ),
                    ARRAY_A
                )
            );

            if ( ! $rows ) {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
                update_option( self::OPTION_MIGRATION, $state, false );
                delete_transient( self::TRANSIENT_LOCK );
                return $state;
            }

            foreach ( $rows as $row ) {
                $post_id = absint( $row['ID'] ?? 0 );
                try {
                    if ( self::REVIEW_POST_TYPE === ( $row['post_type'] ?? '' ) ) {
                        self::ensure_uuid( $post_id, self::META_REVIEW_UUID );
                        if ( ! get_post_meta( $post_id, self::META_REVIEW_STATUS, true ) ) {
                            update_post_meta( $post_id, self::META_REVIEW_STATUS, 'draft' );
                        }
                        if ( ! get_post_meta( $post_id, self::META_REVIEW_TYPE, true ) ) {
                            update_post_meta( $post_id, self::META_REVIEW_TYPE, 'editorial' );
                        }
                        if ( ! get_post_meta( $post_id, self::META_REVIEW_REQUIRED_APPROVALS, true ) ) {
                            update_post_meta( $post_id, self::META_REVIEW_REQUIRED_APPROVALS, 1 );
                        }
                        if ( ! get_post_meta( $post_id, self::META_REVIEW_SNAPSHOTS, true ) ) {
                            update_post_meta( $post_id, self::META_REVIEW_SNAPSHOTS, self::build_snapshots( self::id_list( get_post_meta( $post_id, self::META_REVIEW_DOCUMENT_IDS, true ) ) ) );
                        }
                        self::evaluate_review( $post_id, true );
                    } elseif ( self::PACKAGE_POST_TYPE === ( $row['post_type'] ?? '' ) ) {
                        self::ensure_uuid( $post_id, self::META_PACKAGE_UUID );
                        if ( ! get_post_meta( $post_id, self::META_PACKAGE_STATUS, true ) ) {
                            update_post_meta( $post_id, self::META_PACKAGE_STATUS, 'draft' );
                        }
                        self::evaluate_package( $post_id, true );
                    }
                } catch ( Throwable $error ) {
                    $state['failures'][] = array( 'post_id' => $post_id, 'message' => sanitize_text_field( $error->getMessage() ), 'time' => current_time( 'mysql' ) );
                    $state['failures'] = array_slice( $state['failures'], -self::MAX_HISTORY );
                }
                $state['cursor'] = $post_id;
                $state['processed']++;
            }
            $state['status'] = $state['processed'] >= $state['total'] ? 'complete' : 'pending';
            if ( 'complete' === $state['status'] ) {
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION, $state, false );
            delete_transient( self::TRANSIENT_DASHBOARD );
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION, $state, false );
            delete_transient( self::TRANSIENT_LOCK );
            return new WP_Error( 'review_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
        }
        delete_transient( self::TRANSIENT_LOCK );
        return $state;
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/reviews',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'rest_create_review' ),
                'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/reviews/(?P<id>\d+)',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'rest_review' ),
                    'permission_callback' => array( $this, 'rest_can_read_review' ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array( $this, 'rest_update_review' ),
                    'permission_callback' => array( $this, 'rest_can_edit_review' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/reviews/(?P<id>\d+)/notes',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'rest_create_note' ),
                'permission_callback' => array( $this, 'rest_can_edit_review' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/review-notes/(?P<id>\d+)',
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'rest_resolve_note' ),
                'permission_callback' => array( $this, 'rest_can_edit_note' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/reviews/(?P<id>\d+)/decision',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'rest_record_decision' ),
                'permission_callback' => array( $this, 'rest_can_edit_review' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/reviews/(?P<id>\d+)/transparency',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'rest_transparency' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/publication-packages',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'rest_create_package' ),
                'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/publication-packages/(?P<id>\d+)',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'rest_package' ),
                    'permission_callback' => array( $this, 'rest_can_read_package' ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array( $this, 'rest_update_package' ),
                    'permission_callback' => array( $this, 'rest_can_edit_package' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/publication-packages/(?P<id>\d+)/evaluate',
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'rest_evaluate_package' ),
                'permission_callback' => array( $this, 'rest_can_edit_package' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/publication-packages/(?P<id>\d+)/transition',
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'rest_transition_package' ),
                'permission_callback' => array( $this, 'rest_can_edit_package' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/review-publishing/dashboard',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'rest_dashboard' ),
                'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/review-publishing/migration',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'rest_migration_state' ),
                    'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array( $this, 'rest_run_migration' ),
                    'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
                ),
            )
        );
    }

    public function rest_create_review( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $review_id = wp_insert_post(
            array(
                'post_type' => self::REVIEW_POST_TYPE,
                'post_status' => 'publish',
                'post_title' => sanitize_text_field( $payload['title'] ?? __( 'Research Review', 'sustainable-catalyst-library' ) ),
                'post_content' => sanitize_textarea_field( $payload['description'] ?? '' ),
                'post_author' => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $review_id ) ) {
            return $review_id;
        }
        self::ensure_uuid( $review_id, self::META_REVIEW_UUID );
        update_post_meta( $review_id, self::META_REVIEW_STATUS, 'draft' );
        update_post_meta( $review_id, self::META_REVIEW_TYPE, self::allowed_value( $payload['type'] ?? 'editorial', self::review_types(), 'editorial' ) );
        update_post_meta( $review_id, self::META_REVIEW_DOCUMENT_IDS, self::id_list( $payload['document_ids'] ?? array() ) );
        update_post_meta( $review_id, self::META_REVIEW_PROJECT_IDS, self::id_list( $payload['project_ids'] ?? array() ) );
        update_post_meta( $review_id, self::META_REVIEW_REQUIRED_APPROVALS, max( 1, absint( $payload['required_approvals'] ?? 1 ) ) );
        update_post_meta( $review_id, self::META_REVIEW_ASSIGNMENTS, self::sanitize_assignments( $payload['assignments'] ?? array() ) );
        update_post_meta( $review_id, self::META_REVIEW_SNAPSHOTS, self::build_snapshots( self::id_list( $payload['document_ids'] ?? array() ) ) );
        self::evaluate_review( $review_id, true );
        return rest_ensure_response( self::review_data( $review_id, true ) );
    }

    public function rest_review( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_post', $id );
        $data = self::review_data( $id, $include_private );
        return $data ? rest_ensure_response( $data ) : new WP_Error( 'review_not_found', __( 'Review not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_update_review( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        foreach ( array( 'status' => self::META_REVIEW_STATUS, 'type' => self::META_REVIEW_TYPE, 'gate' => self::META_REVIEW_GATE, 'due_date' => self::META_REVIEW_DUE_DATE, 'public_summary' => self::META_REVIEW_PUBLIC_SUMMARY ) as $field => $meta ) {
            if ( array_key_exists( $field, $payload ) ) {
                update_post_meta( $id, $meta, sanitize_text_field( $payload[ $field ] ) );
            }
        }
        if ( isset( $payload['document_ids'] ) ) {
            update_post_meta( $id, self::META_REVIEW_DOCUMENT_IDS, self::id_list( $payload['document_ids'] ) );
        }
        if ( isset( $payload['assignments'] ) ) {
            update_post_meta( $id, self::META_REVIEW_ASSIGNMENTS, self::sanitize_assignments( $payload['assignments'] ) );
        }
        if ( ! empty( $payload['refresh_snapshots'] ) ) {
            update_post_meta( $id, self::META_REVIEW_SNAPSHOTS, self::build_snapshots( self::id_list( get_post_meta( $id, self::META_REVIEW_DOCUMENT_IDS, true ) ) ) );
        }
        return rest_ensure_response( self::review_data( $id, true ) );
    }

    public function rest_create_note( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $result = self::create_note( absint( $request['id'] ), is_array( $payload ) ? $payload : array() );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_resolve_note( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::resolve_note( absint( $request['id'] ), $payload['resolution'] ?? '', $payload['status'] ?? 'resolved' );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_record_decision( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $result = self::record_decision( absint( $request['id'] ), is_array( $payload ) ? $payload : array() );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_transparency( WP_REST_Request $request ) {
        $data = self::transparency_data( absint( $request['id'] ) );
        return $data ? rest_ensure_response( $data ) : new WP_Error( 'review_transparency_not_found', __( 'Public review transparency is not available.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_create_package( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $package_id = wp_insert_post(
            array(
                'post_type' => self::PACKAGE_POST_TYPE,
                'post_status' => 'publish',
                'post_title' => sanitize_text_field( $payload['title'] ?? __( 'Research Publication Package', 'sustainable-catalyst-library' ) ),
                'post_content' => sanitize_textarea_field( $payload['description'] ?? '' ),
                'post_author' => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $package_id ) ) {
            return $package_id;
        }
        self::ensure_uuid( $package_id, self::META_PACKAGE_UUID );
        update_post_meta( $package_id, self::META_PACKAGE_STATUS, 'draft' );
        update_post_meta( $package_id, self::META_PACKAGE_DOCUMENT_IDS, self::id_list( $payload['document_ids'] ?? array() ) );
        update_post_meta( $package_id, self::META_PACKAGE_PROJECT_IDS, self::id_list( $payload['project_ids'] ?? array() ) );
        update_post_meta( $package_id, self::META_PACKAGE_REVIEW_IDS, self::id_list( $payload['review_ids'] ?? array() ) );
        update_post_meta( $package_id, self::META_PACKAGE_VERSION, sanitize_text_field( $payload['version'] ?? '' ) );
        update_post_meta( $package_id, self::META_PACKAGE_LICENSE, sanitize_text_field( $payload['license'] ?? '' ) );
        update_post_meta( $package_id, self::META_PACKAGE_RELEASE_NOTES, sanitize_textarea_field( $payload['release_notes'] ?? '' ) );
        self::evaluate_package( $package_id, true );
        return rest_ensure_response( self::package_data( $package_id, true ) );
    }

    public function rest_package( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $data = self::package_data( $id, current_user_can( 'edit_post', $id ) );
        return $data ? rest_ensure_response( $data ) : new WP_Error( 'publication_package_not_found', __( 'Publication package not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_update_package( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        if ( isset( $payload['document_ids'] ) ) update_post_meta( $id, self::META_PACKAGE_DOCUMENT_IDS, self::id_list( $payload['document_ids'] ) );
        if ( isset( $payload['project_ids'] ) ) update_post_meta( $id, self::META_PACKAGE_PROJECT_IDS, self::id_list( $payload['project_ids'] ) );
        if ( isset( $payload['review_ids'] ) ) update_post_meta( $id, self::META_PACKAGE_REVIEW_IDS, self::id_list( $payload['review_ids'] ) );
        foreach ( array( 'version' => self::META_PACKAGE_VERSION, 'release_notes' => self::META_PACKAGE_RELEASE_NOTES, 'license' => self::META_PACKAGE_LICENSE, 'doi' => self::META_PACKAGE_DOI, 'canonical_url' => self::META_PACKAGE_CANONICAL_URL, 'embargo_until' => self::META_PACKAGE_EMBARGO_UNTIL, 'publish_at' => self::META_PACKAGE_PUBLISH_AT ) as $field => $meta ) {
            if ( array_key_exists( $field, $payload ) ) update_post_meta( $id, $meta, sanitize_text_field( $payload[ $field ] ) );
        }
        return rest_ensure_response( self::package_data( $id, true ) );
    }

    public function rest_evaluate_package( WP_REST_Request $request ) {
        return rest_ensure_response( self::evaluate_package( absint( $request['id'] ), true ) );
    }

    public function rest_transition_package( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::transition_package( absint( $request['id'] ), $payload['status'] ?? '', $payload['note'] ?? '' );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_dashboard() { return rest_ensure_response( self::dashboard_report( true ) ); }
    public function rest_migration_state() { return rest_ensure_response( self::migration_state() ); }
    public function rest_run_migration( WP_REST_Request $request ) {
        $result = self::run_migration_batch( max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_review( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'edit_post', $id ) || ! empty( self::transparency_data( $id ) );
    }
    public function rest_can_edit_review( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return self::REVIEW_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
    }
    public function rest_can_edit_note( WP_REST_Request $request ) {
        $note_id = absint( $request['id'] );
        $review_id = absint( get_post_meta( $note_id, self::META_NOTE_REVIEW_ID, true ) );
        return self::NOTE_POST_TYPE === get_post_type( $note_id ) && current_user_can( 'edit_post', $review_id );
    }
    public function rest_can_read_package( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'edit_post', $id ) || self::package_is_public( $id );
    }
    public function rest_can_edit_package( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return self::PACKAGE_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) return $response;
        $route = $request->get_route();
        if ( false === strpos( $route, '/sc-library/v1/review' ) && false === strpos( $route, '/sc-library/v1/publication-packages' ) ) return $response;
        $public_transparency = false !== strpos( $route, '/transparency' );
        if ( current_user_can( 'edit_posts' ) || ! $public_transparency ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function shortcode_review_transparency( $atts ) {
        $atts = shortcode_atts( array( 'review' => '' ), $atts, 'sc_review_transparency' );
        $review_id = self::resolve_id( $atts['review'], self::REVIEW_POST_TYPE );
        $data = self::transparency_data( $review_id );
        if ( ! $data ) return '';
        wp_enqueue_style( 'sc-library-collaborative-review-publishing' );
        ob_start();
        ?>
        <section class="sc-review-transparency">
            <header><p class="sc-review-kicker"><?php esc_html_e( 'Review transparency', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $data['title'] ); ?></h2><span class="sc-review-state status-<?php echo esc_attr( $data['status'] ); ?>"><?php echo esc_html( $data['status_label'] ); ?></span></header>
            <p><?php echo nl2br( esc_html( $data['public_summary'] ) ); ?></p>
            <dl>
                <div><dt><?php esc_html_e( 'Review type', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( self::review_types()[ $data['review_type'] ] ?? $data['review_type'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Approvals', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['approval_count'] . '/' . $data['required_approvals'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Open review notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['open_note_count'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Changed documents', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['changed_document_count'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Completed', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['completed_at'] ?: '—' ); ?></dd></div>
            </dl>
            <footer><p><?php esc_html_e( 'This summary confirms recorded workflow activity. It does not disclose private reviewer identities, annotations, conflicts, or deliberations.', 'sustainable-catalyst-library' ); ?></p></footer>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_publication_record( $atts ) {
        $atts = shortcode_atts( array( 'package' => '' ), $atts, 'sc_publication_record' );
        $package_id = self::resolve_id( $atts['package'], self::PACKAGE_POST_TYPE );
        if ( ! self::package_is_public( $package_id ) ) return '';
        $data = self::package_data( $package_id, false );
        wp_enqueue_style( 'sc-library-collaborative-review-publishing' );
        ob_start();
        ?>
        <article class="sc-publication-record">
            <header><p class="sc-review-kicker"><?php esc_html_e( 'Research publication record', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $data['title'] ); ?></h2><p class="sc-publication-version"><?php echo esc_html( $data['version'] ); ?></p></header>
            <p><?php echo nl2br( esc_html( $data['release_notes'] ) ); ?></p>
            <dl>
                <div><dt><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( self::publication_statuses()[ $data['status'] ] ?? $data['status'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'License', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['license'] ?: '—' ); ?></dd></div>
                <div><dt>DOI</dt><dd><?php echo esc_html( $data['doi'] ?: '—' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Published', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['published_at'] ?: '—' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Canonical record', 'sustainable-catalyst-library' ); ?></dt><dd><?php if ( $data['canonical_url'] ) : ?><a href="<?php echo esc_url( $data['canonical_url'] ); ?>"><?php echo esc_html( $data['canonical_url'] ); ?></a><?php else : ?>—<?php endif; ?></dd></div>
            </dl>
        </article>
        <?php
        return ob_get_clean();
    }

    public function shortcode_release_history( $atts ) {
        $atts = shortcode_atts( array( 'limit' => 20 ), $atts, 'sc_research_release_history' );
        $ids = get_posts(
            array(
                'post_type' => self::PACKAGE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $atts['limit'] ) ) ),
                'fields' => 'ids',
                'meta_query' => array(
                    array( 'key' => self::META_PACKAGE_STATUS, 'value' => 'published' ),
                    array( 'key' => self::META_PACKAGE_PUBLIC, 'value' => '1' ),
                ),
                'orderby' => 'meta_value',
                'meta_key' => self::META_PACKAGE_PUBLISHED_AT,
                'order' => 'DESC',
            )
        );
        if ( ! $ids ) return '';
        wp_enqueue_style( 'sc-library-collaborative-review-publishing' );
        ob_start();
        ?><section class="sc-release-history"><header><p class="sc-review-kicker"><?php esc_html_e( 'Research publishing', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Release history', 'sustainable-catalyst-library' ); ?></h2></header><ol><?php foreach ( $ids as $id ) : $data = self::package_data( $id, false ); ?><li><article><h3><?php echo esc_html( $data['title'] ); ?></h3><p><strong><?php echo esc_html( $data['version'] ); ?></strong> · <?php echo esc_html( $data['published_at'] ); ?></p><p><?php echo esc_html( wp_trim_words( $data['release_notes'], 45 ) ); ?></p></article></li><?php endforeach; ?></ol></section><?php
        return ob_get_clean();
    }

    public function shortcode_dashboard() {
        if ( ! current_user_can( 'edit_posts' ) ) return '';
        nocache_headers();
        wp_enqueue_style( 'sc-library-collaborative-review-publishing' );
        $report = self::dashboard_report( true );
        ob_start();
        ?><section class="sc-review-private-dashboard"><h2><?php esc_html_e( 'Collaborative review dashboard', 'sustainable-catalyst-library' ); ?></h2><div class="sc-review-metrics"><article><strong><?php echo esc_html( $report['active_review_count'] ); ?></strong><span><?php esc_html_e( 'Active reviews', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $report['open_note_count'] ); ?></strong><span><?php esc_html_e( 'Open notes', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $report['ready_package_count'] ); ?></strong><span><?php esc_html_e( 'Ready packages', 'sustainable-catalyst-library' ); ?></span></article></div></section><?php
        return ob_get_clean();
    }

    public function filter_project_context( $context, $project_id = 0, $args = array() ) {
        $review_ids = get_posts(
            array(
                'post_type' => self::REVIEW_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 50,
                'fields' => 'ids',
                'meta_key' => self::META_REVIEW_PROJECT_IDS,
                'meta_value' => 'i:' . absint( $project_id ) . ';',
                'meta_compare' => 'LIKE',
            )
        );
        $package_ids = get_posts(
            array(
                'post_type' => self::PACKAGE_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 50,
                'fields' => 'ids',
                'meta_key' => self::META_PACKAGE_PROJECT_IDS,
                'meta_value' => 'i:' . absint( $project_id ) . ';',
                'meta_compare' => 'LIKE',
            )
        );
        if ( ! $review_ids && ! $package_ids ) return $context;
        $addition = array(
            'schema' => self::HANDOFF_SCHEMA,
            'reviews' => array_map( static function ( $id ) { return self::review_data( $id, true ); }, $review_ids ),
            'publication_packages' => array_map( static function ( $id ) { return self::package_data( $id, true ); }, $package_ids ),
        );
        return is_array( $context ) ? array_merge( $context, array( 'collaborative_review_publishing' => $addition ) ) : array( 'collaborative_review_publishing' => $addition );
    }

    public function filter_handoff_sections( $sections, $project_id = 0, $args = array() ) {
        $context = $this->filter_project_context( array(), $project_id, $args );
        if ( empty( $context['collaborative_review_publishing'] ) ) return $sections;
        $sections = is_array( $sections ) ? $sections : array();
        $sections['collaborative_review_publishing'] = $context['collaborative_review_publishing'];
        return $sections;
    }

    public function cleanup_deleted_record( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( self::REVIEW_POST_TYPE === $post_type ) {
            foreach ( self::review_notes( $post_id ) as $note ) wp_delete_post( $note['note_id'], true );
            foreach ( get_posts( array( 'post_type' => self::PACKAGE_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 500, 'fields' => 'ids' ) ) as $package_id ) {
                $ids = array_values( array_filter( self::id_list( get_post_meta( $package_id, self::META_PACKAGE_REVIEW_IDS, true ) ), static function ( $id ) use ( $post_id ) { return $id !== absint( $post_id ); } ) );
                update_post_meta( $package_id, self::META_PACKAGE_REVIEW_IDS, $ids );
            }
        } elseif ( SC_Library_Foundation_Pages::POST_TYPE === $post_type ) {
            foreach ( get_posts( array( 'post_type' => self::REVIEW_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 500, 'fields' => 'ids' ) ) as $review_id ) {
                $ids = array_values( array_filter( self::id_list( get_post_meta( $review_id, self::META_REVIEW_DOCUMENT_IDS, true ) ), static function ( $id ) use ( $post_id ) { return $id !== absint( $post_id ); } ) );
                update_post_meta( $review_id, self::META_REVIEW_DOCUMENT_IDS, $ids );
            }
            foreach ( get_posts( array( 'post_type' => self::PACKAGE_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 500, 'fields' => 'ids' ) ) as $package_id ) {
                $ids = array_values( array_filter( self::id_list( get_post_meta( $package_id, self::META_PACKAGE_DOCUMENT_IDS, true ) ), static function ( $id ) use ( $post_id ) { return $id !== absint( $post_id ); } ) );
                update_post_meta( $package_id, self::META_PACKAGE_DOCUMENT_IDS, $ids );
            }
        }
        delete_transient( self::TRANSIENT_DASHBOARD );
    }

    private static function package_is_public( $package_id ) {
        if ( self::PACKAGE_POST_TYPE !== get_post_type( $package_id ) || '1' !== get_post_meta( $package_id, self::META_PACKAGE_PUBLIC, true ) || 'published' !== get_post_meta( $package_id, self::META_PACKAGE_STATUS, true ) ) return false;
        $embargo = (string) get_post_meta( $package_id, self::META_PACKAGE_EMBARGO_UNTIL, true );
        return ! $embargo || $embargo <= current_time( 'Y-m-d' );
    }

    private static function document_hash( $document_id ) {
        $title = (string) get_the_title( $document_id );
        $content = (string) get_post_field( 'post_content', $document_id );
        $raw = class_exists( 'SC_Library_PDF_To_Document' ) ? (string) get_post_meta( $document_id, SC_Library_PDF_To_Document::META_RAW_TEXT, true ) : '';
        return hash( 'sha256', $title . "\n" . ( trim( $raw ) ?: wp_strip_all_tags( $content ) ) );
    }

    private static function ensure_uuid( $post_id, $meta_key ) {
        $uuid = (string) get_post_meta( $post_id, $meta_key, true );
        if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
            $uuid = wp_generate_uuid4();
            update_post_meta( $post_id, $meta_key, $uuid );
        }
        return $uuid;
    }

    private static function append_history( $post_id, $meta_key, $entry ) {
        $history = get_post_meta( $post_id, $meta_key, true );
        $history = is_array( $history ) ? $history : array();
        $history[] = array_merge( array( 'event_id' => wp_generate_uuid4(), 'created_at' => current_time( 'mysql' ), 'created_by' => get_current_user_id() ), is_array( $entry ) ? $entry : array() );
        update_post_meta( $post_id, $meta_key, array_slice( $history, -self::MAX_HISTORY ) );
    }

    private static function migration_state() {
        return wp_parse_args( is_array( get_option( self::OPTION_MIGRATION, array() ) ) ? get_option( self::OPTION_MIGRATION, array() ) : array(), self::default_migration_state() );
    }

    private static function default_migration_state() {
        return array( 'version' => self::VERSION, 'status' => 'pending', 'cursor' => 0, 'processed' => 0, 'total' => 0, 'failures' => array(), 'last_error' => '', 'started_at' => '', 'updated_at' => '', 'completed_at' => '' );
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function sanitize_date( $value ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) return '';
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
    }

    private static function sanitize_datetime( $value ) {
        $value = str_replace( 'T', ' ', sanitize_text_field( $value ) );
        if ( '' === $value ) return '';
        $date = DateTime::createFromFormat( 'Y-m-d H:i', $value );
        return $date ? $date->format( 'Y-m-d H:i:s' ) : '';
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) $raw = preg_split( '/[\s,]+/', $raw );
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function resolve_id( $value, $post_type ) {
        if ( is_numeric( $value ) && $post_type === get_post_type( absint( $value ) ) ) return absint( $value );
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, $post_type );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) return;
        WP_CLI::add_command( 'sc-library reviews evaluate', static function ( $args ) {
            $result = self::evaluate_review( absint( $args[0] ?? 0 ), true );
            $result ? WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) : WP_CLI::error( 'Review not found.' );
        } );
        WP_CLI::add_command( 'sc-library reviews note', static function ( $args, $assoc ) {
            $result = self::create_note( absint( $args[0] ?? 0 ), array( 'document_id' => $assoc['document'] ?? 0, 'type' => $assoc['type'] ?? 'comment', 'severity' => $assoc['severity'] ?? 'info', 'section' => $assoc['section'] ?? '', 'body' => $assoc['body'] ?? '' ) );
            is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( 'Created note ' . absint( $result['note_id'] ?? 0 ) . '.' );
        } );
        WP_CLI::add_command( 'sc-library reviews decision', static function ( $args, $assoc ) {
            $result = self::record_decision( absint( $args[0] ?? 0 ), array( 'assignment_id' => $assoc['assignment'] ?? '', 'decision' => $assoc['decision'] ?? 'pending', 'note' => $assoc['note'] ?? '' ) );
            is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result ) );
        } );
        WP_CLI::add_command( 'sc-library publishing evaluate', static function ( $args ) {
            $result = self::evaluate_package( absint( $args[0] ?? 0 ), true );
            $result ? WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) : WP_CLI::error( 'Package not found.' );
        } );
        WP_CLI::add_command( 'sc-library publishing transition', static function ( $args, $assoc ) {
            $result = self::transition_package( absint( $args[0] ?? 0 ), $assoc['status'] ?? '', $assoc['note'] ?? '' );
            is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( 'Publication package updated.' );
        } );
        WP_CLI::add_command( 'sc-library reviews migrate', static function ( $args, $assoc ) {
            $result = self::run_migration_batch( absint( $assoc['limit'] ?? self::MIGRATION_BATCH ) );
            is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( wp_json_encode( $result ) );
        } );
        WP_CLI::add_command( 'sc-library reviews dashboard', static function () {
            WP_CLI::log( wp_json_encode( self::dashboard_report( true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
        } );
    }
}
