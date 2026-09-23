# Publication Corpus Integration — v5.17.1

The scientific knowledge landscape is a live analytical view over Library records rather than a manually provisioned visualization object.

## Corpus mode

The default shortcode:

    [sc_library_knowledge_landscape]

requests the live WordPress publication corpus from the Library backend. The default backend source boundary is `wordpress-main`, which is the source key used by the WordPress ingestion packet for published Library content.

Optional controls:

    [sc_library_knowledge_landscape source_key="wordpress-main" max_publications="250" max_topics="36"]

To analyze one publication:

    [sc_library_knowledge_landscape scope="publication" record_id="wordpress:1:post:1621"]

## Scientific interpretation

The visualization represents measured or declared relationships. Topic co-occurrence means two topics were associated with the same publication or reviewed source span. Semantic similarity means cosine similarity between current stored vectors produced by the same embedding provider/model/dimension contract. Neither relationship is a causal or truth claim.

## Future 4D path

The corpus contract is intentionally portable to Workspace and Platform Core. It supplies publication/topic nodes, typed weighted edges, publication dates, source provenance, semantic state, and renderer metadata needed for later spatial knowledge terrain, temporal evolution, linked views and visual query.
