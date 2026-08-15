<?php
/**
 * Metadata Quality & Entity Resolution — v4.3.34.
 *
 * Adds deterministic metadata-quality diagnostics and non-destructive entity
 * resolution above the existing Citation Studio source normalization and v3.2
 * named-entity authority records. No record is merged, deleted, or rewritten
 * without an explicit reviewer action.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Metadata_Quality_Entity_Resolution {
    public const VERSION = '4.3.34';
    public const SCHEMA = 'sc-library-metadata-quality/1.0';
    public const REPORT_SCHEMA = 'sc-library-metadata-quality-report/1.0';
    public const RESOLUTION_SCHEMA = 'sc-library-entity-resolution/1.0';
    public const REVIEW_SCHEMA = 'sc-library-metadata-review-event/1.0';
    public const POST_TYPE = 'sc_metadata_review';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/metadata-quality';
    public const NONCE_ACTION = 'sc_library_metadata_quality_v4334';
    public const META_EVENT = '_sc_metadata_review_event_v4334';
    public const META_ENTITY_CANONICAL = '_sc_entity_resolution_canonical_id_v4334';
    public const META_ENTITY_STATUS = '_sc_entity_resolution_status_v4334';
    public const META_ENTITY_HISTORY = '_sc_entity_resolution_history_v4334';
    public const MAX_CANDIDATES = 30;
    public const MAX_SCAN = 250;
    public const MAX_HISTORY = 100;

    public function __construct() {
        add_action('init', array($this,'register_post_type'), 13);
        add_action('wp_enqueue_scripts', array($this,'register_assets'));
        add_action('rest_api_init', array($this,'register_rest_routes'));
        add_shortcode('sc_metadata_quality_center', array($this,'shortcode'));
        add_filter('sc_library_resolve_entity_id', array($this,'filter_resolve_entity_id'), 10, 1);
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels'=>array('name'=>__('Metadata Reviews','sustainable-catalyst-library'),'singular_name'=>__('Metadata Review','sustainable-catalyst-library')),
            'public'=>false,'publicly_queryable'=>false,'show_ui'=>false,'show_in_menu'=>false,'show_in_rest'=>false,
            'exclude_from_search'=>true,'rewrite'=>false,'query_var'=>false,'supports'=>array('title','author'),'capability_type'=>'post','map_meta_cap'=>true,
        ));
    }

    public function register_assets() {
        wp_register_style('sc-library-metadata-quality-v4334', SC_LIBRARY_URL.'assets/css/sc-library-metadata-quality-v4334.css', array(), SC_LIBRARY_VERSION);
        wp_register_script('sc-library-metadata-quality-v4334', SC_LIBRARY_URL.'assets/js/sc-library-metadata-quality-v4334.js', array(), SC_LIBRARY_VERSION, true);
    }

    public static function contract() {
        return array(
            'schema'=>self::SCHEMA,
            'citation_source_normalization_reused'=>true,
            'v320_named_entity_authority_reused'=>true,
            'deterministic_normalization_only'=>true,
            'quality_scores_are_diagnostics_not_truth'=>true,
            'duplicate_candidates_are_proposals_not_merges'=>true,
            'explicit_reviewer_acceptance_required'=>true,
            'automatic_merge'=>false,
            'automatic_record_deletion'=>false,
            'automatic_assignment_rewrite'=>false,
            'automatic_metadata_overwrite'=>false,
            'pre_resolution_values_preserved'=>true,
            'entity_alias_history_preserved'=>true,
            'source_provenance_preserved'=>true,
            'automatic_publication'=>false,
            'automatic_workspace_write'=>false,
        );
    }

    private static function now(){ return gmdate('c'); }
    private static function clean($v,$n=240){ $v=sanitize_text_field((string)$v); return function_exists('mb_substr')?mb_substr($v,0,$n):substr($v,0,$n); }
    private static function list_strings($v,$limit=80){
        if(is_string($v)) $v=preg_split('/[\r\n,]+/u',$v);
        $out=array(); foreach((array)$v as $x){$x=self::clean($x,180);if($x!==''&&!in_array($x,$out,true))$out[]=$x;if(count($out)>=$limit)break;} return $out;
    }
    public static function normalize_label($value){
        $value=html_entity_decode(wp_strip_all_tags((string)$value),ENT_QUOTES,'UTF-8');
        $value=remove_accents($value); $value=strtolower($value); $value=preg_replace('/[^a-z0-9]+/u',' ',$value); return trim(preg_replace('/\s+/u',' ',$value));
    }
    public static function normalize_doi($value){$v=strtolower(trim((string)$value));$v=preg_replace('#^https?://(?:dx\.)?doi\.org/#','',$v);$v=preg_replace('/^doi:\s*/','',$v);return trim($v);}
    public static function normalize_isbn($value){return strtoupper(preg_replace('/[^0-9Xx]/','',(string)$value));}
    public static function normalize_url($value){$v=esc_url_raw((string)$value);if($v==='')return ''; $p=wp_parse_url($v); if(!is_array($p)||empty($p['host']))return $v; $scheme=strtolower((string)($p['scheme']??'https')); $host=strtolower((string)$p['host']); $path=rtrim((string)($p['path']??''),'/'); return $scheme.'://'.$host.$path;}

    private static function editor_can_review(){return is_user_logged_in() && current_user_can('edit_posts');}
    private static function source_post_type(){return class_exists('SC_Library_Citation_Source_Manager')?SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE:'sc_research_source';}
    private static function entity_post_type(){return class_exists('SC_Library_Topics_Concepts_Relationships')?SC_Library_Topics_Concepts_Relationships::ENTITY_POST_TYPE:'sc_named_entity';}

    public static function source_quality_report($source_id){
        $source_id=absint($source_id); $p=get_post($source_id); if(!$p||self::source_post_type()!==$p->post_type)return new WP_Error('sc_metadata_source','Research Source not found.',array('status'=>404));
        $data=class_exists('SC_Library_Citation_Source_Manager')?SC_Library_Citation_Source_Manager::get_source_data($source_id,true):array();
        $title=(string)($data['title']??get_the_title($source_id)); $authors=(array)($data['authors']??array()); $org=(string)($data['organization']??'');
        $doi=class_exists('SC_Library_Citation_Source_Manager')?(string)get_post_meta($source_id,SC_Library_Citation_Source_Manager::META_NORMALIZED_DOI,true):''; if($doi==='')$doi=self::normalize_doi($data['doi']??''); $isbn=class_exists('SC_Library_Citation_Source_Manager')?(string)get_post_meta($source_id,SC_Library_Citation_Source_Manager::META_NORMALIZED_ISBN,true):''; if($isbn==='')$isbn=self::normalize_isbn($data['isbn']??''); $pmid=self::clean($data['pmid']??'',80); $url=class_exists('SC_Library_Citation_Source_Manager')?(string)get_post_meta($source_id,SC_Library_Citation_Source_Manager::META_NORMALIZED_URL,true):''; if($url==='')$url=self::normalize_url($data['url']??'');
        $checks=array(
            'title'=>array('ok'=>self::normalize_label($title)!=='','weight'=>20),
            'creator'=>array('ok'=>!empty($authors)||$org!=='','weight'=>15),
            'date_or_year'=>array('ok'=>!empty($data['publication_date'])||!empty($data['year']),'weight'=>10),
            'source_type'=>array('ok'=>!empty($data['source_type']),'weight'=>10),
            'resolvable_identifier'=>array('ok'=>$doi!==''||$isbn!==''||$pmid!==''||$url!=='','weight'=>20),
            'language'=>array('ok'=>!empty($data['language']),'weight'=>5),
            'provenance'=>array('ok'=>!empty($data['metadata_provenance']),'weight'=>10),
            'verification'=>array('ok'=>!empty($data['metadata_verified'])||!empty($data['last_verified']),'weight'=>10),
        );
        $score=0;$missing=array();foreach($checks as $k=>$c){if($c['ok'])$score+=$c['weight'];else $missing[]=$k;}
        $dup=(array)($data['duplicate_matches']??array());$canonical=absint($data['canonical_source_id']??0)?:$source_id;
        return array('schema'=>self::REPORT_SCHEMA,'record_kind'=>'research_source','record_id'=>$source_id,'title'=>$title,'quality_score'=>$score,'checks'=>$checks,'missing'=>$missing,'normalized'=>array('title'=>self::normalize_label($title),'doi'=>$doi,'isbn'=>$isbn,'pmid'=>$pmid,'url'=>$url),'duplicate_candidates'=>array_values(array_map('absint',$dup)),'canonical_source_id'=>$canonical,'citation_completeness_score'=>absint($data['completeness_score']??0),'quality_score_is_diagnostic'=>true,'automatic_rewrite'=>false);
    }

    private static function entity_snapshot($entity_id){
        $entity_id=absint($entity_id);$p=get_post($entity_id);if(!$p||self::entity_post_type()!==$p->post_type)return array();
        $aliases=class_exists('SC_Library_Topics_Concepts_Relationships')?self::list_strings(get_post_meta($entity_id,SC_Library_Topics_Concepts_Relationships::META_ENTITY_ALIASES,true)):array();
        $uri=class_exists('SC_Library_Topics_Concepts_Relationships')?(string)get_post_meta($entity_id,SC_Library_Topics_Concepts_Relationships::META_ENTITY_URI,true):'';
        $type=class_exists('SC_Library_Topics_Concepts_Relationships')?(string)get_post_meta($entity_id,SC_Library_Topics_Concepts_Relationships::META_ENTITY_TYPE,true):'';
        return array('id'=>$entity_id,'title'=>get_the_title($entity_id),'normalized_title'=>self::normalize_label(get_the_title($entity_id)),'aliases'=>$aliases,'normalized_aliases'=>array_values(array_unique(array_filter(array_map(array(self::class,'normalize_label'),$aliases)))),'external_uri'=>esc_url_raw($uri),'entity_type'=>sanitize_key($type?:'other'),'canonical_id'=>absint(get_post_meta($entity_id,self::META_ENTITY_CANONICAL,true)),'resolution_status'=>(string)get_post_meta($entity_id,self::META_ENTITY_STATUS,true));
    }

    public static function entity_candidates($entity_id){
        $base=self::entity_snapshot($entity_id); if(!$base)return new WP_Error('sc_metadata_entity','Named Entity not found.',array('status'=>404));
        $ids=get_posts(array('post_type'=>self::entity_post_type(),'post_status'=>array('publish','private','draft'),'posts_per_page'=>self::MAX_SCAN,'fields'=>'ids','orderby'=>'ID','order'=>'ASC'));
        $out=array(); foreach($ids as $id){$id=absint($id);if($id===absint($entity_id))continue;$c=self::entity_snapshot($id);if(!$c)continue;$strength='';$score=0;$reason='';
            if($base['external_uri']!==''&&$c['external_uri']!==''&&strtolower($base['external_uri'])===strtolower($c['external_uri'])){$strength='exact-authority-uri';$score=100;$reason='Same external authority URI';}
            elseif($base['normalized_title']!==''&&$base['normalized_title']===$c['normalized_title']){$strength='exact-normalized-label';$score=96;$reason='Same normalized canonical label';}
            elseif($base['normalized_title']!==''&&in_array($base['normalized_title'],$c['normalized_aliases'],true)){$strength='label-alias-match';$score=92;$reason='Canonical label matches candidate alias';}
            elseif($c['normalized_title']!==''&&in_array($c['normalized_title'],$base['normalized_aliases'],true)){$strength='alias-label-match';$score=92;$reason='Alias matches candidate canonical label';}
            else{$shared=array_intersect($base['normalized_aliases'],$c['normalized_aliases']);if($shared){$strength='shared-normalized-alias';$score=88;$reason='Shared normalized alias';}}
            if($score>0)$out[]=array('candidate'=>$c,'score'=>$score,'strength'=>$strength,'reason'=>$reason,'proposal_only'=>true);
        }
        usort($out,static function($a,$b){return ($b['score']<=>$a['score'])?:($a['candidate']['id']<=>$b['candidate']['id']);}); return array_slice($out,0,self::MAX_CANDIDATES);
    }

    public static function entity_quality_report($entity_id){
        $e=self::entity_snapshot($entity_id);if(!$e)return new WP_Error('sc_metadata_entity','Named Entity not found.',array('status'=>404));
        $checks=array('canonical_label'=>array('ok'=>$e['normalized_title']!=='','weight'=>30),'entity_type'=>array('ok'=>$e['entity_type']!==''&&$e['entity_type']!=='other','weight'=>15),'authority_uri'=>array('ok'=>$e['external_uri']!=='','weight'=>25),'aliases'=>array('ok'=>!empty($e['aliases']),'weight'=>15),'authority_vocabulary'=>array('ok'=>class_exists('SC_Library_Topics_Concepts_Relationships')&&absint(get_post_meta($entity_id,SC_Library_Topics_Concepts_Relationships::META_ENTITY_VOCABULARY_ID,true))>0,'weight'=>15));
        $score=0;$missing=array();foreach($checks as $k=>$c){if($c['ok'])$score+=$c['weight'];else$missing[]=$k;}
        $cands=self::entity_candidates($entity_id);if(is_wp_error($cands))$cands=array();
        return array('schema'=>self::REPORT_SCHEMA,'record_kind'=>'named_entity','record_id'=>absint($entity_id),'entity'=>$e,'quality_score'=>$score,'checks'=>$checks,'missing'=>$missing,'resolution_candidates'=>$cands,'resolved_canonical_id'=>self::resolve_entity_id($entity_id),'quality_score_is_diagnostic'=>true,'automatic_merge'=>false);
    }

    public static function resolve_entity_id($entity_id){
        $current=absint($entity_id);$seen=array();for($i=0;$i<8&&$current;$i++){if(isset($seen[$current]))break;$seen[$current]=true;$next=absint(get_post_meta($current,self::META_ENTITY_CANONICAL,true));if(!$next||$next===$current||self::entity_post_type()!==get_post_type($next))break;$current=$next;}return $current;
    }
    public function filter_resolve_entity_id($entity_id){return self::resolve_entity_id($entity_id);}

    private static function review_event($user_id,$kind,$payload){
        $id=wp_insert_post(array('post_type'=>self::POST_TYPE,'post_status'=>'private','post_author'=>absint($user_id),'post_title'=>self::clean(ucwords(str_replace('_',' ',$kind)).' · '.self::now(),180)),true);
        if(is_wp_error($id))return $id;$event=array('schema'=>self::REVIEW_SCHEMA,'kind'=>sanitize_key($kind),'reviewer_user_id'=>absint($user_id),'created_at'=>self::now(),'payload'=>$payload);update_post_meta($id,self::META_EVENT,$event);return $event+array('review_id'=>absint($id));
    }

    public static function decide_entity_resolution($user_id,$candidate_id,$canonical_id,$decision,$note=''){
        if(!absint($user_id)||!user_can($user_id,'edit_posts'))return new WP_Error('sc_metadata_permission','Editorial metadata permission is required.',array('status'=>403));
        $candidate_id=absint($candidate_id);$canonical_id=absint($canonical_id);$decision=sanitize_key($decision);if(!in_array($decision,array('accept','reject'),true))return new WP_Error('sc_metadata_decision','Decision must be accept or reject.',array('status'=>400));
        $before_candidate=self::entity_snapshot($candidate_id);$before_canonical=self::entity_snapshot($canonical_id);if(!$before_candidate||!$before_canonical||$candidate_id===$canonical_id)return new WP_Error('sc_metadata_entities','Two distinct Named Entity records are required.',array('status'=>400));
        $payload=array('candidate_before'=>$before_candidate,'canonical_before'=>$before_canonical,'decision'=>$decision,'note'=>self::clean($note,1000),'automatic_assignment_rewrite'=>false,'record_deleted'=>false);
        if($decision==='accept'){
            if(self::resolve_entity_id($canonical_id)===$candidate_id)return new WP_Error('sc_metadata_cycle','Resolution would create an entity canonicalization cycle.',array('status'=>409));
            $aliases=array_merge($before_canonical['aliases'],array($before_candidate['title']),$before_candidate['aliases']);$aliases=array_values(array_filter(array_unique(array_map(array(self::class,'clean'),$aliases))));
            if(class_exists('SC_Library_Topics_Concepts_Relationships'))update_post_meta($canonical_id,SC_Library_Topics_Concepts_Relationships::META_ENTITY_ALIASES,array_slice($aliases,0,80));
            update_post_meta($candidate_id,self::META_ENTITY_CANONICAL,$canonical_id);update_post_meta($candidate_id,self::META_ENTITY_STATUS,'resolved-alias');
            $history=get_post_meta($candidate_id,self::META_ENTITY_HISTORY,true);$history=is_array($history)?$history:array();$history[]=array('schema'=>self::RESOLUTION_SCHEMA,'decision'=>'accepted','canonical_id'=>$canonical_id,'reviewer_user_id'=>absint($user_id),'note'=>self::clean($note,1000),'created_at'=>self::now(),'candidate_before'=>$before_candidate,'canonical_before'=>$before_canonical,'record_deleted'=>false,'assignments_rewritten'=>false);update_post_meta($candidate_id,self::META_ENTITY_HISTORY,array_slice($history,-self::MAX_HISTORY));
            $payload['resolved_canonical_id']=$canonical_id;$payload['canonical_aliases_after']=$aliases;
        } else {
            $history=get_post_meta($candidate_id,self::META_ENTITY_HISTORY,true);$history=is_array($history)?$history:array();$history[]=array('schema'=>self::RESOLUTION_SCHEMA,'decision'=>'rejected','canonical_id'=>$canonical_id,'reviewer_user_id'=>absint($user_id),'note'=>self::clean($note,1000),'created_at'=>self::now());update_post_meta($candidate_id,self::META_ENTITY_HISTORY,array_slice($history,-self::MAX_HISTORY));$payload['resolved_canonical_id']=self::resolve_entity_id($candidate_id);
        }
        $event=self::review_event($user_id,'entity_resolution',$payload);return is_wp_error($event)?$event:array('ok'=>true,'decision'=>$decision,'candidate_id'=>$candidate_id,'canonical_id'=>$canonical_id,'resolved_canonical_id'=>self::resolve_entity_id($candidate_id),'review'=>$event,'destructive_merge'=>false,'assignments_rewritten'=>false);
    }

    public static function state(){
        $source_count=wp_count_posts(self::source_post_type());$entity_count=wp_count_posts(self::entity_post_type());
        return array('schema'=>self::SCHEMA,'version'=>self::VERSION,'contract'=>self::contract(),'counts'=>array('research_sources'=>isset($source_count->publish)?absint($source_count->publish):0,'named_entities'=>isset($entity_count->publish)?absint($entity_count->publish):0),'review_capable'=>self::editor_can_review());
    }

    public function register_rest_routes(){
        register_rest_route(self::REST_NAMESPACE,self::REST_ROUTE,array('methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>array($this,'rest_state')));
        register_rest_route(self::REST_NAMESPACE,self::REST_ROUTE.'/sources/(?P<id>\\d+)',array('methods'=>WP_REST_Server::READABLE,'permission_callback'=>array($this,'rest_can_review'),'callback'=>array($this,'rest_source')));
        register_rest_route(self::REST_NAMESPACE,self::REST_ROUTE.'/entities/(?P<id>\\d+)',array('methods'=>WP_REST_Server::READABLE,'permission_callback'=>array($this,'rest_can_review'),'callback'=>array($this,'rest_entity')));
        register_rest_route(self::REST_NAMESPACE,self::REST_ROUTE.'/entities/(?P<id>\\d+)/resolve',array('methods'=>WP_REST_Server::CREATABLE,'permission_callback'=>array($this,'rest_can_review'),'callback'=>array($this,'rest_resolve')));
        register_rest_route(self::REST_NAMESPACE,self::REST_ROUTE.'/reviews',array('methods'=>WP_REST_Server::READABLE,'permission_callback'=>array($this,'rest_can_review'),'callback'=>array($this,'rest_reviews')));
    }
    public function rest_can_review(){return self::editor_can_review();}
    public function rest_state(){return rest_ensure_response(self::state());}
    private static function rest_result($value){return is_wp_error($value)?$value:rest_ensure_response($value);}
    public function rest_source($r){return self::rest_result(self::source_quality_report(absint($r['id'])));}
    public function rest_entity($r){return self::rest_result(self::entity_quality_report(absint($r['id'])));}
    public function rest_resolve($r){$p=$r->get_json_params();$p=is_array($p)?$p:array();return self::rest_result(self::decide_entity_resolution(get_current_user_id(),absint($r['id']),absint($p['canonical_id']??0),$p['decision']??'', $p['note']??''));}
    public function rest_reviews(){
        $ids=get_posts(array('post_type'=>self::POST_TYPE,'post_status'=>'private','author'=>get_current_user_id(),'posts_per_page'=>50,'orderby'=>'date','order'=>'DESC','fields'=>'ids'));$out=array();foreach($ids as $id){$e=get_post_meta($id,self::META_EVENT,true);if(is_array($e))$out[]=array('review_id'=>absint($id))+$e;}return rest_ensure_response(array('schema'=>self::REVIEW_SCHEMA,'reviews'=>$out));
    }

    public function shortcode($atts){
        $atts=shortcode_atts(array('title'=>__('Metadata Quality & Entity Resolution','sustainable-catalyst-library')),$atts,'sc_metadata_quality_center');wp_enqueue_style('sc-library-metadata-quality-v4334');wp_enqueue_script('sc-library-metadata-quality-v4334');
        wp_localize_script('sc-library-metadata-quality-v4334','SC_LIBRARY_METADATA_QUALITY',array('rest'=>esc_url_raw(rest_url(self::REST_NAMESPACE.self::REST_ROUTE)),'nonce'=>wp_create_nonce('wp_rest'),'canReview'=>self::editor_can_review()));
        ob_start();?><section class="sc-metadata-quality" data-sc-metadata-quality="v4.3.34"><header><p class="sc-metadata-quality__kicker"><?php esc_html_e('Metadata governance','sustainable-catalyst-library');?></p><h3><?php echo esc_html($atts['title']);?></h3><p><?php esc_html_e('Inspect metadata completeness, normalized identifiers, and reviewable duplicate/entity candidates. Diagnostic scores help locate gaps; they do not establish truth, authority, or identity on their own.','sustainable-catalyst-library');?></p></header><div class="sc-metadata-quality__principles"><span>Deterministic normalization</span><span>Explicit human resolution</span><span>Provenance preserved</span><span>No silent merges</span></div><?php if(!self::editor_can_review()):?><p class="sc-metadata-quality__notice"><?php esc_html_e('Public metadata standards are visible here. Sign in with editorial permissions to inspect individual records and review entity-resolution proposals.','sustainable-catalyst-library');?></p><?php else:?><div class="sc-metadata-quality__tools"><form data-sc-metadata-inspect><label><span><?php esc_html_e('Record family','sustainable-catalyst-library');?></span><select name="kind"><option value="sources">Research Source</option><option value="entities">Named Entity</option></select></label><label><span><?php esc_html_e('Record ID','sustainable-catalyst-library');?></span><input name="id" type="number" min="1" required></label><button type="submit"><?php esc_html_e('Inspect metadata','sustainable-catalyst-library');?></button></form><div class="sc-metadata-quality__result" data-sc-metadata-result aria-live="polite"><p><?php esc_html_e('Enter a Research Source or Named Entity ID to inspect deterministic metadata quality and candidate relationships.','sustainable-catalyst-library');?></p></div></div><?php endif;?></section><?php return ob_get_clean();
    }
}
