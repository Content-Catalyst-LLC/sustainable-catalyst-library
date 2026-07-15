# Research Librarian Pathway Handoff — v3.3.0

## Filter

```text
sc_library_research_librarian_pathway_recommendations
```

Arguments:

```text
existing recommendations
context array
limit
```

## Context

Supported context fields:

```text
query
topic_ids
concept_ids
entity_ids
node_keys
level
audience
```

Node keys use:

```text
kind:id
```

Example:

```text
document:123
source:456
claim:789
```

## Recommendation output

Each appended recommendation contains:

```text
type = knowledge-pathway
id
title
url
summary
level
steps
score
reasons
schema
```

## Ranking

The score can incorporate:

- matching pathway record;
- shared Topics;
- shared Concepts;
- shared Entities;
- matching level;
- query and audience term matches.

## Reliability boundary

A pathway recommendation is navigation, not an evidentiary answer.

The Research Librarian should continue to:

- ground factual responses in retrieved records;
- cite Sources and documents;
- distinguish curated routes from direct evidence;
- enforce public/private boundaries;
- avoid treating pathway order as proof of importance or correctness.
