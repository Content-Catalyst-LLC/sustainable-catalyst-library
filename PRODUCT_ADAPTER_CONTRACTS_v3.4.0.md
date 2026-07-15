# Product Adapter Contracts — v3.4.0

## Shared envelope

Every adapter receives:

```text
schema
request_type
question
instructions
parameters
node_keys
```

The shared bundle also provides the stable project identity, selected bundle sections, provenance, and return information.

## Research Lab

Contract:

```text
sc-platform-handoff/research-lab/1.0
```

Adapter object:

```text
experiment_context
```

Fields can include objectives, methods, scope, datasets, and project evidence.

## Workbench

Contract:

```text
sc-platform-handoff/workbench/1.0
```

Adapter object:

```text
calculation_context
```

Fields can include assumptions, units, methods, datasets, and requested output.

## Decision Studio

Contract:

```text
sc-platform-handoff/decision-studio/1.0
```

Adapter object:

```text
decision_context
```

Fields can include decision question, criteria, assumptions, scenarios, evidence packet, and Source-integrity review.

## Research Librarian

Contract:

```text
sc-platform-handoff/research-librarian/1.0
```

Adapter object:

```text
research_context
```

Fields can include project data, bibliography, semantic coverage, pathways, recommendations, and knowledge gaps.

## Site Intelligence

Contract:

```text
sc-platform-handoff/site-intelligence/1.0
```

Adapter object:

```text
intelligence_context
```

Fields can include dataset references, country codes, geographic scope, temporal scope, indicators, and saved-view URL.

## Extension

A product can add fields through:

```php
add_filter(
    'sc_library_cross_product_handoff_bundle',
    function ( $bundle, $target_product, $handoff_type, $project_id ) {
        return $bundle;
    },
    10,
    4
);
```

Extensions should preserve the envelope schema and avoid adding secrets to bundle content.
