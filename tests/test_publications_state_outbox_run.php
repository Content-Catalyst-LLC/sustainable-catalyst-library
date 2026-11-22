<?php
/** Isolated PHP contract tests for the opt-in state outbox worker. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
    public function __construct(
        public int $ID,
        public string $post_type = 'post',
        public string $url = 'https://sustainablecatalyst.com/erw/'
    ) {}
}
class WP_Error {
    public function __construct( public array $data = array() ) {}
    public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function absint( $value ): int { return abs( (int) $value ); }
function wp_json_encode( $value, $options = 0 ): string { return json_encode( $value, $options ); }
function get_post( int $id ) { return $GLOBALS['posts'][$id] ?? null; }
function get_permalink( WP_Post $post ): string { return $post->url; }
function get_option( string $name, $default = null ) {
    return $GLOBALS['options'][$name] ?? $default;
}
function delete_option( string $name ): bool {
    unset( $GLOBALS['options'][$name] );
    $GLOBALS['deleted'][] = $name;
    return true;
}
function update_option( string $name, $value, bool $autoload = false ): bool {
    $GLOBALS['options'][$name] = $value;
    return true;
}
function wp_next_scheduled( string $hook, array $args = array() ) {
    return 1; // Prevent any scheduling in this isolated run test.
}
class FakeWPDB {
    public string $options = 'wp_options';
    public function esc_like( $s ) { return $s; }
    public function prepare( $query, ...$args ) { return $query; }
    public function get_col( $sql ): array {
        return array_keys( array_filter(
            $GLOBALS['options'],
            fn( $k ) => str_starts_with( $k, 'sc_pub_discovery_state_event_' ),
            ARRAY_FILTER_USE_KEY
        ) );
    }
}
$GLOBALS['wpdb'] = new FakeWPDB;
class SC_Library_Publications_Discovery_Manifest {
    public static function enabled(): bool { return true; }
    public static function metadata_for_post( WP_Post $post ): array {
        return array(
            'publication_discovery_approved' => true,
            'wordpress_public_verified' => true,
            'wordpress_password_protected' => false,
            'publication_fingerprint' => str_repeat( 'a', 64 ),
            'publication_field' => 'sustainable-systems',
            'publication_map_key' => 'enhanced-rock-weathering',
        );
    }
}
class SC_Library_Python_Backend {
    public static function configured(): bool { return true; }
    public function sync_post( int $id ): void { $GLOBALS['reindexed'][] = $id; }
    public static function signed_request( string $method, string $path, string $body ) {
        $GLOBALS['requests'][] = array( 'method' => $method, 'path' => $path );
        $event = json_decode( $body, true );
        if ( ! empty( $GLOBALS['force_index_mismatch'] ) && count( $GLOBALS['requests'] ) === 1 ) {
            return new WP_Error( array( 'status' => 409, 'body' => array(
                'detail' => 'index-not-current-or-unapproved',
            ) ) );
        }
        return array(
            'record_id' => $event['record_id'],
            'sequence' => $event['sequence'],
            'state' => 'accepted',
        );
    }
}
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-publications-state-outbox.php';

function check( bool $ok, string $label ): void {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
    echo "PASS: {$label}\n";
}
function fresh(): string {
    $name = 'sc_pub_discovery_state_event_0000000000000000123';
    $GLOBALS['options'] = array(
        $name => array(
            'record_id' => 'wordpress:1:post:123',
            'post_id' => 123, 'sequence' => 123,
            'attempts' => 0, 'retry_after' => 0,
        ),
    );
    $GLOBALS['posts'] = array( 123 => new WP_Post(123) );
    $GLOBALS['deleted'] = array();
    $GLOBALS['requests'] = array();
    $GLOBALS['reindexed'] = array();
    $GLOBALS['force_index_mismatch'] = false;
    return $name;
}

$name = fresh();
( new SC_Library_Publications_State_Outbox() )->run();
check( ! isset( $GLOBALS['options'][$name] ), 'accepted approval acknowledged and dequeued' );
check( count( $GLOBALS['requests'] ) === 1, 'unchanged approval uses one signed event' );
check( count( $GLOBALS['reindexed'] ) === 0, 'unchanged approval avoids full article re-index' );

$name = fresh();
$GLOBALS['force_index_mismatch'] = true;
( new SC_Library_Publications_State_Outbox() )->run();
check( ! isset( $GLOBALS['options'][$name] ), 'newly indexed approval acknowledged' );
check( count( $GLOBALS['requests'] ) === 2, '409 index mismatch triggers exact event retry' );
check( $GLOBALS['reindexed'] === array( 123 ), 'only changed article re-indexed' );

$name = fresh();
unset( $GLOBALS['posts'][123] );
( new SC_Library_Publications_State_Outbox() )->run();
check( ! isset( $GLOBALS['options'][$name] ), 'deleted article withdrawal acknowledged' );
check( count( $GLOBALS['reindexed'] ) === 0, 'withdrawal never re-indexes deleted article' );

echo "SC-PUB-SEO-03B2 outbox run contract: all checks passed.\n";
