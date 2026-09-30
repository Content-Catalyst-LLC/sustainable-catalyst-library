<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Source_Transparency {
    public const VERSION = '5.56.0';
    public const SCHEMA = 'sc-library-source-transparency-readiness/1.0';
    public function register_hooks(): void {
        add_shortcode('sc_source_transparency_status', [$this, 'shortcode']);
    }
    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/source-transparency/readiness',['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r) || 200 !== (int)wp_remote_retrieve_response_code($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
        $b=json_decode((string)wp_remote_retrieve_body($r),true);
        return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable','counts'=>[]];
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Source Transparency, Quality Signals & User Trust Policies'],$atts,'sc_source_transparency_status');
        $p=self::readiness(); $c=(array)($p['counts']??[]);
        ob_start(); ?>
        <section class="sc-source-transparency" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-kicker">Knowledge Library v5.56.0</p>
            <h2><?php echo esc_html((string)$atts['title']); ?></h2>
            <p>Source-quality signals describe observable provenance and transparency characteristics. User trust policies are separate preference rules and do not establish truth, credibility, endorsement, or evidence weight.</p>
            <dl>
                <dt>State</dt><dd><?php echo esc_html((string)($p['state']??'unavailable')); ?></dd>
                <dt>Signals</dt><dd><?php echo esc_html((string)((int)($c['signals']??0))); ?></dd>
                <dt>Profiles</dt><dd><?php echo esc_html((string)((int)($c['profiles']??0))); ?></dd>
                <dt>Trust policies</dt><dd><?php echo esc_html((string)((int)($c['policies']??0))); ?></dd>
            </dl>
        </section>
        <?php return (string)ob_get_clean();
    }
}
