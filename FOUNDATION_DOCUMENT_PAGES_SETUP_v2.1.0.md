# Knowledge Library v2.1.0 — Foundation Document Pages

## Install and build

Run the included installer from the extracted upgrade folder:

```bash
cd ~/Downloads/sustainable-catalyst-library-v2.1.0-upgrade
chmod +x install_and_push_library_v2_1_0.sh
./install_and_push_library_v2_1_0.sh
```

The installer updates the existing local Knowledge Library repository, validates the release, creates the WordPress plugin ZIP, commits, and pushes to GitHub.

No-push validation and packaging:

```bash
SC_LIBRARY_V210_NO_PUSH=1 ./install_and_push_library_v2_1_0.sh
```

Custom repository location:

```bash
SC_LIBRARY_REPO_DIR="$HOME/Downloads/sustainable-catalyst-library" ./install_and_push_library_v2_1_0.sh
```

## WordPress installation

Upload:

```text
~/Downloads/sustainable-catalyst-library-v2.1.0.zip
```

Choose **Replace current with uploaded**.

## Create a Foundation Document

1. Open **SC Library → Foundation Docs → Add New**.
2. Enter the title.
3. Add an optional one-sentence introduction in the editor.
4. Select an existing PDF from the Media Library.
5. Publish.

The page automatically receives an embedded PDF viewer, Open PDF button, Download PDF button, and Back to Foundations link.

## Foundations page shortcode

Replace the broad documentation Library embed with:

```text
[sc_foundation_documents per_page="12" search="true" orderby="title"]
```

This listing contains only Foundation Document pages and uses no blog or Library taxonomies.

## Advanced tools

Use **Advanced Library tools → Open advanced editor** only when you need the retained PDF extraction, citation, version, or page-aware indexing controls.
