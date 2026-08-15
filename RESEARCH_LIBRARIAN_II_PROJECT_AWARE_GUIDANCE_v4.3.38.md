# Research Librarian II — Project-Aware Guidance v4.3.38

## Purpose

v4.3.38 makes the Research Librarian aware of the user's existing private research structure without replacing the established site-scoped orchestrator or creating a second project store.

The new layer reads only records owned by the current Sustainable Catalyst account:

- Research Projects and references from v4.3.30
- Source Bundles from v4.3.30
- Reading Notebooks, notes, and annotations from v4.3.31
- Evidence Matrices, claims, evidence relationships, and deterministic diagnostics from v4.3.32

## Guidance contract

Project-aware guidance is deterministic and descriptive. It can identify conditions such as:

- a thin source base;
- unresolved project references;
- an unscoped project that could benefit from a focused Source Bundle;
- missing Reading Notebook or locatable annotations;
- absence of an Evidence Matrix once explicit claims emerge;
- matrix claims with no recorded counterevidence;
- unchecked quotations or locators;
- single-source dependency;
- questions that should route through Access Intelligence II or Open Learning II;
- publication work that should preserve the public/private Research Graph boundary.

These are workflow diagnostics, not truth judgments.

## Remote synthesis boundary

Private project context is **not sent to the optional Research Librarian remote-synthesis endpoint** by v4.3.38.

When the user chooses **Continue in the Research Librarian**, the handoff contains only:

1. the user's question; and
2. at most eight `publish`-status Research Source post IDs already referenced by the selected project.

Notebook text, annotations, bundle manifests, project metadata, private claims, evidence links, and matrix diagnostics remain in the authenticated v4.3.38 view.

## No automatic mutation

v4.3.38 does not automatically:

- write to a Research Project;
- create or edit a notebook;
- create a claim;
- promote an annotation into evidence;
- alter claim status or confidence;
- publish anything;
- write to Workspace.

All existing user-confirmation and canonical-record boundaries remain in force.
