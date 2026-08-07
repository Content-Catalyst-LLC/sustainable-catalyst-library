<?php
/** Publications v4.3.0 public template. Variables: $topics, $heading, $intro, $instance_id. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$fields = array();
foreach ( $topics as $topic ) {
    $fields[ $topic['field'] ][] = $topic;
}
?>
<section id="<?php echo esc_attr( $instance_id ); ?>" class="sc-publications" aria-label="<?php esc_attr_e( 'Sustainable Catalyst Publications', 'sustainable-catalyst-library' ); ?>">
    <header class="sc-publications__masthead">
        <div class="sc-publications__identity">
            <p class="sc-publications__system-id"><span aria-hidden="true">KL</span> <?php esc_html_e( 'Knowledge Library', 'sustainable-catalyst-library' ); ?></p>
            <h2><?php echo esc_html( $heading ); ?></h2>
        </div>
        <?php if ( $intro ) : ?><p class="sc-publications__intro"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
        <span class="sc-publications__status"><?php esc_html_e( 'Editorial Index', 'sustainable-catalyst-library' ); ?></span>
    </header>

    <nav class="sc-publications__navigator" aria-label="<?php esc_attr_e( 'Publication fields', 'sustainable-catalyst-library' ); ?>">
        <?php $field_number = 0; foreach ( $fields as $field => $field_topics ) : $field_number++; $field_id = $instance_id . '-field-' . sanitize_title( $field ); ?>
            <a href="#<?php echo esc_attr( $field_id ); ?>"><span><?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $field ); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="sc-publications__body">
        <?php $field_number = 0; $topic_number = 0; foreach ( $fields as $field => $field_topics ) : $field_number++; $field_id = $instance_id . '-field-' . sanitize_title( $field ); ?>
            <section id="<?php echo esc_attr( $field_id ); ?>" class="sc-publications__field">
                <header class="sc-publications__field-header">
                    <p><?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></p>
                    <h3><?php echo esc_html( $field ); ?></h3>
                </header>

                <?php foreach ( $field_topics as $topic ) : $topic_number++; ?>
                    <article class="sc-publications__topic" id="<?php echo esc_attr( $instance_id . '-topic-' . $topic['key'] ); ?>">
                        <header class="sc-publications__topic-header">
                            <div>
                                <p class="sc-publications__eyebrow"><?php esc_html_e( 'Publication field', 'sustainable-catalyst-library' ); ?></p>
                                <h4><?php echo esc_html( $topic['title'] ); ?></h4>
                            </div>
                            <?php if ( $topic['description'] ) : ?><p><?php echo esc_html( $topic['description'] ); ?></p><?php endif; ?>
                        </header>

                        <a class="sc-publications__map-hero" href="<?php echo esc_url( home_url( $topic['map_url'] ) ); ?>">
                            <span class="sc-publications__map-index"><?php echo esc_html( str_pad( (string) $topic_number, 2, '0', STR_PAD_LEFT ) ); ?></span>
                            <span class="sc-publications__map-copy">
                                <span class="sc-publications__map-label"><?php esc_html_e( 'Article Map', 'sustainable-catalyst-library' ); ?></span>
                                <strong><?php echo esc_html( $topic['map_title'] ); ?></strong>
                                <span><?php esc_html_e( 'Explore the complete structured pathway for this subject.', 'sustainable-catalyst-library' ); ?></span>
                            </span>
                            <span class="sc-publications__map-action"><?php esc_html_e( 'Explore map', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">→</span></span>
                        </a>

                        <ol class="sc-publications__articles">
                            <?php foreach ( $topic['articles'] as $index => $article ) : ?>
                                <li>
                                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                                    <a href="<?php echo esc_url( $article['url'] ); ?>"><?php echo esc_html( $article['title'] ); ?></a>
                                    <span class="sc-publications__article-arrow" aria-hidden="true">↗</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </article>
                <?php endforeach; ?>
                <a class="sc-publications__back" href="#<?php echo esc_attr( $instance_id ); ?>"><?php esc_html_e( 'Back to fields', 'sustainable-catalyst-library' ); ?> ↑</a>
            </section>
        <?php endforeach; ?>
    </div>
</section>
