<?php
/**
 * Citation formatting and source reliability companion.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Citation_Source_Reliability {
    public const VERSION = '2.5.1';
    public const HISTORY_SCHEMA = 'sc-library-source-history/1.0';
    public const RELIABILITY_SCHEMA = 'sc-library-source-reliability/1.0';

    public const META_VALIDATION = '_sc_source_validation_issues';
    public const META_COMPLETENESS = '_sc_source_completeness_score';
    public const META_RELIABILITY_STATUS = '_sc_source_reliability_status';
    public const META_METADATA_HASH = '_sc_source_metadata_hash';
    public const META_HISTORY = '_sc_source_metadata_history';
    public const META_LAST_CHANGE = '_sc_source_last_metadata_change';
    public const META_DUPLICATE_DECISIONS = '_sc_source_duplicate_decisions';
    public const META_CANONICAL_ID = '_sc_source_canonical_id';
    public const META_CITATION_CACHE = '_sc_source_citation_cache';
    public const META_CITATION_CACHE_VERSION = '_sc_source_citation_cache_version';
    public const META_LAST_RELIABILITY_CHECK = '_sc_source_last_reliability_check';
    public const META_VERIFICATION_INVALIDATED = '_sc_source_verification_invalidated';

    public const HISTORY_LIMIT = 20;
    public const WRITE_LIMIT = 60;
    public const WRITE_WINDOW = HOUR_IN_SECONDS;

    private static $update_context = array();
    private static $restored = false;

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_migrate_sources' ), 280 );
        add_action( 'pre_post_update', array( $this, 'capture_pre_post_update' ), 10, 2 );
        add_action( 'added_post_meta', array( $this, 'invalidate_citation_meta' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'invalidate_citation_meta' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'invalidate_citation_meta' ), 10, 4 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 70 );
        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, array( $this, 'save_reliability_controls' ), 95, 3 );
        add_action( 'admin_post_sc_library_v251_restore_source_snapshot', array( $this, 'admin_restore_snapshot' ) );
        add_action( 'admin_post_sc_library_v251_recheck_source', array( $this, 'admin_recheck_source' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 205 );

        add_filter( 'sc_library_source_duplicate_candidates', array( $this, 'filter_duplicate_candidates' ), 10, 2 );
        add_filter( 'manage_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE . '_posts_columns', array( $this, 'source_columns' ), 30 );
        add_action( 'manage_' . SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE . '_posts_custom_column', array( $this, 'source_column_content' ), 30, 2 );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 40 );
        add_filter( 'rest_post_dispatch', array( $this, 'rest_response_headers' ), 20, 3 );
    }

    public function maybe_migrate_sources() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) || get_option( 'sc_library_citation_reliability_version' ) === self::VERSION ) { return; }
        global $wpdb;
        $cursor = absint( get_option( 'sc_library_citation_reliability_cursor', 0 ) );
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d AND post_status IN ('publish','draft','pending','private','future') ORDER BY ID ASC LIMIT 25",
                SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                $cursor
            )
        );
        $ids = array_map( 'absint', (array) $ids );
        if ( ! $ids ) {
            update_option( 'sc_library_citation_reliability_version', self::VERSION, false );
            delete_option( 'sc_library_citation_reliability_cursor' );
            return;
        }
        foreach ( $ids as $source_id ) {
            SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
            self::finalize_source_update( $source_id, '1' === get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, true ), 'migration' );
            $cursor = max( $cursor, $source_id );
        }
        update_option( 'sc_library_citation_reliability_cursor', $cursor, false );
    }

    public function capture_pre_post_update( $post_id, $data ) {
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $post_id ) ) {
            self::begin_source_update( $post_id, 'admin' );
        }
    }

    public function invalidate_citation_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $post_id ) ) { return; }
        if ( in_array( $meta_key, array( self::META_CITATION_CACHE, self::META_CITATION_CACHE_VERSION ), true ) ) { return; }
        if ( 0 === strpos( (string) $meta_key, '_sc_source_' ) ) {
            delete_post_meta( $post_id, self::META_CITATION_CACHE );
        }
    }

    public static function begin_source_update( $source_id, $context = 'unknown' ) {
        $source_id = absint( $source_id );
        if ( ! $source_id || isset( self::$update_context[ $source_id ] ) ) {
            return;
        }

        self::$update_context[ $source_id ] = array(
            'context'      => sanitize_key( $context ),
            'started_at'   => current_time( 'mysql' ),
            'actor_id'     => get_current_user_id(),
            'verified'     => '1' === get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, true ),
            'last_verified'=> (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LAST_VERIFIED, true ),
            'snapshot'     => self::snapshot_source( $source_id ),
            'metadata_hash'=> (string) get_post_meta( $source_id, self::META_METADATA_HASH, true ),
        );
    }

    public static function admin_verification_confirmation( $source_id ) {
        $before_verified = ! empty( self::$update_context[ absint( $source_id ) ]['verified'] );
        if ( $before_verified ) { return isset( $_POST['sc_source_reverify'] ); }
        return isset( $_POST['sc_source_verified'] );
    }

    public static function finalize_source_update( $source_id, $explicit_verified = false, $context = '' ) {
        $source_id = absint( $source_id );
        if ( ! $source_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $source_id ) ) {
            return;
        }

        $before = self::$update_context[ $source_id ] ?? array(
            'context'       => sanitize_key( $context ?: 'rebuild' ),
            'started_at'    => current_time( 'mysql' ),
            'actor_id'      => get_current_user_id(),
            'verified'      => '1' === get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, true ),
            'last_verified' => (string) get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LAST_VERIFIED, true ),
            'snapshot'      => array(),
            'metadata_hash' => (string) get_post_meta( $source_id, self::META_METADATA_HASH, true ),
        );
        unset( self::$update_context[ $source_id ] );

        $after = self::snapshot_source( $source_id );
        $changed_fields = self::changed_fields( $before['snapshot'], $after );
        $new_hash = self::metadata_hash( $after );
        $critical = array_values(
            array_intersect(
                $changed_fields,
                array(
                    'title', 'authors', 'organization', 'organization_short', 'editors', 'year', 'publication_date',
                    'source_type', 'container_title', 'publisher', 'place', 'edition', 'volume', 'issue', 'pages',
                    'chapter', 'report_number', 'standard_number', 'jurisdiction', 'doi', 'isbn', 'pmid', 'url',
                    'archive_url', 'access_date', 'language',
                )
            )
        );

        if ( $changed_fields ) {
            self::append_history(
                $source_id,
                array(
                    'schema'         => self::HISTORY_SCHEMA,
                    'changed_at'     => current_time( 'mysql' ),
                    'actor_id'       => absint( $before['actor_id'] ?? get_current_user_id() ),
                    'context'        => sanitize_key( $context ?: ( $before['context'] ?? 'unknown' ) ),
                    'changed_fields' => $changed_fields,
                    'before'         => $before['snapshot'],
                    'after_hash'     => $new_hash,
                )
            );
            update_post_meta(
                $source_id,
                self::META_LAST_CHANGE,
                array(
                    'changed_at'     => current_time( 'mysql' ),
                    'actor_id'       => get_current_user_id(),
                    'context'        => sanitize_key( $context ?: ( $before['context'] ?? 'unknown' ) ),
                    'changed_fields' => $changed_fields,
                )
            );
        }

        if ( ! $explicit_verified && ! empty( $before['verified'] ) && $critical ) {
            update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_VERIFIED, '0' );
            if ( ! empty( $before['last_verified'] ) ) { update_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LAST_VERIFIED, $before['last_verified'] ); }
            else { delete_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_LAST_VERIFIED ); }
            update_post_meta(
                $source_id,
                self::META_VERIFICATION_INVALIDATED,
                array(
                    'invalidated_at' => current_time( 'mysql' ),
                    'changed_fields' => $critical,
                )
            );
        } elseif ( $explicit_verified ) {
            delete_post_meta( $source_id, self::META_VERIFICATION_INVALIDATED );
        }

        update_post_meta( $source_id, self::META_METADATA_HASH, $new_hash );
        delete_post_meta( $source_id, self::META_CITATION_CACHE );
        update_post_meta( $source_id, self::META_CITATION_CACHE_VERSION, self::VERSION );
        self::recalculate_reliability( $source_id );
    }

    public static function enforce_write_rate_limit( WP_REST_Request $request, $operation ) {
        if ( apply_filters( 'sc_library_citation_disable_write_rate_limit', false, $request, $operation ) ) {
            return true;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'sc_library_auth_required', __( 'Authentication is required for citation write operations.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) );
        }

        $limit = max( 1, absint( apply_filters( 'sc_library_citation_write_limit', self::WRITE_LIMIT, $user_id, $operation ) ) );
        $window = max( MINUTE_IN_SECONDS, absint( apply_filters( 'sc_library_citation_write_window', self::WRITE_WINDOW, $user_id, $operation ) ) );
        $bucket = 'sc_cite_rl_' . md5( $user_id . '|' . sanitize_key( $operation ) . '|' . floor( time() / $window ) );
        $count = absint( get_transient( $bucket ) );
        if ( $count >= $limit ) {
            return new WP_Error(
                'sc_library_rate_limited',
                __( 'The citation write limit has been reached. Try again after the current rate window.', 'sustainable-catalyst-library' ),
                array( 'status' => 429, 'retry_after' => $window )
            );
        }
        set_transient( $bucket, $count + 1, $window + MINUTE_IN_SECONDS );
        return true;
    }

    public static function validate_expected_modified( $source_id, WP_REST_Request $request ) {
        $expected = sanitize_text_field( $request->get_param( 'expected_modified_gmt' ) ?: $request->get_header( 'If-Unmodified-Since' ) );
        if ( ! $expected ) {
            return true;
        }

        $actual = get_post_modified_time( 'c', true, $source_id );
        $expected_timestamp = strtotime( $expected );
        $actual_timestamp = strtotime( $actual );
        if ( ! $expected_timestamp || ! $actual_timestamp || $expected_timestamp !== $actual_timestamp ) {
            return new WP_Error(
                'sc_library_source_conflict',
                __( 'The source changed after it was loaded. Refresh the record before submitting another update.', 'sustainable-catalyst-library' ),
                array( 'status' => 409, 'modified_gmt' => $actual )
            );
        }
        return true;
    }

    public static function idempotent_create_response( WP_REST_Request $request ) {
        $key = sanitize_text_field( $request->get_header( 'Idempotency-Key' ) );
        if ( ! $key || ! get_current_user_id() ) {
            return null;
        }
        $transient = 'sc_cite_idem_' . md5( get_current_user_id() . '|' . $key );
        $source_id = absint( get_transient( $transient ) );
        if ( $source_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $source_id ) && current_user_can( 'edit_post', $source_id ) ) {
            return $source_id;
        }
        return null;
    }

    public static function remember_idempotent_create( WP_REST_Request $request, $source_id ) {
        $key = sanitize_text_field( $request->get_header( 'Idempotency-Key' ) );
        if ( $key && get_current_user_id() && $source_id ) {
            set_transient( 'sc_cite_idem_' . md5( get_current_user_id() . '|' . $key ), absint( $source_id ), DAY_IN_SECONDS );
        }
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-source-reliability',
            __( 'Citation Reliability', 'sustainable-catalyst-library' ),
            array( $this, 'render_reliability_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-source-duplicate-review',
            __( 'Duplicate Reconciliation', 'sustainable-catalyst-library' ),
            array( $this, 'render_duplicate_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'sc-source-change-history',
            __( 'Metadata Change Review', 'sustainable-catalyst-library' ),
            array( $this, 'render_history_box' ),
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'normal',
            'low'
        );
    }

    public function render_reliability_box( $post ) {
        $score = absint( get_post_meta( $post->ID, self::META_COMPLETENESS, true ) );
        $status = sanitize_key( get_post_meta( $post->ID, self::META_RELIABILITY_STATUS, true ) ?: 'unchecked' );
        $issues = get_post_meta( $post->ID, self::META_VALIDATION, true );
        $issues = is_array( $issues ) ? $issues : array();
        $invalidated = get_post_meta( $post->ID, self::META_VERIFICATION_INVALIDATED, true );
        ?>
        <div class="sc-source-reliability-card is-<?php echo esc_attr( $status ); ?>">
            <strong><?php echo esc_html( sprintf( __( '%d%% complete', 'sustainable-catalyst-library' ), $score ) ); ?></strong>
            <span><?php echo esc_html( self::status_label( $status ) ); ?></span>
        </div>
        <?php if ( $invalidated ) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e( 'Metadata verification was cleared because citation-critical fields changed.', 'sustainable-catalyst-library' ); ?></p></div>
        <?php endif; ?>
        <?php if ( $issues ) : ?>
            <ul class="sc-source-reliability-issues">
                <?php foreach ( $issues as $issue ) : ?>
                    <li class="is-<?php echo esc_attr( $issue['severity'] ?? 'warning' ); ?>"><strong><?php echo esc_html( $issue['field'] ?? __( 'Source', 'sustainable-catalyst-library' ) ); ?>:</strong> <?php echo esc_html( $issue['message'] ?? '' ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?php esc_html_e( 'No citation-formatting issues detected.', 'sustainable-catalyst-library' ); ?></p>
        <?php endif; ?>
        <p><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_v251_recheck_source', 'source_id' => $post->ID ), admin_url( 'admin-post.php' ) ), 'sc_library_v251_recheck_source_' . $post->ID ) ); ?>"><?php esc_html_e( 'Recheck Source', 'sustainable-catalyst-library' ); ?></a></p>
        <?php
    }

    public function render_duplicate_box( $post ) {
        wp_nonce_field( 'sc_library_v251_duplicate_review_' . $post->ID, 'sc_library_v251_duplicate_nonce' );
        $matches = self::id_list( get_post_meta( $post->ID, SC_Library_Citation_Source_Manager::META_DUPLICATES, true ) );
        $decisions = get_post_meta( $post->ID, self::META_DUPLICATE_DECISIONS, true );
        $decisions = is_array( $decisions ) ? $decisions : array();
        $canonical_id = absint( get_post_meta( $post->ID, self::META_CANONICAL_ID, true ) );
        if ( ! $matches && ! $decisions ) {
            echo '<p>' . esc_html__( 'No possible duplicate records are currently associated with this source.', 'sustainable-catalyst-library' ) . '</p>';
            return;
        }
        ?>
        <table class="widefat striped sc-source-duplicate-table">
            <thead><tr><th><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Decision', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Canonical record', 'sustainable-catalyst-library' ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( array_values( array_unique( array_merge( $matches, array_map( 'absint', array_keys( $decisions ) ) ) ) ) as $other_id ) : ?>
                <?php if ( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== get_post_type( $other_id ) ) { continue; } ?>
                <tr>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $other_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $other_id ) ); ?></a><br><small><?php echo esc_html( SC_Library_Citation_Source_Manager::format_citation( $other_id, 'harvard', 'in-text' ) ); ?></small></td>
                    <td><select name="sc_v251_duplicate_decision[<?php echo esc_attr( $other_id ); ?>]">
                        <?php foreach ( self::duplicate_decision_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $decisions[ $other_id ] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                    </select></td>
                    <td><label><input type="radio" name="sc_v251_canonical_id" value="<?php echo esc_attr( $other_id ); ?>" <?php checked( $canonical_id, $other_id ); ?>> <?php esc_html_e( 'Use this record', 'sustainable-catalyst-library' ); ?></label></td>
                </tr>
            <?php endforeach; ?>
                <tr><td><strong><?php echo esc_html( get_the_title( $post->ID ) ); ?></strong></td><td><?php esc_html_e( 'Current record', 'sustainable-catalyst-library' ); ?></td><td><label><input type="radio" name="sc_v251_canonical_id" value="<?php echo esc_attr( $post->ID ); ?>" <?php checked( $canonical_id ?: $post->ID, $post->ID ); ?>> <?php esc_html_e( 'Use this record', 'sustainable-catalyst-library' ); ?></label></td></tr>
            </tbody>
        </table>
        <p class="description"><?php esc_html_e( 'Reconciliation records a reviewed relationship. It does not delete or silently merge source metadata.', 'sustainable-catalyst-library' ); ?></p>
        <?php
    }

    public function render_history_box( $post ) {
        $history = self::history( $post->ID );
        if ( ! $history ) {
            echo '<p>' . esc_html__( 'No structured metadata changes have been recorded yet.', 'sustainable-catalyst-library' ) . '</p>';
            return;
        }
        ?>
        <ol class="sc-source-history-list">
            <?php foreach ( array_reverse( $history, true ) as $index => $entry ) : ?>
                <li>
                    <strong><?php echo esc_html( $entry['changed_at'] ?? '' ); ?></strong>
                    <span><?php echo esc_html( implode( ', ', (array) ( $entry['changed_fields'] ?? array() ) ) ); ?></span>
                    <small><?php echo esc_html( sprintf( __( 'Context: %1$s · User: %2$d', 'sustainable-catalyst-library' ), $entry['context'] ?? 'unknown', absint( $entry['actor_id'] ?? 0 ) ) ); ?></small>
                    <?php if ( ! empty( $entry['before'] ) ) : ?>
                        <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_v251_restore_source_snapshot', 'source_id' => $post->ID, 'history_index' => $index ), admin_url( 'admin-post.php' ) ), 'sc_library_v251_restore_source_snapshot_' . $post->ID . '_' . $index ) ); ?>"><?php esc_html_e( 'Restore previous metadata', 'sustainable-catalyst-library' ); ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    public function save_reliability_controls( $post_id, $post, $update ) {
        if ( self::$restored || ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['sc_library_v251_duplicate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_library_v251_duplicate_nonce'] ) ), 'sc_library_v251_duplicate_review_' . $post_id ) ) {
            self::recalculate_reliability( $post_id );
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $decisions = array();
        foreach ( (array) wp_unslash( $_POST['sc_v251_duplicate_decision'] ?? array() ) as $other_id => $decision ) {
            $other_id = absint( $other_id );
            $decision = sanitize_key( $decision );
            if ( $other_id && SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $other_id ) && isset( self::duplicate_decision_options()[ $decision ] ) ) {
                $decisions[ $other_id ] = $decision;
            }
        }
        if ( $decisions ) {
            update_post_meta( $post_id, self::META_DUPLICATE_DECISIONS, $decisions );
        } else {
            delete_post_meta( $post_id, self::META_DUPLICATE_DECISIONS );
        }

        $canonical_id = absint( wp_unslash( $_POST['sc_v251_canonical_id'] ?? $post_id ) );
        if ( $canonical_id && ( $canonical_id === $post_id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $canonical_id ) ) ) {
            update_post_meta( $post_id, self::META_CANONICAL_ID, $canonical_id );
        }
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $post_id );
        foreach ( array_keys( $decisions ) as $other_id ) { SC_Library_Citation_Source_Manager::rebuild_source_indexes( absint( $other_id ) ); }
        self::recalculate_reliability( $post_id );
    }

    public function filter_duplicate_candidates( $ids, $source_id ) {
        $decisions = get_post_meta( $source_id, self::META_DUPLICATE_DECISIONS, true );
        $decisions = is_array( $decisions ) ? $decisions : array();
        return array_values(
            array_filter(
                self::id_list( $ids ),
                static function ( $other_id ) use ( $decisions, $source_id ) {
                    if ( 'not-duplicate' === ( $decisions[ $other_id ] ?? '' ) ) { return false; }
                    $other_decisions = get_post_meta( $other_id, self::META_DUPLICATE_DECISIONS, true );
                    return ! is_array( $other_decisions ) || 'not-duplicate' !== ( $other_decisions[ $source_id ] ?? '' );
                }
            )
        );
    }

    public static function recalculate_reliability( $source_id ) {
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, true );
        if ( ! $data ) {
            return array();
        }
        $issues = self::validation_issues( $data );
        $score = self::completeness_score( $data );
        $has_error = false;
        $has_warning = false;
        foreach ( $issues as $issue ) {
            $has_error = $has_error || 'error' === ( $issue['severity'] ?? '' );
            $has_warning = $has_warning || 'warning' === ( $issue['severity'] ?? '' );
        }
        $status = $has_error ? 'invalid' : ( $has_warning || $score < 80 ? 'needs-review' : 'ready' );
        update_post_meta( $source_id, self::META_VALIDATION, $issues );
        update_post_meta( $source_id, self::META_COMPLETENESS, $score );
        update_post_meta( $source_id, self::META_RELIABILITY_STATUS, $status );
        update_post_meta( $source_id, self::META_LAST_RELIABILITY_CHECK, current_time( 'mysql' ) );
        return array( 'status' => $status, 'score' => $score, 'issues' => $issues );
    }

    private static function validation_issues( $data ) {
        $issues = array();
        $add = static function ( $field, $severity, $message ) use ( &$issues ) {
            $issues[] = array( 'field' => $field, 'severity' => $severity, 'message' => $message );
        };

        if ( ! trim( (string) ( $data['title'] ?? '' ) ) ) {
            $add( 'title', 'error', __( 'A source title is required.', 'sustainable-catalyst-library' ) );
        }
        if ( empty( $data['authors'] ) && empty( $data['organization'] ) ) {
            $add( 'creator', 'warning', __( 'Add a personal or institutional author for a stable author-date citation.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['year'] ) && ! preg_match( '/^(1[0-9]{3}|20[0-9]{2}|21[0-9]{2})$/', (string) $data['year'] ) ) {
            $add( 'year', 'warning', __( 'Publication year should be a four-digit year; leave it empty for n.d.', 'sustainable-catalyst-library' ) );
        }
        if ( empty( $data['source_type'] ) ) {
            $add( 'source_type', 'error', __( 'Choose a source type so the formatter can apply the correct pattern.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['doi'] ) && ! self::valid_doi( $data['doi'] ) ) {
            $add( 'doi', 'error', __( 'The DOI does not match the expected DOI syntax.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['isbn'] ) && ! self::valid_isbn( $data['isbn'] ) ) {
            $add( 'isbn', 'error', __( 'The ISBN checksum is invalid.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['pmid'] ) && ! preg_match( '/^[0-9]{1,9}$/', (string) $data['pmid'] ) ) {
            $add( 'pmid', 'warning', __( 'PMID should contain digits only.', 'sustainable-catalyst-library' ) );
        }
        foreach ( array_merge( (array) ( $data['authors'] ?? array() ), (array) ( $data['editors'] ?? array() ) ) as $person ) {
            if ( ! empty( $person['orcid'] ) && ! self::valid_orcid( $person['orcid'] ) ) {
                $add( 'orcid', 'error', __( 'One or more ORCID identifiers failed checksum validation.', 'sustainable-catalyst-library' ) );
                break;
            }
        }
        if ( ! empty( $data['url'] ) && ! wp_http_validate_url( $data['url'] ) ) {
            $add( 'url', 'error', __( 'The canonical URL is invalid.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['archive_url'] ) && ! wp_http_validate_url( $data['archive_url'] ) ) {
            $add( 'archive_url', 'warning', __( 'The archive URL is invalid.', 'sustainable-catalyst-library' ) );
        }
        if ( 'journal-article' === ( $data['source_type'] ?? '' ) && empty( $data['container_title'] ) ) {
            $add( 'container_title', 'warning', __( 'Journal articles should name the journal or proceedings.', 'sustainable-catalyst-library' ) );
        }
        if ( in_array( $data['source_type'] ?? '', array( 'book', 'book-chapter', 'report', 'standard' ), true ) && empty( $data['publisher'] ) ) {
            $add( 'publisher', 'warning', __( 'This source type normally requires a publisher or issuing institution.', 'sustainable-catalyst-library' ) );
        }
        if ( 'book-chapter' === ( $data['source_type'] ?? '' ) && empty( $data['container_title'] ) ) {
            $add( 'container_title', 'error', __( 'A book chapter requires the containing book title.', 'sustainable-catalyst-library' ) );
        }
        if ( 'webpage' === ( $data['source_type'] ?? '' ) && ! empty( $data['url'] ) && empty( $data['access_date'] ) ) {
            $add( 'access_date', 'warning', __( 'Web sources should record an access date.', 'sustainable-catalyst-library' ) );
        }
        if ( ! empty( $data['pages'] ) && ! preg_match( '/^[A-Za-z]?[0-9]+(?:\s*[–—-]\s*[A-Za-z]?[0-9]+)?(?:\s*,\s*[A-Za-z]?[0-9]+(?:\s*[–—-]\s*[A-Za-z]?[0-9]+)?)*$/u', (string) $data['pages'] ) ) {
            $add( 'pages', 'warning', __( 'Review the page or page-range syntax.', 'sustainable-catalyst-library' ) );
        }
        return $issues;
    }

    private static function completeness_score( $data ) {
        $weights = array(
            'title' => 18, 'creator' => 16, 'year' => 10, 'source_type' => 12, 'publication' => 12,
            'identifier' => 10, 'access' => 6, 'abstract' => 5, 'language' => 3, 'provenance' => 4, 'verified' => 4,
        );
        $score = 0;
        $score += ! empty( $data['title'] ) ? $weights['title'] : 0;
        $score += ! empty( $data['authors'] ) || ! empty( $data['organization'] ) ? $weights['creator'] : 0;
        $score += ! empty( $data['year'] ) || ! empty( $data['publication_date'] ) ? $weights['year'] : 0;
        $score += ! empty( $data['source_type'] ) ? $weights['source_type'] : 0;
        $score += ! empty( $data['container_title'] ) || ! empty( $data['publisher'] ) ? $weights['publication'] : 0;
        $score += ! empty( $data['doi'] ) || ! empty( $data['isbn'] ) || ! empty( $data['pmid'] ) ? $weights['identifier'] : 0;
        $score += ! empty( $data['url'] ) || ! empty( $data['attachment_id'] ) ? $weights['access'] : 0;
        $score += ! empty( $data['abstract'] ) ? $weights['abstract'] : 0;
        $score += ! empty( $data['language'] ) ? $weights['language'] : 0;
        $score += ! empty( $data['metadata_provenance'] ) ? $weights['provenance'] : 0;
        $score += ! empty( $data['metadata_verified'] ) ? $weights['verified'] : 0;
        return min( 100, $score );
    }

    public static function valid_doi( $doi ) {
        $doi = trim( preg_replace( '/[\s\x{00A0}]+/u', '', (string) $doi ) );
        return (bool) preg_match( '/^10\.\d{4,9}\/[-._;()\/:A-Z0-9]+$/i', $doi );
    }

    public static function valid_isbn( $isbn ) {
        $isbn = strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $isbn ) );
        if ( 10 === strlen( $isbn ) ) {
            $sum = 0;
            for ( $i = 0; $i < 10; $i++ ) {
                $digit = 'X' === $isbn[ $i ] ? 10 : intval( $isbn[ $i ] );
                if ( 10 === $digit && 9 !== $i ) { return false; }
                $sum += ( 10 - $i ) * $digit;
            }
            return 0 === $sum % 11;
        }
        if ( 13 === strlen( $isbn ) && ctype_digit( $isbn ) ) {
            $sum = 0;
            for ( $i = 0; $i < 12; $i++ ) { $sum += intval( $isbn[ $i ] ) * ( 0 === $i % 2 ? 1 : 3 ); }
            return intval( $isbn[12] ) === ( 10 - ( $sum % 10 ) ) % 10;
        }
        return false;
    }

    public static function valid_orcid( $orcid ) {
        $digits = strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $orcid ) );
        if ( 16 !== strlen( $digits ) || ! preg_match( '/^[0-9]{15}[0-9X]$/', $digits ) ) { return false; }
        $total = 0;
        for ( $i = 0; $i < 15; $i++ ) { $total = ( $total + intval( $digits[ $i ] ) ) * 2; }
        $remainder = $total % 11;
        $result = ( 12 - $remainder ) % 11;
        $check = 10 === $result ? 'X' : (string) $result;
        return $check === $digits[15];
    }

    public static function canonical_url( $url ) {
        $url = esc_url_raw( trim( (string) $url ) );
        if ( ! $url ) { return ''; }
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) { return $url; }
        $scheme = strtolower( $parts['scheme'] ?? 'https' );
        $host = strtolower( preg_replace( '/^www\./i', '', $parts['host'] ) );
        $port = isset( $parts['port'] ) && ! in_array( intval( $parts['port'] ), array( 80, 443 ), true ) ? ':' . intval( $parts['port'] ) : '';
        $path = isset( $parts['path'] ) ? preg_replace( '#/+#', '/', $parts['path'] ) : '';
        $path = '/' === $path ? '' : untrailingslashit( $path );
        $query = array();
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
            foreach ( array_keys( $query ) as $key ) {
                if ( preg_match( '/^(utm_|fbclid$|gclid$|mc_cid$|mc_eid$|ref$|source$)/i', $key ) ) { unset( $query[ $key ] ); }
            }
            ksort( $query );
        }
        return $scheme . '://' . $host . $port . $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
    }

    public function source_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $updated['sc_source_reliability'] = __( 'Reliability', 'sustainable-catalyst-library' );
            }
            $updated[ $key ] = $label;
        }
        return $updated;
    }

    public function source_column_content( $column, $source_id ) {
        if ( 'sc_source_reliability' !== $column ) { return; }
        $status = sanitize_key( get_post_meta( $source_id, self::META_RELIABILITY_STATUS, true ) ?: 'unchecked' );
        $score = absint( get_post_meta( $source_id, self::META_COMPLETENESS, true ) );
        echo '<strong class="sc-source-reliability-state is-' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</strong>';
        echo '<span class="sc-source-review-detail">' . esc_html( sprintf( __( '%d%% complete', 'sustainable-catalyst-library' ), $score ) ) . '</span>';
    }

    public function register_rest_routes() {
        register_rest_route(
            SC_Library_Citation_Source_Manager::API_NAMESPACE,
            '/sources/(?P<id>\d+)/reliability',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_reliability' ),
                'permission_callback' => static function ( WP_REST_Request $request ) {
                    $id = absint( $request['id'] );
                    return 'publish' === get_post_status( $id ) || current_user_can( 'edit_post', $id );
                },
            )
        );
        register_rest_route(
            SC_Library_Citation_Source_Manager::API_NAMESPACE,
            '/sources/(?P<id>\d+)/history',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_history' ),
                'permission_callback' => static function ( WP_REST_Request $request ) {
                    return current_user_can( 'edit_post', absint( $request['id'] ) );
                },
            )
        );
        register_rest_route(
            SC_Library_Citation_Source_Manager::API_NAMESPACE,
            '/sources/(?P<id>\d+)/duplicates',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_duplicates' ),
                    'permission_callback' => static function ( WP_REST_Request $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); },
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_update_duplicates' ),
                    'permission_callback' => static function ( WP_REST_Request $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); },
                ),
            )
        );
    }

    public function rest_reliability( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        if ( current_user_can( 'edit_post', $id ) ) {
            $result = self::recalculate_reliability( $id );
        } else {
            $result = array(
                'status' => sanitize_key( get_post_meta( $id, self::META_RELIABILITY_STATUS, true ) ?: 'unchecked' ),
                'score'  => absint( get_post_meta( $id, self::META_COMPLETENESS, true ) ),
            );
        }
        return rest_ensure_response( array_merge( array( 'schema' => self::RELIABILITY_SCHEMA, 'source_id' => $id ), $result ) );
    }

    public function rest_history( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'schema' => self::HISTORY_SCHEMA, 'source_id' => absint( $request['id'] ), 'history' => self::history( absint( $request['id'] ) ) ) );
    }

    public function rest_duplicates( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return rest_ensure_response( self::duplicate_payload( $id ) );
    }

    public function rest_update_duplicates( WP_REST_Request $request ) {
        $rate = self::enforce_write_rate_limit( $request, 'duplicates' );
        if ( is_wp_error( $rate ) ) { return $rate; }
        $id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $decisions = array();
        foreach ( (array) ( $payload['decisions'] ?? array() ) as $other_id => $decision ) {
            $other_id = absint( $other_id );
            $decision = sanitize_key( $decision );
            if ( $other_id && isset( self::duplicate_decision_options()[ $decision ] ) ) { $decisions[ $other_id ] = $decision; }
        }
        update_post_meta( $id, self::META_DUPLICATE_DECISIONS, $decisions );
        $canonical_id = absint( $payload['canonical_id'] ?? $id );
        if ( $canonical_id === $id || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE === get_post_type( $canonical_id ) ) { update_post_meta( $id, self::META_CANONICAL_ID, $canonical_id ); }
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $id );
        foreach ( array_keys( $decisions ) as $other_id ) { SC_Library_Citation_Source_Manager::rebuild_source_indexes( absint( $other_id ) ); }
        self::recalculate_reliability( $id );
        return rest_ensure_response( self::duplicate_payload( $id ) );
    }

    public function rest_response_headers( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response || 0 !== strpos( $request->get_route(), '/' . SC_Library_Citation_Source_Manager::API_NAMESPACE . '/' ) ) { return $response; }
        $data = $response->get_data();
        $etag = '"' . md5( wp_json_encode( $data ) ) . '"';
        $response->header( 'ETag', $etag );
        $response->header( 'Cache-Control', 'private, max-age=0, must-revalidate' );
        if ( preg_match( '#/sources/(\d+)#', $request->get_route(), $matches ) ) {
            $modified = get_post_modified_time( 'D, d M Y H:i:s', true, absint( $matches[1] ) );
            if ( $modified ) { $response->header( 'Last-Modified', $modified . ' GMT' ); }
        }
        return $response;
    }

    public function admin_restore_snapshot() {
        $source_id = absint( wp_unslash( $_GET['source_id'] ?? 0 ) );
        $index = intval( wp_unslash( $_GET['history_index'] ?? -1 ) );
        if ( ! $source_id || $index < 0 || ! current_user_can( 'edit_post', $source_id ) ) { wp_die( esc_html__( 'You are not allowed to restore this source snapshot.', 'sustainable-catalyst-library' ) ); }
        check_admin_referer( 'sc_library_v251_restore_source_snapshot_' . $source_id . '_' . $index );
        $history = self::history( $source_id );
        if ( ! isset( $history[ $index ]['before'] ) || ! is_array( $history[ $index ]['before'] ) ) { $this->redirect_source( $source_id, 'snapshot_missing' ); }
        $result = self::restore_snapshot( $source_id, $history[ $index ]['before'] );
        $this->redirect_source( $source_id, is_wp_error( $result ) ? $result->get_error_code() : 'snapshot_restored' );
    }

    public function admin_recheck_source() {
        $source_id = absint( wp_unslash( $_GET['source_id'] ?? 0 ) );
        if ( ! $source_id || ! current_user_can( 'edit_post', $source_id ) ) { wp_die( esc_html__( 'You are not allowed to recheck this source.', 'sustainable-catalyst-library' ) ); }
        check_admin_referer( 'sc_library_v251_recheck_source_' . $source_id );
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
        self::recalculate_reliability( $source_id );
        $this->redirect_source( $source_id, 'source_rechecked' );
    }

    public function admin_notices() {
        $notice = sanitize_key( wp_unslash( $_GET['sc_v251_notice'] ?? '' ) );
        $messages = array(
            'snapshot_restored' => array( 'success', __( 'The selected source metadata snapshot was restored.', 'sustainable-catalyst-library' ) ),
            'source_rechecked'   => array( 'success', __( 'Citation metadata, normalized identifiers, duplicate candidates, and reliability status were rebuilt.', 'sustainable-catalyst-library' ) ),
            'snapshot_missing'   => array( 'error', __( 'The requested source snapshot is no longer available.', 'sustainable-catalyst-library' ) ),
        );
        if ( isset( $messages[ $notice ] ) ) { echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>'; }
    }

    private static function snapshot_source( $source_id ) {
        $post = get_post( $source_id );
        if ( ! $post || SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE !== $post->post_type ) { return array(); }
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, true );
        unset(
            $data['citation'], $data['citation_html'], $data['in_text_citation'], $data['_links'], $data['modified_gmt'], $data['permalink'],
            $data['year_suffix'], $data['citation_key'], $data['citation_reliability'], $data['completeness_score'],
            $data['validation_issues'], $data['duplicate_matches']
        );
        $data['post_status'] = $post->post_status;
        $data['topics'] = wp_get_object_terms( $source_id, SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY, array( 'fields' => 'slugs' ) );
        return $data;
    }

    private static function restore_snapshot( $source_id, $snapshot ) {
        self::begin_source_update( $source_id, 'restore' );
        self::$restored = true;
        $updated = wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_title' => $snapshot['title'] ?? '', 'post_excerpt' => $snapshot['abstract'] ?? '', 'post_content' => $snapshot['description'] ?? '', 'post_status' => $snapshot['post_status'] ?? 'draft' ) ), true );
        if ( is_wp_error( $updated ) ) { self::$restored = false; return $updated; }
        SC_Library_Citation_Source_Manager::restore_source_snapshot_data( $source_id, $snapshot );
        SC_Library_Citation_Source_Manager::rebuild_source_indexes( $source_id );
        self::$restored = false;
        self::finalize_source_update( $source_id, false, 'restore' );
        return true;
    }

    private static function metadata_hash( $snapshot ) { return hash( 'sha256', wp_json_encode( $snapshot ) ); }

    private static function changed_fields( $before, $after ) {
        if ( ! is_array( $before ) || ! $before ) { return array(); }
        $fields = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );
        $changed = array();
        foreach ( $fields as $field ) { if ( wp_json_encode( $before[ $field ] ?? null ) !== wp_json_encode( $after[ $field ] ?? null ) ) { $changed[] = $field; } }
        return $changed;
    }

    private static function append_history( $source_id, $entry ) {
        $history = self::history( $source_id );
        $history[] = $entry;
        if ( count( $history ) > self::HISTORY_LIMIT ) { $history = array_slice( $history, -self::HISTORY_LIMIT ); }
        update_post_meta( $source_id, self::META_HISTORY, $history );
    }

    private static function history( $source_id ) {
        $history = get_post_meta( $source_id, self::META_HISTORY, true );
        return is_array( $history ) ? array_values( $history ) : array();
    }

    private static function duplicate_payload( $source_id ) {
        $matches = self::id_list( get_post_meta( $source_id, SC_Library_Citation_Source_Manager::META_DUPLICATES, true ) );
        $records = array();
        foreach ( $matches as $id ) { $records[] = array( 'id' => $id, 'title' => get_the_title( $id ), 'citation' => SC_Library_Citation_Source_Manager::format_citation( $id, 'harvard', 'reference' ) ); }
        return array( 'source_id' => $source_id, 'canonical_id' => absint( get_post_meta( $source_id, self::META_CANONICAL_ID, true ) ) ?: $source_id, 'decisions' => get_post_meta( $source_id, self::META_DUPLICATE_DECISIONS, true ) ?: array(), 'matches' => $records );
    }

    private static function duplicate_decision_options() {
        return array( '' => __( 'Unreviewed', 'sustainable-catalyst-library' ), 'same-work' => __( 'Same work', 'sustainable-catalyst-library' ), 'alternate-edition' => __( 'Alternate edition or version', 'sustainable-catalyst-library' ), 'related-work' => __( 'Related but distinct work', 'sustainable-catalyst-library' ), 'not-duplicate' => __( 'Not a duplicate', 'sustainable-catalyst-library' ) );
    }

    private static function status_label( $status ) {
        $labels = array( 'ready' => __( 'Citation ready', 'sustainable-catalyst-library' ), 'needs-review' => __( 'Needs review', 'sustainable-catalyst-library' ), 'invalid' => __( 'Invalid metadata', 'sustainable-catalyst-library' ), 'unchecked' => __( 'Not checked', 'sustainable-catalyst-library' ) );
        return $labels[ $status ] ?? ucfirst( str_replace( '-', ' ', $status ) );
    }

    private static function id_list( $value ) { $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) ); sort( $ids, SORT_NUMERIC ); return $ids; }

    private function redirect_source( $source_id, $notice ) {
        wp_safe_redirect( add_query_arg( array( 'post' => $source_id, 'action' => 'edit', 'sc_v251_notice' => sanitize_key( $notice ) ), admin_url( 'post.php' ) ) );
        exit;
    }
}
