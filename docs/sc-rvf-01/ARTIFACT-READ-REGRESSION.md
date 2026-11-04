# SC RVF 01 artifact read regression

An authorized development handoff test uploaded the published EPA Report 136 and native Lab dataset through the existing signed artifact API. GET of the content then returned HTTP 500 because require_admin_bearer was referenced but never defined. The broad metadata route also preceded /content and captured its trailing path, which would prevent content retrieval after the authentication error was fixed.

This patch defines the intended bearer credential check, reuses it inside the existing signed mutation guard, and registers the content route before the metadata catch-all. Missing/incorrect read credentials return 401; an unconfigured key returns 503. Signed mutations still require their timestamp and HMAC. Existing storage formats, immutable artifacts, source IDs and database schemas are unchanged.

Validation: 22 local tests passed across actual HTTP route dispatch, artifact storage and the Library/Core bridge. New route tests ensure unauthorized reads never reach storage; content requests reach the byte reader with the original artifact ID; metadata requests retain their identity; and bearer-only mutations remain rejected.

The running development image has not been replaced. The published PDF and dataset artifacts were independently verified using the existing native storage reader after the HTTP download failed. That is storage integrity evidence, not a successful API round trip. Library source ingest and the existing Core adapter/project binding/reconciliation succeeded separately. A full source-to-publication runtime acceptance still requires a staged candidate containing this fix and the existing Lab/Workspace SC-RVF changes, then real API round-trip validation and governed Workspace integration.

Master issue: https://github.com/Content-Catalyst-LLC/ucd-sustainable-development-research/issues/2
