# Knowledge Model Architecture — v3.2.0

## Principles

The semantic layer is additive, inspectable, and WordPress-native. It does not replace collections, Source topics, project Source relationships, Claim-to-Evidence links, Source-integrity relationships, citations, or bibliographies.

## Node model

Schema: `sc-library-knowledge-node/1.0`.

Node kinds: document, source, claim, evidence, project, concept, topic, entity, vocabulary.

Node responses can include kind, ID, label, summary, URL, public state, Topics, Concepts, Entities, and relationships.

## Topic model

Canonical taxonomy: `sc_library_topic`.

Topics are hierarchical and store alternative labels, scope note, URI, vocabulary ID, status, and legacy Source Topic ID.

## Concept model

Post type: `sc_library_concept`. Concepts are records rather than terms so they can carry long-form explanations, revisions, authorship, excerpts, public pages, and typed relationships.

## Named Entity and vocabulary models

`sc_named_entity` represents identifiable things. `sc_control_vocab` represents the authority, URI, version, language, and license governing semantic records.

## Relationship model

Post type: `sc_knowledge_rel`. Schema: `sc-library-knowledge-relation/1.0`.

Canonical direction is stored as `from_kind`, `from_id`, `relationship`, `to_kind`, and `to_id`. Incoming views use inverse labels. Metadata includes note, weight, public state, status, and audit fields.

Weights indicate editorial importance, not truth or confidence.

## Assignment storage

Topic assignments use WordPress term relationships. Concept and Entity assignments use bounded postmeta ID arrays. Typed cross-record relationships use hidden relationship posts.

## Privacy and deletion

Public responses require public nodes and active public relationships. Private data requires edit permission. Deleting a node or Topic removes relationship records pointing to it, but does not delete other knowledge records.

## Future compatibility

The schemas support later JSON-LD, graph exports, pathway generation, Research Librarian retrieval, external vocabulary alignment, and dedicated graph storage.
