<?php
if (!defined('ABSPATH')) { exit; }
/** v5.54.0 Translation & Transliteration Alignment Matrix readiness surface. */
final class SC_Library_Translation_Alignment {
    public const VERSION = '5.54.0';
    public const SCHEMA = 'sc-library-translation-alignment-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_translation_alignment_status', [$this,'shortcode']); }
    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/translation-alignment/readiness',['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r) || 200 !== (int)wp_remote_retrieve_response_code($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $body=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($body)?$body:['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Translation & Transliteration Alignment'],$atts,'sc_translation_alignment_status');
        $p=self::readiness(); $c=(array)($p['counts']??[]); $state=sanitize_text_field((string)($p['state']??'unavailable'));
        ob_start(); ?>
        <section class="sc-translation-alignment" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-translation-alignment__kicker"><?php esc_html_e('Knowledge Library v5.54.0','sustainable-catalyst-library'); ?></p>
          <h2><?php echo esc_html((string)$atts['title']); ?></h2>
          <p><?php esc_html_e('Align preserved original-language text with derived translations or transliterations while retaining exact span identity, transformation provenance, ambiguity, and review state. Alignment confidence is descriptive and does not establish semantic equivalence, evidence, or truth.','sustainable-catalyst-library'); ?></p>
          <dl><dt><?php esc_html_e('State','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($state); ?></dd>
          <dt><?php esc_html_e('Matrices','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string)((int)($c['matrices']??0))); ?></dd>
          <dt><?php esc_html_e('Alignment links','sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string)((int)($c['links']??0))); ?></dd></dl>
        </section><?php return (string)ob_get_clean();
    }
}
