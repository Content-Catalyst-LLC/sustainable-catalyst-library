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
    public const ROUTE_VERSION = '2.1.1';

    private static $allow_foundation_query = false;
    private static $saving_title = false;

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
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_document' ), 30, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'editor_notice' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_filter( 'template_include', array( $this, 'single_template' ), 100 );
        add_filter( 'body_class', array( $this, 'body_classes' ) );

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
        $attachment_id = self::get_pdf_attachment_id( $post->ID );
        $attachment    = $attachment_id ? get_post( $attachment_id ) : null;
        $filename      = $attachment_id ? basename( (string) get_attached_file( $attachment_id ) ) : '';
        $url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
        $advanced_url  = add_query_arg( 'sc_foundation_advanced', '1', get_edit_post_link( $post->ID, 'raw' ) );

        wp_nonce_field( 'sc_library_save_foundation_page', 'sc_library_foundation_page_nonce' );
        ?>
        <div class="sc-foundation-page-selector" data-sc-foundation-page-selector>
            <input type="hidden" name="sc_library_foundation_page_pdf_id" value="<?php echo esc_attr( $attachment_id ); ?>" data-sc-foundation-pdf-id>
            <div class="sc-foundation-page-selector__summary" data-sc-foundation-pdf-summary>
                <?php if ( $attachment_id && $url ) : ?>
                    <strong><?php echo esc_html( $attachment ? $attachment->post_title : $filename ); ?></strong>
                    <span><?php echo esc_html( $filename ); ?></span>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open selected PDF', 'sustainable-catalyst-library' ); ?></a>
                <?php else : ?>
                    <strong><?php esc_html_e( 'No PDF selected', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Choose an existing PDF from the WordPress Media Library.', 'sustainable-catalyst-library' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="sc-foundation-page-selector__actions">
                <button type="button" class="button button-primary" data-sc-foundation-select-pdf><?php esc_html_e( 'Select PDF', 'sustainable-catalyst-library' ); ?></button>
                <button type="button" class="button" data-sc-foundation-remove-pdf <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Remove PDF', 'sustainable-catalyst-library' ); ?></button>
            </div>
            <p class="description">
                <?php esc_html_e( 'The selected file is embedded automatically on the public Foundation Document page. Open and Download PDF buttons are also generated automatically.', 'sustainable-catalyst-library' ); ?>
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
        if ( $attachment_id && 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
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
            )
        );
    }

    public function editor_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type || $this->is_advanced_editor() ) {
            return;
        }
        echo '<div class="notice notice-info sc-foundation-page-notice"><p><strong>' . esc_html__( 'Foundation Document Page', 'sustainable-catalyst-library' ) . '</strong> — ' . esc_html__( 'Add a title, an optional introduction in the editor, select a PDF, and publish. Foundation Docs are separate from blog posts, categories, tags, archives, and feeds.', 'sustainable-catalyst-library' ) . '</p></div>';
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

    public function shortcode_foundation_documents( $atts ) {
        $atts = shortcode_atts(
            array(
                'per_page' => '12',
                'search'   => 'true',
                'orderby'  => 'title',
                'order'    => 'ASC',
                'title'    => 'Foundation Documents',
            ),
            $atts,
            'sc_foundation_documents'
        );

        wp_enqueue_style( 'sc-library-foundation-pages' );

        $per_page = max( 1, min( 50, absint( $atts['per_page'] ) ) );
        $paged    = isset( $_GET['foundation_page'] ) ? max( 1, absint( $_GET['foundation_page'] ) ) : 1;
        $search   = isset( $_GET['foundation_q'] ) ? sanitize_text_field( wp_unslash( $_GET['foundation_q'] ) ) : '';
        $orderby  = in_array( $atts['orderby'], array( 'title', 'date', 'menu_order' ), true ) ? $atts['orderby'] : 'title';
        $order    = 'DESC' === strtoupper( $atts['order'] ) ? 'DESC' : 'ASC';

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
        <section class="sc-foundation-documents" aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
            <?php if ( $atts['title'] ) : ?>
                <div class="sc-foundation-documents__heading">
                    <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                    <p><?php esc_html_e( 'Published Foundation Documents with embedded PDF readers and direct download controls.', 'sustainable-catalyst-library' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN ) ) : ?>
                <form class="sc-foundation-documents__search" method="get" action="<?php echo esc_url( remove_query_arg( array( 'foundation_q', 'foundation_page' ) ) ); ?>">
                    <label for="sc-foundation-documents-search"><?php esc_html_e( 'Search Foundation Docs', 'sustainable-catalyst-library' ); ?></label>
                    <div>
                        <input id="sc-foundation-documents-search" type="search" name="foundation_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search document titles and introductions', 'sustainable-catalyst-library' ); ?>">
                        <button type="submit"><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ( $documents->have_posts() ) : ?>
                <div class="sc-foundation-documents__grid">
                    <?php while ( $documents->have_posts() ) : $documents->the_post(); ?>
                        <?php
                        $document_id = get_the_ID();
                        $intro       = trim( wp_strip_all_tags( get_post_field( 'post_content', $document_id ) ) );
                        $pdf_id      = self::get_pdf_attachment_id( $document_id );
                        ?>
                        <article class="sc-foundation-document-card">
                            <p class="sc-foundation-document-card__label"><?php esc_html_e( 'Foundation Document', 'sustainable-catalyst-library' ); ?></p>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <?php if ( $intro ) : ?>
                                <p><?php echo esc_html( wp_trim_words( $intro, 30 ) ); ?></p>
                            <?php endif; ?>
                            <div class="sc-foundation-document-card__footer">
                                <a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Open document', 'sustainable-catalyst-library' ); ?> →</a>
                                <?php if ( $pdf_id ) : ?><span><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></span><?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ( $documents->max_num_pages > 1 ) : ?>
                    <nav class="sc-foundation-documents__pagination" aria-label="<?php esc_attr_e( 'Foundation Document pages', 'sustainable-catalyst-library' ); ?>">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base'      => add_query_arg( 'foundation_page', '%#%' ),
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
                <div class="sc-foundation-documents__empty">
                    <strong><?php esc_html_e( 'No Foundation Documents found.', 'sustainable-catalyst-library' ); ?></strong>
                    <?php if ( $search ) : ?><p><?php esc_html_e( 'Try a broader search term.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function render_single_document( $post_id ) {
        wp_enqueue_style( 'sc-library-foundation-pages' );

        $post          = get_post( $post_id );
        $attachment_id = self::get_pdf_attachment_id( $post_id );
        $pdf_url       = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
        $filename      = $attachment_id ? basename( (string) get_attached_file( $attachment_id ) ) : '';
        $intro         = $post ? trim( (string) $post->post_content ) : '';
        $foundations   = apply_filters( 'sc_library_foundations_page_url', home_url( '/foundations/' ) );

        ob_start();
        ?>
        <article class="sc-foundation-document-single">
            <header class="sc-foundation-document-single__header">
                <p class="sc-foundation-document-single__eyebrow"><?php esc_html_e( 'Sustainable Catalyst Foundation Document', 'sustainable-catalyst-library' ); ?></p>
                <h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
                <?php if ( $intro ) : ?>
                    <div class="sc-foundation-document-single__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
                <?php endif; ?>
            </header>

            <?php if ( $pdf_url ) : ?>
                <div class="sc-foundation-document-single__viewer" aria-label="<?php echo esc_attr( sprintf( __( 'Embedded PDF: %s', 'sustainable-catalyst-library' ), get_the_title( $post_id ) ) ); ?>">
                    <object data="<?php echo esc_url( $pdf_url ); ?>#view=FitH&amp;toolbar=1&amp;navpanes=0" type="application/pdf">
                        <div class="sc-foundation-document-single__fallback">
                            <p><?php esc_html_e( 'This browser cannot display the PDF inline.', 'sustainable-catalyst-library' ); ?></p>
                            <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the PDF', 'sustainable-catalyst-library' ); ?></a>
                        </div>
                    </object>
                </div>
                <div class="sc-foundation-document-single__actions">
                    <a class="sc-foundation-document-button sc-foundation-document-button--primary" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?></a>
                    <a class="sc-foundation-document-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $filename ); ?>"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?></a>
                    <a class="sc-foundation-document-button" href="<?php echo esc_url( $foundations ); ?>"><?php esc_html_e( 'Back to Foundations', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php else : ?>
                <div class="sc-foundation-document-single__missing">
                    <strong><?php esc_html_e( 'PDF unavailable', 'sustainable-catalyst-library' ); ?></strong>
                    <p><?php esc_html_e( 'A PDF has not yet been selected for this Foundation Document.', 'sustainable-catalyst-library' ); ?></p>
                    <a href="<?php echo esc_url( $foundations ); ?>"><?php esc_html_e( 'Back to Foundations', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function get_pdf_attachment_id( $post_id ) {
        foreach ( self::$compatible_pdf_meta_keys as $meta_key ) {
            $attachment_id = absint( get_post_meta( $post_id, $meta_key, true ) );
            if ( $attachment_id && 'application/pdf' === get_post_mime_type( $attachment_id ) ) {
                return $attachment_id;
            }
        }
        return 0;
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
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : '2.1.0';
    }
}
