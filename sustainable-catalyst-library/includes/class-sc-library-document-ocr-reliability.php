<?php
/**
 * OCR reliability, recovery, and source-integrity companion.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Document_OCR_Reliability {
    public const VERSION = '2.4.1';
    public const SOURCE_CHECKSUM_META = '_sc_document_ocr_source_checksum';
    public const SOURCE_ARCHIVE_META = '_sc_document_ocr_stale_archive';
    public const RECONVERSION_REQUIRED_META = '_sc_document_ocr_reconversion_required';
    public const BACKUPS_META = '_sc_document_ocr_backups';
    public const LAST_REPAIR_META = '_sc_ocr_job_last_repair';
    public const TEMP_CLEANUP_HOOK = 'sc_library_v241_cleanup_ocr_temp';
    public const PROVIDER_CACHE_KEY = 'sc_library_v241_ocr_provider_paths';
    public const LEASE_SECONDS = 300;
    public const BACKUP_LIMIT = 5;
    public const ARCHIVE_LIMIT = 3;

    public function __construct() {
        add_action( 'init', array( $this, 'schedule_cleanup' ), 260 );
        add_action( self::TEMP_CLEANUP_HOOK, array( __CLASS__, 'cleanup_temp_files' ) );

        add_filter( 'sc_library_tesseract_candidates', array( __CLASS__, 'tesseract_candidates' ), 5 );
        add_filter( 'sc_library_pdf_rasterizer_candidates', array( __CLASS__, 'rasterizer_candidates' ), 5 );
        add_filter( 'sc_library_ocr_providers', array( __CLASS__, 'harden_providers' ), 200 );
        add_filter( 'sc_library_ocr_process_page', array( __CLASS__, 'process_external_page' ), 5, 3 );

        add_action( 'admin_post_sc_library_v241_repair_ocr_job', array( $this, 'admin_repair_job' ) );
        add_action( 'admin_post_sc_library_v241_restore_ocr_backup', array( $this, 'admin_restore_backup' ) );
        add_action( 'admin_post_sc_library_v241_cleanup_ocr_temp', array( $this, 'admin_cleanup_temp' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 176 );
    }

    public function schedule_cleanup() {
        if ( ! wp_next_scheduled( self::TEMP_CLEANUP_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::TEMP_CLEANUP_HOOK );
        }
    }

    public static function tesseract_candidates( $candidates ) {
        $configured = defined( 'SC_LIBRARY_TESSERACT_BINARY' ) ? SC_LIBRARY_TESSERACT_BINARY : '';
        return self::binary_candidates( $candidates, $configured, 'tesseract' );
    }

    public static function rasterizer_candidates( $candidates ) {
        $configured = defined( 'SC_LIBRARY_PDF_RASTERIZER_BINARY' ) ? SC_LIBRARY_PDF_RASTERIZER_BINARY : '';
        $candidates = self::binary_candidates( $candidates, $configured, 'pdftoppm' );
        return self::binary_candidates( $candidates, '', 'pdftocairo' );
    }

    private static function binary_candidates( $candidates, $configured, $name ) {
        $resolved = array();
        if ( is_string( $configured ) && '' !== trim( $configured ) ) {
            $resolved[] = trim( $configured );
        }

        $path = getenv( 'PATH' );
        if ( is_string( $path ) ) {
            foreach ( explode( PATH_SEPARATOR, $path ) as $directory ) {
                $directory = trim( $directory );
                if ( '' !== $directory ) {
                    $resolved[] = trailingslashit( $directory ) . $name;
                }
            }
        }

        foreach ( (array) $candidates as $candidate ) {
            $resolved[] = $candidate;
        }

        return array_values( array_unique( array_filter( array_map( 'strval', $resolved ) ) ) );
    }

    public static function harden_providers( $providers ) {
        if ( ! is_array( $providers ) ) {
            return array();
        }

        if ( isset( $providers['external_endpoint'] ) ) {
            $endpoint = defined( 'SC_LIBRARY_OCR_ENDPOINT' ) ? trim( (string) SC_LIBRARY_OCR_ENDPOINT ) : '';
            $key = defined( 'SC_LIBRARY_OCR_API_KEY' ) ? trim( (string) SC_LIBRARY_OCR_API_KEY ) : '';
            $valid_url = $endpoint && wp_http_validate_url( $endpoint );
            $https = $valid_url && 'https' === strtolower( (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) );
            $allow_http = (bool) apply_filters( 'sc_library_ocr_allow_http_endpoint', false, $endpoint );

            $providers['external_endpoint']['available'] = (bool) ( $valid_url && $key && ( $https || $allow_http ) );
            if ( ! $endpoint ) {
                $providers['external_endpoint']['description'] = __( 'External OCR endpoint is not configured.', 'sustainable-catalyst-library' );
            } elseif ( ! $valid_url ) {
                $providers['external_endpoint']['description'] = __( 'External OCR endpoint is not a valid URL.', 'sustainable-catalyst-library' );
            } elseif ( ! $https && ! $allow_http ) {
                $providers['external_endpoint']['description'] = __( 'External OCR endpoint must use HTTPS.', 'sustainable-catalyst-library' );
            } elseif ( ! $key ) {
                $providers['external_endpoint']['description'] = __( 'External OCR API key is required for signed requests.', 'sustainable-catalyst-library' );
            } else {
                $providers['external_endpoint']['description'] = __( 'Signed HTTPS page requests sent to the configured OCR endpoint.', 'sustainable-catalyst-library' );
            }
        }

        if ( isset( $providers['local_tesseract'] ) ) {
            $providers['local_tesseract']['description'] .= ' ' . __( 'v2.4.1 resolves configured binaries and executable PATH locations.', 'sustainable-catalyst-library' );
        }

        return $providers;
    }

    public static function process_external_page( $result, $context, $provider_id ) {
        if ( 'external_endpoint' !== $provider_id ) {
            return $result;
        }

        $endpoint = defined( 'SC_LIBRARY_OCR_ENDPOINT' ) ? trim( (string) SC_LIBRARY_OCR_ENDPOINT ) : '';
        $key = defined( 'SC_LIBRARY_OCR_API_KEY' ) ? trim( (string) SC_LIBRARY_OCR_API_KEY ) : '';

        if ( ! $endpoint || ! wp_http_validate_url( $endpoint ) ) {
            return new WP_Error( 'provider_unavailable', __( 'The external OCR endpoint is missing or invalid.', 'sustainable-catalyst-library' ) );
        }
        if ( ! $key ) {
            return new WP_Error( 'provider_unavailable', __( 'The external OCR API key is required.', 'sustainable-catalyst-library' ) );
        }

        $scheme = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) );
        if ( 'https' !== $scheme && ! apply_filters( 'sc_library_ocr_allow_http_endpoint', false, $endpoint ) ) {
            return new WP_Error( 'provider_unavailable', __( 'The external OCR endpoint must use HTTPS.', 'sustainable-catalyst-library' ) );
        }

        $payload = array(
            'version'      => $context['version'] ?? 'sc-library-ocr/1.0',
            'document_id'  => absint( $context['document_id'] ?? 0 ),
            'page'         => absint( $context['page'] ?? 0 ),
            'language'     => sanitize_text_field( $context['language'] ?? 'eng' ),
            'pdf_url'      => esc_url_raw( $context['pdf_url'] ?? '' ),
            'checksum'     => sanitize_text_field( $context['checksum'] ?? '' ),
            'requested_at' => gmdate( 'c' ),
        );
        $body = wp_json_encode( $payload );
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Content-Type'       => 'application/json',
                    'Accept'             => 'application/json',
                    'Authorization'      => 'Bearer ' . $key,
                    'X-SC-OCR-Signature' => hash_hmac( 'sha256', $body, $key ),
                    'X-SC-OCR-Version'   => self::VERSION,
                ),
                'body'        => $body,
                'timeout'     => max( 30, absint( apply_filters( 'sc_library_ocr_external_timeout', 120, $context ) ) ),
                'redirection' => 1,
                'data_format' => 'body',
                'limit_response_size' => max( 65536, absint( apply_filters( 'sc_library_ocr_external_max_response_bytes', 5 * 1024 * 1024, $context ) ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'external_failed', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 || ! is_array( $decoded ) ) {
            return new WP_Error(
                'external_failed',
                sprintf( __( 'External OCR returned HTTP %d or invalid JSON.', 'sustainable-catalyst-library' ), $code )
            );
        }

        $text = wp_check_invalid_utf8( (string) ( $decoded['text'] ?? '' ) );
        $max_chars = max( 1000, absint( apply_filters( 'sc_library_ocr_max_page_characters', 500000, $context ) ) );
        if ( '' === trim( $text ) ) {
            return new WP_Error( 'ocr_empty', __( 'The external OCR provider returned no readable text.', 'sustainable-catalyst-library' ) );
        }
        if ( strlen( $text ) > $max_chars ) {
            return new WP_Error( 'ocr_response_too_large', __( 'The external OCR page text exceeded the configured size limit.', 'sustainable-catalyst-library' ) );
        }

        return array(
            'text'       => $text,
            'confidence' => max( 0, min( 100, (float) ( $decoded['confidence'] ?? 0 ) ) ),
            'language'   => sanitize_text_field( $decoded['language'] ?? 'und' ),
            'provider'   => sanitize_text_field( $decoded['provider'] ?? 'external_endpoint' ),
            'warnings'   => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $decoded['warnings'] ?? array() ) ) ) ),
        );
    }

    public static function validate_queue_request( $document_id, $provider_id, $language, $providers ) {
        if ( ! isset( $providers[ $provider_id ] ) || empty( $providers[ $provider_id ]['available'] ) ) {
            return new WP_Error( 'provider_unavailable', __( 'The selected OCR provider is unavailable.', 'sustainable-catalyst-library' ) );
        }

        $source = self::ensure_source_current( $document_id );
        if ( is_wp_error( $source ) ) {
            return $source;
        }

        if ( 'local_tesseract' === $provider_id ) {
            $available = array_map( 'strtolower', (array) ( $providers[ $provider_id ]['languages'] ?? array() ) );
            if ( $available ) {
                foreach ( preg_split( '/[+,\s]+/', strtolower( (string) $language ) ) as $requested ) {
                    if ( $requested && ! in_array( $requested, $available, true ) ) {
                        return new WP_Error(
                            'language_unavailable',
                            sprintf( __( 'The local Tesseract language “%s” is not installed.', 'sustainable-catalyst-library' ), $requested )
                        );
                    }
                }
            }
        }

        return true;
    }

    public static function source_fingerprint( $document_id ) {
        $attachment_id = absint( get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PDF_ID, true ) );
        $file = $attachment_id ? get_attached_file( $attachment_id ) : '';
        if ( ! $attachment_id || ! $file || ! is_file( $file ) || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
            return new WP_Error( 'missing_pdf', __( 'The original PDF is missing or invalid.', 'sustainable-catalyst-library' ) );
        }

        return array(
            'attachment_id' => $attachment_id,
            'checksum'      => hash_file( 'sha256', $file ),
            'size'          => filesize( $file ),
            'modified'      => filemtime( $file ),
        );
    }

    public static function ensure_source_current( $document_id ) {
        $fingerprint = self::source_fingerprint( $document_id );
        if ( is_wp_error( $fingerprint ) ) {
            return $fingerprint;
        }

        $reconversion_required = (bool) get_post_meta( $document_id, self::RECONVERSION_REQUIRED_META, true );
        if ( $reconversion_required ) {
            $conversion_checksum = sanitize_text_field( get_post_meta( $document_id, '_sc_document_checksum', true ) );
            if ( $conversion_checksum && hash_equals( $conversion_checksum, (string) $fingerprint['checksum'] ) ) {
                delete_post_meta( $document_id, self::RECONVERSION_REQUIRED_META );
                update_post_meta( $document_id, self::SOURCE_CHECKSUM_META, $fingerprint['checksum'] );
                return $fingerprint;
            }
            return new WP_Error(
                'reconversion_required',
                __( 'The attached PDF changed and must be reconverted before OCR can continue.', 'sustainable-catalyst-library' )
            );
        }

        $stored = get_post_meta( $document_id, self::SOURCE_CHECKSUM_META, true );
        if ( ! $stored ) {
            $conversion_checksum = sanitize_text_field( get_post_meta( $document_id, '_sc_document_checksum', true ) );
            if ( $conversion_checksum && ! hash_equals( $conversion_checksum, (string) $fingerprint['checksum'] ) ) {
                $stored = $conversion_checksum;
            } else {
                update_post_meta( $document_id, self::SOURCE_CHECKSUM_META, $fingerprint['checksum'] );
                return $fingerprint;
            }
        }
        if ( hash_equals( (string) $stored, (string) $fingerprint['checksum'] ) ) {
            return $fingerprint;
        }

        $archive = get_post_meta( $document_id, self::SOURCE_ARCHIVE_META, true );
        $archive = is_array( $archive ) ? $archive : array();
        $archive[] = array(
            'archived_at' => current_time( 'mysql' ),
            'checksum'    => $stored,
            'pages'       => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGES, true ),
            'summary'     => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_SUMMARY, true ),
            'status'      => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_STATUS, true ),
        );
        if ( count( $archive ) > self::ARCHIVE_LIMIT ) {
            $archive = array_slice( $archive, -self::ARCHIVE_LIMIT );
        }
        update_post_meta( $document_id, self::SOURCE_ARCHIVE_META, $archive );

        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGES );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_SUMMARY );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_STATUS );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PUBLIC_WARNING );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_APPLIED_AT );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_APPLIED_BY );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_RAW_TEXT );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGE_MAP );
        delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGE_COUNT );
        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_EXTRACTION_STATUS, 'pending' );
        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_DOCUMENT_REVIEWED, 0 );
        update_post_meta( $document_id, self::RECONVERSION_REQUIRED_META, 1 );
        delete_transient( 'sc_library_v241_ocr_workspace_totals' );
        update_post_meta( $document_id, self::SOURCE_CHECKSUM_META, $fingerprint['checksum'] );

        return new WP_Error(
            'source_changed',
            __( 'The attached PDF changed. Prior OCR page results were archived and the current PDF must be reconverted before OCR continues.', 'sustainable-catalyst-library' )
        );
    }

    public static function snapshot_document( $document_id ) {
        $post = get_post( $document_id );
        if ( ! $post || SC_Library_Document_OCR_Processing::POST_TYPE !== $post->post_type ) {
            return new WP_Error( 'invalid_document', __( 'The OCR document record could not be found.', 'sustainable-catalyst-library' ) );
        }

        $backups = get_post_meta( $document_id, self::BACKUPS_META, true );
        $backups = is_array( $backups ) ? $backups : array();
        $backups[] = array(
            'created_at'       => current_time( 'mysql' ),
            'created_by'       => get_current_user_id(),
            'post_content'     => $post->post_content,
            'post_excerpt'     => $post->post_excerpt,
            'post_status'      => $post->post_status,
            'raw_text'         => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_RAW_TEXT, true ),
            'page_map'         => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGE_MAP, true ),
            'extraction_status'=> get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_EXTRACTION_STATUS, true ),
            'reviewed'         => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_DOCUMENT_REVIEWED, true ),
            'public_warning'   => get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PUBLIC_WARNING, true ),
        );
        if ( count( $backups ) > self::BACKUP_LIMIT ) {
            $backups = array_slice( $backups, -self::BACKUP_LIMIT );
        }
        update_post_meta( $document_id, self::BACKUPS_META, $backups );
        delete_transient( 'sc_library_v241_ocr_workspace_totals' );
        return true;
    }

    public static function has_backup( $document_id ) {
        $backups = get_post_meta( $document_id, self::BACKUPS_META, true );
        return is_array( $backups ) && ! empty( $backups );
    }

    public static function restore_latest_backup( $document_id ) {
        $backups = get_post_meta( $document_id, self::BACKUPS_META, true );
        if ( ! is_array( $backups ) || ! $backups ) {
            return new WP_Error( 'no_backup', __( 'No pre-OCR document backup is available.', 'sustainable-catalyst-library' ) );
        }

        $backup = array_pop( $backups );
        $updated = wp_update_post(
            wp_slash(
                array(
                    'ID'           => $document_id,
                    'post_content' => $backup['post_content'] ?? '',
                    'post_excerpt' => $backup['post_excerpt'] ?? '',
                    'post_status'  => $backup['post_status'] ?? 'draft',
                )
            ),
            true
        );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_RAW_TEXT, $backup['raw_text'] ?? '' );
        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGE_MAP, $backup['page_map'] ?? '' );
        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_EXTRACTION_STATUS, $backup['extraction_status'] ?? 'ready_review' );
        update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_DOCUMENT_REVIEWED, $backup['reviewed'] ?? 0 );

        if ( ! empty( $backup['public_warning'] ) ) {
            update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PUBLIC_WARNING, $backup['public_warning'] );
        } else {
            delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PUBLIC_WARNING );
        }

        update_post_meta( $document_id, self::BACKUPS_META, $backups );
        delete_transient( 'sc_library_v241_ocr_workspace_totals' );
        return true;
    }

    public static function has_active_pages( $document_id ) {
        $pages = get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGES, true );
        if ( ! is_array( $pages ) ) {
            return false;
        }
        foreach ( $pages as $page ) {
            if ( is_array( $page ) && in_array( $page['status'] ?? '', array( 'queued', 'processing' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    public static function repair_job( $job_id ) {
        if ( SC_Library_Document_OCR_Processing::JOB_TYPE !== get_post_type( $job_id ) ) {
            return new WP_Error( 'invalid_job', __( 'The OCR job could not be found.', 'sustainable-catalyst-library' ) );
        }

        $items = get_post_meta( $job_id, SC_Library_Document_OCR_Processing::META_JOB_ITEMS, true );
        $items = is_array( $items ) ? array_values( $items ) : array();
        $now = time();
        $requeued = 0;
        $failed = 0;
        $documents_to_refresh = array();

        foreach ( $items as &$item ) {
            $state = sanitize_key( $item['state'] ?? '' );
            $document_id = absint( $item['document_id'] ?? 0 );
            $page_number = absint( $item['page'] ?? 0 );

            if ( ! $document_id || SC_Library_Document_OCR_Processing::POST_TYPE !== get_post_type( $document_id ) || ! $page_number ) {
                $item['state'] = 'failed';
                $item['message'] = __( 'OCR queue item references a missing document or page.', 'sustainable-catalyst-library' );
                unset( $item['lock_user'], $item['lock_client'], $item['lock_time'], $item['lock_token'] );
                $failed++;
                continue;
            }

            if ( 'processing' === $state ) {
                $lock_time = absint( $item['lock_time'] ?? 0 );
                if ( ! $lock_time || $now - $lock_time > self::LEASE_SECONDS ) {
                    $item['state'] = 'queued';
                    $item['message'] = __( 'Stale OCR lease repaired and returned to the queue.', 'sustainable-catalyst-library' );
                    unset( $item['lock_user'], $item['lock_client'], $item['lock_time'], $item['lock_token'] );
                    $documents_to_refresh[ $document_id ][ $page_number ] = array(
                        'status'  => 'queued',
                        'message' => $item['message'],
                    );
                    $requeued++;
                }
            }
        }
        unset( $item );

        foreach ( $documents_to_refresh as $document_id => $page_updates ) {
            $pages = get_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGES, true );
            if ( ! is_array( $pages ) ) {
                continue;
            }
            foreach ( $page_updates as $page_number => $update ) {
                if ( isset( $pages[ $page_number ] ) && is_array( $pages[ $page_number ] ) ) {
                    $pages[ $page_number ]['status'] = $update['status'];
                    $pages[ $page_number ]['message'] = $update['message'];
                    $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
                }
            }
            update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_PAGES, $pages );
            delete_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_SUMMARY );
            update_post_meta( $document_id, SC_Library_Document_OCR_Processing::META_STATUS, 'queued' );
        }
        if ( $documents_to_refresh ) {
            delete_transient( 'sc_library_v241_ocr_workspace_totals' );
        }

        update_post_meta( $job_id, SC_Library_Document_OCR_Processing::META_JOB_ITEMS, $items );
        update_post_meta( $job_id, self::LAST_REPAIR_META, current_time( 'mysql' ) );

        $has_queue = false;
        $has_attention = false;
        foreach ( $items as $item ) {
            if ( in_array( $item['state'] ?? '', array( 'queued', 'processing' ), true ) ) {
                $has_queue = true;
            }
            if ( in_array( $item['state'] ?? '', array( 'failed', 'unavailable', 'low_confidence' ), true ) ) {
                $has_attention = true;
            }
        }
        update_post_meta(
            $job_id,
            SC_Library_Document_OCR_Processing::META_JOB_STATUS,
            $has_queue ? 'running' : ( $has_attention ? 'complete_with_errors' : 'complete' )
        );

        return array( 'requeued' => $requeued, 'failed' => $failed );
    }

    public function admin_repair_job() {
        $job_id = absint( wp_unslash( $_POST['job_id'] ?? 0 ) );
        if ( ! $job_id || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair this OCR job.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v241_repair_ocr_job_' . $job_id );
        $result = self::repair_job( $job_id );
        $notice = is_wp_error( $result ) ? $result->get_error_code() : 'job_repaired';
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-ocr-review&tab=queue&job_id=' . $job_id . '&sc_v241_notice=' . sanitize_key( $notice ) ) );
        exit;
    }

    public function admin_restore_backup() {
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to restore this document.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v241_restore_ocr_backup_' . $document_id );
        $result = self::restore_latest_backup( $document_id );
        $notice = is_wp_error( $result ) ? $result->get_error_code() : 'backup_restored';
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-ocr-review&document_id=' . $document_id . '&sc_v241_notice=' . sanitize_key( $notice ) ) );
        exit;
    }

    public function admin_cleanup_temp() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to clean OCR temporary files.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v241_cleanup_ocr_temp' );
        $count = self::cleanup_temp_files();
        delete_transient( 'sc_library_v241_local_ocr_status' );
        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-ocr-review&tab=providers&sc_v241_notice=temp_cleaned&sc_v241_count=' . absint( $count ) ) );
        exit;
    }

    public static function cleanup_temp_files() {
        $uploads = wp_upload_dir();
        $directory = trailingslashit( $uploads['basedir'] ) . 'sc-library-ocr';
        if ( ! is_dir( $directory ) ) {
            return 0;
        }

        $cutoff = time() - max( HOUR_IN_SECONDS, absint( apply_filters( 'sc_library_ocr_temp_retention_seconds', DAY_IN_SECONDS ) ) );
        $removed = 0;
        foreach ( glob( trailingslashit( $directory ) . '*' ) ?: array() as $path ) {
            if ( is_dir( $path ) && filemtime( $path ) < $cutoff ) {
                self::remove_tree( $path );
                $removed++;
            }
        }
        return $removed;
    }

    private static function remove_tree( $directory ) {
        foreach ( scandir( $directory ) ?: array() as $item ) {
            if ( in_array( $item, array( '.', '..' ), true ) ) {
                continue;
            }
            $path = trailingslashit( $directory ) . $item;
            if ( is_dir( $path ) ) {
                self::remove_tree( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $directory );
    }

    public static function safe_csv_cell( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value );
        }
        $value = (string) $value;
        if ( preg_match( '/^[=\-+@]/', ltrim( $value ) ) ) {
            $value = "'" . $value;
        }
        return $value;
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-ocr-review' ) ) {
            return;
        }

        $notice = isset( $_GET['sc_v241_notice'] ) ? sanitize_key( wp_unslash( $_GET['sc_v241_notice'] ) ) : '';
        $messages = array(
            'job_repaired'    => array( 'success', __( 'OCR queue state repaired. Stale leases were requeued and missing items were marked for attention.', 'sustainable-catalyst-library' ) ),
            'backup_restored' => array( 'success', __( 'The latest pre-OCR document backup was restored.', 'sustainable-catalyst-library' ) ),
            'temp_cleaned'    => array( 'success', sprintf( __( 'OCR temporary cleanup completed. Removed %d expired directories.', 'sustainable-catalyst-library' ), absint( $_GET['sc_v241_count'] ?? 0 ) ) ),
            'source_changed'  => array( 'warning', __( 'The attached PDF changed. Prior OCR records were archived; run PDF conversion again before OCR.', 'sustainable-catalyst-library' ) ),
            'reconversion_required' => array( 'error', __( 'Run PDF conversion again for the current attachment before analyzing or queueing OCR pages.', 'sustainable-catalyst-library' ) ),
            'language_unavailable' => array( 'error', __( 'The requested local OCR language is not installed.', 'sustainable-catalyst-library' ) ),
            'published_confirmation_required' => array( 'error', __( 'Confirm that the published document may return to draft before applying OCR.', 'sustainable-catalyst-library' ) ),
            'active_ocr_job'  => array( 'error', __( 'Pause, complete, or cancel active OCR pages before applying text.', 'sustainable-catalyst-library' ) ),
            'no_backup'       => array( 'error', __( 'No pre-OCR document backup is available.', 'sustainable-catalyst-library' ) ),
        );

        if ( isset( $messages[ $notice ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>';
        }
    }
}
