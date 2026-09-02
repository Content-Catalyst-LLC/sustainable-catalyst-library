<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.6.0 R3 capability-preserving Research Library interface.
 *
 * The restored v5.4 public page is the preservation baseline. Heavy Library
 * applications are no longer all rendered into the first page response.
 * Instead, this hub exposes every protected capability as a stable, grouped
 * entry point and mounts the selected existing shortcode in a same-origin
 * frame only after the visitor opens it. This preserves shortcode behavior,
 * WordPress authentication, registered styles/scripts and existing APIs.
 */
final class SC_Library_Capability_Hub {
    public const VERSION = '5.6.0.31';
    private const QUERY_ARG = 'sc_library_capability';

    public function register_hooks(): void {
        add_shortcode('sc_library_capability_hub', [$this, 'shortcode']);
        add_action('template_redirect', [$this, 'maybe_render_capability_frame'], 0);
    }

    /** @return array<string,array<string,mixed>> */
    public static function registry(): array {
        return [
            'relationship-browser' => self::cap('explore', 'knowledge-relationship-browser', 'Relationships', 'Explore explicit topics, concepts and declared relationships.', '[sc_knowledge_relationship_browser title="Explore Topics, Concepts, and Relationships"]', []),
            'pathway-recommendations' => self::cap('explore', 'pathway-recommendations', 'Pathway Recommendations', 'Surface relevant Knowledge Pathways without rendering the full pathway system up front.', '[sc_pathway_recommendations limit="6"]', []),
            'curated-spaces' => self::cap('explore', 'curated-knowledge-spaces', 'Curated Knowledge Spaces', 'Open editor-governed research collections, exhibitions and knowledge spaces.', '[sc_research_curated_spaces title="Research Collections, Exhibitions & Curated Knowledge Spaces"]', ['curated-knowledge-spaces-title']),
            'publication-graph' => self::cap('explore', 'publication-research-graph-section', 'Publications ↔ Research Graph', 'Trace a publication into its declared public research context.', '[sc_publications_research_graph title="Publications ↔ Research Graph"]', ['publication-research-graph', 'publication-research-graph-title']),

            'research-access' => self::cap('access', 'research-access', 'Search Libraries & Research', 'Search libraries, universities, repositories, archives and scholarly sources.', '[sc_research_access providers="internetarchive,mit,harvard,loc,ucd,crossref,openalex,datacite,pubmed,pmc,europepmc,arxiv" default_providers="internetarchive,mit,harvard,ucd,openalex,europepmc,arxiv" limit="5" title="Search Libraries, University Research, and Scholarly Sources"]', ['research-access-title']),
            'institutional-network' => self::cap('access', 'institutional-research-network', 'Institutional Research Network', 'Connect to supported university and research-institution discovery systems.', '[sc_institutional_connector_network title="Institutional Research Network"]', []),
            'public-library-network' => self::cap('access', 'public-library-network', 'Public Library Network', 'Use public-library profiles and local access relationships without handing over library passwords.', '[sc_public_library_network title="Public Library Network & Local Access"]', []),
            'access-intelligence' => self::cap('access', 'access-intelligence-ii', 'Access Intelligence II', 'Compare legitimate open, library, institutional, request and physical access routes.', '[sc_access_intelligence_ii title="Access Intelligence II"]', []),
            'global-discovery' => self::cap('access', 'global-research-discovery', 'Global Research Discovery', 'Search canonical public Library records together with explicitly published federation metadata.', '[sc_global_research_discovery title="Global Research Discovery & Federated Search"]', ['global-research-discovery-title']),
            'research-identity' => self::cap('access', 'research-identity-authority', 'Research Identity & Authority', 'Resolve DOI, ORCID, ROR, ISBN, ISSN, Wikidata and PMID identifiers without collapsing ambiguity.', '[sc_research_identity_authority title="Research Identity, Authority & Persistent Identifier Network"]', ['research-identity-authority-title']),

            'research-home' => self::cap('research', 'personal-research-environment', 'Research Home', 'Resume account-scoped research state across the Library without creating a replacement data silo.', '[sc_personal_research_environment title="Unified Personal Research Environment"]', ['personal-research-environment-title']),
            'my-library' => self::cap('research', 'personal-library', 'My Library', 'Maintain a private personal collection, notes, progress and recommendations.', '[sc_personal_library title="My Library"]', ['personal-library-title']),
            'saved-research' => self::cap('research', 'saved-research', 'Saved Research & Queue', 'Return to saved searches, watchlists and queued research work.', '[sc_research_continuity title="Saved Research & Queue"]', ['saved-research-title']),
            'research-projects' => self::cap('research', 'research-projects', 'Research Projects & Source Bundles', 'Organize research around owned projects and references-only source bundles.', '[sc_unified_research_projects title="Research Projects & Source Bundles"]', ['research-projects-title']),
            'reading-notebooks' => self::cap('research', 'reading-notebooks', 'Reading, Notebook & Annotation', 'Keep project-linked reading notes, reusable excerpts and precise source annotations.', '[sc_reading_notebook_workspace title="Reading, Notebook & Annotation Workspace"]', ['reading-notebooks-title']),

            'evidence-matrix' => self::cap('evidence', 'evidence-matrix', 'Evidence Matrix', 'Compare claims against explicitly linked evidence, qualification, contradiction and context.', '[sc_evidence_matrix_workspace title="Evidence Matrix & Claim Intelligence"]', ['evidence-matrix-title']),
            'knowledge-graph' => self::cap('evidence', 'knowledge-graph-evidence', 'Knowledge Graph & Evidence', 'See the explicit project relationships already stored across research objects.', '[sc_knowledge_graph_evidence_intelligence title="Knowledge Graph & Evidence Intelligence"]', ['knowledge-graph-evidence-title']),
            'public-evidence' => self::cap('evidence', 'public-evidence-claims', 'Public Evidence & Claims', 'Trace published claims into explicitly linked public evidence and sources.', '[sc_public_evidence_claim_navigation title="Public Evidence & Claim Navigation"]', ['public-evidence-claims-title']),
            'metadata-quality' => self::cap('evidence', 'metadata-quality', 'Metadata Quality & Entity Resolution', 'Review metadata completeness and authority-resolution candidates without silent merges.', '[sc_metadata_quality_center title="Metadata Quality & Entity Resolution"]', ['metadata-quality-title']),

            'research-rooms' => self::cap('collaborate', 'collaborative-research-rooms', 'Collaborative Research Rooms', 'Share bounded project context with explicit roles instead of exposing an entire private project.', '[sc_collaborative_research_rooms title="Collaborative Research Rooms"]', ['collaborative-research-rooms-title']),
            'team-libraries' => self::cap('collaborate', 'institutional-team-libraries', 'Institutional & Team Libraries', 'Build durable shared reference collections without absorbing personal research.', '[sc_institutional_team_libraries title="Institutional & Team Libraries"]', ['institutional-team-libraries-title']),
            'federation' => self::cap('collaborate', 'global-research-federation', 'Global Research Federation', 'Exchange governed references-only research metadata while preserving provenance and privacy boundaries.', '[sc_global_research_federation title="Global Research Federation"]', ['global-research-federation-title']),
            'workspace-continuity' => self::cap('collaborate', 'workspace-continuity', 'Library ↔ Workspace Continuity', 'Prepare explicit references-only handoffs into Workspace and return to the same Library context.', '[sc_library_workspace_continuity title="Library ↔ Workspace Continuity"]', ['workspace-continuity-title']),
            'project-librarian' => self::cap('collaborate', 'research-librarian', 'Project-Aware Research Librarian', 'Use private project context to identify deterministic gaps and next research steps.', '[sc_research_librarian_ii title="Research Librarian II — Project-Aware Guidance"]', ['research-librarian-title']),
            'research-librarian' => self::cap('collaborate', 'research-front-door', 'Ask the Research Librarian', 'Search site-scoped knowledge and get bounded guidance for understanding, access and next steps.', '[sc_research_librarian_orchestrator]', ['research-front-door-title']),

            'citation-studio' => self::cap('produce', 'citation-studio', 'Citation Studio', 'Save, organize, format and export research sources.', '[sc_citation_studio limit="100" style="harvard"]', ['citation-studio-title']),
            'document-builder' => self::cap('produce', 'research-document-builder', 'Research Document Builder', 'Turn selected sources and your own analysis into source-aware DOCX or PDF outputs.', '[sc_research_document_builder limit="100" style="harvard"]', ['research-document-builder-title']),
            'research-workspace' => self::cap('produce', 'research-workspace', 'Research Workspace', 'Keep sources, notes, citations, structures and drafts together.', '[sc_library_unified_workspace]', ['research-workspace-title']),
            'research-portability' => self::cap('produce', 'research-portability', 'Research Portability & Preservation', 'Export owned projects as checksummed preservation packages without making private research public.', '[sc_research_portability title="Research Portability & Preservation"]', []),
            'api-interoperability' => self::cap('produce', 'library-api-interoperability', 'Library API, Embeds & Interoperability', 'Reuse already-public Library knowledge through bounded public interfaces and embeds.', '[sc_library_api_interoperability title="Library API, Embeds & Interoperability"]', ['library-api-interoperability-title']),
            'connected-public-research' => self::cap('produce', 'connected-public-research', 'Connected Public Research', 'Move from public records into their declared one-hop research context.', '[sc_connected_public_research title="Connected Public Research Infrastructure"]', ['connected-public-research-title']),
            'open-courses' => self::cap('produce', 'open-course-finder', 'Open Course Finder', 'Find reviewed open courses by subject, level, access model and Knowledge Pathway.', '[sc_open_course_finder title="Find Free and Open Courses" show_providers="true"]', ['open-course-finder-title']),
            'open-learning' => self::cap('produce', 'open-learning-ii', 'Open Learning II', 'Build transparent learning routes without confusing planning with provider enrollment.', '[sc_open_learning_ii title="Open Learning II — Build a Learning Route"]', []),
            'research-infrastructure' => self::cap('produce', 'research-infrastructure', 'Research Infrastructure', 'Inspect production readiness, Foundation Documents, institutional research and archival infrastructure.', '[sc_library_readiness_status show_categories="true"]\n[sc_institutional_research_portal documents="12" units="0" compact="true" featured="6"]', ['research-infrastructure-title']),
        ];
    }

    private static function cap(string $group, string $anchor, string $label, string $summary, string $shortcode, array $aliases): array {
        return compact('group', 'anchor', 'label', 'summary', 'shortcode', 'aliases');
    }

    /** @return array<string,array<string,string>> */
    public static function groups(): array {
        return [
            'explore' => ['label' => 'Explore Knowledge', 'summary' => 'Relationships, pathways, curated spaces and publication context.'],
            'access' => ['label' => 'Find & Access Research', 'summary' => 'Libraries, universities, scholarly sources, archives, identifiers and legitimate access routes.'],
            'research' => ['label' => 'My Research', 'summary' => 'Personal collections, saved research, projects, source bundles and reading notebooks.'],
            'evidence' => ['label' => 'Evidence & Analysis', 'summary' => 'Evidence matrices, explicit graphs, public claim traces and metadata governance.'],
            'collaborate' => ['label' => 'Collaborate & Connect', 'summary' => 'Research Rooms, Team Libraries, federation, Workspace continuity and librarian guidance.'],
            'produce' => ['label' => 'Produce & Preserve', 'summary' => 'Citations, documents, workspace, portability, APIs, open learning and institutional infrastructure.'],
        ];
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Library Capability Map',
            'intro' => 'The complete Library stays visible by research area. Open a capability only when you need the full application; heavy tools remain inside a bounded workspace.',
            'default_group' => 'explore',
            'display' => 'tabbed',
            'exclude_groups' => '',
            'exclude_capabilities' => '',
        ], $atts, 'sc_library_capability_hub');

        wp_enqueue_style('sc-library-capability-hub-v560r3', SC_LIBRARY_URL . 'assets/css/sc-library-capability-hub-v560r3.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-capability-hub-v560r3', SC_LIBRARY_URL . 'assets/js/sc-library-capability-hub-v560r3.js', [], SC_LIBRARY_VERSION, true);
        $page_url = is_singular() ? get_permalink() : home_url('/knowledge-libraries/');
        wp_localize_script('sc-library-capability-hub-v560r3', 'SCLibraryCapabilityHubV560R3', [
            'frameBase' => esc_url_raw(remove_query_arg([self::QUERY_ARG, 'library_legacy'], $page_url)),
            'queryArg' => self::QUERY_ARG,
            'version' => self::VERSION,
            'strings' => [
                'open' => __('Open', 'sustainable-catalyst-library'),
                'close' => __('Close workspace', 'sustainable-catalyst-library'),
                'loading' => __('Opening Library capability…', 'sustainable-catalyst-library'),
            ],
        ]);

        $registry = self::registry();
        $groups = self::groups();
        $excluded_caps = array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', (string) $atts['exclude_capabilities'])));
        foreach ($excluded_caps as $cap_key) { unset($registry[$cap_key]); }
        $display = sanitize_key((string) $atts['display']);
        if (!in_array($display, ['tabbed', 'expanded'], true)) { $display = 'tabbed'; }
        $excluded = array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', (string) $atts['exclude_groups'])));
        foreach ($excluded as $group_key) { unset($groups[$group_key]); }
        if (!$groups) { return ''; }
        $default_group = sanitize_key((string) $atts['default_group']);
        if (!isset($groups[$default_group])) { $default_group = (string) array_key_first($groups); }
        $instance = 'sc-library-capability-hub-' . wp_rand(1000, 999999);
        ob_start(); ?>
        <section class="sc-library-capability-hub sc-library-capability-hub--<?php echo esc_attr($display); ?>" id="<?php echo esc_attr($instance); ?>" data-sc-library-capability-hub data-display="<?php echo esc_attr($display); ?>">
            <header class="sc-library-capability-hub__header">
                <p class="sc-library-capability-hub__kicker"><?php esc_html_e('Complete Research System', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                <p><?php echo esc_html((string) $atts['intro']); ?></p>
            </header>
            <nav class="sc-library-capability-hub__group-nav" aria-label="<?php esc_attr_e('Research Library capability groups', 'sustainable-catalyst-library'); ?>">
                <?php foreach ($groups as $group_key => $group) : ?>
                    <button type="button" data-capability-group-button="<?php echo esc_attr($group_key); ?>" aria-selected="<?php echo $group_key === $default_group ? 'true' : 'false'; ?>" class="<?php echo $group_key === $default_group ? 'is-active' : ''; ?>"><?php echo esc_html($group['label']); ?></button>
                <?php endforeach; ?>
            </nav>
            <div class="sc-library-capability-hub__groups">
                <?php foreach ($groups as $group_key => $group) : ?>
                    <section class="sc-library-capability-group<?php echo $group_key === $default_group ? ' is-active-group' : ''; ?>" id="library-group-<?php echo esc_attr($group_key); ?>" data-capability-group="<?php echo esc_attr($group_key); ?>" <?php echo ('tabbed' === $display && $group_key !== $default_group) ? 'hidden' : ''; ?>>
                        <div class="sc-library-capability-group__heading">
                            <div><p><?php echo esc_html($group['label']); ?></p><h3><?php echo esc_html($group['label']); ?></h3></div>
                            <span><?php echo esc_html($group['summary']); ?></span>
                        </div>
                        <?php if ('access' === $group_key) : ?>
                            <p class="sc-library-capability-group__network"><strong><?php esc_html_e('Connected research:', 'sustainable-catalyst-library'); ?></strong> Internet Archive · MIT · Harvard · Library of Congress · University College Dublin · Crossref · OpenAlex · DataCite · PubMed · PMC · Europe PMC · arXiv</p>
                        <?php endif; ?>
                        <div class="sc-library-capability-grid">
                            <?php foreach ($registry as $key => $cap) : if ($cap['group'] !== $group_key) { continue; } ?>
                                <article class="sc-library-capability-card" id="<?php echo esc_attr($cap['anchor']); ?>" data-capability-key="<?php echo esc_attr($key); ?>">
                                    <?php foreach ($cap['aliases'] as $alias) : ?><span id="<?php echo esc_attr($alias); ?>" class="sc-library-capability-anchor" aria-hidden="true"></span><?php endforeach; ?>
                                    <h4><?php echo esc_html($cap['label']); ?></h4>
                                    <p><?php echo esc_html($cap['summary']); ?></p>
                                    <button type="button" data-open-capability="<?php echo esc_attr($key); ?>" aria-controls="<?php echo esc_attr($instance); ?>-workspace"><?php esc_html_e('Open', 'sustainable-catalyst-library'); ?> <span aria-hidden="true">→</span></button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
            <section class="sc-library-capability-workspace" id="<?php echo esc_attr($instance); ?>-workspace" data-capability-workspace hidden aria-live="polite">
                <div class="sc-library-capability-workspace__bar">
                    <div><p><?php esc_html_e('Active Library capability', 'sustainable-catalyst-library'); ?></p><h3 data-capability-workspace-title></h3></div>
                    <button type="button" data-close-capability aria-label="<?php esc_attr_e('Close capability workspace', 'sustainable-catalyst-library'); ?>">×</button>
                </div>
                <div class="sc-library-capability-workspace__status" data-capability-status><?php esc_html_e('Select a capability above.', 'sustainable-catalyst-library'); ?></div>
                <div class="sc-library-capability-workspace__frame" data-capability-frame-host></div>
            </section>
        </section>
        <?php return (string) ob_get_clean();
    }

    public function maybe_render_capability_frame(): void {
        if (!isset($_GET[self::QUERY_ARG])) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $key = sanitize_key((string) wp_unslash($_GET[self::QUERY_ARG])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $registry = self::registry();
        if (!isset($registry[$key])) {
            status_header(404);
            nocache_headers();
            exit;
        }

        $cap = $registry[$key];
        // Render before wp_head() so the selected existing shortcode can enqueue
        // all of its registered CSS/JS into this isolated same-origin document.
        $content = do_shortcode((string) $cap['shortcode']);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex,nofollow,noarchive">
            <title><?php echo esc_html($cap['label']); ?> — <?php esc_html_e('Research Library', 'sustainable-catalyst-library'); ?></title>
            <?php wp_head(); ?>
            <style>html,body{margin:0!important;padding:0!important;background:transparent!important}.sc-library-capability-frame{padding:2px 2px 12px;max-width:none!important}.sc-library-capability-frame>*:first-child{margin-top:0!important}#wpadminbar{display:none!important}html{margin-top:0!important}</style>
        </head>
        <body <?php body_class('sc-library-capability-frame-document'); ?>>
            <?php if (function_exists('wp_body_open')) { wp_body_open(); } ?>
            <main class="sc-library-capability-frame" id="sc-library-capability-frame-<?php echo esc_attr($key); ?>">
                <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <script>
            (function(){
                var key=<?php echo wp_json_encode($key); ?>;
                var send=function(){
                    var h=Math.max(document.documentElement.scrollHeight,document.body?document.body.scrollHeight:0);
                    try{parent.postMessage({type:'sc-library-capability-height',key:key,height:h},location.origin);}catch(e){}
                };
                if('ResizeObserver' in window){new ResizeObserver(send).observe(document.documentElement);} 
                window.addEventListener('load',send); setTimeout(send,100); setTimeout(send,600);
            }());
            </script>
            <?php wp_footer(); ?>
        </body>
        </html><?php
        exit;
    }
}
