<?php
/**
 * OCR and scanned-document processing.
 *
 * Adds page-level scan detection, persistent OCR jobs, local Tesseract and
 * external provider adapters, confidence/language records, manual correction,
 * side-by-side review, and controlled application to readable documents.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Document_OCR_Processing {
    public const VERSION = '2.4.1';
    public const POST_TYPE = 'sc_foundation_doc';
    public const JOB_TYPE = 'sc_pdf_ocr_job';

    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const META_RAW_TEXT = '_sc_document_raw_text';
    public const META_PAGE_MAP = '_sc_document_page_map';
    public const META_PAGE_COUNT = '_sc_document_page_count';
    public const META_EXTRACTION_STATUS = '_sc_document_extraction_status';
    public const META_DOCUMENT_REVIEWED = '_sc_document_reviewed';

    public const META_PAGES = '_sc_document_ocr_pages';
    public const META_SUMMARY = '_sc_document_ocr_summary';
    public const META_STATUS = '_sc_document_ocr_status';
    public const META_PROVIDER = '_sc_document_ocr_provider';
    public const META_LANGUAGE = '_sc_document_ocr_language';
    public const META_CONFIDENCE_THRESHOLD = '_sc_document_ocr_confidence_threshold';
    public const META_PUBLIC_WARNING = '_sc_document_ocr_public_warning';
    public const META_APPLIED_AT = '_sc_document_ocr_applied_at';
    public const META_APPLIED_BY = '_sc_document_ocr_applied_by';
    public const META_LOG = '_sc_document_ocr_log';

    public const META_JOB_ITEMS = '_sc_ocr_job_items';
    public const META_JOB_STATUS = '_sc_ocr_job_status';
    public const META_JOB_CONFIG = '_sc_ocr_job_config';
    public const META_JOB_LOG = '_sc_ocr_job_log';

    public const DEFAULT_MIN_SOURCE_CHARACTERS = 80;
    public const DEFAULT_CONFIDENCE_THRESHOLD = 75;
    public const PAGE_SIZE = 40;
    public const JOB_LIMIT = 30;
    public const LOG_LIMIT = 100;

    private static $page_status_labels = array(
        'not_needed'     => 'Text available',
        'needs_ocr'      => 'Needs OCR',
        'queued'         => 'Queued',
        'processing'     => 'Processing',
        'complete'       => 'OCR complete',
        'low_confidence' => 'Low confidence',
        'reviewed'       => 'Reviewed',
        'failed'         => 'Failed',
        'unavailable'    => 'Provider unavailable',
        'cancelled'      => 'Cancelled',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'register_job_type' ), 210 );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ), 235 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 150 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 175 );

        add_action( 'added_post_meta', array( $this, 'watch_extraction_status' ), 250, 4 );
        add_action( 'updated_post_meta', array( $this, 'watch_extraction_status' ), 250, 4 );

        add_action( 'admin_post_sc_library_v240_analyze_document', array( $this, 'admin_analyze_document' ) );
        add_action( 'admin_post_sc_library_v240_create_ocr_job', array( $this, 'admin_create_ocr_job' ) );
        add_action( 'admin_post_sc_library_v240_save_page_review', array( $this, 'admin_save_page_review' ) );
        add_action( 'admin_post_sc_library_v240_apply_ocr', array( $this, 'admin_apply_ocr' ) );
        add_action( 'admin_post_sc_library_v240_export_document_ocr', array( $this, 'export_document_ocr_csv' ) );
        add_action( 'admin_post_sc_library_v240_export_ocr_job', array( $this, 'export_ocr_job_csv' ) );

        add_action( 'wp_ajax_sc_library_v240_job_status', array( $this, 'ajax_job_status' ) );
        add_action( 'wp_ajax_sc_library_v240_next_item', array( $this, 'ajax_next_item' ) );
        add_action( 'wp_ajax_sc_library_v240_process_item', array( $this, 'ajax_process_item' ) );
        add_action( 'wp_ajax_sc_library_v240_control_job', array( $this, 'ajax_control_job' ) );

        add_filter( 'the_content', array( $this, 'public_ocr_notice' ), 7 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'document_columns' ), 180 );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'document_column_content' ), 180, 2 );
    }

    public function register_job_type() {
        register_post_type(
            self::JOB_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'OCR Jobs', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'OCR Job', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'show_ui'             => false,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title' ),
                'can_export'          => false,
            )
        );
    }

    public function register_admin_page() {
        add_submenu_page(
            'sc-library',
            __( 'OCR and Scanned Documents', 'sustainable-catalyst-library' ),
            __( 'OCR Review', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-ocr-review',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-ocr-review' ) ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-ocr-review',
            SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css',
            array(),
            $this->version()
        );
        wp_enqueue_script(
            'sc-library-ocr-review',
            SC_LIBRARY_URL . 'assets/js/sc-library-ocr-review.js',
            array( 'jquery' ),
            $this->version(),
            true
        );
        wp_localize_script(
            'sc-library-ocr-review',
            'SCLibraryOcrReview',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_ocr_v240' ),
                'jobId'   => isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0,
                'strings' => array(
                    'paused'   => __( 'OCR queue paused.', 'sustainable-catalyst-library' ),
                    'complete' => __( 'OCR queue complete.', 'sustainable-catalyst-library' ),
                    'errors'   => __( 'OCR queue complete with pages requiring attention.', 'sustainable-catalyst-library' ),
                    'retrying' => __( 'Connection interrupted. Retrying…', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
        $job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'documents';

        if ( $document_id && self::POST_TYPE === get_post_type( $document_id ) ) {
            $this->render_document_review( $document_id );
            return;
        }
        if ( $job_id && self::JOB_TYPE === get_post_type( $job_id ) ) {
            $tab = 'queue';
        }
        ?>
        <div class="wrap sc-ocr-admin">
            <h1><?php esc_html_e( 'OCR and Scanned Document Processing', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Detect low-text pages, process selected pages, review confidence, correct OCR, and apply reviewed text while preserving the original PDF.', 'sustainable-catalyst-library' ); ?></p>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'OCR workspace', 'sustainable-catalyst-library' ); ?>">
                <a class="nav-tab <?php echo 'documents' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&tab=documents' ) ); ?>"><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></a>
                <a class="nav-tab <?php echo 'queue' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&tab=queue' ) ); ?>"><?php esc_html_e( 'OCR Queue', 'sustainable-catalyst-library' ); ?></a>
                <a class="nav-tab <?php echo 'providers' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&tab=providers' ) ); ?>"><?php esc_html_e( 'Providers', 'sustainable-catalyst-library' ); ?></a>
            </nav>
            <?php
            if ( 'queue' === $tab ) {
                $this->render_queue_tab( $job_id );
            } elseif ( 'providers' === $tab ) {
                $this->render_providers_tab();
            } else {
                $this->render_documents_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_documents_tab() {
        $page = isset( $_GET['ocr_page'] ) ? max( 1, absint( wp_unslash( $_GET['ocr_page'] ) ) ) : 1;
        $filter = isset( $_GET['ocr_status'] ) ? sanitize_key( wp_unslash( $_GET['ocr_status'] ) ) : 'all';
        $query_args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => self::PAGE_SIZE,
            'paged'          => $page,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );
        if ( 'all' !== $filter ) {
            $status_values = array(
                'needs_ocr'      => array( 'needs_ocr' ),
                'processing'     => array( 'queued', 'processing' ),
                'low_confidence' => array( 'needs_review' ),
                'complete'       => array( 'complete' ),
                'reviewed'       => array( 'reviewed', 'applied', 'applied_with_warnings' ),
            );
            if ( 'not_required' === $filter ) {
                $query_args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => self::META_STATUS,
                        'value'   => 'not_required',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => self::META_STATUS,
                        'compare' => 'NOT EXISTS',
                    ),
                );
            } elseif ( isset( $status_values[ $filter ] ) ) {
                $query_args['meta_query'] = array(
                    array(
                        'key'     => self::META_STATUS,
                        'value'   => $status_values[ $filter ],
                        'compare' => 'IN',
                    ),
                );
            }
        }
        $query = new WP_Query( $query_args );
        $totals = $this->workspace_totals();
        ?>
        <section class="sc-ocr-section">
            <div class="sc-pdf-bulk-cards">
                <div><strong><?php echo esc_html( $totals['documents'] ); ?></strong><span><?php esc_html_e( 'PDF Documents', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $totals['needs_ocr'] ); ?></strong><span><?php esc_html_e( 'Need OCR', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $totals['low_confidence'] ); ?></strong><span><?php esc_html_e( 'Low-confidence pages', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $totals['reviewed'] ); ?></strong><span><?php esc_html_e( 'Reviewed OCR pages', 'sustainable-catalyst-library' ); ?></span></div>
            </div>
            <form method="get" class="sc-pdf-bulk-filter">
                <input type="hidden" name="page" value="sc-library-ocr-review">
                <input type="hidden" name="tab" value="documents">
                <label><?php esc_html_e( 'OCR status', 'sustainable-catalyst-library' ); ?>
                    <select name="ocr_status">
                        <?php foreach ( $this->document_filter_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="button"><?php esc_html_e( 'Filter', 'sustainable-catalyst-library' ); ?></button>
            </form>
            <table class="widefat striped sc-ocr-document-table">
                <thead><tr><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Extraction', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'OCR pages', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Confidence', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Provider', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Review', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                <tbody>
                <?php
                $shown = 0;
                if ( $query->have_posts() ) :
                    while ( $query->have_posts() ) :
                        $query->the_post();
                        $post_id = get_the_ID();
                        $summary = $this->summary( $post_id );
                        $state = $this->document_state( $summary );
                        $shown++;
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title() ?: __( '(Untitled)', 'sustainable-catalyst-library' ) ); ?></a></strong><br><span class="description"><?php echo esc_html( sprintf( __( 'Document %d', 'sustainable-catalyst-library' ), $post_id ) ); ?></span></td>
                            <td><?php echo esc_html( $this->extraction_status_label( get_post_meta( $post_id, self::META_EXTRACTION_STATUS, true ) ) ); ?></td>
                            <td><?php echo esc_html( sprintf( __( '%1$d need OCR · %2$d complete · %3$d reviewed', 'sustainable-catalyst-library' ), $summary['needs_ocr'], $summary['complete'] + $summary['low_confidence'], $summary['reviewed'] ) ); ?></td>
                            <td><?php echo $summary['average_confidence'] > 0 ? esc_html( number_format_i18n( $summary['average_confidence'], 1 ) . '%' ) : '—'; ?></td>
                            <td><?php echo esc_html( $summary['provider'] ?: __( 'Not selected', 'sustainable-catalyst-library' ) ); ?></td>
                            <td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&document_id=' . $post_id ) ); ?>"><?php esc_html_e( 'Open OCR Review', 'sustainable-catalyst-library' ); ?></a></td>
                        </tr>
                        <?php
                    endwhile;
                endif;
                wp_reset_postdata();
                if ( ! $shown ) : ?><tr><td colspan="6"><?php esc_html_e( 'No documents on this page match the selected OCR status.', 'sustainable-catalyst-library' ); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
            <?php if ( $query->max_num_pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'ocr_page' => '%#%', 'ocr_status' => $filter ) ), 'current' => $page, 'total' => $query->max_num_pages ) ) ); ?></div></div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_document_review( $document_id ) {
        if ( ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to review this document.', 'sustainable-catalyst-library' ) );
        }

        $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $document_id );
        $source_changed = is_wp_error( $source_state ) && 'source_changed' === $source_state->get_error_code();
        $requires_reconversion = is_wp_error( $source_state ) && in_array( $source_state->get_error_code(), array( 'source_changed', 'reconversion_required' ), true );

        $pages = $this->pages( $document_id );
        if ( ! $pages && ! $requires_reconversion ) {
            $pages = $this->analyze_document( $document_id, true );
        }

        $summary = $this->summary( $document_id );
        $pdf_id = absint( get_post_meta( $document_id, self::META_PDF_ID, true ) );
        $pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
        $current_page = isset( $_GET['ocr_review_page'] ) ? max( 1, absint( wp_unslash( $_GET['ocr_review_page'] ) ) ) : $this->first_attention_page( $pages );
        if ( ! isset( $pages[ $current_page ] ) ) {
            $current_page = $pages ? absint( array_key_first( $pages ) ) : 1;
        }
        $current = $pages[ $current_page ] ?? array();
        $providers = $this->providers();
        $selected_provider = sanitize_key( (string) get_post_meta( $document_id, self::META_PROVIDER, true ) );
        if ( ! isset( $providers[ $selected_provider ] ) ) {
            $selected_provider = $this->default_provider_id( $providers );
        }
        $selected_language = sanitize_text_field( (string) get_post_meta( $document_id, self::META_LANGUAGE, true ) ) ?: 'eng';
        $threshold = $this->confidence_threshold( $document_id );
        $document_status = get_post_status( $document_id );
        $has_backup = SC_Library_Document_OCR_Reliability::has_backup( $document_id );
        ?>
        <div class="wrap sc-ocr-admin sc-ocr-document-review">
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review' ) ); ?>">← <?php esc_html_e( 'Back to OCR documents', 'sustainable-catalyst-library' ); ?></a></p>
            <div class="sc-ocr-review-header">
                <div>
                    <p class="sc-pdf-bulk-kicker"><?php esc_html_e( 'OCR Review', 'sustainable-catalyst-library' ); ?></p>
                    <h1><?php echo esc_html( get_the_title( $document_id ) ); ?></h1>
                    <p><?php echo esc_html( sprintf( __( '%1$d pages · %2$d need OCR · %3$d low confidence · %4$d reviewed', 'sustainable-catalyst-library' ), $summary['total'], $summary['needs_ocr'], $summary['low_confidence'], $summary['reviewed'] ) ); ?></p>
                </div>
                <div class="sc-ocr-review-header__actions">
                    <a class="button" href="<?php echo esc_url( get_edit_post_link( $document_id, 'raw' ) ); ?>"><?php esc_html_e( 'Edit Document Record', 'sustainable-catalyst-library' ); ?></a>
                    <?php if ( $pdf_url ) : ?><a class="button" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Original PDF', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
                    <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_v240_export_document_ocr', 'document_id' => $document_id ), admin_url( 'admin-post.php' ) ), 'sc_library_v240_export_document_ocr_' . $document_id ) ); ?>"><?php esc_html_e( 'Export OCR Report CSV', 'sustainable-catalyst-library' ); ?></a>
                </div>
            </div>

            <?php if ( $source_changed || $requires_reconversion ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'The attached PDF changed. Previous OCR records were archived. Run PDF conversion again before analyzing or queueing OCR pages.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php endif; ?>

            <div class="sc-ocr-review-controls">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_v240_analyze_document_' . $document_id ); ?>
                    <input type="hidden" name="action" value="sc_library_v240_analyze_document">
                    <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
                    <button class="button"><?php esc_html_e( 'Reanalyze Page Text', 'sustainable-catalyst-library' ); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-ocr-queue-needs-form">
                    <?php wp_nonce_field( 'sc_library_v240_create_ocr_job_' . $document_id ); ?>
                    <input type="hidden" name="action" value="sc_library_v240_create_ocr_job">
                    <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
                    <input type="hidden" name="selection_mode" value="needs_ocr">
                    <?php $this->render_provider_controls( $providers, $selected_provider, $selected_language, $threshold ); ?>
                    <button class="button button-primary"><?php esc_html_e( 'Queue Pages Needing OCR', 'sustainable-catalyst-library' ); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-ocr-apply-form">
                    <?php wp_nonce_field( 'sc_library_v240_apply_ocr_' . $document_id ); ?>
                    <input type="hidden" name="action" value="sc_library_v240_apply_ocr">
                    <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
                    <label><input type="checkbox" name="allow_unreviewed" value="1"> <?php esc_html_e( 'Allow completed but unreviewed OCR pages', 'sustainable-catalyst-library' ); ?></label>
                    <?php if ( 'publish' === $document_status ) : ?>
                        <label class="sc-ocr-published-confirmation"><input type="checkbox" name="confirm_published_draft" value="1" required> <?php esc_html_e( 'Return this published document to Draft so the rebuilt reading layer can be reviewed before republishing.', 'sustainable-catalyst-library' ); ?></label>
                    <?php endif; ?>
                    <button class="button"><?php esc_html_e( 'Apply Reviewed OCR to Document', 'sustainable-catalyst-library' ); ?></button>
                </form>

                <?php if ( $has_backup ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'sc_library_v241_restore_ocr_backup_' . $document_id ); ?>
                        <input type="hidden" name="action" value="sc_library_v241_restore_ocr_backup">
                        <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
                        <button class="button"><?php esc_html_e( 'Restore Latest Pre-OCR Backup', 'sustainable-catalyst-library' ); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="sc-ocr-review-layout">
                <section class="sc-ocr-review-pdf" aria-labelledby="sc-ocr-pdf-title">
                    <h2 id="sc-ocr-pdf-title"><?php echo esc_html( sprintf( __( 'Original PDF — page %d', 'sustainable-catalyst-library' ), $current_page ) ); ?></h2>
                    <?php if ( $pdf_url ) : ?>
                        <iframe title="<?php echo esc_attr( sprintf( __( 'Original PDF page %d', 'sustainable-catalyst-library' ), $current_page ) ); ?>" src="<?php echo esc_url( $pdf_url . '#page=' . $current_page . '&zoom=page-width' ); ?>"></iframe>
                    <?php else : ?>
                        <div class="sc-document-library__empty"><strong><?php esc_html_e( 'Original PDF unavailable.', 'sustainable-catalyst-library' ); ?></strong></div>
                    <?php endif; ?>
                </section>

                <section class="sc-ocr-review-text" aria-labelledby="sc-ocr-text-title">
                    <h2 id="sc-ocr-text-title"><?php echo esc_html( sprintf( __( 'Readable text — page %d', 'sustainable-catalyst-library' ), $current_page ) ); ?></h2>
                    <?php $this->render_page_review_form( $document_id, $current_page, $current ); ?>
                </section>
            </div>

            <section class="sc-ocr-page-inventory" aria-labelledby="sc-ocr-page-inventory-title">
                <div class="sc-ocr-page-inventory__heading">
                    <div><p class="sc-pdf-bulk-kicker"><?php esc_html_e( 'Page Inventory', 'sustainable-catalyst-library' ); ?></p><h2 id="sc-ocr-page-inventory-title"><?php esc_html_e( 'Select and reprocess pages', 'sustainable-catalyst-library' ); ?></h2></div>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_library_v240_create_ocr_job_' . $document_id ); ?>
                    <input type="hidden" name="action" value="sc_library_v240_create_ocr_job">
                    <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
                    <input type="hidden" name="selection_mode" value="selected">
                    <div class="sc-ocr-page-inventory__toolbar">
                        <?php $this->render_provider_controls( $providers, $selected_provider, $selected_language, $threshold ); ?>
                        <button class="button button-primary"><?php esc_html_e( 'Queue Selected Pages', 'sustainable-catalyst-library' ); ?></button>
                    </div>
                    <table class="widefat striped">
                        <thead><tr><td class="check-column"><input type="checkbox" data-sc-select-all></td><th><?php esc_html_e( 'Page', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Source text', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'OCR status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Confidence', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Language', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Message', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Review', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $pages as $page_number => $page ) : ?>
                            <tr class="<?php echo $page_number === $current_page ? 'is-current' : ''; ?>">
                                <th class="check-column"><input type="checkbox" name="page_numbers[]" value="<?php echo esc_attr( $page_number ); ?>"></th>
                                <td><strong><?php echo esc_html( $page_number ); ?></strong></td>
                                <td><?php echo esc_html( sprintf( _n( '%d character', '%d characters', absint( $page['source_chars'] ?? 0 ), 'sustainable-catalyst-library' ), absint( $page['source_chars'] ?? 0 ) ) ); ?></td>
                                <td><strong class="sc-ocr-state is-<?php echo esc_attr( $page['status'] ?? 'needs_ocr' ); ?>"><?php echo esc_html( $this->page_status_label( $page['status'] ?? '' ) ); ?></strong></td>
                                <td><?php echo ! empty( $page['confidence'] ) ? esc_html( number_format_i18n( (float) $page['confidence'], 1 ) . '%' ) : '—'; ?></td>
                                <td><?php echo esc_html( $page['language'] ?? '—' ); ?></td>
                                <td><?php echo esc_html( $page['message'] ?? '' ); ?></td>
                                <td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sc-library-ocr-review', 'document_id' => $document_id, 'ocr_review_page' => $page_number ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Review page', 'sustainable-catalyst-library' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </section>
        </div>
        <?php
    }

    private function render_provider_controls( $providers, $selected_provider, $selected_language, $threshold ) {
        ?>
        <label><span><?php esc_html_e( 'Provider', 'sustainable-catalyst-library' ); ?></span>
            <select name="ocr_provider">
                <?php foreach ( $providers as $provider_id => $provider ) : ?><option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( $selected_provider, $provider_id ); ?> <?php disabled( empty( $provider['available'] ) ); ?>><?php echo esc_html( $provider['name'] . ( empty( $provider['available'] ) ? ' — unavailable' : '' ) ); ?></option><?php endforeach; ?>
            </select>
        </label>
        <label><span><?php esc_html_e( 'OCR language', 'sustainable-catalyst-library' ); ?></span><input type="text" name="ocr_language" value="<?php echo esc_attr( $selected_language ); ?>" placeholder="eng"></label>
        <label><span><?php esc_html_e( 'Low-confidence threshold', 'sustainable-catalyst-library' ); ?></span><input type="number" name="ocr_confidence_threshold" min="0" max="100" step="1" value="<?php echo esc_attr( $threshold ); ?>"></label>
        <?php
    }

    private function render_page_review_form( $document_id, $page_number, $page ) {
        $resolved = (string) ( $page['corrected_text'] ?? '' );
        if ( '' === trim( $resolved ) ) {
            $resolved = (string) ( $page['ocr_text'] ?? '' );
        }
        ?>
        <div class="sc-ocr-page-status">
            <span class="sc-ocr-state is-<?php echo esc_attr( $page['status'] ?? 'needs_ocr' ); ?>"><?php echo esc_html( $this->page_status_label( $page['status'] ?? '' ) ); ?></span>
            <?php if ( ! empty( $page['confidence'] ) ) : ?><span><?php echo esc_html( sprintf( __( '%s confidence', 'sustainable-catalyst-library' ), number_format_i18n( (float) $page['confidence'], 1 ) . '%' ) ); ?></span><?php endif; ?>
            <?php if ( ! empty( $page['language'] ) ) : ?><span><?php echo esc_html( sprintf( __( 'Language: %s', 'sustainable-catalyst-library' ), $page['language'] ) ); ?></span><?php endif; ?>
            <?php if ( ! empty( $page['provider'] ) ) : ?><span><?php echo esc_html( sprintf( __( 'Provider: %s', 'sustainable-catalyst-library' ), $page['provider'] ) ); ?></span><?php endif; ?>
        </div>
        <details class="sc-ocr-source-text"><summary><?php esc_html_e( 'View original extracted text', 'sustainable-catalyst-library' ); ?></summary><pre><?php echo esc_html( $page['source_text'] ?? '' ); ?></pre></details>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sc_library_v240_save_page_review_' . $document_id . '_' . $page_number ); ?>
            <input type="hidden" name="action" value="sc_library_v240_save_page_review">
            <input type="hidden" name="document_id" value="<?php echo esc_attr( $document_id ); ?>">
            <input type="hidden" name="page_number" value="<?php echo esc_attr( $page_number ); ?>">
            <label for="sc-ocr-corrected-text"><strong><?php esc_html_e( 'Reviewed OCR text', 'sustainable-catalyst-library' ); ?></strong></label>
            <textarea id="sc-ocr-corrected-text" name="corrected_text" rows="24"><?php echo esc_textarea( $resolved ); ?></textarea>
            <label class="sc-ocr-review-confirmation"><input type="checkbox" name="mark_reviewed" value="1" <?php checked( 'reviewed', $page['status'] ?? '' ); ?>> <?php esc_html_e( 'I compared this page with the original PDF and reviewed the text.', 'sustainable-catalyst-library' ); ?></label>
            <p><button class="button button-primary"><?php esc_html_e( 'Save Page Review', 'sustainable-catalyst-library' ); ?></button></p>
        </form>
        <?php
    }

    private function render_queue_tab( $job_id ) {
        $jobs = get_posts( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'posts_per_page' => self::JOB_LIMIT, 'orderby' => 'date', 'order' => 'DESC' ) );
        if ( ! $job_id && $jobs ) {
            $job_id = $jobs[0]->ID;
        }
        $job = $job_id ? get_post( $job_id ) : null;
        $state = $job ? $this->job_state( $job_id ) : array();
        ?>
        <section class="sc-ocr-section">
            <div class="sc-pdf-job-layout">
                <aside class="sc-pdf-job-list">
                    <h2><?php esc_html_e( 'Recent OCR jobs', 'sustainable-catalyst-library' ); ?></h2>
                    <?php if ( $jobs ) : ?><ul><?php foreach ( $jobs as $entry ) : $entry_state = $this->job_state( $entry->ID ); ?><li class="<?php echo $entry->ID === $job_id ? 'is-current' : ''; ?>"><a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&tab=queue&job_id=' . $entry->ID ) ); ?>"><strong><?php echo esc_html( $entry->post_title ); ?></strong><span><?php echo esc_html( $this->job_status_label( $entry_state['status'] ) . ' · ' . $entry_state['processed'] . '/' . $entry_state['total'] ); ?></span></a></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'No OCR jobs yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </aside>
                <div class="sc-pdf-job-detail">
                    <?php if ( $job ) : ?>
                        <div class="sc-pdf-job-header" data-sc-ocr-job data-job-id="<?php echo esc_attr( $job_id ); ?>" data-job-status="<?php echo esc_attr( $state['status'] ); ?>">
                            <div><p class="sc-pdf-bulk-kicker"><?php esc_html_e( 'OCR Queue', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $job->post_title ); ?></h2><p data-sc-ocr-job-message><?php echo esc_html( $this->job_status_message( $state ) ); ?></p></div>
                            <div class="sc-pdf-job-controls">
                                <button type="button" class="button" data-sc-ocr-control="pause" <?php disabled( 'running' !== $state['status'] ); ?>><?php esc_html_e( 'Pause', 'sustainable-catalyst-library' ); ?></button>
                                <button type="button" class="button button-primary" data-sc-ocr-control="resume" <?php disabled( ! in_array( $state['status'], array( 'paused', 'complete_with_errors' ), true ) ); ?>><?php esc_html_e( 'Resume', 'sustainable-catalyst-library' ); ?></button>
                                <button type="button" class="button" data-sc-ocr-control="retry" <?php disabled( ! $state['failed'] ); ?>><?php esc_html_e( 'Retry Failed', 'sustainable-catalyst-library' ); ?></button>
                                <button type="button" class="button" data-sc-ocr-control="cancel" <?php disabled( in_array( $state['status'], array( 'complete', 'cancelled' ), true ) ); ?>><?php esc_html_e( 'Cancel', 'sustainable-catalyst-library' ); ?></button>
                                <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_v240_export_ocr_job', 'job_id' => $job_id ), admin_url( 'admin-post.php' ) ), 'sc_library_v240_export_ocr_job_' . $job_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'sustainable-catalyst-library' ); ?></a>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-ocr-inline-form">
                                    <?php wp_nonce_field( 'sc_library_v241_repair_ocr_job_' . $job_id ); ?>
                                    <input type="hidden" name="action" value="sc_library_v241_repair_ocr_job">
                                    <input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">
                                    <button class="button"><?php esc_html_e( 'Repair Queue State', 'sustainable-catalyst-library' ); ?></button>
                                </form>
                            </div>
                            <progress value="<?php echo esc_attr( $state['processed'] ); ?>" max="<?php echo esc_attr( max( 1, $state['total'] ) ); ?>" data-sc-ocr-progress></progress>
                            <div class="sc-pdf-job-stats"><span><strong data-sc-ocr-done><?php echo esc_html( $state['done'] ); ?></strong> <?php esc_html_e( 'complete', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-ocr-queued><?php echo esc_html( $state['queued'] ); ?></strong> <?php esc_html_e( 'queued', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-ocr-failed><?php echo esc_html( $state['failed'] ); ?></strong> <?php esc_html_e( 'attention', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-ocr-reviewed><?php echo esc_html( $state['reviewed'] ); ?></strong> <?php esc_html_e( 'reviewed', 'sustainable-catalyst-library' ); ?></span></div>
                        </div>
                        <table class="widefat striped sc-ocr-job-items">
                            <thead><tr><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Page', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Provider', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'State', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Confidence', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Attempts', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Message', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                            <tbody data-sc-ocr-items>
                            <?php foreach ( $state['items'] as $index => $item ) : ?>
                                <tr data-item-index="<?php echo esc_attr( $index ); ?>"><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&document_id=' . absint( $item['document_id'] ?? 0 ) . '&ocr_review_page=' . absint( $item['page'] ?? 1 ) ) ); ?>"><?php echo esc_html( get_the_title( absint( $item['document_id'] ?? 0 ) ) ?: sprintf( __( 'Document %d', 'sustainable-catalyst-library' ), absint( $item['document_id'] ?? 0 ) ) ); ?></a></td><td><?php echo esc_html( absint( $item['page'] ?? 0 ) ); ?></td><td><?php echo esc_html( $item['provider'] ?? '' ); ?></td><td data-item-state><?php echo esc_html( $this->page_status_label( $item['state'] ?? '' ) ); ?></td><td data-item-confidence><?php echo ! empty( $item['confidence'] ) ? esc_html( number_format_i18n( (float) $item['confidence'], 1 ) . '%' ) : '—'; ?></td><td><?php echo esc_html( absint( $item['attempts'] ?? 0 ) ); ?></td><td data-item-message><?php echo esc_html( $item['message'] ?? '' ); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="sc-document-library__empty"><strong><?php esc_html_e( 'No OCR queue selected.', 'sustainable-catalyst-library' ); ?></strong><p><?php esc_html_e( 'Open a document in OCR Review and queue selected pages.', 'sustainable-catalyst-library' ); ?></p></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function render_providers_tab() {
        $providers = $this->providers();
        $local = $this->local_provider_status();
        ?>
        <section class="sc-ocr-section">
            <div class="sc-ocr-provider-intro">
                <h2><?php esc_html_e( 'OCR provider status', 'sustainable-catalyst-library' ); ?></h2>
                <p><?php esc_html_e( 'The plugin does not bundle an OCR model. It can use free local Tesseract binaries, a configured external endpoint, or a custom WordPress provider.', 'sustainable-catalyst-library' ); ?></p>
            </div>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Provider', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Available', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Description', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Languages', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody>
            <?php foreach ( $providers as $provider ) : ?><tr><td><strong><?php echo esc_html( $provider['name'] ); ?></strong></td><td><?php echo ! empty( $provider['available'] ) ? esc_html__( 'Yes', 'sustainable-catalyst-library' ) : esc_html__( 'No', 'sustainable-catalyst-library' ); ?></td><td><?php echo esc_html( $provider['description'] ?? '' ); ?></td><td><?php echo esc_html( ! empty( $provider['languages'] ) ? implode( ', ', array_slice( $provider['languages'], 0, 30 ) ) : __( 'Provider-defined', 'sustainable-catalyst-library' ) ); ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <h2><?php esc_html_e( 'Local provider diagnostics', 'sustainable-catalyst-library' ); ?></h2>
            <table class="widefat striped sc-ocr-provider-diagnostics"><tbody>
                <tr><th><?php esc_html_e( 'Tesseract', 'sustainable-catalyst-library' ); ?></th><td><code><?php echo esc_html( $local['tesseract'] ?: __( 'Not found', 'sustainable-catalyst-library' ) ); ?></code></td></tr>
                <tr><th><?php esc_html_e( 'PDF rasterizer', 'sustainable-catalyst-library' ); ?></th><td><code><?php echo esc_html( $local['rasterizer'] ?: __( 'Not found', 'sustainable-catalyst-library' ) ); ?></code></td></tr>
                <tr><th><?php esc_html_e( 'External endpoint', 'sustainable-catalyst-library' ); ?></th><td><code><?php echo defined( 'SC_LIBRARY_OCR_ENDPOINT' ) && SC_LIBRARY_OCR_ENDPOINT ? esc_html( SC_LIBRARY_OCR_ENDPOINT ) : esc_html__( 'Not configured', 'sustainable-catalyst-library' ); ?></code></td></tr>
                <tr><th><?php esc_html_e( 'External API key', 'sustainable-catalyst-library' ); ?></th><td><?php echo defined( 'SC_LIBRARY_OCR_API_KEY' ) && SC_LIBRARY_OCR_API_KEY ? esc_html__( 'Configured', 'sustainable-catalyst-library' ) : esc_html__( 'Not configured', 'sustainable-catalyst-library' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Lease timeout', 'sustainable-catalyst-library' ); ?></th><td><?php echo esc_html( SC_Library_Document_OCR_Reliability::LEASE_SECONDS ); ?> <?php esc_html_e( 'seconds', 'sustainable-catalyst-library' ); ?></td></tr>
            </tbody></table>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-ocr-provider-maintenance">
                <?php wp_nonce_field( 'sc_library_v241_cleanup_ocr_temp' ); ?>
                <input type="hidden" name="action" value="sc_library_v241_cleanup_ocr_temp">
                <button class="button"><?php esc_html_e( 'Clean Expired OCR Temporary Files', 'sustainable-catalyst-library' ); ?></button>
            </form>

            <h2><?php esc_html_e( 'External provider contract', 'sustainable-catalyst-library' ); ?></h2>
            <p><?php esc_html_e( 'Define both SC_LIBRARY_OCR_ENDPOINT and SC_LIBRARY_OCR_API_KEY. v2.4.1 requires signed HTTPS requests unless a development-only filter explicitly permits HTTP.', 'sustainable-catalyst-library' ); ?></p>
            <pre>{
  "text": "Recognized page text",
  "confidence": 91.4,
  "language": "eng",
  "provider": "example-ocr",
  "warnings": []
}</pre>

            <h2><?php esc_html_e( 'WordPress integration hooks', 'sustainable-catalyst-library' ); ?></h2>
            <pre>sc_library_ocr_providers
sc_library_ocr_process_page</pre>
        </section>
        <?php
    }

    public function watch_extraction_status( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( self::META_EXTRACTION_STATUS !== $meta_key || self::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }
        if ( in_array( sanitize_key( (string) $meta_value ), array( 'needs_ocr', 'ready_review', 'legacy_content' ), true ) ) {
            $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $post_id );
            if ( is_wp_error( $source_state ) ) {
                return;
            }
            $this->analyze_document( $post_id, false );
        }
    }

    public function admin_analyze_document() {
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to analyze this document.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_analyze_document_' . $document_id );
        $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $document_id );
        if ( is_wp_error( $source_state ) ) {
            $this->redirect_document( $document_id, $source_state->get_error_code() );
        }
        $this->analyze_document( $document_id, true );
        $this->redirect_document( $document_id, 'analyzed' );
    }

    public function admin_create_ocr_job() {
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to queue OCR for this document.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_create_ocr_job_' . $document_id );

        $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $document_id );
        if ( is_wp_error( $source_state ) ) {
            $this->redirect_document( $document_id, $source_state->get_error_code() );
        }

        $pages = $this->pages( $document_id );
        if ( ! $pages ) {
            $pages = $this->analyze_document( $document_id, true );
        }

        $selection_mode = sanitize_key( wp_unslash( $_POST['selection_mode'] ?? 'selected' ) );
        $selected = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['page_numbers'] ?? array() ) ) ) ) );
        if ( 'needs_ocr' === $selection_mode ) {
            $selected = array();
            foreach ( $pages as $page_number => $page ) {
                if ( in_array( $page['status'] ?? '', array( 'needs_ocr', 'failed', 'unavailable', 'low_confidence' ), true ) ) {
                    $selected[] = absint( $page_number );
                }
            }
        }
        if ( ! $selected ) {
            $this->redirect_document( $document_id, 'no_pages' );
        }

        $providers = $this->providers();
        $provider = sanitize_key( wp_unslash( $_POST['ocr_provider'] ?? '' ) );
        if ( ! isset( $providers[ $provider ] ) || empty( $providers[ $provider ]['available'] ) ) {
            $this->redirect_document( $document_id, 'provider_unavailable' );
        }

        $language = sanitize_text_field( wp_unslash( $_POST['ocr_language'] ?? 'eng' ) ) ?: 'eng';
        $threshold = max( 0, min( 100, absint( wp_unslash( $_POST['ocr_confidence_threshold'] ?? self::DEFAULT_CONFIDENCE_THRESHOLD ) ) ) );
        $queue_validation = SC_Library_Document_OCR_Reliability::validate_queue_request( $document_id, $provider, $language, $providers );
        if ( is_wp_error( $queue_validation ) ) {
            $this->redirect_document( $document_id, $queue_validation->get_error_code() );
        }

        update_post_meta( $document_id, self::META_PROVIDER, $provider );
        update_post_meta( $document_id, self::META_LANGUAGE, $language );
        update_post_meta( $document_id, self::META_CONFIDENCE_THRESHOLD, $threshold );

        $items = array();
        foreach ( $selected as $page_number ) {
            if ( ! isset( $pages[ $page_number ] ) ) {
                continue;
            }
            $pages[ $page_number ]['status'] = 'queued';
            $pages[ $page_number ]['provider'] = $provider;
            $pages[ $page_number ]['message'] = __( 'Queued for OCR.', 'sustainable-catalyst-library' );
            $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
            $items[] = array(
                'document_id' => $document_id,
                'document_title' => get_the_title( $document_id ),
                'page'        => $page_number,
                'provider'    => $provider,
                'language'    => $language,
                'threshold'   => $threshold,
                'source_checksum' => sanitize_text_field( get_post_meta( $document_id, SC_Library_Document_OCR_Reliability::SOURCE_CHECKSUM_META, true ) ),
                'state'       => 'queued',
                'confidence'  => 0,
                'attempts'    => 0,
                'message'     => __( 'Queued for OCR.', 'sustainable-catalyst-library' ),
                'updated_at'  => current_time( 'mysql' ),
            );
        }
        if ( ! $items ) {
            $this->redirect_document( $document_id, 'no_pages' );
        }

        $this->save_pages( $document_id, $pages );
        $job_id = $this->create_job(
            sprintf( __( 'OCR · %1$s · %2$s', 'sustainable-catalyst-library' ), get_the_title( $document_id ), current_time( 'Y-m-d H:i' ) ),
            $items,
            array(
                'document_id' => $document_id,
                'provider'    => $provider,
                'language'    => $language,
                'threshold'   => $threshold,
            )
        );
        update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
        update_post_meta( $document_id, self::META_STATUS, 'queued' );
        $this->log( $document_id, 'job-created', sprintf( __( 'OCR job %d created with %d pages.', 'sustainable-catalyst-library' ), $job_id, count( $items ) ) );

        wp_safe_redirect( admin_url( 'admin.php?page=sc-library-ocr-review&tab=queue&job_id=' . $job_id . '&sc_v240_notice=job_created' ) );
        exit;
    }

    public function admin_save_page_review() {
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        $page_number = absint( wp_unslash( $_POST['page_number'] ?? 0 ) );
        if ( ! $document_id || ! $page_number || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to review this OCR page.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_save_page_review_' . $document_id . '_' . $page_number );

        $pages = $this->pages( $document_id );
        if ( ! isset( $pages[ $page_number ] ) ) {
            wp_die( esc_html__( 'The OCR page record could not be found.', 'sustainable-catalyst-library' ) );
        }

        $corrected = $this->normalize_text( wp_unslash( $_POST['corrected_text'] ?? '' ) );
        $pages[ $page_number ]['corrected_text'] = $corrected;
        $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );

        if ( isset( $_POST['mark_reviewed'] ) ) {
            if ( '' === trim( $corrected ) ) {
                $this->redirect_document( $document_id, 'empty_review', $page_number );
            }
            $pages[ $page_number ]['status'] = 'reviewed';
            $pages[ $page_number ]['reviewed_at'] = current_time( 'mysql' );
            $pages[ $page_number ]['reviewed_by'] = get_current_user_id();
            $pages[ $page_number ]['message'] = __( 'OCR text reviewed against the original PDF.', 'sustainable-catalyst-library' );
        } elseif ( in_array( $pages[ $page_number ]['status'] ?? '', array( 'reviewed', 'not_needed' ), true ) ) {
            $confidence = (float) ( $pages[ $page_number ]['confidence'] ?? 0 );
            $pages[ $page_number ]['status'] = $confidence && $confidence < $this->confidence_threshold( $document_id ) ? 'low_confidence' : 'complete';
            unset( $pages[ $page_number ]['reviewed_at'], $pages[ $page_number ]['reviewed_by'] );
        }

        $this->save_pages( $document_id, $pages );
        $this->log( $document_id, 'page-reviewed', sprintf( __( 'Page %d review saved.', 'sustainable-catalyst-library' ), $page_number ) );
        $this->redirect_document( $document_id, 'page_saved', $page_number );
    }

    public function admin_apply_ocr() {
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to apply OCR to this document.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_apply_ocr_' . $document_id );

        $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $document_id );
        if ( is_wp_error( $source_state ) ) {
            $this->redirect_document( $document_id, $source_state->get_error_code() );
        }
        if ( SC_Library_Document_OCR_Reliability::has_active_pages( $document_id ) ) {
            $this->redirect_document( $document_id, 'active_ocr_job' );
        }
        if ( 'publish' === get_post_status( $document_id ) && empty( $_POST['confirm_published_draft'] ) ) {
            $this->redirect_document( $document_id, 'published_confirmation_required' );
        }

        $snapshot = SC_Library_Document_OCR_Reliability::snapshot_document( $document_id );
        if ( is_wp_error( $snapshot ) ) {
            $this->redirect_document( $document_id, $snapshot->get_error_code() );
        }

        $result = $this->apply_ocr_to_document( $document_id, isset( $_POST['allow_unreviewed'] ) );
        $this->redirect_document( $document_id, is_wp_error( $result ) ? $result->get_error_code() : 'ocr_applied' );
    }

    public function ajax_job_status() {
        $job_id = $this->verify_job_ajax();
        wp_send_json_success( $this->job_state( $job_id ) );
    }

    public function ajax_next_item() {
        $job_id = $this->verify_job_ajax();
        $status = sanitize_key( (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) );
        if ( 'running' !== $status ) {
            wp_send_json_success( array( 'status' => $status, 'item' => null, 'job' => $this->job_state( $job_id ) ) );
        }

        $items = $this->job_items( $job_id );
        $now = time();
        $user_id = get_current_user_id();
        $client_id = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
        $selected = null;

        if ( $client_id ) {
            foreach ( $items as $index => $item ) {
                $lock_time = absint( $item['lock_time'] ?? 0 );
                if (
                    'processing' === sanitize_key( $item['state'] ?? '' )
                    && ! empty( $item['lock_client'] )
                    && hash_equals( (string) $item['lock_client'], $client_id )
                    && $lock_time
                    && $now - $lock_time <= SC_Library_Document_OCR_Reliability::LEASE_SECONDS
                ) {
                    $selected = array_merge( $item, array( 'index' => $index ) );
                    break;
                }
            }
        }

        foreach ( $items as $index => &$item ) {
            if ( null !== $selected ) {
                break;
            }
            $state = sanitize_key( $item['state'] ?? '' );
            $lock_time = absint( $item['lock_time'] ?? 0 );
            $reclaimable = 'processing' === $state && ( ! $lock_time || $now - $lock_time > SC_Library_Document_OCR_Reliability::LEASE_SECONDS );
            if ( 'queued' === $state || $reclaimable ) {
                $item['state'] = 'processing';
                $item['lock_user'] = $user_id;
                $item['lock_client'] = $client_id ?: wp_generate_password( 24, false, false );
                $item['lock_time'] = $now;
                $item['lock_token'] = wp_generate_password( 40, false, false );
                $item['message'] = $reclaimable
                    ? __( 'Recovered an expired OCR processing lease.', 'sustainable-catalyst-library' )
                    : __( 'OCR page lease acquired.', 'sustainable-catalyst-library' );
                $item['updated_at'] = current_time( 'mysql' );
                $selected = array_merge( $item, array( 'index' => $index ) );
                break;
            }
        }
        unset( $item );

        if ( null === $selected ) {
            $this->settle_job_status( $job_id, $items );
            wp_send_json_success( array( 'status' => get_post_meta( $job_id, self::META_JOB_STATUS, true ), 'item' => null, 'job' => $this->job_state( $job_id ) ) );
        }

        update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
        wp_send_json_success( array( 'status' => 'running', 'item' => $selected, 'job' => $this->job_state( $job_id ) ) );
    }

    public function ajax_process_item() {
        $job_id = $this->verify_job_ajax();
        $index = isset( $_POST['item_index'] ) ? intval( wp_unslash( $_POST['item_index'] ) ) : -1;
        $lock_token = sanitize_text_field( wp_unslash( $_POST['lock_token'] ?? '' ) );
        $items = $this->job_items( $job_id );
        if ( $index < 0 || ! isset( $items[ $index ] ) ) {
            wp_send_json_error( array( 'message' => __( 'The OCR queue item no longer exists.', 'sustainable-catalyst-library' ) ), 404 );
        }

        $item = $items[ $index ];
        $item_state = sanitize_key( $item['state'] ?? '' );
        if ( in_array( $item_state, array( 'complete', 'low_confidence', 'reviewed', 'failed', 'unavailable', 'cancelled' ), true ) ) {
            wp_send_json_success( $this->job_state( $job_id ) );
        }
        if ( 'processing' !== $item_state ) {
            wp_send_json_error( array( 'message' => __( 'The OCR queue item is not locked for processing.', 'sustainable-catalyst-library' ) ), 409 );
        }
        $client_id = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
        if (
            ! $lock_token
            || empty( $item['lock_token'] )
            || ! hash_equals( (string) $item['lock_token'], $lock_token )
            || ! $client_id
            || empty( $item['lock_client'] )
            || ! hash_equals( (string) $item['lock_client'], $client_id )
        ) {
            wp_send_json_error( array( 'message' => __( 'The OCR queue lease token is missing, expired, or owned by another queue runner.', 'sustainable-catalyst-library' ) ), 409 );
        }

        $document_id = absint( $item['document_id'] ?? 0 );
        $page_number = absint( $item['page'] ?? 0 );
        if ( ! $document_id || ! $page_number || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to process this OCR page.', 'sustainable-catalyst-library' ) ), 403 );
        }

        $source_state = SC_Library_Document_OCR_Reliability::ensure_source_current( $document_id );
        $expected_checksum = sanitize_text_field( $item['source_checksum'] ?? '' );
        if ( is_wp_error( $source_state ) || ( $expected_checksum && ! hash_equals( $expected_checksum, (string) ( $source_state['checksum'] ?? '' ) ) ) ) {
            $message = is_wp_error( $source_state )
                ? $source_state->get_error_message()
                : __( 'The attached PDF changed after this OCR job was queued.', 'sustainable-catalyst-library' );
            $items[ $index ]['state'] = 'failed';
            $items[ $index ]['message'] = $message;
            $items[ $index ]['updated_at'] = current_time( 'mysql' );
            unset( $items[ $index ]['lock_user'], $items[ $index ]['lock_client'], $items[ $index ]['lock_time'], $items[ $index ]['lock_token'] );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            $this->job_log( $job_id, 'source-changed', $message );
            $this->settle_job_status( $job_id, $items );
            wp_send_json_success( $this->job_state( $job_id ) );
        }

        $pages = $this->pages( $document_id );
        if ( ! isset( $pages[ $page_number ] ) ) {
            wp_send_json_error( array( 'message' => __( 'The OCR page record could not be found.', 'sustainable-catalyst-library' ) ), 404 );
        }

        $pages[ $page_number ]['status'] = 'processing';
        $pages[ $page_number ]['message'] = __( 'OCR processing started.', 'sustainable-catalyst-library' );
        $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
        $this->save_pages( $document_id, $pages );

        $result = $this->process_page(
            $document_id,
            $page_number,
            sanitize_key( $item['provider'] ?? '' ),
            sanitize_text_field( $item['language'] ?? 'eng' )
        );

        $attempts = absint( $item['attempts'] ?? 0 ) + 1;
        if ( 'cancelled' === sanitize_key( (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) ) ) {
            $items = $this->job_items( $job_id );
            if ( isset( $items[ $index ] ) ) {
                $items[ $index ]['state'] = 'cancelled';
                $items[ $index ]['message'] = __( 'OCR result discarded because the queue was cancelled.', 'sustainable-catalyst-library' );
                $items[ $index ]['attempts'] = $attempts;
                $items[ $index ]['updated_at'] = current_time( 'mysql' );
                unset( $items[ $index ]['lock_user'], $items[ $index ]['lock_client'], $items[ $index ]['lock_time'], $items[ $index ]['lock_token'] );
                update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            }
            $pages = $this->pages( $document_id );
            if ( isset( $pages[ $page_number ] ) ) {
                $pages[ $page_number ]['status'] = 'cancelled';
                $pages[ $page_number ]['message'] = __( 'OCR processing was cancelled.', 'sustainable-catalyst-library' );
                $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
                $this->save_pages( $document_id, $pages );
            }
            wp_send_json_success( $this->job_state( $job_id ) );
        }

        if ( is_wp_error( $result ) ) {
            $page_status = 'provider_unavailable' === $result->get_error_code() ? 'unavailable' : 'failed';
            $pages = $this->pages( $document_id );
            if ( isset( $pages[ $page_number ] ) ) {
                $pages[ $page_number ]['status'] = $page_status;
                $pages[ $page_number ]['message'] = $result->get_error_message();
                $pages[ $page_number ]['attempts'] = $attempts;
                $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
                $this->save_pages( $document_id, $pages );
            }

            $items[ $index ]['state'] = $page_status;
            $items[ $index ]['message'] = $result->get_error_message();
            $items[ $index ]['attempts'] = $attempts;
            $items[ $index ]['updated_at'] = current_time( 'mysql' );
            unset( $items[ $index ]['lock_user'], $items[ $index ]['lock_client'], $items[ $index ]['lock_time'], $items[ $index ]['lock_token'] );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            $this->job_log( $job_id, $page_status, sprintf( __( 'Page %1$d: %2$s', 'sustainable-catalyst-library' ), $page_number, $result->get_error_message() ) );
            $this->settle_job_status( $job_id, $items );
            wp_send_json_success( $this->job_state( $job_id ) );
        }

        $confidence = max( 0, min( 100, (float) ( $result['confidence'] ?? 0 ) ) );
        $threshold = max( 0, min( 100, absint( $item['threshold'] ?? self::DEFAULT_CONFIDENCE_THRESHOLD ) ) );
        $page_status = $confidence > 0 && $confidence < $threshold ? 'low_confidence' : 'complete';
        $message = 'low_confidence' === $page_status
            ? sprintf( __( 'OCR completed below the %d%% review threshold.', 'sustainable-catalyst-library' ), $threshold )
            : __( 'OCR completed. Review against the original PDF.', 'sustainable-catalyst-library' );

        $pages = $this->pages( $document_id );
        $pages[ $page_number ]['status'] = $page_status;
        $pages[ $page_number ]['ocr_text'] = $this->normalize_text( $result['text'] ?? '' );
        $pages[ $page_number ]['corrected_text'] = '';
        $pages[ $page_number ]['confidence'] = round( $confidence, 2 );
        $pages[ $page_number ]['language'] = sanitize_text_field( $result['language'] ?? $this->detect_language_hint( $result['text'] ?? '' ) );
        $pages[ $page_number ]['provider'] = sanitize_text_field( $result['provider'] ?? ( $item['provider'] ?? '' ) );
        $pages[ $page_number ]['warnings'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $result['warnings'] ?? array() ) ) ) );
        $pages[ $page_number ]['attempts'] = $attempts;
        $pages[ $page_number ]['message'] = $message;
        $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
        $this->save_pages( $document_id, $pages );

        $items[ $index ]['state'] = $page_status;
        $items[ $index ]['confidence'] = round( $confidence, 2 );
        $items[ $index ]['message'] = $message;
        $items[ $index ]['attempts'] = $attempts;
        $items[ $index ]['updated_at'] = current_time( 'mysql' );
        unset( $items[ $index ]['lock_user'], $items[ $index ]['lock_client'], $items[ $index ]['lock_time'], $items[ $index ]['lock_token'] );
        update_post_meta( $job_id, self::META_JOB_ITEMS, $items );

        $this->job_log( $job_id, $page_status, sprintf( __( 'Page %1$d completed at %2$s%% confidence.', 'sustainable-catalyst-library' ), $page_number, number_format_i18n( $confidence, 1 ) ) );
        $this->log( $document_id, $page_status, sprintf( __( 'Page %1$d OCR completed at %2$s%% confidence.', 'sustainable-catalyst-library' ), $page_number, number_format_i18n( $confidence, 1 ) ) );
        $this->settle_job_status( $job_id, $items );
        wp_send_json_success( $this->job_state( $job_id ) );
    }

    public function ajax_control_job() {
        $job_id = $this->verify_job_ajax();
        $control = sanitize_key( wp_unslash( $_POST['control'] ?? '' ) );
        $items = $this->job_items( $job_id );

        if ( 'pause' === $control ) {
            update_post_meta( $job_id, self::META_JOB_STATUS, 'paused' );
            $this->job_log( $job_id, 'paused', __( 'OCR queue paused after the active page.', 'sustainable-catalyst-library' ) );
        } elseif ( 'resume' === $control ) {
            update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
            $this->job_log( $job_id, 'resumed', __( 'OCR queue resumed.', 'sustainable-catalyst-library' ) );
        } elseif ( 'retry' === $control ) {
            foreach ( $items as &$item ) {
                if ( in_array( $item['state'] ?? '', array( 'failed', 'unavailable', 'low_confidence' ), true ) ) {
                    $item['state'] = 'queued';
                    $item['message'] = __( 'Returned to OCR queue.', 'sustainable-catalyst-library' );
                    $this->sync_page_state_from_job_item( $item, 'queued', $item['message'] );
                    unset( $item['lock_user'], $item['lock_client'], $item['lock_time'], $item['lock_token'] );
                }
            }
            unset( $item );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
            $this->job_log( $job_id, 'retry', __( 'Attention pages returned to the queue.', 'sustainable-catalyst-library' ) );
        } elseif ( 'cancel' === $control ) {
            foreach ( $items as &$item ) {
                if ( in_array( $item['state'] ?? '', array( 'queued', 'processing' ), true ) ) {
                    $item['state'] = 'cancelled';
                    $item['message'] = __( 'Cancelled by an administrator.', 'sustainable-catalyst-library' );
                    $this->sync_page_state_from_job_item( $item, 'cancelled', $item['message'] );
                    unset( $item['lock_user'], $item['lock_client'], $item['lock_time'], $item['lock_token'] );
                }
            }
            unset( $item );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            update_post_meta( $job_id, self::META_JOB_STATUS, 'cancelled' );
            $this->job_log( $job_id, 'cancelled', __( 'OCR queue cancelled.', 'sustainable-catalyst-library' ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Unknown OCR queue control.', 'sustainable-catalyst-library' ) ), 400 );
        }

        wp_send_json_success( $this->job_state( $job_id ) );
    }

    private function analyze_document( $document_id, $force ) {
        $existing = $this->pages( $document_id );
        if ( $existing && ! $force ) {
            return $existing;
        }

        $raw = (string) get_post_meta( $document_id, self::META_RAW_TEXT, true );
        $page_map_raw = get_post_meta( $document_id, self::META_PAGE_MAP, true );
        $page_map = is_array( $page_map_raw ) ? $page_map_raw : json_decode( (string) $page_map_raw, true );
        $page_count = absint( get_post_meta( $document_id, self::META_PAGE_COUNT, true ) );
        $extraction_status = sanitize_key( (string) get_post_meta( $document_id, self::META_EXTRACTION_STATUS, true ) );
        $min_chars = max( 10, absint( apply_filters( 'sc_library_ocr_min_source_characters', self::DEFAULT_MIN_SOURCE_CHARACTERS, $document_id ) ) );
        $detected = array();

        if ( is_array( $page_map ) && $page_map ) {
            foreach ( $page_map as $index => $entry ) {
                $page_number = absint( $entry['page'] ?? $index + 1 );
                $start = absint( $entry['start'] ?? 0 );
                $length = absint( $entry['length'] ?? 0 );
                $source = $length ? substr( $raw, $start, $length ) : '';
                $source = $this->normalize_text( $source );
                $chars = $this->non_space_length( $source );
                $old = $existing[ $page_number ] ?? array();
                $status = $chars < $min_chars ? 'needs_ocr' : 'not_needed';
                if ( $this->has_ocr_result( $old ) ) {
                    $status = $old['status'] ?? $status;
                }
                $detected[ $page_number ] = array_merge(
                    array(
                        'page'           => $page_number,
                        'source_text'    => $source,
                        'source_chars'   => $chars,
                        'status'         => $status,
                        'ocr_text'       => '',
                        'corrected_text' => '',
                        'confidence'     => 0,
                        'language'       => '',
                        'provider'       => '',
                        'warnings'       => array(),
                        'attempts'       => 0,
                        'message'        => 'needs_ocr' === $status ? __( 'Too little extracted text was found on this page.', 'sustainable-catalyst-library' ) : __( 'Extracted text appears usable.', 'sustainable-catalyst-library' ),
                        'updated_at'     => current_time( 'mysql' ),
                    ),
                    $old,
                    array(
                        'page'         => $page_number,
                        'source_text'  => $source,
                        'source_chars' => $chars,
                    )
                );
            }
        } else {
            $page_count = max( 1, $page_count );
            for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
                $old = $existing[ $page_number ] ?? array();
                $source = 1 === $page_number ? $this->normalize_text( $raw ) : '';
                $chars = $this->non_space_length( $source );
                $status = ( 'needs_ocr' === $extraction_status || $chars < $min_chars ) ? 'needs_ocr' : 'not_needed';
                if ( $this->has_ocr_result( $old ) ) {
                    $status = $old['status'] ?? $status;
                }
                $detected[ $page_number ] = array_merge(
                    array(
                        'page'           => $page_number,
                        'source_text'    => $source,
                        'source_chars'   => $chars,
                        'status'         => $status,
                        'ocr_text'       => '',
                        'corrected_text' => '',
                        'confidence'     => 0,
                        'language'       => '',
                        'provider'       => '',
                        'warnings'       => array(),
                        'attempts'       => 0,
                        'message'        => 'needs_ocr' === $status ? __( 'No reliable extracted page text is available.', 'sustainable-catalyst-library' ) : __( 'Extracted text appears usable.', 'sustainable-catalyst-library' ),
                        'updated_at'     => current_time( 'mysql' ),
                    ),
                    $old,
                    array(
                        'page'         => $page_number,
                        'source_text'  => $source,
                        'source_chars' => $chars,
                    )
                );
            }
        }

        ksort( $detected );
        $this->save_pages( $document_id, $detected );
        $this->log( $document_id, 'analyzed', sprintf( __( 'Analyzed %d pages for OCR requirements.', 'sustainable-catalyst-library' ), count( $detected ) ) );
        return $detected;
    }

    private function process_page( $document_id, $page_number, $provider_id, $language ) {
        $context = $this->page_context( $document_id, $page_number, $language );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $filtered = apply_filters( 'sc_library_ocr_process_page', null, $context, $provider_id );
        if ( is_wp_error( $filtered ) ) {
            return $filtered;
        }
        if ( is_array( $filtered ) && isset( $filtered['text'] ) ) {
            return $this->validate_provider_result( $filtered, $provider_id );
        }
        if ( 'local_tesseract' === $provider_id ) {
            return $this->process_local_tesseract( $context );
        }
        if ( 'external_endpoint' === $provider_id ) {
            return $this->process_external_endpoint( $context );
        }
        return new WP_Error( 'provider_unavailable', __( 'The selected OCR provider is not available.', 'sustainable-catalyst-library' ) );
    }

    private function page_context( $document_id, $page_number, $language ) {
        $attachment_id = absint( get_post_meta( $document_id, self::META_PDF_ID, true ) );
        $file = $attachment_id ? get_attached_file( $attachment_id ) : '';
        $url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

        if ( ! $attachment_id || ! $file || ! is_file( $file ) || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
            return new WP_Error( 'missing_pdf', __( 'The original PDF is missing or invalid.', 'sustainable-catalyst-library' ) );
        }

        return array(
            'version'       => 'sc-library-ocr/1.0',
            'document_id'   => $document_id,
            'attachment_id' => $attachment_id,
            'page'          => $page_number,
            'language'      => $language ?: 'eng',
            'pdf_file'      => $file,
            'pdf_url'       => $url,
            'checksum'      => hash_file( 'sha256', $file ),
        );
    }

    private function process_local_tesseract( $context ) {
        $status = $this->local_provider_status();
        if ( ! $status['available'] ) {
            return new WP_Error( 'provider_unavailable', __( 'Local OCR requires Tesseract, pdftoppm or pdftocairo, and PHP process execution.', 'sustainable-catalyst-library' ) );
        }

        @set_time_limit( max( 120, absint( apply_filters( 'sc_library_ocr_page_time_limit', 240, $context ) ) ) );
        $uploads = wp_upload_dir();
        $base_dir = trailingslashit( $uploads['basedir'] ) . 'sc-library-ocr';
        wp_mkdir_p( $base_dir );
        $job_dir = trailingslashit( $base_dir ) . 'doc-' . absint( $context['document_id'] ) . '-page-' . absint( $context['page'] ) . '-' . wp_generate_password( 8, false, false );
        wp_mkdir_p( $job_dir );

        $prefix = trailingslashit( $job_dir ) . 'page';
        $dpi = max( 120, min( 400, absint( apply_filters( 'sc_library_ocr_raster_dpi', 220, $context ) ) ) );
        $page = absint( $context['page'] );
        $command = array(
            $status['rasterizer'],
            '-f', (string) $page,
            '-l', (string) $page,
            '-singlefile',
            '-png',
            '-r', (string) $dpi,
            $context['pdf_file'],
            $prefix,
        );

        $raster = $this->run_process( $command, null, 180 );
        if ( is_wp_error( $raster ) ) {
            $this->remove_directory( $job_dir );
            return $raster;
        }

        $image = $prefix . '.png';
        if ( ! is_file( $image ) ) {
            $matches = glob( $prefix . '*.png' );
            $image = $matches ? $matches[0] : '';
        }
        if ( ! $image || ! is_file( $image ) ) {
            $this->remove_directory( $job_dir );
            return new WP_Error( 'raster_failed', __( 'The PDF page could not be rasterized for OCR.', 'sustainable-catalyst-library' ) );
        }

        $max_image_bytes = absint( apply_filters( 'sc_library_ocr_max_page_image_bytes', 30 * 1024 * 1024, $context ) );
        if ( $max_image_bytes && filesize( $image ) > $max_image_bytes ) {
            $this->remove_directory( $job_dir );
            return new WP_Error( 'image_too_large', __( 'The rasterized page exceeds the configured OCR image limit.', 'sustainable-catalyst-library' ) );
        }

        $language = sanitize_text_field( $context['language'] ?: 'eng' );
        $ocr = $this->run_process(
            array( $status['tesseract'], $image, 'stdout', '-l', $language, '--psm', '6', 'tsv' ),
            null,
            180
        );
        if ( is_wp_error( $ocr ) ) {
            $this->remove_directory( $job_dir );
            return $ocr;
        }

        $result = $this->parse_tesseract_tsv( $ocr['stdout'] );
        $this->remove_directory( $job_dir );
        if ( '' === trim( $result['text'] ) ) {
            return new WP_Error( 'ocr_empty', __( 'Tesseract did not return readable text for this page.', 'sustainable-catalyst-library' ) );
        }

        $result['language'] = $this->detect_language_hint( $result['text'], $language );
        $result['provider'] = 'local_tesseract';
        $result['warnings'] = array();
        return $result;
    }

    private function process_external_endpoint( $context ) {
        if ( ! defined( 'SC_LIBRARY_OCR_ENDPOINT' ) || ! SC_LIBRARY_OCR_ENDPOINT ) {
            return new WP_Error( 'provider_unavailable', __( 'The external OCR endpoint is not configured.', 'sustainable-catalyst-library' ) );
        }

        $payload = array(
            'version'      => $context['version'],
            'document_id'  => $context['document_id'],
            'page'         => $context['page'],
            'language'     => $context['language'],
            'pdf_url'      => $context['pdf_url'],
            'checksum'     => $context['checksum'],
            'requested_at' => gmdate( 'c' ),
        );
        $secret = defined( 'SC_LIBRARY_OCR_API_KEY' ) && SC_LIBRARY_OCR_API_KEY ? SC_LIBRARY_OCR_API_KEY : wp_salt( 'auth' );
        $headers = array(
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'X-SC-OCR-Signature' => hash_hmac( 'sha256', wp_json_encode( $payload ), $secret ),
        );
        if ( defined( 'SC_LIBRARY_OCR_API_KEY' ) && SC_LIBRARY_OCR_API_KEY ) {
            $headers['Authorization'] = 'Bearer ' . SC_LIBRARY_OCR_API_KEY;
        }

        $response = wp_remote_post(
            SC_LIBRARY_OCR_ENDPOINT,
            array(
                'headers'     => $headers,
                'body'        => wp_json_encode( $payload ),
                'timeout'     => max( 30, absint( apply_filters( 'sc_library_ocr_external_timeout', 120, $context ) ) ),
                'redirection' => 2,
                'data_format' => 'body',
            )
        );
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'external_failed', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            return new WP_Error( 'external_failed', sprintf( __( 'External OCR returned HTTP %d or invalid JSON.', 'sustainable-catalyst-library' ), $code ) );
        }
        return $this->validate_provider_result( $body, 'external_endpoint' );
    }

    private function validate_provider_result( $result, $provider_id ) {
        $text = $this->normalize_text( $result['text'] ?? '' );
        if ( '' === trim( $text ) ) {
            return new WP_Error( 'ocr_empty', __( 'The OCR provider returned no readable text.', 'sustainable-catalyst-library' ) );
        }
        $max_chars = max( 1000, absint( apply_filters( 'sc_library_ocr_max_page_characters', 500000, $result, $provider_id ) ) );
        if ( strlen( $text ) > $max_chars ) {
            return new WP_Error( 'ocr_response_too_large', __( 'The OCR page text exceeded the configured size limit.', 'sustainable-catalyst-library' ) );
        }

        return array(
            'text'       => $text,
            'confidence' => max( 0, min( 100, (float) ( $result['confidence'] ?? 0 ) ) ),
            'language'   => sanitize_text_field( $result['language'] ?? $this->detect_language_hint( $text ) ),
            'provider'   => sanitize_text_field( $result['provider'] ?? $provider_id ),
            'warnings'   => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $result['warnings'] ?? array() ) ) ) ),
        );
    }

    private function parse_tesseract_tsv( $tsv ) {
        $lines = preg_split( '/\R/u', (string) $tsv );
        $groups = array();
        $confidence_total = 0.0;
        $confidence_weight = 0;

        foreach ( $lines as $index => $line ) {
            if ( 0 === $index || '' === trim( $line ) ) {
                continue;
            }
            $columns = str_getcsv( $line, "\t" );
            if ( count( $columns ) < 12 ) {
                continue;
            }

            $text = trim( (string) $columns[11] );
            $confidence = (float) $columns[10];
            if ( '' === $text ) {
                continue;
            }
            $key = implode( '-', array_map( 'absint', array_slice( $columns, 1, 4 ) ) );
            $groups[ $key ] = $groups[ $key ] ?? array();
            $groups[ $key ][] = $text;

            if ( $confidence >= 0 ) {
                $weight = max( 1, $this->non_space_length( $text ) );
                $confidence_total += $confidence * $weight;
                $confidence_weight += $weight;
            }
        }

        $paragraphs = array();
        foreach ( $groups as $words ) {
            $line = trim( preg_replace( '/\s+/u', ' ', implode( ' ', $words ) ) );
            if ( $line ) {
                $paragraphs[] = $line;
            }
        }

        return array(
            'text'       => implode( "\n", $paragraphs ),
            'confidence' => $confidence_weight ? $confidence_total / $confidence_weight : 0,
        );
    }

    private function run_process( $command, $input = null, $timeout = 180 ) {
        if ( ! function_exists( 'proc_open' ) ) {
            return new WP_Error( 'provider_unavailable', __( 'PHP process execution is unavailable.', 'sustainable-catalyst-library' ) );
        }

        $process = @proc_open(
            $command,
            array(
                0 => array( 'pipe', 'r' ),
                1 => array( 'pipe', 'w' ),
                2 => array( 'pipe', 'w' ),
            ),
            $pipes
        );
        if ( ! is_resource( $process ) ) {
            return new WP_Error( 'process_failed', __( 'The OCR process could not be started.', 'sustainable-catalyst-library' ) );
        }

        if ( null !== $input ) {
            fwrite( $pipes[0], (string) $input );
        }
        fclose( $pipes[0] );
        stream_set_blocking( $pipes[1], false );
        stream_set_blocking( $pipes[2], false );

        $stdout = '';
        $stderr = '';
        $started = time();
        $reported_exit = null;

        while ( true ) {
            $status = proc_get_status( $process );
            $stdout .= stream_get_contents( $pipes[1] );
            $stderr .= stream_get_contents( $pipes[2] );
            if ( ! $status['running'] ) {
                $reported_exit = isset( $status['exitcode'] ) ? (int) $status['exitcode'] : null;
                break;
            }
            if ( time() - $started > $timeout ) {
                proc_terminate( $process, 9 );
                fclose( $pipes[1] );
                fclose( $pipes[2] );
                proc_close( $process );
                return new WP_Error( 'process_timeout', __( 'The OCR page process timed out.', 'sustainable-catalyst-library' ) );
            }
            usleep( 100000 );
        }

        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $exit = proc_close( $process );
        if ( $exit < 0 && null !== $reported_exit ) {
            $exit = $reported_exit;
        }
        if ( 0 !== $exit ) {
            return new WP_Error( 'process_failed', sanitize_text_field( wp_trim_words( $stderr ?: __( 'OCR command failed.', 'sustainable-catalyst-library' ), 35, '…' ) ) );
        }
        return array( 'stdout' => $stdout, 'stderr' => $stderr );
    }

    private function providers() {
        $local = $this->local_provider_status();
        $providers = array(
            'local_tesseract' => array(
                'id'          => 'local_tesseract',
                'name'        => __( 'Local Tesseract OCR', 'sustainable-catalyst-library' ),
                'available'   => $local['available'],
                'description' => __( 'Free local OCR using Tesseract and Poppler page rasterization on the WordPress server.', 'sustainable-catalyst-library' ),
                'languages'   => $local['languages'],
            ),
            'external_endpoint' => array(
                'id'          => 'external_endpoint',
                'name'        => __( 'External OCR Endpoint', 'sustainable-catalyst-library' ),
                'available'   => defined( 'SC_LIBRARY_OCR_ENDPOINT' ) && (bool) SC_LIBRARY_OCR_ENDPOINT,
                'description' => __( 'Signed JSON page requests sent to an endpoint configured through WordPress constants.', 'sustainable-catalyst-library' ),
                'languages'   => array(),
            ),
        );

        $providers = apply_filters( 'sc_library_ocr_providers', $providers );
        foreach ( $providers as $provider_id => &$provider ) {
            $provider['id'] = sanitize_key( $provider['id'] ?? $provider_id );
            $provider['name'] = sanitize_text_field( $provider['name'] ?? $provider_id );
            $provider['available'] = ! empty( $provider['available'] );
            $provider['description'] = sanitize_text_field( $provider['description'] ?? '' );
            $provider['languages'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $provider['languages'] ?? array() ) ) ) );
        }
        unset( $provider );
        return $providers;
    }

    private function local_provider_status() {
        $cache_key = 'sc_library_v241_local_ocr_status';
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['available'], $cached['tesseract'], $cached['rasterizer'], $cached['languages'] ) ) {
            return $cached;
        }

        $tesseract = $this->find_binary( apply_filters( 'sc_library_tesseract_candidates', array( '/usr/bin/tesseract', '/usr/local/bin/tesseract', '/opt/homebrew/bin/tesseract' ) ) );
        $rasterizer = $this->find_binary( apply_filters( 'sc_library_pdf_rasterizer_candidates', array( '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm', '/opt/homebrew/bin/pdftoppm', '/usr/bin/pdftocairo', '/usr/local/bin/pdftocairo', '/opt/homebrew/bin/pdftocairo' ) ) );

        $languages = array();
        if ( $tesseract && function_exists( 'proc_open' ) ) {
            $result = $this->run_process( array( $tesseract, '--list-langs' ), null, 20 );
            if ( ! is_wp_error( $result ) ) {
                foreach ( array_slice( preg_split( '/\R/u', $result['stdout'] ), 1 ) as $line ) {
                    $line = sanitize_text_field( trim( $line ) );
                    if ( $line ) {
                        $languages[] = $line;
                    }
                }
            }
        }

        $status = array(
            'available'  => (bool) ( $tesseract && $rasterizer && function_exists( 'proc_open' ) ),
            'tesseract'  => $tesseract,
            'rasterizer' => $rasterizer,
            'languages'  => array_values( array_unique( $languages ) ),
        );
        set_transient( $cache_key, $status, 10 * MINUTE_IN_SECONDS );
        return $status;
    }

    private function find_binary( $candidates ) {
        foreach ( (array) $candidates as $candidate ) {
            if ( is_string( $candidate ) && is_file( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }
        return '';
    }

    private function apply_ocr_to_document( $document_id, $allow_unreviewed ) {
        $pages = $this->pages( $document_id );
        if ( ! $pages ) {
            return new WP_Error( 'no_ocr_pages', __( 'No page-level OCR records are available.', 'sustainable-catalyst-library' ) );
        }

        $assembled = array();
        $ocr_pages = 0;
        $warning_pages = array();

        foreach ( $pages as $page_number => $page ) {
            $status = sanitize_key( $page['status'] ?? '' );
            $source = $this->normalize_text( $page['source_text'] ?? '' );
            $corrected = $this->normalize_text( $page['corrected_text'] ?? '' );
            $ocr = $this->normalize_text( $page['ocr_text'] ?? '' );
            $text = $source;
            $used_ocr = false;

            if ( 'reviewed' === $status && $corrected ) {
                $text = $corrected;
                $used_ocr = true;
            } elseif ( $allow_unreviewed && in_array( $status, array( 'complete', 'low_confidence' ), true ) && $ocr ) {
                $text = $corrected ?: $ocr;
                $used_ocr = true;
                $warning_pages[] = $page_number;
            } elseif ( in_array( $status, array( 'needs_ocr', 'failed', 'unavailable', 'queued', 'processing' ), true ) && ! $source ) {
                $warning_pages[] = $page_number;
            }

            if ( $used_ocr ) {
                $ocr_pages++;
                if ( (float) ( $page['confidence'] ?? 0 ) < $this->confidence_threshold( $document_id ) && 'reviewed' !== $status ) {
                    $warning_pages[] = $page_number;
                }
            }
            $assembled[ $page_number ] = $text;
        }

        if ( 0 === $ocr_pages ) {
            return new WP_Error( 'no_reviewed_ocr', __( 'No reviewed OCR text is ready to apply. Review pages first or explicitly allow completed unreviewed pages.', 'sustainable-catalyst-library' ) );
        }

        ksort( $assembled );
        $html = '';
        $raw = '';
        $map = array();
        $offset = 0;

        foreach ( $assembled as $page_number => $text ) {
            $text = $this->normalize_text( $text );
            $html .= '<section class="sc-pdf-document-page sc-pdf-document-page--ocr" data-pdf-page="' . esc_attr( $page_number ) . '">';
            $html .= '<p class="sc-pdf-document-page__number">' . esc_html( sprintf( __( 'Page %d', 'sustainable-catalyst-library' ), $page_number ) ) . '</p>';
            foreach ( preg_split( '/\n{2,}/u', $text ) as $paragraph ) {
                $paragraph = trim( preg_replace( '/\s+/u', ' ', $paragraph ) );
                if ( $paragraph ) {
                    $html .= '<p>' . esc_html( $paragraph ) . '</p>';
                }
            }
            $html .= '</section>';

            $map[] = array( 'page' => $page_number, 'start' => $offset, 'length' => strlen( $text ) );
            $raw .= ( '' === $raw ? '' : "\n\n" ) . $text;
            $offset += strlen( $text ) + 2;
        }

        $updated = wp_update_post(
            wp_slash(
                array(
                    'ID'           => $document_id,
                    'post_content' => $html,
                    'post_excerpt' => wp_trim_words( trim( preg_replace( '/\s+/u', ' ', $raw ) ), 45, '…' ),
                    'post_status'  => 'publish' === get_post_status( $document_id ) ? 'draft' : get_post_status( $document_id ),
                )
            ),
            true
        );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        update_post_meta( $document_id, self::META_RAW_TEXT, $raw );
        update_post_meta( $document_id, self::META_PAGE_MAP, wp_json_encode( $map ) );
        update_post_meta( $document_id, self::META_EXTRACTION_STATUS, 'ready_review' );
        update_post_meta( $document_id, self::META_DOCUMENT_REVIEWED, 0 );
        update_post_meta( $document_id, self::META_APPLIED_AT, current_time( 'mysql' ) );
        update_post_meta( $document_id, self::META_APPLIED_BY, get_current_user_id() );
        update_post_meta( $document_id, self::META_STATUS, $warning_pages ? 'applied_with_warnings' : 'applied' );
        update_post_meta(
            $document_id,
            self::META_PUBLIC_WARNING,
            array(
                'active'        => 1,
                'ocr_pages'     => $ocr_pages,
                'warning_pages' => array_values( array_unique( array_map( 'absint', $warning_pages ) ) ),
                'message'       => $warning_pages
                    ? __( 'This readable document includes OCR-derived text with pages requiring additional verification. Consult the original PDF for authoritative wording.', 'sustainable-catalyst-library' )
                    : __( 'This readable document includes reviewed OCR-derived text. The original PDF remains the authoritative source.', 'sustainable-catalyst-library' ),
            )
        );

        $this->log( $document_id, 'applied', sprintf( __( 'Applied reviewed OCR from %d pages to the readable document.', 'sustainable-catalyst-library' ), $ocr_pages ) );
        return true;
    }

    public function public_ocr_notice( $content ) {
        if ( is_admin() || ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $warning = get_post_meta( get_the_ID(), self::META_PUBLIC_WARNING, true );
        if ( ! is_array( $warning ) || empty( $warning['active'] ) ) {
            return $content;
        }

        $notice = '<aside class="sc-pdf-document__ocr-notice" role="note"><strong>' . esc_html__( 'OCR-derived reading layer', 'sustainable-catalyst-library' ) . '</strong><p>' . esc_html( $warning['message'] ?? __( 'This readable document includes OCR-derived text. Consult the original PDF for authoritative wording.', 'sustainable-catalyst-library' ) ) . '</p></aside>';
        return $notice . $content;
    }

    private function pages( $document_id ) {
        $pages = get_post_meta( $document_id, self::META_PAGES, true );
        if ( ! is_array( $pages ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $pages as $key => $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }
            $page_number = absint( $page['page'] ?? $key );
            if ( $page_number < 1 ) {
                continue;
            }
            $normalized[ $page_number ] = $page;
            $normalized[ $page_number ]['page'] = $page_number;
        }
        ksort( $normalized );
        return $normalized;
    }

    private function save_pages( $document_id, $pages ) {
        ksort( $pages );
        delete_transient( 'sc_library_v241_ocr_workspace_totals' );
        update_post_meta( $document_id, self::META_PAGES, $pages );

        $summary = $this->calculate_summary( $pages );
        update_post_meta( $document_id, self::META_SUMMARY, $summary );

        $status = 'not_required';
        if ( $summary['processing'] || $summary['queued'] ) {
            $status = 'processing';
        } elseif ( $summary['needs_ocr'] || $summary['failed'] || $summary['unavailable'] ) {
            $status = 'needs_ocr';
        } elseif ( $summary['low_confidence'] ) {
            $status = 'needs_review';
        } elseif ( $summary['reviewed'] ) {
            $status = 'reviewed';
        } elseif ( $summary['complete'] ) {
            $status = 'complete';
        }
        update_post_meta( $document_id, self::META_STATUS, $status );
    }

    private function summary( $document_id ) {
        $summary = get_post_meta( $document_id, self::META_SUMMARY, true );
        if ( is_array( $summary ) ) {
            return wp_parse_args( $summary, $this->empty_summary() );
        }

        $pages = $this->pages( $document_id );
        if ( $pages ) {
            $summary = $this->calculate_summary( $pages );
            update_post_meta( $document_id, self::META_SUMMARY, $summary );
            return $summary;
        }
        return $this->empty_summary();
    }

    private function calculate_summary( $pages ) {
        $summary = $this->empty_summary();
        $confidence_total = 0.0;
        $confidence_count = 0;
        $providers = array();

        foreach ( $pages as $page ) {
            $summary['total']++;
            $status = sanitize_key( $page['status'] ?? 'needs_ocr' );
            if ( isset( $summary[ $status ] ) ) {
                $summary[ $status ]++;
            }
            if ( ! empty( $page['confidence'] ) ) {
                $confidence_total += (float) $page['confidence'];
                $confidence_count++;
            }
            if ( ! empty( $page['provider'] ) ) {
                $providers[] = sanitize_text_field( $page['provider'] );
            }
        }

        $summary['average_confidence'] = $confidence_count ? round( $confidence_total / $confidence_count, 2 ) : 0;
        $providers = array_values( array_unique( $providers ) );
        $summary['provider'] = $providers ? implode( ', ', $providers ) : '';
        return $summary;
    }

    private function empty_summary() {
        return array(
            'total'              => 0,
            'not_needed'         => 0,
            'needs_ocr'          => 0,
            'queued'             => 0,
            'processing'         => 0,
            'complete'           => 0,
            'low_confidence'     => 0,
            'reviewed'           => 0,
            'failed'             => 0,
            'unavailable'        => 0,
            'cancelled'          => 0,
            'average_confidence' => 0,
            'provider'           => '',
        );
    }

    private function workspace_totals() {
        $cached = get_transient( 'sc_library_v241_ocr_workspace_totals' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $totals = array( 'documents' => count( $ids ), 'needs_ocr' => 0, 'low_confidence' => 0, 'reviewed' => 0 );
        foreach ( $ids as $document_id ) {
            $summary = $this->summary( $document_id );
            if ( $summary['needs_ocr'] || $summary['failed'] || $summary['unavailable'] ) {
                $totals['needs_ocr']++;
            }
            $totals['low_confidence'] += absint( $summary['low_confidence'] );
            $totals['reviewed'] += absint( $summary['reviewed'] );
        }
        set_transient( 'sc_library_v241_ocr_workspace_totals', $totals, 5 * MINUTE_IN_SECONDS );
        return $totals;
    }

    private function document_state( $summary ) {
        if ( $summary['processing'] || $summary['queued'] ) {
            return 'processing';
        }
        if ( $summary['needs_ocr'] || $summary['failed'] || $summary['unavailable'] ) {
            return 'needs_ocr';
        }
        if ( $summary['low_confidence'] ) {
            return 'low_confidence';
        }
        if ( $summary['reviewed'] ) {
            return 'reviewed';
        }
        if ( $summary['complete'] ) {
            return 'complete';
        }
        return 'not_required';
    }

    private function document_filter_labels() {
        return array(
            'all'            => __( 'All documents', 'sustainable-catalyst-library' ),
            'needs_ocr'      => __( 'Needs OCR', 'sustainable-catalyst-library' ),
            'processing'     => __( 'Processing', 'sustainable-catalyst-library' ),
            'low_confidence' => __( 'Low confidence', 'sustainable-catalyst-library' ),
            'complete'       => __( 'OCR complete', 'sustainable-catalyst-library' ),
            'reviewed'       => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'not_required'   => __( 'OCR not required', 'sustainable-catalyst-library' ),
        );
    }

    private function create_job( $title, $items, $config ) {
        $job_id = wp_insert_post( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'post_title' => $title ), true );
        if ( is_wp_error( $job_id ) ) {
            wp_die( esc_html( $job_id->get_error_message() ) );
        }
        update_post_meta( $job_id, self::META_JOB_ITEMS, array_values( $items ) );
        update_post_meta( $job_id, self::META_JOB_STATUS, 'created' );
        update_post_meta( $job_id, self::META_JOB_CONFIG, $config );
        update_post_meta( $job_id, self::META_JOB_LOG, array() );
        $this->prune_jobs();
        return $job_id;
    }

    private function job_items( $job_id ) {
        $items = get_post_meta( $job_id, self::META_JOB_ITEMS, true );
        return is_array( $items ) ? array_values( $items ) : array();
    }

    private function job_state( $job_id ) {
        $items = $this->job_items( $job_id );
        $state = array( 'queued' => 0, 'processing' => 0, 'processed' => 0, 'done' => 0, 'failed' => 0, 'reviewed' => 0, 'cancelled' => 0 );

        foreach ( $items as $item ) {
            $item_state = sanitize_key( $item['state'] ?? '' );
            if ( 'queued' === $item_state ) {
                $state['queued']++;
            } elseif ( 'processing' === $item_state ) {
                $state['processing']++;
            } elseif ( in_array( $item_state, array( 'complete', 'reviewed' ), true ) ) {
                $state['processed']++;
                $state['done']++;
                if ( 'reviewed' === $item_state ) {
                    $state['reviewed']++;
                }
            } elseif ( in_array( $item_state, array( 'failed', 'unavailable', 'low_confidence' ), true ) ) {
                $state['processed']++;
                $state['failed']++;
            } elseif ( 'cancelled' === $item_state ) {
                $state['processed']++;
                $state['cancelled']++;
            }
        }

        $public_items = array();
        foreach ( $items as $item ) {
            unset( $item['lock_token'], $item['lock_user'], $item['lock_client'] );
            $public_items[] = $item;
        }

        return array_merge(
            $state,
            array(
                'job_id' => $job_id,
                'status' => sanitize_key( (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) ) ?: 'created',
                'total'  => count( $items ),
                'items'  => $public_items,
                'logs'   => $this->job_logs( $job_id ),
            )
        );
    }

    private function settle_job_status( $job_id, $items ) {
        $has_active = false;
        $has_attention = false;
        foreach ( $items as $item ) {
            $state = sanitize_key( $item['state'] ?? '' );
            if ( in_array( $state, array( 'queued', 'processing' ), true ) ) {
                $has_active = true;
            }
            if ( in_array( $state, array( 'failed', 'unavailable', 'low_confidence' ), true ) ) {
                $has_attention = true;
            }
        }
        if ( $has_active ) {
            return;
        }

        update_post_meta( $job_id, self::META_JOB_STATUS, $has_attention ? 'complete_with_errors' : 'complete' );
        $this->job_log( $job_id, $has_attention ? 'complete-with-errors' : 'complete', $has_attention ? __( 'OCR queue completed with pages requiring attention.', 'sustainable-catalyst-library' ) : __( 'OCR queue completed successfully.', 'sustainable-catalyst-library' ) );

        $config = get_post_meta( $job_id, self::META_JOB_CONFIG, true );
        $document_id = is_array( $config ) ? absint( $config['document_id'] ?? 0 ) : 0;
        if ( $document_id ) {
            update_post_meta( $document_id, self::META_STATUS, $has_attention ? 'needs_review' : 'complete' );
            $this->log( $document_id, 'job-complete', $has_attention ? __( 'OCR job completed with pages requiring attention.', 'sustainable-catalyst-library' ) : __( 'OCR job completed.', 'sustainable-catalyst-library' ) );
        }
    }

    private function verify_job_ajax() {
        check_ajax_referer( 'sc_library_ocr_v240', 'nonce' );
        $job_id = absint( wp_unslash( $_POST['job_id'] ?? 0 ) );
        if ( ! $job_id || self::JOB_TYPE !== get_post_type( $job_id ) || ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to manage this OCR queue.', 'sustainable-catalyst-library' ) ), 403 );
        }
        return $job_id;
    }

    private function job_log( $job_id, $event, $message ) {
        $logs = $this->job_logs( $job_id );
        $logs[] = array( 'time' => current_time( 'mysql' ), 'event' => sanitize_key( $event ), 'message' => sanitize_text_field( $message ), 'user' => get_current_user_id() );
        if ( count( $logs ) > self::LOG_LIMIT ) {
            $logs = array_slice( $logs, -self::LOG_LIMIT );
        }
        update_post_meta( $job_id, self::META_JOB_LOG, $logs );
    }

    private function job_logs( $job_id ) {
        $logs = get_post_meta( $job_id, self::META_JOB_LOG, true );
        return is_array( $logs ) ? $logs : array();
    }

    private function log( $document_id, $event, $message ) {
        $logs = get_post_meta( $document_id, self::META_LOG, true );
        $logs = is_array( $logs ) ? $logs : array();
        $logs[] = array( 'time' => current_time( 'mysql' ), 'event' => sanitize_key( $event ), 'message' => sanitize_text_field( $message ), 'user' => get_current_user_id() );
        if ( count( $logs ) > self::LOG_LIMIT ) {
            $logs = array_slice( $logs, -self::LOG_LIMIT );
        }
        update_post_meta( $document_id, self::META_LOG, $logs );
    }

    public function export_document_ocr_csv() {
        $document_id = absint( wp_unslash( $_GET['document_id'] ?? 0 ) );
        if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) {
            wp_die( esc_html__( 'You are not allowed to export this OCR report.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_export_document_ocr_' . $document_id );
        $this->csv_headers( 'ocr-report-document-' . $document_id . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'document_id', 'page', 'source_characters', 'status', 'confidence', 'language', 'provider', 'attempts', 'reviewed_at', 'reviewed_by', 'message', 'warnings' ) );
        foreach ( $this->pages( $document_id ) as $page ) {
            fputcsv(
                $out,
                array_map(
                    array( 'SC_Library_Document_OCR_Reliability', 'safe_csv_cell' ),
                    array(
                        $document_id,
                        $page['page'] ?? '',
                        $page['source_chars'] ?? 0,
                        $page['status'] ?? '',
                        $page['confidence'] ?? 0,
                        $page['language'] ?? '',
                        $page['provider'] ?? '',
                        $page['attempts'] ?? 0,
                        $page['reviewed_at'] ?? '',
                        $page['reviewed_by'] ?? '',
                        $page['message'] ?? '',
                        implode( '; ', (array) ( $page['warnings'] ?? array() ) ),
                    )
                )
            );
        }
        fclose( $out );
        exit;
    }

    public function export_ocr_job_csv() {
        $job_id = absint( wp_unslash( $_GET['job_id'] ?? 0 ) );
        if ( ! $job_id || self::JOB_TYPE !== get_post_type( $job_id ) || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to export this OCR job.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v240_export_ocr_job_' . $job_id );
        $this->csv_headers( 'ocr-job-' . $job_id . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'item', 'document_id', 'document_title', 'page', 'provider', 'language', 'state', 'confidence', 'attempts', 'message', 'updated_at' ) );
        foreach ( $this->job_items( $job_id ) as $index => $item ) {
            fputcsv(
                $out,
                array_map(
                    array( 'SC_Library_Document_OCR_Reliability', 'safe_csv_cell' ),
                    array(
                        $index + 1,
                        $item['document_id'] ?? '',
                        ! empty( $item['document_id'] ) ? get_the_title( $item['document_id'] ) : '',
                        $item['page'] ?? '',
                        $item['provider'] ?? '',
                        $item['language'] ?? '',
                        $item['state'] ?? '',
                        $item['confidence'] ?? 0,
                        $item['attempts'] ?? 0,
                        $item['message'] ?? '',
                        $item['updated_at'] ?? '',
                    )
                )
            );
        }
        fclose( $out );
        exit;
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-ocr-review' ) ) {
            return;
        }

        $notice = isset( $_GET['sc_v240_notice'] ) ? sanitize_key( wp_unslash( $_GET['sc_v240_notice'] ) ) : '';
        $messages = array(
            'analyzed'             => array( 'success', __( 'Page text was analyzed and the OCR inventory was refreshed.', 'sustainable-catalyst-library' ) ),
            'job_created'          => array( 'success', __( 'OCR job created. Keep the queue page open while pages are processed; the job can be resumed later.', 'sustainable-catalyst-library' ) ),
            'page_saved'           => array( 'success', __( 'Page review saved.', 'sustainable-catalyst-library' ) ),
            'ocr_applied'          => array( 'success', __( 'Reviewed OCR was applied to the readable document. Review the complete document before publishing.', 'sustainable-catalyst-library' ) ),
            'no_pages'             => array( 'error', __( 'No eligible OCR pages were selected.', 'sustainable-catalyst-library' ) ),
            'provider_unavailable' => array( 'error', __( 'The selected OCR provider is unavailable.', 'sustainable-catalyst-library' ) ),
            'empty_review'         => array( 'error', __( 'Reviewed OCR text cannot be empty.', 'sustainable-catalyst-library' ) ),
            'no_ocr_pages'         => array( 'error', __( 'No page-level OCR records are available.', 'sustainable-catalyst-library' ) ),
            'no_reviewed_ocr'      => array( 'error', __( 'No reviewed OCR text is ready to apply.', 'sustainable-catalyst-library' ) ),
        );

        if ( isset( $messages[ $notice ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>';
        }
    }

    public function document_columns( $columns ) {
        $updated = array();
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $updated['sc_document_ocr'] = __( 'OCR', 'sustainable-catalyst-library' );
            }
            $updated[ $key ] = $label;
        }
        return $updated;
    }

    public function document_column_content( $column, $document_id ) {
        if ( 'sc_document_ocr' !== $column ) {
            return;
        }

        $summary = $this->summary( $document_id );
        $state = $this->document_state( $summary );
        $labels = $this->document_filter_labels();
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=sc-library-ocr-review&document_id=' . $document_id ) ) . '"><strong class="sc-ocr-state is-' . esc_attr( $state ) . '">' . esc_html( $labels[ $state ] ?? ucfirst( str_replace( '_', ' ', $state ) ) ) . '</strong></a>';
        if ( $summary['total'] ) {
            echo '<span class="sc-admin-state-detail">' . esc_html( sprintf( __( '%1$d pages · %2$d reviewed', 'sustainable-catalyst-library' ), $summary['total'], $summary['reviewed'] ) ) . '</span>';
        }
    }

    private function first_attention_page( $pages ) {
        foreach ( $pages as $page_number => $page ) {
            if ( in_array( $page['status'] ?? '', array( 'needs_ocr', 'low_confidence', 'failed', 'unavailable', 'complete' ), true ) ) {
                return absint( $page_number );
            }
        }
        return $pages ? absint( array_key_first( $pages ) ) : 1;
    }

    private function default_provider_id( $providers ) {
        foreach ( $providers as $provider_id => $provider ) {
            if ( ! empty( $provider['available'] ) ) {
                return $provider_id;
            }
        }
        return 'local_tesseract';
    }

    private function confidence_threshold( $document_id ) {
        $value = get_post_meta( $document_id, self::META_CONFIDENCE_THRESHOLD, true );
        return '' === $value ? self::DEFAULT_CONFIDENCE_THRESHOLD : max( 0, min( 100, absint( $value ) ) );
    }

    private function detect_language_hint( $text, $fallback = '' ) {
        $text = (string) $text;
        if ( preg_match( '/[\x{0600}-\x{06FF}]/u', $text ) ) {
            return 'ara';
        }
        if ( preg_match( '/[\x{0400}-\x{04FF}]/u', $text ) ) {
            return 'cyr';
        }
        if ( preg_match( '/[\x{0590}-\x{05FF}]/u', $text ) ) {
            return 'heb';
        }
        if ( preg_match( '/[\x{3040}-\x{30FF}]/u', $text ) ) {
            return 'jpn';
        }
        if ( preg_match( '/[\x{4E00}-\x{9FFF}]/u', $text ) ) {
            return 'chi';
        }

        $lower = strtolower( ' ' . preg_replace( '/\s+/u', ' ', $text ) . ' ' );
        $signals = array(
            'eng' => array( ' the ', ' and ', ' of ', ' to ', ' in ' ),
            'spa' => array( ' el ', ' la ', ' de ', ' que ', ' y ' ),
            'fra' => array( ' le ', ' la ', ' de ', ' et ', ' des ' ),
            'deu' => array( ' der ', ' die ', ' und ', ' von ', ' das ' ),
            'por' => array( ' de ', ' do ', ' da ', ' e ', ' que ' ),
            'ita' => array( ' il ', ' la ', ' di ', ' e ', ' che ' ),
        );
        $best = '';
        $best_score = 0;
        foreach ( $signals as $language => $words ) {
            $score = 0;
            foreach ( $words as $word ) {
                $score += substr_count( $lower, $word );
            }
            if ( $score > $best_score ) {
                $best = $language;
                $best_score = $score;
            }
        }
        return $best ?: ( $fallback ?: 'und' );
    }

    private function page_status_label( $status ) {
        return self::$page_status_labels[ $status ] ?? ucfirst( str_replace( '_', ' ', (string) $status ) );
    }

    private function extraction_status_label( $status ) {
        $labels = array(
            'pending'        => __( 'Not converted', 'sustainable-catalyst-library' ),
            'extracting'     => __( 'Converting', 'sustainable-catalyst-library' ),
            'ready_review'   => __( 'Ready for review', 'sustainable-catalyst-library' ),
            'reviewed'       => __( 'Reviewed', 'sustainable-catalyst-library' ),
            'published'      => __( 'Published', 'sustainable-catalyst-library' ),
            'needs_ocr'      => __( 'Needs OCR', 'sustainable-catalyst-library' ),
            'failed'         => __( 'Failed', 'sustainable-catalyst-library' ),
            'legacy_content' => __( 'Existing content', 'sustainable-catalyst-library' ),
        );
        $status = sanitize_key( (string) $status );
        return $labels[ $status ] ?? __( 'Unknown', 'sustainable-catalyst-library' );
    }

    private function job_status_label( $status ) {
        $labels = array(
            'created'              => __( 'Created', 'sustainable-catalyst-library' ),
            'running'              => __( 'Running', 'sustainable-catalyst-library' ),
            'paused'               => __( 'Paused', 'sustainable-catalyst-library' ),
            'complete'             => __( 'Complete', 'sustainable-catalyst-library' ),
            'complete_with_errors' => __( 'Complete with attention', 'sustainable-catalyst-library' ),
            'cancelled'            => __( 'Cancelled', 'sustainable-catalyst-library' ),
        );
        return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', (string) $status ) );
    }

    private function job_status_message( $state ) {
        if ( 'running' === $state['status'] ) {
            return __( 'The browser requests one server-side OCR page at a time. The persistent job can resume after closing this page.', 'sustainable-catalyst-library' );
        }
        if ( 'paused' === $state['status'] ) {
            return __( 'The OCR queue is paused.', 'sustainable-catalyst-library' );
        }
        if ( 'complete_with_errors' === $state['status'] ) {
            return __( 'The OCR queue finished, but low-confidence, failed, or unavailable pages require attention.', 'sustainable-catalyst-library' );
        }
        if ( 'complete' === $state['status'] ) {
            return __( 'All queued OCR pages completed.', 'sustainable-catalyst-library' );
        }
        return __( 'Review the OCR queue and controls below.', 'sustainable-catalyst-library' );
    }

    private function has_ocr_result( $page ) {
        return ! empty( $page['ocr_text'] ) || ! empty( $page['corrected_text'] ) || in_array( $page['status'] ?? '', array( 'complete', 'low_confidence', 'reviewed' ), true );
    }

    private function non_space_length( $text ) {
        $compact = preg_replace( '/\s+/u', '', (string) $text );
        return function_exists( 'mb_strlen' ) ? mb_strlen( $compact ) : strlen( $compact );
    }

    private function normalize_text( $text ) {
        $text = wp_check_invalid_utf8( (string) $text );
        $text = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), $text );
        $text = preg_replace( '/[\x{00A0}\t]+/u', ' ', $text );
        $text = preg_replace( '/[ ]{2,}/u', ' ', $text );
        return trim( preg_replace( '/\n{3,}/u', "\n\n", $text ) );
    }

    private function remove_directory( $directory ) {
        if ( ! is_dir( $directory ) ) {
            return;
        }
        $items = scandir( $directory );
        if ( ! is_array( $items ) ) {
            return;
        }
        foreach ( $items as $item ) {
            if ( in_array( $item, array( '.', '..' ), true ) ) {
                continue;
            }
            $path = trailingslashit( $directory ) . $item;
            if ( is_dir( $path ) ) {
                $this->remove_directory( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $directory );
    }

    private function sync_page_state_from_job_item( $item, $status, $message ) {
        $document_id = absint( $item['document_id'] ?? 0 );
        $page_number = absint( $item['page'] ?? 0 );
        if ( ! $document_id || ! $page_number ) {
            return;
        }

        $pages = $this->pages( $document_id );
        if ( ! isset( $pages[ $page_number ] ) ) {
            return;
        }

        $pages[ $page_number ]['status'] = sanitize_key( $status );
        $pages[ $page_number ]['message'] = sanitize_text_field( $message );
        $pages[ $page_number ]['updated_at'] = current_time( 'mysql' );
        $this->save_pages( $document_id, $pages );
    }

    private function redirect_document( $document_id, $notice, $page_number = 0 ) {
        $reliability_notices = array(
            'source_changed',
            'reconversion_required',
            'language_unavailable',
            'published_confirmation_required',
            'active_ocr_job',
            'no_backup',
            'invalid_document',
            'missing_pdf',
        );
        $notice_key = in_array( sanitize_key( $notice ), $reliability_notices, true ) ? 'sc_v241_notice' : 'sc_v240_notice';
        $args = array(
            'page'        => 'sc-library-ocr-review',
            'document_id' => $document_id,
            $notice_key   => sanitize_key( $notice ),
        );
        if ( $page_number ) {
            $args['ocr_review_page'] = $page_number;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function csv_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    }

    private function prune_jobs() {
        $jobs = get_posts( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
        $retained_terminal = 0;
        foreach ( $jobs as $job_id ) {
            $status = sanitize_key( (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) );
            if ( in_array( $status, array( 'running', 'paused', 'created' ), true ) ) {
                continue;
            }
            $retained_terminal++;
            if ( $retained_terminal > self::JOB_LIMIT ) {
                wp_delete_post( $job_id, true );
            }
        }
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : self::VERSION;
    }
}
