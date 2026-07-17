<?php
/**
 * Connected Institutional Knowledge and Research Platform.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SC_Library_Connected_Institutional_Platform {
	public const VERSION = '4.0.0';
	public const API_NAMESPACE = 'sc-library/v1';

	public const PLATFORM_SCHEMA = 'sc-library-connected-institutional-platform/1.0';
	public const REGISTRY_SCHEMA = 'sc-library-institutional-record-registry/1.0';
	public const RECORD_SCHEMA = 'sc-library-institutional-record/1.0';
	public const SEARCH_SCHEMA = 'sc-library-institutional-search/1.0';
	public const GRAPH_SCHEMA = 'sc-library-institutional-knowledge-graph/1.0';
	public const WORKSPACE_SCHEMA = 'sc-library-institutional-workspace/1.0';
	public const HEALTH_SCHEMA = 'sc-library-institutional-health/1.0';
	public const PERMISSION_SCHEMA = 'sc-library-institutional-permissions/1.0';
	public const HANDOFF_SCHEMA = 'sc-platform-handoff/institutional-research/1.0';
	public const PORTAL_SCHEMA = 'sc-library-institutional-research-portal/1.0';
	public const MIGRATION_SCHEMA = 'sc-library-institutional-migration/1.0';

	public const INSTITUTION_POST_TYPE = 'sc_institution';
	public const UNIT_POST_TYPE = 'sc_research_unit';

	public const OPTION_CAPABILITY_VERSION = 'sc_library_v400_capability_version';
	public const OPTION_MIGRATION = 'sc_library_v400_institutional_migration';
	public const OPTION_ACTIVITY = 'sc_library_v400_institutional_activity';
	public const OPTION_PLATFORM = 'sc_library_v400_platform_settings';

	public const TRANSIENT_DASHBOARD = 'sc_library_v400_dashboard';
	public const TRANSIENT_HEALTH = 'sc_library_v400_health';
	public const TRANSIENT_REGISTRY = 'sc_library_v400_registry';
	public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v400_migration_lock';

	public const CRON_MIGRATION = 'sc_library_v400_migration_tick';
	public const CRON_HEALTH = 'sc_library_v400_health_tick';

	public const META_UUID = '_sc_institutional_uuid';
	public const META_INSTITUTION_ID = '_sc_institution_id';
	public const META_UNIT_IDS = '_sc_research_unit_ids';
	public const META_VISIBILITY = '_sc_record_visibility';
	public const META_STEWARD_ID = '_sc_record_steward_id';
	public const META_GOVERNANCE_STATE = '_sc_record_governance_state';
	public const META_RECORD_URN = '_sc_record_urn';
	public const META_REGISTRY_HASH = '_sc_record_registry_hash';
	public const META_REGISTERED_AT = '_sc_record_registered_at';
	public const META_UPDATED_AT = '_sc_record_registry_updated_at';

	public const META_INSTITUTION_STATUS = '_sc_institution_status';
	public const META_INSTITUTION_SHORT_NAME = '_sc_institution_short_name';
	public const META_INSTITUTION_DESCRIPTION = '_sc_institution_description';
	public const META_INSTITUTION_IDENTIFIERS = '_sc_institution_identifiers';
	public const META_INSTITUTION_CONTACT = '_sc_institution_contact';
	public const META_INSTITUTION_PUBLIC = '_sc_institution_public';

	public const META_UNIT_STATUS = '_sc_unit_status';
	public const META_UNIT_TYPE = '_sc_unit_type';
	public const META_UNIT_DESCRIPTION = '_sc_unit_description';
	public const META_UNIT_LEAD_IDS = '_sc_unit_lead_ids';
	public const META_UNIT_SCOPE = '_sc_unit_scope';
	public const META_UNIT_PUBLIC = '_sc_unit_public';

	public const MIGRATION_BATCH = 25;
	public const LOCK_SECONDS = 180;
	public const MAX_SEARCH_LIMIT = 100;
	public const DEFAULT_SEARCH_LIMIT = 25;
	public const MAX_GRAPH_NODES = 250;
	public const MAX_ACTIVITY = 500;

	private static $saving = false;

	public function __construct() {
		add_action( 'init', array( $this, 'register_record_types' ), 400 );
		add_action( 'init', array( $this, 'schedule_operations' ), 999 );
		add_action( 'admin_init', array( $this, 'sync_capabilities' ), 40 );
		add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
		add_action( self::CRON_HEALTH, array( $this, 'refresh_health_cache' ) );

		add_action( 'admin_menu', array( $this, 'register_workspace' ), 400 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 400 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 400 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

		add_action( 'save_post', array( $this, 'save_institutional_context' ), 400, 3 );
		add_action( 'save_post_' . self::INSTITUTION_POST_TYPE, array( $this, 'save_institution' ), 400, 3 );
		add_action( 'save_post_' . self::UNIT_POST_TYPE, array( $this, 'save_unit' ), 400, 3 );
		add_action( 'before_delete_post', array( $this, 'cleanup_deleted_record' ) );

		add_action( 'wp_ajax_sc_library_v400_run_migration', array( $this, 'ajax_run_migration' ) );
		add_action( 'wp_ajax_sc_library_v400_refresh_health', array( $this, 'ajax_refresh_health' ) );
		add_action( 'wp_ajax_sc_library_v400_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_sc_library_v400_create_handoff', array( $this, 'ajax_create_handoff' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'harden_rest_responses' ), 140, 3 );

		add_filter( 'sc_library_research_librarian_project_context', array( $this, 'filter_research_librarian_context' ), 100, 3 );
		add_filter( 'sc_library_cross_product_handoff_sections', array( $this, 'filter_handoff_sections' ), 100, 3 );
		add_filter( 'sc_library_public_api_record', array( $this, 'filter_public_api_record' ), 100, 4 );

		add_shortcode( 'sc_institutional_research_portal', array( $this, 'shortcode_portal' ) );
		add_shortcode( 'sc_institutional_search', array( $this, 'shortcode_search' ) );
		add_shortcode( 'sc_institutional_platform_status', array( $this, 'shortcode_status' ) );
		add_shortcode( 'sc_institutional_record', array( $this, 'shortcode_record' ) );

		self::register_cli_commands();
	}

	public static function capability_map() {
		return array(
			'sc_library_read_institutional'         => __( 'Read institutional records', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional'       => __( 'Manage institutions and research units', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional_records'=> __( 'Manage institutional record context', 'sustainable-catalyst-library' ),
			'sc_library_publish_institutional'      => __( 'Publish institutional research records', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional_permissions' => __( 'Manage institutional capabilities', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional_handoffs' => __( 'Create institutional cross-product handoffs', 'sustainable-catalyst-library' ),
			'sc_library_view_institutional_health'  => __( 'View institutional platform health', 'sustainable-catalyst-library' ),
			'sc_library_export_institutional'       => __( 'Export institutional research records', 'sustainable-catalyst-library' ),
		);
	}

	public static function visibility_options() {
		return array(
			'public'       => __( 'Public', 'sustainable-catalyst-library' ),
			'institution'  => __( 'Institutional', 'sustainable-catalyst-library' ),
			'unit'         => __( 'Research unit', 'sustainable-catalyst-library' ),
			'restricted'   => __( 'Restricted', 'sustainable-catalyst-library' ),
		);
	}

	public static function governance_states() {
		return array(
			'unclassified' => __( 'Unclassified', 'sustainable-catalyst-library' ),
			'draft'        => __( 'Draft', 'sustainable-catalyst-library' ),
			'managed'      => __( 'Managed', 'sustainable-catalyst-library' ),
			'review'       => __( 'Under review', 'sustainable-catalyst-library' ),
			'approved'     => __( 'Approved', 'sustainable-catalyst-library' ),
			'published'    => __( 'Published', 'sustainable-catalyst-library' ),
			'archived'     => __( 'Archived', 'sustainable-catalyst-library' ),
			'restricted'   => __( 'Restricted', 'sustainable-catalyst-library' ),
		);
	}

	public static function institution_statuses() {
		return array(
			'active'     => __( 'Active', 'sustainable-catalyst-library' ),
			'inactive'   => __( 'Inactive', 'sustainable-catalyst-library' ),
			'archived'   => __( 'Archived', 'sustainable-catalyst-library' ),
		);
	}

	public static function unit_types() {
		return array(
			'research-center' => __( 'Research center', 'sustainable-catalyst-library' ),
			'program'         => __( 'Program', 'sustainable-catalyst-library' ),
			'lab'             => __( 'Laboratory', 'sustainable-catalyst-library' ),
			'library'         => __( 'Library or archive', 'sustainable-catalyst-library' ),
			'advisory'        => __( 'Advisory practice', 'sustainable-catalyst-library' ),
			'publication'     => __( 'Publication unit', 'sustainable-catalyst-library' ),
			'platform'        => __( 'Platform team', 'sustainable-catalyst-library' ),
			'other'           => __( 'Other', 'sustainable-catalyst-library' ),
		);
	}

	public static function record_registry() {
		$cached = get_transient( self::TRANSIENT_REGISTRY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$registry = array(
			'document' => array(
				'label' => __( 'Knowledge documents', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Foundation_Pages' ) ? SC_Library_Foundation_Pages::POST_TYPE : 'sc_foundation_doc',
				'public' => true,
			),
			'source' => array(
				'label' => __( 'Research sources', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE : 'sc_research_source',
				'public' => false,
			),
			'claim' => array(
				'label' => __( 'Research claims', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Evidence_Claim_Linking' ) ? SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE : 'sc_research_claim',
				'public' => false,
			),
			'evidence-note' => array(
				'label' => __( 'Evidence notes', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Evidence_Claim_Linking' ) ? SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE : 'sc_evidence_note',
				'public' => false,
			),
			'project' => array(
				'label' => __( 'Research projects', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE : 'sc_research_project',
				'public' => false,
			),
			'concept' => array(
				'label' => __( 'Concepts', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::CONCEPT_POST_TYPE : 'sc_library_concept',
				'public' => true,
			),
			'entity' => array(
				'label' => __( 'Named entities', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::ENTITY_POST_TYPE : 'sc_named_entity',
				'public' => true,
			),
			'vocabulary' => array(
				'label' => __( 'Controlled vocabularies', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::VOCABULARY_POST_TYPE : 'sc_control_vocab',
				'public' => true,
			),
			'relationship' => array(
				'label' => __( 'Knowledge relationships', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::RELATION_POST_TYPE : 'sc_knowledge_rel',
				'public' => false,
			),
			'pathway' => array(
				'label' => __( 'Knowledge pathways', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : 'sc_knowledge_path',
				'public' => true,
			),
			'collection' => array(
				'label' => __( 'Institutional collections', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Institutional_Collections_Archives' ) ? SC_Library_Institutional_Collections_Archives::COLLECTION_POST_TYPE : 'sc_inst_collection',
				'public' => true,
			),
			'archive-component' => array(
				'label' => __( 'Archive components', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Institutional_Collections_Archives' ) ? SC_Library_Institutional_Collections_Archives::COMPONENT_POST_TYPE : 'sc_archive_component',
				'public' => false,
			),
			'accession' => array(
				'label' => __( 'Archive accessions', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Institutional_Collections_Archives' ) ? SC_Library_Institutional_Collections_Archives::ACCESSION_POST_TYPE : 'sc_archive_accession',
				'public' => false,
			),
			'review' => array(
				'label' => __( 'Research reviews', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Collaborative_Review_Publishing' ) ? SC_Library_Collaborative_Review_Publishing::REVIEW_POST_TYPE : 'sc_review_cycle',
				'public' => false,
			),
			'publication' => array(
				'label' => __( 'Research publications', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Collaborative_Review_Publishing' ) ? SC_Library_Collaborative_Review_Publishing::PACKAGE_POST_TYPE : 'sc_pub_package',
				'public' => true,
			),
			'quality-policy' => array(
				'label' => __( 'Research policies', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Research_Quality_Governance' ) ? SC_Library_Research_Quality_Governance::POLICY_POST_TYPE : 'sc_research_policy',
				'public' => false,
			),
			'quality-issue' => array(
				'label' => __( 'Quality issues', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Research_Quality_Governance' ) ? SC_Library_Research_Quality_Governance::ISSUE_POST_TYPE : 'sc_quality_issue',
				'public' => false,
			),
			'workspace-handoff' => array(
				'label' => __( 'Workspace handoffs', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Cross_Product_Research_Handoffs' ) ? SC_Library_Cross_Product_Research_Handoffs::HANDOFF_POST_TYPE : 'sc_workspace_handoff',
				'public' => false,
			),
			'api-export' => array(
				'label' => __( 'API exports', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Public_API_Export_Federation' ) ? SC_Library_Public_API_Export_Federation::EXPORT_POST_TYPE : 'sc_export_job',
				'public' => false,
			),
			'federation-peer' => array(
				'label' => __( 'Federation peers', 'sustainable-catalyst-library' ),
				'post_type' => class_exists( 'SC_Library_Public_API_Export_Federation' ) ? SC_Library_Public_API_Export_Federation::PEER_POST_TYPE : 'sc_federation_peer',
				'public' => false,
			),
			'institution' => array(
				'label' => __( 'Institutions', 'sustainable-catalyst-library' ),
				'post_type' => self::INSTITUTION_POST_TYPE,
				'public' => true,
			),
			'unit' => array(
				'label' => __( 'Research units', 'sustainable-catalyst-library' ),
				'post_type' => self::UNIT_POST_TYPE,
				'public' => true,
			),
		);
		$registry = apply_filters( 'sc_library_institutional_record_registry', $registry );
		set_transient( self::TRANSIENT_REGISTRY, $registry, 10 * MINUTE_IN_SECONDS );
		return $registry;
	}

	public static function supported_post_types() {
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $entry ) {
							return sanitize_key( $entry['post_type'] ?? '' );
						},
						self::record_registry()
					)
				)
			)
		);
	}

	public function register_record_types() {
		register_post_type(
			self::INSTITUTION_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Institutions', 'sustainable-catalyst-library' ),
					'singular_name' => __( 'Institution', 'sustainable-catalyst-library' ),
					'add_new_item'  => __( 'Add Institution', 'sustainable-catalyst-library' ),
					'edit_item'     => __( 'Edit Institution', 'sustainable-catalyst-library' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'has_archive'         => 'institutions',
				'rewrite'             => array( 'slug' => 'institution' ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => false,
			)
		);
		register_post_type(
			self::UNIT_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Research Units', 'sustainable-catalyst-library' ),
					'singular_name' => __( 'Research Unit', 'sustainable-catalyst-library' ),
					'add_new_item'  => __( 'Add Research Unit', 'sustainable-catalyst-library' ),
					'edit_item'     => __( 'Edit Research Unit', 'sustainable-catalyst-library' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'has_archive'         => 'research-units',
				'rewrite'             => array( 'slug' => 'research-unit' ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => false,
			)
		);
	}

	public function schedule_operations() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_MIGRATION ) ) {
			wp_schedule_event( time() + 900, 'hourly', self::CRON_MIGRATION );
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HEALTH ) ) {
			wp_schedule_event( time() + 1200, 'twicedaily', self::CRON_HEALTH );
		}
		if ( ! is_array( get_option( self::OPTION_MIGRATION, null ) ) ) {
			update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
		}
		if ( ! is_array( get_option( self::OPTION_PLATFORM, null ) ) ) {
			update_option(
				self::OPTION_PLATFORM,
				array(
					'schema'               => self::PLATFORM_SCHEMA,
					'status'               => 'active',
					'default_institution'  => 0,
					'public_portal'        => true,
					'public_search'        => true,
					'public_graph'         => true,
					'created_at'           => current_time( 'mysql' ),
					'updated_at'           => current_time( 'mysql' ),
				),
				false
			);
		}
	}

	public function sync_capabilities() {
		if ( self::VERSION === get_option( self::OPTION_CAPABILITY_VERSION, '' ) ) {
			return;
		}
		$all_caps = array_keys( self::capability_map() );
		$editor_caps = array(
			'sc_library_read_institutional',
			'sc_library_manage_institutional_records',
			'sc_library_publish_institutional',
			'sc_library_manage_institutional_handoffs',
			'sc_library_view_institutional_health',
			'sc_library_export_institutional',
		);
		$author_caps = array( 'sc_library_read_institutional' );
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( $all_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			foreach ( $editor_caps as $cap ) {
				$editor->add_cap( $cap );
			}
		}
		$author = get_role( 'author' );
		if ( $author ) {
			foreach ( $author_caps as $cap ) {
				$author->add_cap( $cap );
			}
		}
		update_option( self::OPTION_CAPABILITY_VERSION, self::VERSION, false );
		self::activity( 'capabilities-synchronized', array( 'version' => self::VERSION ) );
	}

	public function register_workspace() {
		add_submenu_page(
			'sc-library',
			__( 'Connected Institutional Knowledge and Research Platform', 'sustainable-catalyst-library' ),
			__( 'Institutional Platform', 'sustainable-catalyst-library' ),
			'sc_library_read_institutional',
			'sc-library-institutional-platform',
			array( $this, 'render_workspace' )
		);
		add_submenu_page(
			'sc-library',
			__( 'Institutions', 'sustainable-catalyst-library' ),
			__( 'Institutions', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional',
			'edit.php?post_type=' . self::INSTITUTION_POST_TYPE
		);
		add_submenu_page(
			'sc-library',
			__( 'Research Units', 'sustainable-catalyst-library' ),
			__( 'Research Units', 'sustainable-catalyst-library' ),
			'sc_library_manage_institutional',
			'edit.php?post_type=' . self::UNIT_POST_TYPE
		);
	}

	public function register_meta_boxes() {
		foreach ( self::supported_post_types() as $post_type ) {
			if ( in_array( $post_type, array( self::INSTITUTION_POST_TYPE, self::UNIT_POST_TYPE ), true ) ) {
				continue;
			}
			add_meta_box(
				'sc-institutional-record-context',
				__( 'Institutional Record Context', 'sustainable-catalyst-library' ),
				array( $this, 'render_record_context_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
		add_meta_box(
			'sc-institution-profile',
			__( 'Institution Profile', 'sustainable-catalyst-library' ),
			array( $this, 'render_institution_meta_box' ),
			self::INSTITUTION_POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'sc-research-unit-profile',
			__( 'Research Unit Profile', 'sustainable-catalyst-library' ),
			array( $this, 'render_unit_meta_box' ),
			self::UNIT_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$is_workspace = false !== strpos( (string) $screen->id, 'sc-library-institutional-platform' );
		if ( ! $is_workspace && ! in_array( $screen->post_type, self::supported_post_types(), true ) ) {
			return;
		}
		$this->register_assets();
		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		wp_enqueue_script( 'sc-library-connected-institutional-platform' );
		wp_localize_script(
			'sc-library-connected-institutional-platform',
			'SCLibraryInstitutionalPlatform',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sc_library_institutional_platform_v400' ),
				'strings' => array(
					'working'  => __( 'Working…', 'sustainable-catalyst-library' ),
					'complete' => __( 'Operation complete.', 'sustainable-catalyst-library' ),
					'error'    => __( 'The institutional platform operation failed.', 'sustainable-catalyst-library' ),
				),
			)
		);
	}

	public function register_public_assets() {
		$this->register_assets();
	}

	private function register_assets() {
		wp_register_style(
			'sc-library-connected-institutional-platform',
			SC_LIBRARY_URL . 'assets/css/sc-library-connected-institutional-platform.css',
			array(),
			self::VERSION
		);
		wp_register_script(
			'sc-library-connected-institutional-platform',
			SC_LIBRARY_URL . 'assets/js/sc-library-connected-institutional-platform.js',
			array(),
			self::VERSION,
			true
		);
	}

	public function render_record_context_meta_box( $post ) {
		wp_nonce_field( 'sc_library_save_institutional_context_' . $post->ID, 'sc_library_institutional_context_nonce' );
		$institution_id = absint( get_post_meta( $post->ID, self::META_INSTITUTION_ID, true ) );
		$unit_ids = self::id_list( get_post_meta( $post->ID, self::META_UNIT_IDS, true ) );
		$visibility = (string) get_post_meta( $post->ID, self::META_VISIBILITY, true ) ?: ( 'publish' === $post->post_status ? 'public' : 'institution' );
		$state = (string) get_post_meta( $post->ID, self::META_GOVERNANCE_STATE, true ) ?: 'unclassified';
		?>
		<div class="sc-inst-record-context">
			<label><strong><?php esc_html_e( 'Institution', 'sustainable-catalyst-library' ); ?></strong>
				<select name="sc_institution_id">
					<option value="0"><?php esc_html_e( 'No institution assigned', 'sustainable-catalyst-library' ); ?></option>
					<?php foreach ( self::institution_options() as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $institution_id, $id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><strong><?php esc_html_e( 'Research unit IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_research_unit_ids" value="<?php echo esc_attr( implode( ', ', $unit_ids ) ); ?>"></label>
			<label><strong><?php esc_html_e( 'Visibility', 'sustainable-catalyst-library' ); ?></strong><select name="sc_record_visibility"><?php foreach ( self::visibility_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $visibility, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong><?php esc_html_e( 'Governance state', 'sustainable-catalyst-library' ); ?></strong><select name="sc_record_governance_state"><?php foreach ( self::governance_states() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong><?php esc_html_e( 'Record steward user ID', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="0" name="sc_record_steward_id" value="<?php echo esc_attr( absint( get_post_meta( $post->ID, self::META_STEWARD_ID, true ) ) ); ?>"></label>
			<p><strong><?php esc_html_e( 'Institutional URN', 'sustainable-catalyst-library' ); ?></strong><br><code><?php echo esc_html( self::ensure_record_identity( $post->ID ) ); ?></code></p>
		</div>
		<?php
	}

	public function render_institution_meta_box( $post ) {
		wp_nonce_field( 'sc_library_save_institution_' . $post->ID, 'sc_library_institution_nonce' );
		$status = (string) get_post_meta( $post->ID, self::META_INSTITUTION_STATUS, true ) ?: 'active';
		?>
		<div class="sc-inst-editor">
			<div class="sc-inst-field-grid">
				<label><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_institution_status"><?php foreach ( self::institution_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'Short name', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_institution_short_name" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION_SHORT_NAME, true ) ); ?>"></label>
				<label><strong><?php esc_html_e( 'Contact', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_institution_contact" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION_CONTACT, true ) ); ?>"></label>
				<label><input type="checkbox" name="sc_institution_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_INSTITUTION_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Show this institution in public portals', 'sustainable-catalyst-library' ); ?></label>
			</div>
			<label><strong><?php esc_html_e( 'Institution description', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_institution_description" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_INSTITUTION_DESCRIPTION, true ) ); ?></textarea></label>
			<label><strong><?php esc_html_e( 'External identifiers', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_institution_identifiers" rows="4" placeholder="ror=https://ror.org/...&#10;grid=..."><?php echo esc_textarea( self::key_value_lines( get_post_meta( $post->ID, self::META_INSTITUTION_IDENTIFIERS, true ) ) ); ?></textarea></label>
		</div>
		<?php
	}

	public function render_unit_meta_box( $post ) {
		wp_nonce_field( 'sc_library_save_unit_' . $post->ID, 'sc_library_unit_nonce' );
		$status = (string) get_post_meta( $post->ID, self::META_UNIT_STATUS, true ) ?: 'active';
		$type = (string) get_post_meta( $post->ID, self::META_UNIT_TYPE, true ) ?: 'research-center';
		?>
		<div class="sc-inst-editor">
			<div class="sc-inst-field-grid">
				<label><strong><?php esc_html_e( 'Institution', 'sustainable-catalyst-library' ); ?></strong><select name="sc_unit_institution_id"><option value="0"><?php esc_html_e( 'No institution assigned', 'sustainable-catalyst-library' ); ?></option><?php foreach ( self::institution_options() as $id => $label ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( absint( get_post_meta( $post->ID, self::META_INSTITUTION_ID, true ) ), $id ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_unit_status"><?php foreach ( self::institution_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'Unit type', 'sustainable-catalyst-library' ); ?></strong><select name="sc_unit_type"><?php foreach ( self::unit_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'Lead user IDs', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_unit_lead_ids" value="<?php echo esc_attr( implode( ', ', self::id_list( get_post_meta( $post->ID, self::META_UNIT_LEAD_IDS, true ) ) ) ); ?>"></label>
				<label><input type="checkbox" name="sc_unit_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_UNIT_PUBLIC, true ) ); ?>> <?php esc_html_e( 'Show this unit in public portals', 'sustainable-catalyst-library' ); ?></label>
			</div>
			<label><strong><?php esc_html_e( 'Unit description', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_unit_description" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_UNIT_DESCRIPTION, true ) ); ?></textarea></label>
			<label><strong><?php esc_html_e( 'Research scope', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_unit_scope" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_UNIT_SCOPE, true ) ); ?></textarea></label>
		</div>
		<?php
	}

	public function save_institutional_context( $post_id, $post, $update ) {
		if (
			self::$saving
			|| ! $post instanceof WP_Post
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| in_array( $post->post_type, array( self::INSTITUTION_POST_TYPE, self::UNIT_POST_TYPE ), true )
			|| ! in_array( $post->post_type, self::supported_post_types(), true )
			|| ! isset( $_POST['sc_library_institutional_context_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sc_library_institutional_context_nonce'] ) ),
				'sc_library_save_institutional_context_' . $post_id
			)
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! current_user_can( 'sc_library_manage_institutional_records' )
		) {
			return;
		}
		self::$saving = true;
		$institution_id = absint( wp_unslash( $_POST['sc_institution_id'] ?? 0 ) );
		if ( $institution_id && self::INSTITUTION_POST_TYPE !== get_post_type( $institution_id ) ) {
			$institution_id = 0;
		}
		$unit_ids = array_values(
			array_filter(
				self::id_list( wp_unslash( $_POST['sc_research_unit_ids'] ?? array() ) ),
				static function ( $id ) {
					return self::UNIT_POST_TYPE === get_post_type( $id );
				}
			)
		);
		$visibility = self::allowed_value( wp_unslash( $_POST['sc_record_visibility'] ?? 'institution' ), self::visibility_options(), 'institution' );
		$state = self::allowed_value( wp_unslash( $_POST['sc_record_governance_state'] ?? 'unclassified' ), self::governance_states(), 'unclassified' );
		$steward_id = absint( wp_unslash( $_POST['sc_record_steward_id'] ?? 0 ) );

		update_post_meta( $post_id, self::META_INSTITUTION_ID, $institution_id );
		update_post_meta( $post_id, self::META_UNIT_IDS, $unit_ids );
		update_post_meta( $post_id, self::META_VISIBILITY, $visibility );
		update_post_meta( $post_id, self::META_GOVERNANCE_STATE, $state );
		update_post_meta( $post_id, self::META_STEWARD_ID, $steward_id );
		self::register_record( $post_id, true );
		self::activity(
			'record-context-updated',
			array(
				'post_id'        => $post_id,
				'institution_id' => $institution_id,
				'unit_ids'       => $unit_ids,
				'visibility'     => $visibility,
				'state'          => $state,
			)
		);
		self::invalidate_caches();
		self::$saving = false;
	}

	public function save_institution( $post_id, $post, $update ) {
		if (
			self::$saving
			|| ! $post instanceof WP_Post
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| self::INSTITUTION_POST_TYPE !== $post->post_type
			|| ! isset( $_POST['sc_library_institution_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sc_library_institution_nonce'] ) ),
				'sc_library_save_institution_' . $post_id
			)
			|| ! current_user_can( 'sc_library_manage_institutional' )
		) {
			return;
		}
		self::$saving = true;
		self::ensure_record_identity( $post_id, 'institution' );
		update_post_meta( $post_id, self::META_INSTITUTION_STATUS, self::allowed_value( wp_unslash( $_POST['sc_institution_status'] ?? 'active' ), self::institution_statuses(), 'active' ) );
		update_post_meta( $post_id, self::META_INSTITUTION_SHORT_NAME, sanitize_text_field( wp_unslash( $_POST['sc_institution_short_name'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_DESCRIPTION, sanitize_textarea_field( wp_unslash( $_POST['sc_institution_description'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_IDENTIFIERS, self::parse_key_value_lines( wp_unslash( $_POST['sc_institution_identifiers'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_CONTACT, sanitize_text_field( wp_unslash( $_POST['sc_institution_contact'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_PUBLIC, isset( $_POST['sc_institution_public'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_VISIBILITY, isset( $_POST['sc_institution_public'] ) ? 'public' : 'institution' );
		update_post_meta( $post_id, self::META_GOVERNANCE_STATE, 'managed' );
		self::register_record( $post_id, true );
		self::activity( 'institution-updated', array( 'institution_id' => $post_id ) );
		self::invalidate_caches();
		self::$saving = false;
	}

	public function save_unit( $post_id, $post, $update ) {
		if (
			self::$saving
			|| ! $post instanceof WP_Post
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| self::UNIT_POST_TYPE !== $post->post_type
			|| ! isset( $_POST['sc_library_unit_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sc_library_unit_nonce'] ) ),
				'sc_library_save_unit_' . $post_id
			)
			|| ! current_user_can( 'sc_library_manage_institutional' )
		) {
			return;
		}
		self::$saving = true;
		$institution_id = absint( wp_unslash( $_POST['sc_unit_institution_id'] ?? 0 ) );
		if ( $institution_id && self::INSTITUTION_POST_TYPE !== get_post_type( $institution_id ) ) {
			$institution_id = 0;
		}
		self::ensure_record_identity( $post_id, 'unit' );
		update_post_meta( $post_id, self::META_INSTITUTION_ID, $institution_id );
		update_post_meta( $post_id, self::META_UNIT_STATUS, self::allowed_value( wp_unslash( $_POST['sc_unit_status'] ?? 'active' ), self::institution_statuses(), 'active' ) );
		update_post_meta( $post_id, self::META_UNIT_TYPE, self::allowed_value( wp_unslash( $_POST['sc_unit_type'] ?? 'research-center' ), self::unit_types(), 'research-center' ) );
		update_post_meta( $post_id, self::META_UNIT_DESCRIPTION, sanitize_textarea_field( wp_unslash( $_POST['sc_unit_description'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_UNIT_LEAD_IDS, self::id_list( wp_unslash( $_POST['sc_unit_lead_ids'] ?? array() ) ) );
		update_post_meta( $post_id, self::META_UNIT_SCOPE, sanitize_textarea_field( wp_unslash( $_POST['sc_unit_scope'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_UNIT_PUBLIC, isset( $_POST['sc_unit_public'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::META_VISIBILITY, isset( $_POST['sc_unit_public'] ) ? 'public' : 'institution' );
		update_post_meta( $post_id, self::META_GOVERNANCE_STATE, 'managed' );
		self::register_record( $post_id, true );
		self::activity( 'research-unit-updated', array( 'unit_id' => $post_id, 'institution_id' => $institution_id ) );
		self::invalidate_caches();
		self::$saving = false;
	}

	public static function register_record( $post_id, $persist = true ) {
		$post_id = absint( $post_id );
		$type = self::type_for_post_type( get_post_type( $post_id ) );
		if ( ! $type ) {
			return array();
		}
		$urn = self::ensure_record_identity( $post_id, $type );
		$data = self::serialize_record( $post_id, true, true );
		if ( ! $data ) {
			return array();
		}
		$hash_data = $data;
		unset( $hash_data['registry_hash'], $hash_data['registered_at'], $hash_data['registry_updated_at'] );
		$hash = hash( 'sha256', self::canonical_json( $hash_data ) );
		if ( $persist ) {
			update_post_meta( $post_id, self::META_REGISTRY_HASH, $hash );
			if ( ! get_post_meta( $post_id, self::META_REGISTERED_AT, true ) ) {
				update_post_meta( $post_id, self::META_REGISTERED_AT, current_time( 'mysql' ) );
			}
			update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
		}
		$data['registry_hash'] = $hash;
		$data['urn'] = $urn;
		return $data;
	}

	public static function ensure_record_identity( $post_id, $type = '' ) {
		$post_id = absint( $post_id );
		$type = $type ?: self::type_for_post_type( get_post_type( $post_id ) );
		if ( ! $post_id || ! $type ) {
			return '';
		}
		$uuid = (string) get_post_meta( $post_id, self::META_UUID, true );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, self::META_UUID, $uuid );
		}
		$urn = (string) get_post_meta( $post_id, self::META_RECORD_URN, true );
		if ( ! $urn ) {
			$site = substr( hash( 'sha256', home_url( '/' ) ), 0, 16 );
			$urn = 'urn:sc-library:' . $site . ':' . sanitize_key( $type ) . ':' . $uuid;
			update_post_meta( $post_id, self::META_RECORD_URN, $urn );
		}
		return $urn;
	}

	public static function serialize_record( $post_id, $include_private = false, $trusted_context = false ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$type = self::type_for_post_type( $post->post_type );
		if ( ! $type ) {
			return array();
		}
		$visibility = (string) get_post_meta( $post_id, self::META_VISIBILITY, true );
		if ( ! $visibility ) {
			$visibility = 'publish' === $post->post_status ? 'public' : 'institution';
		}
		$can_private = $include_private && ( $trusted_context || current_user_can( 'sc_library_read_institutional' ) );
		if ( 'public' !== $visibility && ! $can_private ) {
			return array();
		}
		if ( ! $can_private && self::INSTITUTION_POST_TYPE === $post->post_type && '1' !== get_post_meta( $post_id, self::META_INSTITUTION_PUBLIC, true ) ) {
			return array();
		}
		if ( ! $can_private && self::UNIT_POST_TYPE === $post->post_type && '1' !== get_post_meta( $post_id, self::META_UNIT_PUBLIC, true ) ) {
			return array();
		}
		$institution_id = absint( get_post_meta( $post_id, self::META_INSTITUTION_ID, true ) );
		$unit_ids = self::id_list( get_post_meta( $post_id, self::META_UNIT_IDS, true ) );
		if ( self::UNIT_POST_TYPE === $post->post_type ) {
			$unit_ids = array( $post_id );
		}
		$record = array(
			'schema'              => self::RECORD_SCHEMA,
			'id'                  => $post_id,
			'uuid'                => self::ensure_uuid_meta( $post_id ),
			'urn'                 => self::ensure_record_identity( $post_id, $type ),
			'type'                => $type,
			'type_label'          => self::record_registry()[ $type ]['label'] ?? $type,
			'post_type'           => $post->post_type,
			'title'               => html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'slug'                => (string) $post->post_name,
			'url'                 => 'publish' === $post->post_status ? get_permalink( $post_id ) : '',
			'excerpt'             => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'status'              => (string) $post->post_status,
			'visibility'          => $visibility,
			'governance_state'    => (string) get_post_meta( $post_id, self::META_GOVERNANCE_STATE, true ) ?: 'unclassified',
			'institution_id'      => $institution_id,
			'institution'         => $institution_id ? self::public_identity( $institution_id ) : array(),
			'unit_ids'            => $unit_ids,
			'units'               => array_values( array_filter( array_map( array( __CLASS__, 'public_identity' ), $unit_ids ) ) ),
			'published_at'        => get_post_time( DATE_ATOM, true, $post_id ),
			'modified_at'         => get_post_modified_time( DATE_ATOM, true, $post_id ),
			'content_hash'        => hash( 'sha256', (string) get_the_title( $post_id ) . "\n" . (string) get_post_field( 'post_content', $post_id ) ),
			'registry_hash'       => (string) get_post_meta( $post_id, self::META_REGISTRY_HASH, true ),
			'registered_at'       => (string) get_post_meta( $post_id, self::META_REGISTERED_AT, true ),
			'registry_updated_at' => (string) get_post_meta( $post_id, self::META_UPDATED_AT, true ),
		);
		if ( 'document' === $type && class_exists( 'SC_Library_Public_API_Export_Federation' ) ) {
			$public = SC_Library_Public_API_Export_Federation::serialize_record( $post_id, 'documents', false );
			if ( is_array( $public ) && $public ) {
				foreach ( array( 'topics', 'collections', 'source_file', 'document_intelligence' ) as $field ) {
					if ( isset( $public[ $field ] ) ) {
						$record[ $field ] = $public[ $field ];
					}
				}
			}
		}
		if ( 'institution' === $type ) {
			$record['short_name'] = (string) get_post_meta( $post_id, self::META_INSTITUTION_SHORT_NAME, true );
			$record['description'] = (string) get_post_meta( $post_id, self::META_INSTITUTION_DESCRIPTION, true );
			$record['identifiers'] = (array) get_post_meta( $post_id, self::META_INSTITUTION_IDENTIFIERS, true );
		} elseif ( 'unit' === $type ) {
			$record['unit_type'] = (string) get_post_meta( $post_id, self::META_UNIT_TYPE, true );
			$record['description'] = (string) get_post_meta( $post_id, self::META_UNIT_DESCRIPTION, true );
			$record['scope'] = (string) get_post_meta( $post_id, self::META_UNIT_SCOPE, true );
		}
		if ( $can_private ) {
			$record['author_id'] = absint( $post->post_author );
			$record['steward_id'] = absint( get_post_meta( $post_id, self::META_STEWARD_ID, true ) );
			$record['raw_content'] = (string) $post->post_content;
			if ( 'institution' === $type ) {
				$record['contact'] = (string) get_post_meta( $post_id, self::META_INSTITUTION_CONTACT, true );
			}
			if ( 'unit' === $type ) {
				$record['lead_ids'] = self::id_list( get_post_meta( $post_id, self::META_UNIT_LEAD_IDS, true ) );
			}
		}
		return apply_filters( 'sc_library_institutional_record', $record, $post_id, $include_private );
	}

	public static function public_identity( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( self::INSTITUTION_POST_TYPE, self::UNIT_POST_TYPE ), true ) ) {
			return array();
		}
		$public = '1' === get_post_meta(
			$post_id,
			self::INSTITUTION_POST_TYPE === get_post_type( $post_id ) ? self::META_INSTITUTION_PUBLIC : self::META_UNIT_PUBLIC,
			true
		);
		if ( ! $public && ! current_user_can( 'sc_library_read_institutional' ) ) {
			return array();
		}
		return array(
			'id'    => $post_id,
			'uuid'  => self::ensure_uuid_meta( $post_id ),
			'urn'   => self::ensure_record_identity( $post_id ),
			'title' => get_the_title( $post_id ),
			'url'   => 'publish' === get_post_status( $post_id ) ? get_permalink( $post_id ) : '',
			'type'  => self::type_for_post_type( get_post_type( $post_id ) ),
		);
	}

	public static function type_for_post_type( $post_type ) {
		foreach ( self::record_registry() as $type => $definition ) {
			if ( sanitize_key( $post_type ) === sanitize_key( $definition['post_type'] ?? '' ) ) {
				return $type;
			}
		}
		return '';
	}

	public static function institutional_search( $args = array(), $include_private = false ) {
		$args = wp_parse_args(
			$args,
			array(
				'query'          => '',
				'types'          => array(),
				'institution_id' => 0,
				'unit_id'        => 0,
				'visibility'     => '',
				'governance'     => '',
				'limit'          => self::DEFAULT_SEARCH_LIMIT,
				'cursor'         => '',
			)
		);
		$can_private = $include_private && current_user_can( 'sc_library_read_institutional' );
		$registry = self::record_registry();
		$requested_types = array_filter( array_map( 'sanitize_key', (array) $args['types'] ) );
		if ( ! $requested_types ) {
			$requested_types = array_keys( $registry );
		}
		$requested_types = array_values(
			array_filter(
				$requested_types,
				static function ( $type ) use ( $registry, $can_private ) {
					return isset( $registry[ $type ] ) && ( $can_private || ! empty( $registry[ $type ]['public'] ) );
				}
			)
		);
		$post_types = array_values(
			array_unique(
				array_map(
					static function ( $type ) use ( $registry ) {
						return $registry[ $type ]['post_type'];
					},
					$requested_types
				)
			)
		);
		$limit = max( 1, min( self::MAX_SEARCH_LIMIT, absint( $args['limit'] ) ) );
		$cursor = self::decode_cursor( $args['cursor'] );
		$offset = max( 0, absint( $cursor['offset'] ?? 0 ) );
		$query_args = array(
			'post_type'              => $post_types,
			'post_status'            => $can_private ? array( 'publish', 'private', 'draft', 'pending', 'future' ) : 'publish',
			'posts_per_page'         => $limit + 1,
			'offset'                 => $offset,
			'fields'                 => 'ids',
			'orderby'                => array( 'modified' => 'DESC', 'ID' => 'DESC' ),
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'suppress_filters'       => false,
		);
		$query = sanitize_text_field( $args['query'] );
		if ( $query ) {
			$query_args['s'] = $query;
		}
		$meta_query = array( 'relation' => 'AND' );
		if ( ! $can_private ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => self::META_VISIBILITY, 'value' => 'public' ),
				array( 'key' => self::META_VISIBILITY, 'compare' => 'NOT EXISTS' ),
			);
		} elseif ( $args['visibility'] && isset( self::visibility_options()[ sanitize_key( $args['visibility'] ) ] ) ) {
			$meta_query[] = array( 'key' => self::META_VISIBILITY, 'value' => sanitize_key( $args['visibility'] ) );
		}
		$institution_id = absint( $args['institution_id'] );
		if ( $institution_id ) {
			$meta_query[] = array( 'key' => self::META_INSTITUTION_ID, 'value' => $institution_id, 'type' => 'NUMERIC' );
		}
		$unit_id = absint( $args['unit_id'] );
		if ( $unit_id ) {
			$meta_query[] = array( 'key' => self::META_UNIT_IDS, 'value' => 'i:' . $unit_id . ';', 'compare' => 'LIKE' );
		}
		$governance = sanitize_key( $args['governance'] );
		if ( $governance && isset( self::governance_states()[ $governance ] ) ) {
			$meta_query[] = array( 'key' => self::META_GOVERNANCE_STATE, 'value' => $governance );
		}
		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		$ids = array_map( 'absint', (array) get_posts( $query_args ) );
		$has_more = count( $ids ) > $limit;
		$ids = array_slice( $ids, 0, $limit );
		$records = array_values(
			array_filter(
				array_map(
					static function ( $id ) use ( $can_private ) {
						return self::serialize_record( $id, $can_private );
					},
					$ids
				)
			)
		);
		$facets = array();
		foreach ( $records as $record ) {
			$type = $record['type'] ?? 'unknown';
			$facets[ $type ] = absint( $facets[ $type ] ?? 0 ) + 1;
		}
		$result = array(
			'schema'       => self::SEARCH_SCHEMA,
			'query'        => $query,
			'types'        => $requested_types,
			'count'        => count( $records ),
			'limit'        => $limit,
			'cursor_type'  => 'opaque-offset',
			'offset'       => $offset,
			'next_cursor'  => $has_more ? self::encode_cursor( array( 'offset' => $offset + $limit, 'types' => hash( 'sha256', implode( ',', $requested_types ) ) ) ) : '',
			'facets'       => $facets,
			'records'      => $records,
			'generated_at' => current_time( 'mysql' ),
		);
		$result['etag'] = '"' . hash( 'sha256', self::canonical_json( self::stable_etag_data( $result ) ) ) . '"';
		return $result;
	}

	public static function knowledge_graph( $args = array(), $include_private = false ) {
		$args = wp_parse_args(
			$args,
			array(
				'kind'  => '',
				'id'    => 0,
				'depth' => 1,
				'limit' => self::MAX_GRAPH_NODES,
			)
		);
		$kind = sanitize_key( $args['kind'] );
		$id = absint( $args['id'] );
		$depth = max( 1, min( 2, absint( $args['depth'] ) ) );
		$limit = max( 1, min( self::MAX_GRAPH_NODES, absint( $args['limit'] ) ) );
		if ( ! $kind || ! $id ) {
			return new WP_Error( 'institutional_graph_seed_missing', __( 'A graph seed kind and ID are required.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		$seed = self::graph_node( $kind, $id, $include_private );
		if ( ! $seed ) {
			return new WP_Error( 'institutional_graph_seed_not_found', __( 'The graph seed is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}

		$nodes = array( self::node_key( $kind, $id ) => $seed );
		$edges = array();
		$frontier = array( array( 'kind' => $kind, 'id' => $id ) );
		for ( $level = 0; $level < $depth && $frontier; $level++ ) {
			$next = array();
			foreach ( $frontier as $ref ) {
				foreach ( self::semantic_edges_for_node( $ref['kind'], $ref['id'], $include_private ) as $edge ) {
					$edge_key = hash( 'sha256', self::canonical_json( $edge ) );
					$edges[ $edge_key ] = $edge;
					foreach ( array( 'from', 'to' ) as $side ) {
						$node_ref = $edge[ $side ];
						$key = self::node_key( $node_ref['kind'], $node_ref['id'] );
						if ( isset( $nodes[ $key ] ) || count( $nodes ) >= $limit ) {
							continue;
						}
						$node = self::graph_node( $node_ref['kind'], $node_ref['id'], $include_private );
						if ( $node ) {
							$nodes[ $key ] = $node;
							$next[] = $node_ref;
						}
					}
					if ( count( $nodes ) >= $limit ) {
						break 2;
					}
				}
				foreach ( self::institutional_edges_for_node( $ref['kind'], $ref['id'], $include_private ) as $edge ) {
					$edge_key = hash( 'sha256', self::canonical_json( $edge ) );
					$edges[ $edge_key ] = $edge;
					$target = $edge['to'];
					$key = self::node_key( $target['kind'], $target['id'] );
					if ( ! isset( $nodes[ $key ] ) && count( $nodes ) < $limit ) {
						$node = self::graph_node( $target['kind'], $target['id'], $include_private );
						if ( $node ) {
							$nodes[ $key ] = $node;
							$next[] = $target;
						}
					}
				}
			}
			$frontier = $next;
		}
		$result = array(
			'schema'       => self::GRAPH_SCHEMA,
			'seed'         => array( 'kind' => $kind, 'id' => $id ),
			'depth'        => $depth,
			'node_count'   => count( $nodes ),
			'edge_count'   => count( $edges ),
			'truncated'    => count( $nodes ) >= $limit,
			'nodes'        => array_values( $nodes ),
			'edges'        => array_values( $edges ),
			'generated_at' => current_time( 'mysql' ),
		);
		$result['graph_sha256'] = hash( 'sha256', self::canonical_json( self::stable_etag_data( $result ) ) );
		return $result;
	}

	private static function graph_node( $kind, $id, $include_private ) {
		$kind = sanitize_key( $kind );
		$id = absint( $id );
		if ( 'topic' === $kind ) {
			$taxonomy = class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY : 'sc_library_topic';
			$term = get_term( $id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				return array();
			}
			return array(
				'kind'        => 'topic',
				'id'          => $id,
				'label'       => (string) $term->name,
				'slug'        => (string) $term->slug,
				'description' => wp_strip_all_tags( (string) $term->description ),
				'url'         => get_term_link( $term ),
			);
		}
		$record = self::serialize_record( $id, $include_private );
		if ( ! $record ) {
			return array();
		}
		return array(
			'kind'       => $record['type'],
			'id'         => $record['id'],
			'urn'        => $record['urn'],
			'label'      => $record['title'],
			'url'        => $record['url'],
			'visibility' => $record['visibility'],
			'state'      => $record['governance_state'],
		);
	}

	private static function semantic_edges_for_node( $kind, $id, $include_private ) {
		if ( ! class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
			return array();
		}
		$relation_type = SC_Library_Topics_Concepts_Relationships::RELATION_POST_TYPE;
		$from_kind = SC_Library_Topics_Concepts_Relationships::META_RELATION_FROM_KIND;
		$from_id = SC_Library_Topics_Concepts_Relationships::META_RELATION_FROM_ID;
		$to_kind = SC_Library_Topics_Concepts_Relationships::META_RELATION_TO_KIND;
		$to_id = SC_Library_Topics_Concepts_Relationships::META_RELATION_TO_ID;
		$relation_key = SC_Library_Topics_Concepts_Relationships::META_RELATION_TYPE;
		$public_key = SC_Library_Topics_Concepts_Relationships::META_RELATION_PUBLIC;
		$status_key = SC_Library_Topics_Concepts_Relationships::META_RELATION_STATUS;
		$ids = get_posts(
			array(
				'post_type'      => $relation_type,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'relation' => 'AND',
						array( 'key' => $from_kind, 'value' => $kind ),
						array( 'key' => $from_id, 'value' => $id, 'type' => 'NUMERIC' ),
					),
					array(
						'relation' => 'AND',
						array( 'key' => $to_kind, 'value' => $kind ),
						array( 'key' => $to_id, 'value' => $id, 'type' => 'NUMERIC' ),
					),
				),
			)
		);
		$edges = array();
		foreach ( $ids as $relation_id ) {
			$is_public = '1' === get_post_meta( $relation_id, $public_key, true );
			$status = (string) get_post_meta( $relation_id, $status_key, true );
			if ( ! $include_private && ( ! $is_public || ( $status && 'active' !== $status ) ) ) {
				continue;
			}
			$edges[] = array(
				'id'       => absint( $relation_id ),
				'type'     => (string) get_post_meta( $relation_id, $relation_key, true ) ?: 'related-to',
				'from'     => array(
					'kind' => sanitize_key( get_post_meta( $relation_id, $from_kind, true ) ),
					'id'   => absint( get_post_meta( $relation_id, $from_id, true ) ),
				),
				'to'       => array(
					'kind' => sanitize_key( get_post_meta( $relation_id, $to_kind, true ) ),
					'id'   => absint( get_post_meta( $relation_id, $to_id, true ) ),
				),
				'public'   => $is_public,
				'source'   => 'semantic-relationship',
			);
		}
		return $edges;
	}

	private static function institutional_edges_for_node( $kind, $id, $include_private ) {
		$kind = sanitize_key( $kind );
		$id = absint( $id );
		if ( 'topic' === $kind ) {
			return array();
		}
		$post_id = $id;
		if ( ! get_post( $post_id ) ) {
			return array();
		}
		$record = self::serialize_record( $post_id, $include_private );
		if ( ! $record ) {
			return array();
		}
		$edges = array();
		$from = array( 'kind' => $record['type'], 'id' => $post_id );
		$institution_id = absint( $record['institution_id'] ?? 0 );
		if ( $institution_id ) {
			$edges[] = array(
				'type'   => 'governed-by-institution',
				'from'   => $from,
				'to'     => array( 'kind' => 'institution', 'id' => $institution_id ),
				'public' => 'public' === $record['visibility'],
				'source' => 'institutional-registry',
			);
		}
		foreach ( self::id_list( $record['unit_ids'] ?? array() ) as $unit_id ) {
			if ( $unit_id === $post_id && 'unit' === $record['type'] ) {
				continue;
			}
			$edges[] = array(
				'type'   => 'managed-by-unit',
				'from'   => $from,
				'to'     => array( 'kind' => 'unit', 'id' => $unit_id ),
				'public' => 'public' === $record['visibility'],
				'source' => 'institutional-registry',
			);
		}
		if ( 'unit' === $record['type'] && $institution_id ) {
			$edges[] = array(
				'type'   => 'part-of-institution',
				'from'   => $from,
				'to'     => array( 'kind' => 'institution', 'id' => $institution_id ),
				'public' => 'public' === $record['visibility'],
				'source' => 'institutional-registry',
			);
		}
		return $edges;
	}

	private static function node_key( $kind, $id ) {
		return sanitize_key( $kind ) . ':' . absint( $id );
	}

	public static function platform_summary( $include_private = false ) {
		$settings = get_option( self::OPTION_PLATFORM, array() );
		$health = self::health_report( false );
		$registry = self::record_registry();
		$counts = array();
		foreach ( $registry as $type => $definition ) {
			if ( ! $include_private && empty( $definition['public'] ) ) {
				continue;
			}
			$count = wp_count_posts( $definition['post_type'] );
			$counts[ $type ] = is_object( $count ) ? absint( $count->publish ?? 0 ) : 0;
		}
		$data = array(
			'schema'          => self::PLATFORM_SCHEMA,
			'version'         => self::VERSION,
			'status'          => (string) ( $settings['status'] ?? 'active' ),
			'name'            => get_bloginfo( 'name' ) . ' Knowledge Library',
			'base_url'        => home_url( '/' ),
			'api_url'         => rest_url( self::API_NAMESPACE ),
			'default_institution' => absint( $settings['default_institution'] ?? 0 ),
			'public_portal'   => ! empty( $settings['public_portal'] ),
			'public_search'   => ! empty( $settings['public_search'] ),
			'public_graph'    => ! empty( $settings['public_graph'] ),
			'record_types'    => count( $counts ),
			'record_counts'   => $counts,
			'health'          => array(
				'status'     => $health['status'],
				'score'      => $health['score'],
				'components' => $health['component_count'],
			),
			'generated_at'    => current_time( 'mysql' ),
		);
		if ( $include_private && current_user_can( 'sc_library_read_institutional' ) ) {
			$data['capabilities'] = array_keys( self::capability_map() );
			$data['migration'] = self::migration_state();
		}
		return $data;
	}

	public static function health_report( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT_HEALTH );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$components = array(
			'foundation' => array( 'class' => 'SC_Library_Foundation_Pages', 'expected' => '' ),
			'pdf_to_document' => array( 'class' => 'SC_Library_PDF_To_Document', 'expected' => '' ),
			'ocr' => array( 'class' => 'SC_Library_Document_OCR_Processing', 'expected' => '2.4.1' ),
			'sources' => array( 'class' => 'SC_Library_Citation_Source_Manager', 'expected' => '2.5.1' ),
			'connectors' => array( 'class' => 'SC_Library_Scholarly_Library_Connectors', 'expected' => '2.6.1' ),
			'evidence_claims' => array( 'class' => 'SC_Library_Evidence_Claim_Linking', 'expected' => '2.7.0' ),
			'connected_research' => array( 'class' => 'SC_Library_Connected_Research_Environment', 'expected' => '3.0.0' ),
			'source_integrity' => array( 'class' => 'SC_Library_Source_Versioning_Integrity', 'expected' => '3.1.0' ),
			'semantics' => array( 'class' => 'SC_Library_Topics_Concepts_Relationships', 'expected' => '3.2.0' ),
			'pathways' => array( 'class' => 'SC_Library_Knowledge_Pathways_Article_Maps', 'expected' => '3.3.0' ),
			'handoffs' => array( 'class' => 'SC_Library_Cross_Product_Research_Handoffs', 'expected' => '3.4.0' ),
			'quality_governance' => array( 'class' => 'SC_Library_Research_Quality_Governance', 'expected' => '3.5.0' ),
			'archives' => array( 'class' => 'SC_Library_Institutional_Collections_Archives', 'expected' => '3.6.0' ),
			'document_intelligence' => array( 'class' => 'SC_Library_Research_Librarian_Document_Intelligence', 'expected' => '3.7.0' ),
			'review_publishing' => array( 'class' => 'SC_Library_Collaborative_Review_Publishing', 'expected' => '3.8.0' ),
			'api_export_federation' => array( 'class' => 'SC_Library_Public_API_Export_Federation', 'expected' => '3.9.0' ),
			'institutional_platform' => array( 'class' => __CLASS__, 'expected' => self::VERSION ),
		);
		$results = array();
		$passed = 0;
		foreach ( $components as $key => $definition ) {
			$loaded = class_exists( $definition['class'] );
			$version = '';
			if ( $loaded ) {
				$constant = $definition['class'] . '::VERSION';
				if ( defined( $constant ) ) {
					$version = (string) constant( $constant );
				}
			}
			$version_ok = ! $definition['expected'] || ! $version || version_compare( $version, $definition['expected'], '>=' );
			$ok = $loaded && $version_ok;
			if ( $ok ) {
				$passed++;
			}
			$results[ $key ] = array(
				'loaded'      => $loaded,
				'version'     => $version,
				'expected'    => $definition['expected'],
				'version_ok'  => $version_ok,
				'status'      => $ok ? 'ready' : 'degraded',
			);
		}
		$checks = array(
			'plugin_version' => array(
				'passed' => defined( 'SC_LIBRARY_VERSION' ) && self::VERSION === SC_LIBRARY_VERSION,
				'value'  => defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : '',
			),
			'migration' => array(
				'passed' => in_array( self::migration_state()['status'], array( 'pending', 'running', 'complete' ), true ),
				'value'  => self::migration_state()['status'],
			),
			'cron_migration' => array(
				'passed' => ! function_exists( 'wp_next_scheduled' ) || (bool) wp_next_scheduled( self::CRON_MIGRATION ),
				'value'  => function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::CRON_MIGRATION ) : 0,
			),
			'cron_health' => array(
				'passed' => ! function_exists( 'wp_next_scheduled' ) || (bool) wp_next_scheduled( self::CRON_HEALTH ),
				'value'  => function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::CRON_HEALTH ) : 0,
			),
			'record_registry' => array(
				'passed' => count( self::record_registry() ) >= 20,
				'value'  => count( self::record_registry() ),
			),
		);
		foreach ( $checks as $check ) {
			if ( ! empty( $check['passed'] ) ) {
				$passed++;
			}
		}
		$total = count( $components ) + count( $checks );
		$score = $total ? (int) round( ( $passed / $total ) * 100 ) : 0;
		$status = 100 === $score ? 'ready' : ( $score >= 80 ? 'degraded' : 'attention' );
		$report = array(
			'schema'          => self::HEALTH_SCHEMA,
			'version'         => self::VERSION,
			'status'          => $status,
			'score'           => $score,
			'component_count' => count( $components ),
			'components'      => $results,
			'checks'          => $checks,
			'migration'       => self::migration_state(),
			'generated_at'    => current_time( 'mysql' ),
		);
		set_transient( self::TRANSIENT_HEALTH, $report, 30 * MINUTE_IN_SECONDS );
		return $report;
	}

	public function refresh_health_cache() {
		self::health_report( true );
	}

	public static function workspace_report() {
		$cached = get_transient( self::TRANSIENT_DASHBOARD );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$registry = self::record_registry();
		$counts = array();
		$total = 0;
		foreach ( $registry as $type => $definition ) {
			$count = wp_count_posts( $definition['post_type'] );
			$published = is_object( $count ) ? absint( $count->publish ?? 0 ) : 0;
			$private = is_object( $count ) ? absint( $count->private ?? 0 ) : 0;
			$draft = is_object( $count ) ? absint( $count->draft ?? 0 ) : 0;
			$counts[ $type ] = array(
				'label'     => $definition['label'],
				'published' => $published,
				'private'   => $private,
				'draft'     => $draft,
				'total'     => $published + $private + $draft,
			);
			$total += $counts[ $type ]['total'];
		}
		$governance = array_fill_keys( array_keys( self::governance_states() ), 0 );
		$visibility = array_fill_keys( array_keys( self::visibility_options() ), 0 );
		$ids = get_posts(
			array(
				'post_type'      => self::supported_post_types(),
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => 2000,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		foreach ( $ids as $id ) {
			$state = (string) get_post_meta( $id, self::META_GOVERNANCE_STATE, true ) ?: 'unclassified';
			$record_visibility = (string) get_post_meta( $id, self::META_VISIBILITY, true ) ?: ( 'publish' === get_post_status( $id ) ? 'public' : 'institution' );
			if ( isset( $governance[ $state ] ) ) {
				$governance[ $state ]++;
			}
			if ( isset( $visibility[ $record_visibility ] ) ) {
				$visibility[ $record_visibility ]++;
			}
		}
		$report = array(
			'schema'            => self::WORKSPACE_SCHEMA,
			'version'           => self::VERSION,
			'total_records'     => $total,
			'institution_count' => absint( $counts['institution']['total'] ?? 0 ),
			'unit_count'        => absint( $counts['unit']['total'] ?? 0 ),
			'document_count'    => absint( $counts['document']['total'] ?? 0 ),
			'project_count'     => absint( $counts['project']['total'] ?? 0 ),
			'publication_count' => absint( $counts['publication']['total'] ?? 0 ),
			'counts'            => $counts,
			'governance'        => $governance,
			'visibility'        => $visibility,
			'health'            => self::health_report( false ),
			'migration'         => self::migration_state(),
			'activity'          => array_slice( (array) get_option( self::OPTION_ACTIVITY, array() ), -100 ),
			'generated_at'      => current_time( 'mysql' ),
		);
		set_transient( self::TRANSIENT_DASHBOARD, $report, 10 * MINUTE_IN_SECONDS );
		return $report;
	}

	public static function build_handoff_envelope( $args = array(), $include_private = true ) {
		$args = wp_parse_args(
			$args,
			array(
				'project_id'     => 0,
				'target_product' => '',
				'handoff_type'   => '',
				'records'        => array(),
				'sections'       => array(),
				'request'        => array(),
			)
		);
		$records = array();
		foreach ( (array) $args['records'] as $ref ) {
			if ( is_numeric( $ref ) ) {
				$record = self::serialize_record( absint( $ref ), $include_private );
			} elseif ( is_array( $ref ) ) {
				$record = self::serialize_record( absint( $ref['id'] ?? 0 ), $include_private );
			} else {
				$record = array();
			}
			if ( $record ) {
				$records[] = $record;
			}
		}
		$project_id = absint( $args['project_id'] );
		if ( $project_id ) {
			$project = self::serialize_record( $project_id, $include_private );
			if ( $project && ! in_array( $project['id'], wp_list_pluck( $records, 'id' ), true ) ) {
				array_unshift( $records, $project );
			}
		}
		$institution_ids = array_values( array_unique( array_filter( array_map( static function ( $record ) { return absint( $record['institution_id'] ?? 0 ); }, $records ) ) ) );
		$unit_ids = array_values( array_unique( array_merge( ...array_map( static function ( $record ) { return self::id_list( $record['unit_ids'] ?? array() ); }, $records ?: array( array() ) ) ) ) );
		$envelope = array(
			'schema'         => self::HANDOFF_SCHEMA,
			'envelope_id'    => wp_generate_uuid4(),
			'source_product' => 'knowledge-library',
			'source_version' => self::VERSION,
			'target_product' => sanitize_key( $args['target_product'] ),
			'handoff_type'   => sanitize_key( $args['handoff_type'] ),
			'project_id'     => $project_id,
			'institutions'   => array_values( array_filter( array_map( array( __CLASS__, 'public_identity' ), $institution_ids ) ) ),
			'units'          => array_values( array_filter( array_map( array( __CLASS__, 'public_identity' ), $unit_ids ) ) ),
			'records'        => $records,
			'sections'       => array_values( array_unique( array_map( 'sanitize_key', (array) $args['sections'] ) ) ),
			'request'        => self::sanitize_recursive( $args['request'] ),
			'health'         => array_intersect_key( self::health_report( false ), array_flip( array( 'status', 'score', 'version' ) ) ),
			'created_at'     => current_time( 'mysql' ),
			'created_by'     => get_current_user_id(),
		);
		$envelope['checksum'] = hash( 'sha256', self::canonical_json( $envelope ) );
		return $envelope;
	}

	public static function create_platform_handoff( $args = array() ) {
		if ( ! current_user_can( 'sc_library_manage_institutional_handoffs' ) ) {
			return new WP_Error( 'institutional_handoff_forbidden', __( 'You cannot create institutional handoffs.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
		}
		$envelope = self::build_handoff_envelope( $args, true );
		$result = array(
			'schema'   => self::HANDOFF_SCHEMA,
			'envelope' => $envelope,
			'handoff'  => array(),
		);
		$project_id = absint( $args['project_id'] ?? 0 );
		if ( $project_id && class_exists( 'SC_Library_Cross_Product_Research_Handoffs' ) && ! empty( $args['target_product'] ) && ! empty( $args['handoff_type'] ) ) {
			$handoff_args = array(
				'target_product' => sanitize_key( $args['target_product'] ),
				'handoff_type'   => sanitize_key( $args['handoff_type'] ),
				'status'         => sanitize_key( $args['status'] ?? 'draft' ),
				'direction'      => 'outbound',
				'sections'       => (array) ( $args['sections'] ?? array() ),
				'request'        => array_merge( self::sanitize_recursive( $args['request'] ?? array() ), array( 'institutional_envelope' => $envelope ) ),
				'issue_token'    => ! empty( $args['issue_token'] ),
			);
			$handoff = SC_Library_Cross_Product_Research_Handoffs::create_handoff( $project_id, $handoff_args );
			if ( is_wp_error( $handoff ) ) {
				return $handoff;
			}
			$result['handoff'] = $handoff;
		}
		self::activity(
			'institutional-handoff-created',
			array(
				'target_product' => $envelope['target_product'],
				'handoff_type'   => $envelope['handoff_type'],
				'project_id'     => $project_id,
				'record_count'   => count( $envelope['records'] ),
			)
		);
		return $result;
	}

	public function render_workspace() {
		if ( ! current_user_can( 'sc_library_read_institutional' ) ) {
			return;
		}
		$report = self::workspace_report();
		$health = $report['health'];
		?>
		<div class="wrap sc-inst-center">
			<p class="sc-inst-kicker"><?php esc_html_e( 'Knowledge Library v4.0.0', 'sustainable-catalyst-library' ); ?></p>
			<h1><?php esc_html_e( 'Connected Institutional Knowledge and Research Platform', 'sustainable-catalyst-library' ); ?></h1>
			<p><?php esc_html_e( 'Operate documents, sources, evidence, projects, semantics, pathways, collections, reviews, publications, exports, federation, and cross-product research handoffs through one institutional registry and workspace.', 'sustainable-catalyst-library' ); ?></p>

			<div class="sc-inst-metrics">
				<article><strong><?php echo esc_html( $report['total_records'] ); ?></strong><span><?php esc_html_e( 'Registered records', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['institution_count'] ); ?></strong><span><?php esc_html_e( 'Institutions', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['unit_count'] ); ?></strong><span><?php esc_html_e( 'Research units', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['document_count'] ); ?></strong><span><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['project_count'] ); ?></strong><span><?php esc_html_e( 'Projects', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $health['score'] ); ?></strong><span><?php esc_html_e( 'Platform health', 'sustainable-catalyst-library' ); ?> /100</span></article>
			</div>

			<section class="sc-inst-health status-<?php echo esc_attr( $health['status'] ); ?>">
				<div>
					<p class="sc-inst-kicker"><?php esc_html_e( 'Platform integrity', 'sustainable-catalyst-library' ); ?></p>
					<h2><?php echo esc_html( ucfirst( $health['status'] ) ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '%1$d of %2$d subsystems and platform checks are healthy.', 'sustainable-catalyst-library' ), (int) round( $health['score'] / 100 * ( count( $health['components'] ) + count( $health['checks'] ) ) ), count( $health['components'] ) + count( $health['checks'] ) ) ); ?></p>
				</div>
				<button type="button" class="button" data-sc-inst-refresh-health><?php esc_html_e( 'Refresh Health', 'sustainable-catalyst-library' ); ?></button>
				<span data-sc-inst-health-status aria-live="polite"></span>
			</section>

			<div class="sc-inst-tools">
				<section class="sc-inst-tool">
					<h2><?php esc_html_e( 'Unified institutional search', 'sustainable-catalyst-library' ); ?></h2>
					<form data-sc-inst-search-form>
						<label><?php esc_html_e( 'Search records', 'sustainable-catalyst-library' ); ?><input type="search" name="query" placeholder="<?php esc_attr_e( 'Documents, projects, claims, pathways, collections…', 'sustainable-catalyst-library' ); ?>"></label>
						<label><?php esc_html_e( 'Record types', 'sustainable-catalyst-library' ); ?><select name="types" multiple size="8"><?php foreach ( self::record_registry() as $type => $definition ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $definition['label'] ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Institution', 'sustainable-catalyst-library' ); ?><select name="institution_id"><option value="0"><?php esc_html_e( 'All institutions', 'sustainable-catalyst-library' ); ?></option><?php foreach ( self::institution_options() as $id => $label ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Search Institutional Records', 'sustainable-catalyst-library' ); ?></button>
					</form>
					<div data-sc-inst-search-results aria-live="polite"></div>
				</section>
				<section class="sc-inst-tool">
					<h2><?php esc_html_e( 'Cross-product institutional handoff', 'sustainable-catalyst-library' ); ?></h2>
					<form data-sc-inst-handoff-form>
						<label><?php esc_html_e( 'Research Project ID', 'sustainable-catalyst-library' ); ?><input type="number" min="1" name="project_id"></label>
						<label><?php esc_html_e( 'Target product', 'sustainable-catalyst-library' ); ?><input type="text" name="target_product" placeholder="research-lab"></label>
						<label><?php esc_html_e( 'Handoff type', 'sustainable-catalyst-library' ); ?><input type="text" name="handoff_type" placeholder="research-context"></label>
						<label><?php esc_html_e( 'Additional record IDs', 'sustainable-catalyst-library' ); ?><input type="text" name="records" placeholder="123, 456"></label>
						<button type="submit" class="button"><?php esc_html_e( 'Create Handoff Envelope', 'sustainable-catalyst-library' ); ?></button>
					</form>
					<div data-sc-inst-handoff-result aria-live="polite"></div>
				</section>
			</div>

			<section class="sc-inst-migration">
				<div>
					<h2><?php esc_html_e( 'Institutional registry migration', 'sustainable-catalyst-library' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '%1$d of %2$d records processed', 'sustainable-catalyst-library' ), $report['migration']['processed'], $report['migration']['total'] ) ); ?></p>
				</div>
				<button type="button" class="button button-primary" data-sc-inst-run-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button>
				<span data-sc-inst-migration-status aria-live="polite"></span>
			</section>

			<div class="sc-inst-section-heading">
				<div><h2><?php esc_html_e( 'Institutional registry', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Published, private, and draft records across the connected Knowledge Library architecture.', 'sustainable-catalyst-library' ); ?></p></div>
				<div><a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::INSTITUTION_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Institution', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::UNIT_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Research Unit', 'sustainable-catalyst-library' ); ?></a></div>
			</div>
			<div class="sc-inst-record-grid">
				<?php foreach ( $report['counts'] as $type => $count ) : ?>
					<article><h3><?php echo esc_html( $count['label'] ); ?></h3><dl><div><dt><?php esc_html_e( 'Published', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $count['published'] ); ?></dd></div><div><dt><?php esc_html_e( 'Private', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $count['private'] ); ?></dd></div><div><dt><?php esc_html_e( 'Draft', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $count['draft'] ); ?></dd></div></dl></article>
				<?php endforeach; ?>
			</div>

			<div class="sc-inst-columns">
				<section>
					<h2><?php esc_html_e( 'Governance states', 'sustainable-catalyst-library' ); ?></h2>
					<?php echo self::render_count_list( $report['governance'], self::governance_states() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
				<section>
					<h2><?php esc_html_e( 'Visibility', 'sustainable-catalyst-library' ); ?></h2>
					<?php echo self::render_count_list( $report['visibility'], self::visibility_options() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_count_list( $counts, $labels ) {
		ob_start();
		echo '<dl class="sc-inst-count-list">';
		foreach ( $counts as $key => $count ) {
			echo '<div><dt>' . esc_html( $labels[ $key ] ?? $key ) . '</dt><dd>' . esc_html( absint( $count ) ) . '</dd></div>';
		}
		echo '</dl>';
		return ob_get_clean();
	}

	public function ajax_run_migration() {
		$this->verify_ajax( 'sc_library_manage_institutional' );
		$this->send_ajax_result( self::run_migration_batch( self::MIGRATION_BATCH ), 'migration' );
	}

	public function ajax_refresh_health() {
		$this->verify_ajax( 'sc_library_view_institutional_health' );
		$this->send_ajax_result( self::health_report( true ), 'health' );
	}

	public function ajax_search() {
		$this->verify_ajax( 'sc_library_read_institutional' );
		$types = array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_POST['types'] ?? '' ) ) ) ) );
		$result = self::institutional_search(
			array(
				'query'          => wp_unslash( $_POST['query'] ?? '' ),
				'types'          => $types,
				'institution_id' => wp_unslash( $_POST['institution_id'] ?? 0 ),
				'limit'          => 50,
			),
			true
		);
		$this->send_ajax_result( $result, 'search' );
	}

	public function ajax_create_handoff() {
		$this->verify_ajax( 'sc_library_manage_institutional_handoffs' );
		$records = self::id_list( wp_unslash( $_POST['records'] ?? array() ) );
		$result = self::create_platform_handoff(
			array(
				'project_id'     => wp_unslash( $_POST['project_id'] ?? 0 ),
				'target_product' => wp_unslash( $_POST['target_product'] ?? '' ),
				'handoff_type'   => wp_unslash( $_POST['handoff_type'] ?? '' ),
				'records'        => $records,
				'sections'       => array( 'project', 'documents', 'sources', 'citations', 'evidence', 'quality', 'institutional' ),
				'request'        => array( 'purpose' => 'institutional-workspace-handoff' ),
			)
		);
		$this->send_ajax_result( $result, 'handoff' );
	}

	private function verify_ajax( $capability ) {
		check_ajax_referer( 'sc_library_institutional_platform_v400', 'nonce' );
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

	public function filter_research_librarian_context( $context, $project_id = 0, $args = array() ) {
		$project_id = absint( $project_id );
		if ( ! $project_id ) {
			return $context;
		}
		$record = self::serialize_record( $project_id, current_user_can( 'sc_library_read_institutional' ) );
		if ( ! $record ) {
			return $context;
		}
		$addition = array(
			'schema'       => self::PLATFORM_SCHEMA,
			'platform'     => self::platform_summary( false ),
			'project'      => $record,
			'institution'  => $record['institution'],
			'units'        => $record['units'],
			'health'       => array_intersect_key( self::health_report( false ), array_flip( array( 'status', 'score', 'version' ) ) ),
			'generated_at' => current_time( 'mysql' ),
		);
		return is_array( $context )
			? array_merge( $context, array( 'institutional_platform' => $addition ) )
			: array( 'institutional_platform' => $addition );
	}

	public function filter_handoff_sections( $sections, $project_id = 0, $args = array() ) {
		$project_id = absint( $project_id );
		if ( ! $project_id ) {
			return $sections;
		}
		$sections = is_array( $sections ) ? $sections : array();
		$sections['institutional_platform'] = self::build_handoff_envelope(
			array(
				'project_id'     => $project_id,
				'target_product' => sanitize_key( $args['target_product'] ?? '' ),
				'handoff_type'   => sanitize_key( $args['handoff_type'] ?? '' ),
				'records'        => array( $project_id ),
				'sections'       => array( 'institutional-registry', 'permissions', 'health' ),
				'request'        => $args['request'] ?? array(),
			),
			current_user_can( 'sc_library_read_institutional' )
		);
		return $sections;
	}

	public function filter_public_api_record( $record, $post_id, $type, $include_private ) {
		if ( ! is_array( $record ) ) {
			return $record;
		}
		$institutional = self::serialize_record( $post_id, false );
		if ( ! $institutional ) {
			return $record;
		}
		$record['institutional'] = array(
			'schema'           => self::RECORD_SCHEMA,
			'urn'              => $institutional['urn'],
			'visibility'       => $institutional['visibility'],
			'governance_state' => $institutional['governance_state'],
			'institution'      => $institutional['institution'],
			'units'            => $institutional['units'],
			'registry_hash'    => $institutional['registry_hash'],
		);
		return $record;
	}

	public function register_rest_routes() {
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/platform',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_platform' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_health' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/registry',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_registry' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_search' ),
				'permission_callback' => array( $this, 'rest_can_search' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/records/(?P<type>[a-z\-]+)/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_record' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/graph',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_graph' ),
				'permission_callback' => array( $this, 'rest_can_graph' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/handoffs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_handoff' ),
				'permission_callback' => static function () {
					return current_user_can( 'sc_library_manage_institutional_handoffs' );
				},
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_dashboard' ),
				'permission_callback' => static function () {
					return current_user_can( 'sc_library_read_institutional' );
				},
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/permissions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_permissions' ),
				'permission_callback' => static function () {
					return current_user_can( 'sc_library_manage_institutional_permissions' );
				},
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/institutional/migration',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_migration_state' ),
					'permission_callback' => static function () {
						return current_user_can( 'sc_library_manage_institutional' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_run_migration' ),
					'permission_callback' => static function () {
						return current_user_can( 'sc_library_manage_institutional' );
					},
				),
			)
		);
	}

	public function rest_platform() {
		return rest_ensure_response( self::platform_summary( current_user_can( 'sc_library_read_institutional' ) ) );
	}

	public function rest_health() {
		$report = self::health_report( false );
		if ( ! current_user_can( 'sc_library_view_institutional_health' ) ) {
			$report = array_intersect_key( $report, array_flip( array( 'schema', 'version', 'status', 'score', 'component_count', 'generated_at' ) ) );
		}
		return rest_ensure_response( $report );
	}

	public function rest_registry() {
		$registry = array();
		foreach ( self::record_registry() as $type => $definition ) {
			if ( empty( $definition['public'] ) && ! current_user_can( 'sc_library_read_institutional' ) ) {
				continue;
			}
			$registry[ $type ] = array(
				'label'     => $definition['label'],
				'post_type' => current_user_can( 'sc_library_read_institutional' ) ? $definition['post_type'] : '',
				'public'    => ! empty( $definition['public'] ),
			);
		}
		return rest_ensure_response(
			array(
				'schema'       => self::REGISTRY_SCHEMA,
				'version'      => self::VERSION,
				'record_types' => $registry,
				'generated_at' => current_time( 'mysql' ),
			)
		);
	}

	public function rest_search( WP_REST_Request $request ) {
		$types = $request->get_param( 'types' );
		if ( is_string( $types ) ) {
			$types = explode( ',', $types );
		}
		return rest_ensure_response(
			self::institutional_search(
				array(
					'query'          => $request->get_param( 'query' ) ?: '',
					'types'          => (array) $types,
					'institution_id' => $request->get_param( 'institution_id' ) ?: 0,
					'unit_id'        => $request->get_param( 'unit_id' ) ?: 0,
					'visibility'     => $request->get_param( 'visibility' ) ?: '',
					'governance'     => $request->get_param( 'governance' ) ?: '',
					'limit'          => $request->get_param( 'limit' ) ?: self::DEFAULT_SEARCH_LIMIT,
					'cursor'         => $request->get_param( 'cursor' ) ?: '',
				),
				current_user_can( 'sc_library_read_institutional' )
			)
		);
	}

	public function rest_record( WP_REST_Request $request ) {
		$type = sanitize_key( $request['type'] );
		$id = absint( $request['id'] );
		if ( $type !== self::type_for_post_type( get_post_type( $id ) ) ) {
			return new WP_Error( 'institutional_record_type_mismatch', __( 'The institutional record type does not match.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		$record = self::serialize_record( $id, current_user_can( 'sc_library_read_institutional' ) );
		return $record
			? rest_ensure_response( $record )
			: new WP_Error( 'institutional_record_not_found', __( 'The institutional record is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
	}

	public function rest_graph( WP_REST_Request $request ) {
		$result = self::knowledge_graph(
			array(
				'kind'  => $request->get_param( 'kind' ),
				'id'    => $request->get_param( 'id' ),
				'depth' => $request->get_param( 'depth' ) ?: 1,
				'limit' => $request->get_param( 'limit' ) ?: self::MAX_GRAPH_NODES,
			),
			current_user_can( 'sc_library_read_institutional' )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_create_handoff( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result = self::create_platform_handoff( is_array( $payload ) ? $payload : array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_dashboard() {
		return rest_ensure_response( self::workspace_report() );
	}

	public function rest_permissions() {
		$roles = array();
		foreach ( wp_roles()->roles as $role_key => $definition ) {
			$role = get_role( $role_key );
			$caps = array();
			foreach ( self::capability_map() as $cap => $label ) {
				$caps[ $cap ] = $role ? $role->has_cap( $cap ) : false;
			}
			$roles[ $role_key ] = array(
				'label'        => translate_user_role( $definition['name'] ),
				'capabilities' => $caps,
			);
		}
		return rest_ensure_response(
			array(
				'schema'       => self::PERMISSION_SCHEMA,
				'version'      => self::VERSION,
				'capabilities' => self::capability_map(),
				'roles'        => $roles,
				'generated_at' => current_time( 'mysql' ),
			)
		);
	}

	public function rest_migration_state() {
		return rest_ensure_response( self::migration_state() );
	}

	public function rest_run_migration( WP_REST_Request $request ) {
		$result = self::run_migration_batch( max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_can_search() {
		$settings = get_option( self::OPTION_PLATFORM, array() );
		return current_user_can( 'sc_library_read_institutional' ) || ! empty( $settings['public_search'] );
	}

	public function rest_can_graph() {
		$settings = get_option( self::OPTION_PLATFORM, array() );
		return current_user_can( 'sc_library_read_institutional' ) || ! empty( $settings['public_graph'] );
	}

	public function harden_rest_responses( $response, $server, $request ) {
		if ( ! $response instanceof WP_REST_Response ) {
			return $response;
		}
		$route = $request->get_route();
		if ( false === strpos( $route, '/sc-library/v1/institutional/' ) ) {
			return $response;
		}
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-SC-Platform-Version', self::VERSION );
		$public_routes = array( '/platform', '/health', '/registry', '/search', '/records/', '/graph' );
		$is_public = false;
		foreach ( $public_routes as $needle ) {
			if ( false !== strpos( $route, '/institutional' . $needle ) ) {
				$is_public = true;
				break;
			}
		}
		if ( $is_public && 'GET' === $request->get_method() && 200 === $response->get_status() && ! current_user_can( 'sc_library_read_institutional' ) ) {
			$data = $response->get_data();
			$etag = '"' . hash( 'sha256', self::canonical_json( self::stable_etag_data( $data ) ) ) . '"';
			$response->header( 'ETag', $etag );
			$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=600' );
			$response->header( 'Vary', 'Accept, Accept-Encoding' );
			if ( trim( (string) $request->get_header( 'if-none-match' ) ) === $etag ) {
				$response->set_status( 304 );
				$response->set_data( null );
			}
		} else {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Vary', 'Cookie, Authorization' );
		}
		return $response;
	}

	public function shortcode_portal( $atts ) {
		try {
			return $this->shortcode_portal_inner( $atts );
		} catch ( \Throwable $error ) {
			error_log(
				'[Sustainable Catalyst Library] Institutional portal recovery: '
				. wp_json_encode(
					array(
						'class'   => get_class( $error ),
						'message' => $error->getMessage(),
						'file'    => wp_basename( $error->getFile() ),
						'line'    => $error->getLine(),
					)
				)
			);

			return $this->render_public_portal_fallback( $atts );
		}
	}

	private static function shortcode_value_is_true( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on', 'compact' ), true );
	}

	private static function compact_record_label( $record ) {
		$title = strtolower( wp_strip_all_tags( (string) ( $record['title'] ?? '' ) ) );
		$labels = array(
			'charter'     => __( 'Institutional charter', 'sustainable-catalyst-library' ),
			'principles'  => __( 'Public commitments', 'sustainable-catalyst-library' ),
			'policy'      => __( 'Institutional policy', 'sustainable-catalyst-library' ),
			'standard'    => __( 'Institutional standard', 'sustainable-catalyst-library' ),
			'architecture'=> __( 'Platform architecture', 'sustainable-catalyst-library' ),
		);
		foreach ( $labels as $needle => $label ) {
			if ( false !== strpos( $title, $needle ) ) {
				return $label;
			}
		}
		return (string) ( $record['type_label'] ?? __( 'Institutional record', 'sustainable-catalyst-library' ) );
	}

	private static function prioritize_compact_records( $records ) {
		$priority_titles = array(
			'sustainable catalyst institutional charter',
			'sustainable catalyst principles and public commitments',
			'sustainable catalyst platform architecture and product taxonomy',
			'evidence, claims, and methodology standard',
			'responsible ai and human review standard',
			'scientific research and reproducibility standard',
		);
		$priorities = array_flip( $priority_titles );
		$prepared = array();
		foreach ( array_values( (array) $records ) as $index => $record ) {
			if ( empty( $record['title'] ) || empty( $record['url'] ) ) {
				continue;
			}
			$normalized = strtolower( trim( wp_strip_all_tags( (string) $record['title'] ) ) );
			$record['_sc_compact_priority'] = isset( $priorities[ $normalized ] ) ? $priorities[ $normalized ] : 1000 + $index;
			$record['_sc_compact_index'] = $index;
			$prepared[] = $record;
		}
		usort(
			$prepared,
			static function ( $left, $right ) {
				$priority = (int) $left['_sc_compact_priority'] <=> (int) $right['_sc_compact_priority'];
				return 0 !== $priority ? $priority : ( (int) $left['_sc_compact_index'] <=> (int) $right['_sc_compact_index'] );
			}
		);
		foreach ( $prepared as &$record ) {
			unset( $record['_sc_compact_priority'], $record['_sc_compact_index'] );
		}
		unset( $record );
		return $prepared;
	}

	private static function render_compact_record_cards( $records ) {
		ob_start();
		?>
		<div class="sc-inst-compact-grid">
			<?php foreach ( $records as $record ) : ?>
				<article class="sc-inst-compact-card">
					<a href="<?php echo esc_url( $record['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open %s', 'sustainable-catalyst-library' ), $record['title'] ) ); ?>">
						<span class="sc-inst-compact-type"><?php echo esc_html( self::compact_record_label( $record ) ); ?></span>
						<strong><?php echo esc_html( $record['title'] ); ?></strong>
						<span class="sc-inst-compact-arrow" aria-hidden="true">→</span>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_compact_public_portal( $records, $featured = 6, $recovered = false ) {
		$records = self::prioritize_compact_records( $records );
		$total = count( $records );
		$featured = max( 1, min( 12, absint( $featured ) ) );
		$primary = array_slice( $records, 0, $featured );
		$additional = array_slice( $records, $featured );
		$state_label = $recovered
			? __( 'Protected server-rendered catalog', 'sustainable-catalyst-library' )
			: __( 'Institutional catalog', 'sustainable-catalyst-library' );

		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		ob_start();
		?>
		<section class="sc-inst-public-portal sc-inst-public-portal--compact<?php echo $recovered ? ' sc-inst-public-portal--recovered' : ''; ?>"<?php echo $recovered ? ' data-sc-library-portal-recovery="4.0.6"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<header class="sc-inst-compact-header">
				<div>
					<p class="sc-inst-kicker"><?php esc_html_e( 'Research Library', 'sustainable-catalyst-library' ); ?></p>
					<h3><?php esc_html_e( 'Public institutional catalog', 'sustainable-catalyst-library' ); ?></h3>
					<p class="sc-inst-compact-description"><?php esc_html_e( 'Charters, standards, methods, and stewardship records.', 'sustainable-catalyst-library' ); ?></p>
				</div>
				<p class="sc-inst-compact-status">
					<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
					<span><?php esc_html_e( 'public records', 'sustainable-catalyst-library' ); ?></span>
					<small><?php echo esc_html( $state_label ); ?></small>
				</p>
			</header>

			<?php if ( $primary ) : ?>
				<?php echo self::render_compact_record_cards( $primary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $additional ) : ?>
					<details class="sc-inst-compact-more">
						<summary>
							<span><?php echo esc_html( sprintf( __( 'Browse all %d institutional records', 'sustainable-catalyst-library' ), $total ) ); ?></span>
							<span class="sc-inst-compact-toggle" aria-hidden="true">+</span>
						</summary>
						<?php echo self::render_compact_record_cards( $additional ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</details>
				<?php endif; ?>
			<?php else : ?>
				<p class="sc-inst-compact-empty"><?php esc_html_e( 'No published research records are currently available.', 'sustainable-catalyst-library' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private function render_public_portal_fallback( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'documents' => 8,
				'units'     => 12,
				'compact'   => 'false',
				'featured'  => 6,
			),
			(array) $atts,
			'sc_institutional_research_portal'
		);
		$compact = self::shortcode_value_is_true( $atts['compact'] );
		$document_limit = max( 1, min( 30, absint( $atts['documents'] ) ) );
		$post_type = post_type_exists( 'sc_foundation_doc' )
			? 'sc_foundation_doc'
			: ( post_type_exists( 'sc_library_document' ) ? 'sc_library_document' : 'post' );

		$ids = get_posts(
			array(
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => $document_limit,
				'fields'              => 'ids',
				'orderby'             => 'modified',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
			)
		);

		if ( $compact ) {
			$records = array();
			foreach ( $ids as $id ) {
				$post_type_object = get_post_type_object( get_post_type( $id ) );
				$records[] = array(
					'title'      => get_the_title( $id ),
					'url'        => get_permalink( $id ),
					'type_label' => $post_type_object && ! empty( $post_type_object->labels->singular_name )
						? $post_type_object->labels->singular_name
						: __( 'Institutional record', 'sustainable-catalyst-library' ),
				);
			}
			return $this->render_compact_public_portal( $records, $atts['featured'], true );
		}

		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		ob_start();
		?>
		<section class="sc-inst-public-portal sc-inst-public-portal--recovered" data-sc-library-portal-recovery="4.0.6">
			<header>
				<p class="sc-inst-kicker"><?php esc_html_e( 'Research Library', 'sustainable-catalyst-library' ); ?></p>
				<h2><?php esc_html_e( 'Published research records', 'sustainable-catalyst-library' ); ?></h2>
				<p><?php esc_html_e( 'The institutional catalog is using its protected server-rendered view.', 'sustainable-catalyst-library' ); ?></p>
			</header>

			<?php if ( $ids ) : ?>
				<div class="sc-inst-public-grid">
					<?php foreach ( $ids as $id ) : ?>
						<article>
							<h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>
							<?php
							$summary = get_the_excerpt( $id );
							if ( '' === trim( (string) $summary ) ) {
								$summary = wp_trim_words(
									wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ),
									32
								);
							}
							?>
							<?php if ( '' !== trim( (string) $summary ) ) : ?>
								<p><?php echo esc_html( $summary ); ?></p>
							<?php endif; ?>
							<p><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php esc_html_e( 'Open record', 'sustainable-catalyst-library' ); ?> →</a></p>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No published research records are currently available.', 'sustainable-catalyst-library' ); ?></p>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function shortcode_portal_inner( $atts ) {
		$settings = get_option( self::OPTION_PLATFORM, array() );
		if ( empty( $settings['public_portal'] ) ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'documents' => 8,
				'units'     => 12,
				'compact'   => 'false',
				'featured'  => 6,
			),
			$atts,
			'sc_institutional_research_portal'
		);
		$compact = self::shortcode_value_is_true( $atts['compact'] );
		$unit_limit = max( 0, min( 50, absint( $atts['units'] ) ) );
		$institutions = $compact ? array() : get_posts(
			array(
				'post_type'      => self::INSTITUTION_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => self::META_INSTITUTION_PUBLIC,
				'meta_value'     => '1',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$units = ( $compact || 0 === $unit_limit ) ? array() : get_posts(
			array(
				'post_type'      => self::UNIT_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $unit_limit,
				'fields'         => 'ids',
				'meta_key'       => self::META_UNIT_PUBLIC,
				'meta_value'     => '1',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$documents = self::institutional_search(
			array(
				'types' => array( 'document', 'publication', 'pathway', 'collection' ),
				'limit' => max( 1, min( 30, absint( $atts['documents'] ) ) ),
			),
			false
		);
		wp_enqueue_style( 'sc-library-connected-institutional-platform' );

		if ( $compact ) {
			return $this->render_compact_public_portal( $documents['records'] ?? array(), $atts['featured'], false );
		}

		ob_start();
		?>
		<section class="sc-inst-public-portal">
			<header>
				<p class="sc-inst-kicker"><?php esc_html_e( 'Connected institutional research', 'sustainable-catalyst-library' ); ?></p>
				<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p><?php esc_html_e( 'Discover public documents, research pathways, institutional collections, reviewed publications, and the units responsible for their stewardship.', 'sustainable-catalyst-library' ); ?></p>
			</header>
			<?php if ( $institutions ) : ?><section><h3><?php esc_html_e( 'Institutions', 'sustainable-catalyst-library' ); ?></h3><div class="sc-inst-public-grid"><?php foreach ( $institutions as $id ) : ?><article><h4><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h4><p><?php echo esc_html( get_post_meta( $id, self::META_INSTITUTION_DESCRIPTION, true ) ); ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
			<?php if ( $units ) : ?><section><h3><?php esc_html_e( 'Research units', 'sustainable-catalyst-library' ); ?></h3><div class="sc-inst-public-grid"><?php foreach ( $units as $id ) : ?><article><h4><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h4><p><?php echo esc_html( get_post_meta( $id, self::META_UNIT_SCOPE, true ) ); ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
			<?php if ( ! empty( $documents['records'] ) ) : ?><section><h3><?php esc_html_e( 'Public research records', 'sustainable-catalyst-library' ); ?></h3><?php echo self::render_public_records( $documents['records'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section><?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public function shortcode_search( $atts ) {
		$atts = shortcode_atts( array( 'query' => '', 'types' => 'document,publication,pathway,collection', 'limit' => 20 ), $atts, 'sc_institutional_search' );
		$types = array_filter( array_map( 'sanitize_key', explode( ',', $atts['types'] ) ) );
		$result = self::institutional_search( array( 'query' => $atts['query'], 'types' => $types, 'limit' => $atts['limit'] ), false );
		if ( empty( $result['records'] ) ) {
			return '';
		}
		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		return '<section class="sc-inst-public-search"><p class="sc-inst-kicker">' . esc_html__( 'Institutional knowledge search', 'sustainable-catalyst-library' ) . '</p><h2>' . esc_html__( 'Research records', 'sustainable-catalyst-library' ) . '</h2>' . self::render_public_records( $result['records'] ) . '</section>';
	}

	public function shortcode_status() {
		$health = self::health_report( false );
		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		ob_start();
		?>
		<section class="sc-inst-public-status status-<?php echo esc_attr( $health['status'] ); ?>">
			<p class="sc-inst-kicker"><?php esc_html_e( 'Institutional platform status', 'sustainable-catalyst-library' ); ?></p>
			<h2><?php echo esc_html( ucfirst( $health['status'] ) ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Platform health score: %d/100', 'sustainable-catalyst-library' ), $health['score'] ) ); ?></p>
			<p><?php echo esc_html( sprintf( __( '%d connected knowledge and research subsystems are registered.', 'sustainable-catalyst-library' ), $health['component_count'] ) ); ?></p>
		</section>
		<?php
		return ob_get_clean();
	}

	public function shortcode_record( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'type' => '' ), $atts, 'sc_institutional_record' );
		$id = absint( $atts['id'] );
		$type = sanitize_key( $atts['type'] );
		if ( ! $id || ( $type && $type !== self::type_for_post_type( get_post_type( $id ) ) ) ) {
			return '';
		}
		$record = self::serialize_record( $id, false );
		if ( ! $record ) {
			return '';
		}
		wp_enqueue_style( 'sc-library-connected-institutional-platform' );
		return '<article class="sc-inst-public-record"><p class="sc-inst-kicker">' . esc_html( $record['type_label'] ) . '</p><h2><a href="' . esc_url( $record['url'] ) . '">' . esc_html( $record['title'] ) . '</a></h2><p>' . esc_html( $record['excerpt'] ) . '</p><dl><div><dt>' . esc_html__( 'Governance', 'sustainable-catalyst-library' ) . '</dt><dd>' . esc_html( self::governance_states()[ $record['governance_state'] ] ?? $record['governance_state'] ) . '</dd></div><div><dt>' . esc_html__( 'Modified', 'sustainable-catalyst-library' ) . '</dt><dd>' . esc_html( $record['modified_at'] ) . '</dd></div><div><dt>URN</dt><dd><code>' . esc_html( $record['urn'] ) . '</code></dd></div></dl></article>';
	}

	private static function render_public_records( $records ) {
		ob_start();
		echo '<ol class="sc-inst-public-record-list">';
		foreach ( $records as $record ) {
			echo '<li><article><p class="sc-inst-record-type">' . esc_html( $record['type_label'] ?? $record['type'] ) . '</p><h3><a href="' . esc_url( $record['url'] ) . '">' . esc_html( $record['title'] ) . '</a></h3><p>' . esc_html( $record['excerpt'] ) . '</p><small>' . esc_html( $record['modified_at'] ) . '</small></article></li>';
		}
		echo '</ol>';
		return ob_get_clean();
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
			return new WP_Error( 'institutional_migration_locked', __( 'Another institutional migration batch is running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
		}
		set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
		$state = self::migration_state();
		$state['status'] = 'running';
		$state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
		try {
			$post_types = self::supported_post_types();
			$statuses = array( 'publish', 'private', 'draft', 'pending', 'future' );
			$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status IN ({$status_placeholders})";
			$state['total'] = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( $post_types, $statuses ) ) ) );

			$query_sql = "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status IN ({$status_placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
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
				$state['updated_at'] = current_time( 'mysql' );
				update_option( self::OPTION_MIGRATION, $state, false );
				delete_transient( self::TRANSIENT_MIGRATION_LOCK );
				self::invalidate_caches();
				return $state;
			}
			$settings = get_option( self::OPTION_PLATFORM, array() );
			$default_institution = absint( $settings['default_institution'] ?? 0 );
			foreach ( $rows as $row ) {
				$post_id = absint( $row['ID'] ?? 0 );
				try {
					$post_type = sanitize_key( $row['post_type'] ?? '' );
					$post_status = sanitize_key( $row['post_status'] ?? '' );
					self::ensure_record_identity( $post_id );
					if ( '' === get_post_meta( $post_id, self::META_VISIBILITY, true ) ) {
						$public_types = array_filter(
							self::record_registry(),
							static function ( $definition ) {
								return ! empty( $definition['public'] );
							}
						);
						$public_post_types = wp_list_pluck( $public_types, 'post_type' );
						if ( self::INSTITUTION_POST_TYPE === $post_type ) {
							$visibility = '1' === get_post_meta( $post_id, self::META_INSTITUTION_PUBLIC, true ) ? 'public' : 'institution';
						} elseif ( self::UNIT_POST_TYPE === $post_type ) {
							$visibility = '1' === get_post_meta( $post_id, self::META_UNIT_PUBLIC, true ) ? 'public' : 'institution';
						} else {
							$visibility = 'publish' === $post_status && in_array( $post_type, $public_post_types, true ) ? 'public' : 'institution';
						}
						update_post_meta( $post_id, self::META_VISIBILITY, $visibility );
					}
					if ( '' === get_post_meta( $post_id, self::META_GOVERNANCE_STATE, true ) ) {
						$governance = 'publish' === $post_status ? 'published' : ( 'private' === $post_status ? 'managed' : 'draft' );
						update_post_meta( $post_id, self::META_GOVERNANCE_STATE, $governance );
					}
					if ( $default_institution && ! get_post_meta( $post_id, self::META_INSTITUTION_ID, true ) && ! in_array( $post_type, array( self::INSTITUTION_POST_TYPE ), true ) ) {
						update_post_meta( $post_id, self::META_INSTITUTION_ID, $default_institution );
					}
					if ( self::INSTITUTION_POST_TYPE === $post_type ) {
						if ( ! get_post_meta( $post_id, self::META_INSTITUTION_STATUS, true ) ) {
							update_post_meta( $post_id, self::META_INSTITUTION_STATUS, 'active' );
						}
					} elseif ( self::UNIT_POST_TYPE === $post_type ) {
						if ( ! get_post_meta( $post_id, self::META_UNIT_STATUS, true ) ) {
							update_post_meta( $post_id, self::META_UNIT_STATUS, 'active' );
						}
						if ( ! get_post_meta( $post_id, self::META_UNIT_TYPE, true ) ) {
							update_post_meta( $post_id, self::META_UNIT_TYPE, 'research-center' );
						}
					}
					self::register_record( $post_id, true );
				} catch ( Throwable $error ) {
					$state['failures'][] = array(
						'post_id' => $post_id,
						'message' => sanitize_text_field( $error->getMessage() ),
						'time'    => current_time( 'mysql' ),
					);
					$state['failures'] = array_slice( $state['failures'], -100 );
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
			self::invalidate_caches();
		} catch ( Throwable $error ) {
			$state['status'] = 'failed';
			$state['last_error'] = sanitize_text_field( $error->getMessage() );
			$state['updated_at'] = current_time( 'mysql' );
			update_option( self::OPTION_MIGRATION, $state, false );
			delete_transient( self::TRANSIENT_MIGRATION_LOCK );
			return new WP_Error( 'institutional_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
		}
		delete_transient( self::TRANSIENT_MIGRATION_LOCK );
		return $state;
	}

	public function cleanup_deleted_record( $post_id ) {
		$post_id = absint( $post_id );
		$post_type = get_post_type( $post_id );
		if ( self::INSTITUTION_POST_TYPE === $post_type || self::UNIT_POST_TYPE === $post_type ) {
			$ids = get_posts(
				array(
					'post_type'      => self::supported_post_types(),
					'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page' => 5000,
					'fields'         => 'ids',
				)
			);
			foreach ( $ids as $id ) {
				if ( $id === $post_id ) {
					continue;
				}
				if ( self::INSTITUTION_POST_TYPE === $post_type && absint( get_post_meta( $id, self::META_INSTITUTION_ID, true ) ) === $post_id ) {
					delete_post_meta( $id, self::META_INSTITUTION_ID );
				}
				if ( self::UNIT_POST_TYPE === $post_type ) {
					$unit_ids = array_values(
						array_filter(
							self::id_list( get_post_meta( $id, self::META_UNIT_IDS, true ) ),
							static function ( $unit_id ) use ( $post_id ) {
								return $unit_id !== $post_id;
							}
						)
					);
					update_post_meta( $id, self::META_UNIT_IDS, $unit_ids );
				}
			}
		}
		self::activity( 'institutional-record-deleted', array( 'post_id' => $post_id, 'post_type' => $post_type ) );
		self::invalidate_caches();
	}

	private static function institution_options() {
		$ids = get_posts(
			array(
				'post_type'      => self::INSTITUTION_POST_TYPE,
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$options = array();
		foreach ( $ids as $id ) {
			$options[ absint( $id ) ] = get_the_title( $id );
		}
		return $options;
	}

	private static function ensure_uuid_meta( $post_id ) {
		$uuid = (string) get_post_meta( $post_id, self::META_UUID, true );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, self::META_UUID, $uuid );
		}
		return $uuid;
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

	private static function parse_key_value_lines( $raw ) {
		$values = array();
		foreach ( preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( $raw ) ) as $line ) {
			if ( false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $key, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$key = sanitize_key( $key );
			if ( $key && $value ) {
				$values[ $key ] = sanitize_text_field( $value );
			}
		}
		return $values;
	}

	private static function key_value_lines( $values ) {
		$lines = array();
		foreach ( (array) $values as $key => $value ) {
			$lines[] = sanitize_key( $key ) . '=' . sanitize_text_field( $value );
		}
		return implode( "\n", $lines );
	}

	private static function encode_cursor( $payload ) {
		return rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
	}

	private static function decode_cursor( $cursor ) {
		$cursor = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $cursor );
		if ( ! $cursor ) {
			return array();
		}
		$padding = strlen( $cursor ) % 4;
		if ( $padding ) {
			$cursor .= str_repeat( '=', 4 - $padding );
		}
		$data = json_decode( base64_decode( strtr( $cursor, '-_', '+/' ), true ), true );
		return is_array( $data ) ? $data : array();
	}

	private static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key = is_int( $key ) ? $key : sanitize_key( $key );
				$clean[ $clean_key ] = self::sanitize_recursive( $item );
			}
			return $clean;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return sanitize_textarea_field( (string) $value );
	}

	private static function canonical_json( $value ) {
		return wp_json_encode( self::recursive_sort( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function recursive_sort( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::recursive_sort( $item );
		}
		return $value;
	}

	private static function stable_etag_data( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( array( 'generated_at', 'etag' ) as $volatile ) {
			unset( $value[ $volatile ] );
		}
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::stable_etag_data( $item );
			}
		}
		return $value;
	}

	private static function activity( $event, $context = array() ) {
		$activity = get_option( self::OPTION_ACTIVITY, array() );
		$activity = is_array( $activity ) ? $activity : array();
		$activity[] = array(
			'activity_id' => wp_generate_uuid4(),
			'event'       => sanitize_text_field( $event ),
			'context'     => self::sanitize_recursive( $context ),
			'user_id'     => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);
		update_option( self::OPTION_ACTIVITY, array_slice( $activity, -self::MAX_ACTIVITY ), false );
	}

	private static function invalidate_caches() {
		delete_transient( self::TRANSIENT_DASHBOARD );
		delete_transient( self::TRANSIENT_HEALTH );
		delete_transient( self::TRANSIENT_REGISTRY );
	}

	private static function migration_state() {
		$state = get_option( self::OPTION_MIGRATION, array() );
		return wp_parse_args( is_array( $state ) ? $state : array(), self::default_migration_state() );
	}

	private static function default_migration_state() {
		return array(
			'schema'       => self::MIGRATION_SCHEMA,
			'version'      => self::VERSION,
			'status'       => 'pending',
			'cursor'       => 0,
			'processed'    => 0,
			'total'        => 0,
			'failures'     => array(),
			'last_error'   => '',
			'started_at'   => '',
			'updated_at'   => '',
			'completed_at' => '',
		);
	}

	private static function register_cli_commands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		WP_CLI::add_command(
			'sc-library institutional health',
			static function () {
				WP_CLI::log( wp_json_encode( self::health_report( true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional registry',
			static function () {
				WP_CLI::log( wp_json_encode( self::record_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional record',
			static function ( $args ) {
				$record = self::serialize_record( absint( $args[0] ?? 0 ), true );
				$record ? WP_CLI::log( wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) : WP_CLI::error( 'Record not found.' );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional search',
			static function ( $args, $assoc ) {
				$result = self::institutional_search(
					array(
						'query' => $assoc['query'] ?? '',
						'types' => isset( $assoc['types'] ) ? explode( ',', $assoc['types'] ) : array(),
						'limit' => absint( $assoc['limit'] ?? self::DEFAULT_SEARCH_LIMIT ),
					),
					true
				);
				WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional graph',
			static function ( $args, $assoc ) {
				$result = self::knowledge_graph(
					array(
						'kind'  => $assoc['kind'] ?? '',
						'id'    => $assoc['id'] ?? 0,
						'depth' => $assoc['depth'] ?? 1,
					),
					true
				);
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional handoff',
			static function ( $args, $assoc ) {
				$result = self::create_platform_handoff(
					array(
						'project_id'     => $assoc['project'] ?? 0,
						'target_product' => $assoc['target'] ?? '',
						'handoff_type'   => $assoc['type'] ?? '',
						'records'        => isset( $assoc['records'] ) ? self::id_list( $assoc['records'] ) : array(),
						'status'         => $assoc['status'] ?? 'draft',
					)
				);
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional migrate',
			static function ( $args, $assoc ) {
				$result = self::run_migration_batch( absint( $assoc['limit'] ?? self::MIGRATION_BATCH ) );
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( wp_json_encode( $result ) );
			}
		);
		WP_CLI::add_command(
			'sc-library institutional dashboard',
			static function () {
				WP_CLI::log( wp_json_encode( self::workspace_report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
	}
}
