<?php
/**
 * Curated Homepage Spotlight console.
 *
 * Every public category page and every card is explicitly created, ordered,
 * enabled, and scheduled by an administrator. Taxonomies may assist source
 * search, but they never populate, group, reorder, or backfill the widget.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Homepage_Spotlight {
    public const VERSION = '4.1.0';
    public const ITEM_POST_TYPE = 'sc_home_spotlight';
    public const PAGE_POST_TYPE = 'sc_spot_page';
    public const SHORTCODE = 'sc_homepage_spotlight';
    public const CACHE_KEY = 'sc_library_homepage_spotlight_pages_v410';
    public const CAPABILITY = 'manage_options';

    private const META_PAGE_DESCRIPTION = '_sc_spotlight_page_description';
    private const META_PAGE_ENABLED = '_sc_spotlight_page_enabled';
    private const META_PAGE_ITEM_LIMIT = '_sc_spotlight_page_item_limit';

    private const META_PAGE_ID = '_sc_spotlight_page_id';
    private const META_SOURCE_TYPE = '_sc_spotlight_source_type';
    private const META_SOURCE_ID = '_sc_spotlight_source_id';
    private const META_LABEL = '_sc_spotlight_label';
    private const META_HEADLINE = '_sc_spotlight_headline';
    private const META_SUMMARY = '_sc_spotlight_summary';
    private const META_ACTION_LABEL = '_sc_spotlight_action_label';
    private const META_URL = '_sc_spotlight_url';
    private const META_USE_CANONICAL = '_sc_spotlight_use_canonical';
    private const META_SHOW_THUMBNAIL = '_sc_spotlight_show_thumbnail';
    private const META_SHOW_METADATA = '_sc_spotlight_show_metadata';
    private const META_DISMISSIBLE = '_sc_spotlight_dismissible';
    private const META_ENABLED = '_sc_spotlight_enabled';
    private const META_START_AT = '_sc_spotlight_start_at';
    private const META_END_AT = '_sc_spotlight_end_at';

    private static bool $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ), 40 );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ), 80 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_source_meta_boxes' ), 90 );

        add_action( 'admin_post_sc_library_spotlight_save_page', array( $this, 'handle_save_page' ) );
        add_action( 'admin_post_sc_library_spotlight_page_action', array( $this, 'handle_page_action' ) );
        add_action( 'admin_post_sc_library_spotlight_starter_pages', array( $this, 'handle_starter_pages' ) );
        add_action( 'admin_post_sc_library_spotlight_save_item', array( $this, 'handle_save_item' ) );
        add_action( 'admin_post_sc_library_spotlight_item_action', array( $this, 'handle_item_action' ) );
        add_action( 'admin_post_sc_library_spotlight_orders', array( $this, 'handle_orders' ) );
        add_action( 'wp_ajax_sc_library_spotlight_search_sources', array( $this, 'ajax_search_sources' ) );

        add_action( 'save_post', array( $this, 'invalidate_for_saved_post' ), 999, 3 );
        add_action( 'transition_post_status', array( $this, 'invalidate_for_status_change' ), 999, 3 );
        add_action( 'before_delete_post', array( $this, 'invalidate_cache' ) );
        add_action( 'trashed_post', array( $this, 'invalidate_cache' ) );
        add_action( 'untrashed_post', array( $this, 'invalidate_cache' ) );

        add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
    }

    /**
     * Public editorial contract, exposed for diagnostics and release tests.
     *
     * @return array<string,mixed>
     */
    public static function selection_contract(): array {
        return array(
            'manual_category_pages' => true,
            'manual_card_selection' => true,
            'manual_page_order' => true,
            'manual_card_order' => true,
            'category_names_configurable' => true,
            'category_count_configurable' => true,
            'cards_per_page' => array( 4, 5 ),
            'minimum_valid_cards_per_page' => 4,
            'taxonomy_assisted_search_only' => true,
            'taxonomy_autopopulation' => false,
            'automatic_fallback' => false,
            'automatic_latest' => false,
            'automatic_popular' => false,
            'automatic_random' => false,
            'automatic_backfill' => false,
            'empty_queue_behavior' => 'hide',
            'autoplay_default' => false,
        );
    }

    /** @return string[] */
    public static function suggested_starter_pages(): array {
        return array(
            'Sustainable Development',
            'Planetary Boundaries',
            'International Law',
            'Biology',
            'Systems Thinking',
        );
    }

    public function register_post_types(): void {
        register_post_type(
            self::PAGE_POST_TYPE,
            array(
                'labels' => array(
                    'name' => __( 'Spotlight Pages', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Spotlight Page', 'sustainable-catalyst-library' ),
                ),
                'description' => __( 'Administrator-defined subject pages for the Homepage Spotlight.', 'sustainable-catalyst-library' ),
                'public' => false,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => array( 'title', 'revisions', 'page-attributes' ),
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => true,
                'delete_with_user' => false,
            )
        );

        register_post_type(
            self::ITEM_POST_TYPE,
            array(
                'labels' => array(
                    'name' => __( 'Homepage Spotlight Cards', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Homepage Spotlight Card', 'sustainable-catalyst-library' ),
                ),
                'description' => __( 'Administrator-curated Library cards and announcements.', 'sustainable-catalyst-library' ),
                'public' => false,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => array( 'title', 'revisions', 'page-attributes' ),
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => true,
                'delete_with_user' => false,
            )
        );
    }

    public function register_admin_page(): void {
        add_submenu_page(
            'sc-library',
            __( 'Homepage Spotlight', 'sustainable-catalyst-library' ),
            __( 'Homepage Spotlight', 'sustainable-catalyst-library' ),
            self::CAPABILITY,
            'sc-library-homepage-spotlight',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( false === strpos( $hook, 'sc-library-homepage-spotlight' ) ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-homepage-spotlight-admin',
            SC_LIBRARY_URL . 'assets/css/sc-library-homepage-spotlight-admin.css',
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-homepage-spotlight-admin',
            SC_LIBRARY_URL . 'assets/js/sc-library-homepage-spotlight-admin.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-homepage-spotlight-admin',
            'SCLibrarySpotlightAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_library_spotlight_search' ),
                'searching' => __( 'Searching…', 'sustainable-catalyst-library' ),
                'noResults' => __( 'No published Library records found.', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function register_source_meta_boxes(): void {
        foreach ( $this->eligible_source_post_types() as $post_type ) {
            add_meta_box(
                'sc-library-homepage-spotlight-source',
                __( 'Homepage Spotlight', 'sustainable-catalyst-library' ),
                array( $this, 'render_source_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_source_meta_box( WP_Post $post ): void {
        $entries = get_posts(
            array(
                'post_type' => self::ITEM_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'future' ),
                'posts_per_page' => 20,
                'fields' => 'ids',
                'meta_key' => self::META_SOURCE_ID,
                'meta_value' => $post->ID,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            )
        );

        if ( empty( $entries ) ) {
            echo '<p>' . esc_html__( 'This record has not been selected for the homepage.', 'sustainable-catalyst-library' ) . '</p>';
            $url = add_query_arg(
                array(
                    'page' => 'sc-library-homepage-spotlight',
                    'source_id' => $post->ID,
                ),
                admin_url( 'admin.php' )
            );
            echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Add as Spotlight draft', 'sustainable-catalyst-library' ) . '</a></p>';
            echo '<p class="description">' . esc_html__( 'You will choose its category page and card position before publishing.', 'sustainable-catalyst-library' ) . '</p>';
            return;
        }

        echo '<ul class="sc-library-spotlight-source-status">';
        foreach ( $entries as $entry_id ) {
            $entry = get_post( $entry_id );
            if ( ! $entry ) {
                continue;
            }
            $page = get_post( absint( get_post_meta( $entry_id, self::META_PAGE_ID, true ) ) );
            $status = $this->item_status( $entry );
            $url = add_query_arg(
                array(
                    'page' => 'sc-library-homepage-spotlight',
                    'edit_item' => $entry_id,
                ),
                admin_url( 'admin.php' )
            );
            echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $entry_id ) ) . '</a><br><small>';
            echo esc_html( $page ? $page->post_title : __( 'No category', 'sustainable-catalyst-library' ) );
            echo ' · ' . esc_html( $status['label'] ) . '</small></li>';
        }
        echo '</ul>';
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage Homepage Spotlight.', 'sustainable-catalyst-library' ) );
        }

        $edit_page_id = isset( $_GET['edit_page'] ) ? absint( $_GET['edit_page'] ) : 0;
        $edit_item_id = isset( $_GET['edit_item'] ) ? absint( $_GET['edit_item'] ) : 0;
        $source_id = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0;

        $edit_page = $edit_page_id ? get_post( $edit_page_id ) : null;
        if ( $edit_page && self::PAGE_POST_TYPE !== $edit_page->post_type ) {
            $edit_page = null;
            $edit_page_id = 0;
        }
        $edit_item = $edit_item_id ? get_post( $edit_item_id ) : null;
        if ( $edit_item && self::ITEM_POST_TYPE !== $edit_item->post_type ) {
            $edit_item = null;
            $edit_item_id = 0;
        }

        $pages = $this->all_pages();
        $items = $this->all_items();
        $counts = array(
            'categories' => count( $pages ),
            'selected cards' => count( $items ),
            'active cards' => 0,
            'scheduled' => 0,
            'invalid' => 0,
            'disabled' => 0,
        );
        foreach ( $items as $item ) {
            $status = $this->item_status( $item );
            if ( 'active' === $status['key'] ) {
                $counts['active cards']++;
            } elseif ( isset( $counts[ $status['key'] ] ) ) {
                $counts[ $status['key'] ]++;
            }
        }
        ?>
        <div class="wrap sc-library-spotlight-admin">
            <h1><?php esc_html_e( 'Homepage Spotlight', 'sustainable-catalyst-library' ); ?></h1>
            <p class="sc-library-spotlight-lede"><?php esc_html_e( 'Create the subject pages you want, then choose every Library record or announcement placed on them. Category names, category count, order, and every card remain under editorial control.', 'sustainable-catalyst-library' ); ?></p>
            <?php $this->render_admin_notice(); ?>

            <div class="sc-library-spotlight-contract" role="note">
                <strong><?php esc_html_e( 'Editorial contract:', 'sustainable-catalyst-library' ); ?></strong>
                <?php esc_html_e( 'Taxonomies can help you find records, but they never create a category page or add an article. Invalid and expired cards are never replaced automatically.', 'sustainable-catalyst-library' ); ?>
            </div>

            <div class="sc-library-spotlight-metrics" aria-label="<?php esc_attr_e( 'Spotlight status summary', 'sustainable-catalyst-library' ); ?>">
                <?php foreach ( $counts as $label => $count ) : ?>
                    <div><span><?php echo esc_html( ucwords( $label ) ); ?></span><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong></div>
                <?php endforeach; ?>
            </div>

            <section class="sc-library-spotlight-section">
                <div class="sc-library-spotlight-section-heading">
                    <div>
                        <h2><?php esc_html_e( '1. Configure category pages', 'sustainable-catalyst-library' ); ?></h2>
                        <p><?php esc_html_e( 'Create, rename, reorder, enable, or replace the subject pages shown in the widget.', 'sustainable-catalyst-library' ); ?></p>
                    </div>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="sc_library_spotlight_starter_pages">
                        <?php wp_nonce_field( 'sc_library_spotlight_starter_pages' ); ?>
                        <button class="button" type="submit"><?php esc_html_e( 'Add suggested five-page starter set', 'sustainable-catalyst-library' ); ?></button>
                    </form>
                </div>
                <div class="sc-library-spotlight-admin-grid sc-library-spotlight-admin-grid--pages">
                    <div class="sc-library-spotlight-editor-card">
                        <h3><?php echo $edit_page_id ? esc_html__( 'Edit category page', 'sustainable-catalyst-library' ) : esc_html__( 'Add category page', 'sustainable-catalyst-library' ); ?></h3>
                        <?php $this->render_page_form( $edit_page_id, $this->page_form_values( $edit_page ) ); ?>
                    </div>
                    <div class="sc-library-spotlight-queue-card">
                        <?php $this->render_pages_table( $pages ); ?>
                    </div>
                </div>
            </section>

            <section class="sc-library-spotlight-section">
                <div class="sc-library-spotlight-section-heading">
                    <div>
                        <h2><?php esc_html_e( '2. Curate the cards', 'sustainable-catalyst-library' ); ?></h2>
                        <p><?php esc_html_e( 'Each category supports four or five deliberately selected cards. Position 1 becomes the lead card when a page contains five.', 'sustainable-catalyst-library' ); ?></p>
                    </div>
                    <code>[sc_homepage_spotlight autoplay="false" interval="16000"]</code>
                </div>
                <div class="sc-library-spotlight-admin-grid">
                    <div class="sc-library-spotlight-editor-card">
                        <h3><?php echo $edit_item_id ? esc_html__( 'Edit selected card', 'sustainable-catalyst-library' ) : esc_html__( 'Add selected card', 'sustainable-catalyst-library' ); ?></h3>
                        <?php $this->render_item_form( $edit_item_id, $this->item_form_values( $edit_item, $source_id ), $pages ); ?>
                    </div>
                    <div class="sc-library-spotlight-queue-card">
                        <?php $this->render_grouped_item_queue( $pages ); ?>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    /** @param array<string,mixed> $values */
    private function render_page_form( int $edit_page_id, array $values ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-library-spotlight-form">
            <input type="hidden" name="action" value="sc_library_spotlight_save_page">
            <input type="hidden" name="spotlight_page_id" value="<?php echo esc_attr( $edit_page_id ); ?>">
            <?php wp_nonce_field( 'sc_library_spotlight_save_page', 'sc_library_spotlight_nonce' ); ?>

            <label for="sc-library-spotlight-page-title"><strong><?php esc_html_e( 'Category name', 'sustainable-catalyst-library' ); ?></strong></label>
            <input class="widefat" id="sc-library-spotlight-page-title" name="page_title" type="text" value="<?php echo esc_attr( $values['title'] ); ?>" placeholder="<?php esc_attr_e( 'Sustainable Development', 'sustainable-catalyst-library' ); ?>" required>

            <label for="sc-library-spotlight-page-description"><strong><?php esc_html_e( 'Short category description', 'sustainable-catalyst-library' ); ?></strong></label>
            <textarea class="widefat" id="sc-library-spotlight-page-description" name="page_description" rows="3" maxlength="280"><?php echo esc_textarea( $values['description'] ); ?></textarea>

            <div class="sc-library-spotlight-two-column">
                <div>
                    <label for="sc-library-spotlight-page-limit"><strong><?php esc_html_e( 'Cards on this page', 'sustainable-catalyst-library' ); ?></strong></label>
                    <select class="widefat" id="sc-library-spotlight-page-limit" name="item_limit">
                        <option value="4" <?php selected( $values['item_limit'], 4 ); ?>>4</option>
                        <option value="5" <?php selected( $values['item_limit'], 5 ); ?>>5</option>
                    </select>
                </div>
                <div>
                    <label for="sc-library-spotlight-page-order"><strong><?php esc_html_e( 'Category order', 'sustainable-catalyst-library' ); ?></strong></label>
                    <input class="widefat" id="sc-library-spotlight-page-order" name="menu_order" type="number" min="0" step="1" value="<?php echo esc_attr( $values['menu_order'] ); ?>">
                </div>
            </div>

            <label><input type="checkbox" name="enabled" value="1" <?php checked( $values['enabled'], 1 ); ?>> <strong><?php esc_html_e( 'Include this category when it has valid cards', 'sustainable-catalyst-library' ); ?></strong></label>

            <div class="sc-library-spotlight-form-actions">
                <button class="button button-primary" type="submit" name="save_mode" value="publish"><?php esc_html_e( 'Save category page', 'sustainable-catalyst-library' ); ?></button>
                <button class="button" type="submit" name="save_mode" value="draft"><?php esc_html_e( 'Save disabled draft', 'sustainable-catalyst-library' ); ?></button>
                <?php if ( $edit_page_id ) : ?>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-homepage-spotlight' ) ); ?>"><?php esc_html_e( 'Add another category', 'sustainable-catalyst-library' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    /** @param array<string,mixed> $values @param WP_Post[] $pages */
    private function render_item_form( int $edit_item_id, array $values, array $pages ): void {
        $source = ! empty( $values['source_id'] ) ? get_post( (int) $values['source_id'] ) : null;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-library-spotlight-form">
            <input type="hidden" name="action" value="sc_library_spotlight_save_item">
            <input type="hidden" name="spotlight_id" value="<?php echo esc_attr( $edit_item_id ); ?>">
            <?php wp_nonce_field( 'sc_library_spotlight_save_item', 'sc_library_spotlight_nonce' ); ?>

            <div class="sc-library-spotlight-two-column">
                <div>
                    <label for="sc-library-spotlight-page-id"><strong><?php esc_html_e( 'Category page', 'sustainable-catalyst-library' ); ?></strong></label>
                    <select class="widefat" id="sc-library-spotlight-page-id" name="spotlight_page_id" required <?php disabled( empty( $pages ) ); ?>>
                        <option value=""><?php esc_html_e( 'Choose a category', 'sustainable-catalyst-library' ); ?></option>
                        <?php foreach ( $pages as $page ) : ?>
                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $values['page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sc-library-spotlight-card-order"><strong><?php esc_html_e( 'Card position', 'sustainable-catalyst-library' ); ?></strong></label>
                    <select class="widefat" id="sc-library-spotlight-card-order" name="menu_order">
                        <?php for ( $position = 1; $position <= 5; $position++ ) : ?>
                            <option value="<?php echo esc_attr( $position ); ?>" <?php selected( $values['menu_order'], $position ); ?>><?php echo esc_html( $position ); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <?php if ( empty( $pages ) ) : ?>
                <p class="notice notice-warning inline"><?php esc_html_e( 'Create at least one category page before adding cards.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>

            <fieldset>
                <legend><?php esc_html_e( 'Content source', 'sustainable-catalyst-library' ); ?></legend>
                <label><input type="radio" name="source_type" value="library" <?php checked( $values['source_type'], 'library' ); ?>> <?php esc_html_e( 'Knowledge Library record', 'sustainable-catalyst-library' ); ?></label>
                <label><input type="radio" name="source_type" value="announcement" <?php checked( $values['source_type'], 'announcement' ); ?>> <?php esc_html_e( 'Site announcement', 'sustainable-catalyst-library' ); ?></label>
            </fieldset>

            <div class="sc-library-spotlight-source-picker" data-source-section="library">
                <label for="sc-library-spotlight-source-search"><strong><?php esc_html_e( 'Selected Library record', 'sustainable-catalyst-library' ); ?></strong></label>
                <input type="hidden" id="sc-library-spotlight-source-id" name="source_id" value="<?php echo esc_attr( $values['source_id'] ); ?>">
                <input type="search" id="sc-library-spotlight-source-search" value="<?php echo esc_attr( $source ? get_the_title( $source ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Search published Library content', 'sustainable-catalyst-library' ); ?>" autocomplete="off">
                <div id="sc-library-spotlight-source-results" class="sc-library-spotlight-source-results" aria-live="polite"></div>
                <p class="description"><?php esc_html_e( 'Search may use existing Library metadata, but only your click selects the record.', 'sustainable-catalyst-library' ); ?></p>
            </div>

            <label for="sc-library-spotlight-label"><strong><?php esc_html_e( 'Card label', 'sustainable-catalyst-library' ); ?></strong></label>
            <input class="widefat" id="sc-library-spotlight-label" name="label" type="text" value="<?php echo esc_attr( $values['label'] ); ?>" placeholder="<?php esc_attr_e( 'From the Knowledge Library', 'sustainable-catalyst-library' ); ?>">

            <label for="sc-library-spotlight-headline"><strong><?php esc_html_e( 'Homepage headline', 'sustainable-catalyst-library' ); ?></strong></label>
            <input class="widefat" id="sc-library-spotlight-headline" name="headline" type="text" value="<?php echo esc_attr( $values['headline'] ); ?>" required>

            <label for="sc-library-spotlight-summary"><strong><?php esc_html_e( 'Short description', 'sustainable-catalyst-library' ); ?></strong></label>
            <textarea class="widefat" id="sc-library-spotlight-summary" name="summary" rows="4" maxlength="420"><?php echo esc_textarea( $values['summary'] ); ?></textarea>

            <div class="sc-library-spotlight-two-column">
                <div>
                    <label for="sc-library-spotlight-action-label"><strong><?php esc_html_e( 'Action label', 'sustainable-catalyst-library' ); ?></strong></label>
                    <input class="widefat" id="sc-library-spotlight-action-label" name="action_label" type="text" value="<?php echo esc_attr( $values['action_label'] ); ?>" placeholder="<?php esc_attr_e( 'Read article', 'sustainable-catalyst-library' ); ?>">
                </div>
                <div>
                    <label for="sc-library-spotlight-url"><strong><?php esc_html_e( 'Custom destination', 'sustainable-catalyst-library' ); ?></strong></label>
                    <input class="widefat" id="sc-library-spotlight-url" name="url" type="url" value="<?php echo esc_attr( $values['url'] ); ?>" placeholder="https://">
                </div>
            </div>

            <div class="sc-library-spotlight-checkboxes">
                <label><input type="checkbox" name="use_canonical" value="1" <?php checked( $values['use_canonical'], 1 ); ?>> <?php esc_html_e( 'Use the linked record’s canonical URL', 'sustainable-catalyst-library' ); ?></label>
                <label><input type="checkbox" name="show_thumbnail" value="1" <?php checked( $values['show_thumbnail'], 1 ); ?>> <?php esc_html_e( 'Show thumbnail when available', 'sustainable-catalyst-library' ); ?></label>
                <label><input type="checkbox" name="show_metadata" value="1" <?php checked( $values['show_metadata'], 1 ); ?>> <?php esc_html_e( 'Show document metadata', 'sustainable-catalyst-library' ); ?></label>
                <label data-announcement-only><input type="checkbox" name="dismissible" value="1" <?php checked( $values['dismissible'], 1 ); ?>> <?php esc_html_e( 'Allow visitors to dismiss this announcement', 'sustainable-catalyst-library' ); ?></label>
                <label><input type="checkbox" name="enabled" value="1" <?php checked( $values['enabled'], 1 ); ?>> <strong><?php esc_html_e( 'Display this card when its schedule and source are valid', 'sustainable-catalyst-library' ); ?></strong></label>
            </div>

            <div class="sc-library-spotlight-two-column">
                <div>
                    <label for="sc-library-spotlight-start"><strong><?php esc_html_e( 'Start', 'sustainable-catalyst-library' ); ?></strong></label>
                    <input class="widefat" id="sc-library-spotlight-start" name="start_at" type="datetime-local" value="<?php echo esc_attr( $values['start_at'] ); ?>">
                </div>
                <div>
                    <label for="sc-library-spotlight-end"><strong><?php esc_html_e( 'End', 'sustainable-catalyst-library' ); ?></strong></label>
                    <input class="widefat" id="sc-library-spotlight-end" name="end_at" type="datetime-local" value="<?php echo esc_attr( $values['end_at'] ); ?>">
                </div>
            </div>

            <div class="sc-library-spotlight-form-actions">
                <button class="button button-primary" type="submit" name="save_mode" value="publish" <?php disabled( empty( $pages ) ); ?>><?php esc_html_e( 'Save selected card', 'sustainable-catalyst-library' ); ?></button>
                <button class="button" type="submit" name="save_mode" value="draft" <?php disabled( empty( $pages ) ); ?>><?php esc_html_e( 'Save disabled draft', 'sustainable-catalyst-library' ); ?></button>
                <?php if ( $edit_item_id ) : ?>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-homepage-spotlight' ) ); ?>"><?php esc_html_e( 'Add another card', 'sustainable-catalyst-library' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    /** @param WP_Post[] $pages */
    private function render_pages_table( array $pages ): void {
        if ( empty( $pages ) ) {
            echo '<div class="sc-library-spotlight-empty"><h3>' . esc_html__( 'No category pages yet', 'sustainable-catalyst-library' ) . '</h3><p>' . esc_html__( 'Create your own category or add the optional starter set. Nothing appears publicly until valid cards are assigned.', 'sustainable-catalyst-library' ) . '</p></div>';
            return;
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sc_library_spotlight_orders">
            <?php wp_nonce_field( 'sc_library_spotlight_orders', 'sc_library_spotlight_nonce' ); ?>
            <div class="sc-library-spotlight-table-wrap">
                <table class="widefat striped sc-library-spotlight-queue">
                    <thead><tr><th></th><th><?php esc_html_e( 'Category page', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Cards', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Limit', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'State', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Order', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody data-spotlight-sortable data-order-step="10">
                    <?php foreach ( $pages as $page ) :
                        $enabled = (bool) get_post_meta( $page->ID, self::META_PAGE_ENABLED, true );
                        $limit = $this->page_item_limit( $page->ID );
                        $count = count( $this->items_for_page( $page->ID ) );
                        ?>
                        <tr data-spotlight-row>
                            <td class="sc-library-spotlight-drag" aria-hidden="true">⋮⋮</td>
                            <td><strong><?php echo esc_html( $page->post_title ); ?></strong><?php if ( get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ) ) : ?><br><small><?php echo esc_html( wp_trim_words( get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ), 16, '…' ) ); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                            <td><?php echo esc_html( $limit ); ?></td>
                            <td><span class="sc-library-spotlight-status sc-library-spotlight-status--<?php echo esc_attr( $enabled && 'publish' === $page->post_status ? 'active' : 'disabled' ); ?>"><?php echo esc_html( $enabled && 'publish' === $page->post_status ? __( 'Enabled', 'sustainable-catalyst-library' ) : __( 'Disabled', 'sustainable-catalyst-library' ) ); ?></span></td>
                            <td><input type="number" min="0" step="1" name="page_order[<?php echo esc_attr( $page->ID ); ?>]" value="<?php echo esc_attr( $page->menu_order ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Order for %s', 'sustainable-catalyst-library' ), $page->post_title ) ); ?>"></td>
                            <td><?php echo wp_kses_post( $this->page_actions( $page, $enabled ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p><button class="button" type="submit"><?php esc_html_e( 'Save category order', 'sustainable-catalyst-library' ); ?></button></p>
        </form>
        <?php
    }

    /** @param WP_Post[] $pages */
    private function render_grouped_item_queue( array $pages ): void {
        if ( empty( $pages ) ) {
            echo '<div class="sc-library-spotlight-empty"><h3>' . esc_html__( 'Create a category first', 'sustainable-catalyst-library' ) . '</h3><p>' . esc_html__( 'Cards are always assigned to a category page; no uncategorized public fallback exists.', 'sustainable-catalyst-library' ) . '</p></div>';
            return;
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sc_library_spotlight_orders">
            <?php wp_nonce_field( 'sc_library_spotlight_orders', 'sc_library_spotlight_nonce' ); ?>
            <?php foreach ( $pages as $page ) :
                $items = $this->items_for_page( $page->ID );
                $limit = $this->page_item_limit( $page->ID );
                ?>
                <section class="sc-library-spotlight-category-queue">
                    <div class="sc-library-spotlight-category-heading">
                        <h3><?php echo esc_html( $page->post_title ); ?></h3>
                        <span><?php echo esc_html( sprintf( _n( '%1$d selected card · %2$d-card page', '%1$d selected cards · %2$d-card page', count( $items ), 'sustainable-catalyst-library' ), count( $items ), $limit ) ); ?></span>
                    </div>
                    <?php if ( empty( $items ) ) : ?>
                        <p class="sc-library-spotlight-empty-row"><?php esc_html_e( 'No cards selected for this category.', 'sustainable-catalyst-library' ); ?></p>
                    <?php else : ?>
                        <div class="sc-library-spotlight-table-wrap">
                            <table class="widefat striped sc-library-spotlight-queue">
                                <thead><tr><th></th><th><?php esc_html_e( 'Selected card', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Position', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                                <tbody data-spotlight-sortable data-order-step="1">
                                <?php foreach ( $items as $item ) :
                                    $status = $this->item_status( $item );
                                    $source_type = get_post_meta( $item->ID, self::META_SOURCE_TYPE, true ) ?: 'library';
                                    $source_id = absint( get_post_meta( $item->ID, self::META_SOURCE_ID, true ) );
                                    ?>
                                    <tr data-spotlight-row>
                                        <td class="sc-library-spotlight-drag" aria-hidden="true">⋮⋮</td>
                                        <td><strong><?php echo esc_html( get_post_meta( $item->ID, self::META_HEADLINE, true ) ?: $item->post_title ); ?></strong><br><?php echo wp_kses_post( $this->schedule_summary( $item ) ); ?></td>
                                        <td><?php echo esc_html( 'announcement' === $source_type ? __( 'Announcement', 'sustainable-catalyst-library' ) : ( $source_id ? get_the_title( $source_id ) : __( 'Missing record', 'sustainable-catalyst-library' ) ) ); ?></td>
                                        <td><span class="sc-library-spotlight-status sc-library-spotlight-status--<?php echo esc_attr( $status['key'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span><?php if ( $status['reason'] ) : ?><br><small><?php echo esc_html( $status['reason'] ); ?></small><?php endif; ?></td>
                                        <td><input type="number" min="1" max="5" step="1" name="item_order[<?php echo esc_attr( $item->ID ); ?>]" value="<?php echo esc_attr( max( 1, min( 5, (int) $item->menu_order ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Card position for %s', 'sustainable-catalyst-library' ), $item->post_title ) ); ?>"></td>
                                        <td><?php echo wp_kses_post( $this->item_actions( $item, $status ) ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
            <?php
            $known_page_ids = array_map( static fn( WP_Post $page ): int => $page->ID, $pages );
            $orphaned = array_values( array_filter( $this->all_items(), static function ( WP_Post $item ) use ( $known_page_ids ): bool {
                $assigned_page_id = absint( get_post_meta( $item->ID, self::META_PAGE_ID, true ) );
                return ! in_array( $assigned_page_id, $known_page_ids, true );
            } ) );
            if ( ! empty( $orphaned ) ) : ?>
                <section class="sc-library-spotlight-category-queue">
                    <div class="sc-library-spotlight-category-heading">
                        <h3><?php esc_html_e( 'Unassigned or unavailable category', 'sustainable-catalyst-library' ); ?></h3>
                        <span><?php esc_html_e( 'Edit these cards and choose an available category page.', 'sustainable-catalyst-library' ); ?></span>
                    </div>
                    <div class="sc-library-spotlight-table-wrap">
                        <table class="widefat striped sc-library-spotlight-queue">
                            <thead><tr><th><?php esc_html_e( 'Selected card', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ( $orphaned as $item ) : $status = $this->item_status( $item ); ?>
                                <tr><td><strong><?php echo esc_html( get_post_meta( $item->ID, self::META_HEADLINE, true ) ?: $item->post_title ); ?></strong></td><td><span class="sc-library-spotlight-status sc-library-spotlight-status--invalid"><?php esc_html_e( 'Needs category', 'sustainable-catalyst-library' ); ?></span></td><td><?php echo wp_kses_post( $this->item_actions( $item, $status ) ); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
            <p><button class="button" type="submit"><?php esc_html_e( 'Save card positions', 'sustainable-catalyst-library' ); ?></button></p>
        </form>
        <?php
    }

    public function handle_save_page(): void {
        $this->require_admin_action( 'sc_library_spotlight_save_page', 'sc_library_spotlight_nonce' );
        $page_id = isset( $_POST['spotlight_page_id'] ) ? absint( $_POST['spotlight_page_id'] ) : 0;
        $title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';
        if ( '' === $title ) {
            $this->redirect_notice( 'category-required' );
        }
        if ( $page_id ) {
            $existing = get_post( $page_id );
            if ( ! $existing || self::PAGE_POST_TYPE !== $existing->post_type ) {
                $this->redirect_notice( 'invalid' );
            }
        }

        $save_mode = isset( $_POST['save_mode'] ) ? sanitize_key( wp_unslash( $_POST['save_mode'] ) ) : 'publish';
        $status = 'draft' === $save_mode ? 'draft' : 'publish';
        self::$saving = true;
        $result = wp_insert_post(
            array(
                'ID' => $page_id,
                'post_type' => self::PAGE_POST_TYPE,
                'post_status' => $status,
                'post_title' => $title,
                'menu_order' => isset( $_POST['menu_order'] ) ? max( 0, absint( $_POST['menu_order'] ) ) : $this->next_page_order(),
            ),
            true
        );
        self::$saving = false;
        if ( is_wp_error( $result ) ) {
            $this->redirect_notice( 'save-failed' );
        }
        $page_id = (int) $result;
        $limit = isset( $_POST['item_limit'] ) && 4 === absint( $_POST['item_limit'] ) ? 4 : 5;
        update_post_meta( $page_id, self::META_PAGE_DESCRIPTION, isset( $_POST['page_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['page_description'] ) ) : '' );
        update_post_meta( $page_id, self::META_PAGE_ITEM_LIMIT, $limit );
        update_post_meta( $page_id, self::META_PAGE_ENABLED, 'publish' === $status && isset( $_POST['enabled'] ) ? 1 : 0 );
        $this->invalidate_cache();
        $this->redirect_notice( 'category-saved', 0, $page_id );
    }

    public function handle_page_action(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage Homepage Spotlight.', 'sustainable-catalyst-library' ) );
        }
        $page_id = isset( $_GET['spotlight_page_id'] ) ? absint( $_GET['spotlight_page_id'] ) : 0;
        $command = isset( $_GET['command'] ) ? sanitize_key( wp_unslash( $_GET['command'] ) ) : '';
        check_admin_referer( 'sc_library_spotlight_page_action_' . $page_id . '_' . $command );
        $page = get_post( $page_id );
        if ( ! $page || self::PAGE_POST_TYPE !== $page->post_type ) {
            $this->redirect_notice( 'invalid' );
        }

        switch ( $command ) {
            case 'enable':
                wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
                update_post_meta( $page_id, self::META_PAGE_ENABLED, 1 );
                $notice = 'category-enabled';
                break;
            case 'disable':
                update_post_meta( $page_id, self::META_PAGE_ENABLED, 0 );
                $notice = 'category-disabled';
                break;
            case 'trash':
                wp_trash_post( $page_id );
                $notice = 'category-trashed';
                break;
            default:
                $this->redirect_notice( 'invalid' );
        }
        $this->invalidate_cache();
        $this->redirect_notice( $notice );
    }

    public function handle_starter_pages(): void {
        $this->require_admin_action( 'sc_library_spotlight_starter_pages', '_wpnonce' );
        $existing_titles = array_map(
            static fn( WP_Post $page ): string => strtolower( trim( $page->post_title ) ),
            $this->all_pages()
        );
        $order = $this->next_page_order();
        $created = 0;
        foreach ( self::suggested_starter_pages() as $title ) {
            if ( in_array( strtolower( $title ), $existing_titles, true ) ) {
                continue;
            }
            self::$saving = true;
            $page_id = wp_insert_post(
                array(
                    'post_type' => self::PAGE_POST_TYPE,
                    'post_status' => 'publish',
                    'post_title' => $title,
                    'menu_order' => $order,
                )
            );
            self::$saving = false;
            if ( $page_id ) {
                update_post_meta( $page_id, self::META_PAGE_ENABLED, 1 );
                update_post_meta( $page_id, self::META_PAGE_ITEM_LIMIT, 5 );
                update_post_meta( $page_id, self::META_PAGE_DESCRIPTION, '' );
                $created++;
                $order += 10;
            }
        }
        $this->invalidate_cache();
        $this->redirect_notice( $created ? 'starter-added' : 'starter-exists' );
    }

    public function handle_save_item(): void {
        $this->require_admin_action( 'sc_library_spotlight_save_item', 'sc_library_spotlight_nonce' );
        $item_id = isset( $_POST['spotlight_id'] ) ? absint( $_POST['spotlight_id'] ) : 0;
        if ( $item_id ) {
            $existing = get_post( $item_id );
            if ( ! $existing || self::ITEM_POST_TYPE !== $existing->post_type ) {
                $this->redirect_notice( 'invalid' );
            }
        }
        $page_id = isset( $_POST['spotlight_page_id'] ) ? absint( $_POST['spotlight_page_id'] ) : 0;
        $page = get_post( $page_id );
        if ( ! $page || self::PAGE_POST_TYPE !== $page->post_type ) {
            $this->redirect_notice( 'category-required' );
        }

        $source_type = isset( $_POST['source_type'] ) && 'announcement' === sanitize_key( wp_unslash( $_POST['source_type'] ) ) ? 'announcement' : 'library';
        $source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
        if ( 'library' === $source_type ) {
            $source = $source_id ? get_post( $source_id ) : null;
            if ( ! $source || 'publish' !== $source->post_status || ! in_array( $source->post_type, $this->eligible_source_post_types(), true ) ) {
                $this->redirect_notice( 'source-required' );
            }
        }

        $headline = isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '';
        if ( '' === $headline ) {
            $this->redirect_notice( 'headline-required' );
        }
        $save_mode = isset( $_POST['save_mode'] ) ? sanitize_key( wp_unslash( $_POST['save_mode'] ) ) : 'publish';
        $status = 'draft' === $save_mode ? 'draft' : 'publish';
        $card_order = isset( $_POST['menu_order'] ) ? max( 1, min( 5, absint( $_POST['menu_order'] ) ) ) : 1;

        self::$saving = true;
        $result = wp_insert_post(
            array(
                'ID' => $item_id,
                'post_type' => self::ITEM_POST_TYPE,
                'post_status' => $status,
                'post_title' => $headline,
                'menu_order' => $card_order,
            ),
            true
        );
        self::$saving = false;
        if ( is_wp_error( $result ) ) {
            $this->redirect_notice( 'save-failed' );
        }
        $item_id = (int) $result;

        update_post_meta( $item_id, self::META_PAGE_ID, $page_id );
        update_post_meta( $item_id, self::META_SOURCE_TYPE, $source_type );
        update_post_meta( $item_id, self::META_SOURCE_ID, 'library' === $source_type ? $source_id : 0 );
        update_post_meta( $item_id, self::META_LABEL, isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '' );
        update_post_meta( $item_id, self::META_HEADLINE, $headline );
        update_post_meta( $item_id, self::META_SUMMARY, isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '' );
        update_post_meta( $item_id, self::META_ACTION_LABEL, isset( $_POST['action_label'] ) ? sanitize_text_field( wp_unslash( $_POST['action_label'] ) ) : '' );
        update_post_meta( $item_id, self::META_URL, isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '' );
        update_post_meta( $item_id, self::META_USE_CANONICAL, isset( $_POST['use_canonical'] ) ? 1 : 0 );
        update_post_meta( $item_id, self::META_SHOW_THUMBNAIL, isset( $_POST['show_thumbnail'] ) ? 1 : 0 );
        update_post_meta( $item_id, self::META_SHOW_METADATA, isset( $_POST['show_metadata'] ) ? 1 : 0 );
        update_post_meta( $item_id, self::META_DISMISSIBLE, 'announcement' === $source_type && isset( $_POST['dismissible'] ) ? 1 : 0 );
        update_post_meta( $item_id, self::META_ENABLED, 'publish' === $status && isset( $_POST['enabled'] ) ? 1 : 0 );
        update_post_meta( $item_id, self::META_START_AT, $this->parse_local_datetime( $_POST['start_at'] ?? '' ) );
        update_post_meta( $item_id, self::META_END_AT, $this->parse_local_datetime( $_POST['end_at'] ?? '' ) );

        $this->invalidate_cache();
        $this->redirect_notice( 'card-saved', $item_id );
    }

    public function handle_item_action(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage Homepage Spotlight.', 'sustainable-catalyst-library' ) );
        }
        $item_id = isset( $_GET['spotlight_id'] ) ? absint( $_GET['spotlight_id'] ) : 0;
        $command = isset( $_GET['command'] ) ? sanitize_key( wp_unslash( $_GET['command'] ) ) : '';
        check_admin_referer( 'sc_library_spotlight_item_action_' . $item_id . '_' . $command );
        $item = get_post( $item_id );
        if ( ! $item || self::ITEM_POST_TYPE !== $item->post_type ) {
            $this->redirect_notice( 'invalid' );
        }

        switch ( $command ) {
            case 'enable':
                wp_update_post( array( 'ID' => $item_id, 'post_status' => 'publish' ) );
                update_post_meta( $item_id, self::META_ENABLED, 1 );
                $notice = 'card-enabled';
                break;
            case 'disable':
                update_post_meta( $item_id, self::META_ENABLED, 0 );
                $notice = 'card-disabled';
                break;
            case 'duplicate':
                self::$saving = true;
                $new_id = wp_insert_post(
                    array(
                        'post_type' => self::ITEM_POST_TYPE,
                        'post_status' => 'draft',
                        'post_title' => $item->post_title . ' ' . __( '(Copy)', 'sustainable-catalyst-library' ),
                        'menu_order' => $item->menu_order,
                    )
                );
                self::$saving = false;
                if ( $new_id ) {
                    foreach ( $this->item_meta_keys() as $key ) {
                        update_post_meta( $new_id, $key, get_post_meta( $item_id, $key, true ) );
                    }
                    update_post_meta( $new_id, self::META_ENABLED, 0 );
                }
                $notice = 'card-duplicated';
                break;
            case 'trash':
                wp_trash_post( $item_id );
                $notice = 'card-trashed';
                break;
            default:
                $this->redirect_notice( 'invalid' );
        }
        $this->invalidate_cache();
        $this->redirect_notice( $notice );
    }

    public function handle_orders(): void {
        $this->require_admin_action( 'sc_library_spotlight_orders', 'sc_library_spotlight_nonce' );
        $page_orders = isset( $_POST['page_order'] ) && is_array( $_POST['page_order'] ) ? wp_unslash( $_POST['page_order'] ) : array();
        $item_orders = isset( $_POST['item_order'] ) && is_array( $_POST['item_order'] ) ? wp_unslash( $_POST['item_order'] ) : array();

        self::$saving = true;
        foreach ( $page_orders as $page_id => $order ) {
            $page = get_post( absint( $page_id ) );
            if ( $page && self::PAGE_POST_TYPE === $page->post_type ) {
                wp_update_post( array( 'ID' => $page->ID, 'menu_order' => max( 0, absint( $order ) ) ) );
            }
        }
        foreach ( $item_orders as $item_id => $order ) {
            $item = get_post( absint( $item_id ) );
            if ( $item && self::ITEM_POST_TYPE === $item->post_type ) {
                wp_update_post( array( 'ID' => $item->ID, 'menu_order' => max( 1, min( 5, absint( $order ) ) ) ) );
            }
        }
        self::$saving = false;
        $this->invalidate_cache();
        $this->redirect_notice( 'order-saved' );
    }

    public function ajax_search_sources(): void {
        if ( ! current_user_can( self::CAPABILITY ) || ! check_ajax_referer( 'sc_library_spotlight_search', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( array( 'items' => array() ) );
        }
        $posts = get_posts(
            array(
                'post_type' => $this->eligible_source_post_types(),
                'post_status' => 'publish',
                'posts_per_page' => 20,
                's' => $query,
                'orderby' => 'relevance date',
                'order' => 'DESC',
                'suppress_filters' => false,
            )
        );
        $items = array();
        foreach ( $posts as $post ) {
            $type = get_post_type_object( $post->post_type );
            $items[] = array(
                'id' => $post->ID,
                'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
                'excerpt' => $this->source_summary( $post ),
                'type' => $type ? $type->labels->singular_name : $post->post_type,
            );
        }
        wp_send_json_success( array( 'items' => $items ) );
    }

    /** @param array<string,mixed>|string $atts */
    public function shortcode( $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'autoplay' => 'false',
                'rotate' => '',
                'interval' => '16000',
                'controls' => 'true',
                'tabs' => 'true',
                'loop' => 'true',
                'pause_on_hover' => 'true',
                'category_limit' => '0',
                'show_thumbnail' => '',
                'show_metadata' => '',
                'title' => __( 'Explore the Knowledge Library', 'sustainable-catalyst-library' ),
                'intro' => __( 'Selected research across the subjects currently featured by Sustainable Catalyst.', 'sustainable-catalyst-library' ),
                'empty' => 'hide',
            ),
            is_array( $atts ) ? $atts : array(),
            self::SHORTCODE
        );
        $category_limit = max( 0, absint( $atts['category_limit'] ) );
        $pages = $this->active_pages( $category_limit );
        if ( empty( $pages ) ) {
            return 'hide' === sanitize_key( (string) $atts['empty'] ) ? '' : '<div class="sc-homepage-spotlight-empty"></div>';
        }

        wp_enqueue_style(
            'sc-library-homepage-spotlight',
            SC_LIBRARY_URL . 'assets/css/sc-library-homepage-spotlight.css',
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-homepage-spotlight',
            SC_LIBRARY_URL . 'assets/js/sc-library-homepage-spotlight.js',
            array(),
            self::VERSION,
            true
        );

        $autoplay_value = '' !== (string) $atts['rotate'] ? $atts['rotate'] : $atts['autoplay'];
        $autoplay = $this->truthy( $autoplay_value );
        $controls = $this->truthy( $atts['controls'] ) && count( $pages ) > 1;
        $tabs = $this->truthy( $atts['tabs'] ) && count( $pages ) > 1;
        $loop = $this->truthy( $atts['loop'] );
        $pause_on_hover = $this->truthy( $atts['pause_on_hover'] );
        $interval = max( 8000, min( 60000, absint( $atts['interval'] ) ) );
        $show_thumbnail_override = '' === (string) $atts['show_thumbnail'] ? null : $this->truthy( $atts['show_thumbnail'] );
        $show_metadata_override = '' === (string) $atts['show_metadata'] ? null : $this->truthy( $atts['show_metadata'] );
        $heading = sanitize_text_field( (string) $atts['title'] );
        $intro = sanitize_textarea_field( (string) $atts['intro'] );
        $instance_id = wp_unique_id( 'sc-homepage-spotlight-' );
        $template = SC_LIBRARY_DIR . 'templates/homepage-spotlight.php';
        if ( ! is_readable( $template ) ) {
            return '';
        }

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    /** @return array<int,array<string,mixed>> */
    private function active_pages( int $category_limit = 0 ): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( ! is_array( $cached ) || ! isset( $cached['pages'] ) || ! is_array( $cached['pages'] ) ) {
            $cached = $this->build_public_pages();
            set_transient( self::CACHE_KEY, $cached, max( 30, (int) $cached['ttl'] ) );
        }
        $pages = $cached['pages'];
        return $category_limit > 0 ? array_slice( $pages, 0, $category_limit ) : $pages;
    }

    /** @return array{pages:array<int,array<string,mixed>>,ttl:int} */
    private function build_public_pages(): array {
        $now = current_time( 'timestamp', true );
        $next_boundary = 0;
        $public_pages = array();
        $pages = get_posts(
            array(
                'post_type' => self::PAGE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_key' => self::META_PAGE_ENABLED,
                'meta_value' => 1,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            )
        );

        foreach ( $pages as $page ) {
            $limit = $this->page_item_limit( $page->ID );
            $cards = array();
            $candidates = get_posts(
                array(
                    'post_type' => self::ITEM_POST_TYPE,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        'relation' => 'AND',
                        array( 'key' => self::META_PAGE_ID, 'value' => $page->ID, 'compare' => '=' ),
                        array( 'key' => self::META_ENABLED, 'value' => 1, 'compare' => '=' ),
                    ),
                    'orderby' => 'menu_order ID',
                    'order' => 'ASC',
                    'suppress_filters' => true,
                )
            );

            // A slot may contain scheduled replacements, but only one active
            // card can occupy that administrator-defined position at a time.
            $slot_candidates = array();
            for ( $slot = 1; $slot <= $limit; $slot++ ) {
                $slot_candidates[ $slot ] = array();
            }
            foreach ( $candidates as $candidate ) {
                $slot = max( 1, min( 5, (int) $candidate->menu_order ) );
                if ( $slot <= $limit ) {
                    $slot_candidates[ $slot ][] = $candidate;
                }
            }

            for ( $slot = 1; $slot <= $limit; $slot++ ) {
                foreach ( $slot_candidates[ $slot ] as $candidate ) {
                    $start = absint( get_post_meta( $candidate->ID, self::META_START_AT, true ) );
                    $end = absint( get_post_meta( $candidate->ID, self::META_END_AT, true ) );
                    foreach ( array( $start, $end ) as $boundary ) {
                        if ( $boundary > $now && ( 0 === $next_boundary || $boundary < $next_boundary ) ) {
                            $next_boundary = $boundary;
                        }
                    }
                    if ( $start && $start > $now ) {
                        continue;
                    }
                    if ( $end && $end <= $now ) {
                        continue;
                    }
                    $card = $this->resolve_public_item( $candidate );
                    if ( null === $card ) {
                        continue;
                    }
                    $card['slot'] = $slot;
                    $cards[] = $card;
                    break;
                }
            }
            if ( count( $cards ) < 4 ) {
                continue;
            }
            $public_pages[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'description' => sanitize_text_field( get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ) ),
                'item_limit' => $limit,
                'cards' => $cards,
            );
        }

        $ttl = 300;
        if ( $next_boundary ) {
            $ttl = max( 30, min( 300, $next_boundary - $now + 1 ) );
        }
        return array( 'pages' => $public_pages, 'ttl' => $ttl );
    }

    /** @return array<string,mixed>|null */
    private function resolve_public_item( WP_Post $entry ): ?array {
        $validation = $this->validate_item_source( $entry );
        if ( ! $validation['valid'] ) {
            return null;
        }
        $source_type = get_post_meta( $entry->ID, self::META_SOURCE_TYPE, true ) ?: 'library';
        $source_id = absint( get_post_meta( $entry->ID, self::META_SOURCE_ID, true ) );
        $source = 'library' === $source_type ? get_post( $source_id ) : null;
        $use_canonical = (bool) get_post_meta( $entry->ID, self::META_USE_CANONICAL, true );
        $custom_url = esc_url_raw( get_post_meta( $entry->ID, self::META_URL, true ) );
        $url = $custom_url;
        if ( $source && $use_canonical ) {
            $url = get_permalink( $source );
        }
        $headline = get_post_meta( $entry->ID, self::META_HEADLINE, true ) ?: get_the_title( $entry );
        $summary = get_post_meta( $entry->ID, self::META_SUMMARY, true );
        if ( ! $summary && $source ) {
            $summary = $this->source_summary( $source );
        }
        $thumbnail = '';
        if ( $source && get_post_meta( $entry->ID, self::META_SHOW_THUMBNAIL, true ) && has_post_thumbnail( $source ) ) {
            $thumbnail = get_the_post_thumbnail( $source, 'medium_large', array( 'loading' => 'lazy' ) );
        }
        $label = get_post_meta( $entry->ID, self::META_LABEL, true );
        if ( ! $label ) {
            $label = 'announcement' === $source_type ? __( 'Site Announcement', 'sustainable-catalyst-library' ) : __( 'From the Knowledge Library', 'sustainable-catalyst-library' );
        }
        $action_label = get_post_meta( $entry->ID, self::META_ACTION_LABEL, true );
        if ( ! $action_label && $url ) {
            $action_label = 'announcement' === $source_type ? __( 'Learn more', 'sustainable-catalyst-library' ) : __( 'Read article', 'sustainable-catalyst-library' );
        }

        return array(
            'id' => $entry->ID,
            'source_type' => $source_type,
            'label' => sanitize_text_field( $label ),
            'headline' => sanitize_text_field( $headline ),
            'summary' => sanitize_textarea_field( $summary ),
            'action_label' => sanitize_text_field( $action_label ),
            'url' => esc_url_raw( $url ),
            'thumbnail' => $thumbnail,
            'show_thumbnail' => (bool) get_post_meta( $entry->ID, self::META_SHOW_THUMBNAIL, true ),
            'show_metadata' => (bool) get_post_meta( $entry->ID, self::META_SHOW_METADATA, true ),
            'metadata' => $source ? $this->source_metadata( $source ) : '',
            'dismissible' => 'announcement' === $source_type && (bool) get_post_meta( $entry->ID, self::META_DISMISSIBLE, true ),
            'dismiss_key' => 'sc-homepage-spotlight-' . $entry->ID . '-' . sanitize_key( $entry->post_modified_gmt ),
        );
    }

    /** @return array{valid:bool,reason:string} */
    private function validate_item_source( WP_Post $entry ): array {
        $page_id = absint( get_post_meta( $entry->ID, self::META_PAGE_ID, true ) );
        $page = $page_id ? get_post( $page_id ) : null;
        if ( ! $page || self::PAGE_POST_TYPE !== $page->post_type || 'publish' !== $page->post_status || ! get_post_meta( $page_id, self::META_PAGE_ENABLED, true ) ) {
            return array( 'valid' => false, 'reason' => __( 'Category page is missing or disabled.', 'sustainable-catalyst-library' ) );
        }
        $source_type = get_post_meta( $entry->ID, self::META_SOURCE_TYPE, true ) ?: 'library';
        if ( 'announcement' === $source_type ) {
            return array( 'valid' => true, 'reason' => '' );
        }
        $source_id = absint( get_post_meta( $entry->ID, self::META_SOURCE_ID, true ) );
        $source = $source_id ? get_post( $source_id ) : null;
        if ( ! $source ) {
            return array( 'valid' => false, 'reason' => __( 'Linked Library record is missing.', 'sustainable-catalyst-library' ) );
        }
        if ( 'publish' !== $source->post_status ) {
            return array( 'valid' => false, 'reason' => __( 'Linked Library record is not published.', 'sustainable-catalyst-library' ) );
        }
        if ( post_password_required( $source ) ) {
            return array( 'valid' => false, 'reason' => __( 'Linked Library record is password protected.', 'sustainable-catalyst-library' ) );
        }
        $source_type_object = get_post_type_object( $source->post_type );
        $wordpress_public_type = $source_type_object && ( $source_type_object->public || $source_type_object->publicly_queryable );
        if ( $wordpress_public_type && function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $source ) ) {
            return array( 'valid' => false, 'reason' => __( 'Linked Library record is not publicly viewable.', 'sustainable-catalyst-library' ) );
        }
        return array( 'valid' => true, 'reason' => '' );
    }

    /** @return array{key:string,label:string,reason:string} */
    private function item_status( WP_Post $entry ): array {
        if ( 'publish' !== $entry->post_status ) {
            return array( 'key' => 'disabled', 'label' => __( 'Draft', 'sustainable-catalyst-library' ), 'reason' => '' );
        }
        if ( ! get_post_meta( $entry->ID, self::META_ENABLED, true ) ) {
            return array( 'key' => 'disabled', 'label' => __( 'Disabled', 'sustainable-catalyst-library' ), 'reason' => '' );
        }
        $validation = $this->validate_item_source( $entry );
        if ( ! $validation['valid'] ) {
            return array( 'key' => 'invalid', 'label' => __( 'Invalid', 'sustainable-catalyst-library' ), 'reason' => $validation['reason'] );
        }
        $now = current_time( 'timestamp', true );
        $start = absint( get_post_meta( $entry->ID, self::META_START_AT, true ) );
        $end = absint( get_post_meta( $entry->ID, self::META_END_AT, true ) );
        if ( $start && $start > $now ) {
            return array( 'key' => 'scheduled', 'label' => __( 'Scheduled', 'sustainable-catalyst-library' ), 'reason' => '' );
        }
        if ( $end && $end <= $now ) {
            return array( 'key' => 'expired', 'label' => __( 'Expired', 'sustainable-catalyst-library' ), 'reason' => '' );
        }
        return array( 'key' => 'active', 'label' => __( 'Active', 'sustainable-catalyst-library' ), 'reason' => '' );
    }

    /** @return WP_Post[] */
    private function all_pages(): array {
        return get_posts(
            array(
                'post_type' => self::PAGE_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'future' ),
                'posts_per_page' => -1,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            )
        );
    }

    /** @return WP_Post[] */
    private function all_items(): array {
        return get_posts(
            array(
                'post_type' => self::ITEM_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'future' ),
                'posts_per_page' => -1,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            )
        );
    }

    /** @return WP_Post[] */
    private function items_for_page( int $page_id ): array {
        return get_posts(
            array(
                'post_type' => self::ITEM_POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'pending', 'future' ),
                'posts_per_page' => -1,
                'meta_key' => self::META_PAGE_ID,
                'meta_value' => $page_id,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            )
        );
    }

    /** @return array<string,mixed> */
    private function page_form_values( ?WP_Post $page ): array {
        if ( $page ) {
            return array(
                'title' => $page->post_title,
                'description' => get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ),
                'enabled' => (int) get_post_meta( $page->ID, self::META_PAGE_ENABLED, true ),
                'item_limit' => $this->page_item_limit( $page->ID ),
                'menu_order' => (int) $page->menu_order,
            );
        }
        return array(
            'title' => '',
            'description' => '',
            'enabled' => 1,
            'item_limit' => 5,
            'menu_order' => $this->next_page_order(),
        );
    }

    /** @return array<string,mixed> */
    private function item_form_values( ?WP_Post $item, int $source_id = 0 ): array {
        if ( $item ) {
            return array(
                'page_id' => absint( get_post_meta( $item->ID, self::META_PAGE_ID, true ) ),
                'source_type' => get_post_meta( $item->ID, self::META_SOURCE_TYPE, true ) ?: 'library',
                'source_id' => absint( get_post_meta( $item->ID, self::META_SOURCE_ID, true ) ),
                'label' => get_post_meta( $item->ID, self::META_LABEL, true ),
                'headline' => get_post_meta( $item->ID, self::META_HEADLINE, true ) ?: $item->post_title,
                'summary' => get_post_meta( $item->ID, self::META_SUMMARY, true ),
                'action_label' => get_post_meta( $item->ID, self::META_ACTION_LABEL, true ),
                'url' => get_post_meta( $item->ID, self::META_URL, true ),
                'use_canonical' => (int) get_post_meta( $item->ID, self::META_USE_CANONICAL, true ),
                'show_thumbnail' => (int) get_post_meta( $item->ID, self::META_SHOW_THUMBNAIL, true ),
                'show_metadata' => (int) get_post_meta( $item->ID, self::META_SHOW_METADATA, true ),
                'dismissible' => (int) get_post_meta( $item->ID, self::META_DISMISSIBLE, true ),
                'enabled' => (int) get_post_meta( $item->ID, self::META_ENABLED, true ),
                'start_at' => $this->format_local_datetime( absint( get_post_meta( $item->ID, self::META_START_AT, true ) ) ),
                'end_at' => $this->format_local_datetime( absint( get_post_meta( $item->ID, self::META_END_AT, true ) ) ),
                'menu_order' => max( 1, min( 5, (int) $item->menu_order ) ),
            );
        }
        $source = $source_id ? get_post( $source_id ) : null;
        $pages = $this->all_pages();
        return array(
            'page_id' => ! empty( $pages ) ? $pages[0]->ID : 0,
            'source_type' => 'library',
            'source_id' => $source ? $source->ID : 0,
            'label' => __( 'From the Knowledge Library', 'sustainable-catalyst-library' ),
            'headline' => $source ? get_the_title( $source ) : '',
            'summary' => $source ? $this->source_summary( $source ) : '',
            'action_label' => __( 'Read article', 'sustainable-catalyst-library' ),
            'url' => '',
            'use_canonical' => 1,
            'show_thumbnail' => 0,
            'show_metadata' => 1,
            'dismissible' => 0,
            'enabled' => 0,
            'start_at' => '',
            'end_at' => '',
            'menu_order' => 1,
        );
    }

    private function page_item_limit( int $page_id ): int {
        return 4 === absint( get_post_meta( $page_id, self::META_PAGE_ITEM_LIMIT, true ) ) ? 4 : 5;
    }

    private function source_summary( WP_Post $post ): string {
        $summary = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        return wp_trim_words( $summary, 34, '…' );
    }

    private function source_metadata( WP_Post $post ): string {
        $type_object = get_post_type_object( $post->post_type );
        $parts = array();
        if ( $type_object ) {
            $parts[] = $type_object->labels->singular_name;
        }
        foreach ( array( 'sc_document_family', 'sc_document_type', 'category' ) as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
                continue;
            }
            $terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $parts[] = $terms[0];
                break;
            }
        }
        $parts[] = sprintf( __( 'Updated %s', 'sustainable-catalyst-library' ), get_the_modified_date( get_option( 'date_format' ), $post ) );
        return implode( ' · ', array_filter( array_map( 'sanitize_text_field', $parts ) ) );
    }

    private function next_page_order(): int {
        $pages = $this->all_pages();
        if ( empty( $pages ) ) {
            return 10;
        }
        $orders = array_map( static fn( WP_Post $page ): int => (int) $page->menu_order, $pages );
        return max( $orders ) + 10;
    }

    /** @return string[] */
    private function eligible_source_post_types(): array {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        foreach ( array( 'post', 'page', 'sc_foundation_doc', 'sc_pdf_document', 'sc_research_source', 'sc_knowledge_pathway', 'sc_document_repository' ) as $known_type ) {
            if ( post_type_exists( $known_type ) ) {
                $post_types[] = $known_type;
            }
        }
        $post_types = array_values( array_unique( array_filter( $post_types ) ) );
        return array_values( array_diff( $post_types, array( 'attachment', self::ITEM_POST_TYPE, self::PAGE_POST_TYPE ) ) );
    }

    private function schedule_summary( WP_Post $item ): string {
        $start = absint( get_post_meta( $item->ID, self::META_START_AT, true ) );
        $end = absint( get_post_meta( $item->ID, self::META_END_AT, true ) );
        if ( ! $start && ! $end ) {
            return '<small>' . esc_html__( 'No date limits', 'sustainable-catalyst-library' ) . '</small>';
        }
        $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        $lines = array();
        if ( $start ) {
            $lines[] = '<small>' . esc_html__( 'Starts:', 'sustainable-catalyst-library' ) . ' ' . esc_html( wp_date( $format, $start, wp_timezone() ) ) . '</small>';
        }
        if ( $end ) {
            $lines[] = '<small>' . esc_html__( 'Ends:', 'sustainable-catalyst-library' ) . ' ' . esc_html( wp_date( $format, $end, wp_timezone() ) ) . '</small>';
        }
        return implode( '<br>', $lines );
    }

    private function page_actions( WP_Post $page, bool $enabled ): string {
        $edit = add_query_arg( array( 'page' => 'sc-library-homepage-spotlight', 'edit_page' => $page->ID ), admin_url( 'admin.php' ) );
        $command = $enabled ? 'disable' : 'enable';
        $links = array(
            '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'sustainable-catalyst-library' ) . '</a>',
            '<a href="' . esc_url( $this->page_action_url( $page->ID, $command ) ) . '">' . esc_html( $enabled ? __( 'Disable', 'sustainable-catalyst-library' ) : __( 'Enable', 'sustainable-catalyst-library' ) ) . '</a>',
            '<a class="submitdelete" href="' . esc_url( $this->page_action_url( $page->ID, 'trash' ) ) . '" data-confirm="' . esc_attr__( 'Move this category page to Trash? Its cards will remain stored but cannot display until reassigned.', 'sustainable-catalyst-library' ) . '">' . esc_html__( 'Trash', 'sustainable-catalyst-library' ) . '</a>',
        );
        return implode( ' · ', $links );
    }

    /** @param array{key:string,label:string,reason:string} $status */
    private function item_actions( WP_Post $item, array $status ): string {
        $edit = add_query_arg( array( 'page' => 'sc-library-homepage-spotlight', 'edit_item' => $item->ID ), admin_url( 'admin.php' ) );
        $command = 'disabled' === $status['key'] ? 'enable' : 'disable';
        $links = array(
            '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'sustainable-catalyst-library' ) . '</a>',
            '<a href="' . esc_url( $this->item_action_url( $item->ID, $command ) ) . '">' . esc_html( 'enable' === $command ? __( 'Enable', 'sustainable-catalyst-library' ) : __( 'Disable', 'sustainable-catalyst-library' ) ) . '</a>',
            '<a href="' . esc_url( $this->item_action_url( $item->ID, 'duplicate' ) ) . '">' . esc_html__( 'Duplicate', 'sustainable-catalyst-library' ) . '</a>',
            '<a class="submitdelete" href="' . esc_url( $this->item_action_url( $item->ID, 'trash' ) ) . '" data-confirm="' . esc_attr__( 'Move this Spotlight card to Trash?', 'sustainable-catalyst-library' ) . '">' . esc_html__( 'Trash', 'sustainable-catalyst-library' ) . '</a>',
        );
        return implode( ' · ', $links );
    }

    private function page_action_url( int $page_id, string $command ): string {
        $url = add_query_arg(
            array(
                'action' => 'sc_library_spotlight_page_action',
                'spotlight_page_id' => $page_id,
                'command' => $command,
            ),
            admin_url( 'admin-post.php' )
        );
        return wp_nonce_url( $url, 'sc_library_spotlight_page_action_' . $page_id . '_' . $command );
    }

    private function item_action_url( int $item_id, string $command ): string {
        $url = add_query_arg(
            array(
                'action' => 'sc_library_spotlight_item_action',
                'spotlight_id' => $item_id,
                'command' => $command,
            ),
            admin_url( 'admin-post.php' )
        );
        return wp_nonce_url( $url, 'sc_library_spotlight_item_action_' . $item_id . '_' . $command );
    }

    private function parse_local_datetime( $value ): int {
        $value = sanitize_text_field( wp_unslash( (string) $value ) );
        if ( '' === $value ) {
            return 0;
        }
        $date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
        return $date ? $date->getTimestamp() : 0;
    }

    private function format_local_datetime( int $timestamp ): string {
        return $timestamp ? wp_date( 'Y-m-d\TH:i', $timestamp, wp_timezone() ) : '';
    }

    /** @return string[] */
    private function item_meta_keys(): array {
        return array(
            self::META_PAGE_ID,
            self::META_SOURCE_TYPE,
            self::META_SOURCE_ID,
            self::META_LABEL,
            self::META_HEADLINE,
            self::META_SUMMARY,
            self::META_ACTION_LABEL,
            self::META_URL,
            self::META_USE_CANONICAL,
            self::META_SHOW_THUMBNAIL,
            self::META_SHOW_METADATA,
            self::META_DISMISSIBLE,
            self::META_ENABLED,
            self::META_START_AT,
            self::META_END_AT,
        );
    }

    private function truthy( $value ): bool {
        return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
    }

    private function require_admin_action( string $action, string $nonce_field ): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to manage Homepage Spotlight.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( $action, $nonce_field );
    }

    public function invalidate_for_saved_post( int $post_id, WP_Post $post, bool $update ): void {
        unset( $post_id, $post, $update );
        if ( ! self::$saving ) {
            $this->invalidate_cache();
        }
    }

    public function invalidate_for_status_change( string $new_status, string $old_status, WP_Post $post ): void {
        unset( $new_status, $old_status, $post );
        $this->invalidate_cache();
    }

    public function invalidate_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    private function redirect_notice( string $notice, int $edit_item_id = 0, int $edit_page_id = 0 ): void {
        $args = array(
            'page' => 'sc-library-homepage-spotlight',
            'spotlight_notice' => sanitize_key( $notice ),
        );
        if ( $edit_item_id ) {
            $args['edit_item'] = $edit_item_id;
        }
        if ( $edit_page_id ) {
            $args['edit_page'] = $edit_page_id;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function render_admin_notice(): void {
        $notice = isset( $_GET['spotlight_notice'] ) ? sanitize_key( wp_unslash( $_GET['spotlight_notice'] ) ) : '';
        if ( ! $notice ) {
            return;
        }
        $messages = array(
            'category-saved' => __( 'The category page was saved.', 'sustainable-catalyst-library' ),
            'category-enabled' => __( 'The category page was enabled.', 'sustainable-catalyst-library' ),
            'category-disabled' => __( 'The category page was removed from public display.', 'sustainable-catalyst-library' ),
            'category-trashed' => __( 'The category page was moved to Trash. Its cards were preserved.', 'sustainable-catalyst-library' ),
            'starter-added' => __( 'The suggested starter pages were added. They remain fully editable.', 'sustainable-catalyst-library' ),
            'starter-exists' => __( 'The suggested starter pages already exist; no duplicates were created.', 'sustainable-catalyst-library' ),
            'card-saved' => __( 'The selected Spotlight card was saved.', 'sustainable-catalyst-library' ),
            'card-enabled' => __( 'The selected card was enabled.', 'sustainable-catalyst-library' ),
            'card-disabled' => __( 'The selected card was removed from public display.', 'sustainable-catalyst-library' ),
            'card-duplicated' => __( 'A disabled draft copy was created.', 'sustainable-catalyst-library' ),
            'card-trashed' => __( 'The Spotlight card was moved to Trash.', 'sustainable-catalyst-library' ),
            'order-saved' => __( 'The administrator-defined category and card order was saved.', 'sustainable-catalyst-library' ),
            'category-required' => __( 'Create or choose a valid category page.', 'sustainable-catalyst-library' ),
            'source-required' => __( 'Choose a valid published Knowledge Library record.', 'sustainable-catalyst-library' ),
            'headline-required' => __( 'Enter a homepage headline.', 'sustainable-catalyst-library' ),
            'save-failed' => __( 'WordPress could not save the Spotlight record.', 'sustainable-catalyst-library' ),
            'invalid' => __( 'The requested Spotlight action was invalid.', 'sustainable-catalyst-library' ),
        );
        $message = $messages[ $notice ] ?? $messages['invalid'];
        $errors = array( 'category-required', 'source-required', 'headline-required', 'save-failed', 'invalid' );
        $class = in_array( $notice, $errors, true ) ? 'notice notice-error' : 'notice notice-success is-dismissible';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
    }
}
