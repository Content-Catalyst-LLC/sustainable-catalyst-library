<?php
/**
 * SC-PUB-RELEASE-02 · Compact, progressively disclosed Publications directory.
 *
 * One native HTML <details> reveals the existing verified WordPress links.
 * No JavaScript, new indexing, editorial mutation, or new request handling.
 * Styles are intentionally co-located for the isolated one-file canary.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$directory_title = $instance_id . '-directory-title';
$directory_field_count = count( $publications_directory );
$directory_map_count = 0;
$directory_article_count = 0;
foreach ( $publications_directory as $directory_field ) {
    $directory_map_count += count( $directory_field['maps'] ?? array() );
    foreach ( $directory_field['maps'] ?? array() as $directory_map ) {
        $directory_article_count += count( $directory_map['articles'] ?? array() );
    }
}
?>
<style id="sc-pub-directory-v2-styles">
/* Isolated to the Publications directory; other field stages are untouched. */
.sc-pubdir2{margin:clamp(1.1rem,2.5vw,1.9rem) 0;box-sizing:border-box;font:inherit;color:#171717}
.sc-pubdir2 *, .sc-pubdir2 *::before,.sc-pubdir2 *::after{box-sizing:border-box}
.sc-pubdir2__shell{border:1px solid #dedad2;border-top:3px solid #171717;background:#fff}
.sc-pubdir2__summary{list-style:none;display:flex;align-items:center;gap:clamp(.8rem,2.6vw,2rem);padding:clamp(1rem,2.8vw,1.6rem);cursor:pointer;min-height:100px}
.sc-pubdir2__summary::-webkit-details-marker{display:none}
.sc-pubdir2__intro{display:flex;flex:1;min-width:0;flex-direction:column;gap:.28rem}
.sc-pubdir2__eyebrow{display:block;font-size:.65rem;font-weight:800;letter-spacing:.125em;line-height:1.4;text-transform:uppercase;color:#b00020}
.sc-pubdir2__heading{display:block;font-size:clamp(1.13rem,2vw,1.55rem);font-weight:780;line-height:1.2;letter-spacing:-.025em;color:#141414}
.sc-pubdir2__subline{display:block;font-size:.78rem;line-height:1.55;color:#5f5b56}
.sc-pubdir2__action{display:inline-flex;align-items:center;justify-content:center;gap:.6rem;flex-shrink:0;font-size:.72rem;font-weight:800;letter-spacing:.01em;color:#171717;border:1px solid #171717;padding:.68rem .9rem;min-height:42px;white-space:nowrap}
.sc-pubdir2__chevron{display:inline-block;font-size:1.2em;color:#b00020;transition:transform .18s ease}
.sc-pubdir2__shell[open] .sc-pubdir2__chevron{transform:rotate(45deg)}
.sc-pubdir2__summary:hover{background:#faf8f5}
.sc-pubdir2__summary:focus-visible,.sc-pubdir2 a:focus-visible{outline:3px solid #b00020;outline-offset:3px}
.sc-pubdir2__body{border-top:1px solid #e1dcd4;padding:clamp(.9rem,2vw,1.3rem);background:#fbfaf7}
.sc-pubdir2__hint{margin:0 0 1rem;font-size:.78rem;line-height:1.55;color:#5e5b55}
.sc-pubdir2__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;gap:.9rem}
.sc-pubdir2__field{min-width:0;margin:0;background:#fff;border:1px solid #e6e1d9;border-top:2px solid #241f1c}
.sc-pubdir2__field-head{display:flex;align-items:baseline;justify-content:space-between;gap:.7rem;border-bottom:1px solid #e8e3dc;padding:.85rem .9rem .7rem}
.sc-pubdir2__field-head h4{margin:0;font-size:.88rem;font-weight:780;line-height:1.35;color:#1d1d1d}
.sc-pubdir2__field-head small{font-size:.65rem;line-height:1.5;color:#82796e;white-space:nowrap}
.sc-pubdir2__maps,.sc-pubdir2__articles{list-style:none;margin:0;padding:0}
.sc-pubdir2__maps{padding:.3rem .85rem .75rem}
.sc-pubdir2__map{padding:.7rem .15rem}
.sc-pubdir2__map+.sc-pubdir2__map{border-top:1px solid #eeeae4}
.sc-pubdir2 a{text-decoration:none;color:inherit;text-underline-offset:3px;overflow-wrap:anywhere}
.sc-pubdir2 a:hover{text-decoration:underline;color:#b00020}
.sc-pubdir2__map-link{display:block;font-weight:700;font-size:.79rem;line-height:1.45}
.sc-pubdir2__map-link::before{content:"";display:inline-block;width:.32rem;height:.32rem;margin-right:.5rem;background:#b00020;vertical-align:middle}
.sc-pubdir2__articles{margin:.55rem 0 .1rem .15rem;padding-left:.82rem;border-left:1px solid #ddd3c6}
.sc-pubdir2__articles li+li{margin-top:.42rem}
.sc-pubdir2__articles a{display:block;font-size:.73rem;line-height:1.5;color:#514c47}
.sc-pubdir2__foot{border-top:1px solid #e7e1d9;padding:.72rem 1rem;background:#f7f3ed;font-size:.69rem;line-height:1.55;color:#686159}
@media(max-width:700px){.sc-pubdir2__grid{grid-template-columns:1fr}.sc-pubdir2__summary{align-items:flex-start}.sc-pubdir2__action{margin-top:.2rem}}
@media(max-width:470px){.sc-pubdir2__summary{flex-direction:column;align-items:stretch;gap:.9rem}.sc-pubdir2__action{align-self:flex-start}.sc-pubdir2__field-head{flex-wrap:wrap}}
@media(prefers-reduced-motion:reduce){.sc-pubdir2__chevron{transition:none}}
</style>
<nav class="sc-pubdir2" aria-label="<?php esc_attr_e( 'Verified Article Map directory', 'sustainable-catalyst-library' ); ?>" data-sc-publications-directory data-sc-publications-directory-design="2">
    <details class="sc-pubdir2__shell">
        <summary class="sc-pubdir2__summary" aria-labelledby="<?php echo esc_attr( $directory_title ); ?>">
            <span class="sc-pubdir2__intro">
                <span class="sc-pubdir2__eyebrow"><?php esc_html_e( 'DIRECT PUBLICATION LINKS', 'sustainable-catalyst-library' ); ?></span>
                <span id="<?php echo esc_attr( $directory_title ); ?>" class="sc-pubdir2__heading"><?php esc_html_e( 'Browse the Article Map directory', 'sustainable-catalyst-library' ); ?></span>
                <span class="sc-pubdir2__subline"><?php echo esc_html( sprintf( __( '%d available maps across %d fields · %d curated article links', 'sustainable-catalyst-library' ), $directory_map_count, $directory_field_count, $directory_article_count ) ); ?></span>
            </span>
            <span class="sc-pubdir2__action" aria-hidden="true">
                <?php esc_html_e( 'Explore index', 'sustainable-catalyst-library' ); ?>
                <span class="sc-pubdir2__chevron">+</span>
            </span>
        </summary>
        <div class="sc-pubdir2__body">
            <p class="sc-pubdir2__hint"><?php esc_html_e( 'Browse published series and their selected articles. The field spotlights below offer a guided view.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-pubdir2__grid">
                <?php foreach ( $publications_directory as $directory_field ) : ?>
                    <?php if ( empty( $directory_field['maps'] ) ) { continue; } ?>
                    <section class="sc-pubdir2__field">
                        <header class="sc-pubdir2__field-head">
                            <h4><?php echo esc_html( $directory_field['title'] ); ?></h4>
                            <small><?php echo esc_html( sprintf( _n( '%d map', '%d maps', count( $directory_field['maps'] ), 'sustainable-catalyst-library' ), count( $directory_field['maps'] ) ) ); ?></small>
                        </header>
                        <ul class="sc-pubdir2__maps">
                            <?php foreach ( $directory_field['maps'] as $directory_map ) : ?>
                                <li class="sc-pubdir2__map">
                                    <a class="sc-pubdir2__map-link" data-sc-directory-map href="<?php echo esc_url( $directory_map['url'] ); ?>"><?php echo esc_html( $directory_map['title'] ); ?></a>
                                    <?php if ( ! empty( $directory_map['articles'] ) ) : ?>
                                        <ul class="sc-pubdir2__articles" aria-label="<?php echo esc_attr( sprintf( __( 'Selected publications in %s', 'sustainable-catalyst-library' ), $directory_map['title'] ) ); ?>">
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
            </div>
            <p class="sc-pubdir2__foot"><?php esc_html_e( 'Only publicly eligible WordPress destinations are included. Full field navigation remains available below.', 'sustainable-catalyst-library' ); ?></p>
        </div>
    </details>
</nav>
