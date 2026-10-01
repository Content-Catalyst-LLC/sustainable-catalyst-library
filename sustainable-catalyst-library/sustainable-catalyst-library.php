<?php
/**
 * Plugin Name: Sustainable Catalyst Library
 * Plugin URI: https://sustainablecatalyst.com/knowledge-libraries/
 * Description: Sustainable Catalyst Library 5.73.0 Python Retrieval & Search Orchestration.
 * Version: 5.73.0
 * Author: Content Catalyst LLC
 * Author URI: https://sustainablecatalyst.com/
 * Text Domain: sustainable-catalyst-library
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_LIBRARY_VERSION', '5.73.0');
define('SC_LIBRARY_WORDPRESS_ROLE', 'thin-adapter');
define('SC_LIBRARY_WORDPRESS_AUTHORITATIVE', false);
define('SC_LIBRARY_LEGACY_LOCAL_RESEARCH_AUTHORITY', false);
define('SC_LIBRARY_RESEARCH_RUNTIME_AUTHORITY', 'library-api');
define('SC_CARBON_NATURE_VERSION', '0.5.0');
define('SC_ENERGY_SYSTEMS_VERSION', '1.5.0');
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
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundations-first-edition-v210.php';

require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-system-v200.php';
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
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-publications.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-field-spotlights.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-python-backend.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-publication-visualizations.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-knowledge-landscape.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-retrieval-evaluation.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-temporal-evolution.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-methodology-intelligence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-research-gap-novelty.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-native-graph-runtime.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-literature-review.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-living-evidence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-ingestion-job-fabric.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-execution-fabric.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-artifact-storage.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-pipeline-engine.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-compute-broker.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-research-corpus-builder.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-unified-runtime.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-execution-lineage.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-python-operations.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-dynamic-explorer.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-capability-hub.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-research-network-console.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-homepage-console.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-public-interface-assets.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-institutional-research-sources.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-global-source-federation-registry.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-original-language-corpus.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-ocr-htr-transcription-lineage.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-linguistic-corpus.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-cross-language-resolution.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-source-transparency.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-global-knowledge-federation.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-runtime-authority.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-api-v1.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-web-application.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-identity-access.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-wordpress-thin-adapter.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-public-routing-bridge.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-cross-product-service-integration.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-state-migration.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-runtime-certification.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-cross-civilizational-linking.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-translation-alignment.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-biomedical-evidence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-fda-regulatory-intelligence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-medical-terminology.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-clinical-trial-intelligence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-biomedical-evidence-grading.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-biomedical-evidence-graph.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-institutional-research-network.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-private-organizational-knowledge.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-carbon-nature-intelligence.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-energy-systems-intelligence.php';

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
        $publications = new SC_Library_Publications();
        $field_spotlights = new SC_Library_Field_Spotlights();
        $python_backend = new SC_Library_Python_Backend();
        $publication_visualizations = new SC_Library_Publication_Visualizations();
        $knowledge_landscape = new SC_Library_Knowledge_Landscape();
        $retrieval_evaluation = new SC_Library_Retrieval_Evaluation();
        $temporal_evolution = new SC_Library_Temporal_Evolution();
        $methodology_intelligence = new SC_Library_Methodology_Intelligence();
        $research_gap_novelty = new SC_Library_Research_Gap_Novelty();
        $native_graph_runtime = new SC_Library_Native_Graph_Runtime();
        $literature_review = new SC_Library_Literature_Review();
        $living_evidence = new SC_Library_Living_Evidence();
        $ingestion_job_fabric = new SC_Library_Ingestion_Job_Fabric();
        $execution_fabric = new SC_Library_Execution_Fabric();
        $artifact_storage = new SC_Library_Artifact_Storage();
        $pipeline_engine = new SC_Library_Pipeline_Engine();
        $compute_broker = new SC_Library_Compute_Broker();
        $research_corpus_builder = new SC_Library_Research_Corpus_Builder();
        $unified_runtime = new SC_Library_Unified_Runtime();
        $execution_lineage = new SC_Library_Execution_Lineage();
        $python_operations = new SC_Library_Python_Operations();
        $dynamic_explorer = new SC_Library_Dynamic_Explorer();
        $capability_hub = new SC_Library_Capability_Hub();
        $research_network_console = new SC_Library_Research_Network_Console();
        $homepage_console = new SC_Library_Homepage_Console();
        $public_interface_assets = new SC_Library_Public_Interface_Assets();
        $institutional_research_sources = new SC_Library_Institutional_Research_Sources();
        $global_source_federation_registry = new SC_Library_Global_Source_Federation_Registry();
        $original_language_corpus = new SC_Library_Original_Language_Corpus();
        $ocr_htr_transcription_lineage = new SC_Library_OCR_HTR_Transcription_Lineage();
        $linguistic_corpus = new SC_Library_Linguistic_Corpus();
        $source_transparency = new SC_Library_Source_Transparency();
        $global_knowledge_federation = new SC_Library_Global_Knowledge_Federation();
        $runtime_authority = new SC_Library_Runtime_Authority();
        $library_api_v1 = new SC_Library_API_V1();
        $library_web_application = new SC_Library_Web_Application();
        $wordpress_thin_adapter = new SC_Library_WordPress_Thin_Adapter();
        $public_routing_bridge = new SC_Library_Public_Routing_Bridge();
        $cross_product_service_integration = new SC_Library_Cross_Product_Service_Integration();
        $state_migration = new SC_Library_State_Migration();
        $runtime_certification = new SC_Library_Runtime_Certification();
        $cross_civilizational_linking = new SC_Library_Cross_Civilizational_Linking();
        $translation_alignment = new SC_Library_Translation_Alignment();
        $cross_language_resolution = new SC_Library_Cross_Language_Resolution();
        $biomedical_evidence = new SC_Library_Biomedical_Evidence();
        $fda_regulatory_intelligence = new SC_Library_FDA_Regulatory_Intelligence();
        $medical_terminology = new SC_Library_Medical_Terminology();
        $clinical_trial_intelligence = new SC_Library_Clinical_Trial_Intelligence();
        $biomedical_evidence_grading = new SC_Library_Biomedical_Evidence_Grading();
        $biomedical_evidence_graph = new SC_Library_Biomedical_Evidence_Graph();
        $institutional_research_network = new SC_Library_Institutional_Research_Network();
        $private_organizational_knowledge = new SC_Library_Private_Organizational_Knowledge();
        $carbon_nature_intelligence = new SC_Library_Carbon_Nature_Intelligence();
        $energy_systems_intelligence = new SC_Library_Energy_Systems_Intelligence();

        // v4.2.0 is an optional, contained editorial surface. A Spotlight
        // startup failure must not terminate the public Research Library.
        try {
            $spotlight_path = SC_LIBRARY_DIR . 'includes/class-sc-library-homepage-spotlight.php';
            if (!is_readable($spotlight_path)) {
                throw new RuntimeException('Homepage Spotlight module file is missing or unreadable.');
            }
            require_once $spotlight_path;
            if (!class_exists('SC_Library_Homepage_Spotlight', false)) {
                throw new RuntimeException('Homepage Spotlight class was not declared.');
            }
            new SC_Library_Homepage_Spotlight();
            update_option('sc_library_homepage_spotlight_v410_status', [
                'active' => true,
                'version' => '4.2.0',
                'error' => '',
                'timestamp' => current_time('mysql', true),
            ], false);
        } catch (Throwable $error) {
            error_log('[Sustainable Catalyst Library] Homepage Spotlight startup failure: ' . $error->getMessage());
            update_option('sc_library_homepage_spotlight_v410_status', [
                'active' => false,
                'version' => '4.2.0',
                'error' => $error->getMessage(),
                'timestamp' => current_time('mysql', true),
            ], false);
        }

        $cross_product_service_integration->register_hooks();
        $state_migration->register_hooks();
        $runtime_certification->register_hooks();
        $legacy_state_retired = (bool) get_option(SC_Library_State_Migration::RETIRED_OPTION, false);
        $taxonomies->register_hooks();
        $relationships->register_hooks();
        $indexer->register_hooks();
        $scanner->register_hooks();
        $editor->register_hooks();
        $rest->register_hooks();
        $admin->register_hooks();
        if (!$legacy_state_retired) { $notebook->register_hooks(); }
        if (!$legacy_state_retired) { $boards->register_hooks(); }
        $integrations->register_hooks();
        $annotations->register_hooks();
        $books->register_hooks();
        $document_production->register_hooks();
        $documentation->register_hooks();
        $foundation_documents->register_hooks();
        $multimedia->register_hooks();
        if (!$legacy_state_retired) { $planner->register_hooks(); }
        $portability->register_hooks();
        $planning_analytics->register_hooks();
        if (!$legacy_state_retired) { $workspaces->register_hooks(); }
        if (!$legacy_state_retired) { $collaboration->register_hooks(); }
        if (!$legacy_state_retired) { $knowledge_graph->register_hooks(); }
        if (!$legacy_state_retired) { $orchestrator->register_hooks(); }
        if (!$legacy_state_retired) { $developer_api->register_hooks(); }
        $preservation->register_hooks();
        $hardening->register_hooks();
        $unified_system->register_hooks();
        $shortcodes->register_hooks();
        $publications->register_hooks();
        $field_spotlights->register_hooks();
        $python_backend->register_hooks();
        $publication_visualizations->register_hooks();
        $knowledge_landscape->register_hooks();
        $retrieval_evaluation->register_hooks();
        $temporal_evolution->register_hooks();
        $methodology_intelligence->register_hooks();
        $research_gap_novelty->register_hooks();
        $native_graph_runtime->register_hooks();
        $literature_review->register_hooks();
        $living_evidence->register_hooks();
        $ingestion_job_fabric->register_hooks();
        $execution_fabric->register_hooks();
        $artifact_storage->register_hooks();
        $pipeline_engine->register_hooks();
        $compute_broker->register_hooks();
        $research_corpus_builder->register_hooks();
        $unified_runtime->register_hooks();
        $execution_lineage->register_hooks();
        $python_operations->register_hooks();
        $dynamic_explorer->register_hooks();
        $capability_hub->register_hooks();
        $research_network_console->register_hooks();
        $homepage_console->register_hooks();
        $public_interface_assets->register_hooks();
        $institutional_research_sources->register_hooks();
        $global_source_federation_registry->register_hooks();
        $original_language_corpus->register_hooks();
        $ocr_htr_transcription_lineage->register_hooks();
        $linguistic_corpus->register_hooks();
        $source_transparency->register_hooks();
        $global_knowledge_federation->register_hooks();
        $runtime_authority->register_hooks();
        $library_api_v1->register_hooks();
        $library_web_application->register_hooks();
        $wordpress_thin_adapter->register_hooks();
        $cross_civilizational_linking->register_hooks();
        $translation_alignment->register_hooks();
        $cross_language_resolution->register_hooks();
        $biomedical_evidence->register_hooks();
        $fda_regulatory_intelligence->register_hooks();
        $medical_terminology->register_hooks();
        $clinical_trial_intelligence->register_hooks();
        $biomedical_evidence_grading->register_hooks();
        $biomedical_evidence_graph->register_hooks();
        $institutional_research_network->register_hooks();
        $private_organizational_knowledge->register_hooks();
        $carbon_nature_intelligence->register_hooks();
        $energy_systems_intelligence->register_hooks();
    }
}

SC_Library_Plugin::instance();
