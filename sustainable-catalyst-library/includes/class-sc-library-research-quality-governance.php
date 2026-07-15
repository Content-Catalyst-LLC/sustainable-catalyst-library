<?php
/**
 * Research Quality and Governance Center.
 *
 * Adds governance policies, project quality evaluations, review gates,
 * quality issues, exceptions, approval history, transparency summaries,
 * cross-product quality context, REST routes, and WP-CLI operations.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Research_Quality_Governance {
    public const VERSION = '3.5.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const POLICY_POST_TYPE = 'sc_research_policy';
    public const REVIEW_POST_TYPE = 'sc_quality_review';
    public const ISSUE_POST_TYPE = 'sc_quality_issue';

    public const QUALITY_SCHEMA = 'sc-library-research-quality/1.0';
    public const POLICY_SCHEMA = 'sc-library-governance-policy/1.0';
    public const REVIEW_SCHEMA = 'sc-library-quality-review/1.0';
    public const ISSUE_SCHEMA = 'sc-library-quality-issue/1.0';
    public const TRANSPARENCY_SCHEMA = 'sc-library-research-transparency/1.0';
    public const DASHBOARD_SCHEMA = 'sc-library-governance-dashboard/1.0';

    public const META_PROJECT_PROFILE = '_sc_quality_governance_profile';
    public const META_PROJECT_POLICIES = '_sc_quality_policy_ids';
    public const META_PROJECT_GATE = '_sc_quality_gate';
    public const META_PROJECT_SCORE = '_sc_quality_last_score';
    public const META_PROJECT_EVALUATION = '_sc_quality_last_evaluation';
    public const META_PROJECT_HISTORY = '_sc_quality_approval_history';
    public const META_PROJECT_PUBLIC = '_sc_quality_public_summary';
    public const META_PROJECT_REVIEW_IDS = '_sc_quality_review_ids';
    public const META_PROJECT_ISSUE_IDS = '_sc_quality_issue_ids';
    public const META_PROJECT_EXCEPTION_IDS = '_sc_quality_exception_ids';
    public const META_PROJECT_MIGRATED = '_sc_quality_v350_migrated';

    public const META_POLICY_DOMAIN = '_sc_policy_domain';
    public const META_POLICY_VERSION = '_sc_policy_version';
    public const META_POLICY_STATUS = '_sc_policy_status';
    public const META_POLICY_GATE = '_sc_policy_required_gate';
    public const META_POLICY_CONTROLS = '_sc_policy_controls';
    public const META_POLICY_PUBLIC = '_sc_policy_public';
    public const META_POLICY_EFFECTIVE = '_sc_policy_effective_date';
    public const META_POLICY_REVIEW_DATE = '_sc_policy_review_date';
    public const META_POLICY_OWNER = '_sc_policy_owner';

    public const META_RECORD_PROJECT = '_sc_quality_project_id';
    public const META_RECORD_DOMAIN = '_sc_quality_domain';
    public const META_RECORD_STATUS = '_sc_quality_record_status';
    public const META_RECORD_OUTCOME = '_sc_quality_review_outcome';
    public const META_RECORD_SEVERITY = '_sc_quality_issue_severity';
    public const META_RECORD_FINDINGS = '_sc_quality_findings';
    public const META_RECORD_ACTIONS = '_sc_quality_required_actions';
    public const META_RECORD_REVIEWER = '_sc_quality_reviewer_id';
    public const META_RECORD_DUE = '_sc_quality_due_date';
    public const META_RECORD_COMPLETED = '_sc_quality_completed_at';
    public const META_RECORD_EXCEPTION = '_sc_quality_exception';
    public const META_RECORD_EXCEPTION_EXPIRY = '_sc_quality_exception_expiry';
    public const META_RECORD_EXCEPTION_APPROVER = '_sc_quality_exception_approver';
    public const META_RECORD_HISTORY = '_sc_quality_record_history';

    public const OPTION_MIGRATION = 'sc_library_v350_quality_migration';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v350_quality_migration_lock';
    public const TRANSIENT_DASHBOARD = 'sc_library_v350_governance_dashboard';
    public const CRON_MIGRATION = 'sc_library_v350_quality_migration_tick';
    public const CRON_STALE_REVIEW = 'sc_library_v350_stale_review_tick';

    public const MIGRATION_BATCH = 20;
    public const LOCK_SECONDS = 180;
    public const MAX_HISTORY = 100;
    public const MAX_LINKED_RECORDS = 500;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_record_types' ), 300 );
        add_action( 'init', array( $this, 'schedule_operations' ), 990 );
        add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
        add_action( self::CRON_STALE_REVIEW, array( $this, 'run_stale_review_scan' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 300 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 200 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 200 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, array( $this, 'save_project_governance' ), 200, 3 );
        add_action( 'save_post_' . self::POLICY_POST_TYPE, array( $this, 'save_policy' ), 200, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_record' ) );

        add_action( 'wp_ajax_sc_library_v350_evaluate_project', array( $this, 'ajax_evaluate_project' ) );
        add_action( 'wp_ajax_sc_library_v350_create_review', array( $this, 'ajax_create_review' ) );
        add_action( 'wp_ajax_sc_library_v350_create_issue', array( $this, 'ajax_create_issue' ) );
        add_action( 'wp_ajax_sc_library_v350_transition_gate', array( $this, 'ajax_transition_gate' ) );
        add_action( 'wp_ajax_sc_library_v350_run_migration', array( $this, 'ajax_run_migration' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 80, 3 );
        add_filter( 'sc_library_cross_product_handoff_bundle', array( $this, 'filter_handoff_bundle' ), 40, 4 );

        add_shortcode( 'sc_research_quality', array( $this, 'shortcode_quality' ) );
        add_shortcode( 'sc_research_governance', array( $this, 'shortcode_governance' ) );
        add_shortcode( 'sc_research_transparency', array( $this, 'shortcode_transparency' ) );
        add_shortcode( 'sc_research_governance_dashboard', array( $this, 'shortcode_dashboard' ) );

        self::register_cli_commands();
    }

    public static function gate_options() {
        return array(
            'draft'           => __( 'Draft', 'sustainable-catalyst-library' ),
            'internal-review' => __( 'Internal review', 'sustainable-catalyst-library' ),
            'quality-review'  => __( 'Quality review', 'sustainable-catalyst-library' ),
            'conditional'     => __( 'Conditionally approved', 'sustainable-catalyst-library' ),
            'approved'        => __( 'Approved', 'sustainable-catalyst-library' ),
            'published'       => __( 'Published', 'sustainable-catalyst-library' ),
            'archived'        => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public static function governance_profiles() {
        return array(
            'exploratory'    => __( 'Exploratory research', 'sustainable-catalyst-library' ),
            'standard'       => __( 'Standard research', 'sustainable-catalyst-library' ),
            'high-assurance' => __( 'High-assurance research', 'sustainable-catalyst-library' ),
            'public-release' => __( 'Public release', 'sustainable-catalyst-library' ),
            'institutional'  => __( 'Institutional or regulated research', 'sustainable-catalyst-library' ),
        );
    }

    public static function review_domains() {
        return array(
            'methodology'     => __( 'Methodology', 'sustainable-catalyst-library' ),
            'evidence'        => __( 'Evidence sufficiency', 'sustainable-catalyst-library' ),
            'citation'        => __( 'Citation quality', 'sustainable-catalyst-library' ),
            'provenance'      => __( 'Provenance and reproducibility', 'sustainable-catalyst-library' ),
            'integrity'       => __( 'Research integrity', 'sustainable-catalyst-library' ),
            'ethics'          => __( 'Ethics', 'sustainable-catalyst-library' ),
            'privacy'         => __( 'Privacy and data protection', 'sustainable-catalyst-library' ),
            'legal'           => __( 'Legal and policy', 'sustainable-catalyst-library' ),
            'accessibility'   => __( 'Accessibility', 'sustainable-catalyst-library' ),
            'publication'     => __( 'Publication readiness', 'sustainable-catalyst-library' ),
            'reproducibility' => __( 'Reproducibility', 'sustainable-catalyst-library' ),
            'cross-product'   => __( 'Cross-product handoff readiness', 'sustainable-catalyst-library' ),
        );
    }

    public static function review_outcomes() {
        return array(
            'pending'     => __( 'Pending', 'sustainable-catalyst-library' ),
            'pass'        => __( 'Pass', 'sustainable-catalyst-library' ),
            'conditional' => __( 'Conditional pass', 'sustainable-catalyst-library' ),
            'fail'        => __( 'Fail', 'sustainable-catalyst-library' ),
            'waived'      => __( 'Waived', 'sustainable-catalyst-library' ),
        );
    }

    public static function issue_statuses() {
        return array(
            'open'       => __( 'Open', 'sustainable-catalyst-library' ),
            'in-review'  => __( 'In review', 'sustainable-catalyst-library' ),
            'mitigated'  => __( 'Mitigated', 'sustainable-catalyst-library' ),
            'accepted'   => __( 'Accepted risk', 'sustainable-catalyst-library' ),
            'resolved'   => __( 'Resolved', 'sustainable-catalyst-library' ),
            'closed'     => __( 'Closed', 'sustainable-catalyst-library' ),
        );
    }

    public static function severity_options() {
        return array(
            'low'      => __( 'Low', 'sustainable-catalyst-library' ),
            'medium'   => __( 'Medium', 'sustainable-catalyst-library' ),
            'high'     => __( 'High', 'sustainable-catalyst-library' ),
            'critical' => __( 'Critical', 'sustainable-catalyst-library' ),
        );
    }

    public static function policy_domains() {
        return array_merge(
            self::review_domains(),
            array(
                'records'    => __( 'Records and retention', 'sustainable-catalyst-library' ),
                'authorship' => __( 'Authorship and attribution', 'sustainable-catalyst-library' ),
                'conflicts'  => __( 'Conflicts of interest', 'sustainable-catalyst-library' ),
                'security'   => __( 'Information security', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function register_record_types() {
        register_post_type(
            self::POLICY_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Research Policies', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Research Policy', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Research Policy', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Research Policy', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'revisions' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'exclude_from_search' => true,
            )
        );

        foreach (
            array(
                self::REVIEW_POST_TYPE => __( 'Quality Reviews', 'sustainable-catalyst-library' ),
                self::ISSUE_POST_TYPE  => __( 'Quality Issues', 'sustainable-catalyst-library' ),
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
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_STALE_REVIEW ) ) {
            wp_schedule_event( time() + 3600, 'daily', self::CRON_STALE_REVIEW );
        }
        $state = get_option( self::OPTION_MIGRATION, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
        }
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Research Quality and Governance Center', 'sustainable-catalyst-library' ),
            __( 'Quality & Governance', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-quality-governance',
            array( $this, 'render_workspace' )
        );
        add_submenu_page(
            'sc-library',
            __( 'Research Policies', 'sustainable-catalyst-library' ),
            __( 'Research Policies', 'sustainable-catalyst-library' ),
            'edit_posts',
            'edit.php?post_type=' . self::POLICY_POST_TYPE
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-research-quality-governance',
            __( 'Research Quality and Governance', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_meta_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-research-quality-readiness',
            __( 'Quality Readiness', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_readiness_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-research-policy-controls',
            __( 'Governance Policy Controls', 'sustainable-catalyst-library' ),
            array( $this, 'render_policy_meta_box' ),
            self::POLICY_POST_TYPE,
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
                SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                self::POLICY_POST_TYPE,
            ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-quality-governance' );
        if ( ! $supported && ! $workspace ) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style( 'sc-library-quality-governance' );
        wp_enqueue_script( 'sc-library-quality-governance' );
        wp_localize_script(
            'sc-library-quality-governance',
            'SCLibraryQualityGovernance',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_quality_governance_v350' ),
                'strings' => array(
                    'working'  => __( 'Working…', 'sustainable-catalyst-library' ),
                    'complete' => __( 'Operation complete.', 'sustainable-catalyst-library' ),
                    'error'    => __( 'The governance operation failed.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        $this->register_assets();
    }

    private function register_assets() {
        wp_register_style(
            'sc-library-quality-governance',
            SC_LIBRARY_URL . 'assets/css/sc-library-research-quality-governance.css',
            array( 'sc-library-connected-research' ),
            self::VERSION
        );
        wp_register_script(
            'sc-library-quality-governance',
            SC_LIBRARY_URL . 'assets/js/sc-library-research-quality-governance.js',
            array(),
            self::VERSION,
            true
        );
    }

    public static function evaluate_project( $project_id, $persist = true ) {
        $project_id = absint( $project_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error(
                'invalid_quality_project',
                __( 'The Research Project is invalid.', 'sustainable-catalyst-library' ),
                array( 'status' => 404 )
            );
        }

        $dimensions = array(
            'research-design' => self::evaluate_research_design( $project_id ),
            'sources'         => self::evaluate_sources( $project_id ),
            'evidence'        => self::evaluate_evidence( $project_id ),
            'provenance'      => self::evaluate_provenance( $project_id ),
            'semantics'       => self::evaluate_semantics( $project_id ),
            'pathways'        => self::evaluate_pathways( $project_id ),
            'handoffs'        => self::evaluate_handoffs( $project_id ),
            'governance'      => self::evaluate_governance( $project_id ),
        );

        $score = 0;
        $maximum = 0;
        $findings = array();
        $actions = array();
        foreach ( $dimensions as $dimension ) {
            $score += absint( $dimension['score'] ?? 0 );
            $maximum += absint( $dimension['maximum'] ?? 0 );
            $findings = array_merge( $findings, (array) ( $dimension['findings'] ?? array() ) );
            $actions = array_merge( $actions, (array) ( $dimension['actions'] ?? array() ) );
        }

        $issues = self::project_issue_records( $project_id, true );
        $reviews = self::project_review_records( $project_id, true );
        $critical_open = count(
            array_filter(
                $issues,
                static function ( $issue ) {
                    return 'critical' === ( $issue['severity'] ?? '' )
                        && ! in_array( $issue['status'] ?? '', array( 'resolved', 'closed' ), true );
                }
            )
        );
        $high_open = count(
            array_filter(
                $issues,
                static function ( $issue ) {
                    return 'high' === ( $issue['severity'] ?? '' )
                        && ! in_array( $issue['status'] ?? '', array( 'resolved', 'closed' ), true );
                }
            )
        );
        $failed_reviews = count(
            array_filter(
                $reviews,
                static function ( $review ) {
                    return 'fail' === ( $review['outcome'] ?? '' );
                }
            )
        );

        $percentage = $maximum ? (int) round( ( $score / $maximum ) * 100 ) : 0;
        $status = self::readiness_status( $percentage, $critical_open, $failed_reviews );
        $gate = (string) get_post_meta( $project_id, self::META_PROJECT_GATE, true ) ?: 'draft';
        $profile = (string) get_post_meta( $project_id, self::META_PROJECT_PROFILE, true ) ?: 'standard';

        if ( $critical_open ) {
            $findings[] = sprintf(
                _n(
                    '%d open critical issue blocks approval.',
                    '%d open critical issues block approval.',
                    $critical_open,
                    'sustainable-catalyst-library'
                ),
                $critical_open
            );
            $actions[] = __( 'Resolve or formally except every critical issue before approval.', 'sustainable-catalyst-library' );
        }
        if ( $failed_reviews ) {
            $findings[] = sprintf(
                _n(
                    '%d failed review requires remediation.',
                    '%d failed reviews require remediation.',
                    $failed_reviews,
                    'sustainable-catalyst-library'
                ),
                $failed_reviews
            );
        }

        $evaluation = array(
            'schema'          => self::QUALITY_SCHEMA,
            'project_id'      => $project_id,
            'project_title'   => get_the_title( $project_id ),
            'profile'         => $profile,
            'profile_label'   => self::governance_profiles()[ $profile ] ?? $profile,
            'gate'            => $gate,
            'gate_label'      => self::gate_options()[ $gate ] ?? $gate,
            'score'           => $percentage,
            'earned_points'   => $score,
            'maximum_points'  => $maximum,
            'status'          => $status,
            'status_label'    => self::readiness_labels()[ $status ] ?? $status,
            'dimensions'      => $dimensions,
            'findings'        => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $findings ) ) ) ),
            'required_actions'=> array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $actions ) ) ) ),
            'issue_counts'    => array(
                'total'         => count( $issues ),
                'critical_open' => $critical_open,
                'high_open'     => $high_open,
            ),
            'review_counts'   => array(
                'total'   => count( $reviews ),
                'failed'  => $failed_reviews,
                'pending' => count(
                    array_filter(
                        $reviews,
                        static function ( $review ) {
                            return 'pending' === ( $review['outcome'] ?? '' );
                        }
                    )
                ),
            ),
            'evaluated_at'    => current_time( 'mysql' ),
            'evaluated_by'    => get_current_user_id(),
            'version'         => self::VERSION,
        );

        if ( $persist ) {
            update_post_meta( $project_id, self::META_PROJECT_SCORE, $percentage );
            update_post_meta( $project_id, self::META_PROJECT_EVALUATION, $evaluation );
            self::append_project_history(
                $project_id,
                array(
                    'event'   => 'quality-evaluation',
                    'score'   => $percentage,
                    'status'  => $status,
                    'gate'    => $gate,
                    'message' => __( 'Research quality evaluation completed.', 'sustainable-catalyst-library' ),
                )
            );
            delete_transient( self::TRANSIENT_DASHBOARD );
        }

        return $evaluation;
    }

    public static function readiness_labels() {
        return array(
            'blocked'             => __( 'Blocked', 'sustainable-catalyst-library' ),
            'not-ready'           => __( 'Not ready', 'sustainable-catalyst-library' ),
            'needs-review'        => __( 'Needs review', 'sustainable-catalyst-library' ),
            'conditionally-ready' => __( 'Conditionally ready', 'sustainable-catalyst-library' ),
            'ready'               => __( 'Ready', 'sustainable-catalyst-library' ),
        );
    }

    private static function readiness_status( $score, $critical_open, $failed_reviews ) {
        if ( $critical_open || $failed_reviews ) {
            return 'blocked';
        }
        if ( $score < 50 ) {
            return 'not-ready';
        }
        if ( $score < 70 ) {
            return 'needs-review';
        }
        if ( $score < 85 ) {
            return 'conditionally-ready';
        }
        return 'ready';
    }

    private static function dimension( $label, $score, $maximum, $findings = array(), $actions = array() ) {
        $score = max( 0, min( absint( $maximum ), absint( $score ) ) );
        $ratio = $maximum ? $score / $maximum : 0;
        $status = $ratio >= 0.85 ? 'strong' : ( $ratio >= 0.65 ? 'adequate' : ( $ratio >= 0.4 ? 'weak' : 'missing' ) );
        return array(
            'label'    => $label,
            'score'    => $score,
            'maximum'  => absint( $maximum ),
            'status'   => $status,
            'findings' => array_values( array_filter( $findings ) ),
            'actions'  => array_values( array_filter( $actions ) ),
        );
    }

    private static function evaluate_research_design( $project_id ) {
        $question = (string) get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_RESEARCH_QUESTION, true );
        $objectives = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_OBJECTIVES, true );
        $methods = (string) get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_METHODS, true );
        $scope = (string) get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SCOPE, true );

        $score = 0;
        $findings = array();
        $actions = array();
        if ( '' !== trim( $question ) ) {
            $score += 4;
        } else {
            $findings[] = __( 'The project has no explicit research question.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Define the research question.', 'sustainable-catalyst-library' );
        }
        if ( is_array( $objectives ) ? count( array_filter( $objectives ) ) : '' !== trim( (string) $objectives ) ) {
            $score += 3;
        } else {
            $actions[] = __( 'Record measurable research objectives.', 'sustainable-catalyst-library' );
        }
        if ( '' !== trim( $methods ) ) {
            $score += 4;
        } else {
            $findings[] = __( 'The methodology is not documented.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Document methods, assumptions, and limitations.', 'sustainable-catalyst-library' );
        }
        if ( '' !== trim( $scope ) ) {
            $score += 2;
        } else {
            $actions[] = __( 'Define the research scope and exclusions.', 'sustainable-catalyst-library' );
        }
        if ( has_excerpt( $project_id ) || '' !== trim( (string) get_post_field( 'post_content', $project_id ) ) ) {
            $score += 2;
        }

        return self::dimension( __( 'Research design', 'sustainable-catalyst-library' ), $score, 15, $findings, $actions );
    }

    private static function evaluate_sources( $project_id ) {
        $entries = class_exists( 'SC_Library_Connected_Research_Environment' )
            ? SC_Library_Connected_Research_Environment::source_entries( $project_id, true )
            : array();
        $included = array_values(
            array_filter(
                (array) $entries,
                static function ( $entry ) {
                    return 'included' === ( $entry['inclusion'] ?? 'included' );
                }
            )
        );
        $source_ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ( $entry ) {
                            return absint( $entry['source_id'] ?? 0 );
                        },
                        $included
                    )
                )
            )
        );

        $score = 0;
        $findings = array();
        $actions = array();
        if ( $source_ids ) {
            $score += 5;
        } else {
            $findings[] = __( 'The project has no included Research Sources.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Add and review Research Sources.', 'sustainable-catalyst-library' );
        }

        $verified = 0;
        $peer_reviewed = 0;
        $integrity_risk = 0;
        foreach ( $source_ids as $source_id ) {
            if ( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, true ) ) {
                $verified++;
            }
            if ( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PEER_REVIEWED, true ) ) {
                $peer_reviewed++;
            }
            if ( class_exists( 'SC_Library_Source_Versioning_Integrity' ) ) {
                $integrity = SC_Library_Source_Versioning_Integrity::get_integrity_data( $source_id, true );
                if ( in_array( $integrity['severity'] ?? '', array( 'high', 'critical' ), true ) ) {
                    $integrity_risk++;
                }
            }
        }

        if ( $source_ids ) {
            $verified_ratio = $verified / count( $source_ids );
            $score += (int) round( $verified_ratio * 5 );
            $peer_ratio = $peer_reviewed / count( $source_ids );
            $score += (int) round( $peer_ratio * 3 );
            if ( 0 === $integrity_risk ) {
                $score += 2;
            } else {
                $findings[] = sprintf(
                    _n(
                        '%d Source has a high or critical integrity warning.',
                        '%d Sources have high or critical integrity warnings.',
                        $integrity_risk,
                        'sustainable-catalyst-library'
                    ),
                    $integrity_risk
                );
                $actions[] = __( 'Review Source-integrity warnings and replacement guidance.', 'sustainable-catalyst-library' );
            }
        }

        return self::dimension( __( 'Sources and citations', 'sustainable-catalyst-library' ), $score, 15, $findings, $actions );
    }

    private static function evaluate_evidence( $project_id ) {
        $claim_ids = get_posts(
            array(
                'post_type'      => SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'meta_key'       => SC_Library_Evidence_Claim_Linking::META_CLAIM_PROJECT_IDS,
                'meta_value'     => 'i:' . absint( $project_id ) . ';',
                'meta_compare'   => 'LIKE',
            )
        );
        $evidence_ids = get_posts(
            array(
                'post_type'      => SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'meta_key'       => SC_Library_Evidence_Claim_Linking::META_PROJECT_IDS,
                'meta_value'     => 'i:' . absint( $project_id ) . ';',
                'meta_compare'   => 'LIKE',
            )
        );

        $score = 0;
        $findings = array();
        $actions = array();
        if ( $claim_ids ) {
            $score += 5;
        } else {
            $findings[] = __( 'The project has no structured Research Claims.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Record the project’s principal Claims.', 'sustainable-catalyst-library' );
        }
        if ( $evidence_ids ) {
            $score += 5;
        } else {
            $findings[] = __( 'The project has no Evidence Notes.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Create Evidence Notes with precise locators.', 'sustainable-catalyst-library' );
        }

        $linked_claims = 0;
        foreach ( $claim_ids as $claim_id ) {
            $links = get_post_meta( $claim_id, SC_Library_Evidence_Claim_Linking::META_CLAIM_EVIDENCE_IDS, true );
            if ( is_array( $links ) && array_filter( array_map( 'absint', $links ) ) ) {
                $linked_claims++;
            }
        }
        if ( $claim_ids ) {
            $score += (int) round( ( $linked_claims / count( $claim_ids ) ) * 5 );
            if ( $linked_claims < count( $claim_ids ) ) {
                $actions[] = __( 'Connect every substantive Claim to reviewed Evidence Notes.', 'sustainable-catalyst-library' );
            }
        }

        return self::dimension( __( 'Claims and evidence', 'sustainable-catalyst-library' ), $score, 15, $findings, $actions );
    }

    private static function evaluate_provenance( $project_id ) {
        $document_ids = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS, true );
        $document_ids = self::id_list( $document_ids );
        $source_entries = SC_Library_Connected_Research_Environment::source_entries( $project_id, true );

        $score = 0;
        $findings = array();
        $actions = array();
        if ( $document_ids ) {
            $score += 3;
        }
        $provenance_count = 0;
        foreach ( (array) $source_entries as $entry ) {
            $source_id = absint( $entry['source_id'] ?? 0 );
            if ( ! $source_id ) {
                continue;
            }
            $provenance = get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROVENANCE, true );
            if ( $provenance ) {
                $provenance_count++;
            }
        }
        if ( $source_entries ) {
            $score += (int) round( min( 1, $provenance_count / count( $source_entries ) ) * 4 );
        }
        $activity = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_ACTIVITY, true );
        if ( is_array( $activity ) && $activity ) {
            $score += 2;
        }
        $snapshots = get_post_meta( $project_id, SC_Library_Connected_Research_Environment::META_SNAPSHOTS, true );
        if ( is_array( $snapshots ) && $snapshots ) {
            $score += 1;
        }

        if ( $score < 6 ) {
            $findings[] = __( 'Project provenance and reproducibility records are incomplete.', 'sustainable-catalyst-library' );
            $actions[] = __( 'Record source provenance, project activity, methods, and stable snapshots.', 'sustainable-catalyst-library' );
        }

        return self::dimension( __( 'Provenance and reproducibility', 'sustainable-catalyst-library' ), $score, 10, $findings, $actions );
    }

    private static function evaluate_semantics( $project_id ) {
        $topics = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
            ? wp_get_object_terms( $project_id, SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, array( 'fields' => 'ids' ) )
            : array();
        $concepts = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
            ? self::id_list( get_post_meta( $project_id, SC_Library_Topics_Concepts_Relationships::META_CONCEPT_IDS, true ) )
            : array();
        $entities = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
            ? self::id_list( get_post_meta( $project_id, SC_Library_Topics_Concepts_Relationships::META_ENTITY_IDS, true ) )
            : array();

        $score = 0;
        $actions = array();
        if ( ! is_wp_error( $topics ) && $topics ) {
            $score += 4;
        } else {
            $actions[] = __( 'Assign canonical Knowledge Topics.', 'sustainable-catalyst-library' );
        }
        if ( $concepts ) {
            $score += 3;
        } else {
            $actions[] = __( 'Connect reusable Concepts.', 'sustainable-catalyst-library' );
        }
        if ( $entities ) {
            $score += 1;
        }

        return self::dimension( __( 'Semantic organization', 'sustainable-catalyst-library' ), $score, 8, array(), $actions );
    }

    private static function evaluate_pathways( $project_id ) {
        $pathway_ids = class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' )
            ? get_posts(
                array(
                    'post_type'      => SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE,
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => 50,
                    'fields'         => 'ids',
                    'meta_key'       => SC_Library_Knowledge_Pathways_Article_Maps::META_DERIVED_PROJECT_ID,
                    'meta_value'     => absint( $project_id ),
                )
            )
            : array();

        $score = $pathway_ids ? 7 : 0;
        $actions = $pathway_ids ? array() : array( __( 'Create or derive a Knowledge Pathway for public or internal navigation.', 'sustainable-catalyst-library' ) );
        return self::dimension( __( 'Pathways and navigation', 'sustainable-catalyst-library' ), $score, 7, array(), $actions );
    }

    private static function evaluate_handoffs( $project_id ) {
        $score = 0;
        $actions = array();
        if ( class_exists( 'SC_Library_Cross_Product_Research_Handoffs' ) ) {
            $identity = SC_Library_Cross_Product_Research_Handoffs::project_identity( $project_id, true );
            if ( ! is_wp_error( $identity ) && ! empty( $identity['uuid'] ) ) {
                $score += 4;
            }
            $handoff_ids = get_posts(
                array(
                    'post_type'      => SC_Library_Cross_Product_Research_Handoffs::HANDOFF_POST_TYPE,
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => 100,
                    'fields'         => 'ids',
                    'meta_key'       => SC_Library_Cross_Product_Research_Handoffs::META_PROJECT_ID,
                    'meta_value'     => absint( $project_id ),
                )
            );
            if ( $handoff_ids ) {
                $score += 3;
            }
        }
        if ( $score < 4 ) {
            $actions[] = __( 'Confirm stable project identity and target-product readiness.', 'sustainable-catalyst-library' );
        }

        return self::dimension( __( 'Cross-product readiness', 'sustainable-catalyst-library' ), $score, 7, array(), $actions );
    }

    private static function evaluate_governance( $project_id ) {
        $policies = self::id_list( get_post_meta( $project_id, self::META_PROJECT_POLICIES, true ) );
        $reviews = self::project_review_records( $project_id, true );
        $issues = self::project_issue_records( $project_id, true );
        $gate = (string) get_post_meta( $project_id, self::META_PROJECT_GATE, true ) ?: 'draft';

        $score = 0;
        $findings = array();
        $actions = array();
        if ( $policies ) {
            $score += 4;
        } else {
            $actions[] = __( 'Assign applicable governance policies.', 'sustainable-catalyst-library' );
        }
        $passed = count(
            array_filter(
                $reviews,
                static function ( $review ) {
                    return in_array( $review['outcome'] ?? '', array( 'pass', 'conditional', 'waived' ), true );
                }
            )
        );
        if ( $passed ) {
            $score += min( 4, $passed );
        } else {
            $actions[] = __( 'Complete at least one recorded quality review.', 'sustainable-catalyst-library' );
        }

        $open_high = count(
            array_filter(
                $issues,
                static function ( $issue ) {
                    return in_array( $issue['severity'] ?? '', array( 'high', 'critical' ), true )
                        && ! in_array( $issue['status'] ?? '', array( 'resolved', 'closed' ), true );
                }
            )
        );
        if ( 0 === $open_high ) {
            $score += 3;
        } else {
            $findings[] = __( 'High-severity governance issues remain open.', 'sustainable-catalyst-library' );
        }

        if ( in_array( $gate, array( 'quality-review', 'conditional', 'approved', 'published' ), true ) ) {
            $score += 1;
        }

        return self::dimension( __( 'Governance and review', 'sustainable-catalyst-library' ), $score, 12, $findings, $actions );
    }

    public static function create_review( $project_id, $args = array() ) {
        $project_id = absint( $project_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error( 'invalid_review_project', __( 'The Research Project is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'review_forbidden', __( 'You cannot create a review for this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $args = wp_parse_args(
            $args,
            array(
                'domain'    => 'publication',
                'outcome'   => 'pending',
                'findings'  => '',
                'actions'   => '',
                'reviewer'  => get_current_user_id(),
                'due_date'  => '',
                'title'     => '',
            )
        );
        $domain = self::allowed_value( $args['domain'], self::review_domains(), 'publication' );
        $outcome = self::allowed_value( $args['outcome'], self::review_outcomes(), 'pending' );
        $title = sanitize_text_field( $args['title'] );
        if ( '' === $title ) {
            $title = sprintf(
                __( '%1$s review — %2$s', 'sustainable-catalyst-library' ),
                self::review_domains()[ $domain ],
                get_the_title( $project_id )
            );
        }

        $review_id = wp_insert_post(
            array(
                'post_type'    => self::REVIEW_POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => sanitize_textarea_field( $args['findings'] ),
                'post_author'  => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $review_id ) ) {
            return $review_id;
        }

        update_post_meta( $review_id, self::META_RECORD_PROJECT, $project_id );
        update_post_meta( $review_id, self::META_RECORD_DOMAIN, $domain );
        update_post_meta( $review_id, self::META_RECORD_OUTCOME, $outcome );
        update_post_meta( $review_id, self::META_RECORD_STATUS, 'completed' === $outcome ? 'completed' : 'active' );
        update_post_meta( $review_id, self::META_RECORD_FINDINGS, sanitize_textarea_field( $args['findings'] ) );
        update_post_meta( $review_id, self::META_RECORD_ACTIONS, sanitize_textarea_field( $args['actions'] ) );
        update_post_meta( $review_id, self::META_RECORD_REVIEWER, absint( $args['reviewer'] ) ?: get_current_user_id() );
        update_post_meta( $review_id, self::META_RECORD_DUE, self::sanitize_date( $args['due_date'] ) );
        if ( 'pending' !== $outcome ) {
            update_post_meta( $review_id, self::META_RECORD_COMPLETED, current_time( 'mysql' ) );
        }
        update_post_meta(
            $review_id,
            self::META_RECORD_HISTORY,
            array(
                array(
                    'event'      => 'created',
                    'outcome'    => $outcome,
                    'created_at' => current_time( 'mysql' ),
                    'created_by' => get_current_user_id(),
                ),
            )
        );

        self::append_project_record_id( $project_id, self::META_PROJECT_REVIEW_IDS, $review_id );
        self::append_project_history(
            $project_id,
            array(
                'event'     => 'quality-review-created',
                'review_id' => $review_id,
                'domain'    => $domain,
                'outcome'   => $outcome,
                'message'   => __( 'Quality review created.', 'sustainable-catalyst-library' ),
            )
        );
        self::evaluate_project( $project_id, true );
        return self::review_data( $review_id, true );
    }

    public static function create_issue( $project_id, $args = array() ) {
        $project_id = absint( $project_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error( 'invalid_issue_project', __( 'The Research Project is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'issue_forbidden', __( 'You cannot create an issue for this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $args = wp_parse_args(
            $args,
            array(
                'domain'           => 'integrity',
                'severity'         => 'medium',
                'status'           => 'open',
                'title'            => '',
                'description'      => '',
                'actions'          => '',
                'due_date'         => '',
                'exception'        => false,
                'exception_expiry' => '',
                'exception_approver'=> 0,
            )
        );
        $domain = self::allowed_value( $args['domain'], self::review_domains(), 'integrity' );
        $severity = self::allowed_value( $args['severity'], self::severity_options(), 'medium' );
        $status = self::allowed_value( $args['status'], self::issue_statuses(), 'open' );
        $title = sanitize_text_field( $args['title'] );
        if ( '' === $title ) {
            $title = sprintf(
                __( '%1$s issue — %2$s', 'sustainable-catalyst-library' ),
                self::review_domains()[ $domain ],
                get_the_title( $project_id )
            );
        }

        $issue_id = wp_insert_post(
            array(
                'post_type'    => self::ISSUE_POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => sanitize_textarea_field( $args['description'] ),
                'post_author'  => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $issue_id ) ) {
            return $issue_id;
        }

        update_post_meta( $issue_id, self::META_RECORD_PROJECT, $project_id );
        update_post_meta( $issue_id, self::META_RECORD_DOMAIN, $domain );
        update_post_meta( $issue_id, self::META_RECORD_SEVERITY, $severity );
        update_post_meta( $issue_id, self::META_RECORD_STATUS, $status );
        update_post_meta( $issue_id, self::META_RECORD_ACTIONS, sanitize_textarea_field( $args['actions'] ) );
        update_post_meta( $issue_id, self::META_RECORD_DUE, self::sanitize_date( $args['due_date'] ) );
        update_post_meta( $issue_id, self::META_RECORD_EXCEPTION, rest_sanitize_boolean( $args['exception'] ) ? '1' : '0' );
        update_post_meta( $issue_id, self::META_RECORD_EXCEPTION_EXPIRY, self::sanitize_date( $args['exception_expiry'] ) );
        update_post_meta( $issue_id, self::META_RECORD_EXCEPTION_APPROVER, absint( $args['exception_approver'] ) );
        update_post_meta(
            $issue_id,
            self::META_RECORD_HISTORY,
            array(
                array(
                    'event'      => 'created',
                    'status'     => $status,
                    'severity'   => $severity,
                    'created_at' => current_time( 'mysql' ),
                    'created_by' => get_current_user_id(),
                ),
            )
        );

        self::append_project_record_id( $project_id, self::META_PROJECT_ISSUE_IDS, $issue_id );
        if ( rest_sanitize_boolean( $args['exception'] ) ) {
            self::append_project_record_id( $project_id, self::META_PROJECT_EXCEPTION_IDS, $issue_id );
        }
        self::append_project_history(
            $project_id,
            array(
                'event'     => 'quality-issue-created',
                'issue_id'  => $issue_id,
                'domain'    => $domain,
                'severity'  => $severity,
                'status'    => $status,
                'message'   => __( 'Quality issue created.', 'sustainable-catalyst-library' ),
            )
        );
        self::evaluate_project( $project_id, true );
        return self::issue_data( $issue_id, true );
    }

    public static function transition_gate( $project_id, $gate, $note = '' ) {
        $project_id = absint( $project_id );
        $gate = self::allowed_value( $gate, self::gate_options(), '' );
        if ( ! $gate ) {
            return new WP_Error( 'invalid_governance_gate', __( 'The requested governance gate is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'gate_forbidden', __( 'You cannot transition this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $evaluation = self::evaluate_project( $project_id, true );
        if ( is_wp_error( $evaluation ) ) {
            return $evaluation;
        }

        if ( in_array( $gate, array( 'approved', 'published' ), true ) ) {
            if ( 'blocked' === $evaluation['status'] || $evaluation['score'] < 70 ) {
                return new WP_Error(
                    'quality_gate_blocked',
                    __( 'The project does not meet the minimum quality threshold for this gate.', 'sustainable-catalyst-library' ),
                    array( 'status' => 409, 'evaluation' => $evaluation )
                );
            }
            if ( $evaluation['issue_counts']['critical_open'] > 0 || $evaluation['review_counts']['failed'] > 0 ) {
                return new WP_Error(
                    'quality_gate_critical',
                    __( 'Critical issues or failed reviews must be resolved before approval.', 'sustainable-catalyst-library' ),
                    array( 'status' => 409, 'evaluation' => $evaluation )
                );
            }
        }

        $previous = (string) get_post_meta( $project_id, self::META_PROJECT_GATE, true ) ?: 'draft';
        update_post_meta( $project_id, self::META_PROJECT_GATE, $gate );
        self::append_project_history(
            $project_id,
            array(
                'event'         => 'gate-transition',
                'previous_gate' => $previous,
                'gate'          => $gate,
                'score'         => $evaluation['score'],
                'note'          => sanitize_text_field( $note ),
                'message'       => sprintf(
                    __( 'Governance gate changed from %1$s to %2$s.', 'sustainable-catalyst-library' ),
                    self::gate_options()[ $previous ] ?? $previous,
                    self::gate_options()[ $gate ]
                ),
            )
        );
        delete_transient( self::TRANSIENT_DASHBOARD );

        return array(
            'project_id'    => $project_id,
            'previous_gate' => $previous,
            'gate'          => $gate,
            'gate_label'    => self::gate_options()[ $gate ],
            'evaluation'    => self::evaluate_project( $project_id, true ),
        );
    }

    public static function project_review_records( $project_id, $include_private = false ) {
        $ids = self::id_list( get_post_meta( $project_id, self::META_PROJECT_REVIEW_IDS, true ) );
        if ( ! $ids ) {
            $ids = get_posts(
                array(
                    'post_type'      => self::REVIEW_POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => self::MAX_LINKED_RECORDS,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_RECORD_PROJECT,
                    'meta_value'     => absint( $project_id ),
                )
            );
        }
        $records = array();
        foreach ( $ids as $id ) {
            $record = self::review_data( $id, $include_private );
            if ( $record ) {
                $records[] = $record;
            }
        }
        return $records;
    }

    public static function project_issue_records( $project_id, $include_private = false ) {
        $ids = self::id_list( get_post_meta( $project_id, self::META_PROJECT_ISSUE_IDS, true ) );
        if ( ! $ids ) {
            $ids = get_posts(
                array(
                    'post_type'      => self::ISSUE_POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => self::MAX_LINKED_RECORDS,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_RECORD_PROJECT,
                    'meta_value'     => absint( $project_id ),
                )
            );
        }
        $records = array();
        foreach ( $ids as $id ) {
            $record = self::issue_data( $id, $include_private );
            if ( $record ) {
                $records[] = $record;
            }
        }
        return $records;
    }

    public static function review_data( $review_id, $include_private = false ) {
        if ( self::REVIEW_POST_TYPE !== get_post_type( $review_id ) ) {
            return array();
        }
        $project_id = absint( get_post_meta( $review_id, self::META_RECORD_PROJECT, true ) );
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        $domain = (string) get_post_meta( $review_id, self::META_RECORD_DOMAIN, true ) ?: 'publication';
        $outcome = (string) get_post_meta( $review_id, self::META_RECORD_OUTCOME, true ) ?: 'pending';
        $data = array(
            'schema'        => self::REVIEW_SCHEMA,
            'review_id'     => absint( $review_id ),
            'project_id'    => $project_id,
            'title'         => get_the_title( $review_id ),
            'domain'        => $domain,
            'domain_label'  => self::review_domains()[ $domain ] ?? $domain,
            'outcome'       => $outcome,
            'outcome_label' => self::review_outcomes()[ $outcome ] ?? $outcome,
            'status'        => (string) get_post_meta( $review_id, self::META_RECORD_STATUS, true ) ?: 'active',
            'completed_at'  => (string) get_post_meta( $review_id, self::META_RECORD_COMPLETED, true ),
        );
        if ( $include_private ) {
            $data['findings'] = (string) get_post_meta( $review_id, self::META_RECORD_FINDINGS, true );
            $data['required_actions'] = (string) get_post_meta( $review_id, self::META_RECORD_ACTIONS, true );
            $data['reviewer_id'] = absint( get_post_meta( $review_id, self::META_RECORD_REVIEWER, true ) );
            $data['due_date'] = (string) get_post_meta( $review_id, self::META_RECORD_DUE, true );
            $data['history'] = self::history( $review_id, self::META_RECORD_HISTORY );
        }
        return $data;
    }

    public static function issue_data( $issue_id, $include_private = false ) {
        if ( self::ISSUE_POST_TYPE !== get_post_type( $issue_id ) ) {
            return array();
        }
        $project_id = absint( get_post_meta( $issue_id, self::META_RECORD_PROJECT, true ) );
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        $domain = (string) get_post_meta( $issue_id, self::META_RECORD_DOMAIN, true ) ?: 'integrity';
        $severity = (string) get_post_meta( $issue_id, self::META_RECORD_SEVERITY, true ) ?: 'medium';
        $status = (string) get_post_meta( $issue_id, self::META_RECORD_STATUS, true ) ?: 'open';
        $data = array(
            'schema'         => self::ISSUE_SCHEMA,
            'issue_id'       => absint( $issue_id ),
            'project_id'     => $project_id,
            'title'          => get_the_title( $issue_id ),
            'domain'         => $domain,
            'domain_label'   => self::review_domains()[ $domain ] ?? $domain,
            'severity'       => $severity,
            'severity_label' => self::severity_options()[ $severity ] ?? $severity,
            'status'         => $status,
            'status_label'   => self::issue_statuses()[ $status ] ?? $status,
            'exception'      => '1' === get_post_meta( $issue_id, self::META_RECORD_EXCEPTION, true ),
        );
        if ( $include_private ) {
            $data['description'] = (string) get_post_field( 'post_content', $issue_id );
            $data['required_actions'] = (string) get_post_meta( $issue_id, self::META_RECORD_ACTIONS, true );
            $data['due_date'] = (string) get_post_meta( $issue_id, self::META_RECORD_DUE, true );
            $data['exception_expiry'] = (string) get_post_meta( $issue_id, self::META_RECORD_EXCEPTION_EXPIRY, true );
            $data['exception_approver'] = absint( get_post_meta( $issue_id, self::META_RECORD_EXCEPTION_APPROVER, true ) );
            $data['history'] = self::history( $issue_id, self::META_RECORD_HISTORY );
        }
        return $data;
    }

    public static function project_transparency( $project_id, $include_private = false ) {
        $project_id = absint( $project_id );
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        if ( ! $include_private && '1' !== get_post_meta( $project_id, self::META_PROJECT_PUBLIC, true ) ) {
            return array();
        }
        $evaluation = get_post_meta( $project_id, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $project_id, $include_private );
        }
        if ( is_wp_error( $evaluation ) ) {
            return array();
        }

        $policies = array();
        foreach ( self::id_list( get_post_meta( $project_id, self::META_PROJECT_POLICIES, true ) ) as $policy_id ) {
            if ( self::POLICY_POST_TYPE !== get_post_type( $policy_id ) ) {
                continue;
            }
            if ( ! $include_private && '1' !== get_post_meta( $policy_id, self::META_POLICY_PUBLIC, true ) ) {
                continue;
            }
            $policies[] = array(
                'policy_id' => $policy_id,
                'title'     => get_the_title( $policy_id ),
                'domain'    => (string) get_post_meta( $policy_id, self::META_POLICY_DOMAIN, true ),
                'version'   => (string) get_post_meta( $policy_id, self::META_POLICY_VERSION, true ),
                'status'    => (string) get_post_meta( $policy_id, self::META_POLICY_STATUS, true ),
            );
        }

        $reviews = array_map(
            static function ( $review ) {
                return array_intersect_key(
                    $review,
                    array_flip( array( 'review_id', 'domain', 'domain_label', 'outcome', 'outcome_label', 'completed_at' ) )
                );
            },
            self::project_review_records( $project_id, $include_private )
        );
        $issues = array_map(
            static function ( $issue ) {
                return array_intersect_key(
                    $issue,
                    array_flip( array( 'issue_id', 'domain', 'domain_label', 'severity', 'severity_label', 'status', 'status_label', 'exception' ) )
                );
            },
            self::project_issue_records( $project_id, $include_private )
        );

        return array(
            'schema'           => self::TRANSPARENCY_SCHEMA,
            'project_id'       => $project_id,
            'title'            => get_the_title( $project_id ),
            'url'              => self::project_is_public( $project_id ) ? get_permalink( $project_id ) : '',
            'profile'          => $evaluation['profile'],
            'profile_label'    => $evaluation['profile_label'],
            'gate'             => $evaluation['gate'],
            'gate_label'       => $evaluation['gate_label'],
            'quality_score'    => $evaluation['score'],
            'readiness_status' => $evaluation['status'],
            'readiness_label'  => $evaluation['status_label'],
            'dimensions'       => array_map(
                static function ( $dimension ) {
                    return array(
                        'label'   => $dimension['label'],
                        'score'   => $dimension['score'],
                        'maximum' => $dimension['maximum'],
                        'status'  => $dimension['status'],
                    );
                },
                $evaluation['dimensions']
            ),
            'policies'         => $policies,
            'reviews'          => $reviews,
            'issues'           => $issues,
            'last_evaluated'   => $evaluation['evaluated_at'],
        );
    }

    private static function append_project_record_id( $project_id, $meta_key, $record_id ) {
        $ids = self::id_list( get_post_meta( $project_id, $meta_key, true ) );
        $ids[] = absint( $record_id );
        $ids = array_slice( array_values( array_unique( array_filter( $ids ) ) ), -self::MAX_LINKED_RECORDS );
        update_post_meta( $project_id, $meta_key, $ids );
    }

    private static function append_project_history( $project_id, $event ) {
        $history = self::history( $project_id, self::META_PROJECT_HISTORY );
        $history[] = array_merge(
            array(
                'event_id'   => wp_generate_uuid4(),
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            ),
            is_array( $event ) ? $event : array()
        );
        update_post_meta( $project_id, self::META_PROJECT_HISTORY, array_slice( $history, -self::MAX_HISTORY ) );
    }

    private static function history( $post_id, $meta_key ) {
        $history = get_post_meta( $post_id, $meta_key, true );
        return is_array( $history ) ? array_values( $history ) : array();
    }

    public function save_project_governance( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== $post->post_type
        ) {
            return;
        }

        $submitted = isset( $_POST['sc_library_quality_governance_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_quality_governance_nonce'] ) ),
                'sc_library_save_quality_governance_' . $post_id
            )
            && current_user_can( 'edit_post', $post_id );

        if ( ! $submitted ) {
            return;
        }

        self::$saving = true;
        $profile = self::allowed_value(
            wp_unslash( $_POST['sc_quality_governance_profile'] ?? 'standard' ),
            self::governance_profiles(),
            'standard'
        );
        $gate = self::allowed_value(
            wp_unslash( $_POST['sc_quality_gate'] ?? 'draft' ),
            self::gate_options(),
            'draft'
        );
        $policy_ids = self::id_list( wp_unslash( $_POST['sc_quality_policy_ids'] ?? array() ) );
        $policy_ids = array_values(
            array_filter(
                $policy_ids,
                static function ( $policy_id ) {
                    return self::POLICY_POST_TYPE === get_post_type( $policy_id );
                }
            )
        );

        update_post_meta( $post_id, self::META_PROJECT_PROFILE, $profile );
        update_post_meta( $post_id, self::META_PROJECT_GATE, $gate );
        $policy_ids
            ? update_post_meta( $post_id, self::META_PROJECT_POLICIES, array_slice( $policy_ids, 0, self::MAX_LINKED_RECORDS ) )
            : delete_post_meta( $post_id, self::META_PROJECT_POLICIES );
        update_post_meta( $post_id, self::META_PROJECT_PUBLIC, isset( $_POST['sc_quality_public_summary'] ) ? '1' : '0' );

        self::append_project_history(
            $post_id,
            array(
                'event'   => 'governance-settings-updated',
                'profile' => $profile,
                'gate'    => $gate,
                'message' => __( 'Governance settings updated.', 'sustainable-catalyst-library' ),
            )
        );
        self::evaluate_project( $post_id, true );
        self::$saving = false;
    }

    public function save_policy( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::POLICY_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_policy_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_policy_nonce'] ) ),
                'sc_library_save_policy_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;
        $domain = self::allowed_value(
            wp_unslash( $_POST['sc_policy_domain'] ?? 'methodology' ),
            self::policy_domains(),
            'methodology'
        );
        $status = self::allowed_value(
            wp_unslash( $_POST['sc_policy_status'] ?? 'draft' ),
            array(
                'draft'      => 'Draft',
                'active'     => 'Active',
                'deprecated' => 'Deprecated',
                'archived'   => 'Archived',
            ),
            'draft'
        );
        $gate = self::allowed_value(
            wp_unslash( $_POST['sc_policy_required_gate'] ?? 'quality-review' ),
            self::gate_options(),
            'quality-review'
        );
        update_post_meta( $post_id, self::META_POLICY_DOMAIN, $domain );
        update_post_meta( $post_id, self::META_POLICY_VERSION, sanitize_text_field( wp_unslash( $_POST['sc_policy_version'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_POLICY_STATUS, $status );
        update_post_meta( $post_id, self::META_POLICY_GATE, $gate );
        update_post_meta( $post_id, self::META_POLICY_CONTROLS, sanitize_textarea_field( wp_unslash( $_POST['sc_policy_controls'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_POLICY_PUBLIC, isset( $_POST['sc_policy_public'] ) ? '1' : '0' );
        update_post_meta( $post_id, self::META_POLICY_EFFECTIVE, self::sanitize_date( wp_unslash( $_POST['sc_policy_effective_date'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_POLICY_REVIEW_DATE, self::sanitize_date( wp_unslash( $_POST['sc_policy_review_date'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_POLICY_OWNER, absint( wp_unslash( $_POST['sc_policy_owner'] ?? 0 ) ) );
        self::$saving = false;
        delete_transient( self::TRANSIENT_DASHBOARD );
    }

    public function render_project_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_quality_governance_' . $post->ID, 'sc_library_quality_governance_nonce' );
        $profile = (string) get_post_meta( $post->ID, self::META_PROJECT_PROFILE, true ) ?: 'standard';
        $gate = (string) get_post_meta( $post->ID, self::META_PROJECT_GATE, true ) ?: 'draft';
        $policy_ids = self::id_list( get_post_meta( $post->ID, self::META_PROJECT_POLICIES, true ) );
        $policies = get_posts(
            array(
                'post_type'      => self::POLICY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $evaluation = get_post_meta( $post->ID, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $post->ID, false );
        }
        $reviews = self::project_review_records( $post->ID, true );
        $issues = self::project_issue_records( $post->ID, true );
        ?>
        <div class="sc-quality-editor" data-sc-quality-project="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-quality-editor__controls">
                <label>
                    <strong><?php esc_html_e( 'Governance profile', 'sustainable-catalyst-library' ); ?></strong>
                    <select name="sc_quality_governance_profile">
                        <?php foreach ( self::governance_profiles() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $profile, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <strong><?php esc_html_e( 'Current gate', 'sustainable-catalyst-library' ); ?></strong>
                    <select name="sc_quality_gate">
                        <?php foreach ( self::gate_options() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $gate, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="sc-quality-editor__public">
                    <input type="checkbox" name="sc_quality_public_summary" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PROJECT_PUBLIC, true ) ); ?>>
                    <?php esc_html_e( 'Publish a research-transparency summary for this public project.', 'sustainable-catalyst-library' ); ?>
                </label>
            </div>

            <section>
                <h3><?php esc_html_e( 'Applicable governance policies', 'sustainable-catalyst-library' ); ?></h3>
                <div class="sc-quality-policy-grid">
                    <?php if ( $policies ) : ?>
                        <?php foreach ( $policies as $policy ) : ?>
                            <label>
                                <input type="checkbox" name="sc_quality_policy_ids[]" value="<?php echo esc_attr( $policy->ID ); ?>" <?php checked( in_array( $policy->ID, $policy_ids, true ) ); ?>>
                                <span><strong><?php echo esc_html( $policy->post_title ); ?></strong><small><?php echo esc_html( self::policy_domains()[ get_post_meta( $policy->ID, self::META_POLICY_DOMAIN, true ) ] ?? '' ); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No governance policies have been created yet.', 'sustainable-catalyst-library' ); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <?php echo self::render_evaluation_panel( $evaluation, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="sc-quality-actions">
                <button type="button" class="button button-primary" data-sc-quality-evaluate><?php esc_html_e( 'Evaluate Project', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-quality-review-open><?php esc_html_e( 'Create Review', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-quality-issue-open><?php esc_html_e( 'Create Issue', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-quality-gate-open><?php esc_html_e( 'Transition Gate', 'sustainable-catalyst-library' ); ?></button>
                <span data-sc-quality-status aria-live="polite"></span>
            </div>

            <?php echo self::render_review_issue_tables( $reviews, $issues ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo self::render_dialogs( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    public function render_project_readiness_box( $post ) {
        $evaluation = get_post_meta( $post->ID, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $post->ID, false );
        }
        if ( is_wp_error( $evaluation ) ) {
            echo '<p>' . esc_html( $evaluation->get_error_message() ) . '</p>';
            return;
        }
        ?>
        <div class="sc-quality-readiness">
            <div class="sc-quality-score status-<?php echo esc_attr( $evaluation['status'] ); ?>">
                <strong><?php echo esc_html( $evaluation['score'] ); ?></strong>
                <span>/100</span>
            </div>
            <p><strong><?php echo esc_html( $evaluation['status_label'] ); ?></strong></p>
            <p><?php echo esc_html( $evaluation['gate_label'] ); ?></p>
            <dl>
                <div><dt><?php esc_html_e( 'Open critical issues', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $evaluation['issue_counts']['critical_open'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Failed reviews', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $evaluation['review_counts']['failed'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Last evaluated', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $evaluation['evaluated_at'] ); ?></dd></div>
            </dl>
        </div>
        <?php
    }

    public function render_policy_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_policy_' . $post->ID, 'sc_library_policy_nonce' );
        $domain = (string) get_post_meta( $post->ID, self::META_POLICY_DOMAIN, true ) ?: 'methodology';
        $status = (string) get_post_meta( $post->ID, self::META_POLICY_STATUS, true ) ?: 'draft';
        $gate = (string) get_post_meta( $post->ID, self::META_POLICY_GATE, true ) ?: 'quality-review';
        ?>
        <div class="sc-policy-editor">
            <div class="sc-policy-editor__grid">
                <label><strong><?php esc_html_e( 'Policy domain', 'sustainable-catalyst-library' ); ?></strong><select name="sc_policy_domain"><?php foreach ( self::policy_domains() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $domain, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Policy version', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_policy_version" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_POLICY_VERSION, true ) ); ?>" placeholder="1.0"></label>
                <label><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_policy_status"><?php foreach ( array( 'draft' => 'Draft', 'active' => 'Active', 'deprecated' => 'Deprecated', 'archived' => 'Archived' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Required by gate', 'sustainable-catalyst-library' ); ?></strong><select name="sc_policy_required_gate"><?php foreach ( self::gate_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $gate, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Effective date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_policy_effective_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_POLICY_EFFECTIVE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Next review date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_policy_review_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_POLICY_REVIEW_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Policy owner user ID', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="0" name="sc_policy_owner" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_POLICY_OWNER, true ) ); ?>"></label>
                <label class="sc-policy-editor__public"><input type="checkbox" name="sc_policy_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_POLICY_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Include this policy in public transparency summaries.', 'sustainable-catalyst-library' ); ?></label>
            </div>
            <label><strong><?php esc_html_e( 'Policy controls and required evidence', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_policy_controls" rows="10" placeholder="<?php esc_attr_e( 'One control or requirement per line.', 'sustainable-catalyst-library' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_POLICY_CONTROLS, true ) ); ?></textarea></label>
        </div>
        <?php
    }

    private static function render_evaluation_panel( $evaluation, $private ) {
        if ( is_wp_error( $evaluation ) || ! is_array( $evaluation ) ) {
            return '';
        }
        ob_start();
        ?>
        <section class="sc-quality-evaluation status-<?php echo esc_attr( $evaluation['status'] ); ?>">
            <header>
                <div>
                    <p class="sc-quality-kicker"><?php esc_html_e( 'Research quality evaluation', 'sustainable-catalyst-library' ); ?></p>
                    <h3><?php echo esc_html( $evaluation['status_label'] ); ?></h3>
                </div>
                <div class="sc-quality-evaluation__score"><strong><?php echo esc_html( $evaluation['score'] ); ?></strong><span>/100</span></div>
            </header>
            <div class="sc-quality-dimensions">
                <?php foreach ( $evaluation['dimensions'] as $key => $dimension ) : ?>
                    <article class="status-<?php echo esc_attr( $dimension['status'] ); ?>">
                        <div><strong><?php echo esc_html( $dimension['label'] ); ?></strong><span><?php echo esc_html( $dimension['score'] . '/' . $dimension['maximum'] ); ?></span></div>
                        <progress max="<?php echo esc_attr( $dimension['maximum'] ); ?>" value="<?php echo esc_attr( $dimension['score'] ); ?>"><?php echo esc_html( $dimension['score'] ); ?></progress>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ( $private && ! empty( $evaluation['required_actions'] ) ) : ?>
                <div class="sc-quality-required-actions"><h4><?php esc_html_e( 'Required actions', 'sustainable-catalyst-library' ); ?></h4><ul><?php foreach ( $evaluation['required_actions'] as $action ) : ?><li><?php echo esc_html( $action ); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_review_issue_tables( $reviews, $issues ) {
        ob_start();
        ?>
        <div class="sc-quality-records">
            <section>
                <h3><?php esc_html_e( 'Quality reviews', 'sustainable-catalyst-library' ); ?></h3>
                <?php if ( $reviews ) : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Review', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Domain', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Outcome', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Completed', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody><?php foreach ( $reviews as $review ) : ?><tr><td><?php echo esc_html( $review['title'] ); ?></td><td><?php echo esc_html( $review['domain_label'] ); ?></td><td><?php echo esc_html( $review['outcome_label'] ); ?></td><td><?php echo esc_html( $review['completed_at'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p><?php esc_html_e( 'No quality reviews have been recorded.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
            </section>
            <section>
                <h3><?php esc_html_e( 'Issues and exceptions', 'sustainable-catalyst-library' ); ?></h3>
                <?php if ( $issues ) : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Issue', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Domain', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Severity', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody><?php foreach ( $issues as $issue ) : ?><tr><td><?php echo esc_html( $issue['title'] ); ?><?php if ( $issue['exception'] ) : ?> <span class="sc-quality-exception"><?php esc_html_e( 'Exception', 'sustainable-catalyst-library' ); ?></span><?php endif; ?></td><td><?php echo esc_html( $issue['domain_label'] ); ?></td><td><span class="sc-quality-severity severity-<?php echo esc_attr( $issue['severity'] ); ?>"><?php echo esc_html( $issue['severity_label'] ); ?></span></td><td><?php echo esc_html( $issue['status_label'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p><?php esc_html_e( 'No quality issues have been recorded.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_dialogs( $project_id ) {
        ob_start();
        ?>
        <dialog class="sc-quality-dialog" data-sc-quality-review-dialog>
            <form method="dialog" data-sc-quality-review-form>
                <h3><?php esc_html_e( 'Create quality review', 'sustainable-catalyst-library' ); ?></h3>
                <input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>">
                <label><?php esc_html_e( 'Review domain', 'sustainable-catalyst-library' ); ?><select name="domain"><?php foreach ( self::review_domains() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e( 'Outcome', 'sustainable-catalyst-library' ); ?><select name="outcome"><?php foreach ( self::review_outcomes() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e( 'Findings', 'sustainable-catalyst-library' ); ?><textarea name="findings" rows="5"></textarea></label>
                <label><?php esc_html_e( 'Required actions', 'sustainable-catalyst-library' ); ?><textarea name="actions" rows="4"></textarea></label>
                <label><?php esc_html_e( 'Due date', 'sustainable-catalyst-library' ); ?><input type="date" name="due_date"></label>
                <div><button type="submit" class="button button-primary"><?php esc_html_e( 'Create Review', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-quality-dialog-close><?php esc_html_e( 'Cancel', 'sustainable-catalyst-library' ); ?></button></div>
            </form>
        </dialog>
        <dialog class="sc-quality-dialog" data-sc-quality-issue-dialog>
            <form method="dialog" data-sc-quality-issue-form>
                <h3><?php esc_html_e( 'Create quality issue', 'sustainable-catalyst-library' ); ?></h3>
                <input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>">
                <label><?php esc_html_e( 'Title', 'sustainable-catalyst-library' ); ?><input type="text" name="title"></label>
                <label><?php esc_html_e( 'Domain', 'sustainable-catalyst-library' ); ?><select name="domain"><?php foreach ( self::review_domains() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e( 'Severity', 'sustainable-catalyst-library' ); ?><select name="severity"><?php foreach ( self::severity_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e( 'Description', 'sustainable-catalyst-library' ); ?><textarea name="description" rows="5"></textarea></label>
                <label><?php esc_html_e( 'Required actions', 'sustainable-catalyst-library' ); ?><textarea name="actions" rows="4"></textarea></label>
                <label><?php esc_html_e( 'Due date', 'sustainable-catalyst-library' ); ?><input type="date" name="due_date"></label>
                <label><input type="checkbox" name="exception" value="1"> <?php esc_html_e( 'Record as a governed exception', 'sustainable-catalyst-library' ); ?></label>
                <label><?php esc_html_e( 'Exception expiry', 'sustainable-catalyst-library' ); ?><input type="date" name="exception_expiry"></label>
                <div><button type="submit" class="button button-primary"><?php esc_html_e( 'Create Issue', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-quality-dialog-close><?php esc_html_e( 'Cancel', 'sustainable-catalyst-library' ); ?></button></div>
            </form>
        </dialog>
        <dialog class="sc-quality-dialog" data-sc-quality-gate-dialog>
            <form method="dialog" data-sc-quality-gate-form>
                <h3><?php esc_html_e( 'Transition governance gate', 'sustainable-catalyst-library' ); ?></h3>
                <input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>">
                <label><?php esc_html_e( 'New gate', 'sustainable-catalyst-library' ); ?><select name="gate"><?php foreach ( self::gate_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e( 'Decision note', 'sustainable-catalyst-library' ); ?><textarea name="note" rows="4"></textarea></label>
                <div><button type="submit" class="button button-primary"><?php esc_html_e( 'Transition Gate', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-quality-dialog-close><?php esc_html_e( 'Cancel', 'sustainable-catalyst-library' ); ?></button></div>
            </form>
        </dialog>
        <?php
        return ob_get_clean();
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $dashboard = self::dashboard_report( true );
        $migration = self::migration_state();
        ?>
        <div class="wrap sc-governance-center">
            <p class="sc-quality-kicker"><?php esc_html_e( 'Knowledge Library v3.5.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Research Quality and Governance Center', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Evaluate research readiness, apply governance policies, record reviews and exceptions, manage approval gates, and publish transparent quality summaries.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-governance-metrics">
                <article><strong><?php echo esc_html( $dashboard['project_count'] ); ?></strong><span><?php esc_html_e( 'Research Projects', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $dashboard['ready_count'] ); ?></strong><span><?php esc_html_e( 'Ready or conditionally ready', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $dashboard['blocked_count'] ); ?></strong><span><?php esc_html_e( 'Blocked projects', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $dashboard['open_issue_count'] ); ?></strong><span><?php esc_html_e( 'Open issues', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $dashboard['overdue_count'] ); ?></strong><span><?php esc_html_e( 'Overdue reviews or issues', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $dashboard['average_score'] ); ?></strong><span><?php esc_html_e( 'Average quality score', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-governance-migration">
                <div>
                    <h2><?php esc_html_e( 'Governance profile migration', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d projects processed', 'sustainable-catalyst-library' ), $migration['processed'], $migration['total'] ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-quality-migration-state><?php echo esc_html( ucfirst( $migration['status'] ) ); ?></span></p>
                </div>
                <button type="button" class="button button-primary" data-sc-quality-run-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-quality-migration-status aria-live="polite"></div>
            </section>

            <section>
                <div class="sc-governance-section-heading">
                    <div><h2><?php esc_html_e( 'Project readiness register', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Scores are decision support. They do not replace expert review or establish factual truth.', 'sustainable-catalyst-library' ); ?></p></div>
                    <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::POLICY_POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Research Policies', 'sustainable-catalyst-library' ); ?></a>
                </div>
                <?php if ( $dashboard['projects'] ) : ?>
                    <div class="sc-governance-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e( 'Project', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Profile', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Gate', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Score', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Readiness', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Issues', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Last evaluated', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ( $dashboard['projects'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( get_edit_post_link( $item['project_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                                        <td><?php echo esc_html( $item['profile_label'] ); ?></td>
                                        <td><?php echo esc_html( $item['gate_label'] ); ?></td>
                                        <td><strong><?php echo esc_html( $item['score'] ); ?></strong>/100</td>
                                        <td><span class="sc-quality-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( $item['status_label'] ); ?></span></td>
                                        <td><?php echo esc_html( $item['open_issues'] ); ?></td>
                                        <td><?php echo esc_html( $item['evaluated_at'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'No Research Projects were found.', 'sustainable-catalyst-library' ); ?></p>
                <?php endif; ?>
            </section>

            <section class="sc-governance-alerts">
                <h2><?php esc_html_e( 'Governance alerts', 'sustainable-catalyst-library' ); ?></h2>
                <div class="sc-governance-alert-grid">
                    <article><h3><?php esc_html_e( 'Critical and high issues', 'sustainable-catalyst-library' ); ?></h3><?php echo self::render_alert_list( $dashboard['high_risk_issues'], 'issue' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
                    <article><h3><?php esc_html_e( 'Overdue reviews and actions', 'sustainable-catalyst-library' ); ?></h3><?php echo self::render_alert_list( $dashboard['overdue_records'], 'record' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
                    <article><h3><?php esc_html_e( 'Policy review dates', 'sustainable-catalyst-library' ); ?></h3><?php echo self::render_alert_list( $dashboard['policy_alerts'], 'policy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
                </div>
            </section>
        </div>
        <?php
    }

    private static function render_alert_list( $items, $type ) {
        if ( ! $items ) {
            return '<p>' . esc_html__( 'No alerts.', 'sustainable-catalyst-library' ) . '</p>';
        }
        ob_start();
        echo '<ul>';
        foreach ( $items as $item ) {
            echo '<li>';
            if ( ! empty( $item['project_id'] ) ) {
                echo '<a href="' . esc_url( get_edit_post_link( $item['project_id'], 'raw' ) ) . '">' . esc_html( $item['project_title'] ?? get_the_title( $item['project_id'] ) ) . '</a>: ';
            }
            echo esc_html( $item['title'] ?? $item['message'] ?? '' );
            if ( ! empty( $item['due_date'] ) ) {
                echo ' <small>' . esc_html( $item['due_date'] ) . '</small>';
            }
            echo '</li>';
        }
        echo '</ul>';
        return ob_get_clean();
    }

    public static function dashboard_report( $include_private = false ) {
        if ( ! $include_private ) {
            $cached = get_transient( self::TRANSIENT_DASHBOARD );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $project_ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $projects = array();
        $scores = array();
        $ready = 0;
        $blocked = 0;
        $open_issue_count = 0;
        $overdue_count = 0;
        $high_risk = array();
        $overdue = array();
        $today = current_time( 'Y-m-d' );

        foreach ( $project_ids as $project_id ) {
            if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
                continue;
            }
            $evaluation = get_post_meta( $project_id, self::META_PROJECT_EVALUATION, true );
            if ( ! is_array( $evaluation ) ) {
                $evaluation = self::evaluate_project( $project_id, false );
            }
            if ( is_wp_error( $evaluation ) ) {
                continue;
            }
            $issues = self::project_issue_records( $project_id, $include_private );
            $reviews = self::project_review_records( $project_id, $include_private );
            $open_issues = count(
                array_filter(
                    $issues,
                    static function ( $issue ) {
                        return ! in_array( $issue['status'] ?? '', array( 'resolved', 'closed' ), true );
                    }
                )
            );
            $open_issue_count += $open_issues;
            if ( in_array( $evaluation['status'], array( 'ready', 'conditionally-ready' ), true ) ) {
                $ready++;
            }
            if ( 'blocked' === $evaluation['status'] ) {
                $blocked++;
            }
            $scores[] = $evaluation['score'];

            foreach ( $issues as $issue ) {
                if (
                    in_array( $issue['severity'] ?? '', array( 'high', 'critical' ), true )
                    && ! in_array( $issue['status'] ?? '', array( 'resolved', 'closed' ), true )
                ) {
                    $high_risk[] = array(
                        'project_id'    => $project_id,
                        'project_title' => get_the_title( $project_id ),
                        'title'         => $issue['title'],
                        'severity'      => $issue['severity'],
                        'due_date'      => $issue['due_date'] ?? '',
                    );
                }
                if ( ! empty( $issue['due_date'] ) && $issue['due_date'] < $today && ! in_array( $issue['status'], array( 'resolved', 'closed' ), true ) ) {
                    $overdue_count++;
                    $overdue[] = array(
                        'project_id'    => $project_id,
                        'project_title' => get_the_title( $project_id ),
                        'title'         => $issue['title'],
                        'due_date'      => $issue['due_date'],
                    );
                }
            }
            foreach ( $reviews as $review ) {
                if ( ! empty( $review['due_date'] ) && $review['due_date'] < $today && 'pending' === $review['outcome'] ) {
                    $overdue_count++;
                    $overdue[] = array(
                        'project_id'    => $project_id,
                        'project_title' => get_the_title( $project_id ),
                        'title'         => $review['title'],
                        'due_date'      => $review['due_date'],
                    );
                }
            }

            $projects[] = array(
                'project_id'    => $project_id,
                'title'         => get_the_title( $project_id ),
                'profile'       => $evaluation['profile'],
                'profile_label' => $evaluation['profile_label'],
                'gate'          => $evaluation['gate'],
                'gate_label'    => $evaluation['gate_label'],
                'score'         => $evaluation['score'],
                'status'        => $evaluation['status'],
                'status_label'  => $evaluation['status_label'],
                'open_issues'   => $open_issues,
                'evaluated_at'  => $evaluation['evaluated_at'],
            );
        }

        $policy_alerts = array();
        $policy_ids = get_posts(
            array(
                'post_type'      => self::POLICY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'fields'         => 'ids',
            )
        );
        foreach ( $policy_ids as $policy_id ) {
            $review_date = (string) get_post_meta( $policy_id, self::META_POLICY_REVIEW_DATE, true );
            if ( $review_date && $review_date <= gmdate( 'Y-m-d', strtotime( '+30 days' ) ) ) {
                $policy_alerts[] = array(
                    'title'    => get_the_title( $policy_id ),
                    'due_date' => $review_date,
                    'message'  => __( 'Policy review is due.', 'sustainable-catalyst-library' ),
                );
            }
        }

        $report = array(
            'schema'             => self::DASHBOARD_SCHEMA,
            'project_count'      => count( $projects ),
            'ready_count'        => $ready,
            'blocked_count'      => $blocked,
            'open_issue_count'   => $open_issue_count,
            'overdue_count'      => $overdue_count,
            'average_score'      => $scores ? (int) round( array_sum( $scores ) / count( $scores ) ) : 0,
            'projects'           => $projects,
            'high_risk_issues'   => array_slice( $high_risk, 0, 50 ),
            'overdue_records'    => array_slice( $overdue, 0, 50 ),
            'policy_alerts'      => array_slice( $policy_alerts, 0, 50 ),
            'generated_at'       => current_time( 'mysql' ),
            'include_private'    => (bool) $include_private,
        );

        if ( ! $include_private ) {
            set_transient( self::TRANSIENT_DASHBOARD, $report, 10 * MINUTE_IN_SECONDS );
        }
        return $report;
    }

    public function run_scheduled_migration() {
        $state = self::migration_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public static function run_migration_batch( $limit = self::MIGRATION_BATCH ) {
        global $wpdb;
        if ( get_transient( self::TRANSIENT_MIGRATION_LOCK ) ) {
            return new WP_Error( 'quality_migration_locked', __( 'Another governance migration batch is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );

        try {
            $post_type = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE;
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
                update_option( self::OPTION_MIGRATION, $state, false );
                delete_transient( self::TRANSIENT_MIGRATION_LOCK );
                return $state;
            }

            foreach ( $ids as $project_id ) {
                try {
                    if ( ! get_post_meta( $project_id, self::META_PROJECT_PROFILE, true ) ) {
                        update_post_meta( $project_id, self::META_PROJECT_PROFILE, 'standard' );
                    }
                    if ( ! get_post_meta( $project_id, self::META_PROJECT_GATE, true ) ) {
                        update_post_meta( $project_id, self::META_PROJECT_GATE, 'draft' );
                    }
                    self::evaluate_project( $project_id, true );
                    update_post_meta( $project_id, self::META_PROJECT_MIGRATED, self::VERSION );
                } catch ( Throwable $error ) {
                    $state['failures'][] = array(
                        'project_id' => $project_id,
                        'message'    => sanitize_text_field( $error->getMessage() ),
                        'time'       => current_time( 'mysql' ),
                    );
                    $state['failures'] = array_slice( $state['failures'], -100 );
                }
                $state['cursor'] = $project_id;
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
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error( 'quality_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
        }

        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    public function run_stale_review_scan() {
        delete_transient( self::TRANSIENT_DASHBOARD );
        self::dashboard_report( true );
    }

    public function ajax_evaluate_project() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot evaluate this project.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::evaluate_project( $project_id, true );
        $this->send_ajax_result( $result, 'evaluation' );
    }

    public function ajax_create_review() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $result = self::create_review(
            $project_id,
            array(
                'domain'   => wp_unslash( $_POST['domain'] ?? '' ),
                'outcome'  => wp_unslash( $_POST['outcome'] ?? '' ),
                'findings' => wp_unslash( $_POST['findings'] ?? '' ),
                'actions'  => wp_unslash( $_POST['actions'] ?? '' ),
                'due_date' => wp_unslash( $_POST['due_date'] ?? '' ),
            )
        );
        $this->send_ajax_result( $result, 'review' );
    }

    public function ajax_create_issue() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $result = self::create_issue(
            $project_id,
            array(
                'title'            => wp_unslash( $_POST['title'] ?? '' ),
                'domain'           => wp_unslash( $_POST['domain'] ?? '' ),
                'severity'         => wp_unslash( $_POST['severity'] ?? '' ),
                'description'      => wp_unslash( $_POST['description'] ?? '' ),
                'actions'          => wp_unslash( $_POST['actions'] ?? '' ),
                'due_date'         => wp_unslash( $_POST['due_date'] ?? '' ),
                'exception'        => wp_unslash( $_POST['exception'] ?? false ),
                'exception_expiry' => wp_unslash( $_POST['exception_expiry'] ?? '' ),
            )
        );
        $this->send_ajax_result( $result, 'issue' );
    }

    public function ajax_transition_gate() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::transition_gate(
            absint( wp_unslash( $_POST['project_id'] ?? 0 ) ),
            wp_unslash( $_POST['gate'] ?? '' ),
            wp_unslash( $_POST['note'] ?? '' )
        );
        $this->send_ajax_result( $result, 'transition' );
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $result = self::run_migration_batch( self::MIGRATION_BATCH );
        $this->send_ajax_result( $result, 'migration' );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_quality_governance_v350', 'nonce' );
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
            '/projects/(?P<id>\d+)/quality',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_project_quality' ),
                    'permission_callback' => array( $this, 'rest_can_read_project' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_evaluate_project' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/governance',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_project_governance' ),
                    'permission_callback' => array( $this, 'rest_can_read_project' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_project_governance' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/reviews',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_create_review' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/issues',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_create_issue' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/gate',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_transition_gate' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/transparency',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_transparency' ),
                'permission_callback' => array( $this, 'rest_can_read_transparency' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/governance/dashboard',
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
            '/governance/migration',
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

    public function rest_project_quality( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $evaluation = get_post_meta( $project_id, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $project_id, current_user_can( 'edit_post', $project_id ) );
        }
        return is_wp_error( $evaluation ) ? $evaluation : rest_ensure_response( $evaluation );
    }

    public function rest_evaluate_project( WP_REST_Request $request ) {
        $result = self::evaluate_project( absint( $request['id'] ), true );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_project_governance( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response(
            array(
                'project_id' => $project_id,
                'profile'    => (string) get_post_meta( $project_id, self::META_PROJECT_PROFILE, true ) ?: 'standard',
                'gate'       => (string) get_post_meta( $project_id, self::META_PROJECT_GATE, true ) ?: 'draft',
                'policies'   => self::id_list( get_post_meta( $project_id, self::META_PROJECT_POLICIES, true ) ),
                'reviews'    => self::project_review_records( $project_id, true ),
                'issues'     => self::project_issue_records( $project_id, true ),
                'history'    => self::history( $project_id, self::META_PROJECT_HISTORY ),
            )
        );
    }

    public function rest_update_project_governance( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        if ( array_key_exists( 'profile', $payload ) ) {
            update_post_meta(
                $project_id,
                self::META_PROJECT_PROFILE,
                self::allowed_value( $payload['profile'], self::governance_profiles(), 'standard' )
            );
        }
        if ( array_key_exists( 'policies', $payload ) ) {
            $policy_ids = array_values(
                array_filter(
                    self::id_list( $payload['policies'] ),
                    static function ( $policy_id ) {
                        return self::POLICY_POST_TYPE === get_post_type( $policy_id );
                    }
                )
            );
            $policy_ids
                ? update_post_meta( $project_id, self::META_PROJECT_POLICIES, array_slice( $policy_ids, 0, self::MAX_LINKED_RECORDS ) )
                : delete_post_meta( $project_id, self::META_PROJECT_POLICIES );
        }
        if ( array_key_exists( 'public_summary', $payload ) ) {
            update_post_meta(
                $project_id,
                self::META_PROJECT_PUBLIC,
                rest_sanitize_boolean( $payload['public_summary'] ) ? '1' : '0'
            );
        }

        self::append_project_history(
            $project_id,
            array(
                'event'   => 'governance-api-update',
                'message' => __( 'Governance settings updated through the REST API.', 'sustainable-catalyst-library' ),
            )
        );

        return rest_ensure_response(
            array(
                'project_id' => $project_id,
                'evaluation' => self::evaluate_project( $project_id, true ),
            )
        );
    }

    public function rest_create_review( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::create_review( absint( $request['id'] ), $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_create_issue( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::create_issue( absint( $request['id'] ), $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_transition_gate( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::transition_gate(
            absint( $request['id'] ),
            $payload['gate'] ?? '',
            $payload['note'] ?? ''
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_project_transparency( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_post', $project_id );
        $data = self::project_transparency( $project_id, $include_private );
        if ( ! $data ) {
            return new WP_Error( 'transparency_unavailable', __( 'No public transparency summary is available.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( $data );
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

    public function rest_can_read_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id ) || self::project_is_public( $project_id );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && current_user_can( 'edit_post', $project_id );
    }

    public function rest_can_read_transparency( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id )
            || ( self::project_is_public( $project_id ) && '1' === get_post_meta( $project_id, self::META_PROJECT_PUBLIC, true ) );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = $request->get_route();
        if ( false === strpos( $route, '/sc-library/v1/' ) ) {
            return $response;
        }
        $private = current_user_can( 'edit_posts' )
            || false !== strpos( $route, '/governance/' )
            || false !== strpos( $route, '/quality' )
            || false !== strpos( $route, '/reviews' )
            || false !== strpos( $route, '/issues' )
            || false !== strpos( $route, '/gate' );
        if ( $private ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function filter_handoff_bundle( $bundle, $target_product, $handoff_type, $project_id ) {
        if ( ! is_array( $bundle ) ) {
            return $bundle;
        }
        $evaluation = get_post_meta( $project_id, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $project_id, false );
        }
        if ( is_wp_error( $evaluation ) ) {
            return $bundle;
        }
        $bundle['quality_governance'] = array(
            'schema'           => self::QUALITY_SCHEMA,
            'profile'          => $evaluation['profile'],
            'gate'             => $evaluation['gate'],
            'score'            => $evaluation['score'],
            'readiness_status' => $evaluation['status'],
            'critical_issues'  => $evaluation['issue_counts']['critical_open'],
            'failed_reviews'   => $evaluation['review_counts']['failed'],
            'evaluated_at'     => $evaluation['evaluated_at'],
        );
        return $bundle;
    }

    public function shortcode_quality( $atts ) {
        $atts = shortcode_atts(
            array(
                'project'         => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_research_quality'
        );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $project_id );
        if ( $include_private ) {
            nocache_headers();
        }
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return '';
        }
        $evaluation = get_post_meta( $project_id, self::META_PROJECT_EVALUATION, true );
        if ( ! is_array( $evaluation ) ) {
            $evaluation = self::evaluate_project( $project_id, $include_private );
        }
        if ( is_wp_error( $evaluation ) ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-quality-governance' );
        return self::render_evaluation_panel( $evaluation, $include_private );
    }

    public function shortcode_governance( $atts ) {
        $atts = shortcode_atts(
            array(
                'project'         => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_research_governance'
        );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $project_id );
        if ( $include_private ) {
            nocache_headers();
        }
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return '';
        }
        $reviews = self::project_review_records( $project_id, $include_private );
        $issues = self::project_issue_records( $project_id, $include_private );
        wp_enqueue_style( 'sc-library-quality-governance' );
        ob_start();
        ?>
        <section class="sc-public-governance">
            <header><p class="sc-quality-kicker"><?php esc_html_e( 'Research governance', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( get_the_title( $project_id ) ); ?></h2></header>
            <?php echo self::render_review_issue_tables( $reviews, $issues ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_transparency( $atts ) {
        $atts = shortcode_atts(
            array(
                'project'         => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_research_transparency'
        );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $project_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $data = self::project_transparency( $project_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-quality-governance' );
        return self::render_transparency_summary( $data );
    }

    public function shortcode_dashboard( $atts ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return '';
        }
        nocache_headers();
        $report = self::dashboard_report( true );
        wp_enqueue_style( 'sc-library-quality-governance' );
        ob_start();
        ?>
        <section class="sc-public-governance-dashboard">
            <header><p class="sc-quality-kicker"><?php esc_html_e( 'Research governance dashboard', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Quality and readiness overview', 'sustainable-catalyst-library' ); ?></h2></header>
            <div class="sc-governance-metrics">
                <article><strong><?php echo esc_html( $report['project_count'] ); ?></strong><span><?php esc_html_e( 'Projects', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['ready_count'] ); ?></strong><span><?php esc_html_e( 'Ready', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['blocked_count'] ); ?></strong><span><?php esc_html_e( 'Blocked', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['average_score'] ); ?></strong><span><?php esc_html_e( 'Average score', 'sustainable-catalyst-library' ); ?></span></article>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_transparency_summary( $data ) {
        ob_start();
        ?>
        <section class="sc-research-transparency status-<?php echo esc_attr( $data['readiness_status'] ); ?>">
            <header>
                <div><p class="sc-quality-kicker"><?php esc_html_e( 'Research transparency summary', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $data['title'] ); ?></h2></div>
                <div class="sc-quality-evaluation__score"><strong><?php echo esc_html( $data['quality_score'] ); ?></strong><span>/100</span></div>
            </header>
            <div class="sc-transparency-status">
                <span><?php echo esc_html( $data['profile_label'] ); ?></span>
                <span><?php echo esc_html( $data['gate_label'] ); ?></span>
                <span><?php echo esc_html( $data['readiness_label'] ); ?></span>
            </div>
            <div class="sc-quality-dimensions">
                <?php foreach ( $data['dimensions'] as $dimension ) : ?>
                    <article class="status-<?php echo esc_attr( $dimension['status'] ); ?>"><div><strong><?php echo esc_html( $dimension['label'] ); ?></strong><span><?php echo esc_html( $dimension['score'] . '/' . $dimension['maximum'] ); ?></span></div><progress max="<?php echo esc_attr( $dimension['maximum'] ); ?>" value="<?php echo esc_attr( $dimension['score'] ); ?>"></progress></article>
                <?php endforeach; ?>
            </div>
            <div class="sc-transparency-grid">
                <article><h3><?php esc_html_e( 'Applicable policies', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['policies'] as $policy ) : ?><li><?php echo esc_html( $policy['title'] . ( $policy['version'] ? ' v' . $policy['version'] : '' ) ); ?></li><?php endforeach; ?></ul></article>
                <article><h3><?php esc_html_e( 'Completed and pending reviews', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['reviews'] as $review ) : ?><li><?php echo esc_html( $review['domain_label'] . ': ' . $review['outcome_label'] ); ?></li><?php endforeach; ?></ul></article>
                <article><h3><?php esc_html_e( 'Recorded issues', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['issues'] as $issue ) : ?><li><?php echo esc_html( $issue['domain_label'] . ': ' . $issue['severity_label'] . ' — ' . $issue['status_label'] ); ?></li><?php endforeach; ?></ul></article>
            </div>
            <footer><p><?php esc_html_e( 'This summary describes process readiness and recorded governance controls. It does not certify that every conclusion is true or that all risks have been eliminated.', 'sustainable-catalyst-library' ); ?></p><small><?php echo esc_html( $data['last_evaluated'] ); ?></small></footer>
        </section>
        <?php
        return ob_get_clean();
    }

    public function cleanup_deleted_record( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( self::REVIEW_POST_TYPE === $post_type || self::ISSUE_POST_TYPE === $post_type ) {
            $project_id = absint( get_post_meta( $post_id, self::META_RECORD_PROJECT, true ) );
            if ( $project_id ) {
                foreach (
                    array(
                        self::META_PROJECT_REVIEW_IDS,
                        self::META_PROJECT_ISSUE_IDS,
                        self::META_PROJECT_EXCEPTION_IDS,
                    ) as $meta_key
                ) {
                    $ids = array_values(
                        array_filter(
                            self::id_list( get_post_meta( $project_id, $meta_key, true ) ),
                            static function ( $id ) use ( $post_id ) {
                                return absint( $id ) !== absint( $post_id );
                            }
                        )
                    );
                    $ids ? update_post_meta( $project_id, $meta_key, $ids ) : delete_post_meta( $project_id, $meta_key );
                }
                delete_transient( self::TRANSIENT_DASHBOARD );
            }
        } elseif ( self::POLICY_POST_TYPE === $post_type ) {
            $project_ids = get_posts(
                array(
                    'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                    'posts_per_page' => 500,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_PROJECT_POLICIES,
                    'meta_value'     => 'i:' . absint( $post_id ) . ';',
                    'meta_compare'   => 'LIKE',
                )
            );
            foreach ( $project_ids as $project_id ) {
                $ids = array_values(
                    array_filter(
                        self::id_list( get_post_meta( $project_id, self::META_PROJECT_POLICIES, true ) ),
                        static function ( $id ) use ( $post_id ) {
                            return absint( $id ) !== absint( $post_id );
                        }
                    )
                );
                $ids ? update_post_meta( $project_id, self::META_PROJECT_POLICIES, $ids ) : delete_post_meta( $project_id, self::META_PROJECT_POLICIES );
            }
            delete_transient( self::TRANSIENT_DASHBOARD );
        }
    }

    private static function project_is_public( $project_id ) {
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) || 'publish' !== get_post_status( $project_id ) ) {
            return false;
        }
        $visibility = get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
        return in_array( $visibility, array( '', 'public' ), true );
    }

    private static function resolve_project_id( $value ) {
        if ( is_numeric( $value ) && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        if ( '' === trim( (string) $value ) && is_singular( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE ) ) {
            return get_queried_object_id();
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE );
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

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        WP_CLI::add_command(
            'sc-library quality evaluate',
            static function ( $args ) {
                $project_id = absint( $args[0] ?? 0 );
                $result = self::evaluate_project( $project_id, true );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );

        WP_CLI::add_command(
            'sc-library quality review',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $args[0] ?? 0 );
                $result = self::create_review(
                    $project_id,
                    array(
                        'domain'   => $assoc_args['domain'] ?? 'publication',
                        'outcome'  => $assoc_args['outcome'] ?? 'pending',
                        'findings' => $assoc_args['findings'] ?? '',
                        'actions'  => $assoc_args['actions'] ?? '',
                        'due_date' => $assoc_args['due-date'] ?? '',
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( 'Created quality review ' . absint( $result['review_id'] ?? 0 ) . '.' );
            }
        );

        WP_CLI::add_command(
            'sc-library quality issue',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $args[0] ?? 0 );
                $result = self::create_issue(
                    $project_id,
                    array(
                        'title'       => $assoc_args['title'] ?? '',
                        'domain'      => $assoc_args['domain'] ?? 'integrity',
                        'severity'    => $assoc_args['severity'] ?? 'medium',
                        'description' => $assoc_args['description'] ?? '',
                        'actions'     => $assoc_args['actions'] ?? '',
                        'due_date'    => $assoc_args['due-date'] ?? '',
                        'exception'   => isset( $assoc_args['exception'] ),
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( 'Created quality issue ' . absint( $result['issue_id'] ?? 0 ) . '.' );
            }
        );

        WP_CLI::add_command(
            'sc-library quality gate',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $args[0] ?? 0 );
                $gate = sanitize_key( $args[1] ?? '' );
                $result = self::transition_gate( $project_id, $gate, $assoc_args['note'] ?? '' );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( 'Project moved to ' . sanitize_text_field( $result['gate_label'] ?? $gate ) . '.' );
            }
        );

        WP_CLI::add_command(
            'sc-library quality dashboard',
            static function () {
                WP_CLI::log( wp_json_encode( self::dashboard_report( true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );

        WP_CLI::add_command(
            'sc-library quality migrate',
            static function ( $args, $assoc_args ) {
                $limit = absint( $assoc_args['limit'] ?? self::MIGRATION_BATCH );
                $result = self::run_migration_batch( $limit );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
    }
}
