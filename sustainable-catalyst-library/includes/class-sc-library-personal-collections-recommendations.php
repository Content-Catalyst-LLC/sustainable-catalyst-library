<?php
/**
 * Personal Library Collections & Recommendations.
 *
 * v4.3.28 adds a private, account-owned personal library for books, films,
 * music, articles, archives, courses, datasets, tools, websites, podcasts and
 * other resources. Personal records are explicitly separate from Sustainable
 * Catalyst's official editorial recommendations and are never published by
 * this module.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Personal_Collections_Recommendations {
    public const VERSION = '4.3.28';
    public const SCHEMA = 'sc-library-personal-library/1.0';
    public const USER_META_ITEMS = 'sc_library_personal_items_v4328';
    public const USER_META_COLLECTIONS = 'sc_library_personal_collections_v4328';
    public const NONCE_ACTION = 'sc_library_personal_library_v4328';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/personal-library';
    public const MAX_ITEMS = 500;
    public const MAX_COLLECTIONS = 50;

    /** @var array<string,string> */
    private const TYPES = array(
        'book'    => 'Book',
        'film'    => 'Film',
        'music'   => 'Music',
        'article' => 'Article',
        'archive' => 'Archive',
        'course'  => 'Course',
        'dataset' => 'Dataset',
        'tool'    => 'Tool',
        'website' => 'Website',
        'podcast' => 'Podcast',
        'other'   => 'Other',
    );

    /** @var array<string,string> */
    private const RELATIONSHIPS = array(
        'saved'       => 'Saved',
        'recommended' => 'Personal recommendation',
        'reference'   => 'Reference',
    );

    /** @var array<string,string> */
    private const STATUSES = array(
        'saved'     => 'Saved for later',
        'active'    => 'In progress',
        'completed' => 'Completed',
        'archived'  => 'Archived',
    );

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_personal_library', array( $this, 'shortcode' ) );

        add_action( 'wp_ajax_sc_library_v4328_add_item', array( $this, 'ajax_add_item' ) );
        add_action( 'wp_ajax_sc_library_v4328_update_item', array( $this, 'ajax_update_item' ) );
        add_action( 'wp_ajax_sc_library_v4328_delete_item', array( $this, 'ajax_delete_item' ) );
        add_action( 'wp_ajax_sc_library_v4328_create_collection', array( $this, 'ajax_create_collection' ) );

        // Stable integration boundary for later Workspace / Research Access handoffs.
        add_action( 'sc_library_save_personal_item', array( $this, 'action_save_personal_item' ), 10, 2 );
        add_filter( 'sc_library_personal_library_items', array( $this, 'filter_personal_items' ), 10, 2 );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-personal-library-v4328',
            SC_LIBRARY_URL . 'assets/css/sc-library-personal-library-v4328.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-personal-library-v4328',
            SC_LIBRARY_URL . 'assets/js/sc-library-personal-library-v4328.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public static function types() {
        return self::TYPES;
    }

    public static function relationships() {
        return self::RELATIONSHIPS;
    }

    public static function statuses() {
        return self::STATUSES;
    }

    public static function editorial_separation_contract() {
        return array(
            'schema'                         => self::SCHEMA,
            'record_owner'                   => 'current-wordpress-user',
            'visibility'                     => 'private',
            'official_editorial_separate'    => true,
            'automatic_publication'          => false,
            'automatic_editorial_promotion'  => false,
            'workspace_account_continuity'   => true,
        );
    }

    public static function items_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return array();
        }
        $items = get_user_meta( $user_id, self::USER_META_ITEMS, true );
        $items = is_array( $items ) ? $items : array();
        $normalized = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $clean = self::sanitize_item( $item, false );
            if ( ! empty( $clean['id'] ) && ! empty( $clean['title'] ) ) {
                $normalized[] = $clean;
            }
        }
        return array_slice( $normalized, -self::MAX_ITEMS );
    }

    public static function collections_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return array();
        }
        $stored = get_user_meta( $user_id, self::USER_META_COLLECTIONS, true );
        $stored = is_array( $stored ) ? $stored : array();
        $collections = array();
        foreach ( $stored as $name ) {
            $name = self::sanitize_collection_name( $name );
            if ( '' !== $name ) {
                $collections[ strtolower( $name ) ] = $name;
            }
        }
        foreach ( self::items_for_user( $user_id ) as $item ) {
            $name = self::sanitize_collection_name( $item['collection'] ?? '' );
            if ( '' !== $name ) {
                $collections[ strtolower( $name ) ] = $name;
            }
        }
        natcasesort( $collections );
        return array_slice( array_values( $collections ), 0, self::MAX_COLLECTIONS );
    }

    private static function sanitize_collection_name( $value ) {
        $value = sanitize_text_field( (string) $value );
        $value = trim( preg_replace( '/\s+/', ' ', $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80 ) : substr( $value, 0, 80 );
    }

    private static function enum_value( $value, $allowed, $fallback ) {
        $value = sanitize_key( (string) $value );
        return array_key_exists( $value, $allowed ) ? $value : $fallback;
    }

    private static function sanitize_item( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = sanitize_text_field( (string) ( $input['id'] ?? '' ) );
        if ( $generate_id && '' === $id ) {
            $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'scpl-', true );
        }
        $title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
        $creator = sanitize_text_field( (string) ( $input['creator'] ?? '' ) );
        $year = sanitize_text_field( (string) ( $input['year'] ?? '' ) );
        $year = preg_replace( '/[^0-9?\-]/', '', $year );
        $url = esc_url_raw( (string) ( $input['url'] ?? '' ) );
        $identifier = sanitize_text_field( (string) ( $input['identifier'] ?? '' ) );
        $notes = sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) );
        $why = sanitize_textarea_field( (string) ( $input['why'] ?? '' ) );
        $collection = self::sanitize_collection_name( $input['collection'] ?? '' );
        $created = sanitize_text_field( (string) ( $input['created_at'] ?? '' ) );
        $updated = sanitize_text_field( (string) ( $input['updated_at'] ?? '' ) );

        return array(
            'id'           => $id,
            'title'        => $title,
            'type'         => self::enum_value( $input['type'] ?? '', self::TYPES, 'other' ),
            'relationship' => self::enum_value( $input['relationship'] ?? '', self::RELATIONSHIPS, 'saved' ),
            'status'       => self::enum_value( $input['status'] ?? '', self::STATUSES, 'saved' ),
            'creator'      => $creator,
            'year'         => $year,
            'url'          => $url,
            'identifier'   => $identifier,
            'collection'   => $collection,
            'notes'        => $notes,
            'why'          => $why,
            'origin'       => 'personal',
            'visibility'   => 'private',
            'created_at'   => $created,
            'updated_at'   => $updated,
        );
    }

    private static function persist_items( $user_id, $items ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }
        $items = is_array( $items ) ? array_values( $items ) : array();
        $items = array_slice( $items, -self::MAX_ITEMS );
        return false !== update_user_meta( $user_id, self::USER_META_ITEMS, $items );
    }

    public static function add_item_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return new WP_Error( 'sc_library_personal_library_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) );
        }
        $item = self::sanitize_item( $input, true );
        if ( '' === trim( $item['title'] ) ) {
            return new WP_Error( 'sc_library_personal_library_title_required', __( 'A title is required.', 'sustainable-catalyst-library' ) );
        }
        $now = current_time( 'mysql', true );
        $item['created_at'] = $now;
        $item['updated_at'] = $now;
        $items = self::items_for_user( $user_id );
        $items[] = $item;
        self::persist_items( $user_id, $items );
        if ( '' !== $item['collection'] ) {
            self::add_collection_for_user( $user_id, $item['collection'] );
        }
        do_action( 'sc_library_personal_item_saved', $item, $user_id );
        return $item;
    }

    public static function update_item_for_user( $user_id, $item_id, $input ) {
        $user_id = absint( $user_id );
        $item_id = sanitize_text_field( (string) $item_id );
        if ( ! $user_id || '' === $item_id ) {
            return new WP_Error( 'sc_library_personal_library_bad_request', __( 'The personal Library item could not be identified.', 'sustainable-catalyst-library' ) );
        }
        $items = self::items_for_user( $user_id );
        $found = false;
        foreach ( $items as $index => $existing ) {
            if ( (string) ( $existing['id'] ?? '' ) !== $item_id ) {
                continue;
            }
            $merged = array_merge( $existing, is_array( $input ) ? $input : array() );
            $merged['id'] = $item_id;
            $merged['created_at'] = (string) ( $existing['created_at'] ?? '' );
            $clean = self::sanitize_item( $merged, false );
            if ( '' === trim( $clean['title'] ) ) {
                return new WP_Error( 'sc_library_personal_library_title_required', __( 'A title is required.', 'sustainable-catalyst-library' ) );
            }
            $clean['updated_at'] = current_time( 'mysql', true );
            $items[ $index ] = $clean;
            $found = true;
            if ( '' !== $clean['collection'] ) {
                self::add_collection_for_user( $user_id, $clean['collection'] );
            }
            break;
        }
        if ( ! $found ) {
            return new WP_Error( 'sc_library_personal_library_not_found', __( 'That personal Library item was not found.', 'sustainable-catalyst-library' ) );
        }
        self::persist_items( $user_id, $items );
        return $items[ $index ];
    }

    public static function delete_item_for_user( $user_id, $item_id ) {
        $user_id = absint( $user_id );
        $item_id = sanitize_text_field( (string) $item_id );
        $items = self::items_for_user( $user_id );
        $next = array();
        $deleted = false;
        foreach ( $items as $item ) {
            if ( (string) ( $item['id'] ?? '' ) === $item_id ) {
                $deleted = true;
                continue;
            }
            $next[] = $item;
        }
        if ( $deleted ) {
            self::persist_items( $user_id, $next );
        }
        return $deleted;
    }

    public static function add_collection_for_user( $user_id, $name ) {
        $user_id = absint( $user_id );
        $name = self::sanitize_collection_name( $name );
        if ( ! $user_id || '' === $name ) {
            return false;
        }
        $collections = self::collections_for_user( $user_id );
        $exists = false;
        foreach ( $collections as $collection ) {
            if ( 0 === strcasecmp( $collection, $name ) ) {
                $exists = true;
                break;
            }
        }
        if ( ! $exists ) {
            $collections[] = $name;
        }
        $collections = array_slice( array_values( array_unique( $collections ) ), 0, self::MAX_COLLECTIONS );
        return false !== update_user_meta( $user_id, self::USER_META_COLLECTIONS, $collections );
    }

    public function filter_personal_items( $items, $user_id ) {
        $user_id = absint( $user_id );
        return $user_id ? self::items_for_user( $user_id ) : ( is_array( $items ) ? $items : array() );
    }

    public function action_save_personal_item( $item, $user_id = 0 ) {
        $user_id = absint( $user_id ) ?: get_current_user_id();
        if ( $user_id && is_array( $item ) ) {
            self::add_item_for_user( $user_id, $item );
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => array( $this, 'rest_can_read' ),
                'callback'            => array( $this, 'rest_personal_library' ),
            )
        );
    }

    public function rest_can_read() {
        return is_user_logged_in();
    }

    public function rest_personal_library() {
        $user_id = get_current_user_id();
        return rest_ensure_response(
            array(
                'schema'       => self::SCHEMA,
                'version'      => self::VERSION,
                'visibility'   => 'private',
                'user_id'      => $user_id,
                'count'        => count( self::items_for_user( $user_id ) ),
                'collections'  => self::collections_for_user( $user_id ),
                'items'        => self::items_for_user( $user_id ),
                'separation'   => self::editorial_separation_contract(),
            )
        );
    }

    private function require_ajax_user() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sign in to manage your personal Library.', 'sustainable-catalyst-library' ) ), 401 );
        }
        return get_current_user_id();
    }

    public function ajax_add_item() {
        $user_id = $this->require_ajax_user();
        $item = self::add_item_for_user( $user_id, $this->request_item_payload() );
        if ( is_wp_error( $item ) ) {
            wp_send_json_error( array( 'message' => $item->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'schema' => self::SCHEMA, 'item' => $item, 'message' => __( 'Saved to your private Library.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_update_item() {
        $user_id = $this->require_ajax_user();
        $item_id = sanitize_text_field( wp_unslash( $_POST['item_id'] ?? '' ) );
        $item = self::update_item_for_user( $user_id, $item_id, $this->request_item_payload() );
        if ( is_wp_error( $item ) ) {
            wp_send_json_error( array( 'message' => $item->get_error_message() ), 404 );
        }
        wp_send_json_success( array( 'schema' => self::SCHEMA, 'item' => $item, 'message' => __( 'Personal Library item updated.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_delete_item() {
        $user_id = $this->require_ajax_user();
        $item_id = sanitize_text_field( wp_unslash( $_POST['item_id'] ?? '' ) );
        if ( ! self::delete_item_for_user( $user_id, $item_id ) ) {
            wp_send_json_error( array( 'message' => __( 'That personal Library item was not found.', 'sustainable-catalyst-library' ) ), 404 );
        }
        wp_send_json_success( array( 'schema' => self::SCHEMA, 'item_id' => $item_id, 'message' => __( 'Removed from your private Library.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_create_collection() {
        $user_id = $this->require_ajax_user();
        $name = self::sanitize_collection_name( wp_unslash( $_POST['name'] ?? '' ) );
        if ( '' === $name ) {
            wp_send_json_error( array( 'message' => __( 'Enter a collection name.', 'sustainable-catalyst-library' ) ), 400 );
        }
        self::add_collection_for_user( $user_id, $name );
        wp_send_json_success( array( 'schema' => self::SCHEMA, 'collection' => $name, 'message' => __( 'Collection created.', 'sustainable-catalyst-library' ) ) );
    }

    private function request_item_payload() {
        $keys = array( 'title', 'type', 'relationship', 'status', 'creator', 'year', 'url', 'identifier', 'collection', 'notes', 'why' );
        $payload = array();
        foreach ( $keys as $key ) {
            $payload[ $key ] = wp_unslash( $_POST[ $key ] ?? '' );
        }
        return $payload;
    }

    private function current_return_url() {
        $request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if ( '' !== $request_uri && 0 === strpos( $request_uri, '/' ) ) {
            return home_url( $request_uri );
        }
        return home_url( '/knowledge-libraries/' );
    }

    private function render_options( $options, $selected = '' ) {
        $html = '';
        foreach ( $options as $value => $label ) {
            $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        return $html;
    }

    private function render_collection_options( $collections, $selected = '', $include_blank = true ) {
        $html = $include_blank ? '<option value="">' . esc_html__( 'No collection', 'sustainable-catalyst-library' ) . '</option>' : '';
        foreach ( $collections as $collection ) {
            $html .= '<option value="' . esc_attr( $collection ) . '" ' . selected( $selected, $collection, false ) . '>' . esc_html( $collection ) . '</option>';
        }
        return $html;
    }

    private function render_items( $items, $collections ) {
        if ( ! $items ) {
            return '<div class="sc-personal-library__empty" data-sc-personal-empty><strong>' . esc_html__( 'Your personal Library is empty.', 'sustainable-catalyst-library' ) . '</strong><span>' . esc_html__( 'Save a book, film, article, course, dataset, tool, archive, piece of music, or another resource you want to keep.', 'sustainable-catalyst-library' ) . '</span></div>';
        }
        $html = '<div class="sc-personal-library__grid" data-sc-personal-list>';
        foreach ( array_reverse( $items ) as $item ) {
            $search = strtolower( implode( ' ', array_filter( array( $item['title'], $item['creator'], $item['year'], $item['identifier'], $item['collection'], $item['notes'], $item['why'] ) ) ) );
            $html .= '<article class="sc-personal-library__item" data-sc-personal-item data-type="' . esc_attr( $item['type'] ) . '" data-relationship="' . esc_attr( $item['relationship'] ) . '" data-collection="' . esc_attr( $item['collection'] ) . '" data-search="' . esc_attr( $search ) . '">';
            $html .= '<div class="sc-personal-library__item-head"><div><small>' . esc_html( self::TYPES[ $item['type'] ] ?? self::TYPES['other'] ) . '</small><h4>' . esc_html( $item['title'] ) . '</h4></div><span>' . esc_html( self::RELATIONSHIPS[ $item['relationship'] ] ?? self::RELATIONSHIPS['saved'] ) . '</span></div>';
            if ( $item['creator'] || $item['year'] ) {
                $html .= '<p class="sc-personal-library__byline">' . esc_html( trim( $item['creator'] . ( $item['creator'] && $item['year'] ? ' · ' : '' ) . $item['year'] ) ) . '</p>';
            }
            if ( $item['why'] ) {
                $html .= '<p class="sc-personal-library__why"><strong>' . esc_html__( 'Why I kept this', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html( $item['why'] ) . '</p>';
            }
            if ( $item['notes'] ) {
                $html .= '<p class="sc-personal-library__notes">' . esc_html( $item['notes'] ) . '</p>';
            }
            $html .= '<div class="sc-personal-library__badges"><span>' . esc_html( self::STATUSES[ $item['status'] ] ?? self::STATUSES['saved'] ) . '</span>';
            if ( $item['collection'] ) {
                $html .= '<span>' . esc_html( $item['collection'] ) . '</span>';
            }
            if ( $item['identifier'] ) {
                $html .= '<span>' . esc_html( $item['identifier'] ) . '</span>';
            }
            $html .= '</div>';
            if ( $item['url'] ) {
                $html .= '<p><a class="sc-personal-library__open" href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open resource →', 'sustainable-catalyst-library' ) . '</a></p>';
            }
            $html .= '<details class="sc-personal-library__edit"><summary>' . esc_html__( 'Manage', 'sustainable-catalyst-library' ) . '</summary>';
            $html .= '<form data-sc-personal-update-form><input type="hidden" name="item_id" value="' . esc_attr( $item['id'] ) . '">';
            $html .= '<label><span>' . esc_html__( 'Relationship', 'sustainable-catalyst-library' ) . '</span><select name="relationship">' . $this->render_options( self::RELATIONSHIPS, $item['relationship'] ) . '</select></label>';
            $html .= '<label><span>' . esc_html__( 'Status', 'sustainable-catalyst-library' ) . '</span><select name="status">' . $this->render_options( self::STATUSES, $item['status'] ) . '</select></label>';
            $html .= '<label><span>' . esc_html__( 'Collection', 'sustainable-catalyst-library' ) . '</span><select name="collection">' . $this->render_collection_options( $collections, $item['collection'] ) . '</select></label>';
            $html .= '<input type="hidden" name="title" value="' . esc_attr( $item['title'] ) . '"><input type="hidden" name="type" value="' . esc_attr( $item['type'] ) . '"><input type="hidden" name="creator" value="' . esc_attr( $item['creator'] ) . '"><input type="hidden" name="year" value="' . esc_attr( $item['year'] ) . '"><input type="hidden" name="url" value="' . esc_attr( $item['url'] ) . '"><input type="hidden" name="identifier" value="' . esc_attr( $item['identifier'] ) . '"><input type="hidden" name="notes" value="' . esc_attr( $item['notes'] ) . '"><input type="hidden" name="why" value="' . esc_attr( $item['why'] ) . '">';
            $html .= '<div class="sc-personal-library__manage-actions"><button type="submit">' . esc_html__( 'Save changes', 'sustainable-catalyst-library' ) . '</button><button type="button" class="is-danger" data-sc-personal-delete="' . esc_attr( $item['id'] ) . '">' . esc_html__( 'Remove', 'sustainable-catalyst-library' ) . '</button><span aria-live="polite"></span></div></form></details>';
            $html .= '</article>';
        }
        $html .= '</div>';
        return $html;
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'title' => 'My Library',
            ),
            $atts,
            'sc_personal_library'
        );
        wp_enqueue_style( 'sc-library-personal-library-v4328' );
        wp_enqueue_script( 'sc-library-personal-library-v4328' );

        $signed_in = is_user_logged_in();
        $user_id = $signed_in ? get_current_user_id() : 0;
        $items = $user_id ? self::items_for_user( $user_id ) : array();
        $collections = $user_id ? self::collections_for_user( $user_id ) : array();

        wp_localize_script(
            'sc-library-personal-library-v4328',
            'SCLibraryPersonalLibrary',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
                'signedIn' => $signed_in,
                'schema'   => self::SCHEMA,
            )
        );

        ob_start();
        ?>
        <section class="sc-personal-library" data-sc-personal-library="v4.3.28">
            <header class="sc-personal-library__header">
                <div>
                    <p class="sc-personal-library__kicker"><?php esc_html_e( 'Private personal collection', 'sustainable-catalyst-library' ); ?></p>
                    <h3><?php echo esc_html( $atts['title'] ); ?></h3>
                    <p><?php esc_html_e( 'Keep resources that matter to you across formats and subjects. These records belong to your Sustainable Catalyst account and stay separate from Sustainable Catalyst\'s official editorial recommendations.', 'sustainable-catalyst-library' ); ?></p>
                </div>
                <div class="sc-personal-library__privacy"><strong><?php esc_html_e( 'Private by default', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Nothing saved here is automatically published, endorsed, or promoted by Sustainable Catalyst.', 'sustainable-catalyst-library' ); ?></span></div>
            </header>
            <?php if ( ! $signed_in ) : ?>
                <div class="sc-personal-library__signin">
                    <strong><?php esc_html_e( 'Public Library discovery remains open.', 'sustainable-catalyst-library' ); ?></strong>
                    <span><?php esc_html_e( 'Sign in with the same Sustainable Catalyst / Workspace account to build a private personal Library. No second Library account is required.', 'sustainable-catalyst-library' ); ?></span>
                    <a href="<?php echo esc_url( wp_login_url( $this->current_return_url() ) ); ?>"><?php esc_html_e( 'Sign in to My Library →', 'sustainable-catalyst-library' ); ?></a>
                </div>
            <?php else : ?>
                <div class="sc-personal-library__summary">
                    <div><strong data-sc-personal-count><?php echo esc_html( number_format_i18n( count( $items ) ) ); ?></strong><span><?php esc_html_e( 'private items', 'sustainable-catalyst-library' ); ?></span></div>
                    <div><strong><?php echo esc_html( number_format_i18n( count( $collections ) ) ); ?></strong><span><?php esc_html_e( 'collections', 'sustainable-catalyst-library' ); ?></span></div>
                    <div><strong><?php echo esc_html( number_format_i18n( count( array_filter( $items, static function( $item ) { return 'recommended' === ( $item['relationship'] ?? '' ); } ) ) ) ); ?></strong><span><?php esc_html_e( 'personal recommendations', 'sustainable-catalyst-library' ); ?></span></div>
                </div>

                <div class="sc-personal-library__toolbar">
                    <label><span><?php esc_html_e( 'Find', 'sustainable-catalyst-library' ); ?></span><input type="search" data-sc-personal-search placeholder="<?php esc_attr_e( 'Title, creator, collection…', 'sustainable-catalyst-library' ); ?>"></label>
                    <label><span><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></span><select data-sc-personal-type-filter><option value=""><?php esc_html_e( 'All types', 'sustainable-catalyst-library' ); ?></option><?php echo $this->render_options( self::TYPES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                    <label><span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span><select data-sc-personal-relationship-filter><option value=""><?php esc_html_e( 'All relationships', 'sustainable-catalyst-library' ); ?></option><?php echo $this->render_options( self::RELATIONSHIPS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                    <label><span><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></span><select data-sc-personal-collection-filter><option value=""><?php esc_html_e( 'All collections', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $collections as $collection ) : ?><option value="<?php echo esc_attr( $collection ); ?>"><?php echo esc_html( $collection ); ?></option><?php endforeach; ?></select></label>
                </div>

                <div class="sc-personal-library__actions">
                    <details class="sc-personal-library__add" open>
                        <summary><?php esc_html_e( 'Add to My Library', 'sustainable-catalyst-library' ); ?></summary>
                        <form data-sc-personal-add-form>
                            <div class="sc-personal-library__form-grid">
                                <label class="is-wide"><span><?php esc_html_e( 'Title', 'sustainable-catalyst-library' ); ?></span><input name="title" required maxlength="500"></label>
                                <label><span><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></span><select name="type"><?php echo $this->render_options( self::TYPES, 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                                <label><span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span><select name="relationship"><?php echo $this->render_options( self::RELATIONSHIPS, 'saved' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                                <label><span><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></span><select name="status"><?php echo $this->render_options( self::STATUSES, 'saved' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                                <label><span><?php esc_html_e( 'Creator / author / organization', 'sustainable-catalyst-library' ); ?></span><input name="creator"></label>
                                <label><span><?php esc_html_e( 'Year', 'sustainable-catalyst-library' ); ?></span><input name="year" inputmode="numeric" maxlength="12"></label>
                                <label><span><?php esc_html_e( 'Identifier', 'sustainable-catalyst-library' ); ?></span><input name="identifier" placeholder="ISBN, DOI, catalog ID…"></label>
                                <label class="is-wide"><span>URL</span><input type="url" name="url"></label>
                                <label><span><?php esc_html_e( 'Collection', 'sustainable-catalyst-library' ); ?></span><input name="collection" list="sc-personal-library-collections-v4328" maxlength="80"></label>
                                <label class="is-wide"><span><?php esc_html_e( 'Why I kept this', 'sustainable-catalyst-library' ); ?></span><textarea name="why" rows="3" placeholder="<?php esc_attr_e( 'Your own recommendation or reason for saving it.', 'sustainable-catalyst-library' ); ?>"></textarea></label>
                                <label class="is-wide"><span><?php esc_html_e( 'Private notes', 'sustainable-catalyst-library' ); ?></span><textarea name="notes" rows="3"></textarea></label>
                            </div>
                            <datalist id="sc-personal-library-collections-v4328"><?php foreach ( $collections as $collection ) : ?><option value="<?php echo esc_attr( $collection ); ?>"></option><?php endforeach; ?></datalist>
                            <button type="submit" class="sc-personal-library__primary"><?php esc_html_e( 'Save privately', 'sustainable-catalyst-library' ); ?></button>
                            <span data-sc-personal-add-status aria-live="polite"></span>
                        </form>
                    </details>
                    <details class="sc-personal-library__collection-create">
                        <summary><?php esc_html_e( 'Create collection', 'sustainable-catalyst-library' ); ?></summary>
                        <form data-sc-personal-collection-form><label><span><?php esc_html_e( 'Collection name', 'sustainable-catalyst-library' ); ?></span><input name="name" maxlength="80" required></label><button type="submit"><?php esc_html_e( 'Create', 'sustainable-catalyst-library' ); ?></button><span aria-live="polite"></span></form>
                    </details>
                </div>

                <p class="sc-personal-library__status" data-sc-personal-status aria-live="polite"></p>
                <?php echo $this->render_items( $items, $collections ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}
