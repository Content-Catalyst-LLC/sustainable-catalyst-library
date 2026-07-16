<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Shortcodes {
    private static int $instance_count = 0;

    public function register_hooks(): void {
        add_shortcode('sc_library', [$this, 'render']);
        add_action('admin_notices', [$this, 'runtime_error_notice'], 2);
    }

    public function render(array $atts = []): string {
        $initial_buffer_level = ob_get_level();
        try {
            return $this->render_runtime($atts);
        } catch (Throwable $error) {
            while (ob_get_level() > $initial_buffer_level) {
                ob_end_clean();
            }
            $this->record_runtime_error($error);
            $fallback_buffer_level = ob_get_level();
            try {
                return $this->render_emergency_fallback($atts);
            } catch (Throwable $fallback_error) {
                while (ob_get_level() > $fallback_buffer_level) {
                    ob_end_clean();
                }
                $this->record_runtime_error($fallback_error, 'fallback');
                return '<section class="sc-library sc-library--recovery"><h2>'
                    . esc_html__('Research Library', 'sustainable-catalyst-library')
                    . '</h2><p>'
                    . esc_html__('The Library is temporarily operating in recovery mode. Published articles and documents remain available through site search and direct links.', 'sustainable-catalyst-library')
                    . '</p></section>';
            }
        }
    }

    private function render_runtime(array $atts = []): string {
        $requested_mode = sanitize_key((string) ($atts['mode'] ?? ''));
        $requested_collection = sanitize_title((string) ($atts['collection'] ?? ''));
        if (class_exists('SC_Library_Documentation') && ($requested_mode === 'documentation' || ($requested_collection === SC_Library_Documentation::COLLECTION_SLUG && $requested_mode !== 'registry' && $requested_mode !== 'planner'))) {
            return SC_Library_Documentation::render_shortcode($atts);
        }
        if (class_exists('SC_Library_Planner') && in_array($requested_mode, ['registry', 'planner', 'roadmap'], true)) {
            $planner = new SC_Library_Planner(new SC_Library_Indexer(new SC_Library_Relationships()), new SC_Library_Relationships());
            if ($requested_mode === 'registry') {
                return $planner->render_registry_shortcode($atts);
            }
            return $planner->render_tracker_shortcode($atts);
        }

        self::$instance_count++;

        $default_mode = (string) get_option('sc_library_default_mode', 'compact');
        $atts = shortcode_atts([
            'category' => '',
            'collection' => '',
            'series' => '',
            'concept' => '',
            'record' => '',
            'per_page' => (string) get_option('sc_library_items_per_page', 10),
            'title' => 'Explore the knowledge base',
            'intro' => 'Search the Sustainable Catalyst knowledge architecture or open a domain, series, or concept to reveal connected publications.',
            'show_header' => 'true',
            'show_pathways' => (string) (int) get_option('sc_library_show_pathways', 1),
            'initial_results' => (string) (int) get_option('sc_library_initial_results', 0),
            'mode' => $default_mode,
            'density' => (string) get_option('sc_library_result_density', 'compact'),
            'show_workspace' => (string) (int) get_option('sc_library_enable_notebook', 1),
        ], $atts, 'sc_library');

        $allowed_modes = ['compact', 'full', 'search', 'domains', 'pathways'];
        $mode = in_array($atts['mode'], $allowed_modes, true) ? $atts['mode'] : 'compact';
        $density = in_array($atts['density'], ['compact', 'comfortable'], true) ? $atts['density'] : 'compact';
        $per_page = min(30, max(1, absint($atts['per_page'])));
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $show_pathways = filter_var($atts['show_pathways'], FILTER_VALIDATE_BOOLEAN);
        $initial_results = filter_var($atts['initial_results'], FILTER_VALIDATE_BOOLEAN);
        $show_workspace = filter_var($atts['show_workspace'], FILTER_VALIDATE_BOOLEAN) && SC_Library_Notebook::enabled();
        $title = sanitize_text_field($atts['title']);
        $intro = sanitize_text_field($atts['intro']);
        $initial_category = sanitize_title($atts['category']);
        $initial_series = sanitize_title($atts['series']);
        $initial_concept = sanitize_title($atts['concept']);
        $initial_record = absint($atts['record']);
        $instance_id = 'sc-library-' . self::$instance_count . '-' . wp_rand(1000, 9999);
        $pathways = $this->featured_pathways();

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-discovery', SC_LIBRARY_URL . 'assets/css/sc-library-discovery.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library', SC_LIBRARY_URL . 'assets/js/sc-library.js', [], SC_LIBRARY_VERSION, true);
        if ($show_workspace) {
            SC_Library_Notebook::enqueue_assets();
        }
        if (class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled()) {
            SC_Library_Integrations::enqueue_assets();
        }
        if (class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled()) {
            SC_Library_Annotations::enqueue_assets();
        }
        if (class_exists('SC_Library_Books') && SC_Library_Books::enabled()) {
            SC_Library_Books::enqueue_assets();
        }
        wp_localize_script('sc-library', 'SCLibraryShared', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'discoverySchema' => 'sc-library-discovery/1.0',
            'discoveryInterfaceVersion' => '2.0.1',
            'matrixEnabled' => SC_Library_Notebook::matrix_enabled(),
            'boardsEnabled' => class_exists('SC_Library_Boards') && SC_Library_Boards::enabled(),
            'integrationsEnabled' => class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled(),
            'annotationsEnabled' => class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled(),
            'booksEnabled' => class_exists('SC_Library_Books') && SC_Library_Books::enabled(),
            'graphEnabled' => class_exists('SC_Library_Knowledge_Graph') && SC_Library_Knowledge_Graph::enabled(),
            'graphPageUrl' => esc_url_raw((string) get_option('sc_library_graph_page_url', home_url('/knowledge-graph/'))),
            'orchestratorEnabled' => class_exists('SC_Library_Orchestrator') && SC_Library_Orchestrator::enabled(),
            'orchestratorPageUrl' => esc_url_raw((string) get_option('sc_library_orchestrator_page_url', home_url('/research-librarian/'))),
            'strings' => [
                'loading' => __('Searching the knowledge base…', 'sustainable-catalyst-library'),
                'empty' => __('No knowledge records match this request.', 'sustainable-catalyst-library'),
                'error' => __('The knowledge base could not be loaded. Please try again.', 'sustainable-catalyst-library'),
                'categoriesError' => __('Topic navigation is temporarily unavailable.', 'sustainable-catalyst-library'),
                'facetsError' => __('Series and concept navigation is temporarily unavailable.', 'sustainable-catalyst-library'),
                'discoveryLoading' => __('Loading topics, relationships, and pathways…', 'sustainable-catalyst-library'),
                'discoveryReady' => __('Discovery interface ready.', 'sustainable-catalyst-library'),
                'discoveryError' => __('The discovery interface could not be loaded.', 'sustainable-catalyst-library'),
                'retryDiscovery' => __('Retry discovery', 'sustainable-catalyst-library'),
                'pathwaysEmpty' => __('No featured pathways are configured yet.', 'sustainable-catalyst-library'),
                'recordLoading' => __('Loading the knowledge record…', 'sustainable-catalyst-library'),
                'copySuccess' => __('Record link copied.', 'sustainable-catalyst-library'),
                'copyFailure' => __('Copy the address from your browser.', 'sustainable-catalyst-library'),
                'saveRecord' => __('Save to Notebook', 'sustainable-catalyst-library'),
                'writeNote' => __('Write note', 'sustainable-catalyst-library'),
                'translateRecord' => __('Open Translation Matrix', 'sustainable-catalyst-library'),
                'whiteboardRecord' => __('Open Whiteboard', 'sustainable-catalyst-library'),
                'chalkboardRecord' => __('Open Chalkboard', 'sustainable-catalyst-library'),
                'workbenchRecord' => __('Analyze in Workbench', 'sustainable-catalyst-library'),
                'decisionRecord' => __('Build Decision Canvas', 'sustainable-catalyst-library'),
                'siteRecord' => __('Investigate Geographic Context', 'sustainable-catalyst-library'),
                'annotateRecord' => __('Annotate and Handwrite', 'sustainable-catalyst-library'),
                'bookRecord' => __('Add to Custom Book', 'sustainable-catalyst-library'),
                'graphRecord' => __('View Relationship Graph', 'sustainable-catalyst-library'),
                'orchestrateRecord' => __('Ask Research Librarian', 'sustainable-catalyst-library'),
                'results' => __('results', 'sustainable-catalyst-library'),
                'result' => __('result', 'sustainable-catalyst-library'),
            ],
        ]);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-app.php';
        return (string) ob_get_clean();
    }

    private function render_emergency_fallback(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Explore the knowledge base',
            'intro' => 'Search published Sustainable Catalyst research, documents, and knowledge records.',
            'show_header' => 'true',
            'per_page' => '12',
        ], $atts, 'sc_library');

        $search = sanitize_text_field(wp_unslash((string) ($_GET['library_search'] ?? '')));
        $topic = sanitize_title(wp_unslash((string) ($_GET['library_topic'] ?? '')));
        $page = max(1, absint($_GET['library_page'] ?? 1));
        $per_page = min(24, max(1, absint($atts['per_page'])));
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);

        $post_types = array_values(array_filter(
            ['post', 'page', 'sc_foundation_doc', 'sc_pdf_document'],
            'post_type_exists'
        ));
        if (!$post_types) {
            $post_types = ['post', 'page'];
        }

        $query_args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $search,
            'ignore_sticky_posts' => true,
            'orderby' => $search !== '' ? 'relevance' : 'modified',
            'order' => 'DESC',
        ];

        if ($topic !== '' && taxonomy_exists('category')) {
            $query_args['category_name'] = $topic;
        }

        $query = new WP_Query($query_args);
        $topics = taxonomy_exists('category') ? get_terms([
            'taxonomy' => 'category',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 18,
        ]) : [];
        if (is_wp_error($topics)) {
            $topics = [];
        }

        $base_url = get_permalink(get_queried_object_id());
        if (!$base_url) {
            $base_url = home_url('/research-library/');
        }

        ob_start();
        ?>
        <section class="sc-library sc-library--recovery" data-sc-library-recovery="1">
            <?php if ($show_header) : ?>
                <header class="sc-library__masthead">
                    <p class="sc-library__eyebrow"><?php esc_html_e('Sustainable Catalyst Knowledge Base', 'sustainable-catalyst-library'); ?></p>
                    <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                    <p><?php echo esc_html((string) $atts['intro']); ?></p>
                </header>
            <?php endif; ?>

            <div class="sc-library__recovery-notice" role="status">
                <strong><?php esc_html_e('Protected Library mode', 'sustainable-catalyst-library'); ?></strong>
                <span><?php esc_html_e('The interactive layer encountered an error, so this server-rendered catalog is being shown instead.', 'sustainable-catalyst-library'); ?></span>
            </div>

            <form class="sc-library__command" method="get" action="<?php echo esc_url($base_url); ?>" role="search">
                <label class="sc-library__search-field">
                    <span class="screen-reader-text"><?php esc_html_e('Search the Research Library', 'sustainable-catalyst-library'); ?></span>
                    <input type="search" name="library_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search research, concepts, and documents', 'sustainable-catalyst-library'); ?>">
                </label>
                <button type="submit" class="sc-library__search-button"><?php esc_html_e('Search', 'sustainable-catalyst-library'); ?></button>
            </form>

            <?php if ($topics) : ?>
                <nav class="sc-library__recovery-topics" aria-label="<?php esc_attr_e('Research topics', 'sustainable-catalyst-library'); ?>">
                    <a href="<?php echo esc_url(remove_query_arg(['library_topic', 'library_page'], $base_url)); ?>"><?php esc_html_e('All topics', 'sustainable-catalyst-library'); ?></a>
                    <?php foreach ($topics as $term) : ?>
                        <a href="<?php echo esc_url(add_query_arg(['library_topic' => $term->slug], $base_url)); ?>"><?php echo esc_html($term->name); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="sc-library__results-head">
                <div><p class="sc-library__results-kicker"><?php esc_html_e('Knowledge records', 'sustainable-catalyst-library'); ?></p>
                <h3><?php echo $search !== '' ? esc_html(sprintf(__('Results for “%s”', 'sustainable-catalyst-library'), $search)) : esc_html__('Recently updated', 'sustainable-catalyst-library'); ?></h3></div>
                <span><?php echo esc_html(sprintf(_n('%s record', '%s records', (int) $query->found_posts, 'sustainable-catalyst-library'), number_format_i18n((int) $query->found_posts))); ?></span>
            </div>

            <div class="sc-library__results sc-library__results--server">
                <?php if ($query->have_posts()) : ?>
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <article class="sc-library-card">
                            <p class="sc-library-card__type"><?php echo esc_html(get_post_type_object(get_post_type())?->labels->singular_name ?? ucfirst((string) get_post_type())); ?></p>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt() ?: get_the_content()), 34)); ?></p>
                            <a class="sc-library-card__open" href="<?php the_permalink(); ?>"><?php esc_html_e('Open record', 'sustainable-catalyst-library'); ?> →</a>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <p class="sc-library__empty"><?php esc_html_e('No published knowledge records match this request.', 'sustainable-catalyst-library'); ?></p>
                <?php endif; ?>
            </div>

            <?php if ((int) $query->max_num_pages > 1) : ?>
                <nav class="sc-library__pagination" aria-label="<?php esc_attr_e('Library result pages', 'sustainable-catalyst-library'); ?>">
                    <?php
                    echo wp_kses_post(paginate_links([
                        'base' => esc_url_raw(add_query_arg('library_page', '%#%', $base_url)),
                        'format' => '',
                        'current' => $page,
                        'total' => (int) $query->max_num_pages,
                        'type' => 'list',
                        'add_args' => array_filter(['library_search' => $search, 'library_topic' => $topic]),
                    ]));
                    ?>
                </nav>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function record_runtime_error(Throwable $error, string $stage = 'interactive'): void {
        $record = [
            'stage' => $stage,
            'class' => get_class($error),
            'message' => $error->getMessage(),
            'file' => basename($error->getFile()),
            'line' => $error->getLine(),
            'url' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '',
            'timestamp' => current_time('mysql', true),
        ];
        update_option('sc_library_last_public_runtime_error', $record, false);
        error_log('[Sustainable Catalyst Library] Research Library runtime recovery: ' . wp_json_encode($record));
    }

    public function runtime_error_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $record = get_option('sc_library_last_public_runtime_error', []);
        if (!is_array($record) || empty($record['message'])) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>'
            . esc_html__('Research Library recovery mode was activated.', 'sustainable-catalyst-library')
            . '</strong> '
            . esc_html(sprintf('%s in %s:%s', (string) $record['message'], (string) ($record['file'] ?? ''), (string) ($record['line'] ?? '')))
            . '</p></div>';
    }

    private function featured_pathways(): array {
        $raw = (string) get_option('sc_library_featured_pathways', '');
        $pathways = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $pathways[] = [
                'title' => sanitize_text_field($parts[0]),
                'url' => esc_url($parts[1]),
                'description' => isset($parts[2]) ? sanitize_text_field($parts[2]) : '',
            ];
        }
        return array_slice($pathways, 0, 8);
    }
}
