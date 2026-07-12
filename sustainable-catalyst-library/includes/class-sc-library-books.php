<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Books {
    public const SCHEMA = 'sc-library-book/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_library_book_builder', [$this, 'render_shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return class_exists('SC_Library_Notebook')
            && SC_Library_Notebook::enabled()
            && (bool) get_option('sc_library_enable_books', 1);
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-books', SC_LIBRARY_URL . 'assets/css/sc-library-books.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-books', SC_LIBRARY_URL . 'assets/js/sc-library-books.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-books', 'SCBooksShared', [
            'version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'workspaceSchema' => SC_Library_Notebook::SCHEMA,
            'legacyWorkspaceSchemas' => SC_Library_Notebook::legacy_schemas(),
            'storageKey' => 'scLibraryWorkspaceV120',
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'themes' => array_values(self::themes()),
            'pageSizes' => array_values(self::page_sizes()),
            'mediaModes' => array_values(self::media_modes()),
            'itemTypes' => array_values(self::item_types()),
            'defaultTheme' => (string) get_option('sc_library_default_book_theme', 'institutional'),
            'defaultPageSize' => (string) get_option('sc_library_default_book_page_size', 'letter'),
            'strings' => [
                'saved' => __('Book project saved.', 'sustainable-catalyst-library'),
                'deleted' => __('Book project deleted.', 'sustainable-catalyst-library'),
                'confirmDelete' => __('Delete this book project? The source publications and Notebook artifacts will not be deleted.', 'sustainable-catalyst-library'),
                'blockedPopup' => __('The browser blocked the preview window.', 'sustainable-catalyst-library'),
                'loadingContent' => __('Preparing publication content and research artifacts…', 'sustainable-catalyst-library'),
                'contentError' => __('Some publication content could not be loaded. The available metadata and links were retained.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('The Custom Book Builder is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Custom Book Builder',
            'intro' => 'Combine publications, notes, sources, matrices, visual boards, handwritten annotations, and custom sections into a portable research book and PDF-ready edition.',
            'open' => 'true',
            'book' => '',
        ], $atts, 'sc_library_book_builder');

        self::enqueue_assets();
        $book_builder_title = sanitize_text_field((string) $atts['title']);
        $book_builder_intro = sanitize_text_field((string) $atts['intro']);
        $book_builder_open = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);
        $book_id = sanitize_text_field((string) $atts['book']);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-book-builder.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        register_rest_route('sustainable-catalyst/v1', '/library/book-schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => self::SCHEMA,
                'workspace_schema' => SC_Library_Notebook::SCHEMA,
                'themes' => array_values(self::themes()),
                'page_sizes' => array_values(self::page_sizes()),
                'media_modes' => array_values(self::media_modes()),
                'item_types' => array_values(self::item_types()),
            ]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sustainable-catalyst/v1', '/library/items/(?P<id>\d+)/book', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'book_content'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn($value) => absint($value) > 0,
                ],
            ],
        ]);
    }

    public function book_content(WP_REST_Request $request): WP_REST_Response {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !is_post_type_viewable($post->post_type)) {
            return new WP_REST_Response(['message' => __('Publication not found.', 'sustainable-catalyst-library')], 404);
        }

        $raw = strip_shortcodes((string) $post->post_content);
        $media = $this->media_urls($post_id, $raw);
        $rendered = do_blocks($raw);
        $rendered = wpautop($rendered);
        $rendered = $this->clean_book_html($rendered);

        $author = get_the_author_meta('display_name', (int) $post->post_author);
        $excerpt = has_excerpt($post_id)
            ? get_the_excerpt($post_id)
            : wp_trim_words(wp_strip_all_tags($rendered), 60, '…');

        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'id' => $post_id,
            'record_identifier' => sprintf('sc:library:%s:%d', $post->post_type, $post_id),
            'title' => get_the_title($post_id),
            'author' => $author,
            'excerpt' => $excerpt,
            'content_html' => $rendered,
            'url' => get_permalink($post_id),
            'published_at' => get_post_time(DATE_RFC3339, false, $post_id),
            'modified_at' => get_post_modified_time(DATE_RFC3339, false, $post_id),
            'media' => $media,
        ]);
    }

    private function clean_book_html(string $html): string {
        if ($html === '') {
            return '';
        }

        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $doc = new DOMDocument('1.0', 'UTF-8');
            $wrapped = '<!doctype html><html><body><div id="sc-book-root">' . $html . '</div></body></html>';
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $remove_tags = ['script', 'style', 'form', 'nav', 'button', 'iframe', 'video', 'audio', 'canvas', 'noscript'];
            foreach ($remove_tags as $tag) {
                $nodes = [];
                foreach ($doc->getElementsByTagName($tag) as $node) {
                    $nodes[] = $node;
                }
                foreach ($nodes as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
            $root = $doc->getElementById('sc-book-root');
            if ($root) {
                $clean = '';
                foreach ($root->childNodes as $child) {
                    $clean .= $doc->saveHTML($child);
                }
                $html = $clean;
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return wp_kses_post($html);
    }

    private function media_urls(int $post_id, string $content): array {
        $urls = [];
        $meta = (string) get_post_meta($post_id, '_sc_library_video_urls', true);
        foreach (preg_split('/\R/', $meta) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && wp_http_validate_url($line)) {
                $urls[] = $line;
            }
        }

        if (preg_match_all('/(?:src|href)=["\']([^"\']+(?:youtube\.com|youtu\.be|vimeo\.com|\.mp4(?:\?[^"\']*)?)[^"\']*)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_starts_with($url, '//')) {
                    $url = 'https:' . $url;
                }
                if (wp_http_validate_url($url)) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique(array_map('esc_url_raw', $urls)));
    }

    public static function themes(): array {
        return [
            'institutional' => ['id' => 'institutional', 'label' => __('Institutional', 'sustainable-catalyst-library'), 'description' => __('Strong Sustainable Catalyst headings and restrained research styling.', 'sustainable-catalyst-library')],
            'academic' => ['id' => 'academic', 'label' => __('Academic monograph', 'sustainable-catalyst-library'), 'description' => __('Traditional scholarly typography and chapter structure.', 'sustainable-catalyst-library')],
            'technical' => ['id' => 'technical', 'label' => __('Technical report', 'sustainable-catalyst-library'), 'description' => __('Dense tables, code, equations, diagrams, and audit metadata.', 'sustainable-catalyst-library')],
            'reader' => ['id' => 'reader', 'label' => __('Reader edition', 'sustainable-catalyst-library'), 'description' => __('Generous spacing for long-form reading and annotation.', 'sustainable-catalyst-library')],
            'minimal' => ['id' => 'minimal', 'label' => __('Minimal ink', 'sustainable-catalyst-library'), 'description' => __('Printer-friendly black-and-white layout.', 'sustainable-catalyst-library')],
        ];
    }

    public static function page_sizes(): array {
        return [
            'letter' => ['id' => 'letter', 'label' => __('US Letter', 'sustainable-catalyst-library'), 'css' => 'letter'],
            'a4' => ['id' => 'a4', 'label' => __('A4', 'sustainable-catalyst-library'), 'css' => 'A4'],
        ];
    }

    public static function media_modes(): array {
        return [
            'linked' => ['id' => 'linked', 'label' => __('Linked media card', 'sustainable-catalyst-library')],
            'poster' => ['id' => 'poster', 'label' => __('Poster, citation, and link', 'sustainable-catalyst-library')],
            'preview' => ['id' => 'preview', 'label' => __('Playable browser preview with PDF fallback', 'sustainable-catalyst-library')],
        ];
    }

    public static function item_types(): array {
        return [
            'record' => ['id' => 'record', 'label' => __('Library publication', 'sustainable-catalyst-library')],
            'note' => ['id' => 'note', 'label' => __('Notebook note', 'sustainable-catalyst-library')],
            'source' => ['id' => 'source', 'label' => __('External or physical source', 'sustainable-catalyst-library')],
            'matrix' => ['id' => 'matrix', 'label' => __('Technical Translation Matrix', 'sustainable-catalyst-library')],
            'board' => ['id' => 'board', 'label' => __('Whiteboard or Chalkboard', 'sustainable-catalyst-library')],
            'annotation' => ['id' => 'annotation', 'label' => __('Annotation or handwriting page', 'sustainable-catalyst-library')],
            'custom' => ['id' => 'custom', 'label' => __('Custom editorial section', 'sustainable-catalyst-library')],
        ];
    }
}
