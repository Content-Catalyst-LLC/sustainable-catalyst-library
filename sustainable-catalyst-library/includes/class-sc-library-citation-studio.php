<?php
/**
 * Citation Studio & personal Source Manager.
 *
 * Account-owned research sources, multi-style citation previews, collections,
 * notes, and interoperable BibTeX / RIS / CSL-JSON export.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Citation_Studio {
    public const VERSION = '4.3.22';
    public const META_OWNER = '_sc_source_personal_owner';
    public const META_COLLECTION = '_sc_source_personal_collection';
    public const USER_COLLECTIONS = 'sc_library_source_collections_v4322';
    public const NONCE_ACTION = 'sc_library_citation_studio_v4322';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_citation_studio', array( $this, 'shortcode' ) );

        add_filter( 'sc_library_citation_styles', array( $this, 'citation_styles' ) );
        add_filter( 'sc_library_format_citation', array( $this, 'format_citation' ), 20, 5 );
        add_filter( 'sc_library_source_data', array( $this, 'source_data' ), 20, 3 );

        add_action( 'wp_ajax_sc_library_v4322_list_sources', array( $this, 'ajax_list_sources' ) );
        add_action( 'wp_ajax_sc_library_v4322_create_source', array( $this, 'ajax_create_source' ) );
        add_action( 'wp_ajax_sc_library_v4322_update_source', array( $this, 'ajax_update_source' ) );
        add_action( 'wp_ajax_sc_library_v4322_delete_source', array( $this, 'ajax_delete_source' ) );
        add_action( 'wp_ajax_sc_library_v4322_create_collection', array( $this, 'ajax_create_collection' ) );
        add_action( 'wp_ajax_sc_library_v4322_export_sources', array( $this, 'ajax_export_sources' ) );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-citation-studio',
            SC_LIBRARY_URL . 'assets/css/sc-library-citation-studio.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-citation-studio',
            SC_LIBRARY_URL . 'assets/js/sc-library-citation-studio.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public function citation_styles( $styles ) {
        $styles = is_array( $styles ) ? $styles : array();
        $profiles = array(
            'apa-7' => array( 'name' => 'APA 7', 'description' => 'APA seventh-edition author-date profile.' ),
            'mla-9' => array( 'name' => 'MLA 9', 'description' => 'MLA ninth-edition works-cited profile.' ),
            'chicago-author-date' => array( 'name' => 'Chicago — Author-Date', 'description' => 'Chicago author-date reference profile.' ),
            'chicago-notes-bibliography' => array( 'name' => 'Chicago — Notes & Bibliography', 'description' => 'Chicago notes-and-bibliography reference profile.' ),
            'ieee' => array( 'name' => 'IEEE', 'description' => 'IEEE reference profile; final numbering is assigned in the document bibliography.' ),
            'vancouver' => array( 'name' => 'Vancouver', 'description' => 'Vancouver biomedical reference profile; final numbering is assigned in the document bibliography.' ),
            'ama' => array( 'name' => 'AMA', 'description' => 'AMA reference profile; final numbering is assigned in the document bibliography.' ),
            'acs-author-date' => array( 'name' => 'ACS — Author-Date', 'description' => 'ACS author-date chemistry reference profile.' ),
        );
        foreach ( $profiles as $id => $profile ) {
            $styles[ $id ] = array(
                'id'          => $id,
                'name'        => __( $profile['name'], 'sustainable-catalyst-library' ),
                'schema'      => SC_Library_Citation_Source_Manager::STYLE_SCHEMA,
                'description' => __( $profile['description'], 'sustainable-catalyst-library' ),
            );
        }
        return $styles;
    }

    public function format_citation( $citation, $data, $style, $mode, $locator ) {
        if ( 'harvard' === $style || ! is_array( $data ) ) {
            return $citation;
        }
        $style = sanitize_key( $style );
        $mode = sanitize_key( $mode );
        if ( in_array( $mode, array( 'in-text', 'intext', 'author-date' ), true ) ) {
            return $this->format_in_text( $data, $style, $locator );
        }
        if ( 'citation-key' === $mode ) {
            return (string) ( $data['citation_key'] ?? '' );
        }
        $plain = $this->format_reference( $data, $style );
        return 'reference-html' === $mode ? esc_html( $plain ) : $plain;
    }

    public function source_data( $data, $source_id, $include_private ) {
        $owner = absint( get_post_meta( $source_id, self::META_OWNER, true ) );
        if ( $owner ) {
            $data['personal_owner'] = $owner;
            $data['personal_collection'] = (string) get_post_meta( $source_id, self::META_COLLECTION, true );
            if ( $include_private && get_current_user_id() === $owner ) {
                $data['private_notes'] = (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_NOTES, true );
            }
        }
        return $data;
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'limit' => 100,
                'style' => 'harvard',
            ),
            $atts,
            'sc_citation_studio'
        );
        wp_enqueue_style( 'sc-library-citation-studio' );
        wp_enqueue_script( 'sc-library-citation-studio' );

        $signed_in = is_user_logged_in();
        $style = sanitize_key( $atts['style'] ?: 'harvard' );
        $styles = SC_Library_Citation_Source_Manager::citation_styles();
        if ( ! isset( $styles[ $style ] ) ) {
            $style = 'harvard';
        }
        $sources = $signed_in ? $this->personal_sources( get_current_user_id(), '', min( 250, max( 1, absint( $atts['limit'] ) ) ) ) : array();
        $collections = $signed_in ? $this->collections( get_current_user_id() ) : array();

        wp_localize_script(
            'sc-library-citation-studio',
            'SCLibraryCitationStudio',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
                'signedIn'     => $signed_in,
                'defaultStyle' => $style,
                'styles'       => array_values( $styles ),
                'studioAnchor' => '#citation-studio',
            )
        );

        ob_start();
        ?>
        <div class="sc-citation-studio" data-sc-citation-studio data-default-style="<?php echo esc_attr( $style ); ?>">
            <?php if ( ! $signed_in ) : ?>
                <div class="sc-citation-studio__account-note">
                    <strong><?php esc_html_e( 'Public discovery stays open.', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Sign in with your Sustainable Catalyst / Workspace account to save private sources, notes, collections, citations, and exports.', 'sustainable-catalyst-library' ); ?></span>
                    <a href="<?php echo esc_url( wp_login_url( $this->current_url_with_anchor() ) ); ?>"><?php esc_html_e( 'Sign in to manage sources →', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php else : ?>
                <header class="sc-citation-studio__toolbar">
                    <div>
                        <p class="sc-citation-studio__eyebrow"><?php esc_html_e( 'My Sources', 'sustainable-catalyst-library' ); ?></p>
                        <strong data-sc-source-count><?php echo esc_html( number_format_i18n( count( $sources ) ) ); ?></strong>
                        <span><?php esc_html_e( 'private research sources', 'sustainable-catalyst-library' ); ?></span>
                    </div>
                    <label>
                        <span><?php esc_html_e( 'Citation style', 'sustainable-catalyst-library' ); ?></span>
                        <select data-sc-citation-style>
                            <?php foreach ( $styles as $id => $profile ) : ?>
                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $style, $id ); ?>><?php echo esc_html( $profile['name'] ?? $id ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></span>
                        <select data-sc-source-collection-filter>
                            <option value=""><?php esc_html_e( 'All sources', 'sustainable-catalyst-library' ); ?></option>
                            <?php foreach ( $collections as $collection ) : ?>
                                <option value="<?php echo esc_attr( $collection ); ?>"><?php echo esc_html( $collection ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sc-citation-studio__search">
                        <span><?php esc_html_e( 'Find source', 'sustainable-catalyst-library' ); ?></span>
                        <input type="search" placeholder="<?php esc_attr_e( 'Title, author, DOI, ISBN…', 'sustainable-catalyst-library' ); ?>" data-sc-source-search>
                    </label>
                </header>

                <div class="sc-citation-studio__actions">
                    <details class="sc-citation-studio__add">
                        <summary><?php esc_html_e( 'Add a source manually', 'sustainable-catalyst-library' ); ?></summary>
                        <?php echo $this->source_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </details>
                    <details class="sc-citation-studio__collection-create">
                        <summary><?php esc_html_e( 'Create collection', 'sustainable-catalyst-library' ); ?></summary>
                        <form data-sc-create-collection>
                            <label><span><?php esc_html_e( 'Collection name', 'sustainable-catalyst-library' ); ?></span><input name="name" maxlength="80" required></label>
                            <button type="submit"><?php esc_html_e( 'Create', 'sustainable-catalyst-library' ); ?></button>
                            <span data-sc-collection-status aria-live="polite"></span>
                        </form>
                    </details>
                    <div class="sc-citation-studio__export" aria-label="<?php esc_attr_e( 'Source exports', 'sustainable-catalyst-library' ); ?>">
                        <span><?php esc_html_e( 'Export', 'sustainable-catalyst-library' ); ?></span>
                        <button type="button" data-sc-export-format="bibtex">BibTeX</button>
                        <button type="button" data-sc-export-format="ris">RIS</button>
                        <button type="button" data-sc-export-format="csl-json">CSL-JSON</button>
                    </div>
                </div>

                <p class="sc-citation-studio__status" data-sc-citation-studio-status aria-live="polite"></p>
                <div class="sc-citation-studio__sources" data-sc-source-list>
                    <?php echo $this->render_sources( $sources, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function source_form() {
        ob_start();
        ?>
        <form class="sc-citation-studio__source-form" data-sc-create-source>
            <div class="sc-citation-studio__form-grid">
                <label class="is-wide"><span><?php esc_html_e( 'Title', 'sustainable-catalyst-library' ); ?></span><input name="title" required maxlength="500"></label>
                <label><span><?php esc_html_e( 'Author(s)', 'sustainable-catalyst-library' ); ?></span><input name="authors" placeholder="Family, Given; Family, Given"></label>
                <label><span><?php esc_html_e( 'Organization', 'sustainable-catalyst-library' ); ?></span><input name="organization"></label>
                <label><span><?php esc_html_e( 'Year', 'sustainable-catalyst-library' ); ?></span><input name="year" inputmode="numeric" maxlength="10"></label>
                <label><span><?php esc_html_e( 'Source type', 'sustainable-catalyst-library' ); ?></span><select name="source_type"><?php echo $this->source_type_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                <label><span>DOI</span><input name="doi" placeholder="10.xxxx/…"></label>
                <label><span>ISBN</span><input name="isbn"></label>
                <label class="is-wide"><span>URL</span><input name="url" type="url"></label>
                <label><span><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></span><input name="collection" list="sc-citation-collections"></label>
                <label class="is-wide"><span><?php esc_html_e( 'Private note', 'sustainable-catalyst-library' ); ?></span><textarea name="notes" rows="3"></textarea></label>
            </div>
            <datalist id="sc-citation-collections"><?php foreach ( $this->collections( get_current_user_id() ) as $collection ) : ?><option value="<?php echo esc_attr( $collection ); ?>"></option><?php endforeach; ?></datalist>
            <button type="submit" class="sc-citation-studio__primary"><?php esc_html_e( 'Save Source', 'sustainable-catalyst-library' ); ?></button>
            <span data-sc-create-source-status aria-live="polite"></span>
        </form>
        <?php
        return ob_get_clean();
    }

    private function source_type_options() {
        $types = array(
            'journal-article' => 'Journal article', 'book' => 'Book', 'book-chapter' => 'Book chapter', 'report' => 'Report',
            'webpage' => 'Webpage', 'dataset' => 'Dataset', 'conference-paper' => 'Conference paper', 'thesis' => 'Thesis or dissertation',
            'standard' => 'Standard', 'legislation' => 'Legislation', 'software' => 'Software', 'archive' => 'Archival material',
        );
        $html = '';
        foreach ( $types as $value => $label ) {
            $html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        return $html;
    }

    private function render_sources( $sources, $style ) {
        if ( ! $sources ) {
            return '<div class="sc-citation-studio__empty"><strong>' . esc_html__( 'No saved sources yet.', 'sustainable-catalyst-library' ) . '</strong><span>' . esc_html__( 'Use Research Access to save a discovered source, or add one manually above.', 'sustainable-catalyst-library' ) . '</span></div>';
        }
        $html = '<ol class="sc-citation-studio__source-index">';
        foreach ( $sources as $source ) {
            $id = absint( $source['id'] ?? 0 );
            $citation = SC_Library_Citation_Source_Manager::format_citation( $id, $style, 'reference' );
            $in_text = SC_Library_Citation_Source_Manager::format_citation( $id, $style, 'in-text' );
            $creator = $this->creator_label( $source );
            $search = strtolower( implode( ' ', array_filter( array( $source['title'] ?? '', $creator, $source['year'] ?? '', $source['doi'] ?? '', $source['isbn'] ?? '', $source['personal_collection'] ?? '' ) ) ) );
            $html .= '<li data-sc-source-item data-source-id="' . esc_attr( $id ) . '" data-source-collection="' . esc_attr( $source['personal_collection'] ?? '' ) . '" data-source-search="' . esc_attr( $search ) . '">';
            $html .= '<div class="sc-citation-studio__source-main"><div class="sc-citation-studio__source-meta">';
            $html .= '<span class="sc-citation-studio__source-type">' . esc_html( $source['source_type_name'] ?: 'Research source' ) . '</span>';
            if ( ! empty( $source['personal_collection'] ) ) { $html .= '<span class="sc-citation-studio__collection">' . esc_html( $source['personal_collection'] ) . '</span>'; }
            $html .= '</div><h3>' . esc_html( $source['title'] ?? '' ) . '</h3>';
            $byline = implode( ' · ', array_filter( array( $creator, $source['year'] ?? '', $source['container_title'] ?? '' ) ) );
            if ( $byline ) { $html .= '<p class="sc-citation-studio__byline">' . esc_html( $byline ) . '</p>'; }
            $identifiers = array();
            if ( ! empty( $source['doi'] ) ) { $identifiers[] = 'DOI ' . $source['doi']; }
            if ( ! empty( $source['isbn'] ) ) { $identifiers[] = 'ISBN ' . $source['isbn']; }
            if ( $identifiers ) { $html .= '<p class="sc-citation-studio__identifiers">' . esc_html( implode( ' · ', $identifiers ) ) . '</p>'; }
            $html .= '</div>';
            $html .= '<div class="sc-citation-studio__citation"><span data-sc-citation-style-label>' . esc_html( $this->style_name( $style ) ) . '</span><p data-sc-citation-value>' . esc_html( $citation ) . '</p><div><button type="button" data-sc-copy-citation>' . esc_html__( 'Copy Citation', 'sustainable-catalyst-library' ) . '</button><button type="button" data-sc-copy-intext data-value="' . esc_attr( $in_text ) . '">' . esc_html__( 'Copy In-Text', 'sustainable-catalyst-library' ) . '</button><a href="#research-document-builder" data-sc-add-source-to-document="' . esc_attr( $id ) . '">' . esc_html__( 'Add to Document', 'sustainable-catalyst-library' ) . '</a></div></div>';
            $html .= '<details class="sc-citation-studio__edit"><summary>' . esc_html__( 'Notes & source details', 'sustainable-catalyst-library' ) . '</summary>';
            $html .= '<form data-sc-update-source><input type="hidden" name="source_id" value="' . esc_attr( $id ) . '"><div class="sc-citation-studio__edit-grid">';
            $html .= '<label><span>' . esc_html__( 'Collection', 'sustainable-catalyst-library' ) . '</span><input name="collection" value="' . esc_attr( $source['personal_collection'] ?? '' ) . '"></label>';
            $html .= '<label><span>' . esc_html__( 'Year', 'sustainable-catalyst-library' ) . '</span><input name="year" value="' . esc_attr( $source['year'] ?? '' ) . '"></label>';
            $html .= '<label class="is-wide"><span>' . esc_html__( 'Private note', 'sustainable-catalyst-library' ) . '</span><textarea name="notes" rows="3">' . esc_textarea( $source['private_notes'] ?? '' ) . '</textarea></label>';
            $html .= '</div><div class="sc-citation-studio__edit-actions"><button type="submit">' . esc_html__( 'Save Changes', 'sustainable-catalyst-library' ) . '</button><button type="button" class="is-danger" data-sc-delete-source="' . esc_attr( $id ) . '">' . esc_html__( 'Remove Source', 'sustainable-catalyst-library' ) . '</button><span data-sc-source-action-status aria-live="polite"></span></div></form></details>';
            $html .= '</li>';
        }
        return $html . '</ol>';
    }

    public function ajax_list_sources() {
        $this->require_user();
        $style = sanitize_key( wp_unslash( $_POST['style'] ?? 'harvard' ) );
        $collection = sanitize_text_field( wp_unslash( $_POST['collection'] ?? '' ) );
        $sources = $this->personal_sources( get_current_user_id(), $collection, 250 );
        wp_send_json_success( array(
            'count' => count( $sources ),
            'html' => $this->render_sources( $sources, $style ),
            'collections' => $this->collections( get_current_user_id() ),
        ) );
    }

    public function ajax_create_source() {
        $this->require_user();
        $payload = wp_unslash( $_POST );
        $source_id = $this->create_personal_source( $payload, get_current_user_id() );
        if ( is_wp_error( $source_id ) ) {
            wp_send_json_error( array( 'message' => $source_id->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'source_id' => $source_id, 'message' => __( 'Source saved to My Sources.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_update_source() {
        $this->require_user();
        $source_id = absint( $_POST['source_id'] ?? 0 );
        if ( ! $this->owns_source( $source_id, get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => __( 'Source not found.', 'sustainable-catalyst-library' ) ), 404 );
        }
        $collection = $this->sanitize_collection( wp_unslash( $_POST['collection'] ?? '' ) );
        $notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
        $year = sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) );
        $this->update_or_delete_meta( $source_id, self::META_COLLECTION, $collection );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_NOTES, $notes );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_YEAR, $year );
        if ( $collection ) { $this->remember_collection( get_current_user_id(), $collection ); }
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
        wp_send_json_success( array( 'message' => __( 'Source updated.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_delete_source() {
        $this->require_user();
        $source_id = absint( $_POST['source_id'] ?? 0 );
        if ( ! $this->owns_source( $source_id, get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => __( 'Source not found.', 'sustainable-catalyst-library' ) ), 404 );
        }
        wp_delete_post( $source_id, true );
        wp_send_json_success( array( 'message' => __( 'Source removed from My Sources.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_create_collection() {
        $this->require_user();
        $name = $this->sanitize_collection( wp_unslash( $_POST['name'] ?? '' ) );
        if ( ! $name ) {
            wp_send_json_error( array( 'message' => __( 'Enter a collection name.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $this->remember_collection( get_current_user_id(), $name );
        wp_send_json_success( array( 'message' => __( 'Collection created.', 'sustainable-catalyst-library' ), 'collections' => $this->collections( get_current_user_id() ) ) );
    }

    public function ajax_export_sources() {
        $this->require_user();
        $format = sanitize_key( wp_unslash( $_POST['format'] ?? 'csl-json' ) );
        $collection = sanitize_text_field( wp_unslash( $_POST['collection'] ?? '' ) );
        $sources = $this->personal_sources( get_current_user_id(), $collection, 500 );
        if ( 'bibtex' === $format ) {
            $content = $this->export_bibtex( $sources ); $filename = 'sustainable-catalyst-sources.bib'; $mime = 'application/x-bibtex';
        } elseif ( 'ris' === $format ) {
            $content = $this->export_ris( $sources ); $filename = 'sustainable-catalyst-sources.ris'; $mime = 'application/x-research-info-systems';
        } else {
            $format = 'csl-json'; $content = wp_json_encode( array_map( array( $this, 'to_csl_json' ), $sources ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); $filename = 'sustainable-catalyst-sources.json'; $mime = 'application/json';
        }
        wp_send_json_success( array( 'format' => $format, 'filename' => $filename, 'mime' => $mime, 'content' => $content, 'count' => count( $sources ) ) );
    }

    public static function save_normalized_result( $result, $user_id ) {
        $studio = new self();
        return $studio->create_personal_source_from_result( $result, absint( $user_id ) );
    }

    private function create_personal_source_from_result( $result, $user_id ) {
        if ( ! $user_id || ! is_array( $result ) || empty( $result['title'] ) ) {
            return new WP_Error( 'sc_citation_invalid_result', __( 'The discovery result cannot be saved.', 'sustainable-catalyst-library' ) );
        }
        $existing = $this->find_personal_duplicate( $user_id, $result['doi'] ?? '', $result['isbn'] ?? '', $result['title'] ?? '' );
        if ( $existing ) { return $existing; }
        $payload = array(
            'title' => $result['title'] ?? '',
            'authors_array' => $result['authors'] ?? array(),
            'organization' => $result['organization'] ?? '',
            'year' => $result['year'] ?? '',
            'source_type' => $result['source_type'] ?? 'report',
            'doi' => $result['doi'] ?? '',
            'isbn' => $result['isbn'] ?? '',
            'pmid' => $result['pmid'] ?? '',
            'url' => $result['record_url'] ?? $result['open_access_url'] ?? '',
            'container_title' => $result['container_title'] ?? '',
            'publisher' => $result['publisher'] ?? '',
            'volume' => $result['volume'] ?? '',
            'issue' => $result['issue'] ?? '',
            'pages' => $result['pages'] ?? '',
            'abstract' => $result['abstract'] ?? '',
            'notes' => '',
            'collection' => '',
            'provenance' => sprintf( 'Saved from Research Access provider %s (%s).', sanitize_key( $result['provider'] ?? '' ), sanitize_text_field( $result['provider_record_id'] ?? '' ) ),
        );
        return $this->create_personal_source( $payload, $user_id );
    }

    private function create_personal_source( $payload, $user_id ) {
        $user_id = absint( $user_id );
        $title = sanitize_text_field( $payload['title'] ?? '' );
        if ( ! $user_id || ! $title ) {
            return new WP_Error( 'sc_citation_title_required', __( 'A source title is required.', 'sustainable-catalyst-library' ) );
        }
        $existing = $this->find_personal_duplicate( $user_id, $payload['doi'] ?? '', $payload['isbn'] ?? '', $title );
        if ( $existing ) { return $existing; }
        $source_id = wp_insert_post( array(
            'post_type' => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'post_status' => 'private',
            'post_author' => $user_id,
            'post_title' => $title,
            'post_excerpt' => sanitize_textarea_field( $payload['abstract'] ?? '' ),
            'post_content' => '',
        ), true );
        if ( is_wp_error( $source_id ) ) { return $source_id; }

        update_post_meta( $source_id, self::META_OWNER, $user_id );
        $authors = ! empty( $payload['authors_array'] ) && is_array( $payload['authors_array'] ) ? $this->sanitize_authors_array( $payload['authors_array'] ) : $this->parse_authors( $payload['authors'] ?? '' );
        update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_AUTHORS, $authors );
        $map = array(
            'organization' => SC_Library_Citation_Source_Manager::META_ORGANIZATION, 'year' => SC_Library_Citation_Source_Manager::META_YEAR,
            'container_title' => SC_Library_Citation_Source_Manager::META_CONTAINER_TITLE, 'publisher' => SC_Library_Citation_Source_Manager::META_PUBLISHER,
            'volume' => SC_Library_Citation_Source_Manager::META_VOLUME, 'issue' => SC_Library_Citation_Source_Manager::META_ISSUE,
            'pages' => SC_Library_Citation_Source_Manager::META_PAGES, 'pmid' => SC_Library_Citation_Source_Manager::META_PMID,
        );
        foreach ( $map as $field => $meta ) { $this->update_or_delete_meta( $source_id, $meta, sanitize_text_field( $payload[ $field ] ?? '' ) ); }
        $doi = $this->normalize_doi( $payload['doi'] ?? '' );
        $isbn = $this->normalize_isbn( $payload['isbn'] ?? '' );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_DOI, $doi );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_ISBN, $isbn );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_URL, esc_url_raw( $payload['url'] ?? '' ) );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_NOTES, sanitize_textarea_field( $payload['notes'] ?? '' ) );
        $this->update_or_delete_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROVENANCE, sanitize_textarea_field( $payload['provenance'] ?? 'Saved in Citation Studio.' ) );
        $collection = $this->sanitize_collection( $payload['collection'] ?? '' );
        $this->update_or_delete_meta( $source_id, self::META_COLLECTION, $collection );
        if ( $collection ) { $this->remember_collection( $user_id, $collection ); }
        $source_type = sanitize_title( $payload['source_type'] ?? 'report' );
        if ( $source_type ) { wp_set_object_terms( $source_id, array( $source_type ), SC_Library_Citation_Source_Manager::SOURCE_TYPE_TAXONOMY, false ); }
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
        return $source_id;
    }

    private function personal_sources( $user_id, $collection = '', $limit = 100 ) {
        $args = array(
            'post_type' => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'post_status' => array( 'private', 'draft' ),
            'posts_per_page' => min( 500, max( 1, absint( $limit ) ) ),
            'orderby' => 'modified', 'order' => 'DESC',
            'author' => absint( $user_id ),
            'meta_query' => array( array( 'key' => self::META_OWNER, 'value' => absint( $user_id ), 'compare' => '=' ) ),
            'fields' => 'ids',
        );
        if ( $collection ) { $args['meta_query'][] = array( 'key' => self::META_COLLECTION, 'value' => sanitize_text_field( $collection ), 'compare' => '=' ); }
        $ids = get_posts( $args );
        $sources = array();
        foreach ( $ids as $id ) {
            $data = SC_Library_Citation_Source_Manager::get_source_data( $id, true );
            if ( ! $data ) { continue; }
            $data['private_notes'] = (string) get_post_meta( $id, SC_Library_Citation_Source_Manager::META_NOTES, true );
            $data['personal_collection'] = (string) get_post_meta( $id, self::META_COLLECTION, true );
            $sources[] = $data;
        }
        return $sources;
    }

    private function owns_source( $source_id, $user_id ) {
        return $source_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id ) && absint( get_post_meta( $source_id, self::META_OWNER, true ) ) === absint( $user_id );
    }

    private function find_personal_duplicate( $user_id, $doi, $isbn, $title ) {
        $doi = $this->normalize_doi( $doi ); $isbn = $this->normalize_isbn( $isbn );
        $queries = array();
        if ( $doi ) { $queries[] = array( 'key' => SC_Library_Citation_Source_Manager::META_DOI, 'value' => $doi, 'compare' => '=' ); }
        if ( $isbn ) { $queries[] = array( 'key' => SC_Library_Citation_Source_Manager::META_ISBN, 'value' => $isbn, 'compare' => '=' ); }
        if ( ! $queries ) { return 0; }
        foreach ( $queries as $meta ) {
            $ids = get_posts( array( 'post_type' => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, 'post_status' => array( 'private', 'draft' ), 'author' => absint( $user_id ), 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => self::META_OWNER, 'value' => absint( $user_id ) ), $meta ) ) );
            if ( $ids ) { return absint( $ids[0] ); }
        }
        $title = sanitize_text_field( $title );
        if ( $title ) {
            $ids = get_posts( array( 'post_type' => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, 'post_status' => array( 'private', 'draft' ), 'author' => absint( $user_id ), 'title' => $title, 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => self::META_OWNER, 'value' => absint( $user_id ) ) ) ) );
            if ( $ids ) { return absint( $ids[0] ); }
        }
        return 0;
    }

    private function require_user() {
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Sign in to manage My Sources.', 'sustainable-catalyst-library' ) ), 401 ); }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
    }

    private function collections( $user_id ) {
        $collections = get_user_meta( absint( $user_id ), self::USER_COLLECTIONS, true );
        $collections = is_array( $collections ) ? array_values( array_unique( array_filter( array_map( array( $this, 'sanitize_collection' ), $collections ) ) ) ) : array();
        natcasesort( $collections );
        return array_values( $collections );
    }

    private function remember_collection( $user_id, $name ) {
        $name = $this->sanitize_collection( $name ); if ( ! $name ) { return; }
        $collections = $this->collections( $user_id ); $collections[] = $name; $collections = array_values( array_unique( $collections ) ); natcasesort( $collections );
        update_user_meta( absint( $user_id ), self::USER_COLLECTIONS, array_values( $collections ) );
    }

    private function sanitize_collection( $name ) { $name = trim( sanitize_text_field( $name ) ); return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 80 ) : substr( $name, 0, 80 ); }
    private function update_or_delete_meta( $id, $key, $value ) { if ( '' === $value || null === $value || array() === $value ) { delete_post_meta( $id, $key ); } else { update_post_meta( $id, $key, $value ); } }
    private function normalize_doi( $doi ) { $doi = strtolower( trim( preg_replace( '#^https?://(?:dx\.)?doi\.org/#i', '', (string) $doi ) ) ); return preg_match( '/^10\.\d{4,9}\/\S+$/i', $doi ) ? rtrim( $doi, '.,; ' ) : $doi; }
    private function normalize_isbn( $isbn ) { return strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $isbn ) ); }

    private function parse_authors( $text ) {
        $people = array();
        foreach ( preg_split( '/\s*;\s*/u', trim( (string) $text ) ) as $entry ) {
            if ( ! $entry ) { continue; }
            $parts = array_map( 'trim', explode( ',', $entry, 2 ) );
            if ( count( $parts ) === 2 ) { $people[] = array( 'family' => sanitize_text_field( $parts[0] ), 'given' => sanitize_text_field( $parts[1] ) ); }
            else { $words = preg_split( '/\s+/u', $entry ); $family = array_pop( $words ); $people[] = array( 'family' => sanitize_text_field( $family ), 'given' => sanitize_text_field( implode( ' ', $words ) ) ); }
        }
        return array_slice( $people, 0, 40 );
    }
    private function sanitize_authors_array( $authors ) {
        $clean = array(); foreach ( $authors as $author ) { if ( ! is_array( $author ) ) { continue; } $family = sanitize_text_field( $author['family'] ?? '' ); $given = sanitize_text_field( $author['given'] ?? '' ); if ( $family || $given ) { $clean[] = array( 'family' => $family, 'given' => $given ); } } return array_slice( $clean, 0, 40 );
    }

    private function style_name( $style ) { $styles = SC_Library_Citation_Source_Manager::citation_styles(); return $styles[ $style ]['name'] ?? $style; }
    private function creator_label( $data ) {
        $names = array(); foreach ( (array) ( $data['authors'] ?? array() ) as $a ) { $name = trim( ( $a['given'] ?? '' ) . ' ' . ( $a['family'] ?? '' ) ); if ( $name ) { $names[] = $name; } }
        if ( $names ) { return implode( ', ', array_slice( $names, 0, 3 ) ) . ( count( $names ) > 3 ? ' et al.' : '' ); }
        return (string) ( $data['organization'] ?? '' );
    }
    private function author_family( $data ) { if ( ! empty( $data['authors'][0]['family'] ) ) { return $data['authors'][0]['family']; } return (string) ( $data['organization_short'] ?: $data['organization'] ?: 'Source' ); }
    private function year( $data ) { return (string) ( $data['year'] ?: ( $data['publication_date'] ? substr( $data['publication_date'], 0, 4 ) : 'n.d.' ) ); }
    private function author_string( $data, $initials = false ) {
        $out = array(); foreach ( (array) ( $data['authors'] ?? array() ) as $a ) { $family = trim( $a['family'] ?? '' ); $given = trim( $a['given'] ?? '' ); if ( $initials ) { $given = implode( '', array_map( static function( $p ) { return $p ? mb_substr( $p, 0, 1 ) . '.' : ''; }, preg_split( '/[\s-]+/u', $given ) ) ); } $out[] = trim( $family . ( $given ? ', ' . $given : '' ) ); }
        return $out ? implode( '; ', $out ) : (string) ( $data['organization'] ?? '' );
    }
    private function doi_or_url( $data ) { if ( ! empty( $data['doi'] ) ) { return 'https://doi.org/' . $data['doi']; } return (string) ( $data['url'] ?? '' ); }
    private function journal_bits( $data ) { $bits = array(); if ( ! empty( $data['container_title'] ) ) { $bits[] = $data['container_title']; } if ( ! empty( $data['volume'] ) ) { $bits[] = 'vol. ' . $data['volume']; } if ( ! empty( $data['issue'] ) ) { $bits[] = 'no. ' . $data['issue']; } if ( ! empty( $data['pages'] ) ) { $bits[] = 'pp. ' . $data['pages']; } return implode( ', ', $bits ); }

    private function format_in_text( $data, $style, $locator ) {
        $family = $this->author_family( $data ); $year = $this->year( $data ); $locator = trim( (string) $locator );
        if ( 'mla-9' === $style ) { return '(' . trim( $family . ( $locator ? ' ' . preg_replace( '/^p{1,2}\.\s*/i', '', $locator ) : '' ) ) . ')'; }
        if ( 'chicago-author-date' === $style || 'acs-author-date' === $style ) { return '(' . $family . ' ' . $year . ( $locator ? ', ' . $locator : '' ) . ')'; }
        if ( in_array( $style, array( 'ieee', 'vancouver', 'ama' ), true ) ) { return '[n]'; }
        if ( 'chicago-notes-bibliography' === $style ) { return $family . ', ' . ( $data['title'] ?? '' ) . ( $locator ? ', ' . $locator : '' ) . '.'; }
        return '(' . $family . ', ' . $year . ( $locator ? ', ' . $locator : '' ) . ')';
    }

    private function format_reference( $data, $style ) {
        $authors = $this->author_string( $data, in_array( $style, array( 'ieee', 'vancouver', 'ama', 'acs-author-date' ), true ) );
        $year = $this->year( $data ); $title = trim( (string) ( $data['title'] ?? '' ) ); $container = trim( (string) ( $data['container_title'] ?? '' ) ); $url = $this->doi_or_url( $data );
        if ( 'apa-7' === $style ) { return $this->clean_join( array( $authors . ' (' . $year . ').', $title . '.', $container ? $container . $this->apa_volume_issue_pages( $data ) . '.' : '', $data['publisher'] ? $data['publisher'] . '.' : '', $url ) ); }
        if ( 'mla-9' === $style ) { return $this->clean_join( array( $authors ? $authors . '.' : '', '“' . $title . '.”', $container ? $container . ',' : '', $data['publisher'] ? $data['publisher'] . ',' : '', $year . ',', $this->journal_bits( $data ) ? $this->journal_bits( $data ) . '.' : '', $url ? $url . '.' : '' ) ); }
        if ( 'chicago-author-date' === $style ) { return $this->clean_join( array( $authors . '.', $year . '.', '“' . $title . '.”', $this->chicago_container( $data ), $url ) ); }
        if ( 'chicago-notes-bibliography' === $style ) { return $this->clean_join( array( $authors . '.', '“' . $title . '.”', $this->chicago_container( $data, true ), $url ) ); }
        if ( 'ieee' === $style ) { return $this->clean_join( array( $authors . ',', '“' . $title . ',”', $container ? $container . ',' : '', $data['volume'] ? 'vol. ' . $data['volume'] . ',' : '', $data['issue'] ? 'no. ' . $data['issue'] . ',' : '', $data['pages'] ? 'pp. ' . $data['pages'] . ',' : '', $year . '.', $url ) ); }
        if ( 'vancouver' === $style ) { return $this->clean_join( array( $authors . '.', $title . '.', $container ? $container . '.' : '', $year . $this->vancouver_tail( $data ) . '.', $url ) ); }
        if ( 'ama' === $style ) { return $this->clean_join( array( $authors . '.', $title . '.', $container ? $container . '.' : '', $year . ';' . $this->ama_tail( $data ) . '.', $url ) ); }
        if ( 'acs-author-date' === $style ) { return $this->clean_join( array( $authors . '.', $title . '.', $container ? $container : '', $year . ( $data['volume'] ? ', ' . $data['volume'] : '' ) . ( $data['pages'] ? ', ' . $data['pages'] : '' ) . '.', $url ) ); }
        return $this->clean_join( array( $authors ? $authors . '.' : '', $title . '.', $container ? $container . '.' : '', $year . '.', $url ) );
    }

    private function apa_volume_issue_pages( $d ) { $s=''; if ( ! empty( $d['volume'] ) ) { $s .= ', ' . $d['volume']; } if ( ! empty( $d['issue'] ) ) { $s .= '(' . $d['issue'] . ')'; } if ( ! empty( $d['pages'] ) ) { $s .= ', ' . $d['pages']; } return $s; }
    private function chicago_container( $d, $include_year = false ) { $s = $d['container_title'] ?? ''; if ( ! empty( $d['volume'] ) ) { $s .= ' ' . $d['volume']; } if ( ! empty( $d['issue'] ) ) { $s .= ', no. ' . $d['issue']; } if ( $include_year ) { $s .= ' (' . $this->year( $d ) . ')'; } if ( ! empty( $d['pages'] ) ) { $s .= ': ' . $d['pages']; } return $s ? $s . '.' : ''; }
    private function vancouver_tail( $d ) { $s=''; if ( ! empty( $d['volume'] ) ) { $s .= ';' . $d['volume']; } if ( ! empty( $d['issue'] ) ) { $s .= '(' . $d['issue'] . ')'; } if ( ! empty( $d['pages'] ) ) { $s .= ':' . $d['pages']; } return $s; }
    private function ama_tail( $d ) { $s=''; if ( ! empty( $d['volume'] ) ) { $s .= $d['volume']; } if ( ! empty( $d['issue'] ) ) { $s .= '(' . $d['issue'] . ')'; } if ( ! empty( $d['pages'] ) ) { $s .= ':' . $d['pages']; } return $s; }
    private function clean_join( $parts ) { return trim( preg_replace( '/\s+/u', ' ', implode( ' ', array_values( array_filter( array_map( 'trim', $parts ) ) ) ) ) ); }

    public function to_csl_json( $source ) {
        $type_map = array( 'journal-article'=>'article-journal','book'=>'book','book-chapter'=>'chapter','report'=>'report','webpage'=>'webpage','dataset'=>'dataset','conference-paper'=>'paper-conference','thesis'=>'thesis','software'=>'software' );
        $year = preg_match( '/^\d{4}$/', (string) ( $source['year'] ?? '' ) ) ? absint( $source['year'] ) : 0;
        $item = array( 'id' => (string) ( $source['citation_key'] ?: 'sc-source-' . $source['id'] ), 'type' => $type_map[ $source['source_type'] ?? '' ] ?? 'document', 'title' => $source['title'] ?? '' );
        if ( ! empty( $source['authors'] ) ) { $item['author'] = array_map( static function( $a ) { return array_filter( array( 'family' => $a['family'] ?? '', 'given' => $a['given'] ?? '' ) ); }, $source['authors'] ); }
        elseif ( ! empty( $source['organization'] ) ) { $item['author'] = array( array( 'literal' => $source['organization'] ) ); }
        if ( $year ) { $item['issued'] = array( 'date-parts' => array( array( $year ) ) ); }
        foreach ( array( 'container_title'=>'container-title','publisher'=>'publisher','volume'=>'volume','issue'=>'issue','pages'=>'page','doi'=>'DOI','isbn'=>'ISBN','url'=>'URL' ) as $from=>$to ) { if ( ! empty( $source[ $from ] ) ) { $item[ $to ] = $source[ $from ]; } }
        return $item;
    }

    private function export_bibtex( $sources ) {
        $entries = array(); foreach ( $sources as $s ) { $type_map=array('journal-article'=>'article','book'=>'book','book-chapter'=>'incollection','report'=>'techreport','conference-paper'=>'inproceedings','thesis'=>'phdthesis'); $type=$type_map[$s['source_type']??'']??'misc'; $key=$s['citation_key']?:'sc_source_'.$s['id']; $fields=array('title'=>$s['title']??'','author'=>$this->bibtex_authors($s),'year'=>$s['year']??'','journal'=>$s['container_title']??'','publisher'=>$s['publisher']??'','volume'=>$s['volume']??'','number'=>$s['issue']??'','pages'=>$s['pages']??'','doi'=>$s['doi']??'','isbn'=>$s['isbn']??'','url'=>$s['url']??''); $lines=array('@'.$type.'{'.$this->ascii_key($key).','); foreach($fields as $name=>$value){ if(''!==trim((string)$value)){ $lines[]='  '.$name.' = {'.$this->bibtex_escape($value).'},'; } } $lines[]='}'; $entries[]=implode("\n",$lines); } return implode("\n\n",$entries)."\n";
    }
    private function export_ris( $sources ) {
        $type_map=array('journal-article'=>'JOUR','book'=>'BOOK','book-chapter'=>'CHAP','report'=>'RPRT','webpage'=>'ELEC','dataset'=>'DATA','conference-paper'=>'CPAPER','thesis'=>'THES','software'=>'COMP'); $out=array(); foreach($sources as $s){ $out[]='TY  - '.($type_map[$s['source_type']??'']??'GEN'); $out[]='TI  - '.($s['title']??''); foreach((array)($s['authors']??array()) as $a){ $out[]='AU  - '.trim(($a['family']??'').', '.($a['given']??''),', '); } if(empty($s['authors'])&&!empty($s['organization'])){$out[]='AU  - '.$s['organization'];} if(!empty($s['year'])){$out[]='PY  - '.$s['year'];} if(!empty($s['container_title'])){$out[]='JO  - '.$s['container_title'];} if(!empty($s['volume'])){$out[]='VL  - '.$s['volume'];} if(!empty($s['issue'])){$out[]='IS  - '.$s['issue'];} if(!empty($s['pages'])){$parts=preg_split('/[-–—]/u',$s['pages'],2);$out[]='SP  - '.trim($parts[0]);if(isset($parts[1])){$out[]='EP  - '.trim($parts[1]);}} if(!empty($s['publisher'])){$out[]='PB  - '.$s['publisher'];} if(!empty($s['doi'])){$out[]='DO  - '.$s['doi'];} if(!empty($s['isbn'])){$out[]='SN  - '.$s['isbn'];} if(!empty($s['url'])){$out[]='UR  - '.$s['url'];} $out[]='ER  - '; $out[]=''; } return implode("\n",$out);
    }
    private function bibtex_authors( $s ) { $out=array(); foreach((array)($s['authors']??array()) as $a){$out[]=trim(($a['family']??'').', '.($a['given']??''),', ');} return $out?implode(' and ',$out):(string)($s['organization']??''); }
    private function bibtex_escape( $v ) { return str_replace(array('{','}'),array('\\{','\\}'),(string)$v); }
    private function ascii_key( $v ) { $v=sanitize_title((string)$v); return $v?:'sc-source'; }
    private function current_url_with_anchor() { $scheme=is_ssl()?'https':'http'; $host=sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']??'')); $uri=esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']??'/')); return esc_url_raw($scheme.'://'.$host.$uri.'#citation-studio'); }
}
