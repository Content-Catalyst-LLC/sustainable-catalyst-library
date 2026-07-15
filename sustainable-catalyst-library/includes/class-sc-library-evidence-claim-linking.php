<?php
/**
 * Quotations, Evidence Notes, and Claim Linking.
 *
 * Structured quotations and evidence notes, page-precise locators, research
 * claims, support/contradict/context links, project evidence packets,
 * citation-ready exports, review states, permissions, and REST APIs.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Evidence_Claim_Linking {
    public const VERSION = '2.7.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const NOTE_SCHEMA = 'sc-library-evidence-note/1.0';
    public const CLAIM_SCHEMA = 'sc-library-research-claim/1.0';
    public const LINK_SCHEMA = 'sc-library-claim-evidence-link/1.0';
    public const PACKET_SCHEMA = 'sc-library-evidence-packet/1.0';

    public const NOTE_POST_TYPE = 'sc_evidence_note';
    public const CLAIM_POST_TYPE = 'sc_research_claim';
    public const EVIDENCE_TYPE_TAXONOMY = 'sc_evidence_type';
    public const CLAIM_TYPE_TAXONOMY = 'sc_claim_type';

    public const META_SOURCE_ID = '_sc_evidence_source_id';
    public const META_DOCUMENT_ID = '_sc_evidence_document_id';
    public const META_PROJECT_IDS = '_sc_evidence_project_ids';
    public const META_CLAIM_LINKS = '_sc_evidence_claim_links';
    public const META_LOCATOR_TYPE = '_sc_evidence_locator_type';
    public const META_LOCATOR_START = '_sc_evidence_locator_start';
    public const META_LOCATOR_END = '_sc_evidence_locator_end';
    public const META_LOCATOR_LABEL = '_sc_evidence_locator_label';
    public const META_CONTEXT_BEFORE = '_sc_evidence_context_before';
    public const META_CONTEXT_AFTER = '_sc_evidence_context_after';
    public const META_ANALYSIS = '_sc_evidence_analysis';
    public const META_TRANSCRIPTION_METHOD = '_sc_evidence_transcription_method';
    public const META_QUOTE_VERIFIED = '_sc_evidence_quote_verified';
    public const META_LOCATOR_VERIFIED = '_sc_evidence_locator_verified';
    public const META_VISIBILITY = '_sc_evidence_visibility';
    public const META_REVIEW_STATUS = '_sc_evidence_review_status';
    public const META_CONFIDENCE = '_sc_evidence_confidence';
    public const META_ATTACHMENT_ID = '_sc_evidence_attachment_id';
    public const META_CONTENT_HASH = '_sc_evidence_content_hash';
    public const META_LAST_REVIEWED = '_sc_evidence_last_reviewed';
    public const META_LAST_REVIEWED_BY = '_sc_evidence_last_reviewed_by';

    public const META_CLAIM_PROJECT_IDS = '_sc_claim_project_ids';
    public const META_CLAIM_EVIDENCE_IDS = '_sc_claim_evidence_ids';
    public const META_CLAIM_STATUS = '_sc_claim_status';
    public const META_CLAIM_CONFIDENCE = '_sc_claim_confidence';
    public const META_CLAIM_VISIBILITY = '_sc_claim_visibility';
    public const META_CLAIM_SCOPE = '_sc_claim_scope';
    public const META_CLAIM_ASSUMPTIONS = '_sc_claim_assumptions';
    public const META_CLAIM_LIMITATIONS = '_sc_claim_limitations';
    public const META_CLAIM_COUNTERCLAIM = '_sc_claim_counterclaim';
    public const META_CLAIM_REVIEW_NOTES = '_sc_claim_review_notes';
    public const META_CLAIM_LAST_REVIEWED = '_sc_claim_last_reviewed';
    public const META_CLAIM_LAST_REVIEWED_BY = '_sc_claim_last_reviewed_by';
    public const META_CLAIM_CONTENT_HASH = '_sc_claim_content_hash';

    public const OPTION_ROUTES_VERSION = 'sc_library_evidence_routes_version_v270';
    public const OPTION_MIGRATION_DONE = 'sc_library_evidence_migration_done_v270';
    public const MAX_LINKS = 100;
    public const MAX_PROJECTS = 100;

    private static $saving_note = false;
    private static $saving_claim = false;
    private static $deleted_note_claim_ids = array();
    private static $deleted_claim_note_ids = array();

    public function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ), 228 );
        add_action( 'init', array( $this, 'ensure_default_terms' ), 242 );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 999 );

        add_action( 'admin_menu', array( $this, 'register_workspace_page' ), 248 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 80 );
        add_action( 'save_post_' . self::NOTE_POST_TYPE, array( $this, 'save_evidence_note' ), 50, 3 );
        add_action( 'save_post_' . self::CLAIM_POST_TYPE, array( $this, 'save_claim' ), 50, 3 );
        add_action( 'before_delete_post', array( $this, 'before_delete_record' ), 70, 2 );
        add_action( 'deleted_post', array( $this, 'after_delete_record' ), 70, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 90 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 230 );

        add_filter( 'manage_' . self::NOTE_POST_TYPE . '_posts_columns', array( $this, 'note_columns' ) );
        add_action( 'manage_' . self::NOTE_POST_TYPE . '_posts_custom_column', array( $this, 'note_column_content' ), 10, 2 );
        add_filter( 'manage_' . self::CLAIM_POST_TYPE . '_posts_columns', array( $this, 'claim_columns' ) );
        add_action( 'manage_' . self::CLAIM_POST_TYPE . '_posts_custom_column', array( $this, 'claim_column_content' ), 10, 2 );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_shortcode( 'sc_evidence_note', array( $this, 'shortcode_evidence_note' ) );
        add_shortcode( 'sc_claim_evidence', array( $this, 'shortcode_claim_evidence' ) );
        add_shortcode( 'sc_project_evidence', array( $this, 'shortcode_project_evidence' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_content_types() {
        register_post_type(
            self::NOTE_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Evidence Notes', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Evidence Note', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Evidence Notes', 'sustainable-catalyst-library' ),
                    'add_new'            => __( 'Add Evidence Note', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Evidence Note', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Evidence Note', 'sustainable-catalyst-library' ),
                    'new_item'           => __( 'New Evidence Note', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Evidence Notes', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No evidence notes found.', 'sustainable-catalyst-library' ),
                    'not_found_in_trash' => __( 'No evidence notes found in Trash.', 'sustainable-catalyst-library' ),
                    'all_items'          => __( 'All Evidence Notes', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-format-quote',
                'can_export'          => true,
            )
        );

        register_post_type(
            self::CLAIM_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Research Claims', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Research Claim', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Research Claims', 'sustainable-catalyst-library' ),
                    'add_new'            => __( 'Add Research Claim', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Research Claim', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Research Claim', 'sustainable-catalyst-library' ),
                    'new_item'           => __( 'New Research Claim', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Research Claims', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No research claims found.', 'sustainable-catalyst-library' ),
                    'not_found_in_trash' => __( 'No research claims found in Trash.', 'sustainable-catalyst-library' ),
                    'all_items'          => __( 'All Research Claims', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-networking',
                'can_export'          => true,
            )
        );

        register_taxonomy(
            self::EVIDENCE_TYPE_TAXONOMY,
            self::NOTE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Evidence Types', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Evidence Type', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Evidence Types', 'sustainable-catalyst-library' ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_menu'      => 'sc-library',
                'show_in_rest'      => false,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );

        register_taxonomy(
            self::CLAIM_TYPE_TAXONOMY,
            self::CLAIM_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Claim Types', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Claim Type', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Claim Types', 'sustainable-catalyst-library' ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_menu'      => 'sc-library',
                'show_in_rest'      => false,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );
    }

    public function ensure_default_terms() {
        $evidence_types = array(
            'direct-quotation' => __( 'Direct quotation', 'sustainable-catalyst-library' ),
            'paraphrase'       => __( 'Paraphrase', 'sustainable-catalyst-library' ),
            'data-point'       => __( 'Data point', 'sustainable-catalyst-library' ),
            'definition'       => __( 'Definition', 'sustainable-catalyst-library' ),
            'method'           => __( 'Method or procedure', 'sustainable-catalyst-library' ),
            'observation'      => __( 'Observation', 'sustainable-catalyst-library' ),
            'counterevidence'  => __( 'Counterevidence', 'sustainable-catalyst-library' ),
            'context'          => __( 'Context', 'sustainable-catalyst-library' ),
        );
        foreach ( $evidence_types as $slug => $name ) {
            if ( ! term_exists( $slug, self::EVIDENCE_TYPE_TAXONOMY ) ) {
                wp_insert_term( $name, self::EVIDENCE_TYPE_TAXONOMY, array( 'slug' => $slug ) );
            }
        }

        $claim_types = array(
            'descriptive'    => __( 'Descriptive', 'sustainable-catalyst-library' ),
            'causal'         => __( 'Causal', 'sustainable-catalyst-library' ),
            'comparative'    => __( 'Comparative', 'sustainable-catalyst-library' ),
            'predictive'     => __( 'Predictive', 'sustainable-catalyst-library' ),
            'normative'      => __( 'Normative', 'sustainable-catalyst-library' ),
            'methodological' => __( 'Methodological', 'sustainable-catalyst-library' ),
            'legal'          => __( 'Legal or governance', 'sustainable-catalyst-library' ),
            'interpretive'   => __( 'Interpretive', 'sustainable-catalyst-library' ),
        );
        foreach ( $claim_types as $slug => $name ) {
            if ( ! term_exists( $slug, self::CLAIM_TYPE_TAXONOMY ) ) {
                wp_insert_term( $name, self::CLAIM_TYPE_TAXONOMY, array( 'slug' => $slug ) );
            }
        }
    }

    public function maybe_flush_rewrites() {
        if ( get_option( self::OPTION_ROUTES_VERSION ) === self::VERSION ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( self::OPTION_ROUTES_VERSION, self::VERSION, false );
    }

    public function register_workspace_page() {
        add_submenu_page(
            'sc-library',
            __( 'Evidence and Claims', 'sustainable-catalyst-library' ),
            __( 'Evidence and Claims', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-evidence-claims',
            array( $this, 'render_workspace_page' )
        );
    }

    public function render_workspace_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $note_counts = wp_count_posts( self::NOTE_POST_TYPE );
        $claim_counts = wp_count_posts( self::CLAIM_POST_TYPE );
        $open_claims = get_posts(
            array(
                'post_type'      => self::CLAIM_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 12,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $recent_notes = get_posts(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 12,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        ?>
        <div class="wrap sc-evidence-workspace">
            <p class="sc-evidence-kicker"><?php esc_html_e( 'Knowledge Library v2.7.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Quotations, Evidence Notes, and Claim Linking', 'sustainable-catalyst-library' ); ?></h1>
            <p class="sc-evidence-lede"><?php esc_html_e( 'Capture exact quotations, paraphrases, data points, definitions, methods, and counterevidence; preserve precise locators; connect evidence to research claims; and assemble inspectable project evidence packets.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-evidence-metrics">
                <article><strong><?php echo esc_html( number_format_i18n( absint( $note_counts->publish ?? 0 ) + absint( $note_counts->draft ?? 0 ) + absint( $note_counts->private ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Evidence notes', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( number_format_i18n( absint( $claim_counts->publish ?? 0 ) + absint( $claim_counts->draft ?? 0 ) + absint( $claim_counts->private ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Research claims', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( number_format_i18n( self::count_open_links() ) ); ?></strong><span><?php esc_html_e( 'Claim-evidence links', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( number_format_i18n( self::count_verified_notes() ) ); ?></strong><span><?php esc_html_e( 'Verified evidence notes', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <div class="sc-evidence-workspace__actions">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::NOTE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Evidence Note', 'sustainable-catalyst-library' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::CLAIM_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Research Claim', 'sustainable-catalyst-library' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::NOTE_POST_TYPE ) ); ?>"><?php esc_html_e( 'All Evidence Notes', 'sustainable-catalyst-library' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::CLAIM_POST_TYPE ) ); ?>"><?php esc_html_e( 'All Research Claims', 'sustainable-catalyst-library' ); ?></a>
            </div>

            <div class="sc-evidence-workspace__grid">
                <section>
                    <h2><?php esc_html_e( 'Recently updated evidence', 'sustainable-catalyst-library' ); ?></h2>
                    <?php if ( $recent_notes ) : ?>
                        <div class="sc-evidence-admin-list">
                            <?php foreach ( $recent_notes as $note ) : ?>
                                <?php $data = self::get_evidence_data( $note->ID, true ); ?>
                                <?php if ( ! $data ) { continue; } ?>
                                <article>
                                    <h3><a href="<?php echo esc_url( get_edit_post_link( $note->ID, 'raw' ) ); ?>"><?php echo esc_html( $note->post_title ); ?></a></h3>
                                    <p><?php echo esc_html( self::evidence_type_label( $note->ID ) . ' · ' . self::locator_display( $data ) ); ?></p>
                                    <small><?php echo esc_html( ucfirst( str_replace( '-', ' ', $data['review_status'] ) ) ); ?></small>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?><p><?php esc_html_e( 'No evidence notes yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </section>

                <section>
                    <h2><?php esc_html_e( 'Recently updated claims', 'sustainable-catalyst-library' ); ?></h2>
                    <?php if ( $open_claims ) : ?>
                        <div class="sc-evidence-admin-list">
                            <?php foreach ( $open_claims as $claim ) : ?>
                                <?php $data = self::get_claim_data( $claim->ID, true ); ?>
                                <?php if ( ! $data ) { continue; } ?>
                                <article>
                                    <h3><a href="<?php echo esc_url( get_edit_post_link( $claim->ID, 'raw' ) ); ?>"><?php echo esc_html( $claim->post_title ); ?></a></h3>
                                    <p><?php echo esc_html( wp_trim_words( $data['statement'], 24 ) ); ?></p>
                                    <small><?php echo esc_html( count( $data['evidence_ids'] ) . ' evidence link(s) · ' . ucfirst( $data['status'] ) ); ?></small>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?><p><?php esc_html_e( 'No research claims yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-evidence-source-location',
            __( 'Source, Document, and Locator', 'sustainable-catalyst-library' ),
            array( $this, 'render_note_source_box' ),
            self::NOTE_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-evidence-claims-projects',
            __( 'Claim and Project Links', 'sustainable-catalyst-library' ),
            array( $this, 'render_note_links_box' ),
            self::NOTE_POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-evidence-review',
            __( 'Evidence Review', 'sustainable-catalyst-library' ),
            array( $this, 'render_note_review_box' ),
            self::NOTE_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-evidence-citation-preview',
            __( 'Citation-Ready Evidence', 'sustainable-catalyst-library' ),
            array( $this, 'render_note_export_box' ),
            self::NOTE_POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'sc-claim-settings',
            __( 'Claim Review and Scope', 'sustainable-catalyst-library' ),
            array( $this, 'render_claim_settings_box' ),
            self::CLAIM_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-claim-evidence',
            __( 'Linked Evidence', 'sustainable-catalyst-library' ),
            array( $this, 'render_claim_evidence_box' ),
            self::CLAIM_POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-claim-export',
            __( 'Evidence Packet', 'sustainable-catalyst-library' ),
            array( $this, 'render_claim_export_box' ),
            self::CLAIM_POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'sc-source-evidence-summary',
            __( 'Quotations and Evidence', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_evidence_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'side',
            'default'
        );
        add_meta_box(
            'sc-project-evidence-summary',
            __( 'Claims and Evidence Packet', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_evidence_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_note_source_box( $post ) {
        wp_nonce_field( 'sc_library_save_evidence_note_' . $post->ID, 'sc_library_evidence_note_nonce' );
        $source_id = absint( get_post_meta( $post->ID, self::META_SOURCE_ID, true ) );
        $document_id = absint( get_post_meta( $post->ID, self::META_DOCUMENT_ID, true ) );
        ?>
        <div class="sc-evidence-fields">
            <p class="description"><?php esc_html_e( 'Use the main editor for the exact quotation, paraphrase, data point, definition, method, or observation. Use the excerpt field for a concise research note.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-evidence-field-grid">
                <?php $this->post_select( 'sc_evidence_source_id', __( 'Research Source', 'sustainable-catalyst-library' ), $source_id, SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, __( 'No Source record', 'sustainable-catalyst-library' ) ); ?>
                <?php $this->post_select( 'sc_evidence_document_id', __( 'Knowledge Library document', 'sustainable-catalyst-library' ), $document_id, SC_Library_Foundation_Pages::POST_TYPE, __( 'No document record', 'sustainable-catalyst-library' ) ); ?>
                <?php $this->select_field( 'sc_evidence_locator_type', __( 'Locator type', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LOCATOR_TYPE, true ) ?: 'page', self::locator_type_options() ); ?>
                <?php $this->text_field( 'sc_evidence_locator_start', __( 'Locator start', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LOCATOR_START, true ), '12' ); ?>
                <?php $this->text_field( 'sc_evidence_locator_end', __( 'Locator end', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LOCATOR_END, true ), '14' ); ?>
                <?php $this->text_field( 'sc_evidence_locator_label', __( 'Custom locator label', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LOCATOR_LABEL, true ), 'Figure 3, Table 2, §4.1' ); ?>
                <?php $this->select_field( 'sc_evidence_transcription_method', __( 'Capture method', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_TRANSCRIPTION_METHOD, true ) ?: 'manual', self::transcription_options() ); ?>
                <?php $this->attachment_field( $post->ID ); ?>
            </div>
            <div class="sc-evidence-context-grid">
                <label><strong><?php esc_html_e( 'Context before', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_evidence_context_before" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CONTEXT_BEFORE, true ) ); ?></textarea></label>
                <label><strong><?php esc_html_e( 'Context after', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_evidence_context_after" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CONTEXT_AFTER, true ) ); ?></textarea></label>
            </div>
            <label><strong><?php esc_html_e( 'Interpretation and analytical note', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_evidence_analysis" rows="6"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_ANALYSIS, true ) ); ?></textarea></label>
        </div>
        <?php
    }

    public function render_note_links_box( $post ) {
        $links = self::claim_links( $post->ID );
        $claims = get_posts(
            array(
                'post_type'      => self::CLAIM_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 300,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $projects = self::project_posts();
        $selected_projects = self::id_list( get_post_meta( $post->ID, self::META_PROJECT_IDS, true ) );
        ?>
        <div class="sc-evidence-link-editor" data-sc-evidence-link-editor>
            <p><?php esc_html_e( 'Connect this evidence to one or more claims. The relationship describes what the evidence does, not whether the source itself is generally trustworthy.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-evidence-link-rows" data-sc-evidence-link-rows>
                <?php
                $rows = $links ?: array(
                    array(
                        'claim_id' => 0,
                        'relation' => 'supports',
                        'strength' => 3,
                        'note'     => '',
                    )
                );
                foreach ( $rows as $index => $link ) :
                    ?>
                    <div class="sc-evidence-link-row" data-sc-evidence-link-row>
                        <label>
                            <span><?php esc_html_e( 'Research claim', 'sustainable-catalyst-library' ); ?></span>
                            <select name="sc_evidence_claim_links[<?php echo esc_attr( $index ); ?>][claim_id]">
                                <option value="0"><?php esc_html_e( 'Select a claim', 'sustainable-catalyst-library' ); ?></option>
                                <?php foreach ( $claims as $claim ) : ?>
                                    <option value="<?php echo esc_attr( $claim->ID ); ?>" <?php selected( absint( $link['claim_id'] ?? 0 ), $claim->ID ); ?>><?php echo esc_html( $claim->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span>
                            <select name="sc_evidence_claim_links[<?php echo esc_attr( $index ); ?>][relation]">
                                <?php foreach ( self::relation_options() as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $link['relation'] ?? 'supports', $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Strength', 'sustainable-catalyst-library' ); ?></span>
                            <select name="sc_evidence_claim_links[<?php echo esc_attr( $index ); ?>][strength]">
                                <?php for ( $strength = 1; $strength <= 5; $strength++ ) : ?>
                                    <option value="<?php echo esc_attr( $strength ); ?>" <?php selected( absint( $link['strength'] ?? 3 ), $strength ); ?>><?php echo esc_html( $strength ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="sc-evidence-link-row__note">
                            <span><?php esc_html_e( 'Link note', 'sustainable-catalyst-library' ); ?></span>
                            <input type="text" name="sc_evidence_claim_links[<?php echo esc_attr( $index ); ?>][note]" value="<?php echo esc_attr( $link['note'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Why this evidence supports, contradicts, qualifies, or contextualizes the claim', 'sustainable-catalyst-library' ); ?>">
                        </label>
                        <button type="button" class="button" data-sc-remove-evidence-link><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <p><button type="button" class="button" data-sc-add-evidence-link><?php esc_html_e( 'Add Claim Link', 'sustainable-catalyst-library' ); ?></button></p>

            <template data-sc-evidence-link-template>
                <div class="sc-evidence-link-row" data-sc-evidence-link-row>
                    <label>
                        <span><?php esc_html_e( 'Research claim', 'sustainable-catalyst-library' ); ?></span>
                        <select data-name="claim_id">
                            <option value="0"><?php esc_html_e( 'Select a claim', 'sustainable-catalyst-library' ); ?></option>
                            <?php foreach ( $claims as $claim ) : ?><option value="<?php echo esc_attr( $claim->ID ); ?>"><?php echo esc_html( $claim->post_title ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span>
                        <select data-name="relation"><?php foreach ( self::relation_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Strength', 'sustainable-catalyst-library' ); ?></span>
                        <select data-name="strength"><?php for ( $strength = 1; $strength <= 5; $strength++ ) : ?><option value="<?php echo esc_attr( $strength ); ?>" <?php selected( 3, $strength ); ?>><?php echo esc_html( $strength ); ?></option><?php endfor; ?></select>
                    </label>
                    <label class="sc-evidence-link-row__note">
                        <span><?php esc_html_e( 'Link note', 'sustainable-catalyst-library' ); ?></span>
                        <input type="text" data-name="note">
                    </label>
                    <button type="button" class="button" data-sc-remove-evidence-link><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                </div>
            </template>

            <h3><?php esc_html_e( 'Research projects', 'sustainable-catalyst-library' ); ?></h3>
            <div class="sc-evidence-project-picker">
                <?php if ( $projects ) : ?>
                    <?php foreach ( $projects as $project ) : ?>
                        <label><input type="checkbox" name="sc_evidence_project_ids[]" value="<?php echo esc_attr( $project->ID ); ?>" <?php checked( in_array( $project->ID, $selected_projects, true ) ); ?>> <span><?php echo esc_html( $project->post_title ); ?></span></label>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p><?php esc_html_e( 'No Research Projects are available.', 'sustainable-catalyst-library' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_note_review_box( $post ) {
        $review_status = get_post_meta( $post->ID, self::META_REVIEW_STATUS, true ) ?: 'draft';
        $visibility = get_post_meta( $post->ID, self::META_VISIBILITY, true ) ?: 'private';
        $confidence = absint( get_post_meta( $post->ID, self::META_CONFIDENCE, true ) ?: 3 );
        ?>
        <div class="sc-evidence-review-box">
            <?php $this->select_field( 'sc_evidence_review_status', __( 'Review status', 'sustainable-catalyst-library' ), $review_status, self::evidence_review_options() ); ?>
            <?php $this->select_field( 'sc_evidence_visibility', __( 'Visibility', 'sustainable-catalyst-library' ), $visibility, self::visibility_options() ); ?>
            <label><strong><?php esc_html_e( 'Evidence confidence', 'sustainable-catalyst-library' ); ?></strong><select name="sc_evidence_confidence"><?php for ( $value = 1; $value <= 5; $value++ ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $confidence, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endfor; ?></select></label>
            <p><label><input type="checkbox" name="sc_evidence_quote_verified" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_QUOTE_VERIFIED, true ) ); ?>> <?php esc_html_e( 'Exact wording or transcription checked', 'sustainable-catalyst-library' ); ?></label></p>
            <p><label><input type="checkbox" name="sc_evidence_locator_verified" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_LOCATOR_VERIFIED, true ) ); ?>> <?php esc_html_e( 'Locator checked against source material', 'sustainable-catalyst-library' ); ?></label></p>
            <?php if ( '1' === get_post_meta( $post->ID, self::META_QUOTE_VERIFIED, true ) || '1' === get_post_meta( $post->ID, self::META_LOCATOR_VERIFIED, true ) ) : ?>
                <p><label><input type="checkbox" name="sc_evidence_reverified" value="1"> <?php esc_html_e( 'I rechecked the quotation and locator after editing citation-critical fields.', 'sustainable-catalyst-library' ); ?></label></p>
            <?php endif; ?>
            <?php if ( get_post_meta( $post->ID, self::META_LAST_REVIEWED, true ) ) : ?>
                <p class="description"><?php echo esc_html( sprintf( __( 'Last reviewed: %s', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LAST_REVIEWED, true ) ) ); ?></p>
            <?php endif; ?>
            <p class="description"><?php esc_html_e( 'Public visibility is still restricted to published notes with a public, published Source or document context.', 'sustainable-catalyst-library' ); ?></p>
        </div>
        <?php
    }

    public function render_note_export_box( $post ) {
        $data = self::get_evidence_data( $post->ID, true );
        $quote = $data ? self::citation_ready_quote( $data ) : '';
        $markdown = $data ? self::evidence_markdown( $data ) : '';
        ?>
        <div class="sc-evidence-export-box">
            <p><strong><?php esc_html_e( 'Quotation with in-text citation', 'sustainable-catalyst-library' ); ?></strong></p>
            <blockquote data-sc-copy-value="<?php echo esc_attr( $quote ); ?>"><?php echo esc_html( $quote ?: __( 'Save the evidence note to generate an export.', 'sustainable-catalyst-library' ) ); ?></blockquote>
            <?php if ( $quote ) : ?><button type="button" class="button" data-sc-copy-evidence-value><?php esc_html_e( 'Copy Quotation', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>
            <p><strong><?php esc_html_e( 'Markdown evidence note', 'sustainable-catalyst-library' ); ?></strong></p>
            <textarea readonly rows="8" data-sc-copy-value><?php echo esc_textarea( $markdown ); ?></textarea>
            <?php if ( $markdown ) : ?><button type="button" class="button" data-sc-copy-evidence-value><?php esc_html_e( 'Copy Markdown', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>
            <?php if ( $post->ID ) : ?><p><code>[sc_evidence_note id="<?php echo esc_attr( $post->ID ); ?>"]</code></p><?php endif; ?>
        </div>
        <?php
    }

    public function render_claim_settings_box( $post ) {
        wp_nonce_field( 'sc_library_save_research_claim_' . $post->ID, 'sc_library_research_claim_nonce' );
        $projects = self::project_posts();
        $selected_projects = self::id_list( get_post_meta( $post->ID, self::META_CLAIM_PROJECT_IDS, true ) );
        ?>
        <div class="sc-claim-fields">
            <p class="description"><?php esc_html_e( 'Use the main editor for the full claim statement. The title should be a short, stable label for the claim.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-evidence-field-grid">
                <?php $this->select_field( 'sc_claim_status', __( 'Claim status', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_CLAIM_STATUS, true ) ?: 'draft', self::claim_status_options() ); ?>
                <?php $this->select_field( 'sc_claim_visibility', __( 'Visibility', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_CLAIM_VISIBILITY, true ) ?: 'private', self::visibility_options() ); ?>
                <label><strong><?php esc_html_e( 'Confidence', 'sustainable-catalyst-library' ); ?></strong><input type="number" name="sc_claim_confidence" min="0" max="100" value="<?php echo esc_attr( absint( get_post_meta( $post->ID, self::META_CLAIM_CONFIDENCE, true ) ) ); ?>"></label>
            </div>
            <?php if ( 'verified' === get_post_meta( $post->ID, self::META_CLAIM_STATUS, true ) ) : ?>
                <p><label><input type="checkbox" name="sc_claim_reverified" value="1"> <?php esc_html_e( 'I rechecked the claim after changing its statement or review fields.', 'sustainable-catalyst-library' ); ?></label></p>
            <?php endif; ?>
            <label><strong><?php esc_html_e( 'Scope and boundaries', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_claim_scope" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CLAIM_SCOPE, true ) ); ?></textarea></label>
            <div class="sc-evidence-context-grid">
                <label><strong><?php esc_html_e( 'Assumptions', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_claim_assumptions" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CLAIM_ASSUMPTIONS, true ) ); ?></textarea></label>
                <label><strong><?php esc_html_e( 'Limitations and uncertainty', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_claim_limitations" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CLAIM_LIMITATIONS, true ) ); ?></textarea></label>
            </div>
            <label><strong><?php esc_html_e( 'Counterclaim or alternative explanation', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_claim_counterclaim" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CLAIM_COUNTERCLAIM, true ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Private review notes', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_claim_review_notes" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CLAIM_REVIEW_NOTES, true ) ); ?></textarea></label>

            <h3><?php esc_html_e( 'Research projects', 'sustainable-catalyst-library' ); ?></h3>
            <div class="sc-evidence-project-picker">
                <?php foreach ( $projects as $project ) : ?>
                    <label><input type="checkbox" name="sc_claim_project_ids[]" value="<?php echo esc_attr( $project->ID ); ?>" <?php checked( in_array( $project->ID, $selected_projects, true ) ); ?>> <span><?php echo esc_html( $project->post_title ); ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_claim_evidence_box( $post ) {
        $packet = self::claim_packet( $post->ID, true );
        ?>
        <div class="sc-claim-evidence-list">
            <p><?php esc_html_e( 'Evidence links are edited from each Evidence Note so the relationship and rationale remain attached to the evidence record.', 'sustainable-catalyst-library' ); ?></p>
            <?php if ( $packet['links'] ) : ?>
                <?php foreach ( $packet['links'] as $item ) : ?>
                    <article class="relation-<?php echo esc_attr( $item['link']['relation'] ); ?>">
                        <header>
                            <h3><a href="<?php echo esc_url( get_edit_post_link( $item['evidence']['id'], 'raw' ) ); ?>"><?php echo esc_html( $item['evidence']['title'] ); ?></a></h3>
                            <strong><?php echo esc_html( self::relation_label( $item['link']['relation'] ) ); ?></strong>
                        </header>
                        <blockquote><?php echo esc_html( wp_trim_words( $item['evidence']['content'], 55 ) ); ?></blockquote>
                        <p><?php echo esc_html( $item['evidence']['source_citation'] ); ?></p>
                        <small><?php echo esc_html( sprintf( __( 'Strength %1$d/5 · %2$s', 'sustainable-catalyst-library' ), $item['link']['strength'], self::locator_display( $item['evidence'] ) ) ); ?></small>
                        <?php if ( $item['link']['note'] ) : ?><p><?php echo esc_html( $item['link']['note'] ); ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php esc_html_e( 'No evidence notes are linked to this claim.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_claim_export_box( $post ) {
        $markdown = self::claim_packet_markdown( $post->ID, true );
        ?>
        <div class="sc-evidence-export-box">
            <textarea readonly rows="12" data-sc-copy-value><?php echo esc_textarea( $markdown ); ?></textarea>
            <?php if ( $markdown ) : ?><button type="button" class="button" data-sc-copy-evidence-value><?php esc_html_e( 'Copy Evidence Packet', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>
            <?php if ( $post->ID ) : ?><p><code>[sc_claim_evidence id="<?php echo esc_attr( $post->ID ); ?>"]</code></p><?php endif; ?>
        </div>
        <?php
    }

    public function render_source_evidence_box( $post ) {
        $note_ids = self::evidence_ids_for_source( $post->ID, true );
        $claim_ids = array();
        foreach ( $note_ids as $note_id ) {
            foreach ( self::claim_links( $note_id ) as $link ) {
                $claim_ids[] = absint( $link['claim_id'] );
            }
        }
        $claim_ids = array_values( array_unique( array_filter( $claim_ids ) ) );
        $add_url = add_query_arg(
            array(
                'post_type'    => self::NOTE_POST_TYPE,
                'sc_source_id' => $post->ID,
            ),
            admin_url( 'post-new.php' )
        );
        ?>
        <div class="sc-evidence-summary-box">
            <p><strong><?php echo esc_html( number_format_i18n( count( $note_ids ) ) ); ?></strong> <?php esc_html_e( 'evidence notes', 'sustainable-catalyst-library' ); ?></p>
            <p><strong><?php echo esc_html( number_format_i18n( count( $claim_ids ) ) ); ?></strong> <?php esc_html_e( 'linked claims', 'sustainable-catalyst-library' ); ?></p>
            <p><a class="button" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Evidence from Source', 'sustainable-catalyst-library' ); ?></a></p>
            <?php if ( $note_ids ) : ?>
                <ul>
                    <?php foreach ( array_slice( $note_ids, 0, 8 ) as $note_id ) : ?>
                        <li><a href="<?php echo esc_url( get_edit_post_link( $note_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $note_id ) ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_project_evidence_box( $post ) {
        $note_ids = self::evidence_ids_for_project( $post->ID, true );
        $claim_ids = self::claim_ids_for_project( $post->ID, true );
        ?>
        <div class="sc-evidence-summary-box">
            <p><strong><?php echo esc_html( number_format_i18n( count( $claim_ids ) ) ); ?></strong> <?php esc_html_e( 'project claims', 'sustainable-catalyst-library' ); ?></p>
            <p><strong><?php echo esc_html( number_format_i18n( count( $note_ids ) ) ); ?></strong> <?php esc_html_e( 'project evidence notes', 'sustainable-catalyst-library' ); ?></p>
            <p><code>[sc_project_evidence project="<?php echo esc_attr( $post->post_name ?: $post->ID ); ?>"]</code></p>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-evidence-claims' ) ); ?>"><?php esc_html_e( 'Open Evidence Workspace', 'sustainable-catalyst-library' ); ?></a></p>
        </div>
        <?php
    }

    public function save_evidence_note( $post_id, $post, $update ) {
        if ( self::$saving_note || ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_evidence_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_evidence_note_nonce'] ) ), 'sc_library_save_evidence_note_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saving_note = true;
        $old_links = self::claim_links( $post_id );

        $source_id = absint( wp_unslash( $_POST['sc_evidence_source_id'] ?? 0 ) );
        if ( $source_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            $source_id = 0;
        }
        if ( ! $source_id && isset( $_GET['sc_source_id'] ) ) {
            $candidate = absint( wp_unslash( $_GET['sc_source_id'] ) );
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $candidate ) ) {
                $source_id = $candidate;
            }
        }
        self::update_or_delete_meta( $post_id, self::META_SOURCE_ID, $source_id );

        $document_id = absint( wp_unslash( $_POST['sc_evidence_document_id'] ?? 0 ) );
        if ( $document_id && SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
            $document_id = 0;
        }
        self::update_or_delete_meta( $post_id, self::META_DOCUMENT_ID, $document_id );

        self::update_or_delete_meta( $post_id, self::META_LOCATOR_TYPE, self::allowed_value( wp_unslash( $_POST['sc_evidence_locator_type'] ?? 'page' ), self::locator_type_options(), 'page' ) );
        self::update_or_delete_meta( $post_id, self::META_LOCATOR_START, sanitize_text_field( wp_unslash( $_POST['sc_evidence_locator_start'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_LOCATOR_END, sanitize_text_field( wp_unslash( $_POST['sc_evidence_locator_end'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_LOCATOR_LABEL, sanitize_text_field( wp_unslash( $_POST['sc_evidence_locator_label'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CONTEXT_BEFORE, sanitize_textarea_field( wp_unslash( $_POST['sc_evidence_context_before'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CONTEXT_AFTER, sanitize_textarea_field( wp_unslash( $_POST['sc_evidence_context_after'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_ANALYSIS, sanitize_textarea_field( wp_unslash( $_POST['sc_evidence_analysis'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_TRANSCRIPTION_METHOD, self::allowed_value( wp_unslash( $_POST['sc_evidence_transcription_method'] ?? 'manual' ), self::transcription_options(), 'manual' ) );
        self::update_or_delete_meta( $post_id, self::META_VISIBILITY, self::allowed_value( wp_unslash( $_POST['sc_evidence_visibility'] ?? 'private' ), self::visibility_options(), 'private' ) );
        self::update_or_delete_meta( $post_id, self::META_REVIEW_STATUS, self::allowed_value( wp_unslash( $_POST['sc_evidence_review_status'] ?? 'draft' ), self::evidence_review_options(), 'draft' ) );
        update_post_meta( $post_id, self::META_CONFIDENCE, max( 1, min( 5, absint( wp_unslash( $_POST['sc_evidence_confidence'] ?? 3 ) ) ) ) );

        $attachment_id = absint( wp_unslash( $_POST['sc_evidence_attachment_id'] ?? 0 ) );
        if ( $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
            $attachment_id = 0;
        }
        self::update_or_delete_meta( $post_id, self::META_ATTACHMENT_ID, $attachment_id );

        $projects = self::validated_ids(
            wp_unslash( $_POST['sc_evidence_project_ids'] ?? array() ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            self::MAX_PROJECTS
        );
        self::update_id_meta( $post_id, self::META_PROJECT_IDS, $projects );

        $links = self::sanitize_claim_links( wp_unslash( $_POST['sc_evidence_claim_links'] ?? array() ), $old_links );
        if ( $links ) {
            update_post_meta( $post_id, self::META_CLAIM_LINKS, $links );
        } else {
            delete_post_meta( $post_id, self::META_CLAIM_LINKS );
        }
        self::sync_claim_reverse_links( $post_id, $old_links, $links );

        $old_hash = (string) get_post_meta( $post_id, self::META_CONTENT_HASH, true );
        $new_hash = self::evidence_content_hash( $post_id );
        $quote_verified = isset( $_POST['sc_evidence_quote_verified'] ) ? '1' : '0';
        $locator_verified = isset( $_POST['sc_evidence_locator_verified'] ) ? '1' : '0';
        if ( $old_hash && $new_hash !== $old_hash && ! isset( $_POST['sc_evidence_reverified'] ) ) {
            $quote_verified = '0';
            $locator_verified = '0';
            if ( 'verified' === get_post_meta( $post_id, self::META_REVIEW_STATUS, true ) ) {
                update_post_meta( $post_id, self::META_REVIEW_STATUS, 'review' );
            }
        }
        update_post_meta( $post_id, self::META_CONTENT_HASH, $new_hash );
        update_post_meta( $post_id, self::META_QUOTE_VERIFIED, $quote_verified );
        update_post_meta( $post_id, self::META_LOCATOR_VERIFIED, $locator_verified );

        $review_status = get_post_meta( $post_id, self::META_REVIEW_STATUS, true );
        if ( '1' === $quote_verified || '1' === $locator_verified || in_array( $review_status, array( 'reviewed', 'verified' ), true ) ) {
            update_post_meta( $post_id, self::META_LAST_REVIEWED, current_time( 'mysql' ) );
            update_post_meta( $post_id, self::META_LAST_REVIEWED_BY, get_current_user_id() );
        }

        self::$saving_note = false;
    }

    public function save_claim( $post_id, $post, $update ) {
        if ( self::$saving_claim || ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_research_claim_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_research_claim_nonce'] ) ), 'sc_library_save_research_claim_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saving_claim = true;
        $previous_status = (string) get_post_meta( $post_id, self::META_CLAIM_STATUS, true );
        $old_hash = (string) get_post_meta( $post_id, self::META_CLAIM_CONTENT_HASH, true );
        $status = self::allowed_value( wp_unslash( $_POST['sc_claim_status'] ?? 'draft' ), self::claim_status_options(), 'draft' );

        self::update_or_delete_meta( $post_id, self::META_CLAIM_VISIBILITY, self::allowed_value( wp_unslash( $_POST['sc_claim_visibility'] ?? 'private' ), self::visibility_options(), 'private' ) );
        update_post_meta( $post_id, self::META_CLAIM_CONFIDENCE, max( 0, min( 100, absint( wp_unslash( $_POST['sc_claim_confidence'] ?? 0 ) ) ) ) );
        self::update_or_delete_meta( $post_id, self::META_CLAIM_SCOPE, sanitize_textarea_field( wp_unslash( $_POST['sc_claim_scope'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CLAIM_ASSUMPTIONS, sanitize_textarea_field( wp_unslash( $_POST['sc_claim_assumptions'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CLAIM_LIMITATIONS, sanitize_textarea_field( wp_unslash( $_POST['sc_claim_limitations'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CLAIM_COUNTERCLAIM, sanitize_textarea_field( wp_unslash( $_POST['sc_claim_counterclaim'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_CLAIM_REVIEW_NOTES, sanitize_textarea_field( wp_unslash( $_POST['sc_claim_review_notes'] ?? '' ) ) );

        $projects = self::validated_ids(
            wp_unslash( $_POST['sc_claim_project_ids'] ?? array() ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            self::MAX_PROJECTS
        );
        self::update_id_meta( $post_id, self::META_CLAIM_PROJECT_IDS, $projects );

        $new_hash = self::claim_content_hash( $post_id );
        if ( $old_hash && $old_hash !== $new_hash && 'verified' === $previous_status && 'verified' === $status && ! isset( $_POST['sc_claim_reverified'] ) ) {
            $status = 'review';
        }
        self::update_or_delete_meta( $post_id, self::META_CLAIM_STATUS, $status );
        update_post_meta( $post_id, self::META_CLAIM_CONTENT_HASH, $new_hash );

        if ( in_array( $status, array( 'reviewed', 'verified', 'disputed' ), true ) ) {
            update_post_meta( $post_id, self::META_CLAIM_LAST_REVIEWED, current_time( 'mysql' ) );
            update_post_meta( $post_id, self::META_CLAIM_LAST_REVIEWED_BY, get_current_user_id() );
        }

        self::rebuild_claim_evidence_index( $post_id );
        self::$saving_claim = false;
    }

    public function before_delete_record( $post_id, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( self::NOTE_POST_TYPE === $post->post_type ) {
            self::$deleted_note_claim_ids = self::claim_ids_from_links( self::claim_links( $post_id ) );
            return;
        }
        if ( self::CLAIM_POST_TYPE === $post->post_type ) {
            self::$deleted_claim_note_ids = self::id_list( get_post_meta( $post_id, self::META_CLAIM_EVIDENCE_IDS, true ) );
            return;
        }
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === $post->post_type ) {
            self::clear_deleted_relationship( self::NOTE_POST_TYPE, self::META_SOURCE_ID, $post_id, false );
        } elseif ( SC_Library_Foundation_Pages::POST_TYPE === $post->post_type ) {
            self::clear_deleted_relationship( self::NOTE_POST_TYPE, self::META_DOCUMENT_ID, $post_id, false );
        } elseif ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === $post->post_type ) {
            self::remove_project_relationships( $post_id );
        }
    }

    public function after_delete_record( $post_id, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( self::NOTE_POST_TYPE === $post->post_type ) {
            foreach ( self::$deleted_note_claim_ids as $claim_id ) {
                $ids = self::id_list( get_post_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, true ) );
                self::update_id_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, array_values( array_diff( $ids, array( $post_id ) ) ) );
            }
            self::$deleted_note_claim_ids = array();
        } elseif ( self::CLAIM_POST_TYPE === $post->post_type ) {
            foreach ( self::$deleted_claim_note_ids as $note_id ) {
                $links = array_values(
                    array_filter(
                        self::claim_links( $note_id ),
                        static function ( $link ) use ( $post_id ) {
                            return absint( $link['claim_id'] ?? 0 ) !== absint( $post_id );
                        }
                    )
                );
                $links ? update_post_meta( $note_id, self::META_CLAIM_LINKS, $links ) : delete_post_meta( $note_id, self::META_CLAIM_LINKS );
            }
            self::$deleted_claim_note_ids = array();
        }
    }

    private static function sanitize_claim_links( $raw_links, $old_links = array() ) {
        $old_by_claim = array();
        foreach ( (array) $old_links as $old ) {
            $claim_id = absint( $old['claim_id'] ?? 0 );
            if ( $claim_id ) {
                $old_by_claim[ $claim_id ] = $old;
            }
        }

        $links = array();
        $seen = array();
        foreach ( (array) $raw_links as $raw ) {
            if ( ! is_array( $raw ) ) {
                continue;
            }
            $claim_id = absint( $raw['claim_id'] ?? 0 );
            if ( ! $claim_id || isset( $seen[ $claim_id ] ) || self::CLAIM_POST_TYPE !== get_post_type( $claim_id ) ) {
                continue;
            }
            $seen[ $claim_id ] = true;
            $previous = $old_by_claim[ $claim_id ] ?? array();
            $relation = self::allowed_value( $raw['relation'] ?? 'supports', self::relation_options(), 'supports' );
            $links[] = array(
                'schema'     => self::LINK_SCHEMA,
                'claim_id'   => $claim_id,
                'relation'   => $relation,
                'strength'   => max( 1, min( 5, absint( $raw['strength'] ?? 3 ) ) ),
                'note'       => sanitize_text_field( $raw['note'] ?? '' ),
                'created_at' => sanitize_text_field( $previous['created_at'] ?? current_time( 'mysql' ) ),
                'created_by' => absint( $previous['created_by'] ?? get_current_user_id() ),
                'updated_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id(),
            );
            if ( count( $links ) >= self::MAX_LINKS ) {
                break;
            }
        }
        return $links;
    }

    private static function sync_claim_reverse_links( $note_id, $old_links, $new_links ) {
        $old_ids = self::claim_ids_from_links( $old_links );
        $new_ids = self::claim_ids_from_links( $new_links );
        $affected = array_values( array_unique( array_merge( $old_ids, $new_ids ) ) );

        foreach ( $affected as $claim_id ) {
            $evidence_ids = self::id_list( get_post_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, true ) );
            if ( in_array( $claim_id, $new_ids, true ) ) {
                $evidence_ids[] = absint( $note_id );
            } else {
                $evidence_ids = array_values( array_diff( $evidence_ids, array( absint( $note_id ) ) ) );
            }
            self::update_id_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, array_values( array_unique( $evidence_ids ) ) );
        }
    }

    public static function rebuild_claim_evidence_index( $claim_id ) {
        if ( self::CLAIM_POST_TYPE !== get_post_type( $claim_id ) ) {
            return array();
        }
        $note_ids = get_posts(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_CLAIM_LINKS,
            )
        );
        $matched = array();
        foreach ( $note_ids as $note_id ) {
            foreach ( self::claim_links( $note_id ) as $link ) {
                if ( absint( $link['claim_id'] ) === absint( $claim_id ) ) {
                    $matched[] = absint( $note_id );
                    break;
                }
            }
        }
        $matched = array_values( array_unique( $matched ) );
        self::update_id_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, $matched );
        return $matched;
    }

    private static function claim_ids_from_links( $links ) {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ( $link ) {
                            return absint( $link['claim_id'] ?? 0 );
                        },
                        (array) $links
                    )
                )
            )
        );
    }

    public static function claim_links( $note_id ) {
        $links = get_post_meta( $note_id, self::META_CLAIM_LINKS, true );
        if ( ! is_array( $links ) ) {
            return array();
        }
        $clean = array();
        foreach ( $links as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }
            $claim_id = absint( $link['claim_id'] ?? 0 );
            if ( ! $claim_id || self::CLAIM_POST_TYPE !== get_post_type( $claim_id ) ) {
                continue;
            }
            $clean[] = array(
                'schema'     => self::LINK_SCHEMA,
                'claim_id'   => $claim_id,
                'relation'   => self::allowed_value( $link['relation'] ?? 'supports', self::relation_options(), 'supports' ),
                'strength'   => max( 1, min( 5, absint( $link['strength'] ?? 3 ) ) ),
                'note'       => sanitize_text_field( $link['note'] ?? '' ),
                'created_at' => sanitize_text_field( $link['created_at'] ?? '' ),
                'created_by' => absint( $link['created_by'] ?? 0 ),
                'updated_at' => sanitize_text_field( $link['updated_at'] ?? '' ),
                'updated_by' => absint( $link['updated_by'] ?? 0 ),
            );
        }
        return $clean;
    }

    private static function clear_deleted_relationship( $post_type, $meta_key, $deleted_id, $is_array ) {
        $ids = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => $meta_key,
            )
        );
        foreach ( $ids as $id ) {
            if ( $is_array ) {
                $values = self::id_list( get_post_meta( $id, $meta_key, true ) );
                self::update_id_meta( $id, $meta_key, array_values( array_diff( $values, array( absint( $deleted_id ) ) ) ) );
            } elseif ( absint( get_post_meta( $id, $meta_key, true ) ) === absint( $deleted_id ) ) {
                delete_post_meta( $id, $meta_key );
            }
        }
    }

    private static function remove_project_relationships( $project_id ) {
        foreach ( array(
            array( self::NOTE_POST_TYPE, self::META_PROJECT_IDS ),
            array( self::CLAIM_POST_TYPE, self::META_CLAIM_PROJECT_IDS ),
        ) as $relationship ) {
            $ids = get_posts(
                array(
                    'post_type'      => $relationship[0],
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_key'       => $relationship[1],
                )
            );
            foreach ( $ids as $id ) {
                $projects = self::id_list( get_post_meta( $id, $relationship[1], true ) );
                self::update_id_meta( $id, $relationship[1], array_values( array_diff( $projects, array( absint( $project_id ) ) ) ) );
            }
        }
    }

    private static function validated_ids( $raw, $post_type, $limit = 100 ) {
        $valid = array();
        foreach ( self::id_list( $raw ) as $id ) {
            if ( $post_type === get_post_type( $id ) ) {
                $valid[] = $id;
            }
            if ( count( $valid ) >= $limit ) {
                break;
            }
        }
        return array_values( array_unique( $valid ) );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function update_id_meta( $post_id, $meta_key, $ids ) {
        $ids = self::id_list( $ids );
        if ( $ids ) {
            update_post_meta( $post_id, $meta_key, $ids );
        } else {
            delete_post_meta( $post_id, $meta_key );
        }
    }

    private static function update_or_delete_meta( $post_id, $meta_key, $value ) {
        if ( '' === (string) $value || 0 === $value || array() === $value ) {
            delete_post_meta( $post_id, $meta_key );
        } else {
            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function project_posts() {
        return get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 300,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
    }

    private function text_field( $name, $label, $value, $placeholder = '' ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong><input id="' . esc_attr( $name ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"></label>';
    }

    private function select_field( $name, $label, $value, $options ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong><select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
        foreach ( $options as $option_value => $option_label ) {
            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select></label>';
    }

    private function post_select( $name, $label, $selected_id, $post_type, $empty_label ) {
        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        if ( ! $selected_id ) {
            $query_key = 'sc_source_id';
            if ( self::NOTE_POST_TYPE === get_post_type() && isset( $_GET[ $query_key ] ) ) {
                $candidate = absint( wp_unslash( $_GET[ $query_key ] ) );
                if ( $post_type === get_post_type( $candidate ) ) {
                    $selected_id = $candidate;
                }
            }
        }
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong><select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
        echo '<option value="0">' . esc_html( $empty_label ) . '</option>';
        foreach ( $posts as $item ) {
            echo '<option value="' . esc_attr( $item->ID ) . '" ' . selected( absint( $selected_id ), $item->ID, false ) . '>' . esc_html( $item->post_title ) . '</option>';
        }
        echo '</select></label>';
    }

    private function attachment_field( $note_id ) {
        $attachment_id = absint( get_post_meta( $note_id, self::META_ATTACHMENT_ID, true ) );
        $attachment = $attachment_id ? get_post( $attachment_id ) : null;
        ?>
        <label class="sc-evidence-attachment-field">
            <strong><?php esc_html_e( 'Supporting attachment', 'sustainable-catalyst-library' ); ?></strong>
            <input type="hidden" name="sc_evidence_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" data-sc-evidence-attachment-id>
            <span data-sc-evidence-attachment-status><?php echo esc_html( $attachment ? $attachment->post_title : __( 'No attachment selected', 'sustainable-catalyst-library' ) ); ?></span>
            <span><button type="button" class="button" data-sc-select-evidence-attachment><?php esc_html_e( 'Select', 'sustainable-catalyst-library' ); ?></button> <button type="button" class="button" data-sc-remove-evidence-attachment <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button></span>
        </label>
        <?php
    }

    public static function relation_options() {
        return array(
            'supports'       => __( 'Supports', 'sustainable-catalyst-library' ),
            'contradicts'    => __( 'Contradicts', 'sustainable-catalyst-library' ),
            'qualifies'      => __( 'Qualifies', 'sustainable-catalyst-library' ),
            'contextualizes' => __( 'Contextualizes', 'sustainable-catalyst-library' ),
            'illustrates'    => __( 'Illustrates', 'sustainable-catalyst-library' ),
            'unresolved'     => __( 'Unresolved', 'sustainable-catalyst-library' ),
        );
    }

    private static function relation_label( $relation ) {
        $options = self::relation_options();
        return $options[ $relation ] ?? ucfirst( str_replace( '-', ' ', $relation ) );
    }

    private static function locator_type_options() {
        return array(
            'page'      => __( 'Page', 'sustainable-catalyst-library' ),
            'pages'     => __( 'Page range', 'sustainable-catalyst-library' ),
            'paragraph' => __( 'Paragraph', 'sustainable-catalyst-library' ),
            'section'   => __( 'Section', 'sustainable-catalyst-library' ),
            'chapter'   => __( 'Chapter', 'sustainable-catalyst-library' ),
            'figure'    => __( 'Figure', 'sustainable-catalyst-library' ),
            'table'     => __( 'Table', 'sustainable-catalyst-library' ),
            'timecode'  => __( 'Timecode', 'sustainable-catalyst-library' ),
            'record'    => __( 'Record or dataset row', 'sustainable-catalyst-library' ),
            'custom'    => __( 'Custom locator', 'sustainable-catalyst-library' ),
        );
    }

    private static function transcription_options() {
        return array(
            'manual'      => __( 'Manual transcription', 'sustainable-catalyst-library' ),
            'copy-paste'  => __( 'Copied from digital text', 'sustainable-catalyst-library' ),
            'ocr'         => __( 'OCR extraction', 'sustainable-catalyst-library' ),
            'api-import'  => __( 'API or database import', 'sustainable-catalyst-library' ),
            'observation' => __( 'Direct research observation', 'sustainable-catalyst-library' ),
        );
    }

    private static function evidence_review_options() {
        return array(
            'draft'     => __( 'Draft', 'sustainable-catalyst-library' ),
            'review'    => __( 'Needs review', 'sustainable-catalyst-library' ),
            'reviewed'  => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'verified'  => __( 'Verified', 'sustainable-catalyst-library' ),
            'disputed'  => __( 'Disputed', 'sustainable-catalyst-library' ),
            'retracted' => __( 'Retracted', 'sustainable-catalyst-library' ),
        );
    }

    private static function claim_status_options() {
        return array(
            'draft'    => __( 'Draft', 'sustainable-catalyst-library' ),
            'review'   => __( 'Under review', 'sustainable-catalyst-library' ),
            'reviewed' => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'verified' => __( 'Verified', 'sustainable-catalyst-library' ),
            'disputed' => __( 'Disputed', 'sustainable-catalyst-library' ),
            'retired'  => __( 'Retired', 'sustainable-catalyst-library' ),
        );
    }

    private static function visibility_options() {
        return array(
            'private' => __( 'Private', 'sustainable-catalyst-library' ),
            'project' => __( 'Visible in an authorized project packet', 'sustainable-catalyst-library' ),
            'public'  => __( 'Public when all linked records permit publication', 'sustainable-catalyst-library' ),
        );
    }

    public static function get_evidence_data( $note_id, $include_private = false ) {
        $post = get_post( $note_id );
        if ( ! $post || self::NOTE_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && ! self::evidence_is_public( $note_id ) ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $note_id ) ) {
            $include_private = false;
            if ( ! self::evidence_is_public( $note_id ) ) {
                return array();
            }
        }

        $source_id = absint( get_post_meta( $note_id, self::META_SOURCE_ID, true ) );
        $document_id = absint( get_post_meta( $note_id, self::META_DOCUMENT_ID, true ) );
        $project_ids = self::id_list( get_post_meta( $note_id, self::META_PROJECT_IDS, true ) );
        $links = self::claim_links( $note_id );

        if ( ! $include_private ) {
            $project_ids = array_values(
                array_filter(
                    $project_ids,
                    array( __CLASS__, 'project_is_public' )
                )
            );
            $links = array_values(
                array_filter(
                    $links,
                    static function ( $link ) {
                        return self::claim_is_public( absint( $link['claim_id'] ?? 0 ) );
                    }
                )
            );
        }

        $attachment_id = absint( get_post_meta( $note_id, self::META_ATTACHMENT_ID, true ) );
        $data = array(
            'schema'               => self::NOTE_SCHEMA,
            'id'                   => $note_id,
            'title'                => get_the_title( $note_id ),
            'content'              => wp_strip_all_tags( $post->post_content ),
            'summary'              => wp_strip_all_tags( $post->post_excerpt ),
            'evidence_type'        => self::evidence_type_slug( $note_id ),
            'evidence_type_label'  => self::evidence_type_label( $note_id ),
            'source_id'            => $source_id,
            'source_title'         => $source_id ? get_the_title( $source_id ) : '',
            'source_url'           => $source_id && 'publish' === get_post_status( $source_id ) ? get_permalink( $source_id ) : '',
            'source_citation'      => $source_id ? SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'reference' ) : '',
            'source_in_text'       => $source_id ? SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'in-text', self::locator_for_citation( $note_id ) ) : '',
            'document_id'          => $document_id,
            'document_title'       => $document_id ? get_the_title( $document_id ) : '',
            'document_url'         => $document_id && 'publish' === get_post_status( $document_id ) ? get_permalink( $document_id ) : '',
            'project_ids'          => $project_ids,
            'claim_links'          => $links,
            'locator_type'         => (string) get_post_meta( $note_id, self::META_LOCATOR_TYPE, true ),
            'locator_start'        => (string) get_post_meta( $note_id, self::META_LOCATOR_START, true ),
            'locator_end'          => (string) get_post_meta( $note_id, self::META_LOCATOR_END, true ),
            'locator_label'        => (string) get_post_meta( $note_id, self::META_LOCATOR_LABEL, true ),
            'context_before'       => (string) get_post_meta( $note_id, self::META_CONTEXT_BEFORE, true ),
            'context_after'        => (string) get_post_meta( $note_id, self::META_CONTEXT_AFTER, true ),
            'analysis'             => (string) get_post_meta( $note_id, self::META_ANALYSIS, true ),
            'transcription_method' => (string) get_post_meta( $note_id, self::META_TRANSCRIPTION_METHOD, true ),
            'quote_verified'       => '1' === get_post_meta( $note_id, self::META_QUOTE_VERIFIED, true ),
            'locator_verified'     => '1' === get_post_meta( $note_id, self::META_LOCATOR_VERIFIED, true ),
            'visibility'           => (string) get_post_meta( $note_id, self::META_VISIBILITY, true ),
            'review_status'        => (string) get_post_meta( $note_id, self::META_REVIEW_STATUS, true ),
            'confidence'           => absint( get_post_meta( $note_id, self::META_CONFIDENCE, true ) ),
            'attachment_id'        => $attachment_id,
            'attachment_url'       => $attachment_id ? wp_get_attachment_url( $attachment_id ) : '',
            'content_hash'         => (string) get_post_meta( $note_id, self::META_CONTENT_HASH, true ),
            'last_reviewed'        => (string) get_post_meta( $note_id, self::META_LAST_REVIEWED, true ),
            'modified_gmt'         => get_post_modified_time( 'c', true, $note_id ),
            'public'               => self::evidence_is_public( $note_id ),
        );

        if ( ! $include_private ) {
            unset( $data['content_hash'] );
        }
        return $data;
    }

    public static function get_claim_data( $claim_id, $include_private = false ) {
        $post = get_post( $claim_id );
        if ( ! $post || self::CLAIM_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && ! self::claim_is_public( $claim_id ) ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $claim_id ) ) {
            $include_private = false;
            if ( ! self::claim_is_public( $claim_id ) ) {
                return array();
            }
        }

        $evidence_ids = self::id_list( get_post_meta( $claim_id, self::META_CLAIM_EVIDENCE_IDS, true ) );
        $project_ids = self::id_list( get_post_meta( $claim_id, self::META_CLAIM_PROJECT_IDS, true ) );
        if ( ! $include_private ) {
            $evidence_ids = array_values( array_filter( $evidence_ids, array( __CLASS__, 'evidence_is_public' ) ) );
            $project_ids = array_values( array_filter( $project_ids, array( __CLASS__, 'project_is_public' ) ) );
        }

        $data = array(
            'schema'           => self::CLAIM_SCHEMA,
            'id'               => $claim_id,
            'title'            => get_the_title( $claim_id ),
            'statement'        => wp_strip_all_tags( $post->post_content ),
            'summary'          => wp_strip_all_tags( $post->post_excerpt ),
            'claim_type'       => self::claim_type_slug( $claim_id ),
            'claim_type_label' => self::claim_type_label( $claim_id ),
            'project_ids'      => $project_ids,
            'evidence_ids'     => $evidence_ids,
            'status'           => (string) get_post_meta( $claim_id, self::META_CLAIM_STATUS, true ),
            'confidence'       => absint( get_post_meta( $claim_id, self::META_CLAIM_CONFIDENCE, true ) ),
            'visibility'       => (string) get_post_meta( $claim_id, self::META_CLAIM_VISIBILITY, true ),
            'scope'            => (string) get_post_meta( $claim_id, self::META_CLAIM_SCOPE, true ),
            'assumptions'      => (string) get_post_meta( $claim_id, self::META_CLAIM_ASSUMPTIONS, true ),
            'limitations'      => (string) get_post_meta( $claim_id, self::META_CLAIM_LIMITATIONS, true ),
            'counterclaim'     => (string) get_post_meta( $claim_id, self::META_CLAIM_COUNTERCLAIM, true ),
            'review_notes'     => $include_private ? (string) get_post_meta( $claim_id, self::META_CLAIM_REVIEW_NOTES, true ) : '',
            'last_reviewed'    => (string) get_post_meta( $claim_id, self::META_CLAIM_LAST_REVIEWED, true ),
            'modified_gmt'     => get_post_modified_time( 'c', true, $claim_id ),
            'public'           => self::claim_is_public( $claim_id ),
        );

        return apply_filters( 'sc_library_claim_data', $data, $claim_id, $include_private );
    }

    public static function claim_packet( $claim_id, $include_private = false ) {
        $claim = self::get_claim_data( $claim_id, $include_private );
        if ( ! $claim ) {
            return array(
                'schema' => self::PACKET_SCHEMA,
                'claim'  => array(),
                'links'  => array(),
            );
        }

        $links = array();
        foreach ( $claim['evidence_ids'] as $note_id ) {
            $evidence = self::get_evidence_data( $note_id, $include_private );
            if ( ! $evidence ) {
                continue;
            }
            foreach ( $evidence['claim_links'] as $link ) {
                if ( absint( $link['claim_id'] ) === absint( $claim_id ) ) {
                    $links[] = array(
                        'link'     => $link,
                        'evidence' => $evidence,
                    );
                    break;
                }
            }
        }

        usort(
            $links,
            static function ( $left, $right ) {
                $relation_order = array(
                    'supports'       => 1,
                    'qualifies'      => 2,
                    'illustrates'    => 3,
                    'contextualizes' => 4,
                    'contradicts'    => 5,
                    'unresolved'     => 6,
                );
                $left_relation = $left['link']['relation'] ?? 'unresolved';
                $right_relation = $right['link']['relation'] ?? 'unresolved';
                $relation_compare = ( $relation_order[ $left_relation ] ?? 99 ) <=> ( $relation_order[ $right_relation ] ?? 99 );
                if ( 0 !== $relation_compare ) {
                    return $relation_compare;
                }
                return absint( $right['link']['strength'] ?? 0 ) <=> absint( $left['link']['strength'] ?? 0 );
            }
        );

        return array(
            'schema'       => self::PACKET_SCHEMA,
            'claim'        => $claim,
            'links'        => $links,
            'link_count'   => count( $links ),
            'relation_totals' => self::relation_totals( $links ),
            'generated_at' => current_time( 'mysql' ),
        );
    }

    public static function project_packet( $project_id, $include_private = false ) {
        $project = get_post( $project_id );
        if ( ! $project || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== $project->post_type ) {
            return array();
        }
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $project_id ) ) {
            $include_private = false;
            if ( ! self::project_is_public( $project_id ) ) {
                return array();
            }
        }

        $claim_ids = self::claim_ids_for_project( $project_id, $include_private );
        $note_ids = self::evidence_ids_for_project( $project_id, $include_private );
        $claims = array();
        foreach ( $claim_ids as $claim_id ) {
            $packet = self::claim_packet( $claim_id, $include_private );
            if ( $packet['claim'] ) {
                $claims[] = $packet;
                foreach ( $packet['claim']['evidence_ids'] as $evidence_id ) {
                    $note_ids[] = $evidence_id;
                }
            }
        }

        $notes = array();
        foreach ( array_values( array_unique( $note_ids ) ) as $note_id ) {
            $data = self::get_evidence_data( $note_id, $include_private );
            if ( $data ) {
                $notes[] = $data;
            }
        }

        return array(
            'schema'       => self::PACKET_SCHEMA,
            'project'      => array(
                'id'          => $project_id,
                'title'       => get_the_title( $project_id ),
                'description' => wp_strip_all_tags( $project->post_content ),
                'status'      => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_STATUS, true ),
                'visibility'  => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true ),
            ),
            'claims'       => $claims,
            'evidence'     => $notes,
            'claim_count'  => count( $claims ),
            'evidence_count' => count( $notes ),
            'generated_at' => current_time( 'mysql' ),
        );
    }

    private static function relation_totals( $links ) {
        $totals = array_fill_keys( array_keys( self::relation_options() ), 0 );
        foreach ( $links as $item ) {
            $relation = $item['link']['relation'] ?? 'unresolved';
            if ( ! isset( $totals[ $relation ] ) ) {
                $totals[ $relation ] = 0;
            }
            $totals[ $relation ]++;
        }
        return $totals;
    }

    public static function evidence_is_public( $note_id ) {
        if ( self::NOTE_POST_TYPE !== get_post_type( $note_id ) || 'publish' !== get_post_status( $note_id ) ) {
            return false;
        }
        if ( 'public' !== get_post_meta( $note_id, self::META_VISIBILITY, true ) ) {
            return false;
        }
        if ( 'retracted' === get_post_meta( $note_id, self::META_REVIEW_STATUS, true ) ) {
            return false;
        }
        $source_id = absint( get_post_meta( $note_id, self::META_SOURCE_ID, true ) );
        $document_id = absint( get_post_meta( $note_id, self::META_DOCUMENT_ID, true ) );
        if ( $source_id && 'publish' !== get_post_status( $source_id ) ) {
            return false;
        }
        if ( $document_id && 'publish' !== get_post_status( $document_id ) ) {
            return false;
        }
        return true;
    }

    public static function claim_is_public( $claim_id ) {
        if ( self::CLAIM_POST_TYPE !== get_post_type( $claim_id ) || 'publish' !== get_post_status( $claim_id ) ) {
            return false;
        }
        if ( 'public' !== get_post_meta( $claim_id, self::META_CLAIM_VISIBILITY, true ) ) {
            return false;
        }
        return ! in_array( get_post_meta( $claim_id, self::META_CLAIM_STATUS, true ), array( 'retired' ), true );
    }

    public static function project_is_public( $project_id ) {
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && 'publish' === get_post_status( $project_id )
            && 'public' === get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
    }

    public static function evidence_ids_for_source( $source_id, $include_private = false ) {
        $ids = get_posts(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_SOURCE_ID,
                'meta_value'     => absint( $source_id ),
                'orderby'        => 'date',
                'order'          => 'ASC',
            )
        );
        return $include_private ? array_map( 'absint', $ids ) : array_values( array_filter( array_map( 'absint', $ids ), array( __CLASS__, 'evidence_is_public' ) ) );
    }

    public static function evidence_ids_for_project( $project_id, $include_private = false ) {
        $ids = get_posts(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::META_PROJECT_IDS,
                        'value'   => 'i:' . absint( $project_id ) . ';',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        return $include_private ? array_map( 'absint', $ids ) : array_values( array_filter( array_map( 'absint', $ids ), array( __CLASS__, 'evidence_is_public' ) ) );
    }

    public static function claim_ids_for_project( $project_id, $include_private = false ) {
        $ids = get_posts(
            array(
                'post_type'      => self::CLAIM_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::META_CLAIM_PROJECT_IDS,
                        'value'   => 'i:' . absint( $project_id ) . ';',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        return $include_private ? array_map( 'absint', $ids ) : array_values( array_filter( array_map( 'absint', $ids ), array( __CLASS__, 'claim_is_public' ) ) );
    }

    public static function locator_display( $data ) {
        if ( is_numeric( $data ) ) {
            $data = self::get_evidence_data( absint( $data ), true );
        }
        if ( ! is_array( $data ) ) {
            return '';
        }
        $custom = trim( (string) ( $data['locator_label'] ?? '' ) );
        if ( $custom ) {
            return $custom;
        }
        $type = sanitize_key( $data['locator_type'] ?? 'page' );
        $start = trim( (string) ( $data['locator_start'] ?? '' ) );
        $end = trim( (string) ( $data['locator_end'] ?? '' ) );
        if ( ! $start && ! $end ) {
            return '';
        }
        $range = $start;
        if ( $end && $end !== $start ) {
            $range .= '–' . $end;
        }
        $prefixes = array(
            'page'      => 'p. ',
            'pages'     => 'pp. ',
            'paragraph' => 'para. ',
            'section'   => '§ ',
            'chapter'   => 'ch. ',
            'figure'    => 'fig. ',
            'table'     => 'table ',
            'timecode'  => '',
            'record'    => 'record ',
            'custom'    => '',
        );
        if ( 'page' === $type && $end && $end !== $start ) {
            return 'pp. ' . $range;
        }
        return ( $prefixes[ $type ] ?? '' ) . $range;
    }

    private static function locator_for_citation( $note_id ) {
        $data = array(
            'locator_type'  => get_post_meta( $note_id, self::META_LOCATOR_TYPE, true ),
            'locator_start' => get_post_meta( $note_id, self::META_LOCATOR_START, true ),
            'locator_end'   => get_post_meta( $note_id, self::META_LOCATOR_END, true ),
            'locator_label' => get_post_meta( $note_id, self::META_LOCATOR_LABEL, true ),
        );
        return self::locator_display( $data );
    }

    public static function citation_ready_quote( $data ) {
        if ( is_numeric( $data ) ) {
            $data = self::get_evidence_data( absint( $data ), true );
        }
        if ( ! is_array( $data ) || empty( $data['content'] ) ) {
            return '';
        }
        $content = trim( preg_replace( '/\s+/u', ' ', $data['content'] ) );
        $is_quote = 'direct-quotation' === ( $data['evidence_type'] ?? '' );
        $rendered = $is_quote ? '“' . trim( $content, " \t\n\r\0\x0B\"“”" ) . '”' : $content;
        $citation = trim( (string) ( $data['source_in_text'] ?? '' ) );
        if ( ! $citation && ! empty( $data['source_citation'] ) ) {
            $citation = $data['source_citation'];
        }
        return trim( $rendered . ( $citation ? ' ' . $citation : '' ) );
    }

    public static function evidence_markdown( $data ) {
        if ( is_numeric( $data ) ) {
            $data = self::get_evidence_data( absint( $data ), true );
        }
        if ( ! is_array( $data ) || empty( $data['title'] ) ) {
            return '';
        }
        $lines = array(
            '### ' . $data['title'],
            '',
            '**Type:** ' . $data['evidence_type_label'],
            '**Review:** ' . ucfirst( str_replace( '-', ' ', $data['review_status'] ) ),
            '**Locator:** ' . ( self::locator_display( $data ) ?: 'Not recorded' ),
            '',
        );
        if ( 'direct-quotation' === $data['evidence_type'] ) {
            foreach ( preg_split( '/\R/u', trim( $data['content'] ) ) as $line ) {
                $lines[] = '> ' . $line;
            }
        } else {
            $lines[] = $data['content'];
        }
        $lines[] = '';
        if ( $data['source_citation'] ) {
            $lines[] = '**Source:** ' . $data['source_citation'];
        } elseif ( $data['document_title'] ) {
            $lines[] = '**Document:** ' . $data['document_title'];
        }
        if ( $data['summary'] ) {
            $lines[] = '';
            $lines[] = '**Evidence note:** ' . $data['summary'];
        }
        if ( $data['analysis'] ) {
            $lines[] = '';
            $lines[] = '**Analysis:** ' . $data['analysis'];
        }
        if ( $data['claim_links'] ) {
            $lines[] = '';
            $lines[] = '**Claim links:**';
            foreach ( $data['claim_links'] as $link ) {
                $lines[] = '- ' . self::relation_label( $link['relation'] ) . ': ' . get_the_title( $link['claim_id'] ) . ' (strength ' . $link['strength'] . '/5)' . ( $link['note'] ? ' — ' . $link['note'] : '' );
            }
        }
        return implode( "\n", $lines );
    }

    public static function claim_packet_markdown( $claim_id, $include_private = false ) {
        $packet = self::claim_packet( $claim_id, $include_private );
        if ( empty( $packet['claim'] ) ) {
            return '';
        }
        $claim = $packet['claim'];
        $lines = array(
            '# ' . $claim['title'],
            '',
            $claim['statement'],
            '',
            '**Claim type:** ' . $claim['claim_type_label'],
            '**Status:** ' . ucfirst( $claim['status'] ),
            '**Confidence:** ' . $claim['confidence'] . '%',
        );
        foreach ( array(
            'scope'        => 'Scope',
            'assumptions'  => 'Assumptions',
            'limitations'  => 'Limitations',
            'counterclaim' => 'Counterclaim',
        ) as $key => $label ) {
            if ( $claim[ $key ] ) {
                $lines[] = '';
                $lines[] = '**' . $label . ':** ' . $claim[ $key ];
            }
        }
        foreach ( self::relation_options() as $relation => $label ) {
            $matching = array_values(
                array_filter(
                    $packet['links'],
                    static function ( $item ) use ( $relation ) {
                        return $relation === ( $item['link']['relation'] ?? '' );
                    }
                )
            );
            if ( ! $matching ) {
                continue;
            }
            $lines[] = '';
            $lines[] = '## ' . $label;
            foreach ( $matching as $item ) {
                $lines[] = '';
                $lines[] = self::evidence_markdown( $item['evidence'] );
                if ( $item['link']['note'] ) {
                    $lines[] = '';
                    $lines[] = '**Link rationale:** ' . $item['link']['note'];
                }
            }
        }
        return implode( "\n", $lines );
    }

    public static function project_packet_markdown( $project_id, $include_private = false ) {
        $packet = self::project_packet( $project_id, $include_private );
        if ( ! $packet ) {
            return '';
        }
        $lines = array(
            '# ' . $packet['project']['title'] . ' — Evidence Packet',
            '',
            $packet['project']['description'],
            '',
            '**Claims:** ' . $packet['claim_count'],
            '**Evidence notes:** ' . $packet['evidence_count'],
        );
        foreach ( $packet['claims'] as $claim_packet ) {
            $lines[] = '';
            $lines[] = self::claim_packet_markdown( $claim_packet['claim']['id'], $include_private );
        }
        $linked_note_ids = array();
        foreach ( $packet['claims'] as $claim_packet ) {
            $linked_note_ids = array_merge( $linked_note_ids, $claim_packet['claim']['evidence_ids'] );
        }
        $unlinked = array_values(
            array_filter(
                $packet['evidence'],
                static function ( $evidence ) use ( $linked_note_ids ) {
                    return ! in_array( $evidence['id'], $linked_note_ids, true );
                }
            )
        );
        if ( $unlinked ) {
            $lines[] = '';
            $lines[] = '# Project Evidence Not Yet Linked to a Claim';
            foreach ( $unlinked as $evidence ) {
                $lines[] = '';
                $lines[] = self::evidence_markdown( $evidence );
            }
        }
        return implode( "\n", $lines );
    }

    private static function evidence_content_hash( $note_id ) {
        return hash(
            'sha256',
            wp_json_encode(
                array(
                    'content'       => get_post_field( 'post_content', $note_id ),
                    'source_id'     => absint( get_post_meta( $note_id, self::META_SOURCE_ID, true ) ),
                    'document_id'   => absint( get_post_meta( $note_id, self::META_DOCUMENT_ID, true ) ),
                    'locator_type'  => get_post_meta( $note_id, self::META_LOCATOR_TYPE, true ),
                    'locator_start' => get_post_meta( $note_id, self::META_LOCATOR_START, true ),
                    'locator_end'   => get_post_meta( $note_id, self::META_LOCATOR_END, true ),
                    'locator_label' => get_post_meta( $note_id, self::META_LOCATOR_LABEL, true ),
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private static function claim_content_hash( $claim_id ) {
        return hash(
            'sha256',
            wp_json_encode(
                array(
                    'title'         => get_the_title( $claim_id ),
                    'statement'     => get_post_field( 'post_content', $claim_id ),
                    'scope'         => get_post_meta( $claim_id, self::META_CLAIM_SCOPE, true ),
                    'assumptions'   => get_post_meta( $claim_id, self::META_CLAIM_ASSUMPTIONS, true ),
                    'limitations'   => get_post_meta( $claim_id, self::META_CLAIM_LIMITATIONS, true ),
                    'counterclaim'  => get_post_meta( $claim_id, self::META_CLAIM_COUNTERCLAIM, true ),
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private static function evidence_type_slug( $note_id ) {
        $terms = wp_get_object_terms( $note_id, self::EVIDENCE_TYPE_TAXONOMY );
        return is_wp_error( $terms ) || ! $terms ? 'observation' : $terms[0]->slug;
    }

    private static function evidence_type_label( $note_id ) {
        $terms = wp_get_object_terms( $note_id, self::EVIDENCE_TYPE_TAXONOMY );
        return is_wp_error( $terms ) || ! $terms ? __( 'Evidence note', 'sustainable-catalyst-library' ) : $terms[0]->name;
    }

    private static function claim_type_slug( $claim_id ) {
        $terms = wp_get_object_terms( $claim_id, self::CLAIM_TYPE_TAXONOMY );
        return is_wp_error( $terms ) || ! $terms ? 'descriptive' : $terms[0]->slug;
    }

    private static function claim_type_label( $claim_id ) {
        $terms = wp_get_object_terms( $claim_id, self::CLAIM_TYPE_TAXONOMY );
        return is_wp_error( $terms ) || ! $terms ? __( 'Research claim', 'sustainable-catalyst-library' ) : $terms[0]->name;
    }

    private static function count_open_links() {
        $ids = get_posts(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_CLAIM_LINKS,
            )
        );
        $count = 0;
        foreach ( $ids as $id ) {
            $count += count( self::claim_links( $id ) );
        }
        return $count;
    }

    private static function count_verified_notes() {
        $query = new WP_Query(
            array(
                'post_type'      => self::NOTE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'   => self::META_REVIEW_STATUS,
                        'value' => 'verified',
                    ),
                    array(
                        'relation' => 'AND',
                        array(
                            'key'   => self::META_QUOTE_VERIFIED,
                            'value' => '1',
                        ),
                        array(
                            'key'   => self::META_LOCATOR_VERIFIED,
                            'value' => '1',
                        ),
                    ),
                ),
            )
        );
        return absint( $query->found_posts );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $supported = in_array(
            $screen->post_type,
            array(
                self::NOTE_POST_TYPE,
                self::CLAIM_POST_TYPE,
                SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-evidence-claims' );
        if ( ! $supported && ! $workspace ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'sc-library-evidence-claims',
            SC_LIBRARY_URL . 'assets/css/sc-library-evidence-claims.css',
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-evidence-claims',
            SC_LIBRARY_URL . 'assets/js/sc-library-evidence-claims.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-evidence-claims',
            'SCLibraryEvidenceClaims',
            array(
                'strings' => array(
                    'copied'          => __( 'Copied.', 'sustainable-catalyst-library' ),
                    'copyFailed'      => __( 'Copy failed.', 'sustainable-catalyst-library' ),
                    'selectFile'      => __( 'Select Supporting Evidence', 'sustainable-catalyst-library' ),
                    'useFile'         => __( 'Use this file', 'sustainable-catalyst-library' ),
                    'noAttachment'    => __( 'No attachment selected', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-evidence-claims',
            SC_LIBRARY_URL . 'assets/css/sc-library-evidence-claims.css',
            array(),
            self::VERSION
        );
        wp_register_script(
            'sc-library-evidence-claims',
            SC_LIBRARY_URL . 'assets/js/sc-library-evidence-claims.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function shortcode_evidence_note( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'include_private' => 'false' ), $atts, 'sc_evidence_note' );
        $note_id = absint( $atts['id'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && $note_id && current_user_can( 'edit_post', $note_id );
        $data = self::get_evidence_data( $note_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-evidence-claims' );
        wp_enqueue_script( 'sc-library-evidence-claims' );
        return self::render_evidence_card( $data, true );
    }

    public function shortcode_claim_evidence( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'include_private' => 'false' ), $atts, 'sc_claim_evidence' );
        $claim_id = absint( $atts['id'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && $claim_id && current_user_can( 'edit_post', $claim_id );
        $packet = self::claim_packet( $claim_id, $include_private );
        if ( empty( $packet['claim'] ) ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-evidence-claims' );
        wp_enqueue_script( 'sc-library-evidence-claims' );
        return self::render_claim_packet_html( $packet );
    }

    public function shortcode_project_evidence( $atts ) {
        $atts = shortcode_atts(
            array(
                'project'         => '',
                'format'          => 'cards',
                'include_private' => 'false',
            ),
            $atts,
            'sc_project_evidence'
        );
        $project_id = self::resolve_project_id( $atts['project'] );
        if ( ! $project_id ) {
            return '';
        }
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $project_id );
        $packet = self::project_packet( $project_id, $include_private );
        if ( ! $packet ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-evidence-claims' );
        wp_enqueue_script( 'sc-library-evidence-claims' );

        if ( 'markdown' === sanitize_key( $atts['format'] ) && $include_private ) {
            return '<pre class="sc-evidence-markdown-export">' . esc_html( self::project_packet_markdown( $project_id, true ) ) . '</pre>';
        }

        ob_start();
        ?>
        <section class="sc-project-evidence-packet" aria-labelledby="sc-project-evidence-heading-<?php echo esc_attr( $project_id ); ?>">
            <header>
                <p class="sc-evidence-kicker"><?php esc_html_e( 'Research evidence packet', 'sustainable-catalyst-library' ); ?></p>
                <h2 id="sc-project-evidence-heading-<?php echo esc_attr( $project_id ); ?>"><?php echo esc_html( $packet['project']['title'] ); ?></h2>
                <?php if ( $packet['project']['description'] ) : ?><p><?php echo esc_html( $packet['project']['description'] ); ?></p><?php endif; ?>
                <div class="sc-evidence-packet-metrics">
                    <span><?php echo esc_html( sprintf( __( '%d claims', 'sustainable-catalyst-library' ), $packet['claim_count'] ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( '%d evidence notes', 'sustainable-catalyst-library' ), $packet['evidence_count'] ) ); ?></span>
                </div>
            </header>
            <?php foreach ( $packet['claims'] as $claim_packet ) : ?>
                <?php echo self::render_claim_packet_html( $claim_packet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
            <?php if ( ! $packet['claims'] && $packet['evidence'] ) : ?>
                <div class="sc-evidence-card-grid">
                    <?php foreach ( $packet['evidence'] as $evidence ) : ?>
                        <?php echo self::render_evidence_card( $evidence, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_public_source_evidence( $source_id ) {
        $note_ids = self::evidence_ids_for_source( $source_id, false );
        if ( ! $note_ids ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-evidence-claims' );
        wp_enqueue_script( 'sc-library-evidence-claims' );
        ob_start();
        ?>
        <section class="sc-source-evidence-section" aria-labelledby="sc-source-evidence-heading-<?php echo esc_attr( $source_id ); ?>">
            <p class="sc-source-record__kicker"><?php esc_html_e( 'Quotations and evidence', 'sustainable-catalyst-library' ); ?></p>
            <h2 id="sc-source-evidence-heading-<?php echo esc_attr( $source_id ); ?>"><?php esc_html_e( 'Evidence notes from this source', 'sustainable-catalyst-library' ); ?></h2>
            <div class="sc-evidence-card-grid">
                <?php foreach ( $note_ids as $note_id ) : ?>
                    <?php $data = self::get_evidence_data( $note_id, false ); ?>
                    <?php if ( $data ) : ?>
                        <?php echo self::render_evidence_card( $data, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_evidence_card( $data, $show_source = true ) {
        $quote = self::citation_ready_quote( $data );
        ob_start();
        ?>
        <article class="sc-evidence-card relation-<?php echo esc_attr( $data['claim_links'][0]['relation'] ?? 'unlinked' ); ?>">
            <header>
                <div>
                    <p class="sc-evidence-card__type"><?php echo esc_html( $data['evidence_type_label'] ); ?></p>
                    <h3><?php echo esc_html( $data['title'] ); ?></h3>
                </div>
                <span class="sc-evidence-card__review"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $data['review_status'] ) ) ); ?></span>
            </header>
            <?php if ( 'direct-quotation' === $data['evidence_type'] ) : ?>
                <blockquote><?php echo esc_html( $data['content'] ); ?></blockquote>
            <?php else : ?>
                <p class="sc-evidence-card__content"><?php echo esc_html( $data['content'] ); ?></p>
            <?php endif; ?>
            <dl>
                <?php if ( self::locator_display( $data ) ) : ?><div><dt><?php esc_html_e( 'Locator', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( self::locator_display( $data ) ); ?></dd></div><?php endif; ?>
                <div><dt><?php esc_html_e( 'Confidence', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['confidence'] . '/5' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Quote checked', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['quote_verified'] ? __( 'Yes', 'sustainable-catalyst-library' ) : __( 'No', 'sustainable-catalyst-library' ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Locator checked', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['locator_verified'] ? __( 'Yes', 'sustainable-catalyst-library' ) : __( 'No', 'sustainable-catalyst-library' ) ); ?></dd></div>
            </dl>
            <?php if ( $data['summary'] ) : ?><p class="sc-evidence-card__note"><?php echo esc_html( $data['summary'] ); ?></p><?php endif; ?>
            <?php if ( $data['analysis'] ) : ?><details><summary><?php esc_html_e( 'Analysis', 'sustainable-catalyst-library' ); ?></summary><p><?php echo esc_html( $data['analysis'] ); ?></p></details><?php endif; ?>
            <?php if ( $show_source && $data['source_citation'] ) : ?><p class="sc-evidence-card__citation"><?php echo esc_html( $data['source_citation'] ); ?></p><?php endif; ?>
            <?php if ( $data['claim_links'] ) : ?>
                <div class="sc-evidence-card__claims">
                    <strong><?php esc_html_e( 'Claim relationships', 'sustainable-catalyst-library' ); ?></strong>
                    <ul>
                        <?php foreach ( $data['claim_links'] as $link ) : ?>
                            <li><span><?php echo esc_html( self::relation_label( $link['relation'] ) ); ?></span> <?php echo esc_html( get_the_title( $link['claim_id'] ) ); ?> · <?php echo esc_html( $link['strength'] . '/5' ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ( $quote ) : ?>
                <button type="button" class="sc-evidence-copy" data-sc-copy-value="<?php echo esc_attr( $quote ); ?>"><?php esc_html_e( 'Copy citation-ready evidence', 'sustainable-catalyst-library' ); ?></button>
            <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function render_claim_packet_html( $packet ) {
        $claim = $packet['claim'];
        ob_start();
        ?>
        <section class="sc-claim-packet" aria-labelledby="sc-claim-heading-<?php echo esc_attr( $claim['id'] ); ?>">
            <header>
                <p class="sc-evidence-kicker"><?php echo esc_html( $claim['claim_type_label'] ); ?></p>
                <h2 id="sc-claim-heading-<?php echo esc_attr( $claim['id'] ); ?>"><?php echo esc_html( $claim['title'] ); ?></h2>
                <p class="sc-claim-packet__statement"><?php echo esc_html( $claim['statement'] ); ?></p>
                <div class="sc-evidence-packet-metrics">
                    <span><?php echo esc_html( ucfirst( $claim['status'] ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( '%d%% confidence', 'sustainable-catalyst-library' ), $claim['confidence'] ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( '%d evidence links', 'sustainable-catalyst-library' ), $packet['link_count'] ) ); ?></span>
                </div>
            </header>
            <?php if ( class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) : ?>
                <?php echo SC_Library_Topics_Concepts_Relationships::render_public_node_panel( 'claim', $claim['id'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php foreach ( self::relation_options() as $relation => $label ) : ?>
                <?php
                $items = array_values(
                    array_filter(
                        $packet['links'],
                        static function ( $item ) use ( $relation ) {
                            return $relation === ( $item['link']['relation'] ?? '' );
                        }
                    )
                );
                ?>
                <?php if ( $items ) : ?>
                    <section class="sc-claim-relation-group relation-<?php echo esc_attr( $relation ); ?>">
                        <h3><?php echo esc_html( $label ); ?> <span><?php echo esc_html( count( $items ) ); ?></span></h3>
                        <div class="sc-evidence-card-grid">
                            <?php foreach ( $items as $item ) : ?>
                                <?php echo self::render_evidence_card( $item['evidence'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ( $claim['limitations'] || $claim['counterclaim'] ) : ?>
                <aside class="sc-claim-packet__limits">
                    <?php if ( $claim['limitations'] ) : ?><p><strong><?php esc_html_e( 'Limitations:', 'sustainable-catalyst-library' ); ?></strong> <?php echo esc_html( $claim['limitations'] ); ?></p><?php endif; ?>
                    <?php if ( $claim['counterclaim'] ) : ?><p><strong><?php esc_html_e( 'Counterclaim:', 'sustainable-catalyst-library' ); ?></strong> <?php echo esc_html( $claim['counterclaim'] ); ?></p><?php endif; ?>
                </aside>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function resolve_project_id( $value ) {
        if ( is_numeric( $value ) && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/evidence-notes',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_list_evidence' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_evidence' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'edit_posts' );
                    },
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/evidence-notes/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_evidence' ),
                    'permission_callback' => array( $this, 'rest_can_read_evidence' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_evidence' ),
                    'permission_callback' => array( $this, 'rest_can_edit_evidence' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/evidence-notes/(?P<id>\d+)/links',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_evidence_links' ),
                'permission_callback' => array( $this, 'rest_can_edit_evidence' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/claims',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_list_claims' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_claim' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'edit_posts' );
                    },
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/claims/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_claim' ),
                    'permission_callback' => array( $this, 'rest_can_read_claim' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_claim' ),
                    'permission_callback' => array( $this, 'rest_can_edit_claim' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/claims/(?P<id>\d+)/evidence',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_claim_packet' ),
                'permission_callback' => array( $this, 'rest_can_read_claim' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/evidence',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_packet' ),
                'permission_callback' => array( $this, 'rest_can_read_project_packet' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/evidence/export',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_export_evidence' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_list_evidence( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' );
        $args = array(
            'post_type'      => self::NOTE_POST_TYPE,
            'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
            'posts_per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
            'paged'          => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );
        $search = sanitize_text_field( $request->get_param( 'search' ) );
        if ( $search ) {
            $args['s'] = $search;
        }

        $meta_query = array();
        $source_id = absint( $request->get_param( 'source_id' ) );
        $document_id = absint( $request->get_param( 'document_id' ) );
        $project_id = absint( $request->get_param( 'project_id' ) );
        $claim_id = absint( $request->get_param( 'claim_id' ) );
        if ( $source_id ) {
            $meta_query[] = array( 'key' => self::META_SOURCE_ID, 'value' => $source_id, 'compare' => '=' );
        }
        if ( $document_id ) {
            $meta_query[] = array( 'key' => self::META_DOCUMENT_ID, 'value' => $document_id, 'compare' => '=' );
        }
        if ( $project_id ) {
            $meta_query[] = array( 'key' => self::META_PROJECT_IDS, 'value' => 'i:' . $project_id . ';', 'compare' => 'LIKE' );
        }
        if ( $claim_id ) {
            $meta_query[] = array( 'key' => self::META_CLAIM_LINKS, 'value' => '"claim_id";i:' . $claim_id . ';', 'compare' => 'LIKE' );
        }
        if ( ! $include_private ) {
            $meta_query[] = array( 'key' => self::META_VISIBILITY, 'value' => 'public', 'compare' => '=' );
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $data = self::get_evidence_data( $post->ID, $include_private );
            if ( $data ) {
                $items[] = $data;
            }
        }
        $response = rest_ensure_response(
            array(
                'schema' => self::NOTE_SCHEMA,
                'items'  => $items,
                'page'   => max( 1, absint( $args['paged'] ) ),
                'total'  => absint( $query->found_posts ),
                'pages'  => absint( $query->max_num_pages ),
            )
        );
        $response->header( 'X-WP-Total', absint( $query->found_posts ) );
        $response->header( 'X-WP-TotalPages', absint( $query->max_num_pages ) );
        return $response;
    }

    public function rest_get_evidence( WP_REST_Request $request ) {
        $note_id = absint( $request['id'] );
        return rest_ensure_response( self::get_evidence_data( $note_id, current_user_can( 'edit_post', $note_id ) ) );
    }

    public function rest_create_evidence( WP_REST_Request $request ) {
        return $this->rest_upsert_evidence( $request, 0 );
    }

    public function rest_update_evidence( WP_REST_Request $request ) {
        return $this->rest_upsert_evidence( $request, absint( $request['id'] ) );
    }

    private function rest_upsert_evidence( WP_REST_Request $request, $note_id ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $old_hash = $note_id ? (string) get_post_meta( $note_id, self::META_CONTENT_HASH, true ) : '';
        $creating = ! $note_id;
        if ( $creating ) {
            $note_id = wp_insert_post(
                wp_slash(
                    array(
                        'post_type'    => self::NOTE_POST_TYPE,
                        'post_status'  => self::rest_status( $payload['post_status'] ?? 'draft' ),
                        'post_title'   => sanitize_text_field( $payload['title'] ?? __( 'Untitled evidence note', 'sustainable-catalyst-library' ) ),
                        'post_content' => wp_kses_post( $payload['content'] ?? '' ),
                        'post_excerpt' => sanitize_textarea_field( $payload['summary'] ?? '' ),
                    )
                ),
                true
            );
            if ( is_wp_error( $note_id ) ) {
                return $note_id;
            }
        } else {
            $update = array( 'ID' => $note_id );
            if ( array_key_exists( 'title', $payload ) ) {
                $update['post_title'] = sanitize_text_field( $payload['title'] );
            }
            if ( array_key_exists( 'content', $payload ) ) {
                $update['post_content'] = wp_kses_post( $payload['content'] );
            }
            if ( array_key_exists( 'summary', $payload ) ) {
                $update['post_excerpt'] = sanitize_textarea_field( $payload['summary'] );
            }
            if ( array_key_exists( 'post_status', $payload ) ) {
                $update['post_status'] = self::rest_status( $payload['post_status'] );
            }
            if ( count( $update ) > 1 ) {
                wp_update_post( wp_slash( $update ) );
            }
        }

        $old_links = self::claim_links( $note_id );

        if ( array_key_exists( 'source_id', $payload ) ) {
            $source_id = absint( $payload['source_id'] );
            if ( $source_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                return new WP_Error( 'invalid_evidence_source', __( 'The selected Research Source is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
            }
            self::update_or_delete_meta( $note_id, self::META_SOURCE_ID, $source_id );
        }
        if ( array_key_exists( 'document_id', $payload ) ) {
            $document_id = absint( $payload['document_id'] );
            if ( $document_id && SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
                return new WP_Error( 'invalid_evidence_document', __( 'The selected Knowledge Library document is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
            }
            self::update_or_delete_meta( $note_id, self::META_DOCUMENT_ID, $document_id );
        }
        if ( array_key_exists( 'attachment_id', $payload ) ) {
            $attachment_id = absint( $payload['attachment_id'] );
            if ( $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
                return new WP_Error( 'invalid_evidence_attachment', __( 'The selected evidence attachment is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
            }
            self::update_or_delete_meta( $note_id, self::META_ATTACHMENT_ID, $attachment_id );
        }
        if ( array_key_exists( 'locator_type', $payload ) ) {
            self::update_or_delete_meta( $note_id, self::META_LOCATOR_TYPE, self::allowed_value( $payload['locator_type'], self::locator_type_options(), 'page' ) );
        }
        if ( array_key_exists( 'transcription_method', $payload ) ) {
            self::update_or_delete_meta( $note_id, self::META_TRANSCRIPTION_METHOD, self::allowed_value( $payload['transcription_method'], self::transcription_options(), 'manual' ) );
        }
        if ( array_key_exists( 'visibility', $payload ) ) {
            self::update_or_delete_meta( $note_id, self::META_VISIBILITY, self::allowed_value( $payload['visibility'], self::visibility_options(), 'private' ) );
        }
        if ( array_key_exists( 'review_status', $payload ) ) {
            self::update_or_delete_meta( $note_id, self::META_REVIEW_STATUS, self::allowed_value( $payload['review_status'], self::evidence_review_options(), 'draft' ) );
        }

        $simple_fields = array(
            'locator_start'  => array( self::META_LOCATOR_START, 'text' ),
            'locator_end'    => array( self::META_LOCATOR_END, 'text' ),
            'locator_label'  => array( self::META_LOCATOR_LABEL, 'text' ),
            'context_before' => array( self::META_CONTEXT_BEFORE, 'textarea' ),
            'context_after'  => array( self::META_CONTEXT_AFTER, 'textarea' ),
            'analysis'       => array( self::META_ANALYSIS, 'textarea' ),
        );
        foreach ( $simple_fields as $field => $definition ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }
            $value = self::sanitize_rest_value( $payload[ $field ], $definition[1] );
            self::update_or_delete_meta( $note_id, $definition[0], $value );
        }
        if ( array_key_exists( 'confidence', $payload ) ) {
            update_post_meta( $note_id, self::META_CONFIDENCE, max( 1, min( 5, absint( $payload['confidence'] ) ) ) );
        }
        if ( array_key_exists( 'quote_verified', $payload ) ) {
            update_post_meta( $note_id, self::META_QUOTE_VERIFIED, rest_sanitize_boolean( $payload['quote_verified'] ) ? '1' : '0' );
        }
        if ( array_key_exists( 'locator_verified', $payload ) ) {
            update_post_meta( $note_id, self::META_LOCATOR_VERIFIED, rest_sanitize_boolean( $payload['locator_verified'] ) ? '1' : '0' );
        }
        if ( array_key_exists( 'project_ids', $payload ) ) {
            self::update_id_meta( $note_id, self::META_PROJECT_IDS, self::validated_ids( $payload['project_ids'], SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, self::MAX_PROJECTS ) );
        }
        if ( array_key_exists( 'claim_links', $payload ) ) {
            $links = self::sanitize_claim_links( $payload['claim_links'], $old_links );
            $links ? update_post_meta( $note_id, self::META_CLAIM_LINKS, $links ) : delete_post_meta( $note_id, self::META_CLAIM_LINKS );
            self::sync_claim_reverse_links( $note_id, $old_links, $links );
        }
        if ( ! empty( $payload['evidence_type'] ) ) {
            wp_set_object_terms( $note_id, array( sanitize_title( $payload['evidence_type'] ) ), self::EVIDENCE_TYPE_TAXONOMY, false );
        }

        $new_hash = self::evidence_content_hash( $note_id );
        if ( $old_hash && $old_hash !== $new_hash && empty( $payload['reverified'] ) ) {
            update_post_meta( $note_id, self::META_QUOTE_VERIFIED, '0' );
            update_post_meta( $note_id, self::META_LOCATOR_VERIFIED, '0' );
            if ( 'verified' === get_post_meta( $note_id, self::META_REVIEW_STATUS, true ) ) {
                update_post_meta( $note_id, self::META_REVIEW_STATUS, 'review' );
            }
        }
        update_post_meta( $note_id, self::META_CONTENT_HASH, $new_hash );

        $review_status = (string) get_post_meta( $note_id, self::META_REVIEW_STATUS, true );
        if (
            '1' === get_post_meta( $note_id, self::META_QUOTE_VERIFIED, true )
            || '1' === get_post_meta( $note_id, self::META_LOCATOR_VERIFIED, true )
            || in_array( $review_status, array( 'reviewed', 'verified' ), true )
        ) {
            update_post_meta( $note_id, self::META_LAST_REVIEWED, current_time( 'mysql' ) );
            update_post_meta( $note_id, self::META_LAST_REVIEWED_BY, get_current_user_id() );
        }

        return rest_ensure_response( self::get_evidence_data( $note_id, true ) );
    }

    public function rest_update_evidence_links( WP_REST_Request $request ) {
        $note_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $old_links = self::claim_links( $note_id );
        $links = self::sanitize_claim_links( $payload['links'] ?? array(), $old_links );
        $links ? update_post_meta( $note_id, self::META_CLAIM_LINKS, $links ) : delete_post_meta( $note_id, self::META_CLAIM_LINKS );
        self::sync_claim_reverse_links( $note_id, $old_links, $links );
        return rest_ensure_response(
            array(
                'schema' => self::LINK_SCHEMA,
                'note_id' => $note_id,
                'links' => $links,
            )
        );
    }

    public function rest_list_claims( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' );
        $args = array(
            'post_type'      => self::CLAIM_POST_TYPE,
            'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
            'posts_per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
            'paged'          => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );
        $search = sanitize_text_field( $request->get_param( 'search' ) );
        if ( $search ) {
            $args['s'] = $search;
        }
        $project_id = absint( $request->get_param( 'project_id' ) );
        $meta_query = array();
        if ( $project_id ) {
            $meta_query[] = array(
                'key'     => self::META_CLAIM_PROJECT_IDS,
                'value'   => 'i:' . $project_id . ';',
                'compare' => 'LIKE',
            );
        }
        if ( ! $include_private ) {
            $meta_query[] = array( 'key' => self::META_CLAIM_VISIBILITY, 'value' => 'public', 'compare' => '=' );
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $data = self::get_claim_data( $post->ID, $include_private );
            if ( $data ) {
                $items[] = $data;
            }
        }
        $response = rest_ensure_response(
            array(
                'schema' => self::CLAIM_SCHEMA,
                'items'  => $items,
                'page'   => max( 1, absint( $args['paged'] ) ),
                'total'  => absint( $query->found_posts ),
                'pages'  => absint( $query->max_num_pages ),
            )
        );
        $response->header( 'X-WP-Total', absint( $query->found_posts ) );
        $response->header( 'X-WP-TotalPages', absint( $query->max_num_pages ) );
        return $response;
    }

    public function rest_get_claim( WP_REST_Request $request ) {
        $claim_id = absint( $request['id'] );
        return rest_ensure_response( self::get_claim_data( $claim_id, current_user_can( 'edit_post', $claim_id ) ) );
    }

    public function rest_create_claim( WP_REST_Request $request ) {
        return $this->rest_upsert_claim( $request, 0 );
    }

    public function rest_update_claim( WP_REST_Request $request ) {
        return $this->rest_upsert_claim( $request, absint( $request['id'] ) );
    }

    private function rest_upsert_claim( WP_REST_Request $request, $claim_id ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $old_hash = $claim_id ? (string) get_post_meta( $claim_id, self::META_CLAIM_CONTENT_HASH, true ) : '';
        $previous_status = $claim_id ? (string) get_post_meta( $claim_id, self::META_CLAIM_STATUS, true ) : '';
        if ( ! $claim_id ) {
            $claim_id = wp_insert_post(
                wp_slash(
                    array(
                        'post_type'    => self::CLAIM_POST_TYPE,
                        'post_status'  => self::rest_status( $payload['post_status'] ?? 'draft' ),
                        'post_title'   => sanitize_text_field( $payload['title'] ?? __( 'Untitled research claim', 'sustainable-catalyst-library' ) ),
                        'post_content' => wp_kses_post( $payload['statement'] ?? '' ),
                        'post_excerpt' => sanitize_textarea_field( $payload['summary'] ?? '' ),
                    )
                ),
                true
            );
            if ( is_wp_error( $claim_id ) ) {
                return $claim_id;
            }
        } else {
            $update = array( 'ID' => $claim_id );
            if ( array_key_exists( 'title', $payload ) ) {
                $update['post_title'] = sanitize_text_field( $payload['title'] );
            }
            if ( array_key_exists( 'statement', $payload ) ) {
                $update['post_content'] = wp_kses_post( $payload['statement'] );
            }
            if ( array_key_exists( 'summary', $payload ) ) {
                $update['post_excerpt'] = sanitize_textarea_field( $payload['summary'] );
            }
            if ( array_key_exists( 'post_status', $payload ) ) {
                $update['post_status'] = self::rest_status( $payload['post_status'] );
            }
            if ( count( $update ) > 1 ) {
                wp_update_post( wp_slash( $update ) );
            }
        }

        $requested_status = array_key_exists( 'status', $payload )
            ? self::allowed_value( $payload['status'], self::claim_status_options(), 'draft' )
            : ( get_post_meta( $claim_id, self::META_CLAIM_STATUS, true ) ?: 'draft' );
        if ( array_key_exists( 'visibility', $payload ) ) {
            self::update_or_delete_meta( $claim_id, self::META_CLAIM_VISIBILITY, self::allowed_value( $payload['visibility'], self::visibility_options(), 'private' ) );
        }

        $fields = array(
            'scope'        => array( self::META_CLAIM_SCOPE, 'textarea' ),
            'assumptions'  => array( self::META_CLAIM_ASSUMPTIONS, 'textarea' ),
            'limitations'  => array( self::META_CLAIM_LIMITATIONS, 'textarea' ),
            'counterclaim' => array( self::META_CLAIM_COUNTERCLAIM, 'textarea' ),
            'review_notes' => array( self::META_CLAIM_REVIEW_NOTES, 'textarea' ),
        );
        foreach ( $fields as $field => $definition ) {
            if ( array_key_exists( $field, $payload ) ) {
                self::update_or_delete_meta( $claim_id, $definition[0], self::sanitize_rest_value( $payload[ $field ], $definition[1] ) );
            }
        }
        if ( array_key_exists( 'confidence', $payload ) ) {
            update_post_meta( $claim_id, self::META_CLAIM_CONFIDENCE, max( 0, min( 100, absint( $payload['confidence'] ) ) ) );
        }
        if ( array_key_exists( 'project_ids', $payload ) ) {
            self::update_id_meta( $claim_id, self::META_CLAIM_PROJECT_IDS, self::validated_ids( $payload['project_ids'], SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, self::MAX_PROJECTS ) );
        }
        if ( ! empty( $payload['claim_type'] ) ) {
            wp_set_object_terms( $claim_id, array( sanitize_title( $payload['claim_type'] ) ), self::CLAIM_TYPE_TAXONOMY, false );
        }
        $new_hash = self::claim_content_hash( $claim_id );
        if (
            $old_hash
            && $old_hash !== $new_hash
            && 'verified' === $previous_status
            && 'verified' === $requested_status
            && empty( $payload['reverified'] )
        ) {
            $requested_status = 'review';
        }
        self::update_or_delete_meta( $claim_id, self::META_CLAIM_STATUS, $requested_status );
        update_post_meta( $claim_id, self::META_CLAIM_CONTENT_HASH, $new_hash );

        if ( in_array( $requested_status, array( 'reviewed', 'verified', 'disputed' ), true ) ) {
            update_post_meta( $claim_id, self::META_CLAIM_LAST_REVIEWED, current_time( 'mysql' ) );
            update_post_meta( $claim_id, self::META_CLAIM_LAST_REVIEWED_BY, get_current_user_id() );
        }

        self::rebuild_claim_evidence_index( $claim_id );
        return rest_ensure_response( self::get_claim_data( $claim_id, true ) );
    }

    public function rest_claim_packet( WP_REST_Request $request ) {
        $claim_id = absint( $request['id'] );
        return rest_ensure_response( self::claim_packet( $claim_id, current_user_can( 'edit_post', $claim_id ) ) );
    }

    public function rest_project_packet( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response( self::project_packet( $project_id, current_user_can( 'edit_post', $project_id ) ) );
    }

    public function rest_export_evidence( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $type = sanitize_key( $payload['type'] ?? '' );
        $id = absint( $payload['id'] ?? 0 );
        $format = sanitize_key( $payload['format'] ?? 'json' );
        $include_private = $id && current_user_can( 'edit_post', $id );

        if ( 'note' === $type ) {
            $data = self::get_evidence_data( $id, $include_private );
            $markdown = $data ? self::evidence_markdown( $data ) : '';
        } elseif ( 'claim' === $type ) {
            $data = self::claim_packet( $id, $include_private );
            $markdown = $data && ! empty( $data['claim'] ) ? self::claim_packet_markdown( $id, $include_private ) : '';
        } elseif ( 'project' === $type ) {
            $data = self::project_packet( $id, $include_private );
            $markdown = $data ? self::project_packet_markdown( $id, $include_private ) : '';
        } else {
            return new WP_Error( 'invalid_export_type', __( 'Export type must be note, claim, or project.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        if ( ! $data ) {
            return new WP_Error( 'export_not_found', __( 'The requested evidence record is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response(
            array(
                'schema'  => self::PACKET_SCHEMA,
                'type'    => $type,
                'id'      => $id,
                'format'  => in_array( $format, array( 'json', 'markdown' ), true ) ? $format : 'json',
                'data'    => 'markdown' === $format ? $markdown : $data,
                'markdown'=> $markdown,
            )
        );
    }

    public function rest_can_read_evidence( WP_REST_Request $request ) {
        $note_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $note_id ) || self::evidence_is_public( $note_id );
    }

    public function rest_can_edit_evidence( WP_REST_Request $request ) {
        $note_id = absint( $request['id'] );
        return self::NOTE_POST_TYPE === get_post_type( $note_id ) && current_user_can( 'edit_post', $note_id );
    }

    public function rest_can_read_claim( WP_REST_Request $request ) {
        $claim_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $claim_id ) || self::claim_is_public( $claim_id );
    }

    public function rest_can_edit_claim( WP_REST_Request $request ) {
        $claim_id = absint( $request['id'] );
        return self::CLAIM_POST_TYPE === get_post_type( $claim_id ) && current_user_can( 'edit_post', $claim_id );
    }

    public function rest_can_read_project_packet( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id ) || self::project_is_public( $project_id );
    }

    private static function rest_status( $value ) {
        $value = sanitize_key( $value );
        $allowed = array( 'draft', 'pending', 'private' );
        if ( current_user_can( 'publish_posts' ) ) {
            $allowed[] = 'publish';
        }
        return in_array( $value, $allowed, true ) ? $value : 'draft';
    }

    private static function sanitize_rest_value( $value, $type ) {
        switch ( $type ) {
            case 'absint':
                return absint( $value );
            case 'textarea':
                return sanitize_textarea_field( $value );
            case 'key':
                return sanitize_key( $value );
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }

    public function note_columns( $columns ) {
        return array(
            'cb'            => $columns['cb'] ?? '<input type="checkbox">',
            'title'         => __( 'Evidence Note', 'sustainable-catalyst-library' ),
            'evidence_type' => __( 'Type', 'sustainable-catalyst-library' ),
            'source'        => __( 'Source', 'sustainable-catalyst-library' ),
            'locator'       => __( 'Locator', 'sustainable-catalyst-library' ),
            'claims'        => __( 'Claims', 'sustainable-catalyst-library' ),
            'review'        => __( 'Review', 'sustainable-catalyst-library' ),
            'date'          => $columns['date'] ?? __( 'Date', 'sustainable-catalyst-library' ),
        );
    }

    public function note_column_content( $column, $post_id ) {
        $data = self::get_evidence_data( $post_id, true );
        if ( ! $data ) {
            return;
        }
        if ( 'evidence_type' === $column ) {
            echo esc_html( $data['evidence_type_label'] );
        } elseif ( 'source' === $column ) {
            echo esc_html( $data['source_title'] ?: $data['document_title'] ?: '—' );
        } elseif ( 'locator' === $column ) {
            echo esc_html( self::locator_display( $data ) ?: '—' );
        } elseif ( 'claims' === $column ) {
            echo esc_html( number_format_i18n( count( $data['claim_links'] ) ) );
        } elseif ( 'review' === $column ) {
            echo esc_html( ucfirst( str_replace( '-', ' ', $data['review_status'] ) ) );
        }
    }

    public function claim_columns( $columns ) {
        return array(
            'cb'         => $columns['cb'] ?? '<input type="checkbox">',
            'title'      => __( 'Research Claim', 'sustainable-catalyst-library' ),
            'claim_type' => __( 'Type', 'sustainable-catalyst-library' ),
            'status'     => __( 'Status', 'sustainable-catalyst-library' ),
            'confidence' => __( 'Confidence', 'sustainable-catalyst-library' ),
            'evidence'   => __( 'Evidence', 'sustainable-catalyst-library' ),
            'date'       => $columns['date'] ?? __( 'Date', 'sustainable-catalyst-library' ),
        );
    }

    public function claim_column_content( $column, $post_id ) {
        $data = self::get_claim_data( $post_id, true );
        if ( ! $data ) {
            return;
        }
        if ( 'claim_type' === $column ) {
            echo esc_html( $data['claim_type_label'] );
        } elseif ( 'status' === $column ) {
            echo esc_html( ucfirst( $data['status'] ) );
        } elseif ( 'confidence' === $column ) {
            echo esc_html( $data['confidence'] . '%' );
        } elseif ( 'evidence' === $column ) {
            echo esc_html( number_format_i18n( count( $data['evidence_ids'] ) ) );
        }
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }
        if ( self::NOTE_POST_TYPE === $screen->post_type ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Evidence text, locators, and claim relationships are independent research records. Publishing an Evidence Note does not make it public unless its visibility is Public and linked records also permit publication.', 'sustainable-catalyst-library' ) . '</p></div>';
        } elseif ( self::CLAIM_POST_TYPE === $screen->post_type ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'A claim is not treated as verified merely because evidence is linked. Review relationship type, strength, counterevidence, assumptions, limitations, and source quality.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }
}
