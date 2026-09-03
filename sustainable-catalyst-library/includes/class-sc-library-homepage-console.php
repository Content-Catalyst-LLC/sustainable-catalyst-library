<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.7.1 Homepage Research Network & Knowledge Discovery Console.
 *
 * A compact public front door owned by the Research Library plugin. The
 * console reuses the governed research-network registries and the Dynamic
 * Explorer bootstrap endpoint rather than maintaining a second set of source
 * names, counts, or corpus telemetry.
 */
final class SC_Library_Homepage_Console {
    /** Module provenance version; public release identity comes from SC_LIBRARY_VERSION. */
    public const VERSION = '5.7.1';
    public const SHORTCODE = 'sc_library_homepage_console';
    public const REST_NAMESPACE = 'sc-library/v1';
    private const RUNTIME_HEALTH_TRANSIENT = 'sc_library_runtime_release_health_v1';

    public function register_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_runtime_route']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    /** Canonical current Library release identity. */
    public static function runtime_version(): string {
        return defined('SC_LIBRARY_VERSION') ? (string) SC_LIBRARY_VERSION : self::VERSION;
    }

    public function register_runtime_route(): void {
        register_rest_route(self::REST_NAMESPACE, '/runtime/release', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'runtime_release'],
        ]);
    }

    public function runtime_release(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        $library_version = self::runtime_version();
        $installed_version = (string) get_option('sc_library_version', '');
        $backend = self::cached_backend_health();

        $response = new WP_REST_Response([
            'schema' => 'sc-library-runtime-release/1.0',
            'library' => [
                'version' => $library_version,
                'installed_version' => $installed_version,
                'synchronized' => $installed_version === $library_version,
            ],
            'backend' => [
                'configured' => !empty($backend['configured']),
                'ok' => !empty($backend['ok']),
                'state' => (string) ($backend['state'] ?? 'unknown'),
                'service' => (string) ($backend['service'] ?? 'sustainable-catalyst-library-backend'),
                'version' => isset($backend['version']) && $backend['version'] !== null ? (string) $backend['version'] : null,
            ],
            'module_provenance' => [
                'homepage_console' => self::VERSION,
            ],
        ], 200);
        $response->header('Cache-Control', 'no-store, max-age=0');
        return $response;
    }

    /** @return array<string,mixed> */
    private static function cached_backend_health(): array {
        $cached = get_transient(self::RUNTIME_HEALTH_TRANSIENT);
        if (is_array($cached)) { return $cached; }
        $health = class_exists('SC_Library_Python_Backend')
            ? SC_Library_Python_Backend::health()
            : ['ok' => false, 'configured' => false, 'state' => 'bridge_unavailable', 'version' => null];
        set_transient(self::RUNTIME_HEALTH_TRANSIENT, $health, 60);
        return $health;
    }

    public function maybe_enqueue_assets(): void {
        if (!is_singular()) { return; }
        $post = get_queried_object();
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        if ($content === '' || !has_shortcode($content, self::SHORTCODE)) { return; }
        $this->enqueue_assets();
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-homepage-console-v561',
            SC_LIBRARY_URL . 'assets/css/sc-library-homepage-console-v561.css',
            [],
            SC_LIBRARY_VERSION
        );
        wp_enqueue_script(
            'sc-library-homepage-console-v561',
            SC_LIBRARY_URL . 'assets/js/sc-library-homepage-console-v561.js',
            [],
            SC_LIBRARY_VERSION,
            true
        );
        wp_localize_script('sc-library-homepage-console-v561', 'SCLibraryHomepageConsoleV561', [
            'bootstrapUrl' => esc_url_raw(rest_url(SC_Library_Dynamic_Explorer::REST_NAMESPACE . '/explorer/bootstrap')),
            'libraryUrl' => esc_url_raw(home_url('/knowledge-libraries/')),
            'runtimeUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/runtime/release')),
            'version' => self::runtime_version(),
            'strings' => [
                'connected' => __('Connected index', 'sustainable-catalyst-library'),
                'local' => __('Local index', 'sustainable-catalyst-library'),
                'unavailable' => __('Live counts unavailable', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    /** @return array<int,array<string,string>> */
    private static function featured_sources(): array {
        $registry = class_exists('SC_Library_Research_Network_Console')
            ? SC_Library_Research_Network_Console::source_registry()
            : [];
        $by_id = [];
        foreach ($registry as $row) {
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id !== '' && !isset($by_id[$id])) { $by_id[$id] = $row; }
        }

        $priority = [
            'mit', 'harvard', 'johns-hopkins-dataverse', 'ucd', 'pubmed', 'clinicaltrials', 'mesh', 'rxnorm', 'fda-regulatory', 'yale', 'princeton', 'stanford',
            'nypl', 'loc', 'internetarchive', 'openalex', 'crossref',
            'europepmc', 'arxiv', 'worldcat',
        ];
        $featured = [];
        foreach ($priority as $id) {
            if (isset($by_id[$id])) { $featured[] = $by_id[$id]; }
        }
        return $featured;
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'mode' => 'full',
            'title' => 'Research across institutions, evidence & open knowledge',
            'intro' => 'Search Sustainable Catalyst knowledge, see the research network behind it, move into university and public-library discovery, or ask the Research Librarian for a bounded starting route.',
        ], $atts, self::SHORTCODE);

        $mode = sanitize_key((string) $atts['mode']);
        if (!in_array($mode, ['full', 'compact', 'network'], true)) { $mode = 'full'; }
        $this->enqueue_assets();

        $sources = self::featured_sources();
        $network_counts = class_exists('SC_Library_Research_Network_Console')
            ? SC_Library_Research_Network_Console::source_counts()
            : ['routes' => count($sources), 'universities' => 0, 'libraries' => 0, 'scholarly' => 0];
        $course_count = class_exists('SC_Library_Open_Course_Finder')
            ? count(SC_Library_Open_Course_Finder::launch_catalog())
            : 0;
        $public_library_count = class_exists('SC_Library_Public_Library_Network')
            ? count(SC_Library_Public_Library_Network::registry())
            : (int) ($network_counts['libraries'] ?? 0);

        $instance = 'sc-library-home-console-' . wp_rand(1000, 999999);
        $library_url = home_url('/knowledge-libraries/');
        $network_url = $library_url . '#research-network';
        $local_library_url = $library_url . '#public-library-network';
        $librarian_url = $library_url . '#research-front-door';

        ob_start(); ?>
        <section class="sc-library-home-console sc-library-home-console--<?php echo esc_attr($mode); ?>" id="<?php echo esc_attr($instance); ?>" data-sc-library-home-console data-library-version="<?php echo esc_attr(self::runtime_version()); ?>">
            <?php if ('network' !== $mode) : ?>
            <header class="sc-library-home-console__header">
                <div class="sc-library-home-console__identity">
                    <p class="sc-library-home-console__kicker"><span aria-hidden="true">KL</span><?php esc_html_e('Knowledge Library', 'sustainable-catalyst-library'); ?></p>
                    <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                    <?php if ('full' === $mode) : ?><p><?php echo esc_html((string) $atts['intro']); ?></p><?php endif; ?>
                </div>
                <div class="sc-library-home-console__live" data-sc-home-live-state><span aria-hidden="true"></span><strong><?php esc_html_e('Research network', 'sustainable-catalyst-library'); ?></strong><small><?php esc_html_e('Loading live index…', 'sustainable-catalyst-library'); ?></small></div>
            </header>

            <div class="sc-library-home-console__metrics" aria-label="Knowledge Library public index metrics">
                <div><strong data-sc-home-metric="records">—</strong><span><?php esc_html_e('published records', 'sustainable-catalyst-library'); ?></span></div>
                <div><strong data-sc-home-metric="topics">—</strong><span><?php esc_html_e('active topics', 'sustainable-catalyst-library'); ?></span></div>
                <div><strong data-sc-home-metric="chunks">—</strong><span><?php esc_html_e('searchable passages', 'sustainable-catalyst-library'); ?></span></div>
                <div><strong><?php echo esc_html(number_format_i18n((int) ($network_counts['routes'] ?? count($sources)))); ?></strong><span><?php esc_html_e('research routes', 'sustainable-catalyst-library'); ?></span></div>
            </div>
            <?php endif; ?>

            <div class="sc-library-home-console__terminal">
                <div class="sc-library-home-console__terminal-head">
                    <div><span class="sc-library-home-console__pulse" aria-hidden="true"></span><strong><?php esc_html_e('RESEARCH NETWORK', 'sustainable-catalyst-library'); ?></strong><small><?php esc_html_e('CONNECTED SOURCES', 'sustainable-catalyst-library'); ?></small></div>
                    <span><?php echo esc_html(number_format_i18n((int) ($network_counts['routes'] ?? count($sources)))); ?> <?php esc_html_e('VISIBLE ROUTES', 'sustainable-catalyst-library'); ?></span>
                </div>
                <div class="sc-library-home-console__columns" aria-hidden="true"><span>SOURCE</span><span>TYPE</span><span>ACCESS</span></div>
                <div class="sc-library-home-console__viewport" tabindex="0" data-sc-home-network-viewport>
                    <div class="sc-library-home-console__track" data-sc-home-network-track>
                        <?php foreach ($sources as $row) : ?>
                        <div class="sc-library-home-console__row" data-sc-home-network-row data-source-id="<?php echo esc_attr((string) ($row['id'] ?? '')); ?>">
                            <div><i aria-hidden="true">&gt;</i><strong><?php echo esc_html((string) ($row['name'] ?? 'Research source')); ?></strong><small><?php echo esc_html((string) ($row['detail'] ?? '')); ?></small></div>
                            <span><?php echo esc_html((string) ($row['type'] ?? 'Research source')); ?></span>
                            <span><?php echo esc_html((string) ($row['mode'] ?? 'DISCOVER')); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sc-library-home-console__terminal-foot"><span>LIBRARY: <strong data-sc-home-library-version>v<?php echo esc_html(self::runtime_version()); ?></strong></span><span>BACKEND: <strong data-sc-home-backend-version>CHECKING</strong></span><span>PROVENANCE: VISIBLE</span><span>ACCESS: LABELED</span><span>ENTITLEMENT: NEVER ASSUMED</span></div>
            </div>

            <?php if ('network' !== $mode) : ?>
            <div class="sc-library-home-console__signals" aria-label="Research Library capabilities">
                <div><span><?php esc_html_e('Research Librarian', 'sustainable-catalyst-library'); ?></span><strong><?php esc_html_e('Ready', 'sustainable-catalyst-library'); ?></strong></div>
                <div><span><?php esc_html_e('Public libraries', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html(number_format_i18n($public_library_count)); ?> <?php esc_html_e('routes', 'sustainable-catalyst-library'); ?></strong></div>
                <div><span><?php esc_html_e('Open courses', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html(number_format_i18n($course_count)); ?> <?php esc_html_e('verified', 'sustainable-catalyst-library'); ?></strong></div>
                <div><span><?php esc_html_e('Provenance', 'sustainable-catalyst-library'); ?></span><strong><?php esc_html_e('Visible', 'sustainable-catalyst-library'); ?></strong></div>
            </div>

            <?php if ('full' === $mode) : ?>
            <form class="sc-library-home-console__search" data-sc-home-library-search>
                <label><span><?php esc_html_e('What are you researching?', 'sustainable-catalyst-library'); ?></span><input type="search" name="query" placeholder="Climate adaptation, international law, systems thinking…" autocomplete="off"></label>
                <div>
                    <button type="submit" data-sc-home-search-target="knowledge"><?php esc_html_e('Search Knowledge', 'sustainable-catalyst-library'); ?></button>
                    <button type="submit" data-sc-home-search-target="research"><?php esc_html_e('Search Research Network', 'sustainable-catalyst-library'); ?></button>
                    <button type="submit" data-sc-home-search-target="librarian"><?php esc_html_e('Ask Research Librarian', 'sustainable-catalyst-library'); ?></button>
                </div>
            </form>
            <?php endif; ?>

            <footer class="sc-library-home-console__actions">
                <a class="is-primary" href="<?php echo esc_url($library_url); ?>"><?php esc_html_e('Explore the Research Library', 'sustainable-catalyst-library'); ?> →</a>
                <a href="<?php echo esc_url($network_url); ?>"><?php esc_html_e('Research Network', 'sustainable-catalyst-library'); ?> →</a>
                <a href="<?php echo esc_url($local_library_url); ?>"><?php esc_html_e('Find a Public Library', 'sustainable-catalyst-library'); ?> →</a>
                <a href="<?php echo esc_url($librarian_url); ?>"><?php esc_html_e('Research Librarian', 'sustainable-catalyst-library'); ?> →</a>
            </footer>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
