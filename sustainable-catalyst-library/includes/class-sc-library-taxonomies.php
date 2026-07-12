<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Taxonomies {
    public const SERIES = 'sc_library_series';
    public const CONCEPT = 'sc_library_concept';
    public const COLLECTION = 'sc_library_collection';
    public const DOCUMENT_CATEGORY = 'sc_library_document_category';

    public function register_hooks(): void {
        add_action('init', [$this, 'register'], 6);
    }

    public function register(): void {
        $post_types = get_option('sc_library_post_types', ['post']);
        $post_types = is_array($post_types) && $post_types ? array_values(array_map('sanitize_key', $post_types)) : ['post'];
        if (class_exists('SC_Library_Planner')) {
            $post_types[] = SC_Library_Planner::POST_TYPE;
            $post_types = array_values(array_unique($post_types));
        }

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


        register_taxonomy(self::COLLECTION, $post_types, [
            'labels' => [
                'name' => __('Library Collections', 'sustainable-catalyst-library'),
                'singular_name' => __('Library Collection', 'sustainable-catalyst-library'),
                'search_items' => __('Search Library Collections', 'sustainable-catalyst-library'),
                'all_items' => __('All Library Collections', 'sustainable-catalyst-library'),
                'edit_item' => __('Edit Library Collection', 'sustainable-catalyst-library'),
                'update_item' => __('Update Library Collection', 'sustainable-catalyst-library'),
                'add_new_item' => __('Add Library Collection', 'sustainable-catalyst-library'),
                'new_item_name' => __('New Library Collection', 'sustainable-catalyst-library'),
                'menu_name' => __('Library Collections', 'sustainable-catalyst-library'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);

        register_taxonomy(self::DOCUMENT_CATEGORY, $post_types, [
            'labels' => [
                'name' => __('Documentation Categories', 'sustainable-catalyst-library'),
                'singular_name' => __('Documentation Category', 'sustainable-catalyst-library'),
                'search_items' => __('Search Documentation Categories', 'sustainable-catalyst-library'),
                'all_items' => __('All Documentation Categories', 'sustainable-catalyst-library'),
                'parent_item' => __('Parent Documentation Category', 'sustainable-catalyst-library'),
                'edit_item' => __('Edit Documentation Category', 'sustainable-catalyst-library'),
                'update_item' => __('Update Documentation Category', 'sustainable-catalyst-library'),
                'add_new_item' => __('Add Documentation Category', 'sustainable-catalyst-library'),
                'new_item_name' => __('New Documentation Category', 'sustainable-catalyst-library'),
                'menu_name' => __('Documentation Categories', 'sustainable-catalyst-library'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => false,
            'query_var' => false,
        ]);
    }
}
