<?php
/**
 * SC-PUB-SEO-02 — visitor-interest discovery preview.
 *
 * Called only on local/staging behind SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW.
 * The independent Python catalog returns candidates. WordPress alone decides
 * whether each suggested editorial link is currently public and canonical.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW' ) || ! SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW
    || ! in_array( wp_get_environment_type(), array( 'local', 'staging' ), true ) ) {
    return;
}

$sc_interest_id = wp_unique_id( 'sc-pub-interest-' );
$sc_interest_q = isset( $_GET['sc_pub_q'] ) && is_scalar( $_GET['sc_pub_q'] )
    ? substr( sanitize_text_field( wp_unslash( (string) $_GET['sc_pub_q'] ) ), 0, 120 ) : '';
$sc_interest_field = isset( $_GET['sc_pub_field'] ) && is_scalar( $_GET['sc_pub_field'] )
    ? sanitize_key( wp_unslash( (string) $_GET['sc_pub_field'] ) ) : '';
$sc_interest_topics = isset( $_GET['sc_pub_interests'] ) && is_scalar( $_GET['sc_pub_interests'] )
    ? substr( sanitize_text_field( wp_unslash( (string) $_GET['sc_pub_interests'] ) ), 0, 400 ) : '';
$sc_allowed_fields = array();
foreach ( $field_list as $sc_field_item ) {
    $sc_field_key = sanitize_key( (string) ( $sc_field_item['key'] ?? '' ) );
    if ( '' !== $sc_field_key ) {
        $sc_allowed_fields[ $sc_field_key ] = $sc_field_item;
    }
}
if ( ! isset( $sc_allowed_fields[ $sc_interest_field ] ) ) { $sc_interest_field = ''; }

$sc_results = array();
$sc_discovery_ready = false;
if ( class_exists( 'SC_Library_Python_Backend' ) && SC_Library_Python_Backend::configured() ) {
    $sc_backend = SC_Library_Python_Backend::base_url();
    $sc_endpoint = add_query_arg(
        array(
            'field' => $sc_interest_field,
            'q' => $sc_interest_q,
            'interests' => $sc_interest_topics,
            'limit' => 12,
        ),
        $sc_backend . '/api/library/v1/publications/discovery'
    );
    $sc_response = wp_remote_get(
        $sc_endpoint,
        array(
            'timeout' => 2,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'limit_response_size' => 262144,
            'headers' => array( 'Accept' => 'application/json' ),
        )
    );
    if ( ! is_wp_error( $sc_response ) && 200 === (int) wp_remote_retrieve_response_code( $sc_response ) ) {
        $sc_payload = json_decode( (string) wp_remote_retrieve_body( $sc_response ), true );
        if ( is_array( $sc_payload ) && 'candidates-only' === ( $sc_payload['state'] ?? null )
            && is_array( $sc_payload['results'] ?? null ) ) {
            $sc_discovery_ready = true;
            $sc_seen_urls = array();
            foreach ( array_slice( $sc_payload['results'], 0, 12 ) as $sc_entry ) {
                if ( ! is_array( $sc_entry ) ) { continue; }
                $sc_record_id = (string) ( $sc_entry['record_id'] ?? '' );
                if ( ! preg_match( '/^wordpress:([1-9][0-9]*):([a-z0-9_-]+):([1-9][0-9]*)$/D', $sc_record_id, $sc_match )
                    || (int) $sc_match[1] !== (int) get_current_blog_id() ) {
                    continue;
                }
                $sc_post = get_post( (int) $sc_match[3] );
                if ( ! $sc_post instanceof WP_Post
                    || $sc_post->post_type !== $sc_match[2]
                    || 'publish' !== $sc_post->post_status
                    || '' !== (string) $sc_post->post_password
                    || ! is_post_publicly_viewable( $sc_post ) ) {
                    continue;
                }
                $sc_url = get_permalink( $sc_post );
                if ( ! $sc_url || $sc_url !== (string) ( $sc_entry['url'] ?? '' )
                    || isset( $sc_seen_urls[ $sc_url ] ) ) {
                    continue;
                }
                $sc_seen_urls[ $sc_url ] = true;
                $sc_results[] = array(
                    'url' => $sc_url,
                    'title' => get_the_title( $sc_post ),
                    'reason' => sanitize_key( (string) ( $sc_entry['discovery_reason'] ?? '' ) ),
                );
            }
        }
    }
}
?>
<section class="sc-publications-interest" aria-labelledby="<?php echo esc_attr( $sc_interest_id ); ?>">
    <div class="sc-publications-interest__intro">
        <p class="sc-field-stack__eyebrow"><?php esc_html_e( 'Discover by interest', 'sustainable-catalyst-library' ); ?></p>
        <h3 id="<?php echo esc_attr( $sc_interest_id ); ?>"><?php esc_html_e( 'What would you like to explore?', 'sustainable-catalyst-library' ); ?></h3>
        <p><?php esc_html_e( 'Find editorial articles and Article Maps by field or a topic that interests you. Browse all fields below without a search.', 'sustainable-catalyst-library' ); ?></p>
    </div>
    <form class="sc-publications-interest__form" method="get" action="<?php echo esc_url( home_url( '/publications/' ) ); ?>" role="search">
        <div>
            <label for="<?php echo esc_attr( $sc_interest_id . '-query' ); ?>"><?php esc_html_e( 'Search publications', 'sustainable-catalyst-library' ); ?></label>
            <input id="<?php echo esc_attr( $sc_interest_id . '-query' ); ?>" name="sc_pub_q" type="search" maxlength="120" value="<?php echo esc_attr( $sc_interest_q ); ?>" placeholder="<?php esc_attr_e( 'e.g., carbon removal or international law', 'sustainable-catalyst-library' ); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr( $sc_interest_id . '-field' ); ?>"><?php esc_html_e( 'Field', 'sustainable-catalyst-library' ); ?></label>
            <select id="<?php echo esc_attr( $sc_interest_id . '-field' ); ?>" name="sc_pub_field">
                <option value=""><?php esc_html_e( 'All fields', 'sustainable-catalyst-library' ); ?></option>
                <?php foreach ( $sc_allowed_fields as $sc_key => $sc_field_item ) : ?>
                <option value="<?php echo esc_attr( $sc_key ); ?>" <?php selected( $sc_interest_field, $sc_key ); ?>><?php echo esc_html( (string) $sc_field_item['title'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="<?php echo esc_attr( $sc_interest_id . '-interests' ); ?>"><?php esc_html_e( 'Interests (optional)', 'sustainable-catalyst-library' ); ?></label>
            <input id="<?php echo esc_attr( $sc_interest_id . '-interests' ); ?>" name="sc_pub_interests" type="text" maxlength="400" value="<?php echo esc_attr( $sc_interest_topics ); ?>" placeholder="<?php esc_attr_e( 'e.g., climate, modeling, governance', 'sustainable-catalyst-library' ); ?>">
        </div>
        <button type="submit"><?php esc_html_e( 'Explore publications', 'sustainable-catalyst-library' ); ?></button>
    </form>

    <?php if ( $sc_discovery_ready && $sc_results ) : ?>
    <div class="sc-publications-interest__results" aria-live="polite">
        <h4><?php esc_html_e( 'Related publications', 'sustainable-catalyst-library' ); ?></h4>
        <ul>
            <?php foreach ( $sc_results as $sc_result ) : ?>
                <li><a href="<?php echo esc_url( $sc_result['url'] ); ?>"><?php echo esc_html( $sc_result['title'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php elseif ( $sc_interest_q || $sc_interest_field || $sc_interest_topics ) : ?>
        <p class="sc-publications-interest__empty"><?php esc_html_e( 'No verified suggestions are available for this selection yet. Browse a field below or try another topic.', 'sustainable-catalyst-library' ); ?></p>
    <?php endif; ?>

    <?php if ( class_exists( 'SC_Library_Publications_Topic_Request', false )
        && SC_Library_Publications_Topic_Request::enabled() ) : ?>
    <section class="sc-publications-interest__topic-request" aria-label="<?php esc_attr_e( 'Suggest a publication topic', 'sustainable-catalyst-library' ); ?>">
        <?php
        $sc_topic_response = isset( $_GET['sc_pub_topic_request'] ) && is_scalar( $_GET['sc_pub_topic_request'] )
            ? sanitize_key( wp_unslash( (string) $_GET['sc_pub_topic_request'] ) ) : '';
        $sc_topic_messages = array(
            'received' => __( 'Your topic suggestion was received for editorial review. Submission does not guarantee an article or a reply.', 'sustainable-catalyst-library' ),
            'invalid' => __( 'Please review the topic and try again.', 'sustainable-catalyst-library' ),
            'rate_limited' => __( 'You have reached the current suggestion limit. Please try again later.', 'sustainable-catalyst-library' ),
            'unavailable' => __( 'Topic requests are temporarily unavailable.', 'sustainable-catalyst-library' ),
        );
        if ( isset( $sc_topic_messages[ $sc_topic_response ] ) ) :
        ?>
            <p role="status"><?php echo esc_html( $sc_topic_messages[ $sc_topic_response ] ); ?></p>
        <?php endif; ?>
        <details <?php echo ( ( $sc_interest_q || $sc_interest_field || $sc_interest_topics ) && empty( $sc_results ) ) ? 'open' : ''; ?>>
            <summary><?php esc_html_e( "Can't find what you're looking for? Request a topic", 'sustainable-catalyst-library' ); ?></summary>
            <p><?php esc_html_e( 'Suggest a subject for future editorial coverage. No account or email address is required. Suggestions are private and reviewed before any content is planned.', 'sustainable-catalyst-library' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-publications-interest__topic-form">
                <input type="hidden" name="action" value="<?php echo esc_attr( SC_Library_Publications_Topic_Request::ACTION ); ?>">
                <?php wp_nonce_field( SC_Library_Publications_Topic_Request::ACTION, 'sc_pub_topic_nonce' ); ?>
                <div class="sc-publications-interest__honeypot" aria-hidden="true">
                    <label for="<?php echo esc_attr( $sc_interest_id . '-website' ); ?>"><?php esc_html_e( 'Website', 'sustainable-catalyst-library' ); ?></label>
                    <input id="<?php echo esc_attr( $sc_interest_id . '-website' ); ?>" name="sc_pub_topic_website" type="text" tabindex="-1" autocomplete="off">
                </div>
                <div>
                    <label for="<?php echo esc_attr( $sc_interest_id . '-suggest' ); ?>"><?php esc_html_e( 'Topic you would like covered', 'sustainable-catalyst-library' ); ?></label>
                    <input id="<?php echo esc_attr( $sc_interest_id . '-suggest' ); ?>" type="text" name="sc_pub_topic_title" required minlength="3" maxlength="160" placeholder="<?php esc_attr_e( 'e.g., carbon removal measurement', 'sustainable-catalyst-library' ); ?>">
                </div>
                <div>
                    <label for="<?php echo esc_attr( $sc_interest_id . '-context' ); ?>"><?php esc_html_e( 'What would you like to learn? (optional)', 'sustainable-catalyst-library' ); ?></label>
                    <textarea id="<?php echo esc_attr( $sc_interest_id . '-context' ); ?>" name="sc_pub_topic_context" rows="3" maxlength="600"></textarea>
                </div>
                <button type="submit"><?php esc_html_e( 'Submit topic suggestion', 'sustainable-catalyst-library' ); ?></button>
            </form>
        </details>
    </section>
    <?php endif; ?>

    <nav aria-label="<?php esc_attr_e( 'Browse all Publications fields', 'sustainable-catalyst-library' ); ?>">
        <h4><?php esc_html_e( 'Browse by field', 'sustainable-catalyst-library' ); ?></h4>
        <ul class="sc-publications-interest__fields">
            <?php foreach ( $sc_allowed_fields as $sc_field_item ) :
                $sc_archive = (string) ( $sc_field_item['browse_url'] ?? '' );
                if ( ! str_starts_with( $sc_archive, '/category/' ) ) { continue; }
                ?>
                <li><a href="<?php echo esc_url( home_url( $sc_archive ) ); ?>"><?php echo esc_html( (string) $sc_field_item['title'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</section>
