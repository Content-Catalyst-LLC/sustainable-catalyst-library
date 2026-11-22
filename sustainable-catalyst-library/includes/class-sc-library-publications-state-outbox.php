<?php
/**
 * SC-PUB-SEO-03B2 — persistent, replayable WordPress publications state outbox.
 *
 * Local/staging only. A monotonically numbered non-autoloaded option is
 * committed before each network attempt. Withdrawals do not depend on a
 * successful DELETE of the underlying Library record.
 *
 * No activation/production write path is introduced in this release.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications_State_Outbox {
    public const CRON = 'sc_pub_discovery_state_outbox';
    public const RECONCILE = 'sc_pub_discovery_state_reconcile';
    private const SEQ_OPTION = 'sc_pub_discovery_state_seq';
    private const EVENT_PREFIX = 'sc_pub_discovery_state_event_';
    private const OFFSET_OPTION = 'sc_pub_discovery_reconcile_offset';
    private const EVENT_API = '/v1/publications/manifest/events';
    private const MAX_BATCH = 40;
    // The 15-minute approval lease supports ~300 approved records at
    // 40 records / 2 minutes in ideal conditions. The separate outbox
    // worker runs every minute, leaving headroom for delayed events. Staging must verify cadence,
    // batch completion, and total eligible record count before activation.
    private const MAX_SCAN = 800;

    public static function enabled(): bool {
        return class_exists( 'SC_Library_Publications_Discovery_Manifest', false )
            && SC_Library_Publications_Discovery_Manifest::enabled()
            && class_exists( 'SC_Library_Python_Backend', false )
            && SC_Library_Python_Backend::configured();
    }

    public function register_hooks(): void {
        if ( ! self::enabled() ) { return; }
        add_action( 'save_post', array( $this, 'on_save' ), 950, 3 );
        add_action( 'transition_post_status', array( $this, 'on_status' ), 950, 3 );
        add_action( 'before_delete_post', array( $this, 'on_delete' ), 950, 1 );
        add_action( self::CRON, array( $this, 'run' ), 10, 0 );
        add_action( self::RECONCILE, array( $this, 'reconcile' ), 10, 0 );
        add_filter( 'cron_schedules', array( $this, 'schedules' ) );
        add_action( 'init', array( $this, 'schedule' ), 100 );
    }

    public function schedules( array $schedules ): array {
        $schedules['sc_pub_two_minutes'] = array( 'interval' => 120, 'display' => 'Publications state reconcile' );
        return $schedules;
    }

    public function schedule(): void {
        if ( ! self::enabled() ) { return; }
        if ( ! wp_next_scheduled( self::RECONCILE ) ) {
            wp_schedule_event( time() + 60, 'sc_pub_five_minutes', self::RECONCILE );
        }
        if ( ! wp_next_scheduled( self::CRON ) ) {
            wp_schedule_single_event( time() + 15, self::CRON );
        }
    }

    public function on_save( int $post_id, WP_Post $post, bool $update ): void {
        unset( $update );
        if ( 'post' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        $this->enqueue( $post_id, $post->post_type );
    }

    public function on_status( string $new, string $old, WP_Post $post ): void {
        if ( $new !== $old && 'post' === $post->post_type ) {
            $this->enqueue( (int) $post->ID, $post->post_type );
        }
    }

    public function on_delete( int $post_id ): void {
        $post = get_post( $post_id );
        if ( $post instanceof WP_Post && 'post' === $post->post_type ) {
            $this->enqueue( $post_id, $post->post_type );
        }
    }

    /**
     * Atomic global source sequence, allocated on the same WordPress DB
     * connection via MySQL LAST_INSERT_ID(). Event option is durable even if
     * the backend or WP cron is offline. No network request runs in save hooks.
     */
    public static function next_sequence(): int {
        global $wpdb;
        add_option( self::SEQ_OPTION, '0', '', false );
        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1) WHERE option_name = %s",
            self::SEQ_OPTION
        );
        if ( 1 !== $wpdb->query( $sql ) ) { return 0; }
        return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
    }

    public function enqueue( int $post_id, string $post_type = 'post' ): bool {
        if ( ! self::enabled() || $post_id < 1 || 'post' !== $post_type ) { return false; }
        $sequence = self::next_sequence();
        if ( $sequence < 1 ) {
            error_log( '[SC Publications] State outbox failed to allocate sequence' );
            return false;
        }
        $key = self::EVENT_PREFIX . sprintf( '%019d', $sequence );
        $queued = add_option( $key, array(
            'sequence' => $sequence,
            'record_id' => SC_Library_Python_Backend::record_id( $post_id, $post_type ),
            'post_id' => $post_id,
            'attempts' => 0,
            'retry_after' => 0,
        ), '', false );
        if ( ! $queued ) {
            error_log( '[SC Publications] Failed to persist editorial state event ' . $sequence );
            return false;
        }
        // Distinct urgency argument avoids a delayed 5-min polling event
        // coalescing a new withdrawal. WP cron still needs real scheduler.
        wp_schedule_single_event( time() + 5, self::CRON, array( 'urgent' ) );
        return true;
    }

    /** @return array<string,mixed> */
    public static function event_for( array $queued ): array {
        $record_id = (string) ( $queued['record_id'] ?? '' );
        $sequence = absint( $queued['sequence'] ?? 0 );
        $post_id = absint( $queued['post_id'] ?? 0 );
        $post = $post_id ? get_post( $post_id ) : null;
        $meta = $post instanceof WP_Post && 'post' === $post->post_type
            ? SC_Library_Publications_Discovery_Manifest::metadata_for_post( $post ) : array();
        $approved = $post instanceof WP_Post && ! empty( $meta['publication_discovery_approved'] )
            && ! empty( $meta['wordpress_public_verified'] )
            && empty( $meta['wordpress_password_protected'] );
        $fingerprint = $approved ? (string) ( $meta['publication_fingerprint'] ?? '' )
            : hash( 'sha256', 'withdraw|' . $record_id . '|' . $sequence );
        return array(
            'schema' => 'sc-publications-state-event/1.0',
            'record_id' => $record_id,
            'sequence' => $sequence,
            'action' => $approved ? 'approve' : 'withdraw',
            'canonical_url' => $approved ? (string) get_permalink( $post ) : '',
            'fingerprint' => $fingerprint,
            'field' => $approved ? (string) $meta['publication_field'] : '',
            'map_key' => $approved ? (string) $meta['publication_map_key'] : '',
        );
    }

    /**
     * Process the newest queued state first so a withdrawal is never held
     * behind an older failed approval. Backend ignores superseded sequence.
     */
    public function run(): void {
        if ( ! self::enabled() ) { return; }
        global $wpdb;
        $prefix = self::EVENT_PREFIX;
        // Scan beyond delayed/retrying rows so they do not starve newly
        // eligible events. Never process more than MAX_BATCH network events
        // per invocation. Queue growth beyond MAX_SCAN is a staging NO-GO.
        $names = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC LIMIT %d",
            $wpdb->esc_like( $prefix ) . '%', self::MAX_SCAN
        ) );
        if ( count( (array) $names ) >= self::MAX_SCAN ) {
            error_log( '[SC Publications] Outbox scan ceiling reached; staging release must remain disabled' );
        }
        $processed = 0;
        foreach ( (array) $names as $key ) {
            if ( $processed >= self::MAX_BATCH ) { break; }
            $queued = get_option( (string) $key );
            if ( ! is_array( $queued ) ) { continue; }
            if ( absint( $queued['retry_after'] ?? 0 ) > time() ) { continue; }
            $processed++;
            $event = self::event_for( $queued );
            $event_body = (string) wp_json_encode( $event, JSON_UNESCAPED_SLASHES );
            // Renew an unchanged, already-indexed signed approval without
            // resending the whole WordPress article on every reconciliation.
            $result = SC_Library_Python_Backend::signed_request(
                'POST', self::EVENT_API, $event_body
            );
            // On a genuinely new or changed editorial snapshot, the backend
            // denies approval until the matching signed catalog ingest lands.
            // Reindex only on that exact rejection, then retry the same event.
            if ( 'approve' === $event['action'] && is_wp_error( $result ) ) {
                $error_data = $result->get_error_data();
                $body = is_array( $error_data ) ? ( $error_data['body'] ?? null ) : null;
                if ( is_array( $error_data ) && (int) ( $error_data['status'] ?? 0 ) === 409
                    && is_array( $body )
                    && (string) ( $body['detail'] ?? '' ) === 'index-not-current-or-unapproved' ) {
                    $post = get_post( (int) $queued['post_id'] );
                    if ( $post instanceof WP_Post ) {
                        ( new SC_Library_Python_Backend() )->sync_post( (int) $post->ID );
                        $result = SC_Library_Python_Backend::signed_request(
                            'POST', self::EVENT_API, $event_body
                        );
                    }
                }
            }
            if ( ! is_wp_error( $result )
                && (string) ( $result['record_id'] ?? '' ) === $event['record_id']
                && absint( $result['sequence'] ?? 0 ) >= (int) $event['sequence']
                && in_array( $result['state'] ?? '', array( 'accepted', 'replayed', 'superseded' ), true ) ) {
                delete_option( (string) $key );
                continue;
            }
            $queued['attempts'] = absint( $queued['attempts'] ?? 0 ) + 1;
            $queued['retry_after'] = time() + min( 900, 15 * ( 2 ** min( 6, $queued['attempts'] ) ) );
            update_option( (string) $key, $queued, false );
            if ( $queued['attempts'] === 1 || 0 === $queued['attempts'] % 8 ) {
                error_log( '[SC Publications] Delayed publication-state replay for sequence ' . $event['sequence'] );
            }
        }
        if ( ! wp_next_scheduled( self::CRON ) ) {
            wp_schedule_single_event( time() + 60, self::CRON );
        }
    }

    /**
     * Lease renewal. Cursor walks *all* published mapped posts over successive
     * two-minute ticks; no one-time recent-post window is treated as complete.
     * Scheduled event and outbox retry require system cron in production.
     */
    public function reconcile(): void {
        if ( ! self::enabled() ) { return; }
        $offset = absint( get_option( self::OFFSET_OPTION, 0 ) );
        $batch = get_posts( array(
            'post_type' => 'post', 'post_status' => 'publish',
            'meta_key' => SC_Library_Publications_Discovery_Manifest::MAP_META,
            'posts_per_page' => self::MAX_BATCH, 'offset' => $offset,
            'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids',
            'no_found_rows' => true, 'suppress_filters' => true,
        ) );
        foreach ( (array) $batch as $post_id ) {
            $this->enqueue( absint( $post_id ) );
        }
        update_option( self::OFFSET_OPTION,
            count( (array) $batch ) < self::MAX_BATCH ? 0 : $offset + count( (array) $batch ),
            false
        );
    }
}
