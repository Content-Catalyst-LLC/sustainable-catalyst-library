<?php
/**
 * Research Librarian Document Intelligence.
 *
 * Adds deterministic document profiles, section and chunk indexing,
 * title-aware retrieval, summaries, key points, questions, gap signals,
 * document comparison, Research Librarian context, reindexing, REST,
 * shortcodes, and WP-CLI.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Research_Librarian_Document_Intelligence {
    public const VERSION = '3.7.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const JOB_POST_TYPE = 'sc_doc_intel_job';
    public const COMPARISON_POST_TYPE = 'sc_doc_compare';

    public const PROFILE_SCHEMA = 'sc-library-document-intelligence/1.0';
    public const SECTION_SCHEMA = 'sc-library-document-section-index/1.0';
    public const CHUNK_SCHEMA = 'sc-library-document-chunk-index/1.0';
    public const RETRIEVAL_SCHEMA = 'sc-library-title-aware-retrieval/1.0';
    public const COMPARISON_SCHEMA = 'sc-library-document-comparison/1.0';
    public const HANDOFF_SCHEMA = 'sc-platform-handoff/research-librarian-document/1.0';
    public const DASHBOARD_SCHEMA = 'sc-library-document-intelligence-dashboard/1.0';

    public const META_STATUS = '_sc_document_intelligence_status';
    public const META_PROFILE = '_sc_document_intelligence_profile';
    public const META_SOURCE_HASH = '_sc_document_intelligence_source_hash';
    public const META_ANALYZER = '_sc_document_intelligence_analyzer';
    public const META_ANALYZED_AT = '_sc_document_intelligence_analyzed_at';
    public const META_ANALYZED_BY = '_sc_document_intelligence_analyzed_by';
    public const META_SECTIONS = '_sc_document_intelligence_sections';
    public const META_CHUNKS = '_sc_document_intelligence_chunks';
    public const META_SUMMARY = '_sc_document_intelligence_summary';
    public const META_KEY_POINTS = '_sc_document_intelligence_key_points';
    public const META_QUESTIONS = '_sc_document_intelligence_questions';
    public const META_TERMS = '_sc_document_intelligence_terms';
    public const META_TITLE_ALIASES = '_sc_document_intelligence_title_aliases';
    public const META_GAPS = '_sc_document_intelligence_gaps';
    public const META_CITATION_SIGNALS = '_sc_document_intelligence_citation_signals';
    public const META_PUBLIC = '_sc_document_intelligence_public';
    public const META_EXCLUDED = '_sc_document_intelligence_excluded';
    public const META_ERROR = '_sc_document_intelligence_error';
    public const META_STALE_REASON = '_sc_document_intelligence_stale_reason';
    public const META_INDEX_VERSION = '_sc_document_intelligence_index_version';
    public const META_MIGRATED = '_sc_document_intelligence_v370_migrated';

    public const META_JOB_ITEMS = '_sc_doc_intel_job_items';
    public const META_JOB_STATUS = '_sc_doc_intel_job_status';
    public const META_JOB_CURSOR = '_sc_doc_intel_job_cursor';
    public const META_JOB_CONFIG = '_sc_doc_intel_job_config';
    public const META_JOB_LOG = '_sc_doc_intel_job_log';

    public const META_COMPARISON_DOCUMENT_IDS = '_sc_doc_compare_document_ids';
    public const META_COMPARISON_RESULT = '_sc_doc_compare_result';
    public const META_COMPARISON_CREATED_AT = '_sc_doc_compare_created_at';
    public const META_COMPARISON_CREATED_BY = '_sc_doc_compare_created_by';

    public const OPTION_MIGRATION = 'sc_library_v370_document_intelligence_migration';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v370_document_intelligence_migration_lock';
    public const TRANSIENT_DASHBOARD = 'sc_library_v370_document_intelligence_dashboard';
    public const CRON_MIGRATION = 'sc_library_v370_document_intelligence_migration_tick';
    public const CRON_STALE = 'sc_library_v370_document_intelligence_stale_tick';

    public const MIGRATION_BATCH = 20;
    public const LOCK_SECONDS = 180;
    public const MAX_SOURCE_CHARS = 500000;
    public const MAX_SECTIONS = 120;
    public const MAX_CHUNKS = 500;
    public const CHUNK_WORDS = 220;
    public const CHUNK_OVERLAP = 40;
    public const MAX_KEY_POINTS = 8;
    public const MAX_QUESTIONS = 8;
    public const MAX_TERMS = 40;
    public const MAX_ALIASES = 20;
    public const MAX_GAPS = 20;
    public const MAX_LOG = 100;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_record_types' ), 340 );
        add_action( 'init', array( $this, 'schedule_operations' ), 997 );
        add_action( self::CRON_MIGRATION, array( $this, 'run_scheduled_migration' ) );
        add_action( self::CRON_STALE, array( $this, 'run_stale_reindex' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 340 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 240 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 240 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action(
            'save_post_' . SC_Library_Foundation_Pages::POST_TYPE,
            array( $this, 'mark_document_stale_on_save' ),
            999,
            3
        );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_document' ) );

        add_action( 'wp_ajax_sc_library_v370_analyze_document', array( $this, 'ajax_analyze_document' ) );
        add_action( 'wp_ajax_sc_library_v370_run_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_sc_library_v370_compare_documents', array( $this, 'ajax_compare_documents' ) );
        add_action( 'wp_ajax_sc_library_v370_search_documents', array( $this, 'ajax_search_documents' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 100, 3 );

        add_filter(
            'sc_library_research_librarian_document_context',
            array( $this, 'filter_document_context' ),
            20,
            3
        );
        add_filter(
            'sc_library_research_librarian_project_context',
            array( $this, 'filter_project_context' ),
            40,
            3
        );

        add_shortcode( 'sc_document_intelligence', array( $this, 'shortcode_document_intelligence' ) );
        add_shortcode( 'sc_document_key_points', array( $this, 'shortcode_key_points' ) );
        add_shortcode( 'sc_document_research_questions', array( $this, 'shortcode_questions' ) );
        add_shortcode( 'sc_document_comparison', array( $this, 'shortcode_comparison' ) );

        self::register_cli_commands();
    }

    public static function statuses() {
        return array(
            'pending'   => __( 'Pending', 'sustainable-catalyst-library' ),
            'indexing'  => __( 'Indexing', 'sustainable-catalyst-library' ),
            'ready'     => __( 'Ready', 'sustainable-catalyst-library' ),
            'partial'   => __( 'Partial', 'sustainable-catalyst-library' ),
            'stale'     => __( 'Stale', 'sustainable-catalyst-library' ),
            'failed'    => __( 'Failed', 'sustainable-catalyst-library' ),
            'excluded'  => __( 'Excluded', 'sustainable-catalyst-library' ),
        );
    }

    public function register_record_types() {
        register_post_type(
            self::JOB_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Document Intelligence Jobs', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Document Intelligence Job', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'show_ui'             => false,
                'show_in_rest'        => false,
                'supports'            => array( 'title', 'author' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'exclude_from_search' => true,
            )
        );

        register_post_type(
            self::COMPARISON_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Document Comparisons', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Document Comparison', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'show_ui'             => false,
                'show_in_rest'        => false,
                'supports'            => array( 'title', 'author' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'exclude_from_search' => true,
            )
        );
    }

    public function schedule_operations() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_MIGRATION ) ) {
            wp_schedule_event( time() + 900, 'hourly', self::CRON_MIGRATION );
        }
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_STALE ) ) {
            wp_schedule_event( time() + 3600, 'daily', self::CRON_STALE );
        }
        $state = get_option( self::OPTION_MIGRATION, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_MIGRATION, self::default_migration_state(), false );
        }
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Research Librarian Document Intelligence', 'sustainable-catalyst-library' ),
            __( 'Document Intelligence', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-document-intelligence',
            array( $this, 'render_workspace' )
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-document-intelligence-profile',
            __( 'Research Librarian Document Intelligence', 'sustainable-catalyst-library' ),
            array( $this, 'render_document_meta_box' ),
            SC_Library_Foundation_Pages::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-document-intelligence-status',
            __( 'Intelligence Status', 'sustainable-catalyst-library' ),
            array( $this, 'render_document_status_box' ),
            SC_Library_Foundation_Pages::POST_TYPE,
            'side',
            'high'
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $document = SC_Library_Foundation_Pages::POST_TYPE === $screen->post_type;
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-document-intelligence' );
        if ( ! $document && ! $workspace ) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style( 'sc-library-document-intelligence' );
        wp_enqueue_script( 'sc-library-document-intelligence' );
        wp_localize_script(
            'sc-library-document-intelligence',
            'SCLibraryDocumentIntelligence',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_document_intelligence_v370' ),
                'strings' => array(
                    'working'  => __( 'Analyzing document…', 'sustainable-catalyst-library' ),
                    'complete' => __( 'Document intelligence updated.', 'sustainable-catalyst-library' ),
                    'error'    => __( 'The document-intelligence operation failed.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function register_public_assets() {
        $this->register_assets();
    }

    private function register_assets() {
        wp_register_style(
            'sc-library-document-intelligence',
            SC_LIBRARY_URL . 'assets/css/sc-library-research-librarian-document-intelligence.css',
            array(),
            self::VERSION
        );
        wp_register_script(
            'sc-library-document-intelligence',
            SC_LIBRARY_URL . 'assets/js/sc-library-research-librarian-document-intelligence.js',
            array(),
            self::VERSION,
            true
        );
    }

    public static function analyze_document( $document_id, $persist = true, $force = false ) {
        $document_id = absint( $document_id );
        if ( SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
            return new WP_Error(
                'invalid_intelligence_document',
                __( 'The Knowledge Library document is invalid.', 'sustainable-catalyst-library' ),
                array( 'status' => 404 )
            );
        }

        if ( '1' === get_post_meta( $document_id, self::META_EXCLUDED, true ) ) {
            if ( $persist ) {
                update_post_meta( $document_id, self::META_STATUS, 'excluded' );
            }
            return array(
                'schema'      => self::PROFILE_SCHEMA,
                'document_id' => $document_id,
                'status'      => 'excluded',
                'message'     => __( 'This document is excluded from document intelligence.', 'sustainable-catalyst-library' ),
            );
        }

        $source = self::document_source( $document_id );
        $source_hash = hash( 'sha256', $source['title'] . "\n" . $source['text'] );
        $existing_hash = (string) get_post_meta( $document_id, self::META_SOURCE_HASH, true );
        $existing = get_post_meta( $document_id, self::META_PROFILE, true );

        if ( ! $force && $source_hash === $existing_hash && is_array( $existing ) && 'ready' === ( $existing['status'] ?? '' ) ) {
            return $existing;
        }

        if ( $persist ) {
            update_post_meta( $document_id, self::META_STATUS, 'indexing' );
            delete_post_meta( $document_id, self::META_ERROR );
        }

        try {
            $sections = self::extract_sections( $source['content'], $source['text'] );
            $chunks = self::build_chunks( $sections );
            $summary = self::build_summary( $source['text'], $source['title'] );
            $key_points = self::build_key_points( $source['text'], $summary );
            $terms = self::extract_terms( $source['text'], $source['title'] );
            $questions = self::build_questions( $source['title'], $sections, $terms, $key_points );
            $citation_signals = self::citation_signals( $source['text'] );
            $aliases = self::title_aliases( $document_id, $source['title'], $sections );
            $gaps = self::gap_signals(
                $document_id,
                $source,
                $sections,
                $chunks,
                $citation_signals,
                $terms
            );

            $status = strlen( trim( $source['text'] ) ) < 250 || ! $chunks ? 'partial' : 'ready';
            $topic_ids = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
                ? wp_get_object_terms(
                    $document_id,
                    SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY,
                    array( 'fields' => 'ids' )
                )
                : array();
            $topic_ids = is_wp_error( $topic_ids ) ? array() : self::id_list( $topic_ids );
            $concept_ids = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
                ? self::id_list(
                    get_post_meta(
                        $document_id,
                        SC_Library_Topics_Concepts_Relationships::META_CONCEPT_IDS,
                        true
                    )
                )
                : array();
            $entity_ids = class_exists( 'SC_Library_Topics_Concepts_Relationships' )
                ? self::id_list(
                    get_post_meta(
                        $document_id,
                        SC_Library_Topics_Concepts_Relationships::META_ENTITY_IDS,
                        true
                    )
                )
                : array();

            $profile = array(
                'schema'            => self::PROFILE_SCHEMA,
                'document_id'       => $document_id,
                'document_title'    => $source['title'],
                'document_url'      => 'publish' === get_post_status( $document_id ) ? get_permalink( $document_id ) : '',
                'status'            => $status,
                'status_label'      => self::statuses()[ $status ] ?? $status,
                'source_hash'       => $source_hash,
                'source_kind'       => $source['kind'],
                'source_characters' => strlen( $source['text'] ),
                'source_truncated'  => $source['truncated'],
                'page_count'        => $source['page_count'],
                'section_count'     => count( $sections ),
                'chunk_count'       => count( $chunks ),
                'summary'           => $summary,
                'key_points'        => $key_points,
                'suggested_questions' => $questions,
                'terms'             => $terms,
                'title_aliases'     => $aliases,
                'gaps'              => $gaps,
                'citation_signals'  => $citation_signals,
                'topic_ids'         => $topic_ids,
                'concept_ids'       => $concept_ids,
                'entity_ids'        => $entity_ids,
                'analyzer'          => 'deterministic-structure-and-retrieval',
                'analyzer_version'  => self::VERSION,
                'analyzed_at'       => current_time( 'mysql' ),
                'analyzed_by'       => get_current_user_id(),
            );

            /**
             * Allows a trusted local or remote provider adapter to enrich the
             * deterministic profile. Adapters must preserve the base schema,
             * document ID, source hash, and private/public boundaries.
             */
            $filtered = apply_filters(
                'sc_library_document_intelligence_analysis',
                $profile,
                $document_id,
                array(
                    'source'   => $source,
                    'sections' => $sections,
                    'chunks'   => $chunks,
                )
            );
            if ( is_array( $filtered ) ) {
                $profile = array_merge( $profile, $filtered );
                $profile['schema'] = self::PROFILE_SCHEMA;
                $profile['document_id'] = $document_id;
                $profile['source_hash'] = $source_hash;
            }

            if ( $persist ) {
                update_post_meta( $document_id, self::META_SOURCE_HASH, $source_hash );
                update_post_meta( $document_id, self::META_STATUS, $profile['status'] );
                update_post_meta( $document_id, self::META_PROFILE, $profile );
                update_post_meta( $document_id, self::META_SECTIONS, $sections );
                update_post_meta( $document_id, self::META_CHUNKS, $chunks );
                update_post_meta( $document_id, self::META_SUMMARY, $profile['summary'] );
                update_post_meta( $document_id, self::META_KEY_POINTS, $profile['key_points'] );
                update_post_meta( $document_id, self::META_QUESTIONS, $profile['suggested_questions'] );
                update_post_meta( $document_id, self::META_TERMS, $profile['terms'] );
                update_post_meta( $document_id, self::META_TITLE_ALIASES, $profile['title_aliases'] );
                update_post_meta( $document_id, self::META_GAPS, $profile['gaps'] );
                update_post_meta( $document_id, self::META_CITATION_SIGNALS, $profile['citation_signals'] );
                update_post_meta( $document_id, self::META_ANALYZER, $profile['analyzer'] );
                update_post_meta( $document_id, self::META_ANALYZED_AT, $profile['analyzed_at'] );
                update_post_meta( $document_id, self::META_ANALYZED_BY, $profile['analyzed_by'] );
                update_post_meta( $document_id, self::META_INDEX_VERSION, self::VERSION );
                delete_post_meta( $document_id, self::META_STALE_REASON );
                delete_post_meta( $document_id, self::META_ERROR );
                delete_transient( self::TRANSIENT_DASHBOARD );
            }

            return $profile;
        } catch ( Throwable $error ) {
            if ( $persist ) {
                update_post_meta( $document_id, self::META_STATUS, 'failed' );
                update_post_meta( $document_id, self::META_ERROR, sanitize_text_field( $error->getMessage() ) );
                delete_transient( self::TRANSIENT_DASHBOARD );
            }
            return new WP_Error(
                'document_intelligence_failed',
                $error->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    public static function document_source( $document_id ) {
        $title = html_entity_decode(
            wp_strip_all_tags( get_the_title( $document_id ) ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $content = (string) get_post_field( 'post_content', $document_id );
        $raw_text = (string) get_post_meta(
            $document_id,
            SC_Library_PDF_To_Document::META_RAW_TEXT,
            true
        );
        $kind = 'document-content';
        $text = trim( $raw_text );

        if ( '' !== $text ) {
            $kind = 'extracted-text';
        } else {
            $text = self::html_to_text( $content );
        }

        $truncated = false;
        if ( strlen( $text ) > self::MAX_SOURCE_CHARS ) {
            $text = substr( $text, 0, self::MAX_SOURCE_CHARS );
            $truncated = true;
        }

        $text = self::normalize_text( $text );
        return array(
            'title'      => trim( $title ),
            'content'    => $content,
            'text'       => $text,
            'kind'       => $kind,
            'truncated'  => $truncated,
            'page_count' => absint(
                get_post_meta(
                    $document_id,
                    SC_Library_PDF_To_Document::META_PAGE_COUNT,
                    true
                )
            ),
        );
    }

    public static function extract_sections( $html, $plain_text ) {
        $sections = array();
        $marked = preg_replace_callback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/is',
            static function ( $matches ) {
                $title = trim(
                    html_entity_decode(
                        wp_strip_all_tags( $matches[2] ),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )
                );
                return "\n[[SC_HEADING|" . absint( $matches[1] ) . '|' . str_replace( array( "\r", "\n", ']' ), ' ', $title ) . "]]\n";
            },
            (string) $html
        );
        $marked_text = self::normalize_text( self::html_to_text( $marked ) );

        if ( false !== strpos( $marked_text, '[[SC_HEADING|' ) ) {
            $parts = preg_split(
                '/\[\[SC_HEADING\|([1-6])\|([^\]]+)\]\]/',
                $marked_text,
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );
            $intro = trim( array_shift( $parts ) );
            if ( $intro ) {
                $sections[] = self::section_record( 'Overview', 1, $intro, 0 );
            }
            while ( count( $parts ) >= 3 && count( $sections ) < self::MAX_SECTIONS ) {
                $level = absint( array_shift( $parts ) );
                $title = sanitize_text_field( array_shift( $parts ) );
                $text = trim( array_shift( $parts ) );
                if ( $title || $text ) {
                    $sections[] = self::section_record(
                        $title ?: __( 'Untitled section', 'sustainable-catalyst-library' ),
                        max( 1, min( 6, $level ) ),
                        $text,
                        count( $sections )
                    );
                }
            }
        }

        if ( ! $sections ) {
            $lines = preg_split( '/\n+/', self::normalize_text( $plain_text ) );
            $current_title = 'Overview';
            $current = array();
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( '' === $line ) {
                    continue;
                }
                if (
                    strlen( $line ) <= 110
                    && count( preg_split( '/\s+/', $line ) ) <= 14
                    && ! preg_match( '/[.!?;:]$/', $line )
                    && (
                        preg_match( '/^\d+(?:\.\d+)*\s+\S+/', $line )
                        || preg_match( '/^[A-Z][A-Z0-9\s,&\-\/]{4,}$/', $line )
                    )
                ) {
                    if ( $current && count( $sections ) < self::MAX_SECTIONS ) {
                        $sections[] = self::section_record(
                            $current_title,
                            2,
                            implode( "\n", $current ),
                            count( $sections )
                        );
                    }
                    $current_title = $line;
                    $current = array();
                } else {
                    $current[] = $line;
                }
            }
            if ( $current && count( $sections ) < self::MAX_SECTIONS ) {
                $sections[] = self::section_record(
                    $current_title,
                    2,
                    implode( "\n", $current ),
                    count( $sections )
                );
            }
        }

        if ( ! $sections && trim( $plain_text ) ) {
            $sections[] = self::section_record(
                'Overview',
                1,
                trim( $plain_text ),
                0
            );
        }

        return array_slice( $sections, 0, self::MAX_SECTIONS );
    }

    private static function section_record( $title, $level, $text, $index ) {
        $text = trim( self::normalize_text( $text ) );
        return array(
            'schema'       => self::SECTION_SCHEMA,
            'section_id'   => 'section-' . ( absint( $index ) + 1 ),
            'index'        => absint( $index ),
            'title'        => sanitize_text_field( $title ),
            'level'        => absint( $level ),
            'text'         => $text,
            'word_count'   => self::word_count( $text ),
            'text_hash'    => hash( 'sha256', $text ),
        );
    }

    public static function build_chunks( $sections ) {
        $chunks = array();
        foreach ( (array) $sections as $section ) {
            $words = preg_split( '/\s+/', trim( (string) ( $section['text'] ?? '' ) ) );
            $words = array_values( array_filter( $words, 'strlen' ) );
            if ( ! $words ) {
                continue;
            }

            $step = max( 1, self::CHUNK_WORDS - self::CHUNK_OVERLAP );
            for ( $offset = 0; $offset < count( $words ); $offset += $step ) {
                if ( count( $chunks ) >= self::MAX_CHUNKS ) {
                    break 2;
                }
                $slice = array_slice( $words, $offset, self::CHUNK_WORDS );
                if ( ! $slice ) {
                    break;
                }
                $text = implode( ' ', $slice );
                $chunks[] = array(
                    'schema'       => self::CHUNK_SCHEMA,
                    'chunk_id'     => 'chunk-' . ( count( $chunks ) + 1 ),
                    'index'        => count( $chunks ),
                    'section_id'   => (string) ( $section['section_id'] ?? '' ),
                    'section_title'=> (string) ( $section['title'] ?? '' ),
                    'word_start'   => $offset,
                    'word_end'     => $offset + count( $slice ) - 1,
                    'word_count'   => count( $slice ),
                    'text'         => $text,
                    'text_hash'    => hash( 'sha256', $text ),
                );
                if ( count( $slice ) < self::CHUNK_WORDS ) {
                    break;
                }
            }
        }
        return $chunks;
    }

    public static function build_summary( $text, $title = '' ) {
        $sentences = self::sentence_split( $text );
        if ( ! $sentences ) {
            return '';
        }

        $title_terms = self::tokenize( $title );
        $scored = array();
        foreach ( array_slice( $sentences, 0, 80 ) as $index => $sentence ) {
            $length = strlen( $sentence );
            if ( $length < 45 || $length > 420 ) {
                continue;
            }
            $score = max( 0, 30 - $index );
            $tokens = self::tokenize( $sentence );
            $score += count( array_intersect( $title_terms, $tokens ) ) * 8;
            if ( preg_match( '/\b(purpose|examines|explores|provides|presents|describes|finds|concludes|argues|documents)\b/i', $sentence ) ) {
                $score += 10;
            }
            if ( preg_match( '/\b(table of contents|copyright|all rights reserved|isbn)\b/i', $sentence ) ) {
                $score -= 30;
            }
            $scored[] = array(
                'index'    => $index,
                'score'    => $score,
                'sentence' => $sentence,
            );
        }

        usort(
            $scored,
            static function ( $a, $b ) {
                $score = $b['score'] <=> $a['score'];
                return 0 !== $score ? $score : $a['index'] <=> $b['index'];
            }
        );
        $selected = array_slice( $scored, 0, 4 );
        usort(
            $selected,
            static function ( $a, $b ) {
                return $a['index'] <=> $b['index'];
            }
        );
        return trim( implode( ' ', array_column( $selected, 'sentence' ) ) );
    }

    public static function build_key_points( $text, $summary = '' ) {
        $sentences = self::sentence_split( $text );
        $summary_sentences = self::sentence_split( $summary );
        $terms = self::extract_terms( $text, '', 20 );
        $term_words = array_column( $terms, 'term' );
        $scored = array();

        foreach ( array_slice( $sentences, 0, 250 ) as $index => $sentence ) {
            $length = strlen( $sentence );
            if ( $length < 55 || $length > 360 || in_array( $sentence, $summary_sentences, true ) ) {
                continue;
            }
            $tokens = self::tokenize( $sentence );
            $score = count( array_intersect( $term_words, $tokens ) ) * 3;
            if ( preg_match( '/\b(finds?|shows?|demonstrates?|indicates?|requires?|recommends?|concludes?|important|significant|primary|central|key)\b/i', $sentence ) ) {
                $score += 12;
            }
            if ( preg_match( '/\b(however|therefore|because|while|although|in contrast)\b/i', $sentence ) ) {
                $score += 4;
            }
            if ( preg_match( '/\b(copyright|table of contents|references|bibliography)\b/i', $sentence ) ) {
                $score -= 20;
            }
            $scored[] = array(
                'index'    => $index,
                'score'    => $score,
                'sentence' => $sentence,
            );
        }

        usort(
            $scored,
            static function ( $a, $b ) {
                $score = $b['score'] <=> $a['score'];
                return 0 !== $score ? $score : $a['index'] <=> $b['index'];
            }
        );

        $points = array();
        $seen = array();
        foreach ( $scored as $item ) {
            $fingerprint = implode( ' ', array_slice( self::tokenize( $item['sentence'] ), 0, 12 ) );
            if ( ! $fingerprint || isset( $seen[ $fingerprint ] ) ) {
                continue;
            }
            $seen[ $fingerprint ] = true;
            $points[] = $item['sentence'];
            if ( count( $points ) >= self::MAX_KEY_POINTS ) {
                break;
            }
        }
        return $points;
    }

    public static function build_questions( $title, $sections, $terms, $key_points ) {
        $questions = array();
        $title = trim( $title );
        if ( $title ) {
            $questions[] = sprintf(
                __( 'What is the central argument or purpose of “%s”?', 'sustainable-catalyst-library' ),
                $title
            );
            $questions[] = sprintf(
                __( 'What evidence and sources does “%s” rely on?', 'sustainable-catalyst-library' ),
                $title
            );
        }

        foreach ( array_slice( (array) $sections, 0, 4 ) as $section ) {
            $section_title = trim( (string) ( $section['title'] ?? '' ) );
            if ( $section_title && ! in_array( strtolower( $section_title ), array( 'overview', 'introduction' ), true ) ) {
                $questions[] = sprintf(
                    __( 'What does the document explain about %s?', 'sustainable-catalyst-library' ),
                    rtrim( $section_title, '.:?' )
                );
            }
        }

        foreach ( array_slice( (array) $terms, 0, 3 ) as $term ) {
            $label = trim( (string) ( $term['label'] ?? $term['term'] ?? '' ) );
            if ( $label ) {
                $questions[] = sprintf(
                    __( 'How does the document define or use %s?', 'sustainable-catalyst-library' ),
                    $label
                );
            }
        }

        if ( $key_points ) {
            $questions[] = __( 'Which conclusions are strongest, and what limitations qualify them?', 'sustainable-catalyst-library' );
        }
        $questions[] = __( 'What related documents, concepts, or Research Sources should be consulted next?', 'sustainable-catalyst-library' );

        return array_slice(
            array_values(
                array_unique(
                    array_filter(
                        array_map( 'sanitize_text_field', $questions )
                    )
                )
            ),
            0,
            self::MAX_QUESTIONS
        );
    }

    public static function extract_terms( $text, $title = '', $limit = self::MAX_TERMS ) {
        $tokens = self::tokenize( $title . ' ' . $text );
        $counts = array_count_values( $tokens );
        foreach ( self::stopwords() as $stopword ) {
            unset( $counts[ $stopword ] );
        }

        arsort( $counts );
        $terms = array();
        foreach ( $counts as $term => $count ) {
            if ( strlen( $term ) < 4 || $count < 2 ) {
                continue;
            }
            $terms[] = array(
                'term'  => $term,
                'label' => ucwords( str_replace( '-', ' ', $term ) ),
                'count' => absint( $count ),
            );
            if ( count( $terms ) >= max( 1, absint( $limit ) ) ) {
                break;
            }
        }
        return $terms;
    }

    public static function title_aliases( $document_id, $title, $sections ) {
        $aliases = array(
            trim( $title ),
            trim( str_replace( array( '–', '—', ':' ), '-', $title ) ),
            trim( str_replace( '-', ' ', (string) get_post_field( 'post_name', $document_id ) ) ),
        );

        $identifier = (string) get_post_meta(
            $document_id,
            SC_Library_PDF_To_Document::META_VERSION,
            true
        );
        if ( $identifier ) {
            $aliases[] = trim( $title . ' ' . $identifier );
        }

        foreach ( array_slice( (array) $sections, 0, 3 ) as $section ) {
            $heading = trim( (string) ( $section['title'] ?? '' ) );
            if ( $heading && 'overview' !== strtolower( $heading ) ) {
                $aliases[] = trim( $title . ': ' . $heading );
            }
        }

        if ( class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            $topic_names = wp_get_object_terms(
                $document_id,
                SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY,
                array( 'fields' => 'names' )
            );
            if ( ! is_wp_error( $topic_names ) ) {
                foreach ( array_slice( (array) $topic_names, 0, 5 ) as $topic ) {
                    $aliases[] = trim( $title . ' — ' . sanitize_text_field( $topic ) );
                }
            }
        }

        $clean = array();
        foreach ( $aliases as $alias ) {
            $alias = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( $alias ) ) );
            if ( $alias ) {
                $clean[ strtolower( $alias ) ] = $alias;
            }
        }
        return array_slice( array_values( $clean ), 0, self::MAX_ALIASES );
    }

    public static function citation_signals( $text ) {
        $text = (string) $text;
        preg_match_all( '/\b10\.\d{4,9}\/[-._;()\/:a-z0-9]+\b/i', $text, $dois );
        preg_match_all( '/https?:\/\/[^\s<>"\']+/i', $text, $urls );
        preg_match_all( '/\[(?:\d{1,3}(?:\s*[-,]\s*\d{1,3})*)\]/', $text, $numeric );
        preg_match_all(
            '/(?:\([A-Z][A-Za-z\'\-]+(?:\s+et al\.)?(?:\s+(?:and|&)\s+[A-Z][A-Za-z\'\-]+)?[,]?\s+(?:19|20)\d{2}[a-z]?\)|\b[A-Z][A-Za-z\'\-]+(?:\s+et al\.)?(?:\s+(?:and|&)\s+[A-Z][A-Za-z\'\-]+)?\s+\((?:19|20)\d{2}[a-z]?\))/',
            $text,
            $author_year
        );
        preg_match_all( '/\b(?:references|bibliography|works cited|sources)\b/i', $text, $reference_headings );

        $sentences = self::sentence_split( $text );
        $claim_like = 0;
        $uncited_claim_like = 0;
        foreach ( $sentences as $sentence ) {
            if ( preg_match( '/\b(shows?|demonstrates?|proves?|indicates?|causes?|results? in|is associated with|significant|increased|decreased)\b/i', $sentence ) ) {
                $claim_like++;
                if (
                    ! preg_match( '/\[\d/', $sentence )
                    && ! preg_match( '/\((?:[A-Z][A-Za-z\'\-]+).*?(?:19|20)\d{2}/', $sentence )
                    && ! preg_match( '/https?:\/\//i', $sentence )
                    && ! preg_match( '/\b10\.\d{4,9}\//i', $sentence )
                ) {
                    $uncited_claim_like++;
                }
            }
        }

        return array(
            'doi_count'             => count( $dois[0] ?? array() ),
            'url_count'             => count( $urls[0] ?? array() ),
            'numeric_citation_count'=> count( $numeric[0] ?? array() ),
            'author_year_count'     => count( $author_year[0] ?? array() ),
            'references_heading'    => ! empty( $reference_headings[0] ),
            'claim_like_sentences'  => $claim_like,
            'uncited_claim_signals' => $uncited_claim_like,
        );
    }

    public static function gap_signals( $document_id, $source, $sections, $chunks, $citation_signals, $terms ) {
        $gaps = array();
        $text = strtolower( (string) $source['text'] );
        $heading_text = strtolower(
            implode(
                ' ',
                array_map(
                    static function ( $section ) {
                        return (string) ( $section['title'] ?? '' );
                    },
                    (array) $sections
                )
            )
        );

        if ( strlen( trim( $source['text'] ) ) < 500 ) {
            $gaps[] = self::gap_record(
                'insufficient-text',
                'high',
                __( 'The readable text is too short for reliable document intelligence.', 'sustainable-catalyst-library' )
            );
        }
        if ( empty( $sections ) || 1 === count( $sections ) ) {
            $gaps[] = self::gap_record(
                'missing-structure',
                'medium',
                __( 'No reliable section structure was detected.', 'sustainable-catalyst-library' )
            );
        }
        if ( ! preg_match( '/\b(method|methodology|approach|research design)\b/', $heading_text . ' ' . $text ) ) {
            $gaps[] = self::gap_record(
                'methods-not-detected',
                'info',
                __( 'No methods or approach section was detected.', 'sustainable-catalyst-library' )
            );
        }
        if ( ! preg_match( '/\b(limitation|limitations|constraints|caveats)\b/', $heading_text . ' ' . $text ) ) {
            $gaps[] = self::gap_record(
                'limitations-not-detected',
                'medium',
                __( 'No explicit limitations or caveats section was detected.', 'sustainable-catalyst-library' )
            );
        }
        if (
            empty( $citation_signals['references_heading'] )
            && 0 === absint( $citation_signals['doi_count'] )
            && 0 === absint( $citation_signals['numeric_citation_count'] )
            && 0 === absint( $citation_signals['author_year_count'] )
        ) {
            $gaps[] = self::gap_record(
                'citations-not-detected',
                'medium',
                __( 'No structured citation signals were detected.', 'sustainable-catalyst-library' )
            );
        }
        if ( absint( $citation_signals['uncited_claim_signals'] ) > 3 ) {
            $gaps[] = self::gap_record(
                'possible-citation-gaps',
                'medium',
                sprintf(
                    __( '%d claim-like sentences may need citation review.', 'sustainable-catalyst-library' ),
                    absint( $citation_signals['uncited_claim_signals'] )
                )
            );
        }
        if ( ! $terms ) {
            $gaps[] = self::gap_record(
                'terms-not-detected',
                'info',
                __( 'The index could not identify recurring document terms.', 'sustainable-catalyst-library' )
            );
        }
        if ( count( $chunks ) >= self::MAX_CHUNKS || ! empty( $source['truncated'] ) ) {
            $gaps[] = self::gap_record(
                'index-truncated',
                'high',
                __( 'The document exceeded the bounded indexing limit and requires segmented processing.', 'sustainable-catalyst-library' )
            );
        }

        if ( class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            $topic_ids = wp_get_object_terms(
                $document_id,
                SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY,
                array( 'fields' => 'ids' )
            );
            if ( is_wp_error( $topic_ids ) || ! $topic_ids ) {
                $gaps[] = self::gap_record(
                    'topics-missing',
                    'info',
                    __( 'No canonical Knowledge Topics are assigned.', 'sustainable-catalyst-library' )
                );
            }
            $concept_ids = self::id_list(
                get_post_meta(
                    $document_id,
                    SC_Library_Topics_Concepts_Relationships::META_CONCEPT_IDS,
                    true
                )
            );
            if ( ! $concept_ids ) {
                $gaps[] = self::gap_record(
                    'concepts-missing',
                    'info',
                    __( 'No reusable Concepts are connected.', 'sustainable-catalyst-library' )
                );
            }
        }

        return array_slice( $gaps, 0, self::MAX_GAPS );
    }

    private static function gap_record( $type, $severity, $message ) {
        return array(
            'type'     => sanitize_key( $type ),
            'severity' => sanitize_key( $severity ),
            'message'  => sanitize_text_field( $message ),
        );
    }

    public static function get_profile( $document_id, $include_private = false, $refresh = false ) {
        $document_id = absint( $document_id );
        if ( SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id ) ) {
            return array();
        }
        if ( ! $include_private && ! self::document_is_public( $document_id ) ) {
            return array();
        }

        $profile = get_post_meta( $document_id, self::META_PROFILE, true );
        if ( $refresh || ! is_array( $profile ) ) {
            $profile = self::analyze_document( $document_id, $include_private, $refresh );
        }
        if ( is_wp_error( $profile ) || ! is_array( $profile ) ) {
            return array();
        }
        return $include_private ? $profile : self::public_profile( $profile );
    }

    private static function public_profile( $profile ) {
        return array(
            'schema'              => self::PROFILE_SCHEMA,
            'document_id'         => absint( $profile['document_id'] ?? 0 ),
            'document_title'      => (string) ( $profile['document_title'] ?? '' ),
            'document_url'        => (string) ( $profile['document_url'] ?? '' ),
            'status'              => (string) ( $profile['status'] ?? '' ),
            'status_label'        => (string) ( $profile['status_label'] ?? '' ),
            'page_count'          => absint( $profile['page_count'] ?? 0 ),
            'section_count'       => absint( $profile['section_count'] ?? 0 ),
            'chunk_count'         => absint( $profile['chunk_count'] ?? 0 ),
            'summary'             => (string) ( $profile['summary'] ?? '' ),
            'key_points'          => array_values( (array) ( $profile['key_points'] ?? array() ) ),
            'suggested_questions' => array_values( (array) ( $profile['suggested_questions'] ?? array() ) ),
            'terms'               => array_values( (array) ( $profile['terms'] ?? array() ) ),
            'gaps'                => array_values( (array) ( $profile['gaps'] ?? array() ) ),
            'citation_signals'    => (array) ( $profile['citation_signals'] ?? array() ),
            'topic_ids'           => self::id_list( $profile['topic_ids'] ?? array() ),
            'concept_ids'         => self::id_list( $profile['concept_ids'] ?? array() ),
            'entity_ids'          => self::id_list( $profile['entity_ids'] ?? array() ),
            'analyzer'            => (string) ( $profile['analyzer'] ?? '' ),
            'analyzer_version'    => (string) ( $profile['analyzer_version'] ?? '' ),
            'analyzed_at'         => (string) ( $profile['analyzed_at'] ?? '' ),
        );
    }

    public static function section_index( $document_id, $include_private = false ) {
        if ( ! $include_private && ! self::document_is_public( $document_id ) ) {
            return array();
        }
        $sections = get_post_meta( $document_id, self::META_SECTIONS, true );
        $sections = is_array( $sections ) ? $sections : array();
        if ( $include_private ) {
            return $sections;
        }
        return array_map(
            static function ( $section ) {
                return array(
                    'schema'     => self::SECTION_SCHEMA,
                    'section_id' => (string) ( $section['section_id'] ?? '' ),
                    'index'      => absint( $section['index'] ?? 0 ),
                    'title'      => (string) ( $section['title'] ?? '' ),
                    'level'      => absint( $section['level'] ?? 0 ),
                    'word_count' => absint( $section['word_count'] ?? 0 ),
                    'text_hash'  => (string) ( $section['text_hash'] ?? '' ),
                );
            },
            $sections
        );
    }

    public static function chunk_index( $document_id ) {
        $chunks = get_post_meta( $document_id, self::META_CHUNKS, true );
        return is_array( $chunks ) ? array_values( $chunks ) : array();
    }

    public static function search_documents( $query, $args = array() ) {
        $query = trim( sanitize_text_field( $query ) );
        if ( '' === $query ) {
            return array(
                'schema'  => self::RETRIEVAL_SCHEMA,
                'query'   => '',
                'results' => array(),
            );
        }

        $args = wp_parse_args(
            $args,
            array(
                'include_private' => false,
                'limit'           => 10,
            )
        );
        $include_private = rest_sanitize_boolean( $args['include_private'] ) && current_user_can( 'edit_posts' );
        $limit = max( 1, min( 50, absint( $args['limit'] ) ) );
        $query_normalized = self::normalize_search_text( $query );
        $query_tokens = self::tokenize( $query );

        $document_ids = get_posts(
            array(
                'post_type'      => SC_Library_Foundation_Pages::POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 1000,
                'fields'         => 'ids',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        $results = array();
        foreach ( $document_ids as $document_id ) {
            if ( ! $include_private && ! self::document_is_public( $document_id ) ) {
                continue;
            }
            $profile = self::get_profile( $document_id, $include_private, false );
            if ( ! $profile || in_array( $profile['status'] ?? '', array( 'excluded', 'failed' ), true ) ) {
                continue;
            }

            $title = (string) ( $profile['document_title'] ?? get_the_title( $document_id ) );
            $aliases = get_post_meta( $document_id, self::META_TITLE_ALIASES, true );
            $aliases = is_array( $aliases ) ? $aliases : array( $title );
            $terms = (array) ( $profile['terms'] ?? array() );
            $summary = (string) ( $profile['summary'] ?? '' );

            $score = 0;
            $reasons = array();
            $title_normalized = self::normalize_search_text( $title );

            if ( $query_normalized === $title_normalized ) {
                $score += 120;
                $reasons[] = 'exact-title';
            } elseif ( 0 === strpos( $title_normalized, $query_normalized ) ) {
                $score += 85;
                $reasons[] = 'title-prefix';
            } elseif ( false !== strpos( $title_normalized, $query_normalized ) ) {
                $score += 65;
                $reasons[] = 'title-contains';
            }

            foreach ( $aliases as $alias ) {
                $alias_normalized = self::normalize_search_text( $alias );
                if ( $query_normalized === $alias_normalized ) {
                    $score += 100;
                    $reasons[] = 'exact-alias';
                    break;
                }
                if ( $alias_normalized && false !== strpos( $alias_normalized, $query_normalized ) ) {
                    $score += 35;
                    $reasons[] = 'alias-match';
                    break;
                }
            }

            $title_tokens = self::tokenize( implode( ' ', array_merge( array( $title ), $aliases ) ) );
            $title_overlap = count( array_intersect( $query_tokens, $title_tokens ) );
            if ( $title_overlap ) {
                $score += $title_overlap * 18;
                $reasons[] = 'title-token-overlap';
            }

            $term_words = array_map(
                static function ( $term ) {
                    return (string) ( $term['term'] ?? '' );
                },
                $terms
            );
            $term_overlap = count( array_intersect( $query_tokens, $term_words ) );
            if ( $term_overlap ) {
                $score += $term_overlap * 8;
                $reasons[] = 'document-term-overlap';
            }

            $summary_tokens = self::tokenize( $summary );
            $summary_overlap = count( array_intersect( $query_tokens, $summary_tokens ) );
            if ( $summary_overlap ) {
                $score += min( 20, $summary_overlap * 3 );
                $reasons[] = 'summary-overlap';
            }

            if ( $score <= 0 ) {
                continue;
            }
            $results[] = array(
                'document_id' => $document_id,
                'title'       => $title,
                'url'         => (string) ( $profile['document_url'] ?? '' ),
                'summary'     => $summary,
                'score'       => $score,
                'reasons'     => array_values( array_unique( $reasons ) ),
                'status'      => (string) ( $profile['status'] ?? '' ),
                'analyzed_at' => (string) ( $profile['analyzed_at'] ?? '' ),
            );
        }

        usort(
            $results,
            static function ( $a, $b ) {
                $score = $b['score'] <=> $a['score'];
                return 0 !== $score ? $score : strnatcasecmp( $a['title'], $b['title'] );
            }
        );

        return array(
            'schema'       => self::RETRIEVAL_SCHEMA,
            'query'        => $query,
            'query_tokens' => $query_tokens,
            'result_count' => min( count( $results ), $limit ),
            'results'      => array_slice( $results, 0, $limit ),
            'generated_at' => current_time( 'mysql' ),
        );
    }

    public static function compare_documents( $document_ids, $persist = false ) {
        $document_ids = array_slice( self::id_list( $document_ids ), 0, 5 );
        if ( count( $document_ids ) < 2 ) {
            return new WP_Error(
                'comparison_requires_documents',
                __( 'Select at least two documents to compare.', 'sustainable-catalyst-library' ),
                array( 'status' => 400 )
            );
        }

        $profiles = array();
        foreach ( $document_ids as $document_id ) {
            if (
                SC_Library_Foundation_Pages::POST_TYPE !== get_post_type( $document_id )
                || ! current_user_can( 'read_post', $document_id )
            ) {
                return new WP_Error(
                    'comparison_document_forbidden',
                    __( 'One or more documents cannot be compared.', 'sustainable-catalyst-library' ),
                    array( 'status' => 403 )
                );
            }
            $profile = self::get_profile( $document_id, true, false );
            if ( ! $profile ) {
                return new WP_Error(
                    'comparison_profile_missing',
                    __( 'One or more documents do not have an intelligence profile.', 'sustainable-catalyst-library' ),
                    array( 'status' => 409 )
                );
            }
            $profiles[ $document_id ] = $profile;
        }

        $term_sets = array();
        $section_sets = array();
        foreach ( $profiles as $document_id => $profile ) {
            $term_sets[ $document_id ] = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static function ( $term ) {
                                return (string) ( $term['term'] ?? '' );
                            },
                            (array) ( $profile['terms'] ?? array() )
                        )
                    )
                )
            );
            $sections = self::section_index( $document_id, true );
            $section_sets[ $document_id ] = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static function ( $section ) {
                                return self::normalize_search_text( $section['title'] ?? '' );
                            },
                            $sections
                        )
                    )
                )
            );
        }

        $shared_terms = call_user_func_array( 'array_intersect', array_values( $term_sets ) );
        $shared_sections = call_user_func_array( 'array_intersect', array_values( $section_sets ) );
        $documents = array();
        foreach ( $profiles as $document_id => $profile ) {
            $other_terms = array();
            foreach ( $term_sets as $other_id => $terms ) {
                if ( $other_id !== $document_id ) {
                    $other_terms = array_merge( $other_terms, $terms );
                }
            }
            $documents[] = array(
                'document_id'  => $document_id,
                'title'        => (string) ( $profile['document_title'] ?? '' ),
                'summary'      => (string) ( $profile['summary'] ?? '' ),
                'key_points'   => array_slice( (array) ( $profile['key_points'] ?? array() ), 0, 5 ),
                'unique_terms' => array_slice(
                    array_values( array_diff( $term_sets[ $document_id ], array_unique( $other_terms ) ) ),
                    0,
                    15
                ),
                'topic_ids'    => self::id_list( $profile['topic_ids'] ?? array() ),
                'concept_ids'  => self::id_list( $profile['concept_ids'] ?? array() ),
                'gaps'         => array_values( (array) ( $profile['gaps'] ?? array() ) ),
            );
        }

        $pairwise = array();
        $ids = array_keys( $profiles );
        for ( $i = 0; $i < count( $ids ); $i++ ) {
            for ( $j = $i + 1; $j < count( $ids ); $j++ ) {
                $left = $ids[ $i ];
                $right = $ids[ $j ];
                $union = array_unique( array_merge( $term_sets[ $left ], $term_sets[ $right ] ) );
                $intersection = array_intersect( $term_sets[ $left ], $term_sets[ $right ] );
                $similarity = $union ? (int) round( ( count( $intersection ) / count( $union ) ) * 100 ) : 0;
                $pairwise[] = array(
                    'left_document_id'  => $left,
                    'right_document_id' => $right,
                    'term_similarity'   => $similarity,
                    'shared_term_count' => count( $intersection ),
                );
            }
        }

        $result = array(
            'schema'          => self::COMPARISON_SCHEMA,
            'document_ids'    => $document_ids,
            'documents'       => $documents,
            'shared_terms'    => array_slice( array_values( array_unique( $shared_terms ) ), 0, 25 ),
            'shared_sections' => array_slice( array_values( array_unique( $shared_sections ) ), 0, 20 ),
            'pairwise'        => $pairwise,
            'created_at'      => current_time( 'mysql' ),
            'created_by'      => get_current_user_id(),
            'limitations'     => __( 'Similarity is based on structured titles, sections, and recurring terms. It does not establish agreement, contradiction, or scholarly equivalence.', 'sustainable-catalyst-library' ),
        );

        if ( $persist ) {
            $comparison_id = wp_insert_post(
                array(
                    'post_type'   => self::COMPARISON_POST_TYPE,
                    'post_status' => 'publish',
                    'post_title'  => sprintf(
                        __( 'Document comparison — %s', 'sustainable-catalyst-library' ),
                        current_time( 'Y-m-d H:i:s' )
                    ),
                    'post_author' => get_current_user_id(),
                ),
                true
            );
            if ( ! is_wp_error( $comparison_id ) ) {
                update_post_meta( $comparison_id, self::META_COMPARISON_DOCUMENT_IDS, $document_ids );
                update_post_meta( $comparison_id, self::META_COMPARISON_RESULT, $result );
                update_post_meta( $comparison_id, self::META_COMPARISON_CREATED_AT, current_time( 'mysql' ) );
                update_post_meta( $comparison_id, self::META_COMPARISON_CREATED_BY, get_current_user_id() );
                $result['comparison_id'] = $comparison_id;
            }
        }

        return $result;
    }

    public function filter_document_context( $context, $document_id = 0, $args = array() ) {
        $document_id = absint( $document_id );
        $include_private = ! empty( $args['include_private'] ) && current_user_can( 'edit_post', $document_id );
        $profile = self::get_profile( $document_id, $include_private, false );
        if ( ! $profile ) {
            return $context;
        }

        $payload = array(
            'schema'       => self::HANDOFF_SCHEMA,
            'document'     => $profile,
            'sections'     => self::section_index( $document_id, $include_private ),
            'retrieval'    => array(
                'title_aliases' => $include_private
                    ? (array) get_post_meta( $document_id, self::META_TITLE_ALIASES, true )
                    : array(),
                'terms'         => (array) ( $profile['terms'] ?? array() ),
            ),
            'generated_at' => current_time( 'mysql' ),
        );

        return is_array( $context )
            ? array_merge( $context, array( 'document_intelligence' => $payload ) )
            : array( 'document_intelligence' => $payload );
    }

    public function filter_project_context( $context, $project_id = 0, $args = array() ) {
        if ( ! class_exists( 'SC_Library_Connected_Research_Environment' ) ) {
            return $context;
        }
        $document_ids = self::id_list(
            get_post_meta(
                absint( $project_id ),
                SC_Library_Connected_Research_Environment::META_DOCUMENT_IDS,
                true
            )
        );
        $documents = array();
        foreach ( array_slice( $document_ids, 0, 20 ) as $document_id ) {
            $profile = self::get_profile(
                $document_id,
                current_user_can( 'edit_post', $document_id ),
                false
            );
            if ( $profile ) {
                $documents[] = array(
                    'document_id'       => $document_id,
                    'title'             => (string) ( $profile['document_title'] ?? '' ),
                    'summary'           => (string) ( $profile['summary'] ?? '' ),
                    'key_points'        => array_slice( (array) ( $profile['key_points'] ?? array() ), 0, 5 ),
                    'terms'             => array_slice( (array) ( $profile['terms'] ?? array() ), 0, 15 ),
                    'gaps'              => array_values( (array) ( $profile['gaps'] ?? array() ) ),
                    'suggested_questions'=> array_slice( (array) ( $profile['suggested_questions'] ?? array() ), 0, 5 ),
                );
            }
        }
        if ( ! $documents ) {
            return $context;
        }

        $addition = array(
            'schema'     => self::HANDOFF_SCHEMA,
            'documents'  => $documents,
            'document_count' => count( $documents ),
        );
        return is_array( $context )
            ? array_merge( $context, array( 'document_intelligence' => $addition ) )
            : array( 'document_intelligence' => $addition );
    }

    public function mark_document_stale_on_save( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || SC_Library_Foundation_Pages::POST_TYPE !== $post->post_type
        ) {
            return;
        }

        $submitted = isset( $_POST['sc_library_document_intelligence_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_document_intelligence_nonce'] ) ),
                'sc_library_save_document_intelligence_' . $post_id
            )
            && current_user_can( 'edit_post', $post_id );

        if ( $submitted ) {
            update_post_meta(
                $post_id,
                self::META_PUBLIC,
                isset( $_POST['sc_document_intelligence_public'] ) ? '1' : '0'
            );
            update_post_meta(
                $post_id,
                self::META_EXCLUDED,
                isset( $_POST['sc_document_intelligence_excluded'] ) ? '1' : '0'
            );
        }

        if ( '1' === get_post_meta( $post_id, self::META_EXCLUDED, true ) ) {
            update_post_meta( $post_id, self::META_STATUS, 'excluded' );
            delete_transient( self::TRANSIENT_DASHBOARD );
            return;
        }

        $profile = get_post_meta( $post_id, self::META_PROFILE, true );
        if ( is_array( $profile ) ) {
            update_post_meta( $post_id, self::META_STATUS, 'stale' );
            update_post_meta(
                $post_id,
                self::META_STALE_REASON,
                __( 'The document was updated after its last intelligence analysis.', 'sustainable-catalyst-library' )
            );
        } else {
            update_post_meta( $post_id, self::META_STATUS, 'pending' );
        }
        delete_transient( self::TRANSIENT_DASHBOARD );
    }

    public function render_document_meta_box( $post ) {
        wp_nonce_field(
            'sc_library_save_document_intelligence_' . $post->ID,
            'sc_library_document_intelligence_nonce'
        );
        $profile = get_post_meta( $post->ID, self::META_PROFILE, true );
        $profile = is_array( $profile ) ? $profile : array();
        $sections = self::section_index( $post->ID, true );
        $gaps = (array) ( $profile['gaps'] ?? array() );
        ?>
        <div class="sc-doc-intel-editor" data-sc-doc-intel-document="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-doc-intel-settings">
                <label>
                    <input type="checkbox" name="sc_document_intelligence_public" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_PUBLIC, true ) ); ?>>
                    <?php esc_html_e( 'Publish the document-intelligence summary, key points, questions, and gap notices.', 'sustainable-catalyst-library' ); ?>
                </label>
                <label>
                    <input type="checkbox" name="sc_document_intelligence_excluded" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_EXCLUDED, true ) ); ?>>
                    <?php esc_html_e( 'Exclude this document from intelligence indexing and retrieval.', 'sustainable-catalyst-library' ); ?>
                </label>
            </div>

            <?php if ( $profile ) : ?>
                <?php echo self::render_profile_panel( $profile, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="sc-doc-intel-editor__grid">
                    <section>
                        <h3><?php esc_html_e( 'Detected sections', 'sustainable-catalyst-library' ); ?></h3>
                        <?php if ( $sections ) : ?>
                            <ol class="sc-doc-intel-section-list">
                                <?php foreach ( $sections as $section ) : ?>
                                    <li><strong><?php echo esc_html( $section['title'] ); ?></strong><span><?php echo esc_html( $section['word_count'] . ' words' ); ?></span></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else : ?>
                            <p><?php esc_html_e( 'No sections detected.', 'sustainable-catalyst-library' ); ?></p>
                        <?php endif; ?>
                    </section>
                    <section>
                        <h3><?php esc_html_e( 'Knowledge and citation gaps', 'sustainable-catalyst-library' ); ?></h3>
                        <?php echo self::render_gap_list( $gaps ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                </div>
            <?php else : ?>
                <div class="sc-doc-intel-empty">
                    <h3><?php esc_html_e( 'No document-intelligence profile yet', 'sustainable-catalyst-library' ); ?></h3>
                    <p><?php esc_html_e( 'Analyze the readable document text to create a section index, retrieval profile, summary, key points, questions, and review signals.', 'sustainable-catalyst-library' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="sc-doc-intel-actions">
                <button type="button" class="button button-primary" data-sc-doc-intel-analyze>
                    <?php esc_html_e( 'Analyze Document', 'sustainable-catalyst-library' ); ?>
                </button>
                <button type="button" class="button" data-sc-doc-intel-force>
                    <?php esc_html_e( 'Force Full Reindex', 'sustainable-catalyst-library' ); ?>
                </button>
                <span data-sc-doc-intel-status aria-live="polite"></span>
            </div>
        </div>
        <?php
    }

    public function render_document_status_box( $post ) {
        $status = (string) get_post_meta( $post->ID, self::META_STATUS, true ) ?: 'pending';
        $profile = get_post_meta( $post->ID, self::META_PROFILE, true );
        $profile = is_array( $profile ) ? $profile : array();
        ?>
        <div class="sc-doc-intel-status-box">
            <p><span class="sc-doc-intel-state status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( self::statuses()[ $status ] ?? $status ); ?></span></p>
            <dl>
                <div><dt><?php esc_html_e( 'Sections', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $profile['section_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Chunks', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $profile['chunk_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Terms', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( (array) ( $profile['terms'] ?? array() ) ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Gaps', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( (array) ( $profile['gaps'] ?? array() ) ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Analyzed', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $profile['analyzed_at'] ?? '—' ); ?></dd></div>
            </dl>
            <?php if ( get_post_meta( $post->ID, self::META_ERROR, true ) ) : ?>
                <p class="sc-doc-intel-error"><?php echo esc_html( get_post_meta( $post->ID, self::META_ERROR, true ) ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $report = self::dashboard_report( true );
        $migration = self::migration_state();
        ?>
        <div class="wrap sc-doc-intel-center">
            <p class="sc-doc-intel-kicker"><?php esc_html_e( 'Knowledge Library v3.7.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Research Librarian Document Intelligence', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Build title-aware retrieval profiles, section and chunk indexes, document summaries, research questions, comparison context, and review signals for Knowledge Library documents.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-doc-intel-metrics">
                <article><strong><?php echo esc_html( $report['document_count'] ); ?></strong><span><?php esc_html_e( 'Documents', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['ready_count'] ); ?></strong><span><?php esc_html_e( 'Ready', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['stale_count'] ); ?></strong><span><?php esc_html_e( 'Stale', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['pending_count'] ); ?></strong><span><?php esc_html_e( 'Pending', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['failed_count'] ); ?></strong><span><?php esc_html_e( 'Failed', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $report['gap_count'] ); ?></strong><span><?php esc_html_e( 'Review signals', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-doc-intel-migration">
                <div>
                    <h2><?php esc_html_e( 'Document intelligence migration', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%1$d of %2$d documents processed', 'sustainable-catalyst-library' ), $migration['processed'], $migration['total'] ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-doc-intel-migration-state><?php echo esc_html( ucfirst( $migration['status'] ) ); ?></span></p>
                </div>
                <button type="button" class="button button-primary" data-sc-doc-intel-run-migration><?php esc_html_e( 'Run Next Index Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-doc-intel-migration-status aria-live="polite"></div>
            </section>

            <div class="sc-doc-intel-tools">
                <section class="sc-doc-intel-tool">
                    <h2><?php esc_html_e( 'Title-aware retrieval test', 'sustainable-catalyst-library' ); ?></h2>
                    <form data-sc-doc-intel-search-form>
                        <label><span class="screen-reader-text"><?php esc_html_e( 'Search documents', 'sustainable-catalyst-library' ); ?></span><input type="search" name="query" placeholder="<?php esc_attr_e( 'Enter a title, topic, or phrase', 'sustainable-catalyst-library' ); ?>"></label>
                        <button type="submit" class="button"><?php esc_html_e( 'Test Retrieval', 'sustainable-catalyst-library' ); ?></button>
                    </form>
                    <div data-sc-doc-intel-search-results aria-live="polite"></div>
                </section>
                <section class="sc-doc-intel-tool">
                    <h2><?php esc_html_e( 'Document comparison', 'sustainable-catalyst-library' ); ?></h2>
                    <form data-sc-doc-intel-compare-form>
                        <label><?php esc_html_e( 'Document IDs', 'sustainable-catalyst-library' ); ?><input type="text" name="document_ids" placeholder="123, 456"></label>
                        <button type="submit" class="button"><?php esc_html_e( 'Compare Documents', 'sustainable-catalyst-library' ); ?></button>
                    </form>
                    <div data-sc-doc-intel-compare-results aria-live="polite"></div>
                </section>
            </div>

            <section>
                <div class="sc-doc-intel-section-heading">
                    <div><h2><?php esc_html_e( 'Document intelligence register', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Generated summaries and gap signals require human review before publication or research use.', 'sustainable-catalyst-library' ); ?></p></div>
                </div>
                <?php if ( $report['documents'] ) : ?>
                    <div class="sc-doc-intel-table-wrap"><table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'Document', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Sections', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Chunks', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Terms', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Gaps', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Analyzed', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $report['documents'] as $item ) : ?>
                                <tr>
                                    <td><a href="<?php echo esc_url( get_edit_post_link( $item['document_id'], 'raw' ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                                    <td><span class="sc-doc-intel-state status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( self::statuses()[ $item['status'] ] ?? $item['status'] ); ?></span></td>
                                    <td><?php echo esc_html( $item['sections'] ); ?></td>
                                    <td><?php echo esc_html( $item['chunks'] ); ?></td>
                                    <td><?php echo esc_html( $item['terms'] ); ?></td>
                                    <td><?php echo esc_html( $item['gaps'] ); ?></td>
                                    <td><?php echo esc_html( $item['analyzed_at'] ?: '—' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php else : ?>
                    <p><?php esc_html_e( 'No Knowledge Library documents were found.', 'sustainable-catalyst-library' ); ?></p>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public static function dashboard_report( $include_private = true ) {
        if ( ! $include_private ) {
            $cached = get_transient( self::TRANSIENT_DASHBOARD );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $document_ids = get_posts(
            array(
                'post_type'      => SC_Library_Foundation_Pages::POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 1000,
                'fields'         => 'ids',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        $documents = array();
        $counts = array_fill_keys( array_keys( self::statuses() ), 0 );
        $gap_count = 0;
        $section_count = 0;
        $chunk_count = 0;

        foreach ( $document_ids as $document_id ) {
            if ( ! $include_private && ! self::document_is_public( $document_id ) ) {
                continue;
            }
            $status = (string) get_post_meta( $document_id, self::META_STATUS, true ) ?: 'pending';
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
            $profile = get_post_meta( $document_id, self::META_PROFILE, true );
            $profile = is_array( $profile ) ? $profile : array();
            $gaps = count( (array) ( $profile['gaps'] ?? array() ) );
            $sections = absint( $profile['section_count'] ?? 0 );
            $chunks = absint( $profile['chunk_count'] ?? 0 );
            $gap_count += $gaps;
            $section_count += $sections;
            $chunk_count += $chunks;

            $documents[] = array(
                'document_id' => $document_id,
                'title'       => get_the_title( $document_id ),
                'status'      => $status,
                'sections'    => $sections,
                'chunks'      => $chunks,
                'terms'       => count( (array) ( $profile['terms'] ?? array() ) ),
                'gaps'        => $gaps,
                'analyzed_at' => (string) ( $profile['analyzed_at'] ?? '' ),
            );
        }

        $report = array(
            'schema'        => self::DASHBOARD_SCHEMA,
            'document_count'=> count( $documents ),
            'ready_count'   => absint( $counts['ready'] ),
            'partial_count' => absint( $counts['partial'] ),
            'stale_count'   => absint( $counts['stale'] ),
            'pending_count' => absint( $counts['pending'] ),
            'failed_count'  => absint( $counts['failed'] ),
            'excluded_count'=> absint( $counts['excluded'] ),
            'gap_count'     => $gap_count,
            'section_count' => $section_count,
            'chunk_count'   => $chunk_count,
            'documents'     => $documents,
            'migration'     => self::migration_state(),
            'generated_at'  => current_time( 'mysql' ),
        );

        if ( ! $include_private ) {
            set_transient( self::TRANSIENT_DASHBOARD, $report, 10 * MINUTE_IN_SECONDS );
        }
        return $report;
    }

    private static function render_profile_panel( $profile, $private = false ) {
        ob_start();
        ?>
        <section class="sc-doc-intel-profile status-<?php echo esc_attr( $profile['status'] ?? 'pending' ); ?>">
            <header>
                <div><p class="sc-doc-intel-kicker"><?php esc_html_e( 'Document intelligence profile', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $profile['document_title'] ?? '' ); ?></h3></div>
                <span class="sc-doc-intel-state status-<?php echo esc_attr( $profile['status'] ?? 'pending' ); ?>"><?php echo esc_html( $profile['status_label'] ?? '' ); ?></span>
            </header>
            <?php if ( ! empty( $profile['summary'] ) ) : ?><div class="sc-doc-intel-summary"><h4><?php esc_html_e( 'Summary', 'sustainable-catalyst-library' ); ?></h4><p><?php echo esc_html( $profile['summary'] ); ?></p></div><?php endif; ?>
            <div class="sc-doc-intel-profile__grid">
                <article><h4><?php esc_html_e( 'Key points', 'sustainable-catalyst-library' ); ?></h4><?php echo self::render_text_list( $profile['key_points'] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
                <article><h4><?php esc_html_e( 'Suggested research questions', 'sustainable-catalyst-library' ); ?></h4><?php echo self::render_text_list( $profile['suggested_questions'] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article>
            </div>
            <div class="sc-doc-intel-terms">
                <?php foreach ( array_slice( (array) ( $profile['terms'] ?? array() ), 0, 20 ) as $term ) : ?>
                    <span><?php echo esc_html( $term['label'] ?? $term['term'] ?? '' ); ?><small><?php echo esc_html( absint( $term['count'] ?? 0 ) ); ?></small></span>
                <?php endforeach; ?>
            </div>
            <?php if ( $private ) : ?>
                <footer><small><?php echo esc_html( sprintf( __( '%1$d sections · %2$d chunks · analyzed %3$s', 'sustainable-catalyst-library' ), absint( $profile['section_count'] ?? 0 ), absint( $profile['chunk_count'] ?? 0 ), $profile['analyzed_at'] ?? '—' ) ); ?></small></footer>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_gap_list( $gaps ) {
        if ( ! $gaps ) {
            return '<p>' . esc_html__( 'No structural gap signals were recorded.', 'sustainable-catalyst-library' ) . '</p>';
        }
        ob_start();
        echo '<ul class="sc-doc-intel-gap-list">';
        foreach ( $gaps as $gap ) {
            echo '<li class="severity-' . esc_attr( $gap['severity'] ?? 'info' ) . '"><strong>' . esc_html( ucfirst( str_replace( '-', ' ', $gap['type'] ?? '' ) ) ) . '</strong><span>' . esc_html( $gap['message'] ?? '' ) . '</span></li>';
        }
        echo '</ul>';
        return ob_get_clean();
    }

    private static function render_text_list( $items ) {
        if ( ! $items ) {
            return '<p>—</p>';
        }
        ob_start();
        echo '<ul>';
        foreach ( (array) $items as $item ) {
            echo '<li>' . esc_html( $item ) . '</li>';
        }
        echo '</ul>';
        return ob_get_clean();
    }

    public static function create_reindex_job( $document_ids, $args = array() ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'document_intelligence_job_forbidden',
                __( 'You cannot create a document-intelligence job.', 'sustainable-catalyst-library' ),
                array( 'status' => 403 )
            );
        }

        $document_ids = array_values(
            array_filter(
                self::id_list( $document_ids ),
                static function ( $document_id ) {
                    return SC_Library_Foundation_Pages::POST_TYPE === get_post_type( $document_id )
                        && current_user_can( 'edit_post', $document_id );
                }
            )
        );
        if ( ! $document_ids ) {
            return new WP_Error(
                'document_intelligence_job_empty',
                __( 'No eligible documents were selected.', 'sustainable-catalyst-library' ),
                array( 'status' => 400 )
            );
        }

        $args = wp_parse_args(
            $args,
            array(
                'force' => false,
                'label' => '',
            )
        );
        $job_id = wp_insert_post(
            array(
                'post_type'   => self::JOB_POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => sanitize_text_field( $args['label'] ) ?: sprintf(
                    __( 'Document intelligence job — %s', 'sustainable-catalyst-library' ),
                    current_time( 'Y-m-d H:i:s' )
                ),
                'post_author' => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        $items = array();
        foreach ( $document_ids as $document_id ) {
            $items[] = array(
                'document_id' => $document_id,
                'state'       => 'queued',
                'attempts'    => 0,
                'message'     => '',
                'started_at'  => '',
                'finished_at' => '',
            );
        }
        update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
        update_post_meta( $job_id, self::META_JOB_STATUS, 'queued' );
        update_post_meta( $job_id, self::META_JOB_CURSOR, 0 );
        update_post_meta(
            $job_id,
            self::META_JOB_CONFIG,
            array(
                'force'      => rest_sanitize_boolean( $args['force'] ),
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            )
        );
        update_post_meta( $job_id, self::META_JOB_LOG, array() );
        return self::job_state( $job_id );
    }

    public static function run_job_batch( $job_id, $limit = 5 ) {
        $job_id = absint( $job_id );
        if ( self::JOB_POST_TYPE !== get_post_type( $job_id ) ) {
            return new WP_Error(
                'document_intelligence_job_invalid',
                __( 'The document-intelligence job is invalid.', 'sustainable-catalyst-library' ),
                array( 'status' => 404 )
            );
        }
        if ( ! current_user_can( 'edit_post', $job_id ) && ! wp_doing_cron() ) {
            return new WP_Error(
                'document_intelligence_job_forbidden',
                __( 'You cannot run this document-intelligence job.', 'sustainable-catalyst-library' ),
                array( 'status' => 403 )
            );
        }

        $items = get_post_meta( $job_id, self::META_JOB_ITEMS, true );
        $items = is_array( $items ) ? array_values( $items ) : array();
        $config = get_post_meta( $job_id, self::META_JOB_CONFIG, true );
        $config = is_array( $config ) ? $config : array();
        $cursor = absint( get_post_meta( $job_id, self::META_JOB_CURSOR, true ) );
        $limit = max( 1, min( 25, absint( $limit ) ) );
        $processed = 0;

        update_post_meta( $job_id, self::META_JOB_STATUS, 'running' );
        for ( $index = $cursor; $index < count( $items ) && $processed < $limit; $index++ ) {
            if ( ! in_array( $items[ $index ]['state'] ?? '', array( 'queued', 'failed' ), true ) ) {
                $cursor = $index + 1;
                continue;
            }
            $document_id = absint( $items[ $index ]['document_id'] ?? 0 );
            $items[ $index ]['state'] = 'running';
            $items[ $index ]['attempts'] = absint( $items[ $index ]['attempts'] ?? 0 ) + 1;
            $items[ $index ]['started_at'] = current_time( 'mysql' );
            update_post_meta( $job_id, self::META_JOB_ITEMS, $items );

            $result = self::analyze_document(
                $document_id,
                true,
                ! empty( $config['force'] )
            );
            if ( is_wp_error( $result ) ) {
                $items[ $index ]['state'] = 'failed';
                $items[ $index ]['message'] = sanitize_text_field( $result->get_error_message() );
            } else {
                $items[ $index ]['state'] = 'complete';
                $items[ $index ]['message'] = sanitize_text_field(
                    self::statuses()[ $result['status'] ?? 'ready' ] ?? 'Complete'
                );
            }
            $items[ $index ]['finished_at'] = current_time( 'mysql' );
            $cursor = $index + 1;
            $processed++;
        }

        $queued = 0;
        $failed = 0;
        foreach ( $items as $item ) {
            if ( 'queued' === ( $item['state'] ?? '' ) ) {
                $queued++;
            } elseif ( 'failed' === ( $item['state'] ?? '' ) ) {
                $failed++;
            }
        }
        $status = $queued ? 'running' : ( $failed ? 'complete-with-errors' : 'complete' );
        update_post_meta( $job_id, self::META_JOB_ITEMS, $items );
        update_post_meta( $job_id, self::META_JOB_CURSOR, $cursor );
        update_post_meta( $job_id, self::META_JOB_STATUS, $status );
        self::append_job_log(
            $job_id,
            array(
                'event'     => 'batch',
                'processed' => $processed,
                'cursor'    => $cursor,
                'status'    => $status,
            )
        );
        return self::job_state( $job_id );
    }

    public static function job_state( $job_id ) {
        if ( self::JOB_POST_TYPE !== get_post_type( $job_id ) ) {
            return array();
        }
        $items = get_post_meta( $job_id, self::META_JOB_ITEMS, true );
        $items = is_array( $items ) ? array_values( $items ) : array();
        $counts = array(
            'queued'   => 0,
            'running'  => 0,
            'complete' => 0,
            'failed'   => 0,
        );
        foreach ( $items as $item ) {
            $state = (string) ( $item['state'] ?? 'queued' );
            if ( isset( $counts[ $state ] ) ) {
                $counts[ $state ]++;
            }
        }
        return array(
            'job_id'    => absint( $job_id ),
            'title'     => get_the_title( $job_id ),
            'status'    => (string) get_post_meta( $job_id, self::META_JOB_STATUS, true ) ?: 'queued',
            'cursor'    => absint( get_post_meta( $job_id, self::META_JOB_CURSOR, true ) ),
            'total'     => count( $items ),
            'counts'    => $counts,
            'config'    => (array) get_post_meta( $job_id, self::META_JOB_CONFIG, true ),
            'items'     => $items,
            'log'       => (array) get_post_meta( $job_id, self::META_JOB_LOG, true ),
        );
    }

    private static function append_job_log( $job_id, $entry ) {
        $log = get_post_meta( $job_id, self::META_JOB_LOG, true );
        $log = is_array( $log ) ? $log : array();
        $log[] = array_merge(
            array(
                'event_id'   => wp_generate_uuid4(),
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            ),
            is_array( $entry ) ? $entry : array()
        );
        update_post_meta( $job_id, self::META_JOB_LOG, array_slice( $log, -self::MAX_LOG ) );
    }

    public function run_scheduled_migration() {
        $state = self::migration_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public function run_stale_reindex() {
        $document_ids = get_posts(
            array(
                'post_type'      => SC_Library_Foundation_Pages::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 10,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => self::META_STATUS,
                        'value'   => array( 'pending', 'stale', 'partial', 'failed' ),
                        'compare' => 'IN',
                    ),
                    array(
                        'key'     => self::META_STATUS,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
                'orderby'        => 'modified',
                'order'          => 'ASC',
            )
        );
        foreach ( $document_ids as $document_id ) {
            if ( '1' !== get_post_meta( $document_id, self::META_EXCLUDED, true ) ) {
                self::analyze_document( $document_id, true, false );
            }
        }
    }

    public static function run_migration_batch( $limit = self::MIGRATION_BATCH ) {
        global $wpdb;
        if ( get_transient( self::TRANSIENT_MIGRATION_LOCK ) ) {
            return new WP_Error(
                'document_intelligence_migration_locked',
                __( 'Another document-intelligence migration batch is already running.', 'sustainable-catalyst-library' ),
                array( 'status' => 409 )
            );
        }

        set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );

        try {
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $post_type = SC_Library_Foundation_Pages::POST_TYPE;

            $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})";
            $state['total'] = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        $count_sql,
                        array_merge( array( $post_type ), $statuses )
                    )
                )
            );

            $query_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
            $params = array_merge(
                array( $post_type ),
                $statuses,
                array(
                    absint( $state['cursor'] ),
                    max( 1, min( 100, absint( $limit ) ) ),
                )
            );
            $ids = array_map(
                'absint',
                (array) $wpdb->get_col( $wpdb->prepare( $query_sql, $params ) )
            );

            if ( ! $ids ) {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
                update_option( self::OPTION_MIGRATION, $state, false );
                delete_transient( self::TRANSIENT_MIGRATION_LOCK );
                return $state;
            }

            foreach ( $ids as $document_id ) {
                try {
                    if ( '' === get_post_meta( $document_id, self::META_PUBLIC, true ) ) {
                        update_post_meta( $document_id, self::META_PUBLIC, '0' );
                    }
                    if ( '' === get_post_meta( $document_id, self::META_EXCLUDED, true ) ) {
                        update_post_meta( $document_id, self::META_EXCLUDED, '0' );
                    }
                    $result = self::analyze_document( $document_id, true, false );
                    if ( is_wp_error( $result ) ) {
                        throw new RuntimeException( $result->get_error_message() );
                    }
                    update_post_meta( $document_id, self::META_MIGRATED, self::VERSION );
                } catch ( Throwable $error ) {
                    $state['failures'][] = array(
                        'document_id' => $document_id,
                        'message'     => sanitize_text_field( $error->getMessage() ),
                        'time'        => current_time( 'mysql' ),
                    );
                    $state['failures'] = array_slice( $state['failures'], -self::MAX_LOG );
                }
                $state['cursor'] = $document_id;
                $state['processed']++;
            }

            $state['status'] = $state['processed'] >= $state['total'] ? 'complete' : 'pending';
            if ( 'complete' === $state['status'] ) {
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION, $state, false );
            delete_transient( self::TRANSIENT_DASHBOARD );
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION, $state, false );
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error(
                'document_intelligence_migration_failed',
                $error->getMessage(),
                array( 'status' => 500 )
            );
        }

        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    public function ajax_analyze_document() {
        $this->verify_ajax( 'edit_posts' );
        $document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $document_id ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You cannot analyze this document.', 'sustainable-catalyst-library' ) ),
                403
            );
        }
        $result = self::analyze_document(
            $document_id,
            true,
            rest_sanitize_boolean( wp_unslash( $_POST['force'] ?? false ) )
        );
        $this->send_ajax_result( $result, 'profile' );
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $this->send_ajax_result(
            self::run_migration_batch( self::MIGRATION_BATCH ),
            'migration'
        );
    }

    public function ajax_search_documents() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::search_documents(
            wp_unslash( $_POST['query'] ?? '' ),
            array(
                'include_private' => true,
                'limit'           => 12,
            )
        );
        wp_send_json_success( array( 'retrieval' => $result ) );
    }

    public function ajax_compare_documents() {
        $this->verify_ajax( 'edit_posts' );
        $result = self::compare_documents(
            self::id_list( wp_unslash( $_POST['document_ids'] ?? array() ) ),
            true
        );
        $this->send_ajax_result( $result, 'comparison' );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_document_intelligence_v370', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You are not allowed to perform this action.', 'sustainable-catalyst-library' ) ),
                403
            );
        }
    }

    private function send_ajax_result( $result, $key ) {
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'data'    => $data,
                ),
                $status
            );
        }
        wp_send_json_success( array( $key => $result ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/documents/(?P<id>\d+)/intelligence',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_document_intelligence' ),
                    'permission_callback' => array( $this, 'rest_can_read_intelligence' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_analyze_document' ),
                    'permission_callback' => array( $this, 'rest_can_edit_document' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/documents/(?P<id>\d+)/sections',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_document_sections' ),
                'permission_callback' => array( $this, 'rest_can_read_intelligence' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/documents/(?P<id>\d+)/chunks',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_document_chunks' ),
                'permission_callback' => array( $this, 'rest_can_edit_document' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_search_documents' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/compare',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_compare_documents' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/jobs',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_create_job' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/jobs/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_job_state' ),
                    'permission_callback' => array( $this, 'rest_can_edit_job' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_job' ),
                    'permission_callback' => array( $this, 'rest_can_edit_job' ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/dashboard',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_dashboard' ),
                'permission_callback' => static function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/document-intelligence/migration',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_migration_state' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_migration' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
            )
        );
    }

    public function rest_document_intelligence( WP_REST_Request $request ) {
        $document_id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_post', $document_id );
        $profile = self::get_profile( $document_id, $include_private, false );
        return $profile
            ? rest_ensure_response( $profile )
            : new WP_Error(
                'document_intelligence_unavailable',
                __( 'Document intelligence is not available.', 'sustainable-catalyst-library' ),
                array( 'status' => 404 )
            );
    }

    public function rest_analyze_document( WP_REST_Request $request ) {
        $result = self::analyze_document(
            absint( $request['id'] ),
            true,
            rest_sanitize_boolean( $request->get_param( 'force' ) )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_document_sections( WP_REST_Request $request ) {
        $document_id = absint( $request['id'] );
        return rest_ensure_response(
            self::section_index(
                $document_id,
                current_user_can( 'edit_post', $document_id )
            )
        );
    }

    public function rest_document_chunks( WP_REST_Request $request ) {
        return rest_ensure_response(
            array(
                'schema'      => self::CHUNK_SCHEMA,
                'document_id' => absint( $request['id'] ),
                'chunks'      => self::chunk_index( absint( $request['id'] ) ),
            )
        );
    }

    public function rest_search_documents( WP_REST_Request $request ) {
        $include_private = rest_sanitize_boolean( $request->get_param( 'include_private' ) )
            && current_user_can( 'edit_posts' );
        return rest_ensure_response(
            self::search_documents(
                $request->get_param( 'q' ) ?: $request->get_param( 'query' ),
                array(
                    'include_private' => $include_private,
                    'limit'           => $request->get_param( 'limit' ) ?: 10,
                )
            )
        );
    }

    public function rest_compare_documents( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::compare_documents(
            $payload['document_ids'] ?? array(),
            rest_sanitize_boolean( $payload['persist'] ?? true )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_create_job( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::create_reindex_job(
            $payload['document_ids'] ?? array(),
            array(
                'force' => $payload['force'] ?? false,
                'label' => $payload['label'] ?? '',
            )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_job_state( WP_REST_Request $request ) {
        $state = self::job_state( absint( $request['id'] ) );
        return $state
            ? rest_ensure_response( $state )
            : new WP_Error(
                'document_intelligence_job_not_found',
                __( 'Document-intelligence job not found.', 'sustainable-catalyst-library' ),
                array( 'status' => 404 )
            );
    }

    public function rest_run_job( WP_REST_Request $request ) {
        $result = self::run_job_batch(
            absint( $request['id'] ),
            $request->get_param( 'limit' ) ?: 5
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_dashboard() {
        return rest_ensure_response( self::dashboard_report( true ) );
    }

    public function rest_migration_state() {
        return rest_ensure_response( self::migration_state() );
    }

    public function rest_run_migration( WP_REST_Request $request ) {
        $result = self::run_migration_batch(
            max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_intelligence( WP_REST_Request $request ) {
        $document_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $document_id )
            || self::document_is_public( $document_id );
    }

    public function rest_can_edit_document( WP_REST_Request $request ) {
        $document_id = absint( $request['id'] );
        return SC_Library_Foundation_Pages::POST_TYPE === get_post_type( $document_id )
            && current_user_can( 'edit_post', $document_id );
    }

    public function rest_can_edit_job( WP_REST_Request $request ) {
        $job_id = absint( $request['id'] );
        return self::JOB_POST_TYPE === get_post_type( $job_id )
            && current_user_can( 'edit_post', $job_id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = $request->get_route();
        if ( false === strpos( $route, '/sc-library/v1/document' ) ) {
            return $response;
        }

        $private = current_user_can( 'edit_posts' )
            || false !== strpos( $route, '/chunks' )
            || false !== strpos( $route, '/compare' )
            || false !== strpos( $route, '/jobs' )
            || false !== strpos( $route, '/dashboard' )
            || false !== strpos( $route, '/migration' )
            || rest_sanitize_boolean( $request->get_param( 'include_private' ) );

        if ( $private ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function shortcode_document_intelligence( $atts ) {
        $atts = shortcode_atts(
            array(
                'document'        => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_document_intelligence'
        );
        $document_id = self::resolve_document_id( $atts['document'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $document_id );
        if ( $include_private ) {
            nocache_headers();
        }
        $profile = self::get_profile( $document_id, $include_private, false );
        if ( ! $profile ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-document-intelligence' );
        return self::render_profile_panel( $profile, $include_private )
            . '<p class="sc-doc-intel-disclaimer">'
            . esc_html__(
                'Generated document intelligence is a navigation and review aid. Verify summaries, key points, citations, and gaps against the original document.',
                'sustainable-catalyst-library'
            )
            . '</p>';
    }

    public function shortcode_key_points( $atts ) {
        $atts = shortcode_atts(
            array(
                'document'        => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_document_key_points'
        );
        $document_id = self::resolve_document_id( $atts['document'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $document_id );
        $profile = self::get_profile( $document_id, $include_private, false );
        if ( ! $profile ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-document-intelligence' );
        ob_start();
        ?>
        <section class="sc-doc-intel-list-panel">
            <p class="sc-doc-intel-kicker"><?php esc_html_e( 'Document intelligence', 'sustainable-catalyst-library' ); ?></p>
            <h2><?php esc_html_e( 'Key points', 'sustainable-catalyst-library' ); ?></h2>
            <?php echo self::render_text_list( $profile['key_points'] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_questions( $atts ) {
        $atts = shortcode_atts(
            array(
                'document'        => '',
                'include_private' => 'false',
            ),
            $atts,
            'sc_document_research_questions'
        );
        $document_id = self::resolve_document_id( $atts['document'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] )
            && current_user_can( 'edit_post', $document_id );
        $profile = self::get_profile( $document_id, $include_private, false );
        if ( ! $profile ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-document-intelligence' );
        ob_start();
        ?>
        <section class="sc-doc-intel-list-panel">
            <p class="sc-doc-intel-kicker"><?php esc_html_e( 'Research guidance', 'sustainable-catalyst-library' ); ?></p>
            <h2><?php esc_html_e( 'Questions to explore', 'sustainable-catalyst-library' ); ?></h2>
            <?php echo self::render_text_list( $profile['suggested_questions'] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_comparison( $atts ) {
        $atts = shortcode_atts(
            array(
                'documents' => '',
            ),
            $atts,
            'sc_document_comparison'
        );
        $document_ids = self::id_list( $atts['documents'] );
        if ( count( $document_ids ) < 2 ) {
            return '';
        }
        foreach ( $document_ids as $document_id ) {
            if ( ! self::document_is_public( $document_id ) ) {
                return '';
            }
        }
        $result = self::compare_documents( $document_ids, false );
        if ( is_wp_error( $result ) ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-document-intelligence' );
        return self::render_comparison_public( $result );
    }

    private static function render_comparison_public( $result ) {
        ob_start();
        ?>
        <section class="sc-doc-intel-comparison">
            <header><p class="sc-doc-intel-kicker"><?php esc_html_e( 'Document comparison', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Shared and distinctive document context', 'sustainable-catalyst-library' ); ?></h2></header>
            <div class="sc-doc-intel-comparison__documents">
                <?php foreach ( $result['documents'] as $document ) : ?>
                    <article>
                        <h3><?php echo esc_html( $document['title'] ); ?></h3>
                        <p><?php echo esc_html( $document['summary'] ); ?></p>
                        <h4><?php esc_html_e( 'Distinctive terms', 'sustainable-catalyst-library' ); ?></h4>
                        <p><?php echo esc_html( implode( ', ', $document['unique_terms'] ) ?: '—' ); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="sc-doc-intel-comparison__shared">
                <article><h3><?php esc_html_e( 'Shared terms', 'sustainable-catalyst-library' ); ?></h3><p><?php echo esc_html( implode( ', ', $result['shared_terms'] ) ?: '—' ); ?></p></article>
                <article><h3><?php esc_html_e( 'Shared section labels', 'sustainable-catalyst-library' ); ?></h3><p><?php echo esc_html( implode( ', ', $result['shared_sections'] ) ?: '—' ); ?></p></article>
            </div>
            <footer><p><?php echo esc_html( $result['limitations'] ); ?></p></footer>
        </section>
        <?php
        return ob_get_clean();
    }

    public function cleanup_deleted_document( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( SC_Library_Foundation_Pages::POST_TYPE === $post_type ) {
            $comparison_ids = get_posts(
                array(
                    'post_type'      => self::COMPARISON_POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => 500,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_COMPARISON_DOCUMENT_IDS,
                    'meta_value'     => 'i:' . absint( $post_id ) . ';',
                    'meta_compare'   => 'LIKE',
                )
            );
            foreach ( $comparison_ids as $comparison_id ) {
                $ids = array_values(
                    array_filter(
                        self::id_list(
                            get_post_meta(
                                $comparison_id,
                                self::META_COMPARISON_DOCUMENT_IDS,
                                true
                            )
                        ),
                        static function ( $id ) use ( $post_id ) {
                            return absint( $id ) !== absint( $post_id );
                        }
                    )
                );
                if ( count( $ids ) < 2 ) {
                    wp_delete_post( $comparison_id, true );
                } else {
                    update_post_meta(
                        $comparison_id,
                        self::META_COMPARISON_DOCUMENT_IDS,
                        $ids
                    );
                }
            }

            $job_ids = get_posts(
                array(
                    'post_type'      => self::JOB_POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => 500,
                    'fields'         => 'ids',
                )
            );
            foreach ( $job_ids as $job_id ) {
                $items = get_post_meta( $job_id, self::META_JOB_ITEMS, true );
                $items = is_array( $items ) ? $items : array();
                $filtered = array_values(
                    array_filter(
                        $items,
                        static function ( $item ) use ( $post_id ) {
                            return absint( $item['document_id'] ?? 0 ) !== absint( $post_id );
                        }
                    )
                );
                if ( count( $filtered ) !== count( $items ) ) {
                    update_post_meta( $job_id, self::META_JOB_ITEMS, $filtered );
                }
            }
            delete_transient( self::TRANSIENT_DASHBOARD );
        }
    }

    private static function document_is_public( $document_id ) {
        return SC_Library_Foundation_Pages::POST_TYPE === get_post_type( $document_id )
            && 'publish' === get_post_status( $document_id )
            && '1' === get_post_meta( $document_id, self::META_PUBLIC, true )
            && '1' !== get_post_meta( $document_id, self::META_EXCLUDED, true );
    }

    private static function resolve_document_id( $value ) {
        if (
            is_numeric( $value )
            && SC_Library_Foundation_Pages::POST_TYPE === get_post_type( absint( $value ) )
        ) {
            return absint( $value );
        }
        if (
            '' === trim( (string) $value )
            && is_singular( SC_Library_Foundation_Pages::POST_TYPE )
        ) {
            return get_queried_object_id();
        }
        $post = get_page_by_path(
            sanitize_title( $value ),
            OBJECT,
            SC_Library_Foundation_Pages::POST_TYPE
        );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function migration_state() {
        $state = get_option( self::OPTION_MIGRATION, array() );
        return wp_parse_args(
            is_array( $state ) ? $state : array(),
            self::default_migration_state()
        );
    }

    private static function default_migration_state() {
        return array(
            'version'      => self::VERSION,
            'status'       => 'pending',
            'cursor'       => 0,
            'processed'    => 0,
            'total'        => 0,
            'failures'     => array(),
            'last_error'   => '',
            'started_at'   => '',
            'updated_at'   => '',
            'completed_at' => '',
        );
    }

    private static function html_to_text( $html ) {
        $html = preg_replace(
            '/<(script|style|noscript|svg|canvas)[^>]*>.*?<\/\1>/is',
            ' ',
            (string) $html
        );
        $html = preg_replace(
            '/<(br|\/p|\/div|\/li|\/section|\/article|\/tr|\/h[1-6])\s*>/i',
            "\n",
            $html
        );
        return html_entity_decode(
            wp_strip_all_tags( $html ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    private static function normalize_text( $text ) {
        $text = str_replace(
            array( "\r\n", "\r", "\xC2\xA0" ),
            array( "\n", "\n", ' ' ),
            (string) $text
        );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        $text = preg_replace( '/ *\n */', "\n", $text );
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        return trim( $text );
    }

    private static function normalize_search_text( $text ) {
        $text = strtolower(
            html_entity_decode(
                wp_strip_all_tags( (string) $text ),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );
        $text = preg_replace( '/[^a-z0-9]+/u', ' ', $text );
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }

    private static function sentence_split( $text ) {
        $text = self::normalize_text( $text );
        if ( '' === $text ) {
            return array();
        }
        $sentences = preg_split(
            '/(?<=[.!?])\s+(?=[A-Z0-9"\'])|\n+/u',
            $text
        );
        return array_values(
            array_filter(
                array_map(
                    static function ( $sentence ) {
                        return trim( preg_replace( '/\s+/', ' ', $sentence ) );
                    },
                    (array) $sentences
                ),
                static function ( $sentence ) {
                    return strlen( $sentence ) >= 20;
                }
            )
        );
    }

    private static function tokenize( $text ) {
        $normalized = self::normalize_search_text( $text );
        if ( '' === $normalized ) {
            return array();
        }
        return array_values(
            array_filter(
                preg_split( '/\s+/', $normalized ),
                static function ( $token ) {
                    return strlen( $token ) >= 3;
                }
            )
        );
    }

    private static function word_count( $text ) {
        return count(
            array_values(
                array_filter(
                    preg_split( '/\s+/', trim( (string) $text ) ),
                    'strlen'
                )
            )
        );
    }

    private static function stopwords() {
        return array(
            'about', 'above', 'after', 'again', 'against', 'almost', 'also',
            'among', 'another', 'because', 'been', 'before', 'being', 'between',
            'both', 'could', 'does', 'doing', 'during', 'each', 'either',
            'from', 'further', 'have', 'having', 'however', 'into', 'itself',
            'many', 'more', 'most', 'much', 'must', 'other', 'over', 'same',
            'should', 'some', 'such', 'than', 'that', 'their', 'theirs',
            'them', 'themselves', 'then', 'there', 'these', 'they', 'this',
            'those', 'through', 'under', 'until', 'very', 'were', 'what',
            'when', 'where', 'which', 'while', 'with', 'within', 'without',
            'would', 'your', 'document', 'documents', 'section', 'sections',
            'research', 'study', 'studies', 'using', 'used', 'use', 'based',
            'including', 'include', 'includes', 'provide', 'provides',
        );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', (array) $raw )
                )
            )
        );
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        WP_CLI::add_command(
            'sc-library document-intelligence analyze',
            static function ( $args, $assoc_args ) {
                $result = self::analyze_document(
                    absint( $args[0] ?? 0 ),
                    true,
                    isset( $assoc_args['force'] )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log(
                    wp_json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence search',
            static function ( $args, $assoc_args ) {
                $result = self::search_documents(
                    implode( ' ', $args ),
                    array(
                        'include_private' => isset( $assoc_args['include-private'] ),
                        'limit'           => absint( $assoc_args['limit'] ?? 10 ),
                    )
                );
                WP_CLI::log(
                    wp_json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence compare',
            static function ( $args, $assoc_args ) {
                $result = self::compare_documents(
                    self::id_list( $args ),
                    isset( $assoc_args['persist'] )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log(
                    wp_json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence job-create',
            static function ( $args, $assoc_args ) {
                $result = self::create_reindex_job(
                    self::id_list( $args ),
                    array(
                        'force' => isset( $assoc_args['force'] ),
                        'label' => $assoc_args['label'] ?? '',
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success(
                    'Created document-intelligence job '
                    . absint( $result['job_id'] ?? 0 )
                    . '.'
                );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence job-run',
            static function ( $args, $assoc_args ) {
                $result = self::run_job_batch(
                    absint( $args[0] ?? 0 ),
                    absint( $assoc_args['limit'] ?? 5 )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log(
                    wp_json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence migrate',
            static function ( $args, $assoc_args ) {
                $result = self::run_migration_batch(
                    absint( $assoc_args['limit'] ?? self::MIGRATION_BATCH )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );

        WP_CLI::add_command(
            'sc-library document-intelligence dashboard',
            static function () {
                WP_CLI::log(
                    wp_json_encode(
                        self::dashboard_report( true ),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
            }
        );
    }
}
