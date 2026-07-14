<?php
/**
 * Bulk Import and Collection Repair.
 *
 * Adds paginated Media Library inventory, safe batch record creation,
 * browser-driven conversion jobs, duplicate/orphan detection, metadata repair,
 * lifecycle and family actions, and CSV reporting.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_PDF_Bulk_Import_Repair {
    public const VERSION = '2.2.2';
    public const DOCUMENT_TYPE = 'sc_foundation_doc';
    public const FAMILY_TAX = 'sc_document_family';
    public const JOB_TYPE = 'sc_pdf_bulk_job';
    public const META_PDF_ID = '_sc_library_foundation_page_pdf_id';
    public const META_STATUS = '_sc_document_extraction_status';
    public const META_CHECKSUM = '_sc_document_checksum';
    public const META_LIFECYCLE = '_sc_document_lifecycle_status';
    public const META_REVIEWED = '_sc_document_reviewed';
    public const META_JOB_ITEMS = '_sc_pdf_bulk_job_items';
    public const META_JOB_STATUS = '_sc_pdf_bulk_job_status';
    public const META_JOB_CONFIG = '_sc_pdf_bulk_job_config';
    public const META_JOB_LOG = '_sc_pdf_bulk_job_log';
    public const META_ATTACHMENT_CHECKSUM = '_sc_library_pdf_attachment_checksum';
    public const META_ATTACHMENT_CHECKSUM_MTIME = '_sc_library_pdf_attachment_checksum_mtime';
    public const DEFAULT_FAMILY = 'foundations';
    public const PAGE_SIZE = 50;
    public const JOB_LIMIT = 30;

    /** @var string[] */
    private static $compatible_pdf_meta = array(
        self::META_PDF_ID,
        '_sc_library_pdf_attachment_id',
        '_sc_library_foundation_pdf_attachment_id',
        '_sc_library_foundation_attachment_id',
        '_sc_foundation_pdf_attachment_id',
        'sc_library_pdf_attachment_id',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'register_job_type' ), 195 );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ), 220 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 140 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 140 );

        add_action( 'admin_post_sc_library_v222_create_import_job', array( $this, 'create_import_job' ) );
        add_action( 'admin_post_sc_library_v222_repair_documents', array( $this, 'repair_documents' ) );
        add_action( 'admin_post_sc_library_v222_export_job', array( $this, 'export_job_csv' ) );
        add_action( 'admin_post_sc_library_v222_export_collection', array( $this, 'export_collection_csv' ) );

        add_action( 'wp_ajax_sc_library_v222_job_status', array( $this, 'ajax_job_status' ) );
        add_action( 'wp_ajax_sc_library_v222_next_item', array( $this, 'ajax_next_item' ) );
        add_action( 'wp_ajax_sc_library_v222_mark_item', array( $this, 'ajax_mark_item' ) );
        add_action( 'wp_ajax_sc_library_v222_control_job', array( $this, 'ajax_control_job' ) );
    }

    public function register_job_type() {
        register_post_type(
            self::JOB_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'PDF Import Jobs', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'PDF Import Job', 'sustainable-catalyst-library' ),
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
        remove_submenu_page( 'sc-library', 'sc-library-pdf-document-import' );
        add_submenu_page(
            'sc-library',
            __( 'Bulk Import and Collection Repair', 'sustainable-catalyst-library' ),
            __( 'Bulk Import & Repair', 'sustainable-catalyst-library' ),
            'upload_files',
            'sc-library-pdf-bulk-import-repair',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-pdf-bulk-import-repair' ) ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-pdf-bulk-import',
            SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css',
            array(),
            $this->version()
        );
        wp_enqueue_script(
            'sc-library-pdf-bulk-import',
            SC_LIBRARY_URL . 'assets/js/sc-library-pdf-bulk-import.js',
            array( 'jquery' ),
            $this->version(),
            true
        );

        $job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
        wp_localize_script(
            'sc-library-pdf-bulk-import',
            'SCLibraryPdfBulk',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'sc_library_pdf_v222' ),
                'conversionNonce'  => wp_create_nonce( 'sc_library_pdf_v221' ),
                'jobId'            => $job_id,
                'pdfJsUrl'         => SC_LIBRARY_URL . 'assets/vendor/pdfjs/build/pdf.mjs',
                'workerUrl'        => SC_LIBRARY_URL . 'assets/vendor/pdfjs/build/pdf.worker.mjs',
                'cMapUrl'          => SC_LIBRARY_URL . 'assets/vendor/pdfjs/cmaps/',
                'fontUrl'          => SC_LIBRARY_URL . 'assets/vendor/pdfjs/standard_fonts/',
                'wasmUrl'          => SC_LIBRARY_URL . 'assets/vendor/pdfjs/wasm/',
                'chunkPages'       => absint( apply_filters( 'sc_library_pdf_bulk_chunk_pages', 5 ) ),
                'chunkCharacters'  => absint( apply_filters( 'sc_library_pdf_bulk_chunk_characters', 240000 ) ),
                'requestRetries'   => absint( apply_filters( 'sc_library_pdf_bulk_request_retries', 3 ) ),
                'retryDelay'       => absint( apply_filters( 'sc_library_pdf_bulk_retry_delay_ms', 900 ) ),
                'strings'          => array(
                    'processing'    => __( 'Processing document %1$d of %2$d…', 'sustainable-catalyst-library' ),
                    'paused'        => __( 'Queue paused.', 'sustainable-catalyst-library' ),
                    'complete'      => __( 'Queue complete.', 'sustainable-catalyst-library' ),
                    'errors'        => __( 'Queue complete with errors.', 'sustainable-catalyst-library' ),
                    'retrying'      => __( 'Connection interrupted. Retrying…', 'sustainable-catalyst-library' ),
                    'workerFallback'=> __( 'PDF worker unavailable. Continuing in compatibility mode…', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'import';
        if ( ! in_array( $tab, array( 'import', 'repair', 'queue' ), true ) ) {
            $tab = 'import';
        }
        $base_url = admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' );
        ?>
        <div class="wrap sc-pdf-bulk-admin">
            <h1><?php esc_html_e( 'PDF Bulk Import and Collection Repair', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Scan existing Media Library PDFs, create document records safely, process conversion queues, and repair collection metadata without creating blog posts.', 'sustainable-catalyst-library' ); ?></p>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'PDF library tools', 'sustainable-catalyst-library' ); ?>">
                <a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base_url ) ); ?>"><?php esc_html_e( 'Import PDFs', 'sustainable-catalyst-library' ); ?></a>
                <a class="nav-tab <?php echo 'repair' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'repair', $base_url ) ); ?>"><?php esc_html_e( 'Collection Repair', 'sustainable-catalyst-library' ); ?></a>
                <a class="nav-tab <?php echo 'queue' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'queue', $base_url ) ); ?>"><?php esc_html_e( 'Conversion Queue', 'sustainable-catalyst-library' ); ?></a>
            </nav>
            <?php
            if ( 'repair' === $tab ) {
                $this->render_repair_tab();
            } elseif ( 'queue' === $tab ) {
                $this->render_queue_tab();
            } else {
                $this->render_import_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_import_tab() {
        $page = isset( $_GET['pdf_page'] ) ? max( 1, absint( wp_unslash( $_GET['pdf_page'] ) ) ) : 1;
        $search = isset( $_GET['pdf_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pdf_search'] ) ) : '';
        $filter = isset( $_GET['pdf_status'] ) ? sanitize_key( wp_unslash( $_GET['pdf_status'] ) ) : 'all';
        $query = new WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'application/pdf',
                'posts_per_page' => self::PAGE_SIZE,
                'paged'          => $page,
                's'              => $search,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        $families = get_terms( array( 'taxonomy' => self::FAMILY_TAX, 'hide_empty' => false ) );
        $counts = $this->inventory_counts();
        ?>
        <section class="sc-pdf-bulk-section">
            <div class="sc-pdf-bulk-cards">
                <div><strong><?php echo esc_html( $counts['pdfs'] ); ?></strong><span><?php esc_html_e( 'Media Library PDFs', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $counts['linked'] ); ?></strong><span><?php esc_html_e( 'Represented', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $counts['unlinked'] ); ?></strong><span><?php esc_html_e( 'Unlinked PDFs', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $counts['duplicates'] ); ?></strong><span><?php esc_html_e( 'Possible duplicates', 'sustainable-catalyst-library' ); ?></span></div>
            </div>

            <form class="sc-pdf-bulk-filter" method="get">
                <input type="hidden" name="page" value="sc-library-pdf-bulk-import-repair">
                <input type="hidden" name="tab" value="import">
                <label><?php esc_html_e( 'Search PDFs', 'sustainable-catalyst-library' ); ?><input type="search" name="pdf_search" value="<?php echo esc_attr( $search ); ?>"></label>
                <label><?php esc_html_e( 'Inventory status', 'sustainable-catalyst-library' ); ?>
                    <select name="pdf_status">
                        <?php foreach ( array( 'all' => __( 'All PDFs', 'sustainable-catalyst-library' ), 'unlinked' => __( 'Unlinked', 'sustainable-catalyst-library' ), 'linked' => __( 'Represented', 'sustainable-catalyst-library' ), 'duplicate' => __( 'Possible duplicate', 'sustainable-catalyst-library' ) ) as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button"><?php esc_html_e( 'Filter', 'sustainable-catalyst-library' ); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sc_library_v222_create_import_job' ); ?>
                <input type="hidden" name="action" value="sc_library_v222_create_import_job">
                <div class="sc-pdf-bulk-toolbar">
                    <label><?php esc_html_e( 'Batch action', 'sustainable-catalyst-library' ); ?>
                        <select name="batch_action" required>
                            <option value="create_only"><?php esc_html_e( 'Create draft records only', 'sustainable-catalyst-library' ); ?></option>
                            <option value="create_and_queue"><?php esc_html_e( 'Create records and queue conversion', 'sustainable-catalyst-library' ); ?></option>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Document family', 'sustainable-catalyst-library' ); ?>
                        <select name="family_id">
                            <?php if ( ! is_wp_error( $families ) ) : foreach ( $families as $family ) : ?>
                                <option value="<?php echo esc_attr( $family->term_id ); ?>" <?php selected( $family->slug, self::DEFAULT_FAMILY ); ?>><?php echo esc_html( $family->name ); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Lifecycle', 'sustainable-catalyst-library' ); ?>
                        <select name="lifecycle">
                            <?php foreach ( $this->lifecycle_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary"><?php esc_html_e( 'Apply to Selected PDFs', 'sustainable-catalyst-library' ); ?></button>
                </div>
                <table class="widefat striped sc-pdf-bulk-table">
                    <thead><tr><td class="check-column"><input type="checkbox" data-sc-select-all></td><th><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Inventory status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Document record', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Checksum', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                    <?php
                    $shown = 0;
                    if ( $query->have_posts() ) :
                        while ( $query->have_posts() ) : $query->the_post();
                            $attachment_id = get_the_ID();
                            $state = $this->attachment_inventory_state( $attachment_id );
                            if ( 'all' !== $filter && $state['status'] !== $filter ) {
                                continue;
                            }
                            $shown++;
                            ?>
                            <tr>
                                <th class="check-column"><input type="checkbox" name="attachment_ids[]" value="<?php echo esc_attr( $attachment_id ); ?>" <?php disabled( 'linked' === $state['status'] || 'duplicate' === $state['status'] ); ?>></th>
                                <td><strong><?php the_title(); ?></strong><br><a href="<?php echo esc_url( wp_get_attachment_url( $attachment_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( basename( (string) get_attached_file( $attachment_id ) ) ); ?></a><br><span class="description"><?php echo esc_html( size_format( filesize( get_attached_file( $attachment_id ) ) ) ); ?></span></td>
                                <td><strong class="sc-inventory-state is-<?php echo esc_attr( $state['status'] ); ?>"><?php echo esc_html( $state['label'] ); ?></strong><span><?php echo esc_html( $state['message'] ); ?></span></td>
                                <td><?php if ( $state['document_id'] ) : ?><a href="<?php echo esc_url( get_edit_post_link( $state['document_id'], 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $state['document_id'] ) ); ?></a><?php else : esc_html_e( 'No record', 'sustainable-catalyst-library' ); endif; ?></td>
                                <td><code><?php echo esc_html( $state['checksum'] ? substr( $state['checksum'], 0, 12 ) . '…' : __( 'Unavailable', 'sustainable-catalyst-library' ) ); ?></code></td>
                            </tr>
                            <?php
                        endwhile;
                    endif;
                    wp_reset_postdata();
                    if ( ! $shown ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No PDFs on this page match the selected inventory filter.', 'sustainable-catalyst-library' ); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>
            <?php if ( $query->max_num_pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'pdf_page' => '%#%', 'pdf_search' => $search, 'pdf_status' => $filter ) ), 'current' => $page, 'total' => $query->max_num_pages ) ) ); ?></div></div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_repair_tab() {
        $page = isset( $_GET['record_page'] ) ? max( 1, absint( wp_unslash( $_GET['record_page'] ) ) ) : 1;
        $issue_filter = isset( $_GET['issue'] ) ? sanitize_key( wp_unslash( $_GET['issue'] ) ) : 'all';
        $query = new WP_Query(
            array(
                'post_type'      => self::DOCUMENT_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => self::PAGE_SIZE,
                'paged'          => $page,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $families = get_terms( array( 'taxonomy' => self::FAMILY_TAX, 'hide_empty' => false ) );
        $report = $this->collection_report();
        ?>
        <section class="sc-pdf-bulk-section">
            <div class="sc-pdf-bulk-cards">
                <div><strong><?php echo esc_html( $report['records'] ); ?></strong><span><?php esc_html_e( 'Document records', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $report['orphans'] ); ?></strong><span><?php esc_html_e( 'Missing or broken PDFs', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $report['metadata'] ); ?></strong><span><?php esc_html_e( 'Missing metadata', 'sustainable-catalyst-library' ); ?></span></div>
                <div><strong><?php echo esc_html( $report['duplicates'] ); ?></strong><span><?php esc_html_e( 'Duplicate records', 'sustainable-catalyst-library' ); ?></span></div>
            </div>
            <div class="sc-pdf-bulk-report-actions">
                <form method="get"><input type="hidden" name="page" value="sc-library-pdf-bulk-import-repair"><input type="hidden" name="tab" value="repair"><label><?php esc_html_e( 'Issue filter', 'sustainable-catalyst-library' ); ?><select name="issue"><?php foreach ( $this->issue_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $issue_filter, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><button class="button"><?php esc_html_e( 'Filter', 'sustainable-catalyst-library' ); ?></button></form>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'sc_library_v222_export_collection', admin_url( 'admin-post.php' ) ), 'sc_library_v222_export_collection' ) ); ?>"><?php esc_html_e( 'Export Collection Report CSV', 'sustainable-catalyst-library' ); ?></a>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sc_library_v222_repair_documents' ); ?>
                <input type="hidden" name="action" value="sc_library_v222_repair_documents">
                <div class="sc-pdf-bulk-toolbar">
                    <label><?php esc_html_e( 'Repair action', 'sustainable-catalyst-library' ); ?>
                        <select name="repair_action" required>
                            <option value="safe_repair"><?php esc_html_e( 'Repair safe metadata', 'sustainable-catalyst-library' ); ?></option>
                            <option value="assign_family"><?php esc_html_e( 'Assign document family', 'sustainable-catalyst-library' ); ?></option>
                            <option value="set_lifecycle"><?php esc_html_e( 'Set lifecycle status', 'sustainable-catalyst-library' ); ?></option>
                            <option value="queue_conversion"><?php esc_html_e( 'Queue missing conversions', 'sustainable-catalyst-library' ); ?></option>
                            <option value="reprocess"><?php esc_html_e( 'Queue full reprocessing', 'sustainable-catalyst-library' ); ?></option>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Family', 'sustainable-catalyst-library' ); ?><select name="family_id"><?php if ( ! is_wp_error( $families ) ) : foreach ( $families as $family ) : ?><option value="<?php echo esc_attr( $family->term_id ); ?>" <?php selected( $family->slug, self::DEFAULT_FAMILY ); ?>><?php echo esc_html( $family->name ); ?></option><?php endforeach; endif; ?></select></label>
                    <label><?php esc_html_e( 'Lifecycle', 'sustainable-catalyst-library' ); ?><select name="lifecycle"><?php foreach ( $this->lifecycle_labels() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <button class="button button-primary"><?php esc_html_e( 'Apply to Selected Records', 'sustainable-catalyst-library' ); ?></button>
                </div>
                <table class="widefat striped sc-pdf-bulk-table">
                    <thead><tr><td class="check-column"><input type="checkbox" data-sc-select-all></td><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Family and lifecycle', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Conversion', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Issues', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                    <tbody>
                    <?php $shown = 0; if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); $post_id = get_the_ID(); $state = $this->document_repair_state( $post_id ); if ( 'all' !== $issue_filter && ! in_array( $issue_filter, $state['issue_codes'], true ) ) { continue; } $shown++; ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="document_ids[]" value="<?php echo esc_attr( $post_id ); ?>"></th>
                            <td><strong><a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title() ?: __( '(Untitled)', 'sustainable-catalyst-library' ) ); ?></a></strong><br><span class="description">ID <?php echo esc_html( $post_id ); ?></span></td>
                            <td><?php echo esc_html( $state['family'] ?: __( 'No family', 'sustainable-catalyst-library' ) ); ?><br><span class="description"><?php echo esc_html( ucfirst( $state['lifecycle'] ?: __( 'missing', 'sustainable-catalyst-library' ) ) ); ?></span></td>
                            <td><strong class="sc-inventory-state is-<?php echo esc_attr( $state['pdf_status'] ); ?>"><?php echo esc_html( $state['pdf_label'] ); ?></strong><?php if ( $state['filename'] ) : ?><span><?php echo esc_html( $state['filename'] ); ?></span><?php endif; ?></td>
                            <td><?php echo esc_html( $state['conversion_label'] ); ?></td>
                            <td><?php if ( $state['issues'] ) : ?><ul class="sc-repair-issues"><?php foreach ( $state['issues'] as $issue ) : ?><li><?php echo esc_html( $issue ); ?></li><?php endforeach; ?></ul><?php else : ?><strong class="sc-inventory-state is-linked"><?php esc_html_e( 'Ready', 'sustainable-catalyst-library' ); ?></strong><?php endif; ?></td>
                        </tr>
                    <?php endwhile; endif; wp_reset_postdata(); if ( ! $shown ) : ?><tr><td colspan="6"><?php esc_html_e( 'No records on this page match the selected issue filter.', 'sustainable-catalyst-library' ); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </form>
            <?php if ( $query->max_num_pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'record_page' => '%#%', 'issue' => $issue_filter ) ), 'current' => $page, 'total' => $query->max_num_pages ) ) ); ?></div></div><?php endif; ?>
        </section>
        <?php
    }

    private function render_queue_tab() {
        $job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
        $jobs = get_posts( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'posts_per_page' => self::JOB_LIMIT, 'orderby' => 'date', 'order' => 'DESC' ) );
        if ( ! $job_id && $jobs ) {
            $job_id = $jobs[0]->ID;
        }
        $job = $job_id ? get_post( $job_id ) : null;
        $state = $job ? $this->job_state( $job_id ) : array();
        ?>
        <section class="sc-pdf-bulk-section">
            <div class="sc-pdf-job-layout">
                <aside class="sc-pdf-job-list">
                    <h2><?php esc_html_e( 'Recent jobs', 'sustainable-catalyst-library' ); ?></h2>
                    <?php if ( $jobs ) : ?><ul><?php foreach ( $jobs as $entry ) : $entry_state = $this->job_state( $entry->ID ); ?><li class="<?php echo $entry->ID === $job_id ? 'is-current' : ''; ?>"><a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'queue', 'job_id' => $entry->ID ), admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' ) ) ); ?>"><strong><?php echo esc_html( $entry->post_title ); ?></strong><span><?php echo esc_html( $this->job_status_label( $entry_state['status'] ) ); ?> · <?php echo esc_html( $entry_state['done'] . '/' . $entry_state['total'] ); ?></span></a></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'No import or conversion jobs yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
                </aside>
                <div class="sc-pdf-job-detail">
                    <?php if ( $job ) : ?>
                        <div class="sc-pdf-job-header" data-sc-bulk-job data-job-id="<?php echo esc_attr( $job_id ); ?>" data-job-status="<?php echo esc_attr( $state['status'] ); ?>">
                            <div><p class="sc-pdf-bulk-kicker"><?php esc_html_e( 'Conversion queue', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $job->post_title ); ?></h2><p data-sc-job-message><?php echo esc_html( $this->job_status_message( $state ) ); ?></p></div>
                            <div class="sc-pdf-job-controls"><button type="button" class="button" data-sc-job-control="pause" <?php disabled( 'running' !== $state['status'] ); ?>><?php esc_html_e( 'Pause', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button button-primary" data-sc-job-control="resume" <?php disabled( ! in_array( $state['status'], array( 'paused', 'stopped', 'complete_with_errors' ), true ) ); ?>><?php esc_html_e( 'Resume', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-job-control="retry" <?php disabled( ! $state['failed'] ); ?>><?php esc_html_e( 'Retry Failed', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-job-control="cancel" <?php disabled( in_array( $state['status'], array( 'complete', 'cancelled' ), true ) ); ?>><?php esc_html_e( 'Cancel', 'sustainable-catalyst-library' ); ?></button><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sc_library_v222_export_job', 'job_id' => $job_id ), admin_url( 'admin-post.php' ) ), 'sc_library_v222_export_job_' . $job_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'sustainable-catalyst-library' ); ?></a></div>
                            <progress value="<?php echo esc_attr( $state['done'] ); ?>" max="<?php echo esc_attr( max( 1, $state['total'] ) ); ?>" data-sc-job-progress></progress>
                            <div class="sc-pdf-job-stats"><span><strong data-sc-job-done><?php echo esc_html( $state['done'] ); ?></strong> <?php esc_html_e( 'complete', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-job-queued><?php echo esc_html( $state['queued'] ); ?></strong> <?php esc_html_e( 'queued', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-job-failed><?php echo esc_html( $state['failed'] ); ?></strong> <?php esc_html_e( 'failed', 'sustainable-catalyst-library' ); ?></span><span><strong data-sc-job-skipped><?php echo esc_html( $state['skipped'] ); ?></strong> <?php esc_html_e( 'skipped', 'sustainable-catalyst-library' ); ?></span></div>
                        </div>
                        <table class="widefat striped sc-pdf-job-items"><thead><tr><th><?php esc_html_e( 'PDF', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'State', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Attempts', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Message', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody data-sc-job-items><?php foreach ( $state['items'] as $index => $item ) : ?><tr data-item-index="<?php echo esc_attr( $index ); ?>"><td><?php echo esc_html( $item['filename'] ?? '' ); ?></td><td><?php if ( ! empty( $item['document_id'] ) ) : ?><a href="<?php echo esc_url( get_edit_post_link( $item['document_id'], 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $item['document_id'] ) ?: sprintf( __( 'Document %d', 'sustainable-catalyst-library' ), $item['document_id'] ) ); ?></a><?php else : ?>—<?php endif; ?></td><td data-item-state><?php echo esc_html( $this->item_state_label( $item['state'] ?? '' ) ); ?></td><td><?php echo esc_html( absint( $item['attempts'] ?? 0 ) ); ?></td><td data-item-message><?php echo esc_html( $item['message'] ?? '' ); ?></td></tr><?php endforeach; ?></tbody></table>
                    <?php else : ?>
                        <div class="sc-document-library__empty"><strong><?php esc_html_e( 'No queue selected.', 'sustainable-catalyst-library' ); ?></strong><p><?php esc_html_e( 'Create a batch from Import PDFs or queue records from Collection Repair.', 'sustainable-catalyst-library' ); ?></p></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }

    public function create_import_job() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You are not allowed to import PDFs.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v222_create_import_job' );
        $attachment_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['attachment_ids'] ?? array() ) ) ) ) );
        if ( ! $attachment_ids ) {
            $this->redirect_notice( 'import', 'no_selection' );
        }
        $batch_action = sanitize_key( wp_unslash( $_POST['batch_action'] ?? 'create_only' ) );
        if ( ! in_array( $batch_action, array( 'create_only', 'create_and_queue' ), true ) ) {
            $batch_action = 'create_only';
        }
        $family_id = absint( wp_unslash( $_POST['family_id'] ?? 0 ) );
        $lifecycle = $this->sanitize_lifecycle( $_POST['lifecycle'] ?? 'current' );
        $items = array();

        foreach ( $attachment_ids as $attachment_id ) {
            $item = $this->build_import_item( $attachment_id, $family_id, $lifecycle, $batch_action );
            $items[] = $item;
        }

        $job_id = $this->create_job(
            sprintf( __( 'PDF Import · %s', 'sustainable-catalyst-library' ), current_time( 'Y-m-d H:i' ) ),
            $items,
            array( 'source' => 'media-library', 'batch_action' => $batch_action, 'family_id' => $family_id, 'lifecycle' => $lifecycle )
        );
        $status = 'create_and_queue' === $batch_action && $this->has_state( $items, 'queued' ) ? 'running' : 'complete';
        update_post_meta( $job_id, self::META_JOB_STATUS, $status );
        $this->job_log( $job_id, 'created', sprintf( __( 'Job created with %d selected PDFs.', 'sustainable-catalyst-library' ), count( $items ) ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'queue', 'job_id' => $job_id, 'sc_v222_notice' => 'job_created' ), admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' ) ) );
        exit;
    }

    public function repair_documents() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair document records.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v222_repair_documents' );
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['document_ids'] ?? array() ) ) ) ) );
        if ( ! $ids ) {
            $this->redirect_notice( 'repair', 'no_selection' );
        }
        $action = sanitize_key( wp_unslash( $_POST['repair_action'] ?? 'safe_repair' ) );
        $family_id = absint( wp_unslash( $_POST['family_id'] ?? 0 ) );
        $lifecycle = $this->sanitize_lifecycle( $_POST['lifecycle'] ?? 'current' );
        $items = array();
        $repaired = 0;

        foreach ( $ids as $post_id ) {
            if ( self::DOCUMENT_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
                continue;
            }
            if ( 'assign_family' === $action ) {
                if ( $family_id && term_exists( $family_id, self::FAMILY_TAX ) ) {
                    wp_set_object_terms( $post_id, array( $family_id ), self::FAMILY_TAX, false );
                    $repaired++;
                }
            } elseif ( 'set_lifecycle' === $action ) {
                update_post_meta( $post_id, self::META_LIFECYCLE, $lifecycle );
                $repaired++;
            } elseif ( in_array( $action, array( 'queue_conversion', 'reprocess' ), true ) ) {
                $attachment_id = $this->document_attachment_id( $post_id );
                if ( $this->valid_pdf( $attachment_id ) ) {
                    if ( 'reprocess' === $action ) {
                        update_post_meta( $post_id, self::META_STATUS, 'pending' );
                        update_post_meta( $post_id, self::META_REVIEWED, 0 );
                    }
                    $items[] = $this->job_item( $attachment_id, $post_id, 'queued', '', 0 );
                } else {
                    $items[] = $this->job_item( $attachment_id, $post_id, 'failed', __( 'The record does not have a usable PDF.', 'sustainable-catalyst-library' ), 0 );
                }
            } else {
                $repaired += $this->safe_repair_document( $post_id ) ? 1 : 0;
            }
        }

        if ( $items ) {
            $job_id = $this->create_job(
                sprintf( __( 'Collection Repair · %s', 'sustainable-catalyst-library' ), current_time( 'Y-m-d H:i' ) ),
                $items,
                array( 'source' => 'collection-repair', 'repair_action' => $action )
            );
            update_post_meta( $job_id, self::META_JOB_STATUS, $this->has_state( $items, 'queued' ) ? 'running' : 'complete_with_errors' );
            wp_safe_redirect( add_query_arg( array( 'tab' => 'queue', 'job_id' => $job_id, 'sc_v222_notice' => 'job_created' ), admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( array( 'tab' => 'repair', 'sc_v222_notice' => 'repaired', 'count' => $repaired ), admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' ) ) );
        exit;
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
        $current_user = get_current_user_id();
        $now = time();
        $selected = null;

        foreach ( $items as $index => &$item ) {
            $state = $item['state'] ?? '';
            $lock_time = isset( $item['lock_time'] ) ? absint( $item['lock_time'] ) : 0;
            $lock_user = isset( $item['lock_user'] ) ? absint( $item['lock_user'] ) : 0;
            $reclaimable = 'processing' === $state && ( $lock_user === $current_user || ! $lock_time || $now - $lock_time > 180 );
            if ( 'queued' === $state || $reclaimable ) {
                $item['state'] = 'processing';
                $item['lock_user'] = $current_user;
                $item['lock_time'] = $now;
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
        $selected['pdf_url'] = wp_get_attachment_url( absint( $selected['attachment_id'] ?? 0 ) );
        $selected['title'] = ! empty( $selected['document_id'] ) ? get_the_title( $selected['document_id'] ) : '';
        wp_send_json_success( array( 'status' => 'running', 'item' => $selected, 'job' => $this->job_state( $job_id ) ) );
    }

    public function ajax_mark_item() {
        $job_id = $this->verify_job_ajax();
        $index = absint( wp_unslash( $_POST['item_index'] ?? -1 ) );
        $state = sanitize_key( wp_unslash( $_POST['item_state'] ?? 'failed' ) );
        $allowed = array( 'complete', 'failed', 'needs_ocr', 'cancelled', 'skipped_duplicate', 'skipped_existing', 'created' );
        if ( ! in_array( $state, $allowed, true ) ) {
            $state = 'failed';
        }
        $message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
        $items = $this->job_items( $job_id );
        if ( ! isset( $items[ $index ] ) ) {
            wp_send_json_error( array( 'message' => __( 'The queue item no longer exists.', 'sustainable-catalyst-library' ) ), 404 );
        }
        $items[ $index ]['state'] = $state;
        $items[ $index ]['message'] = $message;
        $items[ $index ]['attempts'] = absint( $items[ $index ]['attempts'] ?? 0 ) + ( 'failed' === $state ? 1 : 0 );
        $items[ $index ]['updated_at'] = current_time( 'mysql' );
        unset( $items[ $index ]['lock_user'], $items[ $index ]['lock_time'] );
        update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
        $this->job_log( $job_id, $state, sprintf( __( 'Item %1$d: %2$s', 'sustainable-catalyst-library' ), $index + 1, $message ?: $state ) );
        $this->settle_job_status( $job_id, $items );
        wp_send_json_success( $this->job_state( $job_id ) );
    }

    public function ajax_control_job() {
        $job_id = $this->verify_job_ajax();
        $control = sanitize_key( wp_unslash( $_POST['control'] ?? '' ) );
        $items = $this->job_items( $job_id );
        if ( 'pause' === $control ) {
            update_post_meta( $job_id, self::META_JOB_STATUS, 'paused' );
            $this->job_log( $job_id, 'paused', __( 'Queue paused after the active document.', 'sustainable-catalyst-library' ) );
        } elseif ( 'resume' === $control ) {
            update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
            $this->job_log( $job_id, 'resumed', __( 'Queue resumed.', 'sustainable-catalyst-library' ) );
        } elseif ( 'retry' === $control ) {
            foreach ( $items as &$item ) {
                if ( 'failed' === ( $item['state'] ?? '' ) ) {
                    $item['state'] = 'queued';
                    $item['message'] = '';
                    unset( $item['lock_user'], $item['lock_time'] );
                }
            }
            unset( $item );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
            $this->job_log( $job_id, 'retry', __( 'Failed items returned to the queue.', 'sustainable-catalyst-library' ) );
        } elseif ( 'cancel' === $control ) {
            foreach ( $items as &$item ) {
                if ( in_array( $item['state'] ?? '', array( 'queued', 'processing' ), true ) ) {
                    $item['state'] = 'cancelled';
                    $item['message'] = __( 'Cancelled by an administrator.', 'sustainable-catalyst-library' );
                    unset( $item['lock_user'], $item['lock_time'] );
                }
            }
            unset( $item );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
            update_post_meta( $job_id, self::META_JOB_STATUS, 'cancelled' );
            $this->job_log( $job_id, 'cancelled', __( 'Queue cancelled.', 'sustainable-catalyst-library' ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Unknown queue control.', 'sustainable-catalyst-library' ) ), 400 );
        }
        wp_send_json_success( $this->job_state( $job_id ) );
    }

    public function export_job_csv() {
        $job_id = absint( wp_unslash( $_GET['job_id'] ?? 0 ) );
        if ( ! $job_id || self::JOB_TYPE !== get_post_type( $job_id ) || ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You are not allowed to export this job.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v222_export_job_' . $job_id );
        $this->csv_headers( 'pdf-import-job-' . $job_id . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'item', 'attachment_id', 'filename', 'document_id', 'document_title', 'state', 'attempts', 'message', 'updated_at' ) );
        foreach ( $this->job_items( $job_id ) as $index => $item ) {
            fputcsv( $out, array( $index + 1, $item['attachment_id'] ?? '', $item['filename'] ?? '', $item['document_id'] ?? '', ! empty( $item['document_id'] ) ? get_the_title( $item['document_id'] ) : '', $item['state'] ?? '', $item['attempts'] ?? 0, $item['message'] ?? '', $item['updated_at'] ?? '' ) );
        }
        fclose( $out );
        exit;
    }

    public function export_collection_csv() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You are not allowed to export the collection report.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v222_export_collection' );
        $this->csv_headers( 'pdf-document-collection-report.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'document_id', 'title', 'post_status', 'family', 'lifecycle', 'attachment_id', 'filename', 'pdf_status', 'conversion_status', 'checksum', 'issues' ) );
        $ids = get_posts( array( 'post_type' => self::DOCUMENT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
        foreach ( $ids as $post_id ) {
            $state = $this->document_repair_state( $post_id );
            fputcsv( $out, array( $post_id, get_the_title( $post_id ), get_post_status( $post_id ), $state['family'], $state['lifecycle'], $state['attachment_id'], $state['filename'], $state['pdf_status'], get_post_meta( $post_id, self::META_STATUS, true ), get_post_meta( $post_id, self::META_CHECKSUM, true ), implode( '; ', $state['issues'] ) ) );
        }
        fclose( $out );
        exit;
    }

    public function admin_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || false === strpos( (string) $screen->id, 'sc-library-pdf-bulk-import-repair' ) ) {
            return;
        }
        $notice = isset( $_GET['sc_v222_notice'] ) ? sanitize_key( wp_unslash( $_GET['sc_v222_notice'] ) ) : '';
        if ( 'job_created' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Bulk job created. Keep this queue page open while browser conversion runs; it can be paused or resumed later.', 'sustainable-catalyst-library' ) . '</p></div>';
        } elseif ( 'repaired' === $notice ) {
            $count = absint( wp_unslash( $_GET['count'] ?? 0 ) );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Repaired %d document record.', 'Repaired %d document records.', $count, 'sustainable-catalyst-library' ), $count ) ) . '</p></div>';
        } elseif ( 'no_selection' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Select at least one PDF or document record.', 'sustainable-catalyst-library' ) . '</p></div>';
        }
    }

    private function build_import_item( $attachment_id, $family_id, $lifecycle, $batch_action ) {
        if ( ! $this->valid_pdf( $attachment_id ) ) {
            return $this->job_item( $attachment_id, 0, 'failed', __( 'Invalid or missing PDF attachment.', 'sustainable-catalyst-library' ), 0 );
        }
        $checksum = $this->attachment_checksum( $attachment_id );
        $existing = $this->find_document_by_attachment_or_checksum( $attachment_id, $checksum );
        if ( $existing ) {
            return $this->job_item( $attachment_id, $existing, 'skipped_existing', __( 'An existing document already represents this PDF.', 'sustainable-catalyst-library' ), 0 );
        }
        $document_id = wp_insert_post(
            array(
                'post_type'   => self::DOCUMENT_TYPE,
                'post_status' => 'draft',
                'post_title'  => $this->attachment_title( $attachment_id ),
            ),
            true
        );
        if ( is_wp_error( $document_id ) ) {
            return $this->job_item( $attachment_id, 0, 'failed', $document_id->get_error_message(), 1 );
        }
        $this->write_pdf_meta( $document_id, $attachment_id );
        update_post_meta( $document_id, self::META_STATUS, 'pending' );
        update_post_meta( $document_id, self::META_CHECKSUM, $checksum );
        update_post_meta( $document_id, self::META_LIFECYCLE, $lifecycle );
        update_post_meta( $document_id, self::META_REVIEWED, 0 );
        if ( $family_id && term_exists( $family_id, self::FAMILY_TAX ) ) {
            wp_set_object_terms( $document_id, array( $family_id ), self::FAMILY_TAX, false );
        } else {
            $this->assign_default_family( $document_id );
        }
        return $this->job_item( $attachment_id, $document_id, 'create_and_queue' === $batch_action ? 'queued' : 'created', 'create_and_queue' === $batch_action ? __( 'Draft created and queued for conversion.', 'sustainable-catalyst-library' ) : __( 'Draft document record created.', 'sustainable-catalyst-library' ), 0 );
    }

    private function safe_repair_document( $post_id ) {
        $changed = false;
        $attachment_id = $this->document_attachment_id( $post_id );
        if ( $this->valid_pdf( $attachment_id ) ) {
            $this->write_pdf_meta( $post_id, $attachment_id );
            $checksum = $this->attachment_checksum( $attachment_id );
            if ( $checksum && get_post_meta( $post_id, self::META_CHECKSUM, true ) !== $checksum ) {
                update_post_meta( $post_id, self::META_CHECKSUM, $checksum );
                $changed = true;
            }
            if ( ! trim( get_the_title( $post_id ) ) ) {
                wp_update_post( array( 'ID' => $post_id, 'post_title' => $this->attachment_title( $attachment_id ) ) );
                $changed = true;
            }
        }
        $terms = wp_get_object_terms( $post_id, self::FAMILY_TAX, array( 'fields' => 'ids' ) );
        if ( is_wp_error( $terms ) || ! $terms ) {
            $this->assign_default_family( $post_id );
            $changed = true;
        }
        if ( ! get_post_meta( $post_id, self::META_LIFECYCLE, true ) ) {
            update_post_meta( $post_id, self::META_LIFECYCLE, 'current' );
            $changed = true;
        }
        if ( ! get_post_meta( $post_id, self::META_STATUS, true ) ) {
            update_post_meta( $post_id, self::META_STATUS, trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) ? 'legacy_content' : 'pending' );
            $changed = true;
        }
        return $changed;
    }

    private function attachment_inventory_state( $attachment_id ) {
        $checksum = $this->attachment_checksum( $attachment_id );
        $documents = $this->documents_for_attachment_or_checksum( $attachment_id, $checksum );
        if ( count( $documents ) > 1 ) {
            return array( 'status' => 'duplicate', 'label' => __( 'Possible duplicate', 'sustainable-catalyst-library' ), 'message' => sprintf( __( '%d document records share this attachment or checksum.', 'sustainable-catalyst-library' ), count( $documents ) ), 'document_id' => absint( $documents[0] ), 'checksum' => $checksum );
        }
        if ( 1 === count( $documents ) ) {
            return array( 'status' => 'linked', 'label' => __( 'Represented', 'sustainable-catalyst-library' ), 'message' => __( 'A Knowledge Library document already represents this PDF.', 'sustainable-catalyst-library' ), 'document_id' => absint( $documents[0] ), 'checksum' => $checksum );
        }
        return array( 'status' => 'unlinked', 'label' => __( 'Unlinked PDF', 'sustainable-catalyst-library' ), 'message' => __( 'No PDF Document record currently represents this file.', 'sustainable-catalyst-library' ), 'document_id' => 0, 'checksum' => $checksum );
    }

    private function document_repair_state( $post_id ) {
        $issues = array();
        $codes = array();
        $attachment_id = $this->document_attachment_id( $post_id );
        $pdf_status = 'ready';
        $pdf_label = __( 'Ready', 'sustainable-catalyst-library' );
        $filename = '';
        if ( ! $attachment_id ) {
            $pdf_status = 'orphan';
            $pdf_label = __( 'Missing PDF', 'sustainable-catalyst-library' );
            $issues[] = __( 'No PDF attachment ID.', 'sustainable-catalyst-library' );
            $codes[] = 'orphan';
        } elseif ( ! $this->valid_pdf( $attachment_id ) ) {
            $pdf_status = 'orphan';
            $pdf_label = __( 'Broken PDF', 'sustainable-catalyst-library' );
            $issues[] = __( 'The attached PDF is missing, invalid, or has no public URL.', 'sustainable-catalyst-library' );
            $codes[] = 'orphan';
        } else {
            $file = get_attached_file( $attachment_id );
            $filename = $file ? basename( $file ) : '';
        }
        $terms = wp_get_object_terms( $post_id, self::FAMILY_TAX );
        $family = ! is_wp_error( $terms ) && $terms ? $terms[0]->name : '';
        if ( ! $family ) {
            $issues[] = __( 'No document family.', 'sustainable-catalyst-library' );
            $codes[] = 'metadata';
        }
        $lifecycle = sanitize_key( (string) get_post_meta( $post_id, self::META_LIFECYCLE, true ) );
        if ( ! $lifecycle ) {
            $issues[] = __( 'No lifecycle status.', 'sustainable-catalyst-library' );
            $codes[] = 'metadata';
        }
        $status = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
        if ( ! $status ) {
            $issues[] = __( 'No conversion status.', 'sustainable-catalyst-library' );
            $codes[] = 'metadata';
        }
        if ( $attachment_id && $this->valid_pdf( $attachment_id ) && ! get_post_meta( $post_id, self::META_CHECKSUM, true ) ) {
            $issues[] = __( 'No stored PDF checksum.', 'sustainable-catalyst-library' );
            $codes[] = 'metadata';
        }
        if ( ! trim( get_the_title( $post_id ) ) || ! trim( get_post_field( 'post_excerpt', $post_id ) ) ) {
            $issues[] = __( 'Title or listing summary is incomplete.', 'sustainable-catalyst-library' );
            $codes[] = 'metadata';
        }
        if ( $attachment_id && $this->valid_pdf( $attachment_id ) ) {
            $duplicates = $this->documents_for_attachment_or_checksum( $attachment_id, $this->attachment_checksum( $attachment_id ), $post_id );
            if ( $duplicates ) {
                $issues[] = sprintf( __( 'Possible duplicate of document %d.', 'sustainable-catalyst-library' ), $duplicates[0] );
                $codes[] = 'duplicate';
            }
        }
        if ( ! in_array( $status, array( 'ready_review', 'reviewed', 'published', 'legacy_content' ), true ) ) {
            $codes[] = 'conversion';
        }
        return array(
            'attachment_id'   => $attachment_id,
            'pdf_status'      => $pdf_status,
            'pdf_label'       => $pdf_label,
            'filename'        => $filename,
            'family'          => $family,
            'lifecycle'       => $lifecycle,
            'conversion_label'=> $this->conversion_label( $status ),
            'issues'          => array_values( array_unique( $issues ) ),
            'issue_codes'     => array_values( array_unique( $codes ) ),
        );
    }

    private function inventory_counts() {
        $pdf_ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $counts = array( 'pdfs' => count( $pdf_ids ), 'linked' => 0, 'unlinked' => 0, 'duplicates' => 0 );
        foreach ( $pdf_ids as $attachment_id ) {
            $state = $this->attachment_inventory_state( $attachment_id );
            if ( 'linked' === $state['status'] ) { $counts['linked']++; }
            elseif ( 'duplicate' === $state['status'] ) { $counts['duplicates']++; }
            else { $counts['unlinked']++; }
        }
        return $counts;
    }

    private function collection_report() {
        $ids = get_posts( array( 'post_type' => self::DOCUMENT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $report = array( 'records' => count( $ids ), 'orphans' => 0, 'metadata' => 0, 'duplicates' => 0 );
        foreach ( $ids as $post_id ) {
            $state = $this->document_repair_state( $post_id );
            if ( in_array( 'orphan', $state['issue_codes'], true ) ) { $report['orphans']++; }
            if ( in_array( 'metadata', $state['issue_codes'], true ) ) { $report['metadata']++; }
            if ( in_array( 'duplicate', $state['issue_codes'], true ) ) { $report['duplicates']++; }
        }
        return $report;
    }

    private function create_job( $title, $items, $config ) {
        $job_id = wp_insert_post( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'post_title' => $title ), true );
        if ( is_wp_error( $job_id ) ) {
            wp_die( esc_html( $job_id->get_error_message() ) );
        }
        update_post_meta( $job_id, self::META_JOB_ITEMS, array_values( $items ) );
        update_post_meta( $job_id, self::META_JOB_CONFIG, $config );
        update_post_meta( $job_id, self::META_JOB_STATUS, 'created' );
        update_post_meta( $job_id, self::META_JOB_LOG, array() );
        $this->prune_jobs();
        return $job_id;
    }

    private function job_item( $attachment_id, $document_id, $state, $message, $attempts ) {
        $file = $attachment_id ? get_attached_file( $attachment_id ) : '';
        return array(
            'attachment_id' => absint( $attachment_id ),
            'filename'      => $file ? basename( $file ) : '',
            'document_id'   => absint( $document_id ),
            'state'         => sanitize_key( $state ),
            'message'       => sanitize_text_field( $message ),
            'attempts'      => absint( $attempts ),
            'updated_at'    => current_time( 'mysql' ),
        );
    }

    private function job_items( $job_id ) {
        $items = get_post_meta( $job_id, self::META_JOB_ITEMS, true );
        return is_array( $items ) ? array_values( $items ) : array();
    }

    private function job_state( $job_id ) {
        $items = $this->job_items( $job_id );
        $state = array( 'queued' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled' => 0 );
        foreach ( $items as $item ) {
            $item_state = $item['state'] ?? '';
            if ( 'queued' === $item_state ) { $state['queued']++; }
            elseif ( 'processing' === $item_state ) { $state['processing']++; }
            elseif ( in_array( $item_state, array( 'complete', 'created' ), true ) ) { $state['done']++; }
            elseif ( in_array( $item_state, array( 'failed', 'needs_ocr' ), true ) ) { $state['failed']++; }
            elseif ( in_array( $item_state, array( 'skipped_duplicate', 'skipped_existing' ), true ) ) { $state['skipped']++; }
            elseif ( 'cancelled' === $item_state ) { $state['cancelled']++; }
        }
        return array_merge(
            $state,
            array(
                'job_id' => $job_id,
                'status' => sanitize_key( (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) ) ?: 'created',
                'total'  => count( $items ),
                'items'  => $items,
                'logs'   => $this->job_logs( $job_id ),
            )
        );
    }

    private function settle_job_status( $job_id, $items ) {
        $has_queued = $this->has_state( $items, 'queued' ) || $this->has_state( $items, 'processing' );
        if ( $has_queued ) {
            return;
        }
        $has_failed = $this->has_state( $items, 'failed' ) || $this->has_state( $items, 'needs_ocr' );
        update_post_meta( $job_id, self::META_JOB_STATUS, $has_failed ? 'complete_with_errors' : 'complete' );
        $this->job_log( $job_id, $has_failed ? 'complete-with-errors' : 'complete', $has_failed ? __( 'Queue completed with items requiring attention.', 'sustainable-catalyst-library' ) : __( 'Queue completed successfully.', 'sustainable-catalyst-library' ) );
    }

    private function has_state( $items, $state ) {
        foreach ( $items as $item ) {
            if ( $state === ( $item['state'] ?? '' ) ) { return true; }
        }
        return false;
    }

    private function job_log( $job_id, $event, $message ) {
        $logs = $this->job_logs( $job_id );
        $logs[] = array( 'time' => current_time( 'mysql' ), 'event' => sanitize_key( $event ), 'message' => sanitize_text_field( $message ), 'user' => get_current_user_id() );
        if ( count( $logs ) > 100 ) { $logs = array_slice( $logs, -100 ); }
        update_post_meta( $job_id, self::META_JOB_LOG, $logs );
    }

    private function job_logs( $job_id ) {
        $logs = get_post_meta( $job_id, self::META_JOB_LOG, true );
        return is_array( $logs ) ? $logs : array();
    }

    private function verify_job_ajax() {
        check_ajax_referer( 'sc_library_pdf_v222', 'nonce' );
        $job_id = absint( wp_unslash( $_POST['job_id'] ?? 0 ) );
        if ( ! $job_id || self::JOB_TYPE !== get_post_type( $job_id ) || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to manage this queue.', 'sustainable-catalyst-library' ) ), 403 );
        }
        return $job_id;
    }

    private function document_attachment_id( $post_id ) {
        foreach ( self::$compatible_pdf_meta as $key ) {
            $value = absint( get_post_meta( $post_id, $key, true ) );
            if ( $value ) { return $value; }
        }
        return 0;
    }

    private function write_pdf_meta( $post_id, $attachment_id ) {
        foreach ( self::$compatible_pdf_meta as $key ) {
            update_post_meta( $post_id, $key, absint( $attachment_id ) );
        }
    }

    private function documents_for_attachment_or_checksum( $attachment_id, $checksum, $exclude_id = 0 ) {
        $ids = array();
        if ( $attachment_id ) {
            $ids = array_merge( $ids, get_posts( array( 'post_type' => self::DOCUMENT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'post__not_in' => $exclude_id ? array( $exclude_id ) : array(), 'meta_query' => array( array( 'key' => self::META_PDF_ID, 'value' => $attachment_id, 'compare' => '=' ) ) ) ) );
        }
        if ( $checksum ) {
            $ids = array_merge( $ids, get_posts( array( 'post_type' => self::DOCUMENT_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'post__not_in' => $exclude_id ? array( $exclude_id ) : array(), 'meta_query' => array( array( 'key' => self::META_CHECKSUM, 'value' => $checksum, 'compare' => '=' ) ) ) ) );
        }
        return array_values( array_unique( array_map( 'absint', $ids ) ) );
    }

    private function find_document_by_attachment_or_checksum( $attachment_id, $checksum ) {
        $ids = $this->documents_for_attachment_or_checksum( $attachment_id, $checksum );
        return $ids ? absint( $ids[0] ) : 0;
    }

    private function attachment_checksum( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! is_file( $file ) ) { return ''; }
        $mtime = (string) filemtime( $file );
        $cached_mtime = (string) get_post_meta( $attachment_id, self::META_ATTACHMENT_CHECKSUM_MTIME, true );
        $cached = (string) get_post_meta( $attachment_id, self::META_ATTACHMENT_CHECKSUM, true );
        if ( $cached && hash_equals( $cached_mtime, $mtime ) ) { return $cached; }
        $checksum = hash_file( 'sha256', $file );
        update_post_meta( $attachment_id, self::META_ATTACHMENT_CHECKSUM, $checksum );
        update_post_meta( $attachment_id, self::META_ATTACHMENT_CHECKSUM_MTIME, $mtime );
        return $checksum;
    }

    private function valid_pdf( $attachment_id ) {
        return $attachment_id && 'attachment' === get_post_type( $attachment_id ) && 'application/pdf' === get_post_mime_type( $attachment_id ) && wp_get_attachment_url( $attachment_id );
    }

    private function attachment_title( $attachment_id ) {
        $attachment = get_post( $attachment_id );
        if ( $attachment && trim( $attachment->post_title ) ) { return sanitize_text_field( $attachment->post_title ); }
        $file = get_attached_file( $attachment_id );
        return $file ? sanitize_text_field( ucwords( str_replace( array( '-', '_' ), ' ', pathinfo( basename( $file ), PATHINFO_FILENAME ) ) ) ) : __( 'PDF Document', 'sustainable-catalyst-library' );
    }

    private function assign_default_family( $post_id ) {
        $term = get_term_by( 'slug', self::DEFAULT_FAMILY, self::FAMILY_TAX );
        if ( $term ) { wp_set_object_terms( $post_id, array( $term->term_id ), self::FAMILY_TAX, false ); }
    }

    private function conversion_label( $status ) {
        $labels = array( 'pending' => __( 'Not converted', 'sustainable-catalyst-library' ), 'extracting' => __( 'Converting', 'sustainable-catalyst-library' ), 'ready_review' => __( 'Ready for review', 'sustainable-catalyst-library' ), 'reviewed' => __( 'Reviewed', 'sustainable-catalyst-library' ), 'published' => __( 'Published', 'sustainable-catalyst-library' ), 'needs_ocr' => __( 'Needs OCR', 'sustainable-catalyst-library' ), 'failed' => __( 'Failed', 'sustainable-catalyst-library' ), 'legacy_content' => __( 'Existing content', 'sustainable-catalyst-library' ) );
        return $labels[ $status ] ?? __( 'Not converted', 'sustainable-catalyst-library' );
    }

    private function item_state_label( $state ) {
        $labels = array( 'queued' => __( 'Queued', 'sustainable-catalyst-library' ), 'processing' => __( 'Processing', 'sustainable-catalyst-library' ), 'complete' => __( 'Converted', 'sustainable-catalyst-library' ), 'created' => __( 'Draft created', 'sustainable-catalyst-library' ), 'failed' => __( 'Failed', 'sustainable-catalyst-library' ), 'needs_ocr' => __( 'Needs OCR', 'sustainable-catalyst-library' ), 'skipped_duplicate' => __( 'Duplicate skipped', 'sustainable-catalyst-library' ), 'skipped_existing' => __( 'Existing record', 'sustainable-catalyst-library' ), 'cancelled' => __( 'Cancelled', 'sustainable-catalyst-library' ) );
        return $labels[ $state ] ?? ucfirst( str_replace( '_', ' ', $state ) );
    }

    private function job_status_label( $status ) {
        $labels = array( 'created' => __( 'Created', 'sustainable-catalyst-library' ), 'running' => __( 'Running', 'sustainable-catalyst-library' ), 'paused' => __( 'Paused', 'sustainable-catalyst-library' ), 'complete' => __( 'Complete', 'sustainable-catalyst-library' ), 'complete_with_errors' => __( 'Complete with errors', 'sustainable-catalyst-library' ), 'cancelled' => __( 'Cancelled', 'sustainable-catalyst-library' ) );
        return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
    }

    private function job_status_message( $state ) {
        if ( 'running' === $state['status'] ) { return __( 'This browser processes one document at a time. The job can resume after closing the page.', 'sustainable-catalyst-library' ); }
        if ( 'paused' === $state['status'] ) { return __( 'The queue is paused. Resume when ready.', 'sustainable-catalyst-library' ); }
        if ( 'complete_with_errors' === $state['status'] ) { return __( 'The queue finished, but some records require attention. Export the CSV for details.', 'sustainable-catalyst-library' ); }
        if ( 'complete' === $state['status'] ) { return __( 'All queued work is complete.', 'sustainable-catalyst-library' ); }
        return __( 'Review the item states and queue controls below.', 'sustainable-catalyst-library' );
    }

    private function lifecycle_labels() {
        return array( 'current' => __( 'Current', 'sustainable-catalyst-library' ), 'superseded' => __( 'Superseded', 'sustainable-catalyst-library' ), 'archived' => __( 'Archived', 'sustainable-catalyst-library' ), 'historical' => __( 'Historical', 'sustainable-catalyst-library' ) );
    }

    private function issue_labels() {
        return array( 'all' => __( 'All records', 'sustainable-catalyst-library' ), 'orphan' => __( 'Missing or broken PDF', 'sustainable-catalyst-library' ), 'metadata' => __( 'Missing metadata', 'sustainable-catalyst-library' ), 'duplicate' => __( 'Possible duplicate', 'sustainable-catalyst-library' ), 'conversion' => __( 'Conversion needed', 'sustainable-catalyst-library' ) );
    }

    private function sanitize_lifecycle( $value ) {
        $value = sanitize_key( wp_unslash( $value ) );
        return array_key_exists( $value, $this->lifecycle_labels() ) ? $value : 'current';
    }

    private function csv_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    }

    private function redirect_notice( $tab, $notice ) {
        wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'sc_v222_notice' => $notice ), admin_url( 'admin.php?page=sc-library-pdf-bulk-import-repair' ) ) );
        exit;
    }

    private function prune_jobs() {
        $jobs = get_posts( array( 'post_type' => self::JOB_TYPE, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
        foreach ( array_slice( $jobs, self::JOB_LIMIT ) as $job_id ) { wp_delete_post( $job_id, true ); }
    }

    private function version() {
        return defined( 'SC_LIBRARY_VERSION' ) ? SC_LIBRARY_VERSION : self::VERSION;
    }
}
