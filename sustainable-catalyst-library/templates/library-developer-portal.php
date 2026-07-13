<section class="sc-developer-portal">
    <header class="sc-developer-portal__hero">
        <p class="sc-developer-portal__eyebrow"><?php esc_html_e('Sustainable Catalyst platform infrastructure', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($developer_title); ?></h2>
        <p><?php echo esc_html($developer_intro); ?></p>
        <div class="sc-developer-portal__actions">
            <a class="sc-developer-button is-primary" href="<?php echo esc_url($developer_openapi_url); ?>"><?php esc_html_e('OpenAPI specification', 'sustainable-catalyst-library'); ?></a>
            <a class="sc-developer-button" href="<?php echo esc_url($developer_schema_url); ?>"><?php esc_html_e('JSON Schema registry', 'sustainable-catalyst-library'); ?></a>
        </div>
    </header>

    <div class="sc-developer-portal__grid">
        <article>
            <p class="sc-developer-portal__label"><?php esc_html_e('API base', 'sustainable-catalyst-library'); ?></p>
            <code><?php echo esc_html($developer_api_base); ?></code>
            <p><?php esc_html_e('Public endpoints expose canonical public records, public relationships, graph neighborhoods, and roadmap data.', 'sustainable-catalyst-library'); ?></p>
        </article>
        <article>
            <p class="sc-developer-portal__label"><?php esc_html_e('Authentication', 'sustainable-catalyst-library'); ?></p>
            <code>X-SC-Library-Key: scl_live_…</code>
            <p><?php esc_html_e('Only protected service operations require a scoped administrator-issued key. Plaintext keys are shown once.', 'sustainable-catalyst-library'); ?></p>
        </article>
        <article>
            <p class="sc-developer-portal__label"><?php esc_html_e('Webhook verification', 'sustainable-catalyst-library'); ?></p>
            <code>sha256=HMAC(secret, timestamp.payload)</code>
            <p><?php esc_html_e('Validate the timestamp and X-SC-Signature before accepting an event.', 'sustainable-catalyst-library'); ?></p>
        </article>
    </div>

    <section class="sc-developer-portal__section">
        <h3><?php esc_html_e('Public endpoints', 'sustainable-catalyst-library'); ?></h3>
        <div class="sc-developer-endpoints">
            <?php foreach ([
                'GET /status' => __('Service health and version information', 'sustainable-catalyst-library'),
                'GET /records' => __('Search and paginate public Library records', 'sustainable-catalyst-library'),
                'GET /records/{id}' => __('Read one canonical public record', 'sustainable-catalyst-library'),
                'GET /relationships' => __('Browse provenance-aware public relationships', 'sustainable-catalyst-library'),
                'GET /graph' => __('Read a public Knowledge Graph neighborhood', 'sustainable-catalyst-library'),
                'GET /roadmap' => __('Read the public registry and roadmap tracker', 'sustainable-catalyst-library'),
                'GET /schemas' => __('List the published JSON Schemas', 'sustainable-catalyst-library'),
                'GET /openapi.json' => __('Read the OpenAPI 3.1 specification', 'sustainable-catalyst-library'),
            ] as $endpoint => $description): ?>
                <article><code><?php echo esc_html($endpoint); ?></code><p><?php echo esc_html($description); ?></p></article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sc-developer-portal__section">
        <h3><?php esc_html_e('Webhook events', 'sustainable-catalyst-library'); ?></h3>
        <div class="sc-developer-events">
            <?php foreach ($developer_events as $event => $label): ?>
                <span><code><?php echo esc_html($event); ?></code> <?php echo esc_html($label); ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sc-developer-portal__section sc-developer-code-example">
        <h3><?php esc_html_e('JavaScript example', 'sustainable-catalyst-library'); ?></h3>
        <pre><code>const base = <?php echo wp_json_encode(untrailingslashit($developer_api_base)); ?>;
const response = await fetch(`${base}/records?search=systems&amp;per_page=10`);
if (!response.ok) throw new Error(`Library API ${response.status}`);
const page = await response.json();
console.log(page.items);</code></pre>
    </section>

    <p class="sc-developer-portal__boundary"><?php esc_html_e('The public API does not expose private workspaces, editorial comments, invitation tokens, internal planning notes, API credentials, or webhook signing secrets.', 'sustainable-catalyst-library'); ?></p>
</section>
