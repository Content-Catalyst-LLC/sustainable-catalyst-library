# Release Notes - Sustainable Catalyst Foundations v2.1.3

## Fixed

- Removed the manual First Edition import workflow that created no records on
  the live site.
- Automatically provisions all 13 documents on the first admin request after
  plugin replacement.
- Publishes complete HTML Foundation Document posts rather than treating HTML as
  an inert import asset.
- Repairs existing draft or partial records by stable `SC-FND-###` ID.
- Assigns each record to the Foundations collection and institutional category.
- Attaches each fixed PDF snapshot without making the PDF the sole authority.
- Adds **Knowledge Library -> First Edition Status** and an idempotent
  re-provision action.
- Preserves content edition v2.1.0 while advancing the provisioning system to
  v2.1.3.

## Result

After plugin replacement, the site should contain 13 published HTML records
under **Knowledge Library -> Foundation Documents** and on the public Foundations
catalog.
