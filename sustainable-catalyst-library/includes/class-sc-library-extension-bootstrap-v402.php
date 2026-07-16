<?php
/**
 * Isolated production-extension bootstrap for Knowledge Library v4.0.2.
 *
 * A failure in an optional extension is recorded and isolated instead of
 * terminating the public Research Library request.
 */
if (!defined('ABSPATH')) { exit; }

final class SC_Library_Extension_Bootstrap_V402 {
    public const VERSION = '4.0.2';
    public const MODULE_COUNT = 25;
    private const STATUS_OPTION = 'sc_library_extension_bootstrap_v402_status';

    /** @var array<string,string> */
    private const MODULES = [
        'class-sc-library-pdf-to-document.php' => 'SC_Library_PDF_To_Document',
        'class-sc-library-pdf-conversion-reliability.php' => 'SC_Library_PDF_Conversion_Reliability',
        'class-sc-library-pdf-bulk-import-repair.php' => 'SC_Library_PDF_Bulk_Import_Repair',
        'class-sc-library-document-ocr-processing.php' => 'SC_Library_Document_OCR_Processing',
        'class-sc-library-document-ocr-reliability.php' => 'SC_Library_Document_OCR_Reliability',
        'class-sc-library-document-repository-hardening.php' => 'SC_Library_Document_Repository_Hardening',
        'class-sc-library-document-public-repository.php' => 'SC_Library_Document_Public_Repository',
        'class-sc-library-citation-source-manager.php' => 'SC_Library_Citation_Source_Manager',
        'class-sc-library-citation-source-reliability.php' => 'SC_Library_Citation_Source_Reliability',
        'class-sc-library-scholarly-library-connectors.php' => 'SC_Library_Scholarly_Library_Connectors',
        'class-sc-library-connector-holdings-reliability.php' => 'SC_Library_Connector_Holdings_Reliability',
        'class-sc-library-evidence-claim-linking.php' => 'SC_Library_Evidence_Claim_Linking',
        'class-sc-library-connected-research-environment.php' => 'SC_Library_Connected_Research_Environment',
        'class-sc-library-connected-research-reliability.php' => 'SC_Library_Connected_Research_Reliability',
        'class-sc-library-source-versioning-integrity.php' => 'SC_Library_Source_Versioning_Integrity',
        'class-sc-library-topics-concepts-relationships.php' => 'SC_Library_Topics_Concepts_Relationships',
        'class-sc-library-knowledge-pathways-article-maps.php' => 'SC_Library_Knowledge_Pathways_Article_Maps',
        'class-sc-library-cross-product-research-handoffs.php' => 'SC_Library_Cross_Product_Research_Handoffs',
        'class-sc-library-research-quality-governance.php' => 'SC_Library_Research_Quality_Governance',
        'class-sc-library-institutional-collections-archives.php' => 'SC_Library_Institutional_Collections_Archives',
        'class-sc-library-research-librarian-document-intelligence.php' => 'SC_Library_Research_Librarian_Document_Intelligence',
        'class-sc-library-collaborative-review-publishing.php' => 'SC_Library_Collaborative_Review_Publishing',
        'class-sc-library-public-api-export-federation.php' => 'SC_Library_Public_API_Export_Federation',
        'class-sc-library-connected-institutional-platform.php' => 'SC_Library_Connected_Institutional_Platform',
        'class-sc-library-discovery-interface-reliability.php' => 'SC_Library_Discovery_Interface_Reliability',
    ];

    /** @var array<string,object> */
    private static array $instances = [];

    /** @var array<string,string> */
    private static array $errors = [];

    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) { return; }
        self::$booted = true;

        foreach (self::MODULES as $filename => $class_name) {
            try {
                $path = __DIR__ . '/' . $filename;
                if (!is_readable($path)) {
                    throw new RuntimeException('Module file is missing or unreadable: ' . $filename);
                }

                require_once $path;
                if (!class_exists($class_name, false)) {
                    throw new RuntimeException('Expected class was not declared by ' . $filename);
                }

                self::$instances[$class_name] = new $class_name();
            } catch (Throwable $error) {
                self::$errors[$class_name] = $error->getMessage();
                error_log('[Sustainable Catalyst Library] Extension startup failure: ' . $class_name . ' - ' . $error->getMessage());
            }
        }

        if (function_exists('update_option')) {
            update_option(self::STATUS_OPTION, self::status(), false);
        }
        if (function_exists('add_action')) {
            add_action('admin_notices', [self::class, 'admin_notice'], 1);
        }
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        return [
            'version' => self::VERSION,
            'expected' => self::MODULE_COUNT,
            'active' => count(self::$instances),
            'errors' => self::$errors,
            'timestamp' => function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s'),
        ];
    }

    public static function admin_notice(): void {
        if (empty(self::$errors) || !function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>';
        echo esc_html__('Knowledge Library isolated an extension startup error.', 'sustainable-catalyst-library');
        echo '</strong> ';
        echo esc_html__('The public Research Library remains available through its protected core renderer.', 'sustainable-catalyst-library');
        echo '</p><details><summary>' . esc_html__('View technical details', 'sustainable-catalyst-library') . '</summary><ul>';
        foreach (self::$errors as $class_name => $message) {
            echo '<li><code>' . esc_html($class_name) . '</code>: ' . esc_html($message) . '</li>';
        }
        echo '</ul></details></div>';
    }
}
