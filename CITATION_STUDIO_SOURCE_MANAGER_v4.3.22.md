# Sustainable Catalyst Library v4.3.22
## Citation Studio & Source Manager

### Purpose
v4.3.22 turns source discovery into a personal research workflow. Public Research Access remains available without an account; signed-in Sustainable Catalyst / Workspace users can save normalized discovery results into a private, account-owned My Sources layer.

### Personal source boundary
Personal sources use the existing `sc_research_source` content model but are created as private records, attributed to the current WordPress/Sustainable Catalyst account, and marked with `_sc_source_personal_owner`. They do not become Sustainable Catalyst editorial/public sources merely because a user saves them.

The personal layer supports:
- private My Sources;
- private notes;
- user-defined collections;
- DOI and ISBN normalization;
- duplicate checks by DOI, ISBN, and exact title within the user's personal library;
- provenance for sources saved from Research Access;
- reusable citation metadata;
- interoperable export.

### Research Access handoff
Normalized Research Access results now expose **Save to My Sources** for signed-in users. The existing editor-only **Import as Draft Source** remains a separate institutional/editorial workflow.

A saved result preserves available title, creator, date, container, publisher, identifiers, URL, abstract, provider, and provider record identifier. Saving is based on the existing sealed-result token so the client cannot substitute arbitrary provider metadata into the save operation.

### Citation profiles
The earlier Harvard formatter remains intact. Citation Studio adds profiles for:
- Harvard — Sustainable Catalyst;
- APA 7;
- MLA 9;
- Chicago — Author-Date;
- Chicago — Notes & Bibliography;
- IEEE;
- Vancouver;
- AMA;
- ACS — Author-Date.

Numeric citation systems expose a source-level preview; final sequence numbering belongs to the document/bibliography layer, which is scheduled for v4.3.23.

### Exports
Citation Studio exports the current personal source set (or selected collection) as:
- BibTeX (`.bib`);
- RIS (`.ris`);
- CSL-JSON (`.json`).

The CSL-JSON mapping preserves normalized title, contributor, issued year, container title, publisher, volume, issue, pages, DOI, ISBN, and URL where present.

### Front-end shortcode
`[sc_citation_studio limit="100" style="harvard"]`

Signed-out visitors see an account boundary rather than an empty application. Signed-in users receive source filtering, citation-style switching, manual source entry, collection creation, citation copy controls, note editing, removal, and export.

### Reliability and privacy
- No second Library account is introduced.
- Public Research Access remains available without sign-in.
- Personal source writes require an authenticated user and a dedicated nonce.
- Personal sources are private posts and include an explicit owner marker.
- Research Access saving reuses sealed normalized provider results.
- Existing Publications runtime recovery remains untouched.
- Citation Studio is loaded through the isolated extension bootstrap so a failure does not remove the protected Research Library core.
