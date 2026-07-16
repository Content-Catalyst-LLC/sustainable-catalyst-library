# Release Notes — Sustainable Catalyst Foundations v2.1.4

## Fixed

- Stops native HTML Foundation Documents from being treated as unfinished PDF
  conversions.
- Changes automatic provisioning to draft-first, metadata-first publication.
- Adds explicit `native_html` source-mode metadata.
- Synchronizes the retained extraction status as `legacy_content` and records
  human review compatibility without running PDF extraction.
- Synchronizes all retained Foundation PDF attachment meta keys.
- Publishes each HTML record after metadata and snapshot attachment processing.
- Patches the retained Foundation Page and PDF Conversion Reliability guards to
  exempt only non-empty native HTML records.
- Preserves all conversion and review requirements for genuine PDF-import
  records.
- Advances the First Edition provisioning workflow to v2.1.4 while preserving
  authored content edition v2.1.0.
