(() => {
  'use strict';
  const cfg = window.SCLibraryPortability || {};
  const storageKey = cfg.storageKey || 'scLibraryWorkspaceV120';
  const postgresSchema = String(cfg.postgresSchema || 'sustainable_catalyst_library').replace(/[^a-z0-9_]/gi, '_').toLowerCase();
  const roots = Array.from(document.querySelectorAll('[data-sc-library-portability], [data-sc-library-workspace-root]'));
  if (!roots.length) return;

  const now = () => new Date().toISOString();
  const safeFile = (value) => String(value).toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^-|-$/g, '');
  const sqlText = (value) => `'${String(value ?? '').replace(/'/g, "''")}'`;
  const sqlJson = (value) => `${sqlText(JSON.stringify(value ?? {}))}::jsonb`;
  const sqlTimestamp = (value) => value ? `${sqlText(value)}::timestamptz` : 'NULL';
  const sqlTextArray = (values) => {
    const clean = Array.isArray(values) ? values.filter(Boolean) : [];
    return clean.length ? `ARRAY[${clean.map(sqlText).join(',')}]::text[]` : `'{}'::text[]`;
  };
  const download = (content, filename, type) => {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1200);
  };
  const loadWorkspace = () => {
    const raw = window.localStorage.getItem(storageKey);
    if (!raw) throw new Error(cfg.strings?.missingWorkspace || 'No browser-local Research Notebook workspace was found.');
    const workspace = JSON.parse(raw);
    if (!workspace || typeof workspace !== 'object') throw new Error(cfg.strings?.invalidWorkspace || 'Invalid workspace.');
    return workspace;
  };
  const show = (root, message, error = false) => {
    const notice = root.querySelector('[data-portability-notice]') || root.querySelector('[data-workspace-notice]');
    if (!notice) return;
    notice.hidden = false;
    notice.className = `${notice.className.replace(/\s+is-error/g, '')}${error ? ' is-error' : ''}`;
    notice.textContent = message;
  };

  const schemaSql = () => `CREATE SCHEMA IF NOT EXISTS ${postgresSchema};
SET search_path TO ${postgresSchema}, public;
CREATE TABLE IF NOT EXISTS workspace_collections (collection_id text PRIMARY KEY,title text NOT NULL,description text NOT NULL DEFAULT '',created_at timestamptz,updated_at timestamptz,payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_saved_records (saved_record_id text PRIMARY KEY,wp_record_id bigint,record_identifier text NOT NULL DEFAULT '',title text NOT NULL,canonical_url text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_notes (note_id text PRIMARY KEY,title text NOT NULL,note_type text NOT NULL DEFAULT 'note',body text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],created_at timestamptz,updated_at timestamptz,payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_sources (source_id text PRIMARY KEY,title text NOT NULL,source_type text NOT NULL DEFAULT 'custom',canonical_url text NOT NULL DEFAULT '',doi text NOT NULL DEFAULT '',isbn text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_matrices (matrix_id text PRIMARY KEY,title text NOT NULL,status text NOT NULL DEFAULT 'draft',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_boards (board_id text PRIMARY KEY,title text NOT NULL,board_type text NOT NULL DEFAULT 'whiteboard',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_annotations (annotation_id text PRIMARY KEY,title text NOT NULL,target_type text NOT NULL DEFAULT '',target_id text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_books (book_id text PRIMARY KEY,title text NOT NULL,edition text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_handoffs (handoff_id text PRIMARY KEY,target text NOT NULL DEFAULT '',collection_ids text[] NOT NULL DEFAULT '{}'::text[],payload jsonb NOT NULL DEFAULT '{}'::jsonb);`;

  const workspaceSql = (workspace) => {
    const out = [
      '-- Sustainable Catalyst Library browser workspace PostgreSQL export',
      `-- Workspace schema: ${workspace.schema || cfg.workspaceSchema || ''}`,
      `-- Generated: ${now()}`,
      'SET client_encoding = \'UTF8\';', 'SET standard_conforming_strings = on;', 'BEGIN;', schemaSql(), `SET search_path TO ${postgresSchema}, public;`
    ];
    const pushRows = (table, columns, rows, resolver) => {
      if (!rows.length) return;
      const values = rows.map((row, index) => `(${resolver(row, index).join(', ')})`);
      out.push(`INSERT INTO ${table} (${columns.join(', ')}) VALUES\n${values.join(',\n')}\nON CONFLICT DO NOTHING;`);
    };
    const arr = (name) => Array.isArray(workspace[name]) ? workspace[name] : [];
    pushRows('workspace_collections',['collection_id','title','description','created_at','updated_at','payload'],arr('collections'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.description),sqlTimestamp(r.createdAt),sqlTimestamp(r.updatedAt),sqlJson(r)]);
    pushRows('workspace_saved_records',['saved_record_id','wp_record_id','record_identifier','title','canonical_url','collection_ids','payload'],arr('savedRecords'),(r,i)=>[sqlText(String(r.id || `saved_${r.recordId || i}`)),Number(r.recordId)||'NULL',sqlText(r.recordIdentifier),sqlText(r.title),sqlText(r.url),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_notes',['note_id','title','note_type','body','collection_ids','created_at','updated_at','payload'],arr('notes'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.type||'note'),sqlText(r.body),sqlTextArray(r.collectionIds),sqlTimestamp(r.createdAt),sqlTimestamp(r.updatedAt),sqlJson(r)]);
    pushRows('workspace_sources',['source_id','title','source_type','canonical_url','doi','isbn','collection_ids','payload'],arr('sources'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.type||'custom'),sqlText(r.url),sqlText(r.doi),sqlText(r.isbn),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_matrices',['matrix_id','title','status','collection_ids','payload'],arr('matrices'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.status||'draft'),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_boards',['board_id','title','board_type','collection_ids','payload'],arr('boards'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.type||'whiteboard'),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_annotations',['annotation_id','title','target_type','target_id','collection_ids','payload'],arr('annotations'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.targetType),sqlText(r.targetId),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_books',['book_id','title','edition','collection_ids','payload'],arr('books'),(r)=>[sqlText(r.id),sqlText(r.title),sqlText(r.edition||r.editionName||''),sqlTextArray(r.collectionIds),sqlJson(r)]);
    pushRows('workspace_handoffs',['handoff_id','target','collection_ids','payload'],arr('handoffs'),(r,i)=>[sqlText(String(r.id||`handoff_${i}`)),sqlText(r.target),sqlTextArray(r.collectionIds),sqlJson(r)]);
    out.push('COMMIT;');
    return out.join('\n\n') + '\n';
  };

  const workspaceJsonl = (workspace) => {
    const lines = [{ entity: 'manifest', data: { export_schema: cfg.exportSchema, workspace_schema: workspace.schema, plugin_version: cfg.version, generated_at: now() } }];
    const mapping = { collections:'collection',savedRecords:'saved_record',notes:'note',sources:'source',matrices:'matrix',boards:'board',annotations:'annotation',books:'book',handoffs:'handoff' };
    Object.entries(mapping).forEach(([key, entity]) => (Array.isArray(workspace[key]) ? workspace[key] : []).forEach((data) => lines.push({ entity, data })));
    return lines.map((line) => JSON.stringify(line)).join('\n') + '\n';
  };

  const exportAction = async (root, kind) => {
    try {
      const stamp = new Date().toISOString().replace(/[:.]/g, '-');
      if (kind === 'schema') {
        try {
          const response = await fetch(cfg.schemaEndpoint, { credentials: 'same-origin' });
          if (response.ok) {
            const body = await response.json();
            download(body.sql || schemaSql(), `sustainable-catalyst-library-schema-${stamp}.sql`, 'application/sql');
          } else download(schemaSql(), `sustainable-catalyst-library-schema-${stamp}.sql`, 'application/sql');
        } catch (_) { download(schemaSql(), `sustainable-catalyst-library-schema-${stamp}.sql`, 'application/sql'); }
        show(root, cfg.strings?.downloadReady || 'Export created.'); return;
      }
      const workspace = loadWorkspace();
      if (kind === 'postgresql') download(workspaceSql(workspace), `sustainable-catalyst-library-workspace-${stamp}.sql`, 'application/sql');
      else if (kind === 'jsonl') download(workspaceJsonl(workspace), `sustainable-catalyst-library-workspace-${stamp}.jsonl`, 'application/x-ndjson');
      else download(JSON.stringify({ export_schema: cfg.exportSchema, generated_at: now(), workspace }, null, 2), `sustainable-catalyst-library-workspace-${stamp}.json`, 'application/json');
      show(root, cfg.strings?.downloadReady || 'Export created.');
    } catch (error) { show(root, error.message || String(error), true); }
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-portability-export], [data-export-workspace-postgresql], [data-export-workspace-jsonl], [data-export-workspace-schema]');
    if (!button) return;
    const root = button.closest('[data-sc-library-portability], [data-sc-library-workspace-root]') || document.body;
    let kind = button.dataset.portabilityExport || 'postgresql';
    if (button.hasAttribute('data-export-workspace-jsonl')) kind = 'jsonl';
    if (button.hasAttribute('data-export-workspace-schema')) kind = 'schema';
    exportAction(root, kind);
  });
})();
