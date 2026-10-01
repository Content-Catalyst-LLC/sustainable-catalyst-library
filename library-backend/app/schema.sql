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

-- v2.58.0 — Linguistic Corpus Objects, Concordance & KWIC.
-- Corpus/token objects preserve representation lineage and deterministic offsets.
-- This foundation does not infer morphology, lemma, POS, syntax, meaning, or intent.
CREATE TABLE IF NOT EXISTS library_linguistic_corpora (
    corpus_id text PRIMARY KEY,
    corpus_fingerprint char(64) NOT NULL UNIQUE,
    title text NOT NULL,
    description text,
    tokenizer_spec jsonb NOT NULL,
    tokenizer_spec_fingerprint char(64) NOT NULL,
    language_distribution jsonb NOT NULL DEFAULT '{}'::jsonb,
    script_distribution jsonb NOT NULL DEFAULT '{}'::jsonb,
    source_kind_distribution jsonb NOT NULL DEFAULT '{}'::jsonb,
    document_count integer NOT NULL DEFAULT 0 CHECK (document_count >= 0),
    token_count bigint NOT NULL DEFAULT 0 CHECK (token_count >= 0),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_linguistic_corpora_tokenizer_idx ON library_linguistic_corpora(tokenizer_spec_fingerprint, created_at DESC);

CREATE TABLE IF NOT EXISTS library_linguistic_documents (
    document_id text PRIMARY KEY,
    corpus_id text NOT NULL REFERENCES library_linguistic_corpora(corpus_id) ON DELETE RESTRICT,
    sequence integer NOT NULL CHECK (sequence > 0),
    representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    record_id text,
    capture_id text REFERENCES library_original_language_captures(capture_id) ON DELETE RESTRICT,
    source_asset_id text REFERENCES library_source_media_assets(source_asset_id) ON DELETE RESTRICT,
    derivation_run_id text REFERENCES library_text_derivation_runs(run_id) ON DELETE RESTRICT,
    source_kind text NOT NULL CHECK (source_kind IN ('original','unicode-normalized','ocr','htr','transcription','other-derived')),
    language_bcp47 text NOT NULL,
    script_iso15924 varchar(4),
    language_variant text,
    orthography_variant text,
    review_state text,
    text_sha256 char(64) NOT NULL,
    character_count bigint NOT NULL CHECK (character_count >= 0),
    token_count bigint NOT NULL CHECK (token_count >= 0),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (corpus_id, sequence),
    UNIQUE (corpus_id, representation_id)
);
CREATE INDEX IF NOT EXISTS library_linguistic_documents_corpus_idx ON library_linguistic_documents(corpus_id, sequence ASC);
CREATE INDEX IF NOT EXISTS library_linguistic_documents_representation_idx ON library_linguistic_documents(representation_id, corpus_id);
CREATE INDEX IF NOT EXISTS library_linguistic_documents_language_idx ON library_linguistic_documents(language_bcp47, script_iso15924, source_kind);
CREATE INDEX IF NOT EXISTS library_linguistic_documents_derivation_idx ON library_linguistic_documents(derivation_run_id, corpus_id);

CREATE TABLE IF NOT EXISTS library_linguistic_tokens (
    token_id text PRIMARY KEY,
    corpus_id text NOT NULL REFERENCES library_linguistic_corpora(corpus_id) ON DELETE RESTRICT,
    document_id text NOT NULL REFERENCES library_linguistic_documents(document_id) ON DELETE RESTRICT,
    representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    sequence integer NOT NULL CHECK (sequence > 0),
    token_text text NOT NULL,
    normalized_text text NOT NULL,
    token_kind text NOT NULL CHECK (token_kind IN ('word','punctuation')),
    start_char bigint NOT NULL CHECK (start_char >= 0),
    end_char bigint NOT NULL CHECK (end_char >= start_char),
    text_sha256 char(64) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (document_id, sequence)
);
CREATE INDEX IF NOT EXISTS library_linguistic_tokens_corpus_normalized_idx ON library_linguistic_tokens(corpus_id, normalized_text, document_id, sequence);
CREATE INDEX IF NOT EXISTS library_linguistic_tokens_document_idx ON library_linguistic_tokens(document_id, sequence ASC);
CREATE INDEX IF NOT EXISTS library_linguistic_tokens_representation_idx ON library_linguistic_tokens(representation_id, sequence ASC);


-- v2.59.0 — Cross-Language Entity, Name & Historical Toponym Resolution.
-- Candidate ranking is descriptive and never constitutes automatic identity, evidence, or truth.
CREATE TABLE IF NOT EXISTS library_cross_language_entities (
    entity_id text PRIMARY KEY,
    entity_type text NOT NULL CHECK (entity_type IN ('person','organization','place','work','event','concept','group','jurisdiction','other')),
    canonical_name text NOT NULL,
    authority_namespace text,
    authority_key text,
    country_code varchar(3),
    latitude double precision CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90)),
    longitude double precision CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180)),
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (authority_namespace, authority_key)
);
CREATE INDEX IF NOT EXISTS library_cross_language_entities_type_idx ON library_cross_language_entities(entity_type, canonical_name);

CREATE TABLE IF NOT EXISTS library_entity_name_forms (
    form_id text PRIMARY KEY,
    entity_id text NOT NULL REFERENCES library_cross_language_entities(entity_id) ON DELETE RESTRICT,
    name_text text NOT NULL,
    normalized_key text NOT NULL,
    diacritic_fold_key text NOT NULL,
    language_bcp47 text,
    script_iso15924 varchar(4),
    language_variant text,
    orthography_variant text,
    relation_type text NOT NULL CHECK (relation_type IN ('canonical','alias','variant','historical','endonym','exonym','transliteration','abbreviation','former','other')),
    transliteration_system text,
    valid_from_year integer,
    valid_to_year integer,
    source_reference text,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (valid_from_year IS NULL OR valid_to_year IS NULL OR valid_to_year >= valid_from_year)
);
CREATE INDEX IF NOT EXISTS library_entity_name_forms_normalized_idx ON library_entity_name_forms(normalized_key, entity_id);
CREATE INDEX IF NOT EXISTS library_entity_name_forms_fold_idx ON library_entity_name_forms(diacritic_fold_key, entity_id);
CREATE INDEX IF NOT EXISTS library_entity_name_forms_language_script_idx ON library_entity_name_forms(language_bcp47, script_iso15924, entity_id);
CREATE INDEX IF NOT EXISTS library_entity_name_forms_historical_idx ON library_entity_name_forms(entity_id, valid_from_year, valid_to_year);

CREATE TABLE IF NOT EXISTS library_entity_resolution_cases (
    case_id text PRIMARY KEY,
    query_payload jsonb NOT NULL,
    query_fingerprint char(64) NOT NULL,
    authority_fingerprint char(64) NOT NULL,
    candidate_count integer NOT NULL DEFAULT 0 CHECK (candidate_count >= 0),
    ambiguity jsonb NOT NULL DEFAULT '{}'::jsonb,
    guardrails jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_entity_resolution_cases_query_idx ON library_entity_resolution_cases(query_fingerprint, created_at DESC);

CREATE TABLE IF NOT EXISTS library_entity_resolution_candidates (
    candidate_id text PRIMARY KEY,
    case_id text NOT NULL REFERENCES library_entity_resolution_cases(case_id) ON DELETE RESTRICT,
    entity_id text NOT NULL REFERENCES library_cross_language_entities(entity_id) ON DELETE RESTRICT,
    matched_form_id text NOT NULL REFERENCES library_entity_name_forms(form_id) ON DELETE RESTRICT,
    rank integer NOT NULL CHECK (rank > 0),
    score double precision NOT NULL CHECK (score >= 0.0 AND score <= 1.0),
    signals jsonb NOT NULL DEFAULT '[]'::jsonb,
    temporal_status text NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (case_id, rank, entity_id)
);
CREATE INDEX IF NOT EXISTS library_entity_resolution_candidates_case_idx ON library_entity_resolution_candidates(case_id, rank ASC);
CREATE INDEX IF NOT EXISTS library_entity_resolution_candidates_entity_idx ON library_entity_resolution_candidates(entity_id, created_at DESC);

CREATE TABLE IF NOT EXISTS library_entity_resolution_decisions (
    decision_id text PRIMARY KEY,
    case_id text NOT NULL REFERENCES library_entity_resolution_cases(case_id) ON DELETE RESTRICT,
    state text NOT NULL CHECK (state IN ('accepted','rejected','ambiguous','unresolved')),
    selected_candidate_id text REFERENCES library_entity_resolution_candidates(candidate_id) ON DELETE RESTRICT,
    adjudicator text,
    rationale text,
    evidence_refs jsonb NOT NULL DEFAULT '[]'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    decision_fingerprint char(64) NOT NULL UNIQUE,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK ((state = 'accepted' AND selected_candidate_id IS NOT NULL) OR (state <> 'accepted' AND selected_candidate_id IS NULL))
);
CREATE INDEX IF NOT EXISTS library_entity_resolution_decisions_case_idx ON library_entity_resolution_decisions(case_id, created_at DESC);


-- v2.60.0 — Durable Research Job Queue & Execution State.
-- PostgreSQL is the authoritative job/execution-state store. Redis is dispatch/wake-up
-- coordination only and may be rebuilt without losing durable jobs or provenance.
CREATE TABLE IF NOT EXISTS library_research_jobs (
    job_id text PRIMARY KEY,
    job_type text NOT NULL,
    capability text NOT NULL,
    requested_runtime text NOT NULL DEFAULT 'auto',
    priority smallint NOT NULL DEFAULT 0 CHECK (priority BETWEEN -100 AND 100),
    state text NOT NULL DEFAULT 'queued' CHECK (state IN ('queued','leased','running','retry','complete','failed','cancelled')),
    progress double precision NOT NULL DEFAULT 0 CHECK (progress >= 0 AND progress <= 1),
    idempotency_key varchar(128) NOT NULL UNIQUE,
    input_manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    output_manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    provenance_context jsonb NOT NULL DEFAULT '{}'::jsonb,
    resource_hints jsonb NOT NULL DEFAULT '{}'::jsonb,
    attempt_count integer NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
    max_attempts integer NOT NULL DEFAULT 5 CHECK (max_attempts BETWEEN 1 AND 20),
    lease_owner text,
    lease_expires_at timestamptz,
    available_at timestamptz NOT NULL DEFAULT now(),
    external_execution_id text,
    dispatch_count integer NOT NULL DEFAULT 0 CHECK (dispatch_count >= 0),
    last_dispatched_at timestamptz,
    last_error_class text,
    last_error_detail text,
    job_fingerprint char(64) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    started_at timestamptz,
    completed_at timestamptz,
    cancelled_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_research_jobs_queue_idx ON library_research_jobs(state,available_at,priority DESC,created_at ASC);
CREATE INDEX IF NOT EXISTS library_research_jobs_capability_idx ON library_research_jobs(capability,state,priority DESC,created_at ASC);
CREATE INDEX IF NOT EXISTS library_research_jobs_lease_idx ON library_research_jobs(state,lease_expires_at) WHERE lease_expires_at IS NOT NULL;
CREATE INDEX IF NOT EXISTS library_research_jobs_runtime_idx ON library_research_jobs(requested_runtime,state,priority DESC);

CREATE TABLE IF NOT EXISTS library_research_job_attempts (
    attempt_id text PRIMARY KEY,
    job_id text NOT NULL REFERENCES library_research_jobs(job_id) ON DELETE CASCADE,
    attempt_no integer NOT NULL CHECK (attempt_no > 0),
    runtime_id text,
    worker_id text NOT NULL,
    state text NOT NULL CHECK (state IN ('leased','running','retry','complete','failed','cancelled')),
    lease_expires_at timestamptz,
    execution_metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    failure_class text,
    failure_detail text,
    leased_at timestamptz NOT NULL DEFAULT now(),
    started_at timestamptz,
    heartbeat_at timestamptz,
    finished_at timestamptz,
    UNIQUE(job_id,attempt_no)
);
CREATE INDEX IF NOT EXISTS library_research_job_attempts_job_idx ON library_research_job_attempts(job_id,attempt_no DESC);
CREATE INDEX IF NOT EXISTS library_research_job_attempts_worker_idx ON library_research_job_attempts(worker_id,state,lease_expires_at);

CREATE TABLE IF NOT EXISTS library_research_job_events (
    event_id bigserial PRIMARY KEY,
    job_id text NOT NULL REFERENCES library_research_jobs(job_id) ON DELETE CASCADE,
    event_type text NOT NULL,
    state text NOT NULL,
    progress double precision NOT NULL DEFAULT 0 CHECK (progress >= 0 AND progress <= 1),
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_research_job_events_job_idx ON library_research_job_events(job_id,event_id DESC);
CREATE INDEX IF NOT EXISTS library_research_job_events_type_idx ON library_research_job_events(event_type,created_at DESC);

-- Specialized Worker Runtime & Failure Isolation (Library v5.50.0 / backend v2.61.0)
CREATE TABLE IF NOT EXISTS library_research_workers (
 worker_id text PRIMARY KEY, worker_class text NOT NULL,
 state text NOT NULL DEFAULT 'active' CHECK (state IN ('active','standby','quarantined','offline')),
 runtimes text[] NOT NULL DEFAULT '{}'::text[], capabilities text[] NOT NULL DEFAULT '{}'::text[],
 concurrency_limit integer NOT NULL DEFAULT 1 CHECK (concurrency_limit BETWEEN 1 AND 32),
 profile_fingerprint char(64) NOT NULL, metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
 completed_jobs bigint NOT NULL DEFAULT 0, failed_jobs bigint NOT NULL DEFAULT 0, consecutive_failures integer NOT NULL DEFAULT 0,
 quarantine_reason text, registered_at timestamptz NOT NULL DEFAULT now(), heartbeat_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
 last_success_at timestamptz, last_failure_at timestamptz, quarantined_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_research_workers_state_idx ON library_research_workers(state,heartbeat_at DESC);
CREATE TABLE IF NOT EXISTS library_research_dead_letters (
 dead_letter_id text PRIMARY KEY, job_id text NOT NULL REFERENCES library_research_jobs(job_id) ON DELETE CASCADE,
 attempt_no integer NOT NULL, worker_id text, worker_class text, failure_class text NOT NULL, failure_detail text,
 input_manifest jsonb NOT NULL DEFAULT '{}'::jsonb, provenance_context jsonb NOT NULL DEFAULT '{}'::jsonb,
 state text NOT NULL DEFAULT 'open' CHECK (state IN ('open','reviewed','requeued','closed')), resolution_note text,
 created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_research_dead_letters_state_idx ON library_research_dead_letters(state,created_at DESC);

-- Research Artifact & Object Storage Fabric (Library v5.51.0 / backend v2.62.0)
-- PostgreSQL stores authoritative identity/metadata/provenance; heavyweight bytes live in
-- a content-addressed object store outside PostgreSQL.
CREATE TABLE IF NOT EXISTS library_research_artifacts (
    artifact_id text PRIMARY KEY,
    content_sha256 char(64) NOT NULL UNIQUE,
    artifact_type text NOT NULL,
    media_type text NOT NULL,
    byte_length bigint NOT NULL CHECK (byte_length >= 0),
    storage_backend text NOT NULL CHECK (storage_backend IN ('filesystem','s3')),
    storage_key text NOT NULL,
    original_filename text,
    source_uri text,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_by_job text,
    lifecycle_state text NOT NULL DEFAULT 'active' CHECK (lifecycle_state IN ('active','retained','quarantined','tombstoned')),
    immutable boolean NOT NULL DEFAULT true CHECK (immutable = true),
    manifest_fingerprint char(64) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    verified_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_research_artifacts_type_idx ON library_research_artifacts(artifact_type,lifecycle_state,created_at DESC);
CREATE INDEX IF NOT EXISTS library_research_artifacts_job_idx ON library_research_artifacts(created_by_job,created_at DESC) WHERE created_by_job IS NOT NULL;
CREATE INDEX IF NOT EXISTS library_research_artifacts_storage_idx ON library_research_artifacts(storage_backend,storage_key);

CREATE TABLE IF NOT EXISTS library_artifact_derivations (
    relation_id text PRIMARY KEY,
    parent_artifact_id text NOT NULL REFERENCES library_research_artifacts(artifact_id) ON DELETE RESTRICT,
    child_artifact_id text NOT NULL REFERENCES library_research_artifacts(artifact_id) ON DELETE RESTRICT,
    operation text NOT NULL,
    created_by_job text,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    relation_fingerprint char(64) NOT NULL UNIQUE,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (parent_artifact_id <> child_artifact_id)
);
CREATE INDEX IF NOT EXISTS library_artifact_derivations_parent_idx ON library_artifact_derivations(parent_artifact_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_artifact_derivations_child_idx ON library_artifact_derivations(child_artifact_id,created_at DESC);

CREATE TABLE IF NOT EXISTS library_artifact_events (
    event_id bigserial PRIMARY KEY,
    artifact_id text NOT NULL REFERENCES library_research_artifacts(artifact_id) ON DELETE RESTRICT,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_artifact_events_artifact_idx ON library_artifact_events(artifact_id,event_id DESC);

-- Checkpointed Ingestion & Research Pipeline Engine (Library v5.52.0 / backend v2.63.0)
-- Pipeline definitions/runs are PostgreSQL-authoritative. Stages compile into the existing
-- durable research-job fabric; the pipeline engine is not a second scheduler.
CREATE TABLE IF NOT EXISTS library_research_pipeline_definitions (
    pipeline_id text PRIMARY KEY,
    name text NOT NULL,
    pipeline_version text NOT NULL,
    description text,
    definition jsonb NOT NULL,
    topological_order text[] NOT NULL,
    pipeline_fingerprint char(64) NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_research_pipeline_definitions_fingerprint_idx ON library_research_pipeline_definitions(pipeline_fingerprint);

CREATE TABLE IF NOT EXISTS library_research_pipeline_runs (
    run_id text PRIMARY KEY,
    pipeline_id text NOT NULL REFERENCES library_research_pipeline_definitions(pipeline_id) ON DELETE RESTRICT,
    state text NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','running','retry','complete','failed','cancelled','blocked')),
    idempotency_key varchar(128) NOT NULL UNIQUE,
    input_manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    output_manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    run_fingerprint char(64) NOT NULL,
    resume_count integer NOT NULL DEFAULT 0 CHECK (resume_count >= 0),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz,
    cancelled_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_research_pipeline_runs_pipeline_idx ON library_research_pipeline_runs(pipeline_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_research_pipeline_runs_state_idx ON library_research_pipeline_runs(state,updated_at DESC);

CREATE TABLE IF NOT EXISTS library_research_pipeline_stage_runs (
    stage_run_id text PRIMARY KEY,
    run_id text NOT NULL REFERENCES library_research_pipeline_runs(run_id) ON DELETE CASCADE,
    stage_id text NOT NULL,
    capability text NOT NULL,
    requested_runtime text NOT NULL DEFAULT 'auto',
    depends_on text[] NOT NULL DEFAULT '{}'::text[],
    state text NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','queued','leased','running','retry','complete','failed','cancelled','blocked','skipped')),
    job_id text REFERENCES library_research_jobs(job_id) ON DELETE SET NULL,
    max_attempts integer NOT NULL DEFAULT 5 CHECK (max_attempts BETWEEN 1 AND 20),
    optional boolean NOT NULL DEFAULT false,
    stage_fingerprint char(64) NOT NULL,
    checkpoint jsonb NOT NULL DEFAULT '{}'::jsonb,
    checkpoint_fingerprint char(64),
    output_artifact_ids text[] NOT NULL DEFAULT '{}'::text[],
    failure_class text,
    failure_detail text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz,
    UNIQUE(run_id,stage_id)
);
CREATE INDEX IF NOT EXISTS library_research_pipeline_stage_runs_run_idx ON library_research_pipeline_stage_runs(run_id,state,created_at);
CREATE INDEX IF NOT EXISTS library_research_pipeline_stage_runs_job_idx ON library_research_pipeline_stage_runs(job_id) WHERE job_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS library_research_pipeline_events (
    event_id bigserial PRIMARY KEY,
    run_id text NOT NULL REFERENCES library_research_pipeline_runs(run_id) ON DELETE CASCADE,
    stage_run_id text REFERENCES library_research_pipeline_stage_runs(stage_run_id) ON DELETE CASCADE,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_research_pipeline_events_run_idx ON library_research_pipeline_events(run_id,event_id DESC);


-- Distributed Research Compute Broker & Runtime Observability (Library v5.53.0 / backend v2.64.0)
-- Placement is operational only. PostgreSQL remains authoritative for job state; the broker
-- records admission/placement lineage and descriptive runtime observations.
CREATE TABLE IF NOT EXISTS library_compute_runtime_observations (
    observation_id bigserial PRIMARY KEY,
    worker_id text REFERENCES library_research_workers(worker_id) ON DELETE SET NULL,
    worker_class text NOT NULL,
    state text NOT NULL,
    active_attempts integer NOT NULL DEFAULT 0 CHECK (active_attempts >= 0),
    concurrency_limit integer NOT NULL DEFAULT 1 CHECK (concurrency_limit >= 1),
    available_slots integer NOT NULL DEFAULT 0 CHECK (available_slots >= 0),
    recent_completed integer NOT NULL DEFAULT 0 CHECK (recent_completed >= 0),
    recent_failures integer NOT NULL DEFAULT 0 CHECK (recent_failures >= 0),
    p50_latency_ms double precision,
    heartbeat_age_seconds double precision,
    metrics jsonb NOT NULL DEFAULT '{}'::jsonb,
    observed_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_compute_runtime_observations_worker_idx ON library_compute_runtime_observations(worker_class,observed_at DESC);
CREATE INDEX IF NOT EXISTS library_compute_runtime_observations_time_idx ON library_compute_runtime_observations(observed_at DESC);

CREATE TABLE IF NOT EXISTS library_compute_placement_events (
    placement_id text PRIMARY KEY,
    job_id text REFERENCES library_research_jobs(job_id) ON DELETE SET NULL,
    compute_request_fingerprint char(64) NOT NULL,
    admission_state text NOT NULL CHECK (admission_state IN ('admitted','deferred','rejected')),
    reason text NOT NULL,
    selected_worker_class text,
    selected_runtime text,
    decision_fingerprint char(64) NOT NULL,
    candidates jsonb NOT NULL DEFAULT '[]'::jsonb,
    queue_snapshot jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_compute_placement_events_job_idx ON library_compute_placement_events(job_id,created_at DESC) WHERE job_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS library_compute_placement_events_runtime_idx ON library_compute_placement_events(selected_runtime,created_at DESC);
CREATE INDEX IF NOT EXISTS library_compute_placement_events_admission_idx ON library_compute_placement_events(admission_state,created_at DESC);

-- Knowledge Library v5.54.0 / backend v2.65.0
-- Translation & Transliteration Alignment Matrix.
-- Alignments bind preserved text representations; they never generate translation/transliteration.
CREATE TABLE IF NOT EXISTS library_text_alignment_matrices (
    matrix_id text PRIMARY KEY,
    source_representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    target_representation_id text NOT NULL REFERENCES library_text_representations(representation_id) ON DELETE RESTRICT,
    transformation_kind text NOT NULL CHECK (transformation_kind IN ('translation','transliteration')),
    transformation_id text REFERENCES library_text_transformations(transformation_id) ON DELETE RESTRICT,
    source_language_bcp47 text NOT NULL,
    source_script_iso15924 varchar(4),
    target_language_bcp47 text NOT NULL,
    target_script_iso15924 varchar(4),
    source_text_sha256 char(64) NOT NULL,
    target_text_sha256 char(64) NOT NULL,
    alignment_method text NOT NULL,
    alignment_engine text,
    alignment_engine_version text,
    review_state text NOT NULL DEFAULT 'unreviewed',
    matrix_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CHECK (source_representation_id <> target_representation_id)
);
CREATE INDEX IF NOT EXISTS library_text_alignment_matrices_source_idx ON library_text_alignment_matrices(source_representation_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_alignment_matrices_target_idx ON library_text_alignment_matrices(target_representation_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_text_alignment_matrices_language_idx ON library_text_alignment_matrices(source_language_bcp47,target_language_bcp47,transformation_kind);

CREATE TABLE IF NOT EXISTS library_text_alignment_links (
    alignment_id text PRIMARY KEY,
    matrix_id text NOT NULL REFERENCES library_text_alignment_matrices(matrix_id) ON DELETE CASCADE,
    sequence integer NOT NULL CHECK (sequence >= 1),
    relation_type text NOT NULL CHECK (relation_type IN ('aligned','partial','omitted','added','uncertain')),
    source_spans jsonb NOT NULL DEFAULT '[]'::jsonb,
    target_spans jsonb NOT NULL DEFAULT '[]'::jsonb,
    cardinality text NOT NULL,
    confidence double precision,
    confidence_kind text,
    review_state text NOT NULL DEFAULT 'unreviewed',
    rationale text,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1)),
    UNIQUE(matrix_id,sequence)
);
CREATE INDEX IF NOT EXISTS library_text_alignment_links_matrix_idx ON library_text_alignment_links(matrix_id,sequence);
CREATE INDEX IF NOT EXISTS library_text_alignment_links_review_idx ON library_text_alignment_links(review_state,matrix_id);


-- Knowledge Library v5.55.0 / backend v2.66.0
CREATE TABLE IF NOT EXISTS library_cross_civilizational_links (
 link_id text PRIMARY KEY, source_object_id text NOT NULL, source_object_type text NOT NULL, target_object_id text NOT NULL, target_object_type text NOT NULL, source_evidence_class text NOT NULL, target_evidence_class text NOT NULL, link_type text NOT NULL, basis jsonb NOT NULL, interpretation_boundary text NOT NULL, confidence double precision, confidence_kind text, review_state text NOT NULL DEFAULT 'unreviewed', source_context jsonb NOT NULL, target_context jsonb NOT NULL, link_fingerprint char(64) NOT NULL UNIQUE, provenance jsonb NOT NULL DEFAULT '{}'::jsonb, metadata jsonb NOT NULL DEFAULT '{}'::jsonb, created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(), CHECK (source_object_id <> target_object_id OR source_object_type <> target_object_type), CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))
);
CREATE INDEX IF NOT EXISTS library_cross_civilizational_links_source_idx ON library_cross_civilizational_links(source_object_type,source_object_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_cross_civilizational_links_target_idx ON library_cross_civilizational_links(target_object_type,target_object_id,created_at DESC);
CREATE INDEX IF NOT EXISTS library_cross_civilizational_links_type_idx ON library_cross_civilizational_links(link_type,review_state,created_at DESC);
CREATE TABLE IF NOT EXISTS library_cross_civilizational_link_events (event_id bigserial PRIMARY KEY,link_id text NOT NULL REFERENCES library_cross_civilizational_links(link_id) ON DELETE CASCADE,event_type text NOT NULL,details jsonb NOT NULL DEFAULT '{}'::jsonb,created_at timestamptz NOT NULL DEFAULT now());
CREATE INDEX IF NOT EXISTS library_cross_civilizational_link_events_link_idx ON library_cross_civilizational_link_events(link_id,event_id DESC);

-- Source Transparency, Quality Signals & User Trust Policies (Library v5.56.0 / backend v2.67.0)
-- Descriptive source-quality signals remain separate from user-defined trust choices.
CREATE TABLE IF NOT EXISTS library_source_quality_signals (
    signal_id text PRIMARY KEY,
    source_object_id text NOT NULL,
    source_object_type text NOT NULL,
    signal_type text NOT NULL,
    value_kind text NOT NULL,
    value jsonb NOT NULL,
    observed_at timestamptz,
    observation_basis text,
    signal_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_source_quality_signals_source_idx ON library_source_quality_signals(source_object_id,signal_type,created_at DESC);
CREATE INDEX IF NOT EXISTS library_source_quality_signals_type_idx ON library_source_quality_signals(signal_type,created_at DESC);

CREATE TABLE IF NOT EXISTS library_source_transparency_profiles (
    profile_id text PRIMARY KEY,
    source_object_id text NOT NULL,
    source_object_type text NOT NULL,
    signal_ids text[] NOT NULL DEFAULT '{}',
    profile_context jsonb NOT NULL DEFAULT '{}'::jsonb,
    profile_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_source_transparency_profiles_source_idx ON library_source_transparency_profiles(source_object_id,updated_at DESC);

CREATE TABLE IF NOT EXISTS library_user_trust_policies (
    policy_id text PRIMARY KEY,
    owner_id text NOT NULL,
    name text NOT NULL,
    scope text NOT NULL CHECK (scope IN ('personal','project','team','institutional')),
    state text NOT NULL DEFAULT 'draft' CHECK (state IN ('draft','active','disabled','archived')),
    rules jsonb NOT NULL,
    default_action text NOT NULL DEFAULT 'allow' CHECK (default_action IN ('include','exclude','flag','prioritize-review','require-human-review','allow')),
    policy_fingerprint char(64) NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_user_trust_policies_owner_idx ON library_user_trust_policies(owner_id,state,updated_at DESC);

CREATE TABLE IF NOT EXISTS library_user_trust_policy_events (
    event_id bigserial PRIMARY KEY,
    policy_id text NOT NULL REFERENCES library_user_trust_policies(policy_id) ON DELETE RESTRICT,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_user_trust_policy_events_policy_idx ON library_user_trust_policy_events(policy_id,event_id DESC);


-- Global Knowledge Federation milestone (Library v5.57.0 / backend v2.68.0)
CREATE TABLE IF NOT EXISTS library_global_knowledge_federation_certifications (
    certification_id text PRIMARY KEY,
    federation_id text NOT NULL,
    state text NOT NULL CHECK (state IN ('certified','superseded','revoked')),
    component_snapshot jsonb NOT NULL,
    scope jsonb NOT NULL DEFAULT '{}'::jsonb,
    certification_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    guardrails jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_global_knowledge_federation_certifications_federation_idx ON library_global_knowledge_federation_certifications(federation_id,created_at DESC);
CREATE TABLE IF NOT EXISTS library_global_knowledge_federation_events (
    event_id bigserial PRIMARY KEY,
    certification_id text NOT NULL REFERENCES library_global_knowledge_federation_certifications(certification_id) ON DELETE RESTRICT,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_global_knowledge_federation_events_cert_idx ON library_global_knowledge_federation_events(certification_id,event_id DESC);


-- Library Runtime Authority & WordPress Decoupling Foundation (Library v5.58.0 / backend v2.69.0)
-- PostgreSQL records certification of the dependency/ownership boundary; WordPress is a non-authoritative client adapter.
CREATE TABLE IF NOT EXISTS library_runtime_authority_certifications (
    certification_id text PRIMARY KEY,
    authority_id text NOT NULL,
    state text NOT NULL CHECK (state IN ('certified','superseded','revoked')),
    component_snapshot jsonb NOT NULL,
    client_snapshot jsonb NOT NULL,
    ownership_snapshot jsonb NOT NULL,
    dependency_graph jsonb NOT NULL,
    certification_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    guardrails jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_runtime_authority_certifications_authority_idx ON library_runtime_authority_certifications(authority_id,created_at DESC);
CREATE TABLE IF NOT EXISTS library_runtime_authority_events (
    event_id bigserial PRIMARY KEY,
    certification_id text NOT NULL REFERENCES library_runtime_authority_certifications(certification_id) ON DELETE RESTRICT,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_runtime_authority_events_cert_idx ON library_runtime_authority_events(certification_id,event_id DESC);


-- Independent Library API v1 & Service Contract (Library v5.59.0 / backend v2.70.0)
-- This records API contract publication. WordPress is not an API runtime dependency.
CREATE TABLE IF NOT EXISTS library_api_service_contracts (
    contract_id text PRIMARY KEY,
    api_version text NOT NULL,
    state text NOT NULL CHECK (state IN ('stable','superseded','revoked')),
    base_path text NOT NULL,
    route_catalog jsonb NOT NULL,
    capability_catalog jsonb NOT NULL,
    auth_contract jsonb NOT NULL,
    error_contract text NOT NULL,
    pagination_contract text NOT NULL,
    contract_fingerprint char(64) NOT NULL UNIQUE,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    guardrails jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_api_service_contracts_version_idx ON library_api_service_contracts(api_version,created_at DESC);
CREATE TABLE IF NOT EXISTS library_api_service_contract_events (
    event_id bigserial PRIMARY KEY,
    contract_id text NOT NULL REFERENCES library_api_service_contracts(contract_id) ON DELETE RESTRICT,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_api_service_contract_events_contract_idx ON library_api_service_contract_events(contract_id,event_id DESC);

-- Library Identity, Session & Access Boundary (Library v5.61.0 / backend v2.72.0)
-- Library identities and sessions are authoritative in the Library service. WordPress may bridge
-- an identity assertion later, but WordPress users/cookies are not authoritative Library sessions.
CREATE TABLE IF NOT EXISTS library_identities (
    identity_id text PRIMARY KEY,
    principal_type text NOT NULL CHECK (principal_type IN ('user','service','institution')),
    handle text NOT NULL,
    handle_normalized text NOT NULL UNIQUE,
    display_name text NOT NULL,
    status text NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended','disabled')),
    attributes jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_identities_type_status_idx ON library_identities(principal_type,status,created_at DESC);

CREATE TABLE IF NOT EXISTS library_identity_credentials (
    credential_id text PRIMARY KEY,
    identity_id text NOT NULL REFERENCES library_identities(identity_id) ON DELETE CASCADE,
    credential_type text NOT NULL CHECK (credential_type IN ('password','external-assertion')),
    secret_hash text NOT NULL,
    state text NOT NULL DEFAULT 'active' CHECK (state IN ('active','superseded','revoked')),
    failed_attempts integer NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
    locked_until timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    last_used_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_identity_credentials_identity_idx ON library_identity_credentials(identity_id,credential_type,state,created_at DESC);

CREATE TABLE IF NOT EXISTS library_identity_role_bindings (
    binding_id text PRIMARY KEY,
    identity_id text NOT NULL REFERENCES library_identities(identity_id) ON DELETE CASCADE,
    role text NOT NULL CHECK (role IN ('reader','institution-member','researcher','steward','admin','service')),
    resource_type text NOT NULL DEFAULT 'global',
    resource_id text NOT NULL DEFAULT '*',
    state text NOT NULL DEFAULT 'active' CHECK (state IN ('active','revoked')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(identity_id,role,resource_type,resource_id)
);
CREATE INDEX IF NOT EXISTS library_identity_role_bindings_identity_idx ON library_identity_role_bindings(identity_id,state,role);

CREATE TABLE IF NOT EXISTS library_sessions (
    session_id text PRIMARY KEY,
    identity_id text NOT NULL REFERENCES library_identities(identity_id) ON DELETE CASCADE,
    token_sha256 char(64) NOT NULL UNIQUE,
    csrf_sha256 char(64) NOT NULL,
    state text NOT NULL DEFAULT 'active' CHECK (state IN ('active','revoked','expired')),
    issued_at timestamptz NOT NULL,
    expires_at timestamptz NOT NULL,
    last_seen_at timestamptz,
    revoked_at timestamptz,
    revocation_reason text,
    client_label text,
    user_agent_sha256 char(64),
    remote_addr_sha256 char(64),
    roles_snapshot jsonb NOT NULL DEFAULT '[]'::jsonb,
    scopes_snapshot jsonb NOT NULL DEFAULT '[]'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (expires_at > issued_at)
);
CREATE INDEX IF NOT EXISTS library_sessions_identity_idx ON library_sessions(identity_id,state,expires_at DESC);
CREATE INDEX IF NOT EXISTS library_sessions_expiry_idx ON library_sessions(state,expires_at);

CREATE TABLE IF NOT EXISTS library_access_grants (
    grant_id text PRIMARY KEY,
    identity_id text NOT NULL REFERENCES library_identities(identity_id) ON DELETE CASCADE,
    resource_type text NOT NULL,
    resource_id text NOT NULL,
    action text NOT NULL,
    effect text NOT NULL CHECK (effect IN ('allow','deny')),
    expires_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_access_grants_identity_idx ON library_access_grants(identity_id,resource_type,resource_id,action,effect);

CREATE TABLE IF NOT EXISTS library_identity_events (
    event_id bigserial PRIMARY KEY,
    identity_id text REFERENCES library_identities(identity_id) ON DELETE SET NULL,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_identity_events_identity_idx ON library_identity_events(identity_id,event_id DESC);

-- Direct Cross-Product Library Service Integration (Library v5.64.0 / backend v2.75.0)
-- Stores only non-secret service binding metadata. Credentials remain environment/secret-manager material.
CREATE TABLE IF NOT EXISTS library_cross_product_service_bindings (
    binding_id text PRIMARY KEY,
    product_key text NOT NULL UNIQUE CHECK (product_key IN ('research-librarian','workspace','research-lab','workbench','decision-studio','site-intelligence')),
    service_identity_id text REFERENCES library_identities(identity_id) ON DELETE SET NULL,
    client_base_url text NOT NULL DEFAULT '',
    status text NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
    capability_families jsonb NOT NULL DEFAULT '[]'::jsonb,
    scopes jsonb NOT NULL DEFAULT '[]'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_cross_product_service_bindings_status_idx ON library_cross_product_service_bindings(status,product_key);

CREATE TABLE IF NOT EXISTS library_cross_product_service_events (
    event_id bigserial PRIMARY KEY,
    binding_id text REFERENCES library_cross_product_service_bindings(binding_id) ON DELETE SET NULL,
    product_key text NOT NULL,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_cross_product_service_events_product_idx ON library_cross_product_service_events(product_key,event_id DESC);


-- Library State Migration & WordPress Data Retirement (Library v5.65.0 / backend v2.76.0)
CREATE TABLE IF NOT EXISTS library_wordpress_state_migration_runs (
    run_id text PRIMARY KEY,
    manifest_sha256 text NOT NULL UNIQUE,
    source_site text NOT NULL DEFAULT '',
    expected_items integer NOT NULL CHECK (expected_items >= 0),
    imported_items integer NOT NULL DEFAULT 0 CHECK (imported_items >= 0),
    status text NOT NULL DEFAULT 'importing',
    retirement_eligible boolean NOT NULL DEFAULT false,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    guardrails jsonb NOT NULL DEFAULT '{}'::jsonb,
    started_at timestamptz NOT NULL DEFAULT now(),
    certified_at timestamptz
);
CREATE INDEX IF NOT EXISTS library_wordpress_state_migration_runs_status_idx ON library_wordpress_state_migration_runs(status, started_at DESC);

CREATE TABLE IF NOT EXISTS library_wordpress_state_migration_items (
    item_id text PRIMARY KEY,
    run_id text NOT NULL REFERENCES library_wordpress_state_migration_runs(run_id) ON DELETE CASCADE,
    domain text NOT NULL,
    source_kind text NOT NULL,
    source_key text NOT NULL,
    source_id text NOT NULL,
    content_sha256 text NOT NULL,
    payload jsonb NOT NULL,
    provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
    imported_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE(run_id, domain, source_kind, source_key, source_id)
);
CREATE INDEX IF NOT EXISTS library_wordpress_state_migration_items_run_idx ON library_wordpress_state_migration_items(run_id, domain, source_kind);

CREATE TABLE IF NOT EXISTS library_wordpress_state_migration_events (
    event_id bigserial PRIMARY KEY,
    run_id text REFERENCES library_wordpress_state_migration_runs(run_id) ON DELETE SET NULL,
    event_type text NOT NULL,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS library_wordpress_state_migration_events_run_idx ON library_wordpress_state_migration_events(run_id, event_id DESC);

CREATE TABLE IF NOT EXISTS library_wordpress_retirement_certifications (
    certification_id text PRIMARY KEY,
    run_id text NOT NULL UNIQUE REFERENCES library_wordpress_state_migration_runs(run_id) ON DELETE CASCADE,
    certification_sha256 text NOT NULL UNIQUE,
    status text NOT NULL DEFAULT 'certified',
    item_count integer NOT NULL CHECK (item_count >= 0),
    rollback_copy_retained boolean NOT NULL DEFAULT true,
    destructive_delete_allowed boolean NOT NULL DEFAULT false,
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    certified_at timestamptz NOT NULL DEFAULT now()
);
