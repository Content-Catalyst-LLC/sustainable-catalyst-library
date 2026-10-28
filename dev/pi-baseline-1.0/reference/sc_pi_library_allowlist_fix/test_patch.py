from pathlib import Path
from tempfile import TemporaryDirectory
import subprocess,sys
fixture='''<?php
add_filter("pre_http_request", function ($preempt, $args, $url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (in_array($host, ["localhost", "127.0.0.1", "db"], true)) { return $preempt; }
    $local = ['sc-library-dev-api'=>8080, 'catalyst-workbench-repair-7y'=>8088];
    if (isset($local[$host]) && parse_url($url, PHP_URL_SCHEME) === 'http' && parse_url($url, PHP_URL_PORT) === $local[$host]) { return $preempt; }
    return new WP_Error("catalyst_dev_http_blocked", "External HTTP requests are disabled in the development environment.");
}, 10, 3);
// Prevent external HTTP requests.
function sc_development_api_url($host, $url) {
    $local = ['sc-library-dev-api'=>8080, 'catalyst-workbench-repair-7y'=>8088];
    return isset($local[$host]) && parse_url($url, PHP_URL_SCHEME) === 'http' && parse_url($url, PHP_URL_PORT) === $local[$host];
}
add_filter('http_request_host_is_external', function($external,$host,$url) { return sc_development_api_url($host,$url) ? true : $external; },10,3);
add_filter('http_allowed_safe_ports', function($ports,$host,$url) { return sc_development_api_url($host,$url) ? array_merge($ports,[parse_url($url,PHP_URL_PORT)]) : $ports; },10,3);
'''
with TemporaryDirectory() as d:
    p=Path(d); (p/'original.php').write_text(fixture)
    subprocess.run([sys.executable,str(Path(__file__).parent/'patch_safety.py'),str(p/'original.php'),str(p/'patched.php')],check=True)
    subprocess.run(['php','-l',str(p/'patched.php')],check=True)
    assert 'sc_pi_baseline_library_dev_url_allowed' in (p/'patched.php').read_text()
    assert (p/'patched.php').read_text().count('catalyst-workbench-repair-7y') == 2
    print('PASS: source anchors, changed scope and PHP syntax')
