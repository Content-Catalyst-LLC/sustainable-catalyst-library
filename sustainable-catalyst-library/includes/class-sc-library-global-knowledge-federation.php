<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Global_Knowledge_Federation {
    public const VERSION='5.57.0';
    public const SCHEMA='sc-library-global-knowledge-federation-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_global_knowledge_federation_status',[$this,'shortcode']); }
    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable','component_count'=>0,'ready_component_count'=>0];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/global-knowledge-federation/readiness',['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)||200!==(int)wp_remote_retrieve_response_code($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','component_count'=>0,'ready_component_count'=>0];
        $b=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable'];
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Global Knowledge Federation'],$atts,'sc_global_knowledge_federation_status'); $p=self::readiness();
        ob_start(); ?><section class="sc-global-knowledge-federation" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
        <p class="sc-kicker">Knowledge Library v5.57.0</p><h2><?php echo esc_html((string)$atts['title']); ?></h2>
        <p>Federates source, language, preservation, corpus, identity, alignment, cross-civilizational, transparency, and distributed-compute layers while preserving their separate provenance and governance boundaries.</p>
        <dl><dt>State</dt><dd><?php echo esc_html((string)($p['state']??'unavailable')); ?></dd><dt>Certified components</dt><dd><?php echo esc_html((string)((int)($p['ready_component_count']??0))); ?> / <?php echo esc_html((string)((int)($p['component_count']??0))); ?></dd></dl></section><?php return (string)ob_get_clean();
    }
}
