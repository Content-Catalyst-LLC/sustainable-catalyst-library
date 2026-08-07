<?php
/** Publications v4.3.2 public template. Variables: $topics, $heading, $intro, $instance_id. */
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
        <span class="sc-publications__status"><?php echo esc_html( sprintf( __( '%1$d fields · %2$d article maps', 'sustainable-catalyst-library' ), count( $fields ), count( $topics ) ) ); ?></span>
    </header>

    <nav class="sc-publications__navigator" aria-label="<?php esc_attr_e( 'Publication fields', 'sustainable-catalyst-library' ); ?>">
        <?php $field_number = 0; foreach ( $fields as $field => $field_topics ) : $field_number++; $field_id = $instance_id . '-field-' . sanitize_title( $field ); ?>
            <a href="#<?php echo esc_attr( $field_id ); ?>">
                <span><?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></span>
                <?php echo esc_html( $field ); ?>
                <small><?php echo esc_html( count( $field_topics ) ); ?></small>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sc-publications__body">
        <?php $field_number = 0; $topic_number = 0; foreach ( $fields as $field => $field_topics ) : $field_number++; $field_id = $instance_id . '-field-' . sanitize_title( $field ); ?>
            <section id="<?php echo esc_attr( $field_id ); ?>" class="sc-publications__field">
                <header class="sc-publications__field-header">
                    <p><?php echo esc_html( str_pad( (string) $field_number, 2, '0', STR_PAD_LEFT ) ); ?></p>
                    <div>
                        <h3><?php echo esc_html( $field ); ?></h3>
                        <span><?php echo esc_html( sprintf( _n( '%d article map', '%d article maps', count( $field_topics ), 'sustainable-catalyst-library' ), count( $field_topics ) ) ); ?></span>
                    </div>
                </header>

                <?php foreach ( $field_topics as $topic ) : $topic_number++; $article_count = count( $topic['articles'] ); ?>
                    <article class="sc-publications__topic <?php echo 4 === $article_count ? 'is-complete' : 'is-incomplete'; ?>" id="<?php echo esc_attr( $instance_id . '-topic-' . $topic['key'] ); ?>" data-article-source="<?php echo esc_attr( $topic['article_source'] ); ?>">
                        <header class="sc-publications__topic-header">
                            <div>
                                <p class="sc-publications__eyebrow">
                                    <?php if ( $topic['group'] ) : ?>
                                        <?php echo esc_html( $field . ' / ' . $topic['group'] ); ?>
                                    <?php else : ?>
                                        <?php echo esc_html( $field ); ?>
                                    <?php endif; ?>
                                </p>
                                <h4><?php echo esc_html( $topic['title'] ); ?></h4>
                            </div>
                            <?php if ( $topic['description'] ) : ?><p><?php echo esc_html( $topic['description'] ); ?></p><?php endif; ?>
                        </header>

                        <div class="sc-publications__board">
                            <a class="sc-publications__map-hero" href="<?php echo esc_url( home_url( $topic['map_url'] ) ); ?>">
                                <span class="sc-publications__row-number sc-publications__row-number--map" aria-hidden="true">MAP</span>
                                <span class="sc-publications__map-copy">
                                    <span class="sc-publications__map-label"><?php esc_html_e( 'Article Map', 'sustainable-catalyst-library' ); ?></span>
                                    <strong><?php echo esc_html( $topic['map_title'] ); ?></strong>
                                    <span><?php esc_html_e( 'Explore the complete structured pathway for this subject.', 'sustainable-catalyst-library' ); ?></span>
                                </span>
                                <span class="sc-publications__row-action"><?php esc_html_e( 'Explore map', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">↗</span></span>
                            </a>

                            <?php if ( $topic['articles'] ) : ?>
                                <ol class="sc-publications__articles" aria-label="<?php echo esc_attr( sprintf( __( 'Selected publications for %s', 'sustainable-catalyst-library' ), $topic['title'] ) ); ?>">
                                    <?php foreach ( $topic['articles'] as $index => $article ) : ?>
                                        <li>
                                            <span class="sc-publications__row-number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                                            <h5><a href="<?php echo esc_url( $article['url'] ); ?>"><?php echo esc_html( $article['title'] ); ?></a></h5>
                                            <a class="sc-publications__row-action" href="<?php echo esc_url( $article['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Read %s', 'sustainable-catalyst-library' ), $article['title'] ) ); ?>"><?php esc_html_e( 'Read', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">↗</span></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <a class="sc-publications__back" href="#<?php echo esc_attr( $instance_id ); ?>"><?php esc_html_e( 'Back to fields', 'sustainable-catalyst-library' ); ?> ↑</a>
            </section>
        <?php endforeach; ?>
    </div>
</section>
