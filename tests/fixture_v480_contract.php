<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){ return $s; }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-global-research-federation.php';
$c = SC_Library_Global_Research_Federation::contract();
$p = SC_Library_Global_Research_Federation::compatibility_profile();
$checks = [
    SC_Library_Global_Research_Federation::VERSION === '4.8.0',
    $c['legacy_v390_federation_reused'] === true,
    $c['team_library_authority_reused'] === true,
    $c['creates_parallel_peer_registry'] === false,
    $c['creates_parallel_import_quarantine'] === false,
    $c['creates_parallel_institution_registry'] === false,
    $c['public_exchange_metadata_only'] === true,
    $c['explicit_team_governor_publish_required'] === true,
    $c['explicit_remote_import_review_required'] === true,
    $c['explicit_team_acceptance_required'] === true,
    $c['approved_metadata_does_not_auto_import'] === true,
    $c['peer_trust_is_transport_governance_not_truth'] === true,
    $c['institutional_context_is_not_entitlement'] === true,
    $c['references_only'] === true,
    $c['credentials_exported'] === false,
    $c['automatic_workspace_write'] === false,
    $c['truth_scoring'] === false,
    $p['quarantine_required'] === true,
    $p['automatic_import_allowed'] === false,
    $p['exchange_mode'] === 'references-only-metadata',
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "v4.8.0 contract fixture failed\n"); exit(1); }
echo "PASS - v4.8.0 global research federation contract fixture\n";
