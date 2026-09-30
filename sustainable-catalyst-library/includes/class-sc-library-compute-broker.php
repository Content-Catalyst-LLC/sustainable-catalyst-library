<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Compute_Broker {
    public const SCHEMA='sc-library-distributed-compute-broker-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_library_compute_broker_status',[$this,'render']); }
    private function status(): array {
        if (!class_exists('SC_Library_Python_Backend') || !SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable'];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/compute-broker/readiness',['timeout'=>10,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','error'=>$r->get_error_message()];
        $b=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable'];
    }
    public function render(): string {
        $x=$this->status(); $state=(string)($x['state']??'unavailable'); $profiles=is_array($x['profiles']??null)?$x['profiles']:[]; $queues=is_array($x['queues']??null)?$x['queues']:[];
        ob_start(); ?>
        <section class="sc-compute-broker" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-compute-broker__kicker"><?php esc_html_e('Knowledge Library v5.53.0','sustainable-catalyst-library'); ?></p>
          <h3><?php esc_html_e('Distributed Research Compute Broker & Runtime Observability','sustainable-catalyst-library'); ?></h3>
          <p><strong><?php esc_html_e('State:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($state); ?> · <?php echo esc_html(sprintf(__('Active profiles %1$d · Standby %2$d · Queued/retry %3$d','sustainable-catalyst-library'),(int)($profiles['active']??0),(int)($profiles['standby']??0),(int)($queues['queued_retry']??0))); ?></p>
          <p><?php esc_html_e('Placement uses operational runtime availability, capacity, queue pressure, and configured limits only. Runtime health or lower latency never implies stronger evidence, better research, or result truth.','sustainable-catalyst-library'); ?></p>
        </section><?php return (string)ob_get_clean();
    }
}
