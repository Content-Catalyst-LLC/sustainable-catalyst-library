<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.8.0 Research Network Console.
 *
 * Makes the Library's real research-access surface visible without claiming
 * equivalent integration for every institution. Direct connectors, standards
 * gateways, repositories, scholarly indexes, and public-library catalogs are
 * explicitly labeled by capability.
 */
final class SC_Library_Research_Network_Console {
    public const VERSION = '5.8.0';

    public function register_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_public_assets']);
        add_shortcode('sc_research_network_console', [$this, 'shortcode']);
    }

    public function maybe_enqueue_public_assets(): void {
        if (!is_singular()) { return; }
        $post = get_queried_object();
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        if ($content === '' || !has_shortcode($content, 'sc_research_network_console')) { return; }
        wp_enqueue_style('sc-library-public-interface-v560r3', SC_LIBRARY_URL . 'assets/css/sc-library-public-interface-v560r3.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-research-network-console-v560r3', SC_LIBRARY_URL . 'assets/css/sc-library-research-network-console-v560r3.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-research-network-console-v560r3', SC_LIBRARY_URL . 'assets/js/sc-library-research-network-console-v560r3.js', [], SC_LIBRARY_VERSION, true);
    }

    /** @return array<int,array<string,string>> */
    private static function direct_sources(): array {
        return [
            ['id'=>'internetarchive','name'=>'Internet Archive','kind'=>'library','type'=>'Digital archive','mode'=>'DIRECT SEARCH','detail'=>'Books · archives · media · public digital collections'],
            ['id'=>'mit','name'=>'MIT Libraries','kind'=>'university','type'=>'University library','mode'=>'DIRECT SEARCH','detail'=>'TIMDEX · DSpace@MIT · ArchivesSpace'],
            ['id'=>'harvard','name'=>'Harvard Library','kind'=>'university','type'=>'University library','mode'=>'DIRECT SEARCH','detail'=>'LibraryCloud · HOLLIS metadata · open collections'],
            ['id'=>'ucd','name'=>'University College Dublin','kind'=>'university','type'=>'University repository','mode'=>'DIRECT SEARCH','detail'=>'Research Repository UCD · DSpace discovery'],
            ['id'=>'loc','name'=>'Library of Congress','kind'=>'library','type'=>'National library','mode'=>'DIRECT SEARCH','detail'=>'Catalog · digital collections · authority records'],
            ['id'=>'openalex','name'=>'OpenAlex','kind'=>'scholarly','type'=>'Scholarly graph','mode'=>'DIRECT SEARCH','detail'=>'Works · authors · institutions · citations'],
            ['id'=>'crossref','name'=>'Crossref','kind'=>'scholarly','type'=>'DOI metadata','mode'=>'DIRECT SEARCH','detail'=>'Publication metadata · DOI provenance'],
            ['id'=>'datacite','name'=>'DataCite','kind'=>'scholarly','type'=>'Research identifiers','mode'=>'DIRECT SEARCH','detail'=>'Datasets · DOI records · research objects'],
            ['id'=>'pubmed','name'=>'PubMed','kind'=>'scholarly','type'=>'Biomedical index','mode'=>'DIRECT SEARCH','detail'=>'Biomedical literature · citations'],
            ['id'=>'pmc','name'=>'PubMed Central','kind'=>'scholarly','type'=>'Open full text','mode'=>'DIRECT SEARCH','detail'=>'Biomedical and life-science full text'],
            ['id'=>'europepmc','name'=>'Europe PMC','kind'=>'scholarly','type'=>'Research index','mode'=>'DIRECT SEARCH','detail'=>'Life-science literature · open full-text signals'],
            ['id'=>'arxiv','name'=>'arXiv','kind'=>'scholarly','type'=>'Preprint repository','mode'=>'DIRECT SEARCH','detail'=>'Physics · mathematics · computing · quantitative research'],
        ];
    }

    /** @return array<int,array<string,string>> */
    private static function institutional_sources(): array {
        $out = [];
        if (class_exists('SC_Library_Institutional_Research_Sources')) {
            $out[] = SC_Library_Institutional_Research_Sources::network_source();
        }
        if (!class_exists('SC_Library_Institutional_Connector_Expansion')) { return $out; }
        $types = SC_Library_Institutional_Connector_Expansion::capability_types();
        $skip = ['mit'=>true,'harvard'=>true,'ucd'=>true,'johns-hopkins-dataverse'=>true];
        foreach (SC_Library_Institutional_Connector_Expansion::registry() as $id => $item) {
            if (isset($skip[$id])) { continue; }
            $label = $types[$item['type']]['label'] ?? 'Research Gateway';
            $mode = 'direct' === $item['type'] ? 'DIRECT ROUTE' : ('open-repository' === $item['type'] ? 'OPEN REPOSITORY' : ('standards' === $item['type'] ? 'CATALOG GATEWAY' : 'RESEARCH GATEWAY'));
            $out[] = [
                'id' => sanitize_key($id),
                'name' => (string) $item['name'],
                'kind' => 'university',
                'type' => (string) $label,
                'mode' => $mode,
                'detail' => (string) $item['focus'],
                'search_template' => (string) ($item['search_template'] ?? ''),
                'homepage' => (string) ($item['repository'] ?? ''),
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,string>> */
    private static function biomedical_sources(): array {
        if (!class_exists('SC_Library_Biomedical_Evidence')) { return []; }
        return SC_Library_Biomedical_Evidence::network_sources();
    }

    /** @return array<int,array<string,string>> */
    private static function public_library_sources(): array {
        if (!class_exists('SC_Library_Public_Library_Network')) { return []; }
        $types = SC_Library_Public_Library_Network::access_types();
        $out = [];
        foreach (SC_Library_Public_Library_Network::registry() as $id => $item) {
            if ('loc' === $id) { continue; }
            $out[] = [
                'id' => sanitize_key($id),
                'name' => (string) $item['name'],
                'kind' => 'public-library',
                'type' => (string) ($types[$item['type']]['label'] ?? 'Library'),
                'mode' => 'global-holdings' === $item['type'] ? 'GLOBAL HOLDINGS' : 'PUBLIC CATALOG',
                'detail' => (string) $item['region'],
                'search_template' => (string) ($item['search_template'] ?? ''),
                'homepage' => (string) ($item['homepage'] ?? ''),
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,string>> */
    public static function source_registry(): array {
        return array_merge(self::direct_sources(), self::institutional_sources(), self::biomedical_sources(), self::public_library_sources());
    }

    /** @return array<string,int> */
    public static function source_counts(): array {
        $all = self::source_registry();
        return [
            'routes' => count($all),
            'universities' => count(array_filter($all, static fn($row) => 'university' === ($row['kind'] ?? ''))),
            'libraries' => count(array_filter($all, static fn($row) => in_array(($row['kind'] ?? ''), ['library','public-library'], true))),
            'scholarly' => count(array_filter($all, static fn($row) => 'scholarly' === ($row['kind'] ?? ''))),
        ];
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Research Network Console',
            'intro' => 'Search established libraries, university research systems, scholarly indexes, archives, repositories, and public-library catalogs. Capability labels distinguish direct search from gateways and local-access routes.',
        ], $atts, 'sc_research_network_console');

        wp_enqueue_style('sc-library-public-interface-v560r3', SC_LIBRARY_URL . 'assets/css/sc-library-public-interface-v560r3.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-research-network-console-v560r3', SC_LIBRARY_URL . 'assets/css/sc-library-research-network-console-v560r3.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-research-network-console-v560r3', SC_LIBRARY_URL . 'assets/js/sc-library-research-network-console-v560r3.js', [], SC_LIBRARY_VERSION, true);

        $direct = self::direct_sources();
        $all = self::source_registry();
        $counts = self::source_counts();
        $counts['direct'] = count($direct);

        $instance = 'sc-research-network-' . wp_rand(1000, 999999);
        ob_start(); ?>
        <section class="sc-research-network-console" id="<?php echo esc_attr($instance); ?>" data-sc-research-network-console>
            <header class="sc-research-network-console__head">
                <div>
                    <p class="sc-research-network-console__kicker"><?php esc_html_e('Authoritative Research Infrastructure', 'sustainable-catalyst-library'); ?></p>
                    <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                    <p><?php echo esc_html((string) $atts['intro']); ?></p>
                </div>
                <div class="sc-research-network-console__state" aria-label="Research network directory counts">
                    <span><strong><?php echo esc_html((string) $counts['routes']); ?></strong> visible routes</span>
                    <span><strong><?php echo esc_html((string) $counts['universities']); ?></strong> university / research</span>
                    <span><strong><?php echo esc_html((string) $counts['libraries']); ?></strong> library / archive</span>
                    <span><strong><?php echo esc_html((string) $counts['scholarly']); ?></strong> scholarly indexes</span>
                </div>
            </header>
            <div class="sc-research-network-console__toolbar">
                <label><span><?php esc_html_e('Research query', 'sustainable-catalyst-library'); ?></span><input type="search" data-sc-network-query placeholder="Title, author, DOI, ISBN, topic…" autocomplete="off"></label>
                <div class="sc-research-network-console__filters" aria-label="Filter research network sources">
                    <button type="button" class="is-active" data-sc-network-filter="all">All</button>
                    <button type="button" data-sc-network-filter="university">Universities</button>
                    <button type="button" data-sc-network-filter="library">Libraries</button>
                    <button type="button" data-sc-network-filter="scholarly">Scholarly</button>
                </div>
            </div>
            <div class="sc-research-network-console__screen" tabindex="0" aria-label="Research network source directory">
                <div class="sc-research-network-console__columns" aria-hidden="true"><span>SOURCE</span><span>TYPE</span><span>ACCESS MODE</span><span>ACTION</span></div>
                <div class="sc-research-network-console__viewport" data-sc-network-viewport>
                    <div class="sc-research-network-console__track" data-sc-network-track>
                    <?php foreach ($all as $row) :
                        $kind = (string) ($row['kind'] ?? 'library');
                        $filter_kind = 'public-library' === $kind ? 'library' : $kind;
                        $template = (string) ($row['search_template'] ?? '');
                        $homepage = (string) ($row['homepage'] ?? '');
                        $provider = in_array($row['id'], array_column($direct, 'id'), true) ? (string) $row['id'] : '';
                    ?>
                        <article class="sc-research-network-console__row" data-sc-network-row data-kind="<?php echo esc_attr($filter_kind); ?>" data-source-name="<?php echo esc_attr(strtolower($row['name'] . ' ' . $row['type'] . ' ' . $row['detail'])); ?>">
                            <div><span class="sc-research-network-console__prompt" aria-hidden="true">&gt;</span><strong><?php echo esc_html($row['name']); ?></strong><small><?php echo esc_html($row['detail']); ?></small></div>
                            <span><?php echo esc_html($row['type']); ?></span>
                            <span class="sc-research-network-console__mode"><?php echo esc_html($row['mode']); ?></span>
                            <button type="button" data-sc-network-source-action data-provider="<?php echo esc_attr($provider); ?>" data-template="<?php echo esc_attr($template); ?>" data-homepage="<?php echo esc_attr($homepage); ?>"><?php esc_html_e('Search', 'sustainable-catalyst-library'); ?> →</button>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <footer class="sc-research-network-console__footer">
                <span>PROVENANCE: VISIBLE</span><span>ACCESS ROUTES: LABELED</span><span>ENTITLEMENT: NEVER ASSUMED</span>
                <nav aria-label="Research network actions"><a href="#research-access">Search all research</a><a href="#public-library-network">Public libraries</a><a href="https://search.worldcat.org/libraries" target="_blank" rel="noopener">Find libraries near me</a><a href="#research-front-door">Research Librarian</a></nav>
            </footer>
        </section>
        <?php return (string) ob_get_clean();
    }
}
