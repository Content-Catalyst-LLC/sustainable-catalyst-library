# Sustainable Catalyst Library v4.0.4

## PDF Source Attachment Compatibility Patch

The public Research Library crashed while serializing a document source record.

`SC_Library_Public_API_Export_Federation` referenced:

`SC_Library_PDF_To_Document::META_SOURCE_ATTACHMENT`

The PDF-to-Document class only defined:

`SC_Library_PDF_To_Document::META_PDF_ID`

PHP therefore raised an undefined class constant fatal error during the
Connected Institutional Platform rendering path.

v4.0.4 fixes the problem in two places:

1. The export/federation serializer now uses the canonical `META_PDF_ID`.
2. The PDF-to-Document class exposes `META_SOURCE_ATTACHMENT` as a
   backward-compatible alias to `META_PDF_ID`.

No database migration, document re-import, or manual file edit is required.
