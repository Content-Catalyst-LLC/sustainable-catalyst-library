# Sustainable Catalyst Library v4.3.1 — Full Article Map Registry & Publications Topic Coverage

## Public contract

v4.3.1 expands `[sc_publications]` from the initial Homepage Spotlight subject set to the complete approved Publications hierarchy.

The canonical registry contains **14 major fields and 170 Article Map destinations**. Every registered topic keeps the Publications visual contract:

1. Article Map hero first;
2. up to four companion publications immediately below;
3. no reading-time metadata;
4. no Blog Roll mode.

## Article resolution

v4.3.1 uses a read-only resolver cascade so Publications can cover subjects that are not Homepage Spotlight topics:

1. active Homepage Spotlight curation, when the topic matches a Spotlight subject;
2. the canonical Article Map page's own internal publication order;
3. same-slug Knowledge Pathway steps;
4. same-slug WordPress category content as a final fallback;
5. `sc_library_publications_articles_for_topic` for site-specific extension.

No unrelated filler is invented. If fewer than four companion publications can be resolved, the Article Map hero remains visible and only the available publication links are rendered.

## Registry

Canonical registry data lives in:

`includes/data/publications-article-map-registry-v431.php`

The registry preserves field order, topic order, nested group context, canonical map title, URL, and compatibility aliases for the current Spotlight naming.

Extension filters:

- `sc_library_publications_article_maps`
- `sc_library_publications_registry`
- `sc_library_publications_articles_for_topic`
- `sc_library_publications_topics`

## Design

The v4.3.0 Spotlight-derived visual system is preserved. v4.3.1 adds field and Article Map counts plus restrained nested-group context without increasing article-row density.

## Performance and safety

Resolved Publications topics are cached for ten minutes and invalidated on content saves, deletions, and status transitions.

Homepage Spotlight v4.2.0 remains read-only and unchanged. Publications never mutates Spotlight subjects, cards, schedules, tiers, or rotation state.
