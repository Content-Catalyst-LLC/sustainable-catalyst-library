<?php
if (!defined('ABSPATH')) { exit; }

/** v5.8.0 Biomedical & Clinical Evidence Intelligence Foundation. */
final class SC_Library_Biomedical_Evidence {
    public const VERSION = '5.8.0';
    public const SHORTCODE = 'sc_biomedical_evidence';
    private const SOURCES = ['pubmed','pmc','clinicaltrials','mesh','rxnorm'];

    /** @return array<int,array<string,string>> */
    public static function network_sources(): array {
        return [
            ['id'=>'clinicaltrials','name'=>'ClinicalTrials.gov','kind'=>'scholarly','type'=>'Clinical trial registry','mode'=>'LIVE API','detail'=>'Registered studies · phases · status · outcomes · sponsors'],
            ['id'=>'mesh','name'=>'Medical Subject Headings (MeSH)','kind'=>'scholarly','type'=>'Biomedical terminology','mode'=>'LIVE API','detail'=>'2026 controlled vocabulary · concept resolution · hierarchy'],
            ['id'=>'rxnorm','name'=>'RxNorm','kind'=>'scholarly','type'=>'Drug terminology','mode'=>'LIVE API','detail'=>'Normalized drug concepts · RxCUI resolution'],
        ];
    }

    public function register_hooks(): void {
        add_action('rest_api_init', [$this,'register_routes']);
        add_action('wp_enqueue_scripts', [$this,'register_assets']);
        add_shortcode(self::SHORTCODE, [$this,'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-biomedical-v580', SC_LIBRARY_URL.'assets/css/sc-library-biomedical-v580.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-biomedical-v580', SC_LIBRARY_URL.'assets/js/sc-library-biomedical-v580.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1','/biomedical-sources',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'sources'] ]);
        register_rest_route('sc-library/v1','/biomedical/search',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'unified_search'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true],
            'sources'=>['sanitize_callback'=>'sanitize_text_field','default'=>''],
            'limit'=>['sanitize_callback'=>'absint','default'=>5],
        ]]);
        register_rest_route('sc-library/v1','/biomedical-sources/(?P<source>[a-z0-9-]+)/search',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'source_search'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true], 'limit'=>['sanitize_callback'=>'absint','default'=>10], 'cursor'=>['sanitize_callback'=>'sanitize_text_field','default'=>''],
        ]]);
    }
    public function sources(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/biomedical-sources'); }
    public function unified_search(WP_REST_Request $request) {
        return $this->proxy('/v1/biomedical/search',['q'=>(string)$request->get_param('q'),'sources'=>(string)$request->get_param('sources'),'limit'=>min(20,max(1,(int)$request->get_param('limit')))]);
    }
    public function source_search(WP_REST_Request $request) {
        $source=sanitize_key((string)$request['source']);
        if (!in_array($source,self::SOURCES,true)) return new WP_Error('sc_library_unknown_biomedical_source',__('Unknown biomedical source.','sustainable-catalyst-library'),['status'=>404]);
        return $this->proxy('/v1/biomedical-sources/'.rawurlencode($source).'/search',['q'=>(string)$request->get_param('q'),'limit'=>min(50,max(1,(int)$request->get_param('limit'))),'cursor'=>(string)$request->get_param('cursor')]);
    }
    private function proxy(string $path,array $params=[]) {
        if (!SC_Library_Python_Backend::configured()) return new WP_Error('sc_library_backend_not_configured',__('Library backend is not configured.','sustainable-catalyst-library'),['status'=>503]);
        $url=SC_Library_Python_Backend::base_url().$path; if($params){$url=add_query_arg($params,$url);} 
        $response=wp_remote_get($url,['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($response)) return new WP_Error('sc_library_biomedical_unavailable',$response->get_error_message(),['status'=>503]);
        $code=(int)wp_remote_retrieve_response_code($response); $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if(!is_array($body)) $body=['ok'=>false,'detail'=>__('Biomedical source returned an invalid response.','sustainable-catalyst-library')];
        return new WP_REST_Response($body,$code?:502);
    }
    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts(['title'=>'Biomedical & Clinical Evidence','intro'=>'Search biomedical literature, open full text, clinical trials, medical subject headings, and normalized drug concepts through governed authoritative sources.'],$atts,self::SHORTCODE);
        wp_enqueue_style('sc-library-biomedical-v580'); wp_enqueue_script('sc-library-biomedical-v580');
        $endpoint=rest_url('sc-library/v1/biomedical/search');
        ob_start(); ?>
        <section class="sc-biomedical" data-sc-biomedical data-endpoint="<?php echo esc_url($endpoint); ?>">
          <header><p class="sc-biomedical__kicker"><?php esc_html_e('Medical & Biomedical Knowledge','sustainable-catalyst-library'); ?></p><h2><?php echo esc_html((string)$atts['title']); ?></h2><p><?php echo esc_html((string)$atts['intro']); ?></p></header>
          <div class="sc-biomedical__sources" aria-label="Biomedical sources"><span>PubMed</span><span>PMC</span><span>ClinicalTrials.gov</span><span>MeSH 2026</span><span>RxNorm</span></div>
          <form class="sc-biomedical__search" role="search"><label><span><?php esc_html_e('Biomedical research query','sustainable-catalyst-library'); ?></span><input type="search" name="q" required placeholder="Condition, intervention, drug, mechanism, study…"></label><button type="submit"><?php esc_html_e('Search Evidence','sustainable-catalyst-library'); ?></button></form>
          <p class="sc-biomedical__notice"><?php esc_html_e('Research and evidence infrastructure only. Results are not patient-specific diagnosis, treatment, or clinical decision support.','sustainable-catalyst-library'); ?></p>
          <p class="sc-biomedical__status" aria-live="polite"></p><div class="sc-biomedical__results"></div>
          <footer><?php esc_html_e('Uses publicly available NLM/NIH and ClinicalTrials.gov data. Source inclusion does not imply endorsement.','sustainable-catalyst-library'); ?></footer>
        </section><?php return (string)ob_get_clean();
    }
}
