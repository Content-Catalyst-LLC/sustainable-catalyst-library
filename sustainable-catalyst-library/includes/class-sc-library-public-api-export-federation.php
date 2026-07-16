<?php
/**
 * Public API, Export, and Federation Hardening.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SC_Library_Public_API_Export_Federation {
	public const VERSION = '3.9.0';
	public const API_NAMESPACE = 'sc-library/v1';
	public const PUBLIC_API_SCHEMA = 'sc-library-public-api/1.0';
	public const CAPABILITY_SCHEMA = 'sc-library-api-capabilities/1.0';
	public const EXPORT_SCHEMA = 'sc-library-export-manifest/1.0';
	public const FEDERATION_SCHEMA = 'sc-library-federation-node/1.0';
	public const PEER_SCHEMA = 'sc-library-federation-peer/1.0';
	public const WEBHOOK_SCHEMA = 'sc-library-signed-webhook/1.0';
	public const IMPORT_SCHEMA = 'sc-library-federation-import/1.0';
	public const AUDIT_SCHEMA = 'sc-library-api-audit/1.0';
	public const HANDOFF_SCHEMA = 'sc-platform-handoff/public-api-export-federation/1.0';

	public const TOKEN_POST_TYPE = 'sc_api_token';
	public const EXPORT_POST_TYPE = 'sc_export_job';
	public const PEER_POST_TYPE = 'sc_federation_peer';
	public const WEBHOOK_POST_TYPE = 'sc_federation_hook';
	public const IMPORT_POST_TYPE = 'sc_federation_import';

	public const OPTION_NODE = 'sc_library_v390_federation_node';
	public const OPTION_MIGRATION = 'sc_library_v390_api_export_migration';
	public const OPTION_AUDIT = 'sc_library_v390_api_audit_log';
	public const OPTION_RATE_LIMITS = 'sc_library_v390_rate_limits';
	public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v390_migration_lock';
	public const TRANSIENT_CAPABILITIES = 'sc_library_v390_capabilities';
	public const TRANSIENT_PUBLIC_CATALOG = 'sc_library_v390_public_catalog';
	public const CRON_MIGRATION = 'sc_library_v390_migration_tick';
	public const CRON_EXPORT = 'sc_library_v390_export_tick';
	public const CRON_WEBHOOK = 'sc_library_v390_webhook_tick';
	public const CRON_PEER = 'sc_library_v390_peer_tick';

	public const META_TOKEN_HASH = '_sc_api_token_hash';
	public const META_TOKEN_PREFIX = '_sc_api_token_prefix';
	public const META_TOKEN_SCOPES = '_sc_api_token_scopes';
	public const META_TOKEN_EXPIRES = '_sc_api_token_expires';
	public const META_TOKEN_REVOKED = '_sc_api_token_revoked';
	public const META_TOKEN_LAST_USED = '_sc_api_token_last_used';
	public const META_TOKEN_RATE_LIMIT = '_sc_api_token_rate_limit';
	public const META_TOKEN_CREATED_BY = '_sc_api_token_created_by';

	public const META_EXPORT_UUID = '_sc_export_uuid';
	public const META_EXPORT_STATUS = '_sc_export_status';
	public const META_EXPORT_FORMAT = '_sc_export_format';
	public const META_EXPORT_SCOPE = '_sc_export_scope';
	public const META_EXPORT_FILTERS = '_sc_export_filters';
	public const META_EXPORT_CURSOR = '_sc_export_cursor';
	public const META_EXPORT_TOTAL = '_sc_export_total';
	public const META_EXPORT_PROCESSED = '_sc_export_processed';
	public const META_EXPORT_RECORDS = '_sc_export_records';
	public const META_EXPORT_MANIFEST = '_sc_export_manifest';
	public const META_EXPORT_FILE = '_sc_export_file';
	public const META_EXPORT_PUBLIC = '_sc_export_public';
	public const META_EXPORT_ERROR = '_sc_export_error';
	public const META_EXPORT_CREATED_AT = '_sc_export_created_at';
	public const META_EXPORT_COMPLETED_AT = '_sc_export_completed_at';
	public const META_EXPORT_EXPIRES_AT = '_sc_export_expires_at';

	public const META_PEER_UUID = '_sc_peer_uuid';
	public const META_PEER_STATUS = '_sc_peer_status';
	public const META_PEER_BASE_URL = '_sc_peer_base_url';
	public const META_PEER_NODE_ID = '_sc_peer_node_id';
	public const META_PEER_TRUST = '_sc_peer_trust';
	public const META_PEER_SCOPES = '_sc_peer_scopes';
	public const META_PEER_PUBLIC_KEY = '_sc_peer_public_key';
	public const META_PEER_SHARED_SECRET = '_sc_peer_shared_secret';
	public const META_PEER_LAST_CHECK = '_sc_peer_last_check';
	public const META_PEER_LAST_SUCCESS = '_sc_peer_last_success';
	public const META_PEER_CAPABILITIES = '_sc_peer_capabilities';
	public const META_PEER_ERROR = '_sc_peer_error';

	public const META_HOOK_UUID = '_sc_hook_uuid';
	public const META_HOOK_STATUS = '_sc_hook_status';
	public const META_HOOK_URL = '_sc_hook_url';
	public const META_HOOK_EVENTS = '_sc_hook_events';
	public const META_HOOK_SECRET = '_sc_hook_secret';
	public const META_HOOK_QUEUE = '_sc_hook_queue';
	public const META_HOOK_FAILURES = '_sc_hook_failures';
	public const META_HOOK_LAST_DELIVERY = '_sc_hook_last_delivery';

	public const META_IMPORT_UUID = '_sc_import_uuid';
	public const META_IMPORT_STATUS = '_sc_import_status';
	public const META_IMPORT_PEER_ID = '_sc_import_peer_id';
	public const META_IMPORT_PAYLOAD = '_sc_import_payload';
	public const META_IMPORT_SCHEMA = '_sc_import_schema';
	public const META_IMPORT_HASH = '_sc_import_hash';
	public const META_IMPORT_VALIDATION = '_sc_import_validation';
	public const META_IMPORT_DECISION = '_sc_import_decision';
	public const META_IMPORT_CREATED_AT = '_sc_import_created_at';

	public const MIGRATION_BATCH = 20;
	public const EXPORT_BATCH = 100;
	public const LOCK_SECONDS = 180;
	public const MAX_EXPORT_RECORDS = 50000;
	public const MAX_AUDIT = 500;
	public const MAX_WEBHOOK_QUEUE = 200;
	public const MAX_IMPORT_BYTES = 5242880;
	public const DEFAULT_RATE_LIMIT = 120;
	public const MAX_RATE_LIMIT = 5000;
	public const TOKEN_BYTES = 32;

	public function __construct() {
		add_action( 'init', array( $this, 'register_record_types' ), 380 );
		add_action( 'init', array( $this, 'schedule_operations' ), 999 );
		add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
		add_action( self::CRON_EXPORT, array( $this, 'run_export_queue' ) );
		add_action( self::CRON_WEBHOOK, array( $this, 'run_webhook_queue' ) );
		add_action( self::CRON_PEER, array( $this, 'check_federation_peers' ) );

		add_action( 'admin_menu', array( $this, 'register_workspace' ), 380 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 280 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 280 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

		add_action( 'save_post_' . self::PEER_POST_TYPE, array( $this, 'save_peer' ), 280, 3 );
		add_action( 'save_post_' . self::WEBHOOK_POST_TYPE, array( $this, 'save_webhook' ), 280, 3 );

		add_action( 'wp_ajax_sc_library_v390_create_token', array( $this, 'ajax_create_token' ) );
		add_action( 'wp_ajax_sc_library_v390_create_export', array( $this, 'ajax_create_export' ) );
		add_action( 'wp_ajax_sc_library_v390_run_export', array( $this, 'ajax_run_export' ) );
		add_action( 'wp_ajax_sc_library_v390_run_migration', array( $this, 'ajax_run_migration' ) );
		add_action( 'wp_ajax_sc_library_v390_check_peer', array( $this, 'ajax_check_peer' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'harden_rest_response' ), 120, 3 );

		add_filter( 'sc_library_research_librarian_project_context', array( $this, 'filter_project_context' ), 80, 3 );
		add_filter( 'sc_library_cross_product_handoff_sections', array( $this, 'filter_handoff_sections' ), 80, 3 );

		add_shortcode( 'sc_library_api_capabilities', array( $this, 'shortcode_capabilities' ) );
		add_shortcode( 'sc_library_public_catalog', array( $this, 'shortcode_public_catalog' ) );
		add_shortcode( 'sc_library_federation_status', array( $this, 'shortcode_federation_status' ) );
		add_shortcode( 'sc_library_export_register', array( $this, 'shortcode_export_register' ) );

		self::register_cli_commands();
	}

	public static function token_scopes() {
		return array(
			'catalog:read'       => __( 'Read public catalog', 'sustainable-catalyst-library' ),
			'documents:read'     => __( 'Read public documents', 'sustainable-catalyst-library' ),
			'projects:read'      => __( 'Read authorized research projects', 'sustainable-catalyst-library' ),
			'exports:create'     => __( 'Create export jobs', 'sustainable-catalyst-library' ),
			'exports:read'       => __( 'Read and download exports', 'sustainable-catalyst-library' ),
			'federation:read'    => __( 'Read federation metadata', 'sustainable-catalyst-library' ),
			'federation:import'  => __( 'Submit quarantined federation imports', 'sustainable-catalyst-library' ),
			'webhooks:manage'    => __( 'Manage signed webhooks', 'sustainable-catalyst-library' ),
			'admin:read'         => __( 'Read administrative API status', 'sustainable-catalyst-library' ),
		);
	}

	public static function export_formats() {
		return array(
			'json'   => 'JSON',
			'jsonld' => 'JSON-LD',
			'ndjson' => 'NDJSON',
			'csv'    => 'CSV',
			'bundle' => __( 'ZIP research bundle', 'sustainable-catalyst-library' ),
		);
	}

	public static function export_scopes() {
		return array(
			'documents'    => __( 'Knowledge Library documents', 'sustainable-catalyst-library' ),
			'projects'     => __( 'Research projects', 'sustainable-catalyst-library' ),
			'sources'      => __( 'Research sources', 'sustainable-catalyst-library' ),
			'pathways'     => __( 'Knowledge pathways', 'sustainable-catalyst-library' ),
			'collections'  => __( 'Institutional collections', 'sustainable-catalyst-library' ),
			'publications' => __( 'Research publication records', 'sustainable-catalyst-library' ),
			'catalog'      => __( 'Complete public catalog', 'sustainable-catalyst-library' ),
		);
	}

	public static function peer_statuses() {
		return array(
			'pending'     => __( 'Pending', 'sustainable-catalyst-library' ),
			'active'      => __( 'Active', 'sustainable-catalyst-library' ),
			'degraded'    => __( 'Degraded', 'sustainable-catalyst-library' ),
			'suspended'   => __( 'Suspended', 'sustainable-catalyst-library' ),
			'blocked'     => __( 'Blocked', 'sustainable-catalyst-library' ),
		);
	}

	public static function trust_levels() {
		return array(
			'untrusted'   => __( 'Untrusted', 'sustainable-catalyst-library' ),
			'discovery'   => __( 'Discovery only', 'sustainable-catalyst-library' ),
			'metadata'    => __( 'Metadata exchange', 'sustainable-catalyst-library' ),
			'verified'    => __( 'Verified institutional peer', 'sustainable-catalyst-library' ),
		);
	}

	public function register_record_types() {
		$types = array(
			self::TOKEN_POST_TYPE => array(
				'name' => __( 'API Tokens', 'sustainable-catalyst-library' ),
				'singular' => __( 'API Token', 'sustainable-catalyst-library' ),
			),
			self::EXPORT_POST_TYPE => array(
				'name' => __( 'Export Jobs', 'sustainable-catalyst-library' ),
				'singular' => __( 'Export Job', 'sustainable-catalyst-library' ),
			),
			self::PEER_POST_TYPE => array(
				'name' => __( 'Federation Peers', 'sustainable-catalyst-library' ),
				'singular' => __( 'Federation Peer', 'sustainable-catalyst-library' ),
			),
			self::WEBHOOK_POST_TYPE => array(
				'name' => __( 'Federation Webhooks', 'sustainable-catalyst-library' ),
				'singular' => __( 'Federation Webhook', 'sustainable-catalyst-library' ),
			),
			self::IMPORT_POST_TYPE => array(
				'name' => __( 'Federation Imports', 'sustainable-catalyst-library' ),
				'singular' => __( 'Federation Import', 'sustainable-catalyst-library' ),
			),
		);

		foreach ( $types as $post_type => $labels ) {
			register_post_type(
				$post_type,
				array(
					'labels' => array(
						'name'          => $labels['name'],
						'singular_name' => $labels['singular'],
					),
					'public'              => false,
					'show_ui'             => in_array( $post_type, array( self::PEER_POST_TYPE, self::WEBHOOK_POST_TYPE ), true ),
					'show_in_menu'        => false,
					'show_in_rest'        => false,
					'supports'            => array( 'title', 'author' ),
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
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_EXPORT ) ) {
			wp_schedule_event( time() + 1200, 'hourly', self::CRON_EXPORT );
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_WEBHOOK ) ) {
			wp_schedule_event( time() + 1500, 'hourly', self::CRON_WEBHOOK );
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_PEER ) ) {
			wp_schedule_event( time() + 1800, 'twicedaily', self::CRON_PEER );
		}
		if ( ! is_array( get_option( self::OPTION_MIGRATION, null ) ) ) {
			update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
		}
		if ( ! is_array( get_option( self::OPTION_NODE, null ) ) ) {
			update_option(
				self::OPTION_NODE,
				array(
					'schema'        => self::FEDERATION_SCHEMA,
					'node_id'       => wp_generate_uuid4(),
					'name'          => get_bloginfo( 'name' ),
					'base_url'      => home_url( '/' ),
					'api_url'       => rest_url( self::API_NAMESPACE ),
					'status'        => 'active',
					'public'        => false,
					'contact'       => '',
					'capabilities'  => array_keys( self::export_formats() ),
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				false
			);
		}
	}

	public function register_workspace() {
		add_submenu_page(
			'sc-library',
			__( 'Public API, Export, and Federation', 'sustainable-catalyst-library' ),
			__( 'API, Export & Federation', 'sustainable-catalyst-library' ),
			'manage_options',
			'sc-library-api-export-federation',
			array( $this, 'render_workspace' )
		);
		add_submenu_page(
			'sc-library',
			__( 'Federation Peers', 'sustainable-catalyst-library' ),
			__( 'Federation Peers', 'sustainable-catalyst-library' ),
			'manage_options',
			'edit.php?post_type=' . self::PEER_POST_TYPE
		);
		add_submenu_page(
			'sc-library',
			__( 'Federation Webhooks', 'sustainable-catalyst-library' ),
			__( 'Federation Webhooks', 'sustainable-catalyst-library' ),
			'manage_options',
			'edit.php?post_type=' . self::WEBHOOK_POST_TYPE
		);
	}

	public function register_meta_boxes() {
		add_meta_box(
			'sc-federation-peer-settings',
			__( 'Federation Peer Settings', 'sustainable-catalyst-library' ),
			array( $this, 'render_peer_meta_box' ),
			self::PEER_POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'sc-federation-webhook-settings',
			__( 'Signed Webhook Settings', 'sustainable-catalyst-library' ),
			array( $this, 'render_webhook_meta_box' ),
			self::WEBHOOK_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$supported = in_array( $screen->post_type, array( self::PEER_POST_TYPE, self::WEBHOOK_POST_TYPE ), true );
		$workspace = false !== strpos( (string) $screen->id, 'sc-library-api-export-federation' );
		if ( ! $supported && ! $workspace ) {
			return;
		}
		$this->register_assets();
		wp_enqueue_style( 'sc-library-api-export-federation' );
		wp_enqueue_script( 'sc-library-api-export-federation' );
		wp_localize_script(
			'sc-library-api-export-federation',
			'SCLibraryAPIFederation',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sc_library_api_federation_v390' ),
				'strings' => array(
					'working'  => __( 'Working…', 'sustainable-catalyst-library' ),
					'complete' => __( 'Operation complete.', 'sustainable-catalyst-library' ),
					'error'    => __( 'The API, export, or federation operation failed.', 'sustainable-catalyst-library' ),
				),
			)
		);
	}

	public function register_public_assets() {
		$this->register_assets();
	}

	private function register_assets() {
		wp_register_style(
			'sc-library-api-export-federation',
			SC_LIBRARY_URL . 'assets/css/sc-library-public-api-export-federation.css',
			array(),
			self::VERSION
		);
		wp_register_script(
			'sc-library-api-export-federation',
			SC_LIBRARY_URL . 'assets/js/sc-library-public-api-export-federation.js',
			array(),
			self::VERSION,
			true
		);
	}

	public static function create_token( $label, $scopes, $expires = '', $rate_limit = self::DEFAULT_RATE_LIMIT ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'api_token_forbidden', __( 'You cannot create API tokens.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
		}
		$allowed = array_keys( self::token_scopes() );
		$scopes = array_values( array_intersect( $allowed, array_map( 'sanitize_text_field', (array) $scopes ) ) );
		if ( ! $scopes ) {
			return new WP_Error( 'api_token_scopes_missing', __( 'At least one valid API scope is required.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		$raw = 'sckl_' . bin2hex( random_bytes( self::TOKEN_BYTES ) );
		$hash = hash( 'sha256', $raw );
		$prefix = substr( $raw, 0, 14 );
		$token_id = wp_insert_post(
			array(
				'post_type'   => self::TOKEN_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $label ) ?: __( 'Knowledge Library API Token', 'sustainable-catalyst-library' ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $token_id ) ) {
			return $token_id;
		}
		update_post_meta( $token_id, self::META_TOKEN_HASH, $hash );
		update_post_meta( $token_id, self::META_TOKEN_PREFIX, $prefix );
		update_post_meta( $token_id, self::META_TOKEN_SCOPES, $scopes );
		update_post_meta( $token_id, self::META_TOKEN_EXPIRES, self::sanitize_datetime( $expires ) );
		update_post_meta( $token_id, self::META_TOKEN_REVOKED, '0' );
		update_post_meta( $token_id, self::META_TOKEN_LAST_USED, '' );
		update_post_meta( $token_id, self::META_TOKEN_RATE_LIMIT, max( 1, min( self::MAX_RATE_LIMIT, absint( $rate_limit ) ) ) );
		update_post_meta( $token_id, self::META_TOKEN_CREATED_BY, get_current_user_id() );
		self::audit( 'token-created', array( 'token_id' => $token_id, 'scopes' => $scopes ) );
		return array(
			'token_id' => $token_id,
			'token'    => $raw,
			'prefix'   => $prefix,
			'scopes'   => $scopes,
			'expires'  => self::sanitize_datetime( $expires ),
		);
	}

	public static function authenticate_request( WP_REST_Request $request, $required_scope = '' ) {
		if ( current_user_can( 'manage_options' ) ) {
			return array( 'type' => 'user', 'user_id' => get_current_user_id(), 'scopes' => array_keys( self::token_scopes() ) );
		}
		$authorization = (string) $request->get_header( 'authorization' );
		if ( ! preg_match( '/^Bearer\s+([A-Za-z0-9_]+)$/i', trim( $authorization ), $matches ) ) {
			return new WP_Error( 'api_authentication_required', __( 'A valid bearer token is required.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) );
		}
		$raw = $matches[1];
		$hash = hash( 'sha256', $raw );
		$ids = get_posts(
			array(
				'post_type'      => self::TOKEN_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_key'       => self::META_TOKEN_HASH,
				'meta_value'     => $hash,
			)
		);
		if ( 1 !== count( $ids ) ) {
			return new WP_Error( 'api_token_invalid', __( 'The bearer token is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) );
		}
		$token_id = absint( $ids[0] );
		if ( '1' === get_post_meta( $token_id, self::META_TOKEN_REVOKED, true ) ) {
			return new WP_Error( 'api_token_revoked', __( 'The bearer token has been revoked.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) );
		}
		$expires = (string) get_post_meta( $token_id, self::META_TOKEN_EXPIRES, true );
		if ( $expires && strtotime( $expires ) <= current_time( 'timestamp' ) ) {
			return new WP_Error( 'api_token_expired', __( 'The bearer token has expired.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) );
		}
		$scopes = (array) get_post_meta( $token_id, self::META_TOKEN_SCOPES, true );
		if ( $required_scope && ! in_array( $required_scope, $scopes, true ) ) {
			return new WP_Error( 'api_scope_forbidden', __( 'The bearer token does not include the required scope.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
		}
		$rate = self::enforce_rate_limit(
			'token:' . $token_id,
			absint( get_post_meta( $token_id, self::META_TOKEN_RATE_LIMIT, true ) ?: self::DEFAULT_RATE_LIMIT )
		);
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		update_post_meta( $token_id, self::META_TOKEN_LAST_USED, current_time( 'mysql' ) );
		return array( 'type' => 'token', 'token_id' => $token_id, 'scopes' => $scopes, 'rate' => $rate );
	}

	public static function enforce_rate_limit( $key, $limit = self::DEFAULT_RATE_LIMIT ) {
		$limit = max( 1, min( self::MAX_RATE_LIMIT, absint( $limit ) ) );
		$window = gmdate( 'YmdHi' );
		$option = get_option( self::OPTION_RATE_LIMITS, array() );
		$option = is_array( $option ) ? $option : array();
		$bucket_key = hash( 'sha256', sanitize_text_field( $key ) . '|' . $window );
		$count = absint( $option[ $bucket_key ] ?? 0 ) + 1;
		$option = array_slice( $option, -500, null, true );
		$option[ $bucket_key ] = $count;
		update_option( self::OPTION_RATE_LIMITS, $option, false );
		if ( $count > $limit ) {
			return new WP_Error(
				'api_rate_limit_exceeded',
				__( 'The API rate limit has been exceeded.', 'sustainable-catalyst-library' ),
				array( 'status' => 429, 'limit' => $limit, 'retry_after' => 60 )
			);
		}
		return array( 'limit' => $limit, 'remaining' => max( 0, $limit - $count ), 'reset_seconds' => 60 );
	}

	public static function capabilities( $public = true ) {
		$cache_key = self::TRANSIENT_CAPABILITIES . ( $public ? '_public' : '_private' );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$node = self::node_data( ! $public );
		$data = array(
			'schema'             => self::CAPABILITY_SCHEMA,
			'api_version'        => '1.0',
			'plugin_version'     => self::VERSION,
			'namespace'          => self::API_NAMESPACE,
			'node_id'            => (string) ( $node['node_id'] ?? '' ),
			'node_name'          => (string) ( $node['name'] ?? get_bloginfo( 'name' ) ),
			'formats'            => array_keys( self::export_formats() ),
			'export_scopes'      => array_keys( self::export_scopes() ),
			'pagination'         => array(
				'type'          => 'opaque-cursor',
				'default_limit' => 25,
				'maximum_limit' => 100,
			),
			'conditional_get'    => array( 'etag' => true, 'last_modified' => true ),
			'content_types'      => array( 'application/json', 'application/ld+json', 'application/x-ndjson', 'text/csv', 'application/zip' ),
			'federation'         => array(
				'discovery'        => true,
				'peer_governance'  => true,
				'import_quarantine'=> true,
				'signed_webhooks'  => true,
			),
			'generated_at'       => current_time( 'mysql' ),
		);
		if ( ! $public ) {
			$data['token_scopes'] = array_keys( self::token_scopes() );
			$data['rate_limit_default'] = self::DEFAULT_RATE_LIMIT;
			$data['rate_limit_maximum'] = self::MAX_RATE_LIMIT;
			$data['import_maximum_bytes'] = self::MAX_IMPORT_BYTES;
		}
		set_transient( $cache_key, $data, 10 * MINUTE_IN_SECONDS );
		return $data;
	}

	public static function node_data( $include_private = false ) {
		$node = get_option( self::OPTION_NODE, array() );
		$node = is_array( $node ) ? $node : array();
		$data = array(
			'schema'       => self::FEDERATION_SCHEMA,
			'node_id'      => (string) ( $node['node_id'] ?? '' ),
			'name'         => (string) ( $node['name'] ?? get_bloginfo( 'name' ) ),
			'base_url'     => (string) ( $node['base_url'] ?? home_url( '/' ) ),
			'api_url'      => (string) ( $node['api_url'] ?? rest_url( self::API_NAMESPACE ) ),
			'status'       => (string) ( $node['status'] ?? 'active' ),
			'public'       => ! empty( $node['public'] ),
			'capabilities' => array_values( (array) ( $node['capabilities'] ?? array_keys( self::export_formats() ) ) ),
			'updated_at'   => (string) ( $node['updated_at'] ?? '' ),
		);
		if ( $include_private ) {
			$data['contact'] = (string) ( $node['contact'] ?? '' );
			$data['created_at'] = (string) ( $node['created_at'] ?? '' );
		}
		return $data;
	}

	public static function public_catalog( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'  => 25,
				'cursor' => '',
				'type'   => 'documents',
				'search' => '',
			)
		);
		$limit = max( 1, min( 100, absint( $args['limit'] ) ) );
		$type = sanitize_key( $args['type'] );
		$cursor = self::decode_cursor( $args['cursor'] );
		$after_id = absint( $cursor['after_id'] ?? 0 );
		$search = sanitize_text_field( $args['search'] );

		$post_type_map = array(
			'documents'    => class_exists( 'SC_Library_Foundation_Pages' ) ? SC_Library_Foundation_Pages::POST_TYPE : 'sc_foundation_doc',
			'projects'     => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE : 'sc_research_project',
			'sources'      => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE : 'sc_research_source',
			'pathways'     => class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : 'sc_knowledge_pathway',
			'collections'  => class_exists( 'SC_Library_Institutional_Collections_Archives' ) ? SC_Library_Institutional_Collections_Archives::COLLECTION_POST_TYPE : 'sc_inst_collection',
			'publications' => class_exists( 'SC_Library_Collaborative_Review_Publishing' ) ? SC_Library_Collaborative_Review_Publishing::PACKAGE_POST_TYPE : 'sc_pub_package',
		);
		if ( ! isset( $post_type_map[ $type ] ) ) {
			$type = 'documents';
		}
		$post_type = $post_type_map[ $type ];
		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit + 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			's'              => $search,
		);
		if ( $after_id ) {
			$query_args['post__not_in'] = range( 1, $after_id );
		}
		$ids = array_map( 'absint', (array) get_posts( $query_args ) );
		$has_more = count( $ids ) > $limit;
		$ids = array_slice( $ids, 0, $limit );
		$records = array();
		foreach ( $ids as $id ) {
			$record = self::serialize_record( $id, $type, false );
			if ( $record ) {
				$records[] = $record;
			}
		}
		$last_id = $ids ? end( $ids ) : $after_id;
		$generated = current_time( 'mysql' );
		$payload = array(
			'schema'      => self::PUBLIC_API_SCHEMA,
			'type'        => $type,
			'count'       => count( $records ),
			'limit'       => $limit,
			'next_cursor' => $has_more ? self::encode_cursor( array( 'after_id' => $last_id, 'type' => $type ) ) : '',
			'records'     => $records,
			'generated_at'=> $generated,
		);
		$payload['etag'] = '"' . hash( 'sha256', self::canonical_json( self::stable_etag_data( $payload ) ) ) . '"';
		return $payload;
	}

	public static function serialize_record( $post_id, $type = 'documents', $include_private = false ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}
		$record = array(
			'@id'          => get_permalink( $post_id ),
			'id'           => $post_id,
			'type'         => sanitize_key( $type ),
			'title'        => html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'slug'         => (string) $post->post_name,
			'url'          => get_permalink( $post_id ),
			'excerpt'      => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'published_at' => get_post_time( DATE_ATOM, true, $post_id ),
			'modified_at'  => get_post_modified_time( DATE_ATOM, true, $post_id ),
			'content_hash' => hash( 'sha256', (string) get_the_title( $post_id ) . "\n" . (string) get_post_field( 'post_content', $post_id ) ),
		);

		if ( 'documents' === $type ) {
			$record['topics'] = self::public_terms( $post_id, 'sc_library_topic' );
			$record['collections'] = self::public_terms( $post_id, 'sc_library_collection' );
			$record['source_file'] = self::public_source_file( $post_id );
			$intelligence = get_post_meta( $post_id, '_sc_document_intelligence_profile', true );
			if ( is_array( $intelligence ) ) {
				$record['document_intelligence'] = array(
					'status'     => (string) ( $intelligence['status'] ?? '' ),
					'summary'    => (string) ( $intelligence['summary'] ?? '' ),
					'key_points' => array_values( (array) ( $intelligence['key_points'] ?? array() ) ),
					'analyzed_at'=> (string) ( $intelligence['analyzed_at'] ?? '' ),
				);
			}
		} elseif ( 'publications' === $type && class_exists( 'SC_Library_Collaborative_Review_Publishing' ) ) {
			if ( ! SC_Library_Collaborative_Review_Publishing::package_data( $post_id, false ) ) {
				return array();
			}
			$package = SC_Library_Collaborative_Review_Publishing::package_data( $post_id, false );
			if ( 'published' !== ( $package['status'] ?? '' ) || empty( $package['public'] ) ) {
				return array();
			}
			$record['version'] = (string) ( $package['version'] ?? '' );
			$record['license'] = (string) ( $package['license'] ?? '' );
			$record['doi'] = (string) ( $package['doi'] ?? '' );
			$record['canonical_url'] = (string) ( $package['canonical_url'] ?? '' );
		}
		if ( $include_private && current_user_can( 'edit_post', $post_id ) ) {
			$record['status'] = (string) $post->post_status;
			$record['author_id'] = absint( $post->post_author );
			$record['raw_content'] = (string) $post->post_content;
		}
		return apply_filters( 'sc_library_public_api_record', $record, $post_id, $type, $include_private );
	}

	private static function public_terms( $post_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static function ( $term ) {
				return array(
					'id'   => absint( $term->term_id ),
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			},
			(array) $terms
		);
	}

	private static function public_source_file( $post_id ) {
		if ( ! class_exists( 'SC_Library_PDF_To_Document' ) ) {
			return array();
		}
		$attachment_id = absint( get_post_meta( $post_id, SC_Library_PDF_To_Document::META_PDF_ID, true ) );
		if ( ! $attachment_id ) {
			return array();
		}
		return array(
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'mime_type'     => get_post_mime_type( $attachment_id ),
		);
	}

	private static function encode_cursor( $payload ) {
		$json = wp_json_encode( $payload );
		return rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	private static function decode_cursor( $cursor ) {
		$cursor = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $cursor );
		if ( '' === $cursor ) {
			return array();
		}
		$padding = strlen( $cursor ) % 4;
		if ( $padding ) {
			$cursor .= str_repeat( '=', 4 - $padding );
		}
		$data = json_decode( base64_decode( strtr( $cursor, '-_', '+/' ), true ), true );
		return is_array( $data ) ? $data : array();
	}

	public static function create_export_job( $args = array(), $actor = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'format'  => 'json',
				'scope'   => 'documents',
				'filters' => array(),
				'public'  => false,
				'expires' => '',
			)
		);
		$format = self::allowed_value( $args['format'], self::export_formats(), 'json' );
		$scope = self::allowed_value( $args['scope'], self::export_scopes(), 'documents' );
		$public = rest_sanitize_boolean( $args['public'] ) && current_user_can( 'manage_options' );
		$job_id = wp_insert_post(
			array(
				'post_type'   => self::EXPORT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					__( '%1$s export — %2$s', 'sustainable-catalyst-library' ),
					strtoupper( $format ),
					current_time( 'Y-m-d H:i:s' )
				),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}
		update_post_meta( $job_id, self::META_EXPORT_UUID, wp_generate_uuid4() );
		update_post_meta( $job_id, self::META_EXPORT_STATUS, 'queued' );
		update_post_meta( $job_id, self::META_EXPORT_FORMAT, $format );
		update_post_meta( $job_id, self::META_EXPORT_SCOPE, $scope );
		update_post_meta( $job_id, self::META_EXPORT_FILTERS, self::sanitize_export_filters( $args['filters'] ) );
		update_post_meta( $job_id, self::META_EXPORT_CURSOR, 0 );
		update_post_meta( $job_id, self::META_EXPORT_TOTAL, 0 );
		update_post_meta( $job_id, self::META_EXPORT_PROCESSED, 0 );
		update_post_meta( $job_id, self::META_EXPORT_RECORDS, array() );
		update_post_meta( $job_id, self::META_EXPORT_PUBLIC, $public ? '1' : '0' );
		update_post_meta( $job_id, self::META_EXPORT_CREATED_AT, current_time( 'mysql' ) );
		update_post_meta( $job_id, self::META_EXPORT_EXPIRES_AT, self::sanitize_datetime( $args['expires'] ) );
		self::audit( 'export-created', array( 'job_id' => $job_id, 'format' => $format, 'scope' => $scope, 'actor' => $actor ) );
		return self::export_job_data( $job_id, true );
	}

	public static function run_export_batch( $job_id, $limit = self::EXPORT_BATCH ) {
		$job_id = absint( $job_id );
		if ( self::EXPORT_POST_TYPE !== get_post_type( $job_id ) ) {
			return new WP_Error( 'export_job_invalid', __( 'The export job is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		$status = (string) get_post_meta( $job_id, self::META_EXPORT_STATUS, true );
		if ( in_array( $status, array( 'complete', 'expired', 'cancelled' ), true ) ) {
			return self::export_job_data( $job_id, true );
		}
		$scope = (string) get_post_meta( $job_id, self::META_EXPORT_SCOPE, true ) ?: 'documents';
		$filters = get_post_meta( $job_id, self::META_EXPORT_FILTERS, true );
		$filters = is_array( $filters ) ? $filters : array();
		$cursor = absint( get_post_meta( $job_id, self::META_EXPORT_CURSOR, true ) );
		$processed = absint( get_post_meta( $job_id, self::META_EXPORT_PROCESSED, true ) );
		$records = get_post_meta( $job_id, self::META_EXPORT_RECORDS, true );
		$records = is_array( $records ) ? $records : array();
		$limit = max( 1, min( 500, absint( $limit ) ) );

		update_post_meta( $job_id, self::META_EXPORT_STATUS, 'running' );
		try {
			$batch = self::export_record_batch( $scope, $cursor, $limit, $filters );
			if ( $batch['total'] > self::MAX_EXPORT_RECORDS ) {
				throw new RuntimeException( __( 'The export exceeds the configured record limit.', 'sustainable-catalyst-library' ) );
			}
			foreach ( $batch['records'] as $record ) {
				$records[] = $record;
			}
			$processed += count( $batch['records'] );
			$cursor = absint( $batch['cursor'] );
			update_post_meta( $job_id, self::META_EXPORT_TOTAL, absint( $batch['total'] ) );
			update_post_meta( $job_id, self::META_EXPORT_PROCESSED, $processed );
			update_post_meta( $job_id, self::META_EXPORT_CURSOR, $cursor );
			update_post_meta( $job_id, self::META_EXPORT_RECORDS, $records );

			if ( empty( $batch['has_more'] ) ) {
				$result = self::finalize_export( $job_id, $records );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		} catch ( Throwable $error ) {
			update_post_meta( $job_id, self::META_EXPORT_STATUS, 'failed' );
			update_post_meta( $job_id, self::META_EXPORT_ERROR, sanitize_text_field( $error->getMessage() ) );
			self::audit( 'export-failed', array( 'job_id' => $job_id, 'message' => $error->getMessage() ) );
			return new WP_Error( 'export_job_failed', $error->getMessage(), array( 'status' => 500 ) );
		}
		return self::export_job_data( $job_id, true );
	}

	private static function export_record_batch( $scope, $cursor, $limit, $filters ) {
		$type = 'catalog' === $scope ? 'documents' : $scope;
		$post_types = self::scope_post_types( $scope );
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit + 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		if ( $cursor ) {
			$args['post__not_in'] = range( 1, $cursor );
		}
		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = $filters['search'];
		}
		if ( ! empty( $filters['modified_after'] ) ) {
			$args['date_query'] = array(
				array(
					'column'    => 'post_modified_gmt',
					'after'     => $filters['modified_after'],
					'inclusive' => true,
				),
			);
		}
		$ids = array_map( 'absint', (array) get_posts( $args ) );
		$has_more = count( $ids ) > $limit;
		$ids = array_slice( $ids, 0, $limit );
		$records = array();
		foreach ( $ids as $id ) {
			$record_type = self::type_for_post( get_post_type( $id ) );
			$record = self::serialize_record( $id, $record_type ?: $type, false );
			if ( $record ) {
				$records[] = $record;
			}
		}
		$total = absint(
			wp_count_posts(
				is_array( $post_types ) ? reset( $post_types ) : $post_types
			)->publish ?? 0
		);
		return array(
			'records'  => $records,
			'cursor'   => $ids ? end( $ids ) : $cursor,
			'has_more' => $has_more,
			'total'    => min( self::MAX_EXPORT_RECORDS, $total ),
		);
	}

	private static function scope_post_types( $scope ) {
		$map = array(
			'documents'    => class_exists( 'SC_Library_Foundation_Pages' ) ? SC_Library_Foundation_Pages::POST_TYPE : 'sc_foundation_doc',
			'projects'     => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE : 'sc_research_project',
			'sources'      => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE : 'sc_research_source',
			'pathways'     => class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : 'sc_knowledge_pathway',
			'collections'  => class_exists( 'SC_Library_Institutional_Collections_Archives' ) ? SC_Library_Institutional_Collections_Archives::COLLECTION_POST_TYPE : 'sc_inst_collection',
			'publications' => class_exists( 'SC_Library_Collaborative_Review_Publishing' ) ? SC_Library_Collaborative_Review_Publishing::PACKAGE_POST_TYPE : 'sc_pub_package',
		);
		if ( 'catalog' === $scope ) {
			return array_values( $map );
		}
		return $map[ $scope ] ?? $map['documents'];
	}

	private static function type_for_post( $post_type ) {
		$types = array(
			'documents'    => self::scope_post_types( 'documents' ),
			'projects'     => self::scope_post_types( 'projects' ),
			'sources'      => self::scope_post_types( 'sources' ),
			'pathways'     => self::scope_post_types( 'pathways' ),
			'collections'  => self::scope_post_types( 'collections' ),
			'publications' => self::scope_post_types( 'publications' ),
		);
		foreach ( $types as $name => $mapped ) {
			if ( $post_type === $mapped ) {
				return $name;
			}
		}
		return '';
	}

	private static function finalize_export( $job_id, $records ) {
		$format = (string) get_post_meta( $job_id, self::META_EXPORT_FORMAT, true ) ?: 'json';
		$scope = (string) get_post_meta( $job_id, self::META_EXPORT_SCOPE, true ) ?: 'documents';
		$records = array_values( (array) $records );
		usort(
			$records,
			static function ( $a, $b ) {
				return absint( $a['id'] ?? 0 ) <=> absint( $b['id'] ?? 0 );
			}
		);

		$manifest = array(
			'schema'        => self::EXPORT_SCHEMA,
			'export_id'     => absint( $job_id ),
			'uuid'          => (string) get_post_meta( $job_id, self::META_EXPORT_UUID, true ),
			'format'        => $format,
			'scope'         => $scope,
			'record_count'  => count( $records ),
			'generated_at'  => current_time( 'mysql' ),
			'api_version'   => '1.0',
			'plugin_version'=> self::VERSION,
			'node'          => self::node_data( false ),
			'record_hashes' => array_map(
				static function ( $record ) {
					return array(
						'id'     => absint( $record['id'] ?? 0 ),
						'sha256' => hash( 'sha256', self::canonical_json( $record ) ),
					);
				},
				$records
			),
		);
		$manifest['records_sha256'] = hash( 'sha256', self::canonical_json( $records ) );
		$manifest['manifest_sha256'] = hash( 'sha256', self::canonical_json( $manifest ) );

		$path = self::write_export_file( $job_id, $format, $scope, $records, $manifest );
		if ( is_wp_error( $path ) ) {
			update_post_meta( $job_id, self::META_EXPORT_STATUS, 'failed' );
			update_post_meta( $job_id, self::META_EXPORT_ERROR, $path->get_error_message() );
			return $path;
		}
		update_post_meta( $job_id, self::META_EXPORT_MANIFEST, $manifest );
		update_post_meta( $job_id, self::META_EXPORT_FILE, $path );
		update_post_meta( $job_id, self::META_EXPORT_STATUS, 'complete' );
		update_post_meta( $job_id, self::META_EXPORT_COMPLETED_AT, current_time( 'mysql' ) );
		delete_post_meta( $job_id, self::META_EXPORT_ERROR );
		self::audit( 'export-complete', array( 'job_id' => $job_id, 'format' => $format, 'records' => count( $records ) ) );
		self::queue_event( 'export.completed', array( 'job_id' => $job_id, 'manifest' => $manifest ) );
		return $path;
	}

	private static function write_export_file( $job_id, $format, $scope, $records, $manifest ) {
		$directory = self::export_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$basename = 'sc-library-' . sanitize_file_name( $scope ) . '-' . absint( $job_id );
		$extension = 'bundle' === $format ? 'zip' : ( 'jsonld' === $format ? 'jsonld' : $format );
		$path = trailingslashit( $directory ) . $basename . '.' . $extension;

		if ( 'json' === $format ) {
			$content = wp_json_encode( array( 'manifest' => $manifest, 'records' => $records ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} elseif ( 'jsonld' === $format ) {
			$content = wp_json_encode(
				array(
					'@context' => array(
						'@vocab' => 'https://schema.org/',
						'sc'     => home_url( '/ns/knowledge-library/' ),
					),
					'@type'    => 'DataCatalog',
					'name'     => get_bloginfo( 'name' ) . ' Knowledge Library Export',
					'dataset'  => $records,
					'manifest' => $manifest,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		} elseif ( 'ndjson' === $format ) {
			$lines = array( wp_json_encode( array( '_manifest' => $manifest ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			foreach ( $records as $record ) {
				$lines[] = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
			$content = implode( "\n", $lines ) . "\n";
		} elseif ( 'csv' === $format ) {
			$stream = fopen( 'php://temp', 'w+' );
			fputcsv( $stream, array( 'id', 'type', 'title', 'slug', 'url', 'published_at', 'modified_at', 'content_hash' ) );
			foreach ( $records as $record ) {
				fputcsv(
					$stream,
					array(
						$record['id'] ?? '',
						$record['type'] ?? '',
						$record['title'] ?? '',
						$record['slug'] ?? '',
						$record['url'] ?? '',
						$record['published_at'] ?? '',
						$record['modified_at'] ?? '',
						$record['content_hash'] ?? '',
					)
				);
			}
			rewind( $stream );
			$content = stream_get_contents( $stream );
			fclose( $stream );
		} elseif ( 'bundle' === $format ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return new WP_Error( 'ziparchive_unavailable', __( 'ZipArchive is required for bundle exports.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				return new WP_Error( 'export_bundle_open_failed', __( 'The export bundle could not be created.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
			}
			$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			$zip->addFromString( 'records.json', wp_json_encode( $records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			$zip->addFromString( 'records.ndjson', implode( "\n", array_map( 'wp_json_encode', $records ) ) . "\n" );
			$zip->addFromString( 'README.txt', "Sustainable Catalyst Knowledge Library export\nSchema: " . self::EXPORT_SCHEMA . "\nVerify SHA-256 hashes in manifest.json before use.\n" );
			$zip->close();
			return $path;
		} else {
			return new WP_Error( 'export_format_unsupported', __( 'The requested export format is unsupported.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		if ( false === file_put_contents( $path, $content, LOCK_EX ) ) {
			return new WP_Error( 'export_write_failed', __( 'The export file could not be written.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
		}
		return $path;
	}

	private static function export_directory() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'upload_directory_unavailable', $uploads['error'], array( 'status' => 500 ) );
		}
		$directory = trailingslashit( $uploads['basedir'] ) . 'sc-library-private-exports';
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'export_directory_failed', __( 'The private export directory could not be created.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
		}
		if ( ! file_exists( trailingslashit( $directory ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $directory ) . 'index.php', "<?php\n// Silence is golden.\n", LOCK_EX );
		}
		if ( ! file_exists( trailingslashit( $directory ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $directory ) . '.htaccess', "Deny from all\n", LOCK_EX );
		}
		return $directory;
	}

	public static function export_job_data( $job_id, $include_private = false ) {
		if ( self::EXPORT_POST_TYPE !== get_post_type( $job_id ) ) {
			return array();
		}
		$data = array(
			'schema'       => self::EXPORT_SCHEMA,
			'job_id'       => absint( $job_id ),
			'uuid'         => (string) get_post_meta( $job_id, self::META_EXPORT_UUID, true ),
			'title'        => get_the_title( $job_id ),
			'status'       => (string) get_post_meta( $job_id, self::META_EXPORT_STATUS, true ),
			'format'       => (string) get_post_meta( $job_id, self::META_EXPORT_FORMAT, true ),
			'scope'        => (string) get_post_meta( $job_id, self::META_EXPORT_SCOPE, true ),
			'total'        => absint( get_post_meta( $job_id, self::META_EXPORT_TOTAL, true ) ),
			'processed'    => absint( get_post_meta( $job_id, self::META_EXPORT_PROCESSED, true ) ),
			'public'       => '1' === get_post_meta( $job_id, self::META_EXPORT_PUBLIC, true ),
			'created_at'   => (string) get_post_meta( $job_id, self::META_EXPORT_CREATED_AT, true ),
			'completed_at' => (string) get_post_meta( $job_id, self::META_EXPORT_COMPLETED_AT, true ),
			'expires_at'   => (string) get_post_meta( $job_id, self::META_EXPORT_EXPIRES_AT, true ),
		);
		if ( $include_private ) {
			$data['filters'] = (array) get_post_meta( $job_id, self::META_EXPORT_FILTERS, true );
			$data['cursor'] = absint( get_post_meta( $job_id, self::META_EXPORT_CURSOR, true ) );
			$data['manifest'] = (array) get_post_meta( $job_id, self::META_EXPORT_MANIFEST, true );
			$data['file'] = (string) get_post_meta( $job_id, self::META_EXPORT_FILE, true );
			$data['error'] = (string) get_post_meta( $job_id, self::META_EXPORT_ERROR, true );
		}
		return $data;
	}

	private static function sanitize_export_filters( $filters ) {
		$filters = is_array( $filters ) ? $filters : array();
		return array(
			'search'         => sanitize_text_field( $filters['search'] ?? '' ),
			'modified_after' => self::sanitize_datetime( $filters['modified_after'] ?? '' ),
		);
	}

	private static function canonical_json( $value ) {
		$value = self::recursive_sort( $value );
		return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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

	public function run_export_queue() {
		$ids = get_posts(
			array(
				'post_type'      => self::EXPORT_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META_EXPORT_STATUS,
						'value'   => array( 'queued', 'running' ),
						'compare' => 'IN',
					),
				),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $ids as $id ) {
			self::run_export_batch( $id, self::EXPORT_BATCH );
		}
	}

	public static function peer_data( $peer_id, $include_private = false ) {
		if ( self::PEER_POST_TYPE !== get_post_type( $peer_id ) ) {
			return array();
		}
		$data = array(
			'schema'        => self::PEER_SCHEMA,
			'peer_id'       => absint( $peer_id ),
			'uuid'          => self::ensure_uuid( $peer_id, self::META_PEER_UUID ),
			'name'          => get_the_title( $peer_id ),
			'status'        => (string) get_post_meta( $peer_id, self::META_PEER_STATUS, true ) ?: 'pending',
			'base_url'      => (string) get_post_meta( $peer_id, self::META_PEER_BASE_URL, true ),
			'node_id'       => (string) get_post_meta( $peer_id, self::META_PEER_NODE_ID, true ),
			'trust'         => (string) get_post_meta( $peer_id, self::META_PEER_TRUST, true ) ?: 'untrusted',
			'scopes'        => array_values( (array) get_post_meta( $peer_id, self::META_PEER_SCOPES, true ) ),
			'last_check'    => (string) get_post_meta( $peer_id, self::META_PEER_LAST_CHECK, true ),
			'last_success'  => (string) get_post_meta( $peer_id, self::META_PEER_LAST_SUCCESS, true ),
			'capabilities'  => (array) get_post_meta( $peer_id, self::META_PEER_CAPABILITIES, true ),
			'error'         => (string) get_post_meta( $peer_id, self::META_PEER_ERROR, true ),
		);
		if ( $include_private ) {
			$data['public_key'] = (string) get_post_meta( $peer_id, self::META_PEER_PUBLIC_KEY, true );
			$data['has_shared_secret'] = '' !== (string) get_post_meta( $peer_id, self::META_PEER_SHARED_SECRET, true );
		}
		return $data;
	}

	public static function check_peer( $peer_id ) {
		$peer_id = absint( $peer_id );
		if ( self::PEER_POST_TYPE !== get_post_type( $peer_id ) ) {
			return new WP_Error( 'federation_peer_invalid', __( 'The federation peer is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		$status = (string) get_post_meta( $peer_id, self::META_PEER_STATUS, true ) ?: 'pending';
		if ( in_array( $status, array( 'suspended', 'blocked' ), true ) ) {
			return new WP_Error( 'federation_peer_disabled', __( 'The federation peer is disabled.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
		}
		$base_url = esc_url_raw( get_post_meta( $peer_id, self::META_PEER_BASE_URL, true ) );
		if ( ! self::safe_federation_url( $base_url ) ) {
			update_post_meta( $peer_id, self::META_PEER_STATUS, 'degraded' );
			update_post_meta( $peer_id, self::META_PEER_ERROR, __( 'The peer URL is invalid or unsafe.', 'sustainable-catalyst-library' ) );
			return new WP_Error( 'federation_peer_url_unsafe', __( 'The peer URL is invalid or unsafe.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		$url = trailingslashit( $base_url ) . 'wp-json/' . self::API_NAMESPACE . '/capabilities';
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
				'user-agent'  => 'Sustainable-Catalyst-Knowledge-Library/' . self::VERSION,
			)
		);
		update_post_meta( $peer_id, self::META_PEER_LAST_CHECK, current_time( 'mysql' ) );
		if ( is_wp_error( $response ) ) {
			update_post_meta( $peer_id, self::META_PEER_STATUS, 'degraded' );
			update_post_meta( $peer_id, self::META_PEER_ERROR, sanitize_text_field( $response->get_error_message() ) );
			self::audit( 'peer-check-failed', array( 'peer_id' => $peer_id, 'message' => $response->get_error_message() ) );
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) || self::CAPABILITY_SCHEMA !== ( $body['schema'] ?? '' ) ) {
			update_post_meta( $peer_id, self::META_PEER_STATUS, 'degraded' );
			update_post_meta( $peer_id, self::META_PEER_ERROR, __( 'The peer returned an incompatible capability document.', 'sustainable-catalyst-library' ) );
			return new WP_Error( 'federation_peer_incompatible', __( 'The peer returned an incompatible capability document.', 'sustainable-catalyst-library' ), array( 'status' => 502 ) );
		}
		update_post_meta( $peer_id, self::META_PEER_NODE_ID, sanitize_text_field( $body['node_id'] ?? '' ) );
		update_post_meta( $peer_id, self::META_PEER_CAPABILITIES, $body );
		update_post_meta( $peer_id, self::META_PEER_STATUS, 'active' );
		update_post_meta( $peer_id, self::META_PEER_LAST_SUCCESS, current_time( 'mysql' ) );
		delete_post_meta( $peer_id, self::META_PEER_ERROR );
		self::audit( 'peer-check-success', array( 'peer_id' => $peer_id, 'node_id' => $body['node_id'] ?? '' ) );
		return self::peer_data( $peer_id, true );
	}

	public function check_federation_peers() {
		$ids = get_posts(
			array(
				'post_type'      => self::PEER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META_PEER_STATUS,
						'value'   => array( 'pending', 'active', 'degraded' ),
						'compare' => 'IN',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			self::check_peer( $id );
		}
	}

	public static function queue_event( $event, $payload ) {
		$hook_ids = get_posts(
			array(
				'post_type'      => self::WEBHOOK_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => self::META_HOOK_STATUS,
				'meta_value'     => 'active',
			)
		);
		foreach ( $hook_ids as $hook_id ) {
			$events = (array) get_post_meta( $hook_id, self::META_HOOK_EVENTS, true );
			if ( ! in_array( $event, $events, true ) && ! in_array( '*', $events, true ) ) {
				continue;
			}
			$queue = get_post_meta( $hook_id, self::META_HOOK_QUEUE, true );
			$queue = is_array( $queue ) ? $queue : array();
			$queue[] = array(
				'delivery_id' => wp_generate_uuid4(),
				'event'       => sanitize_text_field( $event ),
				'payload'     => $payload,
				'attempts'    => 0,
				'next_attempt'=> current_time( 'mysql' ),
				'created_at'  => current_time( 'mysql' ),
				'last_error'  => '',
			);
			update_post_meta( $hook_id, self::META_HOOK_QUEUE, array_slice( $queue, -self::MAX_WEBHOOK_QUEUE ) );
		}
	}

	public function run_webhook_queue() {
		$hook_ids = get_posts(
			array(
				'post_type'      => self::WEBHOOK_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => self::META_HOOK_STATUS,
				'meta_value'     => 'active',
			)
		);
		foreach ( $hook_ids as $hook_id ) {
			$this->deliver_webhook_batch( $hook_id, 10 );
		}
	}

	public function deliver_webhook_batch( $hook_id, $limit = 10 ) {
		$url = esc_url_raw( get_post_meta( $hook_id, self::META_HOOK_URL, true ) );
		$secret = (string) get_post_meta( $hook_id, self::META_HOOK_SECRET, true );
		if ( ! self::safe_federation_url( $url ) || strlen( $secret ) < 16 ) {
			return new WP_Error( 'webhook_configuration_invalid', __( 'The webhook URL or secret is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		$queue = get_post_meta( $hook_id, self::META_HOOK_QUEUE, true );
		$queue = is_array( $queue ) ? array_values( $queue ) : array();
		$remaining = array();
		$processed = 0;
		foreach ( $queue as $delivery ) {
			if ( $processed >= $limit || strtotime( $delivery['next_attempt'] ?? '' ) > current_time( 'timestamp' ) ) {
				$remaining[] = $delivery;
				continue;
			}
			$processed++;
			$body = wp_json_encode(
				array(
					'schema'      => self::WEBHOOK_SCHEMA,
					'delivery_id' => $delivery['delivery_id'],
					'event'       => $delivery['event'],
					'payload'     => $delivery['payload'],
					'sent_at'     => current_time( 'mysql' ),
					'node'        => self::node_data( false ),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			$timestamp = (string) time();
			$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
			$response = wp_safe_remote_post(
				$url,
				array(
					'timeout'     => 12,
					'redirection' => 0,
					'headers'     => array(
						'Content-Type'              => 'application/json',
						'X-SC-Webhook-ID'           => $delivery['delivery_id'],
						'X-SC-Webhook-Timestamp'    => $timestamp,
						'X-SC-Webhook-Signature'    => 'sha256=' . $signature,
					),
					'body' => $body,
				)
			);
			$success = ! is_wp_error( $response )
				&& wp_remote_retrieve_response_code( $response ) >= 200
				&& wp_remote_retrieve_response_code( $response ) < 300;
			if ( $success ) {
				update_post_meta( $hook_id, self::META_HOOK_LAST_DELIVERY, current_time( 'mysql' ) );
				self::audit( 'webhook-delivered', array( 'hook_id' => $hook_id, 'delivery_id' => $delivery['delivery_id'] ) );
				continue;
			}
			$delivery['attempts'] = absint( $delivery['attempts'] ?? 0 ) + 1;
			$delivery['last_error'] = is_wp_error( $response )
				? sanitize_text_field( $response->get_error_message() )
				: 'HTTP ' . absint( wp_remote_retrieve_response_code( $response ) );
			if ( $delivery['attempts'] < 5 ) {
				$delay_minutes = min( 720, (int) pow( 2, $delivery['attempts'] ) * 5 );
				$delivery['next_attempt'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay_minutes * MINUTE_IN_SECONDS );
				$remaining[] = $delivery;
			} else {
				$failures = get_post_meta( $hook_id, self::META_HOOK_FAILURES, true );
				$failures = is_array( $failures ) ? $failures : array();
				$failures[] = $delivery;
				update_post_meta( $hook_id, self::META_HOOK_FAILURES, array_slice( $failures, -100 ) );
				self::audit( 'webhook-abandoned', array( 'hook_id' => $hook_id, 'delivery_id' => $delivery['delivery_id'] ) );
			}
		}
		update_post_meta( $hook_id, self::META_HOOK_QUEUE, $remaining );
		return array( 'hook_id' => $hook_id, 'processed' => $processed, 'remaining' => count( $remaining ) );
	}

	public static function quarantine_import( $payload, $peer_id = 0 ) {
		$encoded = wp_json_encode( $payload );
		if ( false === $encoded || strlen( $encoded ) > self::MAX_IMPORT_BYTES ) {
			return new WP_Error( 'federation_import_too_large', __( 'The federation import exceeds the allowed size.', 'sustainable-catalyst-library' ), array( 'status' => 413 ) );
		}
		$validation = self::validate_import_payload( $payload, $peer_id );
		$import_id = wp_insert_post(
			array(
				'post_type'   => self::IMPORT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( __( 'Federation import — %s', 'sustainable-catalyst-library' ), current_time( 'Y-m-d H:i:s' ) ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $import_id ) ) {
			return $import_id;
		}
		update_post_meta( $import_id, self::META_IMPORT_UUID, wp_generate_uuid4() );
		update_post_meta( $import_id, self::META_IMPORT_STATUS, ! empty( $validation['valid'] ) ? 'quarantined' : 'rejected' );
		update_post_meta( $import_id, self::META_IMPORT_PEER_ID, absint( $peer_id ) );
		update_post_meta( $import_id, self::META_IMPORT_PAYLOAD, $payload );
		update_post_meta( $import_id, self::META_IMPORT_SCHEMA, sanitize_text_field( $payload['schema'] ?? '' ) );
		update_post_meta( $import_id, self::META_IMPORT_HASH, hash( 'sha256', self::canonical_json( $payload ) ) );
		update_post_meta( $import_id, self::META_IMPORT_VALIDATION, $validation );
		update_post_meta( $import_id, self::META_IMPORT_CREATED_AT, current_time( 'mysql' ) );
		self::audit( 'federation-import-quarantined', array( 'import_id' => $import_id, 'peer_id' => $peer_id, 'valid' => $validation['valid'] ?? false ) );
		return self::import_data( $import_id, true );
	}

	public static function validate_import_payload( $payload, $peer_id = 0 ) {
		$errors = array();
		if ( ! is_array( $payload ) ) {
			$errors[] = 'payload-not-object';
		} else {
			if ( empty( $payload['schema'] ) ) {
				$errors[] = 'schema-missing';
			}
			if ( empty( $payload['records'] ) || ! is_array( $payload['records'] ) ) {
				$errors[] = 'records-missing';
			}
			if ( count( (array) ( $payload['records'] ?? array() ) ) > 5000 ) {
				$errors[] = 'too-many-records';
			}
			foreach ( array_slice( (array) ( $payload['records'] ?? array() ), 0, 5000 ) as $index => $record ) {
				if ( ! is_array( $record ) || empty( $record['id'] ) || empty( $record['type'] ) || empty( $record['title'] ) ) {
					$errors[] = 'record-invalid-' . absint( $index );
					break;
				}
			}
		}
		$peer = $peer_id ? self::peer_data( $peer_id, true ) : array();
		if ( $peer_id && ( ! $peer || ! in_array( $peer['trust'] ?? '', array( 'metadata', 'verified' ), true ) ) ) {
			$errors[] = 'peer-trust-insufficient';
		}
		return array(
			'valid'       => ! $errors,
			'errors'      => $errors,
			'peer_id'     => absint( $peer_id ),
			'validated_at'=> current_time( 'mysql' ),
			'automatic_import_allowed' => false,
		);
	}

	public static function import_data( $import_id, $include_payload = false ) {
		if ( self::IMPORT_POST_TYPE !== get_post_type( $import_id ) ) {
			return array();
		}
		$data = array(
			'schema'      => self::IMPORT_SCHEMA,
			'import_id'   => absint( $import_id ),
			'uuid'        => (string) get_post_meta( $import_id, self::META_IMPORT_UUID, true ),
			'status'      => (string) get_post_meta( $import_id, self::META_IMPORT_STATUS, true ),
			'peer_id'     => absint( get_post_meta( $import_id, self::META_IMPORT_PEER_ID, true ) ),
			'payload_schema' => (string) get_post_meta( $import_id, self::META_IMPORT_SCHEMA, true ),
			'sha256'      => (string) get_post_meta( $import_id, self::META_IMPORT_HASH, true ),
			'validation'  => (array) get_post_meta( $import_id, self::META_IMPORT_VALIDATION, true ),
			'decision'    => (array) get_post_meta( $import_id, self::META_IMPORT_DECISION, true ),
			'created_at'  => (string) get_post_meta( $import_id, self::META_IMPORT_CREATED_AT, true ),
		);
		if ( $include_payload ) {
			$data['payload'] = get_post_meta( $import_id, self::META_IMPORT_PAYLOAD, true );
		}
		return $data;
	}

	private static function safe_federation_url( $url ) {
		$url = esc_url_raw( $url );
		if ( ! $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! $host || in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return ! self::private_ip( $host );
		}
		return true;
	}

	private static function private_ip( $ip ) {
		return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::API_NAMESPACE,
			'/capabilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_capabilities' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_catalog' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/catalog/(?P<type>[a-z\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_catalog_type' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/records/(?P<type>[a-z\-]+)/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_record' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/exports',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_export' ),
				'permission_callback' => array( $this, 'rest_can_create_export' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/exports/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_export' ),
					'permission_callback' => array( $this, 'rest_can_read_export' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_run_export' ),
					'permission_callback' => array( $this, 'rest_can_create_export' ),
				),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/exports/(?P<id>\d+)/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_download_export' ),
				'permission_callback' => array( $this, 'rest_can_read_export' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/federation/node',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_node' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/federation/peers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_peers' ),
				'permission_callback' => array( $this, 'rest_can_read_federation' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/federation/peers/(?P<id>\d+)/check',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rest_check_peer' ),
				'permission_callback' => array( $this, 'rest_can_manage_federation' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/federation/imports',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_import' ),
				'permission_callback' => array( $this, 'rest_can_import_federation' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/federation/imports/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_import' ),
				'permission_callback' => array( $this, 'rest_can_manage_federation' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/api-export-federation/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_dashboard' ),
				'permission_callback' => array( $this, 'rest_can_read_admin' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/api-export-federation/migration',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_migration_state' ),
					'permission_callback' => array( $this, 'rest_can_read_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_run_migration' ),
					'permission_callback' => array( $this, 'rest_can_manage_federation' ),
				),
			)
		);
	}

	public function rest_capabilities( WP_REST_Request $request ) {
		return rest_ensure_response( self::capabilities( true ) );
	}

	public function rest_catalog( WP_REST_Request $request ) {
		return rest_ensure_response(
			self::public_catalog(
				array(
					'type'   => $request->get_param( 'type' ) ?: 'documents',
					'limit'  => $request->get_param( 'limit' ) ?: 25,
					'cursor' => $request->get_param( 'cursor' ) ?: '',
					'search' => $request->get_param( 'search' ) ?: '',
				)
			)
		);
	}

	public function rest_catalog_type( WP_REST_Request $request ) {
		return rest_ensure_response(
			self::public_catalog(
				array(
					'type'   => $request['type'],
					'limit'  => $request->get_param( 'limit' ) ?: 25,
					'cursor' => $request->get_param( 'cursor' ) ?: '',
					'search' => $request->get_param( 'search' ) ?: '',
				)
			)
		);
	}

	public function rest_record( WP_REST_Request $request ) {
		$type = sanitize_key( $request['type'] );
		$record = self::serialize_record( absint( $request['id'] ), $type, false );
		return $record
			? rest_ensure_response( $record )
			: new WP_Error( 'public_record_not_found', __( 'The public record was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
	}

	public function rest_create_export( WP_REST_Request $request ) {
		$auth = $request->get_attribute( '_sc_library_auth' );
		if ( ! is_array( $auth ) ) {
			$auth = self::authenticate_request( $request, 'exports:create' );
		}
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$payload = $request->get_json_params();
		$result = self::create_export_job( is_array( $payload ) ? $payload : array(), $auth );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_export( WP_REST_Request $request ) {
		$data = self::export_job_data( absint( $request['id'] ), true );
		return $data
			? rest_ensure_response( $data )
			: new WP_Error( 'export_job_not_found', __( 'The export job was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
	}

	public function rest_run_export( WP_REST_Request $request ) {
		$result = self::run_export_batch(
			absint( $request['id'] ),
			max( 1, min( 500, absint( $request->get_param( 'limit' ) ?: self::EXPORT_BATCH ) ) )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_download_export( WP_REST_Request $request ) {
		$job_id = absint( $request['id'] );
		$path = (string) get_post_meta( $job_id, self::META_EXPORT_FILE, true );
		if ( ! $path || ! is_file( $path ) || 0 !== strpos( realpath( $path ), realpath( self::export_directory() ) ) ) {
			return new WP_Error( 'export_file_not_found', __( 'The export file is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		$response = new WP_REST_Response( file_get_contents( $path ), 200 );
		$response->header( 'Content-Type', self::content_type_for_file( $path ) );
		$response->header( 'Content-Disposition', 'attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		$response->header( 'Content-Length', (string) filesize( $path ) );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		self::audit( 'export-downloaded', array( 'job_id' => $job_id ) );
		return $response;
	}

	public function rest_node() {
		$node = self::node_data( false );
		if ( empty( $node['public'] ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'federation_node_private', __( 'Federation node discovery is not public.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $node );
	}

	public function rest_peers() {
		$ids = get_posts(
			array(
				'post_type'      => self::PEER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		return rest_ensure_response(
			array(
				'schema' => self::PEER_SCHEMA,
				'peers'  => array_map( static function ( $id ) { return self::peer_data( $id, true ); }, $ids ),
			)
		);
	}

	public function rest_check_peer( WP_REST_Request $request ) {
		$result = self::check_peer( absint( $request['id'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_create_import( WP_REST_Request $request ) {
		$auth = $request->get_attribute( '_sc_library_auth' );
		if ( ! is_array( $auth ) ) {
			$auth = self::authenticate_request( $request, 'federation:import' );
		}
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$payload = $request->get_json_params();
		$peer_id = absint( $request->get_param( 'peer_id' ) );
		$result = self::quarantine_import( is_array( $payload ) ? $payload : array(), $peer_id );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_import( WP_REST_Request $request ) {
		$data = self::import_data( absint( $request['id'] ), true );
		return $data
			? rest_ensure_response( $data )
			: new WP_Error( 'federation_import_not_found', __( 'The federation import was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
	}

	public function rest_dashboard() {
		return rest_ensure_response( self::dashboard_report() );
	}

	public function rest_migration_state() {
		return rest_ensure_response( self::migration_state() );
	}

	public function rest_run_migration( WP_REST_Request $request ) {
		$result = self::run_migration_batch( max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function rest_can_create_export( WP_REST_Request $request ) {
		$auth = self::authenticate_request( $request, 'exports:create' );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$request->set_attribute( '_sc_library_auth', $auth );
		return true;
	}

	public function rest_can_read_export( WP_REST_Request $request ) {
		$job_id = absint( $request['id'] );
		if ( '1' === get_post_meta( $job_id, self::META_EXPORT_PUBLIC, true ) && 'complete' === get_post_meta( $job_id, self::META_EXPORT_STATUS, true ) ) {
			return true;
		}
		return ! is_wp_error( self::authenticate_request( $request, 'exports:read' ) );
	}

	public function rest_can_read_federation( WP_REST_Request $request ) {
		return ! is_wp_error( self::authenticate_request( $request, 'federation:read' ) );
	}

	public function rest_can_import_federation( WP_REST_Request $request ) {
		$auth = self::authenticate_request( $request, 'federation:import' );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$request->set_attribute( '_sc_library_auth', $auth );
		return true;
	}

	public function rest_can_read_admin( WP_REST_Request $request ) {
		return ! is_wp_error( self::authenticate_request( $request, 'admin:read' ) );
	}

	public function rest_can_manage_federation() {
		return current_user_can( 'manage_options' );
	}

	public function harden_rest_response( $response, $server, $request ) {
		if ( ! $response instanceof WP_REST_Response ) {
			return $response;
		}
		$route = $request->get_route();
		if ( false === strpos( $route, '/sc-library/v1/' ) ) {
			return $response;
		}
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-SC-API-Version', '1.0' );
		$response->header( 'X-SC-Plugin-Version', self::VERSION );

		$public = false !== strpos( $route, '/capabilities' )
			|| false !== strpos( $route, '/catalog' )
			|| false !== strpos( $route, '/records/' )
			|| false !== strpos( $route, '/federation/node' )
			|| false !== strpos( $route, '/transparency' );
		if ( $public && 'GET' === $request->get_method() && 200 === $response->get_status() ) {
			$data = $response->get_data();
			$etag = '"' . hash( 'sha256', self::canonical_json( self::stable_etag_data( $data ) ) ) . '"';
			$response->header( 'ETag', $etag );
			$last_modified = self::response_last_modified( $data );
			if ( $last_modified ) {
				$response->header( 'Last-Modified', gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
			}
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

	private static function response_last_modified( $value ) {
		$timestamps = array();
		$walk = static function ( $item ) use ( &$walk, &$timestamps ) {
			if ( ! is_array( $item ) ) {
				return;
			}
			foreach ( $item as $key => $child ) {
				if ( in_array( $key, array( 'modified_at', 'updated_at', 'completed_at', 'published_at' ), true ) && is_string( $child ) ) {
					$timestamp = strtotime( $child );
					if ( $timestamp ) {
						$timestamps[] = $timestamp;
					}
				} elseif ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $value );
		return $timestamps ? max( $timestamps ) : 0;
	}

	private static function content_type_for_file( $path ) {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$map = array(
			'json'   => 'application/json; charset=utf-8',
			'jsonld' => 'application/ld+json; charset=utf-8',
			'ndjson' => 'application/x-ndjson; charset=utf-8',
			'csv'    => 'text/csv; charset=utf-8',
			'zip'    => 'application/zip',
		);
		return $map[ $extension ] ?? 'application/octet-stream';
	}

	public function save_peer( $post_id, $post, $update ) {
		if (
			! $post instanceof WP_Post
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| self::PEER_POST_TYPE !== $post->post_type
			|| ! isset( $_POST['sc_library_peer_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sc_library_peer_nonce'] ) ),
				'sc_library_save_peer_' . $post_id
			)
			|| ! current_user_can( 'manage_options' )
		) {
			return;
		}
		self::ensure_uuid( $post_id, self::META_PEER_UUID );
		update_post_meta( $post_id, self::META_PEER_STATUS, self::allowed_value( wp_unslash( $_POST['sc_peer_status'] ?? 'pending' ), self::peer_statuses(), 'pending' ) );
		update_post_meta( $post_id, self::META_PEER_BASE_URL, esc_url_raw( wp_unslash( $_POST['sc_peer_base_url'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_PEER_TRUST, self::allowed_value( wp_unslash( $_POST['sc_peer_trust'] ?? 'untrusted' ), self::trust_levels(), 'untrusted' ) );
		update_post_meta( $post_id, self::META_PEER_SCOPES, array_values( array_intersect( array_keys( self::token_scopes() ), array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sc_peer_scopes'] ?? array() ) ) ) ) );
		update_post_meta( $post_id, self::META_PEER_PUBLIC_KEY, sanitize_textarea_field( wp_unslash( $_POST['sc_peer_public_key'] ?? '' ) ) );
		$secret = sanitize_text_field( wp_unslash( $_POST['sc_peer_shared_secret'] ?? '' ) );
		if ( $secret ) {
			update_post_meta( $post_id, self::META_PEER_SHARED_SECRET, wp_hash_password( $secret ) );
		}
		delete_transient( self::TRANSIENT_CAPABILITIES );
		self::audit( 'peer-saved', array( 'peer_id' => $post_id ) );
	}

	public function save_webhook( $post_id, $post, $update ) {
		if (
			! $post instanceof WP_Post
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| self::WEBHOOK_POST_TYPE !== $post->post_type
			|| ! isset( $_POST['sc_library_webhook_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sc_library_webhook_nonce'] ) ),
				'sc_library_save_webhook_' . $post_id
			)
			|| ! current_user_can( 'manage_options' )
		) {
			return;
		}
		self::ensure_uuid( $post_id, self::META_HOOK_UUID );
		$status = sanitize_key( wp_unslash( $_POST['sc_hook_status'] ?? 'inactive' ) );
		update_post_meta( $post_id, self::META_HOOK_STATUS, in_array( $status, array( 'active', 'inactive', 'suspended' ), true ) ? $status : 'inactive' );
		update_post_meta( $post_id, self::META_HOOK_URL, esc_url_raw( wp_unslash( $_POST['sc_hook_url'] ?? '' ) ) );
		$events = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sc_hook_events'] ?? array() ) )
				)
			)
		);
		update_post_meta( $post_id, self::META_HOOK_EVENTS, $events );
		$secret = sanitize_text_field( wp_unslash( $_POST['sc_hook_secret'] ?? '' ) );
		if ( $secret ) {
			update_post_meta( $post_id, self::META_HOOK_SECRET, $secret );
		}
		self::audit( 'webhook-saved', array( 'hook_id' => $post_id, 'events' => $events ) );
	}

	public function render_peer_meta_box( $post ) {
		wp_nonce_field( 'sc_library_save_peer_' . $post->ID, 'sc_library_peer_nonce' );
		$status = (string) get_post_meta( $post->ID, self::META_PEER_STATUS, true ) ?: 'pending';
		$trust = (string) get_post_meta( $post->ID, self::META_PEER_TRUST, true ) ?: 'untrusted';
		$scopes = (array) get_post_meta( $post->ID, self::META_PEER_SCOPES, true );
		?>
		<div class="sc-api-peer-editor" data-sc-peer-id="<?php echo esc_attr( $post->ID ); ?>">
			<div class="sc-api-field-grid">
				<label><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_peer_status"><?php foreach ( self::peer_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'Trust level', 'sustainable-catalyst-library' ); ?></strong><select name="sc_peer_trust"><?php foreach ( self::trust_levels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $trust, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><strong><?php esc_html_e( 'HTTPS base URL', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_peer_base_url" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PEER_BASE_URL, true ) ); ?>" placeholder="https://example.org/"></label>
				<label><strong><?php esc_html_e( 'Node ID', 'sustainable-catalyst-library' ); ?></strong><input type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PEER_NODE_ID, true ) ); ?>" readonly></label>
			</div>
			<fieldset><legend><strong><?php esc_html_e( 'Allowed scopes', 'sustainable-catalyst-library' ); ?></strong></legend><div class="sc-api-scope-grid"><?php foreach ( self::token_scopes() as $scope => $label ) : ?><label><input type="checkbox" name="sc_peer_scopes[]" value="<?php echo esc_attr( $scope ); ?>" <?php checked( in_array( $scope, $scopes, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div></fieldset>
			<label><strong><?php esc_html_e( 'Peer public key or verification note', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_peer_public_key" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PEER_PUBLIC_KEY, true ) ); ?></textarea></label>
			<label><strong><?php esc_html_e( 'Replace shared secret', 'sustainable-catalyst-library' ); ?></strong><input type="password" name="sc_peer_shared_secret" autocomplete="new-password"></label>
			<div class="sc-api-actions"><button type="button" class="button" data-sc-check-peer><?php esc_html_e( 'Check Peer Capabilities', 'sustainable-catalyst-library' ); ?></button><span data-sc-peer-status aria-live="polite"></span></div>
		</div>
		<?php
	}

	public function render_webhook_meta_box( $post ) {
		wp_nonce_field( 'sc_library_save_webhook_' . $post->ID, 'sc_library_webhook_nonce' );
		$status = (string) get_post_meta( $post->ID, self::META_HOOK_STATUS, true ) ?: 'inactive';
		$events = (array) get_post_meta( $post->ID, self::META_HOOK_EVENTS, true );
		$available = array( '*', 'export.completed', 'document.published', 'publication.published', 'source.integrity.changed', 'review.approved' );
		?>
		<div class="sc-api-webhook-editor">
			<div class="sc-api-field-grid">
				<label><strong><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_hook_status"><option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'sustainable-catalyst-library' ); ?></option><option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'sustainable-catalyst-library' ); ?></option><option value="suspended" <?php selected( $status, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'sustainable-catalyst-library' ); ?></option></select></label>
				<label><strong><?php esc_html_e( 'HTTPS delivery URL', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_hook_url" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_HOOK_URL, true ) ); ?>"></label>
				<label><strong><?php esc_html_e( 'Replace signing secret', 'sustainable-catalyst-library' ); ?></strong><input type="password" name="sc_hook_secret" autocomplete="new-password"></label>
			</div>
			<fieldset><legend><strong><?php esc_html_e( 'Events', 'sustainable-catalyst-library' ); ?></strong></legend><div class="sc-api-scope-grid"><?php foreach ( $available as $event ) : ?><label><input type="checkbox" name="sc_hook_events[]" value="<?php echo esc_attr( $event ); ?>" <?php checked( in_array( $event, $events, true ) ); ?>> <?php echo esc_html( $event ); ?></label><?php endforeach; ?></div></fieldset>
			<p><?php esc_html_e( 'Deliveries use HMAC-SHA256 over timestamp plus request body, bounded retries, no redirects, and HTTPS-only URLs.', 'sustainable-catalyst-library' ); ?></p>
		</div>
		<?php
	}

	public static function dashboard_report() {
		$token_ids = get_posts( array( 'post_type' => self::TOKEN_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids' ) );
		$export_ids = get_posts( array( 'post_type' => self::EXPORT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );
		$peer_ids = get_posts( array( 'post_type' => self::PEER_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids' ) );
		$hook_ids = get_posts( array( 'post_type' => self::WEBHOOK_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids' ) );
		$import_ids = get_posts( array( 'post_type' => self::IMPORT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );

		$exports = array_map( static function ( $id ) { return self::export_job_data( $id, true ); }, array_slice( $export_ids, 0, 100 ) );
		$peers = array_map( static function ( $id ) { return self::peer_data( $id, true ); }, $peer_ids );
		$imports = array_map( static function ( $id ) { return self::import_data( $id, false ); }, array_slice( $import_ids, 0, 100 ) );
		$active_tokens = 0;
		foreach ( $token_ids as $id ) {
			$expires = (string) get_post_meta( $id, self::META_TOKEN_EXPIRES, true );
			if ( '1' !== get_post_meta( $id, self::META_TOKEN_REVOKED, true ) && ( ! $expires || strtotime( $expires ) > current_time( 'timestamp' ) ) ) {
				$active_tokens++;
			}
		}
		return array(
			'schema'            => self::AUDIT_SCHEMA,
			'active_tokens'     => $active_tokens,
			'export_count'      => count( $export_ids ),
			'queued_exports'    => count( array_filter( $exports, static function ( $item ) { return in_array( $item['status'] ?? '', array( 'queued', 'running' ), true ); } ) ),
			'complete_exports'  => count( array_filter( $exports, static function ( $item ) { return 'complete' === ( $item['status'] ?? '' ); } ) ),
			'peer_count'        => count( $peers ),
			'active_peers'      => count( array_filter( $peers, static function ( $item ) { return 'active' === ( $item['status'] ?? '' ); } ) ),
			'webhook_count'     => count( $hook_ids ),
			'import_count'      => count( $imports ),
			'quarantined_imports'=> count( array_filter( $imports, static function ( $item ) { return 'quarantined' === ( $item['status'] ?? '' ); } ) ),
			'node'              => self::node_data( true ),
			'capabilities'      => self::capabilities( false ),
			'exports'           => $exports,
			'peers'             => $peers,
			'imports'           => $imports,
			'audit'             => array_slice( (array) get_option( self::OPTION_AUDIT, array() ), -100 ),
			'migration'         => self::migration_state(),
			'generated_at'      => current_time( 'mysql' ),
		);
	}

	public function render_workspace() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = self::dashboard_report();
		?>
		<div class="wrap sc-api-center">
			<p class="sc-api-kicker"><?php esc_html_e( 'Knowledge Library v3.9.0', 'sustainable-catalyst-library' ); ?></p>
			<h1><?php esc_html_e( 'Public API, Export, and Federation Hardening', 'sustainable-catalyst-library' ); ?></h1>
			<p><?php esc_html_e( 'Publish stable metadata contracts, issue scoped tokens, create deterministic exports, govern federation peers, quarantine imports, and inspect auditable delivery activity.', 'sustainable-catalyst-library' ); ?></p>

			<div class="sc-api-metrics">
				<article><strong><?php echo esc_html( $report['active_tokens'] ); ?></strong><span><?php esc_html_e( 'Active tokens', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['complete_exports'] ); ?></strong><span><?php esc_html_e( 'Complete exports', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['queued_exports'] ); ?></strong><span><?php esc_html_e( 'Queued exports', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['active_peers'] ); ?></strong><span><?php esc_html_e( 'Active peers', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['webhook_count'] ); ?></strong><span><?php esc_html_e( 'Webhooks', 'sustainable-catalyst-library' ); ?></span></article>
				<article><strong><?php echo esc_html( $report['quarantined_imports'] ); ?></strong><span><?php esc_html_e( 'Quarantined imports', 'sustainable-catalyst-library' ); ?></span></article>
			</div>

			<div class="sc-api-tools">
				<section class="sc-api-tool">
					<h2><?php esc_html_e( 'Issue scoped API token', 'sustainable-catalyst-library' ); ?></h2>
					<form data-sc-token-form>
						<label><?php esc_html_e( 'Label', 'sustainable-catalyst-library' ); ?><input type="text" name="label" required></label>
						<label><?php esc_html_e( 'Scopes', 'sustainable-catalyst-library' ); ?><select name="scopes" multiple size="7"><?php foreach ( self::token_scopes() as $scope => $label ) : ?><option value="<?php echo esc_attr( $scope ); ?>"><?php echo esc_html( $scope . ' — ' . $label ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Rate limit per minute', 'sustainable-catalyst-library' ); ?><input type="number" name="rate_limit" min="1" max="<?php echo esc_attr( self::MAX_RATE_LIMIT ); ?>" value="<?php echo esc_attr( self::DEFAULT_RATE_LIMIT ); ?>"></label>
						<label><?php esc_html_e( 'Expires', 'sustainable-catalyst-library' ); ?><input type="datetime-local" name="expires"></label>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Token', 'sustainable-catalyst-library' ); ?></button>
					</form>
					<div data-sc-token-result aria-live="polite"></div>
				</section>
				<section class="sc-api-tool">
					<h2><?php esc_html_e( 'Create export job', 'sustainable-catalyst-library' ); ?></h2>
					<form data-sc-export-form>
						<label><?php esc_html_e( 'Scope', 'sustainable-catalyst-library' ); ?><select name="scope"><?php foreach ( self::export_scopes() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Format', 'sustainable-catalyst-library' ); ?><select name="format"><?php foreach ( self::export_formats() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Search filter', 'sustainable-catalyst-library' ); ?><input type="search" name="search"></label>
						<label><input type="checkbox" name="public" value="1"> <?php esc_html_e( 'Allow public download after completion', 'sustainable-catalyst-library' ); ?></label>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Export', 'sustainable-catalyst-library' ); ?></button>
					</form>
					<div data-sc-export-result aria-live="polite"></div>
				</section>
			</div>

			<section class="sc-api-migration">
				<div><h2><?php esc_html_e( 'Hardening migration', 'sustainable-catalyst-library' ); ?></h2><p><?php echo esc_html( sprintf( __( '%1$d of %2$d records processed', 'sustainable-catalyst-library' ), $report['migration']['processed'], $report['migration']['total'] ) ); ?></p></div>
				<button type="button" class="button" data-sc-api-run-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button>
				<span data-sc-api-migration-status aria-live="polite"></span>
			</section>

			<h2><?php esc_html_e( 'Recent exports', 'sustainable-catalyst-library' ); ?></h2>
			<?php echo self::render_export_table( $report['exports'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2><?php esc_html_e( 'Federation peers', 'sustainable-catalyst-library' ); ?></h2>
			<?php echo self::render_peer_table( $report['peers'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2><?php esc_html_e( 'Quarantined imports', 'sustainable-catalyst-library' ); ?></h2>
			<?php echo self::render_import_table( $report['imports'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}

	private static function render_export_table( $exports, $private = false ) {
		if ( ! $exports ) {
			return '<p>' . esc_html__( 'No export jobs have been created.', 'sustainable-catalyst-library' ) . '</p>';
		}
		ob_start();
		?>
		<div class="sc-api-table-wrap"><table class="widefat striped"><thead><tr>
			<th><?php esc_html_e( 'Export', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Format', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Scope', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Progress', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Completed', 'sustainable-catalyst-library' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $exports as $item ) : ?>
			<tr>
				<td><?php echo esc_html( $item['title'] ?? ( 'Export ' . absint( $item['job_id'] ?? 0 ) ) ); ?></td>
				<td><?php echo esc_html( strtoupper( $item['format'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( self::export_scopes()[ $item['scope'] ?? '' ] ?? ( $item['scope'] ?? '' ) ); ?></td>
				<td><span class="sc-api-state status-<?php echo esc_attr( $item['status'] ?? '' ); ?>"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $item['status'] ?? '' ) ) ); ?></span></td>
				<td><?php echo esc_html( absint( $item['processed'] ?? 0 ) . '/' . absint( $item['total'] ?? 0 ) ); ?></td>
				<td><?php echo esc_html( $item['completed_at'] ?? '—' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
		return ob_get_clean();
	}

	private static function render_peer_table( $peers ) {
		if ( ! $peers ) {
			return '<p>' . esc_html__( 'No federation peers have been configured.', 'sustainable-catalyst-library' ) . '</p>';
		}
		ob_start();
		?>
		<div class="sc-api-table-wrap"><table class="widefat striped"><thead><tr>
			<th><?php esc_html_e( 'Peer', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Trust', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Last success', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Error', 'sustainable-catalyst-library' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $peers as $item ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( get_edit_post_link( $item['peer_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a></td>
				<td><?php echo esc_html( self::trust_levels()[ $item['trust'] ] ?? $item['trust'] ); ?></td>
				<td><span class="sc-api-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( self::peer_statuses()[ $item['status'] ] ?? $item['status'] ); ?></span></td>
				<td><?php echo esc_html( $item['last_success'] ?: '—' ); ?></td>
				<td><?php echo esc_html( $item['error'] ?: '—' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
		return ob_get_clean();
	}

	private static function render_import_table( $imports ) {
		if ( ! $imports ) {
			return '<p>' . esc_html__( 'No federation imports have been received.', 'sustainable-catalyst-library' ) . '</p>';
		}
		ob_start();
		?>
		<div class="sc-api-table-wrap"><table class="widefat striped"><thead><tr>
			<th><?php esc_html_e( 'Import', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Peer', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Schema', 'sustainable-catalyst-library' ); ?></th>
			<th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th>
			<th>SHA-256</th>
		</tr></thead><tbody>
		<?php foreach ( $imports as $item ) : ?>
			<tr>
				<td><?php echo esc_html( absint( $item['import_id'] ) ); ?></td>
				<td><?php echo esc_html( absint( $item['peer_id'] ) ?: '—' ); ?></td>
				<td><?php echo esc_html( $item['payload_schema'] ?: '—' ); ?></td>
				<td><span class="sc-api-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( ucfirst( $item['status'] ) ); ?></span></td>
				<td><code><?php echo esc_html( substr( $item['sha256'], 0, 16 ) ); ?>…</code></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
		return ob_get_clean();
	}

	public function ajax_create_token() {
		$this->verify_ajax( 'manage_options' );
		$scopes = isset( $_POST['scopes'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['scopes'] ) ) ) : array();
		$result = self::create_token(
			wp_unslash( $_POST['label'] ?? '' ),
			$scopes,
			wp_unslash( $_POST['expires'] ?? '' ),
			wp_unslash( $_POST['rate_limit'] ?? self::DEFAULT_RATE_LIMIT )
		);
		$this->send_ajax_result( $result, 'token' );
	}

	public function ajax_create_export() {
		$this->verify_ajax( 'manage_options' );
		$result = self::create_export_job(
			array(
				'format' => wp_unslash( $_POST['format'] ?? 'json' ),
				'scope'  => wp_unslash( $_POST['scope'] ?? 'documents' ),
				'public' => wp_unslash( $_POST['public'] ?? false ),
				'filters'=> array( 'search' => wp_unslash( $_POST['search'] ?? '' ) ),
			),
			array( 'type' => 'user', 'user_id' => get_current_user_id() )
		);
		$this->send_ajax_result( $result, 'export' );
	}

	public function ajax_run_export() {
		$this->verify_ajax( 'manage_options' );
		$result = self::run_export_batch( absint( wp_unslash( $_POST['job_id'] ?? 0 ) ), self::EXPORT_BATCH );
		$this->send_ajax_result( $result, 'export' );
	}

	public function ajax_run_migration() {
		$this->verify_ajax( 'manage_options' );
		$this->send_ajax_result( self::run_migration_batch( self::MIGRATION_BATCH ), 'migration' );
	}

	public function ajax_check_peer() {
		$this->verify_ajax( 'manage_options' );
		$this->send_ajax_result( self::check_peer( absint( wp_unslash( $_POST['peer_id'] ?? 0 ) ) ), 'peer' );
	}

	private function verify_ajax( $capability ) {
		check_ajax_referer( 'sc_library_api_federation_v390', 'nonce' );
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

	public static function audit( $event, $context = array() ) {
		$log = get_option( self::OPTION_AUDIT, array() );
		$log = is_array( $log ) ? $log : array();
		$log[] = array(
			'schema'     => self::AUDIT_SCHEMA,
			'audit_id'   => wp_generate_uuid4(),
			'event'      => sanitize_text_field( $event ),
			'context'    => self::redact_audit_context( $context ),
			'user_id'    => get_current_user_id(),
			'ip_hash'    => self::request_ip_hash(),
			'created_at' => current_time( 'mysql' ),
		);
		update_option( self::OPTION_AUDIT, array_slice( $log, -self::MAX_AUDIT ), false );
	}

	private static function redact_audit_context( $context ) {
		$context = is_array( $context ) ? $context : array();
		$blocked = array( 'token', 'secret', 'shared_secret', 'authorization', 'payload', 'raw_content', 'private_key' );
		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $blocked, true ) ) {
				$context[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = self::redact_audit_context( $value );
			} elseif ( is_string( $value ) && strlen( $value ) > 500 ) {
				$context[ $key ] = substr( $value, 0, 500 ) . '…';
			}
		}
		return $context;
	}

	private static function request_ip_hash() {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';
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
			return new WP_Error( 'api_federation_migration_locked', __( 'Another API and federation migration batch is running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
		}
		set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
		$state = self::migration_state();
		$state['status'] = 'running';
		$state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );

		try {
			$post_types = array( self::TOKEN_POST_TYPE, self::EXPORT_POST_TYPE, self::PEER_POST_TYPE, self::WEBHOOK_POST_TYPE, self::IMPORT_POST_TYPE );
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
				delete_transient( self::TRANSIENT_MIGRATION_LOCK );
				return $state;
			}
			foreach ( $rows as $row ) {
				$post_id = absint( $row['ID'] ?? 0 );
				try {
					switch ( $row['post_type'] ?? '' ) {
						case self::TOKEN_POST_TYPE:
							if ( ! get_post_meta( $post_id, self::META_TOKEN_RATE_LIMIT, true ) ) {
								update_post_meta( $post_id, self::META_TOKEN_RATE_LIMIT, self::DEFAULT_RATE_LIMIT );
							}
							if ( '' === get_post_meta( $post_id, self::META_TOKEN_REVOKED, true ) ) {
								update_post_meta( $post_id, self::META_TOKEN_REVOKED, '0' );
							}
							break;
						case self::EXPORT_POST_TYPE:
							self::ensure_uuid( $post_id, self::META_EXPORT_UUID );
							if ( ! get_post_meta( $post_id, self::META_EXPORT_STATUS, true ) ) {
								update_post_meta( $post_id, self::META_EXPORT_STATUS, 'queued' );
							}
							break;
						case self::PEER_POST_TYPE:
							self::ensure_uuid( $post_id, self::META_PEER_UUID );
							if ( ! get_post_meta( $post_id, self::META_PEER_STATUS, true ) ) {
								update_post_meta( $post_id, self::META_PEER_STATUS, 'pending' );
							}
							if ( ! get_post_meta( $post_id, self::META_PEER_TRUST, true ) ) {
								update_post_meta( $post_id, self::META_PEER_TRUST, 'untrusted' );
							}
							break;
						case self::WEBHOOK_POST_TYPE:
							self::ensure_uuid( $post_id, self::META_HOOK_UUID );
							if ( ! get_post_meta( $post_id, self::META_HOOK_STATUS, true ) ) {
								update_post_meta( $post_id, self::META_HOOK_STATUS, 'inactive' );
							}
							break;
						case self::IMPORT_POST_TYPE:
							self::ensure_uuid( $post_id, self::META_IMPORT_UUID );
							break;
					}
				} catch ( Throwable $error ) {
					$state['failures'][] = array( 'post_id' => $post_id, 'message' => sanitize_text_field( $error->getMessage() ), 'time' => current_time( 'mysql' ) );
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
		} catch ( Throwable $error ) {
			$state['status'] = 'failed';
			$state['last_error'] = sanitize_text_field( $error->getMessage() );
			$state['updated_at'] = current_time( 'mysql' );
			update_option( self::OPTION_MIGRATION, $state, false );
			delete_transient( self::TRANSIENT_MIGRATION_LOCK );
			return new WP_Error( 'api_federation_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
		}
		delete_transient( self::TRANSIENT_MIGRATION_LOCK );
		return $state;
	}

	public static function decide_import( $import_id, $decision, $note = '' ) {
		$import_id = absint( $import_id );
		if ( self::IMPORT_POST_TYPE !== get_post_type( $import_id ) ) {
			return new WP_Error( 'federation_import_invalid', __( 'The federation import is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'federation_import_forbidden', __( 'You cannot decide federation imports.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
		}
		$decision = sanitize_key( $decision );
		if ( ! in_array( $decision, array( 'approve-metadata', 'reject', 'archive' ), true ) ) {
			return new WP_Error( 'federation_import_decision_invalid', __( 'The federation import decision is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
		}
		$validation = (array) get_post_meta( $import_id, self::META_IMPORT_VALIDATION, true );
		if ( 'approve-metadata' === $decision && empty( $validation['valid'] ) ) {
			return new WP_Error( 'federation_import_validation_failed', __( 'An invalid import cannot be approved.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
		}
		$status = 'approve-metadata' === $decision ? 'approved-metadata' : ( 'reject' === $decision ? 'rejected' : 'archived' );
		$record = array(
			'decision'   => $decision,
			'note'       => sanitize_textarea_field( $note ),
			'user_id'    => get_current_user_id(),
			'decided_at' => current_time( 'mysql' ),
			'automatic_content_import' => false,
		);
		update_post_meta( $import_id, self::META_IMPORT_DECISION, $record );
		update_post_meta( $import_id, self::META_IMPORT_STATUS, $status );
		self::audit( 'federation-import-decided', array( 'import_id' => $import_id, 'decision' => $decision ) );
		return self::import_data( $import_id, true );
	}

	public function shortcode_capabilities() {
		wp_enqueue_style( 'sc-library-api-export-federation' );
		$data = self::capabilities( true );
		ob_start();
		?>
		<section class="sc-api-public-panel">
			<p class="sc-api-kicker"><?php esc_html_e( 'Knowledge Library API', 'sustainable-catalyst-library' ); ?></p>
			<h2><?php esc_html_e( 'Public API capabilities', 'sustainable-catalyst-library' ); ?></h2>
			<dl>
				<div><dt><?php esc_html_e( 'API version', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['api_version'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Export formats', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( implode( ', ', $data['formats'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Pagination', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['pagination']['type'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Conditional requests', 'sustainable-catalyst-library' ); ?></dt><dd><?php esc_html_e( 'ETag and Last-Modified aware', 'sustainable-catalyst-library' ); ?></dd></div>
			</dl>
		</section>
		<?php
		return ob_get_clean();
	}

	public function shortcode_public_catalog( $atts ) {
		$atts = shortcode_atts( array( 'type' => 'documents', 'limit' => 20, 'search' => '' ), $atts, 'sc_library_public_catalog' );
		$data = self::public_catalog( array( 'type' => $atts['type'], 'limit' => $atts['limit'], 'search' => $atts['search'] ) );
		if ( empty( $data['records'] ) ) {
			return '';
		}
		wp_enqueue_style( 'sc-library-api-export-federation' );
		ob_start();
		?>
		<section class="sc-api-public-catalog">
			<header><p class="sc-api-kicker"><?php esc_html_e( 'Public metadata catalog', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( self::export_scopes()[ $data['type'] ] ?? ucfirst( $data['type'] ) ); ?></h2></header>
			<ol>
				<?php foreach ( $data['records'] as $record ) : ?>
					<li><article><h3><a href="<?php echo esc_url( $record['url'] ); ?>"><?php echo esc_html( $record['title'] ); ?></a></h3><p><?php echo esc_html( $record['excerpt'] ); ?></p><small><?php echo esc_html( $record['modified_at'] ); ?></small></article></li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
		return ob_get_clean();
	}

	public function shortcode_federation_status() {
		$node = self::node_data( false );
		if ( empty( $node['public'] ) ) {
			return '';
		}
		wp_enqueue_style( 'sc-library-api-export-federation' );
		ob_start();
		?>
		<section class="sc-api-public-panel">
			<p class="sc-api-kicker"><?php esc_html_e( 'Federation node', 'sustainable-catalyst-library' ); ?></p>
			<h2><?php echo esc_html( $node['name'] ); ?></h2>
			<dl>
				<div><dt><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( ucfirst( $node['status'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Node ID', 'sustainable-catalyst-library' ); ?></dt><dd><code><?php echo esc_html( $node['node_id'] ); ?></code></dd></div>
				<div><dt><?php esc_html_e( 'API', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $node['api_url'] ); ?></dd></div>
			</dl>
		</section>
		<?php
		return ob_get_clean();
	}

	public function shortcode_export_register( $atts ) {
		$atts = shortcode_atts( array( 'limit' => 20 ), $atts, 'sc_library_export_register' );
		$ids = get_posts(
			array(
				'post_type'      => self::EXPORT_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 100, absint( $atts['limit'] ) ) ),
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => self::META_EXPORT_STATUS, 'value' => 'complete' ),
					array( 'key' => self::META_EXPORT_PUBLIC, 'value' => '1' ),
				),
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		if ( ! $ids ) {
			return '';
		}
		wp_enqueue_style( 'sc-library-api-export-federation' );
		$exports = array_map( static function ( $id ) { return self::export_job_data( $id, false ); }, $ids );
		return '<section class="sc-api-public-panel"><p class="sc-api-kicker">' . esc_html__( 'Open research data', 'sustainable-catalyst-library' ) . '</p><h2>' . esc_html__( 'Public exports', 'sustainable-catalyst-library' ) . '</h2>' . self::render_export_table( $exports, false ) . '</section>';
	}

	public function filter_project_context( $context, $project_id = 0, $args = array() ) {
		$project_id = absint( $project_id );
		if ( ! $project_id ) {
			return $context;
		}
		$export_ids = get_posts(
			array(
				'post_type'      => self::EXPORT_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => self::META_EXPORT_STATUS,
				'meta_value'     => 'complete',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$addition = array(
			'schema'       => self::HANDOFF_SCHEMA,
			'api'          => self::capabilities( true ),
			'node'         => self::node_data( false ),
			'exports'      => array_map( static function ( $id ) { return self::export_job_data( $id, false ); }, $export_ids ),
			'generated_at' => current_time( 'mysql' ),
		);
		return is_array( $context )
			? array_merge( $context, array( 'public_api_export_federation' => $addition ) )
			: array( 'public_api_export_federation' => $addition );
	}

	public function filter_handoff_sections( $sections, $project_id = 0, $args = array() ) {
		$context = $this->filter_project_context( array(), $project_id, $args );
		if ( empty( $context['public_api_export_federation'] ) ) {
			return $sections;
		}
		$sections = is_array( $sections ) ? $sections : array();
		$sections['public_api_export_federation'] = $context['public_api_export_federation'];
		return $sections;
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
			'processed'    => 0,
			'total'        => 0,
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

	private static function sanitize_datetime( $value ) {
		$value = str_replace( 'T', ' ', sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return '';
		}
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i' ) as $format ) {
			$date = DateTime::createFromFormat( $format, $value );
			if ( $date ) {
				return $date->format( 'Y-m-d H:i:s' );
			}
		}
		return '';
	}

	private static function ensure_uuid( $post_id, $meta_key ) {
		$uuid = (string) get_post_meta( $post_id, $meta_key, true );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, $meta_key, $uuid );
		}
		return $uuid;
	}

	private static function register_cli_commands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		WP_CLI::add_command(
			'sc-library api token-create',
			static function ( $args, $assoc ) {
				$result = self::create_token(
					$assoc['label'] ?? 'CLI token',
					isset( $assoc['scopes'] ) ? explode( ',', $assoc['scopes'] ) : array( 'catalog:read' ),
					$assoc['expires'] ?? '',
					absint( $assoc['rate-limit'] ?? self::DEFAULT_RATE_LIMIT )
				);
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library api token-revoke',
			static function ( $args ) {
				$token_id = absint( $args[0] ?? 0 );
				if ( self::TOKEN_POST_TYPE !== get_post_type( $token_id ) ) {
					WP_CLI::error( 'Token not found.' );
				}
				update_post_meta( $token_id, self::META_TOKEN_REVOKED, '1' );
				self::audit( 'token-revoked', array( 'token_id' => $token_id ) );
				WP_CLI::success( 'Token revoked.' );
			}
		);
		WP_CLI::add_command(
			'sc-library export create',
			static function ( $args, $assoc ) {
				$result = self::create_export_job(
					array(
						'format' => $assoc['format'] ?? 'json',
						'scope'  => $assoc['scope'] ?? 'documents',
						'public' => isset( $assoc['public'] ),
						'filters'=> array( 'search' => $assoc['search'] ?? '' ),
					),
					array( 'type' => 'cli' )
				);
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( 'Created export job ' . absint( $result['job_id'] ?? 0 ) . '.' );
			}
		);
		WP_CLI::add_command(
			'sc-library export run',
			static function ( $args, $assoc ) {
				$result = self::run_export_batch( absint( $args[0] ?? 0 ), absint( $assoc['limit'] ?? self::EXPORT_BATCH ) );
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library federation peer-check',
			static function ( $args ) {
				$result = self::check_peer( absint( $args[0] ?? 0 ) );
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
		WP_CLI::add_command(
			'sc-library federation import-decide',
			static function ( $args, $assoc ) {
				$result = self::decide_import( absint( $args[0] ?? 0 ), $assoc['decision'] ?? '', $assoc['note'] ?? '' );
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( 'Federation import updated.' );
			}
		);
		WP_CLI::add_command(
			'sc-library api migrate',
			static function ( $args, $assoc ) {
				$result = self::run_migration_batch( absint( $assoc['limit'] ?? self::MIGRATION_BATCH ) );
				is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( wp_json_encode( $result ) );
			}
		);
		WP_CLI::add_command(
			'sc-library api dashboard',
			static function () {
				WP_CLI::log( wp_json_encode( self::dashboard_report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		);
	}
}
