# Research Portability & Preservation — v4.3.39

## Purpose

v4.3.39 makes the private research environment portable without confusing portability with publication, provider-content copying, or institutional backup. A signed-in user explicitly chooses an owned Research Project and creates a normalized JSON research package.

## Reused canonical systems

The release does not create a second project, notebook, evidence, learning, or preservation store. It reads the established v4.3.30 Research Project/Source Bundle state, v4.3.31 Reading Notebook/Annotation state, v4.3.32 Evidence Matrix state, v4.3.36 learning-route manifests, and the existing `SC_Library_Preservation` archive contract.

## Package structure

`sc-library-research-portability-package/1.0` contains a preservation manifest plus five checksummed sections: project, Source Bundles, Reading Notebooks, Evidence Matrices, and project-linked learning routes. Stable URNs and canonical reference metadata are preserved.

Two export profiles are supported:

- **complete** — includes user-authored notebook/annotation and evidence-matrix content.
- **manifest** — preserves identities, references, counts, and structural metadata while intentionally omitting notebook and matrix content bodies.

## Integrity and preservation

SHA-256 is calculated for every section, the preservation manifest, and the complete package. The package records the source plugin version, export time, canonical Library route, selected profile, and stable project identity. Existing institutional preservation remains a separate server-side archive system; this feature is a portable snapshot of user research.

## Data boundary

The exporter recursively removes binary/storage implementation fields and credential/secret fields. It does not embed private source binaries, local attachment paths, credentials, API keys/tokens, raw WordPress tables, or provider secrets. URLs and bibliographic/source references may remain because the package is references-first.

## Validation, not automatic import

Validation is non-executing and non-importing. `POST /wp-json/sc-library/v1/research-portability/validate` checks package schema, package checksum, preservation-manifest checksum, section checksums, size, and version compatibility. Validation never executes payload content and creates zero records. Automatic import is explicitly false. Restoration remains a deliberate future/manual operation after integrity review.

## No automatic actions

Export and validation do not publish research, promote evidence, update claims, change notebooks/projects, write to Workspace, enroll in courses, or replace institutional backups.
