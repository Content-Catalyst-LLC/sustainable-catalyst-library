<?php
/**
 * Plugin Name: SC Pi Library Development Bridge
 * Description: Pi-only thin adapter for the isolated Knowledge Library development backend.
 */
if (!defined('ABSPATH')) { exit; }

function sc_pi_library_fetch($path, $q = null) {
    $url = 'http://sc-library-pi-adapter:8097/' . $path;
    if ($q !== null) { $url .= '?q=' . rawurlencode($q); }
    $response = wp_remote_get($url, array(
        'timeout' => 12, 'redirection' => 0,
        'headers' => array('Accept' => 'application/json'),
    ));
    if (is_wp_error($response)) {
        return new WP_Error('sc_pi_library_unavailable', 'Development Library adapter unreachable', array('status' => 503));
    }
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($status !== 200 || strlen($body) > 1048576) {
        return new WP_Error('sc_pi_library_bad_status', 'Development Library adapter not ready', array('status' => 503));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return new WP_Error('sc_pi_library_invalid', 'Development Library returned invalid JSON', array('status' => 502));
    }
    return rest_ensure_response($decoded);
}

add_action('rest_api_init', function () {
    register_rest_route('sc-dev/v1', '/library/status', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $raw = sc_pi_library_fetch('health');
            if (is_wp_error($raw)) { return $raw; }
            $health = $raw->get_data();
            return rest_ensure_response(array(
                'ok' => !empty($health['ok']),
                'product' => 'Knowledge Library (Pi development)',
                'version' => sanitize_text_field($health['version'] ?? 'unknown'),
                'database' => sanitize_text_field($health['database'] ?? 'unknown'),
                'role' => 'read-only-thin-adapter',
                'authoritative' => false,
            ));
        },
    ));
    register_rest_route('sc-dev/v1', '/library/search', array(
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'args' => array('q' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field')),
        'callback' => function ($request) {
            $q = trim((string) $request->get_param('q'));
            if ($q === '' || strlen($q) > 160) {
                return new WP_Error('sc_pi_library_query', 'Search must contain 1–160 characters', array('status' => 400));
            }
            return sc_pi_library_fetch('search', $q);
        },
    ));
});

add_action('admin_menu', function () {
    add_management_page('Pi Knowledge Library', 'Pi Knowledge Library', 'manage_options', 'sc-pi-library', function () {
        if (!current_user_can('manage_options')) { return; }
        $status_url = rest_url('sc-dev/v1/library/status');
        $search_url = rest_url('sc-dev/v1/library/search');
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="wrap"><h1>Pi Knowledge Library — Development Only</h1>';
        echo '<p>Read-only bridge into a separate local PostgreSQL Library. No production data is imported.</p>';
        echo '<p><strong>Status:</strong> <span id="sc-pi-lib-status">Checking…</span></p>';
        echo '<label for="sc-pi-lib-q">Find sources</label> <input type="search" id="sc-pi-lib-q" value="carbon" /> ';
        echo '<button type="button" class="button button-primary" id="sc-pi-lib-go">Search</button>';
        echo '<pre id="sc-pi-lib-output" style="white-space:pre-wrap;max-width:850px;max-height:500px;overflow:auto;background:#fff;padding:12px"></pre></div>';
        $cfg = wp_json_encode(array('status' => $status_url, 'search' => $search_url, 'nonce' => $nonce));
        echo '<script>(function(){const cfg=' . $cfg . ';';
        echo 'const status=document.getElementById("sc-pi-lib-status"),out=document.getElementById("sc-pi-lib-output");';
        echo 'async function call(u){const r=await fetch(u,{credentials:"same-origin",headers:{"X-WP-Nonce":cfg.nonce}});const d=await r.json();if(!r.ok)throw Error(d.message||("HTTP "+r.status));return d;}';
        echo 'call(cfg.status).then(d=>status.textContent=d.ok?"Connected ("+d.version+")":"Unavailable").catch(e=>status.textContent=e.message);';
        echo 'document.getElementById("sc-pi-lib-go").onclick=async()=>{out.textContent="Searching…";try{out.textContent=JSON.stringify(await call(cfg.search+"?q="+encodeURIComponent(document.getElementById("sc-pi-lib-q").value)),null,2);}catch(e){out.textContent=e.message;}};';
        echo '})();</script>';
    });
});
