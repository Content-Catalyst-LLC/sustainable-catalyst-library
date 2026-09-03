<?php
if (!defined('ABSPATH')) { exit; }

/** v5.8.2 Medical Terminology & Disease Classification. */
final class SC_Library_Medical_Terminology {
    public const VERSION = '5.8.2';
    public const SHORTCODE = 'sc_medical_terminology';

    /** @return array<int,array<string,string>> */
    public static function network_sources(): array {
        return [[
            'id'=>'icd11','name'=>'WHO ICD-11','kind'=>'scholarly','type'=>'Disease classification','mode'=>'LIVE API',
            'detail'=>'2026 MMS · disease codes · multilingual classification · WHO provenance',
        ]];
    }

    public function register_hooks(): void {
        add_action('rest_api_init', [$this,'register_routes']);
        add_action('wp_enqueue_scripts', [$this,'register_assets']);
        add_shortcode(self::SHORTCODE, [$this,'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-medical-terminology-v582', SC_LIBRARY_URL.'assets/css/sc-library-medical-terminology-v582.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-medical-terminology-v582', SC_LIBRARY_URL.'assets/js/sc-library-medical-terminology-v582.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1','/medical-terminology',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'manifest'] ]);
        register_rest_route('sc-library/v1','/medical-terminology/resolve',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'resolve'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true], 'limit'=>['sanitize_callback'=>'absint','default'=>5],
        ]]);
        register_rest_route('sc-library/v1','/medical-terminology/icd11/search',[ 'methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>[$this,'icd11_search'],'args'=>[
            'q'=>['sanitize_callback'=>'sanitize_text_field','required'=>true], 'limit'=>['sanitize_callback'=>'absint','default'=>10],
        ]]);
    }

    public function manifest(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/medical-terminology'); }
    public function resolve(WP_REST_Request $request) { return $this->proxy('/v1/medical-terminology/resolve',['q'=>(string)$request->get_param('q'),'limit'=>min(10,max(1,(int)$request->get_param('limit')))]); }
    public function icd11_search(WP_REST_Request $request) { return $this->proxy('/v1/medical-terminology/icd11/search',['q'=>(string)$request->get_param('q'),'limit'=>min(25,max(1,(int)$request->get_param('limit')))]); }

    private function proxy(string $path,array $params=[]) {
        if (!SC_Library_Python_Backend::configured()) return new WP_Error('sc_library_backend_not_configured',__('Library backend is not configured.','sustainable-catalyst-library'),['status'=>503]);
        $url=SC_Library_Python_Backend::base_url().$path; if($params){$url=add_query_arg($params,$url);}
        $response=wp_remote_get($url,['timeout'=>12,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($response)) return new WP_Error('sc_library_medical_terminology_unavailable',$response->get_error_message(),['status'=>503]);
        $code=(int)wp_remote_retrieve_response_code($response); $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if(!is_array($body)) $body=['ok'=>false,'detail'=>__('Medical terminology service returned an invalid response.','sustainable-catalyst-library')];
        return new WP_REST_Response($body,$code?:502);
    }

    public function shortcode(array $atts=[]): string {
        $atts=shortcode_atts([
            'title'=>'Medical Terminology & Disease Classification',
            'intro'=>'Resolve biomedical concepts across WHO ICD-11, MeSH, and RxNorm while preserving each vocabulary’s meaning and provenance.',
        ],$atts,self::SHORTCODE);
        wp_enqueue_style('sc-library-medical-terminology-v582'); wp_enqueue_script('sc-library-medical-terminology-v582');
        $endpoint=rest_url('sc-library/v1/medical-terminology/resolve');
        ob_start(); ?>
        <section class="sc-medical-terminology" data-sc-medical-terminology data-endpoint="<?php echo esc_url($endpoint); ?>">
          <header><p class="sc-medical-terminology__kicker"><?php esc_html_e('Clinical Vocabulary Infrastructure','sustainable-catalyst-library'); ?></p><h2><?php echo esc_html((string)$atts['title']); ?></h2><p><?php echo esc_html((string)$atts['intro']); ?></p></header>
          <div class="sc-medical-terminology__sources" aria-label="Terminology sources"><span>WHO ICD-11 · 2026 MMS</span><span>MeSH 2026</span><span>RxNorm</span></div>
          <form class="sc-medical-terminology__search" role="search"><label><span><?php esc_html_e('Medical concept','sustainable-catalyst-library'); ?></span><input type="search" name="q" required placeholder="Disease, disorder, symptom, drug, biomedical concept…"></label><button type="submit"><?php esc_html_e('Resolve Concept','sustainable-catalyst-library'); ?></button></form>
          <p class="sc-medical-terminology__notice"><?php esc_html_e('Cross-vocabulary results are candidate alignments, not automatic semantic equivalence or patient-specific diagnosis. Human review is required.','sustainable-catalyst-library'); ?></p>
          <p class="sc-medical-terminology__status" aria-live="polite"></p><div class="sc-medical-terminology__results"></div>
          <footer><?php esc_html_e('ICD-11 classifications are stewarded by the World Health Organization. MeSH and RxNorm are U.S. National Library of Medicine resources. Source inclusion does not imply endorsement.','sustainable-catalyst-library'); ?></footer>
        </section><?php return (string)ob_get_clean();
    }
}
