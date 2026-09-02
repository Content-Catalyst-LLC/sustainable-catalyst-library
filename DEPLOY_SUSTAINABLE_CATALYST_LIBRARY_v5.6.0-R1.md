# Deploy Sustainable Catalyst Library v5.6.0 R1

## 1. Repository/GitHub
Run the packaged macOS installer. It creates a safety backup, installs the repository payload, runs the 39-test preservation/regression gate, commits, and pushes.

## 2. WordPress plugin
Upload `sustainable-catalyst-library-v5.6.0-R1-wordpress.zip` and replace the current plugin. WordPress reports plugin version **5.6.0.1** so it sorts newer than 5.6.0.

## 3. Python backend
R1 does not change the Python service. Backend **v1.1.0 remains current**. If v1.1.0 is already healthy, do not redeploy it. If the site is still on backend v1.0.x, use the included v1.1.0 backend package and Contabo upgrader from the v5.6.0 line.

## 4. Preview before replacing the live page
After the plugin is active, create a temporary WordPress draft/private page and paste `RESEARCH_LIBRARY_PAGE_v5.6.0-R1.html` there first. Preview the Knowledge Base, connected-library zone, My Research, Evidence, Federation, Citation Studio, Document Builder and several legacy hash links.

Only after that preview is satisfactory should you replace the live Research Library page body. WordPress revisions remain the rollback path. The plugin never rewrites the page automatically.

## 5. Acceptance test
- Knowledge Base Explorer is visible near the top.
- “Search Libraries & Research” opens the access capability.
- Public Library Network and Institutional Research Network open on demand.
- My Research, Evidence, Federation, Citation Studio and Research Document Builder open from the capability hub.
- Existing deep links such as `#evidence-matrix`, `#global-research-federation`, `#citation-studio`, and `#research-projects` open the correct capability.
- No page section should require the old stripped v5.6.0 page.
