<?php
/**
 * Open Course Finder.
 *
 * Public, access-aware discovery across open courseware, university online
 * learning catalogs, MOOC providers, and sustainability learning resources.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Open_Course_Finder {
    public const VERSION = '1.0.0';
    public const VERIFIED_ON = '2026-08-11';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_open_course_finder', array( $this, 'shortcode' ) );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-open-course-finder',
            SC_LIBRARY_URL . 'assets/css/sc-library-open-course-finder.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-open-course-finder',
            SC_LIBRARY_URL . 'assets/js/sc-library-open-course-finder.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    /**
     * Provider-level discovery registry.
     *
     * Capability labels deliberately distinguish native open courseware from
     * audit/preview gateways. Sustainable Catalyst does not imply that an
     * external certificate, full course, or credential is free unless the
     * source institution says so.
     *
     * @return array<string,array<string,string>>
     */
    public static function provider_registry() {
        return array(
            'mit-ocw' => array(
                'name'            => 'MIT OpenCourseWare',
                'institution'     => 'Massachusetts Institute of Technology',
                'access'          => 'free-open',
                'access_label'    => 'Free & Open',
                'capability'      => 'Open courseware',
                'focus'           => 'Thousands of MIT course materials across the curriculum; no enrollment required.',
                'url'             => 'https://ocw.mit.edu/courses/',
                'search_template' => 'https://ocw.mit.edu/search/?q={query}',
            ),
            'harvard-cs50' => array(
                'name'            => 'Harvard CS50',
                'institution'     => 'Harvard University',
                'access'          => 'free-open',
                'access_label'    => 'Free & Open',
                'capability'      => 'Open courseware',
                'focus'           => 'Open computer science courses including CS50x, Python, AI, cybersecurity, and web programming.',
                'url'             => 'https://cs50.harvard.edu/',
                'search_template' => 'https://cs50.harvard.edu/',
            ),
            'yale-online' => array(
                'name'            => 'Yale Online / Open Yale Courses',
                'institution'     => 'Yale University',
                'access'          => 'mixed-open',
                'access_label'    => 'Open + Mixed',
                'capability'      => 'Open catalog',
                'focus'           => 'Free open-access courses plus Coursera and certificate offerings with course-specific terms.',
                'url'             => 'https://online.yale.edu/courses',
                'search_template' => 'https://online.yale.edu/courses',
            ),
            'princeton-online' => array(
                'name'            => 'Princeton Online',
                'institution'     => 'Princeton University',
                'access'          => 'free-open',
                'access_label'    => 'Free & Open',
                'capability'      => 'Open online courses',
                'focus'           => 'Princeton open online courses delivered through Coursera, edX, and other learning platforms.',
                'url'             => 'https://online.princeton.edu/courses',
                'search_template' => 'https://online.princeton.edu/courses',
            ),
            'stanford-online' => array(
                'name'            => 'Stanford Online',
                'institution'     => 'Stanford University',
                'access'          => 'mixed',
                'access_label'    => 'Free + Low-Cost',
                'capability'      => 'University course gateway',
                'focus'           => 'Free and low-cost courses plus broader Stanford Online professional and academic learning.',
                'url'             => 'https://online.stanford.edu/free-courses',
                'search_template' => 'https://online.stanford.edu/search?keywords={query}',
            ),
            'columbia-online' => array(
                'name'            => 'Columbia Online',
                'institution'     => 'Columbia University',
                'access'          => 'mixed-open',
                'access_label'    => 'Open + Audit',
                'capability'      => 'MOOC gateway',
                'focus'           => 'Columbia MOOCs and online learning, including courses delivered through edX and Coursera.',
                'url'             => 'https://online.columbia.edu/moocs/',
                'search_template' => 'https://online.columbia.edu/moocs/',
            ),
            'edx' => array(
                'name'            => 'edX',
                'institution'     => 'Multi-institution platform',
                'access'          => 'free-audit',
                'access_label'    => 'Free Audit',
                'capability'      => 'Course marketplace',
                'focus'           => 'Many university courses can be audited at no charge; verified credentials are typically paid.',
                'url'             => 'https://www.edx.org/courses',
                'search_template' => 'https://www.edx.org/search?q={query}',
            ),
            'coursera' => array(
                'name'            => 'Coursera',
                'institution'     => 'Multi-institution platform',
                'access'          => 'free-preview',
                'access_label'    => 'Free Preview',
                'capability'      => 'Course marketplace',
                'focus'           => 'Most courses expose a free first-module preview; some courses remain free and financial aid may be available.',
                'url'             => 'https://www.coursera.org/courses',
                'search_template' => 'https://www.coursera.org/search?query={query}',
            ),
            'openlearn' => array(
                'name'            => 'OpenLearn',
                'institution'     => 'The Open University',
                'access'          => 'free-open',
                'access_label'    => 'Free & Open',
                'capability'      => 'Open university courses',
                'focus'           => 'Hundreds of free short courses across science, society, environment, computing, arts, business, and more.',
                'url'             => 'https://www.open.edu/openlearn/free-courses/full-catalogue',
                'search_template' => 'https://www.open.edu/openlearn/free-courses/full-catalogue',
            ),
            'sdg-academy' => array(
                'name'            => 'SDG Academy',
                'institution'     => 'Sustainable Development Solutions Network',
                'access'          => 'free-open',
                'access_label'    => 'Free & Open',
                'capability'      => 'Sustainability course catalog',
                'focus'           => 'Free courses focused on sustainable development, SDGs, cities, climate, food systems, rights, and development.',
                'url'             => 'https://sdgacademy.org/courses/',
                'search_template' => 'https://sdgacademy.org/courses/',
            ),
            'fao-elearning' => array(
                'name'            => 'FAO elearning Academy',
                'institution'     => 'Food and Agriculture Organization of the United Nations',
                'access'          => 'free-certificate',
                'access_label'    => 'Free + Certificates',
                'capability'      => 'Open professional learning',
                'focus'           => 'Free multilingual courses on food systems, agriculture, natural resources, nutrition, development, and the SDGs.',
                'url'             => 'https://elearning.fao.org/',
                'search_template' => 'https://elearning.fao.org/local/search/',
            ),
            'unitar' => array(
                'name'            => 'UNITAR / UN Learning',
                'institution'     => 'United Nations Institute for Training and Research',
                'access'          => 'mixed-open',
                'access_label'    => 'Many Free Courses',
                'capability'      => 'UN learning gateway',
                'focus'           => 'Open and mixed-access learning on SDGs, climate, public policy, infrastructure, governance, and professional practice.',
                'url'             => 'https://unitar.org/learning-solutions/learning-resources',
                'search_template' => 'https://event.unitar.org/by-date/self-paced-open-enrolment-events',
            ),
        );
    }

    /**
     * Normalized launch catalog. This is intentionally small and verified,
     * while provider gateways supply the broader discovery surface.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function launch_catalog() {
        return array(
            array(
                'id' => 'mit-6-100l', 'provider' => 'mit-ocw', 'institution' => 'MIT',
                'title' => 'Introduction to CS and Programming Using Python',
                'subjects' => array( 'Computer Science', 'Programming' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'OpenCourseWare',
                'url' => 'https://ocw.mit.edu/courses/6-100l-introduction-to-cs-and-programming-using-python-fall-2022/',
                'summary' => 'Introductory computer science and Python course materials with lectures, readings, code, exercises, and problem sets.',
            ),
            array(
                'id' => 'mit-6-006', 'provider' => 'mit-ocw', 'institution' => 'MIT',
                'title' => 'Introduction to Algorithms',
                'subjects' => array( 'Computer Science', 'Algorithms' ), 'level' => 'Undergraduate',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'OpenCourseWare',
                'url' => 'https://ocw.mit.edu/courses/6-006-introduction-to-algorithms-spring-2020/',
                'summary' => 'Algorithms, data structures, performance analysis, graph searching, sorting, and dynamic programming.',
            ),
            array(
                'id' => 'cs50x', 'provider' => 'harvard-cs50', 'institution' => 'Harvard University',
                'title' => "CS50's Introduction to Computer Science",
                'subjects' => array( 'Computer Science', 'Programming' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'OpenCourseWare',
                'url' => 'https://cs50.harvard.edu/x/',
                'summary' => 'Harvard CS50 introduction to computer science, programming, algorithms, data structures, and computational thinking.',
            ),
            array(
                'id' => 'cs50p', 'provider' => 'harvard-cs50', 'institution' => 'Harvard University',
                'title' => "CS50's Introduction to Programming with Python",
                'subjects' => array( 'Computer Science', 'Programming' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'OpenCourseWare',
                'url' => 'https://cs50.harvard.edu/python/',
                'summary' => 'Programming fundamentals in Python, including functions, conditionals, loops, exceptions, libraries, testing, and files.',
            ),
            array(
                'id' => 'cs50ai', 'provider' => 'harvard-cs50', 'institution' => 'Harvard University',
                'title' => "CS50's Introduction to Artificial Intelligence with Python",
                'subjects' => array( 'Artificial Intelligence', 'Computer Science' ), 'level' => 'Intermediate',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'OpenCourseWare',
                'url' => 'https://cs50.harvard.edu/ai/',
                'summary' => 'Core concepts and algorithms underlying modern artificial intelligence with practical Python projects.',
            ),
            array(
                'id' => 'yale-wellbeing', 'provider' => 'yale-online', 'institution' => 'Yale University',
                'title' => 'The Science of Well-Being',
                'subjects' => array( 'Psychology', 'Well-Being' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free Access Available', 'format' => 'Online course',
                'url' => 'https://online.yale.edu/courses/science-well-being',
                'summary' => 'Research-backed approaches to happiness, habits, expectations, and behavioral change.',
            ),
            array(
                'id' => 'yale-financial-markets', 'provider' => 'yale-online', 'institution' => 'Yale University',
                'title' => 'Financial Markets',
                'subjects' => array( 'Economics', 'Finance', 'Risk' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Open Access Available', 'format' => 'Online course / Open Yale',
                'url' => 'https://online.yale.edu/courses/financial-markets',
                'summary' => 'Markets, risk management, institutions, behavioral finance, and the role of finance in society.',
            ),
            array(
                'id' => 'yale-listening-music', 'provider' => 'yale-online', 'institution' => 'Yale University',
                'title' => 'Listening to Music',
                'subjects' => array( 'Music', 'Humanities' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'Open access / video',
                'url' => 'https://online.yale.edu/courses/listening-music',
                'summary' => 'Develop aural skills and a deeper understanding of Western music through open Yale materials.',
            ),
            array(
                'id' => 'princeton-food-ethics', 'provider' => 'princeton-online', 'institution' => 'Princeton University',
                'title' => 'Food Ethics',
                'subjects' => array( 'Sustainability', 'Ethics', 'Food Systems' ), 'level' => 'Open course',
                'access' => 'free-open', 'access_label' => 'Free via Princeton', 'format' => 'MOOC',
                'url' => 'https://online.princeton.edu/news/new-course-food-ethics',
                'summary' => 'Ethical frameworks for food choices, animal welfare, workers, environmental impacts, food justice, and food security.',
            ),
            array(
                'id' => 'princeton-systemic-risk', 'provider' => 'princeton-online', 'institution' => 'Princeton University',
                'title' => 'Global Systemic Risk',
                'subjects' => array( 'Systems', 'Risk', 'Globalization' ), 'level' => 'Open course',
                'access' => 'free-open', 'access_label' => 'Free via Princeton', 'format' => 'MOOC',
                'url' => 'https://online.princeton.edu/courses',
                'summary' => 'An interdisciplinary introduction to globalization, interconnected systems, cascading failure, and systemic risk.',
            ),
            array(
                'id' => 'princeton-algorithms-1', 'provider' => 'princeton-online', 'institution' => 'Princeton University',
                'title' => 'Algorithms, Part I',
                'subjects' => array( 'Computer Science', 'Algorithms' ), 'level' => 'Intermediate',
                'access' => 'free-open', 'access_label' => 'Free via Princeton', 'format' => 'MOOC',
                'url' => 'https://online.princeton.edu/algorithms-part-i',
                'summary' => 'Fundamental data types, algorithms, data structures, sorting, searching, priority queues, trees, and hashing.',
            ),
            array(
                'id' => 'stanford-cs101', 'provider' => 'stanford-online', 'institution' => 'Stanford University',
                'title' => 'Computer Science 101',
                'subjects' => array( 'Computer Science', 'Digital Literacy' ), 'level' => 'Introductory',
                'access' => 'mixed', 'access_label' => 'Check Current Access', 'format' => 'Self-paced online',
                'url' => 'https://online.stanford.edu/courses/soe-ycscs101-computer-science-101',
                'summary' => 'A zero-prior-experience introduction to essential ideas of computer science and computing.',
            ),
            array(
                'id' => 'sdg-age', 'provider' => 'sdg-academy', 'institution' => 'SDG Academy',
                'title' => 'The Age of Sustainable Development',
                'subjects' => array( 'Sustainability', 'Development', 'SDGs' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'Self-paced MOOC',
                'url' => 'https://sdgacademy.org/course/the-age-of-sustainable-development/',
                'summary' => 'An interdisciplinary introduction to sustainable development across social, economic, policy, and physical sciences.',
            ),
            array(
                'id' => 'sdg-transform', 'provider' => 'sdg-academy', 'institution' => 'SDG Academy',
                'title' => 'Transforming Our World: Achieving the SDGs',
                'subjects' => array( 'Sustainability', 'SDGs', 'Policy' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'Self-paced MOOC',
                'url' => 'https://sdgacademy.org/course/transforming-our-world/',
                'summary' => 'A self-paced overview of the Sustainable Development Goals, why they matter, and approaches to achieving them.',
            ),
            array(
                'id' => 'sdg-cities', 'provider' => 'sdg-academy', 'institution' => 'SDG Academy',
                'title' => 'Cities and the Challenge of Sustainable Development',
                'subjects' => array( 'Sustainability', 'Cities', 'Infrastructure' ), 'level' => 'Introductory',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'Self-paced course',
                'url' => 'https://sdgacademy.org/course/cities-and-the-challenge-of-sustainable-development/',
                'summary' => 'Foundations of sustainable cities, urban growth, technology, transportation, energy systems, and local government challenges.',
            ),
            array(
                'id' => 'fao-food-systems', 'provider' => 'fao-elearning', 'institution' => 'FAO',
                'title' => 'Sustainable Food Systems: An Introduction',
                'subjects' => array( 'Sustainability', 'Food Systems', 'Agriculture' ), 'level' => 'Introductory',
                'access' => 'free-certificate', 'access_label' => 'Free + Certificate', 'format' => 'Certified e-learning',
                'url' => 'https://elearning.fao.org/course/view.php?id=736',
                'summary' => 'Introduction to sustainable food systems through the FAO elearning Academy.',
            ),
            array(
                'id' => 'unitar-infrastructure', 'provider' => 'unitar', 'institution' => 'UNITAR / UN DESA',
                'title' => 'Infrastructure Asset Management for Sustainable Development',
                'subjects' => array( 'Sustainability', 'Infrastructure', 'Public Policy' ), 'level' => 'Professional',
                'access' => 'free-open', 'access_label' => 'Free & Open', 'format' => 'Self-paced MOOC',
                'url' => 'https://unitar.org/about/news-stories/featuredarticles/massive-open-online-course-infrastructure-asset-management-sustainable-development',
                'summary' => 'Public-sector infrastructure asset management aligned with sustainable development goals and long-term public value.',
            ),
        );
    }

    private static function subjects() {
        $subjects = array();
        foreach ( self::launch_catalog() as $course ) {
            foreach ( (array) $course['subjects'] as $subject ) {
                $subjects[ $subject ] = true;
            }
        }
        ksort( $subjects, SORT_NATURAL | SORT_FLAG_CASE );
        return array_keys( $subjects );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'title' => __( 'Find Free and Open Courses', 'sustainable-catalyst-library' ),
                'show_providers' => 'true',
            ),
            $atts,
            'sc_open_course_finder'
        );

        wp_enqueue_style( 'sc-library-open-course-finder' );
        wp_enqueue_script( 'sc-library-open-course-finder' );

        $courses = self::launch_catalog();
        $providers = self::provider_registry();
        $subjects = self::subjects();

        ob_start();
        ?>
        <section class="sc-course-finder" data-sc-course-finder data-verified-on="<?php echo esc_attr( self::VERIFIED_ON ); ?>">
            <header class="sc-course-finder__header">
                <p class="sc-course-finder__kicker"><?php esc_html_e( 'Open Course Finder', 'sustainable-catalyst-library' ); ?></p>
                <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                <p><?php esc_html_e( 'Search a normalized launch catalog of open university courses and sustainability learning, then continue into the provider catalog for broader discovery. Access labels distinguish truly open courses from free audit, free preview, optional paid certificates, and mixed access.', 'sustainable-catalyst-library' ); ?></p>
            </header>

            <form class="sc-course-finder__controls" data-sc-course-finder-form>
                <label class="sc-course-finder__query">
                    <span><?php esc_html_e( 'What do you want to learn?', 'sustainable-catalyst-library' ); ?></span>
                    <input type="search" name="query" placeholder="<?php esc_attr_e( 'e.g. sustainability, algorithms, economics, climate', 'sustainable-catalyst-library' ); ?>">
                </label>
                <label>
                    <span><?php esc_html_e( 'Subject', 'sustainable-catalyst-library' ); ?></span>
                    <select name="subject">
                        <option value=""><?php esc_html_e( 'All subjects', 'sustainable-catalyst-library' ); ?></option>
                        <?php foreach ( $subjects as $subject ) : ?>
                            <option value="<?php echo esc_attr( strtolower( $subject ) ); ?>"><?php echo esc_html( $subject ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e( 'Access', 'sustainable-catalyst-library' ); ?></span>
                    <select name="access">
                        <option value=""><?php esc_html_e( 'All access models', 'sustainable-catalyst-library' ); ?></option>
                        <option value="free-open"><?php esc_html_e( 'Free & open', 'sustainable-catalyst-library' ); ?></option>
                        <option value="free-certificate"><?php esc_html_e( 'Free + certificate', 'sustainable-catalyst-library' ); ?></option>
                        <option value="free-audit"><?php esc_html_e( 'Free audit', 'sustainable-catalyst-library' ); ?></option>
                        <option value="free-preview"><?php esc_html_e( 'Free preview', 'sustainable-catalyst-library' ); ?></option>
                        <option value="mixed"><?php esc_html_e( 'Mixed / check provider', 'sustainable-catalyst-library' ); ?></option>
                    </select>
                </label>
                <button type="submit"><?php esc_html_e( 'Find Courses', 'sustainable-catalyst-library' ); ?></button>
            </form>

            <div class="sc-course-finder__access-key" aria-label="Course access labels">
                <span data-access="free-open"><?php esc_html_e( 'Free & Open', 'sustainable-catalyst-library' ); ?></span>
                <span data-access="free-audit"><?php esc_html_e( 'Free Audit', 'sustainable-catalyst-library' ); ?></span>
                <span data-access="free-preview"><?php esc_html_e( 'Free Preview', 'sustainable-catalyst-library' ); ?></span>
                <span data-access="free-certificate"><?php esc_html_e( 'Free + Certificate', 'sustainable-catalyst-library' ); ?></span>
                <span data-access="mixed"><?php esc_html_e( 'Mixed / Verify', 'sustainable-catalyst-library' ); ?></span>
            </div>

            <div class="sc-course-finder__status" data-sc-course-status aria-live="polite"></div>
            <div class="sc-course-finder__results" data-sc-course-results>
                <?php foreach ( $courses as $course ) :
                    $search_blob = strtolower( implode( ' ', array_merge(
                        array( $course['title'], $course['institution'], $course['summary'], $course['level'], $course['format'] ),
                        $course['subjects']
                    ) ) );
                    ?>
                    <article class="sc-course-card" data-sc-course-card data-course-search="<?php echo esc_attr( $search_blob ); ?>" data-course-subjects="<?php echo esc_attr( strtolower( implode( '|', $course['subjects'] ) ) ); ?>" data-course-access="<?php echo esc_attr( $course['access'] ); ?>">
                        <div class="sc-course-card__topline">
                            <span class="sc-course-card__provider"><?php echo esc_html( $course['institution'] ); ?></span>
                            <strong class="sc-course-card__access is-<?php echo esc_attr( $course['access'] ); ?>"><?php echo esc_html( $course['access_label'] ); ?></strong>
                        </div>
                        <h3><?php echo esc_html( $course['title'] ); ?></h3>
                        <p class="sc-course-card__meta"><?php echo esc_html( $course['level'] . ' · ' . $course['format'] ); ?></p>
                        <p><?php echo esc_html( $course['summary'] ); ?></p>
                        <div class="sc-course-card__subjects">
                            <?php foreach ( $course['subjects'] as $subject ) : ?><span><?php echo esc_html( $subject ); ?></span><?php endforeach; ?>
                        </div>
                        <div class="sc-course-card__actions">
                            <a href="<?php echo esc_url( $course['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Course →', 'sustainable-catalyst-library' ); ?></a>
                            <a href="#research-front-door"><?php esc_html_e( 'Ask the Research Librarian', 'sustainable-catalyst-library' ); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="sc-course-finder__empty" data-sc-course-empty hidden><?php esc_html_e( 'No launch-catalog courses match those filters. Use the provider searches below to search the wider course network.', 'sustainable-catalyst-library' ); ?></p>

            <?php if ( 'true' === strtolower( (string) $atts['show_providers'] ) ) : ?>
            <section class="sc-course-network" aria-labelledby="sc-course-network-title">
                <div class="sc-course-network__heading">
                    <p class="sc-course-finder__kicker"><?php esc_html_e( 'Course Network', 'sustainable-catalyst-library' ); ?></p>
                    <h3 id="sc-course-network-title"><?php esc_html_e( 'Search the Wider Provider Catalogs', 'sustainable-catalyst-library' ); ?></h3>
                    <p><?php esc_html_e( 'The launch catalog is deliberately bounded. These gateways continue the same query into broader university and learning-provider catalogs without pretending Sustainable Catalyst has a direct course API where none is documented.', 'sustainable-catalyst-library' ); ?></p>
                </div>
                <div class="sc-course-network__grid">
                    <?php foreach ( $providers as $provider_id => $provider ) : ?>
                    <article class="sc-course-provider" data-sc-course-provider data-provider-id="<?php echo esc_attr( $provider_id ); ?>">
                        <small><?php echo esc_html( $provider['capability'] ); ?></small>
                        <h4><?php echo esc_html( $provider['name'] ); ?></h4>
                        <p class="sc-course-provider__institution"><?php echo esc_html( $provider['institution'] ); ?></p>
                        <p><?php echo esc_html( $provider['focus'] ); ?></p>
                        <strong class="sc-course-provider__access is-<?php echo esc_attr( $provider['access'] ); ?>"><?php echo esc_html( $provider['access_label'] ); ?></strong>
                        <a data-sc-course-provider-link data-search-template="<?php echo esc_attr( $provider['search_template'] ); ?>" href="<?php echo esc_url( $provider['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Search Provider →', 'sustainable-catalyst-library' ); ?></a>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <p class="sc-course-finder__notice">
                <?php echo esc_html( sprintf( __( 'Access models last reviewed %s. External providers control enrollment, pricing, certificates, course availability, and access terms; verify the current course page before relying on a label.', 'sustainable-catalyst-library' ), self::VERIFIED_ON ) ); ?>
            </p>
        </section>
        <?php
        return ob_get_clean();
    }
}
