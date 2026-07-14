<?php
/**
 * PDF Conversion and Publishing Reliability.
 *
 * Adds resumable browser conversion, duplicate protection, persistent logs,
 * migration auditing, large-document safeguards, and stricter publishing
 * validation without replacing the v2.2.0 document architecture.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_PDF_Conversion_Reliability {
    public const POST_TYPE = 'sc_foundation_doc';
    public const TAX_FAMILY = 'sc_document_family';
    public const VERSION = '2.2.1';
    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const META_STATUS = '_sc_document_extraction_status';
    public const META_METHOD = '_sc_document_extraction_method';
    public const META_RAW_TEXT = '_sc_document_raw_text';
    public const META_PAGE_MAP = '_sc_document_page_map';
    public const META_PAGE_COUNT = '_sc_document_page_count';
    public const META_CHECKSUM = '_sc_document_checksum';
    public const META_REVIEWED = '_sc_document_reviewed';
    public const META_SESSION = '_sc_document_conversion_session';
    public const META_SESSION_ATTACHMENT = '_sc_document_conversion_attachment';
    public const META_SESSION_CHECKSUM = '_sc_document_conversion_checksum';
    public const META_LAST_PAGE = '_sc_document_conversion_last_page';
    public const META_TOTAL_PAGES = '_sc_document_conversion_total_pages';
    public const META_STARTED_AT = '_sc_document_conversion_started_at';
    public const META_UPDATED_AT = '_sc_document_conversion_updated_at';
    public const META_LOG = '_sc_document_conversion_log';
    public const META_MIGRATION_AUDIT = '_sc_document_v221_migration_audit';
    public const MIN_TEXT = 80;
    public const LOG_LIMIT = 50;

    private static $validation_error = '';
    private static $validation_detail = '';

    public function __construct() {
        add_action( 'init', array( $this, 'run_migration_audit' ), 180 );
        add_filter( 'wp_insert_post_data', array( $this, 'validate_publication' ), 120, 2 );
        add_filter( 'redirect_post_location', array( $this, 'redirect_with_validation' ), 120, 2 );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'record_save_state' ), 120, 3 );
        add_action( 'admin_notices', array( $this, 'validation_notices' ), 120 );
        add_action( 'edit_form_after_title', array( $this, 'render_reliability_panel' ), 45 );
        add_action( 'admin_enqueue_scripts', array( $this, 'localize_reliability_client' ), 120 );
        add_action( 'admin_footer', array( $this, 'append_health_logs' ), 120 );

        add_action( 'wp_ajax_sc_library_v221_prepare_pdf_document', array( $this, 'ajax_prepare' ) );
        add_action( 'wp_ajax_sc_library_v221_store_pdf_chunk', array( $this, 'ajax_store_chunk' ) );
        add_action( 'wp_ajax_sc_library_v221_finalize_pdf_document', array( $this, 'ajax_finalize' ) );
        add_action( 'wp_ajax_sc_library_v221_conversion_status', array( $this, 'ajax_status' ) );
        add_action( 'wp_ajax_sc_library_v221_cancel_conversion', array( $this, 'ajax_cancel' ) );
        add_action( 'wp_ajax_sc_library_v221_mark_failure', array( $this, 'ajax_mark_failure' ) );

        add_filter( 'media_row_actions', array( $this, 'remove_duplicate_media_action' ), 120, 2 );
        add_action( 'admin_post_sc_library_v221_retry_conversion', array( $this, 'retry_conversion' ) );
    }

    public function validate_publication( $data, $postarr ) {
        if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) ) {
            return $data;
        }

        $post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
        $attachment_id = isset( $_POST['sc_library_foundation_page_pdf_id'] )
            ? absint( wp_unslash( $_POST['sc_library_foundation_page_pdf_id'] ) )
            : absint( get_post_meta( $post_id, self::META_PDF_ID, true ) );

        if ( ! $this->valid_pdf( $attachment_id ) ) {
            return $this->force_draft( $data, 'missing_pdf', __( 'Select a valid PDF before publishing.', 'sustainable-catalyst-library' ) );
        }

        $checksum = $this->attachment_checksum( $attachment_id );
        $duplicate_id = $this->find_duplicate_document( $post_id, $attachment_id, $checksum );
        if ( $duplicate_id ) {
            return $this->force_draft(
                $data,
                'duplicate_pdf',
                sprintf( __( 'This PDF already belongs to “%s”.', 'sustainable-catalyst-library' ), get_the_title( $duplicate_id ) )
            );
        }

        $content = trim( wp_strip_all_tags( (string) ( $data['post_content'] ?? '' ) ) );
        if ( '' === $content ) {
            return $this->force_draft( $data, 'document_required', __( 'Create or enter the readable document before publishing.', 'sustainable-catalyst-library' ) );
        }

        $status = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
        $allowed_statuses = array( 'ready_review', 'reviewed', 'published', 'legacy_content' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return $this->force_draft(
                $data,
                'conversion_incomplete',
                __( 'The PDF conversion must be completed before publishing.', 'sustainable-catalyst-library' )
            );
        }

        $reviewed = isset( $_POST['sc_pdf_document_reviewed'] )
            ? 1
            : absint( get_post_meta( $post_id, self::META_REVIEWED, true ) );
        if ( ! $reviewed ) {
            return $this->force_draft(
                $data,
                'review_required',
                __( 'Confirm that the generated document was reviewed against the original PDF.', 'sustainable-catalyst-library' )
            );
        }

        return $data;
    }

    public function redirect_with_validation( $location, $post_id ) {
        if ( self::$validation_error ) {
            $location = add_query_arg(
                array(
                    'sc_pdf_v221_error'  => self::$validation_error,
                    'sc_pdf_v221_detail' => rawurlencode( self::$validation_detail ),
                ),
                $location
            );
            self::$validation_error = '';
            self::$validation_detail = '';
        }
        return $location;
    }

    public function validation_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }
        $code = isset( $_GET['sc_pdf_v221_error'] ) ? sanitize_key( wp_unslash( $_GET['sc_pdf_v221_error'] ) ) : '';
        $detail = isset( $_GET['sc_pdf_v221_detail'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['sc_pdf_v221_detail'] ) ) ) : '';
        if ( ! $code ) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'PDF Document not published.', 'sustainable-catalyst-library' ) . '</strong> ' . esc_html( $detail ?: __( 'Resolve the document validation issue and try again.', 'sustainable-catalyst-library' ) ) . '</p></div>';
    }

    public function record_save_state( $post_id, $post, $update ) {
        if ( ! $post || self::POST_TYPE !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $attachment_id = absint( get_post_meta( $post_id, self::META_PDF_ID, true ) );
        if ( $attachment_id && $this->valid_pdf( $attachment_id ) ) {
            $checksum = $this->attachment_checksum( $attachment_id );
            if ( $checksum ) {
                update_post_meta( $post_id, self::META_CHECKSUM, $checksum );
            }
        }
        if ( 'publish' === $post->post_status ) {
            update_post_meta( $post_id, self::META_STATUS, 'published' );
            $this->log( $post_id, 'published', __( 'Readable document and original PDF published.', 'sustainable-catalyst-library' ) );
        }
    }

    public function render_reliability_panel( $post ) {
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
            return;
        }
        $session = $this->session_state( $post->ID );
        $logs = $this->logs( $post->ID );
        ?>
        <div class="sc-pdf-reliability" data-sc-pdf-reliability>
            <div class="sc-pdf-reliability__summary">
                <div>
                    <p class="sc-pdf-reliability__label"><?php esc_html_e( 'Conversion reliability', 'sustainable-catalyst-library' ); ?></p>
                    <strong data-sc-reliability-state><?php echo esc_html( $session['active'] ? __( 'Interrupted conversion can be resumed', 'sustainable-catalyst-library' ) : __( 'Ready', 'sustainable-catalyst-library' ) ); ?></strong>
                    <span data-sc-reliability-detail><?php echo esc_html( $session['active'] ? sprintf( __( 'Saved through page %1$d of %2$d.', 'sustainable-catalyst-library' ), $session['last_page'], $session['total_pages'] ) : __( 'Conversions are saved in small page batches and recorded in the log.', 'sustainable-catalyst-library' ) ); ?></span>
                </div>
                <button type="button" class="button" data-sc-cancel-conversion <?php disabled( ! $session['active'] ); ?>><?php esc_html_e( 'Cancel Saved Conversion', 'sustainable-catalyst-library' ); ?></button>
            </div>
            <details class="sc-pdf-reliability__log">
                <summary><?php esc_html_e( 'Conversion log', 'sustainable-catalyst-library' ); ?></summary>
                <ol data-sc-conversion-log>
                    <?php if ( $logs ) : foreach ( array_slice( array_reverse( $logs ), 0, 10 ) as $entry ) : ?>
                        <li><time><?php echo esc_html( $entry['time'] ?? '' ); ?></time><strong><?php echo esc_html( $entry['event'] ?? '' ); ?></strong><span><?php echo esc_html( $entry['message'] ?? '' ); ?></span></li>
                    <?php endforeach; else : ?>
                        <li><?php esc_html_e( 'No conversion events recorded yet.', 'sustainable-catalyst-library' ); ?></li>
                    <?php endif; ?>
                </ol>
            </details>
        </div>
        <?php
    }

    public function localize_reliability_client() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
            return;
        }
        $post_id = get_the_ID();
        wp_localize_script(
            'sc-library-foundation-pages-admin',
            'SCLibraryPdfReliability',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'sc_library_pdf_v221' ),
                'postId'           => $post_id,
                'maxPages'         => absint( apply_filters( 'sc_library_pdf_conversion_max_pages', 5000, $post_id ) ),
                'chunkPages'       => absint( apply_filters( 'sc_library_pdf_conversion_chunk_pages', 5, $post_id ) ),
                'chunkCharacters'  => absint( apply_filters( 'sc_library_pdf_conversion_chunk_characters', 240000, $post_id ) ),
                'requestRetries'   => absint( apply_filters( 'sc_library_pdf_conversion_request_retries', 3, $post_id ) ),
                'retryDelay'       => absint( apply_filters( 'sc_library_pdf_conversion_retry_delay_ms', 900, $post_id ) ),
                'strings'          => array(
                    'resume'        => __( 'Resume Document Conversion', 'sustainable-catalyst-library' ),
                    'restart'       => __( 'Re-create Document from PDF', 'sustainable-catalyst-library' ),
                    'cancelled'     => __( 'Saved conversion cancelled.', 'sustainable-catalyst-library' ),
                    'retrying'      => __( 'Connection interrupted. Retrying…', 'sustainable-catalyst-library' ),
                    'workerFallback'=> __( 'PDF worker unavailable. Continuing in compatibility mode…', 'sustainable-catalyst-library' ),
                    'tooManyPages'  => __( 'This PDF exceeds the configured page limit.', 'sustainable-catalyst-library' ),
                    'duplicate'     => __( 'This PDF already belongs to another document record.', 'sustainable-catalyst-library' ),
                    'complete'      => __( 'Conversion completed and saved. Review the document below.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function ajax_prepare() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $this->valid_pdf( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Select a valid PDF first.', 'sustainable-catalyst-library' ) ), 400 );
        }

        $file = get_attached_file( $attachment_id );
        $max_bytes = absint( apply_filters( 'sc_library_pdf_conversion_max_bytes', 262144000, $post_id, $attachment_id ) );
        if ( ! $file || ! is_file( $file ) ) {
            wp_send_json_error( array( 'message' => __( 'The PDF file could not be found on the server.', 'sustainable-catalyst-library' ) ), 404 );
        }
        if ( $max_bytes && filesize( $file ) > $max_bytes ) {
            $this->log( $post_id, 'blocked', __( 'PDF exceeds the configured file-size limit.', 'sustainable-catalyst-library' ) );
            wp_send_json_error( array( 'message' => __( 'This PDF is larger than the configured conversion limit.', 'sustainable-catalyst-library' ) ), 413 );
        }

        $checksum = hash_file( 'sha256', $file );
        $duplicate_id = $this->find_duplicate_document( $post_id, $attachment_id, $checksum );
        if ( $duplicate_id ) {
            $this->log( $post_id, 'duplicate', sprintf( __( 'Duplicate of document %d detected.', 'sustainable-catalyst-library' ), $duplicate_id ) );
            wp_send_json_error(
                array(
                    'code'      => 'duplicate_pdf',
                    'message'   => sprintf( __( 'This PDF is already attached to “%s”.', 'sustainable-catalyst-library' ), get_the_title( $duplicate_id ) ),
                    'editUrl'   => get_edit_post_link( $duplicate_id, 'raw' ),
                    'documentId'=> $duplicate_id,
                ),
                409
            );
        }

        update_post_meta( $post_id, self::META_PDF_ID, $attachment_id );
        update_post_meta( $post_id, self::META_CHECKSUM, $checksum );

        $state = $this->session_state( $post_id );
        $resume = $state['active'] && $state['attachment_id'] === $attachment_id && hash_equals( (string) $state['checksum'], (string) $checksum );
        if ( ! $resume ) {
            $this->clear_session( $post_id, false );
            $session_id = wp_generate_uuid4();
            update_post_meta( $post_id, self::META_SESSION, $session_id );
            update_post_meta( $post_id, self::META_SESSION_ATTACHMENT, $attachment_id );
            update_post_meta( $post_id, self::META_SESSION_CHECKSUM, $checksum );
            update_post_meta( $post_id, self::META_LAST_PAGE, 0 );
            update_post_meta( $post_id, self::META_TOTAL_PAGES, 0 );
            update_post_meta( $post_id, self::META_STARTED_AT, current_time( 'mysql' ) );
            update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
            update_post_meta( $post_id, self::META_STATUS, 'extracting' );
            $this->log( $post_id, 'started', __( 'Conversion session started.', 'sustainable-catalyst-library' ) );
            $state = $this->session_state( $post_id );
        } else {
            $this->log( $post_id, 'resumed', sprintf( __( 'Conversion resumed after page %d.', 'sustainable-catalyst-library' ), $state['last_page'] ) );
        }

        $server_limit = absint( apply_filters( 'sc_library_pdf_server_extraction_max_bytes', 52428800, $post_id, $attachment_id ) );
        if ( ! $resume && ( ! $server_limit || filesize( $file ) <= $server_limit ) ) {
            $server = $this->server_extract( $file, $post_id );
            if ( is_array( $server ) && ! empty( $server['pages'] ) ) {
                $stored = $this->store_pages( $post_id, $attachment_id, $server['pages'], $server['method'] );
                wp_send_json_success( $stored );
            }
        }

        wp_send_json_success(
            array(
                'status'       => 'browser_required',
                'pdfUrl'       => wp_get_attachment_url( $attachment_id ),
                'attachmentId' => $attachment_id,
                'sessionId'    => $state['session_id'],
                'resumePage'   => max( 1, $state['last_page'] + 1 ),
                'lastPage'     => $state['last_page'],
                'totalPages'   => $state['total_pages'],
                'resuming'     => $resume,
            )
        );
    }

    public function ajax_store_chunk() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $total_pages = absint( wp_unslash( $_POST['total_pages'] ?? 0 ) );
        $pages = json_decode( wp_unslash( $_POST['pages'] ?? '[]' ), true );
        $state = $this->session_state( $post_id );

        if ( ! $this->valid_pdf( $attachment_id ) || ! $session_id || ! hash_equals( (string) $state['session_id'], $session_id ) || $state['attachment_id'] !== $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'The saved conversion session is no longer valid. Start the conversion again.', 'sustainable-catalyst-library' ) ), 409 );
        }
        if ( ! is_array( $pages ) || ! $pages ) {
            wp_send_json_error( array( 'message' => __( 'No page data was received.', 'sustainable-catalyst-library' ) ), 400 );
        }

        $path = $this->buffer_path( $post_id, $session_id );
        $handle = fopen( $path, 'ab' );
        if ( ! $handle ) {
            wp_send_json_error( array( 'message' => __( 'WordPress could not write the conversion buffer.', 'sustainable-catalyst-library' ) ), 500 );
        }
        $highest = $state['last_page'];
        foreach ( $pages as $page ) {
            $number = absint( $page['page'] ?? 0 );
            if ( $number < 1 ) {
                continue;
            }
            $record = array(
                'page'  => $number,
                'text'  => self::normalize_text( $page['text'] ?? '' ),
                'lines' => $this->sanitize_lines( $page['lines'] ?? array() ),
            );
            fwrite( $handle, wp_json_encode( $record, JSON_UNESCAPED_UNICODE ) . "\n" );
            $highest = max( $highest, $number );
        }
        fclose( $handle );

        update_post_meta( $post_id, self::META_LAST_PAGE, $highest );
        update_post_meta( $post_id, self::META_TOTAL_PAGES, max( $total_pages, $state['total_pages'] ) );
        update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        update_post_meta( $post_id, self::META_STATUS, 'extracting' );
        $this->log( $post_id, 'chunk', sprintf( __( 'Saved through page %1$d of %2$d.', 'sustainable-catalyst-library' ), $highest, max( $total_pages, $state['total_pages'] ) ) );

        wp_send_json_success( array( 'lastPage' => $highest, 'totalPages' => max( $total_pages, $state['total_pages'] ) ) );
    }

    public function ajax_finalize() {
        $post_id = $this->verify_ajax();
        $attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $state = $this->session_state( $post_id );
        if ( ! $session_id || ! hash_equals( (string) $state['session_id'], $session_id ) || $state['attachment_id'] !== $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'The conversion session could not be finalized.', 'sustainable-catalyst-library' ) ), 409 );
        }

        $pages = $this->read_buffer_pages( $post_id, $session_id );
        if ( ! $pages ) {
            wp_send_json_error( array( 'message' => __( 'No converted pages were found.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $total_pages = max( $state['total_pages'], count( $pages ) );
        $missing = array();
        for ( $page = 1; $page <= $total_pages; $page++ ) {
            if ( ! isset( $pages[ $page ] ) ) {
                $missing[] = $page;
                if ( count( $missing ) >= 20 ) {
                    break;
                }
            }
        }
        if ( $missing ) {
            $next = min( $missing );
            $this->log( $post_id, 'incomplete', sprintf( __( 'Finalization paused because page %d is missing.', 'sustainable-catalyst-library' ), $next ) );
            wp_send_json_error(
                array(
                    'code'       => 'incomplete_conversion',
                    'message'    => sprintf( __( 'Page %d was not saved. Resume the conversion from that page.', 'sustainable-catalyst-library' ), $next ),
                    'resumePage' => $next,
                ),
                409
            );
        }

        $result = $this->store_pages( $post_id, $attachment_id, array_values( $pages ), 'pdfjs-resumable-2.2.1' );
        if ( 'ready_review' === ( $result['status'] ?? '' ) ) {
            $this->clear_session( $post_id, false );
            $this->log( $post_id, 'completed', sprintf( __( 'Conversion completed with %d pages.', 'sustainable-catalyst-library' ), count( $pages ) ) );
        }
        wp_send_json_success( $result );
    }

    public function ajax_status() {
        $post_id = $this->verify_ajax();
        $state = $this->session_state( $post_id );
        $state['status'] = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
        $state['logs'] = array_slice( array_reverse( $this->logs( $post_id ) ), 0, 10 );
        wp_send_json_success( $state );
    }

    public function ajax_cancel() {
        $post_id = $this->verify_ajax();
        $this->clear_session( $post_id, true );
        update_post_meta( $post_id, self::META_STATUS, 'pending' );
        $this->log( $post_id, 'cancelled', __( 'Saved conversion session cancelled by the editor.', 'sustainable-catalyst-library' ) );
        wp_send_json_success( array( 'status' => 'pending', 'message' => __( 'Saved conversion cancelled.', 'sustainable-catalyst-library' ) ) );
    }

    public function ajax_mark_failure() {
        $post_id = $this->verify_ajax();
        $code = sanitize_key( wp_unslash( $_POST['failure_code'] ?? 'failed' ) );
        if ( ! in_array( $code, array( 'needs_ocr', 'password_protected', 'failed', 'too_large' ), true ) ) {
            $code = 'failed';
        }
        update_post_meta( $post_id, self::META_STATUS, $code );
        update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        $this->log( $post_id, $code, sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) ) ?: $code );
        wp_send_json_success( array( 'status' => $code ) );
    }

    public function run_migration_audit() {
        if ( self::VERSION === get_option( 'sc_library_pdf_reliability_migration_version' ) ) {
            return;
        }
        $summary = array( 'records' => 0, 'families' => 0, 'statuses' => 0, 'checksums' => 0, 'duplicates' => 0, 'time' => current_time( 'mysql' ) );
        $seen_checksums = array();
        $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        foreach ( $ids as $post_id ) {
            $summary['records']++;
            $terms = wp_get_object_terms( $post_id, self::TAX_FAMILY, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $terms ) || ! $terms ) {
                $this->assign_default_family( $post_id );
                $summary['families']++;
            }
            $status = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
            if ( ! $status ) {
                update_post_meta( $post_id, self::META_STATUS, trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) ? 'legacy_content' : 'pending' );
                $summary['statuses']++;
            }
            $attachment_id = absint( get_post_meta( $post_id, self::META_PDF_ID, true ) );
            if ( $this->valid_pdf( $attachment_id ) ) {
                $checksum = $this->attachment_checksum( $attachment_id );
                if ( $checksum && get_post_meta( $post_id, self::META_CHECKSUM, true ) !== $checksum ) {
                    update_post_meta( $post_id, self::META_CHECKSUM, $checksum );
                    $summary['checksums']++;
                }
                if ( $checksum && isset( $seen_checksums[ $checksum ] ) ) {
                    $summary['duplicates']++;
                    $this->log( $post_id, 'duplicate-audit', sprintf( __( 'Possible duplicate of document %d.', 'sustainable-catalyst-library' ), $seen_checksums[ $checksum ] ) );
                } elseif ( $checksum ) {
                    $seen_checksums[ $checksum ] = $post_id;
                }
            }
            update_post_meta( $post_id, self::META_MIGRATION_AUDIT, self::VERSION );
        }
        update_option( 'sc_library_pdf_reliability_migration_summary', $summary, false );
        update_option( 'sc_library_pdf_reliability_migration_version', self::VERSION, false );
    }

    public function append_health_logs() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-pdf-document-health' ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $summary = get_option( 'sc_library_pdf_reliability_migration_summary', array() );
        $entries = array();
        $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 100, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'DESC' ) );
        foreach ( $ids as $post_id ) {
            foreach ( array_slice( array_reverse( $this->logs( $post_id ) ), 0, 5 ) as $entry ) {
                $entry['post_id'] = $post_id;
                $entries[] = $entry;
            }
        }
        usort( $entries, function( $a, $b ) { return strcmp( (string) ( $b['time'] ?? '' ), (string) ( $a['time'] ?? '' ) ); } );
        ?>
        <div class="wrap sc-pdf-reliability-health">
            <hr>
            <h2><?php esc_html_e( 'v2.2.1 Reliability and Conversion Log', 'sustainable-catalyst-library' ); ?></h2>
            <?php if ( $summary ) : ?>
                <p><?php echo esc_html( sprintf( __( 'Migration audit: %1$d records checked, %2$d family repairs, %3$d status repairs, %4$d checksums added, %5$d possible duplicates.', 'sustainable-catalyst-library' ), $summary['records'] ?? 0, $summary['families'] ?? 0, $summary['statuses'] ?? 0, $summary['checksums'] ?? 0, $summary['duplicates'] ?? 0 ) ); ?></p>
            <?php endif; ?>
            <?php if ( $entries ) : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Time', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Event', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Message', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody><?php foreach ( array_slice( $entries, 0, 30 ) as $entry ) : ?><tr><td><?php echo esc_html( $entry['time'] ?? '' ); ?></td><td><a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'], 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $entry['post_id'] ) ?: sprintf( __( 'Document %d', 'sustainable-catalyst-library' ), $entry['post_id'] ) ); ?></a></td><td><?php echo esc_html( $entry['event'] ?? '' ); ?></td><td><?php echo esc_html( $entry['message'] ?? '' ); ?></td></tr><?php endforeach; ?></tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No conversion events have been recorded yet.', 'sustainable-catalyst-library' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function remove_duplicate_media_action( $actions, $post ) {
        if ( ! $post instanceof WP_Post || 'application/pdf' !== $post->post_mime_type ) {
            return $actions;
        }
        $document_id = $this->find_duplicate_document( 0, $post->ID, $this->attachment_checksum( $post->ID ) );
        if ( $document_id ) {
            $actions['sc_pdf_document_reliability'] = '<a href="' . esc_url( get_edit_post_link( $document_id, 'raw' ) ) . '">' . esc_html__( 'Open existing Knowledge Document', 'sustainable-catalyst-library' ) . '</a>';
            unset( $actions['sc_pdf_document'] );
        }
        return $actions;
    }

    public function retry_conversion() {
        $post_id = absint( wp_unslash( $_GET['post_id'] ?? 0 ) );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You are not allowed to retry this conversion.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v221_retry_' . $post_id );
        wp_safe_redirect( add_query_arg( 'sc_auto_extract', '1', get_edit_post_link( $post_id, 'raw' ) ) );
        exit;
    }

    private function force_draft( $data, $code, $detail ) {
        $data['post_status'] = 'draft';
        self::$validation_error = $code;
        self::$validation_detail = $detail;
        return $data;
    }

    private function verify_ajax() {
        check_ajax_referer( 'sc_library_pdf_v221', 'nonce' );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this document.', 'sustainable-catalyst-library' ) ), 403 );
        }
        return $post_id;
    }

    private function server_extract( $file, $post_id ) {
        $external = apply_filters( 'sc_library_pdf_to_document_extraction', null, $file, $post_id );
        if ( is_array( $external ) && ! empty( $external['pages'] ) ) {
            return array( 'pages' => $external['pages'], 'method' => $external['method'] ?? 'external-extractor' );
        }
        $binary = $this->pdftotext_binary();
        if ( ! $binary || ! function_exists( 'proc_open' ) ) {
            return null;
        }
        @set_time_limit( max( 60, absint( apply_filters( 'sc_library_pdf_server_extraction_time_limit', 180, $post_id ) ) ) );
        $process = @proc_open( array( $binary, '-layout', '-enc', 'UTF-8', $file, '-' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        if ( ! is_resource( $process ) ) {
            return null;
        }
        fclose( $pipes[0] );
        $output = stream_get_contents( $pipes[1] );
        $error = stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $exit = proc_close( $process );
        if ( 0 !== $exit || strlen( preg_replace( '/\s+/u', '', (string) $output ) ) < self::MIN_TEXT ) {
            if ( $error ) {
                $this->log( $post_id, 'server-extraction', sanitize_text_field( wp_trim_words( $error, 30, '…' ) ) );
            }
            return null;
        }
        $pages = array();
        foreach ( preg_split( '/\f/u', $output ) as $index => $chunk ) {
            $pages[] = array( 'page' => $index + 1, 'text' => self::normalize_text( $chunk ), 'lines' => array() );
        }
        return array( 'pages' => $pages, 'method' => 'pdftotext-v2.2.1' );
    }

    private function store_pages( $post_id, $attachment_id, $pages, $method ) {
        $pages = $this->remove_repeated_headers_and_footers( $pages );
        $clean = array();
        $raw = '';
        foreach ( $pages as $index => $page ) {
            $text = self::normalize_text( $page['text'] ?? '' );
            $clean[] = array(
                'page'  => absint( $page['page'] ?? $index + 1 ),
                'text'  => $text,
                'lines' => $this->sanitize_lines( $page['lines'] ?? array() ),
            );
            $raw .= ( $raw ? "\n\n" : '' ) . $text;
        }
        update_post_meta( $post_id, self::META_PAGE_COUNT, count( $clean ) );
        update_post_meta( $post_id, self::META_METHOD, sanitize_text_field( $method ) );
        update_post_meta( $post_id, '_sc_document_extracted_at', current_time( 'mysql' ) );
        $file = get_attached_file( $attachment_id );
        if ( $file && is_file( $file ) ) {
            update_post_meta( $post_id, self::META_CHECKSUM, hash_file( 'sha256', $file ) );
        }
        if ( strlen( preg_replace( '/\s+/u', '', $raw ) ) < self::MIN_TEXT ) {
            update_post_meta( $post_id, self::META_STATUS, 'needs_ocr' );
            $this->log( $post_id, 'needs-ocr', __( 'Conversion found too little text for a reliable readable document.', 'sustainable-catalyst-library' ) );
            return array( 'status' => 'needs_ocr', 'message' => __( 'Little or no extractable text was found. This PDF needs OCR.', 'sustainable-catalyst-library' ) );
        }

        $content = $this->pages_to_html( $clean );
        $title = $this->derive_title( $post_id, $clean );
        $summary = $this->derive_summary( $raw, $title );
        $update = array( 'ID' => $post_id, 'post_content' => $content, 'post_excerpt' => $summary );
        $current_title = trim( get_the_title( $post_id ) );
        if ( ! $current_title || 'Auto Draft' === $current_title ) {
            $update['post_title'] = $title;
        }
        $updated = wp_update_post( wp_slash( $update ), true );
        if ( is_wp_error( $updated ) ) {
            update_post_meta( $post_id, self::META_STATUS, 'failed' );
            $this->log( $post_id, 'save-failed', $updated->get_error_message() );
            return array( 'status' => 'failed', 'message' => $updated->get_error_message() );
        }

        $map = array();
        $offset = 0;
        foreach ( $clean as $page ) {
            $length = strlen( $page['text'] );
            $map[] = array( 'page' => $page['page'], 'start' => $offset, 'length' => $length );
            $offset += $length + 2;
        }
        update_post_meta( $post_id, self::META_RAW_TEXT, $raw );
        update_post_meta( $post_id, self::META_PAGE_MAP, wp_json_encode( $map ) );
        update_post_meta( $post_id, self::META_STATUS, 'ready_review' );
        update_post_meta( $post_id, self::META_REVIEWED, 0 );

        return array(
            'status'  => 'ready_review',
            'message' => __( 'Conversion completed and saved. Review the generated document below.', 'sustainable-catalyst-library' ),
            'content' => $content,
            'title'   => $update['post_title'] ?? get_the_title( $post_id ),
            'summary' => $summary,
            'pages'   => count( $clean ),
        );
    }

    private function pages_to_html( $pages ) {
        $html = '';
        foreach ( $pages as $page ) {
            $html .= '<section class="sc-pdf-document-page" data-pdf-page="' . esc_attr( $page['page'] ) . '">';
            $html .= '<p class="sc-pdf-document-page__number">' . esc_html( sprintf( __( 'Page %d', 'sustainable-catalyst-library' ), $page['page'] ) ) . '</p>';
            $lines = $page['lines'];
            if ( ! $lines ) {
                $lines = array_map( function( $line ) { return array( 'text' => $line, 'size' => 0, 'bold' => false ); }, preg_split( '/\R/u', $page['text'] ) );
            }
            $sizes = array_values( array_filter( array_map( function( $line ) { return (float) ( $line['size'] ?? 0 ); }, $lines ) ) );
            sort( $sizes );
            $median = $sizes ? $sizes[ (int) floor( count( $sizes ) / 2 ) ] : 0;
            $paragraph = array();
            foreach ( $lines as $line ) {
                $text = trim( preg_replace( '/\s+/u', ' ', (string) ( $line['text'] ?? '' ) ) );
                if ( '' === $text ) {
                    $html .= $this->paragraph( $paragraph );
                    $paragraph = array();
                    continue;
                }
                $heading_level = $this->heading_level( $text, (float) ( $line['size'] ?? 0 ), ! empty( $line['bold'] ), $median );
                if ( $heading_level ) {
                    $html .= $this->paragraph( $paragraph );
                    $paragraph = array();
                    $tag = 2 === $heading_level ? 'h2' : 'h3';
                    $html .= '<' . $tag . '>' . esc_html( $text ) . '</' . $tag . '>';
                    continue;
                }
                if ( $paragraph && preg_match( '/-$/u', end( $paragraph ) ) && preg_match( '/^[\p{Ll}]/u', $text ) ) {
                    $last = array_pop( $paragraph );
                    $paragraph[] = preg_replace( '/-$/u', '', $last ) . $text;
                } else {
                    $paragraph[] = $text;
                }
                if ( preg_match( '/[.!?;:]$/u', $text ) && strlen( implode( ' ', $paragraph ) ) > 220 ) {
                    $html .= $this->paragraph( $paragraph );
                    $paragraph = array();
                }
            }
            $html .= $this->paragraph( $paragraph ) . '</section>';
        }
        return $html;
    }

    private function heading_level( $text, $size, $bold, $median ) {
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
        if ( $length < 3 || $length > 140 || preg_match( '/[.!?]$/u', $text ) ) {
            return 0;
        }
        if ( preg_match( '/^(chapter|section|part|appendix)\b/iu', $text ) ) {
            return 2;
        }
        if ( preg_match( '/^\d+(?:\.\d+)*[\s\.\-]+\S/u', $text ) ) {
            return 3;
        }
        if ( $median > 0 && $size >= $median * 1.35 && $length <= 95 ) {
            return 2;
        }
        if ( $median > 0 && ( $size >= $median * 1.16 || $bold ) && $length <= 110 ) {
            return 3;
        }
        $letters = preg_replace( '/[^\p{L}]/u', '', $text );
        if ( $letters && strtoupper( $letters ) === $letters && $length <= 80 ) {
            return 3;
        }
        return 0;
    }

    private function paragraph( $parts ) {
        $text = trim( implode( ' ', (array) $parts ) );
        return $text ? '<p>' . esc_html( $text ) . '</p>' : '';
    }

    private function derive_title( $post_id, $pages ) {
        $current = trim( get_the_title( $post_id ) );
        if ( $current && 'Auto Draft' !== $current ) {
            return $current;
        }
        foreach ( $pages as $page ) {
            foreach ( $page['lines'] ?: array_map( function( $line ) { return array( 'text' => $line ); }, preg_split( '/\R/u', $page['text'] ) ) as $line ) {
                $text = trim( preg_replace( '/\s+/u', ' ', (string) ( $line['text'] ?? '' ) ) );
                $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
                if ( $length >= 5 && $length <= 160 && ! preg_match( '/^page\s+\d+$/iu', $text ) ) {
                    return sanitize_text_field( $text );
                }
            }
        }
        return __( 'Untitled PDF Document', 'sustainable-catalyst-library' );
    }

    private function derive_summary( $raw, $title ) {
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $raw ) ) );
        if ( $title && 0 === stripos( $text, $title ) ) {
            $text = trim( substr( $text, strlen( $title ) ) );
        }
        return wp_trim_words( $text, 45, '…' );
    }

    private function remove_repeated_headers_and_footers( $pages ) {
        if ( count( $pages ) < 4 ) {
            return $pages;
        }
        $candidates = array();
        foreach ( $pages as $page ) {
            $lines = $this->sanitize_lines( $page['lines'] ?? array() );
            if ( ! $lines ) {
                $raw_lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) ( $page['text'] ?? '' ) ) ) ) );
                $lines = array_map( function( $line ) { return array( 'text' => $line, 'size' => 0, 'bold' => false ); }, $raw_lines );
            }
            foreach ( array_slice( $lines, 0, 2 ) as $line ) {
                $key = $this->repeat_key( $line['text'] );
                if ( $key ) { $candidates[ $key ] = ( $candidates[ $key ] ?? 0 ) + 1; }
            }
            foreach ( array_slice( $lines, -2 ) as $line ) {
                $key = $this->repeat_key( $line['text'] );
                if ( $key ) { $candidates[ $key ] = ( $candidates[ $key ] ?? 0 ) + 1; }
            }
        }
        $threshold = max( 3, (int) ceil( count( $pages ) * 0.6 ) );
        $repeated = array_keys( array_filter( $candidates, function( $count ) use ( $threshold ) { return $count >= $threshold; } ) );
        if ( ! $repeated ) {
            return $pages;
        }
        foreach ( $pages as &$page ) {
            if ( ! empty( $page['lines'] ) ) {
                $page['lines'] = array_values( array_filter( $this->sanitize_lines( $page['lines'] ), function( $line ) use ( $repeated ) { return ! in_array( $this->repeat_key( $line['text'] ), $repeated, true ); } ) );
                $page['text'] = implode( "\n", array_map( function( $line ) { return $line['text']; }, $page['lines'] ) );
            }
        }
        unset( $page );
        return $pages;
    }

    private function repeat_key( $text ) {
        $text = strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $text ) ) );
        if ( strlen( $text ) < 3 || strlen( $text ) > 120 || preg_match( '/^\d+$/', $text ) ) {
            return '';
        }
        return $text;
    }

    private function sanitize_lines( $lines ) {
        $clean = array();
        foreach ( (array) $lines as $line ) {
            if ( ! is_array( $line ) ) {
                continue;
            }
            $text = self::normalize_text( $line['text'] ?? '' );
            if ( '' === $text ) {
                continue;
            }
            $clean[] = array(
                'text' => $text,
                'size' => round( max( 0, min( 200, (float) ( $line['size'] ?? 0 ) ) ), 2 ),
                'bold' => ! empty( $line['bold'] ),
            );
        }
        return $clean;
    }

    private function read_buffer_pages( $post_id, $session_id ) {
        $path = $this->buffer_path( $post_id, $session_id );
        if ( ! is_file( $path ) ) {
            return array();
        }
        $pages = array();
        $handle = fopen( $path, 'rb' );
        if ( ! $handle ) {
            return array();
        }
        while ( false !== ( $line = fgets( $handle ) ) ) {
            $record = json_decode( trim( $line ), true );
            if ( is_array( $record ) && ! empty( $record['page'] ) ) {
                $pages[ absint( $record['page'] ) ] = array(
                    'page'  => absint( $record['page'] ),
                    'text'  => self::normalize_text( $record['text'] ?? '' ),
                    'lines' => $this->sanitize_lines( $record['lines'] ?? array() ),
                );
            }
        }
        fclose( $handle );
        ksort( $pages );
        return $pages;
    }

    private function session_state( $post_id ) {
        $session_id = sanitize_text_field( (string) get_post_meta( $post_id, self::META_SESSION, true ) );
        return array(
            'active'        => (bool) $session_id,
            'session_id'    => $session_id,
            'attachment_id' => absint( get_post_meta( $post_id, self::META_SESSION_ATTACHMENT, true ) ),
            'checksum'      => (string) get_post_meta( $post_id, self::META_SESSION_CHECKSUM, true ),
            'last_page'     => absint( get_post_meta( $post_id, self::META_LAST_PAGE, true ) ),
            'total_pages'   => absint( get_post_meta( $post_id, self::META_TOTAL_PAGES, true ) ),
            'started_at'    => (string) get_post_meta( $post_id, self::META_STARTED_AT, true ),
            'updated_at'    => (string) get_post_meta( $post_id, self::META_UPDATED_AT, true ),
        );
    }

    private function clear_session( $post_id, $log = true ) {
        $state = $this->session_state( $post_id );
        if ( $state['session_id'] ) {
            $path = $this->buffer_path( $post_id, $state['session_id'] );
            if ( is_file( $path ) ) {
                @unlink( $path );
            }
        }
        foreach ( array( self::META_SESSION, self::META_SESSION_ATTACHMENT, self::META_SESSION_CHECKSUM, self::META_LAST_PAGE, self::META_TOTAL_PAGES, self::META_STARTED_AT, self::META_UPDATED_AT ) as $key ) {
            delete_post_meta( $post_id, $key );
        }
        if ( $log ) {
            $this->log( $post_id, 'session-cleared', __( 'Saved conversion session cleared.', 'sustainable-catalyst-library' ) );
        }
    }

    private function buffer_path( $post_id, $session_id ) {
        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'sc-library-extraction';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $safe_session = preg_replace( '/[^a-zA-Z0-9\-]/', '', $session_id );
        return trailingslashit( $dir ) . 'document-' . absint( $post_id ) . '-' . $safe_session . '.jsonl';
    }

    private function logs( $post_id ) {
        $logs = get_post_meta( $post_id, self::META_LOG, true );
        return is_array( $logs ) ? $logs : array();
    }

    private function log( $post_id, $event, $message ) {
        $logs = $this->logs( $post_id );
        $logs[] = array(
            'time'    => current_time( 'mysql' ),
            'event'   => sanitize_key( $event ),
            'message' => sanitize_text_field( $message ),
            'user'    => get_current_user_id(),
        );
        if ( count( $logs ) > self::LOG_LIMIT ) {
            $logs = array_slice( $logs, -self::LOG_LIMIT );
        }
        update_post_meta( $post_id, self::META_LOG, $logs );
    }

    private function find_duplicate_document( $post_id, $attachment_id, $checksum = '' ) {
        $queries = array();
        if ( $attachment_id ) {
            $queries[] = array( 'key' => self::META_PDF_ID, 'value' => $attachment_id, 'compare' => '=' );
        }
        if ( $checksum ) {
            $queries[] = array( 'key' => self::META_CHECKSUM, 'value' => $checksum, 'compare' => '=' );
        }
        foreach ( $queries as $meta_query ) {
            $ids = get_posts(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'any',
                    'posts_per_page' => 5,
                    'fields'         => 'ids',
                    'post__not_in'   => $post_id ? array( $post_id ) : array(),
                    'meta_query'     => array( $meta_query ),
                )
            );
            if ( $ids ) {
                return absint( $ids[0] );
            }
        }
        return 0;
    }

    private function attachment_checksum( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        return $file && is_file( $file ) ? hash_file( 'sha256', $file ) : '';
    }

    private function valid_pdf( $attachment_id ) {
        return $attachment_id && 'attachment' === get_post_type( $attachment_id ) && 'application/pdf' === get_post_mime_type( $attachment_id ) && wp_get_attachment_url( $attachment_id );
    }

    private function assign_default_family( $post_id ) {
        $terms = wp_get_object_terms( $post_id, self::TAX_FAMILY, array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $terms ) && $terms ) {
            return;
        }
        $term = get_term_by( 'slug', 'foundations', self::TAX_FAMILY );
        if ( $term ) {
            wp_set_object_terms( $post_id, array( $term->term_id ), self::TAX_FAMILY, false );
        }
    }

    private function pdftotext_binary() {
        foreach ( apply_filters( 'sc_library_pdftotext_candidates', array( '/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext' ) ) as $candidate ) {
            if ( is_string( $candidate ) && is_file( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }
        return '';
    }

    private static function normalize_text( $text ) {
        $text = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), (string) $text );
        $text = preg_replace( '/[\x{00A0}\t]+/u', ' ', $text );
        $text = preg_replace( '/[ ]{2,}/u', ' ', $text );
        return trim( preg_replace( '/\n{3,}/u', "\n\n", $text ) );
    }
}
