# Sustainable Catalyst Foundations v2.1.3

## Automatic HTML Foundation Document Provisioning

The previous First Edition workflow depended on a manual WordPress importer. On
the live site, that importer did not create any records.

v2.1.3 removes the import requirement entirely.

On the first WordPress admin request after plugin replacement, the Knowledge
Library now creates or repairs all 13 records as real, published
`sc_foundation_doc` posts. Each record receives:

- its complete authored HTML body;
- a stable `SC-FND-###` document ID;
- its own `/foundation-documents/<slug>/` URL;
- Foundation Document and Documentation Library metadata;
- membership in the Foundations collection;
- institutional-foundations categorization;
- related-document relationships;
- an attached fixed PDF snapshot;
- visible status `Under Review` while remaining publicly readable.

The process is idempotent. Existing drafts or partial records are updated by
stable document ID instead of duplicated.

### Admin status

Open:

**Knowledge Library -> First Edition Status**

The screen reports how many of the 13 HTML records exist and exposes a
**Re-provision all 13 HTML documents** repair action.

### No import step

There is no First Edition import button in v2.1.3. Uploading and replacing the
plugin is sufficient; provisioning runs automatically on the next admin page.
