<?php
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){ return $s; }
function sanitize_key($s){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$s)); }
function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-research-collections-curated-spaces.php';
$c=SC_Library_Research_Collections_Curated_Spaces::contract();
if (($c['references_only']??false)!==true || ($c['private_projects_exposed']??true)!==false || ($c['automatic_publication']??true)!==false) { fwrite(STDERR,"FAIL - curation boundary\n"); exit(1); }
$k=SC_Library_Research_Collections_Curated_Spaces::kind_registry();
foreach(['research-collection','exhibition','knowledge-space'] as $x){ if(!isset($k[$x])){fwrite(STDERR,"FAIL - kind registry\n");exit(1);} }
$r=SC_Library_Research_Collections_Curated_Spaces::reference_registry();
foreach(['public-claim','public-evidence','federation-manifest'] as $x){ if(!isset($r[$x])){fwrite(STDERR,"FAIL - reference registry\n");exit(1);} }
$a=SC_Library_Research_Collections_Curated_Spaces::manifest_sha256(['b'=>2,'a'=>1]);
$b=SC_Library_Research_Collections_Curated_Spaces::manifest_sha256(['a'=>1,'b'=>2,'generated_at'=>'different']);
if($a!==$b || strlen($a)!==64){fwrite(STDERR,"FAIL - deterministic manifest\n");exit(1);}
echo "PASS - v5.4.0 curated spaces contract fixture\n";
