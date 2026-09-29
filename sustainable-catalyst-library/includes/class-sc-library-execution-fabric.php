<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Execution_Fabric {
    public const SCHEMA='sc-library-execution-fabric-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_library_execution_fabric_status',[$this,'render']); }
    private function status(): array {
        if (!class_exists('SC_Library_Python_Backend') || !SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable'];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/execution-fabric/readiness',['timeout'=>10,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','error'=>$r->get_error_message()];
        $b=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable'];
    }
    public function render(): string {
        $x=$this->status(); $state=(string)($x['state']??'unavailable'); $redis=(string)($x['redis']['state']??'unknown'); $counts=is_array($x['counts']??null)?$x['counts']:[];
        ob_start(); ?>
        <section class="sc-execution-fabric" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-execution-fabric__kicker"><?php esc_html_e('Knowledge Library v5.50.0','sustainable-catalyst-library'); ?></p>
          <h3><?php esc_html_e('Durable Research Execution Fabric','sustainable-catalyst-library'); ?></h3>
          <p><strong><?php esc_html_e('State:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($state); ?> · <strong><?php esc_html_e('Redis dispatch:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($redis); ?></p>
          <p><?php echo esc_html(sprintf(__('Queued %1$d · Running %2$d · Retry %3$d · Failed %4$d','sustainable-catalyst-library'),(int)($counts['queued']??0),(int)($counts['running']??0),(int)($counts['retry']??0),(int)($counts['failed']??0))); ?></p>
          <p><?php esc_html_e('PostgreSQL is authoritative for job state. Redis is dispatch coordination only. Job completion does not establish evidence truth or source validity. Specialized Python, Go, and Rust worker pools are active; provider-dependent OCR/HTR/speech, neural, and Workspace profiles remain explicit standby profiles.','sustainable-catalyst-library'); ?></p>
        </section><?php return (string)ob_get_clean();
    }
}
