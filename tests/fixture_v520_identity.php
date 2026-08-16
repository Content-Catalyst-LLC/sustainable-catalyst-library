<?php
define('ABSPATH', __DIR__ . '/');
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-research-identity-authority-network.php';
$c = SC_Library_Research_Identity_Authority_Network::contract();
if (($c['identifier_validation_mode'] ?? '') !== 'local-syntax-and-checksum') { fwrite(STDERR,"mode\n"); exit(1); }
if (!empty($c['automatic_entity_merge']) || !empty($c['automatic_record_merge']) || !empty($c['external_registry_verification_performed']) || empty($c['ambiguity_preserved'])) { fwrite(STDERR,"boundary\n"); exit(1); }
$doi=SC_Library_Research_Identity_Authority_Network::normalize_identifier('doi','https://doi.org/10.1000/XYZ123');
$orcid=SC_Library_Research_Identity_Authority_Network::normalize_identifier('orcid','https://orcid.org/0000-0002-1825-0097');
$ror=SC_Library_Research_Identity_Authority_Network::normalize_identifier('ror','https://ror.org/03yrm5c26');
$wikidata=SC_Library_Research_Identity_Authority_Network::normalize_identifier('wikidata','https://www.wikidata.org/wiki/Q42');
if ($doi!=='10.1000/xyz123' || $orcid!=='0000-0002-1825-0097' || $ror!=='03yrm5c26' || $wikidata!=='Q42') { fwrite(STDERR,"normalize\n"); exit(1); }
foreach ([['doi',$doi],['orcid',$orcid],['ror',$ror],['isbn','9780306406157'],['issn','0378-5955'],['wikidata','Q42'],['pmid','12345678']] as $pair) {
  if (!SC_Library_Research_Identity_Authority_Network::valid_identifier($pair[0],$pair[1])) { fwrite(STDERR,"valid {$pair[0]}\n"); exit(1); }
}
if (SC_Library_Research_Identity_Authority_Network::valid_identifier('orcid','0000-0000-0000-0000')) { fwrite(STDERR,"invalid-orcid\n"); exit(1); }
echo "PASS - v5.2.0 identity authority contract fixture\n";
