# Sustainable Catalyst Knowledge Library v3.3.0

## Knowledge Pathways and Article Maps

v3.3.0 turns the Knowledge Library's semantic records into curated routes through documents, Research Sources, Claims, Evidence Notes, Concepts, Topics, Entities, Projects, and other pathways.

The release is additive. Existing documents, citations, projects, Topics, Concepts, Source integrity records, Claims, and Evidence Notes remain unchanged.

## Knowledge Pathways

New public post type:

```text
sc_knowledge_path
```

A pathway can store:

- introductory through expert level;
- intended audience;
- estimated completion time;
- learning or research outcomes;
- ordered steps;
- required and optional steps;
- stage, difficulty, time estimate, purpose note, and display label per step;
- prerequisite pathways;
- continuation pathways;
- canonical Knowledge Topics;
- core Concepts and Named Entities;
- pathway type;
- map mode;
- Research Librarian recommendation terms;
- optional originating Research Project.

## Pathway types

```text
Orientation
Learning Path
Research Path
Document Series
Methodology Guide
Evidence Trail
Project-Derived Path
```

## Ordered stages

```text
Orientation
Foundation
Core Reading
Sources and Evidence
Application
Analysis and Critique
Synthesis
Further Exploration
```

Steps may point to:

```text
Knowledge Library document
Research Source
Research Claim
Evidence Note
Research Project
Concept
Knowledge Topic
Named Entity
Controlled Vocabulary
Knowledge Pathway
```

Duplicate node steps and self-referential pathway steps are rejected.

## Prerequisites and continuations

Pathways can explicitly identify:

- recommended prerequisite pathways;
- later continuation pathways;
- required and optional records within the current sequence.

The public page presents both directions without changing the underlying document or Source records.

## Article maps

Every pathway can generate an accessible visual map.

Map modes:

```text
Ordered sequence
Stage map
Semantic network
Compact article map
```

The map includes:

- ordered sequence edges;
- semantic edges already recorded by v3.2.0 between pathway nodes;
- stage-based layout;
- record-type and stage labels;
- links to public records;
- SVG title and description;
- keyboard-scrollable viewport;
- complete text-list fallback.

Map schema:

```text
sc-library-article-map/1.0
```

## Project-derived pathways

From:

```text
SC Library → Pathways and Maps
```

an editor can create a draft pathway from a Research Project.

The draft can include:

```text
Project brief
Connected Knowledge Library documents
Included or candidate Research Sources
Research Claims
Evidence Notes
Project objectives
Project Knowledge Topics
```

Project-derived pathways are never published automatically.

## Record-to-pathway navigation

Public document, Research Source, and Research Claim pages can show pathways containing that record.

This provides contextual navigation from an individual record into:

- foundation reading;
- broader research sequences;
- evidence trails;
- methodology guides;
- advanced continuation routes.

## Research Librarian recommendations

New filter:

```text
sc_library_research_librarian_pathway_recommendations
```

Pathway recommendations can be scored by:

- shared Knowledge Topics;
- shared Concepts;
- shared Named Entities;
- matching records;
- pathway level;
- audience;
- research-question and recommendation-term matches.

Recommendation schema:

```text
sc-library-pathway-recommendations/1.0
```

The integration supplies structured pathway recommendations. It does not replace the Research Librarian's grounding, retrieval, or citation requirements.

## Public shortcodes

```text
[sc_knowledge_pathway id="123"]
[sc_knowledge_pathway slug="climate-foundations"]
[sc_article_map pathway="123"]
[sc_pathway_recommendations query="climate governance"]
```

Private previews require explicit edit permission and `include_private="true"`.

## REST API

```text
GET      /wp-json/sc-library/v1/knowledge/pathways
GET/POST /wp-json/sc-library/v1/knowledge/pathways/{id}
GET      /wp-json/sc-library/v1/knowledge/pathways/{id}/map
POST     /wp-json/sc-library/v1/knowledge/pathways/recommendations
GET      /wp-json/sc-library/v1/knowledge/nodes/{kind}/{id}/pathways
POST     /wp-json/sc-library/v1/projects/{id}/pathway
```

Private and authenticated pathway responses receive no-store cache headers.

## WP-CLI

```text
wp sc-library pathways list
wp sc-library pathways map PATHWAY_ID
wp sc-library pathways derive PROJECT_ID
wp sc-library pathways derive PROJECT_ID --force-new
wp sc-library pathways recommend climate governance
```

## Compatibility

v3.3.0 preserves:

- v3.2.0 Topics, Concepts, Entities, vocabularies, relationships, and coverage;
- v3.1.0 Source versioning and research integrity;
- v3.0.1 production validation and migration reliability;
- v3.0.0 connected research projects and bibliographies;
- v2.7.0 quotations, Evidence Notes, and Claims;
- v2.6.x scholarly connectors and holdings;
- v2.5.x Research Sources and citations;
- v2.4.x OCR;
- v2.3.x public document repository;
- v2.2.x PDF conversion and bulk import.
