<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Web_Application {
    public const VERSION = '1.2.0';
    public function register_hooks(): void { add_shortcode('sc_library_web_app_launch', [$this, 'shortcode']); }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['url'=>'https://library.sustainablecatalyst.com','label'=>'Open Knowledge Library'],$atts,'sc_library_web_app_launch');
        $url=esc_url((string)$atts['url']); $label=esc_html((string)$atts['label']);
        return '<div class="sc-library-web-launch" data-web-version="1.2.0"><p><a href="'.$url.'">'.$label.'</a></p><small>The Library web application runs independently of WordPress.</small></div>';
    }
}
