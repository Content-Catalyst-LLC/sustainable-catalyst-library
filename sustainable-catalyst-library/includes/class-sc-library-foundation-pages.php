<?php
/**
 * Foundation Document Pages.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Foundation_Pages {
    public const POST_TYPE = 'sc_foundation_doc';
    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const SCHEMA = 'sc-library-foundation-page/1.0';
    public const ROUTE_VERSION = '2.1.3';

    private static $allow_foundation_query = false;
    private static $saving_title = false;
    private static $validation_error = '';
    private static $slug_adjusted = false;

    /** @var string[] */
    private static $compatible_pdf_meta_keys = array(
        self::META_PDF_ID,
        '_sc_library_pdf_attachment_id',
        '_sc_library_foundation_pdf_attachment_id',
        '_sc_library_foundation_attachment_id',
        '_sc_foundation_pdf_attachment_id',
        'sc_library_pdf_attachment_id',
    );

    public function __construct() {
        add_filter( 'register_post_type_args', array( $this, 'filter_post_type_args' ), 30, 2 );
        add_action( 'init', array( $this, 'late_post_type_setup' ), 100 );
        add_filter( 'use_block_editor_for_post_type', array( $this, 'use_classic_editor' ), 30, 2 );
        add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 20, 2 );

        add_action( 'edit_form_after_title', array( $this, 'render_inline_pdf_selector' ), 20 );
        add_action( 'do_meta_boxes', array( $this, 'simplify_editor_meta_boxes' ), 100, 3 );
        add_filter( 'wp_insert_post_data', array( $this, 'prevent_publish_without_pdf' ), 40, 2 );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_document' ), 30, 3 );
        add_filter( 'redirect_post_location', array( $this, 'redirect_with_editor_status' ), 40, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'editor_notice' ) );
        add_filter( 'display_post_states', array( $this, 'document_post_states' ), 20, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'document_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_document_column' ), 10, 2 );

        add_action( 'admin_menu', array( $this, 'register_health_page' ), 100 );
        add_action( 'admin_post_sc_library_repair_foundation_routes', array( $this, 'repair_routes' ) );
        add_action( 'admin_post_sc_library_repair_foundation_metadata', array( $this, 'repair_metadata' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_filter( 'template_include', array( $this, 'single_template' ), 100 );
        add_filter( 'body_class', array( $this, 'body_classes' ) );

        add_filter( 'pre_do_shortcode_tag', array( $this, 'bridge_foundations_library_shortcode' ), 20, 4 );
        add_shortcode( 'sc_foundation_documents', array( $this, 'shortcode_foundation_documents' ) );
    }

    public function filter_post_type_args( $args, $post_type ) {
        if ( self::POST_TYPE !== $post_type ) {
            return $args;
        }

        $labels = isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array();
        $args['labels'] = array_merge(
            $labels,
            array(
                'name'               => __( 'Foundation Docs', 'sustainable-catalyst-library' ),
                'singular_name'      => __( 'Foundation Document', 'sustainable-catalyst-library' ),
                'menu_name'          => __( 'Foundation Docs', 'sustainable-catalyst-library' ),
                'add_new'            => __( 'Add New', 'sustainable-catalyst-library' ),
                'add_new_item'       => __( 'Add Foundation Document', 'sustainable-catalyst-library' ),
                'edit_item'          => __( 'Edit Foundation Document', 'sustainable-catalyst-library' ),
                'new_item'           => __( 'New Foundation Document', 'sustainable-catalyst-library' ),
                'view_item'          => __( 'View Foundation Document', 'sustainable-catalyst-library' ),
                'search_items'       => __( 'Search Foundation Docs', 'sustainable-catalyst-library' ),
                'not_found'          => __( 'No Foundation Documents found.', 'sustainable-catalyst-library' ),
                'not_found_in_trash' => __( 'No Foundation Documents found in Trash.', 'sustainable-catalyst-library' ),
                'all_items'          => __( 'All Foundation Docs', 'sustainable-catalyst-library' ),
            )
        );

        $args['public']              = true;
        $args['publicly_queryable']  = true;
        $args['show_ui']             = true;
        $args['show_in_menu']        = 'sc-library';
        $args['show_in_rest']        = true;
        $args['show_in_nav_menus']   = false;
        $args['exclude_from_search'] = true;
        $args['has_archive']         = false;
        $args['hierarchical']        = false;
        $args['taxonomies']          = array();
        $args['supports']            = array( 'title', 'editor', 'revisions' );
        $args['rewrite']             = array(
            'slug'       => 'foundations',
            'with_front' => false,
        );
        $args['query_var']           = true;
        $args['can_export']          = true;
        $args['menu_icon']           = 'dashicons-media-document';

        return $args;
    }

    public function late_post_type_setup() {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        foreach ( array( 'author', 'comments', 'trackbacks', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes', 'post-formats' ) as $support ) {
            remove_post_type_support( self::POST_TYPE, $support );
        }
        add_post_type_support( self::POST_TYPE, 'title' );
        add_post_type_support( self::POST_TYPE, 'editor' );
        add_post_type_support( self::POST_TYPE, 'revisions' );

        foreach ( get_object_taxonomies( self::POST_TYPE ) as $taxonomy ) {
            unregister_taxonomy_for_object_type( $taxonomy, self::POST_TYPE );
        }

        add_rewrite_rule(
            '^foundations/([^/]+)/?$',
            'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
            'top'
        );

        if ( self::ROUTE_VERSION !== get_option( 'sc_library_foundation_pages_rewrite_version' ) ) {
            flush_rewrite_rules( false );
            update_option( 'sc_library_foundation_pages_rewrite_version', self::ROUTE_VERSION, false );
        }
    }

    public function use_classic_editor( $use_block_editor, $post_type ) {
        // Foundation Documents use one stable page-style editor so the PDF selector
        // appears consistently regardless of site-wide Gutenberg settings.
        return self::POST_TYPE === $post_type ? false : $use_block_editor;
    }

    public function title_placeholder( $placeholder, $post ) {
        if ( $post && self::POST_TYPE === $post->post_type ) {
            return __( 'Foundation Document title', 'sustainable-catalyst-library' );
        }
        return $placeholder;
    }

    public function render_inline_pdf_selector( $post ) {
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || $this->is_advanced_editor() ) {
            return;
        }
        ?>
        <div class="sc-foundation-page-inline-panel">
            <div class="sc-foundation-page-inline-panel__heading">
                <span class="dashicons dashicons-pdf" aria-hidden="true"></span>
                <div>
                    <h2><?php esc_html_e( 'Foundation PDF', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php esc_html_e( 'Select one PDF from the Media Library. It will be embedded automatically on the published document page.', 'sustainable-catalyst-library' ); ?></p>
                </div>
            </div>
            <?php $this->render_pdf_meta_box( $post ); ?>
        </div>
        <?php
    }

    public function render_pdf_meta_box( $post ) {
        $health        = self::get_pdf_health( $post->ID );
        $attachment_id = 'ready' === $health['status'] ? $health['attachment_id'] : 0;
        $attachment    = $attachment_id ? get_post( $attachment_id ) : null;
        $filename      = $health['filename'];
        $url           = $health['url'];
        $advanced_url  = add_query_arg( 'sc_foundation_advanced', '1', get_edit_post_link( $post->ID, 'raw' ) );

        wp_nonce_field( 'sc_library_save_foundation_page', 'sc_library_foundation_page_nonce' );
        ?>
        <div class="sc-foundation-page-selector" data-sc-foundation-page-selector data-pdf-required="true">
            <input type="hidden" name="sc_library_foundation_page_pdf_id" value="<?php echo esc_attr( $attachment_id ); ?>" data-sc-foundation-pdf-id>
            <div class="sc-foundation-page-selector__summary" data-sc-foundation-pdf-summary aria-live="polite">
                <?php if ( $attachment_id && $url ) : ?>
                    <strong><?php echo esc_html( $attachment ? $attachment->post_title : $filename ); ?></strong>
                    <span><?php echo esc_html( $filename ); ?></span>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open selected PDF', 'sustainable-catalyst-library' ); ?></a>
                <?php elseif ( 'invalid' === $health['status'] ) : ?>
                    <strong><?php esc_html_e( 'Selected PDF needs repair', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php echo esc_html( $health['message'] ); ?></span>
                <?php else : ?>
                    <strong><?php esc_html_e( 'No PDF selected', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Choose an existing PDF from the WordPress Media Library. A valid PDF is required before publishing.', 'sustainable-catalyst-library' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="sc-foundation-page-selector__actions">
                <button type="button" class="button button-primary" data-sc-foundation-select-pdf><?php esc_html_e( 'Select PDF', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-foundation-remove-pdf <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Remove PDF', 'sustainable-catalyst-library' ); ?></button>
            </div>
            <p class="description">
                <?php esc_html_e( 'A valid PDF is required for publication. The selected file is embedded automatically, with accessible Open and Download controls generated on the public page.', 'sustainable-catalyst-library' ); ?>
            </p>
            <details class="sc-foundation-page-selector__advanced">
                <summary><?php esc_html_e( 'Advanced Library tools', 'sustainable-catalyst-library' ); ?></summary>
                <p><?php esc_html_e( 'Open the advanced editor only when you need legacy PDF extraction, citation, version, or indexing controls.', 'sustainable-catalyst-library' ); ?></p>
                <a class="button" href="<?php echo esc_url( $advanced_url ); ?>"><?php esc_html_e( 'Open advanced editor', 'sustainable-catalyst-library' ); ?></a>
            </details>
        </div>
        <?php
    }

    public function simplify_editor_meta_boxes( $post_type, $context, $post ) {
        if ( self::POST_TYPE !== $post_type || $this->is_advanced_editor() ) {
            return;
        }

        global $wp_meta_boxes;
        if ( empty( $wp_meta_boxes[ self::POST_TYPE ] ) ) {
            return;
        }

        $preserve = array(
            'submitdiv',
            'slugdiv',
            'revisionsdiv',
        );

        foreach ( $wp_meta_boxes[ self::POST_TYPE ] as $box_context => $priorities ) {
            foreach ( $priorities as $priority => $boxes ) {
                if ( ! is_array( $boxes ) ) {
                    continue;
                }
                foreach ( array_keys( $boxes ) as $box_id ) {
                    if ( ! in_array( $box_id, $preserve, true ) ) {
                        remove_meta_box( $box_id, self::POST_TYPE, $box_context );
                    }
                }
            }
        }
    }

    public function prevent_publish_without_pdf( $data, $postarr ) {
        if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
            return $data;
        }

        $target_status = $data['post_status'] ?? '';
        if ( ! in_array( $target_status, array( 'publish', 'future', 'private' ), true ) ) {
            return $data;
        }

        $post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
        $submitted_attachment_id = isset( $_POST['sc_library_foundation_page_pdf_id'] )
            ? absint( wp_unslash( $_POST['sc_library_foundation_page_pdf_id'] ) )
            : 0;
        $attachment_id = $submitted_attachment_id ?: self::get_pdf_attachment_id( $post_id );

        if ( ! self::is_valid_pdf_attachment( $attachment_id ) ) {
            $data['post_status'] = 'draft';
            self::$validation_error = 'missing_pdf';
        }

        return $data;
    }

    public function save_document( $post_id, $post, $update ) {
        if ( self::$saving_title || ! $post || self::POST_TYPE !== $post->post_type ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_foundation_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_foundation_page_nonce'] ) ), 'sc_library_save_foundation_page' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $attachment_id = isset( $_POST['sc_library_foundation_page_pdf_id'] ) ? absint( $_POST['sc_library_foundation_page_pdf_id'] ) : 0;
        if ( ! self::is_valid_pdf_attachment( $attachment_id ) ) {
            $attachment_id = 0;
        }

        foreach ( self::$compatible_pdf_meta_keys as $meta_key ) {
            if ( $attachment_id ) {
                update_post_meta( $post_id, $meta_key, $attachment_id );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        update_post_meta( $post_id, '_sc_library_foundation_pages_only', 1 );
        update_post_meta( $post_id, '_sc_library_foundation_inline_viewer', 1 );
        update_post_meta( $post_id, '_sc_library_foundation_download', 1 );

        if ( $attachment_id && ( '' === trim( $post->post_title ) || 'Auto Draft' === $post->post_title ) ) {
            $attachment = get_post( $attachment_id );
            $title      = $attachment && $attachment->post_title ? $attachment->post_title : pathinfo( basename( (string) get_attached_file( $attachment_id ) ), PATHINFO_FILENAME );
            if ( $title ) {
                self::$saving_title = true;
                wp_update_post(
                    array(
                        'ID'         => $post_id,
                        'post_title' => sanitize_text_field( $title ),
                    )
                );
                self::$saving_title = false;
            }
        }

        $fresh_post = get_post( $post_id );
        if ( $fresh_post && $fresh_post->post_title && $fresh_post->post_name ) {
            $base_slug = sanitize_title( $fresh_post->post_title );
            if ( $base_slug && preg_match( '/^' . preg_quote( $base_slug, '/' ) . '-[0-9]+$/', $fresh_post->post_name ) ) {
                self::$slug_adjusted = true;
            }
        }
    }

    public function redirect_with_editor_status( $location, $post_id ) {
        if ( self::$validation_error ) {
            $location = add_query_arg( 'sc_foundation_error', self::$validation_error, $location );
            self::$validation_error = '';
        }
        if ( self::$slug_adjusted ) {
            $location = add_query_arg( 'sc_foundation_slug_adjusted', '1', $location );
            self::$slug_adjusted = false;
        }
        return $location;
    }

    public function enqueue_admin_assets( $hook_suffix ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'sc-library-foundation-pages-admin',
            SC_LIBRARY_URL . 'assets/js/sc-library-foundation-pages-admin.js',
            array( 'jquery' ),
            $this->version(),
            true
        );
        wp_enqueue_style(
            'sc-library-foundation-pages-admin',
            SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css',
            array(),
            $this->version()
        );
        wp_localize_script(
            'sc-library-foundation-pages-admin',
            'SCFoundationPagesAdmin',
            array(
                'frameTitle'  => __( 'Select Foundation PDF', 'sustainable-catalyst-library' ),
                'buttonText'  => __( 'Use this PDF', 'sustainable-catalyst-library' ),
                'noSelection' => __( 'No PDF selected', 'sustainable-catalyst-library' ),
                'chooseHelp'  => __( 'Choose an existing PDF from the WordPress Media Library.', 'sustainable-catalyst-library' ),
                'openPdf'     => __( 'Open selected PDF', 'sustainable-catalyst-library' ),
                'invalidType' => __( 'Please select a PDF file. Other file types cannot be attached to a Foundation Document.', 'sustainable-catalyst-library' ),
                'mediaError'  => __( 'The WordPress Media Library could not be loaded. Reload this editor or open Foundation Docs Health for diagnostics.', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function editor_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type || $this->is_advanced_editor() ) {
            return;
        }

        $error = isset( $_GET['sc_foundation_error'] ) ? sanitize_key( wp_unslash( $_GET['sc_foundation_error'] ) ) : '';
        if ( 'missing_pdf' === $error ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Foundation Document not published.', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html__( 'Select a valid PDF before publishing. The document was saved as a draft.', 'sustainable-catalyst-library' ) . '</p></div>';
        }

        if ( isset( $_GET['sc_foundation_slug_adjusted'] ) ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Document URL adjusted.', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html__( 'WordPress added a number to avoid a duplicate Foundation Document URL. Review the permalink before sharing it.', 'sustainable-catalyst-library' ) . '</p></div>';
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        $health  = $post_id ? self::get_pdf_health( $post_id ) : array( 'status' => 'missing' );
        if ( $post_id && 'ready' !== $health['status'] ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'PDF required.', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html__( 'This Foundation Document does not currently have a usable PDF. Select or replace the file before publishing.', 'sustainable-catalyst-library' ) . '</p></div>';
        }

        $health_url = admin_url( 'admin.php?page=sc-library-foundation-health' );
        echo '<div class="notice notice-info sc-foundation-page-notice"><p><strong>' . esc_html__( 'Foundation Document Page', 'sustainable-catalyst-library' ) . '</strong> — ' . esc_html__( 'Add a title, optional introduction, select one PDF, and publish.', 'sustainable-catalyst-library' ) . ' <a href="' . esc_url( $health_url ) . '">' . esc_html__( 'Open Foundation Docs Health', 'sustainable-catalyst-library' ) . '</a></p></div>';
    }

    public function document_post_states( $states, $post ) {
        if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type && ! self::get_pdf_attachment_id( $post->ID ) ) {
            $states['sc_foundation_pdf_missing'] = __( 'Needs PDF', 'sustainable-catalyst-library' );
        }
        return $states;
    }

    public function document_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $updated['sc_foundation_pdf'] = __( 'PDF status', 'sustainable-catalyst-library' );
                $updated['sc_foundation_route'] = __( 'Public page', 'sustainable-catalyst-library' );
            }
            $updated[ $key ] = $label;
        }
        return $updated;
    }

    public function render_document_column( $column, $post_id ) {
        if ( 'sc_foundation_pdf' === $column ) {
            $health = self::get_pdf_health( $post_id );
            if ( 'ready' === $health['status'] ) {
                echo '<strong class="sc-foundation-admin-status sc-foundation-admin-status--ready">' . esc_html__( 'Ready', 'sustainable-catalyst-library' ) . '</strong>';
                echo '<span class="sc-foundation-admin-file">' . esc_html( $health['filename'] ) . '</span>';
            } else {
                echo '<strong class="sc-foundation-admin-status sc-foundation-admin-status--warning">' . esc_html__( 'Needs PDF', 'sustainable-catalyst-library' ) . '</strong>';
                echo '<span class="sc-foundation-admin-file">' . esc_html( $health['message'] ) . '</span>';
            }
        }

        if ( 'sc_foundation_route' === $column ) {
            if ( 'publish' === get_post_status( $post_id ) ) {
                echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open page', 'sustainable-catalyst-library' ) . '</a>';
            } else {
                esc_html_e( 'Available after publishing', 'sustainable-catalyst-library' );
            }
        }
    }

    public function register_health_page() {
        add_submenu_page(
            'sc-library',
            __( 'Foundation Docs Health', 'sustainable-catalyst-library' ),
            __( 'Foundation Docs Health', 'sustainable-catalyst-library' ),
            'manage_options',
            'sc-library-foundation-health',
            array( $this, 'render_health_page' )
        );
    }

    public function render_health_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $document_ids = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $ready = array();
        $needs_repair = array();
        foreach ( $document_ids as $document_id ) {
            $health = self::get_pdf_health( $document_id );
            if ( 'ready' === $health['status'] ) {
                $ready[] = $document_id;
            } else {
                $needs_repair[ $document_id ] = $health;
            }
        }

        $rewrite_rules = get_option( 'rewrite_rules', array() );
        $route_ready   = is_array( $rewrite_rules ) && array_key_exists( '^foundations/([^/]+)/?$', $rewrite_rules );
        $media_ready   = function_exists( 'wp_enqueue_media' ) && current_user_can( 'upload_files' );
        $route_version = get_option( 'sc_library_foundation_pages_rewrite_version', '' );

        $message = isset( $_GET['sc_foundation_health_message'] ) ? sanitize_key( wp_unslash( $_GET['sc_foundation_health_message'] ) ) : '';
        $count   = isset( $_GET['sc_foundation_health_count'] ) ? absint( $_GET['sc_foundation_health_count'] ) : 0;
        ?>
        <div class="wrap sc-foundation-health">
            <h1><?php esc_html_e( 'Foundation Docs Health', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Check PDF attachments, public routes, editor availability, and documents that need repair.', 'sustainable-catalyst-library' ); ?></p>

            <?php if ( 'routes_repaired' === $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Foundation Document routes were rebuilt.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php elseif ( 'metadata_repaired' === $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( __( 'Normalized PDF metadata for %d document(s).', 'sustainable-catalyst-library' ), $count ) ); ?></p></div>
            <?php endif; ?>

            <div class="sc-foundation-health__cards">
                <div><strong><?php echo esc_html( count( $document_ids ) ); ?></strong><span><?php esc_html_e( 'Foundation Docs', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( count( $ready ) ); ?></strong><span><?php esc_html_e( 'PDF ready', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( count( $needs_repair ) ); ?></strong><span><?php esc_html_e( 'Need attention', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo $route_ready ? esc_html__( 'Ready', 'sustainable-catalyst-library' ) : esc_html__( 'Repair', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Public route', 'sustainable-catalyst-library' ); ?></span></div>
            </div>

            <table class="widefat striped sc-foundation-health__status">
                <tbody>
                    <tr><th><?php esc_html_e( 'Route version', 'sustainable-catalyst-library' ); ?></th><td><?php echo esc_html( $route_version ?: __( 'Not recorded', 'sustainable-catalyst-library' ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Route rule', 'sustainable-catalyst-library' ); ?></th><td><?php echo $route_ready ? esc_html__( 'Registered', 'sustainable-catalyst-library' ) : esc_html__( 'Missing', 'sustainable-catalyst-library' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Media Library selector', 'sustainable-catalyst-library' ); ?></th><td><?php echo $media_ready ? esc_html__( 'Available', 'sustainable-catalyst-library' ) : esc_html__( 'Unavailable for this account', 'sustainable-catalyst-library' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Editor mode', 'sustainable-catalyst-library' ); ?></th><td><?php esc_html_e( 'Stable page-style editor', 'sustainable-catalyst-library' ); ?></td></tr>
                </tbody>
            </table>

            <div class="sc-foundation-health__actions">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_repair_foundation_routes' ); ?>
                    <input type="hidden" name="action" value="sc_library_repair_foundation_routes">
                    <?php submit_button( __( 'Repair Foundation Routes', 'sustainable-catalyst-library' ), 'primary', 'submit', false ); ?>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_repair_foundation_metadata' ); ?>
                    <input type="hidden" name="action" value="sc_library_repair_foundation_metadata">
                    <?php submit_button( __( 'Normalize PDF Metadata', 'sustainable-catalyst-library' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>

            <h2><?php esc_html_e( 'Documents needing attention', 'sustainable-catalyst-library' ); ?></h2>
            <?php if ( $needs_repair ) : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Action', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ( $needs_repair as $document_id => $health ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( get_the_title( $document_id ) ?: __( '(Untitled)', 'sustainable-catalyst-library' ) ); ?></strong></td>
                            <td><?php echo esc_html( $health['message'] ); ?></td>
                            <td><a class="button" href="<?php echo esc_url( get_edit_post_link( $document_id, 'raw' ) ); ?>"><?php esc_html_e( 'Select or replace PDF', 'sustainable-catalyst-library' ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e( 'All Foundation Documents have usable PDF attachments.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function repair_routes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair Foundation Document routes.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_repair_foundation_routes' );
        flush_rewrite_rules( false );
        update_option( 'sc_library_foundation_pages_rewrite_version', self::ROUTE_VERSION, false );
        wp_safe_redirect( add_query_arg( 'sc_foundation_health_message', 'routes_repaired', admin_url( 'admin.php?page=sc-library-foundation-health' ) ) );
        exit;
    }

    public function repair_metadata() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair Foundation Document metadata.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_repair_foundation_metadata' );

        $document_ids = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );
        $updated = 0;
        foreach ( $document_ids as $document_id ) {
            $attachment_id = self::get_pdf_attachment_id( $document_id );
            if ( ! $attachment_id ) {
                continue;
            }
            foreach ( self::$compatible_pdf_meta_keys as $meta_key ) {
                update_post_meta( $document_id, $meta_key, $attachment_id );
            }
            $updated++;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'sc_foundation_health_message' => 'metadata_repaired',
                    'sc_foundation_health_count'   => $updated,
                ),
                admin_url( 'admin.php?page=sc-library-foundation-health' )
            )
        );
        exit;
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-foundation-pages',
            SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css',
            array(),
            $this->version()
        );

        global $post;
        $has_listing = $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'sc_foundation_documents' );
        if ( is_singular( self::POST_TYPE ) || $has_listing ) {
            wp_enqueue_style( 'sc-library-foundation-pages' );
        }
    }

    public function single_template( $template ) {
        if ( ! is_singular( self::POST_TYPE ) ) {
            return $template;
        }
        $plugin_template = dirname( __DIR__ ) . '/templates/single-sc_foundation_doc.php';
        return is_file( $plugin_template ) ? $plugin_template : $template;
    }

    public function body_classes( $classes ) {
        if ( is_singular( self::POST_TYPE ) ) {
            $classes[] = 'sc-foundation-document-page';
        }
        return $classes;
    }

    public function exclude_from_unrelated_queries( $query ) {
        if ( ! $query instanceof WP_Query || is_admin() || self::$allow_foundation_query ) {
            return $query;
        }

        // Never modify direct Foundation Document requests.
        if (
            $query->is_singular()
            || self::POST_TYPE === $query->get( 'post_type' ) && $query->get( 'name' )
            || $query->get( self::POST_TYPE )
        ) {
            return $query;
        }

        // The post type registration already excludes Foundation Docs from ordinary search,
        // navigation, feeds, blog archives, categories, and tags. No query mutation is needed.
        return $query;
    }

    public function bridge_foundations_library_shortcode( $return, $tag, $attr, $match ) {
        if ( null !== $return ) {
            return $return;
        }

        $attr = is_array( $attr ) ? $attr : array();

        if ( 'sc_foundations_library' === $tag ) {
            return $this->shortcode_foundation_documents(
                array(
                    'per_page'   => isset( $attr['per_page'] ) ? $attr['per_page'] : '12',
                    'search'     => isset( $attr['search'] ) ? $attr['search'] : 'true',
                    'orderby'    => isset( $attr['orderby'] ) ? $attr['orderby'] : 'title',
                    'order'      => isset( $attr['order'] ) ? $attr['order'] : 'ASC',
                    'title'      => isset( $attr['title'] ) ? $attr['title'] : '',
                    'intro'      => isset( $attr['intro'] ) ? $attr['intro'] : '',
                    'show_header'=> isset( $attr['show_header'] ) ? $attr['show_header'] : 'false',
                )
            );
        }

        if ( 'sc_library' !== $tag ) {
            return $return;
        }

        $collection = isset( $attr['collection'] ) ? sanitize_key( $attr['collection'] ) : '';
        $mode       = isset( $attr['mode'] ) ? sanitize_key( $attr['mode'] ) : '';

        if ( 'foundations' !== $collection || ! in_array( $mode, array( 'documentation', 'foundations', 'foundation' ), true ) ) {
            return $return;
        }

        return $this->shortcode_foundation_documents(
            array(
                'per_page'    => isset( $attr['per_page'] ) ? $attr['per_page'] : '12',
                'search'      => isset( $attr['search'] ) ? $attr['search'] : 'true',
                'orderby'     => isset( $attr['orderby'] ) ? $attr['orderby'] : 'title',
                'order'       => isset( $attr['order'] ) ? $attr['order'] : 'ASC',
                'title'       => isset( $attr['title'] ) ? $attr['title'] : '',
                'intro'       => isset( $attr['intro'] ) ? $attr['intro'] : '',
                'show_header' => isset( $attr['show_header'] ) ? $attr['show_header'] : 'true',
            )
        );
    }

    public function shortcode_foundation_documents( $atts ) {
        $atts = shortcode_atts(
            array(
                'per_page'    => '12',
                'search'      => 'true',
                'orderby'     => 'title',
                'order'       => 'ASC',
                'title'       => 'Foundation Documents',
                'intro'       => '',
                'show_header' => 'true',
            ),
            $atts,
            'sc_foundation_documents'
        );

        wp_enqueue_style( 'sc-library-foundation-pages' );

        $per_page    = max( 1, min( 50, absint( $atts['per_page'] ) ) );
        $paged       = isset( $_GET['foundation_page'] ) ? max( 1, absint( $_GET['foundation_page'] ) ) : 1;
        $search      = isset( $_GET['foundation_q'] ) ? sanitize_text_field( wp_unslash( $_GET['foundation_q'] ) ) : '';
        $orderby     = in_array( $atts['orderby'], array( 'title', 'date', 'menu_order' ), true ) ? $atts['orderby'] : 'title';
        $order       = 'DESC' === strtoupper( $atts['order'] ) ? 'DESC' : 'ASC';
        $show_header = filter_var( $atts['show_header'], FILTER_VALIDATE_BOOLEAN );
        $listing_url = get_queried_object_id()
            ? get_permalink( get_queried_object_id() )
            : remove_query_arg( array( 'foundation_q', 'foundation_page' ) );

        self::$allow_foundation_query = true;
        $documents = new WP_Query(
            array(
                'post_type'           => self::POST_TYPE,
                'post_status'         => 'publish',
                'posts_per_page'      => $per_page,
                'paged'               => $paged,
                's'                   => $search,
                'orderby'             => $orderby,
                'order'               => $order,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => false,
            )
        );
        self::$allow_foundation_query = false;

        ob_start();
        ?>
        <section class="sc-foundation-library" aria-label="<?php echo esc_attr( $atts['title'] ?: __( 'Foundation Documents', 'sustainable-catalyst-library' ) ); ?>">
            <?php if ( $show_header && ( $atts['title'] || $atts['intro'] ) ) : ?>
                <header class="sc-foundation-library__header">
                    <?php if ( $atts['title'] ) : ?><h2><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
                    <?php if ( $atts['intro'] ) : ?><p><?php echo esc_html( $atts['intro'] ); ?></p><?php endif; ?>
                </header>
            <?php endif; ?>

            <?php if ( filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN ) ) : ?>
                <form class="sc-foundation-library__search" method="get" action="<?php echo esc_url( $listing_url ); ?>" role="search">
                    <label for="sc-foundation-library-search"><?php esc_html_e( 'Search Foundation Documents', 'sustainable-catalyst-library' ); ?></label>
                    <div>
                        <input id="sc-foundation-library-search" type="search" name="foundation_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search document titles and introductions', 'sustainable-catalyst-library' ); ?>">
                        <button type="submit"><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                    <?php if ( $search ) : ?>
                        <a class="sc-foundation-library__clear" href="<?php echo esc_url( $listing_url ); ?>"><?php esc_html_e( 'Clear search', 'sustainable-catalyst-library' ); ?></a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ( $documents->have_posts() ) : ?>
                <div class="sc-foundation-library__records">
                    <?php while ( $documents->have_posts() ) : $documents->the_post(); ?>
                        <?php
                        $document_id = get_the_ID();
                        $intro       = trim( wp_strip_all_tags( get_post_field( 'post_content', $document_id ) ) );
                        $health      = self::get_pdf_health( $document_id );
                        ?>
                        <article class="sc-foundation-library__record">
                            <div class="sc-foundation-library__record-meta">
                                <span><?php esc_html_e( 'Foundation Document', 'sustainable-catalyst-library' ); ?></span>
                                <?php if ( 'ready' === $health['status'] ) : ?>
                                    <span><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <?php if ( $intro ) : ?>
                                <p><?php echo esc_html( wp_trim_words( $intro, 34 ) ); ?></p>
                            <?php endif; ?>
                            <div class="sc-foundation-library__record-actions">
                                <a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read document', 'sustainable-catalyst-library' ); ?> →</a>
                                <?php if ( 'ready' === $health['status'] ) : ?>
                                    <a href="<?php echo esc_url( $health['url'] ); ?>" target="_blank" rel="noopener" type="application/pdf"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ( $documents->max_num_pages > 1 ) : ?>
                    <?php
                    $pagination_url = add_query_arg(
                        array(
                            'foundation_page' => 999999999,
                            'foundation_q'    => $search ?: false,
                        ),
                        $listing_url
                    );
                    ?>
                    <nav class="sc-foundation-library__pagination" aria-label="<?php esc_attr_e( 'Foundation Document pages', 'sustainable-catalyst-library' ); ?>">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base'      => str_replace( '999999999', '%#%', $pagination_url ),
                                    'format'    => '',
                                    'current'   => $paged,
                                    'total'     => $documents->max_num_pages,
                                    'prev_text' => __( 'Previous', 'sustainable-catalyst-library' ),
                                    'next_text' => __( 'Next', 'sustainable-catalyst-library' ),
                                )
                            )
                        );
                        ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="sc-foundation-library__empty">
                    <strong><?php esc_html_e( 'No Foundation Documents found.', 'sustainable-catalyst-library' ); ?></strong>
                    <?php if ( $search ) : ?><p><?php esc_html_e( 'Try a broader search term or clear the search.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function render_single_document( $post_id ) {
        wp_enqueue_style( 'sc-library-foundation-pages' );

        $post        = get_post( $post_id );
        $health      = self::get_pdf_health( $post_id );
        $pdf_url     = 'ready' === $health['status'] ? $health['url'] : '';
        $filename    = $health['filename'];
        $intro       = $post ? trim( (string) $post->post_content ) : '';
        $foundations = apply_filters( 'sc_library_foundations_page_url', home_url( '/foundations/' ) );
        $file_size   = '';

        if ( 'ready' === $health['status'] && $health['attachment_id'] ) {
            $file_path = get_attached_file( $health['attachment_id'] );
            if ( $file_path && is_file( $file_path ) ) {
                $file_size = size_format( filesize( $file_path ) );
            }
        }

        ob_start();
        ?>
        <article class="cc-research-library-brand cc-rl-v2 sc-foundation-page">
            <header class="cc-rl-hero sc-foundation-page__hero">
                <p class="cc-rl-kicker"><?php esc_html_e( 'Sustainable Catalyst Foundations', 'sustainable-catalyst-library' ); ?></p>
                <h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>

                <?php if ( $intro ) : ?>
                    <div class="cc-rl-intro sc-foundation-page__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
                <?php endif; ?>

                <div class="cc-rl-hero-actions" aria-label="<?php esc_attr_e( 'Foundation Document actions', 'sustainable-catalyst-library' ); ?>">
                    <?php if ( $pdf_url ) : ?>
                        <a class="cc-rl-button cc-rl-button-primary" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener" type="application/pdf"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?></a>
                        <a class="cc-rl-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $filename ); ?>" type="application/pdf"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></a>
                    <?php endif; ?>
                    <a class="cc-rl-button" href="<?php echo esc_url( $foundations ); ?>"><?php esc_html_e( 'Back to Foundations', 'sustainable-catalyst-library' ); ?></a>
                </div>
            </header>

            <section class="cc-rl-section cc-rl-section-white sc-foundation-page__document" aria-labelledby="sc-foundation-document-reader-title">
                <div class="cc-rl-section-heading">
                    <p class="cc-rl-section-kicker"><?php esc_html_e( 'Published Document', 'sustainable-catalyst-library' ); ?></p>
                    <h2 id="sc-foundation-document-reader-title"><?php esc_html_e( 'Read the PDF', 'sustainable-catalyst-library' ); ?></h2>
                </div>

                <?php if ( $pdf_url ) : ?>
                    <p class="sc-foundation-page__file">
                        <strong><?php echo esc_html( $filename ); ?></strong>
                        <?php if ( $file_size ) : ?><span><?php echo esc_html( $file_size ); ?></span><?php endif; ?>
                    </p>

                    <div class="sc-foundation-page__reader">
                        <object
                            data="<?php echo esc_url( $pdf_url ); ?>#view=FitH&amp;toolbar=1&amp;navpanes=0"
                            type="application/pdf"
                            aria-label="<?php echo esc_attr( sprintf( __( 'PDF document: %s', 'sustainable-catalyst-library' ), get_the_title( $post_id ) ) ); ?>"
                        >
                            <div class="sc-foundation-page__fallback">
                                <p><?php esc_html_e( 'This browser could not display the PDF inside the page.', 'sustainable-catalyst-library' ); ?></p>
                                <p><a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the PDF in a new tab', 'sustainable-catalyst-library' ); ?></a></p>
                            </div>
                        </object>
                    </div>

                    <p class="sc-foundation-page__reader-note">
                        <?php esc_html_e( 'The original PDF remains the fixed published document. Use Open PDF when browser privacy settings prevent inline viewing.', 'sustainable-catalyst-library' ); ?>
                    </p>
                <?php else : ?>
                    <div class="sc-foundation-page__fallback">
                        <strong><?php esc_html_e( 'PDF unavailable', 'sustainable-catalyst-library' ); ?></strong>
                        <p><?php echo esc_html( $health['message'] ); ?></p>
                        <?php if ( current_user_can( 'edit_post', $post_id ) ) : ?>
                            <p><a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"><?php esc_html_e( 'Repair this Foundation Document', 'sustainable-catalyst-library' ); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <p class="cc-back-to-top"><a href="<?php echo esc_url( $foundations ); ?>"><?php esc_html_e( 'Back to Foundations', 'sustainable-catalyst-library' ); ?> ↑</a></p>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function get_pdf_attachment_id( $post_id ) {
        $health = self::get_pdf_health( $post_id );
        return 'ready' === $health['status'] ? $health['attachment_id'] : 0;
    }

    public static function get_pdf_health( $post_id ) {
        $raw_attachment_id = 0;
        foreach ( self::$compatible_pdf_meta_keys as $meta_key ) {
            $candidate = absint( get_post_meta( $post_id, $meta_key, true ) );
            if ( $candidate ) {
                $raw_attachment_id = $candidate;
                break;
            }
        }

        if ( ! $raw_attachment_id ) {
            return array(
                'status'        => 'missing',
                'attachment_id' => 0,
                'url'           => '',
                'filename'      => '',
                'message'       => __( 'No PDF has been selected.', 'sustainable-catalyst-library' ),
            );
        }

        $attachment = get_post( $raw_attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            return array(
                'status'        => 'invalid',
                'attachment_id' => $raw_attachment_id,
                'url'           => '',
                'filename'      => '',
                'message'       => __( 'The selected Media Library attachment no longer exists.', 'sustainable-catalyst-library' ),
            );
        }

        if ( 'application/pdf' !== get_post_mime_type( $raw_attachment_id ) ) {
            return array(
                'status'        => 'invalid',
                'attachment_id' => $raw_attachment_id,
                'url'           => '',
                'filename'      => '',
                'message'       => __( 'The selected attachment is not a PDF.', 'sustainable-catalyst-library' ),
            );
        }

        $url = wp_get_attachment_url( $raw_attachment_id );
        if ( ! $url ) {
            return array(
                'status'        => 'invalid',
                'attachment_id' => $raw_attachment_id,
                'url'           => '',
                'filename'      => '',
                'message'       => __( 'WordPress could not resolve a public URL for the selected PDF.', 'sustainable-catalyst-library' ),
            );
        }

        $file = get_attached_file( $raw_attachment_id );
        return array(
            'status'        => 'ready',
            'attachment_id' => $raw_attachment_id,
            'url'           => $url,
            'filename'      => $file ? basename( (string) $file ) : basename( wp_parse_url( $url, PHP_URL_PATH ) ),
            'message'       => __( 'PDF ready.', 'sustainable-catalyst-library' ),
        );
    }

    private static function is_valid_pdf_attachment( $attachment_id ) {
        if ( ! $attachment_id ) {
            return false;
        }
        $attachment = get_post( $attachment_id );
        return $attachment
            && 'attachment' === $attachment->post_type
            && 'application/pdf' === get_post_mime_type( $attachment_id )
            && (bool) wp_get_attachment_url( $attachment_id );
    }

    private function is_advanced_editor() {
        return isset( $_GET['sc_foundation_advanced'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sc_foundation_advanced'] ) );
    }

    private function is_allowed_foundation_rest_request() {
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            return false;
        }
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        foreach ( array( '/foundation-documents', '/foundation-document', '/extract/pages', '/wp/v2/' . self::POST_TYPE ) as $fragment ) {
            if ( false !== strpos( $uri, $fragment ) ) {
                return true;
            }
        }
        return false;
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : '3.0.0';
    }
}

// Knowledge Library v3.0.0: retain citation/source records and add formatting, history, duplicate, and API reliability safeguards.
require_once __DIR__ . '/class-sc-library-pdf-to-document.php';
require_once __DIR__ . '/class-sc-library-pdf-conversion-reliability.php';
require_once __DIR__ . '/class-sc-library-pdf-bulk-import-repair.php';
require_once __DIR__ . '/class-sc-library-document-ocr-processing.php';
require_once __DIR__ . '/class-sc-library-document-ocr-reliability.php';
require_once __DIR__ . '/class-sc-library-document-repository-hardening.php';
require_once __DIR__ . '/class-sc-library-document-public-repository.php';
require_once __DIR__ . '/class-sc-library-citation-source-manager.php';
require_once __DIR__ . '/class-sc-library-citation-source-reliability.php';
require_once __DIR__ . '/class-sc-library-scholarly-library-connectors.php';
require_once __DIR__ . '/class-sc-library-connector-holdings-reliability.php';
require_once __DIR__ . '/class-sc-library-evidence-claim-linking.php';
require_once __DIR__ . '/class-sc-library-connected-research-environment.php';
new SC_Library_PDF_To_Document();
new SC_Library_PDF_Conversion_Reliability();
new SC_Library_PDF_Bulk_Import_Repair();
new SC_Library_Document_OCR_Processing();
new SC_Library_Document_OCR_Reliability();
new SC_Library_Document_Repository_Hardening();
new SC_Library_Document_Public_Repository();
new SC_Library_Citation_Source_Manager();
new SC_Library_Citation_Source_Reliability();
new SC_Library_Scholarly_Library_Connectors();
new SC_Library_Connector_Holdings_Reliability();
new SC_Library_Evidence_Claim_Linking();
new SC_Library_Connected_Research_Environment();
