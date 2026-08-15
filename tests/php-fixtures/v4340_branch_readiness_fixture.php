<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_VERSION', '4.3.40');
define('SC_LIBRARY_DIR', dirname(__DIR__, 2) . '/sustainable-catalyst-library/');
class SC_Library_Indexer {}
class SC_Library_Relationships {}
class Fake_REST_Server_V4340 {
    public array $routes;
    public function __construct(array $routes) { $this->routes = $routes; }
    public function get_routes(): array { return $this->routes; }
}
$GLOBALS['sc_v4340_routes'] = [];
function rest_get_server() { return new Fake_REST_Server_V4340($GLOBALS['sc_v4340_routes']); }
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-hardening.php';
$hard = new SC_Library_Hardening(new SC_Library_Indexer(), new SC_Library_Relationships());
$public = new ReflectionMethod(SC_Library_Hardening::class, 'public_report');
$public->setAccessible(true);
$report = [
    'generated_at' => '2026-08-15 06:00:00',
    'overall_status' => 'review_recommended',
    'score' => 92,
    'counts' => ['ready'=>10,'warning'=>2,'fail'=>0,'info'=>1],
    'categories' => [
        'integrity' => ['label'=>'Integrity','checks'=>[['status'=>'warning']]],
        'branch_43' => ['label'=>'4.3 branch release certification','checks'=>array_fill(0, 10, ['status'=>'ready'])],
    ],
];
$out = $public->invoke($hard, $report);
if (($out['overall_status'] ?? '') !== 'review_recommended' || ($out['branch_release_gate']['status'] ?? '') !== 'ready') { throw new RuntimeException('Branch gate was not independent from operational warnings.'); }
if (($out['branch_release_gate']['network_calls_performed'] ?? true) !== false || ($out['branch_release_gate']['upstream_health_release_blocking'] ?? true) !== false || ($out['branch_release_gate']['private_record_content_inspected'] ?? true) !== false) { throw new RuntimeException('Truthful branch-boundary flags missing.'); }
$report['categories']['branch_43']['checks'][] = ['status'=>'fail'];
$out = $public->invoke($hard, $report);
if (($out['branch_release_gate']['status'] ?? '') !== 'blocked') { throw new RuntimeException('Branch failure did not block branch gate.'); }
$private = new ReflectionMethod(SC_Library_Hardening::class, 'private_v43_routes_require_permission');
$private->setAccessible(true);
$routes = [
    '/sc-library/v1/personal-library', '/sc-library/v1/research-continuity', '/sc-library/v1/research-projects', '/sc-library/v1/reading-notebooks', '/sc-library/v1/evidence-matrices', '/sc-library/v1/workspace-continuity', '/sc-library/v1/research-librarian-v2/catalog', '/sc-library/v1/research-portability/catalog'
];
foreach ($routes as $route) { $GLOBALS['sc_v4340_routes'][$route] = [['permission_callback'=>['Private_Guard','check']]]; }
if ($private->invoke($hard) !== true) { throw new RuntimeException('Authenticated private route set failed certification.'); }
$GLOBALS['sc_v4340_routes']['/sc-library/v1/reading-notebooks'][0]['permission_callback'] = '__return_true';
if ($private->invoke($hard) !== false) { throw new RuntimeException('Public private-route callback did not block certification.'); }
echo "PASS - v4.3.40 branch readiness fixture\n";
