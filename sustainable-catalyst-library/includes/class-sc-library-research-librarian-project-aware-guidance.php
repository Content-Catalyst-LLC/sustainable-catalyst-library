<?php
/**
 * Research Librarian II — Project-Aware Guidance — v4.3.38.
 *
 * Builds a private, account-owned context packet from existing Research
 * Projects, Source Bundles, Reading Notebooks, and Evidence Matrices. The
 * packet is analyzed deterministically and may seed the existing Research
 * Librarian with public Research Source IDs only. Private context is never
 * forwarded to the optional remote synthesis endpoint by this module.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Research_Librarian_Project_Aware_Guidance {
    public const VERSION = '4.3.38';
    public const SCHEMA = 'sc-library-research-librarian-project-guidance/1.0';
    public const CONTEXT_SCHEMA = 'sc-library-project-guidance-context/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-librarian-v2';
    public const NONCE_ACTION = 'sc_library_research_librarian_v2_v4338';
    public const MAX_NOTE_PREVIEWS = 8;
    public const MAX_CLAIM_PREVIEWS = 20;
    public const MAX_PUBLIC_RECORD_IDS = 8;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_research_librarian_ii', array( $this, 'shortcode' ) );
        add_shortcode( 'sc_project_aware_research_librarian', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_research_librarian_project_context', array( $this, 'filter_context' ), 10, 3 );
        add_filter( 'sc_library_project_aware_guidance', array( $this, 'filter_guidance' ), 10, 3 );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-research-librarian-ii-v4338', SC_LIBRARY_URL . 'assets/css/sc-library-research-librarian-ii-v4338.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-research-librarian-ii-v4338', SC_LIBRARY_URL . 'assets/js/sc-library-research-librarian-ii-v4338.js', array(), SC_LIBRARY_VERSION, true );
    }

    public static function contract() {
        return array(
            'schema'                                  => self::SCHEMA,
            'record_owner'                            => 'current-wordpress-user',
            'visibility'                              => 'private',
            'shared_library_workspace_account'        => true,
            'reads_existing_private_records'          => true,
            'copies_underlying_private_records'       => false,
            'private_context_sent_to_remote_synthesis'=> false,
            'public_source_ids_may_seed_orchestrator' => true,
            'guidance_mode'                           => 'deterministic-descriptive',
            'automatic_project_write'                 => false,
            'automatic_notebook_write'                => false,
            'automatic_evidence_promotion'            => false,
            'automatic_claim_creation'                => false,
            'automatic_publication'                   => false,
            'automatic_workspace_write'               => false,
            'user_confirmation_required_for_actions'  => true,
        );
    }

    private static function clean_text( $value, $limit = 240 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function clean_prompt( $value ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1200 ) : substr( $value, 0, 1200 );
    }

    private static function project_module_ready() {
        return class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' );
    }

    public static function context_catalog_for_user( $user_id ) {
        $user_id = absint( $user_id );
        $catalog = array();
        if ( ! $user_id || ! self::project_module_ready() ) { return $catalog; }

        $notebooks = class_exists( 'SC_Library_Reading_Notebook_Annotations' )
            ? SC_Library_Reading_Notebook_Annotations::state_for_user( $user_id )
            : array( 'notebooks' => array() );
        $matrices = class_exists( 'SC_Library_Evidence_Matrix_Claim_Intelligence' )
            ? SC_Library_Evidence_Matrix_Claim_Intelligence::state_for_user( $user_id )
            : array( 'matrices' => array() );

        foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( $user_id ) as $project_id ) {
            $state = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
            if ( is_wp_error( $state ) ) { continue; }
            $item = array(
                'project_id'        => absint( $project_id ),
                'title'             => (string) $state['title'],
                'status'            => (string) $state['status'],
                'research_question' => (string) $state['research_question'],
                'reference_count'   => absint( $state['reference_count'] ),
                'bundle_count'      => absint( $state['bundle_count'] ),
                'bundles'           => array(),
                'notebooks'         => array(),
                'matrices'          => array(),
            );
            foreach ( (array) $state['source_bundles'] as $bundle ) {
                $item['bundles'][] = array(
                    'bundle_id' => (string) ( $bundle['bundle_id'] ?? '' ),
                    'title'     => (string) ( $bundle['title'] ?? 'Source bundle' ),
                    'purpose'   => (string) ( $bundle['purpose'] ?? 'working_set' ),
                    'link_count'=> count( (array) ( $bundle['link_ids'] ?? array() ) ),
                );
            }
            foreach ( (array) ( $notebooks['notebooks'] ?? array() ) as $notebook ) {
                $ctx = (array) ( $notebook['project_context'] ?? array() );
                if ( absint( $ctx['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
                $item['notebooks'][] = array(
                    'notebook_id'      => absint( $notebook['notebook_id'] ?? 0 ),
                    'title'            => (string) ( $notebook['title'] ?? '' ),
                    'note_count'       => absint( $notebook['note_count'] ?? 0 ),
                    'annotation_count' => absint( $notebook['annotation_count'] ?? 0 ),
                    'bundle_id'        => (string) ( $ctx['bundle_id'] ?? '' ),
                );
            }
            foreach ( (array) ( $matrices['matrices'] ?? array() ) as $matrix ) {
                $ctx = (array) ( $matrix['project_context'] ?? array() );
                if ( absint( $ctx['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
                $item['matrices'][] = array(
                    'matrix_id'           => absint( $matrix['matrix_id'] ?? 0 ),
                    'title'               => (string) ( $matrix['title'] ?? '' ),
                    'claim_count'         => absint( $matrix['claim_count'] ?? 0 ),
                    'evidence_link_count' => absint( $matrix['evidence_link_count'] ?? 0 ),
                    'bundle_id'           => (string) ( $ctx['bundle_id'] ?? '' ),
                );
            }
            $catalog[] = $item;
        }
        return $catalog;
    }

    private static function select_bundle( $project_id, $bundle_id, $user_id ) {
        if ( '' === (string) $bundle_id ) { return null; }
        $manifest = SC_Library_Unified_Research_Projects_Source_Bundles::bundle_manifest( $project_id, self::clean_text( $bundle_id, 80 ), $user_id );
        return is_wp_error( $manifest ) ? $manifest : $manifest;
    }

    private static function notebook_preview( $notebook, $user_id ) {
        $state = SC_Library_Reading_Notebook_Annotations::notebook_state( absint( $notebook ), $user_id );
        if ( is_wp_error( $state ) ) { return $state; }
        $previews = array();
        foreach ( array_slice( (array) $state['notes'], 0, self::MAX_NOTE_PREVIEWS ) as $note ) {
            $body = self::clean_text( $note['excerpt'] ?? ( $note['body'] ?? '' ), 260 );
            $previews[] = array(
                'kind'       => 'note',
                'id'         => (string) ( $note['id'] ?? '' ),
                'title'      => (string) ( $note['title'] ?? 'Reading note' ),
                'preview'    => $body,
                'source'     => (string) ( $note['source_resolution']['label'] ?? ( $note['source_label'] ?? '' ) ),
                'source_url' => (string) ( $note['source_resolution']['url'] ?? ( $note['source_url'] ?? '' ) ),
            );
        }
        foreach ( array_slice( (array) $state['annotations'], 0, self::MAX_NOTE_PREVIEWS ) as $annotation ) {
            $body = self::clean_text( $annotation['excerpt'] ?? ( $annotation['body'] ?? '' ), 260 );
            $previews[] = array(
                'kind'       => 'annotation',
                'id'         => (string) ( $annotation['id'] ?? '' ),
                'title'      => 'Source annotation',
                'preview'    => $body,
                'source'     => (string) ( $annotation['source_resolution']['label'] ?? ( $annotation['source_label'] ?? '' ) ),
                'source_url' => (string) ( $annotation['source_resolution']['url'] ?? ( $annotation['source_url'] ?? '' ) ),
            );
        }
        return array(
            'notebook_id'      => absint( $state['notebook_id'] ),
            'title'            => (string) $state['title'],
            'note_count'       => absint( $state['note_count'] ),
            'annotation_count' => absint( $state['annotation_count'] ),
            'previews'         => array_slice( $previews, 0, self::MAX_NOTE_PREVIEWS ),
            'project_context'  => (array) $state['project_context'],
        );
    }

    private static function matrix_preview( $matrix_id, $user_id ) {
        $state = SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state( absint( $matrix_id ), $user_id );
        if ( is_wp_error( $state ) ) { return $state; }
        $claims = array();
        foreach ( array_slice( (array) $state['claims'], 0, self::MAX_CLAIM_PREVIEWS ) as $claim ) {
            $diagnostic = (array) ( $state['diagnostics'][ $claim['id'] ] ?? array() );
            $claims[] = array(
                'id'         => (string) ( $claim['id'] ?? '' ),
                'title'      => (string) ( $claim['title'] ?? '' ),
                'statement'  => self::clean_text( $claim['statement'] ?? '', 360 ),
                'status'     => (string) ( $claim['status'] ?? '' ),
                'confidence' => (string) ( $claim['confidence'] ?? '' ),
                'pattern'    => (string) ( $diagnostic['pattern'] ?? '' ),
                'gaps'       => array_values( array_map( 'sanitize_key', (array) ( $diagnostic['gaps'] ?? array() ) ) ),
            );
        }
        return array(
            'matrix_id'           => absint( $state['matrix_id'] ),
            'title'               => (string) $state['title'],
            'claim_count'         => absint( $state['claim_count'] ),
            'evidence_link_count' => absint( $state['evidence_link_count'] ),
            'claims'              => $claims,
            'project_context'     => (array) $state['project_context'],
        );
    }

    public static function build_context( $user_id, $input ) {
        $user_id = absint( $user_id );
        $project_id = absint( $input['project_id'] ?? 0 );
        if ( ! $user_id || ! self::project_module_ready() || ! SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( $project_id, $user_id ) ) {
            return new WP_Error( 'sc_librarian_v2_project_forbidden', __( 'Choose a Research Project owned by this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $project = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
        if ( is_wp_error( $project ) ) { return $project; }

        $bundle_id = self::clean_text( $input['bundle_id'] ?? '', 80 );
        $notebook_id = absint( $input['notebook_id'] ?? 0 );
        $matrix_id = absint( $input['matrix_id'] ?? 0 );
        $bundle = self::select_bundle( $project_id, $bundle_id, $user_id );
        if ( is_wp_error( $bundle ) ) { return $bundle; }

        $notebook = null;
        if ( $notebook_id ) {
            if ( ! class_exists( 'SC_Library_Reading_Notebook_Annotations' ) ) { return new WP_Error( 'sc_librarian_v2_notebook_unavailable', __( 'Reading Notebook context is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) ); }
            $notebook = self::notebook_preview( $notebook_id, $user_id );
            if ( is_wp_error( $notebook ) ) { return $notebook; }
            if ( absint( $notebook['project_context']['project_id'] ?? 0 ) !== $project_id ) { return new WP_Error( 'sc_librarian_v2_notebook_context', __( 'That notebook is not attached to the selected project.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        }

        $matrix = null;
        if ( $matrix_id ) {
            if ( ! class_exists( 'SC_Library_Evidence_Matrix_Claim_Intelligence' ) ) { return new WP_Error( 'sc_librarian_v2_matrix_unavailable', __( 'Evidence Matrix context is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) ); }
            $matrix = self::matrix_preview( $matrix_id, $user_id );
            if ( is_wp_error( $matrix ) ) { return $matrix; }
            if ( absint( $matrix['project_context']['project_id'] ?? 0 ) !== $project_id ) { return new WP_Error( 'sc_librarian_v2_matrix_context', __( 'That evidence matrix is not attached to the selected project.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        }

        $public_ids = array();
        $unresolved = 0;
        $family_counts = array();
        foreach ( (array) $project['references'] as $reference ) {
            $family = sanitize_key( $reference['family'] ?? 'external' );
            $family_counts[ $family ] = absint( $family_counts[ $family ] ?? 0 ) + 1;
            if ( empty( $reference['resolution']['resolved'] ) ) { $unresolved++; }
            if ( 'source' === $family && ctype_digit( (string) ( $reference['ref_id'] ?? '' ) ) ) {
                $post_id = absint( $reference['ref_id'] );
                if ( $post_id && 'publish' === get_post_status( $post_id ) ) { $public_ids[] = $post_id; }
            }
        }
        $public_ids = array_slice( array_values( array_unique( $public_ids ) ), 0, self::MAX_PUBLIC_RECORD_IDS );

        $context = array(
            'schema'             => self::CONTEXT_SCHEMA,
            'version'            => self::VERSION,
            'visibility'         => 'private',
            'owner_user_id'      => $user_id,
            'project'            => array(
                'project_id'        => $project_id,
                'title'             => (string) $project['title'],
                'status'            => (string) $project['status'],
                'research_question' => (string) $project['research_question'],
                'reference_count'   => absint( $project['reference_count'] ),
                'bundle_count'      => absint( $project['bundle_count'] ),
                'identity'          => (array) $project['project_identity'],
                'reference_families'=> $family_counts,
                'unresolved_references' => $unresolved,
            ),
            'selected_bundle'    => $bundle ? array(
                'bundle_id'       => (string) ( $bundle['bundle']['bundle_id'] ?? '' ),
                'title'           => (string) ( $bundle['bundle']['title'] ?? '' ),
                'purpose'         => (string) ( $bundle['bundle']['purpose'] ?? '' ),
                'reference_count' => absint( $bundle['reference_count'] ?? 0 ),
                'checksum_sha256' => (string) ( $bundle['checksum_sha256'] ?? '' ),
            ) : null,
            'selected_notebook'  => $notebook,
            'selected_matrix'    => $matrix,
            'public_record_ids'  => $public_ids,
            'contract'           => self::contract(),
        );
        $context['checksum_sha256'] = hash( 'sha256', wp_json_encode( $context ) );
        return apply_filters( 'sc_library_research_librarian_project_context_packet', $context, $user_id, $input );
    }

    private static function add_guidance( &$items, $priority, $title, $reason, $target, $kind ) {
        $items[] = array(
            'priority' => absint( $priority ),
            'title'    => self::clean_text( $title, 180 ),
            'reason'   => self::clean_text( $reason, 420 ),
            'target'   => self::clean_text( $target, 160 ),
            'kind'     => sanitize_key( $kind ),
        );
    }

    public static function guidance_for_context( $prompt, $context ) {
        $prompt = self::clean_prompt( $prompt );
        $project = (array) $context['project'];
        $notebook = (array) ( $context['selected_notebook'] ?? array() );
        $matrix = (array) ( $context['selected_matrix'] ?? array() );
        $bundle = (array) ( $context['selected_bundle'] ?? array() );
        $items = array();

        if ( absint( $project['reference_count'] ?? 0 ) < 3 ) {
            self::add_guidance( $items, 10, 'Strengthen the source base', 'This project has fewer than three linked references. Add independent primary, scholarly, institutional, or data sources before drawing strong conclusions.', '#citation-studio', 'source_base' );
        }
        if ( absint( $project['unresolved_references'] ?? 0 ) > 0 ) {
            self::add_guidance( $items, 20, 'Resolve broken or ambiguous references', 'One or more project references no longer resolve cleanly. Review the project before relying on those references in a brief or evidence matrix.', '#research-projects', 'reference_integrity' );
        }
        if ( empty( $bundle ) && absint( $project['reference_count'] ?? 0 ) >= 3 ) {
            self::add_guidance( $items, 30, 'Create a focused Source Bundle', 'The project has multiple references but this guidance session is not scoped to a bundle. A working, evidence, review, or briefing bundle can reduce noise without duplicating sources.', '#research-projects', 'source_bundle' );
        }
        if ( empty( $notebook ) ) {
            self::add_guidance( $items, 40, 'Attach a Reading Notebook', 'No Reading Notebook is selected. A project-linked notebook keeps excerpts, annotations, and your own reasoning distinct from source metadata and later evidence claims.', '#reading-notebooks', 'reading_notebook' );
        } elseif ( absint( $notebook['annotation_count'] ?? 0 ) === 0 ) {
            self::add_guidance( $items, 45, 'Capture locatable annotations', 'The selected notebook has no source annotations. Record page, section, timestamp, paragraph, or other locators before promoting material into an evidence workflow.', '#reading-notebooks', 'annotation' );
        }
        if ( empty( $matrix ) ) {
            self::add_guidance( $items, 50, 'Open an Evidence Matrix when claims emerge', 'No Evidence Matrix is selected. Use one when the project moves from exploration into explicit claims so support, qualification, contradiction, and unresolved evidence remain visible.', '#evidence-matrix', 'evidence_matrix' );
        } else {
            $gap_counts = array();
            foreach ( (array) ( $matrix['claims'] ?? array() ) as $claim ) {
                foreach ( (array) ( $claim['gaps'] ?? array() ) as $gap ) { $gap_counts[ $gap ] = absint( $gap_counts[ $gap ] ?? 0 ) + 1; }
            }
            if ( ! empty( $gap_counts['no_counterevidence_recorded'] ) ) {
                self::add_guidance( $items, 55, 'Seek counterevidence deliberately', 'At least one selected-matrix claim has no recorded counterevidence. Search for credible sources that could weaken, bound, or falsify the working claim.', '#evidence-matrix', 'counterevidence' );
            }
            if ( ! empty( $gap_counts['unchecked_quote_or_locator'] ) ) {
                self::add_guidance( $items, 56, 'Verify quotations and locators', 'At least one evidence relationship has unchecked wording or locator metadata. Verify the passage before using it in a research output.', '#evidence-matrix', 'verification' );
            }
            if ( ! empty( $gap_counts['single_source_dependency'] ) ) {
                self::add_guidance( $items, 57, 'Diversify evidence sources', 'At least one claim depends on a single source family. Add independent evidence before increasing confidence.', '#evidence-matrix', 'source_diversity' );
            }
        }

        $lower = strtolower( $prompt );
        if ( preg_match( '/\b(access|obtain|download|borrow|paywall|full text|full-text)\b/', $lower ) ) {
            self::add_guidance( $items, 60, 'Check Access Intelligence II', 'Your question includes an access intent. Use the access planner to distinguish availability, holdings, entitlement, and eligibility rather than assuming a search result is obtainable.', '#research-access', 'access' );
        }
        if ( preg_match( '/\b(learn|course|teach|study|prerequisite|training)\b/', $lower ) ) {
            self::add_guidance( $items, 65, 'Build an Open Learning route', 'Your question includes a learning intent. Open Learning II can sequence declared course levels and relevance without inventing prerequisites or enrollment guarantees.', '#open-course-finder', 'learning' );
        }
        if ( preg_match( '/\b(publish|article|publication|public|explain)\b/', $lower ) ) {
            self::add_guidance( $items, 70, 'Review the Publications ↔ Research Graph boundary', 'If this work is moving toward publication, expose only explicitly public graph relationships. Private notebooks, matrices, project notes, queues, and bundles remain private.', '#publications-research-graph', 'publication_graph' );
        }
        if ( empty( $items ) ) {
            self::add_guidance( $items, 80, 'Continue the scoped investigation', 'The selected project already has a working source, reading, and evidence structure. Use the existing Research Librarian below for public-record retrieval while keeping this private context packet alongside the response.', '#research-librarian', 'continue' );
        }
        usort( $items, static fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
        return array_slice( $items, 0, 8 );
    }

    public static function build_guidance_packet( $user_id, $input ) {
        $context = self::build_context( $user_id, $input );
        if ( is_wp_error( $context ) ) { return $context; }
        $prompt = self::clean_prompt( $input['prompt'] ?? '' );
        if ( strlen( $prompt ) < 3 ) { return new WP_Error( 'sc_librarian_v2_prompt', __( 'Enter a more specific research question or task.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $guidance = self::guidance_for_context( $prompt, $context );
        $packet = array(
            'schema'      => self::SCHEMA,
            'version'     => self::VERSION,
            'id'          => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'sc-guidance-', true ),
            'created_at'  => current_time( 'c' ),
            'prompt'      => $prompt,
            'context'     => $context,
            'guidance'    => $guidance,
            'orchestrator_handoff' => array(
                'prompt'            => $prompt,
                'record_ids'        => array_values( (array) $context['public_record_ids'] ),
                'private_context_included' => false,
                'target'            => '#research-librarian',
                'event'             => 'sc-library-librarian-request',
            ),
            'contract'     => self::contract(),
        );
        $packet['checksum_sha256'] = hash( 'sha256', wp_json_encode( $packet ) );
        do_action( 'sc_library_research_librarian_project_guidance_generated', $packet, $user_id );
        return apply_filters( 'sc_library_project_aware_guidance_packet', $packet, $user_id, $input );
    }

    public function filter_context( $context, $user_id, $input ) {
        $built = self::build_context( $user_id, (array) $input );
        return is_wp_error( $built ) ? $context : $built;
    }

    public function filter_guidance( $guidance, $user_id, $input ) {
        $packet = self::build_guidance_packet( $user_id, (array) $input );
        return is_wp_error( $packet ) ? $guidance : $packet;
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/catalog', array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => static fn() => is_user_logged_in(),
            'callback'            => static fn() => rest_ensure_response( array(
                'schema'   => 'sc-library-project-guidance-catalog/1.0',
                'version'  => self::VERSION,
                'user_id'  => get_current_user_id(),
                'projects' => self::context_catalog_for_user( get_current_user_id() ),
                'contract' => self::contract(),
            ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/guidance', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static fn() => is_user_logged_in(),
            'callback'            => function( WP_REST_Request $request ) {
                $packet = self::build_guidance_packet( get_current_user_id(), $request->get_json_params() ?: $request->get_params() );
                if ( is_wp_error( $packet ) ) {
                    $status = absint( $packet->get_error_data()['status'] ?? 400 );
                    return new WP_REST_Response( array( 'message' => $packet->get_error_message() ), $status ?: 400 );
                }
                return rest_ensure_response( $packet );
            },
        ) );
    }

    public function shortcode( $atts = array() ) {
        $atts = shortcode_atts( array( 'title' => 'Research Librarian II — Project-Aware Guidance' ), $atts, 'sc_research_librarian_ii' );
        if ( ! is_user_logged_in() ) {
            return '<section class="sc-librarian-v2 sc-librarian-v2--signed-out"><h3>' . esc_html( $atts['title'] ) . '</h3><p>' . esc_html__( 'Sign in with your Sustainable Catalyst account to use private project-aware guidance.', 'sustainable-catalyst-library' ) . '</p></section>';
        }
        $catalog = self::context_catalog_for_user( get_current_user_id() );
        wp_enqueue_style( 'sc-library-research-librarian-ii-v4338' );
        wp_enqueue_script( 'sc-library-research-librarian-ii-v4338' );
        wp_localize_script( 'sc-library-research-librarian-ii-v4338', 'SCResearchLibrarianV2', array(
            'restBase' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'version'  => self::VERSION,
        ) );
        ob_start();
        ?>
        <section class="sc-librarian-v2" data-sc-research-librarian-v2>
            <header class="sc-librarian-v2__header">
                <p class="sc-librarian-v2__kicker"><?php esc_html_e( 'Private project context', 'sustainable-catalyst-library' ); ?></p>
                <h3><?php echo esc_html( $atts['title'] ); ?></h3>
                <p><?php esc_html_e( 'Select an owned Research Project and, optionally, a Source Bundle, Reading Notebook, or Evidence Matrix. The guidance below is deterministic and private; only public Research Source IDs may be passed into the existing Research Librarian.', 'sustainable-catalyst-library' ); ?></p>
            </header>
            <?php if ( ! $catalog ) : ?>
                <p class="sc-librarian-v2__empty"><?php esc_html_e( 'Create a Research Project first, then return here to scope the Research Librarian to that work.', 'sustainable-catalyst-library' ); ?></p>
            <?php else : ?>
                <form class="sc-librarian-v2__form" data-sc-librarian-v2-form>
                    <label><span><?php esc_html_e( 'Research Project', 'sustainable-catalyst-library' ); ?></span><select name="project_id" required><option value=""><?php esc_html_e( 'Choose a project', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $catalog as $project ) : ?><option value="<?php echo esc_attr( $project['project_id'] ); ?>"><?php echo esc_html( $project['title'] ); ?></option><?php endforeach; ?></select></label>
                    <div class="sc-librarian-v2__context-grid">
                        <label><span><?php esc_html_e( 'Source Bundle', 'sustainable-catalyst-library' ); ?></span><select name="bundle_id"><option value=""><?php esc_html_e( 'Whole project', 'sustainable-catalyst-library' ); ?></option></select></label>
                        <label><span><?php esc_html_e( 'Reading Notebook', 'sustainable-catalyst-library' ); ?></span><select name="notebook_id"><option value="0"><?php esc_html_e( 'No notebook selected', 'sustainable-catalyst-library' ); ?></option></select></label>
                        <label><span><?php esc_html_e( 'Evidence Matrix', 'sustainable-catalyst-library' ); ?></span><select name="matrix_id"><option value="0"><?php esc_html_e( 'No matrix selected', 'sustainable-catalyst-library' ); ?></option></select></label>
                    </div>
                    <label><span><?php esc_html_e( 'Question or research task', 'sustainable-catalyst-library' ); ?></span><textarea name="prompt" rows="3" maxlength="1200" required placeholder="<?php esc_attr_e( 'What should I investigate next? Where are the evidence gaps? What sources should I look for?', 'sustainable-catalyst-library' ); ?>"></textarea></label>
                    <div class="sc-librarian-v2__actions"><button type="submit"><?php esc_html_e( 'Generate project-aware guidance', 'sustainable-catalyst-library' ); ?></button><small><?php esc_html_e( 'No project, notebook, evidence, publication, or Workspace record is changed.', 'sustainable-catalyst-library' ); ?></small></div>
                </form>
                <div class="sc-librarian-v2__notice" data-sc-librarian-v2-notice hidden aria-live="polite"></div>
                <div class="sc-librarian-v2__output" data-sc-librarian-v2-output></div>
                <script type="application/json" data-sc-librarian-v2-catalog><?php echo wp_json_encode( $catalog ); ?></script>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
