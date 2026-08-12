<?php
/**
 * Public Library Network & Local Access — v4.3.26.
 *
 * Public-library discovery and membership-aware access routing. Catalog search
 * is public; card eligibility and licensed digital access remain controlled by
 * each library. Sustainable Catalyst never stores external-library passwords.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Public_Library_Network {
    public const VERSION = '4.3.26';
    public const SCHEMA = 'sc-library-public-library-network/1.0';
    public const USER_META = 'sc_library_my_libraries_v4319';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_public_library_network', array( $this, 'shortcode' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_ajax_sc_library_v4326_connect_public_library', array( $this, 'ajax_connect_public_library' ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-public-library-network', SC_LIBRARY_URL . 'assets/css/sc-library-public-library-network.css', array(), self::VERSION );
        wp_register_script( 'sc-library-public-library-network', SC_LIBRARY_URL . 'assets/js/sc-library-public-library-network.js', array(), self::VERSION, true );
    }

    public static function access_types() {
        return array(
            'public-system' => array( 'label' => 'Public Library System', 'rank' => 10 ),
            'research-public' => array( 'label' => 'Public + Research Library', 'rank' => 20 ),
            'national' => array( 'label' => 'National Library', 'rank' => 30 ),
            'global-holdings' => array( 'label' => 'Global Holdings Network', 'rank' => 40 ),
        );
    }

    public static function registry() {
        $items = array(
            'cpl' => array(
                'name' => 'Chicago Public Library', 'region' => 'Chicago, Illinois, USA', 'type' => 'public-system',
                'search_template' => 'https://chipublib.bibliocommons.com/v2/search?query={query}&searchType=smart',
                'homepage' => 'https://www.chipublib.org/', 'digital_url' => 'https://www.chipublib.org/digital-collections/', 'card_url' => 'https://www.chipublib.org/get-a-library-card/', 'ill_url' => '',
                'access' => 'Catalog discovery is public. Borrowing and many licensed digital resources require a valid Chicago Public Library card or other library-authorized access.',
                'services' => array( 'Catalog', 'Digital collections', 'eBooks & databases', 'WorldCat handoff' ),
            ),
            'slpl' => array(
                'name' => 'St. Louis Public Library', 'region' => 'St. Louis, Missouri, USA', 'type' => 'public-system',
                'search_template' => 'https://slpl.bibliocommons.com/v2/search?query={query}&searchType=smart',
                'homepage' => 'https://www.slpl.org/', 'digital_url' => 'https://www.slpl.org/digital-content/', 'card_url' => 'https://www.slpl.org/library-card/', 'ill_url' => 'https://www.slpl.org/resource/',
                'access' => 'Catalog discovery is public. Card-based digital resources and borrowing follow St. Louis Public Library eligibility rules; WorldCat can support request workflows for items not owned locally.',
                'services' => array( 'Catalog', 'Digital content', 'WorldCat', 'Request / ILL route' ),
            ),
            'nypl' => array(
                'name' => 'New York Public Library', 'region' => 'New York, New York, USA', 'type' => 'research-public',
                'search_template' => 'https://www.nypl.org/research/research-catalog/search?q={query}',
                'homepage' => 'https://www.nypl.org/', 'digital_url' => 'https://www.nypl.org/help/services/remote-resources', 'card_url' => 'https://www.nypl.org/library-card', 'ill_url' => '',
                'access' => 'Research Catalog discovery is public. Remote databases and licensed resources may require a valid NYPL card and PIN or onsite access.',
                'services' => array( 'Research Catalog', 'Circulating collections', 'Remote resources', 'Digital Collections' ),
            ),
            'bpl' => array(
                'name' => 'Boston Public Library', 'region' => 'Boston, Massachusetts, USA', 'type' => 'research-public',
                'search_template' => 'https://bpl.bibliocommons.com/v2/search?query={query}&searchType=keyword',
                'homepage' => 'https://www.bpl.org/', 'digital_url' => 'https://www.bpl.org/stream-and-download/', 'card_url' => 'https://www.bpl.org/get-a-library-card/', 'ill_url' => '',
                'access' => 'Catalog discovery is public. Borrowing and licensed digital services depend on BPL card eligibility and resource-specific access rules.',
                'services' => array( 'Catalog', 'Digital media', 'Research resources', 'Local borrowing' ),
            ),
            'lapl' => array(
                'name' => 'Los Angeles Public Library', 'region' => 'Los Angeles, California, USA', 'type' => 'public-system',
                'search_template' => 'https://ls2pac.lapl.org/',
                'homepage' => 'https://www.lapl.org/', 'digital_url' => 'https://www.lapl.org/digital-library', 'card_url' => 'https://www.lapl.org/about-lapl/borrower-services', 'ill_url' => '',
                'access' => 'The public catalog can be opened without a Sustainable Catalyst account. Borrowing and licensed digital resources remain subject to LAPL card and service rules.',
                'services' => array( 'Catalog gateway', 'Digital Library', 'eBooks & media', 'Research databases' ),
            ),
            'sfpl' => array(
                'name' => 'San Francisco Public Library', 'region' => 'San Francisco, California, USA', 'type' => 'public-system',
                'search_template' => 'https://sfpl.bibliocommons.com/v2/search?query={query}&searchType=smart',
                'homepage' => 'https://sfpl.org/', 'digital_url' => 'https://sfpl.org/books-and-media/ebook-collections', 'card_url' => 'https://sfpl.org/services/library-card', 'ill_url' => '',
                'access' => 'Catalog discovery is public. Digital borrowing and licensed databases may require an eligible SFPL card or onsite access.',
                'services' => array( 'Catalog', 'eBooks & streaming', 'Research databases', 'Local holdings' ),
            ),
            'spl' => array(
                'name' => 'Seattle Public Library', 'region' => 'Seattle, Washington, USA', 'type' => 'public-system',
                'search_template' => 'https://seattle.bibliocommons.com/v2/search?query={query}&searchType=smart',
                'homepage' => 'https://www.spl.org/', 'digital_url' => 'https://www.spl.org/online-resources', 'card_url' => 'https://www.spl.org/using-the-library/get-started/get-a-library-card', 'ill_url' => '',
                'access' => 'Catalog discovery is public. Borrowing and subscription resources remain governed by Seattle Public Library card and access policies.',
                'services' => array( 'Catalog', 'Online resources', 'eBooks & media', 'Local holdings' ),
            ),
            'flp' => array(
                'name' => 'Free Library of Philadelphia', 'region' => 'Philadelphia, Pennsylvania, USA', 'type' => 'public-system',
                'search_template' => 'https://catalog.freelibrary.org/',
                'homepage' => 'https://www.freelibrary.org/', 'digital_url' => 'https://libwww.freelibrary.org/databases/', 'card_url' => 'https://www.freelibrary.org/getcard/', 'ill_url' => '',
                'access' => 'Catalog discovery is public. Many subscription databases and digital media services are available online with a valid Free Library card, while some resources are onsite only.',
                'services' => array( 'Catalog', 'Databases', 'Digital media', 'Research resources' ),
            ),
            'tpl' => array(
                'name' => 'Toronto Public Library', 'region' => 'Toronto, Ontario, Canada', 'type' => 'public-system',
                'search_template' => 'https://www.torontopubliclibrary.ca/search.jsp?Ntt={query}',
                'homepage' => 'https://www.torontopubliclibrary.ca/', 'digital_url' => 'https://www.torontopubliclibrary.ca/books-video-music/downloads-ebooks/', 'card_url' => 'https://www.torontopubliclibrary.ca/using-the-library/your-library-card/', 'ill_url' => '',
                'access' => 'Collection discovery is public. Borrowing, streaming, downloads and licensed online resources may require a valid Toronto Public Library card.',
                'services' => array( 'Catalogue', 'eBooks & online content', 'Streaming media', 'Research resources' ),
            ),
            'loc' => array(
                'name' => 'Library of Congress', 'region' => 'Washington, DC, USA', 'type' => 'national',
                'search_template' => 'https://www.loc.gov/search/?q={query}',
                'homepage' => 'https://www.loc.gov/', 'digital_url' => 'https://www.loc.gov/collections/', 'card_url' => 'https://www.loc.gov/rr/readerregistration.html', 'ill_url' => '',
                'access' => 'Large portions of Library of Congress metadata and digital collections are public. Physical requesting and restricted resources follow Library of Congress reader and collection rules.',
                'services' => array( 'Public catalog', 'Digital collections', 'National collections', 'Reader services' ),
            ),
            'worldcat' => array(
                'name' => 'WorldCat', 'region' => 'Libraries worldwide', 'type' => 'global-holdings',
                'search_template' => 'https://search.worldcat.org/search?q={query}',
                'homepage' => 'https://search.worldcat.org/', 'digital_url' => 'https://search.worldcat.org/', 'card_url' => '', 'ill_url' => '',
                'access' => 'WorldCat identifies holdings and online items across libraries worldwide. A holding is discovery evidence, not proof that a user can borrow or open the item.',
                'services' => array( 'Global holdings', 'Nearby libraries', 'Online-item discovery', 'Library handoff' ),
            ),
        );
        return apply_filters( 'sc_library_public_library_registry', $items );
    }

    public static function resolve_search_url( $id, $query ) {
        $registry = self::registry();
        $id = sanitize_key( $id );
        if ( empty( $registry[ $id ]['search_template'] ) ) { return ''; }
        $template = $registry[ $id ]['search_template'];
        $query = trim( wp_strip_all_tags( (string) $query ) );
        if ( false === strpos( $template, '{query}' ) ) { return esc_url_raw( $template ); }
        return esc_url_raw( str_replace( '{query}', rawurlencode( $query ), $template ) );
    }

    private static function connected_ids() {
        if ( ! is_user_logged_in() ) { return array(); }
        $stored = get_user_meta( get_current_user_id(), self::USER_META, true );
        $stored = is_array( $stored ) ? $stored : array();
        $ids = array();
        foreach ( $stored as $row ) {
            $id = sanitize_key( is_array( $row ) ? ( $row['id'] ?? '' ) : '' );
            if ( $id ) { $ids[ $id ] = sanitize_key( (string) ( $row['relation'] ?? 'research' ) ); }
        }
        return $ids;
    }

    public function register_rest_routes() {
        register_rest_route( 'sc-library/v1', '/public-library-network', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => function( WP_REST_Request $request ) {
                $query = sanitize_text_field( (string) $request->get_param( 'q' ) );
                $out = array();
                foreach ( self::registry() as $id => $item ) {
                    $row = $item; $row['id'] = $id; $row['search_url'] = self::resolve_search_url( $id, $query ); $out[] = $row;
                }
                return rest_ensure_response( array( 'schema' => self::SCHEMA, 'count' => count( $out ), 'query' => $query, 'libraries' => $out ) );
            },
        ) );
    }

    public function ajax_connect_public_library() {
        check_ajax_referer( 'sc_library_public_network_v4326', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Sign in to connect a public library.', 'sustainable-catalyst-library' ) ), 401 ); }
        $id = sanitize_key( wp_unslash( $_POST['library_id'] ?? '' ) );
        $relation = sanitize_key( wp_unslash( $_POST['relation'] ?? 'member' ) );
        $relation = in_array( $relation, array( 'member', 'research' ), true ) ? $relation : 'member';
        $registry = self::registry();
        if ( ! isset( $registry[ $id ] ) ) { wp_send_json_error( array( 'message' => __( 'Unknown public library.', 'sustainable-catalyst-library' ) ), 404 ); }
        $stored = get_user_meta( get_current_user_id(), self::USER_META, true );
        $stored = is_array( $stored ) ? $stored : array();
        $next = array(); $found = false;
        foreach ( $stored as $row ) {
            if ( sanitize_key( is_array( $row ) ? ( $row['id'] ?? '' ) : '' ) === $id ) { $next[] = array( 'id' => $id, 'relation' => $relation ); $found = true; }
            else { $next[] = $row; }
        }
        if ( ! $found ) { $next[] = array( 'id' => $id, 'relation' => $relation ); }
        update_user_meta( get_current_user_id(), self::USER_META, array_slice( $next, -20 ) );
        wp_send_json_success( array( 'schema' => self::SCHEMA, 'library_id' => $id, 'relation' => $relation, 'message' => __( 'Library connected to My Libraries.', 'sustainable-catalyst-library' ) ) );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => 'Public Library Network & Local Access' ), $atts, 'sc_public_library_network' );
        wp_enqueue_style( 'sc-library-public-library-network' );
        wp_enqueue_script( 'sc-library-public-library-network' );
        wp_localize_script( 'sc-library-public-library-network', 'SCPublicLibraryNetwork', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sc_library_public_network_v4326' ), 'signedIn' => is_user_logged_in(),
        ) );
        $query = isset( $_GET['sc_public_library_query'] ) ? sanitize_text_field( wp_unslash( $_GET['sc_public_library_query'] ) ) : '';
        $types = self::access_types(); $registry = self::registry(); $connected = self::connected_ids();
        uasort( $registry, static function( $a, $b ) use ( $types ) { $ar = $types[ $a['type'] ]['rank'] ?? 99; $br = $types[ $b['type'] ]['rank'] ?? 99; return $ar === $br ? strcmp( $a['name'], $b['name'] ) : $ar <=> $br; } );
        if ( $connected ) {
            $registry = array_intersect_key( $registry, $connected ) + array_diff_key( $registry, $connected );
        }
        ob_start(); ?>
        <section class="sc-public-library-network" data-sc-public-library-network="v4.3.26">
          <header><p class="sc-connector-kicker"><?php esc_html_e( 'Public Library Network', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><p><?php esc_html_e( 'Search public-library catalogs and digital collections, connect libraries you actually belong to, and carry those memberships into Access Intelligence. Sustainable Catalyst stores the relationship to a library—not its password.', 'sustainable-catalyst-library' ); ?></p></header>
          <form method="get" class="sc-public-library-network__search">
            <label><span><?php esc_html_e( 'Search public-library catalogs', 'sustainable-catalyst-library' ); ?></span><input type="search" name="sc_public_library_query" value="<?php echo esc_attr( $query ); ?>" placeholder="Title, author, subject, ISBN…"></label>
            <button type="submit"><?php esc_html_e( 'Prepare library searches', 'sustainable-catalyst-library' ); ?></button>
          </form>
          <div class="sc-public-library-network__legend"><?php foreach ( $types as $type ) : ?><span><?php echo esc_html( $type['label'] ); ?></span><?php endforeach; ?></div>
          <div class="sc-public-library-network__list">
          <?php foreach ( $registry as $id => $item ) : $url = self::resolve_search_url( $id, $query ); $is_connected = isset( $connected[ $id ] ); ?>
            <article class="sc-public-library-network__item" id="public-library-<?php echo esc_attr( $id ); ?>" data-library-id="<?php echo esc_attr( $id ); ?>">
              <div class="sc-public-library-network__meta"><small><?php echo esc_html( $types[ $item['type'] ]['label'] ?? 'Public Library' ); ?></small><span><?php echo esc_html( $item['region'] ); ?></span><?php if ( $is_connected ) : ?><strong><?php esc_html_e( 'Connected to My Libraries', 'sustainable-catalyst-library' ); ?></strong><?php endif; ?></div>
              <div><h4><?php echo esc_html( $item['name'] ); ?></h4><div class="sc-public-library-network__services"><?php foreach ( $item['services'] as $service ) : ?><span><?php echo esc_html( $service ); ?></span><?php endforeach; ?></div></div>
              <div><p><?php echo esc_html( $item['access'] ); ?></p><div class="sc-public-library-network__actions"><a href="<?php echo esc_url( $url ?: $item['homepage'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $query ? 'Search catalog' : 'Open catalog' ); ?> →</a><?php if ( ! empty( $item['digital_url'] ) ) : ?><a href="<?php echo esc_url( $item['digital_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Digital resources', 'sustainable-catalyst-library' ); ?> →</a><?php endif; ?><?php if ( ! empty( $item['card_url'] ) ) : ?><a href="<?php echo esc_url( $item['card_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Card / eligibility', 'sustainable-catalyst-library' ); ?> →</a><?php endif; ?></div>
              <?php if ( is_user_logged_in() && ! in_array( $item['type'], array( 'national', 'global-holdings' ), true ) ) : ?><div class="sc-public-library-network__connect"><button type="button" data-sc-connect-public-library="<?php echo esc_attr( $id ); ?>" data-relation="member" <?php disabled( $is_connected ); ?>><?php echo esc_html( $is_connected ? 'Connected' : 'I have access / membership' ); ?></button><button type="button" data-sc-connect-public-library="<?php echo esc_attr( $id ); ?>" data-relation="research" <?php disabled( $is_connected ); ?>><?php esc_html_e( 'Include as research library', 'sustainable-catalyst-library' ); ?></button><span aria-live="polite"></span></div><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
          </div>
          <?php if ( ! is_user_logged_in() ) : ?><p class="sc-public-library-network__signin"><strong><?php esc_html_e( 'Public catalog discovery remains open.', 'sustainable-catalyst-library' ); ?></strong> <?php esc_html_e( 'Sign in only if you want to persist library memberships in My Libraries and use those relationships in later access resolution.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
        </section><?php
        return ob_get_clean();
    }
}
