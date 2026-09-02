# Deploy Sustainable Catalyst Library v5.6.0 R3

## 1. Install repository release on macOS

Run the packaged `install_and_push_sustainable_catalyst_library_v5_6_0_r3_macos.sh` against the existing Git checkout. The installer creates a safety ZIP, runs preservation/visibility validation, commits and pushes to the configured origin.

## 2. Install WordPress plugin

Upload `sustainable-catalyst-library-v5.6.0-R3-wordpress.zip` in WordPress and replace the current plugin. Version must report `5.6.0.3`.

## 3. Backend check

Verify the public backend health endpoint. If it reports `1.1.0`, no VPS deployment is needed.

## 4. Preview the R3 page

Use `RESEARCH_LIBRARY_PAGE_v5.6.0-R3.html` on a Draft/Private page first. Verify direct university search, UCD, public libraries/local discovery, Research Librarian, Knowledge Base and the expanded full capability map before changing the live canonical Library page.
