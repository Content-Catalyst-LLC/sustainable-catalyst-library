<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Taxonomies {
    public const SERIES = 'sc_library_series';
    public const CONCEPT = 'sc_library_concept';

    public function register_hooks(): void {
        add_action('init', [$this, 'register'], 6);
    }

    public function register(): void {
        $post_types = get_option('sc_library_post_types', ['post']);
        $post_types = is_array($post_types) && $post_types ? array_values(array_map('sanitize_key', $post_types)) : ['post'];

        register_taxonomy(self::SERIES, $post_types, [
            'labels' => [
                'name' => __('Library Series', 'sustainable-catalyst-library'),
                'singular_name' => __('Library Series', 'sustainable-catalyst-library'),
                'search_items' => __('Search Library Series', 'sustainable-catalyst-library'),
                'all_items' => __('All Library Series', 'sustainable-catalyst-library'),
                'edit_item' => __('Edit Library Series', 'sustainable-catalyst-library'),
                'update_item' => __('Update Library Series', 'sustainable-catalyst-library'),
                'add_new_item' => __('Add Library Series', 'sustainable-catalyst-library'),
                'new_item_name' => __('New Library Series', 'sustainable-catalyst-library'),
                'menu_name' => __('Library Series', 'sustainable-catalyst-library'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);

        register_taxonomy(self::CONCEPT, $post_types, [
            'labels' => [
                'name' => __('Library Concepts', 'sustainable-catalyst-library'),
                'singular_name' => __('Library Concept', 'sustainable-catalyst-library'),
                'search_items' => __('Search Library Concepts', 'sustainable-catalyst-library'),
                'popular_items' => __('Popular Library Concepts', 'sustainable-catalyst-library'),
                'all_items' => __('All Library Concepts', 'sustainable-catalyst-library'),
                'edit_item' => __('Edit Library Concept', 'sustainable-catalyst-library'),
                'update_item' => __('Update Library Concept', 'sustainable-catalyst-library'),
                'add_new_item' => __('Add Library Concept', 'sustainable-catalyst-library'),
                'new_item_name' => __('New Library Concept', 'sustainable-catalyst-library'),
                'menu_name' => __('Library Concepts', 'sustainable-catalyst-library'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);
    }
}
