<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$directory_title = $instance_id . '-directory-title';
?>
<nav class="sc-field-stack__index sc-field-stack__directory" aria-labelledby="<?php echo esc_attr( $directory_title ); ?>" data-sc-publications-directory>
    <h3 id="<?php echo esc_attr( $directory_title ); ?>"><?php esc_html_e( 'Article Map directory', 'sustainable-catalyst-library' ); ?></h3>
    <?php foreach ( $publications_directory as $directory_field ) : ?>
        <section>
            <h4><?php echo esc_html( $directory_field['title'] ); ?></h4>
            <ul>
                <?php foreach ( $directory_field['maps'] as $directory_map ) : ?>
                    <li>
                        <a data-sc-directory-map href="<?php echo esc_url( $directory_map['url'] ); ?>"><?php echo esc_html( $directory_map['title'] ); ?></a>
                        <?php if ( $directory_map['articles'] ) : ?>
                            <ul aria-label="<?php echo esc_attr( sprintf( __( 'Publications in %s', 'sustainable-catalyst-library' ), $directory_map['title'] ) ); ?>">
                                <?php foreach ( $directory_map['articles'] as $directory_article ) : ?>
                                    <li><a data-sc-directory-article href="<?php echo esc_url( $directory_article['url'] ); ?>"><?php echo esc_html( $directory_article['title'] ); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</nav>
