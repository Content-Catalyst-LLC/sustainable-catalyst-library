<?php
if (!defined('ABSPATH')) { exit; }

final class SC_Library_State_Migration {
    public const VERSION = '5.66.0';
    public const RETIRED_OPTION = 'sc_library_legacy_research_state_retired';
    public const RETIREMENT_META_OPTION = 'sc_library_legacy_research_state_retirement';

    private const TABLES = [
        'workspaces' => ['sc_library_workspaces','sc_library_workspace_revisions','sc_library_workspace_collaborators','sc_library_workspace_sync_log'],
        'collaboration' => ['sc_library_reviews','sc_library_review_participants','sc_library_review_comments','sc_library_review_suggestions','sc_library_review_events'],
        'knowledge-graph' => ['sc_library_graph_nodes','sc_library_graph_edges'],
        'relationships' => ['sc_library_relationships'],
        'orchestration' => ['sc_library_orchestration_sessions','sc_library_orchestration_events'],
        'document-production' => ['sc_library_document_jobs','sc_library_document_editions'],
        'multimedia-research' => ['sc_library_media_assets','sc_library_media_clips','sc_library_media_reels','sc_library_media_jobs'],
        'preservation-lineage' => ['sc_library_preservation_snapshots','sc_library_integrity_checks','sc_library_authority_history','sc_library_system_manifests','sc_library_system_events'],
    ];

    private const POST_TYPES = [
        'research-planning' => 'sc_content_plan',
        'research-rooms' => 'sc_research_room',
        'curated-research-spaces' => 'sc_curated_space',
        'reading-notebooks' => 'sc_reading_notebook',
        'evidence-matrices' => 'sc_evidence_matrix',
        'federation-shares' => 'sc_federation_share',
        'metadata-review' => 'sc_metadata_review',
        'team-libraries' => 'sc_team_library',
    ];

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_rest']);
        add_shortcode('sc_library_state_migration_status', [$this, 'shortcode']);
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('sc-library-state', new SC_Library_State_Migration_CLI($this));
        }
    }

    public function register_rest(): void {
        register_rest_route('sc-library/v1', '/state-migration', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'status'],
        ]);
    }

    public function status(): array {
        $backend = $this->backend_readiness();
        $retired = (bool) get_option(self::RETIRED_OPTION, false);
        return [
            'schema' => 'sc-library-wordpress-state-migration-adapter/1.0',
            'version' => self::VERSION,
            'wordpress_role' => 'thin-adapter',
            'wordpress_authoritative' => false,
            'legacy_research_state_retired' => $retired,
            'backend' => $backend,
            'physical_deletion_supported' => false,
        ];
    }

    public function shortcode(): string {
        $s = $this->status();
        $state = esc_html((string)($s['backend']['state'] ?? 'unknown'));
        $retired = !empty($s['legacy_research_state_retired']) ? 'retired' : 'not retired';
        return '<div class="sc-library-state-migration-status"><strong>Library state migration:</strong> '.$state.' · WordPress legacy state '.$retired.'</div>';
    }

    public function backend_readiness(): array {
        $url = SC_Library_Python_Backend::base_url() . '/api/library/v1/state-migration/readiness';
        $r = wp_remote_get($url, ['timeout'=>8,'redirection'=>1,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['state'=>'backend-unavailable'];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        return is_array($body) ? $body : ['state'=>'invalid-response'];
    }

    public function inventory(): array {
        global $wpdb;
        $domains = [];
        foreach (self::TABLES as $domain => $tables) {
            $count = 0; $present = [];
            foreach ($tables as $suffix) {
                $table = $wpdb->prefix . $suffix;
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
                if (!$exists) continue;
                $present[] = $suffix;
                $count += (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            }
            $domains[$domain] = ['source_kind'=>'table','records'=>$count,'sources'=>$present];
        }
        foreach (self::POST_TYPES as $domain => $post_type) {
            $counts = wp_count_posts($post_type);
            $count = 0;
            if ($counts) foreach ((array)$counts as $n) $count += (int)$n;
            $domains[$domain] = ['source_kind'=>'post_type','records'=>$count,'sources'=>[$post_type]];
        }
        return [
            'schema'=>'sc-library-wordpress-state-inventory/1.0',
            'source_site'=>home_url('/'),
            'library_version'=>self::VERSION,
            'domains'=>$domains,
            'legacy_research_state_retired'=>(bool)get_option(self::RETIRED_OPTION,false),
            'excluded'=>['users','passwords','sessions','api-keys','signing-secrets','transients','caches','search-indexes','editorial-pages'],
        ];
    }

    private function scrub($value) {
        if (!is_array($value)) return $value;
        $out = [];
        foreach ($value as $k => $v) {
            $key = strtolower((string)$k);
            if (preg_match('/password|passwd|secret|api[_-]?key|token|credential|authorization|private[_-]?key|client[_-]?secret/', $key)) continue;
            $out[$k] = $this->scrub($v);
        }
        return $out;
    }

    private function canonicalize($value) {
        if (!is_array($value)) return $value;
        foreach ($value as $k => $v) $value[$k] = $this->canonicalize($v);
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        return $value;
    }

    private function item(string $domain, string $kind, string $source_key, string $source_id, array $payload): array {
        $payload = $this->canonicalize($this->scrub($payload));
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return [
            'domain'=>$domain,
            'source_kind'=>$kind,
            'source_key'=>$source_key,
            'source_id'=>$source_id,
            'content_sha256'=>hash('sha256', $json),
            'payload'=>$payload,
            'provenance'=>['source'=>'wordpress','exported_at'=>gmdate('c'),'plugin_version'=>self::VERSION],
        ];
    }

    public function export_manifest(): array {
        global $wpdb;
        $items = [];
        foreach (self::TABLES as $domain => $tables) {
            foreach ($tables as $suffix) {
                $table = $wpdb->prefix . $suffix;
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
                if (!$exists) continue;
                $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
                foreach ((array)$rows as $i => $row) {
                    $id = '';
                    foreach (['id','workspace_id','revision_id','review_id','node_id','edge_id','session_id','event_id','asset_id','job_id','edition_id','snapshot_id','check_id','manifest_id'] as $candidate) {
                        if (isset($row[$candidate]) && (string)$row[$candidate] !== '') { $id = (string)$row[$candidate]; break; }
                    }
                    if ($id === '') $id = (string)$i;
                    $items[] = $this->item($domain,'table',$suffix,$id,$row);
                }
            }
        }
        foreach (self::POST_TYPES as $domain => $post_type) {
            $ids = get_posts(['post_type'=>$post_type,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC']);
            foreach ((array)$ids as $id) {
                $post = get_post($id, ARRAY_A);
                if (!$post) continue;
                $meta = get_post_meta($id);
                $payload = ['post'=>$post,'meta'=>$meta];
                $items[] = $this->item($domain,'post_type',$post_type,(string)$id,$payload);
            }
        }
        return [
            'schema'=>'sc-library-wordpress-state-migration-manifest/1.0',
            'source_site'=>home_url('/'),
            'exported_at'=>gmdate('c'),
            'item_count'=>count($items),
            'items'=>$items,
            'guardrails'=>['credentials_excluded'=>true,'sessions_excluded'=>true,'editorial_content_excluded'=>true,'physical_deletion_requested'=>false],
        ];
    }

    public function verify_certification(string $certification_id): array {
        $url = SC_Library_Python_Backend::base_url() . '/api/library/v1/state-migration/certifications/' . rawurlencode($certification_id);
        $r = wp_remote_get($url, ['timeout'=>8,'redirection'=>1,'headers'=>['Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['valid'=>false,'error'=>$r->get_error_message()];
        if (wp_remote_retrieve_response_code($r) !== 200) return ['valid'=>false,'error'=>'certification-not-found'];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($body) || ($body['status'] ?? '') !== 'certified' || !empty($body['physical_deletion_authorized'])) return ['valid'=>false,'error'=>'certification-invalid'];
        return ['valid'=>true,'certification'=>$body];
    }

    public function retire_authority(string $run_id, string $certification_id): array {
        $check = $this->verify_certification($certification_id);
        if (empty($check['valid'])) throw new RuntimeException((string)($check['error'] ?? 'certification-invalid'));
        $cert = $check['certification'];
        if (($cert['run_id'] ?? '') !== $run_id) throw new RuntimeException('certification-run-mismatch');
        update_option(self::RETIRED_OPTION, 1, false);
        update_option(self::RETIREMENT_META_OPTION, [
            'run_id'=>$run_id,
            'certification_id'=>$certification_id,
            'retired_at'=>gmdate('c'),
            'authority'=>'library-service',
            'physical_data_deleted'=>false,
            'rollback_copy_retained'=>true,
        ], false);
        return ['retired'=>true,'run_id'=>$run_id,'certification_id'=>$certification_id,'physical_data_deleted'=>false];
    }
}

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI_Command')) {
    final class SC_Library_State_Migration_CLI extends WP_CLI_Command {
        private SC_Library_State_Migration $migration;
        public function __construct(SC_Library_State_Migration $migration) { $this->migration = $migration; }

        public function inventory($args, $assoc_args): void {
            WP_CLI::line(wp_json_encode($this->migration->inventory(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }

        public function export($args, $assoc_args): void {
            $output = (string)($assoc_args['output'] ?? '');
            if ($output === '') WP_CLI::error('--output=/absolute/path/manifest.json is required');
            $manifest = $this->migration->export_manifest();
            $bytes = file_put_contents($output, wp_json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            if ($bytes === false) WP_CLI::error('Unable to write migration manifest');
            WP_CLI::success('Exported '.count($manifest['items']).' legacy research-state items to '.$output);
        }

        public function retire($args, $assoc_args): void {
            if (($assoc_args['confirm'] ?? '') !== 'FREEZE-LEGACY-LIBRARY-STATE') WP_CLI::error('--confirm=FREEZE-LEGACY-LIBRARY-STATE is required');
            $run_id = (string)($assoc_args['run-id'] ?? '');
            $certification_id = (string)($assoc_args['certification-id'] ?? '');
            if ($run_id === '' || $certification_id === '') WP_CLI::error('--run-id and --certification-id are required');
            try { $result = $this->migration->retire_authority($run_id,$certification_id); }
            catch (Throwable $e) { WP_CLI::error($e->getMessage()); return; }
            WP_CLI::success('WordPress legacy Library research-state authority retired; no source rows were deleted.');
            WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
    }
}
