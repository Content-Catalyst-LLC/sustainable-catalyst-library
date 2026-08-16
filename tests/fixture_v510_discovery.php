<?php
define('ABSPATH', __DIR__ . '/');
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-global-research-discovery-federated-search.php';
$c = SC_Library_Global_Research_Discovery_Federated_Search::contract();
if (($c['ranking_mode'] ?? '') !== 'deterministic-lexical') { fwrite(STDERR,"ranking\n"); exit(1); }
if (!empty($c['remote_network_calls_during_search']) || !empty($c['private_projects_searched']) || !empty($c['truth_scoring'])) { fwrite(STDERR,"boundary\n"); exit(1); }
$exact = SC_Library_Global_Research_Discovery_Federated_Search::lexical_score('climate systems', ['title'=>'Climate Systems','summary'=>'','canonical_id'=>'','type'=>'publication']);
$partial = SC_Library_Global_Research_Discovery_Federated_Search::lexical_score('climate systems', ['title'=>'A guide to climate systems','summary'=>'','canonical_id'=>'','type'=>'publication']);
$miss = SC_Library_Global_Research_Discovery_Federated_Search::lexical_score('climate systems', ['title'=>'Ancient literature','summary'=>'Myth and memory','canonical_id'=>'','type'=>'publication']);
if (!($exact > $partial && $partial > $miss && $miss === 0)) { fwrite(STDERR,"score\n"); exit(1); }
echo "PASS - v5.1.0 discovery contract fixture\n";
