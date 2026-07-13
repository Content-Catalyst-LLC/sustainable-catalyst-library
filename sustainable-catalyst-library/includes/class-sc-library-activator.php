<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Activator {
    public static function activate(): void {
        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
        update_option('sc_library_version', SC_LIBRARY_VERSION);
        update_option('sc_library_flush_rewrite', 1, false);
        set_transient('sc_library_activation_notice', 1, 90);
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option('sc_library_version', '0.0.0');
        if (version_compare($installed, SC_LIBRARY_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
        update_option('sc_library_version', SC_LIBRARY_VERSION);
        update_option('sc_library_flush_rewrite', 1, false);
        set_transient('sc_library_upgrade_notice', 1, 90);
    }

    public static function repair_schema(): void {
        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
    }

    private static function create_tables(): void {
        global $wpdb;

        $index_table = $wpdb->prefix . 'sc_library_index';
        $relationships_table = $wpdb->prefix . 'sc_library_relationships';
        $workspaces_table = $wpdb->prefix . 'sc_library_workspaces';
        $workspace_revisions_table = $wpdb->prefix . 'sc_library_workspace_revisions';
        $workspace_collaborators_table = $wpdb->prefix . 'sc_library_workspace_collaborators';
        $workspace_sync_log_table = $wpdb->prefix . 'sc_library_workspace_sync_log';
        $document_jobs_table = $wpdb->prefix . 'sc_library_document_jobs';
        $document_editions_table = $wpdb->prefix . 'sc_library_document_editions';
        $scan_items_table = $wpdb->prefix . 'sc_library_scan_items';
        $media_assets_table = $wpdb->prefix . 'sc_library_media_assets';
        $media_clips_table = $wpdb->prefix . 'sc_library_media_clips';
        $media_reels_table = $wpdb->prefix . 'sc_library_media_reels';
        $media_jobs_table = $wpdb->prefix . 'sc_library_media_jobs';
        $reviews_table = $wpdb->prefix . 'sc_library_reviews';
        $review_participants_table = $wpdb->prefix . 'sc_library_review_participants';
        $review_comments_table = $wpdb->prefix . 'sc_library_review_comments';
        $review_suggestions_table = $wpdb->prefix . 'sc_library_review_suggestions';
        $review_events_table = $wpdb->prefix . 'sc_library_review_events';
        $graph_nodes_table = $wpdb->prefix . 'sc_library_graph_nodes';
        $graph_edges_table = $wpdb->prefix . 'sc_library_graph_edges';
        $orchestration_sessions_table = $wpdb->prefix . 'sc_library_orchestration_sessions';
        $orchestration_events_table = $wpdb->prefix . 'sc_library_orchestration_events';
        $api_keys_table = $wpdb->prefix . 'sc_library_api_keys';
        $webhooks_table = $wpdb->prefix . 'sc_library_webhooks';
        $webhook_deliveries_table = $wpdb->prefix . 'sc_library_webhook_deliveries';
        $pdf_pages_table = $wpdb->prefix . 'sc_library_pdf_pages';
        $foundation_versions_table = $wpdb->prefix . 'sc_library_foundation_versions';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $index_sql = "CREATE TABLE {$index_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            record_identifier VARCHAR(191) NULL,
            post_type VARCHAR(64) NOT NULL,
            title TEXT NOT NULL,
            excerpt LONGTEXT NULL,
            searchable_text LONGTEXT NULL,
            permalink TEXT NOT NULL,
            primary_category_id BIGINT UNSIGNED NULL,
            primary_domain_id BIGINT UNSIGNED NULL,
            category_ids LONGTEXT NULL,
            tag_ids LONGTEXT NULL,
            series_term_id BIGINT UNSIGNED NULL,
            series_order DECIMAL(12,3) NOT NULL DEFAULT 0,
            concept_ids LONGTEXT NULL,
            resource_flags LONGTEXT NULL,
            published_at DATETIME NULL,
            modified_at DATETIME NULL,
            indexed_at DATETIME NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'publish',
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY record_identifier (record_identifier),
            KEY post_type (post_type),
            KEY primary_category_id (primary_category_id),
            KEY primary_domain_id (primary_domain_id),
            KEY series_term_id (series_term_id),
            KEY series_order (series_order),
            KEY published_at (published_at),
            KEY modified_at (modified_at),
            FULLTEXT KEY sc_library_search (title, excerpt, searchable_text)
        ) {$charset};";

        $relationships_sql = "CREATE TABLE {$relationships_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_post_id BIGINT UNSIGNED NOT NULL,
            target_post_id BIGINT UNSIGNED NOT NULL,
            relationship_type VARCHAR(64) NOT NULL,
            note TEXT NULL,
            confidence DECIMAL(5,4) NOT NULL DEFAULT 0.8500,
            confidence_basis VARCHAR(32) NOT NULL DEFAULT 'editorial',
            provenance_type VARCHAR(32) NOT NULL DEFAULT 'editorial',
            provenance_url LONGTEXT NULL,
            evidence_note LONGTEXT NULL,
            visibility VARCHAR(16) NOT NULL DEFAULT 'public',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_target_type (source_post_id, target_post_id, relationship_type),
            KEY source_post_id (source_post_id),
            KEY target_post_id (target_post_id),
            KEY relationship_type (relationship_type),
            KEY visibility (visibility),
            KEY confidence (confidence),
            KEY provenance_type (provenance_type)
        ) {$charset};";

        $workspaces_sql = "CREATE TABLE {$workspaces_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'private',
            schema_version VARCHAR(64) NOT NULL,
            workspace_json LONGTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            last_synced_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
            render_etag VARCHAR(191) NOT NULL DEFAULT '',
            sync_status VARCHAR(24) NOT NULL DEFAULT 'local',
            sync_error TEXT NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_uuid (workspace_uuid),
            KEY owner_user_id (owner_user_id),
            KEY visibility (visibility),
            KEY sync_status (sync_status),
            KEY updated_at (updated_at)
        ) {$charset};";

        $workspace_revisions_sql = "CREATE TABLE {$workspace_revisions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL,
            content_hash CHAR(64) NOT NULL,
            workspace_json LONGTEXT NOT NULL,
            change_type VARCHAR(32) NOT NULL DEFAULT 'updated',
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_revision (workspace_id, revision),
            KEY workspace_id (workspace_id),
            KEY created_at (created_at)
        ) {$charset};";

        $workspace_collaborators_sql = "CREATE TABLE {$workspace_collaborators_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'viewer',
            invited_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_user (workspace_id, user_id),
            KEY user_id (user_id),
            KEY role (role)
        ) {$charset};";

        $workspace_sync_log_sql = "CREATE TABLE {$workspace_sync_log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid CHAR(36) NOT NULL,
            direction VARCHAR(16) NOT NULL,
            status VARCHAR(24) NOT NULL,
            response_code INT NOT NULL DEFAULT 0,
            message TEXT NULL,
            content_hash CHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY workspace_uuid (workspace_uuid),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $document_jobs_sql = "CREATE TABLE {$document_jobs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid VARCHAR(64) NOT NULL DEFAULT '',
            book_id VARCHAR(191) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL,
            document_type VARCHAR(24) NOT NULL DEFAULT 'pdf',
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress INT NOT NULL DEFAULT 0,
            attempt INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            request_json LONGTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            remote_job_uuid CHAR(36) NOT NULL,
            renderer_version VARCHAR(64) NOT NULL DEFAULT '',
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            output_sha256 CHAR(64) NOT NULL DEFAULT '',
            output_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            manifest_json LONGTEXT NULL,
            diagnostics_json LONGTEXT NULL,
            error_message TEXT NULL,
            poll_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_uuid (job_uuid),
            KEY owner_user_id (owner_user_id),
            KEY workspace_uuid (workspace_uuid),
            KEY book_id (book_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $document_editions_sql = "CREATE TABLE {$document_editions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            edition_uuid CHAR(36) NOT NULL,
            job_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid VARCHAR(64) NOT NULL DEFAULT '',
            book_id VARCHAR(191) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL,
            edition_label VARCHAR(191) NOT NULL DEFAULT '',
            content_hash CHAR(64) NOT NULL,
            output_sha256 CHAR(64) NOT NULL,
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            manifest_json LONGTEXT NOT NULL,
            frozen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY edition_uuid (edition_uuid),
            KEY job_uuid (job_uuid),
            KEY owner_user_id (owner_user_id),
            KEY book_id (book_id),
            KEY content_hash (content_hash),
            KEY frozen_at (frozen_at)
        ) {$charset};";


        $scan_items_sql = "CREATE TABLE {$scan_items_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_id CHAR(36) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(64) NOT NULL DEFAULT '',
            outcome VARCHAR(24) NOT NULL,
            reason VARCHAR(191) NOT NULL DEFAULT '',
            title TEXT NULL,
            processed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY scan_post (scan_id, post_id),
            KEY scan_id (scan_id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY outcome (outcome),
            KEY processed_at (processed_at)
        ) {$charset};";



        $media_assets_sql = "CREATE TABLE {$media_assets_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            media_type VARCHAR(16) NOT NULL DEFAULT 'video',
            source_kind VARCHAR(32) NOT NULL DEFAULT 'attachment',
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_url LONGTEXT NOT NULL,
            duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            rights_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
            rights_holder VARCHAR(255) NOT NULL DEFAULT '',
            license_name VARCHAR(191) NOT NULL DEFAULT '',
            license_url LONGTEXT NULL,
            rights_note LONGTEXT NULL,
            source_citation LONGTEXT NULL,
            transcript_text LONGTEXT NULL,
            transcript_vtt LONGTEXT NULL,
            captions_url LONGTEXT NULL,
            poster_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            poster_time_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            accessibility_text LONGTEXT NULL,
            visibility VARCHAR(16) NOT NULL DEFAULT 'private',
            metadata_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY asset_uuid (asset_uuid),
            KEY owner_user_id (owner_user_id),
            KEY attachment_id (attachment_id),
            KEY media_type (media_type),
            KEY rights_status (rights_status),
            KEY visibility (visibility),
            KEY updated_at (updated_at)
        ) {$charset};";

        $media_clips_sql = "CREATE TABLE {$media_clips_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            clip_uuid CHAR(36) NOT NULL,
            asset_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            start_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            end_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            poster_time_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            transcript_excerpt LONGTEXT NULL,
            caption_text LONGTEXT NULL,
            annotations_json LONGTEXT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            visibility VARCHAR(16) NOT NULL DEFAULT 'private',
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            poster_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            remote_job_uuid CHAR(36) NOT NULL DEFAULT '',
            metadata_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY clip_uuid (clip_uuid),
            KEY asset_uuid (asset_uuid),
            KEY owner_user_id (owner_user_id),
            KEY status (status),
            KEY visibility (visibility),
            KEY updated_at (updated_at)
        ) {$charset};";

        $media_reels_sql = "CREATE TABLE {$media_reels_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reel_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            clip_uuids_json LONGTEXT NOT NULL,
            visibility VARCHAR(16) NOT NULL DEFAULT 'private',
            edition_mode VARCHAR(24) NOT NULL DEFAULT 'linked',
            metadata_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reel_uuid (reel_uuid),
            KEY owner_user_id (owner_user_id),
            KEY visibility (visibility),
            KEY updated_at (updated_at)
        ) {$charset};";

        $media_jobs_sql = "CREATE TABLE {$media_jobs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_uuid CHAR(36) NOT NULL,
            clip_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress INT NOT NULL DEFAULT 0,
            attempt INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            request_json LONGTEXT NOT NULL,
            remote_job_uuid CHAR(36) NOT NULL,
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            poster_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            output_sha256 CHAR(64) NOT NULL DEFAULT '',
            output_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            diagnostics_json LONGTEXT NOT NULL,
            error_message LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_uuid (job_uuid),
            KEY clip_uuid (clip_uuid),
            KEY owner_user_id (owner_user_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) {$charset};";

        $reviews_sql = "CREATE TABLE {$reviews_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_uuid CHAR(36) NOT NULL,
            subject_type VARCHAR(32) NOT NULL DEFAULT 'other',
            subject_key VARCHAR(191) NOT NULL DEFAULT '',
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            workspace_uuid VARCHAR(64) NOT NULL DEFAULT '',
            owner_user_id BIGINT UNSIGNED NOT NULL,
            assignee_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            summary LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'intake',
            priority VARCHAR(16) NOT NULL DEFAULT 'normal',
            visibility VARCHAR(20) NOT NULL DEFAULT 'private',
            due_at DATETIME NULL,
            decision_note LONGTEXT NULL,
            locked_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            locked_at DATETIME NULL,
            lock_expires_at DATETIME NULL,
            current_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY review_uuid (review_uuid),
            KEY subject_type (subject_type),
            KEY subject_key (subject_key),
            KEY post_id (post_id),
            KEY workspace_uuid (workspace_uuid),
            KEY owner_user_id (owner_user_id),
            KEY assignee_user_id (assignee_user_id),
            KEY status (status),
            KEY priority (priority),
            KEY due_at (due_at),
            KEY updated_at (updated_at)
        ) {$charset};";

        $review_participants_sql = "CREATE TABLE {$review_participants_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            email VARCHAR(191) NOT NULL DEFAULT '',
            role VARCHAR(24) NOT NULL DEFAULT 'reviewer',
            status VARCHAR(24) NOT NULL DEFAULT 'invited',
            invited_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            token_hash CHAR(64) NOT NULL DEFAULT '',
            expires_at DATETIME NULL,
            accepted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY review_user (review_id, user_id),
            UNIQUE KEY review_email (review_id, email),
            KEY review_id (review_id),
            KEY user_id (user_id),
            KEY role (role),
            KEY status (status),
            KEY token_hash (token_hash)
        ) {$charset};";

        $review_comments_sql = "CREATE TABLE {$review_comments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_uuid CHAR(36) NOT NULL,
            review_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            anchor_json LONGTEXT NOT NULL,
            resolved_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY comment_uuid (comment_uuid),
            KEY review_id (review_id),
            KEY parent_id (parent_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $review_suggestions_sql = "CREATE TABLE {$review_suggestions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            suggestion_uuid CHAR(36) NOT NULL,
            review_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            suggestion_type VARCHAR(24) NOT NULL DEFAULT 'replace',
            field_key VARCHAR(64) NOT NULL DEFAULT 'content',
            original_text LONGTEXT NULL,
            proposed_text LONGTEXT NOT NULL,
            rationale LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            decision_note LONGTEXT NULL,
            decided_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            decided_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY suggestion_uuid (suggestion_uuid),
            KEY review_id (review_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY field_key (field_key),
            KEY created_at (created_at)
        ) {$charset};";

        $review_events_sql = "CREATE TABLE {$review_events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_type VARCHAR(64) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY review_id (review_id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};";


        $graph_nodes_sql = "CREATE TABLE {$graph_nodes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            node_uuid CHAR(36) NOT NULL,
            external_key VARCHAR(191) NOT NULL,
            node_type VARCHAR(32) NOT NULL DEFAULT 'other',
            subtype VARCHAR(64) NOT NULL DEFAULT '',
            label TEXT NOT NULL,
            description LONGTEXT NULL,
            canonical_url LONGTEXT NULL,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            term_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            taxonomy VARCHAR(64) NOT NULL DEFAULT '',
            visibility VARCHAR(16) NOT NULL DEFAULT 'public',
            source_kind VARCHAR(32) NOT NULL DEFAULT 'generated',
            source_identifier VARCHAR(191) NOT NULL DEFAULT '',
            metadata_json LONGTEXT NOT NULL,
            published_at DATETIME NULL,
            modified_at DATETIME NULL,
            sync_token VARCHAR(64) NOT NULL DEFAULT '',
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY node_uuid (node_uuid),
            UNIQUE KEY external_key (external_key),
            KEY node_type (node_type),
            KEY subtype (subtype),
            KEY post_id (post_id),
            KEY term_taxonomy (term_id, taxonomy),
            KEY visibility (visibility),
            KEY source_kind (source_kind),
            KEY sync_token (sync_token),
            KEY status (status)
        ) {$charset};";

        $graph_edges_sql = "CREATE TABLE {$graph_edges_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            edge_uuid CHAR(36) NOT NULL,
            source_node_id BIGINT UNSIGNED NOT NULL,
            target_node_id BIGINT UNSIGNED NOT NULL,
            relationship_type VARCHAR(64) NOT NULL,
            label VARCHAR(191) NOT NULL DEFAULT '',
            directionality VARCHAR(16) NOT NULL DEFAULT 'directed',
            confidence DECIMAL(5,4) NOT NULL DEFAULT 0.7500,
            confidence_basis VARCHAR(32) NOT NULL DEFAULT 'editorial',
            provenance_type VARCHAR(32) NOT NULL DEFAULT 'editorial',
            provenance_url LONGTEXT NULL,
            evidence_note LONGTEXT NULL,
            visibility VARCHAR(16) NOT NULL DEFAULT 'public',
            source_kind VARCHAR(32) NOT NULL DEFAULT 'manual',
            source_identifier VARCHAR(191) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            metadata_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            verified_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY edge_uuid (edge_uuid),
            KEY source_node_id (source_node_id),
            KEY target_node_id (target_node_id),
            KEY relationship_type (relationship_type),
            KEY confidence (confidence),
            KEY provenance_type (provenance_type),
            KEY visibility (visibility),
            KEY source_kind (source_kind),
            KEY source_identifier (source_identifier)
        ) {$charset};";

        $orchestration_sessions_sql = "CREATE TABLE {$orchestration_sessions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            question LONGTEXT NOT NULL,
            intent VARCHAR(32) NOT NULL DEFAULT 'discover',
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            provider VARCHAR(100) NOT NULL DEFAULT 'sustainable-catalyst-library',
            model VARCHAR(100) NOT NULL DEFAULT '',
            retrieval_mode VARCHAR(64) NOT NULL DEFAULT 'index_plus_graph',
            response_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_uuid (session_uuid),
            KEY owner_user_id (owner_user_id),
            KEY intent (intent),
            KEY status (status),
            KEY updated_at (updated_at)
        ) {$charset};";

        $orchestration_events_sql = "CREATE TABLE {$orchestration_events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            event_uuid CHAR(36) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_uuid (event_uuid),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};";

        $api_keys_sql = "CREATE TABLE {$api_keys_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            key_uuid CHAR(36) NOT NULL,
            name VARCHAR(191) NOT NULL,
            key_prefix CHAR(12) NOT NULL,
            secret_hash CHAR(64) NOT NULL,
            scopes_json LONGTEXT NOT NULL,
            rate_limit_per_hour INT UNSIGNED NOT NULL DEFAULT 1000,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY key_uuid (key_uuid),
            UNIQUE KEY key_prefix (key_prefix),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY created_by (created_by)
        ) {$charset};";

        $webhooks_sql = "CREATE TABLE {$webhooks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_uuid CHAR(36) NOT NULL,
            name VARCHAR(191) NOT NULL,
            endpoint_url LONGTEXT NOT NULL,
            secret_encrypted LONGTEXT NOT NULL,
            secret_prefix VARCHAR(20) NOT NULL,
            events_json LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_delivery_at DATETIME NULL,
            last_status_code INT NOT NULL DEFAULT 0,
            failure_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY webhook_uuid (webhook_uuid),
            KEY status (status),
            KEY created_by (created_by),
            KEY last_delivery_at (last_delivery_at)
        ) {$charset};";

        $webhook_deliveries_sql = "CREATE TABLE {$webhook_deliveries_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            delivery_uuid CHAR(36) NOT NULL,
            webhook_id BIGINT UNSIGNED NOT NULL,
            event_id CHAR(36) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            attempt INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            response_code INT NOT NULL DEFAULT 0,
            response_body LONGTEXT NULL,
            signature VARCHAR(191) NOT NULL DEFAULT '',
            next_attempt_at DATETIME NULL,
            delivered_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY delivery_uuid (delivery_uuid),
            KEY webhook_id (webhook_id),
            KEY event_id (event_id),
            KEY event_type (event_type),
            KEY status (status),
            KEY next_attempt_at (next_attempt_at),
            KEY created_at (created_at)
        ) {$charset};";

        $pdf_pages_sql = "CREATE TABLE {$pdf_pages_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            page_number INT UNSIGNED NOT NULL,
            page_text LONGTEXT NOT NULL,
            character_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            content_hash CHAR(64) NOT NULL DEFAULT '',
            extracted_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_page (post_id, page_number),
            KEY post_id (post_id),
            KEY page_number (page_number),
            FULLTEXT KEY sc_library_pdf_page_search (page_text)
        ) {$charset};";

        $foundation_versions_sql = "CREATE TABLE {$foundation_versions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            version_label VARCHAR(191) NOT NULL DEFAULT '',
            filename VARCHAR(255) NOT NULL DEFAULT '',
            pdf_url LONGTEXT NOT NULL,
            sha256 CHAR(64) NOT NULL DEFAULT '',
            page_count INT UNSIGNED NOT NULL DEFAULT 0,
            metadata_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY attachment_id (attachment_id),
            KEY version_label (version_label),
            KEY sha256 (sha256),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($index_sql);
        dbDelta($relationships_sql);
        dbDelta($workspaces_sql);
        dbDelta($workspace_revisions_sql);
        dbDelta($workspace_collaborators_sql);
        dbDelta($workspace_sync_log_sql);
        dbDelta($document_jobs_sql);
        dbDelta($document_editions_sql);
        dbDelta($scan_items_sql);
        dbDelta($media_assets_sql);
        dbDelta($media_clips_sql);
        dbDelta($media_reels_sql);
        dbDelta($media_jobs_sql);
        dbDelta($reviews_sql);
        dbDelta($review_participants_sql);
        dbDelta($review_comments_sql);
        dbDelta($review_suggestions_sql);
        dbDelta($review_events_sql);
        dbDelta($graph_nodes_sql);
        dbDelta($graph_edges_sql);
        dbDelta($orchestration_sessions_sql);
        dbDelta($orchestration_events_sql);
        dbDelta($api_keys_sql);
        dbDelta($webhooks_sql);
        dbDelta($webhook_deliveries_sql);
        dbDelta($pdf_pages_sql);
        dbDelta($foundation_versions_sql);
    }

    private static function install_defaults(): void {
        add_option('sc_library_post_types', ['post']);
        add_option('sc_library_items_per_page', 10);
        add_option('sc_library_enable_tags', 1);
        add_option('sc_library_last_full_index', '');
        add_option('sc_library_scan_state', [], '', false);
        add_option('sc_library_scan_logs', [], '', false);
        add_option('sc_library_last_scan_summary', [], '', false);
        add_option('sc_library_default_mode', 'compact');
        add_option('sc_library_initial_results', 0);
        add_option('sc_library_result_density', 'compact');
        add_option('sc_library_excerpt_words', 28);
        add_option('sc_library_show_pathways', 1);
        add_option('sc_library_search_placeholder', 'Search concepts, series, methods, and publications');
        add_option('sc_library_workbench_url', home_url('/workbench/'));
        add_option('sc_library_enable_notebook', 1);
        add_option('sc_library_notebook_storage_mode', 'local');
        add_option('sc_library_enable_persistent_workspaces', 1);
        add_option('sc_library_workspace_storage_mode', 'hybrid');
        add_option('sc_library_workspace_auto_sync', 0);
        add_option('sc_library_workspace_max_mb', 8);
        add_option('sc_library_render_sync_url', '');
        add_option('sc_library_render_sync_api_key', '');
        add_option('sc_library_render_sync_timeout', 8);
        add_option('sc_library_enable_translation_matrix', 1);
        add_option('sc_library_default_matrix_template', 'technical_translation');
        add_option('sc_library_enable_boards', 1);
        add_option('sc_library_default_board_type', 'whiteboard');
        add_option('sc_library_enable_integrations', 1);
        add_option('sc_library_enable_annotations', 1);
        add_option('sc_library_default_annotation_page_style', 'reader');
        add_option('sc_library_enable_books', 1);
        add_option('sc_library_default_book_theme', 'institutional');
        add_option('sc_library_default_book_page_size', 'letter');
        add_option('sc_library_enable_server_documents', 1);
        add_option('sc_library_document_service_url', '');
        add_option('sc_library_document_service_api_key', '');
        add_option('sc_library_document_timeout', 30);
        add_option('sc_library_document_auto_import', 1);
        add_option('sc_library_document_max_request_mb', 8);
        add_option('sc_library_document_max_pdf_mb', 20);
        add_option('sc_library_document_max_attempts', 3);
        add_option('sc_library_document_retention_days', 30);
        add_option('sc_library_enable_documentation', 1);
        add_option('sc_library_enable_foundation_documents', 1);
        add_option('sc_library_pdf_extraction_batch_pages', 10);
        add_option('sc_library_pdf_viewer_default', 1);
        add_option('sc_library_enable_multimedia', 1);
        add_option('sc_library_enable_collaboration', 1);
        add_option('sc_library_enable_knowledge_graph', 1);
        add_option('sc_library_graph_public_limit', 250);
        add_option('sc_library_graph_default_depth', 2);
        add_option('sc_library_graph_min_public_confidence', 0.25);
        add_option('sc_library_graph_page_url', home_url('/knowledge-graph/'));
        add_option('sc_library_graph_last_rebuild', '');
        add_option('sc_library_graph_rebuild_state', [], '', false);
        add_option('sc_library_enable_orchestrator', 1);
        add_option('sc_library_orchestrator_public_discovery', 1);
        add_option('sc_library_orchestrator_page_url', home_url('/research-librarian/'));
        add_option('sc_library_orchestrator_service_url', '');
        add_option('sc_library_orchestrator_service_api_key', '');
        add_option('sc_library_orchestrator_timeout', 10);
        add_option('sc_library_orchestrator_max_records', 8);
        add_option('sc_library_orchestrator_graph_depth', 1);
        add_option('sc_library_enable_developer_api', 1);
        add_option('sc_library_api_public_rate_limit', 300);
        add_option('sc_library_api_key_rate_limit', 1000);
        add_option('sc_library_api_allowed_origins', '');
        add_option('sc_library_developer_portal_url', home_url('/developers/'));
        add_option('sc_library_webhook_max_attempts', 4);
        add_option('sc_library_webhook_timeout', 10);
        add_option('sc_library_review_lock_minutes', 15);
        add_option('sc_library_review_invitation_days', 14);
        add_option('sc_library_media_service_url', '');
        add_option('sc_library_media_service_api_key', '');
        add_option('sc_library_media_allow_remote_urls', 0);
        add_option('sc_library_media_max_source_mb', 500);
        add_option('sc_library_media_max_clip_minutes', 30);
        add_option('sc_library_media_retention_days', 14);
        add_option('sc_library_media_auto_import', 1);
        add_option('sc_library_enable_planner', 1);
        add_option('sc_library_enable_portability', 1);
        add_option('sc_library_release_capacity_threshold', 40);
        add_option('sc_library_default_effort_unit', 'points');
        add_option('sc_library_on_time_tolerance_days', 7);
        add_option('sc_library_export_schema_name', 'sustainable_catalyst_library');
        add_option('sc_library_registry_include_planned', 1);
        add_option('sc_library_registry_include_archived', 1);
        add_option('sc_library_main_page_url', home_url('/research-library/'));
        add_option('sc_library_documentation_search_placeholder', 'Search titles, descriptions, keywords, and document text');
        add_option('sc_library_documentation_include_archived', 0);
        add_option('sc_library_workbench_health_url', '');
        add_option('sc_library_decision_studio_url', home_url('/decision-studio/'));
        add_option('sc_library_decision_studio_health_url', '');
        add_option('sc_library_site_intelligence_url', home_url('/site-intelligence/'));
        add_option('sc_library_site_intelligence_health_url', '');
        add_option('sc_library_lab_url', home_url('/lab/'));
        add_option('sc_library_lab_health_url', '');
        $configured_post_types = get_option('sc_library_post_types', ['post']);
        if (!is_array($configured_post_types)) $configured_post_types = ['post'];
        if (!in_array('sc_foundation_doc', $configured_post_types, true)) {
            $configured_post_types[] = 'sc_foundation_doc';
            update_option('sc_library_post_types', array_values(array_unique($configured_post_types)));
        }
        add_option('sc_library_featured_pathways', implode("\n", [
            'Systems Thinking|/systems-thinking/|Feedback, resilience, leverage points, and complex change.',
            'Mathematical Thinking|/mathematical-thinking/|Symbols, models, uncertainty, and formal reasoning.',
            'Algorithms & Computational Reasoning|/algorithms-computational-reasoning/|Procedure, data structures, complexity, and responsible computation.',
            'Sustainable Development|/sustainable-development/|Ecological limits, institutions, justice, and long-term wellbeing.',
        ]));
    }

    private static function schedule_reconcile(): void {
        if (!wp_next_scheduled('sc_library_daily_reconcile')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sc_library_daily_reconcile');
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('sc_library_daily_reconcile');
        wp_clear_scheduled_hook('sc_library_sync_workspace');
        wp_clear_scheduled_hook('sc_library_refresh_media_job');
    }
}
