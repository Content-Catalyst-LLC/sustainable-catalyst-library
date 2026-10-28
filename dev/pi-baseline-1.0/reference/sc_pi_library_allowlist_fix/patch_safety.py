#!/usr/bin/env python3
"""Preflight-checked, minimal development safety-policy addition."""
from pathlib import Path
import sys
src = Path(sys.argv[1]); dst = Path(sys.argv[2])
s = src.read_text()
checks = [
    '// Prevent external HTTP requests.',
    'add_filter("pre_http_request", function ($preempt, $args, $url) {',
    '    $host = parse_url($url, PHP_URL_HOST);',
    'function sc_development_api_url($host, $url) {',
    '"catalyst_dev_http_blocked"',
    "'http_request_host_is_external'",
    "'http_allowed_safe_ports'",
    "'catalyst-workbench-repair-7y'=>8088",
]
for part in checks:
    if part not in s:
        raise SystemExit('STOP: Unexpected safety policy; no modifications made. Missing: ' + part)
if 'function sc_pi_baseline_library_dev_url_allowed' in s:
    raise SystemExit('STOP: The Library policy seems already patched. No modifications made.')
if s.count('// Prevent external HTTP requests.') != 1 or s.count('    $host = parse_url($url, PHP_URL_HOST);') != 1 or s.count('function sc_development_api_url($host, $url) {') != 1:
    raise SystemExit('STOP: Ambiguous source anchors. No modifications made.')
helper = '''// Pi Baseline 1.0: narrowly scoped, dev-only, read-only Knowledge Library routes.
// Keep the global outbound-HTTP default-deny policy unchanged.
function sc_pi_baseline_library_dev_url_allowed($url) {
    $parts = parse_url($url);
    if (!is_array($parts)
        || ($parts['scheme'] ?? '') !== 'http'
        || ($parts['host'] ?? '') !== 'sc-library-pi-adapter'
        || ($parts['port'] ?? 0) !== 8097) {
        return false;
    }
    $path = $parts['path'] ?? '';
    $query = $parts['query'] ?? '';
    if ($path === '/health') { return $query === ''; }
    return $path === '/search'
        && is_string($query)
        && strlen($query) <= 500
        && preg_match('/\\Aq=[^&]{1,498}\\z/D', $query) === 1;
}

'''
s = s.replace('// Prevent external HTTP requests.', helper + '// Prevent external HTTP requests.', 1)
needle = '    $host = parse_url($url, PHP_URL_HOST);'
addition = '''    // Permit only the Pi Library adapter's two read endpoints; forbid writes.
    if ($host === 'sc-library-pi-adapter') {
        if (strtoupper((string) ($args['method'] ?? 'GET')) === 'GET'
            && sc_pi_baseline_library_dev_url_allowed($url)) {
            return $preempt;
        }
        return new WP_Error(
            "catalyst_dev_http_blocked",
            "External HTTP requests are disabled in the development environment."
        );
    }
'''
s = s.replace(needle, needle + '\n' + addition.rstrip('\n'), 1)
needle2 = 'function sc_development_api_url($host, $url) {'
addition2 = '''
    if ($host === 'sc-library-pi-adapter') {
        return sc_pi_baseline_library_dev_url_allowed($url);
    }'''
s = s.replace(needle2, needle2 + addition2, 1)
if s.count('function sc_pi_baseline_library_dev_url_allowed') != 1 or s.count('sc_pi_baseline_library_dev_url_allowed($url)') != 3:
    raise SystemExit('STOP: Internal patch invariant failed')
dst.write_text(s)
print('PASS: Surgical policy patch generated (no original rules removed).')
