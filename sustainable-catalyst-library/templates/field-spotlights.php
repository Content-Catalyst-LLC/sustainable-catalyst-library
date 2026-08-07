<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-spotlights-' );
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-spotlights" data-sc-field-spotlights="v4.3.7">
<?php $field_number = 0; foreach ( $fields as $field_index => $field ) : $field_number++;
    $panels = array_values( $field['panels'] );
    if ( ! $panels ) { continue; }
    $initial = $panels[0];
    $limit = 8;
    $primary = array_slice( $panels, 0, $limit );
    $additional = array_slice( $panels, $limit );
    $field_id = $instance_id . '-' . sanitize_html_class( (string) $field['key'] );
?>
<section id="<?php echo esc_attr( $field_id ); ?>" class="sc-field-spotlight" data-field-key="<?php echo esc_attr( (string) $field['key'] ); ?>" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr( (string) $interval ); ?>" data-pause-on-hover="<?php echo $pause_on_hover ? 'true' : 'false'; ?>" data-secondary-open="false" data-label-pause="Pause automatic rotation" data-label-play="Play automatic rotation" data-status-auto="Auto" data-status-paused="Paused" data-status-hold="Hold" data-status-static="Static" data-status-reduced="Reduced motion" style="--sc-field-spotlight-interval: <?php echo esc_attr( (string) $interval ); ?>ms;" aria-labelledby="<?php echo esc_attr( $field_id . '-title' ); ?>">
    <header class="sc-field-spotlight__masthead">
        <div class="sc-field-spotlight__identity">
            <p class="sc-field-spotlight__eyebrow"><span aria-hidden="true">KL</span> KNOWLEDGE LIBRARY · FIELD <?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></p>
            <h2 id="<?php echo esc_attr( $field_id . '-title' ); ?>"><?php echo esc_html( (string) $field['title'] ); ?></h2>
            <?php if ( ! empty( $field['description'] ) ) : ?><p class="sc-field-spotlight__description"><?php echo esc_html( (string) $field['description'] ); ?></p><?php endif; ?>
        </div>
        <div class="sc-field-spotlight__telemetry">
            <span class="sc-field-spotlight__status"><i aria-hidden="true"></i><span data-playback-status><?php echo $autoplay ? 'AUTO' : 'PAUSED'; ?></span></span>
            <span><?php echo esc_html( (string) count( $panels ) ); ?> PANELS</span>
            <a href="<?php echo esc_url( home_url( (string) $field['browse_url'] ) ); ?>">Browse field ↗</a>
        </div>
    </header>
    <div class="sc-field-spotlight__progress" aria-hidden="true"><span data-panel-progress></span></div>

    <nav class="sc-field-spotlight__panel-nav" aria-label="<?php echo esc_attr( sprintf( '%s series panels', $field['title'] ) ); ?>">
        <div class="sc-field-spotlight__primary-tabs" role="tablist">
            <?php foreach ( $primary as $index => $panel ) : ?>
                <button type="button" role="tab" class="sc-field-spotlight__tab<?php echo 0 === $index ? ' is-active' : ''; ?>" data-panel-key="<?php echo esc_attr( (string) $panel['key'] ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><strong><?php echo esc_html( (string) $panel['title'] ); ?></strong>
                </button>
            <?php endforeach; ?>
        </div>
        <?php if ( $additional ) : ?>
            <button type="button" class="sc-field-spotlight__more" aria-expanded="false" data-more-toggle>
                <span class="sc-field-spotlight__more-icon" aria-hidden="true">+</span>
                <span data-more-label><?php echo esc_html( (string) $labels['additional_label'] ); ?></span>
                <small><?php echo esc_html( (string) count( $additional ) ); ?></small>
            </button>
            <div class="sc-field-spotlight__additional-tabs" data-additional-tabs hidden role="tablist" aria-label="Additional panels">
                <?php foreach ( $additional as $extra_index => $panel ) : $index = $limit + $extra_index; ?>
                    <button type="button" role="tab" class="sc-field-spotlight__tab" data-panel-key="<?php echo esc_attr( (string) $panel['key'] ); ?>" aria-selected="false">
                        <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><strong><?php echo esc_html( (string) $panel['title'] ); ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </nav>

    <div class="sc-field-spotlight__stage" aria-live="polite">
        <div class="sc-field-spotlight__stage-meta">
            <p class="sc-field-spotlight__breadcrumb"><?php echo esc_html( (string) $field['title'] . ( $initial['source_group'] ? ' / ' . $initial['source_group'] : '' ) ); ?></p>
            <p class="sc-field-spotlight__position">PANEL <strong>01</strong> / <?php echo esc_html( str_pad( (string) count( $panels ), 2, '0', STR_PAD_LEFT ) ); ?></p>
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
                <p class="sc-field-spotlight__hero-label"><?php echo esc_html( (string) $labels['hero_label'] ); ?> · HERO</p>
                <h3><?php echo esc_html( (string) $initial['hero']['title'] ); ?></h3>
                <?php if ( ! empty( $initial['hero']['metadata'] ) ) : ?><p class="sc-field-spotlight__hero-meta"><?php echo esc_html( (string) $initial['hero']['metadata'] ); ?></p><?php endif; ?>
                <p class="sc-field-spotlight__hero-description"><?php echo esc_html( (string) ( $initial['hero']['description'] ?: 'Use the Article Map to move through the complete series, its structure, and related research pathways.' ) ); ?></p>
                <a class="sc-field-spotlight__hero-action" href="<?php echo esc_url( (string) $initial['hero']['url'] ); ?>"><?php echo esc_html( (string) $initial['hero']['cta'] ); ?> <span aria-hidden="true">↗</span></a>
            </div>
        </article>

        <section class="sc-field-spotlight__selected" aria-label="<?php echo esc_attr( (string) $labels['selected_label'] ); ?>">
            <header class="sc-field-spotlight__selected-head"><div><p>CURATED FROM THIS SERIES</p><h4><?php echo esc_html( (string) $labels['selected_label'] ); ?></h4></div><span data-slot-count><?php echo esc_html( (string) $initial['slot_count'] ); ?> SLOTS</span></header>
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
            <div data-panel-index><strong><?php echo esc_html( (string) $initial['title'] ); ?></strong><span>01 / <?php echo esc_html( str_pad( (string) count( $panels ), 2, '0', STR_PAD_LEFT ) ); ?></span></div>
            <button type="button" data-panel-next>Next panel →</button>
        </footer>
    </div>
    <script type="application/json" class="sc-field-spotlight__data"><?php echo wp_json_encode( array( 'field' => $field, 'labels' => $labels ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</section>
<?php endforeach; ?>
</div>
