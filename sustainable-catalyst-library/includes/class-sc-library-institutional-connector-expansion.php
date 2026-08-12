<?php
/**
 * Institutional Connector Expansion — v4.3.25.
 *
 * A truthful capability registry for institutional research access. It does
 * not claim direct API integration where only a repository, standards route,
 * catalog gateway, or authenticated library surface is available.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Institutional_Connector_Expansion {
    public const VERSION = '4.3.25';
    public const SCHEMA = 'sc-library-institutional-connector-network/1.0';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_institutional_connector_network', array( $this, 'shortcode' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-institutional-connectors', SC_LIBRARY_URL . 'assets/css/sc-library-institutional-connectors.css', array(), self::VERSION );
    }

    public static function capability_types() {
        return array(
            'direct' => array( 'label' => 'Direct Connector', 'rank' => 10 ),
            'open-repository' => array( 'label' => 'Open Repository', 'rank' => 20 ),
            'standards' => array( 'label' => 'Standards-Capable', 'rank' => 30 ),
            'gateway' => array( 'label' => 'Research Gateway', 'rank' => 40 ),
        );
    }

    public static function registry() {
        $items = array(
            'mit' => array('name'=>'MIT Libraries','region'=>'Cambridge, Massachusetts, USA','type'=>'direct','focus'=>'Engineering, science, technology, economics, architecture and open research.','search_template'=>'https://timdex.mit.edu/?q={query}','repository'=>'https://dspace.mit.edu/','protocols'=>array('TIMDEX','DSpace','ArchivesSpace'),'access'=>'Public discovery; licensed resources remain authenticated by MIT.'),
            'harvard' => array('name'=>'Harvard Library','region'=>'Cambridge, Massachusetts, USA','type'=>'direct','focus'=>'Multidisciplinary scholarship, archives, digital collections and bibliographic discovery.','search_template'=>'https://library.harvard.edu/search?query={query}','repository'=>'https://dash.harvard.edu/','protocols'=>array('LibraryCloud','Open repository'),'access'=>'Public metadata and open collections; licensed resources require Harvard-authorized access.'),
            'berkeley' => array('name'=>'UC Berkeley / eScholarship','region'=>'Berkeley, California, USA','type'=>'direct','focus'=>'Environment, energy, policy, science, technology and UC open scholarship.','search_template'=>'https://escholarship.org/search/?q={query}','repository'=>'https://escholarship.org/','protocols'=>array('eScholarship','UC Library Search'),'access'=>'Open repository content is public; licensed UC resources remain institution-controlled.'),
            'ucd' => array('name'=>'University College Dublin','region'=>'Dublin, Ireland','type'=>'direct','focus'=>'Research Repository UCD, sustainability, agriculture, science, humanities and public policy.','search_template'=>'https://researchrepository.ucd.ie/discover?query={query}','repository'=>'https://researchrepository.ucd.ie/','protocols'=>array('DSpace'),'access'=>'Repository discovery is public; licensed library access remains authenticated by UCD.'),
            'stanford' => array('name'=>'Stanford University Libraries','region'=>'Stanford, California, USA','type'=>'standards','focus'=>'Science, engineering, sustainability, public policy, digital collections and archives.','search_template'=>'https://searchworks.stanford.edu/?q={query}','repository'=>'https://purl.stanford.edu/','protocols'=>array('SearchWorks','Blacklight','Digital repository'),'access'=>'Catalog and many digital collections are public; subscription content requires Stanford entitlement.'),
            'yale' => array('name'=>'Yale University Library','region'=>'New Haven, Connecticut, USA','type'=>'standards','focus'=>'Humanities, science, environment, law, public policy, archives and digital collections.','search_template'=>'https://search.library.yale.edu/catalog?search_field=all_fields&q={query}','repository'=>'https://elischolar.library.yale.edu/','protocols'=>array('Quicksearch','Catalog','Open repository'),'access'=>'Catalog discovery is public; licensed electronic resources require Yale entitlement.'),
            'princeton' => array('name'=>'Princeton University Library','region'=>'Princeton, New Jersey, USA','type'=>'standards','focus'=>'Public affairs, climate, economics, science, humanities, archives and digital collections.','search_template'=>'https://catalog.princeton.edu/?search_field=all_fields&q={query}','repository'=>'https://dataspace.princeton.edu/','protocols'=>array('Catalog','DataSpace','Digital collections'),'access'=>'Catalog and open repository records are public; licensed resources require Princeton access.'),
            'columbia' => array('name'=>'Columbia University Libraries','region'=>'New York, New York, USA','type'=>'standards','focus'=>'Climate, sustainable development, global affairs, science, engineering and humanities.','search_template'=>'https://clio.columbia.edu/catalog?q={query}','repository'=>'https://academiccommons.columbia.edu/','protocols'=>array('CLIO','Open catalog data','Academic Commons'),'access'=>'Catalog and Academic Commons are public; subscription resources require Columbia entitlement.'),
            'copenhagen' => array('name'=>'University of Copenhagen','region'=>'Copenhagen, Denmark','type'=>'open-repository','focus'=>'Climate, ecology, food, health, governance and sustainability research.','search_template'=>'https://researchprofiles.ku.dk/en/searchAll/index/?search={query}','repository'=>'https://researchprofiles.ku.dk/','protocols'=>array('Research portal'),'access'=>'Public research portal and open outputs where available; licensed access remains institution-controlled.'),
            'stockholm' => array('name'=>'Stockholm University','region'=>'Stockholm, Sweden','type'=>'open-repository','focus'=>'Planetary systems, resilience, climate, governance, environment and social science.','search_template'=>'https://su.diva-portal.org/smash/resultList.jsf?searchType=SIMPLE&query={query}','repository'=>'https://su.diva-portal.org/','protocols'=>array('DiVA'),'access'=>'DiVA records and open files are public; licensed library resources require local entitlement.'),
            'wageningen' => array('name'=>'Wageningen University & Research','region'=>'Wageningen, Netherlands','type'=>'open-repository','focus'=>'Food systems, biodiversity, agriculture, water, land use, circularity and climate.','search_template'=>'https://research.wur.nl/en/searchAll/index/?search={query}','repository'=>'https://research.wur.nl/','protocols'=>array('Research portal'),'access'=>'Public research portal; open outputs are accessible when deposited.'),
            'lund' => array('name'=>'Lund University','region'=>'Lund, Sweden','type'=>'open-repository','focus'=>'Sustainability science, climate, energy, governance, engineering and public health.','search_template'=>'https://lup.lub.lu.se/search/publication?q={query}','repository'=>'https://lup.lub.lu.se/','protocols'=>array('LUP'),'access'=>'Public publication discovery and open files where available.'),
            'eth' => array('name'=>'ETH Zürich','region'=>'Zürich, Switzerland','type'=>'open-repository','focus'=>'Climate, engineering, energy, environmental systems, computing and research data.','search_template'=>'https://www.research-collection.ethz.ch/search?query={query}','repository'=>'https://www.research-collection.ethz.ch/','protocols'=>array('Research Collection'),'access'=>'Open repository material is public; licensed library resources require ETH entitlement.'),
            'oxford' => array('name'=>'University of Oxford','region'=>'Oxford, United Kingdom','type'=>'open-repository','focus'=>'Climate, economics, governance, medicine, humanities, science and sustainability.','search_template'=>'https://ora.ox.ac.uk/search?search={query}','repository'=>'https://ora.ox.ac.uk/','protocols'=>array('ORA'),'access'=>'ORA discovery and open files are public; licensed Bodleian resources remain authenticated.'),
            'cambridge' => array('name'=>'University of Cambridge','region'=>'Cambridge, United Kingdom','type'=>'open-repository','focus'=>'Climate, engineering, energy, environment, science, medicine and humanities.','search_template'=>'https://www.repository.cam.ac.uk/search?query={query}','repository'=>'https://www.repository.cam.ac.uk/','protocols'=>array('Apollo repository'),'access'=>'Repository discovery and deposited open files are public.'),
            'iiasa' => array('name'=>'IIASA','region'=>'Laxenburg, Austria','type'=>'gateway','focus'=>'Systems analysis, climate, energy, transitions, modeling and sustainable development.','search_template'=>'https://iiasa.ac.at/publications','repository'=>'https://iiasa.ac.at/publications','protocols'=>array('Publication gateway'),'access'=>'Public research gateway; individual outputs carry their own rights and access conditions.'),
            'sei' => array('name'=>'Stockholm Environment Institute','region'=>'Stockholm, Sweden / Global','type'=>'gateway','focus'=>'Climate, environment, development, water, energy, policy and transitions.','search_template'=>'https://www.sei.org/publications/?query={query}','repository'=>'https://www.sei.org/publications/','protocols'=>array('Publication gateway'),'access'=>'Public publication gateway; open files are linked where supplied by SEI.'),
            'unu' => array('name'=>'United Nations University','region'=>'Global','type'=>'gateway','focus'=>'SDGs, governance, environment, development policy, health and global systems.','search_template'=>'https://unu.edu/publications','repository'=>'https://unu.edu/publications','protocols'=>array('Publication gateway'),'access'=>'Public publication gateway; rights and full-text availability vary by output.'),
        );
        return apply_filters( 'sc_library_institutional_connector_registry', $items );
    }

    public static function resolve_search_url( $id, $query ) {
        $registry = self::registry();
        $id = sanitize_key( $id );
        if ( empty( $registry[$id]['search_template'] ) ) { return ''; }
        $query = trim( wp_strip_all_tags( (string) $query ) );
        $template = $registry[$id]['search_template'];
        if ( false === strpos( $template, '{query}' ) ) { return esc_url_raw( $template ); }
        return esc_url_raw( str_replace( '{query}', rawurlencode( $query ), $template ) );
    }

    public function register_rest_routes() {
        register_rest_route( 'sc-library/v1', '/institutional-connectors', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => function( WP_REST_Request $request ) {
                $query = sanitize_text_field( (string) $request->get_param('q') );
                $out = array();
                foreach ( self::registry() as $id => $item ) {
                    $row = $item; $row['id'] = $id; $row['search_url'] = self::resolve_search_url( $id, $query ); $out[] = $row;
                }
                return rest_ensure_response( array('schema'=>self::SCHEMA,'count'=>count($out),'query'=>$query,'institutions'=>$out) );
            },
        ) );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array('title'=>'Institutional Research Network','show'=>'all'), $atts, 'sc_institutional_connector_network' );
        wp_enqueue_style( 'sc-library-institutional-connectors' );
        $query = isset($_GET['sc_institution_query']) ? sanitize_text_field( wp_unslash($_GET['sc_institution_query']) ) : '';
        $types = self::capability_types();
        $registry = self::registry();
        uasort($registry, static function($a,$b) use($types){ $ar=$types[$a['type']]['rank']??99; $br=$types[$b['type']]['rank']??99; return $ar===$br ? strcmp($a['name'],$b['name']) : $ar<=>$br; });
        ob_start(); ?>
        <section class="sc-inst-network" data-sc-institutional-connectors="v4.3.25">
          <header><p class="sc-connector-kicker"><?php esc_html_e('Institutional Connector Expansion','sustainable-catalyst-library'); ?></p><h3><?php echo esc_html($atts['title']); ?></h3><p><?php esc_html_e('Search a capability-labeled network of university libraries, open repositories, standards-capable catalogs, and research gateways. A gateway is never presented as a direct connector, and public discovery is never presented as proof of licensed access.','sustainable-catalyst-library'); ?></p></header>
          <form method="get" action="<?php echo esc_url( strtok( home_url( add_query_arg(array(), $GLOBALS['wp']->request ?? '') ), '?' ) ); ?>#institutional-research-network" class="sc-inst-network__search">
            <label><span><?php esc_html_e('Search this institutional network','sustainable-catalyst-library'); ?></span><input type="search" name="sc_institution_query" value="<?php echo esc_attr($query); ?>" placeholder="Topic, title, author, DOI…"></label><button type="submit"><?php esc_html_e('Prepare institution searches','sustainable-catalyst-library'); ?></button>
          </form>
          <div class="sc-inst-network__legend" aria-label="Connector capability legend">
            <?php foreach($types as $type): ?><span><?php echo esc_html($type['label']); ?></span><?php endforeach; ?>
          </div>
          <div class="sc-inst-network__list">
          <?php foreach($registry as $id=>$item): $url=self::resolve_search_url($id,$query); ?>
            <article class="sc-inst-network__item" id="institution-<?php echo esc_attr($id); ?>">
              <div class="sc-inst-network__meta"><small><?php echo esc_html($types[$item['type']]['label']??'Research Gateway'); ?></small><span><?php echo esc_html($item['region']); ?></span></div>
              <h4><?php echo esc_html($item['name']); ?></h4><p><?php echo esc_html($item['focus']); ?></p>
              <div class="sc-inst-network__protocols"><?php foreach($item['protocols'] as $protocol): ?><span><?php echo esc_html($protocol); ?></span><?php endforeach; ?></div>
              <p class="sc-inst-network__access"><?php echo esc_html($item['access']); ?></p>
              <div class="sc-inst-network__actions"><a href="<?php echo esc_url($url ?: $item['repository']); ?>" target="_blank" rel="noopener"><?php echo esc_html($query ? sprintf(__('Search %s','sustainable-catalyst-library'),$item['name']) : sprintf(__('Open %s','sustainable-catalyst-library'),$item['name'])); ?> →</a><?php if(!empty($item['repository']) && $item['repository']!==$url): ?><a href="<?php echo esc_url($item['repository']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Repository / research portal','sustainable-catalyst-library'); ?> →</a><?php endif; ?></div>
            </article>
          <?php endforeach; ?>
          </div>
        </section><?php
        return ob_get_clean();
    }
}
