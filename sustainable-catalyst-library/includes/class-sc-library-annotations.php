<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Annotations {
    public const SCHEMA = 'sc-library-annotation/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_library_annotation_studio', [$this, 'render_shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return class_exists('SC_Library_Notebook')
            && SC_Library_Notebook::enabled()
            && (bool) get_option('sc_library_enable_annotations', 1);
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-annotations', SC_LIBRARY_URL . 'assets/css/sc-library-annotations.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-annotations', SC_LIBRARY_URL . 'assets/js/sc-library-annotations.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-annotations', 'SCAnnotationsShared', [
            'version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'workspaceSchema' => SC_Library_Notebook::SCHEMA,
            'legacyWorkspaceSchemas' => SC_Library_Notebook::legacy_schemas(),
            'storageKey' => 'scLibraryWorkspaceV120',
            'tools' => array_values(self::tools()),
            'targetTypes' => array_values(self::target_types()),
            'pageStyles' => array_values(self::page_styles()),
            'layerTypes' => array_values(self::layer_types()),
            'defaultPageStyle' => (string) get_option('sc_library_default_annotation_page_style', 'reader'),
            'strings' => [
                'saved' => __('Annotation saved.', 'sustainable-catalyst-library'),
                'deleted' => __('Annotation deleted.', 'sustainable-catalyst-library'),
                'storageError' => __('Browser storage is unavailable. Export this annotation before leaving the page.', 'sustainable-catalyst-library'),
                'confirmDelete' => __('Delete this annotation and all of its handwriting, highlights, notes, and shapes?', 'sustainable-catalyst-library'),
                'confirmClearLayer' => __('Clear the active annotation layer?', 'sustainable-catalyst-library'),
                'empty' => __('No annotations have been created yet.', 'sustainable-catalyst-library'),
                'untitled' => __('Untitled annotation', 'sustainable-catalyst-library'),
                'notePrompt' => __('Enter the note text.', 'sustainable-catalyst-library'),
                'anchorPrompt' => __('Enter the passage, section, page, or timestamp this note refers to.', 'sustainable-catalyst-library'),
                'blockedPopup' => __('The browser blocked the print window.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('The Annotation Studio is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Annotation Studio',
            'intro' => 'Add handwritten notes, highlights, shapes, anchored comments, and accessible transcriptions without changing the original source.',
            'open' => 'false',
            'annotation' => '',
            'target_type' => 'custom',
            'target_id' => '',
            'target_title' => '',
            'target_url' => '',
            'target_excerpt' => '',
        ], $atts, 'sc_library_annotation_studio');

        self::enqueue_assets();
        $annotation_title = sanitize_text_field((string) $atts['title']);
        $annotation_intro = sanitize_text_field((string) $atts['intro']);
        $annotation_open = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);
        $annotation_id = sanitize_text_field((string) $atts['annotation']);
        $target_type = sanitize_key((string) $atts['target_type']);
        if (!array_key_exists($target_type, self::target_types())) {
            $target_type = 'custom';
        }
        $target_id = sanitize_text_field((string) $atts['target_id']);
        $target_title = sanitize_text_field((string) $atts['target_title']);
        $target_url = esc_url_raw((string) $atts['target_url']);
        $target_excerpt = sanitize_textarea_field((string) $atts['target_excerpt']);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-annotation-studio.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        register_rest_route('sustainable-catalyst/v1', '/library/annotation-schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => self::SCHEMA,
                'workspace_schema' => SC_Library_Notebook::SCHEMA,
                'tools' => array_values(self::tools()),
                'target_types' => array_values(self::target_types()),
                'page_styles' => array_values(self::page_styles()),
                'layer_types' => array_values(self::layer_types()),
            ]),
            'permission_callback' => '__return_true',
        ]);
    }

    public static function tools(): array {
        return [
            'select' => ['id' => 'select', 'label' => __('Select', 'sustainable-catalyst-library')],
            'pen' => ['id' => 'pen', 'label' => __('Pen', 'sustainable-catalyst-library')],
            'pencil' => ['id' => 'pencil', 'label' => __('Pencil', 'sustainable-catalyst-library')],
            'highlighter' => ['id' => 'highlighter', 'label' => __('Highlighter', 'sustainable-catalyst-library')],
            'eraser' => ['id' => 'eraser', 'label' => __('Eraser', 'sustainable-catalyst-library')],
            'rectangle' => ['id' => 'rectangle', 'label' => __('Rectangle', 'sustainable-catalyst-library')],
            'ellipse' => ['id' => 'ellipse', 'label' => __('Ellipse', 'sustainable-catalyst-library')],
            'arrow' => ['id' => 'arrow', 'label' => __('Arrow', 'sustainable-catalyst-library')],
            'note' => ['id' => 'note', 'label' => __('Note', 'sustainable-catalyst-library')],
        ];
    }

    public static function target_types(): array {
        return [
            'library_record' => ['id' => 'library_record', 'label' => __('Library publication', 'sustainable-catalyst-library')],
            'notebook_note' => ['id' => 'notebook_note', 'label' => __('Notebook note', 'sustainable-catalyst-library')],
            'external_source' => ['id' => 'external_source', 'label' => __('External or physical source', 'sustainable-catalyst-library')],
            'translation_matrix' => ['id' => 'translation_matrix', 'label' => __('Technical Translation Matrix', 'sustainable-catalyst-library')],
            'whiteboard' => ['id' => 'whiteboard', 'label' => __('Whiteboard', 'sustainable-catalyst-library')],
            'chalkboard' => ['id' => 'chalkboard', 'label' => __('Chalkboard', 'sustainable-catalyst-library')],
            'video_clip' => ['id' => 'video_clip', 'label' => __('Video or video snippet', 'sustainable-catalyst-library')],
            'book_page' => ['id' => 'book_page', 'label' => __('Book or document page', 'sustainable-catalyst-library')],
            'custom' => ['id' => 'custom', 'label' => __('Custom material', 'sustainable-catalyst-library')],
        ];
    }

    public static function page_styles(): array {
        return [
            'reader' => ['id' => 'reader', 'label' => __('Reader page', 'sustainable-catalyst-library')],
            'margin' => ['id' => 'margin', 'label' => __('Wide-margin page', 'sustainable-catalyst-library')],
            'lined' => ['id' => 'lined', 'label' => __('Lined notes', 'sustainable-catalyst-library')],
            'dot_grid' => ['id' => 'dot_grid', 'label' => __('Dot grid', 'sustainable-catalyst-library')],
            'graph' => ['id' => 'graph', 'label' => __('Graph paper', 'sustainable-catalyst-library')],
            'cornell' => ['id' => 'cornell', 'label' => __('Cornell notes', 'sustainable-catalyst-library')],
            'blank' => ['id' => 'blank', 'label' => __('Blank page', 'sustainable-catalyst-library')],
            'dark' => ['id' => 'dark', 'label' => __('Dark technical page', 'sustainable-catalyst-library')],
        ];
    }

    public static function layer_types(): array {
        return [
            'ink' => ['id' => 'ink', 'label' => __('Handwriting', 'sustainable-catalyst-library')],
            'highlights' => ['id' => 'highlights', 'label' => __('Highlights', 'sustainable-catalyst-library')],
            'shapes' => ['id' => 'shapes', 'label' => __('Shapes and arrows', 'sustainable-catalyst-library')],
            'notes' => ['id' => 'notes', 'label' => __('Typed notes', 'sustainable-catalyst-library')],
        ];
    }
}
