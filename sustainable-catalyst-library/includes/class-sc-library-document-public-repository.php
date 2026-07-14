<?php
/**
 * Document Families and Public Repository.
 *
 * Adds the public /documents/ repository, family and type landing pages,
 * filters, featured records, lifecycle groupings, and compact document rows.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Document_Public_Repository {
    public const VERSION = '2.3.1';
    public const ROUTE_VERSION = '2.3.0';
    public const POST_TYPE = 'sc_foundation_doc';
    public const TAX_FAMILY = 'sc_document_family';
    public const TAX_TYPE = 'sc_document_type';

    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const META_VERSION = '_sc_document_version';
    public const META_PUBLICATION_DATE = '_sc_document_publication_date';
    public const META_LIFECYCLE = '_sc_document_lifecycle_status';
    public const META_PAGE_COUNT = '_sc_document_page_count';
    public const META_FEATURED = '_sc_document_repository_featured';
    public const META_ORDER = '_sc_document_repository_order';
    public const META_FAMILY_KICKER = '_sc_document_family_kicker';
    public const META_FAMILY_FEATURED = '_sc_document_family_featured';
    public const META_FAMILY_ORDER = '_sc_document_family_order';

    public const QUERY_VAR = 'sc_document_repository';
    public const DEFAULT_PER_PAGE = 18;

    private static $instance = null;

    /** @var int */
    private $render_instance = 0;

    /** @var array<string,string> */
    private static $lifecycle_labels = array(
        'current'    => 'Current',
        'superseded' => 'Superseded',
        'archived'   => 'Archived',
        'historical' => 'Historical',
    );

    public function __construct() {
        self::$instance = $this;

        add_action( 'init', array( $this, 'register_public_structure' ), 230 );
        add_action( 'rest_api_init', array( $this, 'register_public_meta' ), 230 );
        add_filter( 'query_vars', array( $this, 'query_vars' ), 230 );
        add_filter( 'template_include', array( $this, 'template_include' ), 260 );
        add_filter( 'redirect_canonical', array( $this, 'disable_repository_canonical_redirect' ), 20, 2 );
        add_filter( 'document_title_parts', array( $this, 'document_title_parts' ), 40 );
        add_filter( 'body_class', array( $this, 'body_classes' ), 80 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ), 80 );

        add_filter( 'pre_do_shortcode_tag', array( $this, 'bridge_existing_shortcodes' ), 1, 4 );
        add_action( 'init', array( $this, 'register_shortcodes' ), 250 );

        add_action( 'edit_form_after_title', array( $this, 'render_document_repository_settings' ), 70 );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_document_repository_settings' ), 170, 3 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'document_columns' ), 170 );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'document_column_content' ), 170, 2 );

        add_action( self::TAX_FAMILY . '_add_form_fields', array( $this, 'family_add_fields' ) );
        add_action( self::TAX_FAMILY . '_edit_form_fields', array( $this, 'family_edit_fields' ), 10, 2 );
        add_action( 'created_' . self::TAX_FAMILY, array( $this, 'save_family_fields' ) );
        add_action( 'edited_' . self::TAX_FAMILY, array( $this, 'save_family_fields' ) );

        add_action( 'admin_menu', array( $this, 'register_repository_admin_page' ), 240 );
        add_action( 'admin_post_sc_library_v230_repair_repository_routes', array( $this, 'repair_repository_routes' ) );
        add_action( 'admin_post_sc_library_v230_seed_repository', array( $this, 'seed_repository_structure' ) );
    }

    public function register_public_structure() {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        register_taxonomy(
            self::TAX_TYPE,
            array( self::POST_TYPE ),
            array(
                'labels' => array(
                    'name'              => __( 'Document Types', 'sustainable-catalyst-library' ),
                    'singular_name'     => __( 'Document Type', 'sustainable-catalyst-library' ),
                    'menu_name'         => __( 'Document Types', 'sustainable-catalyst-library' ),
                    'search_items'      => __( 'Search Document Types', 'sustainable-catalyst-library' ),
                    'all_items'         => __( 'All Document Types', 'sustainable-catalyst-library' ),
                    'edit_item'         => __( 'Edit Document Type', 'sustainable-catalyst-library' ),
                    'update_item'       => __( 'Update Document Type', 'sustainable-catalyst-library' ),
                    'add_new_item'      => __( 'Add Document Type', 'sustainable-catalyst-library' ),
                    'new_item_name'     => __( 'New Document Type', 'sustainable-catalyst-library' ),
                ),
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_admin_column'  => true,
                'show_in_rest'       => true,
                'hierarchical'       => true,
                'rewrite'            => array(
                    'slug'       => 'documents/type',
                    'with_front' => false,
                ),
            )
        );

        add_rewrite_rule(
            '^documents/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );

        $this->ensure_default_families();
        $this->ensure_default_types();
        $this->migrate_public_repository_once();

        if ( self::ROUTE_VERSION !== get_option( 'sc_library_document_repository_route_version' ) ) {
            flush_rewrite_rules( false );
            update_option( 'sc_library_document_repository_route_version', self::ROUTE_VERSION, false );
        }
    }

    public function register_public_meta() {
        register_post_meta(
            self::POST_TYPE,
            self::META_FEATURED,
            array(
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
        register_post_meta(
            self::POST_TYPE,
            self::META_ORDER,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
        register_term_meta(
            self::TAX_FAMILY,
            self::META_FAMILY_KICKER,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'manage_categories' );
                },
            )
        );
        register_term_meta(
            self::TAX_FAMILY,
            self::META_FAMILY_FEATURED,
            array(
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback'     => function() {
                    return current_user_can( 'manage_categories' );
                },
            )
        );
        register_term_meta(
            self::TAX_FAMILY,
            self::META_FAMILY_ORDER,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     => function() {
                    return current_user_can( 'manage_categories' );
                },
            )
        );
    }

    public function query_vars( $vars ) {
        if ( ! in_array( self::QUERY_VAR, $vars, true ) ) {
            $vars[] = self::QUERY_VAR;
        }
        return $vars;
    }

    public function register_shortcodes() {
        foreach ( array(
            'sc_pdf_document_repository',
            'sc_document_repository',
            'sc_pdf_document_library',
            'sc_pdf_library',
            'sc_foundation_documents',
        ) as $tag ) {
            remove_shortcode( $tag );
            add_shortcode( $tag, array( $this, 'shortcode_repository' ) );
        }
    }

    public function bridge_existing_shortcodes( $return, $tag, $attr, $match ) {
        if ( null !== $return ) {
            return $return;
        }

        $attr = is_array( $attr ) ? $attr : array();

        if ( in_array( $tag, array( 'sc_foundations_library', 'sc_foundation_documents' ), true ) ) {
            $attr['family'] = 'foundations';
            return $this->shortcode_repository( $attr );
        }

        if ( 'sc_library' !== $tag ) {
            return $return;
        }

        $mode = sanitize_key( $attr['mode'] ?? '' );
        $collection = sanitize_title( $attr['collection'] ?? '' );
        if ( ! in_array( $mode, array( 'documentation', 'documents', 'document', 'foundation', 'foundations', 'repository' ), true ) ) {
            return $return;
        }

        if ( 'foundations' === $collection ) {
            $collection = 'foundations';
        }

        if ( $collection && term_exists( $collection, self::TAX_FAMILY ) ) {
            $attr['family'] = $collection;
        }

        $attr['show_header'] = $attr['show_header'] ?? 'false';
        return $this->shortcode_repository( $attr );
    }

    public function shortcode_repository( $atts ) {
        $atts = shortcode_atts(
            array(
                'family'        => '',
                'type'          => '',
                'per_page'      => (string) self::DEFAULT_PER_PAGE,
                'search'        => 'true',
                'filters'       => 'true',
                'featured'      => 'true',
                'families'      => 'false',
                'grouped'       => 'true',
                'show_header'   => 'true',
                'title'         => __( 'PDF Document Repository', 'sustainable-catalyst-library' ),
                'intro'         => '',
                'compact'       => 'true',
            ),
            $atts,
            'sc_pdf_document_repository'
        );

        return $this->render_repository(
            array(
                'family'      => sanitize_title( $atts['family'] ),
                'type'        => sanitize_title( $atts['type'] ),
                'per_page'    => max( 1, min( 50, absint( $atts['per_page'] ) ) ),
                'search'      => filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN ),
                'filters'     => filter_var( $atts['filters'], FILTER_VALIDATE_BOOLEAN ),
                'featured'    => filter_var( $atts['featured'], FILTER_VALIDATE_BOOLEAN ),
                'families'    => filter_var( $atts['families'], FILTER_VALIDATE_BOOLEAN ),
                'grouped'     => filter_var( $atts['grouped'], FILTER_VALIDATE_BOOLEAN ),
                'show_header' => filter_var( $atts['show_header'], FILTER_VALIDATE_BOOLEAN ),
                'title'       => sanitize_text_field( $atts['title'] ),
                'intro'       => sanitize_text_field( $atts['intro'] ),
                'compact'     => filter_var( $atts['compact'], FILTER_VALIDATE_BOOLEAN ),
                'context'     => 'shortcode',
            )
        );
    }

    public function template_include( $template ) {
        if ( get_query_var( self::QUERY_VAR ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/archive-sc_document_repository.php';
            return is_file( $candidate ) ? $candidate : $template;
        }

        if ( is_tax( self::TAX_FAMILY ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/taxonomy-sc_document_family.php';
            return is_file( $candidate ) ? $candidate : $template;
        }

        if ( is_tax( self::TAX_TYPE ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/taxonomy-sc_document_type.php';
            return is_file( $candidate ) ? $candidate : $template;
        }

        return $template;
    }

    public function disable_repository_canonical_redirect( $redirect_url, $requested_url ) {
        if ( get_query_var( self::QUERY_VAR ) ) {
            return false;
        }
        return $redirect_url;
    }

    public function document_title_parts( $parts ) {
        if ( get_query_var( self::QUERY_VAR ) ) {
            $parts['title'] = __( 'PDF Document Repository', 'sustainable-catalyst-library' );
        }
        return $parts;
    }

    public function body_classes( $classes ) {
        if ( get_query_var( self::QUERY_VAR ) ) {
            $classes[] = 'sc-document-repository-page';
        }
        if ( is_tax( self::TAX_FAMILY ) ) {
            $classes[] = 'sc-document-family-page';
        }
        if ( is_tax( self::TAX_TYPE ) ) {
            $classes[] = 'sc-document-type-page';
        }
        return $classes;
    }

    public function enqueue_public_assets() {
        global $post;

        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        $has_shortcode = false;
        foreach ( array( 'sc_pdf_document_repository', 'sc_document_repository', 'sc_pdf_document_library', 'sc_pdf_library', 'sc_foundation_documents' ) as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                $has_shortcode = true;
                break;
            }
        }

        if ( get_query_var( self::QUERY_VAR ) || is_tax( self::TAX_FAMILY ) || is_tax( self::TAX_TYPE ) || $has_shortcode ) {
            wp_enqueue_style( 'sc-library-foundation-pages' );
        }
    }

    public static function render_repository_page() {
        if ( ! self::$instance ) {
            return '';
        }

        return self::$instance->render_repository_shell(
            array(
                'family'      => '',
                'type'        => '',
                'per_page'    => self::DEFAULT_PER_PAGE,
                'search'      => true,
                'filters'     => true,
                'featured'    => true,
                'families'    => true,
                'grouped'     => true,
                'show_header' => false,
                'title'       => __( 'PDF Document Repository', 'sustainable-catalyst-library' ),
                'intro'       => __( 'Readable Knowledge Library records paired with their authoritative original PDFs.', 'sustainable-catalyst-library' ),
                'compact'     => true,
                'context'     => 'repository',
            ),
            __( 'PDF Document Repository', 'sustainable-catalyst-library' ),
            __( 'Browse document families, search readable records, and open or download the authoritative PDFs.', 'sustainable-catalyst-library' )
        );
    }

    public static function render_family_page( $term ) {
        if ( ! self::$instance || ! $term instanceof WP_Term ) {
            return '';
        }

        $kicker = (string) get_term_meta( $term->term_id, self::META_FAMILY_KICKER, true );
        $intro = term_description( $term->term_id, self::TAX_FAMILY );

        return self::$instance->render_repository_shell(
            array(
                'family'      => $term->slug,
                'type'        => '',
                'per_page'    => self::DEFAULT_PER_PAGE,
                'search'      => true,
                'filters'     => true,
                'featured'    => true,
                'families'    => false,
                'grouped'     => true,
                'show_header' => false,
                'title'       => $term->name,
                'intro'       => wp_strip_all_tags( $intro ),
                'compact'     => true,
                'context'     => 'family',
            ),
            $term->name,
            $intro,
            $kicker ?: __( 'Document Family', 'sustainable-catalyst-library' ),
            $term
        );
    }

    public static function render_type_page( $term ) {
        if ( ! self::$instance || ! $term instanceof WP_Term ) {
            return '';
        }

        return self::$instance->render_repository_shell(
            array(
                'family'      => '',
                'type'        => $term->slug,
                'per_page'    => self::DEFAULT_PER_PAGE,
                'search'      => true,
                'filters'     => true,
                'featured'    => true,
                'families'    => false,
                'grouped'     => true,
                'show_header' => false,
                'title'       => $term->name,
                'intro'       => wp_strip_all_tags( term_description( $term->term_id, self::TAX_TYPE ) ),
                'compact'     => true,
                'context'     => 'type',
            ),
            $term->name,
            term_description( $term->term_id, self::TAX_TYPE ),
            __( 'Document Type', 'sustainable-catalyst-library' )
        );
    }

    private function render_repository_shell( $args, $title, $intro, $kicker = '', $current_family = null ) {
        wp_enqueue_style( 'sc-library-foundation-pages' );

        $this->render_instance++;
        $instance_id = 'sc-document-repository-' . $this->render_instance;
        $args['instance_id'] = $instance_id;

        $counts = $this->repository_counts();
        $repository_url = home_url( '/documents/' );

        ob_start();
        ?>
        <div class="cc-research-library-brand cc-rl-v2 sc-public-document-repository" data-sc-document-repository>
            <a class="sc-repository-skip-link" href="<?php echo esc_attr( SC_Library_Document_Repository_Hardening::result_fragment( $instance_id ) ); ?>"><?php esc_html_e( 'Skip to document results', 'sustainable-catalyst-library' ); ?></a>
            <header class="cc-rl-hero sc-public-document-repository__hero" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-title">
                <p class="cc-rl-kicker"><?php echo esc_html( $kicker ?: __( 'Sustainable Catalyst Knowledge Library', 'sustainable-catalyst-library' ) ); ?></p>
                <h1 id="<?php echo esc_attr( $instance_id ); ?>-title"><?php echo esc_html( $title ); ?></h1>
                <?php if ( $intro ) : ?><div class="cc-rl-lede"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div><?php endif; ?>
                <div class="cc-rl-hero-actions">
                    <a class="cc-rl-button cc-rl-button-primary" href="<?php echo esc_attr( SC_Library_Document_Repository_Hardening::result_fragment( $instance_id ) ); ?>"><?php esc_html_e( 'Browse Documents', 'sustainable-catalyst-library' ); ?></a>
                    <?php if ( 'repository' !== ( $args['context'] ?? '' ) ) : ?>
                        <a class="cc-rl-button" href="<?php echo esc_url( $repository_url ); ?>"><?php esc_html_e( 'All Document Families', 'sustainable-catalyst-library' ); ?></a>
                    <?php endif; ?>
                </div>
                <dl class="sc-public-document-repository__metrics">
                    <div><dt><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $counts['documents'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Families', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $counts['families'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Current', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $counts['current'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Last updated', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $counts['updated'] ); ?></dd></div>
                </dl>
            </header>

            <?php if ( ! empty( $args['families'] ) ) : ?>
                <section class="cc-rl-section cc-rl-section-cream sc-public-document-repository__families" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-family-index-title">
                    <div class="cc-rl-section-heading">
                        <p class="cc-rl-section-kicker"><?php esc_html_e( 'Document Families', 'sustainable-catalyst-library' ); ?></p>
                        <h2 id="<?php echo esc_attr( $instance_id ); ?>-family-index-title"><?php esc_html_e( 'Browse the repository by family', 'sustainable-catalyst-library' ); ?></h2>
                    </div>
                    <?php echo $this->render_family_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>

            <section id="<?php echo esc_attr( $instance_id ); ?>-results" class="cc-rl-section cc-rl-section-white sc-public-document-repository__results" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-results-title">
                <h2 id="<?php echo esc_attr( $instance_id ); ?>-results-title" class="screen-reader-text"><?php esc_html_e( 'Document repository results', 'sustainable-catalyst-library' ); ?></h2>
                <?php echo $this->render_repository( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

            <?php if ( $current_family instanceof WP_Term ) : ?>
                <section class="cc-rl-section cc-rl-section-cream sc-public-document-repository__related" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-related-families-title">
                    <div class="cc-rl-section-heading">
                        <p class="cc-rl-section-kicker"><?php esc_html_e( 'Continue Browsing', 'sustainable-catalyst-library' ); ?></p>
                        <h2 id="<?php echo esc_attr( $instance_id ); ?>-related-families-title"><?php esc_html_e( 'Related document families', 'sustainable-catalyst-library' ); ?></h2>
                    </div>
                    <?php echo $this->render_family_index( $current_family->term_id, 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_repository( $args ) {
        wp_enqueue_style( 'sc-library-foundation-pages' );

        $family_lock = sanitize_title( $args['family'] ?? '' );
        $type_lock = sanitize_title( $args['type'] ?? '' );

        $filters = $this->request_filters();
        if ( $family_lock ) {
            $filters['family'] = $family_lock;
        }
        if ( $type_lock ) {
            $filters['type'] = $type_lock;
        }

        $base_url = $this->repository_base_url( $family_lock, $type_lock );
        $page = max( 1, absint( $_GET['sc_doc_page'] ?? 1 ) );
        $per_page = max( 1, min( 50, absint( $args['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );
        $active_filters = $this->has_active_filters( $filters );
        $instance_id = sanitize_html_class( $args['instance_id'] ?? '' );
        if ( ! $instance_id ) {
            $this->render_instance++;
            $instance_id = 'sc-document-repository-' . $this->render_instance;
        }

        $featured_ids = array();
        if ( ! empty( $args['featured'] ) && ! $active_filters ) {
            $featured_ids = $this->featured_document_ids( $family_lock, $type_lock, 4 );
        }
        $featured_display_ids = 1 === $page ? $featured_ids : array();

        $query_args = $this->document_query_args( $filters, $page, $per_page, $featured_ids );
        $documents = new WP_Query( $query_args );
        $total_documents = absint( $documents->found_posts ) + count( $featured_ids );

        ob_start();
        ?>
        <div class="sc-public-document-index <?php echo ! empty( $args['compact'] ) ? 'is-compact' : ''; ?>">
            <?php if ( ! empty( $args['show_header'] ) ) : ?>
                <header class="sc-public-document-index__header">
                    <p class="sc-public-document-index__kicker"><?php esc_html_e( 'PDF Document Library', 'sustainable-catalyst-library' ); ?></p>
                    <?php if ( ! empty( $args['title'] ) ) : ?><h2><?php echo esc_html( $args['title'] ); ?></h2><?php endif; ?>
                    <?php if ( ! empty( $args['intro'] ) ) : ?><p><?php echo esc_html( $args['intro'] ); ?></p><?php endif; ?>
                </header>
            <?php endif; ?>

            <?php if ( ! empty( $args['search'] ) || ! empty( $args['filters'] ) ) : ?>
                <?php echo $this->render_filter_form( $filters, $base_url, $family_lock, $type_lock, ! empty( $args['search'] ), ! empty( $args['filters'] ), $instance_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <?php if ( $featured_display_ids ) : ?>
                <section class="sc-public-document-index__featured" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-featured-documents-title">
                    <div class="sc-public-document-index__section-heading">
                        <p><?php esc_html_e( 'Featured Documents', 'sustainable-catalyst-library' ); ?></p>
                        <h3 id="<?php echo esc_attr( $instance_id ); ?>-featured-documents-title"><?php esc_html_e( 'Pinned repository records', 'sustainable-catalyst-library' ); ?></h3>
                    </div>
                    <div class="sc-public-document-index__rows">
                        <?php foreach ( $featured_display_ids as $document_id ) : ?>
                            <?php echo $this->render_document_row( $document_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div id="<?php echo esc_attr( $instance_id ); ?>-summary" class="sc-public-document-index__summary" role="status" aria-live="polite" aria-atomic="true" tabindex="-1">
                <strong><?php echo esc_html( sprintf( _n( '%s document', '%s documents', $total_documents, 'sustainable-catalyst-library' ), number_format_i18n( $total_documents ) ) ); ?></strong>
                <?php if ( $active_filters ) : ?><span><?php esc_html_e( 'matching the current search and filters', 'sustainable-catalyst-library' ); ?></span><?php endif; ?>
            </div>

            <?php if ( $documents->have_posts() ) : ?>
                <?php
                $grouped_posts = array();
                while ( $documents->have_posts() ) {
                    $documents->the_post();
                    $post_id = get_the_ID();
                    $lifecycle = sanitize_key( (string) get_post_meta( $post_id, self::META_LIFECYCLE, true ) ) ?: 'current';
                    if ( ! isset( $grouped_posts[ $lifecycle ] ) ) {
                        $grouped_posts[ $lifecycle ] = array();
                    }
                    $grouped_posts[ $lifecycle ][] = $post_id;
                }
                wp_reset_postdata();

                $group_order = array( 'current', 'superseded', 'archived', 'historical', 'unclassified' );
                $visible_groups = count( array_filter( $grouped_posts ) );
                foreach ( $group_order as $group ) :
                    if ( empty( $grouped_posts[ $group ] ) ) {
                        continue;
                    }
                    $show_group_heading = ! empty( $args['grouped'] ) && ( $visible_groups > 1 || 'current' !== $group );
                    ?>
                    <section class="sc-public-document-index__group sc-public-document-index__group--<?php echo esc_attr( $group ); ?>">
                        <?php if ( $show_group_heading ) : ?>
                            <div class="sc-public-document-index__group-heading">
                                <h3><?php echo esc_html( $this->lifecycle_label( $group ) ); ?></h3>
                                <span><?php echo esc_html( count( $grouped_posts[ $group ] ) ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="sc-public-document-index__rows">
                            <?php foreach ( $grouped_posts[ $group ] as $document_id ) : ?>
                                <?php echo $this->render_document_row( $document_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <?php if ( $documents->max_num_pages > 1 ) : ?>
                    <?php
                    $pagination_args = $this->filter_query_args( $filters );
                    $pagination_args['sc_doc_page'] = 999999999;
                    $pagination_base = add_query_arg( $pagination_args, $base_url );
                    ?>
                    <nav class="sc-public-document-index__pagination" aria-label="<?php esc_attr_e( 'Document result pages', 'sustainable-catalyst-library' ); ?>">
                        <?php
                        $pagination = paginate_links(
                            array(
                                'base'      => str_replace( '999999999', '%#%', $pagination_base ),
                                'format'    => '',
                                'current'   => $page,
                                'total'     => $documents->max_num_pages,
                                'prev_text' => '<span aria-hidden="true">←</span> ' . __( 'Previous', 'sustainable-catalyst-library' ),
                                'next_text' => __( 'Next', 'sustainable-catalyst-library' ) . ' <span aria-hidden="true">→</span>',
                            )
                        );
                        if ( $pagination ) {
                            $pagination = str_replace( 'class="page-numbers current"', 'class="page-numbers current" aria-current="page"', $pagination );
                            $pagination = preg_replace_callback(
                                '/href="([^"]+)"/',
                                function( $matches ) use ( $instance_id ) {
                                    return 'href="' . esc_url( SC_Library_Document_Repository_Hardening::append_result_fragment( html_entity_decode( $matches[1] ), $instance_id ) ) . '"';
                                },
                                $pagination
                            );
                            echo wp_kses_post( $pagination );
                        }
                        ?>
                    </nav>
                <?php endif; ?>
            <?php elseif ( ! $featured_display_ids ) : ?>
                <div class="sc-document-library__empty" role="status">
                    <strong><?php esc_html_e( 'No documents found.', 'sustainable-catalyst-library' ); ?></strong>
                    <p><?php esc_html_e( 'Clear one or more filters or try a broader search term.', 'sustainable-catalyst-library' ); ?></p>
                    <a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset repository filters', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_filter_form( $filters, $base_url, $family_lock, $type_lock, $show_search, $show_filters, $instance_id ) {
        $families = get_terms(
            array(
                'taxonomy'   => self::TAX_FAMILY,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        $types = get_terms(
            array(
                'taxonomy'   => self::TAX_TYPE,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        $years = $this->meta_values( self::META_PUBLICATION_DATE, true );
        $versions = $this->meta_values( self::META_VERSION, false );

        ob_start();
        ?>
        <form class="sc-public-document-filter" method="get" action="<?php echo esc_url( SC_Library_Document_Repository_Hardening::append_result_fragment( $base_url, $instance_id ) ); ?>" role="search" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-filter-title">
            <fieldset>
                <legend id="<?php echo esc_attr( $instance_id ); ?>-filter-title"><?php esc_html_e( 'Search and filter the document repository', 'sustainable-catalyst-library' ); ?></legend>
                <p id="<?php echo esc_attr( $instance_id ); ?>-filter-help" class="sc-public-document-filter__help"><?php esc_html_e( 'Filters are optional. Applying them reloads the page and moves focus to the result summary.', 'sustainable-catalyst-library' ); ?></p>
                <div class="sc-public-document-filter__grid" aria-describedby="<?php echo esc_attr( $instance_id ); ?>-filter-help">
                <?php if ( $show_search ) : ?>
                    <label class="sc-public-document-filter__search" for="<?php echo esc_attr( $instance_id ); ?>-search">
                        <span><?php esc_html_e( 'Search documents', 'sustainable-catalyst-library' ); ?></span>
                        <input id="<?php echo esc_attr( $instance_id ); ?>-search" type="search" name="sc_doc_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'Search titles, summaries, and readable document text', 'sustainable-catalyst-library' ); ?>" autocomplete="off">
                    </label>
                <?php endif; ?>

                <?php if ( $show_filters && ! $family_lock && ! is_wp_error( $families ) && $families ) : ?>
                    <label for="<?php echo esc_attr( $instance_id ); ?>-family">
                        <span><?php esc_html_e( 'Family', 'sustainable-catalyst-library' ); ?></span>
                        <select id="<?php echo esc_attr( $instance_id ); ?>-family" name="sc_doc_family">
                            <option value=""><?php esc_html_e( 'All families', 'sustainable-catalyst-library' ); ?></option>
                            <?php foreach ( $families as $family ) : ?>
                                <option value="<?php echo esc_attr( $family->slug ); ?>" <?php selected( $filters['family'], $family->slug ); ?>><?php echo esc_html( $family->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <?php if ( $show_filters && ! $type_lock && ! is_wp_error( $types ) && $types ) : ?>
                    <label for="<?php echo esc_attr( $instance_id ); ?>-type">
                        <span><?php esc_html_e( 'Document type', 'sustainable-catalyst-library' ); ?></span>
                        <select id="<?php echo esc_attr( $instance_id ); ?>-type" name="sc_doc_type">
                            <option value=""><?php esc_html_e( 'All types', 'sustainable-catalyst-library' ); ?></option>
                            <?php foreach ( $types as $type ) : ?>
                                <option value="<?php echo esc_attr( $type->slug ); ?>" <?php selected( $filters['type'], $type->slug ); ?>><?php echo esc_html( $type->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <?php if ( $show_filters ) : ?>
                    <label for="<?php echo esc_attr( $instance_id ); ?>-lifecycle">
                        <span><?php esc_html_e( 'Lifecycle', 'sustainable-catalyst-library' ); ?></span>
                        <select id="<?php echo esc_attr( $instance_id ); ?>-lifecycle" name="sc_doc_lifecycle">
                            <option value=""><?php esc_html_e( 'All statuses', 'sustainable-catalyst-library' ); ?></option>
                            <?php foreach ( self::$lifecycle_labels as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['lifecycle'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php if ( $years ) : ?>
                        <label for="<?php echo esc_attr( $instance_id ); ?>-year">
                            <span><?php esc_html_e( 'Publication year', 'sustainable-catalyst-library' ); ?></span>
                            <select id="<?php echo esc_attr( $instance_id ); ?>-year" name="sc_doc_year">
                                <option value=""><?php esc_html_e( 'All years', 'sustainable-catalyst-library' ); ?></option>
                                <?php foreach ( $years as $year ) : ?>
                                    <option value="<?php echo esc_attr( $year ); ?>" <?php selected( $filters['year'], $year ); ?>><?php echo esc_html( $year ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ( $versions ) : ?>
                        <label for="<?php echo esc_attr( $instance_id ); ?>-version">
                            <span><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></span>
                            <select id="<?php echo esc_attr( $instance_id ); ?>-version" name="sc_doc_version">
                                <option value=""><?php esc_html_e( 'All versions', 'sustainable-catalyst-library' ); ?></option>
                                <?php foreach ( $versions as $version ) : ?>
                                    <option value="<?php echo esc_attr( $version ); ?>" <?php selected( $filters['version'], $version ); ?>><?php echo esc_html( $version ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <label for="<?php echo esc_attr( $instance_id ); ?>-sort">
                        <span><?php esc_html_e( 'Sort', 'sustainable-catalyst-library' ); ?></span>
                        <select id="<?php echo esc_attr( $instance_id ); ?>-sort" name="sc_doc_sort">
                            <?php foreach ( $this->sort_labels() as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['sort'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                </div>
            </fieldset>
            <div class="sc-public-document-filter__actions">
                <button type="submit"><?php esc_html_e( 'Apply filters', 'sustainable-catalyst-library' ); ?></button>
                <?php if ( $this->has_active_filters( $filters ) ) : ?>
                    <a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear filters', 'sustainable-catalyst-library' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    private function render_document_row( $post_id, $featured = false ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $pdf = SC_Library_Foundation_Pages::get_pdf_health( $post_id );
        $families = wp_get_object_terms( $post_id, self::TAX_FAMILY );
        $types = wp_get_object_terms( $post_id, self::TAX_TYPE );
        $family = ! is_wp_error( $families ) && $families ? $families[0] : null;
        $type = ! is_wp_error( $types ) && $types ? $types[0] : null;
        $family_url = $family ? get_term_link( $family ) : '';
        $type_url = $type ? get_term_link( $type ) : '';
        $version = (string) get_post_meta( $post_id, self::META_VERSION, true );
        $publication_date = (string) get_post_meta( $post_id, self::META_PUBLICATION_DATE, true );
        $lifecycle = sanitize_key( (string) get_post_meta( $post_id, self::META_LIFECYCLE, true ) ) ?: 'current';
        $page_count = absint( get_post_meta( $post_id, self::META_PAGE_COUNT, true ) );
        $file_size = '';

        if ( 'ready' === $pdf['status'] ) {
            $file = get_attached_file( $pdf['attachment_id'] );
            if ( $file && is_file( $file ) ) {
                $file_size = size_format( filesize( $file ) );
            }
        }

        ob_start();
        ?>
        <article class="sc-public-document-row <?php echo $featured ? 'is-featured' : ''; ?>">
            <div class="sc-public-document-row__main">
                <div class="sc-public-document-row__eyebrow">
                    <?php if ( $family && ! is_wp_error( $family_url ) ) : ?><a href="<?php echo esc_url( $family_url ); ?>"><?php echo esc_html( $family->name ); ?></a><?php endif; ?>
                    <?php if ( $type && ! is_wp_error( $type_url ) ) : ?><a href="<?php echo esc_url( $type_url ); ?>"><?php echo esc_html( $type->name ); ?></a><?php endif; ?>
                    <span class="is-<?php echo esc_attr( $lifecycle ); ?>"><?php echo esc_html( $this->lifecycle_label( $lifecycle ) ); ?></span>
                </div>
                <h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
                <?php if ( has_excerpt( $post_id ) ) : ?><p><?php echo esc_html( get_the_excerpt( $post_id ) ); ?></p><?php endif; ?>
                <dl class="sc-public-document-row__metadata">
                    <?php if ( $publication_date ) : ?><div><dt><?php esc_html_e( 'Published', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $this->format_date( $publication_date ) ); ?></dd></div><?php endif; ?>
                    <?php if ( $version ) : ?><div><dt><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $version ); ?></dd></div><?php endif; ?>
                    <?php if ( $page_count ) : ?><div><dt><?php esc_html_e( 'Length', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( sprintf( _n( '%d page', '%d pages', $page_count, 'sustainable-catalyst-library' ), $page_count ) ); ?></dd></div><?php endif; ?>
                    <?php if ( $file_size ) : ?><div><dt><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $file_size ); ?></dd></div><?php endif; ?>
                </dl>
            </div>
            <nav class="sc-public-document-row__actions" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', 'sustainable-catalyst-library' ), get_the_title( $post_id ) ) ); ?>">
                <a class="sc-public-document-row__primary" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'Read document', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">→</span></a>
                <?php if ( 'ready' === $pdf['status'] ) : ?>
                    <a href="<?php echo esc_url( $pdf['url'] ); ?>" target="_blank" rel="noopener" type="application/pdf"><?php esc_html_e( 'Open PDF', 'sustainable-catalyst-library' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'sustainable-catalyst-library' ); ?></span></a>
                    <a href="<?php echo esc_url( $pdf['url'] ); ?>" download="<?php echo esc_attr( $pdf['filename'] ); ?>" type="application/pdf"><?php esc_html_e( 'Download PDF', 'sustainable-catalyst-library' ); ?><span class="screen-reader-text"><?php echo $file_size ? esc_html( ' — ' . $file_size ) : ''; ?></span></a>
                <?php endif; ?>
            </nav>
        </article>
        <?php
        return ob_get_clean();
    }

    private function render_family_index( $exclude_term_id = 0, $limit = 0 ) {
        $cache_suffix = 'family-index|' . absint( $exclude_term_id ) . '|' . absint( $limit );
        $cached = SC_Library_Document_Repository_Hardening::cache_get( $cache_suffix );
        if ( false !== $cached ) {
            return (string) $cached;
        }

        $families = get_terms(
            array(
                'taxonomy'   => self::TAX_FAMILY,
                'hide_empty' => false,
            )
        );
        if ( is_wp_error( $families ) || ! $families ) {
            return '<div class="sc-document-library__empty"><strong>' . esc_html__( 'No document families are available yet.', 'sustainable-catalyst-library' ) . '</strong></div>';
        }

        usort(
            $families,
            function( $left, $right ) {
                $left_featured = absint( get_term_meta( $left->term_id, self::META_FAMILY_FEATURED, true ) );
                $right_featured = absint( get_term_meta( $right->term_id, self::META_FAMILY_FEATURED, true ) );
                if ( $left_featured !== $right_featured ) {
                    return $right_featured <=> $left_featured;
                }
                $left_order = absint( get_term_meta( $left->term_id, self::META_FAMILY_ORDER, true ) );
                $right_order = absint( get_term_meta( $right->term_id, self::META_FAMILY_ORDER, true ) );
                if ( $left_order !== $right_order ) {
                    return $left_order <=> $right_order;
                }
                return strcasecmp( $left->name, $right->name );
            }
        );

        $rows = array();
        foreach ( $families as $family ) {
            if ( $exclude_term_id && $family->term_id === $exclude_term_id ) {
                continue;
            }
            $rows[] = $family;
            if ( $limit && count( $rows ) >= $limit ) {
                break;
            }
        }

        ob_start();
        ?>
        <div class="sc-document-family-index">
            <?php foreach ( $rows as $family ) : ?>
                <?php
                $url = get_term_link( $family );
                if ( is_wp_error( $url ) ) {
                    continue;
                }
                $kicker = (string) get_term_meta( $family->term_id, self::META_FAMILY_KICKER, true );
                ?>
                <article class="sc-document-family-index__row">
                    <div>
                        <p><?php echo esc_html( $kicker ?: __( 'Document Family', 'sustainable-catalyst-library' ) ); ?></p>
                        <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $family->name ); ?></a></h3>
                        <?php if ( $family->description ) : ?><div><?php echo wp_kses_post( wpautop( wp_trim_words( $family->description, 30, '…' ) ) ); ?></div><?php endif; ?>
                    </div>
                    <div class="sc-document-family-index__count">
                        <strong><?php echo esc_html( number_format_i18n( $family->count ) ); ?></strong>
                        <span><?php echo esc_html( _n( 'document', 'documents', $family->count, 'sustainable-catalyst-library' ) ); ?></span>
                    </div>
                    <a class="sc-document-family-index__action" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open family', 'sustainable-catalyst-library' ); ?> →</a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
        return SC_Library_Document_Repository_Hardening::cache_set( $cache_suffix, ob_get_clean() );
    }

    private function document_query_args( $filters, $page, $per_page, $exclude_ids ) {
        $args = array(
            'post_type'           => self::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => $page,
            's'                   => $filters['q'],
            'ignore_sticky_posts' => true,
            'post__not_in'        => array_map( 'absint', $exclude_ids ),
            'no_found_rows'       => false,
        );

        $tax_query = array( 'relation' => 'AND' );
        if ( $filters['family'] ) {
            $tax_query[] = array(
                'taxonomy' => self::TAX_FAMILY,
                'field'    => 'slug',
                'terms'    => $filters['family'],
            );
        }
        if ( $filters['type'] ) {
            $tax_query[] = array(
                'taxonomy' => self::TAX_TYPE,
                'field'    => 'slug',
                'terms'    => $filters['type'],
            );
        }
        if ( count( $tax_query ) > 1 ) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = array( 'relation' => 'AND' );
        if ( $filters['lifecycle'] ) {
            $meta_query[] = array(
                'key'     => self::META_LIFECYCLE,
                'value'   => $filters['lifecycle'],
                'compare' => '=',
            );
        }
        if ( $filters['year'] ) {
            $meta_query[] = array(
                'key'     => self::META_PUBLICATION_DATE,
                'value'   => $filters['year'],
                'compare' => 'LIKE',
            );
        }
        if ( $filters['version'] ) {
            $meta_query[] = array(
                'key'     => self::META_VERSION,
                'value'   => $filters['version'],
                'compare' => '=',
            );
        }
        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        switch ( $filters['sort'] ) {
            case 'newest':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'oldest':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'updated':
                $args['orderby'] = 'modified';
                $args['order'] = 'DESC';
                break;
            case 'repository':
                $args['meta_key'] = self::META_ORDER;
                $args['orderby'] = array(
                    'meta_value_num' => 'ASC',
                    'title'          => 'ASC',
                );
                break;
            case 'title_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
            case 'title_asc':
            default:
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
        }

        return $args;
    }

    private function featured_document_ids( $family, $type, $limit ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, absint( $limit ) ),
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => self::META_FEATURED,
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
            'meta_key'       => self::META_ORDER,
            'orderby'        => array(
                'meta_value_num' => 'ASC',
                'modified'       => 'DESC',
            ),
        );

        $tax_query = array( 'relation' => 'AND' );
        if ( $family ) {
            $tax_query[] = array(
                'taxonomy' => self::TAX_FAMILY,
                'field'    => 'slug',
                'terms'    => $family,
            );
        }
        if ( $type ) {
            $tax_query[] = array(
                'taxonomy' => self::TAX_TYPE,
                'field'    => 'slug',
                'terms'    => $type,
            );
        }
        if ( count( $tax_query ) > 1 ) {
            $args['tax_query'] = $tax_query;
        }

        return array_map( 'absint', get_posts( $args ) );
    }

    private function request_filters() {
        $lifecycle = sanitize_key( wp_unslash( $_GET['sc_doc_lifecycle'] ?? '' ) );
        if ( ! array_key_exists( $lifecycle, self::$lifecycle_labels ) ) {
            $lifecycle = '';
        }

        $sort = sanitize_key( wp_unslash( $_GET['sc_doc_sort'] ?? 'title_asc' ) );
        if ( ! array_key_exists( $sort, $this->sort_labels() ) ) {
            $sort = 'title_asc';
        }

        $year = sanitize_text_field( wp_unslash( $_GET['sc_doc_year'] ?? '' ) );
        if ( $year && ! preg_match( '/^\d{4}$/', $year ) ) {
            $year = '';
        }

        return array(
            'q'         => sanitize_text_field( wp_unslash( $_GET['sc_doc_q'] ?? '' ) ),
            'family'    => sanitize_title( wp_unslash( $_GET['sc_doc_family'] ?? '' ) ),
            'type'      => sanitize_title( wp_unslash( $_GET['sc_doc_type'] ?? '' ) ),
            'lifecycle' => $lifecycle,
            'year'      => $year,
            'version'   => sanitize_text_field( wp_unslash( $_GET['sc_doc_version'] ?? '' ) ),
            'sort'      => $sort,
        );
    }

    private function filter_query_args( $filters ) {
        $mapping = array(
            'q'         => 'sc_doc_q',
            'family'    => 'sc_doc_family',
            'type'      => 'sc_doc_type',
            'lifecycle' => 'sc_doc_lifecycle',
            'year'      => 'sc_doc_year',
            'version'   => 'sc_doc_version',
            'sort'      => 'sc_doc_sort',
        );
        $args = array();
        foreach ( $mapping as $key => $query_key ) {
            if ( ! empty( $filters[ $key ] ) && ! ( 'sort' === $key && 'title_asc' === $filters[ $key ] ) ) {
                $args[ $query_key ] = $filters[ $key ];
            }
        }
        return $args;
    }

    private function has_active_filters( $filters ) {
        return (bool) (
            $filters['q']
            || $filters['family']
            || $filters['type']
            || $filters['lifecycle']
            || $filters['year']
            || $filters['version']
            || 'title_asc' !== $filters['sort']
        );
    }

    private function repository_base_url( $family_lock, $type_lock ) {
        if ( $family_lock ) {
            $term = get_term_by( 'slug', $family_lock, self::TAX_FAMILY );
            if ( $term ) {
                $url = get_term_link( $term );
                if ( ! is_wp_error( $url ) ) {
                    return $url;
                }
            }
        }

        if ( $type_lock ) {
            $term = get_term_by( 'slug', $type_lock, self::TAX_TYPE );
            if ( $term ) {
                $url = get_term_link( $term );
                if ( ! is_wp_error( $url ) ) {
                    return $url;
                }
            }
        }

        if ( get_query_var( self::QUERY_VAR ) ) {
            return home_url( '/documents/' );
        }

        if ( is_singular() ) {
            return get_permalink( get_queried_object_id() );
        }

        return remove_query_arg(
            array(
                'sc_doc_q',
                'sc_doc_family',
                'sc_doc_type',
                'sc_doc_lifecycle',
                'sc_doc_year',
                'sc_doc_version',
                'sc_doc_sort',
                'sc_doc_page',
            )
        );
    }

    private function repository_counts() {
        $cached = SC_Library_Document_Repository_Hardening::cache_get( 'repository-counts' );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $count = wp_count_posts( self::POST_TYPE );
        $documents = isset( $count->publish ) ? absint( $count->publish ) : 0;

        $families = wp_count_terms(
            array(
                'taxonomy'   => self::TAX_FAMILY,
                'hide_empty' => true,
            )
        );
        if ( is_wp_error( $families ) ) {
            $families = 0;
        }

        $current = new WP_Query(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::META_LIFECYCLE,
                        'value'   => 'current',
                        'compare' => '=',
                    ),
                ),
            )
        );

        $latest = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        return SC_Library_Document_Repository_Hardening::cache_set(
            'repository-counts',
            array(
                'documents' => $documents,
                'families'  => absint( $families ),
                'current'   => absint( $current->found_posts ),
                'updated'   => $latest ? get_the_modified_date( get_option( 'date_format' ), $latest[0] ) : __( 'Not yet', 'sustainable-catalyst-library' ),
            )
        );
    }

    private function meta_values( $meta_key, $years_only ) {
        $cache_suffix = 'meta-values|' . sanitize_key( $meta_key ) . '|' . ( $years_only ? 'years' : 'values' );
        $cached = SC_Library_Document_Repository_Hardening::cache_get( $cache_suffix );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
        global $wpdb;

        $values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s
                   AND p.post_type = %s
                   AND p.post_status = 'publish'
                   AND pm.meta_value <> ''",
                $meta_key,
                self::POST_TYPE
            )
        );

        $values = array_values(
            array_unique(
                array_filter(
                    array_map(
                        function( $value ) use ( $years_only ) {
                            $value = sanitize_text_field( $value );
                            if ( $years_only ) {
                                return preg_match( '/^(\d{4})/', $value, $matches ) ? $matches[1] : '';
                            }
                            return $value;
                        },
                        $values
                    )
                )
            )
        );

        if ( $years_only ) {
            rsort( $values, SORT_NUMERIC );
        } else {
            natcasesort( $values );
            $values = array_values( $values );
        }

        return SC_Library_Document_Repository_Hardening::cache_set( $cache_suffix, $values );
    }

    private function sort_labels() {
        return array(
            'title_asc'  => __( 'Title A–Z', 'sustainable-catalyst-library' ),
            'title_desc' => __( 'Title Z–A', 'sustainable-catalyst-library' ),
            'newest'     => __( 'Newest first', 'sustainable-catalyst-library' ),
            'oldest'     => __( 'Oldest first', 'sustainable-catalyst-library' ),
            'updated'    => __( 'Recently updated', 'sustainable-catalyst-library' ),
            'repository' => __( 'Repository order', 'sustainable-catalyst-library' ),
        );
    }

    private function lifecycle_label( $lifecycle ) {
        return self::$lifecycle_labels[ $lifecycle ] ?? __( 'Unclassified', 'sustainable-catalyst-library' );
    }

    private function format_date( $date ) {
        $timestamp = strtotime( $date );
        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
    }

    public function render_document_repository_settings( $post ) {
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
            return;
        }

        $featured = (bool) get_post_meta( $post->ID, self::META_FEATURED, true );
        $order = absint( get_post_meta( $post->ID, self::META_ORDER, true ) );
        $types = get_terms(
            array(
                'taxonomy'   => self::TAX_TYPE,
                'hide_empty' => false,
                'orderby'    => 'name',
            )
        );
        $assigned = wp_get_object_terms( $post->ID, self::TAX_TYPE, array( 'fields' => 'ids' ) );
        $type_id = ! is_wp_error( $assigned ) && $assigned ? absint( $assigned[0] ) : 0;

        wp_nonce_field( 'sc_library_v230_save_repository_settings', 'sc_library_v230_repository_nonce' );
        ?>
        <details class="sc-document-repository-editor">
            <summary><?php esc_html_e( 'Public repository settings', 'sustainable-catalyst-library' ); ?></summary>
            <div class="sc-document-repository-editor__grid">
                <label>
                    <span><?php esc_html_e( 'Document type', 'sustainable-catalyst-library' ); ?></span>
                    <select name="sc_document_repository_type">
                        <option value=""><?php esc_html_e( 'General document', 'sustainable-catalyst-library' ); ?></option>
                        <?php if ( ! is_wp_error( $types ) ) : foreach ( $types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type->term_id ); ?>" <?php selected( $type_id, $type->term_id ); ?>><?php echo esc_html( $type->name ); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e( 'Repository order', 'sustainable-catalyst-library' ); ?></span>
                    <input type="number" min="0" step="1" name="sc_document_repository_order" value="<?php echo esc_attr( $order ); ?>">
                </label>
                <label class="sc-document-repository-editor__featured">
                    <input type="checkbox" name="sc_document_repository_featured" value="1" <?php checked( $featured ); ?>>
                    <span><?php esc_html_e( 'Feature and pin this document in its public repository view.', 'sustainable-catalyst-library' ); ?></span>
                </label>
            </div>
        </details>
        <?php
    }

    public function save_document_repository_settings( $post_id, $post, $update ) {
        if ( ! $post || self::POST_TYPE !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_v230_repository_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_v230_repository_nonce'] ) ), 'sc_library_v230_save_repository_settings' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, self::META_FEATURED, isset( $_POST['sc_document_repository_featured'] ) ? 1 : 0 );
        update_post_meta( $post_id, self::META_ORDER, absint( wp_unslash( $_POST['sc_document_repository_order'] ?? 0 ) ) );

        $type_id = absint( wp_unslash( $_POST['sc_document_repository_type'] ?? 0 ) );
        if ( $type_id && term_exists( $type_id, self::TAX_TYPE ) ) {
            wp_set_object_terms( $post_id, array( $type_id ), self::TAX_TYPE, false );
        } else {
            wp_set_object_terms( $post_id, array(), self::TAX_TYPE, false );
        }
    }

    public function document_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $updated['sc_document_repository_type'] = __( 'Type', 'sustainable-catalyst-library' );
                $updated['sc_document_repository_featured'] = __( 'Repository', 'sustainable-catalyst-library' );
            }
            $updated[ $key ] = $label;
        }
        return $updated;
    }

    public function document_column_content( $column, $post_id ) {
        if ( 'sc_document_repository_type' === $column ) {
            $types = wp_get_object_terms( $post_id, self::TAX_TYPE );
            echo ! is_wp_error( $types ) && $types ? esc_html( $types[0]->name ) : esc_html__( 'General document', 'sustainable-catalyst-library' );
        } elseif ( 'sc_document_repository_featured' === $column ) {
            if ( get_post_meta( $post_id, self::META_FEATURED, true ) ) {
                echo '<strong class="sc-admin-state is-ready">' . esc_html__( 'Featured', 'sustainable-catalyst-library' ) . '</strong>';
            } else {
                echo esc_html__( 'Standard', 'sustainable-catalyst-library' );
            }
            $order = absint( get_post_meta( $post_id, self::META_ORDER, true ) );
            if ( $order ) {
                echo '<span class="sc-admin-state-detail">' . esc_html( sprintf( __( 'Order %d', 'sustainable-catalyst-library' ), $order ) ) . '</span>';
            }
        }
    }

    public function family_add_fields() {
        ?>
        <div class="form-field">
            <label for="sc-document-family-kicker"><?php esc_html_e( 'Public kicker', 'sustainable-catalyst-library' ); ?></label>
            <input id="sc-document-family-kicker" type="text" name="sc_document_family_kicker" value="" maxlength="120">
            <p><?php esc_html_e( 'A short label shown above the family title. The standard Description field becomes the public editorial introduction.', 'sustainable-catalyst-library' ); ?></p>
        </div>
        <div class="form-field">
            <label for="sc-document-family-order"><?php esc_html_e( 'Repository order', 'sustainable-catalyst-library' ); ?></label>
            <input id="sc-document-family-order" type="number" name="sc_document_family_order" value="0" min="0" step="1">
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="sc_document_family_featured" value="1"> <?php esc_html_e( 'Feature this family near the top of the repository.', 'sustainable-catalyst-library' ); ?></label>
        </div>
        <?php
    }

    public function family_edit_fields( $term, $taxonomy ) {
        $kicker = (string) get_term_meta( $term->term_id, self::META_FAMILY_KICKER, true );
        $order = absint( get_term_meta( $term->term_id, self::META_FAMILY_ORDER, true ) );
        $featured = (bool) get_term_meta( $term->term_id, self::META_FAMILY_FEATURED, true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="sc-document-family-kicker"><?php esc_html_e( 'Public kicker', 'sustainable-catalyst-library' ); ?></label></th>
            <td><input id="sc-document-family-kicker" type="text" name="sc_document_family_kicker" value="<?php echo esc_attr( $kicker ); ?>" maxlength="120"><p class="description"><?php esc_html_e( 'The standard Description field becomes the public editorial introduction.', 'sustainable-catalyst-library' ); ?></p></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="sc-document-family-order"><?php esc_html_e( 'Repository order', 'sustainable-catalyst-library' ); ?></label></th>
            <td><input id="sc-document-family-order" type="number" name="sc_document_family_order" value="<?php echo esc_attr( $order ); ?>" min="0" step="1"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e( 'Featured family', 'sustainable-catalyst-library' ); ?></th>
            <td><label><input type="checkbox" name="sc_document_family_featured" value="1" <?php checked( $featured ); ?>> <?php esc_html_e( 'Feature this family near the top of the repository.', 'sustainable-catalyst-library' ); ?></label></td>
        </tr>
        <?php
    }

    public function save_family_fields( $term_id ) {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        update_term_meta( $term_id, self::META_FAMILY_KICKER, sanitize_text_field( wp_unslash( $_POST['sc_document_family_kicker'] ?? '' ) ) );
        update_term_meta( $term_id, self::META_FAMILY_ORDER, absint( wp_unslash( $_POST['sc_document_family_order'] ?? 0 ) ) );
        update_term_meta( $term_id, self::META_FAMILY_FEATURED, isset( $_POST['sc_document_family_featured'] ) ? 1 : 0 );
    }

    public function register_repository_admin_page() {
        add_submenu_page(
            'sc-library',
            __( 'Public Document Repository', 'sustainable-catalyst-library' ),
            __( 'Public Repository', 'sustainable-catalyst-library' ),
            'manage_options',
            'sc-library-public-document-repository',
            array( $this, 'render_repository_admin_page' )
        );
    }

    public function render_repository_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $counts = $this->repository_counts();
        $families = get_terms( array( 'taxonomy' => self::TAX_FAMILY, 'hide_empty' => false ) );
        $types = get_terms( array( 'taxonomy' => self::TAX_TYPE, 'hide_empty' => false ) );
        $rewrite_rules = get_option( 'rewrite_rules', array() );
        $route_ready = is_array( $rewrite_rules ) && array_key_exists( '^documents/?$', $rewrite_rules );
        ?>
        <div class="wrap sc-document-repository-admin">
            <h1><?php esc_html_e( 'Public Document Repository', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Manage the generated /documents/ repository, document family landing pages, public filters, and repository presentation.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-pdf-bulk-cards">
                <div><strong><?php echo esc_html( $counts['documents'] ); ?></strong><span><?php esc_html_e( 'Published documents', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $counts['families'] ); ?></strong><span><?php esc_html_e( 'Active families', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( is_wp_error( $types ) ? 0 : count( $types ) ); ?></strong><span><?php esc_html_e( 'Document types', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo $route_ready ? esc_html__( 'Ready', 'sustainable-catalyst-library' ) : esc_html__( 'Repair', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( '/documents/ route', 'sustainable-catalyst-library' ); ?></span></div>
            </div>

            <table class="widefat striped sc-document-repository-admin__table">
                <tbody>
                    <tr><th><?php esc_html_e( 'Repository URL', 'sustainable-catalyst-library' ); ?></th><td><a href="<?php echo esc_url( home_url( '/documents/' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/documents/' ) ); ?></a></td></tr>
                    <tr><th><?php esc_html_e( 'Repository shortcode', 'sustainable-catalyst-library' ); ?></th><td><code>[sc_pdf_document_repository]</code></td></tr>
                    <tr><th><?php esc_html_e( 'Family shortcode', 'sustainable-catalyst-library' ); ?></th><td><code>[sc_pdf_document_library family="foundations"]</code></td></tr>
                    <tr><th><?php esc_html_e( 'Family descriptions', 'sustainable-catalyst-library' ); ?></th><td><?php esc_html_e( 'Edit Document Families and use the standard Description field as the public introduction.', 'sustainable-catalyst-library' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Interface hardening', 'sustainable-catalyst-library' ); ?></th><td><?php esc_html_e( 'v2.3.1 keyboard, screen-reader, mobile, high-contrast, reduced-motion, pagination, and caching layer active.', 'sustainable-catalyst-library' ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Cache generation', 'sustainable-catalyst-library' ); ?></th><td><code><?php echo esc_html( SC_Library_Document_Repository_Hardening::cache_generation() ); ?></code></td></tr>
                </tbody>
            </table>

            <div class="sc-health-actions">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_v230_repair_repository_routes' ); ?>
                    <input type="hidden" name="action" value="sc_library_v230_repair_repository_routes">
                    <?php submit_button( __( 'Repair Repository Routes', 'sustainable-catalyst-library' ), 'primary', 'submit', false ); ?>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_v230_seed_repository' ); ?>
                    <input type="hidden" name="action" value="sc_library_v230_seed_repository">
                    <?php submit_button( __( 'Seed Recommended Families and Types', 'sustainable-catalyst-library' ), 'secondary', 'submit', false ); ?>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_v231_clear_repository_cache' ); ?>
                    <input type="hidden" name="action" value="sc_library_v231_clear_repository_cache">
                    <?php submit_button( __( 'Clear Repository Cache', 'sustainable-catalyst-library' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>

            <h2><?php esc_html_e( 'Document families', 'sustainable-catalyst-library' ); ?></h2>
            <?php if ( ! is_wp_error( $families ) && $families ) : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Family', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Public URL', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Edit', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody><?php foreach ( $families as $family ) : $url = get_term_link( $family ); ?><tr><td><strong><?php echo esc_html( $family->name ); ?></strong></td><td><?php echo esc_html( $family->count ); ?></td><td><?php if ( ! is_wp_error( $url ) ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a><?php endif; ?></td><td><a href="<?php echo esc_url( get_edit_term_link( $family->term_id, self::TAX_FAMILY, self::POST_TYPE ) ); ?>"><?php esc_html_e( 'Edit family', 'sustainable-catalyst-library' ); ?></a></td></tr><?php endforeach; ?></tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function repair_repository_routes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair repository routes.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v230_repair_repository_routes' );
        flush_rewrite_rules( false );
        update_option( 'sc_library_document_repository_route_version', self::ROUTE_VERSION, false );
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-public-document-repository' ) );
        exit;
    }

    public function seed_repository_structure() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to seed repository structure.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v230_seed_repository' );
        $this->ensure_default_families();
        $this->ensure_default_types();
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-public-document-repository' ) );
        exit;
    }

    private function ensure_default_families() {
        $families = array(
            'foundations'             => array( 'name' => __( 'Foundations', 'sustainable-catalyst-library' ), 'description' => __( 'Core institutional, mission, governance, and foundational documents.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Institutional Foundations', 'sustainable-catalyst-library' ), 'order' => 10, 'featured' => 1 ),
            'research-reports'        => array( 'name' => __( 'Research Reports', 'sustainable-catalyst-library' ), 'description' => __( 'Long-form reports, findings, public research, and analytical documents.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Research Publications', 'sustainable-catalyst-library' ), 'order' => 20, 'featured' => 1 ),
            'methodology'             => array( 'name' => __( 'Methodology', 'sustainable-catalyst-library' ), 'description' => __( 'Methods, evaluation frameworks, evidence practices, and research protocols.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Methods and Evidence', 'sustainable-catalyst-library' ), 'order' => 30, 'featured' => 1 ),
            'policies-governance'     => array( 'name' => __( 'Policies and Governance', 'sustainable-catalyst-library' ), 'description' => __( 'Policies, governance records, standards, and institutional commitments.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Governance Library', 'sustainable-catalyst-library' ), 'order' => 40, 'featured' => 0 ),
            'platform-documentation'  => array( 'name' => __( 'Platform Documentation', 'sustainable-catalyst-library' ), 'description' => __( 'Public platform guides, architecture records, and operating documentation.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Platform Records', 'sustainable-catalyst-library' ), 'order' => 50, 'featured' => 0 ),
            'technical-documentation' => array( 'name' => __( 'Technical Documentation', 'sustainable-catalyst-library' ), 'description' => __( 'Technical specifications, implementation notes, and engineering documentation.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Technical Library', 'sustainable-catalyst-library' ), 'order' => 60, 'featured' => 0 ),
            'release-documentation'   => array( 'name' => __( 'Release Documentation', 'sustainable-catalyst-library' ), 'description' => __( 'Release notes, build records, deployment guides, and version documentation.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Release Archive', 'sustainable-catalyst-library' ), 'order' => 70, 'featured' => 0 ),
            'historical-archive'      => array( 'name' => __( 'Historical Archive', 'sustainable-catalyst-library' ), 'description' => __( 'Superseded, historical, and preservation-oriented document records.', 'sustainable-catalyst-library' ), 'kicker' => __( 'Institutional Archive', 'sustainable-catalyst-library' ), 'order' => 80, 'featured' => 0 ),
        );

        foreach ( $families as $slug => $definition ) {
            $term = get_term_by( 'slug', $slug, self::TAX_FAMILY );
            if ( ! $term ) {
                $created = wp_insert_term(
                    $definition['name'],
                    self::TAX_FAMILY,
                    array(
                        'slug'        => $slug,
                        'description' => $definition['description'],
                    )
                );
                if ( is_wp_error( $created ) ) {
                    continue;
                }
                $term_id = absint( $created['term_id'] );
            } else {
                $term_id = $term->term_id;
            }

            if ( ! get_term_meta( $term_id, self::META_FAMILY_KICKER, true ) ) {
                update_term_meta( $term_id, self::META_FAMILY_KICKER, $definition['kicker'] );
            }
            if ( ! get_term_meta( $term_id, self::META_FAMILY_ORDER, true ) ) {
                update_term_meta( $term_id, self::META_FAMILY_ORDER, $definition['order'] );
            }
            if ( ! metadata_exists( 'term', $term_id, self::META_FAMILY_FEATURED ) ) {
                update_term_meta( $term_id, self::META_FAMILY_FEATURED, $definition['featured'] );
            }
        }
    }

    private function ensure_default_types() {
        $types = array(
            'general-document'        => __( 'General Document', 'sustainable-catalyst-library' ),
            'foundation-document'     => __( 'Foundation Document', 'sustainable-catalyst-library' ),
            'research-report'         => __( 'Research Report', 'sustainable-catalyst-library' ),
            'methodology-document'    => __( 'Methodology Document', 'sustainable-catalyst-library' ),
            'policy-document'         => __( 'Policy Document', 'sustainable-catalyst-library' ),
            'platform-documentation'  => __( 'Platform Documentation', 'sustainable-catalyst-library' ),
            'technical-documentation' => __( 'Technical Documentation', 'sustainable-catalyst-library' ),
            'release-documentation'   => __( 'Release Documentation', 'sustainable-catalyst-library' ),
            'archive-document'        => __( 'Archive Document', 'sustainable-catalyst-library' ),
        );

        foreach ( $types as $slug => $name ) {
            if ( ! term_exists( $slug, self::TAX_TYPE ) ) {
                wp_insert_term( $name, self::TAX_TYPE, array( 'slug' => $slug ) );
            }
        }
    }

    private function migrate_public_repository_once() {
        if ( self::VERSION === get_option( 'sc_library_document_repository_migration_version' ) ) {
            return;
        }

        $family_type_map = array(
            'foundations'             => 'foundation-document',
            'research-reports'        => 'research-report',
            'methodology'             => 'methodology-document',
            'policies-governance'     => 'policy-document',
            'platform-documentation'  => 'platform-documentation',
            'technical-documentation' => 'technical-documentation',
            'release-documentation'   => 'release-documentation',
            'historical-archive'      => 'archive-document',
        );

        $summary = array(
            'documents' => 0,
            'types'     => 0,
            'orders'    => 0,
            'time'      => current_time( 'mysql' ),
        );

        $document_ids = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $order = 10;
        foreach ( $document_ids as $document_id ) {
            $summary['documents']++;

            $types = wp_get_object_terms( $document_id, self::TAX_TYPE, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $types ) || ! $types ) {
                $families = wp_get_object_terms( $document_id, self::TAX_FAMILY );
                $type_slug = 'general-document';
                if ( ! is_wp_error( $families ) && $families ) {
                    $type_slug = $family_type_map[ $families[0]->slug ] ?? 'general-document';
                }
                $type = get_term_by( 'slug', $type_slug, self::TAX_TYPE );
                if ( $type ) {
                    wp_set_object_terms( $document_id, array( $type->term_id ), self::TAX_TYPE, false );
                    $summary['types']++;
                }
            }

            if ( ! metadata_exists( 'post', $document_id, self::META_ORDER ) ) {
                update_post_meta( $document_id, self::META_ORDER, $order );
                $summary['orders']++;
            }
            $order += 10;
        }

        update_option( 'sc_library_document_repository_migration_summary', $summary, false );
        update_option( 'sc_library_document_repository_migration_version', self::VERSION, false );
    }
}
