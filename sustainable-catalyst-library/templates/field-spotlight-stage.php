<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$panels = array_values( $field['panels'] ?? array() );
if ( ! $panels ) { return; }
$requested_panel = isset( $_GET['sc_publication_panel'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publication_panel'] ) ) : '';
$initial_index = 0;
if ( $requested_panel ) {
    foreach ( $panels as $index => $candidate_panel ) {
        if ( sanitize_title( (string) ( $candidate_panel['key'] ?? '' ) ) === $requested_panel ) { $initial_index = $index; break; }
    }
}
$initial = $panels[ $initial_index ];
$limit = 8;
$primary = array_slice( $panels, 0, $limit );
$additional = array_slice( $panels, $limit );
$field_key = (string) ( $field['key'] ?? '' );
$field_id = $stage_id ?: wp_unique_id( 'sc-field-spotlight-' );
$additional_id = $field_id . '-additional-fields';
$field_number = absint( $field_number ?? ( $field['order'] ?? 1 ) );
$include_data = ! empty( $include_data );
?>
<section id="<?php echo esc_attr( $field_id ); ?>" class="sc-field-spotlight<?php echo ! empty( $master_stage ) ? ' sc-field-spotlight--master-stage' : ''; ?>" data-field-key="<?php echo esc_attr( $field_key ); ?>" data-initial-panel-key="<?php echo esc_attr( (string) $initial['key'] ); ?>" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr( (string) $interval ); ?>" data-pause-on-hover="<?php echo $pause_on_hover ? 'true' : 'false'; ?>" data-secondary-open="false" data-label-pause="Pause automatic rotation" data-label-play="Play automatic rotation" data-status-auto="Auto" data-status-paused="Paused" data-status-hold="Hold" data-status-static="Static" data-status-reduced="Reduced motion" style="--sc-field-spotlight-interval: <?php echo esc_attr( (string) $interval ); ?>ms;" aria-labelledby="<?php echo esc_attr( $field_id . '-title' ); ?>">
    <header class="sc-field-spotlight__masthead">
        <div class="sc-field-spotlight__identity">
            <p class="sc-field-spotlight__eyebrow" data-field-eyebrow><span aria-hidden="true">KL</span> KNOWLEDGE LIBRARY · FIELD <?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></p>
            <h2 id="<?php echo esc_attr( $field_id . '-title' ); ?>" data-field-title><?php echo esc_html( (string) $field['title'] ); ?></h2>
            <p class="sc-field-spotlight__description" data-field-description<?php echo empty( $field['description'] ) ? ' hidden' : ''; ?>><?php echo esc_html( (string) ( $field['description'] ?? '' ) ); ?></p>
        </div>
        <div class="sc-field-spotlight__telemetry">
            <span class="sc-field-spotlight__status"><i aria-hidden="true"></i><span data-playback-status><?php echo $autoplay ? 'AUTO' : 'PAUSED'; ?></span></span>
            <span class="sc-field-spotlight__panel-count" data-field-panel-count><?php echo esc_html( (string) count( $panels ) ); ?> PANELS</span>
            <a class="sc-field-spotlight__browse-link" data-field-browse href="<?php echo esc_url( home_url( (string) $field['browse_url'] ) ); ?>">Browse field ↗</a>
        </div>
    </header>
    <div class="sc-field-spotlight__progress" aria-hidden="true"><span data-panel-progress></span></div>

    <nav class="sc-field-spotlight__panel-nav" data-panel-nav aria-label="<?php echo esc_attr( sprintf( '%s series panels', $field['title'] ) ); ?>">
        <div class="sc-field-spotlight__primary-tabs" data-primary-tabs role="tablist">
            <?php foreach ( $primary as $index => $panel ) : ?>
                <?php $panel_href = add_query_arg( array( 'sc_publication_field' => $field_key, 'sc_publication_panel' => (string) $panel['key'] ) ) . '#' . $field_id; ?>
                <a role="tab" class="sc-field-spotlight__tab<?php echo $initial_index === $index ? ' is-active' : ''; ?>" href="<?php echo esc_url( $panel_href ); ?>" data-panel-key="<?php echo esc_attr( (string) $panel['key'] ); ?>" data-panel-fallback="server" aria-selected="<?php echo $initial_index === $index ? 'true' : 'false'; ?>" tabindex="<?php echo $initial_index === $index ? '0' : '-1'; ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><strong><?php echo esc_html( (string) $panel['title'] ); ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
        <button type="button" class="sc-field-spotlight__more" aria-expanded="false" aria-controls="<?php echo esc_attr( $additional_id ); ?>" data-more-toggle<?php echo $additional ? '' : ' hidden'; ?>>
            <span class="sc-field-spotlight__more-icon" aria-hidden="true">+</span>
            <span data-more-label><?php echo esc_html( (string) $labels['additional_label'] ); ?></span>
            <small data-more-count><?php echo esc_html( (string) count( $additional ) ); ?></small>
        </button>
        <div id="<?php echo esc_attr( $additional_id ); ?>" class="sc-field-spotlight__additional-tabs" data-additional-tabs hidden aria-hidden="true" role="tablist" aria-label="Additional panels">
            <?php foreach ( $additional as $extra_index => $panel ) : $index = $limit + $extra_index; ?>
                <?php $panel_href = add_query_arg( array( 'sc_publication_field' => $field_key, 'sc_publication_panel' => (string) $panel['key'] ) ) . '#' . $field_id; ?>
                <a role="tab" class="sc-field-spotlight__tab<?php echo $initial_index === $index ? ' is-active' : ''; ?>" href="<?php echo esc_url( $panel_href ); ?>" data-panel-key="<?php echo esc_attr( (string) $panel['key'] ); ?>" data-panel-fallback="server" aria-selected="<?php echo $initial_index === $index ? 'true' : 'false'; ?>" tabindex="<?php echo $initial_index === $index ? '0' : '-1'; ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><strong><?php echo esc_html( (string) $panel['title'] ); ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="sc-field-spotlight__stage" aria-live="polite">
        <div class="sc-field-spotlight__stage-meta">
            <p class="sc-field-spotlight__breadcrumb"><?php echo esc_html( (string) $field['title'] . ( ! empty( $initial['source_group'] ) ? ' / ' . $initial['source_group'] : '' ) ); ?></p>
            <p class="sc-field-spotlight__position">PANEL <strong><?php echo esc_html( str_pad( (string) ( $initial_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></strong> / <?php echo esc_html( str_pad( (string) count( $panels ), 2, '0', STR_PAD_LEFT ) ); ?></p>
        </div>

        <article class="sc-field-spotlight__hero">
            <a class="sc-field-spotlight__hero-media" href="<?php echo esc_url( (string) $initial['hero']['url'] ); ?>">
                <?php if ( ! empty( $initial['hero']['thumbnail']['url'] ) ) : ?>
                    <img src="<?php echo esc_url( (string) $initial['hero']['thumbnail']['url'] ); ?>" alt="<?php echo esc_attr( (string) $initial['hero']['thumbnail']['alt'] ); ?>" loading="eager">
                <?php else : ?>
                    <span class="sc-field-spotlight__placeholder"><strong>KL</strong><small>ARTICLE MAP</small></span>
                <?php endif; ?>
            </a>
            <div class="sc-field-spotlight__hero-copy">
                <p class="sc-field-spotlight__hero-label"><?php echo esc_html( (string) $labels['hero_label'] ); ?></p>
                <h3><?php echo esc_html( (string) $initial['hero']['title'] ); ?></h3>
                <p class="sc-field-spotlight__hero-meta"<?php echo empty( $initial['hero']['metadata'] ) ? ' hidden' : ''; ?>><?php echo esc_html( (string) ( $initial['hero']['metadata'] ?? '' ) ); ?></p>
                <p class="sc-field-spotlight__hero-description"><?php echo esc_html( (string) ( $initial['hero']['description'] ?: 'Use the Article Map to move through the complete series, its structure, and related research pathways.' ) ); ?></p>
                <a class="sc-field-spotlight__hero-action" href="<?php echo esc_url( (string) $initial['hero']['url'] ); ?>"><?php echo esc_html( (string) $initial['hero']['cta'] ); ?> <span aria-hidden="true">↗</span></a>
            </div>
        </article>

        <section class="sc-field-spotlight__selected" aria-label="<?php echo esc_attr( (string) $labels['selected_label'] ); ?>">
            <header class="sc-field-spotlight__selected-head"><div><h4><?php echo esc_html( (string) $labels['selected_label'] ); ?></h4></div><span data-slot-count><?php echo esc_html( (string) $initial['slot_count'] ); ?> SLOTS</span></header>
            <div class="sc-field-spotlight__cards" data-supporting-cards>
                <?php foreach ( $initial['articles'] as $index => $article ) : ?>
                    <article class="sc-field-spotlight__card">
                        <a class="sc-field-spotlight__card-media" href="<?php echo esc_url( (string) $article['url'] ); ?>">
                            <?php if ( ! empty( $article['thumbnail']['url'] ) ) : ?><img src="<?php echo esc_url( (string) $article['thumbnail']['url'] ); ?>" alt="<?php echo esc_attr( (string) $article['thumbnail']['alt'] ); ?>" loading="lazy"><?php else : ?><span class="sc-field-spotlight__placeholder sc-field-spotlight__placeholder--small"><strong>KL</strong></span><?php endif; ?>
                        </a>
                        <div class="sc-field-spotlight__card-copy"><p class="sc-field-spotlight__card-number">SELECTED ARTICLE <?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></p><h5><a href="<?php echo esc_url( (string) $article['url'] ); ?>"><?php echo esc_html( (string) $article['title'] ); ?></a></h5><?php if ( $article['metadata'] ) : ?><p class="sc-field-spotlight__card-meta"><?php echo esc_html( (string) $article['metadata'] ); ?></p><?php endif; ?><?php if ( $article['summary'] ) : ?><p class="sc-field-spotlight__card-summary"><?php echo esc_html( (string) $article['summary'] ); ?></p><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="sc-field-spotlight__empty" data-empty-state<?php echo $initial['articles'] ? ' hidden' : ''; ?>>No supporting articles are selected for this panel yet. The Article Map remains available as the permanent hero.</p>
        </section>

        <footer class="sc-field-spotlight__controls">
            <button type="button" data-panel-prev>← Previous panel</button>
            <button type="button" class="sc-field-spotlight__playback" data-panel-toggle aria-pressed="<?php echo $autoplay ? 'false' : 'true'; ?>" aria-label="<?php echo esc_attr( $autoplay ? 'Pause automatic rotation' : 'Play automatic rotation' ); ?>"><span data-panel-toggle-icon aria-hidden="true"><?php echo $autoplay ? 'Ⅱ' : '▶'; ?></span><span data-panel-toggle-text><?php echo $autoplay ? 'Pause' : 'Play'; ?></span></button>
            <div data-panel-index><strong><?php echo esc_html( (string) $initial['title'] ); ?></strong><span><?php echo esc_html( str_pad( (string) ( $initial_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?> / <?php echo esc_html( str_pad( (string) count( $panels ), 2, '0', STR_PAD_LEFT ) ); ?></span></div>
            <button type="button" data-panel-next>Next panel →</button>
        </footer>
    </div>
    <?php if ( $include_data ) : ?><script type="application/json" class="sc-field-spotlight__data"><?php echo wp_json_encode( array( 'field' => $field, 'labels' => $labels ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script><?php endif; ?>
</section>
