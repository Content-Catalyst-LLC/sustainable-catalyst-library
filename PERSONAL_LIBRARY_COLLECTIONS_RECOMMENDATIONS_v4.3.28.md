# Sustainable Catalyst Library v4.3.28 — Personal Library Collections & Recommendations

## Purpose

v4.3.28 adds **My Library**, a private personal collection layer for authenticated Sustainable Catalyst users. It is designed for resources a user wants to keep across research, learning, culture, and practice without converting those choices into Sustainable Catalyst editorial endorsements.

## Supported resource types

- Books
- Films
- Music
- Articles
- Archives
- Courses
- Datasets
- Tools
- Websites
- Podcasts
- Other resources

## Personal organization model

Each item receives a stable private ID and can carry:

- title
- type
- creator / author / organization
- year
- URL
- identifier such as ISBN, DOI, or catalog ID
- personal collection
- private notes
- a private “Why I kept this” recommendation/rationale
- relationship: Saved, Personal recommendation, or Reference
- status: Saved for later, In progress, Completed, or Archived

Collections and items are stored against the current WordPress user through dedicated v4.3.28 user-meta contracts.

## Editorial separation boundary

My Library is **not** the Sustainable Catalyst editorial recommendation system.

- Personal records are private by default.
- `origin` is forced to `personal`.
- `visibility` is forced to `private`.
- Saving an item never publishes it.
- Saving an item never adds it to Publications, Homepage Spotlight, Article Maps, official Library recommendations, or another editorial surface.
- There is no automatic endorsement or editorial promotion path in v4.3.28.

This separation is intentional so a user's own reading, viewing, listening, research, and tool choices remain theirs.

## Shared-account continuity

My Library uses the same authenticated WordPress account already used by Sustainable Catalyst Workspace and private Library tools. No second Library account or migration identity is introduced.

User-meta contracts:

- Items: `sc_library_personal_items_v4328`
- Collections: `sc_library_personal_collections_v4328`

The existing v4.3.27 account-continuity health contract now includes the personal Library user-meta boundary.

## Public / private boundary

Public research discovery remains available without authentication. My Library requires authentication for reading and writing private records.

Authenticated current-user API:

`/wp-json/sc-library/v1/personal-library`

The endpoint returns only the currently authenticated user's My Library data and has no anonymous read permission.

Writes use same-origin WordPress AJAX requests protected by a v4.3.28 nonce.

## Integration boundary

v4.3.28 exposes stable WordPress hooks for future Research Access and Workspace handoffs:

- action: `sc_library_save_personal_item`
- action emitted after persistence: `sc_library_personal_item_saved`
- filter: `sc_library_personal_library_items`

These hooks reuse the v4.3.28 item schema rather than creating a second storage model later.

## Limits

To keep account metadata bounded:

- maximum personal items: 500
- maximum explicit collections: 50

No public sharing, collaborative collections, recommendation publishing, social following, ratings marketplace, or cross-user discovery is added in this release.
