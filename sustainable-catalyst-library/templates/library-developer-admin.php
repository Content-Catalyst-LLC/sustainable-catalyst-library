<div class="wrap sc-library-developer-admin">
    <h1><?php esc_html_e('Public API, Webhooks, and Developer Documentation', 'sustainable-catalyst-library'); ?></h1>
    <p class="description"><?php esc_html_e('Manage the versioned public API, scoped service keys, signed webhook destinations, delivery history, and public developer portal.', 'sustainable-catalyst-library'); ?></p>

    <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(ucwords(str_replace('-', ' ', $notice))); ?></p></div><?php endif; ?>

    <?php if ($new_key): ?>
        <div class="notice notice-warning sc-developer-secret"><h2><?php esc_html_e('Copy this API key now', 'sustainable-catalyst-library'); ?></h2><p><?php esc_html_e('The plaintext key will not be shown again.', 'sustainable-catalyst-library'); ?></p><code data-copy-value><?php echo esc_html($new_key['key']); ?></code><button type="button" class="button" data-copy-button><?php esc_html_e('Copy key', 'sustainable-catalyst-library'); ?></button></div>
    <?php endif; ?>
    <?php if ($new_webhook_secret): ?>
        <div class="notice notice-warning sc-developer-secret"><h2><?php esc_html_e('Copy this webhook signing secret now', 'sustainable-catalyst-library'); ?></h2><p><?php esc_html_e('Use it to verify X-SC-Signature. It will not be shown again.', 'sustainable-catalyst-library'); ?></p><code data-copy-value><?php echo esc_html($new_webhook_secret['secret']); ?></code><button type="button" class="button" data-copy-button><?php esc_html_e('Copy secret', 'sustainable-catalyst-library'); ?></button></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Developer API sections', 'sustainable-catalyst-library'); ?>">
        <a href="#sc-api-settings" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'sustainable-catalyst-library'); ?></a>
        <a href="#sc-api-keys" class="nav-tab"><?php esc_html_e('API Keys', 'sustainable-catalyst-library'); ?></a>
        <a href="#sc-webhooks" class="nav-tab"><?php esc_html_e('Webhooks', 'sustainable-catalyst-library'); ?></a>
        <a href="#sc-deliveries" class="nav-tab"><?php esc_html_e('Deliveries', 'sustainable-catalyst-library'); ?></a>
        <a href="#sc-developer-links" class="nav-tab"><?php esc_html_e('Documentation', 'sustainable-catalyst-library'); ?></a>
    </nav>

    <section id="sc-api-settings" class="sc-developer-panel">
        <h2><?php esc_html_e('API settings', 'sustainable-catalyst-library'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('sc_library_developer_api_settings'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><?php esc_html_e('Enable developer API', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_developer_api" value="1" <?php checked(get_option('sc_library_enable_developer_api', 1)); ?>> <?php esc_html_e('Serve public and protected v1 routes', 'sustainable-catalyst-library'); ?></label></td></tr>
                <tr><th scope="row"><label for="sc-api-public-rate"><?php esc_html_e('Public requests per hour', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-api-public-rate" type="number" min="30" max="5000" name="sc_library_api_public_rate_limit" value="<?php echo esc_attr((int) get_option('sc_library_api_public_rate_limit', 300)); ?>"></td></tr>
                <tr><th scope="row"><label for="sc-api-key-rate"><?php esc_html_e('Default keyed requests per hour', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-api-key-rate" type="number" min="60" max="10000" name="sc_library_api_key_rate_limit" value="<?php echo esc_attr((int) get_option('sc_library_api_key_rate_limit', 1000)); ?>"></td></tr>
                <tr><th scope="row"><label for="sc-api-origins"><?php esc_html_e('Allowed browser origins', 'sustainable-catalyst-library'); ?></label></th><td><textarea id="sc-api-origins" name="sc_library_api_allowed_origins" rows="4" class="large-text code"><?php echo esc_textarea((string) get_option('sc_library_api_allowed_origins', '')); ?></textarea><p class="description"><?php esc_html_e('One exact HTTPS origin per line. Leave blank to disable cross-origin browser access.', 'sustainable-catalyst-library'); ?></p></td></tr>
                <tr><th scope="row"><label for="sc-developer-url"><?php esc_html_e('Developer portal URL', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-developer-url" type="url" class="regular-text" name="sc_library_developer_portal_url" value="<?php echo esc_attr((string) get_option('sc_library_developer_portal_url', home_url('/developers/'))); ?>"></td></tr>
                <tr><th scope="row"><label for="sc-webhook-attempts"><?php esc_html_e('Webhook attempts', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-webhook-attempts" type="number" min="1" max="8" name="sc_library_webhook_max_attempts" value="<?php echo esc_attr((int) get_option('sc_library_webhook_max_attempts', 4)); ?>"></td></tr>
                <tr><th scope="row"><label for="sc-webhook-timeout"><?php esc_html_e('Webhook timeout', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-webhook-timeout" type="number" min="3" max="30" name="sc_library_webhook_timeout" value="<?php echo esc_attr((int) get_option('sc_library_webhook_timeout', 10)); ?>"> <?php esc_html_e('seconds', 'sustainable-catalyst-library'); ?></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </section>

    <section id="sc-api-keys" class="sc-developer-panel">
        <h2><?php esc_html_e('Scoped API keys', 'sustainable-catalyst-library'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-developer-create-form">
            <input type="hidden" name="action" value="sc_library_api_key_create"><?php wp_nonce_field('sc_library_api_key_create'); ?>
            <label><span><?php esc_html_e('Key name', 'sustainable-catalyst-library'); ?></span><input type="text" name="name" required maxlength="191"></label>
            <label><span><?php esc_html_e('Mode', 'sustainable-catalyst-library'); ?></span><select name="mode"><option value="live">Live</option><option value="test">Test</option></select></label>
            <label><span><?php esc_html_e('Requests/hour', 'sustainable-catalyst-library'); ?></span><input type="number" name="rate_limit" min="60" max="10000" value="<?php echo esc_attr((int) get_option('sc_library_api_key_rate_limit', 1000)); ?>"></label>
            <label><span><?php esc_html_e('Expires', 'sustainable-catalyst-library'); ?></span><input type="date" name="expires_at"></label>
            <fieldset><legend><?php esc_html_e('Scopes', 'sustainable-catalyst-library'); ?></legend><?php foreach (SC_Library_Developer_API::scopes() as $scope => $label): ?><label class="sc-developer-check"><input type="checkbox" name="scopes[]" value="<?php echo esc_attr($scope); ?>"> <code><?php echo esc_html($scope); ?></code> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
            <button type="submit" class="button button-primary"><?php esc_html_e('Create API key', 'sustainable-catalyst-library'); ?></button>
        </form>
        <div class="sc-developer-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Name', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Prefix', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Scopes', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Last used', 'sustainable-catalyst-library'); ?></th><th></th></tr></thead><tbody>
            <?php if (!$keys): ?><tr><td colspan="6"><?php esc_html_e('No API keys have been created.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
            <?php foreach ($keys as $key): ?><tr><td><strong><?php echo esc_html($key['name']); ?></strong></td><td><code>scl_…_<?php echo esc_html($key['key_prefix']); ?>_…</code></td><td><?php echo esc_html(implode(', ', json_decode((string) $key['scopes_json'], true) ?: [])); ?></td><td><?php echo esc_html($key['status']); ?></td><td><?php echo esc_html($key['last_used_at'] ?: '—'); ?></td><td><?php if ($key['status'] === 'active'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sc_library_api_key_revoke"><input type="hidden" name="id" value="<?php echo esc_attr((int) $key['id']); ?>"><?php wp_nonce_field('sc_library_api_key_revoke'); ?><button class="button" type="submit"><?php esc_html_e('Revoke', 'sustainable-catalyst-library'); ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section id="sc-webhooks" class="sc-developer-panel">
        <h2><?php esc_html_e('Signed webhook endpoints', 'sustainable-catalyst-library'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-developer-create-form">
            <input type="hidden" name="action" value="sc_library_webhook_create"><?php wp_nonce_field('sc_library_webhook_create'); ?>
            <label><span><?php esc_html_e('Name', 'sustainable-catalyst-library'); ?></span><input type="text" name="name" required maxlength="191"></label>
            <label class="is-wide"><span><?php esc_html_e('HTTPS endpoint', 'sustainable-catalyst-library'); ?></span><input type="url" name="endpoint_url" required placeholder="https://example.org/hooks/library"></label>
            <fieldset><legend><?php esc_html_e('Events', 'sustainable-catalyst-library'); ?></legend><?php foreach (SC_Library_Developer_API::event_types() as $event => $label): ?><label class="sc-developer-check"><input type="checkbox" name="events[]" value="<?php echo esc_attr($event); ?>"> <code><?php echo esc_html($event); ?></code> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
            <button type="submit" class="button button-primary"><?php esc_html_e('Create webhook', 'sustainable-catalyst-library'); ?></button>
        </form>
        <div class="sc-developer-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Name', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Endpoint', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Events', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Last result', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Actions', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
            <?php if (!$webhooks): ?><tr><td colspan="6"><?php esc_html_e('No webhooks have been configured.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
            <?php foreach ($webhooks as $webhook): ?><tr><td><strong><?php echo esc_html($webhook['name']); ?></strong><br><code><?php echo esc_html($webhook['secret_prefix']); ?>…</code></td><td><code><?php echo esc_html($webhook['endpoint_url']); ?></code></td><td><?php echo esc_html(implode(', ', json_decode((string) $webhook['events_json'], true) ?: [])); ?></td><td><?php echo esc_html($webhook['status']); ?></td><td><?php echo esc_html($webhook['last_status_code'] ? (string) $webhook['last_status_code'] : '—'); ?></td><td class="sc-developer-row-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sc_library_webhook_test"><input type="hidden" name="id" value="<?php echo esc_attr((int) $webhook['id']); ?>"><?php wp_nonce_field('sc_library_webhook_test'); ?><button class="button" type="submit"><?php esc_html_e('Test', 'sustainable-catalyst-library'); ?></button></form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sc_library_webhook_toggle"><input type="hidden" name="id" value="<?php echo esc_attr((int) $webhook['id']); ?>"><input type="hidden" name="status" value="<?php echo $webhook['status'] === 'active' ? 'paused' : 'active'; ?>"><?php wp_nonce_field('sc_library_webhook_toggle'); ?><button class="button" type="submit"><?php echo esc_html($webhook['status'] === 'active' ? __('Pause', 'sustainable-catalyst-library') : __('Activate', 'sustainable-catalyst-library')); ?></button></form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this webhook and its delivery history?', 'sustainable-catalyst-library')); ?>');"><input type="hidden" name="action" value="sc_library_webhook_delete"><input type="hidden" name="id" value="<?php echo esc_attr((int) $webhook['id']); ?>"><?php wp_nonce_field('sc_library_webhook_delete'); ?><button class="button button-link-delete" type="submit"><?php esc_html_e('Delete', 'sustainable-catalyst-library'); ?></button></form>
            </td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section id="sc-deliveries" class="sc-developer-panel">
        <h2><?php esc_html_e('Recent webhook deliveries', 'sustainable-catalyst-library'); ?></h2>
        <div class="sc-developer-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Event', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Webhook', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Attempt', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('HTTP', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Created', 'sustainable-catalyst-library'); ?></th><th></th></tr></thead><tbody>
            <?php if (!$deliveries): ?><tr><td colspan="7"><?php esc_html_e('No delivery attempts have been recorded.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
            <?php foreach ($deliveries as $delivery): ?><tr><td><code><?php echo esc_html($delivery['event_type']); ?></code><br><small><?php echo esc_html($delivery['delivery_uuid']); ?></small></td><td><?php echo esc_html($delivery['webhook_name'] ?: '—'); ?></td><td><?php echo esc_html($delivery['status']); ?></td><td><?php echo esc_html((int) $delivery['attempt']); ?></td><td><?php echo esc_html($delivery['response_code'] ?: '—'); ?></td><td><?php echo esc_html($delivery['created_at']); ?></td><td><?php if ($delivery['status'] !== 'delivered'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sc_library_webhook_redeliver"><input type="hidden" name="id" value="<?php echo esc_attr((int) $delivery['id']); ?>"><?php wp_nonce_field('sc_library_webhook_redeliver'); ?><button class="button" type="submit"><?php esc_html_e('Redeliver', 'sustainable-catalyst-library'); ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section id="sc-developer-links" class="sc-developer-panel">
        <h2><?php esc_html_e('Developer documentation', 'sustainable-catalyst-library'); ?></h2>
        <div class="sc-developer-link-grid">
            <a href="<?php echo esc_url(rest_url(SC_Library_Developer_API::API_NAMESPACE . '/openapi.json')); ?>"><strong><?php esc_html_e('OpenAPI 3.1', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Machine-readable endpoint specification', 'sustainable-catalyst-library'); ?></span></a>
            <a href="<?php echo esc_url(rest_url(SC_Library_Developer_API::API_NAMESPACE . '/schemas')); ?>"><strong><?php esc_html_e('JSON Schemas', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Record, relationship, webhook, and error schemas', 'sustainable-catalyst-library'); ?></span></a>
            <a href="<?php echo esc_url((string) get_option('sc_library_developer_portal_url', home_url('/developers/'))); ?>"><strong><?php esc_html_e('Public developer portal', 'sustainable-catalyst-library'); ?></strong><span><code>[sc_library_developer_portal]</code></span></a>
        </div>
        <div class="sc-developer-boundary"><strong><?php esc_html_e('Security boundary', 'sustainable-catalyst-library'); ?></strong><p><?php esc_html_e('Public routes expose only public records. API keys are hashed, webhook secrets are encrypted, cross-origin access is opt-in, webhook destinations must be safe HTTPS URLs, and delivery signatures include a timestamp to limit replay risk.', 'sustainable-catalyst-library'); ?></p></div>
    </section>
</div>
