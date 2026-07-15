# Sustainable Catalyst Knowledge Library v3.2.0

## Topics, Concepts, and Document Relationships

v3.2.0 adds a structured semantic layer across Knowledge Library documents, Research Sources, Claims, Evidence Notes, Research Projects, Concepts, Named Entities, and controlled vocabularies. It preserves every existing record and retains the earlier Source Topic taxonomy during migration.

## Canonical Knowledge Topics

New hierarchical taxonomy: `sc_library_topic`.

Knowledge Topics can be assigned to documents, Sources, Projects, Claims, Evidence Notes, Concepts, and Named Entities. Topic records can store alternative labels, scope notes, parent Topics, external URIs, controlled-vocabulary links, lifecycle status, and a legacy Source Topic identifier.

## Structured semantic records

New public post types:

- `sc_library_concept` — frameworks, theories, principles, methods, models, metrics, systems, standards, legal concepts, policy instruments, and scientific phenomena.
- `sc_named_entity` — people, organizations, places, jurisdictions, treaties, standards, laws, policies, programs, datasets, instruments, technologies, species, materials, and events.
- `sc_control_vocab` — vocabulary prefix, URI, version, license, language, and authority.

## Typed Knowledge Relationships

New hidden relationship record: `sc_knowledge_rel`.

Supported relationships: Related to, Contrasts with, Broader than, Narrower than, Defines, Exemplifies, About topic, Uses concept, Mentions entity, Governed by, Cites, Supports, Derives from, Summarizes, Translates, Supersedes, Precedes, Continues, Contains, Companion to, and Methodology for.

Each relationship stores source and destination node identifiers, type, note, weight, public state, lifecycle status, and creating/updating audit data. Self-relations are rejected and duplicate outgoing editor rows collapse.

## Document relationships

Documents can now form sequences, continuations, translations, summaries, companion sets, containment structures, supersession links, and methodology relationships. This creates the structured foundation for document families and future reading pathways.

## Semantic editor and public discovery

Supported records receive Topic, Concept, Entity, and outgoing-relationship editors. Public document, Source, and Claim pages can display semantic assignments and typed incoming/outgoing relationships. Concepts, Entities, and vocabularies receive public templates.

New workspace: `SC Library → Topics and Relationships`.

Shortcodes:

```text
[sc_knowledge_relationship_browser]
[sc_knowledge_relationship_browser kind="document" id="123"]
[sc_topic_coverage]
[sc_topic_coverage project="123"]
[sc_knowledge_concept id="123"]
```

## Coverage and gap analysis

Library reports measure Topic representation across documents, Sources, Claims, and Projects. Concept reports identify Concepts without Claims or an evidence base. Project reports identify records without Topics or Concepts.

Public library coverage is cached for ten minutes and invalidated on semantic edits, Topic assignments, relationship changes, publication-state changes, deletions, and migration batches.

## Non-destructive migration

Migration stages:

1. existing `sc_source_topic` terms;
2. Research Source assignments;
3. Foundation Document post tags;
4. complete.

The migration copies data into the canonical Topic taxonomy without deleting or unregistering the retained systems. It runs in resumable 25-record batches with stable post-ID cursors, locks, failure history, cron continuation, REST, AJAX, and WP-CLI.

## REST and WP-CLI

REST routes cover nodes, relationships, Topics, Concepts, Entities, library/project coverage, and migration.

WP-CLI:

```text
wp sc-library knowledge migrate-topics
wp sc-library knowledge node KIND ID
wp sc-library knowledge coverage
wp sc-library knowledge relate FROM_KIND FROM_ID RELATION TO_KIND TO_ID
```

## Compatibility

v3.2.0 retains v3.1.0 Source Integrity, v3.0.1 Production Validation, v3.0.0 Connected Research Projects, v2.7.0 Evidence and Claims, v2.6.x connectors and holdings, v2.5.x citations, v2.4.x OCR, v2.3.x repository routes, and v2.2.x PDF systems.
