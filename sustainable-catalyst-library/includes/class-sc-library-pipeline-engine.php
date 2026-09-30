<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Pipeline_Engine {
    public const SCHEMA='sc-library-pipeline-engine-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_library_pipeline_engine_status',[$this,'render']); }
    private function status(): array {
        if (!class_exists('SC_Library_Python_Backend') || !SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable'];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/pipeline-engine/readiness',['timeout'=>10,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','error'=>$r->get_error_message()];
        $b=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable'];
    }
    public function render(): string {
        $x=$this->status(); $state=(string)($x['state']??'unavailable'); $counts=is_array($x['counts']??null)?$x['counts']:[];
        ob_start(); ?>
        <section class="sc-pipeline-engine" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-pipeline-engine__kicker"><?php esc_html_e('Knowledge Library v5.52.0','sustainable-catalyst-library'); ?></p>
          <h3><?php esc_html_e('Checkpointed Ingestion & Research Pipeline Engine','sustainable-catalyst-library'); ?></h3>
          <p><strong><?php esc_html_e('State:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($state); ?> · <?php echo esc_html(sprintf(__('Pipelines %1$d · Runs %2$d · Stage runs %3$d','sustainable-catalyst-library'),(int)($counts['pipelines']??0),(int)($counts['runs']??0),(int)($counts['stage_runs']??0))); ?></p>
          <p><?php esc_html_e('Pipeline stages compile into the durable research-job fabric. Completed checkpoints are reused on resume; pipeline completion does not establish source validity, evidence truth, or automatic Platform Core promotion.','sustainable-catalyst-library'); ?></p>
        </section><?php return (string)ob_get_clean();
    }
}
