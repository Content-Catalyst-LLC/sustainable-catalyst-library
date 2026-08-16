<?php
define('ABSPATH', __DIR__ . '/');
function sanitize_key($value){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$value)); }
require_once dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-public-evidence-claim-navigation.php';

$contract = SC_Library_Public_Evidence_Claim_Navigation::contract();
if (empty($contract['canonical_evidence_claim_store_reused']) || empty($contract['publication_research_graph_reused'])) { fwrite(STDERR,"canonical reuse contract failed\n"); exit(1); }
if (empty($contract['public_claims_only']) || empty($contract['public_evidence_notes_only'])) { fwrite(STDERR,"public-only contract failed\n"); exit(1); }
if (!empty($contract['truth_scoring']) || !empty($contract['automatic_evidence_promotion']) || !empty($contract['automatic_claim_status_change'])) { fwrite(STDERR,"mutation/truth boundary failed\n"); exit(1); }
$registry = SC_Library_Public_Evidence_Claim_Navigation::relation_registry();
foreach (array('supports','qualifies','contradicts','contextualizes','illustrates','unresolved') as $relation) {
    if (!isset($registry[$relation])) { fwrite(STDERR,"missing relation: $relation\n"); exit(1); }
}
$totals = SC_Library_Public_Evidence_Claim_Navigation::relation_totals(array(
    array('relation'=>'supports'), array('relation'=>'supports'), array('relation'=>'contradicts'), array('relation'=>'bogus'),
));
if (2 !== $totals['supports'] || 1 !== $totals['contradicts'] || 1 !== $totals['unresolved']) { fwrite(STDERR,"relation totals failed\n"); exit(1); }
echo "PASS - v5.3.0 public evidence contract fixture\n";
