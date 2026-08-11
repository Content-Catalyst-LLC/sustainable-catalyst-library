=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, knowledge-graph, relationships, provenance, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 4.3.20
License: GPLv2 or later

A unified WordPress Living Knowledge System for public discovery, research workspaces, institutional operations, preservation, APIs, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v4.3.20 adds an Open Course Finder to the access-first Research Library. It searches a normalized launch catalog of open courses, distinguishes free/open, free-audit, free-preview, optional-paid-certificate, and mixed access models, and provides query-aware gateways into major university and public learning providers while preserving Global Library Search, My Libraries, Digital Access Resolver, Research Librarian, Workspace, and Publications recovery.




= Field Spotlight data architecture =

* New SC Library → Field Spotlights administration surface.
* Fourteen major fields are derived from the existing canonical 170-Article-Map registry.
* Nested taxonomy groups are preserved as `source_group` metadata while child Article Maps become peer series panels.
* Every series panel owns a permanent Article Map hero role and canonical route.
* Supporting article slots default to four and are configurable from two through eight per panel.
* Supporting selection mode is manual-only; no latest, popular, taxonomy, random, or automatic backfill path is defined.
* The first eight visible panels are marked Primary by default; remaining panels are marked Additional for the later `+ Explore additional fields` presentation.
* Field-level panel disclosure threshold is globally configurable and individually overridable.
* This release establishes data and administration only; the public Field Spotlight shell follows in the next presentation build.

= Curated Homepage Spotlight =

* Configurable subject pages: create, rename, reorder, enable, disable, and replace categories without code changes.
* Compact four- or five-record airport-board pages with featured treatment for position 1 on five-record pages and durable source thumbnails or neutral Library placeholders.
* Manual selection, assignment, ordering, scheduling, and activation for every Library record and announcement.
* Optional starter set for Sustainable Development, Planetary Boundaries, International Law, Biology, and Systems Thinking; all starter pages remain editable.
* Black structural frame with white, cream, and gray editorial record surfaces, red Library accents, restrained green playback status, and a red progress fill on a neutral gray track.
* Category tabs plus previous, pause/play, and next controls; automatic rotation is enabled by default at a 14-second interval and can be disabled by shortcode.
* Pause on hover and keyboard focus, swipe navigation, reduced-motion protection, and responsive one-column mobile rendering.
* No latest-content, popularity, taxonomy, random, or automatic-backfill selection paths.
* Homepage shortcode `[sc_homepage_spotlight]`.

= Unified Living Knowledge System =

* Public portal shortcode `[sc_library_living_system]` with complete, public, research, and institutional modes.
* Unified research gateway `[sc_library_unified_workspace]` for Notebook, Research Librarian, graph, books, editorial review, and portability.
* Public aggregate component `[sc_library_system_status]`.
* Living Knowledge System administration workspace under SC Library.
* Checksummed `sc-library-system-manifest/1.0` records and privacy-aware `sc-library-system-event/1.0` activity.
* Public system status, capabilities, activity, and manifest routes.
* Developer API schema and `system.manifest.created` webhook event.
* Portable export schema `sc-library-portable-export/3.0` with `system_manifests` and `system_events`.
* WordPress remains canonical; no automatic publication, approval, or scheduling.

= Accessibility, mobile, performance, and security hardening =

* Production Readiness dashboard under SC Library.
* Public status shortcode `[sc_library_readiness_status]` and public summary API.
* High-visibility keyboard focus, skip links, polite live announcements, responsive table regions, and reduced-motion behavior.
* Minimum 44-pixel touch targets, mobile-safe forms, one-column narrow-screen actions, and print fallbacks.
* Bounded cache for an explicit allowlist of unauthenticated public GET routes; private and authenticated endpoints are never cached.
* Anonymous route-specific rate limiting, security response headers, and cache invalidation on content and taxonomy changes.
* Runtime, index, cron, API-key storage, remote-media, preservation, PDF extraction, and backup-boundary diagnostics.
* Daily readiness evaluation and one-click maintenance-schedule repair.
* Portable export schema `sc-library-portable-export/3.0` with normalized `readiness_runs`, `system_manifests`, and `system_events`.

= Foundation Documents and full-text PDF indexing =

* Native `sc_foundation_doc` record type under SC Library.
* Media Library PDF attachment selection with explicit Open PDF and Download PDF controls.
* Bundled PDF.js inline viewer with mobile fallback.
* Browser-local page extraction into normalized WordPress tables; PDFs are not sent to a third-party service.
* Page-aware Library search results and Research Librarian recommendations.
* Extraction status, retry, failure diagnostics, version history, related records, and BibTeX/RIS/CSL/plain citations.
* Migration tool for existing direct-download Foundation PDF links.
* Public shortcode `[sc_foundation_document id="123"]`.

= Public API and developer portal =

* Public namespace `/wp-json/sustainable-catalyst-library/v1`.
* Public records, relationships, graph neighborhoods, roadmap data, schemas, status, and OpenAPI routes.
* Protected export, reindex, and webhook-test operations using scoped administrator-issued keys.
* Keyed API-key hashes, per-key rate limits, expiration, revocation, and last-used timestamps.
* Exact-origin opt-in CORS rather than wildcard access.
* Public shortcode `[sc_library_developer_portal]`.

= Signed webhooks =

* HTTPS-only endpoints with private-network and unsafe-URL safeguards.
* Event subscriptions for publication, plans, documentation, graph rebuilds, workspaces, editorial transitions, books, and media clips.
* Timestamped HMAC SHA-256 delivery signatures.
* Bounded retries, delivery history, response summaries, tests, pause, delete, and redelivery controls.
* Encrypted signing secrets shown only once at creation.

= Portable Data =

* Portable export schema `sc-library-portable-export/3.0`.
* Preservation entities `preservation_snapshots`, `integrity_checks`, and `authority_history`.
* Existing Foundation Document, API, webhook, workspace, graph, multimedia, editorial, planning, and orchestration entities remain portable.
* API-key hashes, encrypted signing secrets, full webhook payloads, delivery signatures, and unrelated private workspace data remain excluded from public exports.

== Installation ==

1. Upload and activate the plugin, choosing **Replace current with uploaded** during an upgrade.
2. Clear WordPress, page-builder, Cloudflare, and browser caches.
3. Open **SC Library → Homepage Spotlight**.
4. Create your own category pages or add the optional five-page starter set.
5. Assign four or five selected cards to each category and enable them.
6. Place `[sc_homepage_spotlight]` directly below the homepage hero.
7. Review desktop, mobile, keyboard, and reduced-motion behavior.
8. Existing Knowledge Library indexes do not need to be rebuilt solely for this upgrade.

== Shortcodes ==

* `[sc_homepage_spotlight]`
* `[sc_library_living_system]`
* `[sc_library_unified_workspace]`
* `[sc_library_system_status]`

* `[sc_library_readiness_status]`
* `[sc_library_institutional_archive]`
* `[sc_library_integrity_status]`
* `[sc_foundation_document id="123"]`
* `[sc_library_developer_portal]`
* `[sc_research_librarian_orchestrator]`
* `[sc_research_librarian_orchestrator mode="front-door" full_url="#research-librarian" library_url="#knowledge-explorer"]`
* `[sc_library show_librarian="true" librarian_target="#research-front-door"]`
* `[sc_library_knowledge_graph]`
* `[sc_library_editorial_workflow]`
* `[sc_library_multimedia_studio]`
* `[sc_library]`
* `[sc_library_registry mode="public"]`
* `[sc_library_portability]`

== REST API ==

* `/wp-json/sustainable-catalyst/v1/library/discovery`
* `/wp-json/sustainable-catalyst-library/v1/discovery`
* `/wp-json/sustainable-catalyst/v1/library/system/status`
* `/wp-json/sustainable-catalyst/v1/library/system/capabilities`
* `/wp-json/sustainable-catalyst/v1/library/system/activity`
* `/wp-json/sustainable-catalyst/v1/library/system/manifest`
* `/wp-json/sustainable-catalyst-library/v1/system`

* `/wp-json/sustainable-catalyst/v1/library/readiness/status`
* `/wp-json/sustainable-catalyst/v1/library/readiness/report`
* `/wp-json/sustainable-catalyst/v1/library/readiness/run`
* `/wp-json/sustainable-catalyst/v1/library/preservation/status`
* `/wp-json/sustainable-catalyst/v1/library/archive`
* `/wp-json/sustainable-catalyst/v1/library/archive/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/archive/{uuid}/manifest`
* `/wp-json/sustainable-catalyst-library/v1/archive`
* `/wp-json/sustainable-catalyst-library/v1/status`
* `/wp-json/sustainable-catalyst-library/v1/records`
* `/wp-json/sustainable-catalyst-library/v1/records/{id}`
* `/wp-json/sustainable-catalyst-library/v1/relationships`
* `/wp-json/sustainable-catalyst-library/v1/graph`
* `/wp-json/sustainable-catalyst-library/v1/roadmap`
* `/wp-json/sustainable-catalyst-library/v1/schemas`
* `/wp-json/sustainable-catalyst-library/v1/openapi.json`

== Changelog ==

= 4.3.20 =
* Added Open Course Finder shortcode `[sc_open_course_finder]`.
* Added normalized launch catalog with course/provider/access metadata.
* Added MIT OpenCourseWare, Harvard CS50, Yale Online/Open Yale Courses, Princeton Online, Stanford Online, Columbia Online, edX, Coursera, OpenLearn, SDG Academy, FAO eLearning Academy, and UNITAR/UN learning gateways.
* Added explicit access labels so free, audit, preview, and paid-certificate models are not conflated.
* Added local keyword, subject, and access filtering plus query-aware provider searches.
* Preserved Research Access, My Libraries, Digital Access Resolver, Research Librarian, Workspace, and Publications recovery.


= 4.3.19.1 =
* Replaces the long-form Research Library page with an access-first layout centered on Research Access, the Research Librarian, direct Library search, Knowledge Pathways, Workspace, applied tools, and compact research infrastructure.
* Removes the legacy main-page sections What This Library Is Built to Do, How the Library Works, From Symbols to Systems, Signature Learning Formats, Technical Knowledge Systems, Research Layer, Research Library Standards, and Library Principles.
* Combines Reader Pathways, Featured Knowledge Pathways, and Core Libraries into one Explore Knowledge surface.
* Compresses Research Flow, Workspace, Connected Platform, documents/methods/standards, and the closing statement without removing their underlying plugin capabilities.
* Preserves v4.3.19 Research Access, My Libraries, Research Libraries, Digital Access Resolver, scholarly connectors, v4.3.18.1 Publications recovery, and all Research Librarian/Workspace confirmation boundaries.


= 4.3.19 =
* Adds My Libraries and Research Libraries to the public Research Access surface for signed-in Sustainable Catalyst / Workspace users.
* Adds a curated global library registry covering launch university, public, national, and worldwide discovery routes.
* Allows users to add a custom library with homepage, catalog-search template, and interlibrary-loan route without storing library credentials.
* Adds a Digital Access Resolver that prioritizes open digital/open-access copies before connected-library searches, global holdings discovery, previews, and request routes.
* Adds account-scoped AJAX persistence for library connections; anonymous users retain full public Research Access search.
* Adds per-result Check My Libraries links and best-access routing in federated search results.
* Preserves v4.3.18 scholarly/university connectors and the v4.3.18.1 Publications panel integrity recovery.


= 4.3.18.1 =

* Repairs Publications/Field Spotlight states that collapse to one visible field or panel while the canonical 170-map registry remains intact.
* Runs a bounded one-time visibility integrity repair during upgrade/activation.
* Preserves editorial titles, descriptions, order, selected articles, hero copy, CTA copy, and panel-content storage.
* Clears Publications topic and Field Spotlight model/public caches so stale one-panel models cannot survive the upgrade.
* Records the repair outcome in `sc_library_publications_integrity_repair_v43181` for diagnostics.


= 4.3.18 =
* Expands Research Access into scholarly literature and university research discovery.
* Adds direct public connectors for UCD Research Repository, arXiv, and Europe PMC.
* Promotes Crossref, DataCite, OpenAlex, PubMed, and PubMed Central into the public Research Access surface.
* Treats Google Scholar as a compliant browser research gateway; no automated Scholar scraping is performed.
* Adds a capability-labeled University & Sustainability Research Network featuring MIT, Harvard, Stanford, Yale, Princeton, Columbia, UC Berkeley, UCD Dublin, Copenhagen, Stockholm, Wageningen, Lund, ETH Zurich, Oxford, Cambridge, IIASA, United Nations University, and Stockholm Environment Institute.
* Makes OpenAlex usable through its free public API without requiring a premium API key; configured keys remain supported.
* Preserves v4.3.17 federated library search, v4.3.16 pathway-aware guidance, v4.3.15 Search/Librarian bridging, and Workspace confirmation boundaries.


= 4.3.17 =
* Adds Research Access as the first functional Research Library surface.
* Adds a public, no-affiliation-required federated search shortcode: [sc_research_access].
* Adds direct launch connectors for Internet Archive, MIT TIMDEX, Harvard LibraryCloud, and Library of Congress.
* Adds UC Berkeley/eScholarship as a public repository access route and connector-registry target.
* Normalizes public/open, institutional, borrowable, physical, and metadata-only access states.
* Keeps external authentication on the source institution; Sustainable Catalyst does not collect library passwords.
* Preserves v4.3.16 pathway-aware Research Librarian guidance, the v4.3.15 Search/Librarian bridge, and Workspace confirmation boundaries.


= 4.3.16 =
* Adds curated Knowledge Pathway recommendations to Research Librarian responses using the existing pathway recommendation engine.
* Uses the research question plus compatible selected-record node context when ranking pathways.
* Returns pathway title, summary, level, step count, recommendation reasons, and up to five ordered starting steps.
* Front-door mode surfaces one strongest pathway and a compact ordered route; the full Librarian can show up to four pathways.
* Optional remote synthesis receives the same bounded pathway context but cannot create actions or expand beyond supplied Library material.
* Preserves the v4.3.15 Search ↔ Librarian bridge, explicit Workspace confirmation, v4.3.13 Field Spotlight behavior, and v4.3.12 panel-content persistence.

= 4.3.15 =
* Adds an opt-in Search ↔ Research Librarian discovery bridge to `[sc_library]`; existing Library embeds remain unchanged unless `show_librarian="true"` is supplied.
* Adds **Ask the Research Librarian** beside direct Library search and **Ask the Research Librarian about these results** once results are available.
* Preserves the active search question and up to eight current result IDs when moving into the Research Librarian.
* Adds **View all matching Library records** to front-door Librarian results and restores the original question into Library search.
* Supports same-page event handoff without reload and cross-page fallback through sanitized query parameters.
* Corrects v4.3.14 front-door wording from “Ask the Library” to **Ask the Research Librarian**.
* Preserves user-confirmed Workspace actions, Research Librarian bounded front-door mode, v4.3.13 Field Spotlight behavior, and v4.3.12 panel-content persistence.

= 4.3.13 =
* Replaced the stacked Field Spotlight shortcode with a single master 14-field Publications surface.
* Added compact selectable field index with keyboard navigation and mobile field selector.
* Active field now swaps its panel rail, Article Map hero, supporting publications, telemetry, and playback state in place.
* Preserved first-eight-panel disclosure, autoplay, supporting-article persistence, and single-field shortcode compatibility.

= 4.3.12 =
* Repairs Field Spotlight content persistence by replacing the fragile Settings API form submission path with an explicit authenticated admin-post save transaction.
* Preserves panel and field context during sanitization so partial editor forms cannot silently lose supporting article or hero-copy changes.
* Verifies the stored option after each save and shows a success/error notice in the Field Spotlight Console.
* Clears both Field Spotlight model and public caches after every intentional save.
* Preserves v4.3.10 supporting article binding and v4.3.9 accordion/white-active/sharp-edge behavior.

= 4.3.10 =
* Repairs the four supporting article positions beneath every Field Spotlight Article Map hero.
* Selecting a Library article now activates its slot automatically; no separate enable-checkbox state can suppress a saved selection.
* Preserves exact slot positions and resolves selected records by saved post ID, canonical URL, or URL slug fallback.
* Public rendering now treats a populated slot as configured even when an older v4.3.9 option record contains a stale disabled flag.
* Saving Field Spotlight content continues to invalidate both normalized-model and public-render transients immediately.



= 4.3.3 =
* Replaces the long Publications landing page with a compact dynamic Spotlight-style console: fourteen major fields control one shared editorial stage.
* Keeps one field and one Article Map active at a time, so Philosophy with 27 areas occupies the same page height as Sustainable Systems with 5.
* Adds previous/next, direct area jump, keyboard arrow, touch-swipe, and responsive mobile select navigation without autoplay.
* Keeps the Article Map as the hero followed by up to four full-width publication rows; no reading-time metadata and no Blog Roll.
* Adds SC Library → Publications customization for masthead copy, public labels, field display titles/descriptions/order/visibility/default maps, Article Map hero copy/CTA/visibility, and optional four-slot manual curation.
* Preserves the canonical 14-field / 170-map registry, automatic resolver cascade, and the isolated Homepage Spotlight v4.2.0 module.


= 4.3.2 =
* Rebuilds each Publications topic as a five-row Spotlight-parity editorial board: Article Map lead row plus up to four full-width publication rows.
* Replaces the heavy black Article Map block and 2x2 article grid with the lighter cream/white rhythm established by the Homepage Knowledge Library Spotlight.
* Gives the Article Map lead row a restrained red lead rule, stronger title hierarchy, compact pathway copy, and a clear Explore Map action.
* Uses alternating white/cream article rows with Spotlight-style numbering, hover/focus treatment, and responsive single-column action placement.
* Preserves the 14-field / 170-map registry, resolver cascade, no-reading-time rule, and no-Blog-Roll contract.
* Leaves Homepage Spotlight v4.2.0 unchanged.


= 4.3.1 =
* Expands Publications from the current Homepage Spotlight subjects to the complete approved Article Map registry: 14 major fields and 170 canonical Article Map destinations.
* Keeps the Article Map as the hero object for every topic and resolves up to four companion publications without introducing a Blog Roll.
* Prefers existing Spotlight curation, then canonical Article Map page order, then Knowledge Pathway steps, then same-slug category content.
* Preserves every Article Map link even when a topic has fewer than four resolvable companion publications; no filler content is invented.
* Adds field/topic counts, nested-group context, cache invalidation, and registry extension hooks while leaving Homepage Spotlight v4.2.0 unchanged.

= 4.3.0 =
* Adds `[sc_publications]`, a native Publications surface inside the Knowledge Library.
* Uses one Article Map hero followed by four curated articles per topic.
* Reuses current Homepage Spotlight curation for the four article selections without altering the homepage widget.
* Adds a canonical Article Map resolver for Spotlight subjects and filter hooks for later registry expansion.
* Preserves the Spotlight visual language: black institutional frame, cream editorial field, restrained red identifiers, thin rules, generous spacing, and lightweight article rows.
* Intentionally excludes Blog Roll mode, chronological feeds, reading-time labels, and dense post-card grids.

= 4.2.0 =

* Expands the recommended Homepage Spotlight structure from five subjects to twelve topics.
* Adds Economics, Artificial Intelligence, Physics, Embedded & Edge Systems, Psychology, Decision Science, and Data Systems & Analytics.
* Introduces an eight-topic primary tier and a four-topic secondary tier within the same widget.
* Keeps the secondary tier collapsed initially behind an accessible “Explore additional topics” control.
* Limits automatic rotation to primary topics until the secondary tier is opened, preventing hidden-topic navigation.
* Preserves five article positions per topic, manual selection, manual ordering, scheduling, source validation, thumbnail recovery, and no automatic backfill.
* Adds topic-tier controls and status labels to the Homepage Spotlight administrator.
* Adds shortcode controls for `secondary_topics`, `secondary_open`, and `secondary_label`.

= 4.1.4 =

* Replaces the mixed red-and-green rotation progress line with a single red fill on a neutral gray track.
* Removes the progress gradient so the timer no longer resembles a changing health or performance score.
* Reserves green for the small AUTO operational status indicator and related accessibility states.
* Preserves the 14-second rotation timing, category transitions, thumbnail repair, light editorial rows, manual curation, and no-backfill rules from v4.1.3.

= 4.1.3 =

* Repairs Homepage Spotlight thumbnail discovery across featured images, Library media metadata, PDF previews, attached images, content images, and image-URL metadata.
* Adds a neutral Knowledge Library placeholder when a selected record has no usable public image, preventing empty thumbnail boxes.
* Loads the first visible screen eagerly and later category screens lazily while preserving responsive image behavior.
* Rebalances the console palette around a black structural frame, white/cream/gray record surfaces, red editorial accents, and green playback status.
* Keeps thumbnails visible on mobile and makes new Library cards opt into thumbnail presentation by default.
* Preserves all manual selection, category, schedule, rotation, accessibility, and no-backfill rules from v4.1.0–v4.1.2.

= 4.1.2 =

* Refreshes Homepage Spotlight as a compact Knowledge Library Console rather than a conventional article-card grid.
* Adds a black airport-board presentation with white and gray typography, purple and pink discovery accents, and green automatic-rotation status.
* Enables automatic category rotation by default at 14 seconds while retaining pause, play, previous, next, category tabs, swipe, focus pause, hover pause, and reduced-motion safeguards.
* Reworks four- and five-record pages into numbered catalog rows; position 1 receives featured treatment on five-record pages.
* Adds a visible AUTO, HOLD, PAUSED, STATIC, or REDUCED MOTION state and a restrained rotation progress line.
* Preserves every v4.1.0–v4.1.1 editorial control, source-discovery repair, schedule, category, and no-backfill rule.

= 4.1.1 =
* Repairs Homepage Spotlight source discovery for standard published articles and other eligible Library records.
* Prevents front-end search filters from hiding valid records in the administrator selector.
* Supports record lookup by title, WordPress post ID, slug, or canonical URL.
* Adds a direct title-and-slug fallback for records missed by WordPress relevance search.
* Keeps explicit click-to-select behavior and rejects unpublished or password-protected sources.
* Removes the installer dependency on a globally installed pytest command.

= 4.1.0 =
* Adds configurable Homepage Spotlight category pages and manually curated four- or five-card layouts.
* Adds category tabs, previous/next controls, optional playback, scheduling, responsive behavior, and reduced-motion protection.
* Requires explicit administrator selection for every public card and provides no automatic population or backfill.

= 4.0.6 =
* Adds a compact display mode to `[sc_institutional_research_portal]`.
* Presents six prioritized institutional records in a restrained two-column catalog.
* Moves remaining records into an accessible collapsed disclosure.
* Removes repeated excerpts and action rows from the compact presentation.
* Supports `compact`, `featured`, `documents`, and zero-unit shortcode settings.
* Applies the compact presentation to the protected server-rendered recovery catalog.
* Keeps the full institutional portal presentation available for existing shortcode uses.


= 4.0.5 =
* Fixes a second undefined class constant in project catalog serialization.
* Uses the canonical Citation Source Manager project post-type constant.
* Adds a compatibility `PROJECT_POST_TYPE` alias to Connected Research Environment.
* Wraps the complete public institutional portal shortcode in a `Throwable` boundary.
* Falls back to a direct server-rendered research catalog instead of displaying WordPress's critical-error page.


= 4.0.4 =
* Fixes a fatal error in Public API / Export / Federation source serialization.
* Replaces the undefined `META_SOURCE_ATTACHMENT` reference with the canonical `META_PDF_ID`.
* Adds `META_SOURCE_ATTACHMENT` as a backward-compatible alias for older extension code.
* Restores the public Research Library page without manual server-file editing.


= 4.0.2 =
* Prevents optional extension startup failures from terminating the Research Library page.
* Wraps the public Research Library shortcode in a Throwable recovery boundary.
* Adds a complete server-rendered fallback catalog using core WordPress queries.
* Stores the exact recovered runtime error for administrators.
* Preserves Foundations v2.1.6 and the 13 First Edition HTML documents.


= 4.0.1 =
* Adds the Discovery Interface Reliability Patch.
* Restores Browse Library domain disclosure controls and nested topic rendering.
* Adds capture-phase click handling, accessibility-state synchronization, and asynchronous refresh recovery.
* Normalizes repeated ampersand encodings only in Library REST display fields.
* Leaves URLs, identifiers, hashes, and unrelated HTML entities unchanged.
* Prevents duplicate handlers while the temporary standalone repair plugin remains active.
* Adds an admin transition notice and preserves all v4.0.0 and earlier systems.


= 4.0.0 =
* Adds the Connected Institutional Knowledge and Research Platform.
* Adds public Institution and Research Unit records.
* Adds a unified typed registry across documents, sources, claims, projects, semantics, pathways, collections, reviews, publications, exports, federation, and institutional records.
* Adds stable UUIDs, Sustainable Catalyst URNs, institution and unit assignments, visibility, governance state, stewards, content hashes, registry hashes, and registration timestamps.
* Adds institutional capabilities for reading, administration, record management, publishing, permissions, handoffs, health, and exports.
* Adds unified public/private search with institution, unit, visibility, governance, facets, opaque cursor pagination, and ETags.
* Adds an institutional knowledge graph combining v3.2.0 semantic relationships with institution and research-unit edges.
* Adds platform health across the retained v2.4.0-v3.9.0 subsystems.
* Adds institutional handoff envelopes integrated with the v3.4.0 cross-product handoff system.
* Adds the Institutional Platform workspace, public research portal, shortcodes, REST API, AJAX operations, migration, and WP-CLI.
* Preserves all v3.9.0 and earlier Knowledge Library systems.


= 3.9.0 =
* Adds Public API, Export, and Federation Hardening.
* Adds versioned capability, catalog, record, export, federation, import, dashboard, and migration REST contracts.
* Adds opaque cursor pagination, bounded page sizes, ETags, conditional GET, cache policy, security headers, and response redaction.
* Adds 32-byte scoped bearer tokens with SHA-256-only storage, expiration, revocation, last-used tracking, and per-minute limits.
* Adds JSON, JSON-LD, NDJSON, CSV, and ZIP research-bundle exports.
* Adds resumable export jobs, private export storage, deterministic sorting, record hashes, records hash, and manifest hash.
* Adds federation node discovery, governed peers, trust levels, capability checks, HTTPS-only safe remote access, and private-network rejection.
* Adds HMAC-SHA256 signed webhooks with no redirects, bounded exponential retry, and failure archives.
* Adds federation import size limits, schema validation, peer-trust validation, SHA-256 quarantine, and administrator decisions without automatic publication.
* Adds redacted API audit logs, Research Librarian context, cross-product handoffs, shortcodes, migration, AJAX, and WP-CLI.
* Preserves all v3.8.0 and earlier Knowledge Library systems.


= 3.8.0 =
* Adds Collaborative Review and Research Publishing.
* Adds Research Review, Review Note, and Publication Package records.
* Adds editorial, methodology, evidence, citation, governance, accessibility, privacy, legal, and publication-readiness review types.
* Adds author, editor, reviewer, approver, and observer assignments.
* Adds approve, approve-with-minor-changes, request-changes, reject, and recuse decisions.
* Adds conflict disclosures, decision notes, invitation and response timestamps, and approval thresholds.
* Adds SHA-256 document snapshots and post-review change detection.
* Adds threaded structured review notes with document, section, anchor, quotation, severity, status, assignee, and resolution.
* Blocks approval for unresolved high-risk notes, conflicts, rejected decisions, requested changes, insufficient approvals, and changed documents.
* Adds publication versions, release notes, rights, DOI, canonical URL, embargoes, schedules, approvals, readiness checks, manifests, and release history.
* Adds public review-transparency, publication-record, release-history, and private dashboard shortcodes.
* Adds scheduled publication processing, Research Librarian context, cross-product handoffs, REST, AJAX, migration, and WP-CLI.
* Preserves all v3.7.0 and earlier Knowledge Library systems.


= 3.7.0 =
* Adds Research Librarian Document Intelligence.
* Adds deterministic document profiles with source hashes, status, page count, section count, chunk count, summaries, key points, questions, terms, aliases, gaps, and citation signals.
* Adds HTML-heading and flattened-text section indexing with bounded fallback behavior.
* Adds 220-word retrieval chunks with 40-word overlap, section relationships, and SHA-256 hashes.
* Adds exact-title, title-prefix, title-contains, alias, term-overlap, and summary-overlap retrieval ranking.
* Adds deterministic summaries, key points, suggested research questions, recurring terms, and trusted provider-adapter extension boundaries.
* Adds DOI, URL, numeric-citation, author-year, reference-heading, claim-like sentence, and possible citation-gap signals.
* Adds methods, limitations, structure, citation, Topic, Concept, and truncation gap notices.
* Adds two-to-five-document comparison with shared terms, distinctive terms, shared section labels, and pairwise term similarity.
* Adds Research Librarian document and project context handoff filters.
* Adds public document-intelligence, key-point, research-question, and comparison shortcodes.
* Adds selected-document reindex jobs, stale-document tracking, resumable migration, REST routes, AJAX actions, and WP-CLI commands.
* Preserves all v3.6.0 and earlier Knowledge Library systems.


= 3.6.0 =
* Adds Institutional Collection, Archive Component, Accession, and Disposition records.
* Adds stable collection UUIDs, identifiers, institution, creator, date, extent, language, scope, arrangement, provenance, rights, restrictions, and citation fields.
* Adds Collection, Fonds, Record Group, Series, Subseries, Box, Folder, Item, and Digital Object levels.
* Adds Public, Reading Room, Restricted, Embargoed, and Confidential access controls.
* Adds accession methods, processing states, donor and agreement records, and ordered custody histories.
* Adds digital-object media metadata, byte counts, checksums, checksum algorithms, and preservation states.
* Adds preservation audits, dashboard metrics, daily audit scheduling, and missing-checksum alerts.
* Adds retention classes, review dates, legal holds, disposition actions, approvals, and audit histories.
* Blocks transfer, deaccession, and destruction while a legal or administrative hold is active.
* Adds public institutional collection pages, hierarchical finding aids, collection browser, and preservation summaries.
* Adds resumable archive migration, REST routes, AJAX actions, shortcodes, and WP-CLI commands.
* Preserves all v3.5.0 and earlier Knowledge Library systems.


= 3.5.0 =
* Adds the Research Quality and Governance Center.
* Adds Exploratory, Standard, High-Assurance, Public Release, and Institutional governance profiles.
* Adds Draft, Internal Review, Quality Review, Conditional, Approved, Published, and Archived gates.
* Adds process-readiness scoring across research design, Sources, evidence, provenance, semantics, Pathways, handoffs, and governance.
* Adds blocking controls for critical issues and failed reviews.
* Adds reusable Research Policy records with versions, owners, controls, dates, and public-transparency settings.
* Adds structured quality reviews, findings, actions, due dates, outcomes, and histories.
* Adds issues, severity, risk acceptance, governed exceptions, expiry dates, and approvers.
* Adds approval histories and gate-transition audit records.
* Adds public Research Transparency summaries with private-field filtering.
* Adds quality-governance context to v3.4.0 cross-product handoff bundles.
* Adds resumable governance migration, REST routes, shortcodes, AJAX actions, and WP-CLI commands.
* Preserves all v3.4.0 and earlier Knowledge Library systems.


= 3.4.0 =
* Adds stable UUID and URN identities for cross-product Research Projects.
* Adds a first-party product registry for Research Lab, Workbench, Decision Studio, Research Librarian, and Site Intelligence.
* Adds typed product-specific handoff contracts and adapter payloads.
* Adds snapshot research bundles containing project, bibliography, evidence, semantic, pathway, integrity, and dataset context.
* Adds expiring HMAC-protected delivery links with token rotation and no plaintext token storage.
* Adds validated handoff statuses, return links, result URLs, and bounded history records.
* Adds JSON, Markdown, and ZIP platform research bundle exports.
* Adds project and administration workspaces, REST routes, shortcodes, extension hooks, and WP-CLI commands.
* Adds resumable stable-identity migration for existing Research Projects.
* Preserves all v3.3.0 and earlier Knowledge Library systems.


= 3.3.0 =
* Adds public Knowledge Pathway records and pathway types.
* Adds ordered cross-record sequences with stages, difficulty, timing, and required/optional states.
* Adds prerequisite and continuation pathway relationships.
* Adds accessible SVG article maps with sequence and semantic edges plus text-list fallbacks.
* Adds draft pathway generation from connected Research Projects.
* Adds pathway membership navigation on public documents, Sources, and Claims.
* Adds Topic, Concept, Entity, node, level, audience, and query-based recommendations.
* Adds the Research Librarian pathway recommendation filter.
* Adds public shortcodes, REST routes, WP-CLI commands, deletion cleanup, and no-store private response boundaries.
* Preserves all v3.2.0 and earlier Knowledge Library systems.


= 3.2.0 =
* Adds a canonical hierarchical Knowledge Topic taxonomy across documents, Sources, Projects, Claims, Evidence Notes, Concepts, and Named Entities.
* Adds public Concept, Named Entity, and Controlled Vocabulary records.
* Adds typed, weighted, audited relationships among nine Knowledge Library node types.
* Adds document sequence, continuation, translation, summary, companion, containment, and methodology relationships.
* Adds semantic editors for Topics, Concepts, Entities, and outgoing cross-record relationships.
* Adds public semantic panels and Concept, Entity, and vocabulary templates.
* Adds administrative and public Knowledge Relationship Browsers.
* Adds library and project Topic, Concept, and knowledge-gap analysis.
* Adds bounded public coverage caching with automatic invalidation.
* Adds one-time rewrite activation for the new public record types.
* Adds resumable, non-destructive migration of Source Topics and Foundation Document tags.
* Adds Knowledge Graph REST routes, shortcodes, and WP-CLI commands.
* Preserves all v3.1.0 and earlier Knowledge Library systems.


= 3.1.0 =
* Adds Source version labels, version numbers, release dates, and version families.
* Adds Supersedes, Corrects, Retracts, Replaces, Version Of, Erratum, Supplement, Translation, and Derived From relationships.
* Adds Current, Updated, Corrected, Superseded, Deprecated, Expression of Concern, Retracted, Withdrawn, and Archived integrity statuses.
* Adds explicit replacement guidance without silently rewriting historical citations.
* Adds capability-independent SHA-256 Source snapshots with bounded retention.
* Adds incoming relationship indexes, relationship-status conflict detection, and resumable integrity scans.
* Adds Source impact reports across projects, documents, Evidence Notes, and Claims.
* Adds project-specific integrity acknowledgements and reviewer decisions.
* Adds public Source notices and project bibliography warnings.
* Adds Source Integrity workspace, shortcodes, REST APIs, and WP-CLI commands.
* Preserves all v3.0.1 and earlier Knowledge Library systems.


= 3.0.1 =
* Adds resumable, locked, bounded Connected Research Project migration.
* Adds project and Source relationship reconciliation and repair.
* Adds Production Validation dashboard, per-project diagnostics, and repair actions.
* Adds malformed snapshot recovery, UUID repair, and SHA-256 rehashing.
* Adds export structural validation for Markdown, text, HTML, BibTeX, RIS, CSL JSON, and connected JSON.
* Adds large-library indexed Source and document lookup.
* Adds private shortcode and REST cache protection.
* Adds bounded post-save repair queues, hourly cron continuation, REST recovery routes, and WP-CLI commands.
* Preserves all v3.0.0 and earlier Knowledge Library capabilities.


= 3.0.0 =
* Adds the Connected Research Project and Bibliography Environment.
* Adds research questions, objectives, methods, scope, dates, team roles, and connected documents to Research Projects.
* Adds project-specific Source roles, bibliography sections, inclusion states, priorities, annotations, and audit fields.
* Synchronizes the augmented Source registry with retained project and Source relationship IDs.
* Adds project-aware Source Discovery imports that enter projects as Candidate Sources.
* Adds grouped live bibliographies and section, author, year, title, and priority sort modes.
* Adds bibliography health and readiness diagnostics.
* Adds bounded, hashed bibliography snapshots.
* Adds Markdown, text, HTML, BibTeX, RIS, CSL JSON, and connected JSON exports.
* Adds a six-tab Research Environment workspace.
* Adds connected public project and bibliography shortcodes with strict privacy boundaries.
* Adds workspace, bibliography, snapshots, export, and activity REST endpoints.
* Preserves v2.7.0 evidence and claims, v2.6.x connectors and holdings, v2.5.x citations, v2.4.x OCR, v2.3.x repository routes, and v2.2.x PDF systems.


= 2.7.0 =
* Adds private Evidence Note and Research Claim record types with revisions and structured taxonomies.
* Adds direct quotation, paraphrase, data point, definition, method, observation, counterevidence, and context evidence types.
* Adds page, page-range, paragraph, section, chapter, figure, table, timecode, dataset-row, and custom locators.
* Adds exact-wording, transcription, and locator verification with content-hash invalidation and explicit re-verification.
* Adds Supports, Contradicts, Qualifies, Contextualizes, Illustrates, and Unresolved claim-evidence relationships with strength and rationale.
* Adds synchronized Claim evidence indexes and deletion-safe relationship cleanup.
* Adds claim scope, assumptions, limitations, counterclaims, confidence, review status, and verification invalidation.
* Adds Research Source and Research Project evidence summary panels.
* Adds public Source-page Evidence Notes with strict publication and visibility boundaries.
* Adds citation-ready Harvard quotation, Evidence Note, Claim packet, and Project packet exports.
* Adds Evidence Note, Claim, Project packet, relationship, and export REST endpoints.
* Adds responsive, print-aware Evidence and Claims workspace, cards, packet views, copy controls, and Media Library attachments.
* Preserves v2.6.1 connector/holdings reliability, v2.6.0 discovery, v2.5.x citations, v2.4.x OCR, v2.3.x repository routes, and v2.2.x PDF systems.


= 2.6.1 =
* Adds persistent provider health states, latency, failure counters, cooldowns, rate-limit headers, and bounded event history.
* Adds bounded retries with jitter, Retry-After handling, circuit breaking, and half-open recovery.
* Adds ETag and Last-Modified conditional requests with retained JSON-body recovery.
* Adds explicit stale-cache fallback when live provider access is unavailable.
* Adds import idempotency keys and provider/import fingerprint reuse.
* Adds metadata conflict records and editor resolutions for structured fields, title, and abstract.
* Adds holdings freshness timestamps, stale detection, manual rechecks, and bounded hourly maintenance.
* Adds Library Profile HTTPS, host, IP, and catalog-token validation.
* Prevents invalid library profiles from appearing on public Source pages.
* Adds connector health, holdings, conflict, and profile-validation REST endpoints.
* Preserves v2.6.0 connectors, v2.5.x citation systems, v2.4.x OCR systems, v2.3.x document routes, and v2.2.x conversion/import systems.


= 2.6.0 =
* Adds federated scholarly and library discovery connectors for Crossref, OpenAlex, DataCite, PubMed, PubMed Central, Library of Congress, Open Library, and Google Books.
* Adds Unpaywall and OpenAlex DOI-based open-access location checks.
* Adds compliant Google Scholar and WorldCat browser-search handoffs without automated scraping.
* Adds private Library Profile records with catalog templates, OpenURL resolvers, proxy prefixes, and interlibrary-loan links.
* Adds the Source Discovery workspace with independent provider searches, normalized result cards, provider diagnostics, library management, and import history.
* Adds short-lived user-bound import tokens, field-level provenance, non-destructive fill-empty imports, explicit overwrite mode, and Draft Source creation.
* Adds provider caching, user-specific token re-sealing, HTTPS host allowlisting, response limits, timeouts, request limits, and provider backoff.
* Adds Source material location records with provider, access type, status, license/version context, and checked timestamps.
* Adds public Source-page discovery and published-library handoffs.
* Adds REST endpoints for connector discovery, provider search, source import, source location, and library profiles.
* Preserves v2.5.1 citation reliability, v2.5.0 source management, v2.4.x OCR systems, v2.3.x document routes, and v2.2.x conversion/import systems.


= 2.5.1 =
* Improves personal-name parsing, institutional-author abbreviations, ORCID validation, locators, page labels, editions, and book-chapter formatting.
* Adds DOI syntax, ISBN checksum, PMID, URL, and source-type reliability checks.
* Adds canonical URL normalization and excludes invalid DOI/ISBN values from duplicate keys.
* Adds citation completeness scores, Citation ready/Needs review/Invalid metadata states, and field-level issue records.
* Adds bounded citation caches with automatic invalidation after structured source changes.
* Adds structured metadata history, previous-snapshot restoration, and project-relationship repair.
* Clears verified status after citation-critical changes unless an editor explicitly confirms re-verification.
* Adds reviewed duplicate dispositions and canonical-record selection without automatic merges or deletions.
* Adds REST write limits, Idempotency-Key source creation, optimistic concurrency, ETag, and Last-Modified support.
* Adds reliability, history, and duplicate-review REST endpoints.
* Adds incremental reliability migration for existing Source records.
* Preserves v2.5.0 Source/Project records, public routes, shortcodes, citation API namespace, and all v2.4.x document/OCR systems.


= 2.5.0 =
* Adds structured Research Source records for scholarly, library, web, dataset, legal, media, software, and archival materials.
* Adds the configurable Harvard — Sustainable Catalyst citation profile with in-text citations, locators, and reference-list entries.
* Adds same-author/same-year suffixes and reusable citation keys.
* Adds Research Project records with synchronized source collections and public/private bibliographies.
* Adds DOI, ISBN, URL, and author-year-title duplicate detection without automatic merging.
* Adds Media Library source attachments and relationships to Knowledge Library documents.
* Adds public Source pages, a searchable Source Library, project bibliographies, and inline citation shortcodes.
* Adds permission-controlled REST endpoints for source search, source creation and updates, citation formatting, project bibliographies, and project-source assignment.
* Keeps private notes, metadata provenance, and duplicate-review records out of public API responses.
* Preserves v2.4.1 OCR reliability, v2.4.0 scanned-document processing, v2.3.1 repository accessibility, v2.3.0 public routes, and v2.2.x conversion/import systems.


= 2.4.1 =
* Pins OCR records and queue items to the SHA-256 checksum of the source PDF.
* Archives stale OCR records and requires PDF reconversion after an attachment changes.
* Adds browser-specific queue clients, opaque lease tokens, retry-safe processing, and corrected item-index validation.
* Adds queue-state repair, stale-lease recovery, cancellation synchronization, and active-job-safe pruning.
* Adds configured/PATH-aware local OCR binary discovery and cached provider diagnostics.
* Requires signed HTTPS external OCR requests with API keys and bounded response sizes.
* Validates installed local OCR languages before queue creation.
* Blocks OCR application during active jobs, creates pre-apply backups, and returns published records to Draft.
* Adds restoration of the latest pre-OCR document backup.
* Adds query-level OCR status filtering, cached workspace totals, formula-safe CSV exports, and temporary-file cleanup.
* Preserves the v2.4.0 OCR model, v2.3.1 repository accessibility, v2.3.0 public routes, v2.2.2 bulk import, and v2.2.1 conversion recovery.


= 2.4.0 =
* Adds page-level scan and low-text detection using the existing PDF page map.
* Adds the OCR Review workspace with side-by-side original PDF and editable page text.
* Adds persistent page-level OCR jobs with pause, resume, retry, cancel, stale-lock recovery, and CSV export.
* Adds free local Tesseract OCR support when Tesseract and Poppler binaries are available on the WordPress server.
* Adds a signed external OCR endpoint contract and custom WordPress provider filters.
* Stores per-page confidence, language hints, provider, warnings, attempts, corrections, and reviewer records.
* Adds selected-page reprocessing and queueing of all pages requiring OCR.
* Applies reviewed OCR back to the readable Knowledge Library document while preserving the original PDF as authoritative.
* Adds public OCR provenance and low-confidence warnings to OCR-derived readable documents.
* Preserves v2.3.1 repository accessibility, v2.3.0 public routes, v2.2.2 bulk import, and v2.2.1 conversion recovery.


= 2.3.1 =
* Adds unique repository landmarks, result IDs, skip links, and accessible heading relationships.
* Rebuilds repository filters with fieldset, legend, explicit labels, help text, and result-focus fragments.
* Adds live result summaries and aria-current pagination with accessible previous and next labels.
* Prevents featured documents from repeating on later result pages and corrects total document counts.
* Adds accessible per-document action navigation and new-tab/download announcements.
* Adds 44-pixel touch targets, stronger focus visibility, improved small-screen layouts, reduced-motion handling, forced-colors support, and print safeguards.
* Adds generation-based caches for repository metrics, family indexes, years, and versions with automatic invalidation.
* Adds a Public Repository cache diagnostic and manual cache-clear control.
* Preserves the v2.3.0 routes, document model, conversion recovery, and bulk-import systems.


= 2.3.0 =
* Adds the generated /documents/ public PDF Document Repository.
* Turns Document Families into editorial public landing pages with descriptions, featured records, filters, lifecycle groups, and related-family navigation.
* Adds repository-wide search across titles, summaries, and readable PDF-derived document content.
* Adds filters for family, document type, lifecycle, publication year, version, and sorting.
* Adds the sc_document_type taxonomy and recommended document families and types.
* Adds featured and pinned document controls plus explicit repository ordering.
* Replaces the earlier simple PDF list shortcodes with the compact public repository renderer while preserving shortcode compatibility.
* Adds a Public Repository administration screen with route diagnostics, family links, seeding, and route repair.
* Preserves the v2.2.0 document model, v2.2.1 conversion recovery, and v2.2.2 bulk import and repair systems.


= 2.2.2 =
* Adds a paginated Media Library PDF inventory with represented, unlinked, and duplicate states.
* Adds safe batch draft creation with family and lifecycle assignment.
* Adds persistent browser-driven conversion queues with pause, resume, retry, cancel, and stale-lock recovery.
* Reuses the v2.2.1 resumable PDF conversion endpoints and PDF.js extraction assets.
* Adds collection repair for missing families, lifecycle states, conversion states, compatible PDF metadata, checksums, and titles.
* Detects missing or broken PDF records, unlinked Media Library PDFs, and duplicate records by attachment ID and SHA-256 checksum.
* Adds bulk family changes, lifecycle changes, conversion queueing, and full reprocessing.
* Adds per-job CSV exports and a full collection repair report CSV.


= 2.2.1 =
* Adds resumable browser PDF conversion with persistent page-batch checkpoints.
* Retries interrupted network requests and supports worker-free PDF.js compatibility mode.
* Adds large-file, page-count, and dynamic chunk-size safeguards.
* Prevents duplicate records by attachment ID and SHA-256 checksum.
* Requires a valid PDF, completed conversion, readable content, and review confirmation before publication.
* Adds persistent per-document conversion logs and health-screen reliability history.
* Improves heading reconstruction with PDF font-size and bold metadata and removes repeated page headers and footers.
* Audits and repairs existing document families, statuses, and checksums without replacing the v2.2.0 architecture.


= 2.2.0 =
* Evolves the existing sc_foundation_doc post type into a PDF-to-Document Knowledge Library.
* Converts text-based PDFs into editable, searchable, revisioned Knowledge Library documents while preserving the original PDF attachment.
* Adds hierarchical Document Families with Foundations as the default family.
* Adds Read Document, View Original PDF, Open PDF, and Download PDF public workflows.
* Adds Media Library Create Knowledge Document actions and bulk PDF record import.
* Adds local PDF.js browser extraction, optional pdftotext server extraction, page maps, checksums, summaries, and extraction status.
* Preserves existing Foundation Document records and redirects legacy /foundations/{slug}/ URLs to /documents/{slug}/.


= 2.1.3 =
* Connects page-based Foundation Documents to the established Foundations Documentation Library shortcode.
* Ensures the Foundations page lists Foundation Document pages rather than ordinary blog posts.
* Replaces the oversized iframe presentation with a native, iframe-free PDF object embed.
* Reuses the existing cc-rl-v2 Sustainable Catalyst page style.
* Replaces rounded document cards with a restrained document-index layout.


= 2.1.2 =
* Prevents Foundation Documents from publishing without a valid Media Library PDF.
* Adds Foundation Docs Health with route diagnostics, PDF attachment checks, and one-click repairs.
* Flags documents that need a PDF in the admin list.
* Adds a visible viewer fallback, accessible PDF controls, file metadata, and mobile improvements.
* Hardens Foundation Document search and pagination.
* Preserves the stable page-style editor regardless of site-wide editor settings.


= 2.1.1 =
* Repairs Foundation Document single-page routing and forces a fresh `/foundations/{slug}/` rewrite flush.
* Removes the query mutation that could turn a valid Foundation Document request into a 404.
* Moves the Select PDF control directly below the title so it cannot be removed with legacy metaboxes.
* Uses the canonical plugin asset URL for the Media Library selector script and styling.
* Keeps Foundation Docs separate from posts, categories, tags, feeds, and unrelated Library results.



= 2.1.0 =
* Adds page-like Foundation Document publishing with a title, optional introduction, Media Library PDF selector, and automatic embedded reader.
* Adds `[sc_foundation_documents]` for a Foundations-only public document listing.
* Removes Foundation Docs from blog categories, tags, archives, feeds, navigation menus, and unrelated public Library queries.
* Preserves advanced PDF extraction and citation controls behind an explicit advanced-editor link.


= 2.0.1 =
* Repaired the native topics, relationships, and pathways discovery interface.
* Added a unified `/library/discovery` endpoint with the `sc-library-discovery/1.0` contract.
* Added plugin-owned responsive discovery CSS insulated from page-level layout overrides.
* Added live counts, loading states, empty states, retry controls, and aggregate/fallback loading.
* Preserved dynamic discovery above search results and the manual topic architecture below the Library page.
* Preserved all v2.0.0 Living Knowledge System capabilities and v1.x compatibility.

= 2.0.0 =
* Unified Public Knowledge, Research Workspace, and Institutional Operations into a Living Knowledge System.
* Added a public portal, unified workspace gateway, and system status shortcode.
* Added checksummed system manifests and privacy-aware cross-module activity.
* Added system REST routes, developer schema, and webhook event.
* Added portable export schema 3.0 with system manifests and events.
* Preserved all v1.20.0 features and specialist tools.


= 1.20.0 =
* Added the Production Readiness dashboard and public status summary.
* Added keyboard focus, skip links, live announcements, reduced-motion, forced-colors, responsive tables, and mobile touch-target hardening.
* Added bounded anonymous public REST caching with content-driven generation invalidation.
* Added route-specific anonymous rate limiting and response-security headers.
* Added runtime, index, cron, privacy, preservation, PDF, and backup-boundary diagnostics.
* Added daily readiness evaluation, maintenance-schedule repair, readiness history, and portable readiness exports in schema 2.1.


= 1.19.0 =
* Added immutable institutional snapshots with SHA-256 source and manifest checksums.
* Added bounded integrity audits for content drift, attachments, authority URLs, supersession chains, and relationships.
* Added append-only authority history, record retention dates, legal holds, and protected cleanup.
* Added public historical browsing, version comparison, canonical-record links, and downloadable preservation manifests.
* Added preservation, integrity, and authority REST routes and webhook events.
* Added PostgreSQL entities `preservation_snapshots`, `integrity_checks`, and `authority_history` in portable schema 2.0.


= 1.18.1 =
* Added the Foundation Document record type and Media Library PDF selector.
* Added bundled PDF.js inline reading with explicit open/download controls and mobile fallback.
* Added page-aware full-text PDF extraction, indexing, search snippets, and diagnostics.
* Added Research Librarian synchronization and exact-page recommendation evidence.
* Added document metadata, version history, related records, and citation exports.
* Added Foundation PDF Migration for existing direct-download links.
* Added public Foundation Document API routes and portable schema 1.9.


= 1.18.0 =
* Added a dedicated versioned public API namespace.
* Added public record, relationship, graph, roadmap, schema, status, and OpenAPI endpoints.
* Added hashed scoped API keys, rate limits, expiration, revocation, and last-used tracking.
* Added signed HTTPS webhooks with encrypted secrets, bounded retries, delivery logs, tests, and redelivery.
* Added publication, plan, documentation, graph, workspace, review, document, and media event bridges.
* Added a native public developer portal and admin Developer API workspace.
* Added OpenAPI 3.1, JSON Schemas, JavaScript/Python clients, and webhook-verification examples.
* Added portable developer metadata entities and export schema 1.8 without exporting secrets.

= 1.17.0 =
* Added site-scoped Research Librarian Workspace Orchestration.
* Added indexed retrieval, Knowledge Graph expansion, and transparent recommendation reasons.
* Added user-confirmed actions for Notebook collections, records, notes, matrices, boards, books, tool handoffs, editorial packets, and exports.
* Added routes to Workbench, Decision Studio, Site Intelligence, and Sustainable Catalyst Lab.
* Added optional remote synthesis constrained to supplied Library records.
* Added saved account sessions and attributed action events.
* Added focused Ask Research Librarian links to Library records.
* Added portable orchestration entities and export schema 1.7.


= 1.16.0 =
* Added normalized graph-node and graph-edge WordPress tables.
* Added publication, concept, domain, series, method, tool, dataset, source, claim, evidence, place, organization, event, and other graph entities.
* Added confidence, confidence basis, provenance, evidence, visibility, attribution, and verification fields.
* Added rebuildable graph projection from the Library index, taxonomies, explicit relationships, planner dependencies, and metadata.
* Added orphaned-record, duplicate-concept, dependency-cycle, provenance-gap, low-confidence, and verification diagnostics.
* Added native SVG graph, accessible relationship list, inspectors, filters, rooted neighborhoods, timeline, and place views.
* Added explicit Whiteboard and Chalkboard promotion into the graph.
* Added graph REST endpoints and public shortcodes.
* Added `graph_nodes` and `graph_edges` to portable export schema 1.6.
* Added public relationship privacy filtering for non-public edges.

= 1.15.0 =
* Added native editorial review records and workflow states.
* Added Observer, Reviewer, Editor, and Approver participant roles.
* Added existing-account and expiring email invitations.
* Added comments, resolution, suggested edits, decisions, and attribution history.
* Added revision conflict detection and expiring editor locks.
* Added workspace-role synchronization for workspace-linked reviews.
* Added editorial REST endpoints, admin dashboard, shortcode, and responsive interface.
* Added five normalized editorial entities to portable export schema 1.5.


= 1.14.1 =
* Rebuilt public record cards as a single-column responsive grid so action controls cannot collapse title and excerpt columns.
* Added semantic excerpt and responsive-card hooks to the public renderer.
* Normalized horizontal writing mode, word breaking, inline sizing, and flex/grid minimum widths.
* Made resource badges and action controls wrap safely on desktop and tablet.
* Added compact two-column and one-column mobile action layouts.
* Added print rules that hide interactive controls and preserve readable full-width titles and excerpts.
* Added static and browser-layout regression tests for long titles, long excerpts, and expanded action sets.

= 1.14.0 =
* Added the native Multimedia Studio.
* Added video/audio asset, clip, evidence-reel, and processing-job schemas and database tables.
* Added rights, license, provenance, citation, transcript, caption, poster, annotation, and accessibility fields.
* Added non-destructive timestamp-based clip definitions.
* Added public evidence-reel shortcode and REST representation.
* Added optional signed Render media processing with bounded FFmpeg clip and poster generation.
* Added automatic WordPress Media Library import, diagnostics, retries, and SHA-256 checksums.
* Added portable PostgreSQL, CSV, JSONL, and JSON media entities.
* Added PDF media links, selected segments, transcript excerpts, and QR fallbacks.
* Updated workspace schema to 1.8 and portable export schema to 1.4.

= 1.13.4 =
* Added raw published inventory independent of saved Library post-type settings.
* Added separate counts for standard Posts, all editorial records, selected scope, and global indexed rows.
* Automatically expands the legacy Posts-only scope when additional editorial records are present.
* Selects all recommended editorial post types by default on Index Tools.
* Added database-only post-type discovery for conditionally registered content types.
* Added a bounded server-side reconciliation fallback for stalled REST/JavaScript scans.
* Added the stable SC Library → Index Tools route while preserving the legacy scanner alias.
* Switched scanner API calls to WordPress-relative REST paths.

= 1.13.3 =

* Replaced the large candidate queue with cursor-based direct database scanning.
* Added direct published counts immune to pre_get_posts and theme query filters.
* Added automatic discovery of Posts, Pages, and editorial custom post types.
* Added recommended-type selection and configuration persistence.
* Added a scan audit table with every post ID, outcome, and reason.
* Added explicit exclusion counts separate from failed records.
* Added strict completion accounting and incomplete/error states.
* Added scanner-state reset and complete JSON audit reports.
* Protected other configured post types during subset scans.
* Converted the synchronous fallback rebuild to bounded cursor batches.

= 1.13.2 =

* Fixed the Index Scanner admin page registration order.
* Registered the SC Library parent menu before all Library submenus.
* Registered the Index Scanner after its parent menu exists.
* Corrected the scanner admin route and asset hook resolution.
* Retained the resumable scanner, diagnostics, repairs, and downloadable logs from v1.13.1.

= 1.13.1 =

* Added a dedicated resumable Index Scanner.
* Added complete, missing, outdated, and repair scan modes.
* Added per-post-type diagnostics and index freshness reporting.
* Added targeted record reindexing by ID or URL.
* Added stale-record, relationship, and identifier repair.
* Added downloadable scan logs and saved scan state.
* Corrected synchronous rebuild eligibility handling for public planned content.

= 1.13.0 =

* Added queued server-side PDF and document production.
* Added ReportLab rendering through the optional Render service.
* Added document-job and frozen-edition registries.
* Added automatic WordPress Media Library import.
* Added content and output checksums, manifests, diagnostics, and retries.
* Added server-rendering controls to the Book Builder.
* Added document production REST endpoints and shortcode.
* Upgraded portable export schema to 1.3.

= 1.12.0 =

* Added persistent WordPress account workspaces.
* Added local, account, and hybrid storage modes.
* Added local-to-account migration and cross-device loading.
* Added revision history, content hashes, and optimistic concurrency.
* Added viewer and editor collaborator roles.
* Added optional Render FastAPI/PostgreSQL synchronization.
* Added health, sync, conflict, and recovery diagnostics.
* Added account workspace REST endpoints and shortcodes.
* Upgraded workspace schema to 1.7 and portable export schema to 1.2.

= 1.11.0 =

* Added planning analytics, dependency intelligence, and release coordination.
