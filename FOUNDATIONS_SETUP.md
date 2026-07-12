# Foundations Documentation Library setup

## 1. Install and index

1. Upload `sustainable-catalyst-library-v1.8.0.zip` in WordPress.
2. Replace the previous plugin version.
3. Open **SC Library** and enable the Foundations Documentation Library.
4. Confirm the main Research Library page URL.
5. Save settings and rebuild the Library index.

## 2. Classify a documentation record

Edit the WordPress page, post, product brief, methodology record, policy, or release document.

1. Assign **Foundations Documentation Library** under **Library Collections**.
2. Assign one or more **Documentation Categories**.
3. Complete **Documentation Authority**:
   - status
   - document type
   - version
   - responsible area
   - authoritative source type and URL
   - current webpage
   - repository documentation
   - PDF snapshot
   - release record
   - last-reviewed date and review interval
   - supersedes, superseded-by, and dependency IDs
   - correction or contribution route
4. Update the record.

## 3. Authority rules

- Institution and product descriptions: current public webpage.
- Technical behavior: repository documentation.
- Methodology and boundaries: current methodology page.
- Release state: repository release record.
- Brand or policy snapshot: published PDF.
- Historical brief: archived PDF.

A PDF should normally be marked **PDF snapshot** when a living page or repository remains the current authority.

## 4. Add the Foundations embed

Use a dedicated WordPress Shortcode block:

```text
[sc_library collection="foundations" mode="documentation"]
```

For a page that already has its own heading:

```text
[sc_library collection="foundations" mode="documentation" show_header="false"]
```

Equivalent alias:

```text
[sc_foundations_library mode="public"]
```

## 5. Test

Verify:

- search finds words in document text and metadata
- category and responsible-area filters work
- current records identify an authoritative source
- PDF snapshots warn that the living page is current
- repository-governed technical records show the repository authority notice
- archived records are hidden until requested
- document panels expand without opening a PDF
- full Library record, webpage, PDF, repository, release, history, and correction actions point to the expected locations
