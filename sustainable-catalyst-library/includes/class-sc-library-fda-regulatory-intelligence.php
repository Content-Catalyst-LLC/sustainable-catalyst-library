<?php
if (!defined('ABSPATH')) { exit; }

/** v5.8.1 FDA Drug & Regulatory Intelligence. */
final class SC_Library_FDA_Regulatory_Intelligence {
    public const VERSION = '5.8.1';
    public const SHORTCODE = 'sc_fda_regulatory_intelligence';
    private const SOURCES = ['drugsfda','fda-labels','fda-ndc','fda-adverse-events','fda-recalls','fda-shortages','orange-book'];

    /** @return array<int,array<string,string>> */
    public static function network_sources(): array {
        return [[
            'id'=>'fda-regulatory',
            'name'=>'FDA Drug & Regulatory Data',
            'kind'=>'scholarly',
            'type'=>'Regulatory intelligence',
            'mode'=>'LIVE API',
            'detail'=>'Drugs@FDA · labels · NDC · FAERS · recalls · shortages · Orange Book',
            'homepage'=>'https://open.fda.gov/apis/drug/',
        ]];
    }

    public function register_hooks(): void {
        add_action('rest_api_init', [$this,'register_routes']);
        add_action('wp_enqueue_scripts', [$this,'register_assets']);
        add_shortcode(self::SHORTCODE, [$this,'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-fda-v581', SC_LIBRARY_URL.'assets/css/sc-library-fda-v581.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-fda-v581', SC_LIBRARY_URL.'assets/js/sc-library-fda-v581.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1','/fda-sources',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'sources'] ]);
        register_rest_route('sc-library/v1','/fda/search',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'unified_search'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true],
            'sources'=>['sanitize_callback'=>'sanitize_text_field','default'=>''],
            'limit'=>['sanitize_callback'=>'absint','default'=>4],
        ]]);
        register_rest_route('sc-library/v1','/fda-sources/(?P<source>[a-z0-9-]+)/search',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'source_search'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true], 'limit'=>['sanitize_callback'=>'absint','default'=>10], 'cursor'=>['sanitize_callback'=>'sanitize_text_field','default'=>''],
        ]]);
    }

    public function sources(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/fda-sources'); }
    public function unified_search(WP_REST_Request $request) {
        return $this->proxy('/v1/fda/search',['q'=>(string)$request->get_param('q'),'sources'=>(string)$request->get_param('sources'),'limit'=>min(10,max(1,(int)$request->get_param('limit')))]);
    }
    public function source_search(WP_REST_Request $request) {
        $source=sanitize_key((string)$request['source']);
        if (!in_array($source,self::SOURCES,true)) return new WP_Error('sc_library_unknown_fda_source',__('Unknown FDA regulatory source.','sustainable-catalyst-library'),['status'=>404]);
        return $this->proxy('/v1/fda-sources/'.rawurlencode($source).'/search',['q'=>(string)$request->get_param('q'),'limit'=>min(20,max(1,(int)$request->get_param('limit'))),'cursor'=>(string)$request->get_param('cursor')]);
    }
    private function proxy(string $path,array $params=[]) {
        if (!SC_Library_Python_Backend::configured()) return new WP_Error('sc_library_backend_not_configured',__('Library backend is not configured.','sustainable-catalyst-library'),['status'=>503]);
        $url=SC_Library_Python_Backend::base_url().$path; if($params){$url=add_query_arg($params,$url);}
        $response=wp_remote_get($url,['timeout'=>15,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($response)) return new WP_Error('sc_library_fda_unavailable',$response->get_error_message(),['status'=>503]);
        $code=(int)wp_remote_retrieve_response_code($response); $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if(!is_array($body)) $body=['ok'=>false,'detail'=>__('FDA source returned an invalid response.','sustainable-catalyst-library')];
        return new WP_REST_Response($body,$code?:502);
    }

    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts([
            'title'=>'FDA Drug & Regulatory Intelligence',
            'intro'=>'Search FDA approvals, prescribing labels, NDC product listings, adverse-event reports, recalls, shortages, and Orange Book records with evidence class and provenance preserved.'
        ],$atts,self::SHORTCODE);
        wp_enqueue_style('sc-library-fda-v581'); wp_enqueue_script('sc-library-fda-v581');
        $endpoint=rest_url('sc-library/v1/fda/search');
        ob_start(); ?>
        <section class="sc-fda" data-sc-fda data-endpoint="<?php echo esc_url($endpoint); ?>">
          <header><p class="sc-fda__kicker"><?php esc_html_e('Regulatory & Drug Safety Knowledge','sustainable-catalyst-library'); ?></p><h2><?php echo esc_html((string)$atts['title']); ?></h2><p><?php echo esc_html((string)$atts['intro']); ?></p></header>
          <div class="sc-fda__sources" aria-label="FDA regulatory sources"><span>Drugs@FDA</span><span>Drug Labels</span><span>NDC</span><span>FAERS</span><span>Recalls</span><span>Shortages</span><span>Orange Book</span></div>
          <form class="sc-fda__search" role="search"><label><span><?php esc_html_e('Drug or regulatory research query','sustainable-catalyst-library'); ?></span><input type="search" name="q" required placeholder="Drug, ingredient, application, recall, safety signal…"></label><button type="submit"><?php esc_html_e('Search FDA','sustainable-catalyst-library'); ?></button></form>
          <div class="sc-fda__guardrails">
            <p><strong><?php esc_html_e('Evidence classes stay separate.','sustainable-catalyst-library'); ?></strong> <?php esc_html_e('Approval records, labels, adverse-event reports, recalls, shortages, and therapeutic-equivalence records are not interchangeable evidence.','sustainable-catalyst-library'); ?></p>
            <p><?php esc_html_e('FAERS/openFDA adverse-event reports do not establish that a drug caused an event and cannot by themselves establish incidence or risk.','sustainable-catalyst-library'); ?></p>
          </div>
          <p class="sc-fda__status" aria-live="polite"></p><div class="sc-fda__results"></div>
          <footer><?php esc_html_e('Research and evidence infrastructure only. Do not rely on openFDA results to make medical-care decisions. Source inclusion does not imply FDA endorsement.','sustainable-catalyst-library'); ?></footer>
        </section><?php return (string)ob_get_clean();
    }
}
