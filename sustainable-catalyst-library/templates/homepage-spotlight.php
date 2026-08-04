<?php
/**
 * Public Knowledge Library Console template.
 *
 * Available variables: $pages, $controls, $tabs, $instance_id, $autoplay,
 * $interval, $loop, $pause_on_hover, $show_thumbnail_override,
 * $show_metadata_override, $heading, $intro.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section
    id="<?php echo esc_attr( $instance_id ); ?>"
    class="sc-homepage-spotlight sc-homepage-spotlight--console"
    data-sc-homepage-spotlight
    data-current="0"
    data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>"
    data-interval="<?php echo esc_attr( $interval ); ?>"
    data-loop="<?php echo $loop ? 'true' : 'false'; ?>"
    data-pause-on-hover="<?php echo $pause_on_hover ? 'true' : 'false'; ?>"
    data-label-pause="<?php esc_attr_e( 'Pause automatic rotation', 'sustainable-catalyst-library' ); ?>"
    data-label-play="<?php esc_attr_e( 'Play automatic rotation', 'sustainable-catalyst-library' ); ?>"
    data-status-auto="<?php esc_attr_e( 'Auto', 'sustainable-catalyst-library' ); ?>"
    data-status-paused="<?php esc_attr_e( 'Paused', 'sustainable-catalyst-library' ); ?>"
    data-status-hold="<?php esc_attr_e( 'Hold', 'sustainable-catalyst-library' ); ?>"
    data-status-static="<?php esc_attr_e( 'Static', 'sustainable-catalyst-library' ); ?>"
    data-status-reduced="<?php esc_attr_e( 'Reduced motion', 'sustainable-catalyst-library' ); ?>"
    style="--sc-spotlight-interval: <?php echo esc_attr( $interval ); ?>ms;"
    aria-label="<?php esc_attr_e( 'Curated Knowledge Library console', 'sustainable-catalyst-library' ); ?>"
>
    <header class="sc-homepage-spotlight__masthead">
        <div class="sc-homepage-spotlight__identity">
            <p class="sc-homepage-spotlight__system-id"><span aria-hidden="true">KL</span> <?php esc_html_e( 'Knowledge Library', 'sustainable-catalyst-library' ); ?></p>
            <?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
        </div>
        <?php if ( $intro ) : ?><p class="sc-homepage-spotlight__intro-copy"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
        <div class="sc-homepage-spotlight__telemetry" aria-label="<?php esc_attr_e( 'Console rotation status', 'sustainable-catalyst-library' ); ?>">
            <span class="sc-homepage-spotlight__status"><span class="sc-homepage-spotlight__status-dot" aria-hidden="true"></span><span data-sc-spotlight-status><?php echo esc_html( $autoplay ? __( 'Auto', 'sustainable-catalyst-library' ) : __( 'Paused', 'sustainable-catalyst-library' ) ); ?></span></span>
            <span class="sc-homepage-spotlight__screen" data-sc-spotlight-position aria-live="polite">01 / <?php echo esc_html( str_pad( (string) count( $pages ), 2, '0', STR_PAD_LEFT ) ); ?></span>
        </div>
    </header>

    <div class="sc-homepage-spotlight__progress" aria-hidden="true"><span data-sc-spotlight-progress></span></div>

    <?php if ( $tabs ) : ?>
        <div class="sc-homepage-spotlight__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Featured subjects', 'sustainable-catalyst-library' ); ?>">
            <?php foreach ( $pages as $page_index => $page ) : ?>
                <button
                    type="button"
                    role="tab"
                    id="<?php echo esc_attr( $instance_id . '-tab-' . $page['id'] ); ?>"
                    aria-controls="<?php echo esc_attr( $instance_id . '-page-' . $page['id'] ); ?>"
                    aria-selected="<?php echo 0 === $page_index ? 'true' : 'false'; ?>"
                    tabindex="<?php echo 0 === $page_index ? '0' : '-1'; ?>"
                    data-sc-spotlight-tab="<?php echo esc_attr( $page_index ); ?>"
                ><span><?php echo esc_html( str_pad( (string) ( $page_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $page['title'] ); ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="sc-homepage-spotlight__viewport" data-sc-spotlight-viewport>
        <?php foreach ( $pages as $page_index => $page ) :
            $card_count = count( $page['cards'] );
            $five_card_page = 5 === $card_count;
            ?>
            <section
                id="<?php echo esc_attr( $instance_id . '-page-' . $page['id'] ); ?>"
                class="sc-homepage-spotlight__page sc-homepage-spotlight__page--<?php echo esc_attr( $card_count ); ?>"
                data-sc-spotlight-page
                data-page-index="<?php echo esc_attr( $page_index ); ?>"
                <?php if ( $tabs ) : ?>role="tabpanel" aria-labelledby="<?php echo esc_attr( $instance_id . '-tab-' . $page['id'] ); ?>"<?php else : ?>role="group" aria-label="<?php echo esc_attr( $page['title'] ); ?>"<?php endif; ?>
                <?php echo 0 === $page_index ? '' : 'hidden'; ?>
            >
                <header class="sc-homepage-spotlight__page-header">
                    <div>
                        <p class="sc-homepage-spotlight__eyebrow"><?php esc_html_e( 'Current subject', 'sustainable-catalyst-library' ); ?></p>
                        <h3 tabindex="-1" data-sc-spotlight-page-heading><?php echo esc_html( $page['title'] ); ?></h3>
                    </div>
                    <?php if ( $page['description'] ) : ?><p><?php echo esc_html( $page['description'] ); ?></p><?php endif; ?>
                </header>

                <div class="sc-homepage-spotlight__board" data-sc-spotlight-cards>
                    <?php foreach ( $page['cards'] as $card_index => $card ) :
                        $show_thumbnail = null === $show_thumbnail_override ? $card['show_thumbnail'] : $show_thumbnail_override;
                        $show_metadata = null === $show_metadata_override ? $card['show_metadata'] : $show_metadata_override;
                        $is_lead = $five_card_page && 0 === $card_index;
                        ?>
                        <article
                            class="sc-homepage-spotlight__card<?php echo $is_lead ? ' sc-homepage-spotlight__card--lead' : ''; ?>"
                            data-sc-spotlight-card
                            <?php echo $card['dismissible'] ? 'data-dismissible="true" data-dismiss-key="' . esc_attr( $card['dismiss_key'] ) . '"' : ''; ?>
                        >
                            <span class="sc-homepage-spotlight__row-number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $card_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                            <?php if ( $show_thumbnail && $card['thumbnail'] ) : ?>
                                <div class="sc-homepage-spotlight__thumbnail"><?php echo wp_kses_post( $card['thumbnail'] ); ?></div>
                            <?php endif; ?>
                            <div class="sc-homepage-spotlight__card-copy">
                                <div class="sc-homepage-spotlight__record-line">
                                    <?php if ( $card['label'] ) : ?><span class="sc-homepage-spotlight__label"><?php echo esc_html( $card['label'] ); ?></span><?php endif; ?>
                                    <?php if ( $show_metadata && $card['metadata'] ) : ?><span class="sc-homepage-spotlight__metadata"><?php echo esc_html( $card['metadata'] ); ?></span><?php endif; ?>
                                </div>
                                <h4 class="sc-homepage-spotlight__headline">
                                    <?php if ( $card['url'] ) : ?><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['headline'] ); ?></a><?php else : ?><?php echo esc_html( $card['headline'] ); ?><?php endif; ?>
                                </h4>
                                <?php if ( $card['summary'] ) : ?><p class="sc-homepage-spotlight__summary"><?php echo esc_html( $card['summary'] ); ?></p><?php endif; ?>
                            </div>
                            <?php if ( $card['url'] && $card['action_label'] ) : ?><a class="sc-homepage-spotlight__action" href="<?php echo esc_url( $card['url'] ); ?>"><span><?php echo esc_html( $card['action_label'] ); ?></span><span aria-hidden="true">↗</span></a><?php endif; ?>
                            <?php if ( $card['dismissible'] ) : ?>
                                <button type="button" class="sc-homepage-spotlight__dismiss" data-sc-spotlight-dismiss aria-label="<?php esc_attr_e( 'Dismiss this announcement', 'sustainable-catalyst-library' ); ?>">×</button>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if ( $controls ) : ?>
        <div class="sc-homepage-spotlight__controls" aria-label="<?php esc_attr_e( 'Knowledge Library console navigation', 'sustainable-catalyst-library' ); ?>">
            <button type="button" data-sc-spotlight-prev aria-label="<?php esc_attr_e( 'Show previous category', 'sustainable-catalyst-library' ); ?>"><span aria-hidden="true">←</span><span><?php esc_html_e( 'Previous', 'sustainable-catalyst-library' ); ?></span></button>
            <button type="button" data-sc-spotlight-toggle aria-pressed="<?php echo $autoplay ? 'false' : 'true'; ?>" aria-label="<?php echo esc_attr( $autoplay ? __( 'Pause automatic rotation', 'sustainable-catalyst-library' ) : __( 'Play automatic rotation', 'sustainable-catalyst-library' ) ); ?>"><span data-sc-spotlight-toggle-icon aria-hidden="true"><?php echo $autoplay ? 'Ⅱ' : '▶'; ?></span><span data-sc-spotlight-toggle-text><?php echo esc_html( $autoplay ? __( 'Pause', 'sustainable-catalyst-library' ) : __( 'Play', 'sustainable-catalyst-library' ) ); ?></span></button>
            <span class="sc-homepage-spotlight__position" data-sc-spotlight-position>01 / <?php echo esc_html( str_pad( (string) count( $pages ), 2, '0', STR_PAD_LEFT ) ); ?></span>
            <button type="button" data-sc-spotlight-next aria-label="<?php esc_attr_e( 'Show next category', 'sustainable-catalyst-library' ); ?>"><span><?php esc_html_e( 'Next', 'sustainable-catalyst-library' ); ?></span><span aria-hidden="true">→</span></button>
        </div>
    <?php endif; ?>
</section>
