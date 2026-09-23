# Publication Corpus Scope Repair — v5.17.1.1

The Research Library can index many kinds of WordPress content, but a publication knowledge landscape should not treat all indexed content as publications.

The canonical corpus boundary is now the set of public source records currently resolved by the Sustainable Catalyst Publications interface. WordPress produces this manifest from the same field/topic/article-map publication resolution used for the visible Publications experience. The Python backend then constrains corpus analysis to those record IDs.

A direct backend request without the WordPress manifest remains useful for diagnostics and programmatic access, but its fallback is intentionally limited to public/published WordPress `post` records.

This separation preserves the broader Knowledge Library index while making the publication visualization semantically precise.
