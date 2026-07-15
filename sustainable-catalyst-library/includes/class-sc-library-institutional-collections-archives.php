<?php
/**
 * Institutional Collections and Archive Management.
 *
 * Adds archival collections, hierarchical components, accession records,
 * custody histories, rights and restrictions, retention/disposition controls,
 * preservation monitoring, finding aids, public discovery, REST, and WP-CLI.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Institutional_Collections_Archives {
    public const VERSION = '3.6.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const COLLECTION_POST_TYPE = 'sc_inst_collection';
    public const COMPONENT_POST_TYPE = 'sc_archive_component';
    public const ACCESSION_POST_TYPE = 'sc_archive_accession';
    public const DISPOSITION_POST_TYPE = 'sc_archive_disposition';

    public const COLLECTION_SCHEMA = 'sc-library-institutional-collection/1.0';
    public const COMPONENT_SCHEMA = 'sc-library-archive-component/1.0';
    public const ACCESSION_SCHEMA = 'sc-library-archive-accession/1.0';
    public const FINDING_AID_SCHEMA = 'sc-library-finding-aid/1.0';
    public const PRESERVATION_SCHEMA = 'sc-library-preservation-audit/1.0';
    public const RETENTION_SCHEMA = 'sc-library-retention-disposition/1.0';
    public const DASHBOARD_SCHEMA = 'sc-library-archive-dashboard/1.0';

    public const META_IDENTIFIER = '_sc_archive_identifier';
    public const META_INSTITUTION = '_sc_archive_institution';
    public const META_DEPARTMENT = '_sc_archive_department';
    public const META_CREATOR = '_sc_archive_creator';
    public const META_DATE_START = '_sc_archive_date_start';
    public const META_DATE_END = '_sc_archive_date_end';
    public const META_EXTENT = '_sc_archive_extent';
    public const META_LANGUAGES = '_sc_archive_languages';
    public const META_SCOPE = '_sc_archive_scope_content';
    public const META_ARRANGEMENT = '_sc_archive_arrangement';
    public const META_PROVENANCE = '_sc_archive_provenance';
    public const META_ACQUISITION = '_sc_archive_acquisition';
    public const META_RIGHTS = '_sc_archive_rights';
    public const META_ACCESS_NOTE = '_sc_archive_access_note';
    public const META_USE_NOTE = '_sc_archive_use_note';
    public const META_CITATION_NOTE = '_sc_archive_citation_note';
    public const META_STATUS = '_sc_archive_status';
    public const META_ACCESS_LEVEL = '_sc_archive_access_level';
    public const META_EMBARGO_UNTIL = '_sc_archive_embargo_until';
    public const META_FINDING_AID_PUBLIC = '_sc_archive_finding_aid_public';
    public const META_PRESERVATION_PROFILE = '_sc_archive_preservation_profile';
    public const META_RETENTION_CLASS = '_sc_archive_retention_class';
    public const META_RETENTION_YEARS = '_sc_archive_retention_years';
    public const META_RETENTION_TRIGGER = '_sc_archive_retention_trigger';
    public const META_RETENTION_REVIEW = '_sc_archive_retention_review';
    public const META_LEGAL_HOLD = '_sc_archive_legal_hold';
    public const META_LAST_AUDIT = '_sc_archive_last_audit';
    public const META_LAST_AUDIT_REPORT = '_sc_archive_last_audit_report';
    public const META_COLLECTION_UUID = '_sc_archive_collection_uuid';

    public const META_COMPONENT_COLLECTION = '_sc_component_collection_id';
    public const META_COMPONENT_PARENT = '_sc_component_parent_id';
    public const META_COMPONENT_LEVEL = '_sc_component_level';
    public const META_COMPONENT_ORDER = '_sc_component_order';
    public const META_COMPONENT_DOCUMENT_IDS = '_sc_component_document_ids';
    public const META_COMPONENT_SOURCE_IDS = '_sc_component_source_ids';
    public const META_COMPONENT_PROJECT_IDS = '_sc_component_project_ids';
    public const META_COMPONENT_DIGITAL_OBJECTS = '_sc_component_digital_objects';
    public const META_COMPONENT_CHECKSUMS = '_sc_component_checksums';
    public const META_COMPONENT_PRESERVATION = '_sc_component_preservation_status';

    public const META_ACCESSION_COLLECTION = '_sc_accession_collection_id';
    public const META_ACCESSION_NUMBER = '_sc_accession_number';
    public const META_ACCESSION_DATE = '_sc_accession_date';
    public const META_ACCESSION_METHOD = '_sc_accession_method';
    public const META_ACCESSION_SOURCE = '_sc_accession_source';
    public const META_ACCESSION_DONOR = '_sc_accession_donor';
    public const META_ACCESSION_AGREEMENT = '_sc_accession_agreement';
    public const META_ACCESSION_EXTENT = '_sc_accession_extent';
    public const META_ACCESSION_RESTRICTIONS = '_sc_accession_restrictions';
    public const META_ACCESSION_STATUS = '_sc_accession_processing_status';
    public const META_ACCESSION_CUSTODY = '_sc_accession_custody_history';

    public const META_DISPOSITION_COLLECTION = '_sc_disposition_collection_id';
    public const META_DISPOSITION_COMPONENT = '_sc_disposition_component_id';
    public const META_DISPOSITION_ACTION = '_sc_disposition_action';
    public const META_DISPOSITION_REASON = '_sc_disposition_reason';
    public const META_DISPOSITION_STATUS = '_sc_disposition_status';
    public const META_DISPOSITION_DUE = '_sc_disposition_due_date';
    public const META_DISPOSITION_APPROVER = '_sc_disposition_approver_id';
    public const META_DISPOSITION_APPROVED_AT = '_sc_disposition_approved_at';
    public const META_DISPOSITION_COMPLETED_AT = '_sc_disposition_completed_at';
    public const META_DISPOSITION_HISTORY = '_sc_disposition_history';

    public const OPTION_MIGRATION = 'sc_library_v360_archive_migration';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v360_archive_migration_lock';
    public const TRANSIENT_DASHBOARD = 'sc_library_v360_archive_dashboard';
    public const CRON_MIGRATION = 'sc_library_v360_archive_migration_tick';
    public const CRON_PRESERVATION = 'sc_library_v360_preservation_audit_tick';

    public const MIGRATION_BATCH = 20;
    public const LOCK_SECONDS = 180;
    public const MAX_HISTORY = 100;
    public const MAX_LINKS = 500;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_record_types' ), 320 );
        add_action( 'init', array( $this, 'schedule_operations' ), 995 );
        add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
        add_action( self::CRON_PRESERVATION, array( $this, 'run_scheduled_preservation_audit' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 320 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 220 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 220 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'save_post_' . self::COLLECTION_POST_TYPE, array( $this, 'save_collection' ), 220, 3 );
        add_action( 'save_post_' . self::COMPONENT_POST_TYPE, array( $this, 'save_component' ), 220, 3 );
        add_action( 'save_post_' . self::ACCESSION_POST_TYPE, array( $this, 'save_accession' ), 220, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_record' ) );

        add_action( 'wp_ajax_sc_library_v360_run_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_sc_library_v360_run_audit', array( $this, 'ajax_run_audit' ) );
        add_action( 'wp_ajax_sc_library_v360_create_disposition', array( $this, 'ajax_create_disposition' ) );
        add_action( 'wp_ajax_sc_library_v360_transition_disposition', array( $this, 'ajax_transition_disposition' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 90, 3 );

        add_shortcode( 'sc_institutional_collection', array( $this, 'shortcode_collection' ) );
        add_shortcode( 'sc_archive_finding_aid', array( $this, 'shortcode_finding_aid' ) );
        add_shortcode( 'sc_archive_collection_browser', array( $this, 'shortcode_collection_browser' ) );
        add_shortcode( 'sc_archive_preservation_status', array( $this, 'shortcode_preservation_status' ) );

        self::register_cli_commands();
    }

    public static function collection_statuses() {
        return array(
            'draft'         => __( 'Draft', 'sustainable-catalyst-library' ),
            'processing'    => __( 'Processing', 'sustainable-catalyst-library' ),
            'active'        => __( 'Active', 'sustainable-catalyst-library' ),
            'published'     => __( 'Published', 'sustainable-catalyst-library' ),
            'closed'        => __( 'Closed', 'sustainable-catalyst-library' ),
            'deaccessioned' => __( 'Deaccessioned', 'sustainable-catalyst-library' ),
            'archived'      => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public static function component_levels() {
        return array(
            'collection' => __( 'Collection', 'sustainable-catalyst-library' ),
            'fonds'      => __( 'Fonds', 'sustainable-catalyst-library' ),
            'record-group'=> __( 'Record group', 'sustainable-catalyst-library' ),
            'series'     => __( 'Series', 'sustainable-catalyst-library' ),
            'subseries'  => __( 'Subseries', 'sustainable-catalyst-library' ),
            'box'        => __( 'Box', 'sustainable-catalyst-library' ),
            'folder'     => __( 'Folder', 'sustainable-catalyst-library' ),
            'item'       => __( 'Item', 'sustainable-catalyst-library' ),
            'digital-object' => __( 'Digital object', 'sustainable-catalyst-library' ),
        );
    }

    public static function access_levels() {
        return array(
            'public'       => __( 'Public', 'sustainable-catalyst-library' ),
            'reading-room' => __( 'Reading room only', 'sustainable-catalyst-library' ),
            'restricted'   => __( 'Restricted', 'sustainable-catalyst-library' ),
            'embargoed'    => __( 'Embargoed', 'sustainable-catalyst-library' ),
            'confidential' => __( 'Confidential', 'sustainable-catalyst-library' ),
        );
    }

    public static function accession_methods() {
        return array(
            'transfer'     => __( 'Institutional transfer', 'sustainable-catalyst-library' ),
            'donation'     => __( 'Donation', 'sustainable-catalyst-library' ),
            'deposit'      => __( 'Deposit', 'sustainable-catalyst-library' ),
            'purchase'     => __( 'Purchase', 'sustainable-catalyst-library' ),
            'born-digital' => __( 'Born-digital intake', 'sustainable-catalyst-library' ),
            'legacy'       => __( 'Legacy or undocumented accession', 'sustainable-catalyst-library' ),
            'other'        => __( 'Other', 'sustainable-catalyst-library' ),
        );
    }

    public static function processing_statuses() {
        return array(
            'received'     => __( 'Received', 'sustainable-catalyst-library' ),
            'quarantined'  => __( 'Quarantined', 'sustainable-catalyst-library' ),
            'inventory'    => __( 'Inventory', 'sustainable-catalyst-library' ),
            'processing'   => __( 'Processing', 'sustainable-catalyst-library' ),
            'cataloged'    => __( 'Cataloged', 'sustainable-catalyst-library' ),
            'closed'       => __( 'Closed', 'sustainable-catalyst-library' ),
        );
    }

    public static function retention_classes() {
        return array(
            'permanent' => __( 'Permanent retention', 'sustainable-catalyst-library' ),
            'review'    => __( 'Review before disposition', 'sustainable-catalyst-library' ),
            'years'     => __( 'Retain for specified years', 'sustainable-catalyst-library' ),
            'transfer'  => __( 'Transfer to another repository', 'sustainable-catalyst-library' ),
            'destroy'   => __( 'Destroy after authorization', 'sustainable-catalyst-library' ),
        );
    }

    public static function preservation_statuses() {
        return array(
            'not-assessed' => __( 'Not assessed', 'sustainable-catalyst-library' ),
            'stable'       => __( 'Stable', 'sustainable-catalyst-library' ),
            'monitor'      => __( 'Monitor', 'sustainable-catalyst-library' ),
            'at-risk'      => __( 'At risk', 'sustainable-catalyst-library' ),
            'critical'     => __( 'Critical', 'sustainable-catalyst-library' ),
            'missing'      => __( 'Missing digital object', 'sustainable-catalyst-library' ),
        );
    }

    public static function disposition_actions() {
        return array(
            'retain'      => __( 'Retain', 'sustainable-catalyst-library' ),
            'review'      => __( 'Review', 'sustainable-catalyst-library' ),
            'transfer'    => __( 'Transfer', 'sustainable-catalyst-library' ),
            'deaccession' => __( 'Deaccession', 'sustainable-catalyst-library' ),
            'destroy'     => __( 'Destroy', 'sustainable-catalyst-library' ),
        );
    }

    public function register_record_types() {
        register_post_type(
            self::COLLECTION_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Institutional Collections', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Institutional Collection', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Institutional Collection', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Institutional Collection', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => true,
                'has_archive'         => 'institutional-collections',
                'rewrite'             => array( 'slug' => 'institutional-collection' ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'exclude_from_search' => false,
            )
        );

        register_post_type(
            self::COMPONENT_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Archive Components', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Archive Component', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Archive Component', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Archive Component', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => true,
                'hierarchical'        => true,
                'has_archive'         => false,
                'rewrite'             => array( 'slug' => 'archive-component' ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'page-attributes', 'author', 'revisions' ),
                'capability_type'     => 'page',
                'map_meta_cap'        => true,
                'exclude_from_search' => false,
            )
        );

        foreach (
            array(
                self::ACCESSION_POST_TYPE   => __( 'Archive Accessions', 'sustainable-catalyst-library' ),
                self::DISPOSITION_POST_TYPE => __( 'Archive Dispositions', 'sustainable-catalyst-library' ),
            ) as $post_type => $label
        ) {
            register_post_type(
                $post_type,
                array(
                    'labels' => array(
                        'name'          => $label,
                        'singular_name' => rtrim( $label, 's' ),
                    ),
                    'public'              => false,
                    'show_ui'             => false,
                    'show_in_rest'        => false,
                    'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
                    'capability_type'     => 'post',
                    'map_meta_cap'        => true,
                    'exclude_from_search' => true,
                )
            );
        }
    }

    public function schedule_operations() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_MIGRATION ) ) {
            wp_schedule_event( time() + 900, 'hourly', self::CRON_MIGRATION );
        }
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_PRESERVATION ) ) {
            wp_schedule_event( time() + 3600, 'daily', self::CRON_PRESERVATION );
        }
        $state = get_option( self::OPTION_MIGRATION, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
        }
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Institutional Collections and Archive Management', 'sustainable-catalyst-library' ),
            __( 'Collections & Archives', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-institutional-archives',
            array( $this, 'render_workspace' )
        );
        add_submenu_page(
            'sc-library',
            __( 'Institutional Collections', 'sustainable-catalyst-library' ),
            __( 'Institutional Collections', 'sustainable-catalyst-library' ),
            'edit_posts',
            'edit.php?post_type=' . self::COLLECTION_POST_TYPE
        );
        add_submenu_page(
            'sc-library',
            __( 'Archive Components', 'sustainable-catalyst-library' ),
            __( 'Archive Components', 'sustainable-catalyst-library' ),
            'edit_posts',
            'edit.php?post_type=' . self::COMPONENT_POST_TYPE
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-institutional-collection-description',
            __( 'Collection Description and Governance', 'sustainable-catalyst-library' ),
            array( $this, 'render_collection_meta_box' ),
            self::COLLECTION_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-institutional-collection-retention',
            __( 'Retention, Access, and Preservation', 'sustainable-catalyst-library' ),
            array( $this, 'render_collection_retention_box' ),
            self::COLLECTION_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-archive-component-description',
            __( 'Archive Component Description', 'sustainable-catalyst-library' ),
            array( $this, 'render_component_meta_box' ),
            self::COMPONENT_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-archive-accession-description',
            __( 'Accession and Custody Record', 'sustainable-catalyst-library' ),
            array( $this, 'render_accession_meta_box' ),
            self::ACCESSION_POST_TYPE,
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
                self::COLLECTION_POST_TYPE,
                self::COMPONENT_POST_TYPE,
                self::ACCESSION_POST_TYPE,
            ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-institutional-archives' );
        if ( ! $supported && ! $workspace ) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style( 'sc-library-institutional-archives' );
        wp_enqueue_script( 'sc-library-institutional-archives' );
        wp_localize_script(
            'sc-library-institutional-archives',
            'SCLibraryInstitutionalArchives',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_institutional_archives_v360' ),
                'strings' => array(
                    'working'  => __( 'Working…', 'sustainable-catalyst-library' ),
                    'complete' => __( 'Operation complete.', 'sustainable-catalyst-library' ),
                    'error'    => __( 'The archive operation failed.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        $this->register_assets();
    }

    private function register_assets() {
        wp_register_style(
            'sc-library-institutional-archives',
            SC_LIBRARY_URL . 'assets/css/sc-library-institutional-archives.css',
            array(),
            self::VERSION
        );
        wp_register_script(
            'sc-library-institutional-archives',
            SC_LIBRARY_URL . 'assets/js/sc-library-institutional-archives.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function save_collection( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::COLLECTION_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_collection_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_collection_nonce'] ) ),
                'sc_library_save_collection_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        $this->ensure_collection_identity( $post_id );

        $text_fields = array(
            self::META_IDENTIFIER     => 'sc_archive_identifier',
            self::META_INSTITUTION    => 'sc_archive_institution',
            self::META_DEPARTMENT     => 'sc_archive_department',
            self::META_CREATOR        => 'sc_archive_creator',
            self::META_DATE_START     => 'sc_archive_date_start',
            self::META_DATE_END       => 'sc_archive_date_end',
            self::META_EXTENT         => 'sc_archive_extent',
            self::META_LANGUAGES      => 'sc_archive_languages',
            self::META_CITATION_NOTE  => 'sc_archive_citation_note',
            self::META_EMBARGO_UNTIL  => 'sc_archive_embargo_until',
            self::META_RETENTION_TRIGGER => 'sc_archive_retention_trigger',
            self::META_RETENTION_REVIEW  => 'sc_archive_retention_review',
            self::META_PRESERVATION_PROFILE => 'sc_archive_preservation_profile',
        );
        foreach ( $text_fields as $meta_key => $field ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) );
            $value ? update_post_meta( $post_id, $meta_key, $value ) : delete_post_meta( $post_id, $meta_key );
        }

        $textarea_fields = array(
            self::META_SCOPE        => 'sc_archive_scope_content',
            self::META_ARRANGEMENT  => 'sc_archive_arrangement',
            self::META_PROVENANCE   => 'sc_archive_provenance',
            self::META_ACQUISITION  => 'sc_archive_acquisition',
            self::META_RIGHTS       => 'sc_archive_rights',
            self::META_ACCESS_NOTE  => 'sc_archive_access_note',
            self::META_USE_NOTE     => 'sc_archive_use_note',
        );
        foreach ( $textarea_fields as $meta_key => $field ) {
            $value = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) );
            $value ? update_post_meta( $post_id, $meta_key, $value ) : delete_post_meta( $post_id, $meta_key );
        }

        update_post_meta(
            $post_id,
            self::META_STATUS,
            self::allowed_value( wp_unslash( $_POST['sc_archive_status'] ?? 'draft' ), self::collection_statuses(), 'draft' )
        );
        update_post_meta(
            $post_id,
            self::META_ACCESS_LEVEL,
            self::allowed_value( wp_unslash( $_POST['sc_archive_access_level'] ?? 'public' ), self::access_levels(), 'public' )
        );
        update_post_meta(
            $post_id,
            self::META_RETENTION_CLASS,
            self::allowed_value( wp_unslash( $_POST['sc_archive_retention_class'] ?? 'permanent' ), self::retention_classes(), 'permanent' )
        );
        update_post_meta( $post_id, self::META_RETENTION_YEARS, max( 0, absint( wp_unslash( $_POST['sc_archive_retention_years'] ?? 0 ) ) ) );
        update_post_meta( $post_id, self::META_FINDING_AID_PUBLIC, isset( $_POST['sc_archive_finding_aid_public'] ) ? '1' : '0' );
        update_post_meta( $post_id, self::META_LEGAL_HOLD, isset( $_POST['sc_archive_legal_hold'] ) ? '1' : '0' );

        delete_transient( self::TRANSIENT_DASHBOARD );
        self::$saving = false;
    }

    public function save_component( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::COMPONENT_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_component_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_component_nonce'] ) ),
                'sc_library_save_component_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        $collection_id = absint( wp_unslash( $_POST['sc_component_collection_id'] ?? 0 ) );
        if ( self::COLLECTION_POST_TYPE === get_post_type( $collection_id ) ) {
            update_post_meta( $post_id, self::META_COMPONENT_COLLECTION, $collection_id );
        } else {
            delete_post_meta( $post_id, self::META_COMPONENT_COLLECTION );
        }

        $parent_id = absint( wp_unslash( $_POST['sc_component_parent_id'] ?? 0 ) );
        if ( $parent_id !== $post_id && self::COMPONENT_POST_TYPE === get_post_type( $parent_id ) ) {
            update_post_meta( $post_id, self::META_COMPONENT_PARENT, $parent_id );
            wp_update_post( array( 'ID' => $post_id, 'post_parent' => $parent_id ) );
        } else {
            delete_post_meta( $post_id, self::META_COMPONENT_PARENT );
        }

        update_post_meta(
            $post_id,
            self::META_COMPONENT_LEVEL,
            self::allowed_value( wp_unslash( $_POST['sc_component_level'] ?? 'series' ), self::component_levels(), 'series' )
        );
        update_post_meta(
            $post_id,
            self::META_ACCESS_LEVEL,
            self::allowed_value( wp_unslash( $_POST['sc_component_access_level'] ?? 'public' ), self::access_levels(), 'public' )
        );
        update_post_meta(
            $post_id,
            self::META_COMPONENT_PRESERVATION,
            self::allowed_value( wp_unslash( $_POST['sc_component_preservation_status'] ?? 'not-assessed' ), self::preservation_statuses(), 'not-assessed' )
        );
        update_post_meta( $post_id, self::META_COMPONENT_ORDER, absint( wp_unslash( $_POST['sc_component_order'] ?? 0 ) ) );
        update_post_meta( $post_id, self::META_IDENTIFIER, sanitize_text_field( wp_unslash( $_POST['sc_component_identifier'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_DATE_START, sanitize_text_field( wp_unslash( $_POST['sc_component_date_start'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_DATE_END, sanitize_text_field( wp_unslash( $_POST['sc_component_date_end'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_EXTENT, sanitize_text_field( wp_unslash( $_POST['sc_component_extent'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_EMBARGO_UNTIL, self::sanitize_date( wp_unslash( $_POST['sc_component_embargo_until'] ?? '' ) ) );

        foreach (
            array(
                self::META_COMPONENT_DOCUMENT_IDS => 'sc_component_document_ids',
                self::META_COMPONENT_SOURCE_IDS   => 'sc_component_source_ids',
                self::META_COMPONENT_PROJECT_IDS  => 'sc_component_project_ids',
            ) as $meta_key => $field
        ) {
            $ids = self::id_list( wp_unslash( $_POST[ $field ] ?? array() ) );
            $ids ? update_post_meta( $post_id, $meta_key, array_slice( $ids, 0, self::MAX_LINKS ) ) : delete_post_meta( $post_id, $meta_key );
        }

        $digital_objects = self::sanitize_digital_objects( wp_unslash( $_POST['sc_component_digital_objects'] ?? array() ) );
        $digital_objects
            ? update_post_meta( $post_id, self::META_COMPONENT_DIGITAL_OBJECTS, $digital_objects )
            : delete_post_meta( $post_id, self::META_COMPONENT_DIGITAL_OBJECTS );

        delete_transient( self::TRANSIENT_DASHBOARD );
        self::$saving = false;
    }

    public function save_accession( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::ACCESSION_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_accession_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_accession_nonce'] ) ),
                'sc_library_save_accession_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        $collection_id = absint( wp_unslash( $_POST['sc_accession_collection_id'] ?? 0 ) );
        update_post_meta( $post_id, self::META_ACCESSION_COLLECTION, self::COLLECTION_POST_TYPE === get_post_type( $collection_id ) ? $collection_id : 0 );
        update_post_meta( $post_id, self::META_ACCESSION_NUMBER, sanitize_text_field( wp_unslash( $_POST['sc_accession_number'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_ACCESSION_DATE, self::sanitize_date( wp_unslash( $_POST['sc_accession_date'] ?? '' ) ) );
        update_post_meta(
            $post_id,
            self::META_ACCESSION_METHOD,
            self::allowed_value( wp_unslash( $_POST['sc_accession_method'] ?? 'transfer' ), self::accession_methods(), 'transfer' )
        );
        update_post_meta( $post_id, self::META_ACCESSION_SOURCE, sanitize_text_field( wp_unslash( $_POST['sc_accession_source'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_ACCESSION_DONOR, sanitize_text_field( wp_unslash( $_POST['sc_accession_donor'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_ACCESSION_AGREEMENT, sanitize_textarea_field( wp_unslash( $_POST['sc_accession_agreement'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_ACCESSION_EXTENT, sanitize_text_field( wp_unslash( $_POST['sc_accession_extent'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_ACCESSION_RESTRICTIONS, sanitize_textarea_field( wp_unslash( $_POST['sc_accession_restrictions'] ?? '' ) ) );
        update_post_meta(
            $post_id,
            self::META_ACCESSION_STATUS,
            self::allowed_value( wp_unslash( $_POST['sc_accession_processing_status'] ?? 'received' ), self::processing_statuses(), 'received' )
        );

        $custody = self::sanitize_custody_events( wp_unslash( $_POST['sc_accession_custody_history'] ?? array() ) );
        $custody ? update_post_meta( $post_id, self::META_ACCESSION_CUSTODY, $custody ) : delete_post_meta( $post_id, self::META_ACCESSION_CUSTODY );

        delete_transient( self::TRANSIENT_DASHBOARD );
        self::$saving = false;
    }

    public function render_collection_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_collection_' . $post->ID, 'sc_library_collection_nonce' );
        $status = (string) get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'draft';
        $access = (string) get_post_meta( $post->ID, self::META_ACCESS_LEVEL, true ) ?: 'public';
        ?>
        <div class="sc-archive-editor" data-sc-archive-collection="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-archive-field-grid">
                <label><strong><?php esc_html_e( 'Collection identifier', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_identifier" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_IDENTIFIER, true ) ); ?>" placeholder="SC-ARC-001"></label>
                <label><strong><?php esc_html_e( 'Institution', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_institution" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Department or repository', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_department" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DEPARTMENT, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Creator or originating body', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_creator" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_CREATOR, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Date range start', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_date_start" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DATE_START, true ) ); ?>" placeholder="1998"></label>
                <label><strong><?php esc_html_e( 'Date range end', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_date_end" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DATE_END, true ) ); ?>" placeholder="2026"></label>
                <label><strong><?php esc_html_e( 'Extent', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_extent" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_EXTENT, true ) ); ?>" placeholder="14 boxes; 22 GB"></label>
                <label><strong><?php esc_html_e( 'Languages', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_languages" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_LANGUAGES, true ) ); ?>" placeholder="English; Urdu"></label>
                <label><strong><?php esc_html_e( 'Collection status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_archive_status"><?php foreach ( self::collection_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Access level', 'sustainable-catalyst-library' ); ?></strong><select name="sc_archive_access_level"><?php foreach ( self::access_levels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $access, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            </div>
            <label><strong><?php esc_html_e( 'Scope and content', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_scope_content" rows="6"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_SCOPE, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Arrangement', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_arrangement" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ARRANGEMENT, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Provenance and custodial history', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_provenance" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PROVENANCE, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Acquisition information', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_acquisition" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ACQUISITION, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Rights statement', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_rights" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_RIGHTS, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Access restrictions', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_access_note" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ACCESS_NOTE, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Conditions governing use', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_archive_use_note" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_USE_NOTE, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Preferred citation', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_citation_note" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_CITATION_NOTE, true ) ); ?>"></label>
        </div>
        <?php
    }

    public function render_collection_retention_box( $post ) {
        $retention = (string) get_post_meta( $post->ID, self::META_RETENTION_CLASS, true ) ?: 'permanent';
        ?>
        <div class="sc-archive-retention">
            <p><strong><?php esc_html_e( 'Stable collection UUID', 'sustainable-catalyst-library' ); ?></strong><br><code><?php echo esc_html( $this->ensure_collection_identity( $post->ID ) ); ?></code></p>
            <label><strong><?php esc_html_e( 'Embargo until', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_archive_embargo_until" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_EMBARGO_UNTIL, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Retention class', 'sustainable-catalyst-library' ); ?></strong><select name="sc_archive_retention_class"><?php foreach ( self::retention_classes() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $retention, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'Retention years', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="0" name="sc_archive_retention_years" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_RETENTION_YEARS, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Retention trigger', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_retention_trigger" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_RETENTION_TRIGGER, true ) ); ?>" placeholder="Project closure"></label>
            <label><strong><?php esc_html_e( 'Next retention review', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_archive_retention_review" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_RETENTION_REVIEW, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Preservation profile', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_archive_preservation_profile" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PRESERVATION_PROFILE, true ) ); ?>" placeholder="PDF/A + original binaries"></label>
            <label><input type="checkbox" name="sc_archive_legal_hold" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_LEGAL_HOLD, true ) ); ?>> <?php esc_html_e( 'Legal or administrative hold', 'sustainable-catalyst-library' ); ?></label>
            <label><input type="checkbox" name="sc_archive_finding_aid_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_FINDING_AID_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Publish the finding aid', 'sustainable-catalyst-library' ); ?></label>
            <button type="button" class="button" data-sc-archive-audit data-collection-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Run Preservation Audit', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-archive-status aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_component_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_component_' . $post->ID, 'sc_library_component_nonce' );
        $collections = get_posts( array( 'post_type' => self::COLLECTION_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
        $components = get_posts( array( 'post_type' => self::COMPONENT_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 500, 'post__not_in' => array( $post->ID ), 'orderby' => 'title', 'order' => 'ASC' ) );
        $collection_id = absint( get_post_meta( $post->ID, self::META_COMPONENT_COLLECTION, true ) );
        $parent_id = absint( get_post_meta( $post->ID, self::META_COMPONENT_PARENT, true ) );
        $level = (string) get_post_meta( $post->ID, self::META_COMPONENT_LEVEL, true ) ?: 'series';
        $access = (string) get_post_meta( $post->ID, self::META_ACCESS_LEVEL, true ) ?: 'public';
        $preservation = (string) get_post_meta( $post->ID, self::META_COMPONENT_PRESERVATION, true ) ?: 'not-assessed';
        $objects = get_post_meta( $post->ID, self::META_COMPONENT_DIGITAL_OBJECTS, true );
        $objects = is_array( $objects ) ? $objects : array();
        ?>
        <div class="sc-archive-editor">
            <div class="sc-archive-field-grid">
                <label><strong><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></strong><select name="sc_component_collection_id"><option value="0"><?php esc_html_e( 'Select collection', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $collections as $collection ) : ?><option value="<?php echo esc_attr( $collection->ID ); ?>" <?php selected( $collection_id, $collection->ID ); ?>><?php echo esc_html( $collection->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Parent component', 'sustainable-catalyst-library' ); ?></strong><select name="sc_component_parent_id"><option value="0"><?php esc_html_e( 'Top level', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $components as $component ) : ?><option value="<?php echo esc_attr( $component->ID ); ?>" <?php selected( $parent_id, $component->ID ); ?>><?php echo esc_html( $component->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Level of description', 'sustainable-catalyst-library' ); ?></strong><select name="sc_component_level"><?php foreach ( self::component_levels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $level, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Component identifier', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_identifier" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_IDENTIFIER, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Order', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="0" name="sc_component_order" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_COMPONENT_ORDER, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Access level', 'sustainable-catalyst-library' ); ?></strong><select name="sc_component_access_level"><?php foreach ( self::access_levels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $access, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Preservation status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_component_preservation_status"><?php foreach ( self::preservation_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $preservation, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Embargo until', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_component_embargo_until" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_EMBARGO_UNTIL, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Date start', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_date_start" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DATE_START, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Date end', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_date_end" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DATE_END, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Extent', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_extent" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_EXTENT, true ) ); ?>"></label>
            </div>
            <div class="sc-archive-link-grid">
                <label><strong><?php esc_html_e( 'Knowledge Library document IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_document_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_COMPONENT_DOCUMENT_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Research Source IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_source_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_COMPONENT_SOURCE_IDS, true ) ) ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Research Project IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_component_project_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_COMPONENT_PROJECT_IDS, true ) ) ) ); ?>"></label>
            </div>
            <section class="sc-archive-digital-objects" data-sc-digital-object-editor>
                <div class="sc-archive-section-heading"><h3><?php esc_html_e( 'Digital objects', 'sustainable-catalyst-library' ); ?></h3><button type="button" class="button" data-sc-add-digital-object><?php esc_html_e( 'Add Digital Object', 'sustainable-catalyst-library' ); ?></button></div>
                <div data-sc-digital-object-rows>
                    <?php foreach ( $objects as $index => $object ) : ?>
                        <?php echo self::render_digital_object_row( $index, $object ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
                <template data-sc-digital-object-template><?php echo self::render_digital_object_row( '__INDEX__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
            </section>
        </div>
        <?php
    }

    public function render_accession_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_accession_' . $post->ID, 'sc_library_accession_nonce' );
        $collections = get_posts( array( 'post_type' => self::COLLECTION_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
        $collection_id = absint( get_post_meta( $post->ID, self::META_ACCESSION_COLLECTION, true ) );
        $method = (string) get_post_meta( $post->ID, self::META_ACCESSION_METHOD, true ) ?: 'transfer';
        $status = (string) get_post_meta( $post->ID, self::META_ACCESSION_STATUS, true ) ?: 'received';
        $custody = get_post_meta( $post->ID, self::META_ACCESSION_CUSTODY, true );
        $custody = is_array( $custody ) ? $custody : array();
        ?>
        <div class="sc-archive-editor">
            <div class="sc-archive-field-grid">
                <label><strong><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></strong><select name="sc_accession_collection_id"><option value="0"><?php esc_html_e( 'Select collection', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $collections as $collection ) : ?><option value="<?php echo esc_attr( $collection->ID ); ?>" <?php selected( $collection_id, $collection->ID ); ?>><?php echo esc_html( $collection->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Accession number', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_accession_number" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ACCESSION_NUMBER, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Accession date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_accession_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ACCESSION_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Method', 'sustainable-catalyst-library' ); ?></strong><select name="sc_accession_method"><?php foreach ( self::accession_methods() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $method, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Source or transferring body', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_accession_source" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ACCESSION_SOURCE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Donor or depositor', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_accession_donor" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ACCESSION_DONOR, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Extent received', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_accession_extent" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ACCESSION_EXTENT, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Processing status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_accession_processing_status"><?php foreach ( self::processing_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            </div>
            <label><strong><?php esc_html_e( 'Agreement and rights transferred', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_accession_agreement" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ACCESSION_AGREEMENT, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Restrictions', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_accession_restrictions" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ACCESSION_RESTRICTIONS, true ) ); ?></textarea></label>
            <section data-sc-custody-editor>
                <div class="sc-archive-section-heading"><h3><?php esc_html_e( 'Custody events', 'sustainable-catalyst-library' ); ?></h3><button type="button" class="button" data-sc-add-custody-event><?php esc_html_e( 'Add Custody Event', 'sustainable-catalyst-library' ); ?></button></div>
                <div data-sc-custody-rows><?php foreach ( $custody as $index => $event ) : ?><?php echo self::render_custody_row( $index, $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?></div>
                <template data-sc-custody-template><?php echo self::render_custody_row( '__INDEX__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
            </section>
        </div>
        <?php
    }

    private static function render_digital_object_row( $index, $object ) {
        $object = wp_parse_args( is_array( $object ) ? $object : array(), array( 'label' => '', 'uri' => '', 'media_type' => '', 'bytes' => 0, 'checksum' => '', 'checksum_algorithm' => 'sha256', 'preservation_status' => 'not-assessed' ) );
        ob_start();
        ?>
        <div class="sc-digital-object-row" data-sc-digital-object-row>
            <label><?php esc_html_e( 'Label', 'sustainable-catalyst-library' ); ?><input data-name="label" type="text" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $object['label'] ); ?>"></label>
            <label><?php esc_html_e( 'URI or path', 'sustainable-catalyst-library' ); ?><input data-name="uri" type="text" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][uri]" value="<?php echo esc_attr( $object['uri'] ); ?>"></label>
            <label><?php esc_html_e( 'Media type', 'sustainable-catalyst-library' ); ?><input data-name="media_type" type="text" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][media_type]" value="<?php echo esc_attr( $object['media_type'] ); ?>"></label>
            <label><?php esc_html_e( 'Bytes', 'sustainable-catalyst-library' ); ?><input data-name="bytes" type="number" min="0" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][bytes]" value="<?php echo esc_attr( $object['bytes'] ); ?>"></label>
            <label><?php esc_html_e( 'Checksum', 'sustainable-catalyst-library' ); ?><input data-name="checksum" type="text" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][checksum]" value="<?php echo esc_attr( $object['checksum'] ); ?>"></label>
            <label><?php esc_html_e( 'Algorithm', 'sustainable-catalyst-library' ); ?><select data-name="checksum_algorithm" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][checksum_algorithm]"><option value="sha256" <?php selected( $object['checksum_algorithm'], 'sha256' ); ?>>SHA-256</option><option value="sha512" <?php selected( $object['checksum_algorithm'], 'sha512' ); ?>>SHA-512</option><option value="md5" <?php selected( $object['checksum_algorithm'], 'md5' ); ?>>MD5 legacy</option></select></label>
            <label><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?><select data-name="preservation_status" name="sc_component_digital_objects[<?php echo esc_attr( $index ); ?>][preservation_status]"><?php foreach ( self::preservation_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $object['preservation_status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <button type="button" class="button-link-delete" data-sc-remove-row><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_custody_row( $index, $event ) {
        $event = wp_parse_args( is_array( $event ) ? $event : array(), array( 'date' => '', 'from' => '', 'to' => '', 'method' => '', 'note' => '' ) );
        ob_start();
        ?>
        <div class="sc-custody-row" data-sc-custody-row>
            <label><?php esc_html_e( 'Date', 'sustainable-catalyst-library' ); ?><input data-name="date" type="date" name="sc_accession_custody_history[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $event['date'] ); ?>"></label>
            <label><?php esc_html_e( 'From', 'sustainable-catalyst-library' ); ?><input data-name="from" type="text" name="sc_accession_custody_history[<?php echo esc_attr( $index ); ?>][from]" value="<?php echo esc_attr( $event['from'] ); ?>"></label>
            <label><?php esc_html_e( 'To', 'sustainable-catalyst-library' ); ?><input data-name="to" type="text" name="sc_accession_custody_history[<?php echo esc_attr( $index ); ?>][to]" value="<?php echo esc_attr( $event['to'] ); ?>"></label>
            <label><?php esc_html_e( 'Method', 'sustainable-catalyst-library' ); ?><input data-name="method" type="text" name="sc_accession_custody_history[<?php echo esc_attr( $index ); ?>][method]" value="<?php echo esc_attr( $event['method'] ); ?>"></label>
            <label class="sc-custody-note"><?php esc_html_e( 'Note', 'sustainable-catalyst-library' ); ?><input data-name="note" type="text" name="sc_accession_custody_history[<?php echo esc_attr( $index ); ?>][note]" value="<?php echo esc_attr( $event['note'] ); ?>"></label>
            <button type="button" class="button-link-delete" data-sc-remove-row><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function collection_data( $collection_id, $include_private = false ) {
        $collection_id = absint( $collection_id );
        if ( self::COLLECTION_POST_TYPE !== get_post_type( $collection_id ) ) {
            return array();
        }
        if ( ! $include_private && ! self::collection_is_public( $collection_id ) ) {
            return array();
        }

        $access = (string) get_post_meta( $collection_id, self::META_ACCESS_LEVEL, true ) ?: 'public';
        $components = self::collection_components( $collection_id, $include_private );
        $accessions = $include_private ? self::collection_accessions( $collection_id ) : array();
        $dispositions = $include_private ? self::collection_dispositions( $collection_id ) : array();

        $data = array(
            'schema'          => self::COLLECTION_SCHEMA,
            'collection_id'   => $collection_id,
            'uuid'            => self::ensure_collection_uuid_static( $collection_id ),
            'identifier'      => (string) get_post_meta( $collection_id, self::META_IDENTIFIER, true ),
            'title'           => get_the_title( $collection_id ),
            'summary'         => (string) get_post_field( 'post_excerpt', $collection_id ),
            'description'     => (string) get_post_field( 'post_content', $collection_id ),
            'institution'     => (string) get_post_meta( $collection_id, self::META_INSTITUTION, true ),
            'department'      => (string) get_post_meta( $collection_id, self::META_DEPARTMENT, true ),
            'creator'         => (string) get_post_meta( $collection_id, self::META_CREATOR, true ),
            'dates'           => array(
                'start' => (string) get_post_meta( $collection_id, self::META_DATE_START, true ),
                'end'   => (string) get_post_meta( $collection_id, self::META_DATE_END, true ),
            ),
            'extent'          => (string) get_post_meta( $collection_id, self::META_EXTENT, true ),
            'languages'       => self::text_list( get_post_meta( $collection_id, self::META_LANGUAGES, true ) ),
            'scope_content'   => (string) get_post_meta( $collection_id, self::META_SCOPE, true ),
            'arrangement'     => (string) get_post_meta( $collection_id, self::META_ARRANGEMENT, true ),
            'provenance'      => (string) get_post_meta( $collection_id, self::META_PROVENANCE, true ),
            'rights'          => (string) get_post_meta( $collection_id, self::META_RIGHTS, true ),
            'access_level'    => $access,
            'access_label'    => self::access_levels()[ $access ] ?? $access,
            'embargo_until'   => (string) get_post_meta( $collection_id, self::META_EMBARGO_UNTIL, true ),
            'status'          => (string) get_post_meta( $collection_id, self::META_STATUS, true ) ?: 'draft',
            'finding_aid_public' => '1' === get_post_meta( $collection_id, self::META_FINDING_AID_PUBLIC, true ),
            'url'             => self::collection_is_public( $collection_id ) ? get_permalink( $collection_id ) : '',
            'component_count' => count( $components ),
            'components'      => $components,
        );

        if ( $include_private ) {
            $data['acquisition'] = (string) get_post_meta( $collection_id, self::META_ACQUISITION, true );
            $data['access_note'] = (string) get_post_meta( $collection_id, self::META_ACCESS_NOTE, true );
            $data['use_note'] = (string) get_post_meta( $collection_id, self::META_USE_NOTE, true );
            $data['citation_note'] = (string) get_post_meta( $collection_id, self::META_CITATION_NOTE, true );
            $data['retention'] = self::retention_data( $collection_id );
            $data['preservation'] = get_post_meta( $collection_id, self::META_LAST_AUDIT_REPORT, true );
            $data['accessions'] = $accessions;
            $data['dispositions'] = $dispositions;
        }

        return $data;
    }

    public static function component_data( $component_id, $include_private = false ) {
        $component_id = absint( $component_id );
        if ( self::COMPONENT_POST_TYPE !== get_post_type( $component_id ) ) {
            return array();
        }
        $collection_id = absint( get_post_meta( $component_id, self::META_COMPONENT_COLLECTION, true ) );
        if ( ! $include_private && ! self::component_is_public( $component_id ) ) {
            return array();
        }

        $level = (string) get_post_meta( $component_id, self::META_COMPONENT_LEVEL, true ) ?: 'series';
        $access = (string) get_post_meta( $component_id, self::META_ACCESS_LEVEL, true ) ?: 'public';
        $data = array(
            'schema'          => self::COMPONENT_SCHEMA,
            'component_id'    => $component_id,
            'collection_id'   => $collection_id,
            'parent_id'       => absint( get_post_meta( $component_id, self::META_COMPONENT_PARENT, true ) ),
            'level'           => $level,
            'level_label'     => self::component_levels()[ $level ] ?? $level,
            'identifier'      => (string) get_post_meta( $component_id, self::META_IDENTIFIER, true ),
            'title'           => get_the_title( $component_id ),
            'summary'         => (string) get_post_field( 'post_excerpt', $component_id ),
            'description'     => (string) get_post_field( 'post_content', $component_id ),
            'dates'           => array(
                'start' => (string) get_post_meta( $component_id, self::META_DATE_START, true ),
                'end'   => (string) get_post_meta( $component_id, self::META_DATE_END, true ),
            ),
            'extent'          => (string) get_post_meta( $component_id, self::META_EXTENT, true ),
            'order'           => absint( get_post_meta( $component_id, self::META_COMPONENT_ORDER, true ) ),
            'access_level'    => $access,
            'access_label'    => self::access_levels()[ $access ] ?? $access,
            'embargo_until'   => (string) get_post_meta( $component_id, self::META_EMBARGO_UNTIL, true ),
            'preservation_status' => (string) get_post_meta( $component_id, self::META_COMPONENT_PRESERVATION, true ) ?: 'not-assessed',
            'url'             => self::component_is_public( $component_id ) ? get_permalink( $component_id ) : '',
        );

        if ( $include_private ) {
            $data['document_ids'] = self::id_list( get_post_meta( $component_id, self::META_COMPONENT_DOCUMENT_IDS, true ) );
            $data['source_ids'] = self::id_list( get_post_meta( $component_id, self::META_COMPONENT_SOURCE_IDS, true ) );
            $data['project_ids'] = self::id_list( get_post_meta( $component_id, self::META_COMPONENT_PROJECT_IDS, true ) );
            $objects = get_post_meta( $component_id, self::META_COMPONENT_DIGITAL_OBJECTS, true );
            $data['digital_objects'] = is_array( $objects ) ? $objects : array();
        }

        return $data;
    }

    public static function accession_data( $accession_id, $include_private = true ) {
        if ( self::ACCESSION_POST_TYPE !== get_post_type( $accession_id ) ) {
            return array();
        }
        $method = (string) get_post_meta( $accession_id, self::META_ACCESSION_METHOD, true ) ?: 'transfer';
        $status = (string) get_post_meta( $accession_id, self::META_ACCESSION_STATUS, true ) ?: 'received';
        return array(
            'schema'            => self::ACCESSION_SCHEMA,
            'accession_id'      => absint( $accession_id ),
            'collection_id'     => absint( get_post_meta( $accession_id, self::META_ACCESSION_COLLECTION, true ) ),
            'accession_number'  => (string) get_post_meta( $accession_id, self::META_ACCESSION_NUMBER, true ),
            'title'             => get_the_title( $accession_id ),
            'accession_date'    => (string) get_post_meta( $accession_id, self::META_ACCESSION_DATE, true ),
            'method'            => $method,
            'method_label'      => self::accession_methods()[ $method ] ?? $method,
            'source'            => (string) get_post_meta( $accession_id, self::META_ACCESSION_SOURCE, true ),
            'donor'             => $include_private ? (string) get_post_meta( $accession_id, self::META_ACCESSION_DONOR, true ) : '',
            'agreement'         => $include_private ? (string) get_post_meta( $accession_id, self::META_ACCESSION_AGREEMENT, true ) : '',
            'extent'            => (string) get_post_meta( $accession_id, self::META_ACCESSION_EXTENT, true ),
            'restrictions'      => $include_private ? (string) get_post_meta( $accession_id, self::META_ACCESSION_RESTRICTIONS, true ) : '',
            'processing_status' => $status,
            'processing_label'  => self::processing_statuses()[ $status ] ?? $status,
            'custody_history'   => $include_private ? self::custody_history( $accession_id ) : array(),
        );
    }

    public static function retention_data( $collection_id ) {
        $class = (string) get_post_meta( $collection_id, self::META_RETENTION_CLASS, true ) ?: 'permanent';
        return array(
            'schema'        => self::RETENTION_SCHEMA,
            'class'         => $class,
            'class_label'   => self::retention_classes()[ $class ] ?? $class,
            'years'         => absint( get_post_meta( $collection_id, self::META_RETENTION_YEARS, true ) ),
            'trigger'       => (string) get_post_meta( $collection_id, self::META_RETENTION_TRIGGER, true ),
            'review_date'   => (string) get_post_meta( $collection_id, self::META_RETENTION_REVIEW, true ),
            'legal_hold'    => '1' === get_post_meta( $collection_id, self::META_LEGAL_HOLD, true ),
        );
    }

    public static function finding_aid( $collection_id, $include_private = false ) {
        $collection = self::collection_data( $collection_id, $include_private );
        if ( ! $collection ) {
            return array();
        }
        if ( ! $include_private && empty( $collection['finding_aid_public'] ) ) {
            return array();
        }

        return array(
            'schema'          => self::FINDING_AID_SCHEMA,
            'collection'      => array_diff_key(
                $collection,
                array_flip( array( 'components', 'accessions', 'dispositions', 'preservation' ) )
            ),
            'hierarchy'       => self::component_tree( $collection_id, $include_private ),
            'generated_at'    => current_time( 'mysql' ),
            'library_version' => self::VERSION,
        );
    }

    public static function collection_components( $collection_id, $include_private = false ) {
        $ids = get_posts(
            array(
                'post_type'      => self::COMPONENT_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => self::MAX_LINKS,
                'fields'         => 'ids',
                'meta_key'       => self::META_COMPONENT_COLLECTION,
                'meta_value'     => absint( $collection_id ),
                'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
            )
        );
        $records = array();
        foreach ( $ids as $id ) {
            $record = self::component_data( $id, $include_private );
            if ( $record ) {
                $records[] = $record;
            }
        }
        usort(
            $records,
            static function ( $a, $b ) {
                $order = absint( $a['order'] ) <=> absint( $b['order'] );
                return 0 !== $order ? $order : strnatcasecmp( $a['title'], $b['title'] );
            }
        );
        return $records;
    }

    public static function component_tree( $collection_id, $include_private = false ) {
        $components = self::collection_components( $collection_id, $include_private );
        $children = array();
        foreach ( $components as $component ) {
            $parent = absint( $component['parent_id'] );
            $children[ $parent ][] = $component;
        }
        $build = static function ( $parent_id, $depth = 0 ) use ( &$build, $children ) {
            if ( $depth > 20 || empty( $children[ $parent_id ] ) ) {
                return array();
            }
            $nodes = array();
            foreach ( $children[ $parent_id ] as $component ) {
                $component['children'] = $build( $component['component_id'], $depth + 1 );
                $nodes[] = $component;
            }
            return $nodes;
        };
        return $build( 0 );
    }

    public static function collection_accessions( $collection_id ) {
        $ids = get_posts(
            array(
                'post_type'      => self::ACCESSION_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => self::MAX_LINKS,
                'fields'         => 'ids',
                'meta_key'       => self::META_ACCESSION_COLLECTION,
                'meta_value'     => absint( $collection_id ),
            )
        );
        return array_values(
            array_filter(
                array_map(
                    static function ( $id ) {
                        return self::accession_data( $id, true );
                    },
                    $ids
                )
            )
        );
    }

    public static function collection_dispositions( $collection_id ) {
        $ids = get_posts(
            array(
                'post_type'      => self::DISPOSITION_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => self::MAX_LINKS,
                'fields'         => 'ids',
                'meta_key'       => self::META_DISPOSITION_COLLECTION,
                'meta_value'     => absint( $collection_id ),
            )
        );
        return array_values(
            array_filter(
                array_map(
                    static function ( $id ) {
                        return self::disposition_data( $id );
                    },
                    $ids
                )
            )
        );
    }

    public static function custody_history( $accession_id ) {
        $history = get_post_meta( $accession_id, self::META_ACCESSION_CUSTODY, true );
        return is_array( $history ) ? array_values( $history ) : array();
    }

    public static function run_preservation_audit( $collection_id, $persist = true ) {
        $collection_id = absint( $collection_id );
        if ( self::COLLECTION_POST_TYPE !== get_post_type( $collection_id ) ) {
            return new WP_Error( 'invalid_archive_collection', __( 'The institutional collection is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }

        $components = self::collection_components( $collection_id, true );
        $object_count = 0;
        $checksum_count = 0;
        $missing_checksum = 0;
        $at_risk = 0;
        $missing = 0;
        $component_reports = array();

        foreach ( $components as $component ) {
            $objects = $component['digital_objects'] ?? array();
            $component_at_risk = 0;
            foreach ( $objects as $object ) {
                $object_count++;
                if ( ! empty( $object['checksum'] ) ) {
                    $checksum_count++;
                } else {
                    $missing_checksum++;
                }
                if ( in_array( $object['preservation_status'] ?? '', array( 'at-risk', 'critical' ), true ) ) {
                    $component_at_risk++;
                    $at_risk++;
                }
                if ( 'missing' === ( $object['preservation_status'] ?? '' ) ) {
                    $missing++;
                }
            }
            $component_reports[] = array(
                'component_id'  => $component['component_id'],
                'title'         => $component['title'],
                'object_count'  => count( $objects ),
                'at_risk_count' => $component_at_risk,
                'status'        => $component['preservation_status'],
            );
        }

        $retention = self::retention_data( $collection_id );
        $due_retention = $retention['review_date'] && $retention['review_date'] <= current_time( 'Y-m-d' );
        $score = 100;
        if ( $object_count ) {
            $score -= (int) round( ( $missing_checksum / $object_count ) * 35 );
            $score -= (int) round( ( $at_risk / $object_count ) * 35 );
            $score -= (int) round( ( $missing / $object_count ) * 30 );
        }
        if ( $due_retention ) {
            $score -= 10;
        }
        $score = max( 0, min( 100, $score ) );
        $status = $score >= 90 ? 'stable' : ( $score >= 70 ? 'monitor' : ( $score >= 40 ? 'at-risk' : 'critical' ) );

        $report = array(
            'schema'             => self::PRESERVATION_SCHEMA,
            'collection_id'      => $collection_id,
            'collection_title'   => get_the_title( $collection_id ),
            'score'              => $score,
            'status'             => $status,
            'status_label'       => self::preservation_statuses()[ $status ] ?? $status,
            'component_count'    => count( $components ),
            'digital_objects'    => $object_count,
            'checksums'          => $checksum_count,
            'missing_checksums'  => $missing_checksum,
            'at_risk_objects'    => $at_risk,
            'missing_objects'    => $missing,
            'retention_review_due' => (bool) $due_retention,
            'legal_hold'         => $retention['legal_hold'],
            'components'         => $component_reports,
            'audited_at'         => current_time( 'mysql' ),
            'audited_by'         => get_current_user_id(),
        );

        if ( $persist ) {
            update_post_meta( $collection_id, self::META_LAST_AUDIT, current_time( 'mysql' ) );
            update_post_meta( $collection_id, self::META_LAST_AUDIT_REPORT, $report );
            delete_transient( self::TRANSIENT_DASHBOARD );
        }
        return $report;
    }

    public static function create_disposition( $collection_id, $args = array() ) {
        $collection_id = absint( $collection_id );
        if ( self::COLLECTION_POST_TYPE !== get_post_type( $collection_id ) ) {
            return new WP_Error( 'invalid_disposition_collection', __( 'The institutional collection is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $collection_id ) ) {
            return new WP_Error( 'disposition_forbidden', __( 'You cannot create a disposition for this collection.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $args = wp_parse_args(
            $args,
            array(
                'component_id' => 0,
                'action'       => 'review',
                'reason'       => '',
                'due_date'     => '',
                'title'        => '',
            )
        );
        $component_id = absint( $args['component_id'] );
        if ( $component_id && self::COMPONENT_POST_TYPE !== get_post_type( $component_id ) ) {
            $component_id = 0;
        }
        $action = self::allowed_value( $args['action'], self::disposition_actions(), 'review' );
        $title = sanitize_text_field( $args['title'] );
        if ( '' === $title ) {
            $title = sprintf(
                __( '%1$s disposition — %2$s', 'sustainable-catalyst-library' ),
                self::disposition_actions()[ $action ],
                $component_id ? get_the_title( $component_id ) : get_the_title( $collection_id )
            );
        }

        $record_id = wp_insert_post(
            array(
                'post_type'    => self::DISPOSITION_POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => sanitize_textarea_field( $args['reason'] ),
                'post_author'  => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $record_id ) ) {
            return $record_id;
        }

        update_post_meta( $record_id, self::META_DISPOSITION_COLLECTION, $collection_id );
        update_post_meta( $record_id, self::META_DISPOSITION_COMPONENT, $component_id );
        update_post_meta( $record_id, self::META_DISPOSITION_ACTION, $action );
        update_post_meta( $record_id, self::META_DISPOSITION_REASON, sanitize_textarea_field( $args['reason'] ) );
        update_post_meta( $record_id, self::META_DISPOSITION_STATUS, 'proposed' );
        update_post_meta( $record_id, self::META_DISPOSITION_DUE, self::sanitize_date( $args['due_date'] ) );
        update_post_meta(
            $record_id,
            self::META_DISPOSITION_HISTORY,
            array(
                array(
                    'event_id'   => wp_generate_uuid4(),
                    'event'      => 'created',
                    'status'     => 'proposed',
                    'created_at' => current_time( 'mysql' ),
                    'created_by' => get_current_user_id(),
                ),
            )
        );
        delete_transient( self::TRANSIENT_DASHBOARD );
        return self::disposition_data( $record_id );
    }

    public static function transition_disposition( $record_id, $status, $note = '' ) {
        $record_id = absint( $record_id );
        if ( self::DISPOSITION_POST_TYPE !== get_post_type( $record_id ) ) {
            return new WP_Error( 'invalid_disposition', __( 'The disposition record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $collection_id = absint( get_post_meta( $record_id, self::META_DISPOSITION_COLLECTION, true ) );
        if ( ! current_user_can( 'edit_post', $collection_id ) ) {
            return new WP_Error( 'disposition_forbidden', __( 'You cannot update this disposition.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $statuses = array(
            'proposed'  => __( 'Proposed', 'sustainable-catalyst-library' ),
            'review'    => __( 'Under review', 'sustainable-catalyst-library' ),
            'approved'  => __( 'Approved', 'sustainable-catalyst-library' ),
            'rejected'  => __( 'Rejected', 'sustainable-catalyst-library' ),
            'completed' => __( 'Completed', 'sustainable-catalyst-library' ),
            'cancelled' => __( 'Cancelled', 'sustainable-catalyst-library' ),
        );
        $status = self::allowed_value( $status, $statuses, '' );
        if ( ! $status ) {
            return new WP_Error( 'invalid_disposition_status', __( 'The disposition status is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $retention = self::retention_data( $collection_id );
        $action = (string) get_post_meta( $record_id, self::META_DISPOSITION_ACTION, true );
        if (
            in_array( $status, array( 'approved', 'completed' ), true )
            && in_array( $action, array( 'destroy', 'deaccession', 'transfer' ), true )
            && $retention['legal_hold']
        ) {
            return new WP_Error(
                'disposition_legal_hold',
                __( 'The collection is under legal or administrative hold. Destructive or transfer disposition is blocked.', 'sustainable-catalyst-library' ),
                array( 'status' => 409 )
            );
        }

        $previous = (string) get_post_meta( $record_id, self::META_DISPOSITION_STATUS, true ) ?: 'proposed';
        update_post_meta( $record_id, self::META_DISPOSITION_STATUS, $status );
        if ( 'approved' === $status ) {
            update_post_meta( $record_id, self::META_DISPOSITION_APPROVER, get_current_user_id() );
            update_post_meta( $record_id, self::META_DISPOSITION_APPROVED_AT, current_time( 'mysql' ) );
        }
        if ( 'completed' === $status ) {
            update_post_meta( $record_id, self::META_DISPOSITION_COMPLETED_AT, current_time( 'mysql' ) );
        }

        $history = get_post_meta( $record_id, self::META_DISPOSITION_HISTORY, true );
        $history = is_array( $history ) ? $history : array();
        $history[] = array(
            'event_id'        => wp_generate_uuid4(),
            'event'           => 'status-transition',
            'previous_status' => $previous,
            'status'          => $status,
            'note'            => sanitize_text_field( $note ),
            'created_at'      => current_time( 'mysql' ),
            'created_by'      => get_current_user_id(),
        );
        update_post_meta( $record_id, self::META_DISPOSITION_HISTORY, array_slice( $history, -self::MAX_HISTORY ) );
        delete_transient( self::TRANSIENT_DASHBOARD );

        return self::disposition_data( $record_id );
    }

    public static function disposition_data( $record_id ) {
        if ( self::DISPOSITION_POST_TYPE !== get_post_type( $record_id ) ) {
            return array();
        }
        $action = (string) get_post_meta( $record_id, self::META_DISPOSITION_ACTION, true ) ?: 'review';
        return array(
            'schema'        => self::RETENTION_SCHEMA,
            'disposition_id'=> absint( $record_id ),
            'collection_id' => absint( get_post_meta( $record_id, self::META_DISPOSITION_COLLECTION, true ) ),
            'component_id'  => absint( get_post_meta( $record_id, self::META_DISPOSITION_COMPONENT, true ) ),
            'title'         => get_the_title( $record_id ),
            'action'        => $action,
            'action_label'  => self::disposition_actions()[ $action ] ?? $action,
            'reason'        => (string) get_post_meta( $record_id, self::META_DISPOSITION_REASON, true ),
            'status'        => (string) get_post_meta( $record_id, self::META_DISPOSITION_STATUS, true ) ?: 'proposed',
            'due_date'      => (string) get_post_meta( $record_id, self::META_DISPOSITION_DUE, true ),
            'approver_id'   => absint( get_post_meta( $record_id, self::META_DISPOSITION_APPROVER, true ) ),
            'approved_at'   => (string) get_post_meta( $record_id, self::META_DISPOSITION_APPROVED_AT, true ),
            'completed_at'  => (string) get_post_meta( $record_id, self::META_DISPOSITION_COMPLETED_AT, true ),
            'history'       => is_array( get_post_meta( $record_id, self::META_DISPOSITION_HISTORY, true ) )
                ? array_values( get_post_meta( $record_id, self::META_DISPOSITION_HISTORY, true ) )
                : array(),
        );
    }

    public static function dashboard_report( $include_private = true ) {
        if ( ! $include_private ) {
            $cached = get_transient( self::TRANSIENT_DASHBOARD );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $collection_ids = get_posts(
            array(
                'post_type'      => self::COLLECTION_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        $collections = array();
        $public = 0;
        $restricted = 0;
        $at_risk = 0;
        $retention_due = 0;
        $legal_hold = 0;
        $digital_objects = 0;
        $missing_checksums = 0;
        $today = current_time( 'Y-m-d' );

        foreach ( $collection_ids as $collection_id ) {
            if ( ! $include_private && ! self::collection_is_public( $collection_id ) ) {
                continue;
            }
            $access = (string) get_post_meta( $collection_id, self::META_ACCESS_LEVEL, true ) ?: 'public';
            $retention = self::retention_data( $collection_id );
            $audit = get_post_meta( $collection_id, self::META_LAST_AUDIT_REPORT, true );
            $audit = is_array( $audit ) ? $audit : self::run_preservation_audit( $collection_id, false );

            if ( 'public' === $access ) {
                $public++;
            } else {
                $restricted++;
            }
            if ( in_array( $audit['status'] ?? '', array( 'at-risk', 'critical' ), true ) ) {
                $at_risk++;
            }
            if ( $retention['review_date'] && $retention['review_date'] <= $today ) {
                $retention_due++;
            }
            if ( $retention['legal_hold'] ) {
                $legal_hold++;
            }
            $digital_objects += absint( $audit['digital_objects'] ?? 0 );
            $missing_checksums += absint( $audit['missing_checksums'] ?? 0 );

            $collections[] = array(
                'collection_id'   => $collection_id,
                'title'           => get_the_title( $collection_id ),
                'identifier'      => (string) get_post_meta( $collection_id, self::META_IDENTIFIER, true ),
                'status'          => (string) get_post_meta( $collection_id, self::META_STATUS, true ) ?: 'draft',
                'access_level'    => $access,
                'access_label'    => self::access_levels()[ $access ] ?? $access,
                'component_count' => count( self::collection_components( $collection_id, $include_private ) ),
                'preservation'    => $audit['status'] ?? 'not-assessed',
                'preservation_score' => absint( $audit['score'] ?? 0 ),
                'retention_review'=> $retention['review_date'],
                'legal_hold'      => $retention['legal_hold'],
                'modified'        => get_post_modified_time( 'Y-m-d H:i:s', false, $collection_id, true ),
            );
        }

        $report = array(
            'schema'             => self::DASHBOARD_SCHEMA,
            'collection_count'   => count( $collections ),
            'public_count'       => $public,
            'restricted_count'   => $restricted,
            'at_risk_count'      => $at_risk,
            'retention_due_count'=> $retention_due,
            'legal_hold_count'   => $legal_hold,
            'digital_objects'    => $digital_objects,
            'missing_checksums'  => $missing_checksums,
            'collections'        => $collections,
            'migration'          => self::migration_state(),
            'generated_at'       => current_time( 'mysql' ),
        );

        if ( ! $include_private ) {
            set_transient( self::TRANSIENT_DASHBOARD, $report, 10 * MINUTE_IN_SECONDS );
        }
        return $report;
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $report = self::dashboard_report( true );
        ?>
        <div class="wrap sc-archive-center">
            <p class="sc-archive-kicker"><?php esc_html_e( 'Knowledge Library v3.6.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Institutional Collections and Archive Management', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Manage archival collections, accessions, hierarchical finding aids, custody, restrictions, preservation, retention, and governed disposition.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-archive-metrics">
                <article><strong><?php echo esc_html( $report['collection_count'] ); ?></strong><span><?php esc_html_e( 'Collections', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['public_count'] ); ?></strong><span><?php esc_html_e( 'Public collections', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['restricted_count'] ); ?></strong><span><?php esc_html_e( 'Restricted collections', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['at_risk_count'] ); ?></strong><span><?php esc_html_e( 'At-risk collections', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['retention_due_count'] ); ?></strong><span><?php esc_html_e( 'Retention reviews due', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['missing_checksums'] ); ?></strong><span><?php esc_html_e( 'Missing checksums', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-archive-migration">
                <div>
                    <h2><?php esc_html_e( 'Archive migration', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d records processed', 'sustainable-catalyst-library' ), $report['migration']['processed'], $report['migration']['total'] ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-archive-migration-state><?php echo esc_html( ucfirst( $report['migration']['status'] ) ); ?></span></p>
                </div>
                <button type="button" class="button button-primary" data-sc-archive-run-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-archive-migration-status aria-live="polite"></div>
            </section>

            <div class="sc-archive-section-heading">
                <div><h2><?php esc_html_e( 'Collection register', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Preservation and retention indicators support archival review; they do not replace professional appraisal or legal analysis.', 'sustainable-catalyst-library' ); ?></p></div>
                <div><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::COLLECTION_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Collection', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::COMPONENT_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Component', 'sustainable-catalyst-library' ); ?></a></div>
            </div>

            <?php if ( $report['collections'] ) : ?>
                <div class="sc-archive-table-wrap"><table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Identifier', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Access', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Components', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Preservation', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Retention review', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $report['collections'] as $item ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $item['collection_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><?php if ( $item['legal_hold'] ) : ?> <span class="sc-archive-hold"><?php esc_html_e( 'Hold', 'sustainable-catalyst-library' ); ?></span><?php endif; ?></td>
                                <td><?php echo esc_html( $item['identifier'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( self::collection_statuses()[ $item['status'] ] ?? $item['status'] ); ?></td>
                                <td><?php echo esc_html( $item['access_label'] ); ?></td>
                                <td><?php echo esc_html( $item['component_count'] ); ?></td>
                                <td><span class="sc-archive-preservation status-<?php echo esc_attr( $item['preservation'] ); ?>"><?php echo esc_html( self::preservation_statuses()[ $item['preservation'] ] ?? $item['preservation'] ); ?></span> <?php echo esc_html( $item['preservation_score'] ); ?>/100</td>
                                <td><?php echo esc_html( $item['retention_review'] ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php else : ?>
                <p><?php esc_html_e( 'No institutional collections have been created.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function run_scheduled_migration() {
        $state = self::migration_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public function run_scheduled_preservation_audit() {
        $collection_ids = get_posts(
            array(
                'post_type'      => self::COLLECTION_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 25,
                'fields'         => 'ids',
                'orderby'        => 'meta_value',
                'meta_key'       => self::META_LAST_AUDIT,
                'order'          => 'ASC',
            )
        );
        foreach ( $collection_ids as $collection_id ) {
            self::run_preservation_audit( $collection_id, true );
        }
    }

    public static function run_migration_batch( $limit = self::MIGRATION_BATCH ) {
        global $wpdb;
        if ( get_transient( self::TRANSIENT_MIGRATION_LOCK ) ) {
            return new WP_Error( 'archive_migration_locked', __( 'Another archive migration batch is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );

        try {
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $post_types = array(
                self::COLLECTION_POST_TYPE,
                self::COMPONENT_POST_TYPE,
                self::ACCESSION_POST_TYPE,
            );

            if ( empty( $state['steps'] ) || ! is_array( $state['steps'] ) ) {
                $state['steps'] = array(
                    'collections' => array( 'post_type' => self::COLLECTION_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
                    'components'  => array( 'post_type' => self::COMPONENT_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
                    'accessions'  => array( 'post_type' => self::ACCESSION_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
                );
            }

            $total = 0;
            foreach ( $post_types as $post_type ) {
                $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})";
                $total += absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $post_type ), $statuses ) ) ) );
            }
            $state['total'] = $total;

            $remaining = max( 1, min( 100, absint( $limit ) ) );
            foreach ( $state['steps'] as $step_key => &$step ) {
                if ( ! empty( $step['complete'] ) || $remaining <= 0 ) {
                    continue;
                }
                $query_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
                $params = array_merge(
                    array( $step['post_type'] ),
                    $statuses,
                    array( absint( $step['cursor'] ), $remaining )
                );
                $ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $query_sql, $params ) ) );
                if ( ! $ids ) {
                    $step['complete'] = true;
                    continue;
                }

                foreach ( $ids as $post_id ) {
                    try {
                        if ( self::COLLECTION_POST_TYPE === $step['post_type'] ) {
                            self::ensure_collection_uuid_static( $post_id );
                            if ( ! get_post_meta( $post_id, self::META_STATUS, true ) ) {
                                update_post_meta( $post_id, self::META_STATUS, 'draft' );
                            }
                            if ( ! get_post_meta( $post_id, self::META_ACCESS_LEVEL, true ) ) {
                                update_post_meta( $post_id, self::META_ACCESS_LEVEL, 'public' );
                            }
                            if ( ! get_post_meta( $post_id, self::META_RETENTION_CLASS, true ) ) {
                                update_post_meta( $post_id, self::META_RETENTION_CLASS, 'permanent' );
                            }
                            self::run_preservation_audit( $post_id, true );
                        } elseif ( self::COMPONENT_POST_TYPE === $step['post_type'] ) {
                            if ( ! get_post_meta( $post_id, self::META_COMPONENT_LEVEL, true ) ) {
                                update_post_meta( $post_id, self::META_COMPONENT_LEVEL, 'series' );
                            }
                            if ( ! get_post_meta( $post_id, self::META_ACCESS_LEVEL, true ) ) {
                                update_post_meta( $post_id, self::META_ACCESS_LEVEL, 'public' );
                            }
                            if ( ! get_post_meta( $post_id, self::META_COMPONENT_PRESERVATION, true ) ) {
                                update_post_meta( $post_id, self::META_COMPONENT_PRESERVATION, 'not-assessed' );
                            }
                        } elseif ( self::ACCESSION_POST_TYPE === $step['post_type'] ) {
                            if ( ! get_post_meta( $post_id, self::META_ACCESSION_STATUS, true ) ) {
                                update_post_meta( $post_id, self::META_ACCESSION_STATUS, 'received' );
                            }
                            if ( ! get_post_meta( $post_id, self::META_ACCESSION_METHOD, true ) ) {
                                update_post_meta( $post_id, self::META_ACCESSION_METHOD, 'legacy' );
                            }
                        }
                    } catch ( Throwable $error ) {
                        $state['failures'][] = array(
                            'post_id' => $post_id,
                            'step'    => $step_key,
                            'message' => sanitize_text_field( $error->getMessage() ),
                            'time'    => current_time( 'mysql' ),
                        );
                        $state['failures'] = array_slice( $state['failures'], -100 );
                    }
                    $step['cursor'] = $post_id;
                    $step['processed']++;
                    $state['processed']++;
                    $remaining--;
                    if ( $remaining <= 0 ) {
                        break;
                    }
                }
                if ( count( $ids ) < max( 1, min( 100, absint( $limit ) ) ) ) {
                    $step['complete'] = true;
                }
            }
            unset( $step );

            $all_complete = true;
            foreach ( $state['steps'] as $step ) {
                if ( empty( $step['complete'] ) ) {
                    $all_complete = false;
                    break;
                }
            }
            $state['status'] = $all_complete ? 'complete' : 'pending';
            if ( $all_complete ) {
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
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error( 'archive_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
        }

        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $this->send_ajax_result( self::run_migration_batch( self::MIGRATION_BATCH ), 'migration' );
    }

    public function ajax_run_audit() {
        $this->verify_ajax( 'edit_posts' );
        $collection_id = absint( wp_unslash( $_POST['collection_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $collection_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot audit this collection.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $this->send_ajax_result( self::run_preservation_audit( $collection_id, true ), 'audit' );
    }

    public function ajax_create_disposition() {
        $this->verify_ajax( 'edit_posts' );
        $collection_id = absint( wp_unslash( $_POST['collection_id'] ?? 0 ) );
        $result = self::create_disposition(
            $collection_id,
            array(
                'component_id' => wp_unslash( $_POST['component_id'] ?? 0 ),
                'action'       => wp_unslash( $_POST['action'] ?? 'review' ),
                'reason'       => wp_unslash( $_POST['reason'] ?? '' ),
                'due_date'     => wp_unslash( $_POST['due_date'] ?? '' ),
                'title'        => wp_unslash( $_POST['title'] ?? '' ),
            )
        );
        $this->send_ajax_result( $result, 'disposition' );
    }

    public function ajax_transition_disposition() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::transition_disposition(
            absint( wp_unslash( $_POST['disposition_id'] ?? 0 ) ),
            wp_unslash( $_POST['status'] ?? '' ),
            wp_unslash( $_POST['note'] ?? '' )
        );
        $this->send_ajax_result( $result, 'disposition' );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_institutional_archives_v360', 'nonce' );
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

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/archives/collections',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_collections' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/collections/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_collection' ),
                'permission_callback' => array( $this, 'rest_can_read_collection' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/collections/(?P<id>\d+)/finding-aid',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_finding_aid' ),
                'permission_callback' => array( $this, 'rest_can_read_finding_aid' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/collections/(?P<id>\d+)/preservation',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_preservation' ),
                    'permission_callback' => array( $this, 'rest_can_edit_collection' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_preservation' ),
                    'permission_callback' => array( $this, 'rest_can_edit_collection' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/collections/(?P<id>\d+)/dispositions',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_create_disposition' ),
                'permission_callback' => array( $this, 'rest_can_edit_collection' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/dispositions/(?P<id>\d+)/status',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_transition_disposition' ),
                'permission_callback' => array( $this, 'rest_can_edit_disposition' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/dashboard',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_dashboard' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/archives/migration',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_migration_state' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_migration' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
            )
        );
    }

    public function rest_collections( WP_REST_Request $request ) {
        $include_private = rest_sanitize_boolean( $request->get_param( 'include_private' ) ) && current_user_can( 'edit_posts' );
        $ids = get_posts(
            array(
                'post_type'      => self::COLLECTION_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
                'paged'          => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
                's'              => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
                'fields'         => 'ids',
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $records = array();
        foreach ( $ids as $id ) {
            $record = self::collection_data( $id, $include_private );
            if ( $record ) {
                unset( $record['components'] );
                $records[] = $record;
            }
        }
        return rest_ensure_response( $records );
    }

    public function rest_collection( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_post', $id );
        $record = self::collection_data( $id, $include_private );
        return $record ? rest_ensure_response( $record ) : new WP_Error( 'collection_not_found', __( 'Collection not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_finding_aid( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_post', $id );
        $record = self::finding_aid( $id, $include_private );
        return $record ? rest_ensure_response( $record ) : new WP_Error( 'finding_aid_not_found', __( 'Finding aid not available.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_preservation( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $report = get_post_meta( $id, self::META_LAST_AUDIT_REPORT, true );
        if ( ! is_array( $report ) ) {
            $report = self::run_preservation_audit( $id, false );
        }
        return is_wp_error( $report ) ? $report : rest_ensure_response( $report );
    }

    public function rest_run_preservation( WP_REST_Request $request ) {
        $result = self::run_preservation_audit( absint( $request['id'] ), true );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_create_disposition( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $result = self::create_disposition( absint( $request['id'] ), is_array( $payload ) ? $payload : array() );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_transition_disposition( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::transition_disposition(
            absint( $request['id'] ),
            $payload['status'] ?? '',
            $payload['note'] ?? ''
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_dashboard() {
        return rest_ensure_response( self::dashboard_report( true ) );
    }

    public function rest_migration_state() {
        return rest_ensure_response( self::migration_state() );
    }

    public function rest_run_migration( WP_REST_Request $request ) {
        $limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) );
        $result = self::run_migration_batch( $limit );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_collection( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'edit_post', $id ) || self::collection_is_public( $id );
    }

    public function rest_can_read_finding_aid( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'edit_post', $id )
            || ( self::collection_is_public( $id ) && '1' === get_post_meta( $id, self::META_FINDING_AID_PUBLIC, true ) );
    }

    public function rest_can_edit_collection( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return self::COLLECTION_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
    }

    public function rest_can_edit_disposition( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $collection_id = absint( get_post_meta( $id, self::META_DISPOSITION_COLLECTION, true ) );
        return self::DISPOSITION_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $collection_id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = $request->get_route();
        if ( false === strpos( $route, '/sc-library/v1/archives/' ) ) {
            return $response;
        }
        if (
            current_user_can( 'edit_posts' )
            || false !== strpos( $route, '/dashboard' )
            || false !== strpos( $route, '/migration' )
            || false !== strpos( $route, '/preservation' )
            || false !== strpos( $route, '/dispositions' )
        ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function shortcode_collection( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'              => '',
                'slug'            => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_institutional_collection'
        );
        $collection_id = self::resolve_collection_id( $atts['id'] ?: $atts['slug'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $collection_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $data = self::collection_data( $collection_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-institutional-archives' );
        return self::render_collection_public( $data );
    }

    public function shortcode_finding_aid( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'              => '',
                'slug'            => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_archive_finding_aid'
        );
        $collection_id = self::resolve_collection_id( $atts['id'] ?: $atts['slug'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $collection_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $data = self::finding_aid( $collection_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-institutional-archives' );
        return self::render_finding_aid_public( $data );
    }

    public function shortcode_collection_browser( $atts ) {
        $atts = shortcode_atts(
            array(
                'search'          => '',
                'access'          => '',
                'status'          => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_archive_collection_browser'
        );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_posts' );
        if ( $include_private ) {
            nocache_headers();
        }
        $ids = get_posts(
            array(
                'post_type'      => self::COLLECTION_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 100,
                'fields'         => 'ids',
                's'              => sanitize_text_field( $atts['search'] ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $records = array();
        foreach ( $ids as $id ) {
            $record = self::collection_data( $id, $include_private );
            if ( ! $record ) {
                continue;
            }
            if ( $atts['access'] && sanitize_key( $atts['access'] ) !== $record['access_level'] ) {
                continue;
            }
            if ( $atts['status'] && sanitize_key( $atts['status'] ) !== $record['status'] ) {
                continue;
            }
            $records[] = $record;
        }
        wp_enqueue_style( 'sc-library-institutional-archives' );
        ob_start();
        ?>
        <section class="sc-archive-browser">
            <header><p class="sc-archive-kicker"><?php esc_html_e( 'Institutional collections', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Archive and collection discovery', 'sustainable-catalyst-library' ); ?></h2></header>
            <div class="sc-archive-browser__grid">
                <?php foreach ( $records as $record ) : ?>
                    <article>
                        <p class="sc-archive-record-id"><?php echo esc_html( $record['identifier'] ?: $record['uuid'] ); ?></p>
                        <h3><?php if ( $record['url'] ) : ?><a href="<?php echo esc_url( $record['url'] ); ?>"><?php echo esc_html( $record['title'] ); ?></a><?php else : ?><?php echo esc_html( $record['title'] ); ?><?php endif; ?></h3>
                        <p><?php echo esc_html( $record['summary'] ?: wp_trim_words( $record['scope_content'], 32 ) ); ?></p>
                        <dl><div><dt><?php esc_html_e( 'Dates', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( trim( $record['dates']['start'] . '–' . $record['dates']['end'], '–' ) ?: '—' ); ?></dd></div><div><dt><?php esc_html_e( 'Extent', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $record['extent'] ?: '—' ); ?></dd></div><div><dt><?php esc_html_e( 'Access', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $record['access_label'] ); ?></dd></div></dl>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_preservation_status( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'              => '',
                'slug'            => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_archive_preservation_status'
        );
        $collection_id = self::resolve_collection_id( $atts['id'] ?: $atts['slug'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $collection_id );
        if ( ! $include_private && ! self::collection_is_public( $collection_id ) ) {
            return '';
        }
        if ( $include_private ) {
            nocache_headers();
        }
        $report = get_post_meta( $collection_id, self::META_LAST_AUDIT_REPORT, true );
        if ( ! is_array( $report ) ) {
            $report = self::run_preservation_audit( $collection_id, false );
        }
        if ( is_wp_error( $report ) ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-institutional-archives' );
        ob_start();
        ?>
        <section class="sc-preservation-summary status-<?php echo esc_attr( $report['status'] ); ?>">
            <header><p class="sc-archive-kicker"><?php esc_html_e( 'Preservation status', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $report['collection_title'] ); ?></h2><div class="sc-preservation-score"><strong><?php echo esc_html( $report['score'] ); ?></strong><span>/100</span></div></header>
            <div class="sc-archive-metrics">
                <article><strong><?php echo esc_html( $report['digital_objects'] ); ?></strong><span><?php esc_html_e( 'Digital objects', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['missing_checksums'] ); ?></strong><span><?php esc_html_e( 'Missing checksums', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['at_risk_objects'] ); ?></strong><span><?php esc_html_e( 'At-risk objects', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['missing_objects'] ); ?></strong><span><?php esc_html_e( 'Missing objects', 'sustainable-catalyst-library' ); ?></span></article>
            </div>
            <footer><p><?php esc_html_e( 'This preservation indicator reflects recorded checksums, object states, and retention review data. It is not a substitute for professional digital-preservation assessment.', 'sustainable-catalyst-library' ); ?></p><small><?php echo esc_html( $report['audited_at'] ); ?></small></footer>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_collection_public( $data ) {
        ob_start();
        ?>
        <article class="sc-institutional-collection">
            <header>
                <p class="sc-archive-kicker"><?php esc_html_e( 'Institutional collection', 'sustainable-catalyst-library' ); ?></p>
                <h2><?php echo esc_html( $data['title'] ); ?></h2>
                <p class="sc-archive-record-id"><?php echo esc_html( $data['identifier'] ?: $data['uuid'] ); ?></p>
            </header>
            <div class="sc-collection-summary-grid">
                <dl>
                    <div><dt><?php esc_html_e( 'Creator', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['creator'] ?: '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Institution', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( trim( $data['institution'] . ( $data['department'] ? ' — ' . $data['department'] : '' ) ) ?: '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Dates', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( trim( $data['dates']['start'] . '–' . $data['dates']['end'], '–' ) ?: '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Extent', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['extent'] ?: '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Languages', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['languages'] ? implode( ', ', $data['languages'] ) : '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Access', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['access_label'] ); ?></dd></div>
                </dl>
                <div><h3><?php esc_html_e( 'Scope and content', 'sustainable-catalyst-library' ); ?></h3><p><?php echo nl2br( esc_html( $data['scope_content'] ?: $data['summary'] ) ); ?></p></div>
            </div>
            <?php if ( $data['arrangement'] ) : ?><section><h3><?php esc_html_e( 'Arrangement', 'sustainable-catalyst-library' ); ?></h3><p><?php echo nl2br( esc_html( $data['arrangement'] ) ); ?></p></section><?php endif; ?>
            <?php if ( $data['provenance'] ) : ?><section><h3><?php esc_html_e( 'Provenance and custodial history', 'sustainable-catalyst-library' ); ?></h3><p><?php echo nl2br( esc_html( $data['provenance'] ) ); ?></p></section><?php endif; ?>
            <?php if ( $data['rights'] ) : ?><section><h3><?php esc_html_e( 'Rights', 'sustainable-catalyst-library' ); ?></h3><p><?php echo nl2br( esc_html( $data['rights'] ) ); ?></p></section><?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function render_finding_aid_public( $data ) {
        ob_start();
        ?>
        <section class="sc-finding-aid">
            <header><p class="sc-archive-kicker"><?php esc_html_e( 'Finding aid', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $data['collection']['title'] ); ?></h2><p><?php echo esc_html( $data['collection']['identifier'] ); ?></p></header>
            <?php echo self::render_component_nodes( $data['hierarchy'], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <footer><small><?php echo esc_html( sprintf( __( 'Generated %s', 'sustainable-catalyst-library' ), $data['generated_at'] ) ); ?></small></footer>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_component_nodes( $nodes, $depth ) {
        if ( ! $nodes || $depth > 20 ) {
            return '';
        }
        ob_start();
        echo '<ol class="sc-finding-aid__level depth-' . esc_attr( $depth ) . '">';
        foreach ( $nodes as $node ) {
            echo '<li>';
            echo '<article>';
            echo '<p class="sc-archive-component-level">' . esc_html( $node['level_label'] ) . '</p>';
            echo '<h3>';
            if ( ! empty( $node['url'] ) ) {
                echo '<a href="' . esc_url( $node['url'] ) . '">' . esc_html( $node['title'] ) . '</a>';
            } else {
                echo esc_html( $node['title'] );
            }
            echo '</h3>';
            if ( ! empty( $node['identifier'] ) ) {
                echo '<p class="sc-archive-record-id">' . esc_html( $node['identifier'] ) . '</p>';
            }
            if ( ! empty( $node['summary'] ) ) {
                echo '<p>' . esc_html( $node['summary'] ) . '</p>';
            }
            echo '<dl><div><dt>' . esc_html__( 'Dates', 'sustainable-catalyst-library' ) . '</dt><dd>' . esc_html( trim( $node['dates']['start'] . '–' . $node['dates']['end'], '–' ) ?: '—' ) . '</dd></div><div><dt>' . esc_html__( 'Extent', 'sustainable-catalyst-library' ) . '</dt><dd>' . esc_html( $node['extent'] ?: '—' ) . '</dd></div><div><dt>' . esc_html__( 'Access', 'sustainable-catalyst-library' ) . '</dt><dd>' . esc_html( $node['access_label'] ) . '</dd></div></dl>';
            echo '</article>';
            echo self::render_component_nodes( $node['children'] ?? array(), $depth + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</li>';
        }
        echo '</ol>';
        return ob_get_clean();
    }

    public function cleanup_deleted_record( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( self::COLLECTION_POST_TYPE === $post_type ) {
            foreach ( array( self::COMPONENT_POST_TYPE, self::ACCESSION_POST_TYPE, self::DISPOSITION_POST_TYPE ) as $linked_type ) {
                $meta_key = self::COMPONENT_POST_TYPE === $linked_type
                    ? self::META_COMPONENT_COLLECTION
                    : ( self::ACCESSION_POST_TYPE === $linked_type ? self::META_ACCESSION_COLLECTION : self::META_DISPOSITION_COLLECTION );
                $ids = get_posts(
                    array(
                        'post_type'      => $linked_type,
                        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                        'posts_per_page' => self::MAX_LINKS,
                        'fields'         => 'ids',
                        'meta_key'       => $meta_key,
                        'meta_value'     => absint( $post_id ),
                    )
                );
                foreach ( $ids as $id ) {
                    update_post_meta( $id, $meta_key, 0 );
                }
            }
        } elseif ( self::COMPONENT_POST_TYPE === $post_type ) {
            $child_ids = get_posts(
                array(
                    'post_type'      => self::COMPONENT_POST_TYPE,
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => self::MAX_LINKS,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_COMPONENT_PARENT,
                    'meta_value'     => absint( $post_id ),
                )
            );
            foreach ( $child_ids as $child_id ) {
                update_post_meta( $child_id, self::META_COMPONENT_PARENT, 0 );
                wp_update_post( array( 'ID' => $child_id, 'post_parent' => 0 ) );
            }
            $disposition_ids = get_posts(
                array(
                    'post_type'      => self::DISPOSITION_POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => self::MAX_LINKS,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_DISPOSITION_COMPONENT,
                    'meta_value'     => absint( $post_id ),
                )
            );
            foreach ( $disposition_ids as $record_id ) {
                update_post_meta( $record_id, self::META_DISPOSITION_COMPONENT, 0 );
            }
        }
        delete_transient( self::TRANSIENT_DASHBOARD );
    }

    private function ensure_collection_identity( $collection_id ) {
        return self::ensure_collection_uuid_static( $collection_id );
    }

    private static function ensure_collection_uuid_static( $collection_id ) {
        $uuid = (string) get_post_meta( $collection_id, self::META_COLLECTION_UUID, true );
        if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
            $uuid = wp_generate_uuid4();
            update_post_meta( $collection_id, self::META_COLLECTION_UUID, $uuid );
        }
        return $uuid;
    }

    private static function sanitize_digital_objects( $raw ) {
        $objects = array();
        foreach ( (array) $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $label = sanitize_text_field( $item['label'] ?? '' );
            $uri = esc_url_raw( $item['uri'] ?? '' );
            if ( ! $label && ! $uri ) {
                continue;
            }
            $algorithm = sanitize_key( $item['checksum_algorithm'] ?? 'sha256' );
            if ( ! in_array( $algorithm, array( 'sha256', 'sha512', 'md5' ), true ) ) {
                $algorithm = 'sha256';
            }
            $objects[] = array(
                'object_id'           => sanitize_text_field( $item['object_id'] ?? wp_generate_uuid4() ),
                'label'               => $label,
                'uri'                 => $uri,
                'media_type'          => sanitize_text_field( $item['media_type'] ?? '' ),
                'bytes'               => absint( $item['bytes'] ?? 0 ),
                'checksum'            => strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) ( $item['checksum'] ?? '' ) ) ),
                'checksum_algorithm'  => $algorithm,
                'preservation_status' => self::allowed_value( $item['preservation_status'] ?? 'not-assessed', self::preservation_statuses(), 'not-assessed' ),
                'updated_at'          => current_time( 'mysql' ),
                'updated_by'          => get_current_user_id(),
            );
            if ( count( $objects ) >= self::MAX_LINKS ) {
                break;
            }
        }
        return $objects;
    }

    private static function sanitize_custody_events( $raw ) {
        $events = array();
        foreach ( (array) $raw as $event ) {
            if ( ! is_array( $event ) ) {
                continue;
            }
            $date = self::sanitize_date( $event['date'] ?? '' );
            $from = sanitize_text_field( $event['from'] ?? '' );
            $to = sanitize_text_field( $event['to'] ?? '' );
            $method = sanitize_text_field( $event['method'] ?? '' );
            $note = sanitize_text_field( $event['note'] ?? '' );
            if ( ! $date && ! $from && ! $to && ! $note ) {
                continue;
            }
            $events[] = array(
                'event_id' => sanitize_text_field( $event['event_id'] ?? wp_generate_uuid4() ),
                'date'     => $date,
                'from'     => $from,
                'to'       => $to,
                'method'   => $method,
                'note'     => $note,
            );
            if ( count( $events ) >= self::MAX_HISTORY ) {
                break;
            }
        }
        usort(
            $events,
            static function ( $a, $b ) {
                return strcmp( $a['date'], $b['date'] );
            }
        );
        return $events;
    }

    private static function collection_is_public( $collection_id ) {
        if ( self::COLLECTION_POST_TYPE !== get_post_type( $collection_id ) || 'publish' !== get_post_status( $collection_id ) ) {
            return false;
        }
        $access = (string) get_post_meta( $collection_id, self::META_ACCESS_LEVEL, true ) ?: 'public';
        if ( 'public' !== $access ) {
            return false;
        }
        $embargo = (string) get_post_meta( $collection_id, self::META_EMBARGO_UNTIL, true );
        return ! $embargo || $embargo <= current_time( 'Y-m-d' );
    }

    private static function component_is_public( $component_id ) {
        if ( self::COMPONENT_POST_TYPE !== get_post_type( $component_id ) || 'publish' !== get_post_status( $component_id ) ) {
            return false;
        }
        $collection_id = absint( get_post_meta( $component_id, self::META_COMPONENT_COLLECTION, true ) );
        if ( ! self::collection_is_public( $collection_id ) ) {
            return false;
        }
        $access = (string) get_post_meta( $component_id, self::META_ACCESS_LEVEL, true ) ?: 'public';
        if ( 'public' !== $access ) {
            return false;
        }
        $embargo = (string) get_post_meta( $component_id, self::META_EMBARGO_UNTIL, true );
        return ! $embargo || $embargo <= current_time( 'Y-m-d' );
    }

    private static function resolve_collection_id( $value ) {
        if ( is_numeric( $value ) && self::COLLECTION_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        if ( '' === trim( (string) $value ) && is_singular( self::COLLECTION_POST_TYPE ) ) {
            return get_queried_object_id();
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, self::COLLECTION_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function migration_state() {
        $state = get_option( self::OPTION_MIGRATION, array() );
        return wp_parse_args( is_array( $state ) ? $state : array(), self::default_migration_state() );
    }

    private static function default_migration_state() {
        return array(
            'version'      => self::VERSION,
            'status'       => 'pending',
            'processed'    => 0,
            'total'        => 0,
            'steps'        => array(
                'collections' => array( 'post_type' => self::COLLECTION_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
                'components'  => array( 'post_type' => self::COMPONENT_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
                'accessions'  => array( 'post_type' => self::ACCESSION_POST_TYPE, 'cursor' => 0, 'processed' => 0, 'complete' => false ),
            ),
            'failures'     => array(),
            'last_error'   => '',
            'started_at'   => '',
            'updated_at'   => '',
            'completed_at' => '',
        );
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function sanitize_date( $value ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            return '';
        }
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function text_list( $raw ) {
        if ( is_array( $raw ) ) {
            $values = $raw;
        } else {
            $values = preg_split( '/[\r\n;,]+/', (string) $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $values ) ) ) );
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        WP_CLI::add_command(
            'sc-library archives collection',
            static function ( $args ) {
                $record = self::collection_data( absint( $args[0] ?? 0 ), true );
                if ( ! $record ) {
                    WP_CLI::error( 'Collection not found.' );
                }
                WP_CLI::log( wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );

        WP_CLI::add_command(
            'sc-library archives finding-aid',
            static function ( $args ) {
                $record = self::finding_aid( absint( $args[0] ?? 0 ), true );
                if ( ! $record ) {
                    WP_CLI::error( 'Finding aid not found.' );
                }
                WP_CLI::log( wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );

        WP_CLI::add_command(
            'sc-library archives audit',
            static function ( $args ) {
                $result = self::run_preservation_audit( absint( $args[0] ?? 0 ), true );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );

        WP_CLI::add_command(
            'sc-library archives disposition',
            static function ( $args, $assoc_args ) {
                $result = self::create_disposition(
                    absint( $args[0] ?? 0 ),
                    array(
                        'component_id' => $assoc_args['component'] ?? 0,
                        'action'       => $assoc_args['action'] ?? 'review',
                        'reason'       => $assoc_args['reason'] ?? '',
                        'due_date'     => $assoc_args['due-date'] ?? '',
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( 'Created disposition ' . absint( $result['disposition_id'] ?? 0 ) . '.' );
            }
        );

        WP_CLI::add_command(
            'sc-library archives migrate',
            static function ( $args, $assoc_args ) {
                $result = self::run_migration_batch( absint( $assoc_args['limit'] ?? self::MIGRATION_BATCH ) );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );

        WP_CLI::add_command(
            'sc-library archives dashboard',
            static function () {
                WP_CLI::log( wp_json_encode( self::dashboard_report( true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
    }
}
