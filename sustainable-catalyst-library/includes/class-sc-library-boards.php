<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Boards {
    public function register_hooks(): void {
        add_shortcode('sc_library_whiteboard', [$this, 'render_whiteboard']);
        add_shortcode('sc_library_chalkboard', [$this, 'render_chalkboard']);
        add_shortcode('sc_library_boards', [$this, 'render_boards']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return SC_Library_Notebook::enabled() && (bool) get_option('sc_library_enable_boards', 1);
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-notebook', SC_LIBRARY_URL . 'assets/css/sc-library-notebook.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-boards', SC_LIBRARY_URL . 'assets/css/sc-library-boards.css', ['sc-library-notebook'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-boards', SC_LIBRARY_URL . 'assets/js/sc-library-boards.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-boards', 'SCBoardShared', [
            'version' => SC_LIBRARY_VERSION,
            'schema' => SC_Library_Notebook::SCHEMA,
            'legacySchemas' => SC_Library_Notebook::legacy_schemas(),
            'storageKey' => 'scLibraryWorkspaceV120',
            'defaultType' => (string) get_option('sc_library_default_board_type', 'whiteboard'),
            'templates' => self::board_templates(),
            'nodeTypes' => self::node_types(),
            'edgeTypes' => self::edge_types(),
            'graphEnabled' => class_exists('SC_Library_Knowledge_Graph') && SC_Library_Knowledge_Graph::enabled(),
            'graphEndpoint' => esc_url_raw(rest_url('sustainable-catalyst/v1/library/graph/board-promotions')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'saved' => __('Board saved to the Research Notebook.', 'sustainable-catalyst-library'),
                'deleted' => __('Board deleted.', 'sustainable-catalyst-library'),
                'confirmClose' => __('Close this board without saving the latest changes?', 'sustainable-catalyst-library'),
                'confirmDelete' => __('Delete this board from local browser storage?', 'sustainable-catalyst-library'),
                'storageError' => __('The board could not be saved in browser storage. Export it before leaving.', 'sustainable-catalyst-library'),
                'connectionLabel' => __('Relationship label', 'sustainable-catalyst-library'),
                'promoteGraph' => __('Promote to Knowledge Graph', 'sustainable-catalyst-library'),
                'graphPromoted' => __('Board entities and relationships were promoted to the Knowledge Graph.', 'sustainable-catalyst-library'),
                'graphPromotionFailed' => __('The board could not be promoted to the Knowledge Graph.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_whiteboard(array $atts = []): string {
        $atts['type'] = 'whiteboard';
        return $this->render_boards($atts);
    }

    public function render_chalkboard(array $atts = []): string {
        $atts['type'] = 'chalkboard';
        return $this->render_boards($atts);
    }

    public function render_boards(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('Whiteboards and Chalkboards are currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'type' => (string) get_option('sc_library_default_board_type', 'whiteboard'),
            'title' => 'Visual Research Boards',
            'intro' => 'Map concepts, connect sources, draw by hand, and move Library records, notes, matrices, and evidence into a visual workspace.',
            'open' => 'false',
            'board' => '',
        ], $atts, 'sc_library_boards');

        $board_type = in_array($atts['type'], ['whiteboard', 'chalkboard'], true) ? $atts['type'] : 'whiteboard';
        $board_title = sanitize_text_field($atts['title']);
        $board_intro = sanitize_text_field($atts['intro']);
        $board_open = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);
        $board_id = sanitize_text_field($atts['board']);

        self::enqueue_assets();

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-board-studio.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        register_rest_route('sustainable-catalyst/v1', '/library/board-templates', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => SC_Library_Notebook::SCHEMA,
                'templates' => array_values(self::board_templates()),
                'node_types' => array_values(self::node_types()),
                'edge_types' => array_values(self::edge_types()),
            ]),
            'permission_callback' => '__return_true',
        ]);
    }

    public static function board_templates(): array {
        return [
            'blank_whiteboard' => [
                'id' => 'blank_whiteboard',
                'label' => __('Blank Whiteboard', 'sustainable-catalyst-library'),
                'type' => 'whiteboard',
                'description' => __('An open canvas for visual research, evidence mapping, and synthesis.', 'sustainable-catalyst-library'),
                'background' => 'grid',
            ],
            'concept_map' => [
                'id' => 'concept_map',
                'label' => __('Concept Map', 'sustainable-catalyst-library'),
                'type' => 'whiteboard',
                'description' => __('Connect a central concept to definitions, sources, implications, and open questions.', 'sustainable-catalyst-library'),
                'background' => 'dots',
            ],
            'evidence_map' => [
                'id' => 'evidence_map',
                'label' => __('Evidence Map', 'sustainable-catalyst-library'),
                'type' => 'whiteboard',
                'description' => __('Organize claims, supporting evidence, challenges, methods, and gaps.', 'sustainable-catalyst-library'),
                'background' => 'grid',
            ],
            'systems_map' => [
                'id' => 'systems_map',
                'label' => __('Systems Map', 'sustainable-catalyst-library'),
                'type' => 'whiteboard',
                'description' => __('Map actors, stocks, flows, feedback, risks, and intervention points.', 'sustainable-catalyst-library'),
                'background' => 'dots',
            ],
            'blank_chalkboard' => [
                'id' => 'blank_chalkboard',
                'label' => __('Blank Chalkboard', 'sustainable-catalyst-library'),
                'type' => 'chalkboard',
                'description' => __('A dark technical canvas for equations, derivations, code logic, and handwritten reasoning.', 'sustainable-catalyst-library'),
                'background' => 'chalk',
            ],
            'equation_workbench' => [
                'id' => 'equation_workbench',
                'label' => __('Equation Workbench', 'sustainable-catalyst-library'),
                'type' => 'chalkboard',
                'description' => __('Move from a problem statement to notation, variables, derivation, code, tests, and interpretation.', 'sustainable-catalyst-library'),
                'background' => 'chalk_grid',
            ],
            'technical_derivation' => [
                'id' => 'technical_derivation',
                'label' => __('Technical Derivation', 'sustainable-catalyst-library'),
                'type' => 'chalkboard',
                'description' => __('Lay out assumptions, steps, intermediate results, validation checks, and conclusions.', 'sustainable-catalyst-library'),
                'background' => 'chalk',
            ],
        ];
    }

    public static function node_types(): array {
        return [
            'concept' => ['id' => 'concept', 'label' => __('Concept', 'sustainable-catalyst-library')],
            'note' => ['id' => 'note', 'label' => __('Note', 'sustainable-catalyst-library')],
            'question' => ['id' => 'question', 'label' => __('Question', 'sustainable-catalyst-library')],
            'claim' => ['id' => 'claim', 'label' => __('Claim', 'sustainable-catalyst-library')],
            'evidence' => ['id' => 'evidence', 'label' => __('Evidence', 'sustainable-catalyst-library')],
            'source' => ['id' => 'source', 'label' => __('Source', 'sustainable-catalyst-library')],
            'publication' => ['id' => 'publication', 'label' => __('Library publication', 'sustainable-catalyst-library')],
            'matrix' => ['id' => 'matrix', 'label' => __('Translation matrix', 'sustainable-catalyst-library')],
            'equation' => ['id' => 'equation', 'label' => __('Equation or formal object', 'sustainable-catalyst-library')],
            'code' => ['id' => 'code', 'label' => __('Code or algorithm', 'sustainable-catalyst-library')],
            'result' => ['id' => 'result', 'label' => __('Result or interpretation', 'sustainable-catalyst-library')],
        ];
    }

    public static function edge_types(): array {
        return [
            'related_to' => ['id' => 'related_to', 'label' => __('Related to', 'sustainable-catalyst-library')],
            'supports' => ['id' => 'supports', 'label' => __('Supports', 'sustainable-catalyst-library')],
            'challenges' => ['id' => 'challenges', 'label' => __('Challenges', 'sustainable-catalyst-library')],
            'explains' => ['id' => 'explains', 'label' => __('Explains', 'sustainable-catalyst-library')],
            'depends_on' => ['id' => 'depends_on', 'label' => __('Depends on', 'sustainable-catalyst-library')],
            'causes' => ['id' => 'causes', 'label' => __('Causes or influences', 'sustainable-catalyst-library')],
            'contrasts_with' => ['id' => 'contrasts_with', 'label' => __('Contrasts with', 'sustainable-catalyst-library')],
            'derived_from' => ['id' => 'derived_from', 'label' => __('Derived from', 'sustainable-catalyst-library')],
            'validates' => ['id' => 'validates', 'label' => __('Validates', 'sustainable-catalyst-library')],
        ];
    }
}
