<?php
/**
 * Scholarly and Library Database Connectors.
 *
 * Federated discovery, provenance-aware source imports, open-access lookup,
 * library profiles, Google Scholar and WorldCat handoffs, caching, backoff,
 * and permission-controlled discovery APIs.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Scholarly_Library_Connectors {
    public const VERSION = '2.6.1';
    public const API_NAMESPACE = 'sc-library/v1';
    public const RESULT_SCHEMA = 'sc-library-discovery-result/1.0';
    public const SEARCH_SCHEMA = 'sc-library-federated-search/1.0';
    public const LOCATOR_SCHEMA = 'sc-library-source-locator/1.0';
    public const PROFILE_SCHEMA = 'sc-library-library-profile/1.0';

    public const PROFILE_POST_TYPE = 'sc_library_profile';

    public const META_CONNECTOR_IDS = '_sc_source_connector_ids';
    public const META_DISCOVERY_HISTORY = '_sc_source_discovery_history';
    public const META_ACCESS_LOCATIONS = '_sc_source_access_locations';
    public const META_CONNECTOR_LAST_CHECKED = '_sc_source_connector_last_checked';
    public const META_IMPORT_FINGERPRINT = '_sc_source_import_fingerprint';

    public const META_PROFILE_CATALOG_TEMPLATE = '_sc_library_profile_catalog_template';
    public const META_PROFILE_OPENURL_BASE = '_sc_library_profile_openurl_base';
    public const META_PROFILE_ILL_URL = '_sc_library_profile_ill_url';
    public const META_PROFILE_PROXY_PREFIX = '_sc_library_profile_proxy_prefix';
    public const META_PROFILE_HOMEPAGE = '_sc_library_profile_homepage';
    public const META_PROFILE_REGION = '_sc_library_profile_region';
    public const META_PROFILE_ENABLED = '_sc_library_profile_enabled';
    public const META_PROFILE_NOTES = '_sc_library_profile_notes';

    public const OPTION_SETTINGS = 'sc_library_connector_settings_v260';
    public const OPTION_ROUTES_VERSION = 'sc_library_connector_routes_version';
    public const CACHE_PREFIX = 'sc_lib_v260_';
    public const HISTORY_LIMIT = 50;
    public const LOCATION_LIMIT = 30;
    public const DEFAULT_CACHE_TTL = 12 * HOUR_IN_SECONDS;
    public const DEFAULT_TIMEOUT = 15;
    public const DEFAULT_RESULTS = 8;

    private static $provider_labels = array(
        'crossref'            => 'Crossref',
        'openalex'            => 'OpenAlex',
        'datacite'            => 'DataCite',
        'pubmed'              => 'PubMed',
        'pmc'                 => 'PubMed Central',
        'loc'                 => 'Library of Congress',
        'openlibrary'         => 'Open Library',
        'googlebooks'         => 'Google Books',
        'unpaywall'           => 'Unpaywall',
        'google_scholar'      => 'Google Scholar',
        'worldcat'            => 'WorldCat',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'register_profile_type' ), 235 );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 999 );

        add_action( 'admin_menu', array( $this, 'register_admin_page' ), 250 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 65 );
        add_action( 'save_post_' . self::PROFILE_POST_TYPE, array( $this, 'save_library_profile' ), 40, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 205 );

        add_action( 'wp_ajax_sc_library_v260_search_provider', array( $this, 'ajax_search_provider' ) );
        add_action( 'wp_ajax_nopriv_sc_library_v260_search_provider', array( $this, 'ajax_search_provider' ) );
        add_action( 'wp_ajax_sc_library_v260_import_result', array( $this, 'ajax_import_result' ) );
        add_action( 'wp_ajax_sc_library_v260_locate_source', array( $this, 'ajax_locate_source' ) );
        add_action( 'wp_ajax_sc_library_v260_test_provider', array( $this, 'ajax_test_provider' ) );
        add_action( 'wp_ajax_sc_library_v260_save_settings', array( $this, 'ajax_save_settings' ) );

        add_shortcode( 'sc_source_discovery', array( $this, 'shortcode_source_discovery' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_profile_type() {
        register_post_type(
            self::PROFILE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Library Profiles', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Library Profile', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Library Profiles', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Library Profile', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Library Profile', 'sustainable-catalyst-library' ),
                    'all_items'     => __( 'All Library Profiles', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'menu_icon'           => 'dashicons-building',
            )
        );
    }

    public function maybe_flush_rewrites() {
        if ( get_option( self::OPTION_ROUTES_VERSION ) === self::VERSION ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( self::OPTION_ROUTES_VERSION, self::VERSION, false );
    }

    public function register_admin_page() {
        add_submenu_page(
            'sc-library',
            __( 'Source Discovery', 'sustainable-catalyst-library' ),
            __( 'Source Discovery', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-source-discovery',
            array( $this, 'render_admin_page' )
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-library-profile-settings',
            __( 'Library Search and Access Settings', 'sustainable-catalyst-library' ),
            array( $this, 'render_library_profile_box' ),
            self::PROFILE_POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sc-source-locator',
            __( 'Locate Source Material', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_locator_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_library_profile_box( $post ) {
        wp_nonce_field( 'sc_library_save_profile_' . $post->ID, 'sc_library_profile_nonce' );
        ?>
        <div class="sc-connector-profile-fields">
            <p><?php esc_html_e( 'Library passwords are never stored. Searches and authentication occur on the library website.', 'sustainable-catalyst-library' ); ?></p>
            <?php
            $this->profile_url_field(
                'sc_library_profile_homepage',
                __( 'Library homepage', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_HOMEPAGE, true ),
                'https://library.example.edu/'
            );
            $this->profile_text_field(
                'sc_library_profile_region',
                __( 'Region or service area', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_REGION, true ),
                'Chicago, Illinois'
            );
            $this->profile_text_field(
                'sc_library_profile_catalog_template',
                __( 'Catalog search URL template', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_CATALOG_TEMPLATE, true ),
                'https://catalog.example.edu/search?q={query}'
            );
            ?>
            <p class="description"><?php esc_html_e( 'Supported tokens: {query}, {title}, {author}, {doi}, {isbn}, {pmid}.', 'sustainable-catalyst-library' ); ?></p>
            <?php
            $this->profile_url_field(
                'sc_library_profile_openurl_base',
                __( 'OpenURL resolver base', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_OPENURL_BASE, true ),
                'https://resolver.example.edu/openurl'
            );
            $this->profile_url_field(
                'sc_library_profile_ill_url',
                __( 'Interlibrary-loan request page', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_ILL_URL, true ),
                'https://library.example.edu/ill'
            );
            $this->profile_text_field(
                'sc_library_profile_proxy_prefix',
                __( 'Optional proxy prefix', 'sustainable-catalyst-library' ),
                get_post_meta( $post->ID, self::META_PROFILE_PROXY_PREFIX, true ),
                'https://proxy.example.edu/login?url='
            );
            ?>
            <label><input type="checkbox" name="sc_library_profile_enabled" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PROFILE_ENABLED, true ) ); ?>> <?php esc_html_e( 'Enable this library profile in source-location tools', 'sustainable-catalyst-library' ); ?></label>
            <label for="sc-library-profile-notes"><strong><?php esc_html_e( 'Private notes', 'sustainable-catalyst-library' ); ?></strong></label>
            <textarea id="sc-library-profile-notes" name="sc_library_profile_notes" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PROFILE_NOTES, true ) ); ?></textarea>
        </div>
        <?php
    }

    private function profile_text_field( $name, $label, $value, $placeholder = '' ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<input id="' . esc_attr( $name ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
    }

    private function profile_url_field( $name, $label, $value, $placeholder = '' ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<input id="' . esc_attr( $name ) . '" type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
    }

    public function save_library_profile( $post_id, $post, $update ) {
        if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_profile_nonce'] ) ), 'sc_library_save_profile_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $url_fields = array(
            'sc_library_profile_homepage'     => self::META_PROFILE_HOMEPAGE,
            'sc_library_profile_openurl_base' => self::META_PROFILE_OPENURL_BASE,
            'sc_library_profile_ill_url'      => self::META_PROFILE_ILL_URL,
        );
        foreach ( $url_fields as $request_key => $meta_key ) {
            $value = esc_url_raw( wp_unslash( $_POST[ $request_key ] ?? '' ) );
            $value ? update_post_meta( $post_id, $meta_key, $value ) : delete_post_meta( $post_id, $meta_key );
        }

        $text_fields = array(
            'sc_library_profile_region'           => self::META_PROFILE_REGION,
            'sc_library_profile_proxy_prefix'     => self::META_PROFILE_PROXY_PREFIX,
            'sc_library_profile_catalog_template' => self::META_PROFILE_CATALOG_TEMPLATE,
        );
        foreach ( $text_fields as $request_key => $meta_key ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $request_key ] ?? '' ) );
            $value ? update_post_meta( $post_id, $meta_key, $value ) : delete_post_meta( $post_id, $meta_key );
        }

        $notes = sanitize_textarea_field( wp_unslash( $_POST['sc_library_profile_notes'] ?? '' ) );
        $notes ? update_post_meta( $post_id, self::META_PROFILE_NOTES, $notes ) : delete_post_meta( $post_id, self::META_PROFILE_NOTES );
        update_post_meta( $post_id, self::META_PROFILE_ENABLED, isset( $_POST['sc_library_profile_enabled'] ) ? '1' : '0' );
        SC_Library_Connector_Holdings_Reliability::validate_library_profile( $post_id, true );
    }

    public function render_source_locator_box( $post ) {
        $data = SC_Library_Citation_Source_Manager::get_source_data( $post->ID, true );
        if ( ! $data ) {
            echo '<p>' . esc_html__( 'Save the Source record before locating material.', 'sustainable-catalyst-library' ) . '</p>';
            return;
        }
        $links = $this->discovery_handoffs( $data );
        ?>
        <div class="sc-source-locator-box" data-sc-source-locator data-source-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><?php esc_html_e( 'Check open access, library catalogs, book services, and scholarly search tools using this Source record.', 'sustainable-catalyst-library' ); ?></p>
            <button type="button" class="button button-primary" data-sc-locate-source><?php esc_html_e( 'Locate Source Material', 'sustainable-catalyst-library' ); ?></button>
            <div class="sc-source-locator-status" data-sc-locator-status aria-live="polite"></div>
            <div class="sc-source-locator-results" data-sc-locator-results></div>
            <p class="sc-source-locator-handoffs">
                <?php foreach ( $links as $link ) : ?>
                    <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['label'] ); ?></a>
                <?php endforeach; ?>
            </p>
        </div>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'discover' ) );
        if ( ! in_array( $tab, array( 'discover', 'providers', 'libraries', 'history' ), true ) ) {
            $tab = 'discover';
        }

        $providers = $this->providers();
        $settings = $this->settings();
        ?>
        <div class="wrap sc-connector-admin">
            <p class="sc-connector-kicker"><?php esc_html_e( 'Knowledge Library v2.6.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Scholarly and Library Database Connectors', 'sustainable-catalyst-library' ); ?></h1>
            <p class="sc-connector-lede"><?php esc_html_e( 'Search scholarly metadata, books, digital collections, biomedical databases, and open-access records; compare normalized results; import reviewed metadata; and route researchers to libraries and discovery services.', 'sustainable-catalyst-library' ); ?></p>

            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Source Discovery sections', 'sustainable-catalyst-library' ); ?>">
                <?php
                foreach ( array(
                    'discover'  => __( 'Discover Sources', 'sustainable-catalyst-library' ),
                    'providers' => __( 'Providers', 'sustainable-catalyst-library' ),
                    'libraries' => __( 'Libraries', 'sustainable-catalyst-library' ),
                    'history'   => __( 'Import History', 'sustainable-catalyst-library' ),
                ) as $tab_id => $label ) :
                    ?>
                    <a class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-source-discovery&tab=' . $tab_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ( 'discover' === $tab ) : ?>
                <?php $this->render_discover_tab( $providers ); ?>
            <?php elseif ( 'providers' === $tab ) : ?>
                <?php $this->render_provider_tab( $providers, $settings ); ?>
            <?php elseif ( 'libraries' === $tab ) : ?>
                <?php $this->render_libraries_tab(); ?>
            <?php else : ?>
                <?php $this->render_history_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_discover_tab( $providers ) {
        $searchable = array_filter(
            $providers,
            static function ( $provider ) {
                return ! empty( $provider['search'] );
            }
        );
        ?>
        <section class="sc-connector-panel sc-connector-discovery" data-sc-connector-discovery>
            <form class="sc-connector-search-form" data-sc-connector-search-form>
                <label class="sc-connector-search-form__query">
                    <span><?php esc_html_e( 'Title, author, DOI, ISBN, PMID, or research question', 'sustainable-catalyst-library' ); ?></span>
                    <input type="search" name="query" required minlength="2" placeholder="<?php esc_attr_e( 'Search scholarly and library databases…', 'sustainable-catalyst-library' ); ?>">
                </label>
                <label>
                    <span><?php esc_html_e( 'Results per provider', 'sustainable-catalyst-library' ); ?></span>
                    <select name="limit">
                        <option value="5">5</option>
                        <option value="8" selected>8</option>
                        <option value="12">12</option>
                    </select>
                </label>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Search Enabled Providers', 'sustainable-catalyst-library' ); ?></button>
            </form>

            <fieldset class="sc-connector-provider-picker">
                <legend><?php esc_html_e( 'Providers', 'sustainable-catalyst-library' ); ?></legend>
                <?php foreach ( $searchable as $provider_id => $provider ) : ?>
                    <label>
                        <input type="checkbox" name="providers[]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( ! empty( $provider['enabled'] ) ); ?> <?php disabled( empty( $provider['available'] ) ); ?>>
                        <span><strong><?php echo esc_html( $provider['name'] ); ?></strong><small><?php echo esc_html( $provider['description'] ); ?></small></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="sc-connector-search-status" data-sc-connector-status aria-live="polite"></div>
            <div class="sc-connector-result-summary" data-sc-connector-summary></div>
            <div class="sc-connector-results" data-sc-connector-results></div>
        </section>
        <?php
    }

    private function render_provider_tab( $providers, $settings ) {
        ?>
        <section class="sc-connector-panel">
            <h2><?php esc_html_e( 'Connector configuration', 'sustainable-catalyst-library' ); ?></h2>
            <p><?php esc_html_e( 'API secrets are stored in WordPress options unless the matching constants are defined in wp-config.php. Constants take precedence.', 'sustainable-catalyst-library' ); ?></p>

            <form class="sc-connector-settings-form" data-sc-connector-settings-form>
                <div class="sc-connector-settings-grid">
                    <label><span><?php esc_html_e( 'Contact email', 'sustainable-catalyst-library' ); ?></span><input type="email" name="contact_email" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" placeholder="research@example.com"><small><?php esc_html_e( 'Used for Crossref polite requests, Open Library identification, NCBI, and Unpaywall.', 'sustainable-catalyst-library' ); ?></small></label>
                    <label><span><?php esc_html_e( 'OpenAlex API key', 'sustainable-catalyst-library' ); ?></span><input type="password" name="openalex_api_key" value="<?php echo esc_attr( $settings['openalex_api_key'] ); ?>" autocomplete="new-password"><small><code>SC_LIBRARY_OPENALEX_API_KEY</code></small></label>
                    <label><span><?php esc_html_e( 'Google Books API key', 'sustainable-catalyst-library' ); ?></span><input type="password" name="google_books_api_key" value="<?php echo esc_attr( $settings['google_books_api_key'] ); ?>" autocomplete="new-password"><small><code>SC_LIBRARY_GOOGLE_BOOKS_API_KEY</code></small></label>
                    <label><span><?php esc_html_e( 'NCBI API key', 'sustainable-catalyst-library' ); ?></span><input type="password" name="ncbi_api_key" value="<?php echo esc_attr( $settings['ncbi_api_key'] ); ?>" autocomplete="new-password"><small><code>SC_LIBRARY_NCBI_API_KEY</code></small></label>
                    <label><span><?php esc_html_e( 'NCBI tool name', 'sustainable-catalyst-library' ); ?></span><input type="text" name="ncbi_tool" value="<?php echo esc_attr( $settings['ncbi_tool'] ); ?>"></label>
                    <label><span><?php esc_html_e( 'Cache duration', 'sustainable-catalyst-library' ); ?></span><select name="cache_ttl"><option value="3600" <?php selected( 3600, $settings['cache_ttl'] ); ?>>1 hour</option><option value="21600" <?php selected( 21600, $settings['cache_ttl'] ); ?>>6 hours</option><option value="43200" <?php selected( 43200, $settings['cache_ttl'] ); ?>>12 hours</option><option value="86400" <?php selected( 86400, $settings['cache_ttl'] ); ?>>24 hours</option></select></label>
                </div>
                <fieldset class="sc-connector-provider-settings">
                    <legend><?php esc_html_e( 'Enabled connectors', 'sustainable-catalyst-library' ); ?></legend>
                    <?php foreach ( $providers as $provider_id => $provider ) : ?>
                        <?php if ( empty( $provider['search'] ) && empty( $provider['locate'] ) ) { continue; } ?>
                        <label><input type="checkbox" name="enabled_providers[]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( ! empty( $provider['enabled'] ) ); ?>> <?php echo esc_html( $provider['name'] ); ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Connector Settings', 'sustainable-catalyst-library' ); ?></button></p>
                <div data-sc-settings-status aria-live="polite"></div>
            </form>

            <h2><?php esc_html_e( 'Provider status', 'sustainable-catalyst-library' ); ?></h2>
            <div class="sc-connector-provider-cards">
                <?php foreach ( $providers as $provider_id => $provider ) : ?>
                    <article data-provider-card="<?php echo esc_attr( $provider_id ); ?>">
                        <header><h3><?php echo esc_html( $provider['name'] ); ?></h3><strong class="<?php echo ! empty( $provider['available'] ) ? 'is-ready' : 'is-unavailable'; ?>"><?php echo esc_html( ! empty( $provider['available'] ) ? __( 'Available', 'sustainable-catalyst-library' ) : __( 'Configuration required', 'sustainable-catalyst-library' ) ); ?></strong></header>
                        <p><?php echo esc_html( $provider['description'] ); ?></p>
                        <dl>
                            <div><dt><?php esc_html_e( 'Mode', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $provider['mode'] ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Cache', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( human_time_diff( 0, $settings['cache_ttl'] ) ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Credentials', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $provider['credential_status'] ); ?></dd></div>
                        </dl>
                        <?php if ( ! empty( $provider['testable'] ) ) : ?><button type="button" class="button" data-sc-test-provider="<?php echo esc_attr( $provider_id ); ?>"><?php esc_html_e( 'Test Provider', 'sustainable-catalyst-library' ); ?></button><div data-provider-test-status aria-live="polite"></div><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php SC_Library_Connector_Holdings_Reliability::render_admin_reliability_panel(); ?>
        </section>
        <?php
    }

    private function render_libraries_tab() {
        $profiles = $this->library_profiles();
        ?>
        <section class="sc-connector-panel">
            <h2><?php esc_html_e( 'Library profiles', 'sustainable-catalyst-library' ); ?></h2>
            <p><?php esc_html_e( 'Profiles connect Source records to public-library, university, consortium, and institutional catalog searches, OpenURL resolvers, proxy links, and interlibrary-loan pages.', 'sustainable-catalyst-library' ); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::PROFILE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Library Profile', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::PROFILE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Profiles', 'sustainable-catalyst-library' ); ?></a></p>
            <?php if ( $profiles ) : ?>
                <div class="sc-library-profile-cards">
                    <?php foreach ( $profiles as $profile ) : ?>
                        <article>
                            <h3><?php echo esc_html( $profile['name'] ); ?></h3>
                            <p><?php echo esc_html( $profile['region'] ?: __( 'No region recorded', 'sustainable-catalyst-library' ) ); ?></p>
                            <ul>
                                <li><?php echo esc_html( $profile['catalog_template'] ? __( 'Catalog search configured', 'sustainable-catalyst-library' ) : __( 'Catalog search not configured', 'sustainable-catalyst-library' ) ); ?></li>
                                <li><?php echo esc_html( $profile['openurl_base'] ? __( 'OpenURL resolver configured', 'sustainable-catalyst-library' ) : __( 'OpenURL resolver not configured', 'sustainable-catalyst-library' ) ); ?></li>
                                <li><?php echo esc_html( $profile['ill_url'] ? __( 'Interlibrary loan configured', 'sustainable-catalyst-library' ) : __( 'Interlibrary loan not configured', 'sustainable-catalyst-library' ) ); ?></li>
                            </ul>
                            <a class="button" href="<?php echo esc_url( get_edit_post_link( $profile['id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit Profile', 'sustainable-catalyst-library' ); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e( 'No enabled library profiles are available yet.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_history_tab() {
        $sources = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 50,
                'meta_key'       => self::META_CONNECTOR_LAST_CHECKED,
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
            )
        );
        ?>
        <section class="sc-connector-panel">
            <h2><?php esc_html_e( 'Recent connector imports and checks', 'sustainable-catalyst-library' ); ?></h2>
            <?php if ( $sources ) : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Connectors', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Last checked', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Access locations', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ( $sources as $source ) : ?>
                        <?php $connector_ids = get_post_meta( $source->ID, self::META_CONNECTOR_IDS, true ); $locations = get_post_meta( $source->ID, self::META_ACCESS_LOCATIONS, true ); ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $source->ID, 'raw' ) ); ?>"><?php echo esc_html( $source->post_title ); ?></a></td>
                            <td><?php echo esc_html( implode( ', ', array_keys( is_array( $connector_ids ) ? $connector_ids : array() ) ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $source->ID, self::META_CONNECTOR_LAST_CHECKED, true ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( count( is_array( $locations ) ? $locations : array() ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No connector import or location history is available yet.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </section>
        <?php
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_discovery = false !== strpos( (string) $screen->id, 'sc-library-source-discovery' );
        $is_profile = self::PROFILE_POST_TYPE === $screen->post_type;
        $is_source = SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === $screen->post_type;
        if ( ! $is_discovery && ! $is_profile && ! $is_source ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-connectors',
            SC_LIBRARY_URL . 'assets/css/sc-library-connectors.css',
            array(),
            $this->version()
        );
        wp_enqueue_script(
            'sc-library-connectors',
            SC_LIBRARY_URL . 'assets/js/sc-library-connectors.js',
            array(),
            $this->version(),
            true
        );
        wp_localize_script( 'sc-library-connectors', 'SCLibraryConnectors', $this->client_config() );
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-connectors',
            SC_LIBRARY_URL . 'assets/css/sc-library-connectors.css',
            array(),
            $this->version()
        );
        wp_register_script(
            'sc-library-connectors',
            SC_LIBRARY_URL . 'assets/js/sc-library-connectors.js',
            array(),
            $this->version(),
            true
        );
        wp_localize_script( 'sc-library-connectors', 'SCLibraryConnectors', $this->client_config() );
    }

    private function client_config() {
        return array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'sc_library_connectors_v260' ),
            'restUrl'          => rest_url( self::API_NAMESPACE ),
            'restNonce'        => wp_create_nonce( 'wp_rest' ),
            'canImport'        => current_user_can( 'edit_posts' ),
            'projectId'        => absint( wp_unslash( $_GET['project_id'] ?? 0 ) ),
            'strings'          => array(
                'searching'     => __( 'Searching provider…', 'sustainable-catalyst-library' ),
                'complete'      => __( 'Search complete.', 'sustainable-catalyst-library' ),
                'failed'        => __( 'Provider search failed.', 'sustainable-catalyst-library' ),
                'importing'     => __( 'Importing source…', 'sustainable-catalyst-library' ),
                'imported'      => __( 'Source imported as a draft.', 'sustainable-catalyst-library' ),
                'locating'      => __( 'Checking source locations…', 'sustainable-catalyst-library' ),
                'none'          => __( 'No matching records were returned.', 'sustainable-catalyst-library' ),
                'settingsSaved' => __( 'Connector settings saved.', 'sustainable-catalyst-library' ),
            ),
        );
    }

    private function settings() {
        $stored = get_option( self::OPTION_SETTINGS, array() );
        $stored = is_array( $stored ) ? $stored : array();

        $defaults = array(
            'contact_email'       => get_option( 'admin_email' ),
            'openalex_api_key'    => '',
            'google_books_api_key'=> '',
            'ncbi_api_key'        => '',
            'ncbi_tool'           => 'sustainable-catalyst-library',
            'cache_ttl'           => self::DEFAULT_CACHE_TTL,
            'enabled_providers'   => array( 'crossref', 'datacite', 'pubmed', 'pmc', 'loc', 'openlibrary', 'unpaywall', 'google_scholar', 'worldcat' ),
        );
        $settings = wp_parse_args( $stored, $defaults );

        $settings['contact_email'] = defined( 'SC_LIBRARY_CONNECTOR_EMAIL' ) && SC_LIBRARY_CONNECTOR_EMAIL
            ? sanitize_email( SC_LIBRARY_CONNECTOR_EMAIL )
            : sanitize_email( $settings['contact_email'] );
        $settings['openalex_api_key'] = defined( 'SC_LIBRARY_OPENALEX_API_KEY' ) && SC_LIBRARY_OPENALEX_API_KEY
            ? sanitize_text_field( SC_LIBRARY_OPENALEX_API_KEY )
            : sanitize_text_field( $settings['openalex_api_key'] );
        $settings['google_books_api_key'] = defined( 'SC_LIBRARY_GOOGLE_BOOKS_API_KEY' ) && SC_LIBRARY_GOOGLE_BOOKS_API_KEY
            ? sanitize_text_field( SC_LIBRARY_GOOGLE_BOOKS_API_KEY )
            : sanitize_text_field( $settings['google_books_api_key'] );
        $settings['ncbi_api_key'] = defined( 'SC_LIBRARY_NCBI_API_KEY' ) && SC_LIBRARY_NCBI_API_KEY
            ? sanitize_text_field( SC_LIBRARY_NCBI_API_KEY )
            : sanitize_text_field( $settings['ncbi_api_key'] );
        $settings['ncbi_tool'] = defined( 'SC_LIBRARY_NCBI_TOOL' ) && SC_LIBRARY_NCBI_TOOL
            ? sanitize_key( SC_LIBRARY_NCBI_TOOL )
            : sanitize_key( $settings['ncbi_tool'] );
        $settings['cache_ttl'] = max( HOUR_IN_SECONDS, min( 7 * DAY_IN_SECONDS, absint( $settings['cache_ttl'] ) ) );
        $settings['enabled_providers'] = array_values( array_unique( array_map( 'sanitize_key', (array) $settings['enabled_providers'] ) ) );

        return $settings;
    }

    public function providers() {
        $settings = $this->settings();
        $enabled = array_flip( $settings['enabled_providers'] );

        $providers = array(
            'crossref' => array(
                'name'              => 'Crossref',
                'description'       => __( 'DOI and scholarly publication metadata with funding, license, contributor, and publication details.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Live REST search and DOI lookup', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => false,
                'testable'          => true,
                'available'         => true,
                'enabled'           => isset( $enabled['crossref'] ),
                'credential_status' => $settings['contact_email'] ? __( 'Polite contact configured', 'sustainable-catalyst-library' ) : __( 'Public pool only', 'sustainable-catalyst-library' ),
            ),
            'openalex' => array(
                'name'              => 'OpenAlex',
                'description'       => __( 'Open scholarly works, authors, institutions, topics, citation relationships, and access locations.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Live works search and DOI lookup', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => true,
                'testable'          => true,
                'available'         => (bool) $settings['openalex_api_key'],
                'enabled'           => isset( $enabled['openalex'] ),
                'credential_status' => $settings['openalex_api_key'] ? __( 'API key configured', 'sustainable-catalyst-library' ) : __( 'API key required for production', 'sustainable-catalyst-library' ),
            ),
            'datacite' => array(
                'name'              => 'DataCite',
                'description'       => __( 'DOI metadata for datasets, software, reports, technical resources, books, and research outputs.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Live DOI search and lookup', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => false,
                'testable'          => true,
                'available'         => true,
                'enabled'           => isset( $enabled['datacite'] ),
                'credential_status' => __( 'Public metadata retrieval', 'sustainable-catalyst-library' ),
            ),
            'pubmed' => array(
                'name'              => 'PubMed',
                'description'       => __( 'Biomedical and life-science citation records from NCBI Entrez.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'E-utilities search and summary', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => false,
                'testable'          => true,
                'available'         => true,
                'enabled'           => isset( $enabled['pubmed'] ),
                'credential_status' => $settings['ncbi_api_key'] ? __( 'API key configured', 'sustainable-catalyst-library' ) : __( 'Lower unauthenticated limits', 'sustainable-catalyst-library' ),
            ),
            'pmc' => array(
                'name'              => 'PubMed Central',
                'description'       => __( 'Biomedical records with a focus on articles available through the PubMed Central archive.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'E-utilities search and summary', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => true,
                'testable'          => true,
                'available'         => true,
                'enabled'           => isset( $enabled['pmc'] ),
                'credential_status' => $settings['ncbi_api_key'] ? __( 'API key configured', 'sustainable-catalyst-library' ) : __( 'Lower unauthenticated limits', 'sustainable-catalyst-library' ),
            ),
            'loc' => array(
                'name'              => 'Library of Congress',
                'description'       => __( 'Digitized books, maps, manuscripts, photographs, audiovisual items, web archives, and collection metadata.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Public loc.gov JSON search', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => true,
                'testable'          => true,
                'available'         => true,
                'enabled'           => isset( $enabled['loc'] ),
                'credential_status' => __( 'No API key required', 'sustainable-catalyst-library' ),
            ),
            'openlibrary' => array(
                'name'              => 'Open Library',
                'description'       => __( 'Human-facing book and edition discovery with identifiers, covers, subjects, and availability links.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Low-volume public book search', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => true,
                'testable'          => true,
                'available'         => (bool) $settings['contact_email'],
                'enabled'           => isset( $enabled['openlibrary'] ),
                'credential_status' => $settings['contact_email'] ? __( 'Identified requests configured', 'sustainable-catalyst-library' ) : __( 'Contact email recommended', 'sustainable-catalyst-library' ),
            ),
            'googlebooks' => array(
                'name'              => 'Google Books',
                'description'       => __( 'Book metadata, identifiers, preview links, reading access, and edition discovery.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Google Books volumes search', 'sustainable-catalyst-library' ),
                'search'            => true,
                'locate'            => true,
                'testable'          => true,
                'available'         => (bool) $settings['google_books_api_key'],
                'enabled'           => isset( $enabled['googlebooks'] ),
                'credential_status' => $settings['google_books_api_key'] ? __( 'API key configured', 'sustainable-catalyst-library' ) : __( 'API key required', 'sustainable-catalyst-library' ),
            ),
            'unpaywall' => array(
                'name'              => 'Unpaywall',
                'description'       => __( 'DOI-based legal open-access locations and best available open copies.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'DOI lookup only', 'sustainable-catalyst-library' ),
                'search'            => false,
                'locate'            => true,
                'testable'          => false,
                'available'         => (bool) $settings['contact_email'],
                'enabled'           => isset( $enabled['unpaywall'] ),
                'credential_status' => $settings['contact_email'] ? __( 'Contact email configured', 'sustainable-catalyst-library' ) : __( 'Contact email required', 'sustainable-catalyst-library' ),
            ),
            'google_scholar' => array(
                'name'              => 'Google Scholar',
                'description'       => __( 'Compliant outbound title, author, DOI, related-work, and citation-discovery searches. No automated scraping.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Browser search handoff', 'sustainable-catalyst-library' ),
                'search'            => false,
                'locate'            => true,
                'testable'          => false,
                'available'         => true,
                'enabled'           => isset( $enabled['google_scholar'] ),
                'credential_status' => __( 'No stored credentials', 'sustainable-catalyst-library' ),
            ),
            'worldcat' => array(
                'name'              => 'WorldCat',
                'description'       => __( 'Outbound global-library discovery by title, ISBN, author, DOI, or keyword.', 'sustainable-catalyst-library' ),
                'mode'              => __( 'Browser search handoff', 'sustainable-catalyst-library' ),
                'search'            => false,
                'locate'            => true,
                'testable'          => false,
                'available'         => true,
                'enabled'           => isset( $enabled['worldcat'] ),
                'credential_status' => __( 'Public search handoff', 'sustainable-catalyst-library' ),
            ),
        );

        return apply_filters( 'sc_library_discovery_providers', $providers, $settings );
    }

    public function ajax_save_settings() {
        $this->verify_ajax( 'manage_options' );
        $settings = $this->settings();

        $settings['contact_email'] = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $settings['openalex_api_key'] = sanitize_text_field( wp_unslash( $_POST['openalex_api_key'] ?? '' ) );
        $settings['google_books_api_key'] = sanitize_text_field( wp_unslash( $_POST['google_books_api_key'] ?? '' ) );
        $settings['ncbi_api_key'] = sanitize_text_field( wp_unslash( $_POST['ncbi_api_key'] ?? '' ) );
        $settings['ncbi_tool'] = sanitize_key( wp_unslash( $_POST['ncbi_tool'] ?? 'sustainable-catalyst-library' ) );
        $settings['cache_ttl'] = max( HOUR_IN_SECONDS, min( DAY_IN_SECONDS, absint( wp_unslash( $_POST['cache_ttl'] ?? self::DEFAULT_CACHE_TTL ) ) ) );
        $settings['enabled_providers'] = array_values( array_unique( array_map( 'sanitize_key', (array) wp_unslash( $_POST['enabled_providers'] ?? array() ) ) ) );

        update_option( self::OPTION_SETTINGS, $settings, false );
        wp_send_json_success( array( 'message' => __( 'Connector settings saved.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_search_provider() {
        $this->verify_discovery_ajax();
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
        $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        $limit = max( 1, min( 20, absint( wp_unslash( $_POST['limit'] ?? self::DEFAULT_RESULTS ) ) ) );

        $rate = $this->enforce_rate_limit( 'search', 60, 10 * MINUTE_IN_SECONDS );
        if ( is_wp_error( $rate ) ) {
            wp_send_json_error( array( 'message' => $rate->get_error_message() ), 429 );
        }

        $result = $this->search_provider( $provider, $query, $limit );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array(
                    'provider' => $provider,
                    'message'  => $result->get_error_message(),
                    'code'     => $result->get_error_code(),
                ),
                absint( $result->get_error_data( 'status' ) ?: 502 )
            );
        }

        wp_send_json_success( $result );
    }

    public function ajax_test_provider() {
        $this->verify_ajax( 'manage_options' );
        $provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
        $started = microtime( true );
        $result = $this->search_provider( $provider, 'climate', 1, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'provider' => $provider ), 502 );
        }
        wp_send_json_success(
            array(
                'provider'   => $provider,
                'message'    => __( 'Provider test succeeded.', 'sustainable-catalyst-library' ),
                'duration_ms'=> round( ( microtime( true ) - $started ) * 1000 ),
                'result_count'=> count( $result['results'] ?? array() ),
            )
        );
    }

    private function verify_discovery_ajax() {
        check_ajax_referer( 'sc_library_connectors_v260', 'nonce' );
        if ( current_user_can( 'edit_posts' ) || apply_filters( 'sc_library_allow_public_discovery', false ) ) {
            return;
        }
        wp_send_json_error( array( 'message' => __( 'Source discovery is available to authorized researchers.', 'sustainable-catalyst-library' ) ), 403 );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_connectors_v260', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to use this connector action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    private function enforce_rate_limit( $action, $limit, $window ) {
        $user_id = get_current_user_id();
        $identity = $user_id ? 'u' . $user_id : 'ip' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ) );
        $key = self::CACHE_PREFIX . 'rate_' . sanitize_key( $action ) . '_' . $identity;
        $state = get_transient( $key );
        $state = is_array( $state ) ? $state : array( 'count' => 0, 'started' => time() );

        if ( time() - absint( $state['started'] ) >= $window ) {
            $state = array( 'count' => 0, 'started' => time() );
        }
        $state['count']++;
        set_transient( $key, $state, $window );

        if ( $state['count'] > $limit ) {
            return new WP_Error( 'connector_rate_limited', __( 'Too many connector requests. Try again after the rate-limit window resets.', 'sustainable-catalyst-library' ), array( 'status' => 429 ) );
        }
        return true;
    }

    public function search_provider( $provider_id, $query, $limit = self::DEFAULT_RESULTS, $bypass_cache = false ) {
        $provider_id = sanitize_key( $provider_id );
        $query = trim( sanitize_text_field( $query ) );
        $limit = max( 1, min( 20, absint( $limit ) ) );
        $providers = $this->providers();

        if ( ! isset( $providers[ $provider_id ] ) || empty( $providers[ $provider_id ]['search'] ) ) {
            return new WP_Error( 'provider_not_searchable', __( 'The selected provider does not support direct search.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        if ( empty( $providers[ $provider_id ]['available'] ) ) {
            return new WP_Error( 'provider_unavailable', __( 'The selected provider requires additional configuration.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) );
        }
        if ( strlen( $query ) < 2 ) {
            return new WP_Error( 'query_too_short', __( 'Enter at least two characters.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $cache_key = self::CACHE_PREFIX . 'search_' . md5( $provider_id . '|' . strtolower( $query ) . '|' . $limit );
        if ( ! $bypass_cache ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                $cached['results'] = $this->seal_results( $cached['results'] ?? array(), $provider_id, $query );
                $cached['cached'] = true;
                $cached['stale'] = false;
                $cached['cache_state'] = 'fresh';
                $cached['provider_health'] = SC_Library_Connector_Holdings_Reliability::provider_health( $provider_id );
                return $cached;
            }
        }

        $stale_payload = SC_Library_Connector_Holdings_Reliability::get_stale_search_cache( $cache_key );
        $circuit = SC_Library_Connector_Holdings_Reliability::provider_request_state( $provider_id );
        $backoff = get_transient( self::CACHE_PREFIX . 'backoff_' . $provider_id );
        if ( is_wp_error( $circuit ) || $backoff ) {
            if ( $stale_payload ) {
                $stale_payload['results'] = $this->seal_results( $stale_payload['results'] ?? array(), $provider_id, $query );
                $stale_payload['provider_health'] = SC_Library_Connector_Holdings_Reliability::provider_health( $provider_id );
                return $stale_payload;
            }
            return is_wp_error( $circuit )
                ? $circuit
                : new WP_Error( 'provider_backoff', sprintf( __( '%s is temporarily paused after a rate-limit or service error.', 'sustainable-catalyst-library' ), $providers[ $provider_id ]['name'] ), array( 'status' => 503 ) );
        }

        $method = 'search_' . $provider_id;
        if ( ! method_exists( $this, $method ) ) {
            return new WP_Error( 'provider_not_implemented', __( 'The selected connector is not implemented.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
        }

        $started = microtime( true );
        $results = $this->{$method}( $query, $limit );
        if ( is_wp_error( $results ) ) {
            if ( $stale_payload ) {
                $stale_payload['results'] = $this->seal_results( $stale_payload['results'] ?? array(), $provider_id, $query );
                $stale_payload['live_error'] = $results->get_error_message();
                $stale_payload['provider_health'] = SC_Library_Connector_Holdings_Reliability::provider_health( $provider_id );
                return $stale_payload;
            }
            return $results;
        }

        $payload = array(
            'schema'       => self::SEARCH_SCHEMA,
            'provider'     => $provider_id,
            'provider_name'=> $providers[ $provider_id ]['name'],
            'query'        => $query,
            'result_count' => count( $results ),
            'results'      => array_values( $results ),
            'cached'       => false,
            'stale'        => false,
            'cache_state'  => 'network',
            'duration_ms'  => round( ( microtime( true ) - $started ) * 1000 ),
            'searched_at'  => current_time( 'mysql' ),
            'provider_health' => SC_Library_Connector_Holdings_Reliability::provider_health( $provider_id ),
        );

        set_transient( $cache_key, $payload, $this->settings()['cache_ttl'] );
        SC_Library_Connector_Holdings_Reliability::store_stale_search_cache( $cache_key, $payload );
        $payload['results'] = $this->seal_results( $payload['results'], $provider_id, $query );
        return $payload;
    }

    private function request_json( $provider_id, $url, $headers = array(), $timeout = self::DEFAULT_TIMEOUT ) {
        return SC_Library_Connector_Holdings_Reliability::request_json(
            $provider_id,
            $url,
            $headers,
            $timeout,
            $this->settings()
        );
    }

    private function search_crossref( $query, $limit ) {
        $settings = $this->settings();
        $params = array(
            'query.bibliographic' => $query,
            'rows'                => $limit,
            'select'              => 'DOI,title,author,issued,published,container-title,publisher,type,URL,ISBN,ISSN,page,volume,issue,abstract,language',
        );
        if ( $settings['contact_email'] ) {
            $params['mailto'] = $settings['contact_email'];
        }
        $data = $this->request_json( 'crossref', add_query_arg( $params, 'https://api.crossref.org/works' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['message']['items'] ?? array() ) as $item ) {
            $title = self::first_string( $item['title'] ?? array() );
            if ( ! $title ) {
                continue;
            }
            $authors = array();
            foreach ( (array) ( $item['author'] ?? array() ) as $author ) {
                $authors[] = self::person(
                    $author['family'] ?? '',
                    $author['given'] ?? '',
                    '',
                    $author['ORCID'] ?? ''
                );
            }
            $issued = $item['issued']['date-parts'][0] ?? $item['published']['date-parts'][0] ?? array();
            $year = isset( $issued[0] ) ? (string) absint( $issued[0] ) : '';
            $type = $this->map_crossref_type( $item['type'] ?? '' );
            $doi = self::normalize_doi( $item['DOI'] ?? '' );
            $url = esc_url_raw( $item['URL'] ?? ( $doi ? 'https://doi.org/' . $doi : '' ) );
            $results[] = $this->normalized_result(
                'crossref',
                (string) ( $item['DOI'] ?? md5( wp_json_encode( $item ) ) ),
                array(
                    'title'            => $title,
                    'authors'          => $authors,
                    'organization'     => '',
                    'year'             => $year,
                    'publication_date' => self::date_parts_to_iso( $issued ),
                    'source_type'      => $type,
                    'container_title'  => self::first_string( $item['container-title'] ?? array() ),
                    'publisher'        => sanitize_text_field( $item['publisher'] ?? '' ),
                    'volume'           => sanitize_text_field( $item['volume'] ?? '' ),
                    'issue'            => sanitize_text_field( $item['issue'] ?? '' ),
                    'pages'            => sanitize_text_field( $item['page'] ?? '' ),
                    'doi'              => $doi,
                    'isbn'             => self::first_string( $item['ISBN'] ?? array() ),
                    'url'              => $url,
                    'language'         => sanitize_text_field( $item['language'] ?? '' ),
                    'abstract'         => self::clean_abstract( $item['abstract'] ?? '' ),
                    'full_text_status' => '',
                    'record_url'       => $url,
                    'identifiers'      => array_filter(
                        array(
                            'doi'  => $doi,
                            'isbn' => self::first_string( $item['ISBN'] ?? array() ),
                            'issn' => self::first_string( $item['ISSN'] ?? array() ),
                        )
                    ),
                    'raw_type'         => sanitize_text_field( $item['type'] ?? '' ),
                )
            );
        }
        return $results;
    }

    private function search_openalex( $query, $limit ) {
        $settings = $this->settings();
        $params = array(
            'search'   => $query,
            'per-page' => $limit,
            'api_key'  => $settings['openalex_api_key'],
        );
        $data = $this->request_json( 'openalex', add_query_arg( $params, 'https://api.openalex.org/works' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['results'] ?? array() ) as $item ) {
            $title = sanitize_text_field( $item['display_name'] ?? $item['title'] ?? '' );
            if ( ! $title ) {
                continue;
            }
            $authors = array();
            foreach ( (array) ( $item['authorships'] ?? array() ) as $authorship ) {
                $display = sanitize_text_field( $authorship['author']['display_name'] ?? '' );
                $authors[] = self::person_from_display_name( $display, $authorship['author']['orcid'] ?? '' );
            }
            $primary = is_array( $item['primary_location'] ?? null ) ? $item['primary_location'] : array();
            $source = is_array( $primary['source'] ?? null ) ? $primary['source'] : array();
            $best_oa = is_array( $item['best_oa_location'] ?? null ) ? $item['best_oa_location'] : array();
            $doi = self::normalize_doi( $item['doi'] ?? '' );
            $open_url = esc_url_raw( $best_oa['pdf_url'] ?? $best_oa['landing_page_url'] ?? '' );
            $record_url = esc_url_raw( $item['id'] ?? '' );
            $results[] = $this->normalized_result(
                'openalex',
                sanitize_text_field( basename( (string) ( $item['id'] ?? '' ) ) ?: md5( wp_json_encode( $item ) ) ),
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => '',
                    'year'             => sanitize_text_field( $item['publication_year'] ?? '' ),
                    'publication_date' => sanitize_text_field( $item['publication_date'] ?? '' ),
                    'source_type'      => $this->map_openalex_type( $item['type'] ?? '' ),
                    'container_title'  => sanitize_text_field( $source['display_name'] ?? '' ),
                    'publisher'        => sanitize_text_field( $source['host_organization_name'] ?? '' ),
                    'volume'           => sanitize_text_field( $item['biblio']['volume'] ?? '' ),
                    'issue'            => sanitize_text_field( $item['biblio']['issue'] ?? '' ),
                    'pages'            => self::openalex_pages( $item['biblio'] ?? array() ),
                    'doi'              => $doi,
                    'pmid'             => self::external_id_value( $item['ids']['pmid'] ?? '' ),
                    'url'              => esc_url_raw( $primary['landing_page_url'] ?? ( $doi ? 'https://doi.org/' . $doi : $record_url ) ),
                    'open_access_url'  => $open_url,
                    'full_text_status' => ! empty( $item['open_access']['is_oa'] ) ? 'open-access' : '',
                    'record_url'       => $record_url,
                    'cited_by_count'   => absint( $item['cited_by_count'] ?? 0 ),
                    'topics'           => array_values(
                        array_filter(
                            array_map(
                                static function ( $topic ) {
                                    return sanitize_text_field( $topic['display_name'] ?? '' );
                                },
                                (array) ( $item['topics'] ?? array() )
                            )
                        )
                    ),
                    'identifiers'      => array_filter(
                        array(
                            'doi'       => $doi,
                            'openalex'  => sanitize_text_field( $item['id'] ?? '' ),
                            'pmid'      => self::external_id_value( $item['ids']['pmid'] ?? '' ),
                            'mag'       => sanitize_text_field( $item['ids']['mag'] ?? '' ),
                        )
                    ),
                    'raw_type'         => sanitize_text_field( $item['type'] ?? '' ),
                )
            );
        }
        return $results;
    }

    private function search_datacite( $query, $limit ) {
        $params = array(
            'query'     => $query,
            'page[size]'=> $limit,
        );
        $data = $this->request_json( 'datacite', add_query_arg( $params, 'https://api.datacite.org/dois' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['data'] ?? array() ) as $record ) {
            $attributes = is_array( $record['attributes'] ?? null ) ? $record['attributes'] : array();
            $title = '';
            foreach ( (array) ( $attributes['titles'] ?? array() ) as $title_record ) {
                if ( ! empty( $title_record['title'] ) ) {
                    $title = sanitize_text_field( $title_record['title'] );
                    break;
                }
            }
            if ( ! $title ) {
                continue;
            }
            $authors = array();
            $organization = '';
            foreach ( (array) ( $attributes['creators'] ?? array() ) as $creator ) {
                if ( 'Organizational' === ( $creator['nameType'] ?? '' ) && ! $organization ) {
                    $organization = sanitize_text_field( $creator['name'] ?? '' );
                    continue;
                }
                $orcid = '';
                foreach ( (array) ( $creator['nameIdentifiers'] ?? array() ) as $identifier ) {
                    if ( false !== stripos( (string) ( $identifier['nameIdentifierScheme'] ?? '' ), 'orcid' ) ) {
                        $orcid = $identifier['nameIdentifier'] ?? '';
                    }
                }
                $authors[] = self::person(
                    $creator['familyName'] ?? '',
                    $creator['givenName'] ?? '',
                    '',
                    $orcid
                );
            }
            $doi = self::normalize_doi( $attributes['doi'] ?? $record['id'] ?? '' );
            $descriptions = (array) ( $attributes['descriptions'] ?? array() );
            $abstract = '';
            foreach ( $descriptions as $description ) {
                if ( in_array( $description['descriptionType'] ?? '', array( 'Abstract', 'Other' ), true ) ) {
                    $abstract = self::clean_abstract( $description['description'] ?? '' );
                    if ( $abstract ) {
                        break;
                    }
                }
            }
            $publisher = sanitize_text_field( $attributes['publisher'] ?? '' );
            if ( is_array( $attributes['publisher'] ?? null ) ) {
                $publisher = sanitize_text_field( $attributes['publisher']['name'] ?? '' );
            }
            $container = '';
            if ( is_array( $attributes['container'] ?? null ) ) {
                $container = sanitize_text_field( $attributes['container']['title'] ?? '' );
            }
            $url = esc_url_raw( $attributes['url'] ?? ( $doi ? 'https://doi.org/' . $doi : '' ) );
            $results[] = $this->normalized_result(
                'datacite',
                (string) ( $record['id'] ?? $doi ?: md5( wp_json_encode( $record ) ) ),
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => $organization,
                    'year'             => sanitize_text_field( $attributes['publicationYear'] ?? '' ),
                    'publication_date' => self::datacite_date( $attributes['dates'] ?? array() ),
                    'source_type'      => $this->map_datacite_type( $attributes['types']['resourceTypeGeneral'] ?? $attributes['types']['resourceType'] ?? '' ),
                    'container_title'  => $container,
                    'publisher'        => $publisher,
                    'doi'              => $doi,
                    'url'              => $url,
                    'language'         => sanitize_text_field( $attributes['language'] ?? '' ),
                    'abstract'         => $abstract,
                    'full_text_status' => '',
                    'record_url'       => $url,
                    'identifiers'      => array_filter( array( 'doi' => $doi ) ),
                    'raw_type'         => sanitize_text_field( $attributes['types']['resourceTypeGeneral'] ?? '' ),
                )
            );
        }
        return $results;
    }

    private function search_pubmed( $query, $limit ) {
        return $this->search_ncbi_database( 'pubmed', $query, $limit );
    }

    private function search_pmc( $query, $limit ) {
        return $this->search_ncbi_database( 'pmc', $query, $limit );
    }

    private function search_ncbi_database( $database, $query, $limit ) {
        $settings = $this->settings();
        $common = array(
            'db'      => $database,
            'retmode' => 'json',
            'tool'    => $settings['ncbi_tool'],
            'email'   => $settings['contact_email'],
        );
        if ( $settings['ncbi_api_key'] ) {
            $common['api_key'] = $settings['ncbi_api_key'];
        }

        $search_params = array_merge(
            $common,
            array(
                'term'   => $query,
                'retmax' => $limit,
                'sort'   => 'relevance',
            )
        );
        $search = $this->request_json(
            $database,
            add_query_arg( $search_params, 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi' )
        );
        if ( is_wp_error( $search ) ) {
            return $search;
        }
        $ids = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $search['esearchresult']['idlist'] ?? array() ) ) ) );
        if ( ! $ids ) {
            return array();
        }

        $summary_params = array_merge(
            $common,
            array(
                'id' => implode( ',', $ids ),
            )
        );
        $summary = $this->request_json(
            $database,
            add_query_arg( $summary_params, 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi' )
        );
        if ( is_wp_error( $summary ) ) {
            return $summary;
        }

        $results = array();
        foreach ( $ids as $id ) {
            $item = is_array( $summary['result'][ $id ] ?? null ) ? $summary['result'][ $id ] : array();
            $title = sanitize_text_field( html_entity_decode( $item['title'] ?? '', ENT_QUOTES, 'UTF-8' ) );
            if ( ! $title ) {
                continue;
            }
            $authors = array();
            foreach ( (array) ( $item['authors'] ?? array() ) as $author ) {
                $authors[] = self::person_from_display_name( sanitize_text_field( $author['name'] ?? '' ) );
            }
            $article_ids = array();
            foreach ( (array) ( $item['articleids'] ?? array() ) as $identifier ) {
                $id_type = sanitize_key( $identifier['idtype'] ?? '' );
                $value = sanitize_text_field( $identifier['value'] ?? '' );
                if ( $id_type && $value ) {
                    $article_ids[ $id_type ] = $value;
                }
            }
            $doi = self::normalize_doi( $article_ids['doi'] ?? '' );
            $pmid = 'pubmed' === $database ? $id : sanitize_text_field( $article_ids['pmid'] ?? '' );
            $pmcid = 'pmc' === $database ? ( 0 === stripos( $id, 'PMC' ) ? $id : 'PMC' . $id ) : sanitize_text_field( $article_ids['pmcid'] ?? '' );
            $record_url = 'pmc' === $database
                ? 'https://pmc.ncbi.nlm.nih.gov/articles/' . rawurlencode( $pmcid ?: $id ) . '/'
                : 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode( $id ) . '/';
            $pubdate = sanitize_text_field( $item['pubdate'] ?? $item['epubdate'] ?? '' );
            $year = preg_match( '/\b(1[0-9]{3}|20[0-9]{2}|21[0-9]{2})\b/', $pubdate, $match ) ? $match[1] : '';
            $pages = sanitize_text_field( $item['pages'] ?? '' );
            $results[] = $this->normalized_result(
                $database,
                $id,
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => '',
                    'year'             => $year,
                    'publication_date' => self::parse_loose_date( $pubdate ),
                    'source_type'      => 'journal-article',
                    'container_title'  => sanitize_text_field( $item['fulljournalname'] ?? $item['source'] ?? '' ),
                    'publisher'        => '',
                    'volume'           => sanitize_text_field( $item['volume'] ?? '' ),
                    'issue'            => sanitize_text_field( $item['issue'] ?? '' ),
                    'pages'            => $pages,
                    'doi'              => $doi,
                    'pmid'             => $pmid,
                    'url'              => $doi ? 'https://doi.org/' . $doi : $record_url,
                    'open_access_url'  => 'pmc' === $database ? $record_url : ( $pmcid ? 'https://pmc.ncbi.nlm.nih.gov/articles/' . rawurlencode( $pmcid ) . '/' : '' ),
                    'full_text_status' => 'pmc' === $database || $pmcid ? 'open-access' : '',
                    'record_url'       => $record_url,
                    'identifiers'      => array_filter(
                        array(
                            'pmid'  => $pmid,
                            'pmcid' => $pmcid,
                            'doi'   => $doi,
                            'pii'   => $article_ids['pii'] ?? '',
                        )
                    ),
                    'raw_type'         => implode( ', ', array_map( 'sanitize_text_field', (array) ( $item['pubtype'] ?? array() ) ) ),
                )
            );
        }
        return $results;
    }

    private function search_loc( $query, $limit ) {
        $params = array(
            'q'  => $query,
            'fo' => 'json',
            'c'  => $limit,
        );
        $data = $this->request_json( 'loc', add_query_arg( $params, 'https://www.loc.gov/search/' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['results'] ?? array() ) as $item ) {
            $title = sanitize_text_field( $item['title'] ?? '' );
            if ( ! $title ) {
                continue;
            }
            $contributors = (array) ( $item['contributor'] ?? array() );
            $authors = array();
            foreach ( $contributors as $contributor ) {
                $authors[] = self::person_from_display_name( sanitize_text_field( $contributor ) );
            }
            $date = sanitize_text_field( $item['date'] ?? '' );
            $year = preg_match( '/\b(1[0-9]{3}|20[0-9]{2}|21[0-9]{2})\b/', $date, $match ) ? $match[1] : '';
            $formats = array_map( 'sanitize_text_field', (array) ( $item['original_format'] ?? $item['format'] ?? array() ) );
            $online_formats = array_map( 'sanitize_text_field', (array) ( $item['online_format'] ?? array() ) );
            $record_url = esc_url_raw( $item['id'] ?? $item['url'] ?? '' );
            $image_url = '';
            if ( ! empty( $item['image_url'] ) && is_array( $item['image_url'] ) ) {
                $image_url = esc_url_raw( end( $item['image_url'] ) );
            }
            $results[] = $this->normalized_result(
                'loc',
                sanitize_text_field( basename( untrailingslashit( $record_url ) ) ?: md5( wp_json_encode( $item ) ) ),
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => sanitize_text_field( $item['partof'][0] ?? '' ),
                    'year'             => $year,
                    'publication_date' => self::parse_loose_date( $date ),
                    'source_type'      => $this->map_loc_type( $formats ),
                    'container_title'  => sanitize_text_field( $item['partof'][0] ?? '' ),
                    'publisher'        => sanitize_text_field( $item['shelf_id'] ?? '' ),
                    'url'              => $record_url,
                    'open_access_url'  => ! empty( $item['resources'][0]['url'] ) ? esc_url_raw( $item['resources'][0]['url'] ) : $record_url,
                    'preview_url'      => $image_url,
                    'full_text_status' => ! empty( $online_formats ) ? 'open-access' : '',
                    'record_url'       => $record_url,
                    'topics'           => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $item['subject'] ?? array() ) ) ) ),
                    'identifiers'      => array_filter(
                        array(
                            'loc'  => sanitize_text_field( basename( untrailingslashit( $record_url ) ) ),
                            'lccn' => sanitize_text_field( $item['number_lccn'][0] ?? '' ),
                        )
                    ),
                    'raw_type'         => implode( ', ', $formats ),
                )
            );
        }
        return $results;
    }

    private function search_openlibrary( $query, $limit ) {
        $params = array(
            'q'      => $query,
            'limit'  => $limit,
            'fields' => 'key,title,author_name,author_key,first_publish_year,publish_date,publisher,isbn,oclc,lccn,language,subject,edition_key,cover_i,ebook_access,public_scan_b,ia',
        );
        $data = $this->request_json( 'openlibrary', add_query_arg( $params, 'https://openlibrary.org/search.json' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['docs'] ?? array() ) as $item ) {
            $title = sanitize_text_field( $item['title'] ?? '' );
            if ( ! $title ) {
                continue;
            }
            $authors = array();
            foreach ( (array) ( $item['author_name'] ?? array() ) as $author_name ) {
                $authors[] = self::person_from_display_name( sanitize_text_field( $author_name ) );
            }
            $key = sanitize_text_field( $item['key'] ?? '' );
            $record_url = $key ? 'https://openlibrary.org' . $key : '';
            $isbn = self::preferred_isbn( $item['isbn'] ?? array() );
            $open_access = ! empty( $item['public_scan_b'] ) || in_array( $item['ebook_access'] ?? '', array( 'public', 'borrowable' ), true );
            $ia_id = self::first_string( $item['ia'] ?? array() );
            $open_url = $ia_id ? 'https://archive.org/details/' . rawurlencode( $ia_id ) : '';
            $cover = ! empty( $item['cover_i'] ) ? 'https://covers.openlibrary.org/b/id/' . absint( $item['cover_i'] ) . '-M.jpg' : '';
            $results[] = $this->normalized_result(
                'openlibrary',
                $key ?: md5( wp_json_encode( $item ) ),
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => '',
                    'year'             => sanitize_text_field( $item['first_publish_year'] ?? '' ),
                    'publication_date' => '',
                    'source_type'      => 'book',
                    'container_title'  => '',
                    'publisher'        => self::first_string( $item['publisher'] ?? array() ),
                    'isbn'             => $isbn,
                    'url'              => $record_url,
                    'open_access_url'  => $open_url,
                    'preview_url'      => $cover,
                    'full_text_status' => $open_access ? ( 'public' === ( $item['ebook_access'] ?? '' ) ? 'open-access' : 'library-holding' ) : '',
                    'record_url'       => $record_url,
                    'topics'           => array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $item['subject'] ?? array() ) ) ) ), 0, 12 ),
                    'identifiers'      => array_filter(
                        array(
                            'openlibrary' => $key,
                            'isbn'        => $isbn,
                            'oclc'        => self::first_string( $item['oclc'] ?? array() ),
                            'lccn'        => self::first_string( $item['lccn'] ?? array() ),
                        )
                    ),
                    'raw_type'         => 'book',
                )
            );
        }
        return $results;
    }

    private function search_googlebooks( $query, $limit ) {
        $settings = $this->settings();
        $params = array(
            'q'          => $query,
            'maxResults' => min( 40, $limit ),
            'printType'  => 'all',
            'key'        => $settings['google_books_api_key'],
        );
        $data = $this->request_json( 'googlebooks', add_query_arg( $params, 'https://www.googleapis.com/books/v1/volumes' ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = array();
        foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
            $volume = is_array( $item['volumeInfo'] ?? null ) ? $item['volumeInfo'] : array();
            $access = is_array( $item['accessInfo'] ?? null ) ? $item['accessInfo'] : array();
            $sale = is_array( $item['saleInfo'] ?? null ) ? $item['saleInfo'] : array();
            $title = sanitize_text_field( $volume['title'] ?? '' );
            if ( ! $title ) {
                continue;
            }
            if ( ! empty( $volume['subtitle'] ) ) {
                $title .= ': ' . sanitize_text_field( $volume['subtitle'] );
            }
            $authors = array();
            foreach ( (array) ( $volume['authors'] ?? array() ) as $author_name ) {
                $authors[] = self::person_from_display_name( sanitize_text_field( $author_name ) );
            }
            $identifiers = array();
            foreach ( (array) ( $volume['industryIdentifiers'] ?? array() ) as $identifier ) {
                $type = sanitize_key( $identifier['type'] ?? '' );
                if ( $type ) {
                    $identifiers[ $type ] = sanitize_text_field( $identifier['identifier'] ?? '' );
                }
            }
            $isbn = $identifiers['isbn_13'] ?? $identifiers['isbn_10'] ?? '';
            $published = sanitize_text_field( $volume['publishedDate'] ?? '' );
            $year = preg_match( '/\b(1[0-9]{3}|20[0-9]{2}|21[0-9]{2})\b/', $published, $match ) ? $match[1] : '';
            $viewability = sanitize_key( $access['viewability'] ?? '' );
            $full_text_status = '';
            if ( ! empty( $access['publicDomain'] ) || 'all_pages' === $viewability ) {
                $full_text_status = 'open-access';
            } elseif ( in_array( $viewability, array( 'partial', 'pages' ), true ) ) {
                $full_text_status = 'preview-only';
            }
            $record_url = esc_url_raw( $volume['infoLink'] ?? $volume['canonicalVolumeLink'] ?? '' );
            $results[] = $this->normalized_result(
                'googlebooks',
                sanitize_text_field( $item['id'] ?? md5( wp_json_encode( $item ) ) ),
                array(
                    'title'            => $title,
                    'authors'          => array_values( array_filter( $authors ) ),
                    'organization'     => '',
                    'year'             => $year,
                    'publication_date' => self::parse_loose_date( $published ),
                    'source_type'      => 'book',
                    'container_title'  => '',
                    'publisher'        => sanitize_text_field( $volume['publisher'] ?? '' ),
                    'pages'            => sanitize_text_field( $volume['pageCount'] ?? '' ),
                    'isbn'             => $isbn,
                    'url'              => $record_url,
                    'open_access_url'  => ! empty( $access['webReaderLink'] ) ? esc_url_raw( $access['webReaderLink'] ) : '',
                    'preview_url'      => esc_url_raw( $volume['previewLink'] ?? '' ),
                    'thumbnail_url'    => esc_url_raw( $volume['imageLinks']['thumbnail'] ?? '' ),
                    'full_text_status' => $full_text_status,
                    'record_url'       => $record_url,
                    'abstract'         => self::clean_abstract( $volume['description'] ?? '' ),
                    'language'         => sanitize_text_field( $volume['language'] ?? '' ),
                    'topics'           => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $volume['categories'] ?? array() ) ) ) ),
                    'identifiers'      => array_filter(
                        array(
                            'google_books' => sanitize_text_field( $item['id'] ?? '' ),
                            'isbn'         => $isbn,
                        )
                    ),
                    'raw_type'         => sanitize_text_field( $volume['printType'] ?? 'BOOK' ),
                )
            );
        }
        return $results;
    }

    private function normalized_result( $provider_id, $provider_record_id, $fields ) {
        $defaults = array(
            'title'            => '',
            'authors'          => array(),
            'organization'     => '',
            'editors'          => array(),
            'year'             => '',
            'publication_date' => '',
            'source_type'      => 'report',
            'container_title'  => '',
            'publisher'        => '',
            'place'            => '',
            'edition'          => '',
            'volume'           => '',
            'issue'            => '',
            'pages'            => '',
            'chapter'          => '',
            'report_number'    => '',
            'standard_number'  => '',
            'jurisdiction'     => '',
            'doi'              => '',
            'isbn'             => '',
            'pmid'             => '',
            'url'              => '',
            'archive_url'      => '',
            'open_access_url'  => '',
            'preview_url'      => '',
            'thumbnail_url'    => '',
            'language'         => '',
            'abstract'         => '',
            'full_text_status' => '',
            'record_url'       => '',
            'cited_by_count'   => 0,
            'topics'           => array(),
            'identifiers'      => array(),
            'raw_type'         => '',
        );
        $fields = wp_parse_args( $fields, $defaults );
        $fields['schema'] = self::RESULT_SCHEMA;
        $fields['provider'] = sanitize_key( $provider_id );
        $fields['provider_name'] = self::$provider_labels[ $provider_id ] ?? $provider_id;
        $fields['provider_record_id'] = sanitize_text_field( $provider_record_id );
        $fields['title'] = sanitize_text_field( $fields['title'] );
        $fields['authors'] = array_values( array_filter( (array) $fields['authors'] ) );
        $fields['editors'] = array_values( array_filter( (array) $fields['editors'] ) );
        $fields['organization'] = sanitize_text_field( $fields['organization'] );
        $fields['year'] = sanitize_text_field( $fields['year'] );
        $fields['publication_date'] = self::valid_iso_date( $fields['publication_date'] ) ? $fields['publication_date'] : '';
        $fields['source_type'] = sanitize_title( $fields['source_type'] ?: 'report' );
        foreach ( array( 'container_title', 'publisher', 'place', 'edition', 'volume', 'issue', 'pages', 'chapter', 'report_number', 'standard_number', 'jurisdiction', 'language', 'raw_type' ) as $field ) {
            $fields[ $field ] = sanitize_text_field( $fields[ $field ] );
        }
        $fields['doi'] = self::normalize_doi( $fields['doi'] );
        $fields['isbn'] = self::normalize_isbn( $fields['isbn'] );
        $fields['pmid'] = sanitize_text_field( $fields['pmid'] );
        foreach ( array( 'url', 'archive_url', 'open_access_url', 'preview_url', 'thumbnail_url', 'record_url' ) as $field ) {
            $fields[ $field ] = esc_url_raw( $fields[ $field ] );
        }
        $fields['abstract'] = self::clean_abstract( $fields['abstract'] );
        $fields['full_text_status'] = sanitize_key( $fields['full_text_status'] );
        $fields['cited_by_count'] = absint( $fields['cited_by_count'] );
        $fields['topics'] = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $fields['topics'] ) ) ) ), 0, 25 );
        $fields['identifiers'] = array_filter(
            array_map(
                'sanitize_text_field',
                is_array( $fields['identifiers'] ) ? $fields['identifiers'] : array()
            )
        );
        $fields['existing_source_ids'] = current_user_can( 'edit_posts' ) ? $this->existing_source_ids( $fields ) : array();
        $fields['discovery_links'] = $this->discovery_handoffs( $fields );
        return $fields;
    }

    private function seal_results( $results, $provider, $query ) {
        $sealed = array();
        foreach ( (array) $results as $result ) {
            $token = wp_generate_password( 48, false, false );
            $key = self::CACHE_PREFIX . 'result_' . hash( 'sha256', $token );
            set_transient(
                $key,
                array(
                    'user_id'  => get_current_user_id(),
                    'provider' => $provider,
                    'query'    => $query,
                    'result'   => $result,
                    'created'  => time(),
                ),
                HOUR_IN_SECONDS
            );
            $result['import_token'] = $token;
            $sealed[] = $result;
        }
        return $sealed;
    }

    private function read_sealed_result( $token ) {
        $token = sanitize_text_field( $token );
        if ( strlen( $token ) < 20 ) {
            return new WP_Error( 'invalid_import_token', __( 'The discovery import token is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $key = self::CACHE_PREFIX . 'result_' . hash( 'sha256', $token );
        $sealed = get_transient( $key );
        if ( ! is_array( $sealed ) || empty( $sealed['result'] ) ) {
            return new WP_Error( 'expired_import_token', __( 'The discovery result expired. Run the search again.', 'sustainable-catalyst-library' ), array( 'status' => 410 ) );
        }
        if ( absint( $sealed['user_id'] ) !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'import_token_owner', __( 'The discovery result belongs to another user.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        delete_transient( $key );
        return $sealed;
    }

    public function ajax_import_result() {
        $this->verify_ajax( 'edit_posts' );
        $rate = $this->enforce_rate_limit( 'import', 30, HOUR_IN_SECONDS );
        if ( is_wp_error( $rate ) ) {
            wp_send_json_error( array( 'message' => $rate->get_error_message() ), 429 );
        }

        $idempotency_key = sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) );
        $replay = SC_Library_Connector_Holdings_Reliability::idempotency_lookup( $idempotency_key );
        if ( $replay ) {
            $replay['idempotent_replay'] = true;
            wp_send_json_success( $replay );
        }

        $sealed = $this->read_sealed_result( wp_unslash( $_POST['token'] ?? '' ) );
        if ( is_wp_error( $sealed ) ) {
            wp_send_json_error( array( 'message' => $sealed->get_error_message() ), absint( $sealed->get_error_data( 'status' ) ?: 400 ) );
        }

        $source_id = absint( wp_unslash( $_POST['source_id'] ?? 0 ) );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'fill_empty' ) );
        if ( ! in_array( $mode, array( 'fill_empty', 'overwrite' ), true ) ) {
            $mode = 'fill_empty';
        }

        $imported = $this->import_result( $sealed['result'], $source_id, $project_id, $mode );
        if ( is_wp_error( $imported ) ) {
            wp_send_json_error( array( 'message' => $imported->get_error_message() ), absint( $imported->get_error_data( 'status' ) ?: 400 ) );
        }

        SC_Library_Connector_Holdings_Reliability::idempotency_store( $idempotency_key, $imported );
        wp_send_json_success( $imported );
    }

    private function import_result( $result, $source_id = 0, $project_id = 0, $mode = 'fill_empty' ) {
        if ( ! is_array( $result ) || empty( $result['title'] ) || empty( $result['provider'] ) ) {
            return new WP_Error( 'invalid_discovery_result', __( 'The discovery result is incomplete.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $idempotent_match = false;
        if ( ! $source_id ) {
            $existing_import = SC_Library_Connector_Holdings_Reliability::find_imported_source( $result );
            if ( $existing_import ) {
                $source_id = $existing_import;
                $idempotent_match = true;
            }
        }

        $creating = ! $source_id;
        if ( $source_id ) {
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) || ! current_user_can( 'edit_post', $source_id ) ) {
                return new WP_Error( 'source_not_editable', __( 'The selected Source record cannot be updated.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
            }
        } else {
            $source_id = wp_insert_post(
                array(
                    'post_type'    => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                    'post_status'  => 'draft',
                    'post_title'   => sanitize_text_field( $result['title'] ),
                    'post_excerpt' => sanitize_textarea_field( $result['abstract'] ?? '' ),
                ),
                true
            );
            if ( is_wp_error( $source_id ) ) {
                return $source_id;
            }
        }

        $critical_map = array(
            'authors'            => SC_Library_Citation_Source_Manager::META_AUTHORS,
            'organization'       => SC_Library_Citation_Source_Manager::META_ORGANIZATION,
            'editors'            => SC_Library_Citation_Source_Manager::META_EDITORS,
            'year'               => SC_Library_Citation_Source_Manager::META_YEAR,
            'publication_date'   => SC_Library_Citation_Source_Manager::META_PUBLICATION_DATE,
            'container_title'    => SC_Library_Citation_Source_Manager::META_CONTAINER_TITLE,
            'publisher'          => SC_Library_Citation_Source_Manager::META_PUBLISHER,
            'place'              => SC_Library_Citation_Source_Manager::META_PLACE,
            'edition'            => SC_Library_Citation_Source_Manager::META_EDITION,
            'volume'             => SC_Library_Citation_Source_Manager::META_VOLUME,
            'issue'              => SC_Library_Citation_Source_Manager::META_ISSUE,
            'pages'              => SC_Library_Citation_Source_Manager::META_PAGES,
            'chapter'            => SC_Library_Citation_Source_Manager::META_CHAPTER,
            'report_number'      => SC_Library_Citation_Source_Manager::META_REPORT_NUMBER,
            'standard_number'    => SC_Library_Citation_Source_Manager::META_STANDARD_NUMBER,
            'jurisdiction'       => SC_Library_Citation_Source_Manager::META_JURISDICTION,
            'doi'                => SC_Library_Citation_Source_Manager::META_DOI,
            'isbn'               => SC_Library_Citation_Source_Manager::META_ISBN,
            'pmid'               => SC_Library_Citation_Source_Manager::META_PMID,
            'url'                => SC_Library_Citation_Source_Manager::META_URL,
            'archive_url'        => SC_Library_Citation_Source_Manager::META_ARCHIVE_URL,
            'language'           => SC_Library_Citation_Source_Manager::META_LANGUAGE,
            'full_text_status'   => SC_Library_Citation_Source_Manager::META_FULL_TEXT_STATUS,
        );

        $provenance = array();
        $changed = array();
        foreach ( $critical_map as $field => $meta_key ) {
            if ( ! array_key_exists( $field, $result ) || in_array( $result[ $field ], array( '', array(), null ), true ) ) {
                continue;
            }
            $existing = get_post_meta( $source_id, $meta_key, true );
            if ( 'fill_empty' === $mode && ! in_array( $existing, array( '', array(), null ), true ) ) {
                SC_Library_Connector_Holdings_Reliability::record_conflict(
                    $source_id,
                    $field,
                    $existing,
                    $result[ $field ],
                    $result['provider'],
                    $result['provider_record_id'] ?? ''
                );
                continue;
            }
            update_post_meta( $source_id, $meta_key, $result[ $field ] );
            $changed[] = $field;
            $provenance[ $field ] = array(
                'provider'           => sanitize_key( $result['provider'] ),
                'provider_record_id' => sanitize_text_field( $result['provider_record_id'] ?? '' ),
                'imported_at'        => current_time( 'mysql' ),
                'imported_by'        => get_current_user_id(),
            );
        }

        if ( ! $creating && 'fill_empty' === $mode ) {
            SC_Library_Connector_Holdings_Reliability::record_conflict(
                $source_id,
                'title',
                get_the_title( $source_id ),
                $result['title'],
                $result['provider'],
                $result['provider_record_id'] ?? ''
            );
            if ( ! empty( $result['abstract'] ) ) {
                SC_Library_Connector_Holdings_Reliability::record_conflict(
                    $source_id,
                    'abstract',
                    get_post_field( 'post_excerpt', $source_id ),
                    $result['abstract'],
                    $result['provider'],
                    $result['provider_record_id'] ?? ''
                );
            }
        }

        if ( $creating || 'overwrite' === $mode ) {
            wp_update_post(
                wp_slash(
                    array(
                        'ID'           => $source_id,
                        'post_title'   => sanitize_text_field( $result['title'] ),
                        'post_excerpt' => sanitize_textarea_field( $result['abstract'] ?? '' ),
                    )
                )
            );
        } elseif ( ! get_post_field( 'post_excerpt', $source_id ) && ! empty( $result['abstract'] ) ) {
            wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_excerpt' => sanitize_textarea_field( $result['abstract'] ) ) ) );
        }

        $type = sanitize_title( $result['source_type'] ?? 'report' );
        $existing_types = wp_get_object_terms( $source_id, SC_Library_Citation_Source_Manager::SOURCE_TYPE_TAXONOMY, array( 'fields' => 'slugs' ) );
        $existing_types = is_wp_error( $existing_types ) ? array() : (array) $existing_types;
        $type_changed = false;
        if (
            term_exists( $type, SC_Library_Citation_Source_Manager::SOURCE_TYPE_TAXONOMY )
            && ( 'overwrite' === $mode || ! $existing_types )
        ) {
            wp_set_object_terms( $source_id, array( $type ), SC_Library_Citation_Source_Manager::SOURCE_TYPE_TAXONOMY, false );
            $type_changed = ! in_array( $type, $existing_types, true );
            $provenance['source_type'] = array(
                'provider'           => sanitize_key( $result['provider'] ),
                'provider_record_id' => sanitize_text_field( $result['provider_record_id'] ?? '' ),
                'imported_at'        => current_time( 'mysql' ),
            );
        }
        if ( ! empty( $result['topics'] ) ) {
            wp_set_object_terms( $source_id, array_slice( array_map( 'sanitize_text_field', $result['topics'] ), 0, 25 ), SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY, true );
        }

        $existing_provenance = get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROVENANCE, true );
        $existing_provenance = is_string( $existing_provenance ) ? json_decode( $existing_provenance, true ) : $existing_provenance;
        $existing_provenance = is_array( $existing_provenance ) ? $existing_provenance : array();
        update_post_meta(
            $source_id,
            SC_Library_Citation_Source_Manager::META_PROVENANCE,
            wp_json_encode( array_merge( $existing_provenance, $provenance ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );

        if ( $creating || $changed || $type_changed ) {
            update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, '0' );
        }

        $connector_ids = get_post_meta( $source_id, self::META_CONNECTOR_IDS, true );
        $connector_ids = is_array( $connector_ids ) ? $connector_ids : array();
        $connector_ids[ sanitize_key( $result['provider'] ) ] = sanitize_text_field( $result['provider_record_id'] ?? '' );
        update_post_meta( $source_id, self::META_CONNECTOR_IDS, $connector_ids );

        $history = get_post_meta( $source_id, self::META_DISCOVERY_HISTORY, true );
        $history = is_array( $history ) ? $history : array();
        $history[] = array(
            'event'              => $creating ? 'created' : 'updated',
            'provider'           => sanitize_key( $result['provider'] ),
            'provider_record_id' => sanitize_text_field( $result['provider_record_id'] ?? '' ),
            'mode'               => $mode,
            'fields'             => $changed,
            'time'               => current_time( 'mysql' ),
            'user'               => get_current_user_id(),
        );
        update_post_meta( $source_id, self::META_DISCOVERY_HISTORY, array_slice( $history, -self::HISTORY_LIMIT ) );
        update_post_meta( $source_id, self::META_CONNECTOR_LAST_CHECKED, current_time( 'mysql' ) );
        update_post_meta( $source_id, self::META_IMPORT_FINGERPRINT, hash( 'sha256', sanitize_key( $result['provider'] ) . '|' . sanitize_text_field( $result['provider_record_id'] ?? '' ) ) );

        $locations = $this->locations_from_result( $result );
        if ( $locations ) {
            $this->store_access_locations( $source_id, $locations );
        }

        if ( $project_id && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id ) && current_user_can( 'edit_post', $project_id ) ) {
            $projects = get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true );
            $projects = array_values( array_unique( array_filter( array_map( 'absint', (array) $projects ) ) ) );
            $projects[] = $project_id;
            update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, array_values( array_unique( $projects ) ) );

            $source_ids = get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true );
            $source_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $source_ids ) ) ) );
            $source_ids[] = $source_id;
            update_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, array_values( array_unique( $source_ids ) ) );

            if ( class_exists( 'SC_Library_Connected_Research_Environment' ) ) {
                SC_Library_Connected_Research_Environment::attach_source_to_project( $project_id, $source_id, 'candidate', 'background' );
            }
        }

        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
        SC_Library_Citation_Source_Reliability::recalculate_reliability( $source_id );

        return array(
            'source_id'      => $source_id,
            'created'        => $creating,
            'edit_url'       => get_edit_post_link( $source_id, 'raw' ),
            'title'          => get_the_title( $source_id ),
            'citation'       => SC_Library_Citation_Source_Manager::format_citation( $source_id, 'harvard', 'reference' ),
            'fields_changed' => $changed,
            'reused_existing_import' => $idempotent_match,
            'open_conflict_count' => count( SC_Library_Connector_Holdings_Reliability::open_conflicts( $source_id ) ),
            'message'        => $creating
                ? __( 'Source imported as a draft for metadata review.', 'sustainable-catalyst-library' )
                : __( 'Source metadata updated and returned to review.', 'sustainable-catalyst-library' ),
        );
    }

    private function existing_source_ids( $result ) {
        $meta_query = array( 'relation' => 'OR' );
        $doi = self::normalize_doi( $result['doi'] ?? '' );
        $isbn = self::normalize_isbn( $result['isbn'] ?? '' );
        $pmid = sanitize_text_field( $result['pmid'] ?? '' );

        if ( $doi ) {
            $meta_query[] = array( 'key' => SC_Library_Citation_Source_Manager::META_NORMALIZED_DOI, 'value' => $doi, 'compare' => '=' );
            $meta_query[] = array( 'key' => SC_Library_Citation_Source_Manager::META_DOI, 'value' => $doi, 'compare' => '=' );
        }
        if ( $isbn ) {
            $meta_query[] = array( 'key' => SC_Library_Citation_Source_Manager::META_NORMALIZED_ISBN, 'value' => $isbn, 'compare' => '=' );
            $meta_query[] = array( 'key' => SC_Library_Citation_Source_Manager::META_ISBN, 'value' => $isbn, 'compare' => '=' );
        }
        if ( $pmid ) {
            $meta_query[] = array( 'key' => SC_Library_Citation_Source_Manager::META_PMID, 'value' => $pmid, 'compare' => '=' );
        }
        if ( count( $meta_query ) === 1 ) {
            return array();
        }

        return array_values(
            array_map(
                'absint',
                get_posts(
                    array(
                        'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                        'posts_per_page' => 20,
                        'fields'         => 'ids',
                        'meta_query'     => $meta_query,
                    )
                )
            )
        );
    }

    private function locations_from_result( $result ) {
        $locations = array();
        foreach ( array(
            'open_access_url' => 'open-access',
            'preview_url'     => 'preview',
            'record_url'      => 'provider-record',
            'url'             => 'canonical',
        ) as $field => $kind ) {
            if ( empty( $result[ $field ] ) ) {
                continue;
            }
            $locations[] = SC_Library_Connector_Holdings_Reliability::normalize_location(
                array(
                    'kind'       => $kind,
                    'provider'   => sanitize_key( $result['provider'] ?? '' ),
                    'label'      => self::$provider_labels[ $result['provider'] ?? '' ] ?? sanitize_text_field( $result['provider'] ?? '' ),
                    'url'        => esc_url_raw( $result[ $field ] ),
                    'status'     => sanitize_key( $result['full_text_status'] ?? '' ),
                    'checked_at' => current_time( 'mysql' ),
                )
            );
        }
        return $locations;
    }

    private function store_access_locations( $source_id, $locations ) {
        $existing = get_post_meta( $source_id, self::META_ACCESS_LOCATIONS, true );
        $existing = is_array( $existing ) ? $existing : array();
        $merged = SC_Library_Connector_Holdings_Reliability::merge_locations( $existing, $locations, self::LOCATION_LIMIT );
        update_post_meta( $source_id, self::META_ACCESS_LOCATIONS, $merged );
        SC_Library_Connector_Holdings_Reliability::holdings_summary( $source_id, true );
    }

    public function ajax_locate_source() {
        $this->verify_ajax( 'edit_posts' );
        $source_id = absint( wp_unslash( $_POST['source_id'] ?? 0 ) );
        if ( ! $source_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) || ! current_user_can( 'edit_post', $source_id ) ) {
            wp_send_json_error( array( 'message' => __( 'The Source record cannot be located.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $rate = $this->enforce_rate_limit( 'locate', 40, HOUR_IN_SECONDS );
        if ( is_wp_error( $rate ) ) {
            wp_send_json_error( array( 'message' => $rate->get_error_message() ), 429 );
        }

        $result = $this->locate_source( $source_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 500 ) );
        }
        wp_send_json_success( $result );
    }

    public function locate_source( $source_id, $force = false ) {
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, true );
        if ( ! $data ) {
            return new WP_Error( 'source_not_found', __( 'Source record not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }

        $cache_key = self::CACHE_PREFIX . 'locate_' . $source_id . '_' . md5( (string) get_post_modified_time( 'c', true, $source_id ) );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $providers = $this->providers();
        $locations = array();
        $warnings = array();

        if ( ! empty( $providers['unpaywall']['enabled'] ) && ! empty( $providers['unpaywall']['available'] ) && ! empty( $data['doi'] ) ) {
            $unpaywall = $this->lookup_unpaywall( $data['doi'] );
            if ( is_wp_error( $unpaywall ) ) {
                $warnings[] = array( 'provider' => 'unpaywall', 'message' => $unpaywall->get_error_message() );
            } elseif ( $unpaywall ) {
                $locations = array_merge( $locations, $unpaywall );
            }
        }

        if ( ! empty( $providers['openalex']['enabled'] ) && ! empty( $providers['openalex']['available'] ) && ! empty( $data['doi'] ) ) {
            $openalex = $this->lookup_openalex_doi( $data['doi'] );
            if ( is_wp_error( $openalex ) ) {
                $warnings[] = array( 'provider' => 'openalex', 'message' => $openalex->get_error_message() );
            } elseif ( $openalex ) {
                $locations = array_merge( $locations, $openalex );
            }
        }

        if ( ! empty( $data['pmid'] ) ) {
            $locations[] = array(
                'kind'       => 'biomedical-record',
                'provider'   => 'pubmed',
                'label'      => 'PubMed',
                'url'        => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode( $data['pmid'] ) . '/',
                'status'     => '',
                'checked_at' => current_time( 'mysql' ),
            );
        }

        if ( ! empty( $data['isbn'] ) ) {
            if ( ! empty( $providers['openlibrary']['enabled'] ) && ! empty( $providers['openlibrary']['available'] ) ) {
                $book = $this->lookup_openlibrary_isbn( $data['isbn'] );
                if ( is_wp_error( $book ) ) {
                    $warnings[] = array( 'provider' => 'openlibrary', 'message' => $book->get_error_message() );
                } elseif ( $book ) {
                    $locations = array_merge( $locations, $book );
                }
            }
            if ( ! empty( $providers['googlebooks']['enabled'] ) && ! empty( $providers['googlebooks']['available'] ) ) {
                $book = $this->lookup_googlebooks_isbn( $data['isbn'] );
                if ( is_wp_error( $book ) ) {
                    $warnings[] = array( 'provider' => 'googlebooks', 'message' => $book->get_error_message() );
                } elseif ( $book ) {
                    $locations = array_merge( $locations, $book );
                }
            }
        }

        foreach ( $this->discovery_handoffs( $data ) as $handoff ) {
            $locations[] = array(
                'kind'       => sanitize_key( $handoff['kind'] ?? 'search-handoff' ),
                'provider'   => sanitize_key( $handoff['provider'] ?? '' ),
                'label'      => sanitize_text_field( $handoff['label'] ),
                'url'        => esc_url_raw( $handoff['url'] ),
                'status'     => 'discovery',
                'checked_at' => current_time( 'mysql' ),
            );
        }

        foreach ( $this->library_actions( $data, true ) as $action ) {
            $locations[] = $action;
        }

        $locations = self::unique_locations( $locations );
        $this->store_access_locations( $source_id, $locations );
        update_post_meta( $source_id, self::META_CONNECTOR_LAST_CHECKED, current_time( 'mysql' ) );

        $payload = array(
            'schema'       => self::LOCATOR_SCHEMA,
            'source_id'    => $source_id,
            'title'        => $data['title'],
            'locations'    => $locations,
            'warnings'     => $warnings,
            'checked_at'   => current_time( 'mysql' ),
            'cached'       => false,
        );
        set_transient( $cache_key, $payload, $this->settings()['cache_ttl'] );
        return $payload;
    }

    private function lookup_unpaywall( $doi ) {
        $settings = $this->settings();
        if ( ! $settings['contact_email'] ) {
            return new WP_Error( 'unpaywall_email_required', __( 'Unpaywall requires a contact email.', 'sustainable-catalyst-library' ) );
        }
        $doi = self::normalize_doi( $doi );
        if ( ! $doi ) {
            return array();
        }
        $url = add_query_arg(
            array( 'email' => $settings['contact_email'] ),
            'https://api.unpaywall.org/v2/' . $doi
        );
        $data = $this->request_json( 'unpaywall', $url );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $locations = array();
        $best = is_array( $data['best_oa_location'] ?? null ) ? $data['best_oa_location'] : array();
        if ( $best ) {
            $best_url = esc_url_raw( $best['url_for_pdf'] ?? $best['url_for_landing_page'] ?? $best['url'] ?? '' );
            if ( $best_url ) {
                $locations[] = array(
                    'kind'       => 'open-access',
                    'provider'   => 'unpaywall',
                    'label'      => __( 'Best open-access copy', 'sustainable-catalyst-library' ),
                    'url'        => $best_url,
                    'status'     => 'open-access',
                    'license'    => sanitize_text_field( $best['license'] ?? '' ),
                    'version'    => sanitize_text_field( $best['version'] ?? '' ),
                    'host_type'  => sanitize_text_field( $best['host_type'] ?? '' ),
                    'checked_at' => current_time( 'mysql' ),
                );
            }
        }
        foreach ( (array) ( $data['oa_locations'] ?? array() ) as $location ) {
            $location_url = esc_url_raw( $location['url_for_pdf'] ?? $location['url_for_landing_page'] ?? $location['url'] ?? '' );
            if ( ! $location_url ) {
                continue;
            }
            $locations[] = array(
                'kind'       => 'open-access',
                'provider'   => 'unpaywall',
                'label'      => sanitize_text_field( $location['repository_institution'] ?? $location['endpoint_id'] ?? __( 'Open-access location', 'sustainable-catalyst-library' ) ),
                'url'        => $location_url,
                'status'     => 'open-access',
                'license'    => sanitize_text_field( $location['license'] ?? '' ),
                'version'    => sanitize_text_field( $location['version'] ?? '' ),
                'host_type'  => sanitize_text_field( $location['host_type'] ?? '' ),
                'checked_at' => current_time( 'mysql' ),
            );
        }
        return self::unique_locations( $locations );
    }

    private function lookup_openalex_doi( $doi ) {
        $settings = $this->settings();
        $doi = self::normalize_doi( $doi );
        if ( ! $doi || ! $settings['openalex_api_key'] ) {
            return array();
        }
        $url = add_query_arg(
            array( 'api_key' => $settings['openalex_api_key'] ),
            'https://api.openalex.org/works/https://doi.org/' . $doi
        );
        $data = $this->request_json( 'openalex', $url );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $locations = array();
        foreach ( array( $data['best_oa_location'] ?? array(), $data['primary_location'] ?? array() ) as $location ) {
            if ( ! is_array( $location ) ) {
                continue;
            }
            $location_url = esc_url_raw( $location['pdf_url'] ?? $location['landing_page_url'] ?? '' );
            if ( ! $location_url ) {
                continue;
            }
            $locations[] = array(
                'kind'       => ! empty( $location['is_oa'] ) ? 'open-access' : 'publisher',
                'provider'   => 'openalex',
                'label'      => sanitize_text_field( $location['source']['display_name'] ?? 'OpenAlex location' ),
                'url'        => $location_url,
                'status'     => ! empty( $location['is_oa'] ) ? 'open-access' : '',
                'license'    => sanitize_text_field( $location['license'] ?? '' ),
                'version'    => sanitize_text_field( $location['version'] ?? '' ),
                'checked_at' => current_time( 'mysql' ),
            );
        }
        return self::unique_locations( $locations );
    }

    private function lookup_openlibrary_isbn( $isbn ) {
        $isbn = self::normalize_isbn( $isbn );
        if ( ! $isbn ) {
            return array();
        }
        $url = add_query_arg(
            array(
                'bibkeys' => 'ISBN:' . $isbn,
                'format'  => 'json',
                'jscmd'   => 'data',
            ),
            'https://openlibrary.org/api/books'
        );
        $data = $this->request_json( 'openlibrary', $url );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        $book = is_array( $data[ 'ISBN:' . $isbn ] ?? null ) ? $data[ 'ISBN:' . $isbn ] : array();
        if ( ! $book ) {
            return array();
        }
        $locations = array();
        if ( ! empty( $book['url'] ) ) {
            $locations[] = array(
                'kind'       => 'book-record',
                'provider'   => 'openlibrary',
                'label'      => 'Open Library',
                'url'        => esc_url_raw( $book['url'] ),
                'status'     => 'discovery',
                'checked_at' => current_time( 'mysql' ),
            );
        }
        if ( ! empty( $book['ebooks'][0]['preview_url'] ) ) {
            $locations[] = array(
                'kind'       => 'preview',
                'provider'   => 'openlibrary',
                'label'      => __( 'Open Library preview or reading access', 'sustainable-catalyst-library' ),
                'url'        => esc_url_raw( $book['ebooks'][0]['preview_url'] ),
                'status'     => 'preview-only',
                'checked_at' => current_time( 'mysql' ),
            );
        }
        return $locations;
    }

    private function lookup_googlebooks_isbn( $isbn ) {
        $results = $this->search_googlebooks( 'isbn:' . self::normalize_isbn( $isbn ), 3 );
        if ( is_wp_error( $results ) ) {
            return $results;
        }
        $locations = array();
        foreach ( $results as $result ) {
            $locations = array_merge( $locations, $this->locations_from_result( $result ) );
        }
        return self::unique_locations( $locations );
    }

    public function discovery_handoffs( $data ) {
        $providers = $this->providers();
        $title = sanitize_text_field( $data['title'] ?? '' );
        $doi = self::normalize_doi( $data['doi'] ?? '' );
        $isbn = self::normalize_isbn( $data['isbn'] ?? '' );
        $pmid = sanitize_text_field( $data['pmid'] ?? '' );
        $creator = '';
        if ( ! empty( $data['authors'][0]['family'] ) ) {
            $creator = sanitize_text_field( $data['authors'][0]['family'] );
        } elseif ( ! empty( $data['organization'] ) ) {
            $creator = sanitize_text_field( $data['organization'] );
        }

        $scholar_query = trim( '"' . $title . '"' . ( $creator ? ' ' . $creator : '' ) . ( $doi ? ' ' . $doi : '' ) );
        $worldcat_query = $isbn ? 'bn:' . $isbn : ( $doi ? 'kw:' . $doi : $title . ( $creator ? ' ' . $creator : '' ) );
        $links = array();
        if ( ! empty( $providers['google_scholar']['enabled'] ) ) {
            $links[] = array(
                'provider' => 'google_scholar',
                'kind'     => 'scholarly-search',
                'label'    => __( 'Search Google Scholar', 'sustainable-catalyst-library' ),
                'url'      => 'https://scholar.google.com/scholar?q=' . rawurlencode( $scholar_query ),
            );
        }
        if ( ! empty( $providers['worldcat']['enabled'] ) ) {
            $links[] = array(
                'provider' => 'worldcat',
                'kind'     => 'library-search',
                'label'    => __( 'Search WorldCat', 'sustainable-catalyst-library' ),
                'url'      => 'https://search.worldcat.org/search?q=' . rawurlencode( $worldcat_query ),
            );
        }
        if ( $doi ) {
            $links[] = array(
                'provider' => 'doi',
                'kind'     => 'canonical',
                'label'    => __( 'Open DOI', 'sustainable-catalyst-library' ),
                'url'      => 'https://doi.org/' . $doi,
            );
        }
        if ( $pmid ) {
            $links[] = array(
                'provider' => 'pubmed',
                'kind'     => 'biomedical-record',
                'label'    => __( 'Open PubMed', 'sustainable-catalyst-library' ),
                'url'      => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode( $pmid ) . '/',
            );
        }
        return apply_filters( 'sc_library_discovery_handoffs', $links, $data );
    }

    private function library_actions( $data, $public_only = false ) {
        $actions = array();
        foreach ( $this->library_profiles( $public_only ) as $profile ) {
            $tokens = $this->profile_tokens( $data );
            if ( $profile['catalog_template'] ) {
                $actions[] = array(
                    'kind'       => 'library-catalog',
                    'provider'   => 'library:' . $profile['id'],
                    'label'      => sprintf( __( 'Search %s catalog', 'sustainable-catalyst-library' ), $profile['name'] ),
                    'url'        => $this->replace_profile_tokens( $profile['catalog_template'], $tokens ),
                    'status'     => 'library-search',
                    'checked_at' => current_time( 'mysql' ),
                );
            }
            if ( $profile['openurl_base'] ) {
                $actions[] = array(
                    'kind'       => 'openurl',
                    'provider'   => 'library:' . $profile['id'],
                    'label'      => sprintf( __( 'Check %s holdings', 'sustainable-catalyst-library' ), $profile['name'] ),
                    'url'        => $this->build_openurl( $profile['openurl_base'], $data ),
                    'status'     => 'holdings-check',
                    'checked_at' => current_time( 'mysql' ),
                );
            }
            if ( $profile['proxy_prefix'] && ! empty( $data['url'] ) ) {
                $actions[] = array(
                    'kind'       => 'library-proxy',
                    'provider'   => 'library:' . $profile['id'],
                    'label'      => sprintf( __( 'Open through %s proxy', 'sustainable-catalyst-library' ), $profile['name'] ),
                    'url'        => esc_url_raw( $profile['proxy_prefix'] . rawurlencode( $data['url'] ) ),
                    'status'     => 'subscription-access',
                    'checked_at' => current_time( 'mysql' ),
                );
            }
            if ( $profile['ill_url'] ) {
                $actions[] = array(
                    'kind'       => 'interlibrary-loan',
                    'provider'   => 'library:' . $profile['id'],
                    'label'      => sprintf( __( 'Request through %s', 'sustainable-catalyst-library' ), $profile['name'] ),
                    'url'        => $profile['ill_url'],
                    'status'     => 'interlibrary-loan',
                    'checked_at' => current_time( 'mysql' ),
                );
            }
        }
        return $actions;
    }

    private function profile_tokens( $data ) {
        $author = '';
        if ( ! empty( $data['authors'][0]['family'] ) ) {
            $author = $data['authors'][0]['family'];
        } elseif ( ! empty( $data['organization'] ) ) {
            $author = $data['organization'];
        }
        return array(
            '{query}'  => rawurlencode( trim( ( $data['title'] ?? '' ) . ' ' . $author ) ),
            '{title}'  => rawurlencode( $data['title'] ?? '' ),
            '{author}' => rawurlencode( $author ),
            '{doi}'    => rawurlencode( $data['doi'] ?? '' ),
            '{isbn}'   => rawurlencode( $data['isbn'] ?? '' ),
            '{pmid}'   => rawurlencode( $data['pmid'] ?? '' ),
        );
    }

    private function replace_profile_tokens( $template, $tokens ) {
        return esc_url_raw( strtr( $template, $tokens ) );
    }

    private function build_openurl( $base, $data ) {
        $params = array(
            'url_ver'   => 'Z39.88-2004',
            'ctx_ver'   => 'Z39.88-2004',
            'rft_val_fmt'=> 'info:ofi/fmt:kev:mtx:journal',
            'rft.title' => $data['title'] ?? '',
            'rft.date'  => $data['year'] ?? '',
            'rft.jtitle'=> $data['container_title'] ?? '',
            'rft.volume'=> $data['volume'] ?? '',
            'rft.issue' => $data['issue'] ?? '',
            'rft.pages' => $data['pages'] ?? '',
            'rft.doi'   => $data['doi'] ?? '',
            'rft.isbn'  => $data['isbn'] ?? '',
        );
        if ( ! empty( $data['authors'][0]['family'] ) ) {
            $params['rft.aulast'] = $data['authors'][0]['family'];
            $params['rft.aufirst'] = $data['authors'][0]['given'] ?? '';
        }
        return add_query_arg( array_filter( $params, static function ( $value ) { return '' !== (string) $value; } ), $base );
    }

    private function library_profiles( $public_only = false ) {
        $posts = get_posts(
            array(
                'post_type'      => self::PROFILE_POST_TYPE,
                'post_status'    => $public_only ? 'publish' : array( 'publish', 'draft', 'private' ),
                'posts_per_page' => 100,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_key'       => self::META_PROFILE_ENABLED,
                'meta_value'     => '1',
            )
        );
        $profiles = array();
        foreach ( $posts as $post ) {
            if ( $public_only ) {
                $validation = SC_Library_Connector_Holdings_Reliability::validate_library_profile( $post->ID, false );
                if ( empty( $validation['valid'] ) ) {
                    continue;
                }
            }
            $profiles[] = array(
                'schema'           => self::PROFILE_SCHEMA,
                'id'               => $post->ID,
                'name'             => $post->post_title,
                'region'           => (string) get_post_meta( $post->ID, self::META_PROFILE_REGION, true ),
                'homepage'         => (string) get_post_meta( $post->ID, self::META_PROFILE_HOMEPAGE, true ),
                'catalog_template' => (string) get_post_meta( $post->ID, self::META_PROFILE_CATALOG_TEMPLATE, true ),
                'openurl_base'     => (string) get_post_meta( $post->ID, self::META_PROFILE_OPENURL_BASE, true ),
                'ill_url'          => (string) get_post_meta( $post->ID, self::META_PROFILE_ILL_URL, true ),
                'proxy_prefix'     => (string) get_post_meta( $post->ID, self::META_PROFILE_PROXY_PREFIX, true ),
            );
        }
        return $profiles;
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/connectors',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_connectors' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/discovery/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_search' ),
                'permission_callback' => array( $this, 'rest_can_discover' ),
                'args'                => array(
                    'provider' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
                    'query'    => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'limit'    => array( 'sanitize_callback' => 'absint', 'default' => self::DEFAULT_RESULTS ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/discovery/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_import' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)/locate',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_locate' ),
                'permission_callback' => static function ( WP_REST_Request $request ) {
                    $source_id = absint( $request['id'] );
                    return $source_id
                        && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id )
                        && current_user_can( 'edit_post', $source_id );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/library-profiles',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_library_profiles' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    public function rest_connectors() {
        $public = array();
        foreach ( $this->providers() as $provider_id => $provider ) {
            $public[] = array(
                'id'          => $provider_id,
                'name'        => $provider['name'],
                'description' => $provider['description'],
                'mode'        => $provider['mode'],
                'search'      => ! empty( $provider['search'] ),
                'locate'      => ! empty( $provider['locate'] ),
                'available'   => ! empty( $provider['available'] ),
                'enabled'     => ! empty( $provider['enabled'] ),
            );
        }
        return rest_ensure_response(
            array(
                'schema'     => self::SEARCH_SCHEMA,
                'version'    => self::VERSION,
                'connectors' => $public,
            )
        );
    }

    public function rest_search( WP_REST_Request $request ) {
        $rate = $this->enforce_rate_limit( 'rest_search', 60, 10 * MINUTE_IN_SECONDS );
        if ( is_wp_error( $rate ) ) {
            return $rate;
        }
        return rest_ensure_response(
            $this->search_provider(
                sanitize_key( $request->get_param( 'provider' ) ),
                sanitize_text_field( $request->get_param( 'query' ) ),
                absint( $request->get_param( 'limit' ) ?: self::DEFAULT_RESULTS )
            )
        );
    }

    public function rest_import( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $idempotency_key = sanitize_text_field( $request->get_header( 'Idempotency-Key' ) ?: ( $payload['idempotency_key'] ?? '' ) );
        $replay = SC_Library_Connector_Holdings_Reliability::idempotency_lookup( $idempotency_key );
        if ( $replay ) {
            $replay['idempotent_replay'] = true;
            return rest_ensure_response( $replay );
        }
        $sealed = $this->read_sealed_result( $payload['token'] ?? '' );
        if ( is_wp_error( $sealed ) ) {
            return $sealed;
        }
        $imported = $this->import_result(
            $sealed['result'],
            absint( $payload['source_id'] ?? 0 ),
            absint( $payload['project_id'] ?? 0 ),
            sanitize_key( $payload['mode'] ?? 'fill_empty' )
        );
        if ( is_wp_error( $imported ) ) {
            return $imported;
        }
        SC_Library_Connector_Holdings_Reliability::idempotency_store( $idempotency_key, $imported );
        return rest_ensure_response( $imported );
    }

    public function rest_locate( WP_REST_Request $request ) {
        return rest_ensure_response( $this->locate_source( absint( $request['id'] ), rest_sanitize_boolean( $request->get_param( 'force' ) ) ) );
    }

    public function rest_library_profiles() {
        return rest_ensure_response(
            array(
                'schema'   => self::PROFILE_SCHEMA,
                'profiles' => $this->library_profiles(),
            )
        );
    }

    public function rest_can_discover() {
        return current_user_can( 'edit_posts' ) || (bool) apply_filters( 'sc_library_allow_public_discovery', false );
    }

    public function shortcode_source_discovery( $atts ) {
        $atts = shortcode_atts(
            array(
                'providers' => 'crossref,datacite,pubmed,pmc,loc,openlibrary',
                'limit'     => 5,
                'title'     => __( 'Source Discovery', 'sustainable-catalyst-library' ),
            ),
            $atts,
            'sc_source_discovery'
        );

        if ( ! current_user_can( 'edit_posts' ) && ! apply_filters( 'sc_library_allow_public_discovery', false ) ) {
            return '';
        }

        wp_enqueue_style( 'sc-library-connectors' );
        wp_enqueue_script( 'sc-library-connectors' );

        $allowed = array_map( 'sanitize_key', preg_split( '/[\s,]+/', $atts['providers'] ) );
        $providers = array_intersect_key( $this->providers(), array_flip( $allowed ) );
        ob_start();
        ?>
        <section class="sc-public-discovery sc-connector-discovery" data-sc-connector-discovery>
            <header>
                <p class="sc-connector-kicker"><?php esc_html_e( 'Knowledge Library', 'sustainable-catalyst-library' ); ?></p>
                <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                <p><?php esc_html_e( 'Search enabled scholarly and library metadata providers. Imported records are saved as drafts for review.', 'sustainable-catalyst-library' ); ?></p>
            </header>
            <form class="sc-connector-search-form" data-sc-connector-search-form>
                <label class="sc-connector-search-form__query"><span><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></span><input type="search" name="query" required minlength="2"></label>
                <input type="hidden" name="limit" value="<?php echo esc_attr( max( 1, min( 12, absint( $atts['limit'] ) ) ) ); ?>">
                <button type="submit"><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></button>
            </form>
            <fieldset class="sc-connector-provider-picker">
                <legend><?php esc_html_e( 'Providers', 'sustainable-catalyst-library' ); ?></legend>
                <?php foreach ( $providers as $provider_id => $provider ) : ?>
                    <?php if ( empty( $provider['search'] ) ) { continue; } ?>
                    <label><input type="checkbox" name="providers[]" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( ! empty( $provider['enabled'] ) ); ?> <?php disabled( empty( $provider['available'] ) ); ?>><span><strong><?php echo esc_html( $provider['name'] ); ?></strong><small><?php echo esc_html( $provider['description'] ); ?></small></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="sc-connector-search-status" data-sc-connector-status aria-live="polite"></div>
            <div class="sc-connector-result-summary" data-sc-connector-summary></div>
            <div class="sc-connector-results" data-sc-connector-results></div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_public_handoffs( $source_id ) {
        wp_enqueue_style( 'sc-library-connectors' );
        wp_enqueue_script( 'sc-library-connectors' );
        $manager = new self();
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, false );
        if ( ! $data ) {
            return '';
        }
        $links = array_merge( $manager->discovery_handoffs( $data ), $manager->public_library_actions( $data ) );
        if ( ! $links ) {
            return '';
        }
        ob_start();
        ?>
        <section class="sc-source-public-locator" aria-labelledby="sc-source-public-locator-heading">
            <p class="sc-source-record__kicker"><?php esc_html_e( 'Locate source material', 'sustainable-catalyst-library' ); ?></p>
            <h2 id="sc-source-public-locator-heading"><?php esc_html_e( 'Find, read, or request this source', 'sustainable-catalyst-library' ); ?></h2>
            <div class="sc-source-public-locator__links">
                <?php foreach ( $links as $link ) : ?>
                    <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['label'] ); ?></a>
                <?php endforeach; ?>
            </div>
            <p><?php esc_html_e( 'Availability is determined by the external service or library. Authentication remains on the provider’s website.', 'sustainable-catalyst-library' ); ?></p>
        </section>
        <?php
        return ob_get_clean();
    }

    private function public_library_actions( $data ) {
        $actions = array();
        foreach ( $this->library_actions( $data ) as $action ) {
            $actions[] = array(
                'provider' => $action['provider'],
                'kind'     => $action['kind'],
                'label'    => $action['label'],
                'url'      => $action['url'],
            );
        }
        return $actions;
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-source-discovery' ) ) {
            return;
        }
        $settings = $this->settings();
        if ( ! $settings['contact_email'] ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Configure a contact email for polite Crossref/Open Library requests and Unpaywall access.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }

    private function map_crossref_type( $type ) {
        $map = array(
            'journal-article'    => 'journal-article',
            'book'               => 'book',
            'book-chapter'       => 'book-chapter',
            'book-section'       => 'book-chapter',
            'proceedings-article'=> 'conference-paper',
            'proceedings'        => 'conference-paper',
            'report'             => 'report',
            'dataset'            => 'dataset',
            'dissertation'       => 'thesis',
            'standard'           => 'standard',
            'posted-content'     => 'report',
            'peer-review'        => 'report',
        );
        return $map[ sanitize_key( $type ) ] ?? 'report';
    }

    private function map_openalex_type( $type ) {
        $map = array(
            'article'       => 'journal-article',
            'book'          => 'book',
            'book-chapter'  => 'book-chapter',
            'dataset'       => 'dataset',
            'dissertation'  => 'thesis',
            'report'        => 'report',
            'standard'      => 'standard',
            'software'      => 'software',
            'paratext'      => 'archive',
            'preprint'      => 'report',
        );
        return $map[ sanitize_key( $type ) ] ?? 'report';
    }

    private function map_datacite_type( $type ) {
        $type = strtolower( sanitize_text_field( $type ) );
        if ( false !== strpos( $type, 'dataset' ) || false !== strpos( $type, 'collection' ) ) { return 'dataset'; }
        if ( false !== strpos( $type, 'software' ) || false !== strpos( $type, 'workflow' ) ) { return 'software'; }
        if ( false !== strpos( $type, 'book chapter' ) ) { return 'book-chapter'; }
        if ( false !== strpos( $type, 'book' ) ) { return 'book'; }
        if ( false !== strpos( $type, 'conference' ) ) { return 'conference-paper'; }
        if ( false !== strpos( $type, 'dissertation' ) || false !== strpos( $type, 'thesis' ) ) { return 'thesis'; }
        if ( false !== strpos( $type, 'journal article' ) ) { return 'journal-article'; }
        return 'report';
    }

    private function map_loc_type( $formats ) {
        $joined = strtolower( implode( ' ', (array) $formats ) );
        if ( false !== strpos( $joined, 'book' ) ) { return 'book'; }
        if ( false !== strpos( $joined, 'film' ) || false !== strpos( $joined, 'video' ) ) { return 'video'; }
        if ( false !== strpos( $joined, 'sound' ) || false !== strpos( $joined, 'audio' ) ) { return 'podcast'; }
        return 'archive';
    }

    private static function person( $family, $given = '', $suffix = '', $orcid = '' ) {
        $family = sanitize_text_field( $family );
        $given = sanitize_text_field( $given );
        if ( ! $family && ! $given ) {
            return array();
        }
        return array(
            'family' => $family,
            'given'  => $given,
            'suffix' => sanitize_text_field( $suffix ),
            'orcid'  => self::normalize_orcid( $orcid ),
        );
    }

    private static function person_from_display_name( $name, $orcid = '' ) {
        $name = trim( sanitize_text_field( $name ) );
        if ( ! $name ) {
            return array();
        }
        if ( false !== strpos( $name, ',' ) ) {
            list( $family, $given ) = array_pad( array_map( 'trim', explode( ',', $name, 2 ) ), 2, '' );
            return self::person( $family, $given, '', $orcid );
        }
        $parts = preg_split( '/\s+/u', $name );
        if ( count( $parts ) === 1 ) {
            return self::person( $parts[0], '', '', $orcid );
        }
        $family = array_pop( $parts );
        return self::person( $family, implode( ' ', $parts ), '', $orcid );
    }

    private static function normalize_orcid( $orcid ) {
        $orcid = preg_replace( '#^https?://orcid\.org/#i', '', trim( (string) $orcid ) );
        return preg_match( '/^\d{4}-\d{4}-\d{4}-[\dX]{4}$/i', $orcid ) ? strtoupper( $orcid ) : '';
    }

    private static function normalize_doi( $doi ) {
        $doi = preg_replace( '#^https?://(dx\.)?doi\.org/#i', '', trim( (string) $doi ) );
        return strtolower( trim( preg_replace( '/^doi:\s*/i', '', $doi ) ) );
    }

    private static function normalize_isbn( $isbn ) {
        return strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $isbn ) );
    }

    private static function preferred_isbn( $values ) {
        $normalized = array_values( array_filter( array_map( array( __CLASS__, 'normalize_isbn' ), (array) $values ) ) );
        foreach ( $normalized as $isbn ) {
            if ( 13 === strlen( $isbn ) ) {
                return $isbn;
            }
        }
        return $normalized[0] ?? '';
    }

    private static function first_string( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
                    return sanitize_text_field( $item );
                }
            }
            return '';
        }
        return sanitize_text_field( $value );
    }

    private static function clean_abstract( $abstract ) {
        $abstract = html_entity_decode( wp_strip_all_tags( (string) $abstract ), ENT_QUOTES, 'UTF-8' );
        return sanitize_textarea_field( trim( preg_replace( '/\s+/u', ' ', $abstract ) ) );
    }

    private static function date_parts_to_iso( $parts ) {
        if ( ! is_array( $parts ) || empty( $parts[0] ) ) {
            return '';
        }
        $year = absint( $parts[0] );
        $month = absint( $parts[1] ?? 1 );
        $day = absint( $parts[2] ?? 1 );
        return checkdate( $month, $day, $year ) ? sprintf( '%04d-%02d-%02d', $year, $month, $day ) : '';
    }

    private static function datacite_date( $dates ) {
        foreach ( (array) $dates as $date ) {
            if ( in_array( $date['dateType'] ?? '', array( 'Issued', 'Published', 'Created' ), true ) ) {
                $parsed = self::parse_loose_date( $date['date'] ?? '' );
                if ( $parsed ) {
                    return $parsed;
                }
            }
        }
        return '';
    }

    private static function parse_loose_date( $date ) {
        $date = trim( sanitize_text_field( $date ) );
        if ( ! $date ) {
            return '';
        }
        if ( preg_match( '/^(\d{4})$/', $date, $match ) ) {
            return $match[1] . '-01-01';
        }
        if ( preg_match( '/^(\d{4})-(\d{2})$/', $date, $match ) ) {
            return $match[1] . '-' . $match[2] . '-01';
        }
        $timestamp = strtotime( $date );
        return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
    }

    private static function valid_iso_date( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
            return false;
        }
        list( $year, $month, $day ) = array_map( 'absint', explode( '-', $date ) );
        return checkdate( $month, $day, $year );
    }

    private static function external_id_value( $value ) {
        $value = trim( (string) $value );
        return preg_replace( '#^https?://[^/]+/#', '', $value );
    }

    private static function openalex_pages( $biblio ) {
        if ( ! is_array( $biblio ) ) {
            return '';
        }
        $first = sanitize_text_field( $biblio['first_page'] ?? '' );
        $last = sanitize_text_field( $biblio['last_page'] ?? '' );
        return $first && $last && $first !== $last ? $first . '–' . $last : ( $first ?: $last );
    }

    private static function unique_locations( $locations ) {
        $indexed = array();
        foreach ( (array) $locations as $location ) {
            if ( ! is_array( $location ) || empty( $location['url'] ) ) {
                continue;
            }
            $url = esc_url_raw( $location['url'] );
            if ( ! $url ) {
                continue;
            }
            $location['url'] = $url;
            $indexed[ md5( strtolower( untrailingslashit( $url ) ) ) ] = $location;
        }
        return array_values( $indexed );
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : self::VERSION;
    }
}
