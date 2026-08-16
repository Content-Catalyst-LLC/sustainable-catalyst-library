<?php
/**
 * Research Collections, Exhibitions & Curated Knowledge Spaces — v5.4.0.
 *
 * Editorial curation over already-public Sustainable Catalyst Library records.
 * Curated spaces store ordered public references and public curator narrative;
 * they never copy private project, notebook, matrix, room, team-membership, or
 * credential data into the public surface.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Research_Collections_Curated_Spaces {
    public const VERSION = '5.4.0';
    public const SCHEMA = 'sc-library-research-curated-spaces/1.0';
    public const SPACE_SCHEMA = 'sc-library-curated-space/1.0';
    public const SECTION_SCHEMA = 'sc-library-curated-space-section/1.0';
    public const REFERENCE_SCHEMA = 'sc-library-curated-space-reference/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-curated-space-manifest/1.0';
    public const POST_TYPE = 'sc_curated_space';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/curated-spaces';
    public const META_SPACE_URN = '_sc_curated_space_urn_v540';
    public const META_KIND = '_sc_curated_space_kind_v540';
    public const META_SUBTITLE = '_sc_curated_space_subtitle_v540';
    public const META_CURATOR_NOTE = '_sc_curated_space_curator_note_v540';
    public const META_SECTIONS = '_sc_curated_space_sections_v540';
    public const MAX_INDEX = 48;
    public const MAX_SECTIONS = 24;
    public const MAX_ITEMS_PER_SECTION = 40;
    public const MAX_SECTION_NARRATIVE = 1800;
    public const MAX_CURATOR_NOTE = 1600;

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_post' ), 20, 3 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_research_curated_spaces', array( $this, 'shortcode_index' ) );
        add_shortcode( 'sc_curated_space', array( $this, 'shortcode_space' ) );
        add_filter( 'sc_library_api_public_object_profiles', array( $this, 'filter_public_profiles' ), 30 );
        add_filter( 'sc_library_api_public_object_payload', array( $this, 'filter_public_object_payload' ), 40, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_headers' ), 38, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'canonical_public_api_reused' => true,
            'public_evidence_navigation_reused' => true,
            'global_federation_public_manifests_reused' => true,
            'wordpress_editorial_publication_authority_reused' => true,
            'creates_parallel_public_record_store' => false,
            'curated_space_store_is_editorial_only' => true,
            'references_only' => true,
            'ordered_sections' => true,
            'underlying_record_ownership_transferred' => false,
            'underlying_record_publication_state_mutated' => false,
            'private_projects_exposed' => false,
            'personal_library_exposed' => false,
            'notebook_bodies_exposed' => false,
            'matrix_bodies_exposed' => false,
            'research_room_membership_exposed' => false,
            'team_library_membership_exposed' => false,
            'credentials_exposed' => false,
            'private_binary_copy' => false,
            'automatic_record_copy' => false,
            'automatic_publication' => false,
            'automatic_evidence_promotion' => false,
            'automatic_federation_acceptance' => false,
            'automatic_workspace_write' => false,
            'public_get_only' => true,
            'cors_credentials_allowed' => false,
        );
    }

    public static function kind_registry() {
        return array(
            'research-collection' => __( 'Research collection', 'sustainable-catalyst-library' ),
            'exhibition' => __( 'Exhibition', 'sustainable-catalyst-library' ),
            'knowledge-space' => __( 'Curated knowledge space', 'sustainable-catalyst-library' ),
        );
    }

    public static function reference_registry() {
        $out = array();
        if ( class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) {
            foreach ( SC_Library_API_Embeds_Interoperability::object_profiles() as $key => $profile ) {
                if ( 'curated-space' === $key ) { continue; }
                $out[ sanitize_key( $key ) ] = (string) ( $profile['label'] ?? $key );
            }
        }
        $out['public-claim'] = __( 'Public research claim', 'sustainable-catalyst-library' );
        $out['public-evidence'] = __( 'Public evidence note', 'sustainable-catalyst-library' );
        $out['federation-manifest'] = __( 'Published federation manifest', 'sustainable-catalyst-library' );
        return $out;
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name' => __( 'Curated Knowledge Spaces', 'sustainable-catalyst-library' ),
                'singular_name' => __( 'Curated Knowledge Space', 'sustainable-catalyst-library' ),
                'add_new_item' => __( 'Add Curated Knowledge Space', 'sustainable-catalyst-library' ),
                'edit_item' => __( 'Edit Curated Knowledge Space', 'sustainable-catalyst-library' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'supports' => array( 'title', 'editor', 'excerpt', 'author', 'revisions' ),
            'capability_type' => 'page',
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-layout',
            'rewrite' => false,
        ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-curated-spaces-v540', SC_LIBRARY_URL . 'assets/css/sc-library-curated-spaces-v540.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-curated-spaces-v540', SC_LIBRARY_URL . 'assets/js/sc-library-curated-spaces-v540.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function admin_assets( $hook ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
        $this->register_assets();
        wp_enqueue_style( 'sc-library-curated-spaces-v540' );
        wp_enqueue_script( 'sc-library-curated-spaces-v540' );
    }

    public function filter_public_profiles( $profiles ) {
        $profiles = is_array( $profiles ) ? $profiles : array();
        $profiles['curated-space'] = array( 'post_type' => self::POST_TYPE, 'label' => __( 'Curated knowledge space', 'sustainable-catalyst-library' ) );
        return $profiles;
    }

    private static function clean( $value, $limit = 600 ) {
        $value = trim( wp_strip_all_tags( (string) $value ) );
        $value = preg_replace( '/\s+/u', ' ', $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function ensure_space_urn( $post_id ) {
        $urn = (string) get_post_meta( $post_id, self::META_SPACE_URN, true );
        if ( '' === trim( $urn ) ) {
            $urn = 'urn:sc:curated-space:' . wp_generate_uuid4();
            update_post_meta( $post_id, self::META_SPACE_URN, $urn );
        }
        return $urn;
    }

    public function add_meta_boxes() {
        add_meta_box( 'sc-curated-space-structure', __( 'Curated Space Structure', 'sustainable-catalyst-library' ), array( $this, 'meta_box' ), self::POST_TYPE, 'normal', 'high' );
    }

    public function meta_box( $post ) {
        wp_nonce_field( 'sc_curated_space_save_' . $post->ID, 'sc_curated_space_nonce' );
        $kind = sanitize_key( (string) get_post_meta( $post->ID, self::META_KIND, true ) );
        if ( ! isset( self::kind_registry()[ $kind ] ) ) { $kind = 'research-collection'; }
        $subtitle = (string) get_post_meta( $post->ID, self::META_SUBTITLE, true );
        $curator = (string) get_post_meta( $post->ID, self::META_CURATOR_NOTE, true );
        $sections = get_post_meta( $post->ID, self::META_SECTIONS, true );
        $sections = is_array( $sections ) ? $sections : array();
        if ( ! $sections ) { $sections = array( array( 'title' => '', 'narrative' => '', 'items' => array() ) ); }
        $ref_types = self::reference_registry();
        ?>
        <div class="sc-curated-admin" data-sc-curated-section-builder>
            <p class="description"><?php esc_html_e( 'Publishing this WordPress record makes only the curator narrative and references to already-public records available. Private research cannot be promoted by this editor.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-curated-admin__meta">
                <label><span><?php esc_html_e( 'Space type', 'sustainable-catalyst-library' ); ?></span><select name="sc_curated_kind"><?php foreach ( self::kind_registry() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $kind, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e( 'Subtitle', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_curated_subtitle" maxlength="240" value="<?php echo esc_attr( $subtitle ); ?>"></label>
            </div>
            <label class="sc-curated-admin__curator"><span><?php esc_html_e( 'Public curator note', 'sustainable-catalyst-library' ); ?></span><textarea name="sc_curated_curator_note" maxlength="<?php echo esc_attr( (string) self::MAX_CURATOR_NOTE ); ?>"><?php echo esc_textarea( $curator ); ?></textarea></label>
            <div class="sc-curated-admin__sections" data-sc-curated-sections>
            <?php foreach ( array_slice( $sections, 0, self::MAX_SECTIONS ) as $si => $section ) : $items = is_array( $section['items'] ?? null ) ? $section['items'] : array(); ?>
                <section class="sc-curated-admin__section" data-sc-curated-section>
                    <div class="sc-curated-admin__section-head"><strong><?php esc_html_e( 'Section', 'sustainable-catalyst-library' ); ?></strong><button type="button" class="button-link-delete" data-sc-curated-remove-section><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button></div>
                    <input type="hidden" data-sc-section-index value="<?php echo esc_attr( (string) $si ); ?>"><input type="hidden" name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][section_urn]" value="<?php echo esc_attr( (string) ( $section['section_urn'] ?? '' ) ); ?>">
                    <label><span><?php esc_html_e( 'Section title', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][title]" maxlength="180" value="<?php echo esc_attr( (string) ( $section['title'] ?? '' ) ); ?>"></label>
                    <label><span><?php esc_html_e( 'Section narrative', 'sustainable-catalyst-library' ); ?></span><textarea name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][narrative]" maxlength="<?php echo esc_attr( (string) self::MAX_SECTION_NARRATIVE ); ?>"><?php echo esc_textarea( (string) ( $section['narrative'] ?? '' ) ); ?></textarea></label>
                    <div class="sc-curated-admin__items" data-sc-curated-items>
                    <?php foreach ( array_slice( $items, 0, self::MAX_ITEMS_PER_SECTION ) as $ii => $item ) : ?>
                        <div class="sc-curated-admin__item" data-sc-curated-item>
                            <select name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][items][<?php echo esc_attr( (string) $ii ); ?>][type]" aria-label="<?php esc_attr_e( 'Reference type', 'sustainable-catalyst-library' ); ?>"><?php foreach ( $ref_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( sanitize_key( (string) ( $item['type'] ?? '' ) ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                            <input type="number" min="1" inputmode="numeric" name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][items][<?php echo esc_attr( (string) $ii ); ?>][id]" value="<?php echo esc_attr( (string) absint( $item['id'] ?? 0 ) ); ?>" placeholder="<?php esc_attr_e( 'Public record ID', 'sustainable-catalyst-library' ); ?>">
                            <input type="text" maxlength="180" name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][items][<?php echo esc_attr( (string) $ii ); ?>][label]" value="<?php echo esc_attr( (string) ( $item['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Optional curator label', 'sustainable-catalyst-library' ); ?>">
                            <input type="text" maxlength="400" name="sc_curated_sections[<?php echo esc_attr( (string) $si ); ?>][items][<?php echo esc_attr( (string) $ii ); ?>][note]" value="<?php echo esc_attr( (string) ( $item['note'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Optional public note', 'sustainable-catalyst-library' ); ?>">
                            <button type="button" class="button-link-delete" data-sc-curated-remove-item aria-label="<?php esc_attr_e( 'Remove reference', 'sustainable-catalyst-library' ); ?>">×</button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" data-sc-curated-add-item><?php esc_html_e( 'Add public reference', 'sustainable-catalyst-library' ); ?></button>
                </section>
            <?php endforeach; ?>
            </div>
            <button type="button" class="button" data-sc-curated-add-section><?php esc_html_e( 'Add section', 'sustainable-catalyst-library' ); ?></button>
            <template data-sc-curated-section-template><section class="sc-curated-admin__section" data-sc-curated-section><div class="sc-curated-admin__section-head"><strong><?php esc_html_e( 'Section', 'sustainable-catalyst-library' ); ?></strong><button type="button" class="button-link-delete" data-sc-curated-remove-section><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button></div><input type="hidden" data-sc-section-index value="__S__"><input type="hidden" name="sc_curated_sections[__S__][section_urn]" value=""><label><span><?php esc_html_e( 'Section title', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_curated_sections[__S__][title]" maxlength="180"></label><label><span><?php esc_html_e( 'Section narrative', 'sustainable-catalyst-library' ); ?></span><textarea name="sc_curated_sections[__S__][narrative]" maxlength="<?php echo esc_attr( (string) self::MAX_SECTION_NARRATIVE ); ?>"></textarea></label><div class="sc-curated-admin__items" data-sc-curated-items></div><button type="button" class="button" data-sc-curated-add-item><?php esc_html_e( 'Add public reference', 'sustainable-catalyst-library' ); ?></button></section></template>
            <template data-sc-curated-item-template><div class="sc-curated-admin__item" data-sc-curated-item><select name="sc_curated_sections[__S__][items][__I__][type]" aria-label="<?php esc_attr_e( 'Reference type', 'sustainable-catalyst-library' ); ?>"><?php foreach ( $ref_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><input type="number" min="1" inputmode="numeric" name="sc_curated_sections[__S__][items][__I__][id]" placeholder="<?php esc_attr_e( 'Public record ID', 'sustainable-catalyst-library' ); ?>"><input type="text" maxlength="180" name="sc_curated_sections[__S__][items][__I__][label]" placeholder="<?php esc_attr_e( 'Optional curator label', 'sustainable-catalyst-library' ); ?>"><input type="text" maxlength="400" name="sc_curated_sections[__S__][items][__I__][note]" placeholder="<?php esc_attr_e( 'Optional public note', 'sustainable-catalyst-library' ); ?>"><button type="button" class="button-link-delete" data-sc-curated-remove-item aria-label="<?php esc_attr_e( 'Remove reference', 'sustainable-catalyst-library' ); ?>">×</button></div></template>
        </div>
        <?php
    }

    public static function resolve_public_reference( $type, $id ) {
        $type = sanitize_key( (string) $type ); $id = absint( $id );
        if ( ! $id || ! isset( self::reference_registry()[ $type ] ) ) { return new WP_Error( 'sc_curated_reference_invalid', __( 'Unsupported curated-space reference.', 'sustainable-catalyst-library' ) ); }
        if ( 'public-claim' === $type ) {
            if ( ! class_exists( 'SC_Library_Public_Evidence_Claim_Navigation' ) ) { return new WP_Error( 'sc_curated_reference_unavailable', __( 'Public claim navigation is unavailable.', 'sustainable-catalyst-library' ) ); }
            $context = SC_Library_Public_Evidence_Claim_Navigation::claim_context( $id ); if ( is_wp_error( $context ) ) { return $context; }
            $r = (array) ( $context['claim'] ?? array() );
            return array( 'type' => $type, 'id' => $id, 'canonical_id' => (string) ( $r['canonical_id'] ?? '' ), 'title' => (string) ( $r['title'] ?? '' ), 'summary' => (string) ( $r['summary'] ?? $r['statement'] ?? '' ), 'url' => (string) ( $r['navigation_url'] ?? '' ), 'provenance' => 'canonical-public-research-claim' );
        }
        if ( 'public-evidence' === $type ) {
            if ( ! class_exists( 'SC_Library_Public_Evidence_Claim_Navigation' ) ) { return new WP_Error( 'sc_curated_reference_unavailable', __( 'Public evidence navigation is unavailable.', 'sustainable-catalyst-library' ) ); }
            $context = SC_Library_Public_Evidence_Claim_Navigation::evidence_context( $id ); if ( is_wp_error( $context ) ) { return $context; }
            $r = (array) ( $context['evidence'] ?? array() );
            return array( 'type' => $type, 'id' => $id, 'canonical_id' => (string) ( $r['canonical_id'] ?? '' ), 'title' => (string) ( $r['title'] ?? '' ), 'summary' => (string) ( $r['excerpt'] ?? '' ), 'url' => (string) ( $r['navigation_url'] ?? '' ), 'provenance' => 'canonical-public-evidence-note' );
        }
        if ( 'federation-manifest' === $type ) {
            if ( ! class_exists( 'SC_Library_Global_Research_Federation' ) ) { return new WP_Error( 'sc_curated_reference_unavailable', __( 'Federation is unavailable.', 'sustainable-catalyst-library' ) ); }
            $m = SC_Library_Global_Research_Federation::manifest_state( $id, 0, false ); if ( is_wp_error( $m ) || 'published' !== ( $m['status'] ?? '' ) ) { return new WP_Error( 'sc_curated_reference_private', __( 'That federation manifest is not public.', 'sustainable-catalyst-library' ) ); }
            return array( 'type' => $type, 'id' => $id, 'canonical_id' => (string) ( $m['manifest_urn'] ?? '' ), 'title' => (string) ( $m['title'] ?? '' ), 'summary' => sprintf( __( '%d published references', 'sustainable-catalyst-library' ), absint( $m['manifest']['reference_count'] ?? 0 ) ), 'url' => esc_url_raw( rest_url( SC_Library_Global_Research_Federation::REST_NAMESPACE . SC_Library_Global_Research_Federation::REST_ROUTE . '/manifests/' . $id ) ), 'provenance' => 'explicitly-published-federation-manifest' );
        }
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return new WP_Error( 'sc_curated_reference_unavailable', __( 'Public Library API is unavailable.', 'sustainable-catalyst-library' ) ); }
        $obj = SC_Library_API_Embeds_Interoperability::normalize_public_object( $type, get_post( $id ) );
        if ( is_wp_error( $obj ) ) { return $obj; }
        return array( 'type' => $type, 'id' => $id, 'canonical_id' => (string) ( $obj['canonical_id'] ?? '' ), 'title' => (string) ( $obj['title'] ?? '' ), 'summary' => (string) ( $obj['summary'] ?? '' ), 'url' => (string) ( $obj['canonical_url'] ?? '' ), 'provenance' => 'canonical-public-library-object' );
    }

    public static function sanitize_sections( $raw ) {
        $out = array();
        foreach ( array_slice( (array) $raw, 0, self::MAX_SECTIONS ) as $section ) {
            if ( ! is_array( $section ) ) { continue; }
            $title = self::clean( $section['title'] ?? '', 180 );
            $narrative = self::clean( $section['narrative'] ?? '', self::MAX_SECTION_NARRATIVE );
            $items = array();
            foreach ( array_slice( (array) ( $section['items'] ?? array() ), 0, self::MAX_ITEMS_PER_SECTION ) as $item ) {
                if ( ! is_array( $item ) ) { continue; }
                $type = sanitize_key( (string) ( $item['type'] ?? '' ) ); $id = absint( $item['id'] ?? 0 );
                if ( ! $type || ! $id || is_wp_error( self::resolve_public_reference( $type, $id ) ) ) { continue; }
                $items[] = array( 'type' => $type, 'id' => $id, 'label' => self::clean( $item['label'] ?? '', 180 ), 'note' => self::clean( $item['note'] ?? '', 400 ) );
            }
            if ( '' === $title && '' === $narrative && ! $items ) { continue; }
            $section_urn = self::clean( $section['section_urn'] ?? '', 200 );
            if ( 0 !== strpos( $section_urn, 'urn:sc:curated-section:' ) ) { $section_urn = 'urn:sc:curated-section:' . wp_generate_uuid4(); }
            $out[] = array( 'section_urn' => $section_urn, 'title' => $title, 'narrative' => $narrative, 'items' => $items );
        }
        return $out;
    }

    public function save_post( $post_id, $post, $update ) {
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( ! isset( $_POST['sc_curated_space_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_curated_space_nonce'] ) ), 'sc_curated_space_save_' . $post_id ) ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
        self::ensure_space_urn( $post_id );
        $kind = sanitize_key( (string) wp_unslash( $_POST['sc_curated_kind'] ?? 'research-collection' ) );
        if ( ! isset( self::kind_registry()[ $kind ] ) ) { $kind = 'research-collection'; }
        update_post_meta( $post_id, self::META_KIND, $kind );
        update_post_meta( $post_id, self::META_SUBTITLE, self::clean( wp_unslash( $_POST['sc_curated_subtitle'] ?? '' ), 240 ) );
        update_post_meta( $post_id, self::META_CURATOR_NOTE, self::clean( wp_unslash( $_POST['sc_curated_curator_note'] ?? '' ), self::MAX_CURATOR_NOTE ) );
        $raw = isset( $_POST['sc_curated_sections'] ) ? wp_unslash( $_POST['sc_curated_sections'] ) : array();
        update_post_meta( $post_id, self::META_SECTIONS, self::sanitize_sections( is_array( $raw ) ? $raw : array() ) );
    }

    private static function canonical_json( $value ) {
        if ( is_array( $value ) ) {
            if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) { ksort( $value ); }
            foreach ( $value as $k => $v ) { $value[ $k ] = self::canonical_json( $v ); }
        }
        return $value;
    }

    public static function manifest_sha256( array $payload ) {
        unset( $payload['generated_at'], $payload['manifest_sha256'] );
        return hash( 'sha256', wp_json_encode( self::canonical_json( $payload ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    public static function space_state( $post_id ) {
        $post = get_post( absint( $post_id ) );
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { return new WP_Error( 'sc_curated_space_unavailable', __( 'That curated knowledge space is not public.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $sections = get_post_meta( $post->ID, self::META_SECTIONS, true ); $sections = is_array( $sections ) ? $sections : array();
        $resolved = array(); $omitted = 0; $total = 0;
        foreach ( array_slice( $sections, 0, self::MAX_SECTIONS ) as $section ) {
            $items = array();
            foreach ( array_slice( (array) ( $section['items'] ?? array() ), 0, self::MAX_ITEMS_PER_SECTION ) as $item ) {
                $r = self::resolve_public_reference( $item['type'] ?? '', $item['id'] ?? 0 );
                if ( is_wp_error( $r ) ) { $omitted++; continue; }
                $r['curator_label'] = self::clean( $item['label'] ?? '', 180 );
                $r['curator_note'] = self::clean( $item['note'] ?? '', 400 );
                $r['reference_schema'] = self::REFERENCE_SCHEMA;
                $items[] = $r; $total++;
            }
            $resolved[] = array( 'schema' => self::SECTION_SCHEMA, 'section_urn' => self::clean( $section['section_urn'] ?? '', 200 ), 'title' => self::clean( $section['title'] ?? '', 180 ), 'narrative' => self::clean( $section['narrative'] ?? '', self::MAX_SECTION_NARRATIVE ), 'items' => $items, 'item_count' => count( $items ) );
        }
        $kind = sanitize_key( (string) get_post_meta( $post->ID, self::META_KIND, true ) );
        if ( ! isset( self::kind_registry()[ $kind ] ) ) { $kind = 'research-collection'; }
        $payload = array(
            'schema' => self::SPACE_SCHEMA,
            'version' => self::VERSION,
            'id' => absint( $post->ID ),
            'space_urn' => self::ensure_space_urn( $post->ID ),
            'kind' => $kind,
            'kind_label' => (string) self::kind_registry()[ $kind ],
            'title' => get_the_title( $post ),
            'subtitle' => self::clean( get_post_meta( $post->ID, self::META_SUBTITLE, true ), 240 ),
            'summary' => self::clean( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content, 700 ),
            'narrative' => wpautop( wp_kses_post( strip_shortcodes( (string) $post->post_content ) ) ),
            'curator_note' => self::clean( get_post_meta( $post->ID, self::META_CURATOR_NOTE, true ), self::MAX_CURATOR_NOTE ),
            'curator' => array( 'user_id' => absint( $post->post_author ), 'display_name' => self::clean( get_the_author_meta( 'display_name', $post->post_author ), 160 ) ),
            'published_at' => get_post_time( 'c', true, $post ),
            'updated_at' => get_post_modified_time( 'c', true, $post ),
            'sections' => $resolved,
            'section_count' => count( $resolved ),
            'reference_count' => $total,
            'omitted_unavailable_references' => $omitted,
            'canonical_url' => esc_url_raw( add_query_arg( 'curated_space', absint( $post->ID ), class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ) ) . '#curated-knowledge-spaces' ),
            'provenance' => array( 'source' => 'editorial-curated-reference-manifest', 'underlying_records_copied' => false, 'references_only' => true ),
            'boundaries' => self::contract(),
        );
        $payload['manifest_sha256'] = self::manifest_sha256( $payload );
        return $payload;
    }

    public static function index_payload() {
        $posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => self::MAX_INDEX, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) );
        $items = array();
        foreach ( $posts as $post ) {
            $state = self::space_state( $post->ID ); if ( is_wp_error( $state ) ) { continue; }
            $items[] = array_intersect_key( $state, array_flip( array( 'schema','version','id','space_urn','kind','kind_label','title','subtitle','summary','curator','updated_at','section_count','reference_count','canonical_url','manifest_sha256' ) ) );
        }
        return array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'spaces' => $items, 'count' => count( $items ), 'reference_types' => self::reference_registry(), 'boundaries' => self::contract() );
    }

    public function filter_public_object_payload( $payload, $type, $post ) {
        if ( 'curated-space' !== sanitize_key( (string) $type ) || ! $post instanceof WP_Post ) { return $payload; }
        $state = self::space_state( $post->ID ); if ( is_wp_error( $state ) ) { return $payload; }
        $payload['curated_space'] = array( 'kind' => $state['kind'], 'kind_label' => $state['kind_label'], 'subtitle' => $state['subtitle'], 'section_count' => $state['section_count'], 'reference_count' => $state['reference_count'], 'manifest_sha256' => $state['manifest_sha256'], 'context_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/' . $post->ID ) ) );
        return $payload;
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_capabilities' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/index', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_index' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_space' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/manifest', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_manifest' ) ) );
    }

    public function rest_capabilities() { return rest_ensure_response( array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'index_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/index' ) ), 'kinds' => self::kind_registry(), 'reference_types' => self::reference_registry(), 'boundaries' => self::contract() ) ); }
    public function rest_index() { return rest_ensure_response( self::index_payload() ); }
    public function rest_space( WP_REST_Request $request ) { $state = self::space_state( absint( $request['id'] ) ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_manifest( WP_REST_Request $request ) { $state = self::space_state( absint( $request['id'] ) ); if ( is_wp_error( $state ) ) { return $state; } return rest_ensure_response( array( 'schema' => self::MANIFEST_SCHEMA, 'version' => self::VERSION, 'space_urn' => $state['space_urn'], 'space_id' => $state['id'], 'title' => $state['title'], 'kind' => $state['kind'], 'sections' => $state['sections'], 'reference_count' => $state['reference_count'], 'manifest_sha256' => $state['manifest_sha256'], 'references_only' => true, 'private_content_included' => false ) ); }

    public function cors_headers( $response, $server, $request ) {
        if ( ! $request instanceof WP_REST_Request || 0 !== strpos( $request->get_route(), '/' . self::REST_NAMESPACE . self::REST_ROUTE ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
        $allowed = class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::allowed_origins() : array();
        if ( $origin && in_array( $origin, $allowed, true ) && method_exists( $response, 'header' ) ) {
            $response->header( 'Access-Control-Allow-Origin', $origin ); $response->header( 'Vary', 'Origin' ); $response->header( 'Access-Control-Allow-Credentials', 'false' ); $response->header( 'Access-Control-Allow-Methods', 'GET' );
        }
        return $response;
    }

    private static function render_space_html( array $space ) {
        ob_start(); ?>
        <article class="sc-curated-space" data-sc-curated-space-id="<?php echo esc_attr( (string) $space['id'] ); ?>">
            <header><p><?php echo esc_html( strtoupper( (string) $space['kind_label'] ) ); ?></p><h4><?php echo esc_html( (string) $space['title'] ); ?></h4><?php if ( $space['subtitle'] ) : ?><span><?php echo esc_html( (string) $space['subtitle'] ); ?></span><?php endif; ?><small><?php echo esc_html( sprintf( __( 'Curated by %s · %d public references', 'sustainable-catalyst-library' ), $space['curator']['display_name'] ?: __( 'Sustainable Catalyst', 'sustainable-catalyst-library' ), absint( $space['reference_count'] ) ) ); ?></small></header>
            <?php if ( $space['narrative'] ) : ?><div class="sc-curated-space__narrative"><?php echo wp_kses_post( $space['narrative'] ); ?></div><?php endif; ?>
            <?php if ( $space['curator_note'] ) : ?><aside><strong><?php esc_html_e( 'Curator note', 'sustainable-catalyst-library' ); ?></strong><p><?php echo esc_html( (string) $space['curator_note'] ); ?></p></aside><?php endif; ?>
            <div class="sc-curated-space__sections"><?php foreach ( $space['sections'] as $section ) : ?><section><h5><?php echo esc_html( (string) $section['title'] ); ?></h5><?php if ( $section['narrative'] ) : ?><p><?php echo esc_html( (string) $section['narrative'] ); ?></p><?php endif; ?><ol><?php foreach ( $section['items'] as $item ) : ?><li><div><small><?php echo esc_html( (string) ( self::reference_registry()[ $item['type'] ] ?? $item['type'] ) ); ?></small><strong><?php echo esc_html( (string) ( $item['curator_label'] ?: $item['title'] ) ); ?></strong><?php if ( $item['curator_note'] ) : ?><span><?php echo esc_html( (string) $item['curator_note'] ); ?></span><?php elseif ( $item['summary'] ) : ?><span><?php echo esc_html( self::clean( $item['summary'], 280 ) ); ?></span><?php endif; ?><code><?php echo esc_html( (string) $item['canonical_id'] ); ?></code></div><?php if ( $item['url'] ) : ?><a href="<?php echo esc_url( $item['url'] ); ?>"><?php esc_html_e( 'Open', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></li><?php endforeach; ?></ol></section><?php endforeach; ?></div>
            <footer><span><?php esc_html_e( 'References only · underlying records retain their own ownership, publication state, provenance, and access rules.', 'sustainable-catalyst-library' ); ?></span><code><?php echo esc_html( (string) $space['manifest_sha256'] ); ?></code></footer>
        </article>
        <?php return ob_get_clean();
    }

    public function shortcode_index( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Research Collections, Exhibitions & Curated Knowledge Spaces', 'sustainable-catalyst-library' ), 'limit' => 12 ), $atts, 'sc_research_curated_spaces' );
        $this->register_assets(); wp_enqueue_style( 'sc-library-curated-spaces-v540' ); wp_enqueue_script( 'sc-library-curated-spaces-v540' );
        $index = self::index_payload(); $spaces = array_slice( (array) $index['spaces'], 0, min( self::MAX_INDEX, max( 1, absint( $atts['limit'] ) ) ) );
        $selected = absint( $_GET['curated_space'] ?? 0 );
        ob_start(); ?>
        <section class="sc-curated-spaces" data-sc-curated-spaces data-api-base="<?php echo esc_attr( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>">
            <header><p><?php esc_html_e( 'Public curation layer', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( (string) $atts['title'] ); ?></h3><span><?php esc_html_e( 'Move through deliberately ordered public records with curator narrative and visible provenance. Curation does not rewrite the underlying evidence or record.', 'sustainable-catalyst-library' ); ?></span></header>
            <div class="sc-curated-spaces__truth" role="note"><strong><?php esc_html_e( 'Editorial selection, not truth ranking', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Inclusion means an editor intentionally placed a public reference in this space. It does not imply consensus, endorsement, ownership transfer, or privileged access.', 'sustainable-catalyst-library' ); ?></span></div>
            <?php if ( $spaces ) : ?><div class="sc-curated-spaces__index" role="list"><?php foreach ( $spaces as $item ) : ?><article role="listitem"><small><?php echo esc_html( (string) $item['kind_label'] ); ?></small><h4><?php echo esc_html( (string) $item['title'] ); ?></h4><p><?php echo esc_html( (string) $item['summary'] ); ?></p><span><?php echo esc_html( sprintf( __( '%d sections · %d references', 'sustainable-catalyst-library' ), absint( $item['section_count'] ), absint( $item['reference_count'] ) ) ); ?></span><a href="<?php echo esc_url( add_query_arg( 'curated_space', absint( $item['id'] ), class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ) ) . '#curated-knowledge-spaces' ); ?>" data-sc-curated-open="<?php echo esc_attr( (string) absint( $item['id'] ) ); ?>"><?php esc_html_e( 'Open curated space', 'sustainable-catalyst-library' ); ?></a></article><?php endforeach; ?></div><?php else : ?><p class="sc-curated-spaces__empty"><?php esc_html_e( 'No curated public spaces have been published yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
            <div class="sc-curated-spaces__detail" data-sc-curated-detail aria-live="polite"><?php if ( $selected ) : $space = self::space_state( $selected ); if ( ! is_wp_error( $space ) ) { echo self::render_space_html( $space ); } endif; ?></div>
        </section>
        <?php return ob_get_clean();
    }

    public function shortcode_space( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sc_curated_space' ); $state = self::space_state( absint( $atts['id'] ) );
        if ( is_wp_error( $state ) ) { return '<p class="sc-curated-spaces__empty">' . esc_html__( 'This curated public space is unavailable.', 'sustainable-catalyst-library' ) . '</p>'; }
        $this->register_assets(); wp_enqueue_style( 'sc-library-curated-spaces-v540' ); return self::render_space_html( $state );
    }
}
