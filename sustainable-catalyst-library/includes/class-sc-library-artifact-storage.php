<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Artifact_Storage {
    public const SCHEMA='sc-library-artifact-storage-readiness/1.0';
    public function register_hooks(): void { add_shortcode('sc_library_artifact_storage_status',[$this,'render']); }
    private function status(): array {
        if (!class_exists('SC_Library_Python_Backend') || !SC_Library_Python_Backend::configured()) return ['schema'=>self::SCHEMA,'state'=>'unavailable'];
        $r=wp_remote_get(SC_Library_Python_Backend::base_url().'/v1/artifact-storage/readiness',['timeout'=>10,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['schema'=>self::SCHEMA,'state'=>'unavailable','error'=>$r->get_error_message()];
        $b=json_decode((string)wp_remote_retrieve_body($r),true); return is_array($b)?$b:['schema'=>self::SCHEMA,'state'=>'unavailable'];
    }
    public function render(): string {
        $x=$this->status(); $state=(string)($x['state']??'unavailable'); $storage=is_array($x['storage']??null)?$x['storage']:[]; $backend=(string)($storage['backend']??'unknown'); $counts=is_array($x['counts']??null)?$x['counts']:[];
        ob_start(); ?>
        <section class="sc-artifact-storage" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
          <p class="sc-artifact-storage__kicker"><?php esc_html_e('Knowledge Library v5.51.0','sustainable-catalyst-library'); ?></p>
          <h3><?php esc_html_e('Research Artifact & Object Storage Fabric','sustainable-catalyst-library'); ?></h3>
          <p><strong><?php esc_html_e('State:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($state); ?> · <strong><?php esc_html_e('Storage backend:','sustainable-catalyst-library'); ?></strong> <?php echo esc_html($backend); ?></p>
          <p><?php echo esc_html(sprintf(__('Active %1$d · Retained %2$d · Quarantined %3$d · Tombstoned %4$d','sustainable-catalyst-library'),(int)($counts['active']??0),(int)($counts['retained']??0),(int)($counts['quarantined']??0),(int)($counts['tombstoned']??0))); ?></p>
          <p><?php esc_html_e('Artifact bytes are immutable and content-addressed by SHA-256. PostgreSQL remains authoritative for identity, provenance, derivation, and lifecycle. Artifact presence or successful integrity verification does not establish evidence truth or source validity.','sustainable-catalyst-library'); ?></p>
        </section><?php return (string)ob_get_clean();
    }
}
