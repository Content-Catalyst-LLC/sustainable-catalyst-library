<?php
/**
 * Research Portability & Preservation — v4.3.39.
 *
 * Creates user-initiated, references-first research packages from the private
 * Research Project environment. Export never publishes, mutates, or copies
 * private binary files. Validation is non-executing and never imports data.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Research_Portability_Preservation {
    public const VERSION = '4.3.39';
    public const PACKAGE_SCHEMA = 'sc-library-research-portability-package/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-research-preservation-manifest/1.0';
    public const VALIDATION_SCHEMA = 'sc-library-research-portability-validation/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-portability';
    public const MAX_PACKAGE_BYTES = 8388608; // 8 MiB JSON validation boundary.
    public const MAX_EXPORT_PROJECTS = 50;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_research_portability', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_research_portability_package', array( $this, 'filter_package' ), 10, 3 );
    }

    public static function contract() {
        return array(
            'schema'                              => self::PACKAGE_SCHEMA,
            'export_is_user_initiated'            => true,
            'references_first'                    => true,
            'stable_urns_preserved'               => true,
            'private_binary_files_embedded'       => false,
            'credentials_embedded'                => false,
            'raw_wordpress_tables_embedded'       => false,
            'publication_implied'                 => false,
            'automatic_import'                    => false,
            'automatic_record_mutation'           => false,
            'automatic_workspace_write'           => false,
            'validation_executes_payload'          => false,
            'validation_creates_records'           => false,
            'existing_preservation_system_reused' => class_exists( 'SC_Library_Preservation' ),
        );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-research-portability-v4339', SC_LIBRARY_URL . 'assets/css/sc-library-research-portability-v4339.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-research-portability-v4339', SC_LIBRARY_URL . 'assets/js/sc-library-research-portability-v4339.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/catalog', array(
            'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_catalog' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/export', array(
            'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_export' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/validate', array(
            'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_validate' ),
        ) );
    }

    public function rest_signed_in() { return is_user_logged_in(); }

    private static function now() { return gmdate( 'c' ); }
    private static function clean_text( $value, $max = 240 ) { return mb_substr( sanitize_text_field( (string) $value ), 0, $max ); }

    private static function canonicalize( $value ) {
        if ( is_array( $value ) ) {
            $is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
            if ( ! $is_list ) { ksort( $value, SORT_STRING ); }
            foreach ( $value as $k => $v ) { $value[ $k ] = self::canonicalize( $v ); }
        }
        return $value;
    }

    private static function checksum( $value ) {
        return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    /** Remove binary/storage implementation fields while preserving references and URLs. */
    private static function scrub_for_portability( $value ) {
        if ( ! is_array( $value ) ) { return $value; }
        $blocked = array( 'attachment_id', 'attachment_path', 'file_path', 'local_path', 'binary', 'bytes', 'file_bytes', 'content_blob', 'credential', 'credentials', 'api_key', 'access_token', 'refresh_token', 'token', 'password', 'authorization', 'consumer_secret', 'secret' );
        $out = array();
        foreach ( $value as $key => $item ) {
            $key_text = is_string( $key ) ? strtolower( $key ) : $key;
            if ( is_string( $key_text ) && in_array( $key_text, $blocked, true ) ) { continue; }
            $out[ $key ] = self::scrub_for_portability( $item );
        }
        return $out;
    }

    private static function project_identity_urn( $project ) {
        $identity = (array) ( $project['project_identity'] ?? array() );
        foreach ( array( 'urn', 'project_urn', 'stable_urn' ) as $key ) {
            if ( ! empty( $identity[ $key ] ) ) { return (string) $identity[ $key ]; }
        }
        return '';
    }

    public static function catalog_for_user( $user_id ) {
        $user_id = absint( $user_id ); $projects = array();
        if ( ! $user_id || ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { return $projects; }
        foreach ( array_slice( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( $user_id ), 0, self::MAX_EXPORT_PROJECTS ) as $project_id ) {
            $state = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
            if ( is_wp_error( $state ) ) { continue; }
            $projects[] = array(
                'project_id' => absint( $project_id ), 'title' => (string) $state['title'], 'status' => (string) $state['status'],
                'project_urn' => self::project_identity_urn( $state ), 'reference_count' => absint( $state['reference_count'] ), 'bundle_count' => absint( $state['bundle_count'] ),
            );
        }
        return $projects;
    }

    private static function attached_notebooks( $user_id, $project_id, $include_content ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Reading_Notebook_Annotations' ) ) { return $out; }
        $state = SC_Library_Reading_Notebook_Annotations::state_for_user( $user_id );
        foreach ( (array) ( $state['notebooks'] ?? array() ) as $notebook ) {
            if ( absint( $notebook['project_context']['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
            if ( ! $include_content ) {
                unset( $notebook['notes'], $notebook['annotations'] );
                $notebook['content_omitted_by_export_profile'] = true;
            }
            $out[] = self::scrub_for_portability( $notebook );
        }
        return $out;
    }

    private static function attached_matrices( $user_id, $project_id, $include_content ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Evidence_Matrix_Claim_Intelligence' ) ) { return $out; }
        $state = SC_Library_Evidence_Matrix_Claim_Intelligence::state_for_user( $user_id );
        foreach ( (array) ( $state['matrices'] ?? array() ) as $matrix ) {
            if ( absint( $matrix['project_context']['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
            if ( ! $include_content ) {
                unset( $matrix['claims'], $matrix['evidence_links'], $matrix['diagnostics'] );
                $matrix['content_omitted_by_export_profile'] = true;
            }
            $out[] = self::scrub_for_portability( $matrix );
        }
        return $out;
    }

    private static function attached_learning_routes( $user_id, $project_id ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Open_Learning_II' ) ) { return $out; }
        $routes = get_user_meta( absint( $user_id ), SC_Library_Open_Learning_II::USER_META, true );
        foreach ( is_array( $routes ) ? $routes : array() as $route ) {
            if ( absint( $route['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
            $out[] = self::scrub_for_portability( SC_Library_Open_Learning_II::route_manifest( $route, $user_id ) );
        }
        return $out;
    }

    private static function bundle_manifests( $user_id, $project_id, $project_state ) {
        $out = array();
        foreach ( (array) ( $project_state['source_bundles'] ?? array() ) as $bundle ) {
            $bundle_id = (string) ( $bundle['bundle_id'] ?? '' ); if ( '' === $bundle_id ) { continue; }
            $manifest = SC_Library_Unified_Research_Projects_Source_Bundles::bundle_manifest( $project_id, $bundle_id, $user_id );
            if ( ! is_wp_error( $manifest ) ) { $out[] = self::scrub_for_portability( $manifest ); }
        }
        return $out;
    }

    public static function build_package( $user_id, $project_id, $profile = 'complete' ) {
        $user_id = absint( $user_id ); $project_id = absint( $project_id );
        $profile = in_array( $profile, array( 'complete', 'manifest' ), true ) ? $profile : 'complete';
        if ( ! $user_id || ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) || ! SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( $project_id, $user_id ) ) {
            return new WP_Error( 'sc_portability_project_forbidden', __( 'Choose a Research Project owned by this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $project = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
        if ( is_wp_error( $project ) ) { return $project; }
        $include_content = 'complete' === $profile;
        $project = self::scrub_for_portability( $project );
        unset( $project['owner_user_id'] );
        $sections = array(
            'project'         => $project,
            'source_bundles'  => self::bundle_manifests( $user_id, $project_id, $project ),
            'notebooks'       => self::attached_notebooks( $user_id, $project_id, $include_content ),
            'evidence_matrices'=> self::attached_matrices( $user_id, $project_id, $include_content ),
            'learning_routes' => self::attached_learning_routes( $user_id, $project_id ),
        );
        $section_checksums = array(); foreach ( $sections as $name => $section ) { $section_checksums[ $name ] = self::checksum( $section ); }
        $export_uuid = wp_generate_uuid4();
        $manifest = array(
            'schema'                    => self::MANIFEST_SCHEMA,
            'export_id'                 => 'research-export-' . $export_uuid,
            'export_urn'                => 'urn:sc:research-export:' . $export_uuid,
            'source_plugin_version'     => self::VERSION,
            'package_schema'            => self::PACKAGE_SCHEMA,
            'profile'                   => $profile,
            'generated_at'              => self::now(),
            'canonical_library_url'     => class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ),
            'project_urn'               => self::project_identity_urn( $project ),
            'section_checksums_sha256'  => $section_checksums,
            'integrity_algorithm'       => 'sha256',
            'portable_representation'   => 'json',
            'preservation_note'         => 'Portable research snapshot. References and user-authored research context are preserved; source binaries and credentials are not embedded.',
            'existing_archive_system'   => class_exists( 'SC_Library_Preservation' ) ? SC_Library_Preservation::SCHEMA : null,
        );
        $manifest['manifest_checksum_sha256'] = self::checksum( $manifest );
        $package = array( 'schema' => self::PACKAGE_SCHEMA, 'version' => self::VERSION, 'manifest' => $manifest, 'sections' => $sections, 'contract' => self::contract() );
        $package = apply_filters( 'sc_library_research_portability_package', $package, $user_id, $project_id );
        if ( ! is_array( $package ) ) { return new WP_Error( 'sc_portability_filter_invalid', __( 'The research portability package filter returned invalid data.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) ); }
        unset( $package['package_checksum_sha256'] );
        $package['package_checksum_sha256'] = self::checksum( $package );
        return $package;
    }

    public function filter_package( $package, $user_id, $project_id ) { return $package; }

    public static function validate_package( $package ) {
        $warnings = array(); $errors = array();
        if ( ! is_array( $package ) ) { return array( 'schema' => self::VALIDATION_SCHEMA, 'valid' => false, 'errors' => array( 'Package must be decoded JSON object data.' ), 'warnings' => array(), 'automatic_import' => false ); }
        $encoded = wp_json_encode( $package );
        if ( false === $encoded || strlen( $encoded ) > self::MAX_PACKAGE_BYTES ) { $errors[] = 'Package exceeds the 8 MiB validation boundary or cannot be encoded.'; }
        if ( self::PACKAGE_SCHEMA !== (string) ( $package['schema'] ?? '' ) ) { $errors[] = 'Unsupported research portability package schema.'; }
        $provided_package_checksum = (string) ( $package['package_checksum_sha256'] ?? '' );
        $for_checksum = $package; unset( $for_checksum['package_checksum_sha256'] );
        $package_checksum_valid = '' !== $provided_package_checksum && hash_equals( $provided_package_checksum, self::checksum( $for_checksum ) );
        if ( ! $package_checksum_valid ) { $errors[] = 'Package checksum does not match the payload.'; }
        $manifest = (array) ( $package['manifest'] ?? array() );
        $manifest_checksum = (string) ( $manifest['manifest_checksum_sha256'] ?? '' );
        $manifest_copy = $manifest; unset( $manifest_copy['manifest_checksum_sha256'] );
        $manifest_checksum_valid = '' !== $manifest_checksum && hash_equals( $manifest_checksum, self::checksum( $manifest_copy ) );
        if ( ! $manifest_checksum_valid ) { $errors[] = 'Preservation manifest checksum does not match.'; }
        $sections = (array) ( $package['sections'] ?? array() );
        $declared = (array) ( $manifest['section_checksums_sha256'] ?? array() );
        $section_results = array();
        foreach ( array( 'project', 'source_bundles', 'notebooks', 'evidence_matrices', 'learning_routes' ) as $name ) {
            $actual = self::checksum( $sections[ $name ] ?? array() ); $expected = (string) ( $declared[ $name ] ?? '' );
            $ok = '' !== $expected && hash_equals( $expected, $actual ); $section_results[ $name ] = array( 'valid' => $ok, 'sha256' => $actual );
            if ( ! $ok ) { $errors[] = 'Section checksum mismatch: ' . $name . '.'; }
        }
        $source_version = self::clean_text( $manifest['source_plugin_version'] ?? '', 40 );
        if ( '' !== $source_version && version_compare( $source_version, self::VERSION, '>' ) ) { $warnings[] = 'Package was created by a newer Library release; review compatibility before any manual restoration.'; }
        if ( 'manifest' === (string) ( $manifest['profile'] ?? '' ) ) { $warnings[] = 'Manifest-only package intentionally omits notebook and evidence-matrix content bodies.'; }
        return array(
            'schema' => self::VALIDATION_SCHEMA, 'version' => self::VERSION, 'valid' => empty( $errors ),
            'package_checksum_valid' => $package_checksum_valid, 'manifest_checksum_valid' => $manifest_checksum_valid,
            'section_checksums' => $section_results, 'errors' => $errors, 'warnings' => $warnings,
            'source_plugin_version' => $source_version, 'current_plugin_version' => self::VERSION,
            'automatic_import' => false, 'records_created' => 0, 'payload_executed' => false,
            'next_step' => empty( $errors ) ? 'Package is structurally intact. Restoration remains an explicit future/manual operation.' : 'Do not restore this package until integrity errors are resolved.',
        );
    }

    public function rest_catalog() {
        return rest_ensure_response( array( 'schema' => 'sc-library-research-portability-catalog/1.0', 'version' => self::VERSION, 'projects' => self::catalog_for_user( get_current_user_id() ), 'profiles' => array( 'complete', 'manifest' ), 'contract' => self::contract() ) );
    }
    public function rest_export( WP_REST_Request $request ) {
        $body = (array) $request->get_json_params();
        $result = self::build_package( get_current_user_id(), absint( $body['project_id'] ?? 0 ), sanitize_key( (string) ( $body['profile'] ?? 'complete' ) ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }
    public function rest_validate( WP_REST_Request $request ) {
        $body = (array) $request->get_json_params(); $package = isset( $body['package'] ) ? $body['package'] : $body;
        return rest_ensure_response( self::validate_package( $package ) );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Research Portability & Preservation', 'sustainable-catalyst-library' ) ), $atts, 'sc_research_portability' );
        wp_enqueue_style( 'sc-library-research-portability-v4339' ); wp_enqueue_script( 'sc-library-research-portability-v4339' );
        $signed = is_user_logged_in();
        wp_localize_script( 'sc-library-research-portability-v4339', 'scResearchPortability', array(
            'restRoot' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'signedIn' => $signed,
            'loginUrl' => wp_login_url( (string) get_permalink() ), 'projects' => $signed ? self::catalog_for_user( get_current_user_id() ) : array(),
        ) );
        ob_start(); ?>
        <section class="sc-research-portability" data-sc-research-portability>
            <header><p class="sc-rp-kicker"><?php esc_html_e( 'Private research · portable by design', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3>
            <p><?php esc_html_e( 'Export an owned Research Project as a checksummed JSON preservation package. Stable identities, references, Source Bundle manifests, project-linked notebooks, evidence matrices, and learning routes travel together without embedding private source binaries or credentials.', 'sustainable-catalyst-library' ); ?></p></header>
            <?php if ( ! $signed ) : ?><p><a class="sc-rp-button" href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>"><?php esc_html_e( 'Sign in to export private research', 'sustainable-catalyst-library' ); ?></a></p><?php else : ?>
            <div class="sc-rp-grid">
                <article class="sc-rp-panel"><h4><?php esc_html_e( 'Create a portable package', 'sustainable-catalyst-library' ); ?></h4>
                    <label><?php esc_html_e( 'Research Project', 'sustainable-catalyst-library' ); ?><select data-sc-rp-project><option value=""><?php esc_html_e( 'Choose a project', 'sustainable-catalyst-library' ); ?></option></select></label>
                    <label><?php esc_html_e( 'Export profile', 'sustainable-catalyst-library' ); ?><select data-sc-rp-profile><option value="complete"><?php esc_html_e( 'Complete research package', 'sustainable-catalyst-library' ); ?></option><option value="manifest"><?php esc_html_e( 'Manifest only — identities and references', 'sustainable-catalyst-library' ); ?></option></select></label>
                    <button type="button" class="sc-rp-button" data-sc-rp-export><?php esc_html_e( 'Create JSON preservation package', 'sustainable-catalyst-library' ); ?></button>
                    <p class="sc-rp-note"><?php esc_html_e( 'Complete packages may include your own notebook/annotation and evidence-matrix text. Source binaries, credentials, raw WordPress tables, and provider secrets are excluded.', 'sustainable-catalyst-library' ); ?></p>
                </article>
                <article class="sc-rp-panel"><h4><?php esc_html_e( 'Validate a package', 'sustainable-catalyst-library' ); ?></h4>
                    <label><?php esc_html_e( 'Research package JSON', 'sustainable-catalyst-library' ); ?><input type="file" accept="application/json,.json" data-sc-rp-file></label>
                    <button type="button" class="sc-rp-button sc-rp-button-secondary" data-sc-rp-validate><?php esc_html_e( 'Validate integrity', 'sustainable-catalyst-library' ); ?></button>
                    <p class="sc-rp-note"><?php esc_html_e( 'Validation checks schema and SHA-256 integrity only. It does not execute the payload, create records, import data, publish anything, or write to Workspace.', 'sustainable-catalyst-library' ); ?></p>
                </article>
            </div>
            <div class="sc-rp-output" data-sc-rp-output aria-live="polite"></div>
            <?php endif; ?>
        </section><?php return ob_get_clean();
    }
}
