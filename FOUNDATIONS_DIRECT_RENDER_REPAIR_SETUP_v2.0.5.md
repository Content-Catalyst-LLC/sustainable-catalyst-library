# Sustainable Catalyst Foundations v2.0.5

## Direct Documentation Shortcode Replacement

The canonical Foundations page was still rendering the retained JavaScript-only
`[sc_foundations_library]` callback after v2.0.4. That callback requests the
documentation REST endpoint in the browser and displays an error when the
request fails.

v2.0.5 replaces that shortcode callback at WordPress `init` priority 999.

On `/institution/foundations/`, the shortcode now:

- reads the retained `foundations` Library Collection directly from WordPress;
- supports all post types registered to the Library Collection taxonomy;
- includes older pages, PDFs, product briefs, policies, and native Foundation Documents;
- renders published records without the REST API or Library search index;
- preserves search, type, status, archive, record, authority, and PDF controls.

On other pages, the retained Knowledge Library documentation interface remains
available.

No Foundations page content needs to be edited.
