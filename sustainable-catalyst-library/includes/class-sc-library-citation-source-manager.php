<?php
/**
 * Citation and Research Source Manager.
 *
 * Structured research sources, Harvard citations, project bibliographies,
 * duplicate detection, source attachments, public shortcodes, and REST APIs.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Citation_Source_Manager {
    public const VERSION = '2.5.1';
    public const API_NAMESPACE = 'sc-library/v1';
    public const STYLE_SCHEMA = 'sc-library-citation-style/1.0';
    public const SOURCE_SCHEMA = 'sc-library-research-source/1.0';
    public const PROJECT_SCHEMA = 'sc-library-research-project/1.0';

    public const SOURCE_POST_TYPE = 'sc_research_source';
    public const PROJECT_POST_TYPE = 'sc_research_project';
    public const SOURCE_TYPE_TAXONOMY = 'sc_source_type';
    public const SOURCE_TOPIC_TAXONOMY = 'sc_source_topic';

    public const META_AUTHORS = '_sc_source_authors';
    public const META_ORGANIZATION = '_sc_source_organization';
    public const META_ORGANIZATION_SHORT = '_sc_source_organization_short';
    public const META_EDITORS = '_sc_source_editors';
    public const META_YEAR = '_sc_source_year';
    public const META_YEAR_SUFFIX = '_sc_source_year_suffix';
    public const META_AUTHOR_YEAR_KEY = '_sc_source_author_year_key';
    public const META_CONTAINER_TITLE = '_sc_source_container_title';
    public const META_PUBLISHER = '_sc_source_publisher';
    public const META_PLACE = '_sc_source_place';
    public const META_EDITION = '_sc_source_edition';
    public const META_VOLUME = '_sc_source_volume';
    public const META_ISSUE = '_sc_source_issue';
    public const META_PAGES = '_sc_source_pages';
    public const META_CHAPTER = '_sc_source_chapter';
    public const META_REPORT_NUMBER = '_sc_source_report_number';
    public const META_STANDARD_NUMBER = '_sc_source_standard_number';
    public const META_JURISDICTION = '_sc_source_jurisdiction';
    public const META_PUBLICATION_DATE = '_sc_source_publication_date';
    public const META_ACCESS_DATE = '_sc_source_access_date';
    public const META_DOI = '_sc_source_doi';
    public const META_ISBN = '_sc_source_isbn';
    public const META_PMID = '_sc_source_pmid';
    public const META_URL = '_sc_source_url';
    public const META_ARCHIVE_URL = '_sc_source_archive_url';
    public const META_LANGUAGE = '_sc_source_language';
    public const META_ATTACHMENT_ID = '_sc_source_attachment_id';
    public const META_RELATED_DOCUMENT_IDS = '_sc_source_related_document_ids';
    public const META_PROJECT_IDS = '_sc_source_project_ids';
    public const META_NOTES = '_sc_source_private_notes';
    public const META_VERIFIED = '_sc_source_metadata_verified';
    public const META_PEER_REVIEWED = '_sc_source_peer_reviewed';
    public const META_SOURCE_LEVEL = '_sc_source_level';
    public const META_FULL_TEXT_STATUS = '_sc_source_full_text_status';
    public const META_CITATION_KEY = '_sc_source_citation_key';
    public const META_FINGERPRINT = '_sc_source_fingerprint';
    public const META_NORMALIZED_DOI = '_sc_source_normalized_doi';
    public const META_NORMALIZED_ISBN = '_sc_source_normalized_isbn';
    public const META_NORMALIZED_URL = '_sc_source_normalized_url';
    public const META_DUPLICATES = '_sc_source_duplicate_matches';
    public const META_PROVENANCE = '_sc_source_metadata_provenance';
    public const META_LAST_VERIFIED = '_sc_source_last_verified';

    public const META_PROJECT_CODE = '_sc_project_code';
    public const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    public const META_PROJECT_STATUS = '_sc_project_status';
    public const META_PROJECT_STYLE = '_sc_project_citation_style';
    public const META_PROJECT_SOURCE_IDS = '_sc_project_source_ids';
    public const META_PROJECT_BIBLIOGRAPHY_TITLE = '_sc_project_bibliography_title';

    private static $saving_source = false;
    private static $saving_project = false;
    private static $deleted_source_group = '';

    public function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ), 225 );
        add_action( 'init', array( $this, 'ensure_default_source_types' ), 240 );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 999 );

        add_action( 'admin_menu', array( $this, 'register_manager_page' ), 245 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 40 );
        add_action( 'save_post_' . self::SOURCE_POST_TYPE, array( $this, 'save_source' ), 40, 3 );
        add_action( 'save_post_' . self::PROJECT_POST_TYPE, array( $this, 'save_project' ), 40, 3 );
        add_action( 'before_delete_post', array( $this, 'before_delete_record' ), 40, 2 );
        add_action( 'deleted_post', array( $this, 'after_delete_record' ), 40, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 190 );

        add_filter( 'manage_' . self::SOURCE_POST_TYPE . '_posts_columns', array( $this, 'source_columns' ) );
        add_action( 'manage_' . self::SOURCE_POST_TYPE . '_posts_custom_column', array( $this, 'source_column_content' ), 10, 2 );
        add_filter( 'manage_' . self::PROJECT_POST_TYPE . '_posts_columns', array( $this, 'project_columns' ) );
        add_action( 'manage_' . self::PROJECT_POST_TYPE . '_posts_custom_column', array( $this, 'project_column_content' ), 10, 2 );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_filter( 'template_include', array( $this, 'template_include' ), 120 );

        add_shortcode( 'sc_source_library', array( $this, 'shortcode_source_library' ) );
        add_shortcode( 'sc_research_bibliography', array( $this, 'shortcode_research_bibliography' ) );
        add_shortcode( 'sc_source_citation', array( $this, 'shortcode_source_citation' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_content_types() {
        register_post_type(
            self::SOURCE_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Research Sources', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Research Source', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Research Sources', 'sustainable-catalyst-library' ),
                    'add_new'            => __( 'Add Source', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Research Source', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Research Source', 'sustainable-catalyst-library' ),
                    'new_item'           => __( 'New Research Source', 'sustainable-catalyst-library' ),
                    'view_item'          => __( 'View Research Source', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Research Sources', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No research sources found.', 'sustainable-catalyst-library' ),
                    'not_found_in_trash' => __( 'No research sources found in Trash.', 'sustainable-catalyst-library' ),
                    'all_items'          => __( 'All Research Sources', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => true,
                'show_in_nav_menus'   => false,
                'exclude_from_search' => false,
                'has_archive'         => 'sources',
                'rewrite'             => array( 'slug' => 'sources', 'with_front' => false ),
                'query_var'           => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
                'menu_icon'           => 'dashicons-book-alt',
                'can_export'          => true,
            )
        );

        register_post_type(
            self::PROJECT_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Research Projects', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Research Project', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Research Projects', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Research Project', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Research Project', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Research Projects', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No research projects found.', 'sustainable-catalyst-library' ),
                    'all_items'          => __( 'All Research Projects', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
                'menu_icon'           => 'dashicons-portfolio',
                'can_export'          => true,
            )
        );

        register_taxonomy(
            self::SOURCE_TYPE_TAXONOMY,
            self::SOURCE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Source Types', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Source Type', 'sustainable-catalyst-library' ),
                    'search_items'  => __( 'Search Source Types', 'sustainable-catalyst-library' ),
                    'all_items'     => __( 'All Source Types', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Source Type', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Source Type', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Source Types', 'sustainable-catalyst-library' ),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_in_menu'      => 'edit.php?post_type=' . self::SOURCE_POST_TYPE,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => array( 'slug' => 'sources/type', 'with_front' => false ),
            )
        );

        register_taxonomy(
            self::SOURCE_TOPIC_TAXONOMY,
            self::SOURCE_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Source Topics', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Source Topic', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Source Topics', 'sustainable-catalyst-library' ),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => array( 'slug' => 'sources/topic', 'with_front' => false ),
            )
        );
    }

    public function ensure_default_source_types() {
        if ( get_option( 'sc_library_source_types_version' ) === self::VERSION ) {
            return;
        }

        $types = array(
            'journal-article'  => 'Journal article',
            'book'             => 'Book',
            'book-chapter'     => 'Book chapter',
            'report'           => 'Report',
            'webpage'          => 'Webpage',
            'dataset'          => 'Dataset',
            'legislation'      => 'Legislation',
            'standard'         => 'Standard',
            'conference-paper' => 'Conference paper',
            'thesis'           => 'Thesis or dissertation',
            'video'            => 'Video',
            'podcast'          => 'Podcast',
            'software'         => 'Software',
            'archive'          => 'Archival material',
        );

        foreach ( $types as $slug => $name ) {
            if ( ! term_exists( $slug, self::SOURCE_TYPE_TAXONOMY ) ) {
                wp_insert_term( $name, self::SOURCE_TYPE_TAXONOMY, array( 'slug' => $slug ) );
            }
        }
        update_option( 'sc_library_source_types_version', self::VERSION, false );
    }

    public function maybe_flush_rewrites() {
        if ( get_option( 'sc_library_citation_routes_version' ) === self::VERSION ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( 'sc_library_citation_routes_version', self::VERSION, false );
    }

    public function register_manager_page() {
        add_submenu_page(
            'sc-library',
            __( 'Citation Manager', 'sustainable-catalyst-library' ),
            __( 'Citation Manager', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-citation-manager',
            array( $this, 'render_manager_page' )
        );
    }

    public function render_manager_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $source_counts = wp_count_posts( self::SOURCE_POST_TYPE );
        $project_counts = wp_count_posts( self::PROJECT_POST_TYPE );
        $verified = new WP_Query(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::META_VERIFIED,
                'meta_value'     => '1',
            )
        );
        $duplicates = new WP_Query(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::META_DUPLICATES,
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );
        ?>
        <div class="wrap sc-citation-admin">
            <p class="sc-citation-kicker"><?php esc_html_e( 'Knowledge Library v2.5.1', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Citation and Research Source Manager', 'sustainable-catalyst-library' ); ?></h1>
            <p class="sc-citation-lede"><?php esc_html_e( 'Store structured sources, generate Harvard citations, organize project bibliographies, preserve attachments, and expose permission-controlled source APIs.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-citation-metrics">
                <div><strong><?php echo esc_html( absint( $source_counts->publish ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Published sources', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $source_counts->draft ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Draft sources', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $project_counts->publish ?? 0 ) + absint( $project_counts->draft ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Research projects', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $verified->found_posts ) ); ?></strong><span><?php esc_html_e( 'Verified sources', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $duplicates->found_posts ) ); ?></strong><span><?php esc_html_e( 'Possible duplicates', 'sustainable-catalyst-library' ); ?></span></div>
            </div>

            <div class="sc-citation-dashboard-grid">
                <section>
                    <h2><?php esc_html_e( 'Source records', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php esc_html_e( 'Create books, journal articles, reports, webpages, datasets, legislation, standards, media, software, and archival sources.', 'sustainable-catalyst-library' ); ?></p>
                    <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::SOURCE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Research Source', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::SOURCE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Sources', 'sustainable-catalyst-library' ); ?></a></p>
                </section>
                <section>
                    <h2><?php esc_html_e( 'Research projects', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php esc_html_e( 'Create project-specific source collections and formatted bibliographies without duplicating source records.', 'sustainable-catalyst-library' ); ?></p>
                    <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::PROJECT_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Research Project', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::PROJECT_POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Projects', 'sustainable-catalyst-library' ); ?></a></p>
                </section>
                <section>
                    <h2><?php esc_html_e( 'Public tools', 'sustainable-catalyst-library' ); ?></h2>
                    <pre>[sc_source_library]
[sc_research_bibliography project="project-slug"]
[sc_source_citation id="123" mode="reference"]</pre>
                </section>
                <section>
                    <h2><?php esc_html_e( 'REST API', 'sustainable-catalyst-library' ); ?></h2>
                    <pre><?php echo esc_html( rest_url( self::API_NAMESPACE . '/sources' ) ); ?>
<?php echo esc_html( rest_url( self::API_NAMESPACE . '/sources/123/citation' ) ); ?>
<?php echo esc_html( rest_url( self::API_NAMESPACE . '/projects/123/bibliography' ) ); ?></pre>
                    <p><?php esc_html_e( 'Published source metadata is readable publicly. Drafts, private projects, and write operations require WordPress permissions.', 'sustainable-catalyst-library' ); ?></p>
                </section>
            </div>
        </div>
        <?php
    }

    public function register_meta_boxes() {
        remove_meta_box( 'tagsdiv-' . self::SOURCE_TYPE_TAXONOMY, self::SOURCE_POST_TYPE, 'side' );

        add_meta_box(
            'sc-research-source-metadata',
            __( 'Bibliographic Metadata', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_metadata_box' ),
            self::SOURCE_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-research-source-citation',
            __( 'Harvard Citation', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_citation_box' ),
            self::SOURCE_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-research-source-quality',
            __( 'Source Review and Access', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_quality_box' ),
            self::SOURCE_POST_TYPE,
            'side',
            'default'
        );
        add_meta_box(
            'sc-research-project-sources',
            __( 'Project Source Library', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_sources_box' ),
            self::PROJECT_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-research-project-settings',
            __( 'Bibliography Settings', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_settings_box' ),
            self::PROJECT_POST_TYPE,
            'side',
            'high'
        );
    }

    public function render_source_metadata_box( $post ) {
        wp_nonce_field( 'sc_library_save_research_source_' . $post->ID, 'sc_library_source_nonce' );

        $authors = self::people_to_lines( get_post_meta( $post->ID, self::META_AUTHORS, true ) );
        $editors = self::people_to_lines( get_post_meta( $post->ID, self::META_EDITORS, true ) );
        $source_type = self::source_type_slug( $post->ID );
        $projects = self::id_list( get_post_meta( $post->ID, self::META_PROJECT_IDS, true ) );
        $related_documents = self::id_list( get_post_meta( $post->ID, self::META_RELATED_DOCUMENT_IDS, true ) );
        $attachment_id = absint( get_post_meta( $post->ID, self::META_ATTACHMENT_ID, true ) );
        $attachment = $attachment_id ? get_post( $attachment_id ) : null;
        ?>
        <div class="sc-citation-field-grid">
            <section class="sc-citation-field-section sc-citation-field-section--full">
                <h3><?php esc_html_e( 'Creators', 'sustainable-catalyst-library' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Enter one person per line: Family name | Given names | Suffix | ORCID. Use an organizational author when no personal author applies.', 'sustainable-catalyst-library' ); ?></p>
                <label for="sc-source-authors"><strong><?php esc_html_e( 'Authors', 'sustainable-catalyst-library' ); ?></strong></label>
                <textarea id="sc-source-authors" name="sc_source_authors" rows="5" placeholder="Ahmad | Tariq&#10;Smith | Jane M. | | 0000-0000-0000-0000"><?php echo esc_textarea( $authors ); ?></textarea>
                <label for="sc-source-organization"><strong><?php esc_html_e( 'Organizational author', 'sustainable-catalyst-library' ); ?></strong></label>
                <input id="sc-source-organization" type="text" name="sc_source_organization" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ORGANIZATION, true ) ); ?>">
                <label for="sc-source-organization-short"><strong><?php esc_html_e( 'Short institutional author', 'sustainable-catalyst-library' ); ?></strong></label>
                <input id="sc-source-organization-short" type="text" name="sc_source_organization_short" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ORGANIZATION_SHORT, true ) ); ?>" placeholder="WHO">
                <p class="description"><?php esc_html_e( 'Optional abbreviation used only for in-text citations; the full organization remains in the reference list.', 'sustainable-catalyst-library' ); ?></p>
                <label for="sc-source-editors"><strong><?php esc_html_e( 'Editors', 'sustainable-catalyst-library' ); ?></strong></label>
                <textarea id="sc-source-editors" name="sc_source_editors" rows="3" placeholder="Jones | Amira"><?php echo esc_textarea( $editors ); ?></textarea>
            </section>

            <section class="sc-citation-field-section">
                <h3><?php esc_html_e( 'Publication', 'sustainable-catalyst-library' ); ?></h3>
                <?php
                $this->select_field(
                    'sc_source_type',
                    __( 'Source type', 'sustainable-catalyst-library' ),
                    $source_type,
                    self::source_type_options()
                );
                $this->text_field( 'sc_source_year', __( 'Publication year', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_YEAR, true ), '2026' );
                $this->date_field( 'sc_source_publication_date', __( 'Full publication date', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PUBLICATION_DATE, true ) );
                $this->text_field( 'sc_source_container_title', __( 'Journal, book, site, or collection', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_CONTAINER_TITLE, true ) );
                $this->text_field( 'sc_source_publisher', __( 'Publisher or institution', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PUBLISHER, true ) );
                $this->text_field( 'sc_source_place', __( 'Place of publication', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PLACE, true ) );
                $this->text_field( 'sc_source_edition', __( 'Edition', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_EDITION, true ), '2nd edn.' );
                ?>
            </section>

            <section class="sc-citation-field-section">
                <h3><?php esc_html_e( 'Journal, chapter, and report details', 'sustainable-catalyst-library' ); ?></h3>
                <?php
                $this->text_field( 'sc_source_volume', __( 'Volume', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_VOLUME, true ) );
                $this->text_field( 'sc_source_issue', __( 'Issue', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_ISSUE, true ) );
                $this->text_field( 'sc_source_pages', __( 'Page range', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PAGES, true ), '42–58' );
                $this->text_field( 'sc_source_chapter', __( 'Chapter or section', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_CHAPTER, true ) );
                $this->text_field( 'sc_source_report_number', __( 'Report number', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_REPORT_NUMBER, true ) );
                $this->text_field( 'sc_source_standard_number', __( 'Standard or legislation number', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_STANDARD_NUMBER, true ) );
                $this->text_field( 'sc_source_jurisdiction', __( 'Jurisdiction', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_JURISDICTION, true ) );
                ?>
            </section>

            <section class="sc-citation-field-section">
                <h3><?php esc_html_e( 'Identifiers', 'sustainable-catalyst-library' ); ?></h3>
                <?php
                $this->text_field( 'sc_source_doi', __( 'DOI', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_DOI, true ), '10.0000/example' );
                $this->text_field( 'sc_source_isbn', __( 'ISBN', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_ISBN, true ) );
                $this->text_field( 'sc_source_pmid', __( 'PMID', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PMID, true ) );
                $this->url_field( 'sc_source_url', __( 'Canonical URL', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_URL, true ) );
                $this->url_field( 'sc_source_archive_url', __( 'Archive URL', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_ARCHIVE_URL, true ) );
                $this->date_field( 'sc_source_access_date', __( 'Access date', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_ACCESS_DATE, true ) );
                $this->text_field( 'sc_source_language', __( 'Language', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LANGUAGE, true ), 'English' );
                ?>
            </section>

            <section class="sc-citation-field-section">
                <h3><?php esc_html_e( 'Projects and relationships', 'sustainable-catalyst-library' ); ?></h3>
                <label for="sc-source-projects"><strong><?php esc_html_e( 'Research project IDs', 'sustainable-catalyst-library' ); ?></strong></label>
                <input id="sc-source-projects" type="text" name="sc_source_project_ids" value="<?php echo esc_attr( implode( ', ', $projects ) ); ?>" placeholder="104, 215">
                <p class="description"><?php esc_html_e( 'Comma-separated Research Project IDs. Project records remain the canonical source-collection index.', 'sustainable-catalyst-library' ); ?></p>
                <label for="sc-source-related-documents"><strong><?php esc_html_e( 'Related Knowledge Library document IDs', 'sustainable-catalyst-library' ); ?></strong></label>
                <input id="sc-source-related-documents" type="text" name="sc_source_related_document_ids" value="<?php echo esc_attr( implode( ', ', $related_documents ) ); ?>" placeholder="44, 51">
                <label for="sc-source-provenance"><strong><?php esc_html_e( 'Metadata provenance', 'sustainable-catalyst-library' ); ?></strong></label>
                <textarea id="sc-source-provenance" name="sc_source_provenance" rows="4" placeholder="Title: publisher page&#10;Authors: original PDF"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PROVENANCE, true ) ); ?></textarea>
            </section>

            <section class="sc-citation-field-section">
                <h3><?php esc_html_e( 'Attached source material', 'sustainable-catalyst-library' ); ?></h3>
                <input type="hidden" name="sc_source_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" data-sc-source-attachment-id>
                <div class="sc-source-attachment-status" data-sc-source-attachment-status>
                    <?php if ( $attachment ) : ?>
                        <strong><?php echo esc_html( $attachment->post_title ?: basename( get_attached_file( $attachment_id ) ) ); ?></strong>
                        <span><?php echo esc_html( get_post_mime_type( $attachment_id ) ); ?></span>
                    <?php else : ?>
                        <strong><?php esc_html_e( 'No source file selected', 'sustainable-catalyst-library' ); ?></strong>
                    <?php endif; ?>
                </div>
                <p><button type="button" class="button" data-sc-source-select-attachment><?php esc_html_e( 'Select Source File', 'sustainable-catalyst-library' ); ?></button> <button type="button" class="button" data-sc-source-remove-attachment <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button></p>
                <p class="description"><?php esc_html_e( 'Attach a PDF, dataset, image, document, or other supporting file from the WordPress Media Library.', 'sustainable-catalyst-library' ); ?></p>
            </section>

            <section class="sc-citation-field-section sc-citation-field-section--full">
                <h3><?php esc_html_e( 'Private research notes', 'sustainable-catalyst-library' ); ?></h3>
                <textarea name="sc_source_private_notes" rows="6"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_NOTES, true ) ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Private notes are excluded from public pages and public API responses.', 'sustainable-catalyst-library' ); ?></p>
            </section>
        </div>
        <?php
    }

    public function render_source_citation_box( $post ) {
        $citation = self::format_citation( $post->ID, 'harvard', 'reference' );
        $in_text = self::format_citation( $post->ID, 'harvard', 'in-text' );
        $key = get_post_meta( $post->ID, self::META_CITATION_KEY, true );
        ?>
        <div class="sc-citation-preview">
            <p><strong><?php esc_html_e( 'Reference-list entry', 'sustainable-catalyst-library' ); ?></strong></p>
            <p class="sc-citation-preview__text" data-sc-copy-value="<?php echo esc_attr( $citation ); ?>"><?php echo esc_html( $citation ?: __( 'Save the source to generate a citation.', 'sustainable-catalyst-library' ) ); ?></p>
            <?php if ( $citation ) : ?><button type="button" class="button" data-sc-copy-target=".sc-citation-preview__text"><?php esc_html_e( 'Copy reference', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>

            <p><strong><?php esc_html_e( 'In-text citation', 'sustainable-catalyst-library' ); ?></strong></p>
            <p class="sc-citation-preview__intext" data-sc-copy-value="<?php echo esc_attr( $in_text ); ?>"><?php echo esc_html( $in_text ?: '—' ); ?></p>
            <?php if ( $in_text ) : ?><button type="button" class="button" data-sc-copy-target=".sc-citation-preview__intext"><?php esc_html_e( 'Copy in-text citation', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>

            <p><strong><?php esc_html_e( 'Citation key', 'sustainable-catalyst-library' ); ?></strong><br><code><?php echo esc_html( $key ?: '—' ); ?></code></p>
            <p class="description"><?php esc_html_e( 'Harvard conventions vary by institution. v2.5.1 uses the tested Sustainable Catalyst Harvard profile with reliability checks.', 'sustainable-catalyst-library' ); ?></p>
        </div>
        <?php
    }

    public function render_source_quality_box( $post ) {
        $duplicates = self::id_list( get_post_meta( $post->ID, self::META_DUPLICATES, true ) );
        ?>
        <p><label><input type="checkbox" name="sc_source_verified" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_VERIFIED, true ) ); ?>> <?php esc_html_e( 'Metadata verified against the source', 'sustainable-catalyst-library' ); ?></label></p>
        <?php if ( '1' === get_post_meta( $post->ID, self::META_VERIFIED, true ) ) : ?>
            <p class="sc-source-reverify"><label><input type="checkbox" name="sc_source_reverify" value="1"> <?php esc_html_e( 'I rechecked citation-critical fields changed in this save.', 'sustainable-catalyst-library' ); ?></label></p>
        <?php endif; ?>
        <p><label><input type="checkbox" name="sc_source_peer_reviewed" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PEER_REVIEWED, true ) ); ?>> <?php esc_html_e( 'Peer reviewed', 'sustainable-catalyst-library' ); ?></label></p>
        <?php
        $this->select_field(
            'sc_source_level',
            __( 'Source level', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_SOURCE_LEVEL, true ),
            array(
                ''          => __( 'Not classified', 'sustainable-catalyst-library' ),
                'primary'   => __( 'Primary source', 'sustainable-catalyst-library' ),
                'secondary' => __( 'Secondary source', 'sustainable-catalyst-library' ),
                'tertiary'  => __( 'Tertiary source', 'sustainable-catalyst-library' ),
            )
        );
        $this->select_field(
            'sc_source_full_text_status',
            __( 'Full-text status', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_FULL_TEXT_STATUS, true ),
            array(
                ''                   => __( 'Not checked', 'sustainable-catalyst-library' ),
                'attached'           => __( 'Attached locally', 'sustainable-catalyst-library' ),
                'open-access'        => __( 'Open access', 'sustainable-catalyst-library' ),
                'subscription'       => __( 'Subscription required', 'sustainable-catalyst-library' ),
                'library-holding'    => __( 'Library holding located', 'sustainable-catalyst-library' ),
                'interlibrary-loan'  => __( 'Interlibrary loan required', 'sustainable-catalyst-library' ),
                'preview-only'       => __( 'Preview only', 'sustainable-catalyst-library' ),
                'unavailable'        => __( 'Full text unavailable', 'sustainable-catalyst-library' ),
            )
        );
        if ( $duplicates ) :
            ?>
            <div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Possible duplicate sources:', 'sustainable-catalyst-library' ); ?></strong></p><ul>
                <?php foreach ( $duplicates as $duplicate_id ) : ?>
                    <li><a href="<?php echo esc_url( get_edit_post_link( $duplicate_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $duplicate_id ) ?: sprintf( __( 'Source %d', 'sustainable-catalyst-library' ), $duplicate_id ) ); ?></a></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <?php if ( get_post_meta( $post->ID, self::META_LAST_VERIFIED, true ) ) : ?>
            <p class="description"><?php echo esc_html( sprintf( __( 'Last verified: %s', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_LAST_VERIFIED, true ) ) ); ?></p>
        <?php endif;
    }

    public function render_project_sources_box( $post ) {
        wp_nonce_field( 'sc_library_save_research_project_' . $post->ID, 'sc_library_project_nonce' );
        $selected = self::id_list( get_post_meta( $post->ID, self::META_PROJECT_SOURCE_IDS, true ) );
        $sources = get_posts(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 250,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <p><?php esc_html_e( 'Select reusable source records for this project. The bibliography is generated alphabetically from the same records.', 'sustainable-catalyst-library' ); ?></p>
        <div class="sc-project-source-picker">
            <label for="sc-project-source-filter" class="screen-reader-text"><?php esc_html_e( 'Filter sources', 'sustainable-catalyst-library' ); ?></label>
            <input id="sc-project-source-filter" type="search" placeholder="<?php esc_attr_e( 'Filter sources…', 'sustainable-catalyst-library' ); ?>" data-sc-project-source-filter>
            <div class="sc-project-source-list" data-sc-project-source-list>
                <?php foreach ( $sources as $source ) : ?>
                    <label data-source-search="<?php echo esc_attr( strtolower( $source->post_title . ' ' . self::format_citation( $source->ID, 'harvard', 'reference' ) ) ); ?>">
                        <input type="checkbox" name="sc_project_source_ids[]" value="<?php echo esc_attr( $source->ID ); ?>" <?php checked( in_array( $source->ID, $selected, true ) ); ?>>
                        <span><strong><?php echo esc_html( $source->post_title ); ?></strong><small><?php echo esc_html( self::format_citation( $source->ID, 'harvard', 'in-text' ) ); ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_project_settings_box( $post ) {
        $this->text_field( 'sc_project_code', __( 'Project code', 'sustainable-catalyst-library' ), get_post_meta( $post->ID, self::META_PROJECT_CODE, true ), 'SC-RESEARCH-001' );
        $this->select_field(
            'sc_project_visibility',
            __( 'Bibliography visibility', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_PROJECT_VISIBILITY, true ) ?: 'private',
            array(
                'private' => __( 'Private', 'sustainable-catalyst-library' ),
                'public'  => __( 'Public when project is published', 'sustainable-catalyst-library' ),
            )
        );
        $this->select_field(
            'sc_project_status',
            __( 'Research status', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_PROJECT_STATUS, true ) ?: 'active',
            array(
                'planning'  => __( 'Planning', 'sustainable-catalyst-library' ),
                'active'    => __( 'Active', 'sustainable-catalyst-library' ),
                'review'    => __( 'Under review', 'sustainable-catalyst-library' ),
                'complete'  => __( 'Complete', 'sustainable-catalyst-library' ),
                'archived'  => __( 'Archived', 'sustainable-catalyst-library' ),
            )
        );
        $this->select_field(
            'sc_project_citation_style',
            __( 'Citation style', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_PROJECT_STYLE, true ) ?: 'harvard',
            self::citation_style_options()
        );
        $this->text_field(
            'sc_project_bibliography_title',
            __( 'Bibliography heading', 'sustainable-catalyst-library' ),
            get_post_meta( $post->ID, self::META_PROJECT_BIBLIOGRAPHY_TITLE, true ) ?: __( 'References', 'sustainable-catalyst-library' )
        );
        if ( $post->ID ) :
            ?>
            <p><code>[sc_research_bibliography project="<?php echo esc_attr( $post->post_name ?: $post->ID ); ?>"]</code></p>
            <?php
        endif;
    }

    private function text_field( $name, $label, $value, $placeholder = '' ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<input id="' . esc_attr( $name ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
    }

    private function url_field( $name, $label, $value ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<input id="' . esc_attr( $name ) . '" type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="https://">';
    }

    private function date_field( $name, $label, $value ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<input id="' . esc_attr( $name ) . '" type="date" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }

    private function select_field( $name, $label, $value, $options ) {
        echo '<label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
        echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
        foreach ( $options as $option_value => $option_label ) {
            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select>';
    }

    public function save_source( $post_id, $post, $update ) {
        if ( self::$saving_source || ! $post instanceof WP_Post ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_source_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_source_nonce'] ) ), 'sc_library_save_research_source_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        SC_Library_Citation_Source_Reliability::begin_source_update( $post_id, 'admin' );
        self::$saving_source = true;
        $old_group = (string) get_post_meta( $post_id, self::META_AUTHOR_YEAR_KEY, true );
        $old_projects = self::id_list( get_post_meta( $post_id, self::META_PROJECT_IDS, true ) );
        $old_duplicates = self::id_list( get_post_meta( $post_id, self::META_DUPLICATES, true ) );

        $authors = self::parse_people_lines( wp_unslash( $_POST['sc_source_authors'] ?? '' ) );
        $editors = self::parse_people_lines( wp_unslash( $_POST['sc_source_editors'] ?? '' ) );
        update_post_meta( $post_id, self::META_AUTHORS, $authors );
        update_post_meta( $post_id, self::META_EDITORS, $editors );

        $text_fields = array(
            'sc_source_organization'       => self::META_ORGANIZATION,
            'sc_source_organization_short' => self::META_ORGANIZATION_SHORT,
            'sc_source_year'               => self::META_YEAR,
            'sc_source_container_title'    => self::META_CONTAINER_TITLE,
            'sc_source_publisher'          => self::META_PUBLISHER,
            'sc_source_place'              => self::META_PLACE,
            'sc_source_edition'            => self::META_EDITION,
            'sc_source_volume'             => self::META_VOLUME,
            'sc_source_issue'              => self::META_ISSUE,
            'sc_source_pages'              => self::META_PAGES,
            'sc_source_chapter'            => self::META_CHAPTER,
            'sc_source_report_number'      => self::META_REPORT_NUMBER,
            'sc_source_standard_number'    => self::META_STANDARD_NUMBER,
            'sc_source_jurisdiction'       => self::META_JURISDICTION,
            'sc_source_pmid'               => self::META_PMID,
            'sc_source_language'           => self::META_LANGUAGE,
        );
        foreach ( $text_fields as $request_key => $meta_key ) {
            self::update_or_delete_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $request_key ] ?? '' ) ) );
        }

        self::update_or_delete_meta(
            $post_id,
            self::META_DOI,
            self::normalize_doi( wp_unslash( $_POST['sc_source_doi'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_ISBN,
            self::format_isbn( wp_unslash( $_POST['sc_source_isbn'] ?? '' ) )
        );

        foreach ( array(
            'sc_source_publication_date' => self::META_PUBLICATION_DATE,
            'sc_source_access_date'      => self::META_ACCESS_DATE,
        ) as $request_key => $meta_key ) {
            $date = sanitize_text_field( wp_unslash( $_POST[ $request_key ] ?? '' ) );
            self::update_or_delete_meta( $post_id, $meta_key, self::valid_date( $date ) ? $date : '' );
        }

        self::update_or_delete_meta( $post_id, self::META_URL, SC_Library_Citation_Source_Reliability::canonical_url( wp_unslash( $_POST['sc_source_url'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_ARCHIVE_URL, esc_url_raw( wp_unslash( $_POST['sc_source_archive_url'] ?? '' ) ) );

        self::update_or_delete_meta(
            $post_id,
            self::META_NOTES,
            sanitize_textarea_field( wp_unslash( $_POST['sc_source_private_notes'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_PROVENANCE,
            sanitize_textarea_field( wp_unslash( $_POST['sc_source_provenance'] ?? '' ) )
        );

        $attachment_id = absint( wp_unslash( $_POST['sc_source_attachment_id'] ?? 0 ) );
        if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
            update_post_meta( $post_id, self::META_ATTACHMENT_ID, $attachment_id );
        } else {
            delete_post_meta( $post_id, self::META_ATTACHMENT_ID );
        }

        $related_documents = self::sanitize_post_ids(
            self::parse_id_input( wp_unslash( $_POST['sc_source_related_document_ids'] ?? '' ) ),
            'sc_foundation_doc'
        );
        self::update_id_meta( $post_id, self::META_RELATED_DOCUMENT_IDS, $related_documents );

        $projects = self::sanitize_post_ids(
            self::parse_id_input( wp_unslash( $_POST['sc_source_project_ids'] ?? '' ) ),
            self::PROJECT_POST_TYPE
        );
        self::update_id_meta( $post_id, self::META_PROJECT_IDS, $projects );
        $this->sync_source_projects( $post_id, $old_projects, $projects );

        update_post_meta( $post_id, self::META_VERIFIED, isset( $_POST['sc_source_verified'] ) ? '1' : '0' );
        update_post_meta( $post_id, self::META_PEER_REVIEWED, isset( $_POST['sc_source_peer_reviewed'] ) ? '1' : '0' );
        self::update_or_delete_meta(
            $post_id,
            self::META_SOURCE_LEVEL,
            in_array( sanitize_key( wp_unslash( $_POST['sc_source_level'] ?? '' ) ), array( 'primary', 'secondary', 'tertiary' ), true )
                ? sanitize_key( wp_unslash( $_POST['sc_source_level'] ) )
                : ''
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_FULL_TEXT_STATUS,
            in_array(
                sanitize_key( wp_unslash( $_POST['sc_source_full_text_status'] ?? '' ) ),
                array( 'attached', 'open-access', 'subscription', 'library-holding', 'interlibrary-loan', 'preview-only', 'unavailable' ),
                true
            ) ? sanitize_key( wp_unslash( $_POST['sc_source_full_text_status'] ) ) : ''
        );
        if ( isset( $_POST['sc_source_verified'] ) ) {
            update_post_meta( $post_id, self::META_LAST_VERIFIED, current_time( 'mysql' ) );
        }

        $source_type = sanitize_title( wp_unslash( $_POST['sc_source_type'] ?? '' ) );
        if ( $source_type && term_exists( $source_type, self::SOURCE_TYPE_TAXONOMY ) ) {
            wp_set_object_terms( $post_id, array( $source_type ), self::SOURCE_TYPE_TAXONOMY, false );
        }

        $doi_value = self::normalize_doi( get_post_meta( $post_id, self::META_DOI, true ) );
        $isbn_value = self::normalize_isbn( get_post_meta( $post_id, self::META_ISBN, true ) );
        $normalized_doi = SC_Library_Citation_Source_Reliability::valid_doi( $doi_value ) ? $doi_value : '';
        $normalized_isbn = SC_Library_Citation_Source_Reliability::valid_isbn( $isbn_value ) ? $isbn_value : '';
        $normalized_url = self::normalize_url( get_post_meta( $post_id, self::META_URL, true ) );
        self::update_or_delete_meta( $post_id, self::META_NORMALIZED_DOI, $normalized_doi );
        self::update_or_delete_meta( $post_id, self::META_NORMALIZED_ISBN, $normalized_isbn );
        self::update_or_delete_meta( $post_id, self::META_NORMALIZED_URL, $normalized_url );

        $group = self::author_year_key( $post_id );
        self::update_or_delete_meta( $post_id, self::META_AUTHOR_YEAR_KEY, $group );
        $fingerprint = self::source_fingerprint( $post_id );
        self::update_or_delete_meta( $post_id, self::META_FINGERPRINT, $fingerprint );

        if ( $old_group && $old_group !== $group ) {
            self::recalculate_year_suffixes( $old_group );
        }
        if ( $group ) {
            self::recalculate_year_suffixes( $group );
        } else {
            delete_post_meta( $post_id, self::META_YEAR_SUFFIX );
            self::update_citation_key( $post_id );
        }

        $duplicates = self::find_duplicate_sources( $post_id );
        self::update_duplicate_relationships( $post_id, $old_duplicates, $duplicates );
        self::update_id_meta( $post_id, self::META_DUPLICATES, $duplicates );

        self::$saving_source = false;
        SC_Library_Citation_Source_Reliability::finalize_source_update( $post_id, SC_Library_Citation_Source_Reliability::admin_verification_confirmation( $post_id ), 'admin' );
    }

    public function save_project( $post_id, $post, $update ) {
        if ( self::$saving_project || ! $post instanceof WP_Post ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_project_nonce'] ) ), 'sc_library_save_research_project_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saving_project = true;
        $old_sources = self::id_list( get_post_meta( $post_id, self::META_PROJECT_SOURCE_IDS, true ) );
        $sources = self::sanitize_post_ids(
            array_map( 'absint', (array) wp_unslash( $_POST['sc_project_source_ids'] ?? array() ) ),
            self::SOURCE_POST_TYPE
        );

        self::update_id_meta( $post_id, self::META_PROJECT_SOURCE_IDS, $sources );
        $this->sync_project_sources( $post_id, $old_sources, $sources );

        self::update_or_delete_meta(
            $post_id,
            self::META_PROJECT_CODE,
            sanitize_text_field( wp_unslash( $_POST['sc_project_code'] ?? '' ) )
        );
        update_post_meta(
            $post_id,
            self::META_PROJECT_VISIBILITY,
            'public' === sanitize_key( wp_unslash( $_POST['sc_project_visibility'] ?? '' ) ) ? 'public' : 'private'
        );
        $status = sanitize_key( wp_unslash( $_POST['sc_project_status'] ?? 'active' ) );
        update_post_meta(
            $post_id,
            self::META_PROJECT_STATUS,
            in_array( $status, array( 'planning', 'active', 'review', 'complete', 'archived' ), true ) ? $status : 'active'
        );
        $style = sanitize_key( wp_unslash( $_POST['sc_project_citation_style'] ?? 'harvard' ) );
        $styles = self::citation_styles();
        update_post_meta( $post_id, self::META_PROJECT_STYLE, isset( $styles[ $style ] ) ? $style : 'harvard' );
        self::update_or_delete_meta(
            $post_id,
            self::META_PROJECT_BIBLIOGRAPHY_TITLE,
            sanitize_text_field( wp_unslash( $_POST['sc_project_bibliography_title'] ?? '' ) )
        );

        self::$saving_project = false;
    }

    public function before_delete_record( $post_id, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( self::SOURCE_POST_TYPE === $post->post_type ) {
            self::$deleted_source_group = (string) get_post_meta( $post_id, self::META_AUTHOR_YEAR_KEY, true );
            $this->sync_source_projects(
                $post_id,
                self::id_list( get_post_meta( $post_id, self::META_PROJECT_IDS, true ) ),
                array()
            );
            self::update_duplicate_relationships(
                $post_id,
                self::id_list( get_post_meta( $post_id, self::META_DUPLICATES, true ) ),
                array()
            );
        }

        if ( self::PROJECT_POST_TYPE === $post->post_type ) {
            $this->sync_project_sources(
                $post_id,
                self::id_list( get_post_meta( $post_id, self::META_PROJECT_SOURCE_IDS, true ) ),
                array()
            );
        }
    }

    public function after_delete_record( $post_id, $post ) {
        if ( $post instanceof WP_Post && self::SOURCE_POST_TYPE === $post->post_type && self::$deleted_source_group ) {
            self::recalculate_year_suffixes( self::$deleted_source_group );
            self::$deleted_source_group = '';
        }
    }

    private function sync_source_projects( $source_id, $old_project_ids, $new_project_ids ) {
        foreach ( array_diff( $old_project_ids, $new_project_ids ) as $project_id ) {
            $sources = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
            self::update_id_meta( $project_id, self::META_PROJECT_SOURCE_IDS, array_values( array_diff( $sources, array( $source_id ) ) ) );
        }
        foreach ( array_diff( $new_project_ids, $old_project_ids ) as $project_id ) {
            $sources = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
            $sources[] = $source_id;
            self::update_id_meta( $project_id, self::META_PROJECT_SOURCE_IDS, $sources );
        }
    }

    private function sync_project_sources( $project_id, $old_source_ids, $new_source_ids ) {
        foreach ( array_diff( $old_source_ids, $new_source_ids ) as $source_id ) {
            $projects = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
            self::update_id_meta( $source_id, self::META_PROJECT_IDS, array_values( array_diff( $projects, array( $project_id ) ) ) );
        }
        foreach ( array_diff( $new_source_ids, $old_source_ids ) as $source_id ) {
            $projects = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
            $projects[] = $project_id;
            self::update_id_meta( $source_id, self::META_PROJECT_IDS, $projects );
        }
    }

    private static function update_duplicate_relationships( $source_id, $old_duplicates, $new_duplicates ) {
        foreach ( array_diff( $old_duplicates, $new_duplicates ) as $other_id ) {
            $matches = self::id_list( get_post_meta( $other_id, self::META_DUPLICATES, true ) );
            self::update_id_meta( $other_id, self::META_DUPLICATES, array_values( array_diff( $matches, array( $source_id ) ) ) );
        }
        foreach ( $new_duplicates as $other_id ) {
            $matches = self::id_list( get_post_meta( $other_id, self::META_DUPLICATES, true ) );
            $matches[] = $source_id;
            self::update_id_meta( $other_id, self::META_DUPLICATES, $matches );
        }
    }

    private static function find_duplicate_sources( $source_id ) {
        $checks = array_filter(
            array(
                self::META_NORMALIZED_DOI  => get_post_meta( $source_id, self::META_NORMALIZED_DOI, true ),
                self::META_NORMALIZED_ISBN => get_post_meta( $source_id, self::META_NORMALIZED_ISBN, true ),
                self::META_NORMALIZED_URL  => get_post_meta( $source_id, self::META_NORMALIZED_URL, true ),
                self::META_FINGERPRINT     => get_post_meta( $source_id, self::META_FINGERPRINT, true ),
            ),
            static function ( $value ) {
                return '' !== (string) $value;
            }
        );
        if ( ! $checks ) {
            return array();
        }

        $meta_query = array( 'relation' => 'OR' );
        foreach ( $checks as $key => $value ) {
            $meta_query[] = array(
                'key'     => $key,
                'value'   => $value,
                'compare' => '=',
            );
        }

        $ids = get_posts(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 50,
                'fields'         => 'ids',
                'post__not_in'   => array( $source_id ),
                'meta_query'     => $meta_query,
            )
        );
        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
        return apply_filters( 'sc_library_source_duplicate_candidates', $ids, $source_id );
    }

    private static function recalculate_year_suffixes( $group_key ) {
        if ( ! $group_key ) {
            return;
        }

        $ids = get_posts(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_key'       => self::META_AUTHOR_YEAR_KEY,
                'meta_value'     => $group_key,
            )
        );
        $count = count( $ids );
        foreach ( $ids as $index => $id ) {
            $suffix = $count > 1 ? self::alpha_suffix( $index ) : '';
            self::update_or_delete_meta( $id, self::META_YEAR_SUFFIX, $suffix );
            self::update_citation_key( $id );
        }
    }

    private static function update_citation_key( $source_id ) {
        $data = self::get_source_data( $source_id, true );
        if ( ! $data ) {
            return;
        }
        $creator = self::primary_creator_token( $data );
        $year = preg_replace( '/[^0-9A-Za-z]/', '', $data['year'] ?: 'nd' );
        $title_words = preg_split( '/[^A-Za-z0-9]+/', $data['title'] );
        $stop = array( 'a', 'an', 'and', 'for', 'in', 'of', 'on', 'the', 'to', 'with' );
        $title_token = 'Source';
        foreach ( (array) $title_words as $word ) {
            if ( strlen( $word ) > 2 && ! in_array( strtolower( $word ), $stop, true ) ) {
                $title_token = $word;
                break;
            }
        }
        $key = preg_replace( '/[^A-Za-z0-9]/', '', $creator . $year . $data['year_suffix'] . $title_token );
        if ( ! $key ) {
            $key = 'Source' . $source_id;
        }

        $existing = get_posts(
            array(
                'post_type'      => self::SOURCE_POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post__not_in'   => array( $source_id ),
                'meta_key'       => self::META_CITATION_KEY,
                'meta_value'     => $key,
            )
        );
        if ( $existing ) {
            $key .= $source_id;
        }
        update_post_meta( $source_id, self::META_CITATION_KEY, $key );
    }

    private static function source_fingerprint( $source_id ) {
        $data = self::get_source_data( $source_id, true );
        if ( ! $data ) {
            return '';
        }
        $creator = strtolower( self::primary_creator_token( $data ) );
        $title = strtolower( remove_accents( $data['title'] ) );
        $title = preg_replace( '/[^a-z0-9]+/', '', $title );
        $year = strtolower( preg_replace( '/[^0-9a-z]+/i', '', $data['year'] ) );
        return hash( 'sha256', $creator . '|' . $year . '|' . $title );
    }

    private static function author_year_key( $source_id ) {
        $data = self::get_source_data( $source_id, true );
        if ( ! $data ) {
            return '';
        }
        return strtolower( sanitize_title( self::primary_creator_token( $data ) . '-' . ( $data['year'] ?: 'n-d' ) ) );
    }

    public static function citation_styles() {
        $styles = array(
            'harvard' => array(
                'id'          => 'harvard',
                'name'        => __( 'Harvard — Sustainable Catalyst', 'sustainable-catalyst-library' ),
                'schema'      => self::STYLE_SCHEMA,
                'description' => __( 'Configurable author-date Harvard profile for in-text citations and reference lists.', 'sustainable-catalyst-library' ),
            ),
        );
        return apply_filters( 'sc_library_citation_styles', $styles );
    }

    private static function citation_style_options() {
        $options = array();
        foreach ( self::citation_styles() as $style_id => $style ) {
            $options[ $style_id ] = $style['name'] ?? $style_id;
        }
        return $options;
    }

    public static function format_citation( $source, $style = 'harvard', $mode = 'reference', $locator = '' ) {
        $source_id = is_array( $source ) ? 0 : absint( $source );
        $style = sanitize_key( $style ?: 'harvard' );
        $mode = sanitize_key( $mode ?: 'reference' );
        $locator = self::normalize_locator( sanitize_text_field( $locator ) );
        $cache_key = $style . '|' . $mode . '|' . $locator;
        if ( $source_id ) {
            $cache = get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE, true );
            if ( is_array( $cache ) && SC_Library_Citation_Source_Reliability::VERSION === get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE_VERSION, true ) && isset( $cache[ $cache_key ] ) ) {
                return (string) $cache[ $cache_key ];
            }
        }
        $data = is_array( $source ) ? $source : self::get_source_data( $source_id, true );
        if ( ! $data ) {
            return '';
        }

        if ( 'citation-key' === $mode ) {
            $citation = (string) ( $data['citation_key'] ?? '' );
        } elseif ( in_array( $mode, array( 'in-text', 'intext', 'author-date' ), true ) ) {
            $citation = self::format_in_text_citation( $data, $locator );
        } elseif ( 'reference-html' === $mode ) {
            $citation = self::format_harvard_reference( $data, true );
        } else {
            $citation = self::format_harvard_reference( $data, false );
        }

        $citation = apply_filters( 'sc_library_format_citation', $citation, $data, $style, $mode, $locator );
        if ( $source_id ) {
            $cache = get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE, true );
            $cache = is_array( $cache ) ? $cache : array();
            $cache[ $cache_key ] = $citation;
            if ( count( $cache ) > 40 ) { $cache = array_slice( $cache, -40, null, true ); }
            update_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE, $cache );
            update_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE_VERSION, SC_Library_Citation_Source_Reliability::VERSION );
        }
        return $citation;
    }

    private static function format_in_text_citation( $data, $locator = '' ) {
        $creator = self::format_creators_in_text( $data );
        $year = self::display_year( $data );
        $inside = trim( $creator . ', ' . $year, ', ' );

        if ( $locator ) {
            $inside .= ', ' . $locator;
        }
        return '(' . $inside . ')';
    }

    private static function format_harvard_reference( $data, $html = false ) {
        $creator = self::format_creators_reference( $data );
        $year = self::display_year( $data );
        $title = trim( (string) $data['title'] );
        $container = trim( (string) $data['container_title'] );
        $type = (string) $data['source_type'];
        $parts = array();

        $lead = trim( $creator );
        if ( $lead ) {
            $lead .= ' (' . $year . ')';
        } else {
            $lead = self::quote_or_emphasize( $title, $html, false ) . ' (' . $year . ')';
            $title = '';
        }
        $parts[] = $lead;

        switch ( $type ) {
            case 'journal-article':
                if ( $title ) {
                    $parts[] = self::quote_or_emphasize( $title, $html, true );
                }
                if ( $container ) {
                    $journal = self::emphasize( $container, $html );
                    if ( $data['volume'] ) {
                        $journal .= ', ' . $data['volume'];
                    }
                    if ( $data['issue'] ) {
                        $journal .= '(' . $data['issue'] . ')';
                    }
                    if ( $data['pages'] ) {
                        $journal .= ', ' . self::page_label( $data['pages'] ) . ' ' . self::normalize_page_range( $data['pages'] );
                    }
                    $parts[] = $journal;
                }
                break;

            case 'book':
                if ( $title ) {
                    $book = self::emphasize( $title, $html );
                    if ( $data['edition'] ) {
                        $book .= '. ' . self::normalize_edition( $data['edition'] );
                    }
                    $parts[] = $book;
                }
                $parts[] = self::publisher_statement( $data );
                break;

            case 'book-chapter':
                if ( $title ) {
                    $parts[] = self::quote_or_emphasize( $title, $html, true );
                }
                $editor_statement = self::format_editors_for_chapter( $data['editors'] );
                if ( $container ) {
                    $chapter = 'In: ';
                    if ( $editor_statement ) {
                        $chapter .= $editor_statement . ', ';
                    }
                    $chapter .= self::emphasize( $container, $html );
                    if ( $data['edition'] ) {
                        $chapter .= '. ' . self::normalize_edition( $data['edition'] );
                    }
                    if ( $data['pages'] ) {
                        $chapter .= ', ' . self::page_label( $data['pages'] ) . ' ' . self::normalize_page_range( $data['pages'] );
                    }
                    $parts[] = $chapter;
                }
                $parts[] = self::publisher_statement( $data );
                break;

            case 'report':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html );
                }
                if ( $data['report_number'] ) {
                    $parts[] = $data['report_number'];
                }
                $parts[] = self::publisher_statement( $data );
                break;

            case 'webpage':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html );
                }
                if ( $container && 0 !== strcasecmp( $container, $title ) ) {
                    $parts[] = $container;
                }
                break;

            case 'dataset':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html ) . ' [Dataset]';
                }
                if ( $data['publisher'] ) {
                    $parts[] = $data['publisher'];
                }
                break;

            case 'legislation':
                if ( $title ) {
                    $legislation = self::emphasize( $title, $html );
                    if ( $data['standard_number'] ) {
                        $legislation .= ', ' . $data['standard_number'];
                    }
                    $parts[] = $legislation;
                }
                if ( $data['jurisdiction'] ) {
                    $parts[] = $data['jurisdiction'];
                }
                break;

            case 'standard':
                if ( $title ) {
                    $standard = '';
                    if ( $data['standard_number'] ) {
                        $standard .= $data['standard_number'] . ': ';
                    }
                    $standard .= self::emphasize( $title, $html );
                    $parts[] = $standard;
                }
                $parts[] = self::publisher_statement( $data );
                break;

            case 'conference-paper':
                if ( $title ) {
                    $parts[] = self::quote_or_emphasize( $title, $html, true );
                }
                if ( $container ) {
                    $parts[] = 'paper presented at ' . self::emphasize( $container, $html );
                }
                if ( $data['place'] ) {
                    $parts[] = $data['place'];
                }
                break;

            case 'thesis':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html );
                }
                $parts[] = trim( 'Thesis. ' . $data['publisher'] );
                break;

            case 'video':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html ) . ' [Video]';
                }
                if ( $container ) {
                    $parts[] = $container;
                }
                break;

            case 'podcast':
                if ( $title ) {
                    $parts[] = self::quote_or_emphasize( $title, $html, true );
                }
                if ( $container ) {
                    $parts[] = self::emphasize( $container, $html ) . ' [Podcast]';
                }
                break;

            case 'software':
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html ) . ' [Software]';
                }
                if ( $data['publisher'] ) {
                    $parts[] = $data['publisher'];
                }
                break;

            case 'archive':
                if ( $title ) {
                    $parts[] = self::quote_or_emphasize( $title, $html, true );
                }
                if ( $container ) {
                    $parts[] = self::emphasize( $container, $html );
                }
                if ( $data['publisher'] || $data['place'] ) {
                    $parts[] = self::publisher_statement( $data );
                }
                break;

            default:
                if ( $title ) {
                    $parts[] = self::emphasize( $title, $html );
                }
                if ( $container ) {
                    $parts[] = $container;
                }
                if ( $data['publisher'] || $data['place'] ) {
                    $parts[] = self::publisher_statement( $data );
                }
                break;
        }

        $identifier = self::identifier_statement( $data, $html );
        if ( $identifier ) {
            $parts[] = $identifier;
        }

        $parts = array_values(
            array_filter(
                array_map(
                    static function ( $part ) {
                        return trim( (string) $part, " \t\n\r\0\x0B." );
                    },
                    $parts
                ),
                static function ( $part ) {
                    return '' !== $part;
                }
            )
        );

        return implode( '. ', $parts ) . ( $parts ? '.' : '' );
    }

    private static function identifier_statement( $data, $html = false ) {
        if ( $data['doi'] ) {
            $doi_url = 'https://doi.org/' . ltrim( $data['doi'], '/' );
            if ( $html ) {
                return 'doi: <a href="' . esc_url( $doi_url ) . '">' . esc_html( $data['doi'] ) . '</a>';
            }
            return 'doi: ' . $data['doi'];
        }

        $url = $data['url'] ?: $data['archive_url'];
        if ( ! $url ) {
            return '';
        }

        $available = $html
            ? 'Available at: <a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>'
            : 'Available at: ' . $url;
        if ( $data['access_date'] ) {
            $available .= ' (Accessed: ' . self::human_date( $data['access_date'] ) . ')';
        }
        return $available;
    }

    private static function publisher_statement( $data ) {
        $place = trim( (string) $data['place'] );
        $publisher = trim( (string) $data['publisher'] );
        if ( $place && $publisher ) {
            return $place . ': ' . $publisher;
        }
        return $publisher ?: $place;
    }

    private static function format_creators_reference( $data ) {
        $people = is_array( $data['authors'] ) ? $data['authors'] : array();
        if ( $people ) {
            $formatted = array();
            foreach ( $people as $person ) {
                $formatted[] = self::format_person_reference( $person );
            }
            return self::natural_join( array_filter( $formatted ) );
        }
        if ( $data['organization'] ) {
            return $data['organization'];
        }
        return '';
    }

    private static function format_creators_in_text( $data ) {
        $people = is_array( $data['authors'] ) ? $data['authors'] : array();
        if ( $people ) {
            $families = array_values(
                array_filter(
                    array_map(
                        static function ( $person ) {
                            return trim( (string) ( $person['family'] ?? '' ) );
                        },
                        $people
                    )
                )
            );
            if ( 1 === count( $families ) ) {
                return $families[0];
            }
            if ( 2 === count( $families ) ) {
                return $families[0] . ' and ' . $families[1];
            }
            if ( count( $families ) > 2 ) {
                return $families[0] . ' et al.';
            }
        }
        if ( $data['organization'] ) {
            return ! empty( $data['organization_short'] ) ? $data['organization_short'] : $data['organization'];
        }
        return self::short_title( $data['title'] );
    }

    private static function format_person_reference( $person ) {
        $family = trim( (string) ( $person['family'] ?? '' ) );
        $given = trim( (string) ( $person['given'] ?? '' ) );
        $suffix = trim( (string) ( $person['suffix'] ?? '' ) );
        if ( ! $family && ! $given ) {
            return '';
        }

        $initials = self::initials( $given );
        $name = $family ?: $given;
        if ( $family && $initials ) {
            $name .= ', ' . $initials;
        }
        if ( $suffix ) {
            $name .= ', ' . $suffix;
        }
        return $name;
    }

    private static function format_person_editor( $person ) {
        $family = trim( (string) ( $person['family'] ?? '' ) );
        $given = trim( (string) ( $person['given'] ?? '' ) );
        $initials = self::initials( $given );
        return trim( $initials . ' ' . $family );
    }

    private static function format_editors_for_chapter( $editors ) {
        if ( ! is_array( $editors ) || ! $editors ) {
            return '';
        }
        $formatted = array_values( array_filter( array_map( array( __CLASS__, 'format_person_editor' ), $editors ) ) );
        if ( ! $formatted ) {
            return '';
        }
        return self::natural_join( $formatted ) . ( count( $formatted ) > 1 ? ' (eds.)' : ' (ed.)' );
    }

    private static function natural_join( $items ) {
        $items = array_values( array_filter( array_map( 'trim', (array) $items ) ) );
        $count = count( $items );
        if ( 0 === $count ) {
            return '';
        }
        if ( 1 === $count ) {
            return $items[0];
        }
        if ( 2 === $count ) {
            return $items[0] . ' and ' . $items[1];
        }
        $last = array_pop( $items );
        return implode( ', ', $items ) . ' and ' . $last;
    }

    private static function initials( $given ) {
        $parts = preg_split( '/[\s-]+/u', trim( (string) $given ) );
        $initials = array();
        foreach ( (array) $parts as $part ) {
            if ( '' === $part ) {
                continue;
            }
            $letter = function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
            $initials[] = strtoupper( $letter ) . '.';
        }
        return implode( ' ', $initials );
    }

    private static function display_year( $data ) {
        $year = trim( (string) $data['year'] );
        if ( ! $year && $data['publication_date'] ) {
            $year = substr( $data['publication_date'], 0, 4 );
        }
        return ( $year ?: 'n.d.' ) . (string) $data['year_suffix'];
    }

    private static function primary_creator_token( $data ) {
        if ( ! empty( $data['authors'][0]['family'] ) ) {
            return (string) $data['authors'][0]['family'];
        }
        if ( $data['organization'] ) {
            $words = preg_split( '/\s+/u', $data['organization'] );
            return implode( '', array_slice( (array) $words, 0, 3 ) );
        }
        return self::short_title( $data['title'] );
    }

    private static function short_title( $title ) {
        $words = preg_split( '/\s+/u', wp_strip_all_tags( (string) $title ) );
        return implode( ' ', array_slice( array_filter( (array) $words ), 0, 4 ) ) ?: __( 'Untitled source', 'sustainable-catalyst-library' );
    }

    private static function quote_or_emphasize( $text, $html, $quote ) {
        if ( $quote ) {
            return $html ? '‘' . esc_html( $text ) . '’' : "'" . $text . "'";
        }
        return self::emphasize( $text, $html );
    }

    private static function emphasize( $text, $html ) {
        return $html ? '<em>' . esc_html( $text ) . '</em>' : $text;
    }

    private static function normalize_edition( $edition ) {
        $edition = trim( preg_replace( '/\s+/u', ' ', (string) $edition ) );
        if ( ! $edition ) { return ''; }
        if ( preg_match( '/^(\d+)(?:st|nd|rd|th)?(?:\s+(?:edn|edition))?\.?$/i', $edition, $matches ) ) {
            $number = absint( $matches[1] );
            $mod100 = $number % 100;
            $suffix = in_array( $mod100, array( 11, 12, 13 ), true ) ? 'th' : array( 1 => 'st', 2 => 'nd', 3 => 'rd' )[ $number % 10 ] ?? 'th';
            return $number . $suffix . ' edn.';
        }
        $edition = preg_replace( '/\s+(edition|edn)\.?$/i', '', $edition );
        return rtrim( $edition, '.' ) . ' edn.';
    }

    private static function page_label( $pages ) {
        $pages = trim( (string) $pages );
        return preg_match( '/[–—,-]/u', $pages ) ? 'pp.' : 'p.';
    }

    private static function normalize_page_range( $pages ) {
        $pages = preg_replace( '/\s*[—–-]\s*/u', '–', trim( (string) $pages ) );
        return preg_replace( '/\s*,\s*/u', ', ', $pages );
    }

    private static function normalize_locator( $locator ) {
        $locator = trim( preg_replace( '/\s+/u', ' ', (string) $locator ) );
        if ( ! $locator ) { return ''; }
        $locator = preg_replace( '/\s*[—–-]\s*/u', '–', $locator );
        if ( preg_match( '/^(p{1,2}\.|para\.|paras\.|ch\.|sec\.|§)\s*/iu', $locator ) ) { return $locator; }
        if ( preg_match( '/^\d+(?:–\d+)?$/u', $locator ) ) { return false !== strpos( $locator, '–' ) ? 'pp. ' . $locator : 'p. ' . $locator; }
        return $locator;
    }

    private static function human_date( $date ) {
        $timestamp = strtotime( (string) $date );
        return $timestamp ? wp_date( 'j F Y', $timestamp ) : $date;
    }

    public static function bibliography_sort_key( $source_id ) {
        $data = self::get_source_data( $source_id, true );
        if ( ! $data ) {
            return '';
        }
        return strtolower(
            remove_accents(
                self::primary_creator_token( $data ) . '|' .
                self::display_year( $data ) . '|' .
                $data['title']
            )
        );
    }

    public static function get_source_data( $source_id, $include_private = false ) {
        $post = get_post( $source_id );
        if ( ! $post || self::SOURCE_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $source_id ) ) {
            return array();
        }

        $type_term = wp_get_object_terms( $source_id, self::SOURCE_TYPE_TAXONOMY );
        $type = ! is_wp_error( $type_term ) && $type_term ? $type_term[0] : null;
        $attachment_id = absint( get_post_meta( $source_id, self::META_ATTACHMENT_ID, true ) );
        $attachment_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
        $can_edit_source = current_user_can( 'edit_post', $source_id );
        $related_document_ids = self::id_list( get_post_meta( $source_id, self::META_RELATED_DOCUMENT_IDS, true ) );
        $project_ids = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
        if ( ! $include_private || ! $can_edit_source ) {
            $related_document_ids = array_values(
                array_filter(
                    $related_document_ids,
                    static function ( $document_id ) {
                        return 'publish' === get_post_status( $document_id );
                    }
                )
            );
            $project_ids = array_values(
                array_filter(
                    $project_ids,
                    static function ( $project_id ) {
                        return self::project_is_public( $project_id );
                    }
                )
            );
        }

        $topics = wp_get_object_terms( $source_id, self::SOURCE_TOPIC_TAXONOMY );
        $topics = is_wp_error( $topics ) ? array() : array_map(
            static function ( $term ) {
                return array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            },
            $topics
        );

        $data = array(
            'schema'               => self::SOURCE_SCHEMA,
            'id'                   => $source_id,
            'status'               => $post->post_status,
            'title'                => get_the_title( $source_id ),
            'abstract'             => $post->post_excerpt,
            'description'          => $post->post_content,
            'permalink'            => 'publish' === $post->post_status ? get_permalink( $source_id ) : '',
            'authors'              => self::people_array( get_post_meta( $source_id, self::META_AUTHORS, true ) ),
            'organization'         => (string) get_post_meta( $source_id, self::META_ORGANIZATION, true ),
            'organization_short'   => (string) get_post_meta( $source_id, self::META_ORGANIZATION_SHORT, true ),
            'editors'              => self::people_array( get_post_meta( $source_id, self::META_EDITORS, true ) ),
            'year'                 => (string) get_post_meta( $source_id, self::META_YEAR, true ),
            'year_suffix'          => (string) get_post_meta( $source_id, self::META_YEAR_SUFFIX, true ),
            'publication_date'     => (string) get_post_meta( $source_id, self::META_PUBLICATION_DATE, true ),
            'access_date'          => (string) get_post_meta( $source_id, self::META_ACCESS_DATE, true ),
            'container_title'      => (string) get_post_meta( $source_id, self::META_CONTAINER_TITLE, true ),
            'publisher'            => (string) get_post_meta( $source_id, self::META_PUBLISHER, true ),
            'place'                => (string) get_post_meta( $source_id, self::META_PLACE, true ),
            'edition'              => (string) get_post_meta( $source_id, self::META_EDITION, true ),
            'volume'               => (string) get_post_meta( $source_id, self::META_VOLUME, true ),
            'issue'                => (string) get_post_meta( $source_id, self::META_ISSUE, true ),
            'pages'                => (string) get_post_meta( $source_id, self::META_PAGES, true ),
            'chapter'              => (string) get_post_meta( $source_id, self::META_CHAPTER, true ),
            'report_number'        => (string) get_post_meta( $source_id, self::META_REPORT_NUMBER, true ),
            'standard_number'      => (string) get_post_meta( $source_id, self::META_STANDARD_NUMBER, true ),
            'jurisdiction'         => (string) get_post_meta( $source_id, self::META_JURISDICTION, true ),
            'doi'                  => (string) get_post_meta( $source_id, self::META_DOI, true ),
            'isbn'                 => (string) get_post_meta( $source_id, self::META_ISBN, true ),
            'pmid'                 => (string) get_post_meta( $source_id, self::META_PMID, true ),
            'url'                  => (string) get_post_meta( $source_id, self::META_URL, true ),
            'archive_url'          => (string) get_post_meta( $source_id, self::META_ARCHIVE_URL, true ),
            'language'             => (string) get_post_meta( $source_id, self::META_LANGUAGE, true ),
            'source_type'          => $type ? $type->slug : '',
            'source_type_name'     => $type ? $type->name : '',
            'topics'               => $topics,
            'attachment_id'        => $attachment_id,
            'attachment_url'       => $attachment_url ?: '',
            'attachment_mime_type' => $attachment_id ? get_post_mime_type( $attachment_id ) : '',
            'related_document_ids' => $related_document_ids,
            'project_ids'          => $project_ids,
            'metadata_verified'    => '1' === get_post_meta( $source_id, self::META_VERIFIED, true ),
            'peer_reviewed'        => '1' === get_post_meta( $source_id, self::META_PEER_REVIEWED, true ),
            'source_level'         => (string) get_post_meta( $source_id, self::META_SOURCE_LEVEL, true ),
            'full_text_status'     => (string) get_post_meta( $source_id, self::META_FULL_TEXT_STATUS, true ),
            'citation_key'         => (string) get_post_meta( $source_id, self::META_CITATION_KEY, true ),
            'last_verified'        => (string) get_post_meta( $source_id, self::META_LAST_VERIFIED, true ),
            'citation_reliability' => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_RELIABILITY_STATUS, true ),
            'completeness_score'   => absint( get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_COMPLETENESS, true ) ),
            'modified_gmt'         => get_post_modified_time( 'c', true, $source_id ),
        );

        if ( $include_private && $can_edit_source ) {
            $data['private_notes'] = (string) get_post_meta( $source_id, self::META_NOTES, true );
            $data['metadata_provenance'] = (string) get_post_meta( $source_id, self::META_PROVENANCE, true );
            $data['duplicate_matches'] = self::id_list( get_post_meta( $source_id, self::META_DUPLICATES, true ) );
            $data['validation_issues'] = get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_VALIDATION, true ) ?: array();
            $data['duplicate_decisions'] = get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_DUPLICATE_DECISIONS, true ) ?: array();
            $data['canonical_source_id'] = absint( get_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CANONICAL_ID, true ) ) ?: $source_id;
        }

        $data['citation'] = self::format_citation( $data, 'harvard', 'reference' );
        $data['citation_html'] = self::format_citation( $data, 'harvard', 'reference-html' );
        $data['in_text_citation'] = self::format_citation( $data, 'harvard', 'in-text' );

        return apply_filters( 'sc_library_source_data', $data, $source_id, $include_private );
    }

    private static function people_array( $value ) {
        if ( ! is_array( $value ) ) { return array(); }
        $people = array();
        foreach ( $value as $person ) {
            if ( ! is_array( $person ) ) { continue; }
            $family = self::normalize_name_component( $person['family'] ?? '' );
            $given = self::normalize_name_component( $person['given'] ?? '' );
            if ( ! $family && ! $given ) { continue; }
            $people[] = array(
                'family' => $family,
                'given'  => $given,
                'suffix' => self::normalize_name_component( $person['suffix'] ?? '' ),
                'orcid'  => self::normalize_orcid( $person['orcid'] ?? '' ),
            );
        }
        return $people;
    }

    private static function parse_people_lines( $value ) {
        $lines = preg_split( '/\\R/u', (string) $value );
        $people = array();
        foreach ( (array) $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) { continue; }
            if ( false !== strpos( $line, '|' ) ) {
                $parts = array_map( 'trim', explode( '|', $line ) );
            } elseif ( false !== strpos( $line, ',' ) ) {
                $parts = array_map( 'trim', explode( ',', $line, 2 ) );
            } else {
                $parts = array( $line, '' );
            }
            $family = self::normalize_name_component( $parts[0] ?? '' );
            $given = self::normalize_name_component( $parts[1] ?? '' );
            if ( ! $family && ! $given ) { continue; }
            $people[] = array(
                'family' => $family,
                'given'  => $given,
                'suffix' => self::normalize_name_component( $parts[2] ?? '' ),
                'orcid'  => self::normalize_orcid( $parts[3] ?? '' ),
            );
        }
        return $people;
    }

    private static function people_to_lines( $value ) {
        $lines = array();
        foreach ( self::people_array( $value ) as $person ) {
            $parts = array( $person['family'], $person['given'], $person['suffix'], $person['orcid'] );
            while ( $parts && '' === end( $parts ) ) { array_pop( $parts ); }
            $lines[] = implode( ' | ', $parts );
        }
        return implode( "\\n", $lines );
    }

    private static function normalize_name_component( $value ) {
        $value = wp_check_invalid_utf8( (string) $value );
        $value = str_replace( array( '’', '‘', '‐', '‑', '—' ), array( "'", "'", '-', '-', '-' ), $value );
        return sanitize_text_field( preg_replace( '/\\s+/u', ' ', trim( $value ) ) );
    }

    private static function normalize_orcid( $orcid ) {
        $orcid = preg_replace( '#^https?://orcid\\.org/#i', '', trim( (string) $orcid ) );
        $orcid = strtoupper( preg_replace( '/[^0-9X]/i', '', $orcid ) );
        if ( 16 !== strlen( $orcid ) ) { return ''; }
        $orcid = substr( $orcid, 0, 4 ) . '-' . substr( $orcid, 4, 4 ) . '-' . substr( $orcid, 8, 4 ) . '-' . substr( $orcid, 12, 4 );
        return SC_Library_Citation_Source_Reliability::valid_orcid( $orcid ) ? $orcid : '';
    }

    private static function normalize_doi( $doi ) {
        $doi = trim( (string) $doi );
        $doi = preg_replace( '#^https?://(dx\\.)?doi\\.org/#i', '', $doi );
        $doi = preg_replace( '/^doi:\\s*/i', '', $doi );
        $doi = rtrim( trim( $doi ), " \\t\\n\\r\\0\\x0B.,;" );
        return strtolower( preg_replace( '/\\s+/u', '', $doi ) );
    }

    private static function normalize_isbn( $isbn ) {
        return strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $isbn ) );
    }

    private static function format_isbn( $isbn ) { return self::normalize_isbn( $isbn ); }

    private static function normalize_url( $url ) { return SC_Library_Citation_Source_Reliability::canonical_url( $url ); }

    private static function valid_date( $date ) {
        if ( ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', (string) $date ) ) { return false; }
        list( $year, $month, $day ) = array_map( 'absint', explode( '-', $date ) );
        return checkdate( $month, $day, $year );
    }

    private static function source_type_slug( $source_id ) {
        $terms = wp_get_object_terms( $source_id, self::SOURCE_TYPE_TAXONOMY, array( 'fields' => 'slugs' ) );
        return ! is_wp_error( $terms ) && $terms ? (string) $terms[0] : 'report';
    }

    private static function source_type_options() {
        $options = array( '' => __( 'Select source type', 'sustainable-catalyst-library' ) );
        $terms = get_terms(
            array(
                'taxonomy'   => self::SOURCE_TYPE_TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $options[ $term->slug ] = $term->name;
            }
        }
        return $options;
    }

    private static function parse_id_input( $value ) {
        return array_map( 'absint', preg_split( '/[\s,;]+/', trim( (string) $value ) ) );
    }

    private static function sanitize_post_ids( $ids, $post_type ) {
        $clean = array();
        foreach ( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) as $id ) {
            if ( $post_type === get_post_type( $id ) ) {
                $clean[] = $id;
            }
        }
        sort( $clean, SORT_NUMERIC );
        return $clean;
    }

    private static function id_list( $value ) {
        if ( is_string( $value ) ) {
            $value = self::parse_id_input( $value );
        }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
        sort( $ids, SORT_NUMERIC );
        return $ids;
    }

    private static function update_id_meta( $post_id, $meta_key, $ids ) {
        $ids = self::id_list( $ids );
        if ( $ids ) {
            update_post_meta( $post_id, $meta_key, $ids );
        } else {
            delete_post_meta( $post_id, $meta_key );
        }
    }

    private static function update_or_delete_meta( $post_id, $meta_key, $value ) {
        if ( is_array( $value ) ? ! empty( $value ) : '' !== trim( (string) $value ) ) {
            update_post_meta( $post_id, $meta_key, $value );
        } else {
            delete_post_meta( $post_id, $meta_key );
        }
    }

    private static function alpha_suffix( $index ) {
        $index = absint( $index );
        $suffix = '';
        do {
            $suffix = chr( 97 + ( $index % 26 ) ) . $suffix;
            $index = intdiv( $index, 26 ) - 1;
        } while ( $index >= 0 );
        return $suffix;
    }

    public function enqueue_admin_assets( $hook ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        $is_source = self::SOURCE_POST_TYPE === $screen->post_type;
        $is_project = self::PROJECT_POST_TYPE === $screen->post_type;
        $is_manager = false !== strpos( (string) $screen->id, 'sc-library-citation-manager' );
        if ( ! $is_source && ! $is_project && ! $is_manager ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-citation-manager',
            SC_LIBRARY_URL . 'assets/css/sc-library-citation-manager.css',
            array(),
            $this->version()
        );
        wp_enqueue_script(
            'sc-library-citation-manager',
            SC_LIBRARY_URL . 'assets/js/sc-library-citation-manager.js',
            array( 'jquery' ),
            $this->version(),
            true
        );
        wp_localize_script(
            'sc-library-citation-manager',
            'SCLibraryCitationManager',
            array(
                'copied'     => __( 'Copied', 'sustainable-catalyst-library' ),
                'copyFailed' => __( 'Copy failed. Select the citation manually.', 'sustainable-catalyst-library' ),
                'mediaTitle' => __( 'Select source material', 'sustainable-catalyst-library' ),
                'mediaButton'=> __( 'Use this file', 'sustainable-catalyst-library' ),
            )
        );

        if ( $is_source ) {
            wp_enqueue_media();
        }
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-citation-manager',
            SC_LIBRARY_URL . 'assets/css/sc-library-citation-manager.css',
            array(),
            $this->version()
        );
        wp_register_script(
            'sc-library-citation-manager',
            SC_LIBRARY_URL . 'assets/js/sc-library-citation-manager.js',
            array(),
            $this->version(),
            true
        );
        wp_localize_script(
            'sc-library-citation-manager',
            'SCLibraryCitationManager',
            array(
                'copied'     => __( 'Copied', 'sustainable-catalyst-library' ),
                'copyFailed' => __( 'Copy failed. Select the citation manually.', 'sustainable-catalyst-library' ),
            )
        );
    }

    private function enqueue_public_assets() {
        wp_enqueue_style( 'sc-library-citation-manager' );
        wp_enqueue_script( 'sc-library-citation-manager' );
    }

    public function source_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            $updated[ $key ] = $label;
            if ( 'title' === $key ) {
                $updated['sc_source_citation'] = __( 'Harvard citation', 'sustainable-catalyst-library' );
                $updated['sc_source_key'] = __( 'Citation key', 'sustainable-catalyst-library' );
                $updated['sc_source_review'] = __( 'Review', 'sustainable-catalyst-library' );
            }
        }
        return $updated;
    }

    public function source_column_content( $column, $post_id ) {
        if ( 'sc_source_citation' === $column ) {
            echo '<span class="sc-source-list-citation">' . esc_html( self::format_citation( $post_id, 'harvard', 'reference' ) ?: '—' ) . '</span>';
        }
        if ( 'sc_source_key' === $column ) {
            echo '<code>' . esc_html( get_post_meta( $post_id, self::META_CITATION_KEY, true ) ?: '—' ) . '</code>';
        }
        if ( 'sc_source_review' === $column ) {
            $verified = '1' === get_post_meta( $post_id, self::META_VERIFIED, true );
            $duplicates = self::id_list( get_post_meta( $post_id, self::META_DUPLICATES, true ) );
            echo '<strong class="sc-source-review-state ' . ( $verified ? 'is-verified' : 'is-unverified' ) . '">' . esc_html( $verified ? __( 'Verified', 'sustainable-catalyst-library' ) : __( 'Unverified', 'sustainable-catalyst-library' ) ) . '</strong>';
            if ( $duplicates ) {
                echo '<span class="sc-source-review-detail">' . esc_html( sprintf( _n( '%d possible duplicate', '%d possible duplicates', count( $duplicates ), 'sustainable-catalyst-library' ), count( $duplicates ) ) ) . '</span>';
            }
        }
    }

    public function project_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            $updated[ $key ] = $label;
            if ( 'title' === $key ) {
                $updated['sc_project_code'] = __( 'Project code', 'sustainable-catalyst-library' );
                $updated['sc_project_sources'] = __( 'Sources', 'sustainable-catalyst-library' );
                $updated['sc_project_visibility'] = __( 'Bibliography', 'sustainable-catalyst-library' );
            }
        }
        return $updated;
    }

    public function project_column_content( $column, $post_id ) {
        if ( 'sc_project_code' === $column ) {
            echo '<code>' . esc_html( get_post_meta( $post_id, self::META_PROJECT_CODE, true ) ?: '—' ) . '</code>';
        }
        if ( 'sc_project_sources' === $column ) {
            $ids = self::id_list( get_post_meta( $post_id, self::META_PROJECT_SOURCE_IDS, true ) );
            echo esc_html( number_format_i18n( count( $ids ) ) );
        }
        if ( 'sc_project_visibility' === $column ) {
            $visibility = get_post_meta( $post_id, self::META_PROJECT_VISIBILITY, true ) ?: 'private';
            echo '<strong>' . esc_html( 'public' === $visibility ? __( 'Public', 'sustainable-catalyst-library' ) : __( 'Private', 'sustainable-catalyst-library' ) ) . '</strong>';
            echo '<span class="sc-source-review-detail">' . esc_html( get_post_meta( $post_id, self::META_PROJECT_STYLE, true ) ?: 'harvard' ) . '</span>';
        }
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::SOURCE_POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( ! $post_id ) {
            return;
        }

        $data = self::get_source_data( $post_id, true );
        if ( ! $data ) {
            return;
        }

        $missing = array();
        if ( ! $data['authors'] && ! $data['organization'] ) {
            $missing[] = __( 'author or organization', 'sustainable-catalyst-library' );
        }
        if ( ! $data['year'] && ! $data['publication_date'] ) {
            $missing[] = __( 'publication year', 'sustainable-catalyst-library' );
        }
        if ( ! $data['source_type'] ) {
            $missing[] = __( 'source type', 'sustainable-catalyst-library' );
        }
        if ( $missing ) {
            echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Citation metadata is incomplete: %s.', 'sustainable-catalyst-library' ), implode( ', ', $missing ) ) ) . '</p></div>';
        }

        $duplicates = self::id_list( get_post_meta( $post_id, self::META_DUPLICATES, true ) );
        if ( $duplicates ) {
            echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( _n( 'This record has %d possible duplicate source.', 'This record has %d possible duplicate sources.', count( $duplicates ), 'sustainable-catalyst-library' ), count( $duplicates ) ) ) . '</p></div>';
        }
    }

    public function template_include( $template ) {
        if ( is_singular( self::SOURCE_POST_TYPE ) ) {
            $candidate = SC_LIBRARY_DIR . 'templates/single-sc_research_source.php';
            if ( is_file( $candidate ) ) {
                return $candidate;
            }
        }
        if ( is_post_type_archive( self::SOURCE_POST_TYPE ) || is_tax( array( self::SOURCE_TYPE_TAXONOMY, self::SOURCE_TOPIC_TAXONOMY ) ) ) {
            $candidate = SC_LIBRARY_DIR . 'templates/archive-sc_research_source.php';
            if ( is_file( $candidate ) ) {
                return $candidate;
            }
        }
        return $template;
    }

    public static function render_public_source_page( $source_id ) {
        $data = self::get_source_data( $source_id, false );
        if ( ! $data ) {
            return '';
        }

        wp_enqueue_style( 'sc-library-citation-manager' );
        wp_enqueue_script( 'sc-library-citation-manager' );

        $related_documents = array();
        foreach ( $data['related_document_ids'] as $document_id ) {
            if ( 'publish' === get_post_status( $document_id ) ) {
                $related_documents[] = array(
                    'id'    => $document_id,
                    'title' => get_the_title( $document_id ),
                    'url'   => get_permalink( $document_id ),
                );
            }
        }
        $projects = array();
        foreach ( $data['project_ids'] as $project_id ) {
            if ( self::project_is_public( $project_id ) ) {
                $projects[] = array(
                    'id'    => $project_id,
                    'title' => get_the_title( $project_id ),
                );
            }
        }

        ob_start();
        ?>
        <main class="sc-source-page" id="main-content">
            <article class="sc-source-record">
                <header class="sc-source-record__header">
                    <p class="sc-source-record__kicker"><?php echo esc_html( $data['source_type_name'] ?: __( 'Research Source', 'sustainable-catalyst-library' ) ); ?></p>
                    <h1><?php echo esc_html( $data['title'] ); ?></h1>
                    <p class="sc-source-record__creator"><?php echo esc_html( self::format_creators_reference( $data ) ); ?><?php echo $data['year'] || $data['publication_date'] ? ' · ' . esc_html( self::display_year( $data ) ) : ''; ?></p>
                    <div class="sc-source-record__actions">
                        <?php if ( $data['url'] ) : ?><a class="sc-source-button sc-source-button--primary" href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Source', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
                        <?php if ( $data['attachment_url'] ) : ?><a class="sc-source-button" href="<?php echo esc_url( $data['attachment_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Attached Material', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
                        <?php if ( $data['doi'] ) : ?><a class="sc-source-button" href="<?php echo esc_url( 'https://doi.org/' . rawurlencode( $data['doi'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open DOI', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
                        <a class="sc-source-button" href="<?php echo esc_url( get_post_type_archive_link( self::SOURCE_POST_TYPE ) ); ?>"><?php esc_html_e( 'Source Library', 'sustainable-catalyst-library' ); ?></a>
                    </div>
                </header>

                <section class="sc-source-citation-panel" aria-labelledby="sc-source-citation-heading">
                    <p class="sc-source-record__kicker"><?php esc_html_e( 'Harvard citation', 'sustainable-catalyst-library' ); ?></p>
                    <h2 id="sc-source-citation-heading"><?php esc_html_e( 'Cite this source', 'sustainable-catalyst-library' ); ?></h2>
                    <div class="sc-source-citation-panel__item">
                        <h3><?php esc_html_e( 'Reference list', 'sustainable-catalyst-library' ); ?></h3>
                        <p data-sc-copy-value="<?php echo esc_attr( $data['citation'] ); ?>"><?php echo wp_kses( $data['citation_html'], array( 'em' => array(), 'a' => array( 'href' => array() ) ) ); ?></p>
                        <button type="button" class="sc-source-copy" data-sc-copy-parent><?php esc_html_e( 'Copy reference', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                    <div class="sc-source-citation-panel__item">
                        <h3><?php esc_html_e( 'In-text', 'sustainable-catalyst-library' ); ?></h3>
                        <p data-sc-copy-value="<?php echo esc_attr( $data['in_text_citation'] ); ?>"><?php echo esc_html( $data['in_text_citation'] ); ?></p>
                        <button type="button" class="sc-source-copy" data-sc-copy-parent><?php esc_html_e( 'Copy in-text citation', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                    <p class="sc-source-citation-panel__key"><?php esc_html_e( 'Citation key:', 'sustainable-catalyst-library' ); ?> <code><?php echo esc_html( $data['citation_key'] ); ?></code></p>
                </section>

                <?php if ( class_exists( 'SC_Library_Scholarly_Library_Connectors' ) ) : ?>
                    <?php echo SC_Library_Scholarly_Library_Connectors::render_public_handoffs( $source_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>

                <?php if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) : ?>
                    <?php echo SC_Library_Evidence_Claim_Linking::render_public_source_evidence( $source_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>

                <div class="sc-source-record__layout">
                    <div class="sc-source-record__main">
                        <?php if ( $data['abstract'] ) : ?>
                            <section>
                                <h2><?php esc_html_e( 'Abstract or summary', 'sustainable-catalyst-library' ); ?></h2>
                                <p><?php echo esc_html( $data['abstract'] ); ?></p>
                            </section>
                        <?php endif; ?>
                        <?php if ( $data['description'] ) : ?>
                            <section>
                                <h2><?php esc_html_e( 'Source description', 'sustainable-catalyst-library' ); ?></h2>
                                <div class="sc-source-record__content"><?php echo wp_kses_post( apply_filters( 'the_content', $data['description'] ) ); ?></div>
                            </section>
                        <?php endif; ?>
                        <?php if ( $related_documents ) : ?>
                            <section>
                                <h2><?php esc_html_e( 'Related Knowledge Library documents', 'sustainable-catalyst-library' ); ?></h2>
                                <ul class="sc-source-related-list">
                                    <?php foreach ( $related_documents as $document ) : ?><li><a href="<?php echo esc_url( $document['url'] ); ?>"><?php echo esc_html( $document['title'] ); ?></a></li><?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="sc-source-record__metadata" aria-label="<?php esc_attr_e( 'Source metadata', 'sustainable-catalyst-library' ); ?>">
                        <h2><?php esc_html_e( 'Source record', 'sustainable-catalyst-library' ); ?></h2>
                        <dl>
                            <?php self::metadata_row( __( 'Type', 'sustainable-catalyst-library' ), $data['source_type_name'] ); ?>
                            <?php self::metadata_row( __( 'Published', 'sustainable-catalyst-library' ), $data['publication_date'] ?: $data['year'] ); ?>
                            <?php self::metadata_row( __( 'Container', 'sustainable-catalyst-library' ), $data['container_title'] ); ?>
                            <?php self::metadata_row( __( 'Publisher', 'sustainable-catalyst-library' ), $data['publisher'] ); ?>
                            <?php self::metadata_row( __( 'Volume and issue', 'sustainable-catalyst-library' ), trim( $data['volume'] . ( $data['issue'] ? ' (' . $data['issue'] . ')' : '' ) ) ); ?>
                            <?php self::metadata_row( __( 'Pages', 'sustainable-catalyst-library' ), $data['pages'] ); ?>
                            <?php self::metadata_row( __( 'DOI', 'sustainable-catalyst-library' ), $data['doi'] ); ?>
                            <?php self::metadata_row( __( 'ISBN', 'sustainable-catalyst-library' ), $data['isbn'] ); ?>
                            <?php self::metadata_row( __( 'PMID', 'sustainable-catalyst-library' ), $data['pmid'] ); ?>
                            <?php self::metadata_row( __( 'Language', 'sustainable-catalyst-library' ), $data['language'] ); ?>
                            <?php self::metadata_row( __( 'Review status', 'sustainable-catalyst-library' ), $data['metadata_verified'] ? __( 'Metadata verified', 'sustainable-catalyst-library' ) : __( 'Not yet verified', 'sustainable-catalyst-library' ) ); ?>
                            <?php self::metadata_row( __( 'Citation status', 'sustainable-catalyst-library' ), $data['citation_reliability'] ? ucfirst( str_replace( '-', ' ', $data['citation_reliability'] ) ) : __( 'Not checked', 'sustainable-catalyst-library' ) ); ?>
                            <?php self::metadata_row( __( 'Full text', 'sustainable-catalyst-library' ), self::full_text_label( $data['full_text_status'] ) ); ?>
                        </dl>
                        <?php if ( $data['topics'] ) : ?>
                            <h3><?php esc_html_e( 'Topics', 'sustainable-catalyst-library' ); ?></h3>
                            <ul class="sc-source-topic-list"><?php foreach ( $data['topics'] as $topic ) : ?><li><?php echo esc_html( $topic['name'] ); ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                        <?php if ( $projects ) : ?>
                            <h3><?php esc_html_e( 'Public research projects', 'sustainable-catalyst-library' ); ?></h3>
                            <ul><?php foreach ( $projects as $project ) : ?><li><?php echo esc_html( $project['title'] ); ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </aside>
                </div>
            </article>
        </main>
        <?php
        return ob_get_clean();
    }

    private static function metadata_row( $label, $value ) {
        if ( '' === trim( (string) $value ) ) {
            return;
        }
        echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
    }

    private static function full_text_label( $status ) {
        $labels = array(
            'attached'          => __( 'Attached locally', 'sustainable-catalyst-library' ),
            'open-access'       => __( 'Open access', 'sustainable-catalyst-library' ),
            'subscription'      => __( 'Subscription required', 'sustainable-catalyst-library' ),
            'library-holding'   => __( 'Library holding located', 'sustainable-catalyst-library' ),
            'interlibrary-loan' => __( 'Interlibrary loan required', 'sustainable-catalyst-library' ),
            'preview-only'      => __( 'Preview only', 'sustainable-catalyst-library' ),
            'unavailable'       => __( 'Unavailable', 'sustainable-catalyst-library' ),
        );
        return $labels[ $status ] ?? __( 'Not checked', 'sustainable-catalyst-library' );
    }

    public function shortcode_source_library( $atts ) {
        $atts = shortcode_atts(
            array(
                'type'     => '',
                'topic'    => '',
                'search'   => '',
                'limit'    => 20,
                'showform' => 'true',
            ),
            $atts,
            'sc_source_library'
        );
        $this->enqueue_public_assets();

        $search = sanitize_text_field( wp_unslash( $_GET['sc_source_q'] ?? $atts['search'] ) );
        $type = sanitize_title( wp_unslash( $_GET['sc_source_type'] ?? $atts['type'] ) );
        $topic = sanitize_title( $atts['topic'] );
        $page = max( 1, absint( wp_unslash( $_GET['sc_source_page'] ?? 1 ) ) );
        $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );

        $args = array(
            'post_type'      => self::SOURCE_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'paged'          => $page,
            's'              => $search,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        $tax_query = array();
        if ( $type ) {
            $tax_query[] = array(
                'taxonomy' => self::SOURCE_TYPE_TAXONOMY,
                'field'    => 'slug',
                'terms'    => array( $type ),
            );
        }
        if ( $topic ) {
            $tax_query[] = array(
                'taxonomy' => self::SOURCE_TOPIC_TAXONOMY,
                'field'    => 'slug',
                'terms'    => array( $topic ),
            );
        }
        if ( $tax_query ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );
        ob_start();
        ?>
        <section class="sc-source-library" aria-labelledby="sc-source-library-heading">
            <header class="sc-source-library__header">
                <p class="sc-source-record__kicker"><?php esc_html_e( 'Knowledge Library', 'sustainable-catalyst-library' ); ?></p>
                <h2 id="sc-source-library-heading"><?php esc_html_e( 'Research Source Library', 'sustainable-catalyst-library' ); ?></h2>
                <p><?php esc_html_e( 'Structured sources with reusable Harvard citations, identifiers, access records, and research-project relationships.', 'sustainable-catalyst-library' ); ?></p>
            </header>
            <?php if ( 'false' !== strtolower( (string) $atts['showform'] ) ) : ?>
                <form method="get" class="sc-source-library__filters">
                    <label><span><?php esc_html_e( 'Search sources', 'sustainable-catalyst-library' ); ?></span><input type="search" name="sc_source_q" value="<?php echo esc_attr( $search ); ?>"></label>
                    <label><span><?php esc_html_e( 'Source type', 'sustainable-catalyst-library' ); ?></span><select name="sc_source_type"><?php foreach ( self::source_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <button type="submit"><?php esc_html_e( 'Search', 'sustainable-catalyst-library' ); ?></button>
                </form>
            <?php endif; ?>
            <p class="sc-source-library__count"><?php echo esc_html( sprintf( _n( '%s source', '%s sources', $query->found_posts, 'sustainable-catalyst-library' ), number_format_i18n( $query->found_posts ) ) ); ?></p>
            <?php if ( $query->have_posts() ) : ?>
                <div class="sc-source-library__results">
                    <?php while ( $query->have_posts() ) : $query->the_post(); $data = self::get_source_data( get_the_ID(), false ); ?>
                        <article class="sc-source-card">
                            <p class="sc-source-card__type"><?php echo esc_html( $data['source_type_name'] ?: __( 'Research source', 'sustainable-catalyst-library' ) ); ?></p>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="sc-source-card__citation" data-sc-copy-value="<?php echo esc_attr( $data['citation'] ); ?>"><?php echo wp_kses( $data['citation_html'], array( 'em' => array(), 'a' => array( 'href' => array() ) ) ); ?></p>
                            <div class="sc-source-card__meta">
                                <?php if ( $data['doi'] ) : ?><span>DOI: <?php echo esc_html( $data['doi'] ); ?></span><?php endif; ?>
                                <?php if ( $data['metadata_verified'] ) : ?><span><?php esc_html_e( 'Metadata verified', 'sustainable-catalyst-library' ); ?></span><?php endif; ?>
                                <span><?php echo esc_html( self::full_text_label( $data['full_text_status'] ) ); ?></span>
                            </div>
                            <div class="sc-source-card__actions">
                                <a href="<?php the_permalink(); ?>"><?php esc_html_e( 'View source record', 'sustainable-catalyst-library' ); ?></a>
                                <button type="button" data-sc-copy-parent><?php esc_html_e( 'Copy citation', 'sustainable-catalyst-library' ); ?></button>
                            </div>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
                <?php if ( $query->max_num_pages > 1 ) : ?>
                    <nav class="sc-source-library__pagination" aria-label="<?php esc_attr_e( 'Source results pages', 'sustainable-catalyst-library' ); ?>">
                        <?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'sc_source_page', '%#%' ), 'current' => $page, 'total' => $query->max_num_pages, 'prev_text' => __( 'Previous', 'sustainable-catalyst-library' ), 'next_text' => __( 'Next', 'sustainable-catalyst-library' ) ) ) ); ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="sc-source-library__empty"><strong><?php esc_html_e( 'No matching sources found.', 'sustainable-catalyst-library' ); ?></strong></div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_research_bibliography( $atts ) {
        $atts = shortcode_atts(
            array(
                'project' => '',
                'title'   => '',
                'style'   => '',
                'showkey' => 'false',
            ),
            $atts,
            'sc_research_bibliography'
        );
        $project = self::resolve_project( $atts['project'] );
        if ( ! $project || ! self::can_read_project( $project->ID ) ) {
            return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Research project not found or not available.', 'sustainable-catalyst-library' ) . '</p>' : '';
        }

        $this->enqueue_public_assets();
        $style = sanitize_key( $atts['style'] ?: get_post_meta( $project->ID, self::META_PROJECT_STYLE, true ) ?: 'harvard' );
        $source_ids = self::project_source_ids( $project->ID, true );
        $title = sanitize_text_field( $atts['title'] ?: get_post_meta( $project->ID, self::META_PROJECT_BIBLIOGRAPHY_TITLE, true ) ?: __( 'References', 'sustainable-catalyst-library' ) );

        ob_start();
        ?>
        <section class="sc-project-bibliography" data-project-id="<?php echo esc_attr( $project->ID ); ?>">
            <header>
                <p class="sc-source-record__kicker"><?php echo esc_html( get_the_title( $project->ID ) ); ?></p>
                <h2><?php echo esc_html( $title ); ?></h2>
                <?php if ( $project->post_excerpt ) : ?><p><?php echo esc_html( $project->post_excerpt ); ?></p><?php endif; ?>
            </header>
            <?php if ( $source_ids ) : ?>
                <ol class="sc-project-bibliography__list">
                    <?php foreach ( $source_ids as $source_id ) : $citation = self::format_citation( $source_id, $style, 'reference' ); ?>
                        <li>
                            <p data-sc-copy-value="<?php echo esc_attr( $citation ); ?>"><?php echo wp_kses( self::format_citation( $source_id, $style, 'reference-html' ), array( 'em' => array(), 'a' => array( 'href' => array() ) ) ); ?></p>
                            <?php if ( 'true' === strtolower( (string) $atts['showkey'] ) ) : ?><code><?php echo esc_html( get_post_meta( $source_id, self::META_CITATION_KEY, true ) ); ?></code><?php endif; ?>
                            <button type="button" data-sc-copy-parent><?php esc_html_e( 'Copy citation', 'sustainable-catalyst-library' ); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p><?php esc_html_e( 'No published sources are attached to this project.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_source_citation( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'      => 0,
                'style'   => 'harvard',
                'mode'    => 'reference',
                'locator' => '',
                'copy'    => 'true',
            ),
            $atts,
            'sc_source_citation'
        );
        $source_id = absint( $atts['id'] );
        if ( ! self::get_source_data( $source_id, false ) ) {
            return '';
        }

        $this->enqueue_public_assets();
        $mode = sanitize_key( $atts['mode'] );
        $citation = self::format_citation( $source_id, sanitize_key( $atts['style'] ), $mode, sanitize_text_field( $atts['locator'] ) );
        $html = 'reference' === $mode
            ? self::format_citation( $source_id, sanitize_key( $atts['style'] ), 'reference-html', sanitize_text_field( $atts['locator'] ) )
            : esc_html( $citation );

        ob_start();
        ?>
        <span class="sc-inline-citation">
            <span data-sc-copy-value="<?php echo esc_attr( $citation ); ?>"><?php echo wp_kses( $html, array( 'em' => array(), 'a' => array( 'href' => array() ) ) ); ?></span>
            <?php if ( 'false' !== strtolower( (string) $atts['copy'] ) ) : ?><button type="button" data-sc-copy-parent><?php esc_html_e( 'Copy', 'sustainable-catalyst-library' ); ?></button><?php endif; ?>
        </span>
        <?php
        return ob_get_clean();
    }

    public static function render_archive_page() {
        $manager = new self();
        return $manager->shortcode_source_library(
            array(
                'type'     => is_tax( self::SOURCE_TYPE_TAXONOMY ) ? get_queried_object()->slug : '',
                'topic'    => is_tax( self::SOURCE_TOPIC_TAXONOMY ) ? get_queried_object()->slug : '',
                'showform' => 'true',
            )
        );
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/sources',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_list_sources' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
                        'type'     => array( 'sanitize_callback' => 'sanitize_title' ),
                        'topic'    => array( 'sanitize_callback' => 'sanitize_title' ),
                        'project'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
                        'page'     => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
                        'per_page' => array( 'sanitize_callback' => 'absint', 'default' => 20 ),
                        'status'   => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'publish' ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_source' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'edit_posts' );
                    },
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_list_sources' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_source' ),
                    'permission_callback' => array( $this, 'rest_can_read_source' ),
                ),
                array(
                    'methods'             => array( 'POST', 'PUT', 'PATCH' ),
                    'callback'            => array( $this, 'rest_update_source' ),
                    'permission_callback' => array( $this, 'rest_can_edit_source' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/sources/(?P<id>\d+)/citation',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_source_citation' ),
                'permission_callback' => array( $this, 'rest_can_read_source' ),
                'args'                => array(
                    'style'   => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'harvard' ),
                    'mode'    => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'reference' ),
                    'locator' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/bibliography',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_bibliography' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/sources',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_update_project_sources' ),
                'permission_callback' => static function ( WP_REST_Request $request ) {
                    $project_id = absint( $request['id'] );
                    return $project_id && current_user_can( 'edit_post', $project_id );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/citation/styles',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => static function () {
                    return rest_ensure_response(
                        array(
                            'schema' => self::STYLE_SCHEMA,
                            'styles' => array_values( self::citation_styles() ),
                        )
                    );
                },
                'permission_callback' => '__return_true',
            )
        );
    }

    public function rest_list_sources( WP_REST_Request $request ) {
        $page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) );
        $requested_status = sanitize_key( $request->get_param( 'status' ) ?: 'publish' );
        $can_read_private = current_user_can( 'edit_posts' );
        $post_status = 'publish';

        if ( $can_read_private && in_array( $requested_status, array( 'any', 'draft', 'pending', 'private', 'future', 'publish' ), true ) ) {
            $post_status = 'any' === $requested_status
                ? array( 'publish', 'draft', 'pending', 'private', 'future' )
                : $requested_status;
        }

        $args = array(
            'post_type'      => self::SOURCE_POST_TYPE,
            'post_status'    => $post_status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => sanitize_text_field( $request->get_param( 'search' ) ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $type = sanitize_title( $request->get_param( 'type' ) );
        $topic = sanitize_title( $request->get_param( 'topic' ) );
        $tax_query = array();
        if ( $type ) {
            $tax_query[] = array(
                'taxonomy' => self::SOURCE_TYPE_TAXONOMY,
                'field'    => 'slug',
                'terms'    => array( $type ),
            );
        }
        if ( $topic ) {
            $tax_query[] = array(
                'taxonomy' => self::SOURCE_TOPIC_TAXONOMY,
                'field'    => 'slug',
                'terms'    => array( $topic ),
            );
        }
        if ( $tax_query ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $project_param = $request->get_param( 'project' );
        if ( $project_param ) {
            $project = self::resolve_project( $project_param );
            if ( ! $project || ! self::can_read_project( $project->ID ) ) {
                return new WP_Error( 'sc_library_project_not_found', __( 'Research project not found or not available.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
            }
            $project_sources = self::project_source_ids( $project->ID, $can_read_private );
            $args['post__in'] = $project_sources ?: array( 0 );
        }

        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $source ) {
            $items[] = $this->rest_source_payload( $source->ID, $can_read_private );
        }

        $response = new WP_REST_Response(
            array(
                'schema'   => self::SOURCE_SCHEMA,
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => absint( $query->found_posts ),
                'pages'    => absint( $query->max_num_pages ),
                'sources'  => $items,
            )
        );
        $response->header( 'X-WP-Total', absint( $query->found_posts ) );
        $response->header( 'X-WP-TotalPages', absint( $query->max_num_pages ) );
        return $response;
    }

    public function rest_get_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return rest_ensure_response( $this->rest_source_payload( $source_id, current_user_can( 'edit_post', $source_id ) ) );
    }

    public function rest_create_source( WP_REST_Request $request ) {
        $rate = SC_Library_Citation_Source_Reliability::enforce_write_rate_limit( $request, 'create-source' );
        if ( is_wp_error( $rate ) ) { return $rate; }
        $existing_id = SC_Library_Citation_Source_Reliability::idempotent_create_response( $request );
        if ( $existing_id ) { return new WP_REST_Response( $this->rest_source_payload( $existing_id, true ), 200 ); }
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : $request->get_params();
        $title = sanitize_text_field( $payload['title'] ?? '' );
        if ( ! $title ) {
            return new WP_Error( 'sc_library_source_title_required', __( 'A source title is required.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $status = sanitize_key( $payload['status'] ?? 'draft' );
        if ( ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
            $status = 'draft';
        }
        if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
            $status = 'draft';
        }

        $source_id = wp_insert_post(
            array(
                'post_type'    => self::SOURCE_POST_TYPE,
                'post_status'  => $status,
                'post_title'   => $title,
                'post_excerpt' => sanitize_textarea_field( $payload['abstract'] ?? '' ),
                'post_content' => wp_kses_post( $payload['description'] ?? '' ),
            ),
            true
        );
        if ( is_wp_error( $source_id ) ) {
            return $source_id;
        }

        SC_Library_Citation_Source_Reliability::begin_source_update( $source_id, 'rest-create' );
        $result = $this->apply_rest_source_payload( $source_id, $payload );
        if ( is_wp_error( $result ) ) { return $result; }
        SC_Library_Citation_Source_Reliability::finalize_source_update( $source_id, ! empty( $payload['metadata_verified'] ), 'rest-create' );
        SC_Library_Citation_Source_Reliability::remember_idempotent_create( $request, $source_id );

        $response = new WP_REST_Response( $this->rest_source_payload( $source_id, true ), 201 );
        $response->header( 'Location', rest_url( self::API_NAMESPACE . '/sources/' . $source_id ) );
        return $response;
    }

    public function rest_update_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        $rate = SC_Library_Citation_Source_Reliability::enforce_write_rate_limit( $request, 'update-source' );
        if ( is_wp_error( $rate ) ) { return $rate; }
        $conflict = SC_Library_Citation_Source_Reliability::validate_expected_modified( $source_id, $request );
        if ( is_wp_error( $conflict ) ) { return $conflict; }
        SC_Library_Citation_Source_Reliability::begin_source_update( $source_id, 'rest-update' );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : $request->get_params();

        $post_update = array( 'ID' => $source_id );
        if ( array_key_exists( 'title', $payload ) ) {
            $post_update['post_title'] = sanitize_text_field( $payload['title'] );
        }
        if ( array_key_exists( 'abstract', $payload ) ) {
            $post_update['post_excerpt'] = sanitize_textarea_field( $payload['abstract'] );
        }
        if ( array_key_exists( 'description', $payload ) ) {
            $post_update['post_content'] = wp_kses_post( $payload['description'] );
        }
        if ( array_key_exists( 'status', $payload ) ) {
            $status = sanitize_key( $payload['status'] );
            if ( in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
                if ( 'publish' !== $status || current_user_can( 'publish_posts' ) ) {
                    $post_update['post_status'] = $status;
                }
            }
        }
        if ( count( $post_update ) > 1 ) {
            $updated = wp_update_post( wp_slash( $post_update ), true );
            if ( is_wp_error( $updated ) ) {
                return $updated;
            }
        }

        $result = $this->apply_rest_source_payload( $source_id, $payload );
        if ( is_wp_error( $result ) ) { return $result; }
        SC_Library_Citation_Source_Reliability::finalize_source_update( $source_id, ! empty( $payload['metadata_verified'] ), 'rest-update' );
        return rest_ensure_response( $this->rest_source_payload( $source_id, true ) );
    }

    private function apply_rest_source_payload( $source_id, $payload ) {
        $old_group = (string) get_post_meta( $source_id, self::META_AUTHOR_YEAR_KEY, true );
        $old_projects = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
        $old_duplicates = self::id_list( get_post_meta( $source_id, self::META_DUPLICATES, true ) );

        if ( array_key_exists( 'authors', $payload ) ) {
            $authors = is_array( $payload['authors'] )
                ? self::people_array( $payload['authors'] )
                : self::parse_people_lines( $payload['authors'] );
            self::update_or_delete_meta( $source_id, self::META_AUTHORS, $authors );
        }
        if ( array_key_exists( 'editors', $payload ) ) {
            $editors = is_array( $payload['editors'] )
                ? self::people_array( $payload['editors'] )
                : self::parse_people_lines( $payload['editors'] );
            self::update_or_delete_meta( $source_id, self::META_EDITORS, $editors );
        }

        $map = array(
            'organization'      => self::META_ORGANIZATION,
            'organization_short'=> self::META_ORGANIZATION_SHORT,
            'year'              => self::META_YEAR,
            'container_title'   => self::META_CONTAINER_TITLE,
            'publisher'         => self::META_PUBLISHER,
            'place'             => self::META_PLACE,
            'edition'           => self::META_EDITION,
            'volume'            => self::META_VOLUME,
            'issue'             => self::META_ISSUE,
            'pages'             => self::META_PAGES,
            'chapter'           => self::META_CHAPTER,
            'report_number'     => self::META_REPORT_NUMBER,
            'standard_number'   => self::META_STANDARD_NUMBER,
            'jurisdiction'      => self::META_JURISDICTION,
            'pmid'              => self::META_PMID,
            'language'          => self::META_LANGUAGE,
            'source_level'      => self::META_SOURCE_LEVEL,
            'full_text_status'  => self::META_FULL_TEXT_STATUS,
            'metadata_provenance' => self::META_PROVENANCE,
            'private_notes'     => self::META_NOTES,
        );
        foreach ( $map as $field => $meta_key ) {
            if ( array_key_exists( $field, $payload ) ) {
                $value = in_array( $field, array( 'metadata_provenance', 'private_notes' ), true )
                    ? sanitize_textarea_field( $payload[ $field ] )
                    : sanitize_text_field( $payload[ $field ] );
                self::update_or_delete_meta( $source_id, $meta_key, $value );
            }
        }

        if ( array_key_exists( 'doi', $payload ) ) {
            self::update_or_delete_meta( $source_id, self::META_DOI, self::normalize_doi( $payload['doi'] ) );
        }
        if ( array_key_exists( 'isbn', $payload ) ) {
            self::update_or_delete_meta( $source_id, self::META_ISBN, self::format_isbn( $payload['isbn'] ) );
        }
        if ( array_key_exists( 'url', $payload ) ) {
            self::update_or_delete_meta( $source_id, self::META_URL, SC_Library_Citation_Source_Reliability::canonical_url( $payload['url'] ) );
        }
        if ( array_key_exists( 'archive_url', $payload ) ) {
            self::update_or_delete_meta( $source_id, self::META_ARCHIVE_URL, esc_url_raw( $payload['archive_url'] ) );
        }
        foreach ( array( 'publication_date' => self::META_PUBLICATION_DATE, 'access_date' => self::META_ACCESS_DATE ) as $field => $meta_key ) {
            if ( array_key_exists( $field, $payload ) ) {
                $date = sanitize_text_field( $payload[ $field ] );
                self::update_or_delete_meta( $source_id, $meta_key, self::valid_date( $date ) ? $date : '' );
            }
        }

        if ( array_key_exists( 'attachment_id', $payload ) ) {
            $attachment_id = absint( $payload['attachment_id'] );
            if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
                update_post_meta( $source_id, self::META_ATTACHMENT_ID, $attachment_id );
            } else {
                delete_post_meta( $source_id, self::META_ATTACHMENT_ID );
            }
        }

        if ( array_key_exists( 'metadata_verified', $payload ) ) {
            update_post_meta( $source_id, self::META_VERIFIED, rest_sanitize_boolean( $payload['metadata_verified'] ) ? '1' : '0' );
            if ( rest_sanitize_boolean( $payload['metadata_verified'] ) ) {
                update_post_meta( $source_id, self::META_LAST_VERIFIED, current_time( 'mysql' ) );
            }
        }
        if ( array_key_exists( 'peer_reviewed', $payload ) ) {
            update_post_meta( $source_id, self::META_PEER_REVIEWED, rest_sanitize_boolean( $payload['peer_reviewed'] ) ? '1' : '0' );
        }

        if ( array_key_exists( 'source_type', $payload ) ) {
            $type = sanitize_title( $payload['source_type'] );
            if ( $type && term_exists( $type, self::SOURCE_TYPE_TAXONOMY ) ) {
                wp_set_object_terms( $source_id, array( $type ), self::SOURCE_TYPE_TAXONOMY, false );
            }
        }
        if ( array_key_exists( 'topics', $payload ) ) {
            $topics = array_map( 'sanitize_text_field', (array) $payload['topics'] );
            wp_set_object_terms( $source_id, array_values( array_filter( $topics ) ), self::SOURCE_TOPIC_TAXONOMY, false );
        }

        if ( array_key_exists( 'related_document_ids', $payload ) ) {
            self::update_id_meta(
                $source_id,
                self::META_RELATED_DOCUMENT_IDS,
                self::sanitize_post_ids( (array) $payload['related_document_ids'], 'sc_foundation_doc' )
            );
        }
        if ( array_key_exists( 'project_ids', $payload ) ) {
            $projects = self::sanitize_post_ids( (array) $payload['project_ids'], self::PROJECT_POST_TYPE );
            self::update_id_meta( $source_id, self::META_PROJECT_IDS, $projects );
            $this->sync_source_projects( $source_id, $old_projects, $projects );
        }

        $doi_value = self::normalize_doi( get_post_meta( $source_id, self::META_DOI, true ) );
        $isbn_value = self::normalize_isbn( get_post_meta( $source_id, self::META_ISBN, true ) );
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_DOI, SC_Library_Citation_Source_Reliability::valid_doi( $doi_value ) ? $doi_value : '' );
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_ISBN, SC_Library_Citation_Source_Reliability::valid_isbn( $isbn_value ) ? $isbn_value : '' );
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_URL, self::normalize_url( get_post_meta( $source_id, self::META_URL, true ) ) );

        $group = self::author_year_key( $source_id );
        self::update_or_delete_meta( $source_id, self::META_AUTHOR_YEAR_KEY, $group );
        self::update_or_delete_meta( $source_id, self::META_FINGERPRINT, self::source_fingerprint( $source_id ) );
        if ( $old_group && $old_group !== $group ) {
            self::recalculate_year_suffixes( $old_group );
        }
        if ( $group ) {
            self::recalculate_year_suffixes( $group );
        } else {
            self::update_citation_key( $source_id );
        }

        $duplicates = self::find_duplicate_sources( $source_id );
        self::update_duplicate_relationships( $source_id, $old_duplicates, $duplicates );
        self::update_id_meta( $source_id, self::META_DUPLICATES, $duplicates );
        return true;
    }

    public function rest_source_citation( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        $style = sanitize_key( $request->get_param( 'style' ) ?: 'harvard' );
        $mode = sanitize_key( $request->get_param( 'mode' ) ?: 'reference' );
        $locator = sanitize_text_field( $request->get_param( 'locator' ) ?: '' );

        return rest_ensure_response(
            array(
                'source_id'     => $source_id,
                'style'         => $style,
                'mode'          => $mode,
                'locator'       => $locator,
                'citation'      => self::format_citation( $source_id, $style, $mode, $locator ),
                'reference'     => self::format_citation( $source_id, $style, 'reference' ),
                'reference_html'=> self::format_citation( $source_id, $style, 'reference-html' ),
                'in_text'       => self::format_citation( $source_id, $style, 'in-text', $locator ),
                'citation_key'  => get_post_meta( $source_id, self::META_CITATION_KEY, true ),
                'schema'        => self::STYLE_SCHEMA,
            )
        );
    }

    public function rest_project_bibliography( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $project = get_post( $project_id );
        $style = sanitize_key( get_post_meta( $project_id, self::META_PROJECT_STYLE, true ) ?: 'harvard' );
        $include_private = current_user_can( 'edit_post', $project_id );
        $source_ids = self::project_source_ids( $project_id, $include_private );
        $entries = array();

        foreach ( $source_ids as $source_id ) {
            $entries[] = array(
                'source_id'      => $source_id,
                'title'          => get_the_title( $source_id ),
                'citation_key'   => get_post_meta( $source_id, self::META_CITATION_KEY, true ),
                'citation'       => self::format_citation( $source_id, $style, 'reference' ),
                'citation_html'  => self::format_citation( $source_id, $style, 'reference-html' ),
                'in_text'        => self::format_citation( $source_id, $style, 'in-text' ),
                'source_url'     => 'publish' === get_post_status( $source_id ) ? get_permalink( $source_id ) : '',
            );
        }

        return rest_ensure_response(
            array(
                'schema'            => self::PROJECT_SCHEMA,
                'project_id'        => $project_id,
                'project_title'     => $project ? $project->post_title : '',
                'project_code'      => get_post_meta( $project_id, self::META_PROJECT_CODE, true ),
                'citation_style'    => $style,
                'bibliography_title'=> get_post_meta( $project_id, self::META_PROJECT_BIBLIOGRAPHY_TITLE, true ) ?: __( 'References', 'sustainable-catalyst-library' ),
                'source_count'      => count( $entries ),
                'entries'           => $entries,
            )
        );
    }

    public function rest_update_project_sources( WP_REST_Request $request ) {
        $rate = SC_Library_Citation_Source_Reliability::enforce_write_rate_limit( $request, 'update-project-sources' );
        if ( is_wp_error( $rate ) ) { return $rate; }
        $project_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : $request->get_params();
        $old_sources = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
        $source_ids = self::sanitize_post_ids( (array) ( $payload['source_ids'] ?? array() ), self::SOURCE_POST_TYPE );
        self::update_id_meta( $project_id, self::META_PROJECT_SOURCE_IDS, $source_ids );
        $this->sync_project_sources( $project_id, $old_sources, $source_ids );

        return rest_ensure_response(
            array(
                'project_id' => $project_id,
                'source_ids' => $source_ids,
                'count'      => count( $source_ids ),
            )
        );
    }

    public function rest_can_read_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return 'publish' === get_post_status( $source_id ) || current_user_can( 'edit_post', $source_id );
    }

    public function rest_can_edit_source( WP_REST_Request $request ) {
        $source_id = absint( $request['id'] );
        return $source_id && self::SOURCE_POST_TYPE === get_post_type( $source_id ) && current_user_can( 'edit_post', $source_id );
    }

    public function rest_can_read_project( WP_REST_Request $request ) {
        return self::can_read_project( absint( $request['id'] ) );
    }

    private function rest_source_payload( $source_id, $include_private ) {
        $data = self::get_source_data( $source_id, $include_private );
        if ( ! $data ) {
            return array();
        }
        $data['_links'] = array(
            'self' => array(
                array( 'href' => rest_url( self::API_NAMESPACE . '/sources/' . $source_id ) ),
            ),
            'citation' => array(
                array( 'href' => rest_url( self::API_NAMESPACE . '/sources/' . $source_id . '/citation' ) ),
            ),
        );
        return $data;
    }

    private static function resolve_project( $project ) {
        if ( $project instanceof WP_Post && self::PROJECT_POST_TYPE === $project->post_type ) {
            return $project;
        }
        if ( is_numeric( $project ) ) {
            $post = get_post( absint( $project ) );
            return $post && self::PROJECT_POST_TYPE === $post->post_type ? $post : null;
        }

        $slug = sanitize_title( (string) $project );
        if ( ! $slug ) {
            return null;
        }
        $posts = get_posts(
            array(
                'post_type'      => self::PROJECT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'name'           => $slug,
            )
        );
        return $posts ? $posts[0] : null;
    }

    private static function project_is_public( $project_id ) {
        return self::PROJECT_POST_TYPE === get_post_type( $project_id )
            && 'publish' === get_post_status( $project_id )
            && 'public' === get_post_meta( $project_id, self::META_PROJECT_VISIBILITY, true );
    }

    private static function can_read_project( $project_id ) {
        return self::project_is_public( $project_id ) || current_user_can( 'edit_post', $project_id );
    }

    private static function project_source_ids( $project_id, $include_private = false ) {
        $ids = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
        $ids = array_values(
            array_filter(
                $ids,
                static function ( $source_id ) use ( $include_private ) {
                    return self::SOURCE_POST_TYPE === get_post_type( $source_id )
                        && ( $include_private || 'publish' === get_post_status( $source_id ) );
                }
            )
        );
        usort(
            $ids,
            static function ( $left, $right ) {
                return strnatcasecmp( self::bibliography_sort_key( $left ), self::bibliography_sort_key( $right ) );
            }
        );
        return $ids;
    }

    public static function rebuild_source_indexes( $source_id ) {
        $source_id = absint( $source_id );
        if ( ! $source_id || self::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) { return false; }
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_DOI, SC_Library_Citation_Source_Reliability::valid_doi( get_post_meta( $source_id, self::META_DOI, true ) ) ? self::normalize_doi( get_post_meta( $source_id, self::META_DOI, true ) ) : '' );
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_ISBN, SC_Library_Citation_Source_Reliability::valid_isbn( get_post_meta( $source_id, self::META_ISBN, true ) ) ? self::normalize_isbn( get_post_meta( $source_id, self::META_ISBN, true ) ) : '' );
        self::update_or_delete_meta( $source_id, self::META_NORMALIZED_URL, self::normalize_url( get_post_meta( $source_id, self::META_URL, true ) ) );
        $old_group = (string) get_post_meta( $source_id, self::META_AUTHOR_YEAR_KEY, true );
        $group = self::author_year_key( $source_id );
        self::update_or_delete_meta( $source_id, self::META_AUTHOR_YEAR_KEY, $group );
        self::update_or_delete_meta( $source_id, self::META_FINGERPRINT, self::source_fingerprint( $source_id ) );
        if ( $old_group && $old_group !== $group ) { self::recalculate_year_suffixes( $old_group ); }
        if ( $group ) { self::recalculate_year_suffixes( $group ); } else { self::update_citation_key( $source_id ); }
        $old_duplicates = self::id_list( get_post_meta( $source_id, self::META_DUPLICATES, true ) );
        $duplicates = self::find_duplicate_sources( $source_id );
        self::update_duplicate_relationships( $source_id, $old_duplicates, $duplicates );
        self::update_id_meta( $source_id, self::META_DUPLICATES, $duplicates );
        delete_post_meta( $source_id, SC_Library_Citation_Source_Reliability::META_CITATION_CACHE );
        return true;
    }

    public static function restore_source_snapshot_data( $source_id, $data ) {
        $old_projects = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
        $map = array(
            'authors' => self::META_AUTHORS, 'organization' => self::META_ORGANIZATION, 'organization_short' => self::META_ORGANIZATION_SHORT,
            'editors' => self::META_EDITORS, 'year' => self::META_YEAR, 'publication_date' => self::META_PUBLICATION_DATE,
            'access_date' => self::META_ACCESS_DATE, 'container_title' => self::META_CONTAINER_TITLE, 'publisher' => self::META_PUBLISHER,
            'place' => self::META_PLACE, 'edition' => self::META_EDITION, 'volume' => self::META_VOLUME, 'issue' => self::META_ISSUE,
            'pages' => self::META_PAGES, 'chapter' => self::META_CHAPTER, 'report_number' => self::META_REPORT_NUMBER,
            'standard_number' => self::META_STANDARD_NUMBER, 'jurisdiction' => self::META_JURISDICTION, 'doi' => self::META_DOI,
            'isbn' => self::META_ISBN, 'pmid' => self::META_PMID, 'url' => self::META_URL, 'archive_url' => self::META_ARCHIVE_URL,
            'language' => self::META_LANGUAGE, 'attachment_id' => self::META_ATTACHMENT_ID, 'related_document_ids' => self::META_RELATED_DOCUMENT_IDS,
            'project_ids' => self::META_PROJECT_IDS, 'private_notes' => self::META_NOTES, 'metadata_verified' => self::META_VERIFIED,
            'peer_reviewed' => self::META_PEER_REVIEWED, 'source_level' => self::META_SOURCE_LEVEL, 'full_text_status' => self::META_FULL_TEXT_STATUS,
            'metadata_provenance' => self::META_PROVENANCE,
        );
        foreach ( $map as $field => $meta_key ) {
            if ( in_array( $field, array( 'metadata_verified', 'peer_reviewed' ), true ) && array_key_exists( $field, $data ) ) {
                update_post_meta( $source_id, $meta_key, ! empty( $data[ $field ] ) ? '1' : '0' );
                continue;
            }
            if ( array_key_exists( $field, $data ) && ! in_array( $data[ $field ], array( '', array(), null ), true ) ) { update_post_meta( $source_id, $meta_key, $data[ $field ] ); }
            else { delete_post_meta( $source_id, $meta_key ); }
        }
        $new_projects = self::id_list( get_post_meta( $source_id, self::META_PROJECT_IDS, true ) );
        foreach ( array_diff( $old_projects, $new_projects ) as $project_id ) {
            $sources = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
            self::update_id_meta( $project_id, self::META_PROJECT_SOURCE_IDS, array_values( array_diff( $sources, array( $source_id ) ) ) );
        }
        foreach ( array_diff( $new_projects, $old_projects ) as $project_id ) {
            if ( self::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) { continue; }
            $sources = self::id_list( get_post_meta( $project_id, self::META_PROJECT_SOURCE_IDS, true ) );
            $sources[] = $source_id;
            self::update_id_meta( $project_id, self::META_PROJECT_SOURCE_IDS, $sources );
        }
        if ( ! empty( $data['source_type'] ) ) { wp_set_object_terms( $source_id, array( sanitize_title( $data['source_type'] ) ), self::SOURCE_TYPE_TAXONOMY, false ); }
        if ( isset( $data['topics'] ) && is_array( $data['topics'] ) ) {
            $topics = array(); foreach ( $data['topics'] as $topic ) { $topics[] = is_array( $topic ) ? ( $topic['slug'] ?? $topic['name'] ?? '' ) : $topic; }
            wp_set_object_terms( $source_id, array_values( array_filter( array_map( 'sanitize_text_field', $topics ) ) ), self::SOURCE_TOPIC_TAXONOMY, false );
        }
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : self::VERSION;
    }
}
