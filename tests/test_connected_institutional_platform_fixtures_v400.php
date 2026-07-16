<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'SC_LIBRARY_VERSION', '4.0.0' );

$GLOBALS['can_private'] = true;
$GLOBALS['next_id'] = 100;
$GLOBALS['posts'] = array(
    1 => array( 'post_type' => 'sc_institution', 'post_status' => 'publish', 'post_title' => 'Sustainable Catalyst', 'post_name' => 'sustainable-catalyst', 'post_excerpt' => 'Institutional research platform.', 'post_content' => 'Connected institutional knowledge and research.', 'post_author' => 7 ),
    2 => array( 'post_type' => 'sc_research_unit', 'post_status' => 'publish', 'post_title' => 'Open Knowledge Lab', 'post_name' => 'open-knowledge-lab', 'post_excerpt' => 'Research unit.', 'post_content' => 'Research, publishing, and public infrastructure.', 'post_author' => 7 ),
    10 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Planetary Boundaries', 'post_name' => 'planetary-boundaries', 'post_excerpt' => 'A public research document.', 'post_content' => 'Planetary boundaries describe critical Earth system processes.', 'post_author' => 7 ),
    11 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'private', 'post_title' => 'Internal Research Note', 'post_name' => 'internal-research-note', 'post_excerpt' => 'Private institutional note.', 'post_content' => 'Internal evidence and review context.', 'post_author' => 7 ),
    12 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Climate Governance', 'post_name' => 'climate-governance', 'post_excerpt' => 'A second public research document.', 'post_content' => 'Climate governance connects evidence, institutions, and accountability.', 'post_author' => 7 ),
    20 => array( 'post_type' => 'sc_research_project', 'post_status' => 'private', 'post_title' => 'Earth Systems Program', 'post_name' => 'earth-systems-program', 'post_excerpt' => 'Institutional research project.', 'post_content' => 'Connected research project.', 'post_author' => 7 ),
);
$GLOBALS['meta'] = array(
    1 => array(
        '_sc_institutional_uuid' => '11111111-2222-4333-8444-555555555555',
        '_sc_record_visibility' => 'public',
        '_sc_record_governance_state' => 'managed',
        '_sc_institution_status' => 'active',
        '_sc_institution_short_name' => 'SC',
        '_sc_institution_description' => 'Open institutional research and knowledge infrastructure.',
        '_sc_institution_identifiers' => array( 'ror' => 'https://ror.org/example' ),
        '_sc_institution_public' => '1',
    ),
    2 => array(
        '_sc_institutional_uuid' => '22222222-3333-4444-8555-666666666666',
        '_sc_institution_id' => 1,
        '_sc_record_visibility' => 'public',
        '_sc_record_governance_state' => 'managed',
        '_sc_unit_status' => 'active',
        '_sc_unit_type' => 'lab',
        '_sc_unit_description' => 'Open Knowledge Lab.',
        '_sc_unit_scope' => 'Technology, human rights, and global sustainability.',
        '_sc_unit_public' => '1',
        '_sc_unit_lead_ids' => array( 7 ),
    ),
    10 => array(
        '_sc_institution_id' => 1,
        '_sc_research_unit_ids' => array( 2 ),
        '_sc_record_visibility' => 'public',
        '_sc_record_governance_state' => 'published',
        '_sc_record_steward_id' => 7,
    ),
    12 => array(
        '_sc_institution_id' => 1,
        '_sc_research_unit_ids' => array( 2 ),
        '_sc_record_visibility' => 'public',
        '_sc_record_governance_state' => 'published',
    ),
    11 => array(
        '_sc_institution_id' => 1,
        '_sc_research_unit_ids' => array( 2 ),
        '_sc_record_visibility' => 'restricted',
        '_sc_record_governance_state' => 'review',
        '_sc_record_steward_id' => 7,
    ),
    20 => array(
        '_sc_institution_id' => 1,
        '_sc_research_unit_ids' => array( 2 ),
        '_sc_record_visibility' => 'institution',
        '_sc_record_governance_state' => 'managed',
    ),
);
$GLOBALS['options'] = array(
    'sc_library_v400_platform_settings' => array(
        'schema' => 'sc-library-connected-institutional-platform/1.0',
        'status' => 'active',
        'default_institution' => 1,
        'public_portal' => true,
        'public_search' => true,
        'public_graph' => true,
    ),
    'sc_library_v400_institutional_migration' => array( 'version' => '4.0.0', 'status' => 'pending', 'cursor' => 0, 'processed' => 0, 'total' => 0 ),
);
$GLOBALS['transients'] = array();

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_Post {
    public $ID;
    public $post_type;
    public $post_status;
    public $post_title;
    public $post_name;
    public $post_excerpt;
    public $post_content;
    public $post_author;
}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}

class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_PDF_To_Document {}
class SC_Library_Document_OCR_Processing { const VERSION = '2.4.1'; }
class SC_Library_Citation_Source_Manager { const VERSION = '2.5.1'; const SOURCE_POST_TYPE = 'sc_research_source'; const PROJECT_POST_TYPE = 'sc_research_project'; }
class SC_Library_Scholarly_Library_Connectors { const VERSION = '2.6.1'; }
class SC_Library_Evidence_Claim_Linking { const VERSION = '2.7.0'; const CLAIM_POST_TYPE = 'sc_research_claim'; const NOTE_POST_TYPE = 'sc_evidence_note'; }
class SC_Library_Connected_Research_Environment { const VERSION = '3.0.0'; }
class SC_Library_Source_Versioning_Integrity { const VERSION = '3.1.0'; }
class SC_Library_Topics_Concepts_Relationships {
    const VERSION = '3.2.0';
    const CONCEPT_POST_TYPE = 'sc_library_concept';
    const ENTITY_POST_TYPE = 'sc_named_entity';
    const VOCABULARY_POST_TYPE = 'sc_control_vocab';
    const RELATION_POST_TYPE = 'sc_knowledge_rel';
    const TOPIC_TAXONOMY = 'sc_library_topic';
    const META_RELATION_FROM_KIND = '_sc_relation_from_kind';
    const META_RELATION_FROM_ID = '_sc_relation_from_id';
    const META_RELATION_TO_KIND = '_sc_relation_to_kind';
    const META_RELATION_TO_ID = '_sc_relation_to_id';
    const META_RELATION_TYPE = '_sc_relation_type';
    const META_RELATION_PUBLIC = '_sc_relation_public';
    const META_RELATION_STATUS = '_sc_relation_status';
}
class SC_Library_Knowledge_Pathways_Article_Maps { const VERSION = '3.3.0'; const PATHWAY_POST_TYPE = 'sc_knowledge_path'; }
class SC_Library_Cross_Product_Research_Handoffs {
    const VERSION = '3.4.0'; const HANDOFF_POST_TYPE = 'sc_workspace_handoff';
    public static function create_handoff( $project_id, $args ) { return array( 'handoff_id' => 88, 'project_id' => $project_id, 'target_product' => $args['target_product'], 'type' => $args['handoff_type'] ); }
}
class SC_Library_Research_Quality_Governance { const VERSION = '3.5.0'; const POLICY_POST_TYPE = 'sc_research_policy'; const ISSUE_POST_TYPE = 'sc_quality_issue'; }
class SC_Library_Institutional_Collections_Archives { const VERSION = '3.6.0'; const COLLECTION_POST_TYPE = 'sc_inst_collection'; const COMPONENT_POST_TYPE = 'sc_archive_component'; const ACCESSION_POST_TYPE = 'sc_archive_accession'; }
class SC_Library_Research_Librarian_Document_Intelligence { const VERSION = '3.7.0'; }
class SC_Library_Collaborative_Review_Publishing { const VERSION = '3.8.0'; const REVIEW_POST_TYPE = 'sc_review_cycle'; const PACKAGE_POST_TYPE = 'sc_pub_package'; }
class SC_Library_Public_API_Export_Federation {
    const VERSION = '3.9.0'; const EXPORT_POST_TYPE = 'sc_export_job'; const PEER_POST_TYPE = 'sc_federation_peer';
    public static function serialize_record( $post_id, $type, $private ) {
        return array( 'topics' => array( array( 'id' => 1, 'name' => 'Earth systems', 'slug' => 'earth-systems' ) ), 'collections' => array(), 'source_file' => array(), 'document_intelligence' => array( 'status' => 'ready', 'summary' => 'Public document intelligence.' ) );
    }
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_rest_route() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-:]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) {
    if ( 'timestamp' === $type ) return strtotime( '2026-07-15 14:00:00' );
    if ( 'Y-m-d' === $type ) return '2026-07-15';
    return '2026-07-15 14:00:00';
}
function get_current_user_id() { return 7; }
function current_user_can( $capability = '' ) { return ! empty( $GLOBALS['can_private'] ); }
function get_bloginfo( $field ) { return 'Sustainable Catalyst'; }
function home_url( $path = '' ) { return 'https://example.org' . $path; }
function rest_url( $path = '' ) { return 'https://example.org/wp-json/' . ltrim( $path, '/' ); }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_the_excerpt( $id ) { return $GLOBALS['posts'][ $id ]['post_excerpt'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/record/' . $id; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_post_time( $format, $gmt, $id ) { return '2026-07-15T10:00:00+00:00'; }
function get_post_modified_time( $format, $gmt, $id ) { return '2026-07-15T11:00:00+00:00'; }
function get_post( $id ) {
    if ( empty( $GLOBALS['posts'][ $id ] ) ) return null;
    $post = new WP_Post();
    $post->ID = $id;
    foreach ( $GLOBALS['posts'][ $id ] as $key => $value ) $post->{$key} = $value;
    return $post;
}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_insert_post( $args, $wp_error = false ) {
    $id = ++$GLOBALS['next_id'];
    $GLOBALS['posts'][ $id ] = array( 'post_type' => $args['post_type'], 'post_status' => $args['post_status'], 'post_title' => $args['post_title'] ?? '', 'post_name' => sanitize_key( $args['post_title'] ?? '' ), 'post_excerpt' => '', 'post_content' => '', 'post_author' => $args['post_author'] ?? 0 );
    $GLOBALS['meta'][ $id ] = array();
    return $id;
}
function get_posts( $args ) {
    $ids = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( isset( $args['post_type'] ) && ! in_array( $post['post_type'], (array) $args['post_type'], true ) ) continue;
        if ( isset( $args['post_status'] ) && ! in_array( $post['post_status'], (array) $args['post_status'], true ) ) continue;
        if ( ! empty( $args['s'] ) && false === stripos( $post['post_title'] . ' ' . $post['post_content'], $args['s'] ) ) continue;
        if ( isset( $args['meta_key'] ) ) {
            $value = $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? null;
            if ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) continue;
        }
        if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
            $allowed = true;
            foreach ( $args['meta_query'] as $key => $clause ) {
                if ( 'relation' === $key || ! is_array( $clause ) || isset( $clause['relation'] ) ) continue;
                $value = $GLOBALS['meta'][ $id ][ $clause['key'] ] ?? null;
                if ( 'NOT EXISTS' === ( $clause['compare'] ?? '' ) ) continue;
                if ( 'LIKE' === ( $clause['compare'] ?? '' ) ) {
                    if ( false === strpos( serialize( $value ), (string) $clause['value'] ) ) $allowed = false;
                } elseif ( isset( $clause['value'] ) && (string) $value !== (string) $clause['value'] ) $allowed = false;
            }
            if ( ! $allowed ) continue;
        }
        $ids[] = $id;
    }
    rsort( $ids );
    $offset = absint( $args['offset'] ?? 0 );
    $limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : count( $ids );
    return array_slice( $ids, $offset, $limit );
}
function wp_count_posts( $post_type ) {
    $counts = array( 'publish' => 0, 'private' => 0, 'draft' => 0 );
    foreach ( $GLOBALS['posts'] as $post ) if ( $post['post_type'] === $post_type && isset( $counts[ $post['post_status'] ] ) ) $counts[ $post['post_status'] ]++;
    return (object) $counts;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { static $i = 0; $i++; return sprintf( 'aaaaaaaa-bbbb-4ccc-8ddd-%012d', $i ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function apply_filters( $tag, $value ) { return $value; }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $item ) use ( $field ) { return $item[ $field ] ?? null; }, $list ); }
function wp_next_scheduled( $hook ) { return 1234567890; }
function __( $text, $domain = null ) { return $text; }
function get_term() { return null; }
function get_term_link() { return ''; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-connected-institutional-platform.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) { fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

$reflection = new ReflectionClass( 'SC_Library_Connected_Institutional_Platform' );

$allowed = $reflection->getMethod( 'allowed_value' );
$allowed->setAccessible( true );
assert_same( 'approved', $allowed->invoke( null, 'approved', SC_Library_Connected_Institutional_Platform::governance_states(), 'draft' ), 'Allowed governance retained' );
assert_same( 'draft', $allowed->invoke( null, 'invalid', SC_Library_Connected_Institutional_Platform::governance_states(), 'draft' ), 'Invalid governance falls back' );

$id_list = $reflection->getMethod( 'id_list' );
$id_list->setAccessible( true );
assert_same( array( 1, 2, 3 ), $id_list->invoke( null, '1, 2 2, 3' ), 'ID list normalized' );

$parse = $reflection->getMethod( 'parse_key_value_lines' );
$parse->setAccessible( true );
assert_same( array( 'ror' => 'https://ror.org/example', 'grid' => 'grid.123' ), $parse->invoke( null, "ror=https://ror.org/example\ngrid=grid.123\ninvalid" ), 'Identifier lines parsed' );

$encode = $reflection->getMethod( 'encode_cursor' );
$decode = $reflection->getMethod( 'decode_cursor' );
$encode->setAccessible( true );
$decode->setAccessible( true );
$cursor = $encode->invoke( null, array( 'offset' => 25, 'types' => 'abc' ) );
assert_true( false === strpos( $cursor, '=' ), 'Cursor padding removed' );
assert_same( 25, $decode->invoke( null, $cursor )['offset'], 'Cursor round trip' );
assert_same( array(), $decode->invoke( null, '***' ), 'Invalid cursor rejected' );

$canonical = $reflection->getMethod( 'canonical_json' );
$canonical->setAccessible( true );
assert_same( '{"a":1,"b":{"a":1,"z":2}}', $canonical->invoke( null, array( 'b' => array( 'z' => 2, 'a' => 1 ), 'a' => 1 ) ), 'Canonical JSON recursively sorted' );

$registry = SC_Library_Connected_Institutional_Platform::record_registry();
assert_true( count( $registry ) >= 20, 'Institutional registry includes connected subsystems' );
assert_same( 'sc_foundation_doc', $registry['document']['post_type'], 'Document type mapped' );
assert_same( 'sc_research_project', $registry['project']['post_type'], 'Project type mapped' );
assert_same( 'sc_institution', $registry['institution']['post_type'], 'Institution type mapped' );
assert_same( 'sc_research_unit', $registry['unit']['post_type'], 'Unit type mapped' );

$urn = SC_Library_Connected_Institutional_Platform::ensure_record_identity( 10 );
assert_true( 0 === strpos( $urn, 'urn:sc-library:' ), 'Institutional URN created' );
assert_true( ! empty( $GLOBALS['meta'][10]['_sc_institutional_uuid'] ), 'Record UUID created' );
assert_same( $urn, $GLOBALS['meta'][10]['_sc_record_urn'], 'URN persisted' );

$record = SC_Library_Connected_Institutional_Platform::serialize_record( 10, false );
assert_same( 'sc-library-institutional-record/1.0', $record['schema'], 'Record schema' );
assert_same( 'document', $record['type'], 'Document record type' );
assert_same( 'public', $record['visibility'], 'Public visibility retained' );
assert_same( 1, $record['institution_id'], 'Institution link retained' );
assert_same( 2, $record['unit_ids'][0], 'Unit link retained' );
assert_same( 'Sustainable Catalyst', $record['institution']['title'], 'Institution identity embedded' );
assert_same( 'Open Knowledge Lab', $record['units'][0]['title'], 'Unit identity embedded' );
assert_true( strlen( $record['content_hash'] ) === 64, 'Record content hash' );
assert_true( isset( $record['document_intelligence']['summary'] ), 'Document intelligence integrated' );
assert_true( ! isset( $record['raw_content'] ), 'Public record omits raw content' );

$private = SC_Library_Connected_Institutional_Platform::serialize_record( 11, true );
assert_same( 'restricted', $private['visibility'], 'Private record available to institutional reader' );
assert_true( isset( $private['raw_content'] ), 'Private record includes raw content' );
assert_same( 7, $private['steward_id'], 'Private steward included' );

$GLOBALS['can_private'] = false;
assert_same( array(), SC_Library_Connected_Institutional_Platform::serialize_record( 11, false ), 'Restricted record blocked publicly' );
assert_true( ! empty( SC_Library_Connected_Institutional_Platform::serialize_record( 10, false ) ), 'Public record remains available' );
$GLOBALS['can_private'] = true;

$registered = SC_Library_Connected_Institutional_Platform::register_record( 10, true );
assert_true( strlen( $registered['registry_hash'] ) === 64, 'Registry SHA-256 created' );
assert_true( ! empty( $GLOBALS['meta'][10]['_sc_record_registered_at'] ), 'Registration timestamp persisted' );

$GLOBALS['can_private'] = false;
$search = SC_Library_Connected_Institutional_Platform::institutional_search( array( 'types' => array( 'document' ), 'limit' => 1 ), false );
assert_same( 'sc-library-institutional-search/1.0', $search['schema'], 'Search schema' );
assert_same( 1, $search['count'], 'Search limit applied' );
assert_same( 'document', $search['records'][0]['type'], 'Search returns document' );
assert_true( ! empty( $search['next_cursor'] ), 'Search cursor returned' );
assert_same( 1, $search['facets']['document'], 'Search facet counted' );
assert_true( 0 === strpos( $search['etag'], '"' ), 'Search ETag quoted' );
$GLOBALS['can_private'] = true;

$graph = SC_Library_Connected_Institutional_Platform::knowledge_graph( array( 'kind' => 'document', 'id' => 10, 'depth' => 1 ), true );
assert_true( ! is_wp_error( $graph ), 'Institutional graph created' );
assert_same( 'sc-library-institutional-knowledge-graph/1.0', $graph['schema'], 'Graph schema' );
assert_true( $graph['node_count'] >= 3, 'Graph includes record, institution, and unit nodes' );
assert_true( $graph['edge_count'] >= 2, 'Graph includes institutional edges' );
assert_true( strlen( $graph['graph_sha256'] ) === 64, 'Graph SHA-256 created' );
$edge_types = array_map( static function ( $edge ) { return $edge['type']; }, $graph['edges'] );
assert_true( in_array( 'governed-by-institution', $edge_types, true ), 'Institution edge included' );
assert_true( in_array( 'managed-by-unit', $edge_types, true ), 'Unit edge included' );

$health = SC_Library_Connected_Institutional_Platform::health_report( true );
assert_same( 'sc-library-institutional-health/1.0', $health['schema'], 'Health schema' );
assert_same( 'ready', $health['status'], 'Health ready with all fixture subsystems' );
assert_same( 100, $health['score'], 'Health score 100' );
assert_true( $health['component_count'] >= 17, 'Health includes platform components' );

$summary = SC_Library_Connected_Institutional_Platform::platform_summary( true );
assert_same( 'sc-library-connected-institutional-platform/1.0', $summary['schema'], 'Platform schema' );
assert_same( '4.0.0', $summary['version'], 'Platform version' );
assert_true( isset( $summary['record_counts']['document'] ), 'Platform includes record counts' );
assert_true( isset( $summary['capabilities'] ), 'Private platform summary includes capabilities' );

$workspace = SC_Library_Connected_Institutional_Platform::workspace_report();
assert_same( 'sc-library-institutional-workspace/1.0', $workspace['schema'], 'Workspace schema' );
assert_true( $workspace['total_records'] >= 5, 'Workspace totals records' );
assert_same( 1, $workspace['institution_count'], 'Workspace institution count' );
assert_same( 1, $workspace['unit_count'], 'Workspace unit count' );
assert_true( isset( $workspace['governance']['published'] ), 'Workspace governance counts' );

$envelope = SC_Library_Connected_Institutional_Platform::build_handoff_envelope( array( 'project_id' => 20, 'target_product' => 'research-lab', 'handoff_type' => 'research-context', 'records' => array( 10 ), 'sections' => array( 'documents', 'institutional' ), 'request' => array( 'purpose' => 'analysis' ) ), true );
assert_same( 'sc-platform-handoff/institutional-research/1.0', $envelope['schema'], 'Handoff schema' );
assert_same( 'knowledge-library', $envelope['source_product'], 'Handoff source product' );
assert_same( '4.0.0', $envelope['source_version'], 'Handoff source version' );
assert_same( 2, count( $envelope['records'] ), 'Project and document included' );
assert_same( 1, count( $envelope['institutions'] ), 'Handoff institution included' );
assert_same( 1, count( $envelope['units'] ), 'Handoff unit included' );
assert_true( strlen( $envelope['checksum'] ) === 64, 'Handoff checksum' );

$handoff = SC_Library_Connected_Institutional_Platform::create_platform_handoff( array( 'project_id' => 20, 'target_product' => 'research-lab', 'handoff_type' => 'research-context', 'records' => array( 10 ) ) );
assert_true( ! is_wp_error( $handoff ), 'Platform handoff created' );
assert_same( 88, $handoff['handoff']['handoff_id'], 'Existing cross-product system invoked' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( 'sc-library-institutional-migration/1.0', $migration['schema'], 'Migration schema' );
assert_same( '4.0.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts zero' );

echo "Connected Institutional Knowledge and Research Platform fixture checks passed: 83\n";
