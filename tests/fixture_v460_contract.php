<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=null){ return $s; }
require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-collaborative-research-rooms.php';
$c = SC_Library_Collaborative_Research_Rooms::contract();
$r = SC_Library_Collaborative_Research_Rooms::roles();
$checks = [
    $c['project_ownership_transferred'] === false,
    $c['room_membership_grants_project_access'] === false,
    $c['explicit_share_required'] === true,
    $c['references_only'] === true,
    $c['copy_private_source_binaries'] === false,
    $c['automatic_publication'] === false,
    $c['automatic_workspace_write'] === false,
    $r['owner']['manage_members'] === true,
    $r['editor']['share'] === true,
    $r['reviewer']['note'] === true,
    $r['reviewer']['decide'] === false,
    $r['observer']['note'] === false,
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "v4.6.0 contract fixture failed\n"); exit(1); }
echo "PASS - v4.6.0 collaboration contract fixture\n";
