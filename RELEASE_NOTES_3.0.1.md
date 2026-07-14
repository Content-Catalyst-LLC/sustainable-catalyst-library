# Knowledge Library v3.0.1

## Production Validation and Migration Reliability Patch

v3.0.1 stabilizes the v3.0.0 Connected Research Project and Bibliography Environment.

### Resumable migration

- persistent migration cursor and progress state
- bounded 10-project batches
- 180-second migration lock
- hourly WordPress cron continuation
- manual batch and reset controls
- failure history and last-error reporting
- safe restart after interrupted requests

### Project integrity and repair

The validator checks and can repair:

- augmented project Source entries
- retained project Source ID indexes
- Source-to-project reverse relationships
- duplicate and missing Source records
- invalid bibliography sections
- missing document relationships
- missing or duplicate team members
- invalid bibliography sort modes
- oversized activity history
- malformed snapshots
- duplicate snapshot identifiers
- snapshot hash mismatches
- cached workspace health

Repairs are non-destructive: valid project content, citations, Sources, claims, evidence, and documents are retained.

### Production Validation workspace

```text
SC Library → Production Validation
```

The dashboard provides migration status, per-project integrity results, manual validation, repair controls, and export validation.

### Large-library lookup

Project editors now load a bounded set of Sources and documents synchronously. A new indexed lookup panel searches and attaches records beyond the initial selector set.

Sources attached through lookup enter as Candidate/Background records. Documents are attached directly to the project.

### Export validation

Structural validation covers:

- Markdown
- plain text
- HTML
- BibTeX
- RIS
- CSL JSON
- connected project JSON

The validator checks record balance, required fields, terminators, JSON encoding, and empty-output failures. It does not replace testing in a downstream reference manager.

### Privacy and cache hardening

Authorized private shortcodes issue no-cache headers. Private or authenticated project REST responses include:

```text
Cache-Control: no-store, no-cache, must-revalidate, private
Vary: Cookie, Authorization
```

### Recovery interfaces

REST:

```text
GET/POST /wp-json/sc-library/v1/projects/reliability/migration
GET      /wp-json/sc-library/v1/projects/{id}/validation
POST     /wp-json/sc-library/v1/projects/{id}/repair
GET      /wp-json/sc-library/v1/projects/{id}/export-validation
```

WP-CLI:

```text
wp sc-library projects migrate
wp sc-library projects validate PROJECT_ID
wp sc-library projects validate PROJECT_ID --repair
wp sc-library projects exports PROJECT_ID
```

### Compatibility

v3.0.1 preserves every v3.0.0 feature and all retained citation, connector, holdings, OCR, document, quotation, evidence, and claim systems.
