# Sustainable Catalyst Foundations v2.1.4

## Native HTML Publication Guard Repair

The 13 First Edition records are native HTML Foundation Documents. Their PDFs
are fixed downloadable snapshots. They are not PDF-to-HTML conversion jobs.

The retained Knowledge Library used the same `sc_foundation_doc` post type for
both workflows, so its v2.2.1 conversion reliability filter forced the new HTML
records back to draft and displayed:

> PDF Document not published. The PDF conversion must be completed before publishing.

v2.1.4 separates the workflows without weakening real PDF-import validation.

### Corrected provisioning sequence

1. Create or update the complete HTML body as a draft.
2. Mark the record as `native_html`.
3. Synchronize compatibility metadata used by the retained Library.
4. Attach the fixed PDF snapshot and synchronize every supported PDF key.
5. Publish the HTML record only after its identity and metadata exist.

### Retained guard repair

The installer patches both retained publication guards so they return early only
when:

- `_sc_foundation_source_mode` is `native_html`; and
- the WordPress HTML content is non-empty.

Normal PDF-conversion records still require a valid PDF, completed conversion,
and review before publication.

After plugin replacement, the next administrator request automatically
re-provisions and publishes the 13 records. The First Edition Status screen also
provides a manual repair action.
