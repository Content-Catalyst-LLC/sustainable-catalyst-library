#!/usr/bin/env python3
from pathlib import Path
import re, sys
root = Path(__file__).resolve().parents[1]
plugin = root / 'sustainable-catalyst-library'
main = (plugin / 'sustainable-catalyst-library.php').read_text(encoding='utf-8')
shortcodes = (plugin / 'includes/class-sc-library-shortcodes.php').read_text(encoding='utf-8')
foundation_pages = (plugin / 'includes/class-sc-library-foundation-pages.php').read_text(encoding='utf-8')
bootstrap = (plugin / 'includes/class-sc-library-extension-bootstrap-v402.php').read_text(encoding='utf-8')
checks = {
    'plugin header 4.0.2': 'Version: 4.0.2' in main,
    'plugin constant 4.0.2': "SC_LIBRARY_VERSION', '4.0.2" in main,
    'Foundations integration 2.1.6': "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.6" in (plugin / 'includes/class-sc-library-foundation-system-v200.php').read_text(encoding='utf-8'),
    'guarded extension bootstrap used': 'SC_Library_Extension_Bootstrap_V402::boot();' in foundation_pages,
    'old unguarded extension constructors removed': 'new SC_Library_Document_Public_Repository();' not in foundation_pages,
    'shortcode Throwable boundary': 'catch (Throwable $error)' in shortcodes,
    'server fallback renderer': 'render_emergency_fallback' in shortcodes,
    'runtime diagnostics': 'sc_library_last_public_runtime_error' in shortcodes,
    'fallback marker': 'data-sc-library-recovery="1"' in shortcodes,
    'module count': 'MODULE_COUNT = 25' in bootstrap,
    'constructor isolation': 'catch (Throwable $error)' in bootstrap,
}
for filename in ['class-sc-library-pdf-to-document.php', 'class-sc-library-pdf-conversion-reliability.php', 'class-sc-library-pdf-bulk-import-repair.php', 'class-sc-library-document-ocr-processing.php', 'class-sc-library-document-ocr-reliability.php', 'class-sc-library-document-repository-hardening.php', 'class-sc-library-document-public-repository.php', 'class-sc-library-citation-source-manager.php', 'class-sc-library-citation-source-reliability.php', 'class-sc-library-scholarly-library-connectors.php', 'class-sc-library-connector-holdings-reliability.php', 'class-sc-library-evidence-claim-linking.php', 'class-sc-library-connected-research-environment.php', 'class-sc-library-connected-research-reliability.php', 'class-sc-library-source-versioning-integrity.php', 'class-sc-library-topics-concepts-relationships.php', 'class-sc-library-knowledge-pathways-article-maps.php', 'class-sc-library-cross-product-research-handoffs.php', 'class-sc-library-research-quality-governance.php', 'class-sc-library-institutional-collections-archives.php', 'class-sc-library-research-librarian-document-intelligence.php', 'class-sc-library-collaborative-review-publishing.php', 'class-sc-library-public-api-export-federation.php', 'class-sc-library-connected-institutional-platform.php', 'class-sc-library-discovery-interface-reliability.php']:
    checks['module shipped: ' + filename] = (plugin / 'includes' / filename).is_file()
    checks['module mapped: ' + filename] = ("'" + filename + "' =>") in bootstrap
for class_name in ['SC_Library_PDF_To_Document', 'SC_Library_PDF_Conversion_Reliability', 'SC_Library_PDF_Bulk_Import_Repair', 'SC_Library_Document_OCR_Processing', 'SC_Library_Document_OCR_Reliability', 'SC_Library_Document_Repository_Hardening', 'SC_Library_Document_Public_Repository', 'SC_Library_Citation_Source_Manager', 'SC_Library_Citation_Source_Reliability', 'SC_Library_Scholarly_Library_Connectors', 'SC_Library_Connector_Holdings_Reliability', 'SC_Library_Evidence_Claim_Linking', 'SC_Library_Connected_Research_Environment', 'SC_Library_Connected_Research_Reliability', 'SC_Library_Source_Versioning_Integrity', 'SC_Library_Topics_Concepts_Relationships', 'SC_Library_Knowledge_Pathways_Article_Maps', 'SC_Library_Cross_Product_Research_Handoffs', 'SC_Library_Research_Quality_Governance', 'SC_Library_Institutional_Collections_Archives', 'SC_Library_Research_Librarian_Document_Intelligence', 'SC_Library_Collaborative_Review_Publishing', 'SC_Library_Public_API_Export_Federation', 'SC_Library_Connected_Institutional_Platform', 'SC_Library_Discovery_Interface_Reliability']:
    checks['class mapped: ' + class_name] = ("=> '" + class_name + "'") in bootstrap
failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if failed:
    print('FAILED: ' + ', '.join(failed), file=sys.stderr)
    raise SystemExit(1)
print(f'PASS: {len(checks)} fatal-recovery contract checks')
