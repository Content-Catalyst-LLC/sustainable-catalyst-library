<?php
/**
 * Connected Research Project and Bibliography Environment.
 *
 * Unifies project briefs, collaborators, Sources, documents, claims, evidence,
 * source discovery, bibliography organization, health, snapshots, activity,
 * public project packets, and multi-format exports.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Connected_Research_Environment {
    public const VERSION = '3.0.1';
    public const API_NAMESPACE = 'sc-library/v1';

    public const WORKSPACE_SCHEMA = 'sc-library-connected-project/1.0';
    public const SOURCE_ENTRY_SCHEMA = 'sc-library-project-source-entry/1.0';
    public const BIBLIOGRAPHY_SCHEMA = 'sc-library-project-bibliography/1.0';
    public const SNAPSHOT_SCHEMA = 'sc-library-bibliography-snapshot/1.0';
    public const EXPORT_SCHEMA = 'sc-library-project-export/1.0';

    public const META_RESEARCH_QUESTION = '_sc_project_research_question';
    public const META_OBJECTIVES = '_sc_project_objectives';
    public const META_METHODS = '_sc_project_methods';
    public const META_SCOPE = '_sc_project_scope';
    public const META_START_DATE = '_sc_project_start_date';
    public const META_TARGET_DATE = '_sc_project_target_date';
    public const META_TEAM = '_sc_project_team';
    public const META_DOCUMENT_IDS = '_sc_project_document_ids';
    public const META_SOURCE_ENTRIES = '_sc_project_source_entries';
    public const META_SECTIONS = '_sc_project_bibliography_sections';
    public const META_SORT = '_sc_project_bibliography_sort';
    public const META_SNAPSHOTS = '_sc_project_bibliography_snapshots';
    public const META_ACTIVITY = '_sc_project_activity';
    public const META_HEALTH = '_sc_project_workspace_health';

    public const MAX_SOURCE_ENTRIES = 1000;
    public const MAX_DOCUMENTS = 500;
    public const MAX_TEAM = 100;
    public const MAX_SECTIONS = 50;
    public const MAX_SNAPSHOTS = 20;
    public const MAX_ACTIVITY = 200;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_migrate_projects' ), 980 );
        add_action( 'admin_menu', array( $this, 'register_workspace_page' ), 252 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 95 );
        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, array( $this, 'save_project_environment' ), 75, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 95 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'wp_ajax_sc_library_v300_create_snapshot', array( $this, 'ajax_create_snapshot' ) );
        add_action( 'wp_ajax_sc_library_v300_delete_snapshot', array( $this, 'ajax_delete_snapshot' ) );

        add_shortcode( 'sc_connected_research_project', array( $this, 'shortcode_project_environment' ) );
        add_shortcode( 'sc_project_bibliography_environment', array( $this, 'shortcode_bibliography_environment' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_workspace_page() {
        add_submenu_page(
            'sc-library',
            __( 'Research Environment', 'sustainable-catalyst-library' ),
            __( 'Research Environment', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-research-environment',
            array( $this, 'render_workspace_page' )
        );
    }

    public function register_meta_boxes() {
        $project_type = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE;
        add_meta_box(
            'sc-connected-project-brief',
            __( 'Connected Project Brief', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_brief_box' ),
            $project_type,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-connected-project-sources',
            __( 'Source Organization and Bibliography', 'sustainable-catalyst-library' ),
            array( $this, 'render_source_entries_box' ),
            $project_type,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-connected-project-records',
            __( 'Documents, Team, and Connected Records', 'sustainable-catalyst-library' ),
            array( $this, 'render_connected_records_box' ),
            $project_type,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-connected-project-health',
            __( 'Project and Bibliography Health', 'sustainable-catalyst-library' ),
            array( $this, 'render_health_box' ),
            $project_type,
            'side',
            'high'
        );
        add_meta_box(
            'sc-connected-project-snapshots',
            __( 'Bibliography Snapshots', 'sustainable-catalyst-library' ),
            array( $this, 'render_snapshots_box' ),
            $project_type,
            'side',
            'default'
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_project = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === $screen->post_type;
        $is_workspace = false !== strpos( (string) $screen->id, 'sc-library-research-environment' );
        if ( ! $is_project && ! $is_workspace ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-connected-research',
            SC_LIBRARY_URL . 'assets/css/sc-library-connected-research.css',
            array( 'sc-library-citation-manager', 'sc-library-evidence-claims' ),
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-connected-research',
            SC_LIBRARY_URL . 'assets/js/sc-library-connected-research.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-connected-research',
            'SCLibraryConnectedResearch',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_connected_research_v300' ),
                'strings' => array(
                    'working'         => __( 'Working…', 'sustainable-catalyst-library' ),
                    'snapshotCreated' => __( 'Bibliography snapshot created.', 'sustainable-catalyst-library' ),
                    'snapshotDeleted' => __( 'Bibliography snapshot deleted.', 'sustainable-catalyst-library' ),
                    'copied'          => __( 'Copied.', 'sustainable-catalyst-library' ),
                    'copyFailed'      => __( 'Copy failed.', 'sustainable-catalyst-library' ),
                    'confirmDelete'   => __( 'Delete this bibliography snapshot?', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-connected-research',
            SC_LIBRARY_URL . 'assets/css/sc-library-connected-research.css',
            array( 'sc-library-citation-manager', 'sc-library-evidence-claims' ),
            self::VERSION
        );
        wp_register_script(
            'sc-library-connected-research',
            SC_LIBRARY_URL . 'assets/js/sc-library-connected-research.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function render_project_brief_box( $post ) {
        wp_nonce_field( 'sc_library_save_connected_project_' . $post->ID, 'sc_library_connected_project_nonce' );
        $objectives = self::text_list( get_post_meta( $post->ID, self::META_OBJECTIVES, true ) );
        ?>
        <div class="sc-connected-fields">
            <label>
                <strong><?php esc_html_e( 'Research question', 'sustainable-catalyst-library' ); ?></strong>
                <textarea name="sc_project_research_question" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_RESEARCH_QUESTION, true ) ); ?></textarea>
            </label>
            <div class="sc-connected-two-column">
                <label>
                    <strong><?php esc_html_e( 'Research objectives', 'sustainable-catalyst-library' ); ?></strong>
                    <textarea name="sc_project_objectives" rows="7" placeholder="<?php esc_attr_e( 'One objective per line', 'sustainable-catalyst-library' ); ?>"><?php echo esc_textarea( implode( "\n", $objectives ) ); ?></textarea>
                </label>
                <label>
                    <strong><?php esc_html_e( 'Methods and research approach', 'sustainable-catalyst-library' ); ?></strong>
                    <textarea name="sc_project_methods" rows="7"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_METHODS, true ) ); ?></textarea>
                </label>
            </div>
            <label>
                <strong><?php esc_html_e( 'Scope, boundaries, and exclusions', 'sustainable-catalyst-library' ); ?></strong>
                <textarea name="sc_project_scope" rows="5"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_SCOPE, true ) ); ?></textarea>
            </label>
            <div class="sc-connected-date-grid">
                <label><strong><?php esc_html_e( 'Start date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_project_start_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_START_DATE, true ) ); ?>"></label>
                <label><strong><?php esc_html_e( 'Target date', 'sustainable-catalyst-library' ); ?></strong><input type="date" name="sc_project_target_date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_TARGET_DATE, true ) ); ?>"></label>
            </div>
        </div>
        <?php
    }

    public function render_source_entries_box( $post ) {
        $entries = self::source_entries( $post->ID, true );
        $sections = self::bibliography_sections( $post->ID );
        $sources = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <div class="sc-project-source-environment" data-sc-source-environment>
            <p><?php esc_html_e( 'Organize Sources without breaking the existing project bibliography. Included Sources appear in the live bibliography; Candidate and Excluded records remain available for research review.', 'sustainable-catalyst-library' ); ?></p>

            <h3><?php esc_html_e( 'Bibliography sections', 'sustainable-catalyst-library' ); ?></h3>
            <div class="sc-project-section-rows" data-sc-section-rows>
                <?php foreach ( $sections as $index => $section ) : ?>
                    <div class="sc-project-section-row" data-sc-section-row>
                        <input type="text" name="sc_project_sections[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $section['title'] ); ?>" aria-label="<?php esc_attr_e( 'Section title', 'sustainable-catalyst-library' ); ?>">
                        <input type="text" name="sc_project_sections[<?php echo esc_attr( $index ); ?>][description]" value="<?php echo esc_attr( $section['description'] ); ?>" aria-label="<?php esc_attr_e( 'Section description', 'sustainable-catalyst-library' ); ?>">
                        <input type="hidden" name="sc_project_sections[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $section['slug'] ); ?>">
                        <button type="button" class="button" data-sc-remove-section><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" data-sc-add-section><?php esc_html_e( 'Add Bibliography Section', 'sustainable-catalyst-library' ); ?></button>
            <template data-sc-section-template>
                <div class="sc-project-section-row" data-sc-section-row>
                    <input type="text" data-name="title" placeholder="<?php esc_attr_e( 'Section title', 'sustainable-catalyst-library' ); ?>">
                    <input type="text" data-name="description" placeholder="<?php esc_attr_e( 'Description', 'sustainable-catalyst-library' ); ?>">
                    <input type="hidden" data-name="slug">
                    <button type="button" class="button" data-sc-remove-section><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                </div>
            </template>

            <h3><?php esc_html_e( 'Project Sources', 'sustainable-catalyst-library' ); ?></h3>
            <div class="sc-project-source-rows" data-sc-source-rows>
                <?php
                $rows = $entries ?: array(
                    array(
                        'source_id'  => 0,
                        'role'       => 'background',
                        'section'    => $sections[0]['slug'],
                        'inclusion'  => 'candidate',
                        'priority'   => 3,
                        'annotation' => '',
                    )
                );
                foreach ( $rows as $index => $entry ) :
                    ?>
                    <div class="sc-project-source-row" data-sc-source-row>
                        <label><span><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></span><select name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][source_id]"><option value="0"><?php esc_html_e( 'Select Source', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>" <?php selected( absint( $entry['source_id'] ), $source->ID ); ?>><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Role', 'sustainable-catalyst-library' ); ?></span><select name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][role]"><?php foreach ( self::source_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $entry['role'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Section', 'sustainable-catalyst-library' ); ?></span><select name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][section]"><?php foreach ( $sections as $section ) : ?><option value="<?php echo esc_attr( $section['slug'] ); ?>" <?php selected( $entry['section'], $section['slug'] ); ?>><?php echo esc_html( $section['title'] ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></span><select name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][inclusion]"><?php foreach ( self::inclusion_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $entry['inclusion'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e( 'Priority', 'sustainable-catalyst-library' ); ?></span><select name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][priority]"><?php for ( $priority = 1; $priority <= 5; $priority++ ) : ?><option value="<?php echo esc_attr( $priority ); ?>" <?php selected( absint( $entry['priority'] ), $priority ); ?>><?php echo esc_html( $priority ); ?></option><?php endfor; ?></select></label>
                        <label class="sc-project-source-row__annotation"><span><?php esc_html_e( 'Project annotation', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_project_source_entries[<?php echo esc_attr( $index ); ?>][annotation]" value="<?php echo esc_attr( $entry['annotation'] ); ?>"></label>
                        <button type="button" class="button" data-sc-remove-source><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" data-sc-add-source><?php esc_html_e( 'Add Project Source', 'sustainable-catalyst-library' ); ?></button>
            <template data-sc-source-template>
                <div class="sc-project-source-row" data-sc-source-row>
                    <label><span><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></span><select data-name="source_id"><option value="0"><?php esc_html_e( 'Select Source', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source->ID ); ?>"><?php echo esc_html( $source->post_title ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Role', 'sustainable-catalyst-library' ); ?></span><select data-name="role"><?php foreach ( self::source_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Section', 'sustainable-catalyst-library' ); ?></span><select data-name="section"><?php foreach ( $sections as $section ) : ?><option value="<?php echo esc_attr( $section['slug'] ); ?>"><?php echo esc_html( $section['title'] ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></span><select data-name="inclusion"><?php foreach ( self::inclusion_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Priority', 'sustainable-catalyst-library' ); ?></span><select data-name="priority"><?php for ( $priority = 1; $priority <= 5; $priority++ ) : ?><option value="<?php echo esc_attr( $priority ); ?>" <?php selected( 3, $priority ); ?>><?php echo esc_html( $priority ); ?></option><?php endfor; ?></select></label>
                    <label class="sc-project-source-row__annotation"><span><?php esc_html_e( 'Project annotation', 'sustainable-catalyst-library' ); ?></span><input type="text" data-name="annotation"></label>
                    <button type="button" class="button" data-sc-remove-source><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                </div>
            </template>

            <p><label><strong><?php esc_html_e( 'Bibliography sort', 'sustainable-catalyst-library' ); ?></strong> <select name="sc_project_bibliography_sort"><?php foreach ( self::sort_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( get_post_meta( $post->ID, self::META_SORT, true ) ?: 'section-author', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
        </div>
        <?php
    }

    public function render_connected_records_box( $post ) {
        $selected_documents = self::id_list( get_post_meta( $post->ID, self::META_DOCUMENT_IDS, true ) );
        $documents = get_posts(
            array(
                'post_type'      => SC_Library_Foundation_Pages::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $team = self::team_entries( $post->ID );
        $users = get_users(
            array(
                'number'  => 500,
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'fields'  => array( 'ID', 'display_name', 'user_email' ),
            )
        );
        ?>
        <div class="sc-connected-records">
            <div>
                <h3><?php esc_html_e( 'Knowledge Library documents', 'sustainable-catalyst-library' ); ?></h3>
                <div class="sc-project-document-picker">
                    <?php foreach ( $documents as $document ) : ?>
                        <label><input type="checkbox" name="sc_project_document_ids[]" value="<?php echo esc_attr( $document->ID ); ?>" <?php checked( in_array( $document->ID, $selected_documents, true ) ); ?>> <span><?php echo esc_html( $document->post_title ); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sc-project-team-editor" data-sc-team-editor>
                <h3><?php esc_html_e( 'Project team', 'sustainable-catalyst-library' ); ?></h3>
                <div data-sc-team-rows>
                    <?php foreach ( $team as $index => $member ) : ?>
                        <div class="sc-project-team-row" data-sc-team-row>
                            <select name="sc_project_team[<?php echo esc_attr( $index ); ?>][user_id]"><option value="0"><?php esc_html_e( 'Select user', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $member['user_id'], $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select>
                            <select name="sc_project_team[<?php echo esc_attr( $index ); ?>][role]"><?php foreach ( self::team_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $member['role'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                            <button type="button" class="button" data-sc-remove-team-member><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" data-sc-add-team-member><?php esc_html_e( 'Add Team Member', 'sustainable-catalyst-library' ); ?></button>
                <template data-sc-team-template>
                    <div class="sc-project-team-row" data-sc-team-row>
                        <select data-name="user_id"><option value="0"><?php esc_html_e( 'Select user', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select>
                        <select data-name="role"><?php foreach ( self::team_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                        <button type="button" class="button" data-sc-remove-team-member><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                </template>
            </div>
        </div>
        <?php
    }

    public function render_health_box( $post ) {
        $health = self::workspace_health( $post->ID, true );
        ?>
        <div class="sc-project-health">
            <strong class="sc-project-health__score"><?php echo esc_html( $health['readiness_score'] . '%' ); ?></strong>
            <span><?php esc_html_e( 'bibliography readiness', 'sustainable-catalyst-library' ); ?></span>
            <dl>
                <div><dt><?php esc_html_e( 'Included Sources', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['included_sources'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Verified metadata', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['verified_sources'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Incomplete Sources', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['incomplete_sources'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Duplicate warnings', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['duplicate_warnings'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['claims'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Evidence Notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['evidence_notes'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $health['documents'] ); ?></dd></div>
            </dl>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-research-environment&project_id=' . $post->ID ) ); ?>"><?php esc_html_e( 'Open Research Environment', 'sustainable-catalyst-library' ); ?></a></p>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-source-discovery&project_id=' . $post->ID ) ); ?>"><?php esc_html_e( 'Discover Sources for Project', 'sustainable-catalyst-library' ); ?></a></p>
        </div>
        <?php
    }

    public function render_snapshots_box( $post ) {
        $snapshots = self::snapshots( $post->ID );
        ?>
        <div class="sc-bibliography-snapshots" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
            <input type="text" data-sc-snapshot-label placeholder="<?php esc_attr_e( 'Snapshot label', 'sustainable-catalyst-library' ); ?>">
            <button type="button" class="button" data-sc-create-snapshot><?php esc_html_e( 'Create Snapshot', 'sustainable-catalyst-library' ); ?></button>
            <div data-sc-snapshot-status aria-live="polite"></div>
            <?php if ( $snapshots ) : ?>
                <ol>
                    <?php foreach ( array_reverse( $snapshots ) as $snapshot ) : ?>
                        <li data-snapshot-id="<?php echo esc_attr( $snapshot['id'] ); ?>">
                            <strong><?php echo esc_html( $snapshot['label'] ); ?></strong>
                            <span><?php echo esc_html( $snapshot['created_at'] . ' · ' . count( $snapshot['entries'] ) . ' entries' ); ?></span>
                            <button type="button" class="button-link-delete" data-sc-delete-snapshot="<?php echo esc_attr( $snapshot['id'] ); ?>"><?php esc_html_e( 'Delete', 'sustainable-catalyst-library' ); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?><p><?php esc_html_e( 'No bibliography snapshots yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
        </div>
        <?php
    }

    public function save_project_environment( $post_id, $post, $update ) {
        if ( self::$saving || ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_connected_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_connected_project_nonce'] ) ), 'sc_library_save_connected_project_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saving = true;
        $old_entries = self::source_entries( $post_id, true );

        self::update_or_delete_meta( $post_id, self::META_RESEARCH_QUESTION, sanitize_textarea_field( wp_unslash( $_POST['sc_project_research_question'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_OBJECTIVES, self::text_list( wp_unslash( $_POST['sc_project_objectives'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_METHODS, sanitize_textarea_field( wp_unslash( $_POST['sc_project_methods'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_SCOPE, sanitize_textarea_field( wp_unslash( $_POST['sc_project_scope'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_START_DATE, self::sanitize_date( wp_unslash( $_POST['sc_project_start_date'] ?? '' ) ) );
        self::update_or_delete_meta( $post_id, self::META_TARGET_DATE, self::sanitize_date( wp_unslash( $_POST['sc_project_target_date'] ?? '' ) ) );

        $sections = self::sanitize_sections( wp_unslash( $_POST['sc_project_sections'] ?? array() ) );
        update_post_meta( $post_id, self::META_SECTIONS, $sections );

        $entries = self::sanitize_source_entries(
            wp_unslash( $_POST['sc_project_source_entries'] ?? array() ),
            $sections,
            $old_entries
        );
        self::save_source_entries( $post_id, $entries, $old_entries );

        $documents = self::validated_ids(
            wp_unslash( $_POST['sc_project_document_ids'] ?? array() ),
            SC_Library_Foundation_Pages::POST_TYPE,
            self::MAX_DOCUMENTS
        );
        self::update_id_meta( $post_id, self::META_DOCUMENT_IDS, $documents );

        $team = self::sanitize_team( wp_unslash( $_POST['sc_project_team'] ?? array() ) );
        self::update_or_delete_meta( $post_id, self::META_TEAM, $team );

        $sort = sanitize_key( wp_unslash( $_POST['sc_project_bibliography_sort'] ?? 'section-author' ) );
        self::update_or_delete_meta( $post_id, self::META_SORT, array_key_exists( $sort, self::sort_options() ) ? $sort : 'section-author' );

        $health = self::workspace_health( $post_id, false );
        update_post_meta( $post_id, self::META_HEALTH, $health );
        self::append_activity(
            $post_id,
            'project-updated',
            __( 'Project environment updated.', 'sustainable-catalyst-library' ),
            array(
                'source_count' => count( $entries ),
                'documents'    => count( $documents ),
                'team'         => count( $team ),
            )
        );
        do_action( 'sc_library_connected_research_saved', $post_id );
        self::$saving = false;
    }

    private static function save_source_entries( $project_id, $entries, $old_entries ) {
        if ( $entries ) {
            update_post_meta( $project_id, self::META_SOURCE_ENTRIES, $entries );
        } else {
            delete_post_meta( $project_id, self::META_SOURCE_ENTRIES );
        }

        $source_ids = array_values(
            array_unique(
                array_map(
                    static function ( $entry ) {
                        return absint( $entry['source_id'] );
                    },
                    $entries
                )
            )
        );
        $old_source_ids = self::id_list( get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true ) );
        self::update_id_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, $source_ids );

        foreach ( array_values( array_unique( array_merge( $old_source_ids, $source_ids ) ) ) as $source_id ) {
            if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                continue;
            }
            $projects = self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, true ) );
            if ( in_array( $source_id, $source_ids, true ) ) {
                $projects[] = $project_id;
            } else {
                $projects = array_values( array_diff( $projects, array( $project_id ) ) );
            }
            self::update_id_meta( $source_id, SC_Library_Citation_Source_Manager::META_PROJECT_IDS, array_values( array_unique( $projects ) ) );
        }
    }

    public static function project_data( $project_id, $include_private = false ) {
        $project = get_post( $project_id );
        if ( ! $project || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== $project->post_type ) {
            return array();
        }
        if ( ! $include_private && ! self::project_is_public( $project_id ) ) {
            return array();
        }
        if ( $include_private && ! self::can_read_private_project( $project_id ) ) {
            return array();
        }

        $entries = self::source_entries( $project_id, $include_private );
        $documents = self::document_ids( $project_id, $include_private );
        $claim_ids = class_exists( 'SC_Library_Evidence_Claim_Linking' )
            ? SC_Library_Evidence_Claim_Linking::claim_ids_for_project( $project_id, $include_private )
            : array();
        $evidence_ids = class_exists( 'SC_Library_Evidence_Claim_Linking' )
            ? SC_Library_Evidence_Claim_Linking::evidence_ids_for_project( $project_id, $include_private )
            : array();

        $data = array(
            'schema'             => self::WORKSPACE_SCHEMA,
            'version'            => self::VERSION,
            'id'                 => $project_id,
            'title'              => get_the_title( $project_id ),
            'description'        => wp_strip_all_tags( $project->post_content ),
            'summary'            => wp_strip_all_tags( $project->post_excerpt ),
            'project_code'       => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_CODE, true ),
            'visibility'         => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true ),
            'status'             => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_STATUS, true ),
            'citation_style'     => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_STYLE, true ) ?: 'harvard',
            'bibliography_title' => (string) get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_BIBLIOGRAPHY_TITLE, true ) ?: __( 'References', 'sustainable-catalyst-library' ),
            'research_question'  => (string) get_post_meta( $project_id, self::META_RESEARCH_QUESTION, true ),
            'objectives'         => self::text_list( get_post_meta( $project_id, self::META_OBJECTIVES, true ) ),
            'methods'            => (string) get_post_meta( $project_id, self::META_METHODS, true ),
            'scope'              => (string) get_post_meta( $project_id, self::META_SCOPE, true ),
            'start_date'         => (string) get_post_meta( $project_id, self::META_START_DATE, true ),
            'target_date'        => (string) get_post_meta( $project_id, self::META_TARGET_DATE, true ),
            'sections'           => self::bibliography_sections( $project_id ),
            'source_entries'     => $entries,
            'document_ids'       => $documents,
            'claim_ids'          => array_map( 'absint', $claim_ids ),
            'evidence_ids'       => array_map( 'absint', $evidence_ids ),
            'health'             => self::workspace_health( $project_id, $include_private ),
            'public'             => self::project_is_public( $project_id ),
            'modified_gmt'       => get_post_modified_time( 'c', true, $project_id ),
        );

        if ( $include_private ) {
            $data['team'] = self::team_entries( $project_id );
            $data['snapshots'] = self::snapshots( $project_id );
            $data['activity'] = self::activity( $project_id );
        }
        return $data;
    }

    public static function source_entries( $project_id, $include_private = false ) {
        $raw = get_post_meta( $project_id, self::META_SOURCE_ENTRIES, true );
        $raw = is_array( $raw ) ? $raw : array();
        if ( ! $raw ) {
            $legacy = self::id_list( get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true ) );
            $default_section = self::bibliography_sections( $project_id )[0]['slug'];
            foreach ( $legacy as $source_id ) {
                $raw[] = array(
                    'schema'     => self::SOURCE_ENTRY_SCHEMA,
                    'source_id'  => $source_id,
                    'role'       => 'background',
                    'section'    => $default_section,
                    'inclusion'  => 'included',
                    'priority'   => 3,
                    'annotation' => '',
                    'added_at'   => '',
                    'added_by'   => 0,
                    'updated_at' => '',
                    'updated_by' => 0,
                );
            }
        }

        $entries = array();
        foreach ( $raw as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $source_id = absint( $entry['source_id'] ?? 0 );
            if ( ! $source_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                continue;
            }
            if ( ! $include_private && 'publish' !== get_post_status( $source_id ) ) {
                continue;
            }
            $source = SC_Library_Citation_Source_Manager::get_source_data( $source_id, $include_private && current_user_can( 'edit_post', $source_id ) );
            if ( ! $source ) {
                continue;
            }
            $entries[] = array(
                'schema'        => self::SOURCE_ENTRY_SCHEMA,
                'source_id'     => $source_id,
                'role'          => self::allowed_value( $entry['role'] ?? 'background', self::source_role_options(), 'background' ),
                'section'       => sanitize_title( $entry['section'] ?? 'core-sources' ),
                'inclusion'     => self::allowed_value( $entry['inclusion'] ?? 'included', self::inclusion_options(), 'included' ),
                'priority'      => max( 1, min( 5, absint( $entry['priority'] ?? 3 ) ) ),
                'annotation'    => sanitize_text_field( $entry['annotation'] ?? '' ),
                'added_at'      => sanitize_text_field( $entry['added_at'] ?? '' ),
                'added_by'      => absint( $entry['added_by'] ?? 0 ),
                'updated_at'    => sanitize_text_field( $entry['updated_at'] ?? '' ),
                'updated_by'    => absint( $entry['updated_by'] ?? 0 ),
                'source'        => $source,
                'citation'      => SC_Library_Citation_Source_Manager::format_citation( $source_id, get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_STYLE, true ) ?: 'harvard', 'reference' ),
            );
        }
        return self::sort_entries( $entries, get_post_meta( $project_id, self::META_SORT, true ) ?: 'section-author', self::bibliography_sections( $project_id ) );
    }

    public static function bibliography( $project_id, $include_private = false ) {
        $project = get_post( $project_id );
        if ( ! $project || ( $include_private ? ! self::can_read_private_project( $project_id ) : ! self::project_is_public( $project_id ) ) ) {
            return array();
        }
        $sections = self::bibliography_sections( $project_id );
        $included = array_values(
            array_filter(
                self::source_entries( $project_id, $include_private ),
                static function ( $entry ) {
                    return 'included' === $entry['inclusion'];
                }
            )
        );
        $grouped = array();
        foreach ( $sections as $section ) {
            $grouped[ $section['slug'] ] = array(
                'slug'        => $section['slug'],
                'title'       => $section['title'],
                'description' => $section['description'],
                'entries'     => array(),
            );
        }
        foreach ( $included as $entry ) {
            $slug = isset( $grouped[ $entry['section'] ] ) ? $entry['section'] : $sections[0]['slug'];
            $grouped[ $slug ]['entries'][] = $entry;
        }
        $grouped = array_values(
            array_filter(
                $grouped,
                static function ( $section ) {
                    return ! empty( $section['entries'] );
                }
            )
        );

        return array(
            'schema'             => self::BIBLIOGRAPHY_SCHEMA,
            'project_id'         => $project_id,
            'project_title'      => get_the_title( $project_id ),
            'bibliography_title' => get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_BIBLIOGRAPHY_TITLE, true ) ?: __( 'References', 'sustainable-catalyst-library' ),
            'citation_style'     => get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_STYLE, true ) ?: 'harvard',
            'sort'               => get_post_meta( $project_id, self::META_SORT, true ) ?: 'section-author',
            'entry_count'        => count( $included ),
            'sections'           => $grouped,
            'generated_at'       => current_time( 'mysql' ),
            'health'             => self::workspace_health( $project_id, $include_private ),
        );
    }

    public static function workspace_health( $project_id, $include_private = false ) {
        $entries = self::source_entries( $project_id, $include_private );
        $included = array_values( array_filter( $entries, static function ( $entry ) { return 'included' === $entry['inclusion']; } ) );
        $verified = 0;
        $incomplete = 0;
        $duplicates = 0;
        $available = 0;
        foreach ( $included as $entry ) {
            $source = $entry['source'];
            if ( ! empty( $source['metadata_verified'] ) ) {
                $verified++;
            }
            if ( absint( $source['completeness_score'] ?? 0 ) < 80 ) {
                $incomplete++;
            }
            if ( ! empty( $source['duplicate_matches'] ) ) {
                $duplicates += count( $source['duplicate_matches'] );
            }
            if ( in_array( $source['full_text_status'] ?? '', array( 'open-access', 'available', 'owned', 'library-access' ), true ) ) {
                $available++;
            }
        }
        $claims = class_exists( 'SC_Library_Evidence_Claim_Linking' )
            ? count( SC_Library_Evidence_Claim_Linking::claim_ids_for_project( $project_id, $include_private ) )
            : 0;
        $evidence = class_exists( 'SC_Library_Evidence_Claim_Linking' )
            ? count( SC_Library_Evidence_Claim_Linking::evidence_ids_for_project( $project_id, $include_private ) )
            : 0;
        $documents = count( self::document_ids( $project_id, $include_private ) );

        $denominator = max( 1, count( $included ) );
        $metadata_score = round( ( $verified / $denominator ) * 35 );
        $completeness_score = round( ( ( $denominator - $incomplete ) / $denominator ) * 35 );
        $availability_score = round( ( $available / $denominator ) * 15 );
        $connection_score = min( 15, ( $claims ? 5 : 0 ) + ( $evidence ? 5 : 0 ) + ( $documents ? 5 : 0 ) );
        $readiness = count( $included ) ? min( 100, $metadata_score + $completeness_score + $availability_score + $connection_score ) : 0;

        return array(
            'schema'              => self::WORKSPACE_SCHEMA,
            'project_id'          => $project_id,
            'readiness_score'     => $readiness,
            'total_sources'       => count( $entries ),
            'included_sources'    => count( $included ),
            'candidate_sources'   => count( array_filter( $entries, static function ( $entry ) { return 'candidate' === $entry['inclusion']; } ) ),
            'excluded_sources'    => count( array_filter( $entries, static function ( $entry ) { return 'excluded' === $entry['inclusion']; } ) ),
            'verified_sources'    => $verified,
            'incomplete_sources'  => $incomplete,
            'duplicate_warnings'  => $duplicates,
            'accessible_sources'  => $available,
            'claims'              => $claims,
            'evidence_notes'      => $evidence,
            'documents'           => $documents,
            'checked_at'          => current_time( 'mysql' ),
        );
    }

    public static function export_project( $project_id, $format = 'markdown', $include_private = false ) {
        $format = sanitize_key( $format );
        $bibliography = self::bibliography( $project_id, $include_private );
        if ( ! $bibliography ) {
            return new WP_Error( 'project_export_unavailable', __( 'The project bibliography is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $project = self::project_data( $project_id, $include_private );
        $sources = array();
        foreach ( $bibliography['sections'] as $section ) {
            foreach ( $section['entries'] as $entry ) {
                $sources[] = $entry['source'];
            }
        }

        if ( 'json' === $format ) {
            $content = array(
                'schema'       => self::EXPORT_SCHEMA,
                'project'      => $project,
                'bibliography' => $bibliography,
            );
            if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
                $content['evidence_packet'] = SC_Library_Evidence_Claim_Linking::project_packet( $project_id, $include_private );
            }
            return array( 'format' => 'json', 'mime_type' => 'application/json', 'extension' => 'json', 'content' => $content );
        }

        if ( 'bibtex' === $format ) {
            $chunks = array();
            foreach ( $sources as $source ) {
                $chunks[] = self::source_to_bibtex( $source );
            }
            return array( 'format' => 'bibtex', 'mime_type' => 'application/x-bibtex', 'extension' => 'bib', 'content' => implode( "\n\n", array_filter( $chunks ) ) );
        }
        if ( 'ris' === $format ) {
            $chunks = array();
            foreach ( $sources as $source ) {
                $chunks[] = self::source_to_ris( $source );
            }
            return array( 'format' => 'ris', 'mime_type' => 'application/x-research-info-systems', 'extension' => 'ris', 'content' => implode( "\n", array_filter( $chunks ) ) );
        }
        if ( 'csl-json' === $format ) {
            $records = array();
            foreach ( $sources as $source ) {
                $records[] = self::source_to_csl( $source );
            }
            return array( 'format' => 'csl-json', 'mime_type' => 'application/vnd.citationstyles.csl+json', 'extension' => 'json', 'content' => $records );
        }

        $lines = array();
        if ( 'markdown' === $format ) {
            $lines[] = '# ' . $project['title'];
            if ( $project['research_question'] ) {
                $lines[] = '';
                $lines[] = '**Research question:** ' . $project['research_question'];
            }
            $lines[] = '';
            $lines[] = '## ' . $bibliography['bibliography_title'];
            foreach ( $bibliography['sections'] as $section ) {
                $lines[] = '';
                $lines[] = '### ' . $section['title'];
                if ( $section['description'] ) {
                    $lines[] = $section['description'];
                }
                foreach ( $section['entries'] as $entry ) {
                    $lines[] = '- ' . $entry['citation'] . ( $entry['annotation'] ? ' — ' . $entry['annotation'] : '' );
                }
            }
            if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
                $evidence = SC_Library_Evidence_Claim_Linking::project_packet_markdown( $project_id, $include_private );
                if ( $evidence ) {
                    $lines[] = '';
                    $lines[] = $evidence;
                }
            }
            return array( 'format' => 'markdown', 'mime_type' => 'text/markdown', 'extension' => 'md', 'content' => implode( "\n", $lines ) );
        }

        if ( 'html' === $format ) {
            ob_start();
            echo '<article class="sc-project-export">';
            echo '<h1>' . esc_html( $project['title'] ) . '</h1>';
            if ( $project['research_question'] ) {
                echo '<p><strong>' . esc_html__( 'Research question:', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html( $project['research_question'] ) . '</p>';
            }
            echo '<h2>' . esc_html( $bibliography['bibliography_title'] ) . '</h2>';
            foreach ( $bibliography['sections'] as $section ) {
                echo '<section><h3>' . esc_html( $section['title'] ) . '</h3>';
                if ( $section['description'] ) {
                    echo '<p>' . esc_html( $section['description'] ) . '</p>';
                }
                echo '<ol>';
                foreach ( $section['entries'] as $entry ) {
                    echo '<li>' . esc_html( $entry['citation'] );
                    if ( $entry['annotation'] ) {
                        echo '<p>' . esc_html( $entry['annotation'] ) . '</p>';
                    }
                    echo '</li>';
                }
                echo '</ol></section>';
            }
            echo '</article>';
            return array( 'format' => 'html', 'mime_type' => 'text/html', 'extension' => 'html', 'content' => ob_get_clean() );
        }

        foreach ( $bibliography['sections'] as $section ) {
            $lines[] = $section['title'];
            foreach ( $section['entries'] as $entry ) {
                $lines[] = $entry['citation'];
            }
            $lines[] = '';
        }
        return array( 'format' => 'text', 'mime_type' => 'text/plain', 'extension' => 'txt', 'content' => trim( implode( "\n", $lines ) ) );
    }

    public static function attach_source_to_project( $project_id, $source_id, $inclusion = 'candidate', $role = 'background' ) {
        if (
            ! current_user_can( 'edit_post', $project_id )
            || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id )
            || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id )
        ) {
            return new WP_Error( 'project_source_forbidden', __( 'The Source cannot be attached to this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        $old_entries = self::source_entries( $project_id, true );
        foreach ( $old_entries as $entry ) {
            if ( absint( $entry['source_id'] ) === absint( $source_id ) ) {
                if ( ! get_post_meta( $project_id, self::META_SOURCE_ENTRIES, true ) ) {
                    $sections = self::bibliography_sections( $project_id );
                    $persisted = self::sanitize_source_entries( $old_entries, $sections, $old_entries );
                    self::save_source_entries( $project_id, $persisted, $old_entries );
                }
                return $entry;
            }
        }

        $raw = array();
        foreach ( $old_entries as $entry ) {
            $raw[] = array(
                'source_id'  => $entry['source_id'],
                'role'       => $entry['role'],
                'section'    => $entry['section'],
                'inclusion'  => $entry['inclusion'],
                'priority'   => $entry['priority'],
                'annotation' => $entry['annotation'],
            );
        }
        $sections = self::bibliography_sections( $project_id );
        $raw[] = array(
            'source_id'  => $source_id,
            'role'       => self::allowed_value( $role, self::source_role_options(), 'background' ),
            'section'    => $sections[0]['slug'],
            'inclusion'  => self::allowed_value( $inclusion, self::inclusion_options(), 'candidate' ),
            'priority'   => 3,
            'annotation' => '',
        );
        $entries = self::sanitize_source_entries( $raw, $sections, $old_entries );
        self::save_source_entries( $project_id, $entries, $old_entries );
        update_post_meta( $project_id, self::META_HEALTH, self::workspace_health( $project_id, true ) );
        self::append_activity(
            $project_id,
            'source-attached',
            __( 'A Source was attached to the project.', 'sustainable-catalyst-library' ),
            array( 'source_id' => $source_id, 'inclusion' => $inclusion )
        );

        foreach ( $entries as $entry ) {
            if ( absint( $entry['source_id'] ) === absint( $source_id ) ) {
                return $entry;
            }
        }
        return true;
    }

    public static function create_snapshot( $project_id, $label = '' ) {
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'snapshot_forbidden', __( 'You cannot create a snapshot for this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $bibliography = self::bibliography( $project_id, true );
        if ( ! $bibliography ) {
            return new WP_Error( 'snapshot_unavailable', __( 'The project bibliography is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $entries = array();
        foreach ( $bibliography['sections'] as $section ) {
            foreach ( $section['entries'] as $entry ) {
                $entries[] = array(
                    'source_id'    => $entry['source_id'],
                    'citation_key' => $entry['source']['citation_key'] ?? '',
                    'citation'     => $entry['citation'],
                    'section'      => $section['slug'],
                    'role'         => $entry['role'],
                    'annotation'   => $entry['annotation'],
                );
            }
        }
        $snapshot = array(
            'schema'      => self::SNAPSHOT_SCHEMA,
            'id'          => wp_generate_uuid4(),
            'label'       => sanitize_text_field( $label ) ?: sprintf( __( 'Snapshot %s', 'sustainable-catalyst-library' ), current_time( 'Y-m-d H:i' ) ),
            'style'       => $bibliography['citation_style'],
            'sort'        => $bibliography['sort'],
            'entries'     => $entries,
            'hash'        => hash( 'sha256', wp_json_encode( $entries ) ),
            'created_at'  => current_time( 'mysql' ),
            'created_by'  => get_current_user_id(),
        );
        $snapshots = self::snapshots( $project_id );
        $snapshots[] = $snapshot;
        update_post_meta( $project_id, self::META_SNAPSHOTS, array_slice( $snapshots, -self::MAX_SNAPSHOTS ) );
        self::append_activity( $project_id, 'bibliography-snapshot', __( 'Bibliography snapshot created.', 'sustainable-catalyst-library' ), array( 'snapshot_id' => $snapshot['id'], 'entries' => count( $entries ) ) );
        return $snapshot;
    }

    public static function delete_snapshot( $project_id, $snapshot_id ) {
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'snapshot_forbidden', __( 'You cannot delete this snapshot.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $snapshots = self::snapshots( $project_id );
        $before = count( $snapshots );
        $snapshots = array_values(
            array_filter(
                $snapshots,
                static function ( $snapshot ) use ( $snapshot_id ) {
                    return ( $snapshot['id'] ?? '' ) !== $snapshot_id;
                }
            )
        );
        if ( count( $snapshots ) === $before ) {
            return new WP_Error( 'snapshot_not_found', __( 'Bibliography snapshot not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $snapshots ? update_post_meta( $project_id, self::META_SNAPSHOTS, $snapshots ) : delete_post_meta( $project_id, self::META_SNAPSHOTS );
        self::append_activity( $project_id, 'bibliography-snapshot-deleted', __( 'Bibliography snapshot deleted.', 'sustainable-catalyst-library' ), array( 'snapshot_id' => $snapshot_id ) );
        return true;
    }

    public function ajax_create_snapshot() {
        $this->verify_ajax();
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $snapshot = self::create_snapshot( $project_id, wp_unslash( $_POST['label'] ?? '' ) );
        if ( is_wp_error( $snapshot ) ) {
            wp_send_json_error( array( 'message' => $snapshot->get_error_message() ), absint( $snapshot->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'snapshot' => $snapshot, 'message' => __( 'Bibliography snapshot created.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_delete_snapshot() {
        $this->verify_ajax();
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $result = self::delete_snapshot( $project_id, sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ?? '' ) ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'message' => __( 'Bibliography snapshot deleted.', 'sustainable-catalyst-library' ) ) );
    }

    private function verify_ajax() {
        check_ajax_referer( 'sc_library_connected_research_v300', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to change project research records.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    public function render_workspace_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $projects = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 200,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $project_id = absint( wp_unslash( $_GET['project_id'] ?? 0 ) );
        if ( ! $project_id && $projects ) {
            $project_id = absint( $projects[0]->ID );
        }
        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'overview' ) );
        $tabs = array(
            'overview'     => __( 'Overview', 'sustainable-catalyst-library' ),
            'sources'      => __( 'Sources', 'sustainable-catalyst-library' ),
            'bibliography' => __( 'Bibliography', 'sustainable-catalyst-library' ),
            'evidence'     => __( 'Claims and Evidence', 'sustainable-catalyst-library' ),
            'documents'    => __( 'Documents', 'sustainable-catalyst-library' ),
            'exports'      => __( 'Exports', 'sustainable-catalyst-library' ),
        );
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'overview';
        }
        ?>
        <div class="wrap sc-connected-workspace">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge Library v3.0.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Connected Research Project and Bibliography Environment', 'sustainable-catalyst-library' ); ?></h1>
            <form method="get" class="sc-connected-project-selector">
                <input type="hidden" name="page" value="sc-library-research-environment">
                <label><span><?php esc_html_e( 'Research Project', 'sustainable-catalyst-library' ); ?></span><select name="project_id"><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>><?php echo esc_html( $project->post_title ); ?></option><?php endforeach; ?></select></label>
                <button type="submit" class="button"><?php esc_html_e( 'Open Project', 'sustainable-catalyst-library' ); ?></button>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE ) ); ?>"><?php esc_html_e( 'New Project', 'sustainable-catalyst-library' ); ?></a>
            </form>
            <?php if ( ! $project_id || ! current_user_can( 'edit_post', $project_id ) ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'Select a project you can edit.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php else : ?>
                <?php $data = self::project_data( $project_id, true ); $bibliography = self::bibliography( $project_id, true ); ?>
                <nav class="nav-tab-wrapper">
                    <?php foreach ( $tabs as $tab_id => $label ) : ?>
                        <a class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-research-environment&project_id=' . $project_id . '&tab=' . $tab_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="sc-connected-workspace__panel">
                    <?php $this->render_workspace_tab( $tab, $data, $bibliography ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_workspace_tab( $tab, $data, $bibliography ) {
        if ( 'overview' === $tab ) {
            $health = $data['health'];
            ?>
            <header class="sc-connected-project-header">
                <div><p class="sc-connected-kicker"><?php echo esc_html( $data['project_code'] ?: __( 'Research project', 'sustainable-catalyst-library' ) ); ?></p><h2><?php echo esc_html( $data['title'] ); ?></h2><p><?php echo esc_html( $data['research_question'] ?: $data['summary'] ); ?></p></div>
                <div class="sc-connected-score"><strong><?php echo esc_html( $health['readiness_score'] . '%' ); ?></strong><span><?php esc_html_e( 'bibliography readiness', 'sustainable-catalyst-library' ); ?></span></div>
            </header>
            <div class="sc-connected-metrics">
                <article><strong><?php echo esc_html( $health['included_sources'] ); ?></strong><span><?php esc_html_e( 'Included Sources', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $health['candidate_sources'] ); ?></strong><span><?php esc_html_e( 'Candidate Sources', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $health['claims'] ); ?></strong><span><?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $health['evidence_notes'] ); ?></strong><span><?php esc_html_e( 'Evidence Notes', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $health['documents'] ); ?></strong><span><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></span></article>
            </div>
            <div class="sc-connected-two-column">
                <section><h3><?php esc_html_e( 'Objectives', 'sustainable-catalyst-library' ); ?></h3><?php if ( $data['objectives'] ) : ?><ol><?php foreach ( $data['objectives'] as $objective ) : ?><li><?php echo esc_html( $objective ); ?></li><?php endforeach; ?></ol><?php else : ?><p><?php esc_html_e( 'No objectives recorded.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?></section>
                <section><h3><?php esc_html_e( 'Methods and scope', 'sustainable-catalyst-library' ); ?></h3><p><?php echo esc_html( $data['methods'] ?: __( 'No method recorded.', 'sustainable-catalyst-library' ) ); ?></p><p><?php echo esc_html( $data['scope'] ); ?></p></section>
            </div>
            <p><a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $data['id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit Project Environment', 'sustainable-catalyst-library' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-source-discovery&project_id=' . $data['id'] ) ); ?>"><?php esc_html_e( 'Discover and Import Sources', 'sustainable-catalyst-library' ); ?></a></p>
            <?php
            return;
        }

        if ( 'sources' === $tab ) {
            ?>
            <h2><?php esc_html_e( 'Project Source Registry', 'sustainable-catalyst-library' ); ?></h2>
            <table class="widefat striped sc-connected-source-table"><thead><tr><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Role', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Section', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Completeness', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Annotation', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody>
            <?php foreach ( $data['source_entries'] as $entry ) : ?><tr><td><a href="<?php echo esc_url( get_edit_post_link( $entry['source_id'], 'raw' ) ); ?>"><?php echo esc_html( $entry['source']['title'] ); ?></a><small><?php echo esc_html( $entry['citation'] ); ?></small></td><td><?php echo esc_html( self::source_role_options()[ $entry['role'] ] ); ?></td><td><?php echo esc_html( self::section_title( $data['sections'], $entry['section'] ) ); ?></td><td><?php echo esc_html( ucfirst( $entry['inclusion'] ) ); ?></td><td><?php echo esc_html( absint( $entry['source']['completeness_score'] ) . '%' ); ?></td><td><?php echo esc_html( $entry['annotation'] ); ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <?php
            return;
        }

        if ( 'bibliography' === $tab ) {
            echo self::render_bibliography_html( $bibliography, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if ( 'evidence' === $tab ) {
            if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
                echo do_shortcode( '[sc_project_evidence project="' . absint( $data['id'] ) . '" include_private="true"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            return;
        }

        if ( 'documents' === $tab ) {
            ?>
            <h2><?php esc_html_e( 'Connected Knowledge Library Documents', 'sustainable-catalyst-library' ); ?></h2>
            <div class="sc-connected-document-grid">
                <?php foreach ( $data['document_ids'] as $document_id ) : ?><article><h3><a href="<?php echo esc_url( get_edit_post_link( $document_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $document_id ) ); ?></a></h3><p><?php echo esc_html( get_post_field( 'post_excerpt', $document_id ) ); ?></p></article><?php endforeach; ?>
            </div>
            <?php
            return;
        }

        $formats = array( 'markdown', 'text', 'html', 'bibtex', 'ris', 'csl-json', 'json' );
        ?>
        <h2><?php esc_html_e( 'Project and Bibliography Exports', 'sustainable-catalyst-library' ); ?></h2>
        <div class="sc-connected-export-grid">
            <?php foreach ( $formats as $format ) : ?>
                <?php $export = self::export_project( $data['id'], $format, true ); $content = is_wp_error( $export ) ? '' : ( is_array( $export['content'] ) ? wp_json_encode( $export['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : $export['content'] ); ?>
                <section><h3><?php echo esc_html( strtoupper( $format ) ); ?></h3><textarea readonly rows="12" data-sc-copy-value><?php echo esc_textarea( $content ); ?></textarea><button type="button" class="button" data-sc-copy-connected><?php esc_html_e( 'Copy Export', 'sustainable-catalyst-library' ); ?></button></section>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function shortcode_project_environment( $atts ) {
        $atts = shortcode_atts( array( 'project' => '', 'include_private' => 'false' ), $atts, 'sc_connected_research_project' );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && self::can_read_private_project( $project_id );
        $data = self::project_data( $project_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        if ( $include_private ) {
            nocache_headers();
        }
        wp_enqueue_style( 'sc-library-connected-research' );
        wp_enqueue_script( 'sc-library-connected-research' );
        $bibliography = self::bibliography( $project_id, $include_private );
        ob_start();
        ?>
        <article class="sc-public-research-environment">
            <header><p class="sc-connected-kicker"><?php echo esc_html( $data['project_code'] ?: __( 'Connected research project', 'sustainable-catalyst-library' ) ); ?></p><h1><?php echo esc_html( $data['title'] ); ?></h1><?php if ( $data['research_question'] ) : ?><p class="sc-public-research-question"><?php echo esc_html( $data['research_question'] ); ?></p><?php endif; ?><div class="sc-connected-metrics"><article><strong><?php echo esc_html( $data['health']['included_sources'] ); ?></strong><span><?php esc_html_e( 'Sources', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $data['health']['claims'] ); ?></strong><span><?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $data['health']['evidence_notes'] ); ?></strong><span><?php esc_html_e( 'Evidence Notes', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $data['health']['documents'] ); ?></strong><span><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></span></article></div></header>
            <?php if ( $data['objectives'] ) : ?><section><h2><?php esc_html_e( 'Research objectives', 'sustainable-catalyst-library' ); ?></h2><ol><?php foreach ( $data['objectives'] as $objective ) : ?><li><?php echo esc_html( $objective ); ?></li><?php endforeach; ?></ol></section><?php endif; ?>
            <?php echo self::render_bibliography_html( $bibliography, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) : ?><?php echo do_shortcode( '[sc_project_evidence project="' . absint( $project_id ) . '"' . ( $include_private ? ' include_private="true"' : '' ) . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    public function shortcode_bibliography_environment( $atts ) {
        $atts = shortcode_atts( array( 'project' => '', 'include_private' => 'false', 'annotations' => 'false' ), $atts, 'sc_project_bibliography_environment' );
        $project_id = self::resolve_project_id( $atts['project'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && self::can_read_private_project( $project_id );
        $bibliography = self::bibliography( $project_id, $include_private );
        if ( ! $bibliography ) {
            return '';
        }
        if ( $include_private ) {
            nocache_headers();
        }
        wp_enqueue_style( 'sc-library-connected-research' );
        wp_enqueue_script( 'sc-library-connected-research' );
        return self::render_bibliography_html( $bibliography, rest_sanitize_boolean( $atts['annotations'] ) );
    }

    private static function render_bibliography_html( $bibliography, $show_annotations ) {
        if ( ! is_array( $bibliography ) ) {
            return '';
        }
        ob_start();
        ?>
        <section class="sc-connected-bibliography" data-project-id="<?php echo esc_attr( $bibliography['project_id'] ); ?>">
            <header><p class="sc-connected-kicker"><?php echo esc_html( $bibliography['project_title'] ); ?></p><h2><?php echo esc_html( $bibliography['bibliography_title'] ); ?></h2><p><?php echo esc_html( sprintf( _n( '%d included Source', '%d included Sources', $bibliography['entry_count'], 'sustainable-catalyst-library' ), $bibliography['entry_count'] ) ); ?></p></header>
            <?php foreach ( $bibliography['sections'] as $section ) : ?>
                <section class="sc-connected-bibliography__section"><h3><?php echo esc_html( $section['title'] ); ?></h3><?php if ( $section['description'] ) : ?><p><?php echo esc_html( $section['description'] ); ?></p><?php endif; ?><ol><?php foreach ( $section['entries'] as $entry ) : ?><li><p data-sc-copy-value="<?php echo esc_attr( $entry['citation'] ); ?>"><?php echo esc_html( $entry['citation'] ); ?></p><?php if ( class_exists( 'SC_Library_Source_Versioning_Integrity' ) ) : ?><?php echo SC_Library_Source_Versioning_Integrity::render_bibliography_integrity_badge( $entry['source_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?><div><span><?php echo esc_html( self::source_role_options()[ $entry['role'] ] ); ?></span><button type="button" data-sc-copy-parent><?php esc_html_e( 'Copy citation', 'sustainable-catalyst-library' ); ?></button></div><?php if ( $show_annotations && $entry['annotation'] ) : ?><aside><?php echo esc_html( $entry['annotation'] ); ?></aside><?php endif; ?></li><?php endforeach; ?></ol></section>
            <?php endforeach; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/workspace',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_workspace' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/workspace',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_workspace' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/bibliography-environment',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_bibliography' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/bibliography-snapshots',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_snapshots' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_snapshot' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_export' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/activity',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_activity' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
    }

    public function rest_workspace( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response( self::project_data( $project_id, self::can_read_private_project( $project_id ) ) );
    }

    public function rest_update_workspace( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        $fields = array(
            'research_question' => array( self::META_RESEARCH_QUESTION, 'textarea' ),
            'methods'           => array( self::META_METHODS, 'textarea' ),
            'scope'             => array( self::META_SCOPE, 'textarea' ),
            'start_date'        => array( self::META_START_DATE, 'date' ),
            'target_date'       => array( self::META_TARGET_DATE, 'date' ),
        );
        foreach ( $fields as $field => $definition ) {
            if ( ! array_key_exists( $field, $payload ) ) {
                continue;
            }
            $value = 'date' === $definition[1] ? self::sanitize_date( $payload[ $field ] ) : sanitize_textarea_field( $payload[ $field ] );
            self::update_or_delete_meta( $project_id, $definition[0], $value );
        }
        if ( array_key_exists( 'objectives', $payload ) ) {
            self::update_or_delete_meta( $project_id, self::META_OBJECTIVES, self::text_list( $payload['objectives'] ) );
        }
        if ( array_key_exists( 'sections', $payload ) ) {
            update_post_meta( $project_id, self::META_SECTIONS, self::sanitize_sections( $payload['sections'] ) );
        }
        if ( array_key_exists( 'source_entries', $payload ) ) {
            $sections = self::bibliography_sections( $project_id );
            $old_entries = self::source_entries( $project_id, true );
            $entries = self::sanitize_source_entries( $payload['source_entries'], $sections, $old_entries );
            self::save_source_entries( $project_id, $entries, $old_entries );
        }
        if ( array_key_exists( 'document_ids', $payload ) ) {
            self::update_id_meta( $project_id, self::META_DOCUMENT_IDS, self::validated_ids( $payload['document_ids'], SC_Library_Foundation_Pages::POST_TYPE, self::MAX_DOCUMENTS ) );
        }
        if ( array_key_exists( 'team', $payload ) ) {
            self::update_or_delete_meta( $project_id, self::META_TEAM, self::sanitize_team( $payload['team'] ) );
        }
        if ( array_key_exists( 'sort', $payload ) ) {
            $sort = sanitize_key( $payload['sort'] );
            self::update_or_delete_meta( $project_id, self::META_SORT, array_key_exists( $sort, self::sort_options() ) ? $sort : 'section-author' );
        }
        update_post_meta( $project_id, self::META_HEALTH, self::workspace_health( $project_id, true ) );
        self::append_activity( $project_id, 'workspace-api-update', __( 'Project environment updated through the REST API.', 'sustainable-catalyst-library' ), array() );
        return rest_ensure_response( self::project_data( $project_id, true ) );
    }

    public function rest_bibliography( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response( self::bibliography( $project_id, self::can_read_private_project( $project_id ) ) );
    }

    public function rest_snapshots( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'schema' => self::SNAPSHOT_SCHEMA, 'items' => self::snapshots( absint( $request['id'] ) ) ) );
    }

    public function rest_create_snapshot( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        return rest_ensure_response( self::create_snapshot( absint( $request['id'] ), $payload['label'] ?? '' ) );
    }

    public function rest_export( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        $format = sanitize_key( $request->get_param( 'format' ) ?: 'markdown' );
        return rest_ensure_response( self::export_project( $project_id, $format, self::can_read_private_project( $project_id ) ) );
    }

    public function rest_activity( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'schema' => self::WORKSPACE_SCHEMA, 'items' => self::activity( absint( $request['id'] ) ) ) );
    }

    public function rest_can_read_project( WP_REST_Request $request ) {
        return self::can_read_project( absint( $request['id'] ) );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id ) && current_user_can( 'edit_post', $project_id );
    }

    public function maybe_migrate_projects() {
        if ( class_exists( 'SC_Library_Connected_Research_Reliability' ) ) {
            SC_Library_Connected_Research_Reliability::maybe_schedule_migration();
            return;
        }
    }


    private static function sanitize_source_entries( $raw, $sections, $old_entries = array() ) {
        $old_by_source = array();
        foreach ( (array) $old_entries as $old ) {
            $old_by_source[ absint( $old['source_id'] ?? 0 ) ] = $old;
        }
        $section_slugs = wp_list_pluck( $sections, 'slug' );
        $default_section = $section_slugs[0] ?? 'core-sources';
        $entries = array();
        $seen = array();
        foreach ( (array) $raw as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $source_id = absint( $entry['source_id'] ?? 0 );
            if ( ! $source_id || isset( $seen[ $source_id ] ) || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
                continue;
            }
            $seen[ $source_id ] = true;
            $old = $old_by_source[ $source_id ] ?? array();
            $section = sanitize_title( $entry['section'] ?? $default_section );
            if ( ! in_array( $section, $section_slugs, true ) ) {
                $section = $default_section;
            }
            $entries[] = array(
                'schema'     => self::SOURCE_ENTRY_SCHEMA,
                'source_id'  => $source_id,
                'role'       => self::allowed_value( $entry['role'] ?? 'background', self::source_role_options(), 'background' ),
                'section'    => $section,
                'inclusion'  => self::allowed_value( $entry['inclusion'] ?? 'candidate', self::inclusion_options(), 'candidate' ),
                'priority'   => max( 1, min( 5, absint( $entry['priority'] ?? 3 ) ) ),
                'annotation' => sanitize_text_field( $entry['annotation'] ?? '' ),
                'added_at'   => sanitize_text_field( $old['added_at'] ?? current_time( 'mysql' ) ),
                'added_by'   => absint( $old['added_by'] ?? get_current_user_id() ),
                'updated_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id(),
            );
            if ( count( $entries ) >= self::MAX_SOURCE_ENTRIES ) {
                break;
            }
        }
        return $entries;
    }

    private static function sanitize_sections( $raw ) {
        $sections = array();
        $seen = array();
        foreach ( (array) $raw as $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }
            $title = sanitize_text_field( $section['title'] ?? '' );
            if ( ! $title ) {
                continue;
            }
            $slug = sanitize_title( $section['slug'] ?? $title );
            if ( isset( $seen[ $slug ] ) ) {
                $slug .= '-' . ( count( $seen ) + 1 );
            }
            $seen[ $slug ] = true;
            $sections[] = array(
                'slug'        => $slug,
                'title'       => $title,
                'description' => sanitize_text_field( $section['description'] ?? '' ),
                'order'       => count( $sections ),
            );
            if ( count( $sections ) >= self::MAX_SECTIONS ) {
                break;
            }
        }
        return $sections ?: self::default_sections();
    }

    private static function sanitize_team( $raw ) {
        $team = array();
        $seen = array();
        foreach ( (array) $raw as $member ) {
            if ( ! is_array( $member ) ) {
                continue;
            }
            $user_id = absint( $member['user_id'] ?? 0 );
            if ( ! $user_id || isset( $seen[ $user_id ] ) || ! get_user_by( 'id', $user_id ) ) {
                continue;
            }
            $seen[ $user_id ] = true;
            $team[] = array(
                'user_id' => $user_id,
                'role'    => self::allowed_value( $member['role'] ?? 'researcher', self::team_role_options(), 'researcher' ),
            );
            if ( count( $team ) >= self::MAX_TEAM ) {
                break;
            }
        }
        return $team;
    }

    public static function bibliography_sections( $project_id ) {
        $sections = get_post_meta( $project_id, self::META_SECTIONS, true );
        return is_array( $sections ) && $sections ? self::sanitize_sections( $sections ) : self::default_sections();
    }

    private static function default_sections() {
        return array(
            array( 'slug' => 'core-sources', 'title' => __( 'Core Sources', 'sustainable-catalyst-library' ), 'description' => '', 'order' => 0 ),
            array( 'slug' => 'background-context', 'title' => __( 'Background and Context', 'sustainable-catalyst-library' ), 'description' => '', 'order' => 1 ),
            array( 'slug' => 'methods-data', 'title' => __( 'Methods and Data', 'sustainable-catalyst-library' ), 'description' => '', 'order' => 2 ),
            array( 'slug' => 'law-policy-standards', 'title' => __( 'Law, Policy, and Standards', 'sustainable-catalyst-library' ), 'description' => '', 'order' => 3 ),
            array( 'slug' => 'counterevidence', 'title' => __( 'Counterevidence and Alternative Views', 'sustainable-catalyst-library' ), 'description' => '', 'order' => 4 ),
        );
    }

    public static function source_role_options() {
        return array(
            'primary'         => __( 'Primary source', 'sustainable-catalyst-library' ),
            'background'      => __( 'Background', 'sustainable-catalyst-library' ),
            'theory'          => __( 'Theory or framework', 'sustainable-catalyst-library' ),
            'method'          => __( 'Method', 'sustainable-catalyst-library' ),
            'data'            => __( 'Data or dataset', 'sustainable-catalyst-library' ),
            'law-policy'      => __( 'Law or policy', 'sustainable-catalyst-library' ),
            'standard'        => __( 'Standard or guidance', 'sustainable-catalyst-library' ),
            'counterevidence' => __( 'Counterevidence', 'sustainable-catalyst-library' ),
            'case-study'      => __( 'Case study', 'sustainable-catalyst-library' ),
            'other'           => __( 'Other', 'sustainable-catalyst-library' ),
        );
    }

    public static function inclusion_options() {
        return array(
            'included'  => __( 'Included in bibliography', 'sustainable-catalyst-library' ),
            'candidate' => __( 'Candidate for review', 'sustainable-catalyst-library' ),
            'excluded'  => __( 'Excluded from bibliography', 'sustainable-catalyst-library' ),
        );
    }

    public static function team_role_options() {
        return array(
            'lead'       => __( 'Project lead', 'sustainable-catalyst-library' ),
            'researcher' => __( 'Researcher', 'sustainable-catalyst-library' ),
            'librarian'  => __( 'Research librarian', 'sustainable-catalyst-library' ),
            'reviewer'   => __( 'Reviewer', 'sustainable-catalyst-library' ),
            'advisor'    => __( 'Advisor', 'sustainable-catalyst-library' ),
            'observer'   => __( 'Observer', 'sustainable-catalyst-library' ),
        );
    }

    public static function sort_options() {
        return array(
            'section-author' => __( 'Section, then author and year', 'sustainable-catalyst-library' ),
            'author-year'    => __( 'Author and year', 'sustainable-catalyst-library' ),
            'year-desc'      => __( 'Newest year first', 'sustainable-catalyst-library' ),
            'title'          => __( 'Title', 'sustainable-catalyst-library' ),
            'priority'       => __( 'Priority, then author', 'sustainable-catalyst-library' ),
        );
    }

    private static function sort_entries( $entries, $sort, $sections ) {
        $section_order = array();
        foreach ( $sections as $index => $section ) {
            $section_order[ $section['slug'] ] = $index;
        }
        usort(
            $entries,
            static function ( $left, $right ) use ( $sort, $section_order ) {
                $left_source = $left['source'];
                $right_source = $right['source'];
                $left_author = strtolower( self::source_author_sort( $left_source ) );
                $right_author = strtolower( self::source_author_sort( $right_source ) );
                $left_year = absint( $left_source['year'] ?? 0 );
                $right_year = absint( $right_source['year'] ?? 0 );
                $left_title = strtolower( $left_source['title'] ?? '' );
                $right_title = strtolower( $right_source['title'] ?? '' );

                if ( 'year-desc' === $sort && $left_year !== $right_year ) {
                    return $right_year <=> $left_year;
                }
                if ( 'title' === $sort && $left_title !== $right_title ) {
                    return strcmp( $left_title, $right_title );
                }
                if ( 'priority' === $sort && $left['priority'] !== $right['priority'] ) {
                    return $right['priority'] <=> $left['priority'];
                }
                if ( 'section-author' === $sort ) {
                    $left_section = $section_order[ $left['section'] ] ?? 999;
                    $right_section = $section_order[ $right['section'] ] ?? 999;
                    if ( $left_section !== $right_section ) {
                        return $left_section <=> $right_section;
                    }
                }
                $author_compare = strcmp( $left_author, $right_author );
                if ( 0 !== $author_compare ) {
                    return $author_compare;
                }
                if ( $left_year !== $right_year ) {
                    return $left_year <=> $right_year;
                }
                return strcmp( $left_title, $right_title );
            }
        );
        return $entries;
    }

    private static function source_author_sort( $source ) {
        if ( ! empty( $source['authors'][0]['family'] ) ) {
            return $source['authors'][0]['family'];
        }
        if ( ! empty( $source['authors'][0]['literal'] ) ) {
            return $source['authors'][0]['literal'];
        }
        return $source['organization'] ?? $source['title'] ?? '';
    }

    private static function source_to_bibtex( $source ) {
        $type_map = array(
            'journal-article' => 'article',
            'book'            => 'book',
            'book-chapter'    => 'incollection',
            'conference-paper'=> 'inproceedings',
            'report'          => 'techreport',
            'dataset'         => 'misc',
            'website'         => 'online',
            'law'             => 'misc',
            'standard'        => 'techreport',
        );
        $type = $type_map[ $source['source_type'] ?? '' ] ?? 'misc';
        $key = preg_replace( '/[^A-Za-z0-9_:-]/', '', $source['citation_key'] ?? '' ) ?: 'source' . absint( $source['id'] ?? 0 );
        $fields = array(
            'title'     => $source['title'] ?? '',
            'year'      => $source['year'] ?? '',
            'journal'   => $source['container_title'] ?? '',
            'publisher' => $source['publisher'] ?? '',
            'volume'    => $source['volume'] ?? '',
            'number'    => $source['issue'] ?? '',
            'pages'     => $source['pages'] ?? '',
            'doi'       => $source['doi'] ?? '',
            'isbn'      => $source['isbn'] ?? '',
            'url'       => $source['url'] ?? '',
        );
        $authors = array();
        foreach ( (array) ( $source['authors'] ?? array() ) as $author ) {
            if ( ! empty( $author['family'] ) ) {
                $authors[] = trim( ( $author['family'] ?? '' ) . ', ' . ( $author['given'] ?? '' ), ', ' );
            } elseif ( ! empty( $author['literal'] ) ) {
                $authors[] = $author['literal'];
            }
        }
        if ( $authors ) {
            $fields['author'] = implode( ' and ', $authors );
        } elseif ( ! empty( $source['organization'] ) ) {
            $fields['author'] = '{' . $source['organization'] . '}';
        }
        $lines = array( '@' . $type . '{' . $key . ',' );
        foreach ( $fields as $field => $value ) {
            if ( '' !== trim( (string) $value ) ) {
                $lines[] = '  ' . $field . ' = {' . self::bibtex_escape( $value ) . '},';
            }
        }
        $lines[] = '}';
        return implode( "\n", $lines );
    }

    private static function source_to_ris( $source ) {
        $type_map = array(
            'journal-article' => 'JOUR',
            'book'            => 'BOOK',
            'book-chapter'    => 'CHAP',
            'conference-paper'=> 'CPAPER',
            'report'          => 'RPRT',
            'dataset'         => 'DATA',
            'website'         => 'ELEC',
            'law'             => 'LEGAL',
            'standard'        => 'STAND',
        );
        $lines = array( 'TY  - ' . ( $type_map[ $source['source_type'] ?? '' ] ?? 'GEN' ) );
        foreach ( (array) ( $source['authors'] ?? array() ) as $author ) {
            $name = ! empty( $author['family'] )
                ? trim( ( $author['family'] ?? '' ) . ', ' . ( $author['given'] ?? '' ), ', ' )
                : ( $author['literal'] ?? '' );
            if ( $name ) {
                $lines[] = 'AU  - ' . self::ris_clean( $name );
            }
        }
        if ( ! empty( $source['organization'] ) && empty( $source['authors'] ) ) {
            $lines[] = 'AU  - ' . self::ris_clean( $source['organization'] );
        }
        $map = array(
            'TI' => 'title',
            'PY' => 'year',
            'JO' => 'container_title',
            'PB' => 'publisher',
            'VL' => 'volume',
            'IS' => 'issue',
            'SP' => 'pages',
            'DO' => 'doi',
            'SN' => 'isbn',
            'UR' => 'url',
            'AB' => 'abstract',
            'LA' => 'language',
        );
        foreach ( $map as $tag => $field ) {
            if ( ! empty( $source[ $field ] ) ) {
                $lines[] = $tag . '  - ' . self::ris_clean( $source[ $field ] );
            }
        }
        $lines[] = 'ER  -';
        return implode( "\n", $lines );
    }

    private static function source_to_csl( $source ) {
        $type_map = array(
            'journal-article' => 'article-journal',
            'book'            => 'book',
            'book-chapter'    => 'chapter',
            'conference-paper'=> 'paper-conference',
            'report'          => 'report',
            'dataset'         => 'dataset',
            'website'         => 'webpage',
            'law'             => 'legislation',
            'standard'        => 'standard',
        );
        $authors = array();
        foreach ( (array) ( $source['authors'] ?? array() ) as $author ) {
            if ( ! empty( $author['family'] ) ) {
                $authors[] = array( 'family' => $author['family'], 'given' => $author['given'] ?? '' );
            } elseif ( ! empty( $author['literal'] ) ) {
                $authors[] = array( 'literal' => $author['literal'] );
            }
        }
        if ( ! $authors && ! empty( $source['organization'] ) ) {
            $authors[] = array( 'literal' => $source['organization'] );
        }
        $year = absint( $source['year'] ?? 0 );
        return array_filter(
            array(
                'id'              => $source['citation_key'] ?: 'source-' . absint( $source['id'] ),
                'type'            => $type_map[ $source['source_type'] ?? '' ] ?? 'document',
                'title'           => $source['title'] ?? '',
                'author'          => $authors,
                'issued'          => $year ? array( 'date-parts' => array( array( $year ) ) ) : null,
                'container-title' => $source['container_title'] ?? '',
                'publisher'       => $source['publisher'] ?? '',
                'publisher-place' => $source['place'] ?? '',
                'volume'          => $source['volume'] ?? '',
                'issue'           => $source['issue'] ?? '',
                'page'            => $source['pages'] ?? '',
                'DOI'             => $source['doi'] ?? '',
                'ISBN'            => $source['isbn'] ?? '',
                'URL'             => $source['url'] ?? '',
                'language'        => $source['language'] ?? '',
                'abstract'        => $source['abstract'] ?? '',
            ),
            static function ( $value ) {
                return null !== $value && '' !== $value && array() !== $value;
            }
        );
    }

    private static function bibtex_escape( $value ) {
        return strtr( (string) $value, array( '\\' => '\\\\', '{' => '\\{', '}' => '\\}', "\r" => ' ', "\n" => ' ' ) );
    }

    private static function ris_clean( $value ) {
        return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
    }

    private static function snapshots( $project_id ) {
        $snapshots = get_post_meta( $project_id, self::META_SNAPSHOTS, true );
        return is_array( $snapshots ) ? array_slice( $snapshots, -self::MAX_SNAPSHOTS ) : array();
    }

    private static function activity( $project_id ) {
        $activity = get_post_meta( $project_id, self::META_ACTIVITY, true );
        return is_array( $activity ) ? array_slice( $activity, -self::MAX_ACTIVITY ) : array();
    }

    private static function append_activity( $project_id, $type, $message, $context ) {
        $activity = self::activity( $project_id );
        $activity[] = array(
            'id'         => wp_generate_uuid4(),
            'type'       => sanitize_key( $type ),
            'message'    => sanitize_text_field( $message ),
            'context'    => is_array( $context ) ? $context : array(),
            'created_at' => current_time( 'mysql' ),
            'created_by' => get_current_user_id(),
        );
        update_post_meta( $project_id, self::META_ACTIVITY, array_slice( $activity, -self::MAX_ACTIVITY ) );
    }

    private static function team_entries( $project_id ) {
        $team = get_post_meta( $project_id, self::META_TEAM, true );
        return self::sanitize_team( is_array( $team ) ? $team : array() );
    }

    private static function document_ids( $project_id, $include_private ) {
        $ids = self::id_list( get_post_meta( $project_id, self::META_DOCUMENT_IDS, true ) );
        foreach ( self::source_entries( $project_id, $include_private ) as $entry ) {
            $ids = array_merge( $ids, self::id_list( $entry['source']['related_document_ids'] ?? array() ) );
        }
        $ids = array_values( array_unique( $ids ) );
        if ( ! $include_private ) {
            $ids = array_values( array_filter( $ids, static function ( $id ) { return 'publish' === get_post_status( $id ); } ) );
        }
        return $ids;
    }

    private static function resolve_project_id( $value ) {
        if ( is_numeric( $value ) && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function can_read_private_project( $project_id ) {
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return false;
        }
        if ( current_user_can( 'edit_post', $project_id ) ) {
            return true;
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        foreach ( self::team_entries( $project_id ) as $member ) {
            if ( absint( $member['user_id'] ) === $user_id ) {
                return true;
            }
        }
        return false;
    }

    private static function can_read_project( $project_id ) {
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return false;
        }
        if ( current_user_can( 'edit_post', $project_id ) || self::project_is_public( $project_id ) ) {
            return true;
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        foreach ( self::team_entries( $project_id ) as $member ) {
            if ( absint( $member['user_id'] ) === $user_id ) {
                return true;
            }
        }
        return false;
    }

    private static function project_is_public( $project_id ) {
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && 'publish' === get_post_status( $project_id )
            && 'public' === get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
    }

    private static function validated_ids( $raw, $post_type, $limit ) {
        $valid = array();
        foreach ( self::id_list( $raw ) as $id ) {
            if ( $post_type === get_post_type( $id ) ) {
                $valid[] = $id;
            }
            if ( count( $valid ) >= $limit ) {
                break;
            }
        }
        return array_values( array_unique( $valid ) );
    }

    private static function text_list( $raw ) {
        if ( is_array( $raw ) ) {
            $parts = $raw;
        } else {
            $parts = preg_split( '/\R/u', (string) $raw );
        }
        return array_values(
            array_filter(
                array_map(
                    static function ( $value ) {
                        return sanitize_text_field( $value );
                    },
                    (array) $parts
                )
            )
        );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function sanitize_date( $value ) {
        $value = sanitize_text_field( $value );
        $date = DateTime::createFromFormat( 'Y-m-d', $value );
        return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
    }

    private static function update_id_meta( $post_id, $key, $ids ) {
        $ids = self::id_list( $ids );
        $ids ? update_post_meta( $post_id, $key, $ids ) : delete_post_meta( $post_id, $key );
    }

    private static function update_or_delete_meta( $post_id, $key, $value ) {
        if ( '' === $value || array() === $value || null === $value ) {
            delete_post_meta( $post_id, $key );
        } else {
            update_post_meta( $post_id, $key, $value );
        }
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function section_title( $sections, $slug ) {
        foreach ( $sections as $section ) {
            if ( $section['slug'] === $slug ) {
                return $section['title'];
            }
        }
        return $slug;
    }
}
