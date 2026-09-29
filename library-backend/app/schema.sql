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

-- v2.23.0 — Platform Core Research Bridge.
-- Library retains raw source/chunk/index state. Only governed, explicit promotion
-- operations enter this bridge; Core remains authoritative for Core object IDs.
CREATE TABLE IF NOT EXISTS library_core_bindings (
    binding_id bigserial PRIMARY KEY,
    library_record_id text NOT NULL,
    library_object_type text NOT NULL DEFAULT 'document',
    core_object_id text NOT NULL,
    core_object_type text NOT NULL,
    core_canonical_uri text NOT NULL DEFAULT '',
    content_hash char(64),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    sync_status text NOT NULL DEFAULT 'synced' CHECK (sync_status IN ('pending','synced','stale','error')),
    last_synced_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(library_record_id, core_object_id)
);
CREATE INDEX IF NOT EXISTS library_core_bindings_record_idx ON library_core_bindings(library_record_id, updated_at DESC);
CREATE INDEX IF NOT EXISTS library_core_bindings_core_idx ON library_core_bindings(core_object_id, updated_at DESC);
CREATE INDEX IF NOT EXISTS library_core_bindings_status_idx ON library_core_bindings(sync_status, updated_at DESC);

CREATE TABLE IF NOT EXISTS library_core_sync_outbox (
    event_id bigserial PRIMARY KEY,
    library_record_id text,
    operation text NOT NULL,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    payload_hash char(64) NOT NULL,
    idempotency_key varchar(128) NOT NULL UNIQUE,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','processing','retry','complete','failed','cancelled')),
    attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
    next_attempt_at timestamptz NOT NULL DEFAULT now(),
    last_http_status integer,
    last_error text,
    core_object_id text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    processed_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_core_sync_outbox_queue_idx ON library_core_sync_outbox(status, next_attempt_at, created_at);
CREATE INDEX IF NOT EXISTS library_core_sync_outbox_record_idx ON library_core_sync_outbox(library_record_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_core_sync_outbox_core_idx ON library_core_sync_outbox(core_object_id, created_at DESC);

-- v2.24.0 — Hybrid Research Retrieval & Core-Aware Results.
-- Semantic vectors remain a Library-owned retrieval artifact. Platform Core is
-- referenced through durable bindings and remains authoritative for governed
-- research/evidence objects, provenance, reasoning, synthesis, and exchange.
CREATE OR REPLACE FUNCTION sc_cosine_similarity(a double precision[], b double precision[])
RETURNS double precision
LANGUAGE SQL
IMMUTABLE
STRICT
PARALLEL SAFE
AS $$
    SELECT CASE
        WHEN cardinality(a)=0 OR cardinality(a)<>cardinality(b) THEN NULL
        ELSE (
            SELECT sum(a[i]*b[i]) /
                   NULLIF(sqrt(sum(a[i]*a[i])) * sqrt(sum(b[i]*b[i])), 0)
              FROM generate_subscripts(a,1) AS g(i)
        )
    END
$$;

CREATE TABLE IF NOT EXISTS library_record_embeddings (
    record_id text PRIMARY KEY REFERENCES library_records(record_id) ON DELETE CASCADE,
    content_hash char(64) NOT NULL,
    input_hash char(64) NOT NULL,
    provider text NOT NULL,
    model text NOT NULL,
    dimensions integer NOT NULL CHECK (dimensions BETWEEN 1 AND 4096),
    embedding double precision[] NOT NULL,
    specification_fingerprint char(64),
    representation_id text,
    execution_target text NOT NULL DEFAULT 'local',
    execution_id text,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CHECK (cardinality(embedding)=dimensions)
);
ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS specification_fingerprint char(64);
ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS representation_id text;
ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS execution_target text NOT NULL DEFAULT 'local';
ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS execution_id text;
ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS provenance jsonb NOT NULL DEFAULT '{}'::jsonb;
CREATE INDEX IF NOT EXISTS library_record_embeddings_model_idx
    ON library_record_embeddings(provider,model,dimensions,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_record_embeddings_content_idx
    ON library_record_embeddings(content_hash,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_record_embeddings_specification_idx
    ON library_record_embeddings(specification_fingerprint,dimensions,updated_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS library_record_embeddings_representation_uidx
    ON library_record_embeddings(representation_id) WHERE representation_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS library_embedding_jobs (
    job_id bigserial PRIMARY KEY,
    record_id text NOT NULL UNIQUE REFERENCES library_records(record_id) ON DELETE CASCADE,
    content_hash char(64) NOT NULL,
    input_hash char(64),
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','processing','retry','complete','failed','cancelled')),
    attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
    next_attempt_at timestamptz NOT NULL DEFAULT now(),
    provider text,
    model text,
    dimensions integer,
    specification_fingerprint char(64),
    execution_target text NOT NULL DEFAULT 'local',
    execution_id text,
    handoff_id text,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    last_error text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz
);
ALTER TABLE library_embedding_jobs ADD COLUMN IF NOT EXISTS specification_fingerprint char(64);
ALTER TABLE library_embedding_jobs ADD COLUMN IF NOT EXISTS execution_target text NOT NULL DEFAULT 'local';
ALTER TABLE library_embedding_jobs ADD COLUMN IF NOT EXISTS execution_id text;
ALTER TABLE library_embedding_jobs ADD COLUMN IF NOT EXISTS handoff_id text;
ALTER TABLE library_embedding_jobs ADD COLUMN IF NOT EXISTS provenance jsonb NOT NULL DEFAULT '{}'::jsonb;
CREATE INDEX IF NOT EXISTS library_embedding_jobs_queue_idx
    ON library_embedding_jobs(status,next_attempt_at,created_at);
CREATE INDEX IF NOT EXISTS library_embedding_jobs_execution_idx
    ON library_embedding_jobs(execution_target,status,next_attempt_at,created_at);

-- v2.51.0 — Scientific Embedding Governance & Workspace Compute Handoff.
-- The Library keeps its operational vector index, Platform Core owns governed
-- representation contracts, and Workspace may execute model compute. A handoff
-- result is an analytical representation and never becomes evidence/truth by itself.
CREATE TABLE IF NOT EXISTS library_embedding_compute_handoffs (
    handoff_id text PRIMARY KEY,
    job_id bigint NOT NULL REFERENCES library_embedding_jobs(job_id) ON DELETE CASCADE,
    record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    content_hash char(64) NOT NULL,
    input_hash char(64) NOT NULL,
    specification_fingerprint char(64) NOT NULL,
    status text NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','claimed','retry','complete','failed','cancelled')),
    attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
    claimed_by text,
    claimed_at timestamptz,
    workspace_execution_id text,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    last_error text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_embedding_handoffs_queue_idx
    ON library_embedding_compute_handoffs(status,created_at);
CREATE INDEX IF NOT EXISTS library_embedding_handoffs_record_idx
    ON library_embedding_compute_handoffs(record_id,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_embedding_handoffs_spec_idx
    ON library_embedding_compute_handoffs(specification_fingerprint,status);

-- Queue legacy/public records that do not yet have a semantic vector. Existing
-- job rows are preserved, so service restarts do not reset completed work.
INSERT INTO library_embedding_jobs(record_id,content_hash,status)
SELECT r.record_id,r.content_hash,'pending'
  FROM library_records r
  LEFT JOIN library_record_embeddings e
    ON e.record_id=r.record_id AND e.content_hash=r.content_hash
 WHERE r.visibility='public' AND r.publication_status='published' AND e.record_id IS NULL
ON CONFLICT (record_id) DO NOTHING;

-- v2.25.0 — Citation Graph & Scholarly Lineage.
-- Library stores declared/imported citation relationships and exact persistent-
-- identifier resolution. Governed scholarly lineage remains a Platform Core concern.
CREATE TABLE IF NOT EXISTS library_citations (
    citation_id bigserial PRIMARY KEY,
    citation_key char(64) NOT NULL UNIQUE,
    citing_record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    cited_record_id text REFERENCES library_records(record_id) ON DELETE SET NULL,
    identifier_type text NOT NULL DEFAULT 'other',
    identifier_value text NOT NULL DEFAULT '',
    raw_citation text NOT NULL DEFAULT '',
    relation_type text NOT NULL DEFAULT 'cites',
    extraction_method text NOT NULL DEFAULT 'manual',
    confidence double precision NOT NULL DEFAULT 1.0 CHECK (confidence >= 0 AND confidence <= 1),
    locator text NOT NULL DEFAULT '',
    source_chunk_ordinal integer CHECK (source_chunk_ordinal IS NULL OR source_chunk_ordinal >= 0),
    resolution_status text NOT NULL DEFAULT 'unresolved' CHECK (resolution_status IN ('resolved','unresolved','rejected')),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_citations_citing_idx ON library_citations(citing_record_id, updated_at DESC);
CREATE INDEX IF NOT EXISTS library_citations_cited_idx ON library_citations(cited_record_id, updated_at DESC) WHERE cited_record_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS library_citations_identifier_idx ON library_citations(identifier_type, lower(identifier_value)) WHERE identifier_value <> '';
CREATE INDEX IF NOT EXISTS library_citations_resolution_idx ON library_citations(resolution_status, updated_at DESC);


-- v2.26.0 — Entity, Finding & Claim Extraction.
-- These rows are Library-owned extraction candidates anchored to source spans.
-- They are not governed Platform Core findings/claims until a human accepts a
-- candidate and explicitly queues it through the allowlisted Core bridge.
CREATE TABLE IF NOT EXISTS library_research_candidates (
    candidate_id bigserial PRIMARY KEY,
    candidate_key char(64) NOT NULL UNIQUE,
    record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    candidate_type text NOT NULL CHECK (candidate_type IN ('entity','finding','claim')),
    candidate_text text NOT NULL,
    entity_type text,
    extraction_method text NOT NULL DEFAULT 'rule-based',
    confidence double precision NOT NULL DEFAULT 0.5 CHECK (confidence >= 0 AND confidence <= 1),
    source_locator text NOT NULL,
    source_chunk_ordinal integer CHECK (source_chunk_ordinal IS NULL OR source_chunk_ordinal >= 0),
    char_start integer NOT NULL DEFAULT 0 CHECK (char_start >= 0),
    char_end integer NOT NULL DEFAULT 0 CHECK (char_end >= char_start),
    source_content_hash char(64),
    review_state text NOT NULL DEFAULT 'pending' CHECK (review_state IN ('pending','accepted','rejected','superseded')),
    reviewer text,
    review_note text NOT NULL DEFAULT '',
    reviewed_at timestamptz,
    core_operation text,
    core_outbox_event_id bigint REFERENCES library_core_sync_outbox(event_id) ON DELETE SET NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_research_candidates_record_idx
    ON library_research_candidates(record_id,candidate_type,review_state,confidence DESC);
CREATE INDEX IF NOT EXISTS library_research_candidates_review_idx
    ON library_research_candidates(review_state,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_research_candidates_core_idx
    ON library_research_candidates(core_outbox_event_id) WHERE core_outbox_event_id IS NOT NULL;


-- v2.27.0 — Publication Visualization Foundations.
-- Library stores renderer-neutral publication visualization specifications and
-- their review state. Platform Core remains authoritative for governed visual
-- research objects, visual reasoning, provenance, reproducibility, and exchange.
CREATE TABLE IF NOT EXISTS library_publication_visualizations (
    visualization_id bigserial PRIMARY KEY,
    visualization_key char(64) NOT NULL UNIQUE,
    record_id text NOT NULL REFERENCES library_records(record_id) ON DELETE CASCADE,
    visualization_kind text NOT NULL CHECK (visualization_kind IN ('citation-network','concept-map','finding-map','claim-map')),
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    source_content_hash char(64) NOT NULL,
    specification jsonb NOT NULL DEFAULT '{}'::jsonb,
    spec_hash char(64) NOT NULL,
    review_state text NOT NULL DEFAULT 'draft' CHECK (review_state IN ('draft','published','rejected','superseded')),
    reviewer text,
    review_note text NOT NULL DEFAULT '',
    reviewed_at timestamptz,
    created_by text NOT NULL DEFAULT 'library-visualization-builder',
    core_operation text,
    core_outbox_event_id bigint REFERENCES library_core_sync_outbox(event_id) ON DELETE SET NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_publication_visualizations_record_idx
    ON library_publication_visualizations(record_id,review_state,visualization_kind,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_publication_visualizations_review_idx
    ON library_publication_visualizations(review_state,updated_at DESC);
CREATE INDEX IF NOT EXISTS library_publication_visualizations_core_idx
    ON library_publication_visualizations(core_outbox_event_id) WHERE core_outbox_event_id IS NOT NULL;

-- v2.56.0 — Original-Language Corpus Ingestion & Preservation.
-- Exact source bytes/text remain immutable preservation artifacts. Any normalized
-- representation is stored separately with explicit transformation lineage.
CREATE TABLE IF NOT EXISTS library_original_language_captures (
    capture_id text PRIMARY KEY,
    capture_fingerprint char(64) NOT NULL UNIQUE,
    record_id text,
    source_id text,
    source_record_id text,
    source_uri text,
    language_bcp47 text NOT NULL,
    script_iso15924 varchar(4),
    language_variant text,
    orthography_variant text,
    media_type text NOT NULL DEFAULT 'text/plain',
    charset text NOT NULL DEFAULT 'utf-8',
    raw_payload bytea NOT NULL,
    raw_payload_sha256 char(64) NOT NULL,
    raw_text text NOT NULL,
    raw_text_sha256 char(64) NOT NULL,
    source_metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    retrieved_at timestamptz NOT NULL DEFAULT now(),
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_original_language_capture_record_idx ON library_original_language_captures(record_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_original_language_capture_source_idx ON library_original_language_captures(source_id, source_record_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_original_language_capture_language_idx ON library_original_language_captures(language_bcp47, script_iso15924, created_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS library_original_language_capture_payload_uidx ON library_original_language_captures(raw_payload_sha256, source_id, source_record_id);

CREATE TABLE IF NOT EXISTS library_text_representations (
    representation_id text PRIMARY KEY,
    capture_id text NOT NULL REFERENCES library_original_language_captures(capture_id) ON DELETE RESTRICT,
    representation_kind text NOT NULL CHECK (representation_kind IN ('original','unicode-normalized','transliteration','translation','ocr','htr','transcription','editorial-normalization')),
    language_bcp47 text NOT NULL,
    script_iso15924 varchar(4),
    language_variant text,
    orthography_variant text,
    text_content text NOT NULL,
    text_sha256 char(64) NOT NULL,
    canonical_original boolean NOT NULL DEFAULT false,
    derived boolean NOT NULL DEFAULT true,
    normalization_form varchar(4),
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (NOT canonical_original OR (representation_kind='original' AND derived=false)),
    CHECK (representation_kind<>'original' OR canonical_original=true)
);
CREATE INDEX IF NOT EXISTS library_text_representations_capture_idx ON library_text_representations(capture_id, created_at ASC);
CREATE INDEX IF NOT EXISTS library_text_representations_language_idx ON library_text_representations(language_bcp47, script_iso15924, representation_kind);
CREATE UNIQUE INDEX IF NOT EXISTS library_text_representations_original_uidx ON library_text_representations(capture_id) WHERE canonical_original=true;

CREATE TABLE IF NOT EXISTS library_text_transformations (
    transformation_id text PRIMARY KEY,
    capture_id text NOT NULL REFERENCES library_original_language_captures(capture_id) ON DELETE RESTRICT,
    input_representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    output_representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    operation text NOT NULL,
    parameters jsonb NOT NULL DEFAULT '{}'::jsonb,
    automatic boolean NOT NULL DEFAULT false,
    lossless_claim boolean NOT NULL DEFAULT false,
    translation boolean NOT NULL DEFAULT false,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (input_representation_id<>output_representation_id)
);
CREATE INDEX IF NOT EXISTS library_text_transformations_capture_idx ON library_text_transformations(capture_id, created_at ASC);
CREATE INDEX IF NOT EXISTS library_text_transformations_input_idx ON library_text_transformations(input_representation_id, created_at ASC);
CREATE INDEX IF NOT EXISTS library_text_transformations_output_idx ON library_text_transformations(output_representation_id, created_at ASC);

-- v2.57.0 — OCR, HTR & Transcription Lineage.
-- Media source bytes are preserved independently of text captures because OCR/HTR
-- and transcription may originate from images, PDFs, audio, or video rather than text.
CREATE TABLE IF NOT EXISTS library_source_media_assets (
    source_asset_id text PRIMARY KEY,
    asset_fingerprint char(64) NOT NULL UNIQUE,
    record_id text,
    source_id text,
    source_record_id text,
    source_uri text,
    media_type text NOT NULL DEFAULT 'application/octet-stream',
    raw_payload bytea NOT NULL,
    raw_payload_sha256 char(64) NOT NULL,
    source_metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_source_media_assets_record_idx ON library_source_media_assets(record_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_source_media_assets_source_idx ON library_source_media_assets(source_id, source_record_id, created_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS library_source_media_assets_payload_uidx ON library_source_media_assets(raw_payload_sha256, source_id, source_record_id);

ALTER TABLE library_text_representations ADD COLUMN IF NOT EXISTS source_asset_id text;
ALTER TABLE library_text_representations ALTER COLUMN capture_id DROP NOT NULL;
CREATE INDEX IF NOT EXISTS library_text_representations_source_asset_idx ON library_text_representations(source_asset_id, representation_kind, created_at ASC);
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname='library_text_representations_source_asset_fk'
    ) THEN
        ALTER TABLE library_text_representations
            ADD CONSTRAINT library_text_representations_source_asset_fk
            FOREIGN KEY (source_asset_id) REFERENCES library_source_media_assets(source_asset_id) ON DELETE RESTRICT;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS library_text_derivation_runs (
    run_id text PRIMARY KEY,
    run_fingerprint char(64) NOT NULL UNIQUE,
    derivation_kind text NOT NULL CHECK (derivation_kind IN ('ocr','htr','transcription')),
    source_asset_id text REFERENCES library_source_media_assets(source_asset_id) ON DELETE RESTRICT,
    input_representation_id text REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    output_representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    engine_provider text NOT NULL,
    engine_name text NOT NULL,
    engine_version text,
    model_name text,
    model_version text,
    engine_spec_fingerprint char(64) NOT NULL,
    parameters jsonb NOT NULL DEFAULT '{}'::jsonb,
    language_bcp47 text NOT NULL,
    script_iso15924 varchar(4),
    output_text_sha256 char(64) NOT NULL,
    confidence_summary jsonb NOT NULL DEFAULT '{}'::jsonb,
    review_state text NOT NULL DEFAULT 'unreviewed' CHECK (review_state IN ('unreviewed','in-review','human-reviewed','accepted','rejected')),
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (source_asset_id IS NOT NULL OR input_representation_id IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS library_text_derivation_runs_kind_idx ON library_text_derivation_runs(derivation_kind, created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_derivation_runs_source_idx ON library_text_derivation_runs(source_asset_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_derivation_runs_input_idx ON library_text_derivation_runs(input_representation_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_derivation_runs_output_idx ON library_text_derivation_runs(output_representation_id, created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_derivation_runs_engine_idx ON library_text_derivation_runs(engine_spec_fingerprint, created_at DESC);

CREATE TABLE IF NOT EXISTS library_text_derivation_segments (
    segment_id text PRIMARY KEY,
    run_id text NOT NULL REFERENCES library_text_derivation_runs(run_id) ON DELETE RESTRICT,
    sequence integer NOT NULL CHECK (sequence > 0),
    segment_kind text NOT NULL,
    page_number integer,
    start_ms bigint,
    end_ms bigint,
    bounding_box jsonb,
    speaker_label text,
    text_content text NOT NULL,
    text_sha256 char(64) NOT NULL,
    confidence double precision CHECK (confidence IS NULL OR (confidence >= 0.0 AND confidence <= 1.0)),
    review_state text NOT NULL DEFAULT 'unreviewed' CHECK (review_state IN ('unreviewed','in-review','human-reviewed','accepted','rejected')),
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (run_id, sequence),
    CHECK (start_ms IS NULL OR start_ms >= 0),
    CHECK (end_ms IS NULL OR (start_ms IS NOT NULL AND end_ms >= start_ms))
);
CREATE INDEX IF NOT EXISTS library_text_derivation_segments_run_idx ON library_text_derivation_segments(run_id, sequence ASC);
CREATE INDEX IF NOT EXISTS library_text_derivation_segments_page_idx ON library_text_derivation_segments(run_id, page_number, sequence ASC);
