# Third-Party Notices — v2.2.0

## PDF.js

Sustainable Catalyst Library bundles PDF.js 6.1.200 for local browser-side PDF text extraction in the WordPress administrator.

- Project: Mozilla PDF.js
- License: Apache License 2.0
- Bundled license: `sustainable-catalyst-library/assets/vendor/pdfjs/LICENSE`
- Bundled components: legacy display module, worker module, character maps, standard fonts, and WebAssembly support files

PDF.js is not used to send documents to an external service. The browser loads the selected Media Library PDF from the same WordPress site and sends extracted page text back to authenticated WordPress AJAX endpoints.
