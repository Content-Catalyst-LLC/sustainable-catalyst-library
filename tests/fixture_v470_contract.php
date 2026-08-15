<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){ return $s; }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-institutional-team-libraries.php';
$c = SC_Library_Institutional_Team_Libraries::contract();
$r = SC_Library_Institutional_Team_Libraries::roles();
$checks = [
    $c['canonical_institution_registry_reused'] === true,
    $c['canonical_unit_registry_reused'] === true,
    $c['creates_parallel_institution_registry'] === false,
    $c['institutional_binding_is_context_not_entitlement'] === true,
    $c['membership_grants_personal_library_access'] === false,
    $c['membership_grants_project_access'] === false,
    $c['membership_grants_research_room_access'] === false,
    $c['explicit_contribution_required'] === true,
    $c['references_only'] === true,
    $c['copy_private_source_binaries'] === false,
    $c['automatic_publication'] === false,
    $r['owner']['manage_members'] === true,
    $r['steward']['manage_members'] === true,
    $r['editor']['manage_collections'] === true,
    $r['contributor']['contribute'] === true,
    $r['reader']['contribute'] === false,
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "v4.7.0 contract fixture failed\n"); exit(1); }
echo "PASS - v4.7.0 institutional/team library contract fixture\n";
