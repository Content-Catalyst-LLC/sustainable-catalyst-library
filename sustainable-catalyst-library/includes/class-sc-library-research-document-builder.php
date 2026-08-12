<?php
/**
 * Research Document Builder.
 *
 * Turns account-owned Citation Studio sources into portable research outputs.
 * Exports are generated server-side as real DOCX and PDF files; no external
 * document-render service is required for the personal research workflow.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Research_Document_Builder {
    public const VERSION = '4.3.23';
    public const USER_META = 'sc_library_research_documents_v4323';
    public const NONCE_ACTION = 'sc_library_research_document_builder_v4323';
    public const MAX_DOCUMENTS = 50;
    public const MAX_SOURCES = 100;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_research_document_builder', array( $this, 'shortcode' ) );

        add_action( 'wp_ajax_sc_library_v4323_list_builder_sources', array( $this, 'ajax_list_sources' ) );
        add_action( 'wp_ajax_sc_library_v4323_save_document', array( $this, 'ajax_save_document' ) );
        add_action( 'wp_ajax_sc_library_v4323_delete_document', array( $this, 'ajax_delete_document' ) );
        add_action( 'wp_ajax_sc_library_v4323_export_document', array( $this, 'ajax_export_document' ) );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-research-document-builder',
            SC_LIBRARY_URL . 'assets/css/sc-library-research-document-builder.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-research-document-builder',
            SC_LIBRARY_URL . 'assets/js/sc-library-research-document-builder.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'limit' => self::MAX_SOURCES,
                'style' => 'harvard',
            ),
            $atts,
            'sc_research_document_builder'
        );

        wp_enqueue_style( 'sc-library-research-document-builder' );
        wp_enqueue_script( 'sc-library-research-document-builder' );

        $signed_in = is_user_logged_in();
        $user_id = get_current_user_id();
        $style = sanitize_key( (string) $atts['style'] );
        $styles = class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::citation_styles() : array();
        if ( ! isset( $styles[ $style ] ) ) {
            $style = isset( $styles['harvard'] ) ? 'harvard' : (string) array_key_first( $styles );
        }
        $limit = min( self::MAX_SOURCES, max( 1, absint( $atts['limit'] ) ) );
        $sources = $signed_in ? $this->personal_sources( $user_id, array(), $limit, $style ) : array();
        $documents = $signed_in ? $this->documents( $user_id ) : array();

        wp_localize_script(
            'sc-library-research-document-builder',
            'SCLibraryResearchDocumentBuilder',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( self::NONCE_ACTION ),
                'signedIn' => $signed_in,
                'anchor' => '#research-document-builder',
                'version' => self::VERSION,
                'maxSources' => self::MAX_SOURCES,
            )
        );

        ob_start();
        ?>
        <div class="sc-research-document-builder" data-sc-research-document-builder data-version="<?php echo esc_attr( self::VERSION ); ?>">
            <?php if ( ! $signed_in ) : ?>
                <div class="sc-research-document-builder__account-note">
                    <strong><?php esc_html_e( 'Build documents from your research sources.', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Sign in with your Sustainable Catalyst / Workspace account to select My Sources, save document drafts, and download DOCX or PDF research outputs.', 'sustainable-catalyst-library' ); ?></span>
                    <a href="<?php echo esc_url( wp_login_url( $this->current_url_with_anchor() ) ); ?>"><?php esc_html_e( 'Sign in to build documents →', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php else : ?>
                <form class="sc-research-document-builder__form" data-sc-document-form>
                    <input type="hidden" name="document_id" value="" data-sc-document-id>
                    <div class="sc-research-document-builder__controls">
                        <label class="is-wide">
                            <span><?php esc_html_e( 'Document title', 'sustainable-catalyst-library' ); ?></span>
                            <input type="text" name="title" maxlength="180" required placeholder="<?php esc_attr_e( 'e.g. Climate Adaptation Research Brief', 'sustainable-catalyst-library' ); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Document type', 'sustainable-catalyst-library' ); ?></span>
                            <select name="template">
                                <?php foreach ( self::templates() as $key => $definition ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $definition['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Citation style', 'sustainable-catalyst-library' ); ?></span>
                            <select name="style">
                                <?php foreach ( $styles as $key => $definition ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $style, $key ); ?>><?php echo esc_html( $definition['name'] ?? $key ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="is-wide">
                            <span><?php esc_html_e( 'Research question / purpose', 'sustainable-catalyst-library' ); ?></span>
                            <textarea name="research_question" rows="3" maxlength="3000" placeholder="<?php esc_attr_e( 'What question is this document intended to investigate, organize, or support?', 'sustainable-catalyst-library' ); ?>"></textarea>
                        </label>
                        <label class="is-wide">
                            <span><?php esc_html_e( 'Working notes / draft analysis', 'sustainable-catalyst-library' ); ?></span>
                            <textarea name="notes" rows="6" maxlength="20000" placeholder="<?php esc_attr_e( 'Add your own synthesis, findings, outline, observations, or research notes. Sustainable Catalyst will structure this text but will not invent missing analysis.', 'sustainable-catalyst-library' ); ?>"></textarea>
                        </label>
                    </div>

                    <fieldset class="sc-research-document-builder__options">
                        <legend><?php esc_html_e( 'Output options', 'sustainable-catalyst-library' ); ?></legend>
                        <label><input type="checkbox" name="include_source_notes" value="1" checked> <?php esc_html_e( 'Include private source notes / annotations', 'sustainable-catalyst-library' ); ?></label>
                        <label><input type="checkbox" name="include_urls" value="1" checked> <?php esc_html_e( 'Include source URLs and identifiers', 'sustainable-catalyst-library' ); ?></label>
                    </fieldset>

                    <section class="sc-research-document-builder__sources" aria-labelledby="sc-document-source-heading">
                        <header>
                            <div>
                                <p class="sc-research-document-builder__eyebrow"><?php esc_html_e( 'My Sources', 'sustainable-catalyst-library' ); ?></p>
                                <h3 id="sc-document-source-heading"><?php esc_html_e( 'Choose the evidence base', 'sustainable-catalyst-library' ); ?></h3>
                            </div>
                            <div class="sc-research-document-builder__source-actions">
                                <button type="button" data-sc-document-select-all><?php esc_html_e( 'Select all', 'sustainable-catalyst-library' ); ?></button>
                                <button type="button" data-sc-document-clear-all><?php esc_html_e( 'Clear', 'sustainable-catalyst-library' ); ?></button>
                                <button type="button" data-sc-document-refresh-sources><?php esc_html_e( 'Refresh sources', 'sustainable-catalyst-library' ); ?></button>
                            </div>
                        </header>
                        <div data-sc-document-source-list>
                            <?php echo $this->render_source_picker( $sources ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </section>

                    <div class="sc-research-document-builder__actions">
                        <button type="submit" class="is-primary" data-sc-document-save><?php esc_html_e( 'Save Draft', 'sustainable-catalyst-library' ); ?></button>
                        <button type="button" data-sc-document-export="docx"><?php esc_html_e( 'Download DOCX', 'sustainable-catalyst-library' ); ?></button>
                        <button type="button" data-sc-document-export="pdf"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></button>
                        <span data-sc-document-status aria-live="polite"></span>
                    </div>
                </form>

                <section class="sc-research-document-builder__saved" aria-labelledby="sc-saved-documents-heading">
                    <div class="sc-research-document-builder__saved-heading">
                        <div>
                            <p class="sc-research-document-builder__eyebrow"><?php esc_html_e( 'My Documents', 'sustainable-catalyst-library' ); ?></p>
                            <h3 id="sc-saved-documents-heading"><?php esc_html_e( 'Saved research document drafts', 'sustainable-catalyst-library' ); ?></h3>
                        </div>
                        <button type="button" data-sc-document-new><?php esc_html_e( 'New Document', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                    <div data-sc-saved-document-list>
                        <?php echo $this->render_saved_documents( $documents ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function ajax_list_sources() {
        $this->require_user();
        $style = sanitize_key( wp_unslash( $_POST['style'] ?? 'harvard' ) );
        $sources = $this->personal_sources( get_current_user_id(), array(), self::MAX_SOURCES, $style );
        wp_send_json_success(
            array(
                'count' => count( $sources ),
                'html' => $this->render_source_picker( $sources ),
            )
        );
    }

    public function ajax_save_document() {
        $this->require_user();
        $user_id = get_current_user_id();
        $document = $this->normalize_document_payload( wp_unslash( $_POST ), $user_id );
        if ( is_wp_error( $document ) ) {
            wp_send_json_error( array( 'message' => $document->get_error_message() ), 400 );
        }
        $documents = $this->documents( $user_id );
        $now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
        $found = false;
        foreach ( $documents as &$existing ) {
            if ( ! empty( $document['id'] ) && (string) ( $existing['id'] ?? '' ) === $document['id'] ) {
                $document['created_at'] = (string) ( $existing['created_at'] ?? $now );
                $document['updated_at'] = $now;
                $existing = $document;
                $found = true;
                break;
            }
        }
        unset( $existing );
        if ( ! $found ) {
            $document['id'] = wp_generate_uuid4();
            $document['created_at'] = $now;
            $document['updated_at'] = $now;
            array_unshift( $documents, $document );
        }
        $documents = array_slice( $documents, 0, self::MAX_DOCUMENTS );
        update_user_meta( $user_id, self::USER_META, $documents );
        wp_send_json_success(
            array(
                'document' => $document,
                'html' => $this->render_saved_documents( $documents ),
                'message' => __( 'Research document draft saved.', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function ajax_delete_document() {
        $this->require_user();
        $user_id = get_current_user_id();
        $id = sanitize_text_field( wp_unslash( $_POST['document_id'] ?? '' ) );
        $documents = array_values(
            array_filter(
                $this->documents( $user_id ),
                static function ( $document ) use ( $id ) {
                    return (string) ( $document['id'] ?? '' ) !== $id;
                }
            )
        );
        update_user_meta( $user_id, self::USER_META, $documents );
        wp_send_json_success(
            array(
                'html' => $this->render_saved_documents( $documents ),
                'message' => __( 'Research document draft removed.', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function ajax_export_document() {
        $this->require_user();
        $user_id = get_current_user_id();
        $format = sanitize_key( wp_unslash( $_POST['format'] ?? '' ) );
        if ( ! in_array( $format, array( 'docx', 'pdf' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Unsupported document format.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $document = $this->normalize_document_payload( wp_unslash( $_POST ), $user_id );
        if ( is_wp_error( $document ) ) {
            wp_send_json_error( array( 'message' => $document->get_error_message() ), 400 );
        }
        $sources = $this->personal_sources( $user_id, $document['source_ids'], self::MAX_SOURCES, $document['style'] );
        $model = self::document_model( $document, $sources );
        try {
            $binary = 'docx' === $format ? self::build_docx_binary( $model ) : self::build_pdf_binary( $model );
        } catch ( Throwable $error ) {
            wp_send_json_error( array( 'message' => __( 'The document could not be generated.', 'sustainable-catalyst-library' ) . ' ' . $error->getMessage() ), 500 );
        }
        if ( '' === $binary ) {
            wp_send_json_error( array( 'message' => __( 'The generated document was empty.', 'sustainable-catalyst-library' ) ), 500 );
        }
        $slug = sanitize_file_name( sanitize_title( $document['title'] ) ?: 'sustainable-catalyst-research-document' );
        $filename = $slug . '.' . $format;
        $mime = 'docx' === $format ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf';

        while ( ob_get_level() ) {
            ob_end_clean();
        }
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $binary ) );
        header( 'X-Sustainable-Catalyst-Document-Version: ' . self::VERSION );
        header( 'X-Sustainable-Catalyst-SHA256: ' . hash( 'sha256', $binary ) );
        echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function require_user() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sign in to manage research documents.', 'sustainable-catalyst-library' ) ), 401 );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
    }

    private function normalize_document_payload( $payload, $user_id ) {
        $title = sanitize_text_field( $payload['title'] ?? '' );
        if ( '' === $title ) {
            return new WP_Error( 'sc_document_title', __( 'Enter a document title.', 'sustainable-catalyst-library' ) );
        }
        $template = sanitize_key( $payload['template'] ?? 'reading-list' );
        if ( ! isset( self::templates()[ $template ] ) ) {
            $template = 'reading-list';
        }
        $style = sanitize_key( $payload['style'] ?? 'harvard' );
        $styles = class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::citation_styles() : array( 'harvard' => array() );
        if ( ! isset( $styles[ $style ] ) ) {
            $style = 'harvard';
        }
        $source_ids = $this->parse_source_ids( $payload['source_ids'] ?? '' );
        $owned = $this->owned_source_ids( $user_id );
        $source_ids = array_values( array_intersect( $source_ids, $owned ) );
        if ( count( $source_ids ) > self::MAX_SOURCES ) {
            $source_ids = array_slice( $source_ids, 0, self::MAX_SOURCES );
        }
        return array(
            'id' => sanitize_text_field( $payload['document_id'] ?? $payload['id'] ?? '' ),
            'title' => $title,
            'template' => $template,
            'research_question' => sanitize_textarea_field( $payload['research_question'] ?? '' ),
            'notes' => sanitize_textarea_field( $payload['notes'] ?? '' ),
            'style' => $style,
            'source_ids' => $source_ids,
            'include_source_notes' => ! empty( $payload['include_source_notes'] ),
            'include_urls' => ! empty( $payload['include_urls'] ),
        );
    }

    private function parse_source_ids( $value ) {
        if ( is_array( $value ) ) {
            $parts = $value;
        } else {
            $parts = preg_split( '/[\s,]+/', (string) $value );
        }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $parts ) ) ) );
        return $ids;
    }

    private function owned_source_ids( $user_id ) {
        if ( ! class_exists( 'SC_Library_Citation_Studio' ) || ! class_exists( 'SC_Library_Citation_Source_Manager' ) ) {
            return array();
        }
        return array_map(
            'absint',
            get_posts(
                array(
                    'post_type' => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                    'post_status' => array( 'draft', 'private', 'publish' ),
                    'posts_per_page' => self::MAX_SOURCES,
                    'fields' => 'ids',
                    'orderby' => 'modified',
                    'order' => 'DESC',
                    'meta_key' => SC_Library_Citation_Studio::META_OWNER,
                    'meta_value' => absint( $user_id ),
                    'no_found_rows' => true,
                )
            )
        );
    }

    private function personal_sources( $user_id, $requested_ids = array(), $limit = self::MAX_SOURCES, $style = 'harvard' ) {
        if ( ! class_exists( 'SC_Library_Citation_Source_Manager' ) || ! class_exists( 'SC_Library_Citation_Studio' ) ) {
            return array();
        }
        $owned_ids = $this->owned_source_ids( $user_id );
        if ( $requested_ids ) {
            $requested_ids = array_values( array_intersect( array_map( 'absint', $requested_ids ), $owned_ids ) );
            $ordered = array();
            foreach ( $requested_ids as $id ) {
                $ordered[ $id ] = true;
            }
            $ids = array_keys( $ordered );
        } else {
            $ids = $owned_ids;
        }
        $ids = array_slice( $ids, 0, max( 1, min( self::MAX_SOURCES, absint( $limit ) ) ) );
        $sources = array();
        foreach ( $ids as $source_id ) {
            $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, true );
            if ( ! $data ) {
                continue;
            }
            $data['citation'] = SC_Library_Citation_Source_Manager::format_citation( $source_id, $style, 'reference' );
            $data['in_text_citation'] = SC_Library_Citation_Source_Manager::format_citation( $source_id, $style, 'in-text' );
            $data['private_notes'] = (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_NOTES, true );
            $data['personal_collection'] = (string) get_post_meta( $source_id, SC_Library_Citation_Studio::META_COLLECTION, true );
            $sources[] = $data;
        }
        return $sources;
    }

    private function render_source_picker( $sources ) {
        if ( ! $sources ) {
            return '<div class="sc-research-document-builder__empty"><strong>' . esc_html__( 'No saved sources yet.', 'sustainable-catalyst-library' ) . '</strong><span>' . esc_html__( 'Save sources in Research Access or Citation Studio, then refresh this list.', 'sustainable-catalyst-library' ) . '</span></div>';
        }
        $html = '<div class="sc-research-document-builder__source-grid">';
        foreach ( $sources as $source ) {
            $id = absint( $source['id'] ?? 0 );
            $citation = (string) ( $source['citation'] ?? $source['title'] ?? '' );
            $collection = (string) ( $source['personal_collection'] ?? '' );
            $html .= '<label class="sc-research-document-builder__source" data-sc-document-source-card="' . esc_attr( $id ) . '">';
            $html .= '<input type="checkbox" name="source_id" value="' . esc_attr( $id ) . '" data-sc-document-source-checkbox>';
            $html .= '<span class="sc-research-document-builder__source-copy"><strong>' . esc_html( $source['title'] ?? '' ) . '</strong>';
            if ( $collection ) {
                $html .= '<span class="sc-research-document-builder__source-collection">' . esc_html( $collection ) . '</span>';
            }
            $html .= '<small>' . esc_html( $citation ) . '</small></span></label>';
        }
        return $html . '</div>';
    }

    private function render_saved_documents( $documents ) {
        if ( ! $documents ) {
            return '<div class="sc-research-document-builder__empty"><strong>' . esc_html__( 'No saved document drafts yet.', 'sustainable-catalyst-library' ) . '</strong><span>' . esc_html__( 'Build a document above and save it to return to the same source selection and notes later.', 'sustainable-catalyst-library' ) . '</span></div>';
        }
        $html = '<div class="sc-research-document-builder__saved-grid">';
        foreach ( $documents as $document ) {
            $template = self::templates()[ $document['template'] ?? 'reading-list' ] ?? self::templates()['reading-list'];
            $json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $html .= '<article class="sc-research-document-builder__saved-card">';
            $html .= '<span>' . esc_html( $template['label'] ) . '</span><h4>' . esc_html( $document['title'] ?? '' ) . '</h4>';
            $html .= '<p>' . esc_html( sprintf( _n( '%d source', '%d sources', count( $document['source_ids'] ?? array() ), 'sustainable-catalyst-library' ), count( $document['source_ids'] ?? array() ) ) ) . ' · ' . esc_html( strtoupper( $document['style'] ?? 'harvard' ) ) . '</p>';
            if ( ! empty( $document['updated_at'] ) ) {
                $html .= '<small>' . esc_html( sprintf( __( 'Updated %s', 'sustainable-catalyst-library' ), mysql2date( get_option( 'date_format' ), $document['updated_at'] ) ) ) . '</small>';
            }
            $html .= '<div><button type="button" data-sc-document-load="' . esc_attr( base64_encode( $json ) ) . '">' . esc_html__( 'Open Draft', 'sustainable-catalyst-library' ) . '</button><button type="button" class="is-danger" data-sc-document-delete="' . esc_attr( $document['id'] ?? '' ) . '">' . esc_html__( 'Delete', 'sustainable-catalyst-library' ) . '</button></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    private function documents( $user_id ) {
        $documents = get_user_meta( $user_id, self::USER_META, true );
        if ( ! is_array( $documents ) ) {
            return array();
        }
        $clean = array();
        foreach ( array_slice( $documents, 0, self::MAX_DOCUMENTS ) as $document ) {
            if ( ! is_array( $document ) || empty( $document['id'] ) || empty( $document['title'] ) ) {
                continue;
            }
            $document['source_ids'] = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $document['source_ids'] ?? array() ) ) ) ) ), 0, self::MAX_SOURCES );
            $clean[] = $document;
        }
        return $clean;
    }

    public static function templates() {
        return array(
            'reading-list' => array( 'label' => __( 'Reading List', 'sustainable-catalyst-library' ), 'description' => __( 'A clean, source-first reading list with citation metadata.', 'sustainable-catalyst-library' ) ),
            'annotated-bibliography' => array( 'label' => __( 'Annotated Bibliography', 'sustainable-catalyst-library' ), 'description' => __( 'Citations paired with your private source annotations.', 'sustainable-catalyst-library' ) ),
            'literature-review-packet' => array( 'label' => __( 'Literature Review Packet', 'sustainable-catalyst-library' ), 'description' => __( 'Research question, working synthesis notes, and a source dossier.', 'sustainable-catalyst-library' ) ),
            'research-brief' => array( 'label' => __( 'Research Brief', 'sustainable-catalyst-library' ), 'description' => __( 'A concise research purpose, draft analysis, and evidence base.', 'sustainable-catalyst-library' ) ),
            'evidence-packet' => array( 'label' => __( 'Evidence Packet', 'sustainable-catalyst-library' ), 'description' => __( 'Evidence notes, source provenance, identifiers, and citations.', 'sustainable-catalyst-library' ) ),
            'research-notes' => array( 'label' => __( 'Research Notes', 'sustainable-catalyst-library' ), 'description' => __( 'Working notes with a source-aware reference section.', 'sustainable-catalyst-library' ) ),
        );
    }

    /**
     * Build a renderer-neutral document model from a normalized draft and sources.
     * This method intentionally does not invent analysis or annotations.
     */
    public static function document_model( $document, $sources ) {
        $templates = self::templates();
        $template_key = (string) ( $document['template'] ?? 'reading-list' );
        $template = $templates[ $template_key ] ?? $templates['reading-list'];
        $title = trim( (string) ( $document['title'] ?? 'Research Document' ) );
        $question = trim( (string) ( $document['research_question'] ?? '' ) );
        $notes = trim( (string) ( $document['notes'] ?? '' ) );
        $include_source_notes = ! empty( $document['include_source_notes'] );
        $include_urls = ! empty( $document['include_urls'] );
        $style = (string) ( $document['style'] ?? 'harvard' );
        $blocks = array();

        $blocks[] = array( 'type' => 'eyebrow', 'text' => 'SUSTAINABLE CATALYST · RESEARCH LIBRARY' );
        $blocks[] = array( 'type' => 'title', 'text' => $title );
        $blocks[] = array( 'type' => 'subtitle', 'text' => $template['label'] . ' · ' . strtoupper( $style ) );
        $display_date = function_exists( 'wp_date' ) ? wp_date( 'F j, Y' ) : gmdate( 'F j, Y' );
        $generated_at = function_exists( 'wp_date' ) ? wp_date( 'c' ) : gmdate( 'c' );
        $blocks[] = array( 'type' => 'meta', 'text' => 'Generated ' . $display_date . ' · ' . count( $sources ) . ( 1 === count( $sources ) ? ' source' : ' sources' ) );
        $blocks[] = array( 'type' => 'rule', 'text' => '' );

        if ( $question ) {
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Research Question / Purpose' );
            $blocks[] = array( 'type' => 'paragraph', 'text' => $question );
        }

        if ( 'reading-list' === $template_key ) {
            if ( $notes ) {
                $blocks[] = array( 'type' => 'heading1', 'text' => 'Reading Notes' );
                $blocks[] = array( 'type' => 'paragraph', 'text' => $notes );
            }
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Reading List' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, false, $include_urls, false ) );
            }
        } elseif ( 'annotated-bibliography' === $template_key ) {
            if ( $notes ) {
                $blocks[] = array( 'type' => 'heading1', 'text' => 'Scope / Working Notes' );
                $blocks[] = array( 'type' => 'paragraph', 'text' => $notes );
            }
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Annotated Bibliography' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, $include_source_notes, $include_urls, true ) );
            }
        } elseif ( 'literature-review-packet' === $template_key ) {
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Working Synthesis Notes' );
            $blocks[] = array( 'type' => 'paragraph', 'text' => $notes ?: 'No synthesis notes supplied.' );
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Source Dossier' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, $include_source_notes, $include_urls, true ) );
            }
        } elseif ( 'research-brief' === $template_key ) {
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Draft Analysis / Findings' );
            $blocks[] = array( 'type' => 'paragraph', 'text' => $notes ?: 'No draft analysis supplied.' );
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Evidence Base' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, false, $include_urls, false ) );
            }
        } elseif ( 'evidence-packet' === $template_key ) {
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Evidence Notes' );
            $blocks[] = array( 'type' => 'paragraph', 'text' => $notes ?: 'No evidence notes supplied.' );
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Evidence Sources' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, $include_source_notes, $include_urls, true ) );
            }
        } else {
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Working Notes' );
            $blocks[] = array( 'type' => 'paragraph', 'text' => $notes ?: 'No working notes supplied.' );
            $blocks[] = array( 'type' => 'heading1', 'text' => 'Sources' );
            foreach ( $sources as $source ) {
                $blocks = array_merge( $blocks, self::source_blocks( $source, $include_source_notes, $include_urls, false ) );
            }
        }

        if ( ! $sources ) {
            $blocks[] = array( 'type' => 'note', 'text' => 'No sources were selected for this export.' );
        }

        $blocks[] = array( 'type' => 'rule', 'text' => '' );
        $blocks[] = array( 'type' => 'note', 'text' => 'This document structures user-selected research materials and user-supplied notes. It does not substitute generated claims for missing analysis.' );

        return array(
            'schema' => 'sc-research-document/1.0',
            'version' => self::VERSION,
            'title' => $title,
            'template' => $template_key,
            'template_label' => $template['label'],
            'style' => $style,
            'generated_at' => $generated_at,
            'source_count' => count( $sources ),
            'blocks' => $blocks,
        );
    }

    private static function source_blocks( $source, $include_note, $include_urls, $heading ) {
        $blocks = array();
        $title = trim( (string) ( $source['title'] ?? '' ) );
        $citation = trim( (string) ( $source['citation'] ?? $title ) );
        if ( $heading && $title ) {
            $blocks[] = array( 'type' => 'heading2', 'text' => $title );
        }
        $blocks[] = array( 'type' => 'citation', 'text' => $citation );
        if ( $include_note && ! empty( $source['private_notes'] ) ) {
            $blocks[] = array( 'type' => 'paragraph', 'text' => trim( (string) $source['private_notes'] ) );
        }
        if ( $include_urls ) {
            $details = array();
            if ( ! empty( $source['doi'] ) ) {
                $details[] = 'DOI: ' . trim( (string) $source['doi'] );
            }
            if ( ! empty( $source['isbn'] ) ) {
                $details[] = 'ISBN: ' . trim( (string) $source['isbn'] );
            }
            if ( ! empty( $source['pmid'] ) ) {
                $details[] = 'PMID: ' . trim( (string) $source['pmid'] );
            }
            if ( ! empty( $source['url'] ) ) {
                $details[] = 'URL: ' . trim( (string) $source['url'] );
            }
            if ( $details ) {
                $blocks[] = array( 'type' => 'source-meta', 'text' => implode( ' · ', $details ) );
            }
        }
        return $blocks;
    }

    /**
     * Generate a standards-based DOCX package. Uses ZipArchive when available,
     * with PharData as a dependency-free PHP fallback.
     */
    public static function build_docx_binary( $model ) {
        $title = (string) ( $model['title'] ?? 'Research Document' );
        $document_xml = self::docx_document_xml( (array) ( $model['blocks'] ?? array() ) );
        $files = array(
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'word/styles.xml' => self::docx_styles_xml(),
            'word/document.xml' => $document_xml,
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . self::xml_text( $title ) . '</dc:title><dc:creator>Sustainable Catalyst</dc:creator><cp:lastModifiedBy>Sustainable Catalyst</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</dcterms:modified></cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Sustainable Catalyst Research Library</Application><AppVersion>4.3</AppVersion></Properties>',
        );
        return self::zip_binary( $files );
    }

    private static function docx_document_xml( $blocks ) {
        $body = '';
        foreach ( $blocks as $block ) {
            $type = (string) ( $block['type'] ?? 'paragraph' );
            $text = (string) ( $block['text'] ?? '' );
            if ( 'rule' === $type ) {
                $body .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="8" w:space="8" w:color="D00000"/></w:pBdr><w:spacing w:after="180"/></w:pPr></w:p>';
                continue;
            }
            $style = array(
                'eyebrow' => 'Eyebrow', 'title' => 'Title', 'subtitle' => 'Subtitle', 'meta' => 'Meta',
                'heading1' => 'Heading1', 'heading2' => 'Heading2', 'citation' => 'Citation',
                'source-meta' => 'SourceMeta', 'note' => 'Note',
            )[ $type ] ?? 'Normal';
            $lines = preg_split( '/\R/u', $text );
            foreach ( (array) $lines as $line ) {
                $body .= '<w:p><w:pPr><w:pStyle w:val="' . self::xml_text( $style ) . '"/></w:pPr><w:r><w:t xml:space="preserve">' . self::xml_text( $line ) . '</w:t></w:r></w:p>';
            }
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr></w:body></w:document>';
    }

    private static function docx_styles_xml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="1A1A1A"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="140"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Eyebrow"><w:name w:val="Eyebrow"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="80"/></w:pPr><w:rPr><w:b/><w:sz w:val="17"/><w:color w:val="5A5A5A"/><w:caps/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Subtitle"/><w:qFormat/><w:pPr><w:spacing w:before="40" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="38"/><w:szCs w:val="38"/><w:color w:val="000000"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="70"/></w:pPr><w:rPr><w:sz w:val="22"/><w:color w:val="4A4A4A"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Meta"><w:name w:val="Meta"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="140"/></w:pPr><w:rPr><w:sz w:val="18"/><w:color w:val="6A6A6A"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="280" w:after="100"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:sz w:val="27"/><w:color w:val="000000"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="200" w:after="70"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:sz w:val="23"/><w:color w:val="161616"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Citation"><w:name w:val="Citation"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="80"/><w:ind w:left="360" w:hanging="360"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="SourceMeta"><w:name w:val="Source Meta"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="120"/></w:pPr><w:rPr><w:sz w:val="17"/><w:color w:val="666666"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Note"><w:name w:val="Note"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="100" w:after="120"/></w:pPr><w:rPr><w:i/><w:sz w:val="18"/><w:color w:val="595959"/></w:rPr></w:style>
</w:styles>';
    }

    private static function zip_binary( $files ) {
        $base = tempnam( sys_get_temp_dir(), 'scdocx-' );
        if ( false === $base ) {
            throw new RuntimeException( 'Could not allocate a temporary DOCX file.' );
        }
        @unlink( $base );
        $path = $base . '.zip';
        try {
            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                    throw new RuntimeException( 'Could not create DOCX ZIP package.' );
                }
                foreach ( $files as $name => $content ) {
                    $zip->addFromString( $name, $content );
                }
                $zip->close();
            } elseif ( class_exists( 'PharData' ) ) {
                $archive = new PharData( $path, 0, null, Phar::ZIP );
                foreach ( $files as $name => $content ) {
                    $archive[ $name ] = $content;
                }
                unset( $archive );
            } else {
                throw new RuntimeException( 'PHP ZIP support is unavailable.' );
            }
            $binary = file_get_contents( $path );
            if ( false === $binary ) {
                throw new RuntimeException( 'Could not read the generated DOCX package.' );
            }
            return $binary;
        } finally {
            @unlink( $path );
        }
    }

    /**
     * Generate a compact, dependency-free PDF with Helvetica text.
     */
    public static function build_pdf_binary( $model ) {
        $blocks = (array) ( $model['blocks'] ?? array() );
        $pages = array();
        $page = array();
        $y = 738.0;
        $left = 54.0;
        $right = 558.0;
        $bottom = 54.0;

        $flush_page = static function () use ( &$pages, &$page, &$y ) {
            if ( $page ) {
                $pages[] = $page;
            }
            $page = array();
            $y = 738.0;
        };

        foreach ( $blocks as $block ) {
            $type = (string) ( $block['type'] ?? 'paragraph' );
            $text = (string) ( $block['text'] ?? '' );
            if ( 'rule' === $type ) {
                if ( $y < $bottom + 24 ) {
                    $flush_page();
                }
                $page[] = array( 'kind' => 'rule', 'y' => $y );
                $y -= 22;
                continue;
            }
            $spec = self::pdf_style( $type );
            $lines = self::pdf_wrap( $text, $spec['size'], $right - $left, $spec['max_chars'] );
            $needed = max( 1, count( $lines ) ) * $spec['leading'] + $spec['after'];
            if ( $y - $needed < $bottom && $page ) {
                $flush_page();
            }
            foreach ( $lines as $line ) {
                if ( $y < $bottom + $spec['leading'] ) {
                    $flush_page();
                }
                $page[] = array(
                    'kind' => 'text',
                    'font' => $spec['font'],
                    'size' => $spec['size'],
                    'x' => $left + $spec['indent'],
                    'y' => $y,
                    'text' => $line,
                    'gray' => $spec['gray'],
                );
                $y -= $spec['leading'];
            }
            $y -= $spec['after'];
        }
        if ( $page || ! $pages ) {
            $pages[] = $page;
        }

        $objects = array();
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $page_ids = array();
        $next = 5;
        foreach ( $pages as $index => $commands ) {
            $page_id = $next++;
            $content_id = $next++;
            $page_ids[] = $page_id;
            $stream = self::pdf_page_stream( $commands, $index + 1, count( $pages ) );
            $objects[ $content_id ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $content_id . ' 0 R >>';
        }
        $kids = implode( ' ', array_map( static fn( $id ) => $id . ' 0 R', $page_ids ) );
        $objects[2] = '<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count( $page_ids ) . ' >>';
        ksort( $objects );

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array( 0 => 0 );
        foreach ( $objects as $id => $body ) {
            $offsets[ $id ] = strlen( $pdf );
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen( $pdf );
        $max = max( array_keys( $objects ) );
        $pdf .= "xref\n0 " . ( $max + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ( $id = 1; $id <= $max; $id++ ) {
            $offset = $offsets[ $id ] ?? 0;
            $pdf .= sprintf( "%010d 00000 n \n", $offset );
        }
        $pdf .= 'trailer << /Size ' . ( $max + 1 ) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    private static function pdf_style( $type ) {
        $styles = array(
            'eyebrow' => array( 'font' => 'F2', 'size' => 8.5, 'leading' => 12.0, 'after' => 4.0, 'indent' => 0.0, 'gray' => 0.36, 'max_chars' => 95 ),
            'title' => array( 'font' => 'F2', 'size' => 21.0, 'leading' => 26.0, 'after' => 7.0, 'indent' => 0.0, 'gray' => 0.0, 'max_chars' => 48 ),
            'subtitle' => array( 'font' => 'F1', 'size' => 11.0, 'leading' => 15.0, 'after' => 4.0, 'indent' => 0.0, 'gray' => 0.28, 'max_chars' => 78 ),
            'meta' => array( 'font' => 'F1', 'size' => 8.5, 'leading' => 12.0, 'after' => 7.0, 'indent' => 0.0, 'gray' => 0.42, 'max_chars' => 95 ),
            'heading1' => array( 'font' => 'F2', 'size' => 13.5, 'leading' => 18.0, 'after' => 6.0, 'indent' => 0.0, 'gray' => 0.0, 'max_chars' => 70 ),
            'heading2' => array( 'font' => 'F2', 'size' => 11.0, 'leading' => 15.0, 'after' => 4.0, 'indent' => 0.0, 'gray' => 0.05, 'max_chars' => 82 ),
            'citation' => array( 'font' => 'F1', 'size' => 9.3, 'leading' => 13.0, 'after' => 5.0, 'indent' => 12.0, 'gray' => 0.08, 'max_chars' => 96 ),
            'source-meta' => array( 'font' => 'F1', 'size' => 7.8, 'leading' => 11.0, 'after' => 6.0, 'indent' => 12.0, 'gray' => 0.42, 'max_chars' => 110 ),
            'note' => array( 'font' => 'F1', 'size' => 8.4, 'leading' => 12.0, 'after' => 6.0, 'indent' => 0.0, 'gray' => 0.38, 'max_chars' => 103 ),
            'paragraph' => array( 'font' => 'F1', 'size' => 10.2, 'leading' => 14.5, 'after' => 8.0, 'indent' => 0.0, 'gray' => 0.08, 'max_chars' => 94 ),
        );
        return $styles[ $type ] ?? $styles['paragraph'];
    }

    private static function pdf_wrap( $text, $font_size, $width, $fallback_chars ) {
        $text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
        if ( '' === $text ) {
            return array( '' );
        }
        $approx = max( 20, min( 140, (int) floor( $width / max( 4.0, $font_size * 0.50 ) ) ) );
        $limit = min( $fallback_chars, $approx );
        $words = preg_split( '/\s+/u', $text );
        $lines = array();
        $line = '';
        foreach ( (array) $words as $word ) {
            $candidate = '' === $line ? $word : $line . ' ' . $word;
            if ( strlen( self::pdf_encode( $candidate ) ) > $limit && '' !== $line ) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ( '' !== $line ) {
            $lines[] = $line;
        }
        return $lines ?: array( '' );
    }

    private static function pdf_page_stream( $commands, $page_number, $page_count ) {
        $stream = "q\n";
        foreach ( $commands as $command ) {
            if ( 'rule' === $command['kind'] ) {
                $y = (float) $command['y'];
                $stream .= "1 0 0 RG 1 w 54 " . self::pdf_num( $y ) . " m 558 " . self::pdf_num( $y ) . " l S\n";
                continue;
            }
            $gray = max( 0.0, min( 1.0, (float) $command['gray'] ) );
            $stream .= self::pdf_num( $gray ) . " g\nBT\n/" . $command['font'] . ' ' . self::pdf_num( $command['size'] ) . " Tf\n";
            $stream .= '1 0 0 1 ' . self::pdf_num( $command['x'] ) . ' ' . self::pdf_num( $command['y'] ) . " Tm\n";
            $stream .= '(' . self::pdf_escape( $command['text'] ) . ") Tj\nET\n";
        }
        $footer = 'Sustainable Catalyst Research Library - Page ' . $page_number . ' of ' . $page_count;
        $stream .= "0.45 g\nBT\n/F1 7.5 Tf\n1 0 0 1 54 30 Tm\n(" . self::pdf_escape( $footer ) . ") Tj\nET\nQ";
        return $stream;
    }

    private static function pdf_encode( $text ) {
        $text = str_replace( array( "\xE2\x80\x94", "\xE2\x80\x93", "\xE2\x86\x92", "\xC2\xB7" ), array( '-', '-', '->', '-' ), (string) $text );
        if ( function_exists( 'iconv' ) ) {
            $encoded = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
            if ( false !== $encoded ) {
                return $encoded;
            }
        }
        return preg_replace( '/[^\x20-\x7E]/', '?', $text );
    }

    private static function pdf_escape( $text ) {
        return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', ' ' ), self::pdf_encode( $text ) );
    }

    private static function pdf_num( $value ) {
        $formatted = number_format( (float) $value, 2, '.', '' );
        return rtrim( rtrim( $formatted, '0' ), '.' );
    }

    private static function xml_text( $text ) {
        return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private function current_url_with_anchor() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        $uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
        return esc_url_raw( $scheme . $host . preg_replace( '/#.*$/', '', $uri ) . '#research-document-builder' );
    }
}
