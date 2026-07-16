<?php
/** Sustainable Catalyst Foundations v2.1.0 First Edition importer. */
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Foundations_First_Edition_V210 {
    private static ?self $instance = null;
    private const RELEASE = '2.1.0';
    private const IMPORT_REL = 'assets/foundations/v2.1.0/import/foundations-first-edition.json';
    public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct() {}
    public function register_hooks(): void {
        add_action('admin_menu', [$this,'admin_menu'], 40);
        add_action('admin_post_sc_foundations_v210_import', [$this,'handle_import']);
        if (defined('WP_CLI') && WP_CLI) { WP_CLI::add_command('sc foundations-first-edition', [$this,'cli_import']); }
    }
    public function admin_menu(): void {
        add_submenu_page('edit.php?post_type=sc_foundation_doc','Institutional Foundations First Edition','First Edition Import','manage_options','sc-foundations-first-edition',[$this,'render_page']);
    }
    public function render_page(): void {
        if (!current_user_can('manage_options')) return;
        $result = get_transient('sc_foundations_v210_import_result_' . get_current_user_id());
        delete_transient('sc_foundations_v210_import_result_' . get_current_user_id());
        echo '<div class="wrap"><h1>Institutional Foundations First Edition</h1><p>Import all 13 complete Foundation Documents, metadata, relationships, and PDF snapshots. The safe default is WordPress draft status.</p>';
        if (is_array($result)) echo '<div class="notice notice-success"><p>'.esc_html(sprintf('Import completed: %d created, %d updated, %d PDFs attached, %d errors.', $result['created'],$result['updated'],$result['attachments'],count($result['errors']))).'</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('sc_foundations_v210_import');
        echo '<input type="hidden" name="action" value="sc_foundations_v210_import"><p><label><input type="checkbox" name="publish" value="1"> Publish records immediately with metadata status Under Review</label></p>';
        submit_button('Import First Edition'); echo '</form></div>';
    }
    public function handle_import(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permission.');
        check_admin_referer('sc_foundations_v210_import');
        $result = $this->import_all(!empty($_POST['publish']));
        set_transient('sc_foundations_v210_import_result_' . get_current_user_id(), $result, 120);
        wp_safe_redirect(admin_url('edit.php?post_type=sc_foundation_doc&page=sc-foundations-first-edition')); exit;
    }
    public function cli_import(array $args, array $assoc_args): void {
        $publish = !empty($assoc_args['publish']); $result = $this->import_all($publish);
        WP_CLI::success(sprintf('Created %d, updated %d, attached %d PDFs, errors %d.', $result['created'],$result['updated'],$result['attachments'],count($result['errors'])));
    }
    private function import_all(bool $publish): array {
        $result=['created'=>0,'updated'=>0,'attachments'=>0,'errors'=>[]];
        $path=trailingslashit(SC_LIBRARY_DIR).self::IMPORT_REL;
        if (!is_readable($path)) { $result['errors'][]='Import manifest missing.'; return $result; }
        $payload=json_decode((string)file_get_contents($path),true);
        if (!is_array($payload) || empty($payload['documents'])) { $result['errors'][]='Import manifest invalid.'; return $result; }
        $id_map=[];
        foreach ($payload['documents'] as $record) {
            $doc_id=sanitize_text_field((string)($record['document_id']??''));
            if ($doc_id==='') continue;
            $existing=$this->find_by_document_id($doc_id);
            $postarr=['post_type'=>'sc_foundation_doc','post_status'=>$publish?'publish':'draft','post_title'=>sanitize_text_field((string)$record['title']),'post_name'=>sanitize_title((string)$record['slug']),'post_excerpt'=>sanitize_text_field((string)$record['excerpt']),'post_content'=>wp_kses_post((string)$record['content_html']),'menu_order'=>absint($record['menu_order']??0)];
            if ($existing) $postarr['ID']=$existing;
            $post_id=wp_insert_post(wp_slash($postarr),true);
            if (is_wp_error($post_id)) { $result['errors'][]=$doc_id.': '.$post_id->get_error_message(); continue; }
            $existing ? $result['updated']++ : $result['created']++;
            $id_map[$doc_id]=(int)$post_id;
            $this->update_meta((int)$post_id,$record);
            if ($this->attach_pdf((int)$post_id,$record)) $result['attachments']++;
            $this->assign_foundations_collection((int)$post_id);
        }
        foreach ($payload['documents'] as $record) {
            $doc_id=(string)($record['document_id']??''); if (empty($id_map[$doc_id])) continue;
            $related=[]; foreach ((array)($record['related_documents']??[]) as $rid) if (!empty($id_map[$rid])) $related[]=$id_map[$rid];
            update_post_meta($id_map[$doc_id], '_sc_foundation_related_ids', implode(',',array_unique($related)));
        }
        update_option('sc_library_foundations_first_edition_version',self::RELEASE,false);
        return $result;
    }
    private function find_by_document_id(string $doc_id): int {
        $q=new WP_Query(['post_type'=>'sc_foundation_doc','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_sc_foundation_document_id','meta_value'=>$doc_id,'no_found_rows'=>true]);
        return !empty($q->posts)?(int)$q->posts[0]:0;
    }
    private function update_meta(int $post_id,array $r): void {
        $map=['document_id','subtitle','record_type','authority_level','status','effective_date','last_reviewed','review_cycle','owner','canonical_record','correction_url'];
        foreach ($map as $k) update_post_meta($post_id,'_sc_foundation_'.$k, is_array($r[$k]??null)?wp_json_encode($r[$k]):sanitize_text_field((string)($r[$k]??'')));
        update_post_meta($post_id,'_sc_foundation_version',sanitize_text_field((string)($r['version']??'1.0.0')));
        update_post_meta($post_id,'_sc_foundation_author','Sustainable Catalyst');
        update_post_meta($post_id,'_sc_foundation_publisher','Sustainable Catalyst');
        update_post_meta($post_id,'_sc_foundation_show_toc',1);
        update_post_meta($post_id,'_sc_foundation_revision_history',wp_json_encode($r['revision_history']??[]));
        update_post_meta($post_id,'_sc_foundation_related_product_slugs',implode(',',array_map('sanitize_title',(array)($r['related_products']??[]))));
        update_post_meta($post_id,'_sc_foundation_related_repository_urls',implode("\n",array_map('esc_url_raw',(array)($r['related_repositories']??[]))));
        update_post_meta($post_id,'_sc_foundation_supersedes_labels',implode("\n",array_map('sanitize_text_field',(array)($r['supersedes']??[]))));
        update_post_meta($post_id,'_sc_library_doc_status','draft');
        update_post_meta($post_id,'_sc_library_doc_type',sanitize_key((string)($r['record_type']??'institutional-standard')));
        update_post_meta($post_id,'_sc_library_foundations_system_version',self::RELEASE);
    }
    private function attach_pdf(int $post_id,array $r): bool {
        $existing=absint(get_post_meta($post_id,'_sc_foundation_pdf_attachment_id',true));
        $expected=sanitize_text_field((string)($r['pdf_sha256']??''));
        if ($existing && get_post_meta($existing,'_sc_foundation_asset_sha256',true)===$expected) return false;
        $rel=(string)($r['plugin_pdf_file']??''); $source=trailingslashit(SC_LIBRARY_DIR).ltrim($rel,'/');
        if (!is_readable($source)) return false;
        $bits=wp_upload_bits(basename($source),null,(string)file_get_contents($source)); if (!empty($bits['error'])) return false;
        $attachment=wp_insert_attachment(['post_mime_type'=>'application/pdf','post_title'=>sanitize_text_field((string)$r['title'].' - Version '.(string)$r['version']),'post_status'=>'inherit'],$bits['file'],$post_id,true);
        if (is_wp_error($attachment)) return false;
        require_once ABSPATH.'wp-admin/includes/image.php'; wp_update_attachment_metadata($attachment,wp_generate_attachment_metadata($attachment,$bits['file']));
        update_post_meta($attachment,'_sc_foundation_asset_sha256',$expected); update_post_meta($post_id,'_sc_foundation_pdf_attachment_id',(int)$attachment); return true;
    }
    private function assign_foundations_collection(int $post_id): void {
        if (!class_exists('SC_Library_Taxonomies')) return; $tax=SC_Library_Taxonomies::COLLECTION; if (!taxonomy_exists($tax)) return;
        $term=term_exists('foundations',$tax); if (!$term) $term=wp_insert_term('Foundations',$tax,['slug'=>'foundations']);
        if (!is_wp_error($term)) wp_set_object_terms($post_id,['foundations'],$tax,true);
    }
}
SC_Library_Foundations_First_Edition_V210::instance()->register_hooks();
