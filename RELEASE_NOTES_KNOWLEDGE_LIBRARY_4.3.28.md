# Sustainable Catalyst Library v4.3.28 — Personal Library Collections & Recommendations

## Added

- `[sc_personal_library]` private personal Library workspace.
- Support for books, films, music, articles, archives, courses, datasets, tools, websites, podcasts, and other resources.
- Private collections with saved, personal-recommendation, and reference relationships.
- Saved-for-later, in-progress, completed, and archived status states.
- Private notes plus a personal “Why I kept this” field.
- Account-scoped add, update, delete, filtering, and collection creation.
- Authenticated current-user API at `/wp-json/sc-library/v1/personal-library`.
- Stable integration hooks for later Research Access and Workspace handoff.

## Privacy and editorial boundary

- Personal Library items are account-owned and private by default.
- Personal records use dedicated v4.3.28 user-meta storage.
- Personal recommendations remain separate from Sustainable Catalyst's official editorial recommendations.
- The release adds no automatic publication, endorsement, editorial promotion, public sharing, or cross-user discovery.

## Continuity repair

- Advances the canonical route / identity health module runtime marker to v4.3.28 so `/wp-json/sc-library/v1/runtime/identity-health` remains green after the plugin version bump.
- Adds the My Library storage contract to the shared-account continuity payload.

## Preserved

- Canonical `/knowledge-libraries/` public route and safe legacy `/library/` redirect from v4.3.27.
- One Sustainable Catalyst / WordPress account across Library and Workspace.
- v4.3.26 Public Library Network & Local Access.
- v4.3.25 Institutional Connector Expansion.
- v4.3.24 Research Librarian Access Intelligence.
- v4.3.23 Research Document Builder.
- v4.3.22 Citation Studio.
- v4.3.22.4 Publications all-fields runtime.

## Deployment note

After plugin deployment, replace the Knowledge Libraries page body with `RESEARCH_LIBRARY_PAGE_v4.3.28.html`, confirm its slug remains `knowledge-libraries`, clear caches, and verify both:

- `/wp-json/sc-library/v1/runtime/identity-health`
- `/wp-json/sc-library/v1/personal-library` while signed in
