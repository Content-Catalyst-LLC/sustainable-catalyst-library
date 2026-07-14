<?php
/**
 * Production Validation and Migration Reliability.
 *
 * Resumable project migration, relationship reconciliation, integrity reports,
 * repair actions, bounded validation queues, private cache protection,
 * snapshot recovery, export diagnostics, and indexed record lookup.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Connected_Research_Reliability {
    public const VERSION = '3.0.1';
    public const MIGRATION_VERSION = '3.0.1';
    public const API_NAMESPACE = 'sc-library/v1';

    public const OPTION_MIGRATION_STATE = 'sc_library_v301_project_migration_state';
    public const OPTION_REPAIR_QUEUE = 'sc_library_v301_project_repair_queue';
    public const OPTION_LAST_REPORT = 'sc_library_v301_last_validation_report';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v301_migration_lock';
    public const CRON_HOOK = 'sc_library_v301_reliability_tick';

    public const META_MIGRATION_VERSION = '_sc_project_v301_migration_version';
    public const META_MIGRATION_REPORT = '_sc_project_v301_migration_report';
    public const META_INTEGRITY_REPORT = '_sc_project_v301_integrity_report';
    public const META_EXPORT_REPORT = '_sc_project_v301_export_report';

    public const BATCH_SIZE = 10;
    public const REPAIR_QUEUE_BATCH = 5;
    public const LOOKUP_LIMIT = 30;
    public const LOCK_SECONDS = 180;

    public function __construct() {
        add_action( 'init', array( __CLASS__, 'maybe_schedule_migration' ), 976 );
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_reliability' ) );

        add_action( 'admin_menu', array( $this, 'register_validation_page' ), 254 );
        add_action( 'add_meta_boxes', array( $this, 'register_reliability_meta_box' ), 110 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 110 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 260 );

        add_action( 'sc_library_connected_research_saved', array( $this, 'queue_project_validation' ) );
        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, array( $this, 'queue_source_projects' ), 100, 3 );

        add_action( 'wp_ajax_sc_library_v301_search_records', array( $this, 'ajax_search_records' ) );
        add_action( 'wp_ajax_sc_library_v301_attach_record', array( $this, 'ajax_attach_record' ) );
        add_action( 'wp_ajax_sc_library_v301_run_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_sc_library_v301_reset_migration', array( $this, 'ajax_reset_migration' ) );
        add_action( 'wp_ajax_sc_library_v301_validate_project', array( $this, 'ajax_validate_project' ) );
        add_action( 'wp_ajax_sc_library_v301_repair_project', array( $this, 'ajax_repair_project' ) );
        add_action( 'wp_ajax_sc_library_v301_validate_exports', array( $this, 'ajax_validate_exports' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 20, 3 );

        self::register_cli_commands();
    }

    public static function maybe_schedule_migration() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }

        $state = get_option( self::OPTION_MIGRATION_STATE, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_MIGRATION_STATE, self::default_migration_state(), false );
        }
    }

    public function run_scheduled_reliability() {
        $this->process_repair_queue( self::REPAIR_QUEUE_BATCH );
        $state = self::migration_state();
        if ( ! in_array( $state['status'], array( 'complete', 'paused' ), true ) ) {
            self::run_migration_batch( self::BATCH_SIZE, false );
        }
    }

    public function register_validation_page() {
        add_submenu_page(
            'sc-library',
            __( 'Production Validation', 'sustainable-catalyst-library' ),
            __( 'Production Validation', 'sustainable-catalyst-library' ),
            'manage_options',
            'sc-library-production-validation',
            array( $this, 'render_validation_page' )
        );
    }

    public function register_reliability_meta_box() {
        add_meta_box(
            'sc-connected-project-reliability',
            __( 'Production Validation and Repair', 'sustainable-catalyst-library' ),
            array( $this, 'render_reliability_meta_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-connected-project-large-library',
            __( 'Large Library Record Lookup', 'sustainable-catalyst-library' ),
            array( $this, 'render_record_lookup_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'normal',
            'default'
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_project = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === $screen->post_type;
        $is_validation = false !== strpos( (string) $screen->id, 'sc-library-production-validation' );
        if ( ! $is_project && ! $is_validation ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-connected-research-reliability',
            SC_LIBRARY_URL . 'assets/css/sc-library-connected-research-reliability.css',
            array( 'sc-library-connected-research' ),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-connected-research-reliability',
            SC_LIBRARY_URL . 'assets/js/sc-library-connected-research-reliability.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-connected-research-reliability',
            'SCLibraryConnectedResearchReliability',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_connected_research_reliability_v301' ),
                'strings' => array(
                    'working'          => __( 'Working…', 'sustainable-catalyst-library' ),
                    'searching'        => __( 'Searching…', 'sustainable-catalyst-library' ),
                    'noResults'        => __( 'No matching records found.', 'sustainable-catalyst-library' ),
                    'attached'         => __( 'Record attached. Reloading…', 'sustainable-catalyst-library' ),
                    'validated'        => __( 'Validation complete.', 'sustainable-catalyst-library' ),
                    'repaired'         => __( 'Repair complete.', 'sustainable-catalyst-library' ),
                    'migrationRun'     => __( 'Migration batch complete.', 'sustainable-catalyst-library' ),
                    'migrationReset'   => __( 'Migration state reset.', 'sustainable-catalyst-library' ),
                    'exportsValidated' => __( 'Export validation complete.', 'sustainable-catalyst-library' ),
                    'confirmReset'     => __( 'Reset the resumable migration cursor and validation history?', 'sustainable-catalyst-library' ),
                    'confirmRepair'    => __( 'Repair this project’s relationships, snapshots, team entries, and indexes?', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function render_reliability_meta_box( $post ) {
        $report = get_post_meta( $post->ID, self::META_INTEGRITY_REPORT, true );
        $report = is_array( $report ) ? $report : self::validate_project( $post->ID, false );
        $migration_version = (string) get_post_meta( $post->ID, self::META_MIGRATION_VERSION, true );
        ?>
        <div class="sc-v301-project-reliability" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><strong><?php esc_html_e( 'Migration', 'sustainable-catalyst-library' ); ?>:</strong> <?php echo esc_html( $migration_version === self::MIGRATION_VERSION ? __( 'Current', 'sustainable-catalyst-library' ) : __( 'Pending validation', 'sustainable-catalyst-library' ) ); ?></p>
            <p><strong><?php esc_html_e( 'Integrity', 'sustainable-catalyst-library' ); ?>:</strong> <span class="sc-v301-status sc-v301-status--<?php echo esc_attr( $report['status'] ?? 'unknown' ); ?>"><?php echo esc_html( ucfirst( $report['status'] ?? 'unknown' ) ); ?></span></p>
            <dl>
                <div><dt><?php esc_html_e( 'Failures', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['failures'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Warnings', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['warnings'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Repairable', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $report['repairable'] ?? array() ) ); ?></dd></div>
            </dl>
            <div class="sc-v301-button-stack">
                <button type="button" class="button" data-sc-v301-validate-project><?php esc_html_e( 'Validate Project', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-v301-repair-project><?php esc_html_e( 'Repair Project', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-v301-validate-exports><?php esc_html_e( 'Validate Exports', 'sustainable-catalyst-library' ); ?></button>
            </div>
            <div data-sc-v301-project-status aria-live="polite"></div>
            <?php if ( ! empty( $report['failures'] ) || ! empty( $report['warnings'] ) ) : ?>
                <details>
                    <summary><?php esc_html_e( 'Validation details', 'sustainable-catalyst-library' ); ?></summary>
                    <ul>
                        <?php foreach ( array_merge( $report['failures'] ?? array(), $report['warnings'] ?? array() ) as $issue ) : ?>
                            <li><?php echo esc_html( $issue ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_record_lookup_box( $post ) {
        ?>
        <div class="sc-v301-record-lookup" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><?php esc_html_e( 'The standard editor loads a bounded set of recent records. Use indexed lookup to attach Sources or Knowledge Library documents from larger collections.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-v301-lookup-controls">
                <label><span><?php esc_html_e( 'Record type', 'sustainable-catalyst-library' ); ?></span><select data-sc-v301-record-type><option value="source"><?php esc_html_e( 'Research Source', 'sustainable-catalyst-library' ); ?></option><option value="document"><?php esc_html_e( 'Knowledge Library document', 'sustainable-catalyst-library' ); ?></option></select></label>
                <label><span><?php esc_html_e( 'Title or identifier', 'sustainable-catalyst-library' ); ?></span><input type="search" data-sc-v301-record-query autocomplete="off" placeholder="<?php esc_attr_e( 'Search at least two characters', 'sustainable-catalyst-library' ); ?>"></label>
                <button type="button" class="button" data-sc-v301-search-records><?php esc_html_e( 'Search Records', 'sustainable-catalyst-library' ); ?></button>
            </div>
            <div data-sc-v301-record-results aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_validation_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $state = self::migration_state();
        $projects = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        ?>
        <div class="wrap sc-v301-validation-page">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge Library v3.0.1', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Production Validation and Migration Reliability', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Run resumable migration batches, inspect project integrity, reconcile relationships, validate exports, and recover malformed bibliography snapshots without deleting research records.', 'sustainable-catalyst-library' ); ?></p>

            <section class="sc-v301-migration-card">
                <div>
                    <h2><?php esc_html_e( 'Resumable migration', 'sustainable-catalyst-library' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?>:</strong> <span data-sc-v301-migration-status><?php echo esc_html( ucfirst( $state['status'] ) ); ?></span></p>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d projects processed', 'sustainable-catalyst-library' ), $state['processed'], $state['total'] ) ); ?></p>
                    <p><?php echo esc_html( sprintf( __( '%1$d repaired · %2$d failures', 'sustainable-catalyst-library' ), $state['repaired'], count( $state['failures'] ) ) ); ?></p>
                </div>
                <div class="sc-v301-button-stack">
                    <button type="button" class="button button-primary" data-sc-v301-run-migration><?php esc_html_e( 'Run Next Batch', 'sustainable-catalyst-library' ); ?></button>
                    <button type="button" class="button" data-sc-v301-reset-migration><?php esc_html_e( 'Reset Migration State', 'sustainable-catalyst-library' ); ?></button>
                </div>
                <div data-sc-v301-migration-message aria-live="polite"></div>
            </section>

            <section>
                <h2><?php esc_html_e( 'Recent project integrity', 'sustainable-catalyst-library' ); ?></h2>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Project', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Migration', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Integrity', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Failures', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Warnings', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $projects as $project ) : ?>
                            <?php $report = get_post_meta( $project->ID, self::META_INTEGRITY_REPORT, true ); $report = is_array( $report ) ? $report : array(); ?>
                            <tr data-project-id="<?php echo esc_attr( $project->ID ); ?>">
                                <td><a href="<?php echo esc_url( get_edit_post_link( $project->ID, 'raw' ) ); ?>"><?php echo esc_html( $project->post_title ); ?></a></td>
                                <td><?php echo esc_html( get_post_meta( $project->ID, self::META_MIGRATION_VERSION, true ) ?: __( 'Pending', 'sustainable-catalyst-library' ) ); ?></td>
                                <td><?php echo esc_html( ucfirst( $report['status'] ?? 'not-checked' ) ); ?></td>
                                <td><?php echo esc_html( count( $report['failures'] ?? array() ) ); ?></td>
                                <td><?php echo esc_html( count( $report['warnings'] ?? array() ) ); ?></td>
                                <td><button type="button" class="button button-small" data-sc-v301-validate-project><?php esc_html_e( 'Validate', 'sustainable-catalyst-library' ); ?></button> <button type="button" class="button button-small" data-sc-v301-repair-project><?php esc_html_e( 'Repair', 'sustainable-catalyst-library' ); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <?php
    }

    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $state = self::migration_state();
        if ( 'failed' === $state['status'] && ! empty( $state['last_error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Knowledge Library project migration paused after an error: %s', 'sustainable-catalyst-library' ), $state['last_error'] ) ) . '</p></div>';
        }
    }

    public function queue_project_validation( $project_id ) {
        self::queue_project( absint( $project_id ) );
    }

    public function queue_source_projects( $post_id, $post, $update ) {
        if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        foreach ( self::id_list( get_post_meta( $post_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true ) ) as $project_id ) {
            self::queue_project( $project_id );
        }
    }

    private static function queue_project( $project_id ) {
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return;
        }
        $queue = self::id_list( get_option( self::OPTION_REPAIR_QUEUE, array() ) );
        $queue[] = $project_id;
        update_option( self::OPTION_REPAIR_QUEUE, array_slice( array_values( array_unique( $queue ) ), -500 ), false );
    }

    private function process_repair_queue( $limit ) {
        $queue = self::id_list( get_option( self::OPTION_REPAIR_QUEUE, array() ) );
        if ( ! $queue ) {
            return;
        }
        $processing = array_slice( $queue, 0, max( 1, absint( $limit ) ) );
        foreach ( $processing as $project_id ) {
            self::validate_project( $project_id, true );
        }
        $remaining = array_values( array_diff( $queue, $processing ) );
        $remaining ? update_option( self::OPTION_REPAIR_QUEUE, $remaining, false ) : delete_option( self::OPTION_REPAIR_QUEUE );
    }

    public static function run_migration_batch( $limit = self::BATCH_SIZE, $force = false ) {
        global $wpdb;

        $lock = get_transient( self::TRANSIENT_MIGRATION_LOCK );
        if ( $lock && ! $force ) {
            return new WP_Error( 'migration_locked', __( 'Another project migration batch is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }

        $token = wp_generate_uuid4();
        set_transient( self::TRANSIENT_MIGRATION_LOCK, $token, self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );
        $state['last_error'] = '';

        try {
            $post_type = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE;
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$status_placeholders})";
            $state['total'] = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $post_type ), $statuses ) ) ) );

            $query_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$status_placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
            $params = array_merge( array( $post_type ), $statuses, array( absint( $state['cursor'] ), max( 1, min( 50, absint( $limit ) ) ) ) );
            $project_ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $query_sql, $params ) ) );

            if ( ! $project_ids ) {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
                $state['updated_at'] = current_time( 'mysql' );
                update_option( self::OPTION_MIGRATION_STATE, $state, false );
                delete_transient( self::TRANSIENT_MIGRATION_LOCK );
                return $state;
            }

            foreach ( $project_ids as $project_id ) {
                $report = self::validate_project( $project_id, true );
                $state['cursor'] = $project_id;
                $state['last_project_id'] = $project_id;
                $state['processed']++;
                if ( ! empty( $report['changes'] ) ) {
                    $state['repaired']++;
                }
                if ( ! empty( $report['failures'] ) ) {
                    $state['failures'][] = array(
                        'project_id' => $project_id,
                        'messages'   => $report['failures'],
                        'time'       => current_time( 'mysql' ),
                    );
                    $state['failures'] = array_slice( $state['failures'], -100 );
                }
            }

            $state['status'] = $state['processed'] >= $state['total'] ? 'complete' : 'pending';
            if ( 'complete' === $state['status'] ) {
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error( 'migration_exception', $error->getMessage(), array( 'status' => 500 ) );
        }

        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    public static function validate_project( $project_id, $repair = false ) {
        $project_id = absint( $project_id );
        $report = array(
            'schema'       => 'sc-library-production-validation/1.0',
            'version'      => self::VERSION,
            'project_id'   => $project_id,
            'status'       => 'valid',
            'failures'     => array(),
            'warnings'     => array(),
            'repairable'   => array(),
            'changes'      => array(),
            'checked_at'   => current_time( 'mysql' ),
            'repair_mode'  => (bool) $repair,
        );

        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            $report['status'] = 'failed';
            $report['failures'][] = __( 'The project record does not exist or has the wrong post type.', 'sustainable-catalyst-library' );
            return $report;
        }

        $sections = self::validated_sections( $project_id, $repair, $report );
        $source_result = self::reconcile_sources( $project_id, $sections, $repair, $report );
        self::validate_documents( $project_id, $repair, $report );
        self::validate_team( $project_id, $repair, $report );
        self::validate_snapshots( $project_id, $repair, $report );
        self::validate_activity( $project_id, $repair, $report );
        self::validate_sort( $project_id, $repair, $report );

        if ( $repair ) {
            $health = SC_Library_Connected_Research_Environment::workspace_health( $project_id, true );
            update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_HEALTH, $health );
            update_post_meta( $project_id, self::META_MIGRATION_VERSION, self::MIGRATION_VERSION );
            update_post_meta(
                $project_id,
                self::META_MIGRATION_REPORT,
                array(
                    'version'    => self::MIGRATION_VERSION,
                    'changes'    => $report['changes'],
                    'failures'   => $report['failures'],
                    'warnings'   => $report['warnings'],
                    'checked_at' => $report['checked_at'],
                )
            );
        }

        if ( $report['failures'] ) {
            $report['status'] = 'failed';
        } elseif ( $report['warnings'] ) {
            $report['status'] = 'warning';
        } elseif ( $report['changes'] ) {
            $report['status'] = 'repaired';
        }

        update_post_meta( $project_id, self::META_INTEGRITY_REPORT, $report );
        update_option( self::OPTION_LAST_REPORT, $report, false );
        return $report;
    }

    private static function validated_sections( $project_id, $repair, &$report ) {
        $stored = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SECTIONS, true );
        $sections = SC_Library_Connected_Research_Environment::bibliography_sections( $project_id );
        $valid = array();
        $seen = array();
        foreach ( (array) $sections as $index => $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }
            $title = sanitize_text_field( $section['title'] ?? '' );
            $slug = sanitize_title( $section['slug'] ?? $title );
            if ( ! $title || ! $slug || isset( $seen[ $slug ] ) ) {
                $report['warnings'][] = __( 'A malformed or duplicate bibliography section was found.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'bibliography-sections';
                continue;
            }
            $seen[ $slug ] = true;
            $valid[] = array(
                'slug'        => $slug,
                'title'       => $title,
                'description' => sanitize_text_field( $section['description'] ?? '' ),
                'order'       => count( $valid ),
            );
        }
        if ( ! $valid ) {
            $valid = SC_Library_Connected_Research_Environment::bibliography_sections( 0 );
        }
        if ( wp_json_encode( $stored ) !== wp_json_encode( $valid ) ) {
            $report['repairable'][] = 'bibliography-sections';
            if ( $repair ) {
                update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SECTIONS, $valid );
                $report['changes'][] = __( 'Bibliography sections normalized.', 'sustainable-catalyst-library' );
            }
        }
        return $valid;
    }

    private static function reconcile_sources( $project_id, $sections, $repair, &$report ) {
        global $wpdb;

        $raw_entries = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SOURCE_ENTRIES, true );
        $raw_entries = is_array( $raw_entries ) ? $raw_entries : array();
        $legacy_ids = self::id_list( get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true ) );

        $like = '%i:' . $project_id . ';%';
        $reverse_ids = array_map(
            'absint',
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value LIKE %s",
                    SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                    SC_Library_Citation_Source_Manager::META_PROJECT_IDS,
                    $like
                )
            )
        );

        $entry_map = array();
        foreach ( $raw_entries as $entry ) {
            if ( ! is_array( $entry ) ) {
                $report['warnings'][] = __( 'A malformed project Source entry was found.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'source-entries';
                continue;
            }
            $source_id = absint( $entry['source_id'] ?? 0 );
            if ( ! $source_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                $report['warnings'][] = __( 'A project Source entry references a missing Source.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'source-entries';
                continue;
            }
            if ( isset( $entry_map[ $source_id ] ) ) {
                $report['warnings'][] = __( 'A duplicate project Source entry was found.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'source-entries';
                continue;
            }
            $entry_map[ $source_id ] = $entry;
        }

        $all_ids = array_values( array_unique( array_merge( array_keys( $entry_map ), $legacy_ids, $reverse_ids ) ) );
        $all_ids = array_values(
            array_filter(
                $all_ids,
                static function ( $source_id ) {
                    return SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id );
                }
            )
        );

        $section_slugs = wp_list_pluck( $sections, 'slug' );
        $default_section = $section_slugs[0] ?? 'core-sources';
        $roles = SC_Library_Connected_Research_Environment::source_role_options();
        $inclusions = SC_Library_Connected_Research_Environment::inclusion_options();
        $normalized = array();

        foreach ( $all_ids as $source_id ) {
            $entry = $entry_map[ $source_id ] ?? array();
            $section = sanitize_title( $entry['section'] ?? $default_section );
            if ( ! in_array( $section, $section_slugs, true ) ) {
                $section = $default_section;
                $report['warnings'][] = sprintf( __( 'Source %d referenced a missing bibliography section.', 'sustainable-catalyst-library' ), $source_id );
                $report['repairable'][] = 'source-sections';
            }
            $role = sanitize_key( $entry['role'] ?? 'background' );
            $inclusion = sanitize_key( $entry['inclusion'] ?? 'included' );
            $normalized[] = array(
                'schema'     => SC_Library_Connected_Research_Environment::SOURCE_ENTRY_SCHEMA,
                'source_id'  => $source_id,
                'role'       => array_key_exists( $role, $roles ) ? $role : 'background',
                'section'    => $section,
                'inclusion'  => array_key_exists( $inclusion, $inclusions ) ? $inclusion : 'included',
                'priority'   => max( 1, min( 5, absint( $entry['priority'] ?? 3 ) ) ),
                'annotation' => sanitize_text_field( $entry['annotation'] ?? '' ),
                'added_at'   => sanitize_text_field( $entry['added_at'] ?? current_time( 'mysql' ) ),
                'added_by'   => absint( $entry['added_by'] ?? get_current_user_id() ),
                'updated_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id(),
            );
        }

        sort( $legacy_ids );
        $normalized_ids = $all_ids;
        sort( $normalized_ids );
        if ( $legacy_ids !== $normalized_ids ) {
            $report['warnings'][] = __( 'The retained project Source ID list did not match the augmented Source registry.', 'sustainable-catalyst-library' );
            $report['repairable'][] = 'project-source-index';
        }
        $sorted_reverse = $reverse_ids;
        sort( $sorted_reverse );
        if ( $sorted_reverse !== $normalized_ids ) {
            $report['warnings'][] = __( 'One or more Source reverse project relationships were inconsistent.', 'sustainable-catalyst-library' );
            $report['repairable'][] = 'source-project-index';
        }

        if ( $repair ) {
            $normalized ? update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SOURCE_ENTRIES, $normalized ) : delete_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SOURCE_ENTRIES );
            self::update_id_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, $normalized_ids );

            foreach ( array_values( array_unique( array_merge( $reverse_ids, $normalized_ids ) ) ) as $source_id ) {
                if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                    continue;
                }
                $projects = self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true ) );
                if ( in_array( $source_id, $normalized_ids, true ) ) {
                    $projects[] = $project_id;
                } else {
                    $projects = array_values( array_diff( $projects, array( $project_id ) ) );
                }
                self::update_id_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, array_values( array_unique( $projects ) ) );
            }
            if ( wp_json_encode( $raw_entries ) !== wp_json_encode( $normalized ) || $legacy_ids !== $normalized_ids || $sorted_reverse !== $normalized_ids ) {
                $report['changes'][] = __( 'Project and Source relationships reconciled.', 'sustainable-catalyst-library' );
            }
        }

        return $normalized;
    }

    private static function validate_documents( $project_id, $repair, &$report ) {
        $stored = self::id_list( get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS, true ) );
        $valid = array_values(
            array_filter(
                $stored,
                static function ( $document_id ) {
                    return SC_Library_Foundation_Pages::POST_TYPE === get_post_type( $document_id );
                }
            )
        );
        if ( $stored !== $valid ) {
            $report['warnings'][] = __( 'One or more connected documents no longer exist.', 'sustainable-catalyst-library' );
            $report['repairable'][] = 'documents';
            if ( $repair ) {
                self::update_id_meta( $project_id, SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS, $valid );
                $report['changes'][] = __( 'Missing document relationships removed.', 'sustainable-catalyst-library' );
            }
        }
    }

    private static function validate_team( $project_id, $repair, &$report ) {
        $stored = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_TEAM, true );
        $stored = is_array( $stored ) ? $stored : array();
        $roles = SC_Library_Connected_Research_Environment::team_role_options();
        $valid = array();
        $seen = array();
        foreach ( $stored as $member ) {
            $user_id = absint( $member['user_id'] ?? 0 );
            $role = sanitize_key( $member['role'] ?? 'researcher' );
            if ( ! $user_id || isset( $seen[ $user_id ] ) || ! get_user_by( 'id', $user_id ) ) {
                $report['warnings'][] = __( 'A missing or duplicate project team member was found.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'team';
                continue;
            }
            $seen[ $user_id ] = true;
            $valid[] = array(
                'user_id' => $user_id,
                'role'    => array_key_exists( $role, $roles ) ? $role : 'researcher',
            );
        }
        if ( wp_json_encode( $stored ) !== wp_json_encode( $valid ) && $repair ) {
            $valid ? update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_TEAM, $valid ) : delete_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_TEAM );
            $report['changes'][] = __( 'Project team entries normalized.', 'sustainable-catalyst-library' );
        }
    }

    private static function validate_snapshots( $project_id, $repair, &$report ) {
        $stored = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SNAPSHOTS, true );
        $stored = is_array( $stored ) ? $stored : array();
        $valid = array();
        $ids = array();
        foreach ( array_slice( $stored, -SC_Library_Connected_Research_Environment::MAX_SNAPSHOTS ) as $snapshot ) {
            if ( ! is_array( $snapshot ) ) {
                $report['warnings'][] = __( 'A malformed bibliography snapshot was found.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'snapshots';
                continue;
            }
            $entries = array();
            foreach ( array_slice( (array) ( $snapshot['entries'] ?? array() ), 0, SC_Library_Connected_Research_Environment::MAX_SOURCE_ENTRIES ) as $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['citation'] ) ) {
                    continue;
                }
                $entries[] = array(
                    'source_id'    => absint( $entry['source_id'] ?? 0 ),
                    'citation_key' => sanitize_text_field( $entry['citation_key'] ?? '' ),
                    'citation'     => sanitize_textarea_field( $entry['citation'] ?? '' ),
                    'section'      => sanitize_title( $entry['section'] ?? 'core-sources' ),
                    'role'         => sanitize_key( $entry['role'] ?? 'background' ),
                    'annotation'   => sanitize_text_field( $entry['annotation'] ?? '' ),
                );
            }
            $id = sanitize_text_field( $snapshot['id'] ?? '' );
            if ( ! $id || isset( $ids[ $id ] ) ) {
                $id = wp_generate_uuid4();
                $report['warnings'][] = __( 'A snapshot had a missing or duplicate identifier.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'snapshots';
            }
            $ids[ $id ] = true;
            $hash = hash( 'sha256', wp_json_encode( $entries ) );
            if ( ( $snapshot['hash'] ?? '' ) !== $hash ) {
                $report['warnings'][] = __( 'A bibliography snapshot hash did not match its stored entries.', 'sustainable-catalyst-library' );
                $report['repairable'][] = 'snapshots';
            }
            $valid[] = array(
                'schema'     => SC_Library_Connected_Research_Environment::SNAPSHOT_SCHEMA,
                'id'         => $id,
                'label'      => sanitize_text_field( $snapshot['label'] ?? __( 'Recovered snapshot', 'sustainable-catalyst-library' ) ),
                'style'      => sanitize_key( $snapshot['style'] ?? 'harvard' ),
                'sort'       => sanitize_key( $snapshot['sort'] ?? 'section-author' ),
                'entries'    => $entries,
                'hash'       => $hash,
                'created_at' => sanitize_text_field( $snapshot['created_at'] ?? current_time( 'mysql' ) ),
                'created_by' => absint( $snapshot['created_by'] ?? 0 ),
            );
        }
        if ( wp_json_encode( $stored ) !== wp_json_encode( $valid ) && $repair ) {
            $valid ? update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SNAPSHOTS, $valid ) : delete_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SNAPSHOTS );
            $report['changes'][] = __( 'Bibliography snapshots recovered and rehashed.', 'sustainable-catalyst-library' );
        }
    }

    private static function validate_activity( $project_id, $repair, &$report ) {
        $stored = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_ACTIVITY, true );
        $stored = is_array( $stored ) ? $stored : array();
        if ( count( $stored ) > SC_Library_Connected_Research_Environment::MAX_ACTIVITY ) {
            $report['warnings'][] = __( 'The project activity history exceeded its retention boundary.', 'sustainable-catalyst-library' );
            $report['repairable'][] = 'activity';
            if ( $repair ) {
                update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_ACTIVITY, array_slice( $stored, -SC_Library_Connected_Research_Environment::MAX_ACTIVITY ) );
                $report['changes'][] = __( 'Project activity history trimmed to the retention boundary.', 'sustainable-catalyst-library' );
            }
        }
    }

    private static function validate_sort( $project_id, $repair, &$report ) {
        $sort = sanitize_key( get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SORT, true ) ?: 'section-author' );
        if ( ! array_key_exists( $sort, SC_Library_Connected_Research_Environment::sort_options() ) ) {
            $report['warnings'][] = __( 'The bibliography sort mode was invalid.', 'sustainable-catalyst-library' );
            $report['repairable'][] = 'sort';
            if ( $repair ) {
                update_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SORT, 'section-author' );
                $report['changes'][] = __( 'Bibliography sort mode reset.', 'sustainable-catalyst-library' );
            }
        }
    }

    public static function validate_exports( $project_id, $include_private = true ) {
        $formats = array( 'markdown', 'text', 'html', 'bibtex', 'ris', 'csl-json', 'json' );
        $report = array(
            'schema'     => 'sc-library-export-validation/1.0',
            'project_id' => absint( $project_id ),
            'status'     => 'valid',
            'formats'    => array(),
            'failures'   => array(),
            'warnings'   => array(),
            'checked_at' => current_time( 'mysql' ),
        );

        foreach ( $formats as $format ) {
            $export = SC_Library_Connected_Research_Environment::export_project( $project_id, $format, $include_private );
            if ( is_wp_error( $export ) ) {
                $report['formats'][ $format ] = array( 'status' => 'failed', 'message' => $export->get_error_message() );
                $report['failures'][] = sprintf( __( '%s export failed.', 'sustainable-catalyst-library' ), strtoupper( $format ) );
                continue;
            }

            $content = $export['content'];
            $valid = true;
            $warnings = array();
            if ( in_array( $format, array( 'markdown', 'text', 'html' ), true ) ) {
                $valid = is_string( $content ) && '' !== trim( wp_strip_all_tags( $content ) );
            } elseif ( 'bibtex' === $format ) {
                $valid = is_string( $content ) && self::validate_bibtex( $content );
            } elseif ( 'ris' === $format ) {
                $valid = is_string( $content ) && self::validate_ris( $content );
            } elseif ( 'csl-json' === $format ) {
                $valid = is_array( $content ) && self::validate_csl_records( $content, $warnings );
            } elseif ( 'json' === $format ) {
                $encoded = wp_json_encode( $content );
                $valid = is_array( $content ) && false !== $encoded && ! empty( $content['schema'] );
            }

            $report['formats'][ $format ] = array(
                'status'   => $valid ? ( $warnings ? 'warning' : 'valid' ) : 'failed',
                'bytes'    => is_string( $content ) ? strlen( $content ) : strlen( wp_json_encode( $content ) ),
                'warnings' => $warnings,
            );
            if ( ! $valid ) {
                $report['failures'][] = sprintf( __( '%s export failed structural validation.', 'sustainable-catalyst-library' ), strtoupper( $format ) );
            }
            foreach ( $warnings as $warning ) {
                $report['warnings'][] = strtoupper( $format ) . ': ' . $warning;
            }
        }

        if ( $report['failures'] ) {
            $report['status'] = 'failed';
        } elseif ( $report['warnings'] ) {
            $report['status'] = 'warning';
        }
        update_post_meta( $project_id, self::META_EXPORT_REPORT, $report );
        return $report;
    }

    private static function validate_bibtex( $content ) {
        $content = trim( $content );
        if ( '' === $content ) {
            return true;
        }
        if ( ! preg_match_all( '/@[A-Za-z]+\s*\{[^,]+,/u', $content, $matches ) ) {
            return false;
        }
        return substr_count( $content, '{' ) === substr_count( $content, '}' );
    }

    private static function validate_ris( $content ) {
        $content = trim( $content );
        if ( '' === $content ) {
            return true;
        }
        $records = preg_split( '/\nER\s+-\s*(?:\n|$)/u', $content );
        if ( substr_count( $content, 'TY  - ' ) !== substr_count( $content, 'ER  -' ) ) {
            return false;
        }
        return 0 === strpos( $content, 'TY  - ' ) && false !== strpos( $content, 'ER  -' );
    }

    private static function validate_csl_records( $records, &$warnings ) {
        foreach ( $records as $index => $record ) {
            if ( ! is_array( $record ) || empty( $record['id'] ) || empty( $record['type'] ) || empty( $record['title'] ) ) {
                return false;
            }
            if ( empty( $record['author'] ) ) {
                $warnings[] = sprintf( __( 'Record %d has no author.', 'sustainable-catalyst-library' ), $index + 1 );
            }
            if ( empty( $record['issued'] ) ) {
                $warnings[] = sprintf( __( 'Record %d has no issued date.', 'sustainable-catalyst-library' ), $index + 1 );
            }
        }
        return true;
    }

    public function ajax_search_records() {
        $this->verify_ajax( 'edit_posts' );
        $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        $type = sanitize_key( wp_unslash( $_POST['record_type'] ?? 'source' ) );
        if ( strlen( $query ) < 2 ) {
            wp_send_json_error( array( 'message' => __( 'Enter at least two characters.', 'sustainable-catalyst-library' ) ), 400 );
        }

        $post_type = 'document' === $type ? SC_Library_Foundation_Pages::POST_TYPE : SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE;
        $search = new WP_Query(
            array(
                'post_type'      => $post_type,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => self::LOOKUP_LIMIT,
                's'              => $query,
                'orderby'        => 'relevance',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        $items = array();
        foreach ( $search->posts as $post ) {
            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }
            $items[] = array(
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'status' => $post->post_status,
                'type'   => $type,
                'edit'   => get_edit_post_link( $post->ID, 'raw' ),
            );
        }
        wp_send_json_success( array( 'items' => $items ) );
    }

    public function ajax_attach_record() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $record_id = absint( wp_unslash( $_POST['record_id'] ?? 0 ) );
        $type = sanitize_key( wp_unslash( $_POST['record_type'] ?? 'source' ) );

        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot edit this project.', 'sustainable-catalyst-library' ) ), 403 );
        }

        if ( 'document' === $type ) {
            if ( SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $record_id ) ) {
                wp_send_json_error( array( 'message' => __( 'The selected document is invalid.', 'sustainable-catalyst-library' ) ), 400 );
            }
            $ids = self::id_list( get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS, true ) );
            $ids[] = $record_id;
            self::update_id_meta( $project_id, SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS, array_values( array_unique( $ids ) ) );
        } else {
            $result = SC_Library_Connected_Research_Environment::attach_source_to_project( $project_id, $record_id, 'candidate', 'background' );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
            }
        }

        self::validate_project( $project_id, true );
        wp_send_json_success( array( 'message' => __( 'Record attached to the project.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $result = self::run_migration_batch( self::BATCH_SIZE, false );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'state' => $result ) );
    }

    public function ajax_reset_migration() {
        $this->verify_ajax( 'manage_options' );
        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        $state = self::default_migration_state();
        update_option( self::OPTION_MIGRATION_STATE, $state, false );
        wp_send_json_success( array( 'state' => $state ) );
    }

    public function ajax_validate_project() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot validate this project.', 'sustainable-catalyst-library' ) ), 403 );
        }
        wp_send_json_success( array( 'report' => self::validate_project( $project_id, false ) ) );
    }

    public function ajax_repair_project() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot repair this project.', 'sustainable-catalyst-library' ) ), 403 );
        }
        wp_send_json_success( array( 'report' => self::validate_project( $project_id, true ) ) );
    }

    public function ajax_validate_exports() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot validate exports for this project.', 'sustainable-catalyst-library' ) ), 403 );
        }
        wp_send_json_success( array( 'report' => self::validate_exports( $project_id, true ) ) );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_connected_research_reliability_v301', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/reliability/migration',
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
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/validation',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_validation' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/repair',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_project_repair' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/export-validation',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_export_validation' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
    }

    public function rest_migration_state() {
        return rest_ensure_response( self::migration_state() );
    }

    public function rest_run_migration( WP_REST_Request $request ) {
        $limit = max( 1, min( 50, absint( $request->get_param( 'limit' ) ?: self::BATCH_SIZE ) ) );
        return rest_ensure_response( self::run_migration_batch( $limit, false ) );
    }

    public function rest_project_validation( WP_REST_Request $request ) {
        return rest_ensure_response( self::validate_project( absint( $request['id'] ), false ) );
    }

    public function rest_project_repair( WP_REST_Request $request ) {
        return rest_ensure_response( self::validate_project( absint( $request['id'] ), true ) );
    }

    public function rest_export_validation( WP_REST_Request $request ) {
        return rest_ensure_response( self::validate_exports( absint( $request['id'] ), true ) );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id ) && current_user_can( 'edit_post', $project_id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = (string) $request->get_route();
        if ( 0 !== strpos( $route, '/' . self::API_NAMESPACE . '/projects/' ) ) {
            return $response;
        }

        $project_id = absint( $request->get_param( 'id' ) );
        $private_route = false !== strpos( $route, '/activity' )
            || false !== strpos( $route, '/snapshots' )
            || false !== strpos( $route, '/repair' )
            || false !== strpos( $route, '/validation' )
            || false !== strpos( $route, '/migration' );

        $private_project = $project_id
            && (
                'publish' !== get_post_status( $project_id )
                || 'public' !== get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true )
            );

        if ( $private_route || $private_project || is_user_logged_in() ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    private static function migration_state() {
        $state = get_option( self::OPTION_MIGRATION_STATE, array() );
        return wp_parse_args( is_array( $state ) ? $state : array(), self::default_migration_state() );
    }

    private static function default_migration_state() {
        return array(
            'version'         => self::MIGRATION_VERSION,
            'status'          => 'pending',
            'cursor'          => 0,
            'total'           => 0,
            'processed'       => 0,
            'repaired'        => 0,
            'failures'        => array(),
            'last_project_id' => 0,
            'last_error'      => '',
            'started_at'      => '',
            'updated_at'      => '',
            'completed_at'    => '',
        );
    }

    private static function update_id_meta( $post_id, $key, $ids ) {
        $ids = self::id_list( $ids );
        $ids ? update_post_meta( $post_id, $key, $ids ) : delete_post_meta( $post_id, $key );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }
        WP_CLI::add_command(
            'sc-library projects migrate',
            static function ( $args, $assoc_args ) {
                $limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : self::BATCH_SIZE;
                $result = self::run_migration_batch( $limit, ! empty( $assoc_args['force'] ) );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library projects validate',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $args[0] ?? 0 );
                $report = self::validate_project( $project_id, ! empty( $assoc_args['repair'] ) );
                WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT ) );
                if ( 'failed' === $report['status'] ) {
                    WP_CLI::halt( 1 );
                }
            }
        );
        WP_CLI::add_command(
            'sc-library projects exports',
            static function ( $args ) {
                $project_id = absint( $args[0] ?? 0 );
                $report = self::validate_exports( $project_id, true );
                WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT ) );
                if ( 'failed' === $report['status'] ) {
                    WP_CLI::halt( 1 );
                }
            }
        );
    }
}
