<?php
if (!defined('ABSPATH')) { exit; }
/** v5.48.0 Cross-Language Entity, Name & Historical Toponym Resolution readiness surface. */
final class SC_Library_Cross_Language_Resolution {
    public const VERSION = '5.48.0';
    public const SCHEMA = 'sc-library-cross-language-resolution-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_cross_language_resolution_status', [$this,'shortcode']); }
    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/cross-language-resolution/readiness',['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r) || 200 !== (int) wp_remote_retrieve_response_code($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $body=json_decode((string) wp_remote_retrieve_body($r),true); return is_array($body)?$body:['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Cross-Language Entity & Toponym Resolution'],$atts,'sc_cross_language_resolution_status');
        $p=self::readiness(); $c=(array)($p['counts']??[]); $state=sanitize_text_field((string)($p['state']??'unavailable'));
        ob_start(); ?>
        <section class="sc-cross-language-resolution" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-cross-language-resolution__kicker"><?php esc_html_e('Knowledge Library v5.48.0','sustainable-catalyst-library'); ?></p>
          <h2><?php echo esc_html((string)$atts['title']); ?></h2>
          <p><?php esc_html_e('Multilingual names, declared transliterations, historical toponyms, and validity windows support candidate resolution while preserving ambiguity. Candidate rank is not identity, evidence, or truth; persistent resolution requires explicit adjudication.','sustainable-catalyst-library'); ?></p>
          <dl><dt><?php esc_html_e('State','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($state); ?></dd>
          <dt><?php esc_html_e('Entities','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string)((int)($c['entities']??0))); ?></dd>
          <dt><?php esc_html_e('Name forms','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string)((int)($c['name_forms']??0))); ?></dd>
          <dt><?php esc_html_e('Resolution cases','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string)((int)($c['resolution_cases']??0))); ?></dd></dl>
        </section><?php return (string)ob_get_clean();
    }
}
