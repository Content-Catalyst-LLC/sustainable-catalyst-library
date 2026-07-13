CREATE SCHEMA IF NOT EXISTS sustainable_catalyst_library;
SET search_path TO sustainable_catalyst_library, public;

CREATE TABLE IF NOT EXISTS export_metadata (
    metadata_key text PRIMARY KEY,
    metadata_value jsonb NOT NULL
);

CREATE TABLE IF NOT EXISTS records (
    record_id bigint PRIMARY KEY,
    record_identifier text UNIQUE NOT NULL,
    kind text NOT NULL,
    post_type text NOT NULL,
    title text NOT NULL,
    excerpt text NOT NULL DEFAULT '',
    canonical_url text NOT NULL DEFAULT '',
    record_state text NOT NULL,
    content_type text NOT NULL,
    area text NOT NULL DEFAULT '',
    product text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    published_at timestamptz,
    modified_at timestamptz,
    expected_release jsonb NOT NULL DEFAULT '{}'::jsonb,
    article_map_id bigint,
    series_order numeric(12,3) NOT NULL DEFAULT 0,
    authoritative boolean NOT NULL DEFAULT false,
    authority_label text NOT NULL DEFAULT '',
    historical boolean NOT NULL DEFAULT false,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS records_state_idx ON records(record_state);
CREATE INDEX IF NOT EXISTS records_type_idx ON records(content_type);
CREATE INDEX IF NOT EXISTS records_area_idx ON records(area);
CREATE INDEX IF NOT EXISTS records_product_idx ON records(product);
CREATE INDEX IF NOT EXISTS records_payload_gin ON records USING gin(payload);

CREATE TABLE IF NOT EXISTS terms (
    term_id bigint NOT NULL,
    taxonomy text NOT NULL,
    slug text NOT NULL,
    name text NOT NULL,
    description text NOT NULL DEFAULT '',
    parent_term_id bigint,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    PRIMARY KEY (taxonomy, term_id)
);

CREATE TABLE IF NOT EXISTS record_terms (
    record_id bigint NOT NULL,
    taxonomy text NOT NULL,
    term_id bigint NOT NULL,
    term_order integer NOT NULL DEFAULT 0,
    PRIMARY KEY (record_id, taxonomy, term_id)
);
CREATE INDEX IF NOT EXISTS record_terms_term_idx ON record_terms(taxonomy, term_id);

CREATE TABLE IF NOT EXISTS relationships (
    relationship_id bigint PRIMARY KEY,
    source_record_id bigint NOT NULL,
    target_record_id bigint NOT NULL,
    relationship_type text NOT NULL,
    note text NOT NULL DEFAULT '',
    sort_order integer NOT NULL DEFAULT 0,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE (source_record_id, target_record_id, relationship_type)
);
CREATE INDEX IF NOT EXISTS relationships_source_idx ON relationships(source_record_id);
CREATE INDEX IF NOT EXISTS relationships_target_idx ON relationships(target_record_id);
CREATE INDEX IF NOT EXISTS relationships_type_idx ON relationships(relationship_type);

CREATE TABLE IF NOT EXISTS resources (
    resource_id text PRIMARY KEY,
    record_id bigint NOT NULL,
    resource_type text NOT NULL,
    url text NOT NULL DEFAULT '',
    label text NOT NULL DEFAULT '',
    sort_order integer NOT NULL DEFAULT 0,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS resources_record_idx ON resources(record_id);
CREATE INDEX IF NOT EXISTS resources_type_idx ON resources(resource_type);

CREATE TABLE IF NOT EXISTS documentation (
    record_id bigint PRIMARY KEY,
    document_status text NOT NULL,
    document_type text NOT NULL DEFAULT '',
    document_version text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    authority_type text NOT NULL DEFAULT '',
    authority_url text NOT NULL DEFAULT '',
    webpage_url text NOT NULL DEFAULT '',
    repository_url text NOT NULL DEFAULT '',
    pdf_url text NOT NULL DEFAULT '',
    release_url text NOT NULL DEFAULT '',
    last_reviewed date,
    review_interval_days integer NOT NULL DEFAULT 0,
    featured boolean NOT NULL DEFAULT false,
    supersedes_record_id bigint,
    superseded_by_record_id bigint,
    dependency_ids bigint[] NOT NULL DEFAULT '{}'::bigint[],
    correction_url text NOT NULL DEFAULT '',
    authority_note text NOT NULL DEFAULT '',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);

CREATE TABLE IF NOT EXISTS plans (
    plan_id bigint PRIMARY KEY,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    wordpress_status text NOT NULL,
    plan_status text NOT NULL,
    priority text NOT NULL,
    content_type text NOT NULL,
    area text NOT NULL DEFAULT '',
    product text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    public boolean NOT NULL DEFAULT false,
    expected_release jsonb NOT NULL DEFAULT '{}'::jsonb,
    article_map_id bigint,
    series_order numeric(12,3) NOT NULL DEFAULT 0,
    linked_draft_id bigint,
    published_record_id bigint,
    dependency_ids bigint[] NOT NULL DEFAULT '{}'::bigint[],
    release_group text NOT NULL DEFAULT '',
    release_track text NOT NULL DEFAULT '',
    milestone text NOT NULL DEFAULT '',
    capacity_owner text NOT NULL DEFAULT '',
    estimated_effort numeric(14,3) NOT NULL DEFAULT 0,
    effort_unit text NOT NULL DEFAULT 'points',
    actual_effort numeric(14,3) NOT NULL DEFAULT 0,
    progress_percent integer NOT NULL DEFAULT 0 CHECK (progress_percent BETWEEN 0 AND 100),
    planned_start date,
    actual_start date,
    dependency_policy text NOT NULL DEFAULT 'all',
    blocked_override boolean NOT NULL DEFAULT false,
    blocked_reason text NOT NULL DEFAULT '',
    actual_publication_date date,
    created_at timestamptz,
    modified_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS plans_status_idx ON plans(plan_status);
CREATE INDEX IF NOT EXISTS plans_area_idx ON plans(area);
CREATE INDEX IF NOT EXISTS plans_product_idx ON plans(product);
CREATE INDEX IF NOT EXISTS plans_release_group_idx ON plans(release_group);
CREATE INDEX IF NOT EXISTS plans_capacity_owner_idx ON plans(capacity_owner);

CREATE TABLE IF NOT EXISTS plan_dependencies (
    plan_id bigint NOT NULL,
    dependency_record_id bigint NOT NULL,
    dependency_order integer NOT NULL DEFAULT 0,
    dependency_policy text NOT NULL DEFAULT 'all',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    PRIMARY KEY (plan_id, dependency_record_id)
);
CREATE INDEX IF NOT EXISTS plan_dependencies_target_idx ON plan_dependencies(dependency_record_id);

-- Browser-local Research Notebook tables. These are populated by the
-- [sc_library_portability] or Notebook PostgreSQL export, not by server exports.
CREATE TABLE IF NOT EXISTS workspace_collections (
    collection_id text PRIMARY KEY,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_saved_records (
    saved_record_id text PRIMARY KEY,
    wp_record_id bigint,
    record_identifier text NOT NULL DEFAULT '',
    title text NOT NULL,
    canonical_url text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_notes (
    note_id text PRIMARY KEY,
    title text NOT NULL,
    note_type text NOT NULL DEFAULT 'note',
    body text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_sources (
    source_id text PRIMARY KEY,
    title text NOT NULL,
    source_type text NOT NULL DEFAULT 'custom',
    canonical_url text NOT NULL DEFAULT '',
    doi text NOT NULL DEFAULT '',
    isbn text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_matrices (matrix_id text PRIMARY KEY, title text NOT NULL, status text NOT NULL DEFAULT 'draft', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_boards (board_id text PRIMARY KEY, title text NOT NULL, board_type text NOT NULL DEFAULT 'whiteboard', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_annotations (annotation_id text PRIMARY KEY, title text NOT NULL, target_type text NOT NULL DEFAULT '', target_id text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_books (book_id text PRIMARY KEY, title text NOT NULL, edition text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_handoffs (handoff_id text PRIMARY KEY, target text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);

CREATE TABLE IF NOT EXISTS account_workspaces (
    workspace_id bigint PRIMARY KEY,
    workspace_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'private',
    schema_version text NOT NULL,
    content_hash char(64) NOT NULL,
    revision bigint NOT NULL,
    last_synced_revision bigint NOT NULL DEFAULT 0,
    sync_status text NOT NULL DEFAULT 'local',
    last_synced_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS account_workspaces_owner_idx ON account_workspaces(owner_user_id);
CREATE TABLE IF NOT EXISTS account_workspace_revisions (
    revision_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    revision bigint NOT NULL,
    content_hash char(64) NOT NULL,
    change_type text NOT NULL,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE(workspace_id, revision)
);
CREATE TABLE IF NOT EXISTS account_workspace_collaborators (
    collaboration_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    user_id bigint NOT NULL,
    role text NOT NULL,
    invited_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    accepted_at timestamptz,
    UNIQUE(workspace_id, user_id)
);
CREATE TABLE IF NOT EXISTS account_workspace_sync_log (
    sync_log_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    workspace_uuid uuid NOT NULL,
    direction text NOT NULL,
    status text NOT NULL,
    response_code integer NOT NULL DEFAULT 0,
    message text NOT NULL DEFAULT '',
    content_hash char(64) NOT NULL DEFAULT '',
    created_at timestamptz
);

CREATE TABLE IF NOT EXISTS document_jobs (
    document_job_id bigint PRIMARY KEY,
    job_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL,
    workspace_uuid text NOT NULL DEFAULT '',
    book_id text NOT NULL DEFAULT '',
    title text NOT NULL,
    document_type text NOT NULL DEFAULT 'pdf',
    status text NOT NULL,
    progress integer NOT NULL DEFAULT 0,
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    content_hash char(64) NOT NULL,
    renderer_version text NOT NULL DEFAULT '',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    output_sha256 char(64) NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    completed_at timestamptz,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS document_jobs_owner_idx ON document_jobs(owner_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS document_jobs_status_idx ON document_jobs(status, updated_at DESC);
CREATE TABLE IF NOT EXISTS document_editions (
    document_edition_id bigint PRIMARY KEY,
    edition_uuid uuid UNIQUE NOT NULL,
    job_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL,
    workspace_uuid text NOT NULL DEFAULT '',
    book_id text NOT NULL DEFAULT '',
    title text NOT NULL,
    edition_label text NOT NULL DEFAULT '',
    content_hash char(64) NOT NULL,
    output_sha256 char(64) NOT NULL,
    output_attachment_id bigint NOT NULL DEFAULT 0,
    output_url text NOT NULL DEFAULT '',
    frozen_at timestamptz,
    created_at timestamptz,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS document_editions_book_idx ON document_editions(book_id, frozen_at DESC);

CREATE TABLE IF NOT EXISTS media_assets (
    media_asset_id bigint PRIMARY KEY,
    asset_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    media_type text NOT NULL,
    source_kind text NOT NULL,
    attachment_id bigint NOT NULL DEFAULT 0,
    source_url text NOT NULL DEFAULT '',
    duration_ms bigint NOT NULL DEFAULT 0,
    rights_status text NOT NULL,
    rights_holder text NOT NULL DEFAULT '',
    license_name text NOT NULL DEFAULT '',
    license_url text NOT NULL DEFAULT '',
    rights_note text NOT NULL DEFAULT '',
    source_citation text NOT NULL DEFAULT '',
    transcript_text text NOT NULL DEFAULT '',
    transcript_vtt text NOT NULL DEFAULT '',
    captions_url text NOT NULL DEFAULT '',
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    poster_time_ms bigint NOT NULL DEFAULT 0,
    accessibility_text text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'private',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_assets_rights_idx ON media_assets(rights_status);
CREATE INDEX IF NOT EXISTS media_assets_visibility_idx ON media_assets(visibility);

CREATE TABLE IF NOT EXISTS media_clips (
    media_clip_id bigint PRIMARY KEY,
    clip_uuid uuid UNIQUE NOT NULL,
    asset_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    start_ms bigint NOT NULL DEFAULT 0,
    end_ms bigint NOT NULL DEFAULT 0,
    poster_time_ms bigint NOT NULL DEFAULT 0,
    transcript_excerpt text NOT NULL DEFAULT '',
    caption_text text NOT NULL DEFAULT '',
    status text NOT NULL DEFAULT 'draft',
    visibility text NOT NULL DEFAULT 'private',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    remote_job_uuid text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_clips_asset_idx ON media_clips(asset_uuid);
CREATE INDEX IF NOT EXISTS media_clips_status_idx ON media_clips(status);

CREATE TABLE IF NOT EXISTS media_reels (
    media_reel_id bigint PRIMARY KEY,
    reel_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    clip_uuids jsonb NOT NULL DEFAULT '[]'::jsonb,
    visibility text NOT NULL DEFAULT 'private',
    edition_mode text NOT NULL DEFAULT 'linked',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_reels_visibility_idx ON media_reels(visibility);

CREATE TABLE IF NOT EXISTS media_jobs (
    media_job_id bigint PRIMARY KEY,
    job_uuid uuid UNIQUE NOT NULL,
    clip_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    status text NOT NULL,
    progress integer NOT NULL DEFAULT 0,
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    remote_job_uuid text NOT NULL DEFAULT '',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    output_sha256 text NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    completed_at timestamptz,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_jobs_clip_idx ON media_jobs(clip_uuid, created_at DESC);
CREATE INDEX IF NOT EXISTS media_jobs_status_idx ON media_jobs(status);

CREATE OR REPLACE VIEW current_registry AS
SELECT * FROM records WHERE historical = false AND record_state NOT IN ('archived', 'superseded', 'cancelled');

CREATE OR REPLACE VIEW public_roadmap AS
SELECT * FROM plans WHERE public = true AND plan_status NOT IN ('cancelled');
