<?php
/** State outbox event serializer: real metadata and no public leakage. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
    public function __construct(
        public int $ID,
        public string $post_type,
        public string $url,
        public bool $allowed = true
    ) {}
}
class SC_Library_Publications_Discovery_Manifest {
    public const MAP_META = '_sc_publication_article_map_key_v4337';
    public static function enabled(): bool { return true; }
    public static function metadata_for_post( WP_Post $post ): array {
        return array(
            'publication_discovery_approved' => $post->allowed,
            'wordpress_public_verified' => $post->allowed,
            'wordpress_password_protected' => ! $post->allowed,
            'publication_fingerprint' => str_repeat( 'a', 64 ),
            'publication_field' => 'sustainable-systems',
            'publication_map_key' => 'enhanced-rock-weathering',
        );
    }
}
class SC_Library_Python_Backend {
    public static function configured(): bool { return true; }
}
function get_post( int $id ) {
    return $GLOBALS['posts'][ $id ] ?? null;
}
function get_permalink( WP_Post $post ): string { return $post->url; }
function absint( $value ): int { return abs( (int) $value ); }

require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-publications-state-outbox.php';

function check( bool $condition, string $label ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
    echo "PASS: {$label}\n";
}

$queued = array(
    'record_id' => 'wordpress:1:post:123',
    'sequence' => 7,
    'post_id' => 123,
    'debug_note' => 'this private note must not be serialized',
);
$GLOBALS['posts'] = array( 123 => new WP_Post(
    123, 'post', 'https://sustainablecatalyst.com/erw/'
) );
$approved = SC_Library_Publications_State_Outbox::event_for( $queued );
check( $approved['schema'] === 'sc-publications-state-event/1.0', 'event contract' );
check( $approved['action'] === 'approve', 'current public editorial state approves' );
check( $approved['sequence'] === 7, 'preserves source sequence' );
check( $approved['canonical_url'] === 'https://sustainablecatalyst.com/erw/', 'exact permalink' );
check( $approved['fingerprint'] === str_repeat( 'a', 64 ), 'indexed revision fingerprint' );
check( ! isset( $approved['debug_note'] ), 'does not serialize outbox metadata' );

$GLOBALS['posts'][123]->allowed = false;
$hidden = SC_Library_Publications_State_Outbox::event_for( $queued );
check( $hidden['action'] === 'withdraw', 'protected or unapproved post withdraws' );
check( '' === $hidden['canonical_url'] && '' === $hidden['map_key'], 'withdrawal contains no private content or routing' );
check( strlen( $hidden['fingerprint'] ) === 64, 'withdrawal has stable digest' );

unset( $GLOBALS['posts'][123] );
$deleted = SC_Library_Publications_State_Outbox::event_for( $queued );
check( $deleted['action'] === 'withdraw', 'deleted post is durable tombstone' );
check( '' === $deleted['field'] && '' === $deleted['map_key'], 'no private metadata on deleted post' );
echo "SC-PUB-SEO-03B2 outbox event contract: all checks passed.\n";
