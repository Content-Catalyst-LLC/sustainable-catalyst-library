<?php
/**
 * Plugin Name: Sustainable Catalyst Library
 * Plugin URI: https://sustainablecatalyst.com/library/
 * Description: Sustainable Catalyst Library v2.0.1 is a unified living knowledge system with a repaired plugin-owned discovery interface with a public discovery layer, authenticated research workspace, institutional operations, cross-system manifests and activity, a database-inventory-aware large-library index scanner, structured indexing, relationships, research notebooks, sources, Technical Translation Matrices, Whiteboards, Chalkboards, Annotation Studio handwriting, custom books, a Foundations Documentation Library, content planner, complete public registry, roadmap tracker, PostgreSQL and portable research-data exports, planning analytics, dependency intelligence, release coordination, persistent account workspaces, Render synchronization, server-side book and PDF production, Multimedia Studio and video snippet production, evidence reels, collaboration, invited review participants, suggested edits, comments, approvals, record locks, attribution history, a provenance-aware knowledge graph, relationship confidence, orphan and duplicate-concept diagnostics, timeline and place views, Whiteboard graph promotion, Research Librarian workspace orchestration, transparent retrieval reasons, user-confirmed action packets, controlled tool routing, optional site-scoped synthesis, a versioned public API, scoped service keys, signed webhooks, OpenAPI and JSON Schema documentation, a developer portal, embedded Foundation Document records, bundled PDF.js viewing, page-aware full-text PDF extraction, citation exports, PDF migration diagnostics, institutional preservation snapshots, integrity audits, checksums, authority history, supersession chains, archival browsing, retention controls, accessibility and mobile hardening, bounded public-response caching, anonymous REST rate limiting, production-readiness diagnostics, security headers, public record-card layout repair, responsive rendering, frozen editions, authority and version controls, search, filters, and public REST endpoints.
 * Version: 3.5.0
 * Author: Content Catalyst LLC
 * Author URI: https://sustainablecatalyst.com/
 * Text Domain: sustainable-catalyst-library
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_LIBRARY_VERSION', '3.5.0');
define('SC_LIBRARY_FILE', __FILE__);
define('SC_LIBRARY_DIR', plugin_dir_path(__FILE__));
define('SC_LIBRARY_URL', plugin_dir_url(__FILE__));

require_once SC_LIBRARY_DIR . 'includes/class-sc-library-activator.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-taxonomies.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-relationships.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-indexer.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-scanner.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-editor.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-rest.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-admin.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-notebook.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-boards.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-integrations.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-annotations.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-books.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-document-production.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-documentation.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-documents.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-pages.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-multimedia.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-planner.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-portability.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-planning-analytics.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-workspaces.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-collaboration.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-knowledge-graph.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-orchestrator.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-developer-api.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-preservation.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-hardening.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-unified-system.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-shortcodes.php';

register_activation_hook(__FILE__, ['SC_Library_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SC_Library_Activator', 'deactivate']);

final class SC_Library_Plugin {
    private static ?SC_Library_Plugin $instance = null;

    public static function instance(): SC_Library_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        SC_Library_Activator::maybe_upgrade();
        load_plugin_textdomain('sustainable-catalyst-library', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $taxonomies = new SC_Library_Taxonomies();
        $relationships = new SC_Library_Relationships();
        $indexer = new SC_Library_Indexer($relationships);
        $scanner = new SC_Library_Scanner($indexer, $relationships);
        $editor = new SC_Library_Editor($indexer, $relationships);
        $rest = new SC_Library_REST($indexer, $relationships);
        $admin = new SC_Library_Admin($indexer, $relationships);
        $notebook = new SC_Library_Notebook();
        $boards = new SC_Library_Boards();
        $integrations = new SC_Library_Integrations($indexer, $relationships);
        $annotations = new SC_Library_Annotations();
        $books = new SC_Library_Books();
        $document_production = new SC_Library_Document_Production();
        $documentation = new SC_Library_Documentation($indexer, $relationships);
        $foundation_documents = new SC_Library_Foundation_Documents($indexer, $relationships);
        new SC_Library_Foundation_Pages();
        $multimedia = new SC_Library_Multimedia();
        $planner = new SC_Library_Planner($indexer, $relationships);
        $portability = new SC_Library_Portability($indexer, $relationships, $planner);
        $planning_analytics = new SC_Library_Planning_Analytics($planner);
        $workspaces = new SC_Library_Workspaces();
        $collaboration = new SC_Library_Collaboration();
        $knowledge_graph = new SC_Library_Knowledge_Graph($indexer, $relationships);
        $orchestrator = new SC_Library_Orchestrator($indexer, $relationships, $knowledge_graph);
        $developer_api = new SC_Library_Developer_API($indexer, $relationships, $knowledge_graph, $planner);
        $preservation = new SC_Library_Preservation($indexer, $relationships);
        $hardening = new SC_Library_Hardening($indexer, $relationships);
        $unified_system = new SC_Library_Unified_System($indexer, $relationships);
        $shortcodes = new SC_Library_Shortcodes();

        $taxonomies->register_hooks();
        $relationships->register_hooks();
        $indexer->register_hooks();
        $scanner->register_hooks();
        $editor->register_hooks();
        $rest->register_hooks();
        $admin->register_hooks();
        $notebook->register_hooks();
        $boards->register_hooks();
        $integrations->register_hooks();
        $annotations->register_hooks();
        $books->register_hooks();
        $document_production->register_hooks();
        $documentation->register_hooks();
        $foundation_documents->register_hooks();
        $multimedia->register_hooks();
        $planner->register_hooks();
        $portability->register_hooks();
        $planning_analytics->register_hooks();
        $workspaces->register_hooks();
        $collaboration->register_hooks();
        $knowledge_graph->register_hooks();
        $orchestrator->register_hooks();
        $developer_api->register_hooks();
        $preservation->register_hooks();
        $hardening->register_hooks();
        $unified_system->register_hooks();
        $shortcodes->register_hooks();
    }
}

SC_Library_Plugin::instance();
