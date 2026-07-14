<?php
/**
 * PDF-to-Document Knowledge Library.
 *
 * Evolves the existing sc_foundation_doc custom post type into a first-class
 * Knowledge Library document record with an authoritative PDF attachment and
 * editable extracted content.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_PDF_To_Document {
    public const POST_TYPE = 'sc_foundation_doc';
    public const TAX_FAMILY = 'sc_document_family';
    public const DEFAULT_FAMILY = 'foundations';
    public const ROUTE_VERSION = '2.2.1';
    public const SCHEMA = 'sc-library-pdf-document/2.0';
    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const META_STATUS = '_sc_document_extraction_status';
    public const META_METHOD = '_sc_document_extraction_method';
    public const META_EXTRACTED_AT = '_sc_document_extracted_at';
    public const META_RAW_TEXT = '_sc_document_raw_text';
    public const META_PAGE_MAP = '_sc_document_page_map';
    public const META_PAGE_COUNT = '_sc_document_page_count';
    public const META_CHECKSUM = '_sc_document_checksum';
    public const META_VERSION = '_sc_document_version';
    public const META_PUBLICATION_DATE = '_sc_document_publication_date';
    public const META_LIFECYCLE = '_sc_document_lifecycle_status';
    public const META_REVIEWED = '_sc_document_reviewed';
    public const MIN_TEXT = 80;

    private static $validation_error = '';

    public function __construct() {
        add_filter( 'register_post_type_args', array( $this, 'filter_post_type_args' ), 50, 2 );
        add_action( 'init', array( $this, 'register_family_and_routes' ), 120 );
        add_action( 'rest_api_init', array( $this, 'register_rest_meta' ) );

        add_action( 'edit_form_after_title', array( $this, 'render_conversion_panel' ), 30 );
        add_filter( 'wp_insert_post_data', array( $this, 'prevent_empty_document_publication' ), 60, 2 );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_document_metadata' ), 60, 3 );
        add_filter( 'redirect_post_location', array( $this, 'redirect_with_status' ), 60, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 60 );
        add_action( 'admin_notices', array( $this, 'editor_notices' ), 60 );

        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ), 60 );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 60, 2 );
        add_filter( 'display_post_states', array( $this, 'post_states' ), 60, 2 );
        add_filter( 'media_row_actions', array( $this, 'media_row_actions' ), 30, 2 );

        add_action( 'admin_menu', array( $this, 'admin_pages' ), 120 );
        add_action( 'admin_post_sc_library_create_document_from_pdf', array( $this, 'create_from_attachment' ) );
        add_action( 'admin_post_sc_library_import_pdf_documents', array( $this, 'import_documents' ) );
        add_action( 'admin_post_sc_library_repair_pdf_document_routes', array( $this, 'repair_routes' ) );
        add_action( 'admin_post_sc_library_normalize_pdf_documents', array( $this, 'normalize_existing_records' ) );

        add_action( 'wp_ajax_sc_library_prepare_pdf_document', array( $this, 'ajax_prepare' ) );
        add_action( 'wp_ajax_sc_library_store_pdf_document_chunk', array( $this, 'ajax_store_chunk' ) );
        add_action( 'wp_ajax_sc_library_finalize_pdf_document', array( $this, 'ajax_finalize' ) );
        add_action( 'wp_ajax_sc_library_mark_pdf_document_failure', array( $this, 'ajax_mark_failure' ) );

        add_filter( 'pre_do_shortcode_tag', array( $this, 'bridge_existing_shortcodes' ), 5, 4 );
        add_shortcode( 'sc_pdf_document_library', array( $this, 'shortcode_library' ) );
        add_shortcode( 'sc_pdf_library', array( $this, 'shortcode_library' ) );

        add_filter( 'template_include', array( $this, 'template_include' ), 200 );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'legacy_redirect' ) );
        add_filter( 'body_class', array( $this, 'body_classes' ), 60 );

        foreach ( array( 'sc_library_record_post_types', 'sc_library_searchable_post_types', 'sc_library_indexable_post_types', 'sc_research_librarian_post_types' ) as $filter_name ) {
            add_filter( $filter_name, array( $this, 'include_post_type' ) );
        }
    }

    public function filter_post_type_args( $args, $post_type ) {
        if ( self::POST_TYPE !== $post_type ) {
            return $args;
        }
        $labels = isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array();
        $args['labels'] = array_merge(
            $labels,
            array(
                'name' => __( 'PDF Documents', 'sustainable-catalyst-library' ),
                'singular_name' => __( 'PDF Document', 'sustainable-catalyst-library' ),
                'menu_name' => __( 'PDF Documents', 'sustainable-catalyst-library' ),
                'add_new' => __( 'Add PDF Document', 'sustainable-catalyst-library' ),
                'add_new_item' => __( 'Create Document from PDF', 'sustainable-catalyst-library' ),
                'edit_item' => __( 'Edit PDF Document', 'sustainable-catalyst-library' ),
                'all_items' => __( 'All PDF Documents', 'sustainable-catalyst-library' ),
                'search_items' => __( 'Search PDF Documents', 'sustainable-catalyst-library' ),
            )
        );
        $args['exclude_from_search'] = false;
        $args['show_in_rest'] = true;
        $args['taxonomies'] = array( self::TAX_FAMILY );
        $args['supports'] = array( 'title', 'editor', 'excerpt', 'revisions' );
        $args['rewrite'] = array( 'slug' => 'documents', 'with_front' => false );
        return $args;
    }

    public function register_family_and_routes() {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return;
        }
        register_taxonomy(
            self::TAX_FAMILY,
            array( self::POST_TYPE ),
            array(
                'labels' => array(
                    'name' => __( 'Document Families', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Document Family', 'sustainable-catalyst-library' ),
                    'menu_name' => __( 'Document Families', 'sustainable-catalyst-library' ),
                    'add_new_item' => __( 'Add Document Family', 'sustainable-catalyst-library' ),
                    'edit_item' => __( 'Edit Document Family', 'sustainable-catalyst-library' ),
                ),
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'hierarchical' => true,
                'rewrite' => array( 'slug' => 'documents/family', 'with_front' => false ),
            )
        );
        foreach ( get_object_taxonomies( self::POST_TYPE ) as $taxonomy ) {
            if ( self::TAX_FAMILY !== $taxonomy ) {
                unregister_taxonomy_for_object_type( $taxonomy, self::POST_TYPE );
            }
        }
        add_post_type_support( self::POST_TYPE, 'excerpt' );
        add_rewrite_rule( '^documents/([^/]+)/?$', 'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]', 'top' );
        add_rewrite_rule( '^foundations/([^/]+)/?$', 'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]&sc_legacy_foundation_route=1', 'top' );
        if ( ! term_exists( self::DEFAULT_FAMILY, self::TAX_FAMILY ) ) {
            wp_insert_term( __( 'Foundations', 'sustainable-catalyst-library' ), self::TAX_FAMILY, array( 'slug' => self::DEFAULT_FAMILY ) );
        }
        $this->migrate_existing_records();
        if ( self::ROUTE_VERSION !== get_option( 'sc_library_pdf_document_route_version' ) ) {
            flush_rewrite_rules( false );
            update_option( 'sc_library_pdf_document_route_version', self::ROUTE_VERSION, false );
        }
    }

    public function register_rest_meta() {
        foreach ( array( self::META_STATUS, self::META_METHOD, self::META_EXTRACTED_AT, self::META_CHECKSUM, self::META_VERSION, self::META_PUBLICATION_DATE, self::META_LIFECYCLE ) as $key ) {
            register_post_meta( self::POST_TYPE, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
        }
        foreach ( array( self::META_PDF_ID, self::META_PAGE_COUNT, self::META_REVIEWED ) as $key ) {
            register_post_meta( self::POST_TYPE, $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
        }
    }

    public function include_post_type( $types ) {
        if ( is_array( $types ) && ! in_array( self::POST_TYPE, $types, true ) ) {
            $types[] = self::POST_TYPE;
        }
        return $types;
    }

    public function render_conversion_panel( $post ) {
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
            return;
        }
        $status = self::status( $post->ID );
        $terms = wp_get_object_terms( $post->ID, self::TAX_FAMILY, array( 'fields' => 'ids' ) );
        $family_id = ! is_wp_error( $terms ) && $terms ? absint( $terms[0] ) : 0;
        $families = get_terms( array( 'taxonomy' => self::TAX_FAMILY, 'hide_empty' => false ) );
        wp_nonce_field( 'sc_library_save_pdf_document', 'sc_library_pdf_document_nonce' );
        ?>
        <div class="sc-pdf-conversion-panel" data-sc-pdf-conversion-panel>
            <div class="sc-pdf-conversion-panel__heading">
                <span class="dashicons dashicons-text-page" aria-hidden="true"></span>
                <div>
                    <h2><?php esc_html_e( 'Create a Knowledge Document from the PDF', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php esc_html_e( 'The original PDF remains authoritative. Extracted text becomes the editable, searchable article in the editor below.', 'sustainable-catalyst-library' ); ?></p>
                </div>
            </div>
            <div class="sc-pdf-conversion-panel__grid">
                <section>
                    <p class="sc-pdf-conversion-panel__label"><?php esc_html_e( 'Document family', 'sustainable-catalyst-library' ); ?></p>
                    <select name="sc_pdf_document_family">
                        <?php if ( ! is_wp_error( $families ) ) : foreach ( $families as $family ) : ?>
                            <option value="<?php echo esc_attr( $family->term_id ); ?>" <?php selected( $family_id, $family->term_id ); ?>><?php echo esc_html( $family->name ); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Families create separate PDF libraries without using blog categories.', 'sustainable-catalyst-library' ); ?></p>
                </section>
                <section>
                    <p class="sc-pdf-conversion-panel__label"><?php esc_html_e( 'Conversion status', 'sustainable-catalyst-library' ); ?></p>
                    <div class="sc-pdf-conversion-panel__status" data-sc-pdf-status data-status="<?php echo esc_attr( $status ); ?>" aria-live="polite">
                        <strong><?php echo esc_html( self::status_label( $status ) ); ?></strong>
                        <span><?php echo esc_html( self::status_message( $status ) ); ?></span>
                        <progress value="0" max="100" data-sc-pdf-progress hidden></progress>
                    </div>
                </section>
                <section class="sc-pdf-conversion-panel__wide">
                    <button type="button" class="button button-primary" data-sc-create-document><?php echo in_array( $status, array( 'ready_review', 'reviewed', 'published' ), true ) ? esc_html__( 'Re-create Document from PDF', 'sustainable-catalyst-library' ) : esc_html__( 'Create Document from PDF', 'sustainable-catalyst-library' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Text-based PDFs are converted locally. Image-only PDFs are marked Needs OCR rather than producing unreliable content.', 'sustainable-catalyst-library' ); ?></p>
                </section>
            </div>
            <details>
                <summary><?php esc_html_e( 'Version, lifecycle, and review', 'sustainable-catalyst-library' ); ?></summary>
                <div class="sc-pdf-conversion-panel__metadata">
                    <label><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?><input type="text" name="sc_pdf_document_version" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VERSION, true ) ); ?>" placeholder="1.0"></label>
                    <label><?php esc_html_e( 'Publication date', 'sustainable-catalyst-library' ); ?><input type="date" name="sc_pdf_document_publication_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PUBLICATION_DATE, true ) ); ?>"></label>
                    <label><?php esc_html_e( 'Lifecycle', 'sustainable-catalyst-library' ); ?><select name="sc_pdf_document_lifecycle"><?php foreach ( array( 'current' => 'Current', 'superseded' => 'Superseded', 'archived' => 'Archived', 'historical' => 'Historical' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( get_post_meta( $post->ID, self::META_LIFECYCLE, true ) ?: 'current', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label class="sc-pdf-conversion-panel__review"><input type="checkbox" name="sc_pdf_document_reviewed" value="1" <?php checked( get_post_meta( $post->ID, self::META_REVIEWED, true ) ); ?>> <?php esc_html_e( 'I reviewed the generated document against the original PDF.', 'sustainable-catalyst-library' ); ?></label>
                </div>
            </details>
        </div>
        <?php
    }

    public function prevent_empty_document_publication( $data, $postarr ) {
        if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) ) {
            return $data;
        }
        if ( '' === trim( wp_strip_all_tags( (string) ( $data['post_content'] ?? '' ) ) ) ) {
            $data['post_status'] = 'draft';
            self::$validation_error = 'document_required';
        }
        return $data;
    }

    public function save_document_metadata( $post_id, $post, $update ) {
        if ( ! $post || self::POST_TYPE !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_pdf_document_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_pdf_document_nonce'] ) ), 'sc_library_save_pdf_document' ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $family_id = isset( $_POST['sc_pdf_document_family'] ) ? absint( wp_unslash( $_POST['sc_pdf_document_family'] ) ) : 0;
        if ( $family_id && term_exists( $family_id, self::TAX_FAMILY ) ) {
            wp_set_object_terms( $post_id, array( $family_id ), self::TAX_FAMILY, false );
        } else {
            $this->assign_default_family( $post_id );
        }
        update_post_meta( $post_id, self::META_VERSION, sanitize_text_field( wp_unslash( $_POST['sc_pdf_document_version'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_PUBLICATION_DATE, sanitize_text_field( wp_unslash( $_POST['sc_pdf_document_publication_date'] ?? '' ) ) );
        $lifecycle = sanitize_key( wp_unslash( $_POST['sc_pdf_document_lifecycle'] ?? 'current' ) );
        update_post_meta( $post_id, self::META_LIFECYCLE, in_array( $lifecycle, array( 'current', 'superseded', 'archived', 'historical' ), true ) ? $lifecycle : 'current' );
        $reviewed = isset( $_POST['sc_pdf_document_reviewed'] ) ? 1 : 0;
        update_post_meta( $post_id, self::META_REVIEWED, $reviewed );
        if ( $reviewed && 'ready_review' === self::status( $post_id ) ) {
            update_post_meta( $post_id, self::META_STATUS, 'reviewed' );
        }
        if ( 'publish' === $post->post_status && in_array( self::status( $post_id ), array( 'ready_review', 'reviewed' ), true ) ) {
            update_post_meta( $post_id, self::META_STATUS, 'published' );
        }
    }

    public function redirect_with_status( $location, $post_id ) {
        if ( self::$validation_error ) {
            $location = add_query_arg( 'sc_pdf_error', self::$validation_error, $location );
            self::$validation_error = '';
        }
        return $location;
    }

    public function enqueue_admin_assets( $hook ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_editor = self::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true );
        $is_admin_page = false !== strpos( (string) $screen->id, 'sc-library-pdf-document' );
        if ( ! $is_editor && ! $is_admin_page ) {
            return;
        }
        wp_enqueue_style( 'sc-library-foundation-pages-admin', SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css', array(), $this->version() );
        if ( ! $is_editor ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'sc-library-foundation-pages-admin', SC_LIBRARY_URL . 'assets/js/sc-library-foundation-pages-admin.js', array( 'jquery' ), $this->version(), true );
        wp_localize_script(
            'sc-library-foundation-pages-admin',
            'SCLibraryPdfDocument',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_library_pdf_document_extract' ),
                'postId' => get_the_ID(),
                'autoExtract' => isset( $_GET['sc_auto_extract'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sc_auto_extract'] ) ),
                'pdfJsUrl' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/build/pdf.mjs',
                'workerUrl' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/build/pdf.worker.mjs',
                'cMapUrl' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/cmaps/',
                'fontUrl' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/standard_fonts/',
                'wasmUrl' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/wasm/',
            )
        );
    }

    public function editor_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }
        if ( isset( $_GET['sc_pdf_error'] ) && 'document_required' === sanitize_key( wp_unslash( $_GET['sc_pdf_error'] ) ) ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Document not published.', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html__( 'Create or enter the readable document content first. The record remains a draft.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }

    public function columns( $columns ) {
        return array(
            'cb' => $columns['cb'] ?? '<input type="checkbox">',
            'title' => __( 'Document', 'sustainable-catalyst-library' ),
            'sc_family' => __( 'Family', 'sustainable-catalyst-library' ),
            'sc_pdf' => __( 'PDF', 'sustainable-catalyst-library' ),
            'sc_conversion' => __( 'Readable document', 'sustainable-catalyst-library' ),
            'sc_lifecycle' => __( 'Status', 'sustainable-catalyst-library' ),
            'date' => $columns['date'] ?? __( 'Date', 'sustainable-catalyst-library' ),
        );
    }

    public function column_content( $column, $post_id ) {
        if ( 'sc_family' === $column ) {
            $terms = wp_get_object_terms( $post_id, self::TAX_FAMILY );
            echo ! is_wp_error( $terms ) && $terms ? esc_html( $terms[0]->name ) : esc_html__( 'Unassigned', 'sustainable-catalyst-library' );
        } elseif ( 'sc_pdf' === $column ) {
            $pdf = SC_Library_Foundation_Pages::get_pdf_health( $post_id );
            echo '<strong class="sc-admin-state ' . ( 'ready' === $pdf['status'] ? 'is-ready' : 'needs-attention' ) . '">' . ( 'ready' === $pdf['status'] ? esc_html__( 'Ready', 'sustainable-catalyst-library' ) : esc_html__( 'Needs PDF', 'sustainable-catalyst-library' ) ) . '</strong>';
            if ( ! empty( $pdf['filename'] ) ) {
                echo '<span class="sc-admin-state-detail">' . esc_html( $pdf['filename'] ) . '</span>';
            }
        } elseif ( 'sc_conversion' === $column ) {
            $status = self::status( $post_id );
            echo '<strong class="sc-admin-state ' . ( in_array( $status, array( 'ready_review', 'reviewed', 'published' ), true ) ? 'is-ready' : 'needs-attention' ) . '">' . esc_html( self::status_label( $status ) ) . '</strong>';
            $pages = absint( get_post_meta( $post_id, self::META_PAGE_COUNT, true ) );
            if ( $pages ) {
                echo '<span class="sc-admin-state-detail">' . esc_html( sprintf( _n( '%d page', '%d pages', $pages, 'sustainable-catalyst-library' ), $pages ) ) . '</span>';
            }
        } elseif ( 'sc_lifecycle' === $column ) {
            echo esc_html( ucfirst( get_post_meta( $post_id, self::META_LIFECYCLE, true ) ?: 'current' ) );
        }
    }

    public function post_states( $states, $post ) {
        if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
            $status = self::status( $post->ID );
            if ( ! in_array( $status, array( 'ready_review', 'reviewed', 'published' ), true ) ) {
                $states['sc_pdf_conversion'] = self::status_label( $status );
            }
        }
        return $states;
    }

    public function media_row_actions( $actions, $post ) {
        if ( ! $post instanceof WP_Post || 'application/pdf' !== $post->post_mime_type || ! current_user_can( 'edit_posts' ) ) {
            return $actions;
        }
        $existing = $this->document_for_attachment( $post->ID );
        if ( $existing ) {
            $actions['sc_pdf_document'] = '<a href="' . esc_url( get_edit_post_link( $existing, 'raw' ) ) . '">' . esc_html__( 'Edit Knowledge Document', 'sustainable-catalyst-library' ) . '</a>';
        } else {
            $url = wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_create_document_from_pdf', 'attachment_id' => $post->ID ), admin_url( 'admin-post.php' ) ), 'sc_library_create_document_from_pdf_' . $post->ID );
            $actions['sc_pdf_document'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Create Knowledge Document', 'sustainable-catalyst-library' ) . '</a>';
        }
        return $actions;
    }

    public function admin_pages() {
        add_submenu_page( 'sc-library', __( 'PDF Document Health', 'sustainable-catalyst-library' ), __( 'PDF Document Health', 'sustainable-catalyst-library' ), 'manage_options', 'sc-library-pdf-document-health', array( $this, 'health_page' ) );
        add_submenu_page( 'sc-library', __( 'Import PDFs', 'sustainable-catalyst-library' ), __( 'Import PDFs', 'sustainable-catalyst-library' ), 'upload_files', 'sc-library-pdf-document-import', array( $this, 'import_page' ) );
    }

    public function health_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $ready = 0;
        $ocr = 0;
        foreach ( $ids as $id ) {
            $status = self::status( $id );
            if ( in_array( $status, array( 'ready_review', 'reviewed', 'published' ), true ) ) {
                $ready++;
            }
            if ( 'needs_ocr' === $status ) {
                $ocr++;
            }
        }
        ?>
        <div class="wrap sc-pdf-document-health">
            <h1><?php esc_html_e( 'PDF Document Health', 'sustainable-catalyst-library' ); ?></h1>
            <div class="sc-health-cards"><div><strong><?php echo esc_html( count( $ids ) ); ?></strong><span><?php esc_html_e( 'Records', 'sustainable-catalyst-library' ); ?></span></div><div><strong><?php echo esc_html( $ready ); ?></strong><span><?php esc_html_e( 'Ready', 'sustainable-catalyst-library' ); ?></span></div><div><strong><?php echo esc_html( count( $ids ) - $ready ); ?></strong><span><?php esc_html_e( 'Need attention', 'sustainable-catalyst-library' ); ?></span></div><div><strong><?php echo esc_html( $ocr ); ?></strong><span><?php esc_html_e( 'Need OCR', 'sustainable-catalyst-library' ); ?></span></div></div>
            <table class="widefat striped sc-health-table"><tbody><tr><th><?php esc_html_e( 'Schema', 'sustainable-catalyst-library' ); ?></th><td><?php echo esc_html( self::SCHEMA ); ?></td></tr><tr><th><?php esc_html_e( 'Route version', 'sustainable-catalyst-library' ); ?></th><td><?php echo esc_html( get_option( 'sc_library_pdf_document_route_version', __( 'Not recorded', 'sustainable-catalyst-library' ) ) ); ?></td></tr><tr><th><?php esc_html_e( 'Browser extraction', 'sustainable-catalyst-library' ); ?></th><td><?php echo is_file( dirname( __DIR__ ) . '/assets/vendor/pdfjs/build/pdf.mjs' ) ? esc_html__( 'PDF.js available', 'sustainable-catalyst-library' ) : esc_html__( 'Missing', 'sustainable-catalyst-library' ); ?></td></tr><tr><th><?php esc_html_e( 'Server extraction', 'sustainable-catalyst-library' ); ?></th><td><?php echo $this->pdftotext_binary() ? esc_html__( 'pdftotext available', 'sustainable-catalyst-library' ) : esc_html__( 'Browser extraction will be used', 'sustainable-catalyst-library' ); ?></td></tr></tbody></table>
            <div class="sc-health-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'sc_library_repair_pdf_document_routes' ); ?><input type="hidden" name="action" value="sc_library_repair_pdf_document_routes"><?php submit_button( __( 'Repair Document Routes', 'sustainable-catalyst-library' ), 'primary', 'submit', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'sc_library_normalize_pdf_documents' ); ?><input type="hidden" name="action" value="sc_library_normalize_pdf_documents"><?php submit_button( __( 'Normalize Existing Records', 'sustainable-catalyst-library' ), 'secondary', 'submit', false ); ?></form></div>
            <h2><?php esc_html_e( 'Records', 'sustainable-catalyst-library' ); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Family', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Conversion', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Action', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody><?php foreach ( $ids as $id ) : $terms = wp_get_object_terms( $id, self::TAX_FAMILY ); ?><tr><td><strong><?php echo esc_html( get_the_title( $id ) ?: __( '(Untitled)', 'sustainable-catalyst-library' ) ); ?></strong></td><td><?php echo ! is_wp_error( $terms ) && $terms ? esc_html( $terms[0]->name ) : esc_html__( 'Unassigned', 'sustainable-catalyst-library' ); ?></td><td><?php echo esc_html( self::status_label( self::status( $id ) ) ); ?></td><td><a class="button" href="<?php echo esc_url( get_edit_post_link( $id, 'raw' ) ); ?>"><?php esc_html_e( 'Open', 'sustainable-catalyst-library' ); ?></a></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php
    }

    public function import_page() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }
        $query = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
        $families = get_terms( array( 'taxonomy' => self::TAX_FAMILY, 'hide_empty' => false ) );
        ?>
        <div class="wrap sc-pdf-import"><h1><?php esc_html_e( 'Import Media Library PDFs', 'sustainable-catalyst-library' ); ?></h1><p><?php esc_html_e( 'Create draft Knowledge Library records from existing PDF attachments. Open each draft to convert and review it.', 'sustainable-catalyst-library' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'sc_library_import_pdf_documents' ); ?><input type="hidden" name="action" value="sc_library_import_pdf_documents"><p><label><strong><?php esc_html_e( 'Family', 'sustainable-catalyst-library' ); ?></strong><br><select name="family_id"><?php if ( ! is_wp_error( $families ) ) : foreach ( $families as $family ) : ?><option value="<?php echo esc_attr( $family->term_id ); ?>" <?php selected( $family->slug, self::DEFAULT_FAMILY ); ?>><?php echo esc_html( $family->name ); ?></option><?php endforeach; endif; ?></select></label></p><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" data-sc-select-all-pdfs></td><th><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Record', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody><?php while ( $query->have_posts() ) : $query->the_post(); $attachment_id = get_the_ID(); $existing = $this->document_for_attachment( $attachment_id ); ?><tr><th class="check-column"><input type="checkbox" name="attachment_ids[]" value="<?php echo esc_attr( $attachment_id ); ?>" <?php disabled( (bool) $existing ); ?>></th><td><strong><?php the_title(); ?></strong><br><?php echo esc_html( basename( (string) get_attached_file( $attachment_id ) ) ); ?></td><td><?php if ( $existing ) : ?><a href="<?php echo esc_url( get_edit_post_link( $existing, 'raw' ) ); ?>"><?php esc_html_e( 'Edit existing record', 'sustainable-catalyst-library' ); ?></a><?php else : esc_html_e( 'Not imported', 'sustainable-catalyst-library' ); endif; ?></td></tr><?php endwhile; wp_reset_postdata(); ?></tbody></table><?php submit_button( __( 'Create Draft Document Records', 'sustainable-catalyst-library' ) ); ?></form></div>
        <?php
    }

    public function create_from_attachment() {
        $attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0;
        check_admin_referer( 'sc_library_create_document_from_pdf_' . $attachment_id );
        if ( ! current_user_can( 'edit_posts' ) || ! $this->valid_pdf( $attachment_id ) ) {
            wp_die( esc_html__( 'The selected attachment cannot be converted.', 'sustainable-catalyst-library' ) );
        }
        $document_id = $this->document_for_attachment( $attachment_id );
        if ( ! $document_id ) {
            $document_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => $this->attachment_title( $attachment_id ) ), true );
            if ( is_wp_error( $document_id ) ) {
                wp_die( esc_html( $document_id->get_error_message() ) );
            }
            $this->write_pdf_meta( $document_id, $attachment_id );
            update_post_meta( $document_id, self::META_STATUS, 'pending' );
            update_post_meta( $document_id, self::META_LIFECYCLE, 'current' );
            $this->assign_default_family( $document_id );
        }
        wp_safe_redirect( add_query_arg( 'sc_auto_extract', '1', get_edit_post_link( $document_id, 'raw' ) ) );
        exit;
    }

    public function import_documents() {
        check_admin_referer( 'sc_library_import_pdf_documents' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You are not allowed to import PDFs.', 'sustainable-catalyst-library' ) );
        }
        $ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['attachment_ids'] ) ) : array();
        $family_id = isset( $_POST['family_id'] ) ? absint( wp_unslash( $_POST['family_id'] ) ) : 0;
        foreach ( array_unique( array_filter( $ids ) ) as $attachment_id ) {
            if ( ! $this->valid_pdf( $attachment_id ) || $this->document_for_attachment( $attachment_id ) ) {
                continue;
            }
            $document_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => $this->attachment_title( $attachment_id ) ), true );
            if ( is_wp_error( $document_id ) ) {
                continue;
            }
            $this->write_pdf_meta( $document_id, $attachment_id );
            update_post_meta( $document_id, self::META_STATUS, 'pending' );
            update_post_meta( $document_id, self::META_LIFECYCLE, 'current' );
            if ( $family_id ) {
                wp_set_object_terms( $document_id, array( $family_id ), self::TAX_FAMILY, false );
            } else {
                $this->assign_default_family( $document_id );
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-pdf-document-import' ) );
        exit;
    }

    public function repair_routes() {
        check_admin_referer( 'sc_library_repair_pdf_document_routes' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair routes.', 'sustainable-catalyst-library' ) );
        }
        flush_rewrite_rules( false );
        update_option( 'sc_library_pdf_document_route_version', self::ROUTE_VERSION, false );
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-pdf-document-health' ) );
        exit;
    }

    public function normalize_existing_records() {
        check_admin_referer( 'sc_library_normalize_pdf_documents' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to normalize records.', 'sustainable-catalyst-library' ) );
        }
        delete_option( 'sc_library_pdf_document_migration_version' );
        $this->migrate_existing_records();
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-pdf-document-health' ) );
        exit;
    }

    public function ajax_prepare() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $this->valid_pdf( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Select a valid PDF first.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $this->write_pdf_meta( $post_id, $attachment_id );
        $this->assign_default_family( $post_id );
        update_post_meta( $post_id, self::META_STATUS, 'extracting' );
        update_post_meta( $post_id, self::META_REVIEWED, 0 );
        $file = get_attached_file( $attachment_id );
        $server = $file ? $this->server_extract( $file, $post_id ) : null;
        if ( is_array( $server ) && ! empty( $server['pages'] ) ) {
            wp_send_json_success( $this->store_pages( $post_id, $attachment_id, $server['pages'], $server['method'] ) );
        }
        $this->reset_buffer( $post_id );
        wp_send_json_success( array( 'status' => 'browser_required', 'pdfUrl' => wp_get_attachment_url( $attachment_id ) ) );
    }

    public function ajax_store_chunk() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $this->valid_pdf( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'The PDF attachment is invalid.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $pages = json_decode( wp_unslash( $_POST['pages'] ?? '[]' ), true );
        if ( ! is_array( $pages ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid extracted page data.', 'sustainable-catalyst-library' ) ), 400 );
        }
        if ( '1' === sanitize_text_field( wp_unslash( $_POST['reset'] ?? '0' ) ) ) {
            $this->reset_buffer( $post_id );
        }
        $path = $this->buffer_path( $post_id );
        $handle = fopen( $path, 'ab' );
        if ( ! $handle ) {
            wp_send_json_error( array( 'message' => __( 'Could not create the extraction buffer.', 'sustainable-catalyst-library' ) ), 500 );
        }
        foreach ( $pages as $page ) {
            $number = absint( $page['page'] ?? 0 );
            if ( $number ) {
                fwrite( $handle, wp_json_encode( array( 'page' => $number, 'text' => self::normalize_text( $page['text'] ?? '' ) ), JSON_UNESCAPED_UNICODE ) . "\n" );
            }
        }
        fclose( $handle );
        wp_send_json_success( array( 'stored' => count( $pages ) ) );
    }

    public function ajax_finalize() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        $path = $this->buffer_path( $post_id );
        if ( ! is_file( $path ) ) {
            wp_send_json_error( array( 'message' => __( 'No extracted pages were received.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $pages = array();
        foreach ( file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
            $page = json_decode( $line, true );
            if ( is_array( $page ) && ! empty( $page['page'] ) ) {
                $pages[ absint( $page['page'] ) ] = $page;
            }
        }
        @unlink( $path );
        ksort( $pages );
        wp_send_json_success( $this->store_pages( $post_id, $attachment_id, array_values( $pages ), 'pdfjs-6.1.200' ) );
    }

    public function ajax_mark_failure() {
        $post_id = $this->verify_ajax();
        $code = sanitize_key( wp_unslash( $_POST['failure_code'] ?? 'failed' ) );
        if ( ! in_array( $code, array( 'needs_ocr', 'password_protected', 'failed' ), true ) ) {
            $code = 'failed';
        }
        update_post_meta( $post_id, self::META_STATUS, $code );
        wp_send_json_success( array( 'status' => $code, 'message' => self::status_message( $code ) ) );
    }

    public function bridge_existing_shortcodes( $return, $tag, $attr, $match ) {
        if ( null !== $return ) {
            return $return;
        }
        $attr = is_array( $attr ) ? $attr : array();
        if ( 'sc_foundations_library' === $tag || 'sc_foundation_documents' === $tag ) {
            $attr['family'] = self::DEFAULT_FAMILY;
            return $this->shortcode_library( $attr );
        }
        if ( 'sc_library' !== $tag ) {
            return $return;
        }
        $mode = sanitize_key( $attr['mode'] ?? '' );
        $collection = sanitize_title( $attr['collection'] ?? '' );
        if ( ! in_array( $mode, array( 'documentation', 'documents', 'document', 'foundation', 'foundations' ), true ) || ! $collection ) {
            return $return;
        }
        if ( 'foundations' === $collection ) {
            $collection = self::DEFAULT_FAMILY;
        }
        if ( ! term_exists( $collection, self::TAX_FAMILY ) ) {
            return $return;
        }
        return $this->shortcode_library( array( 'family' => $collection, 'per_page' => $attr['per_page'] ?? 12, 'search' => $attr['search'] ?? 'true', 'show_header' => $attr['show_header'] ?? 'false', 'title' => $attr['title'] ?? '', 'intro' => $attr['intro'] ?? '' ) );
    }

    public function shortcode_library( $atts ) {
        $atts = shortcode_atts( array( 'family' => '', 'per_page' => '12', 'search' => 'true', 'show_header' => 'true', 'title' => 'PDF Document Library', 'intro' => '' ), $atts, 'sc_pdf_document_library' );
        wp_enqueue_style( 'sc-library-foundation-pages' );
        $family = sanitize_title( $atts['family'] );
        $paged = max( 1, absint( $_GET['document_page'] ?? 1 ) );
        $search = sanitize_text_field( wp_unslash( $_GET['document_q'] ?? '' ) );
        $url = get_queried_object_id() ? get_permalink( get_queried_object_id() ) : remove_query_arg( array( 'document_q', 'document_page' ) );
        $args = array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 50, absint( $atts['per_page'] ) ) ), 'paged' => $paged, 's' => $search, 'orderby' => 'title', 'order' => 'ASC' );
        if ( $family ) {
            $args['tax_query'] = array( array( 'taxonomy' => self::TAX_FAMILY, 'field' => 'slug', 'terms' => $family ) );
        }
        $documents = new WP_Query( $args );
        $term = $family ? get_term_by( 'slug', $family, self::TAX_FAMILY ) : null;
        ob_start();
        ?>
        <section class="sc-document-library">
            <?php if ( filter_var( $atts['show_header'], FILTER_VALIDATE_BOOLEAN ) ) : ?><header class="sc-document-library__header"><p class="sc-document-library__kicker"><?php echo esc_html( $term ? $term->name : __( 'PDF Document Library', 'sustainable-catalyst-library' ) ); ?></p><?php if ( $atts['title'] ) : ?><h2><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?><?php if ( $atts['intro'] ) : ?><p><?php echo esc_html( $atts['intro'] ); ?></p><?php endif; ?></header><?php endif; ?>
            <?php if ( filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN ) ) : ?><form class="sc-document-library__search" method="get" action="<?php echo esc_url( $url ); ?>" role="search"><label for="sc-document-search"><?php esc_html_e( 'Search documents', 'sustainable-catalyst-library' ); ?></label><div><input id="sc-document-search" type="search" name="document_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search titles, summaries, and document text', 'sustainable-catalyst-library' ); ?>"><button type="submit"><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></button></div><?php if ( $search ) : ?><a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Clear search', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></form><?php endif; ?>
            <?php if ( $documents->have_posts() ) : ?><div class="sc-document-library__records"><?php while ( $documents->have_posts() ) : $documents->the_post(); $id = get_the_ID(); $pdf = SC_Library_Foundation_Pages::get_pdf_health( $id ); $terms = wp_get_object_terms( $id, self::TAX_FAMILY ); ?><article class="sc-document-library__record"><div class="sc-document-library__meta"><span><?php echo ! is_wp_error( $terms ) && $terms ? esc_html( $terms[0]->name ) : esc_html__( 'PDF Document', 'sustainable-catalyst-library' ); ?></span><?php if ( get_post_meta( $id, self::META_VERSION, true ) ) : ?><span><?php echo esc_html( sprintf( __( 'Version %s', 'sustainable-catalyst-library' ), get_post_meta( $id, self::META_VERSION, true ) ) ); ?></span><?php endif; ?><span><?php echo esc_html( ucfirst( get_post_meta( $id, self::META_LIFECYCLE, true ) ?: 'current' ) ); ?></span></div><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?><div class="sc-document-library__actions"><a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read document', 'sustainable-catalyst-library' ); ?> →</a><?php if ( 'ready' === $pdf['status'] ) : ?><a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?></a><a href="<?php echo esc_url( $pdf['url'] ); ?>" download="<?php echo esc_attr( $pdf['filename'] ); ?>"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></div></article><?php endwhile; ?></div><?php if ( $documents->max_num_pages > 1 ) : $base = add_query_arg( array( 'document_page' => 999999999, 'document_q' => $search ?: false ), $url ); ?><nav class="sc-document-library__pagination"><?php echo wp_kses_post( paginate_links( array( 'base' => str_replace( '999999999', '%#%', $base ), 'current' => $paged, 'total' => $documents->max_num_pages ) ) ); ?></nav><?php endif; ?><?php else : ?><div class="sc-document-library__empty"><strong><?php esc_html_e( 'No documents found.', 'sustainable-catalyst-library' ); ?></strong></div><?php endif; wp_reset_postdata(); ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function template_include( $template ) {
        if ( is_singular( self::POST_TYPE ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/single-sc_pdf_document.php';
            return is_file( $candidate ) ? $candidate : $template;
        }
        if ( is_tax( self::TAX_FAMILY ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/taxonomy-sc_document_family.php';
            return is_file( $candidate ) ? $candidate : $template;
        }
        return $template;
    }

    public function query_vars( $vars ) {
        if ( ! in_array( 'sc_legacy_foundation_route', $vars, true ) ) {
            $vars[] = 'sc_legacy_foundation_route';
        }
        return $vars;
    }

    public function legacy_redirect() {
        if ( is_singular( self::POST_TYPE ) && get_query_var( 'sc_legacy_foundation_route' ) ) {
            wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
            exit;
        }
    }

    public function body_classes( $classes ) {
        if ( is_singular( self::POST_TYPE ) ) {
            $classes[] = 'sc-pdf-document-page';
        }
        return $classes;
    }

    public static function render_single( $post_id ) {
        wp_enqueue_style( 'sc-library-foundation-pages' );
        $post = get_post( $post_id );
        $pdf = SC_Library_Foundation_Pages::get_pdf_health( $post_id );
        $terms = wp_get_object_terms( $post_id, self::TAX_FAMILY );
        $family = ! is_wp_error( $terms ) && $terms ? $terms[0] : null;
        $family_url = $family ? get_term_link( $family ) : home_url( '/foundations/' );
        if ( is_wp_error( $family_url ) ) {
            $family_url = home_url( '/foundations/' );
        }
        $size = '';
        if ( 'ready' === $pdf['status'] ) {
            $file = get_attached_file( $pdf['attachment_id'] );
            if ( $file && is_file( $file ) ) {
                $size = size_format( filesize( $file ) );
            }
        }
        ob_start();
        ?>
        <article class="cc-research-library-brand cc-rl-v2 sc-pdf-document">
            <header class="cc-rl-hero"><p class="cc-rl-kicker"><?php echo esc_html( $family ? $family->name : __( 'PDF Document Library', 'sustainable-catalyst-library' ) ); ?></p><h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1><?php if ( has_excerpt( $post_id ) ) : ?><p class="cc-rl-lede"><?php echo esc_html( get_the_excerpt( $post_id ) ); ?></p><?php endif; ?><div class="cc-rl-hero-actions"><a class="cc-rl-button cc-rl-button-primary" href="#read-document"><?php esc_html_e( 'Read Document', 'sustainable-catalyst-library' ); ?></a><?php if ( 'ready' === $pdf['status'] ) : ?><a class="cc-rl-button" href="#original-pdf"><?php esc_html_e( 'View Original PDF', 'sustainable-catalyst-library' ); ?></a><a class="cc-rl-button" href="<?php echo esc_url( $pdf['url'] ); ?>" download="<?php echo esc_attr( $pdf['filename'] ); ?>"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></a><?php endif; ?><a class="cc-rl-button" href="<?php echo esc_url( $family_url ); ?>"><?php esc_html_e( 'Back to Family', 'sustainable-catalyst-library' ); ?></a></div><div class="sc-pdf-document__metadata"><?php if ( get_post_meta( $post_id, self::META_VERSION, true ) ) : ?><span><?php echo esc_html( sprintf( __( 'Version %s', 'sustainable-catalyst-library' ), get_post_meta( $post_id, self::META_VERSION, true ) ) ); ?></span><?php endif; ?><span><?php echo esc_html( ucfirst( get_post_meta( $post_id, self::META_LIFECYCLE, true ) ?: 'current' ) ); ?></span><?php if ( get_post_meta( $post_id, self::META_PAGE_COUNT, true ) ) : ?><span><?php echo esc_html( sprintf( _n( '%d page', '%d pages', absint( get_post_meta( $post_id, self::META_PAGE_COUNT, true ) ), 'sustainable-catalyst-library' ), absint( get_post_meta( $post_id, self::META_PAGE_COUNT, true ) ) ) ); ?></span><?php endif; ?></div></header>
            <section id="read-document" class="cc-rl-section cc-rl-section-white"><div class="cc-rl-section-heading"><p class="cc-rl-section-kicker"><?php esc_html_e( 'Readable Document', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Document Text', 'sustainable-catalyst-library' ); ?></h2></div><div class="sc-pdf-document__content"><?php echo apply_filters( 'the_content', $post ? $post->post_content : '' ); ?></div></section>
            <section id="original-pdf" class="cc-rl-section cc-rl-section-cream"><div class="cc-rl-section-heading"><p class="cc-rl-section-kicker"><?php esc_html_e( 'Authoritative Source', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Original PDF', 'sustainable-catalyst-library' ); ?></h2></div><?php if ( 'ready' === $pdf['status'] ) : ?><p class="sc-pdf-document__file"><strong><?php echo esc_html( $pdf['filename'] ); ?></strong><?php if ( $size ) : ?><span><?php echo esc_html( $size ); ?></span><?php endif; ?></p><div class="sc-pdf-document__reader"><object data="<?php echo esc_url( $pdf['url'] ); ?>#view=FitH&amp;toolbar=1&amp;navpanes=0" type="application/pdf"><div class="sc-document-library__empty"><p><?php esc_html_e( 'This browser could not display the PDF inside the page.', 'sustainable-catalyst-library' ); ?></p><p><a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PDF in a new tab', 'sustainable-catalyst-library' ); ?></a></p></div></object></div><div class="cc-rl-hero-actions sc-pdf-document__pdf-actions"><a class="cc-rl-button cc-rl-button-primary" href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?></a><a class="cc-rl-button" href="<?php echo esc_url( $pdf['url'] ); ?>" download="<?php echo esc_attr( $pdf['filename'] ); ?>"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></a></div><?php else : ?><div class="sc-document-library__empty"><strong><?php esc_html_e( 'PDF unavailable', 'sustainable-catalyst-library' ); ?></strong></div><?php endif; ?></section>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function status( $post_id ) {
        $status = sanitize_key( get_post_meta( $post_id, self::META_STATUS, true ) );
        return $status ?: ( trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) ? 'legacy_content' : 'pending' );
    }

    public static function status_label( $status ) {
        $labels = array( 'pending' => 'Not converted', 'extracting' => 'Converting', 'ready_review' => 'Ready for review', 'reviewed' => 'Reviewed', 'published' => 'Published', 'needs_ocr' => 'Needs OCR', 'password_protected' => 'Password protected', 'failed' => 'Conversion failed', 'legacy_content' => 'Existing content' );
        return __( $labels[ $status ] ?? 'Not converted', 'sustainable-catalyst-library' );
    }

    public static function status_message( $status ) {
        $messages = array( 'pending' => 'Select the PDF and create the readable document.', 'extracting' => 'The PDF is being scanned for text and page structure.', 'ready_review' => 'Review the generated article against the PDF before publishing.', 'reviewed' => 'The generated article has been reviewed.', 'published' => 'The readable document and original PDF are published together.', 'needs_ocr' => 'Little or no extractable text was found. This appears to be an image-based PDF.', 'password_protected' => 'The PDF is password protected.', 'failed' => 'The PDF could not be converted. The original file remains attached.', 'legacy_content' => 'Existing content is present. Re-create it from the PDF when appropriate.' );
        return __( $messages[ $status ] ?? $messages['pending'], 'sustainable-catalyst-library' );
    }

    private function verify_ajax() {
        check_ajax_referer( 'sc_library_pdf_document_extract', 'nonce' );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this record.', 'sustainable-catalyst-library' ) ), 403 );
        }
        return $post_id;
    }

    private function server_extract( $file, $post_id ) {
        $external = apply_filters( 'sc_library_pdf_to_document_extraction', null, $file, $post_id );
        if ( is_array( $external ) && ! empty( $external['pages'] ) ) {
            return array( 'pages' => $external['pages'], 'method' => $external['method'] ?? 'external-extractor' );
        }
        $binary = $this->pdftotext_binary();
        if ( ! $binary || ! function_exists( 'proc_open' ) ) {
            return null;
        }
        $process = @proc_open( array( $binary, '-layout', '-enc', 'UTF-8', $file, '-' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        if ( ! is_resource( $process ) ) {
            return null;
        }
        fclose( $pipes[0] );
        $output = stream_get_contents( $pipes[1] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $exit = proc_close( $process );
        if ( 0 !== $exit || strlen( preg_replace( '/\s+/u', '', (string) $output ) ) < self::MIN_TEXT ) {
            return null;
        }
        $pages = array();
        foreach ( preg_split( '/\f/u', $output ) as $index => $chunk ) {
            $pages[] = array( 'page' => $index + 1, 'text' => self::normalize_text( $chunk ) );
        }
        return array( 'pages' => $pages, 'method' => 'pdftotext' );
    }

    private function store_pages( $post_id, $attachment_id, $pages, $method ) {
        $clean = array();
        $raw = '';
        foreach ( $pages as $index => $page ) {
            $text = self::normalize_text( $page['text'] ?? '' );
            $clean[] = array( 'page' => absint( $page['page'] ?? $index + 1 ), 'text' => $text );
            $raw .= ( $raw ? "\n\n" : '' ) . $text;
        }
        update_post_meta( $post_id, self::META_PAGE_COUNT, count( $clean ) );
        update_post_meta( $post_id, self::META_METHOD, sanitize_text_field( $method ) );
        update_post_meta( $post_id, self::META_EXTRACTED_AT, current_time( 'mysql' ) );
        $file = get_attached_file( $attachment_id );
        if ( $file && is_file( $file ) ) {
            update_post_meta( $post_id, self::META_CHECKSUM, hash_file( 'sha256', $file ) );
        }
        if ( strlen( preg_replace( '/\s+/u', '', $raw ) ) < self::MIN_TEXT ) {
            update_post_meta( $post_id, self::META_STATUS, 'needs_ocr' );
            return array( 'status' => 'needs_ocr', 'message' => self::status_message( 'needs_ocr' ) );
        }
        $content = $this->pages_to_html( $clean );
        $title = $this->derive_title( $post_id, $clean );
        $summary = wp_trim_words( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $raw ) ), 45, '…' );
        $update = array( 'ID' => $post_id, 'post_content' => $content, 'post_excerpt' => $summary );
        if ( ! trim( get_the_title( $post_id ) ) || 'Auto Draft' === get_the_title( $post_id ) ) {
            $update['post_title'] = $title;
        }
        wp_update_post( wp_slash( $update ) );
        $map = array();
        $offset = 0;
        foreach ( $clean as $page ) {
            $length = strlen( $page['text'] );
            $map[] = array( 'page' => $page['page'], 'start' => $offset, 'length' => $length );
            $offset += $length + 2;
        }
        update_post_meta( $post_id, self::META_RAW_TEXT, $raw );
        update_post_meta( $post_id, self::META_PAGE_MAP, wp_json_encode( $map ) );
        update_post_meta( $post_id, self::META_STATUS, 'ready_review' );
        update_post_meta( $post_id, self::META_REVIEWED, 0 );
        return array( 'status' => 'ready_review', 'message' => self::status_message( 'ready_review' ), 'content' => $content, 'title' => $update['post_title'] ?? get_the_title( $post_id ), 'summary' => $summary, 'pages' => count( $clean ) );
    }

    private function pages_to_html( $pages ) {
        $html = '';
        foreach ( $pages as $page ) {
            $html .= '<section class="sc-pdf-document-page" data-pdf-page="' . esc_attr( $page['page'] ) . '"><p class="sc-pdf-document-page__number">' . esc_html( sprintf( __( 'Page %d', 'sustainable-catalyst-library' ), $page['page'] ) ) . '</p>';
            $paragraph = array();
            foreach ( preg_split( '/\R/u', $page['text'] ) as $line ) {
                $line = trim( preg_replace( '/\s+/u', ' ', $line ) );
                if ( '' === $line ) {
                    $html .= $this->paragraph( $paragraph );
                    $paragraph = array();
                } elseif ( $this->is_heading( $line ) ) {
                    $html .= $this->paragraph( $paragraph );
                    $paragraph = array();
                    $tag = preg_match( '/^\d+(?:\.\d+)*[\s\.\-]+/', $line ) ? 'h3' : 'h2';
                    $html .= '<' . $tag . '>' . esc_html( $line ) . '</' . $tag . '>';
                } else {
                    $paragraph[] = $line;
                    if ( preg_match( '/[.!?;:]$/u', $line ) && strlen( implode( ' ', $paragraph ) ) > 180 ) {
                        $html .= $this->paragraph( $paragraph );
                        $paragraph = array();
                    }
                }
            }
            $html .= $this->paragraph( $paragraph ) . '</section>';
        }
        return $html;
    }

    private function paragraph( $parts ) {
        $text = trim( implode( ' ', $parts ) );
        return $text ? '<p>' . esc_html( $text ) . '</p>' : '';
    }

    private function is_heading( $line ) {
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line );
        if ( $length < 3 || $length > 120 || preg_match( '/[.!?]$/u', $line ) ) {
            return false;
        }
        if ( preg_match( '/^(chapter|section|part|appendix)\b/iu', $line ) || preg_match( '/^\d+(?:\.\d+)*[\s\.\-]+\S/u', $line ) ) {
            return true;
        }
        $letters = preg_replace( '/[^\p{L}]/u', '', $line );
        return $letters && strtoupper( $letters ) === $letters && $length <= 80;
    }

    private function derive_title( $post_id, $pages ) {
        $current = trim( get_the_title( $post_id ) );
        if ( $current && 'Auto Draft' !== $current ) {
            return $current;
        }
        foreach ( $pages as $page ) {
            foreach ( preg_split( '/\R/u', $page['text'] ) as $line ) {
                $line = trim( preg_replace( '/\s+/u', ' ', $line ) );
                $length = function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line );
                if ( $length >= 5 && $length <= 160 ) {
                    return sanitize_text_field( $line );
                }
            }
        }
        return __( 'Untitled PDF Document', 'sustainable-catalyst-library' );
    }

    private static function normalize_text( $text ) {
        $text = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), (string) $text );
        $text = preg_replace( '/[\x{00A0}\t]+/u', ' ', $text );
        $text = preg_replace( '/[ ]{2,}/u', ' ', $text );
        return trim( preg_replace( '/\n{3,}/u', "\n\n", $text ) );
    }

    private function pdftotext_binary() {
        foreach ( apply_filters( 'sc_library_pdftotext_candidates', array( '/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext' ) ) as $candidate ) {
            if ( is_file( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }
        return '';
    }

    private function buffer_path( $post_id ) {
        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'sc-library-extraction';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        return trailingslashit( $dir ) . 'document-' . absint( $post_id ) . '-user-' . get_current_user_id() . '.jsonl';
    }

    private function reset_buffer( $post_id ) {
        $path = $this->buffer_path( $post_id );
        if ( is_file( $path ) ) {
            @unlink( $path );
        }
    }

    private function document_for_attachment( $attachment_id ) {
        $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::META_PDF_ID, 'meta_value' => absint( $attachment_id ) ) );
        return $ids ? absint( $ids[0] ) : 0;
    }

    private function write_pdf_meta( $post_id, $attachment_id ) {
        foreach ( array( self::META_PDF_ID, '_sc_library_pdf_attachment_id', '_sc_library_foundation_pdf_attachment_id', '_sc_library_foundation_attachment_id', '_sc_foundation_pdf_attachment_id', 'sc_library_pdf_attachment_id' ) as $key ) {
            update_post_meta( $post_id, $key, $attachment_id );
        }
    }

    private function assign_default_family( $post_id ) {
        $terms = wp_get_object_terms( $post_id, self::TAX_FAMILY, array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $terms ) && $terms ) {
            return;
        }
        $term = get_term_by( 'slug', self::DEFAULT_FAMILY, self::TAX_FAMILY );
        if ( $term ) {
            wp_set_object_terms( $post_id, array( $term->term_id ), self::TAX_FAMILY, false );
        }
    }

    private function migrate_existing_records() {
        if ( '2.2.0' === get_option( 'sc_library_pdf_document_migration_version' ) ) {
            return;
        }
        foreach ( get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $post_id ) {
            $this->assign_default_family( $post_id );
            if ( ! get_post_meta( $post_id, self::META_STATUS, true ) ) {
                update_post_meta( $post_id, self::META_STATUS, trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) ? 'legacy_content' : 'pending' );
            }
            if ( ! get_post_meta( $post_id, self::META_LIFECYCLE, true ) ) {
                update_post_meta( $post_id, self::META_LIFECYCLE, 'current' );
            }
        }
        update_option( 'sc_library_pdf_document_migration_version', '2.2.0', false );
    }

    private function valid_pdf( $attachment_id ) {
        return $attachment_id && 'attachment' === get_post_type( $attachment_id ) && 'application/pdf' === get_post_mime_type( $attachment_id ) && wp_get_attachment_url( $attachment_id );
    }

    private function attachment_title( $attachment_id ) {
        $post = get_post( $attachment_id );
        if ( $post && $post->post_title ) {
            return sanitize_text_field( $post->post_title );
        }
        $file = get_attached_file( $attachment_id );
        return $file ? sanitize_text_field( str_replace( array( '-', '_' ), ' ', pathinfo( basename( $file ), PATHINFO_FILENAME ) ) ) : __( 'PDF Document', 'sustainable-catalyst-library' );
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : '2.6.0';
    }
}
