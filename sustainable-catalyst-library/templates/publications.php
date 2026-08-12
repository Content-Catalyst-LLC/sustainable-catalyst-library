<?php
/** Publications v4.3.22.4 server-authoritative Spotlight template. Variables: $fields, $heading, $intro, $labels, $instance_id. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$requested_field = isset( $_GET['sc_publications_field'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publications_field'] ) ) : '';
$requested_map = isset( $_GET['sc_publications_map'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publications_map'] ) ) : '';
$initial_field_index = 0;
if ( $requested_field ) {
    foreach ( $fields as $field_index => $candidate_field ) {
        if ( sanitize_title( (string) ( $candidate_field['key'] ?? '' ) ) === $requested_field ) {
            $initial_field_index = $field_index;
            break;
        }
    }
}
$initial_field = $fields[ $initial_field_index ];
$initial_topic_index = min( max( 0, absint( $initial_field['default_index'] ?? 0 ) ), max( 0, count( $initial_field['topics'] ) - 1 ) );
if ( $requested_map ) {
    foreach ( $initial_field['topics'] as $topic_index => $candidate_topic ) {
        if ( sanitize_title( (string) ( $candidate_topic['key'] ?? '' ) ) === $requested_map ) {
            $initial_topic_index = $topic_index;
            break;
        }
    }
}
$initial_topic = $initial_field['topics'][ $initial_topic_index ];
$total_maps = array_sum( array_map( static fn( $field ) => count( $field['topics'] ), $fields ) );
$payload_fields = array();

foreach ( $fields as $field_index => $field ) {
    $payload_topics = array();
    foreach ( $field['topics'] as $topic ) {
        $payload_articles = array();
        foreach ( $topic['articles'] as $article ) {
            $payload_articles[] = array(
                'title' => (string) $article['title'],
                'url' => (string) $article['url'],
            );
        }
        $payload_topics[] = array(
            'key' => (string) $topic['key'],
            'title' => (string) $topic['title'],
            'group' => (string) $topic['group'],
            'description' => (string) $topic['description'],
            'mapTitle' => (string) $topic['map_title'],
            'mapUrl' => home_url( (string) $topic['map_url'] ),
            'mapCta' => (string) ( $topic['map_cta'] ?: $labels['map_cta'] ),
            'articleSource' => (string) $topic['article_source'],
            'articles' => $payload_articles,
        );
    }
    $payload_fields[] = array(
        'key' => (string) $field['key'],
        'title' => (string) $field['title'],
        'description' => (string) $field['description'],
        'count' => count( $payload_topics ),
        'defaultIndex' => absint( $field['default_index'] ?? 0 ),
        'topics' => $payload_topics,
    );
}

$payload = array(
    'fields' => $payload_fields,
    'labels' => array(
        'areas' => (string) $labels['areas_label'],
        'map' => (string) $labels['map_label'],
        'mapCta' => (string) $labels['map_cta'],
        'previous' => (string) $labels['previous_label'],
        'next' => (string) $labels['next_label'],
        'select' => (string) $labels['select_label'],
        'heroDescription' => (string) $labels['hero_description'],
    ),
);
?>
<section id="<?php echo esc_attr( $instance_id ); ?>" class="sc-publications" data-sc-publications="v4.3.22.4" data-sc-publications-runtime-state="server" data-sc-publications-navigation="server" data-initial-field-key="<?php echo esc_attr( (string) $initial_field['key'] ); ?>" data-initial-map-key="<?php echo esc_attr( (string) $initial_topic['key'] ); ?>" aria-label="<?php esc_attr_e( 'Sustainable Catalyst Publications', 'sustainable-catalyst-library' ); ?>">
    <header class="sc-publications__masthead">
        <div class="sc-publications__identity">
            <p class="sc-publications__system-id"><span aria-hidden="true">KL</span> <?php echo esc_html( (string) $labels['eyebrow'] ); ?></p>
            <h2><?php echo esc_html( $heading ); ?></h2>
        </div>
        <?php if ( $intro ) : ?><p class="sc-publications__intro"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
        <div class="sc-publications__telemetry" aria-label="<?php esc_attr_e( 'Publication coverage', 'sustainable-catalyst-library' ); ?>">
            <span><?php echo esc_html( count( $fields ) ); ?> <?php echo esc_html( strtoupper( (string) $labels['fields_label'] ) ); ?></span>
            <span><?php echo esc_html( $total_maps ); ?> <?php echo esc_html( strtoupper( (string) $labels['areas_label'] ) ); ?></span>
        </div>
    </header>

    <div class="sc-publications__field-deck" role="tablist" aria-label="<?php esc_attr_e( 'Publication fields', 'sustainable-catalyst-library' ); ?>">
        <?php foreach ( $fields as $field_index => $field ) : ?>
            <?php $field_href = add_query_arg( array( 'sc_publications_field' => (string) $field['key'] ), remove_query_arg( 'sc_publications_map' ) ) . '#' . $instance_id; ?>
            <a role="tab" class="sc-publications__field-tab<?php echo $initial_field_index === $field_index ? ' is-active' : ''; ?>" href="<?php echo esc_url( $field_href ); ?>" data-field-index="<?php echo esc_attr( (string) $field_index ); ?>" data-field-fallback="server" data-field-key="<?php echo esc_attr( (string) $field['key'] ); ?>" aria-selected="<?php echo $initial_field_index === $field_index ? 'true' : 'false'; ?>">
                <span class="sc-publications__field-number"><?php echo esc_html( str_pad( (string) ( $field_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                <strong><?php echo esc_html( $field['title'] ); ?></strong>
                <small><?php echo esc_html( count( $field['topics'] ) ); ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="sc-publications__viewport" tabindex="-1">
        <header class="sc-publications__field-header">
            <div class="sc-publications__field-kicker">
                <span class="sc-publications__active-field-number"><?php echo esc_html( str_pad( (string) ( $initial_field_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                <span class="sc-publications__field-position"><?php echo esc_html( str_pad( (string) ( $initial_field_index + 1 ), 2, '0', STR_PAD_LEFT ) . ' / ' . str_pad( (string) count( $fields ), 2, '0', STR_PAD_LEFT ) ); ?></span>
            </div>
            <div class="sc-publications__field-heading">
                <h3><?php echo esc_html( $initial_field['title'] ); ?></h3>
                <p class="sc-publications__field-description"<?php echo $initial_field['description'] ? '' : ' hidden'; ?>><?php echo esc_html( (string) $initial_field['description'] ); ?></p>
            </div>
            <div class="sc-publications__area-count"><strong><?php echo esc_html( count( $initial_field['topics'] ) ); ?></strong><span><?php echo esc_html( strtoupper( (string) $labels['areas_label'] ) ); ?></span></div>
        </header>

        <div class="sc-publications__area-nav">
            <button type="button" class="sc-publications__arrow sc-publications__arrow--previous" data-area-previous aria-label="<?php echo esc_attr( (string) $labels['previous_label'] ); ?>">‹</button>
            <div class="sc-publications__area-rail" role="tablist" aria-label="<?php echo esc_attr( sprintf( __( 'Areas in %s', 'sustainable-catalyst-library' ), $initial_field['title'] ) ); ?>">
                <?php foreach ( $initial_field['topics'] as $index => $topic ) : ?>
                    <?php $topic_href = add_query_arg( array( 'sc_publications_field' => (string) $initial_field['key'], 'sc_publications_map' => (string) $topic['key'] ) ) . '#' . $instance_id; ?>
                    <a role="tab" href="<?php echo esc_url( $topic_href ); ?>" data-area-index="<?php echo esc_attr( (string) $index ); ?>" data-area-fallback="server" aria-selected="<?php echo $initial_topic_index === $index ? 'true' : 'false'; ?>" class="<?php echo $initial_topic_index === $index ? 'is-active' : ''; ?>"><?php echo esc_html( $topic['title'] ); ?></a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="sc-publications__arrow sc-publications__arrow--next" data-area-next aria-label="<?php echo esc_attr( (string) $labels['next_label'] ); ?>">›</button>
            <label class="sc-publications__area-select-wrap">
                <span><?php echo esc_html( (string) $labels['select_label'] ); ?></span>
                <select class="sc-publications__area-select" aria-label="<?php echo esc_attr( (string) $labels['select_label'] ); ?>">
                    <?php foreach ( $initial_field['topics'] as $index => $topic ) : ?><option value="<?php echo esc_attr( (string) $index ); ?>" <?php selected( $initial_topic_index, $index ); ?>><?php echo esc_html( $topic['title'] ); ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>

        <article class="sc-publications__stage" data-field-key="<?php echo esc_attr( (string) $initial_field['key'] ); ?>" data-map-key="<?php echo esc_attr( (string) $initial_topic['key'] ); ?>" data-article-source="<?php echo esc_attr( (string) $initial_topic['article_source'] ); ?>">
            <div class="sc-publications__stage-meta">
                <p class="sc-publications__stage-eyebrow"><?php echo esc_html( $initial_topic['group'] ? $initial_field['title'] . ' / ' . $initial_topic['group'] : $initial_field['title'] ); ?></p>
                <p class="sc-publications__map-position"><?php echo esc_html( str_pad( (string) ( $initial_topic_index + 1 ), 2, '0', STR_PAD_LEFT ) . ' / ' . str_pad( (string) count( $initial_field['topics'] ), 2, '0', STR_PAD_LEFT ) ); ?></p>
            </div>

            <div class="sc-publications__board">
                <a class="sc-publications__map-hero" href="<?php echo esc_url( home_url( (string) $initial_topic['map_url'] ) ); ?>">
                    <span class="sc-publications__map-marker" aria-hidden="true">MAP</span>
                    <span class="sc-publications__map-copy">
                        <span class="sc-publications__map-label"><?php echo esc_html( (string) $labels['map_label'] ); ?></span>
                        <strong><?php echo esc_html( $initial_topic['map_title'] ); ?></strong>
                        <span class="sc-publications__map-description"><?php echo esc_html( $initial_topic['description'] ?: (string) $labels['hero_description'] ); ?></span>
                    </span>
                    <span class="sc-publications__map-action"><?php echo esc_html( $initial_topic['map_cta'] ?: (string) $labels['map_cta'] ); ?> <span aria-hidden="true">↗</span></span>
                </a>

                <ol class="sc-publications__articles" aria-label="<?php echo esc_attr( sprintf( __( 'Selected publications for %s', 'sustainable-catalyst-library' ), $initial_topic['title'] ) ); ?>">
                    <?php for ( $i = 0; $i < 4; $i++ ) : $article = $initial_topic['articles'][ $i ] ?? null; ?>
                        <li<?php echo $article ? '' : ' hidden'; ?>>
                            <span class="sc-publications__row-number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                            <h4><a href="<?php echo $article ? esc_url( $article['url'] ) : '#'; ?>"><?php echo $article ? esc_html( $article['title'] ) : ''; ?></a></h4>
                            <a class="sc-publications__row-action" href="<?php echo $article ? esc_url( $article['url'] ) : '#'; ?>" aria-label="<?php echo $article ? esc_attr( sprintf( __( 'Read %s', 'sustainable-catalyst-library' ), $article['title'] ) ) : ''; ?>"><?php esc_html_e( 'Read', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">↗</span></a>
                        </li>
                    <?php endfor; ?>
                </ol>
            </div>

            <footer class="sc-publications__stage-controls">
                <button type="button" data-area-previous><span aria-hidden="true">←</span> <span class="sc-publications__previous-label"><?php echo esc_html( (string) $labels['previous_label'] ); ?></span></button>
                <div class="sc-publications__stage-index" aria-live="polite"><strong><?php echo esc_html( $initial_topic['title'] ); ?></strong><span><?php echo esc_html( str_pad( (string) ( $initial_topic_index + 1 ), 2, '0', STR_PAD_LEFT ) . ' / ' . str_pad( (string) count( $initial_field['topics'] ), 2, '0', STR_PAD_LEFT ) ); ?></span></div>
                <button type="button" data-area-next><span class="sc-publications__next-label"><?php echo esc_html( (string) $labels['next_label'] ); ?></span> <span aria-hidden="true">→</span></button>
            </footer>
        </article>
    </div>

    <noscript><p class="sc-publications__noscript">Publications navigation remains available through the field and Article Map links above.</p></noscript>

    <script type="application/json" class="sc-publications__data"><?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</section>
