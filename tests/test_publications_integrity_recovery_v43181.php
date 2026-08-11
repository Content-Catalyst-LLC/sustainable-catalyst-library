<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_DIR', dirname(__DIR__) . '/sustainable-catalyst-library/');
define('SC_LIBRARY_VERSION', '4.3.18.1');

$GLOBALS['opts'] = array();
$GLOBALS['deleted_transients'] = array();
function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k,$v,$autoload=null){ $GLOBALS['opts'][$k]=$v; return true; }
function delete_transient($k){ $GLOBALS['deleted_transients'][]=$k; return true; }
function sanitize_title($v){ $v=strtolower(trim((string)$v)); $v=preg_replace('/[^a-z0-9]+/','-',$v); return trim($v,'-'); }

$registry = include SC_LIBRARY_DIR . 'includes/data/publications-article-map-registry-v431.php';
$field_titles = array();
$global_panels = array();
foreach ($registry as $key=>$map) {
    $fk=sanitize_title($map['field'] ?? '');
    if ($fk) $field_titles[$fk]=true;
    if (($map['field'] ?? '') === 'Global Governance') $global_panels[] = sanitize_title($key);
}

$fields=array();
$i=0;
foreach (array_keys($field_titles) as $fk) {
    $fields[$fk]=array('title'=>'KEEP '.$fk,'visible'=>$i===0?1:0,'order'=>$i+1);
    $i++;
}
$GLOBALS['opts']['sc_library_publications_settings_v433']=array(
    'general'=>array('title'=>'Publications'),
    'fields'=>$fields,
    'maps'=>array(),
);

$panels=array();
foreach ($global_panels as $i=>$pk) {
    $panels[$pk]=array(
        'title'=>'KEEP '.$pk,
        'visible'=>$i===0?1:0,
        'order'=>$i+1,
        'hero_title'=>'KEEP HERO '.$pk,
        'articles'=>array(array('title'=>'KEEP ARTICLE','url'=>'https://example.com/article')),
    );
}
$GLOBALS['opts']['sc_library_field_spotlights_settings_v434']=array(
    'general'=>array('panel_limit'=>8),
    'fields'=>array(),
    'panels'=>$panels,
);

require_once SC_LIBRARY_DIR . 'includes/class-sc-library-activator.php';
$method=new ReflectionMethod(SC_Library_Activator::class,'repair_publication_surface_integrity');
$method->setAccessible(true);
$method->invoke(null);

$pub=$GLOBALS['opts']['sc_library_publications_settings_v433'];
foreach ($pub['fields'] as $fk=>$cfg) {
    if (empty($cfg['visible'])) { fwrite(STDERR,"publication field still hidden: $fk\n"); exit(1); }
    if (($cfg['title'] ?? '') !== 'KEEP '.$fk) { fwrite(STDERR,"publication title changed\n"); exit(1); }
}
$fs=$GLOBALS['opts']['sc_library_field_spotlights_settings_v434'];
foreach ($global_panels as $pk) {
    if (empty($fs['panels'][$pk]['visible'])) { fwrite(STDERR,"field panel still hidden: $pk\n"); exit(1); }
    if (($fs['panels'][$pk]['hero_title'] ?? '') !== 'KEEP HERO '.$pk) { fwrite(STDERR,"hero content changed\n"); exit(1); }
    if (($fs['panels'][$pk]['articles'][0]['title'] ?? '') !== 'KEEP ARTICLE') { fwrite(STDERR,"article content changed\n"); exit(1); }
}
$diag=$GLOBALS['opts']['sc_library_publications_integrity_repair_v43181'] ?? array();
if (empty($diag['publications_repaired']) || empty($diag['field_spotlights_repaired'])) { fwrite(STDERR,"repair diagnostics not set\n"); exit(1); }
foreach (array('sc_library_publications_topics_v433','sc_library_field_spotlights_model_v4313','sc_library_field_spotlights_public_v4313') as $key) {
    if (!in_array($key,$GLOBALS['deleted_transients'],true)) { fwrite(STDERR,"cache not cleared: $key\n"); exit(1); }
}
echo "PASS: collapsed Publications and Field Spotlight visibility is restored without changing editorial content.\n";
