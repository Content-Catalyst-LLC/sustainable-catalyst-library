CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE TABLE IF NOT EXISTS library_sources (
    source_key text PRIMARY KEY,
    name text NOT NULL,
    source_type text NOT NULL,
    canonical_url text,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS library_records (
    record_id text PRIMARY KEY,
    source_key text NOT NULL REFERENCES library_sources(source_key) ON UPDATE CASCADE ON DELETE RESTRICT,
    object_type text NOT NULL,
    title text NOT NULL,
    canonical_url text,
    abstract text NOT NULL DEFAULT '',
    body_text text NOT NULL DEFAULT '',
    language text NOT NULL DEFAULT 'en',
    visibility text NOT NULL DEFAULT 'public' CHECK (visibility IN ('public','private','shared','internal')),
    publication_status text NOT NULL DEFAULT 'published' CHECK (publication_status IN ('draft','review','published','archived','superseded','withdrawn')),
    published_at timestamptz,
    source_updated_at timestamptz,
    authors jsonb NOT NULL DEFAULT '[]'::jsonb,
    topics jsonb NOT NULL DEFAULT '[]'::jsonb,
    tags jsonb NOT NULL DEFAULT '[]'::jsonb,
    identifiers jsonb NOT NULL DEFAULT '{}'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    content_hash char(64) NOT NULL,
    revision bigint NOT NULL DEFAULT 1 CHECK (revision > 0),
    created_at timestamptz NOT NULL DEFAULT now(),
    indexed_at timestamptz NOT NULL DEFAULT now(),
    search_vector tsvector GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
        setweight(to_tsvector('english', coalesce(abstract,'')), 'B') ||
        setweight(to_tsvector('english', coalesce(body_text,'')), 'C')
    ) STORED
);
CREATE INDEX IF NOT EXISTS library_records_search_gin ON library_records USING gin(search_vector);
CREATE INDEX IF NOT EXISTS library_records_title_trgm ON library_records USING gin(title gin_trgm_ops);
CREATE INDEX IF NOT EXISTS library_records_public_idx ON library_records(visibility, publication_status, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_records_type_idx ON library_records(object_type, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_records_source_idx ON library_records(source_key, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_records_published_idx ON library_records(published_at DESC NULLS LAST);

CREATE TABLE IF NOT EXISTS library_record_chunks (
    chunk_id bigserial PRIMARY KEY,
    record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    ordinal integer NOT NULL CHECK (ordinal >= 0),
    heading text NOT NULL DEFAULT '',
    text text NOT NULL,
    token_count integer,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    search_vector tsvector GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(heading,'')), 'A') ||
        setweight(to_tsvector('english', coalesce(text,'')), 'B')
    ) STORED,
    UNIQUE(record_id, ordinal)
);
CREATE INDEX IF NOT EXISTS library_record_chunks_search_gin ON library_record_chunks USING gin(search_vector);
CREATE INDEX IF NOT EXISTS library_record_chunks_record_idx ON library_record_chunks(record_id, ordinal);

CREATE TABLE IF NOT EXISTS library_record_versions (
    version_id bigserial PRIMARY KEY,
    record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    revision bigint NOT NULL,
    content_hash char(64) NOT NULL,
    snapshot jsonb NOT NULL,
    observed_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(record_id, revision)
);
CREATE INDEX IF NOT EXISTS library_record_versions_timeline_idx ON library_record_versions(record_id, revision DESC);

CREATE TABLE IF NOT EXISTS library_edges (
    edge_id bigserial PRIMARY KEY,
    source_record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    target_record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    relation text NOT NULL,
    weight double precision NOT NULL DEFAULT 1.0 CHECK (weight >= 0),
    directed boolean NOT NULL DEFAULT true,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(source_record_id, target_record_id, relation)
);
CREATE INDEX IF NOT EXISTS library_edges_source_idx ON library_edges(source_record_id, relation);
CREATE INDEX IF NOT EXISTS library_edges_target_idx ON library_edges(target_record_id, relation);

CREATE TABLE IF NOT EXISTS library_ingest_events (
    event_id bigserial PRIMARY KEY,
    source_key text NOT NULL,
    received_count integer NOT NULL DEFAULT 0,
    changed_count integer NOT NULL DEFAULT 0,
    request_hash char(64) NOT NULL,
    duration_ms integer NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_ingest_events_created_idx ON library_ingest_events(created_at DESC);

-- v2.1.0 — Private Organizational Knowledge Foundation.
-- Private organizational records live in physically separate tables so public
-- Library search/read paths cannot accidentally include them.
CREATE TABLE IF NOT EXISTS library_private_organizations (
    org_key text PRIMARY KEY,
    name text NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS library_private_sources (
    org_key text NOT NULL REFERENCES library_private_organizations(org_key) ON UPDATE CASCADE ON DELETE CASCADE,
    source_key text NOT NULL,
    name text NOT NULL,
    source_type text NOT NULL DEFAULT 'internal',
    canonical_url text,
    owner text,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (org_key, source_key)
);

CREATE TABLE IF NOT EXISTS library_private_records (
    private_record_key char(64) PRIMARY KEY,
    org_key text NOT NULL REFERENCES library_private_organizations(org_key) ON UPDATE CASCADE ON DELETE CASCADE,
    record_id text NOT NULL,
    source_key text NOT NULL,
    object_type text NOT NULL,
    title text NOT NULL,
    canonical_url text,
    abstract text NOT NULL DEFAULT '',
    body_text text NOT NULL DEFAULT '',
    language text NOT NULL DEFAULT 'en',
    original_format text NOT NULL DEFAULT 'text',
    access_level text NOT NULL DEFAULT 'organization' CHECK (access_level IN ('organization','restricted','project')),
    access_scopes jsonb NOT NULL DEFAULT '[]'::jsonb,
    project_key text,
    department text,
    retention_label text,
    source_updated_at timestamptz,
    authors jsonb NOT NULL DEFAULT '[]'::jsonb,
    topics jsonb NOT NULL DEFAULT '[]'::jsonb,
    tags jsonb NOT NULL DEFAULT '[]'::jsonb,
    identifiers jsonb NOT NULL DEFAULT '{}'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    content_hash char(64) NOT NULL,
    revision bigint NOT NULL DEFAULT 1 CHECK (revision > 0),
    created_at timestamptz NOT NULL DEFAULT now(),
    indexed_at timestamptz NOT NULL DEFAULT now(),
    search_vector tsvector GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
        setweight(to_tsvector('english', coalesce(abstract,'')), 'B') ||
        setweight(to_tsvector('english', coalesce(body_text,'')), 'C')
    ) STORED,
    UNIQUE (org_key, record_id),
    FOREIGN KEY (org_key, source_key) REFERENCES library_private_sources(org_key, source_key) ON UPDATE CASCADE ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS library_private_records_search_gin ON library_private_records USING gin(search_vector);
CREATE INDEX IF NOT EXISTS library_private_records_org_idx ON library_private_records(org_key, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_private_records_source_idx ON library_private_records(org_key, source_key, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_private_records_project_idx ON library_private_records(org_key, project_key, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_private_records_department_idx ON library_private_records(org_key, department, indexed_at DESC);
CREATE INDEX IF NOT EXISTS library_private_records_access_idx ON library_private_records(org_key, access_level, indexed_at DESC);

CREATE TABLE IF NOT EXISTS library_private_record_versions (
    version_id bigserial PRIMARY KEY,
    private_record_key char(64) NOT NULL REFERENCES library_private_records(private_record_key) ON DELETE CASCADE,
    revision bigint NOT NULL,
    content_hash char(64) NOT NULL,
    snapshot jsonb NOT NULL,
    observed_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(private_record_key, revision)
);
CREATE INDEX IF NOT EXISTS library_private_record_versions_idx ON library_private_record_versions(private_record_key, revision DESC);

CREATE TABLE IF NOT EXISTS library_private_ingest_events (
    event_id bigserial PRIMARY KEY,
    org_key text NOT NULL,
    source_key text NOT NULL,
    actor_id text NOT NULL,
    received_count integer NOT NULL DEFAULT 0,
    changed_count integer NOT NULL DEFAULT 0,
    request_hash char(64) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_private_ingest_events_idx ON library_private_ingest_events(org_key, created_at DESC);

CREATE TABLE IF NOT EXISTS library_private_access_events (
    event_id bigserial PRIMARY KEY,
    org_key text NOT NULL,
    actor_id text NOT NULL,
    action text NOT NULL,
    private_record_key char(64),
    request_fingerprint char(64),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_private_access_events_idx ON library_private_access_events(org_key, created_at DESC);
CREATE INDEX IF NOT EXISTS library_private_access_record_idx ON library_private_access_events(private_record_key, created_at DESC);
